<?php

namespace Tests\Feature;

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * End-to-end RAG with the fake provider: upload → chunk → embed (pgvector)
 * → semantic search → grounded chat with citations (JSON and SSE).
 */
class RagChatTest extends TestCase
{
    use RefreshDatabase;

    private function uploadHandbook($org): Document
    {
        Storage::fake('local');

        $content = "Employee Handbook\n\n"
            .str_repeat("Our office is open Monday to Friday. Lunch is served at noon in the cafeteria. ", 15)
            ."\n\nRefund policy: customers can request a refund within 30 days of purchase. Refunds are paid to the original payment method within five business days.\n\n"
            .str_repeat('Parking is available in the basement garage for all staff members. ', 15);

        $id = $this->post('/api/documents', [
            'file' => UploadedFile::fake()->createWithContent('handbook.txt', $content),
        ], $this->tenantHeaders($org))->assertAccepted()->json('data.id');

        return Document::withoutTenancy()->findOrFail($id);
    }

    public function test_uploaded_document_is_chunked_and_embedded(): void
    {
        [, $org] = $this->signIn();

        $document = $this->uploadHandbook($org);

        $this->assertSame('ready', $document->status->value);
        $this->assertGreaterThan(1, $document->chunk_count);
        $this->assertDatabaseHas('document_chunks', ['document_id' => $document->id, 'organization_id' => $org->id]);
        $this->assertDatabaseHas('usage_records', ['organization_id' => $org->id, 'type' => 'embedding']);
    }

    public function test_semantic_search_finds_the_relevant_chunk(): void
    {
        [, $org] = $this->signIn();
        $this->uploadHandbook($org);

        $top = $this->postJson('/api/search', ['query' => 'How long do refunds take?'], $this->tenantHeaders($org))
            ->assertOk()
            ->json('data.0');

        $this->assertStringContainsString('refund', strtolower($top['content']));
    }

    public function test_chat_answers_with_citations_and_records_usage(): void
    {
        [, $org] = $this->signIn();
        $document = $this->uploadHandbook($org);

        $conversationId = $this->postJson('/api/conversations', ['provider' => 'fake'], $this->tenantHeaders($org))
            ->assertCreated()
            ->json('data.id');

        $response = $this->postJson("/api/conversations/{$conversationId}/messages", [
            'content' => 'What is the refund policy?',
            'stream' => false,
        ], $this->tenantHeaders($org))->assertOk();

        $response->assertJsonPath('message.role', 'assistant')
            ->assertJsonPath('message.citations.0.document_id', $document->id);
        $this->assertStringContainsString('[1]', $response->json('message.content'));

        $this->getJson("/api/conversations/{$conversationId}", $this->tenantHeaders($org))
            ->assertOk()
            ->assertJsonPath('data.title', 'What is the refund policy?')
            ->assertJsonCount(2, 'data.messages');

        $this->getJson('/api/usage', $this->tenantHeaders($org))
            ->assertOk()
            ->assertJsonPath('usage.messages.used', 1);
    }

    public function test_chat_streams_server_sent_events(): void
    {
        [, $org] = $this->signIn();
        $conversationId = $this->postJson('/api/conversations', [], $this->tenantHeaders($org))->json('data.id');

        $response = $this->post("/api/conversations/{$conversationId}/messages", ['content' => 'Hello there'], $this->tenantHeaders($org));

        $response->assertOk();
        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));

        $body = $response->streamedContent();
        $this->assertStringContainsString("event: delta\n", $body);
        $this->assertStringContainsString("event: done\n", $body);
    }

    public function test_free_plan_message_quota_is_enforced(): void
    {
        config(['plans.plans.free.limits.messages_per_month' => 1]);
        [, $org] = $this->signIn();
        $conversationId = $this->postJson('/api/conversations', [], $this->tenantHeaders($org))->json('data.id');

        $send = fn () => $this->postJson("/api/conversations/{$conversationId}/messages", [
            'content' => 'Hi', 'stream' => false,
        ], $this->tenantHeaders($org));

        $send()->assertOk();
        $send()->assertStatus(402)->assertJsonPath('code', 'quota_exceeded');
    }

    public function test_plan_restricts_providers(): void
    {
        [, $org] = $this->signIn(); // free: no anthropic

        $this->postJson('/api/conversations', ['provider' => 'anthropic'], $this->tenantHeaders($org))
            ->assertStatus(402)
            ->assertJsonPath('limit', 'providers');
    }
}
