<?php

namespace App\Rag;

use App\AI\AiManager;
use App\Models\Organization;
use App\Services\UsageRecorder;

class Retriever
{
    public function __construct(
        private readonly AiManager $ai,
        private readonly VectorStore $store,
        private readonly UsageRecorder $usage,
    ) {}

    /**
     * Embed the query and return the most similar chunks above min_score.
     *
     * @param  list<int>|null  $documentIds
     */
    public function retrieve(Organization $organization, string $query, ?array $documentIds = null, ?int $limit = null): array
    {
        $config = config('ai.embeddings');

        $vector = $this->ai->embedder()->embed([$query], $config['model'], $config['dimensions'])[0] ?? null;

        if (! $vector) {
            return [];
        }

        $this->usage->record(
            organization: $organization,
            type: 'embedding',
            provider: $config['provider'],
            model: $config['model'],
            inputTokens: (int) ceil(mb_strlen($query) / 4),
        );

        $results = $this->store->search(
            $organization->id,
            $vector,
            $limit ?? config('ai.rag.top_k', 6),
            $documentIds ?: null,
        );

        // Hashed bag-of-words (fake) similarities are much lower than real
        // embedding similarities, so the demo provider uses a looser cut-off.
        $minScore = $config['provider'] === 'fake' ? 0.05 : (float) config('ai.rag.min_score', 0.2);

        return array_values(array_filter($results, fn ($r) => $r['score'] >= $minScore));
    }
}
