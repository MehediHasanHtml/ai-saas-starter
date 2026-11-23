<?php

namespace App\Http\Controllers\Api;

use App\AI\AiManager;
use App\Billing\Entitlements;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Conversations are private to the user who created them, inside the
 * current organization (the tenant scope is applied automatically).
 */
class ConversationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $conversations = Conversation::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(30);

        return ConversationResource::collection($conversations);
    }

    public function store(Request $request, AiManager $ai, Entitlements $entitlements): JsonResponse
    {
        $data = $this->validated($request, $ai);
        $organization = $this->tenant();

        $provider = $data['provider'] ?? $organization->setting('default_provider', config('ai.default'));
        $entitlements->ensureProviderAllowed($organization, $provider);

        $model = $data['model']
            ?? $organization->setting('default_model')
            ?? config('ai.default_model')
            ?? ($ai->provider($provider)->models()[0] ?? 'default');

        $conversation = Conversation::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'] ?? 'New chat',
            'provider' => $provider,
            'model' => $model,
            'system_prompt' => $data['system_prompt'] ?? null,
            'use_knowledge_base' => $data['use_knowledge_base'] ?? true,
            'document_ids' => $data['document_ids'] ?? null,
        ]);

        return (new ConversationResource($conversation))->response()->setStatusCode(201);
    }

    public function show(Request $request, Conversation $conversation): ConversationResource
    {
        $this->authorizeOwner($request, $conversation);

        return new ConversationResource($conversation->load('messages'));
    }

    public function update(Request $request, Conversation $conversation, AiManager $ai, Entitlements $entitlements): ConversationResource
    {
        $this->authorizeOwner($request, $conversation);
        $data = $this->validated($request, $ai);

        if (isset($data['provider'])) {
            $entitlements->ensureProviderAllowed($this->tenant(), $data['provider']);
        }

        $conversation->update($data);

        return new ConversationResource($conversation);
    }

    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeOwner($request, $conversation);
        $conversation->delete();

        return response()->json(['message' => 'Conversation deleted.']);
    }

    private function validated(Request $request, AiManager $ai): array
    {
        return $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'provider' => ['sometimes', 'string', Rule::in($ai->available())],
            'model' => ['sometimes', 'string', 'max:100'],
            'system_prompt' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'use_knowledge_base' => ['sometimes', 'boolean'],
            'document_ids' => ['sometimes', 'nullable', 'array'],
            // exists check is tenant-safe: ids from other orgs simply won't match.
            'document_ids.*' => ['integer', Rule::exists('documents', 'id')->where('organization_id', $this->tenant()->id)],
        ]);
    }

    private function authorizeOwner(Request $request, Conversation $conversation): void
    {
        abort_unless((int) $conversation->user_id === (int) $request->user()->id, 404);
    }
}
