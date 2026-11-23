<?php

namespace App\Jobs;

use App\AI\AiManager;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Rag\Chunker;
use App\Rag\TextExtractor;
use App\Rag\VectorStore;
use App\Services\UsageRecorder;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Ingestion pipeline: extract → chunk → embed (batched) → store vectors.
 */
class ProcessDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(public readonly int $documentId) {}

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(
        AiManager $ai,
        TextExtractor $extractor,
        VectorStore $store,
        UsageRecorder $usage,
        TenantContext $tenant,
    ): void {
        $document = Document::withoutTenancy()->with('organization')->find($this->documentId);

        if (! $document) {
            return; // deleted before processing
        }

        $tenant->runAs($document->organization, function () use ($document, $ai, $extractor, $store, $usage) {
            $document->update(['status' => DocumentStatus::Processing, 'error' => null]);

            $config = config('ai.embeddings');
            $chunker = new Chunker(config('ai.rag.chunk_size', 1200), config('ai.rag.chunk_overlap', 200));

            // 1. Extract
            $path = Storage::disk($document->disk)->path($document->path);
            $pages = $extractor->extract($path, $document->extension());

            // 2. Chunk (per page so citations keep page numbers)
            $chunks = [];
            foreach ($pages as $page) {
                foreach ($chunker->split($page['text']) as $text) {
                    $chunks[] = [
                        'content' => $text,
                        'token_count' => (int) ceil(mb_strlen($text) / 4),
                        'metadata' => array_filter(['page' => $page['page']]),
                    ];
                }
            }

            if ($chunks === []) {
                throw new \RuntimeException('The document contains no readable text.');
            }

            // 3. Embed in batches
            $embedder = $ai->embedder();
            foreach (array_chunk(array_keys($chunks), max(1, $config['batch_size'])) as $keys) {
                $vectors = $embedder->embed(
                    array_map(fn ($k) => $chunks[$k]['content'], $keys),
                    $config['model'],
                    $config['dimensions'],
                );

                foreach ($keys as $i => $k) {
                    $chunks[$k]['embedding'] = $vectors[$i]
                        ?? throw new \RuntimeException('Embedding provider returned fewer vectors than inputs.');
                }
            }

            // 4. Store
            $store->replaceDocumentChunks($document->organization_id, $document->id, $chunks);

            $tokens = array_sum(array_column($chunks, 'token_count'));

            $usage->record(
                organization: $document->organization,
                type: 'embedding',
                provider: $config['provider'],
                model: $config['model'],
                inputTokens: $tokens,
                quantity: count($chunks),
                userId: $document->user_id,
            );

            $document->update([
                'status' => DocumentStatus::Ready,
                'chunk_count' => count($chunks),
                'token_count' => $tokens,
                'embedding_model' => $config['provider'].':'.$config['model'],
                'processed_at' => now(),
            ]);
        });
    }

    public function failed(Throwable $e): void
    {
        Log::warning('Document processing failed', ['document' => $this->documentId, 'error' => $e->getMessage()]);

        Document::withoutTenancy()->whereKey($this->documentId)->update([
            'status' => DocumentStatus::Failed->value,
            'error' => mb_strimwidth($e->getMessage(), 0, 500, '…'),
        ]);
    }
}
