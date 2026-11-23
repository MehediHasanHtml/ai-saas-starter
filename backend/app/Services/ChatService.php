<?php

namespace App\Services;

use App\AI\AiManager;
use App\AI\Contracts\ChatProvider;
use App\AI\Data\ChatMessage;
use App\AI\Data\ChatRequest;
use App\AI\Data\ChatResult;
use App\Billing\Entitlements;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Rag\PromptBuilder;
use App\Rag\Retriever;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Orchestrates one chat turn:
 *   quota check → save user message → retrieve context → build provider
 *   request → (stream / call provider) → save assistant message + usage.
 */
class ChatService
{
    public function __construct(
        private readonly AiManager $ai,
        private readonly Retriever $retriever,
        private readonly PromptBuilder $prompts,
        private readonly Entitlements $entitlements,
        private readonly UsageRecorder $usage,
    ) {}

    /**
     * Resolve and authorise a provider/model pair for the conversation's org.
     */
    public function resolveProvider(Conversation $conversation): ChatProvider
    {
        $organization = $conversation->organization;

        if (! $this->ai->has($conversation->provider)) {
            throw new InvalidArgumentException("Unknown AI provider [{$conversation->provider}].");
        }

        $this->entitlements->ensureProviderAllowed($organization, $conversation->provider);

        return $this->ai->provider($conversation->provider);
    }

    /**
     * @return array{request: ChatRequest, citations: array, user_message: Message, provider: ChatProvider}
     */
    public function prepare(Conversation $conversation, User $user, string $content): array
    {
        $organization = $conversation->organization;

        $this->entitlements->ensureCanSendMessage($organization);
        $provider = $this->resolveProvider($conversation);

        $userMessage = $conversation->messages()->create([
            'organization_id' => $organization->id,
            'role' => 'user',
            'content' => $content,
        ]);

        // Auto-title new conversations from the first question.
        if ($conversation->title === 'New chat') {
            $conversation->title = Str::limit(preg_replace('/\s+/', ' ', $content), 60);
        }
        $conversation->last_message_at = now();
        $conversation->save();

        $sources = $conversation->use_knowledge_base
            ? $this->retriever->retrieve($organization, $content, $conversation->document_ids)
            : [];

        $history = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->reorder('id', 'desc') // relation default is ascending
            ->limit(config('ai.rag.history_messages', 12))
            ->get()
            ->reverse()
            ->map(fn (Message $m) => new ChatMessage($m->role, $m->content))
            ->values()
            ->all();

        $system = $this->prompts->system(
            $conversation->system_prompt ?: $organization->setting('system_prompt'),
            $sources,
        );

        return [
            'request' => new ChatRequest(model: $conversation->model, messages: $history, system: $system),
            'citations' => $this->prompts->citations($sources),
            'user_message' => $userMessage,
            'provider' => $provider,
        ];
    }

    public function complete(
        Conversation $conversation,
        User $user,
        ChatProvider $provider,
        ChatResult $result,
        array $citations,
        int $latencyMs,
    ): Message {
        // Only keep citations the model actually referenced, falling back to
        // all retrieved sources if it didn't use bracket citations.
        preg_match_all('/\[(\d+)\]/', $result->content, $m);
        $used = array_map('intval', $m[1]);
        $kept = array_values(array_filter($citations, fn ($c) => in_array($c['index'], $used, true)));

        $message = $conversation->messages()->create([
            'organization_id' => $conversation->organization_id,
            'role' => 'assistant',
            'content' => $result->content,
            'citations' => $kept ?: $citations,
            'provider' => $provider->name(),
            'model' => $result->model,
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
            'latency_ms' => $latencyMs,
        ]);

        $this->usage->record(
            organization: $conversation->organization,
            type: 'chat',
            provider: $provider->name(),
            model: $result->model,
            inputTokens: $result->inputTokens,
            outputTokens: $result->outputTokens,
            userId: $user->id,
        );

        $conversation->forceFill(['last_message_at' => now()])->save();

        return $message;
    }
}
