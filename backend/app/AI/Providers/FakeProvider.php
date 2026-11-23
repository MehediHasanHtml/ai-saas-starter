<?php

namespace App\AI\Providers;

use App\AI\Contracts\EmbeddingProvider;
use App\AI\Data\ChatRequest;
use App\AI\Data\ChatResult;
use App\AI\Data\StreamEvent;
use Generator;

/**
 * Zero-config provider for local development, demos and tests.
 *
 *  - Chat: answers from the first retrieved source (so RAG + citations are
 *    visibly working) or echoes the question.
 *  - Embeddings: feature-hashed bag-of-words vectors, L2-normalised. Texts
 *    that share words get high cosine similarity, which is enough for
 *    retrieval to behave sensibly without a model.
 */
class FakeProvider implements \App\AI\Contracts\ChatProvider, EmbeddingProvider
{
    private const STOPWORDS = [
        'the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'any', 'can', 'had', 'her', 'was', 'one',
        'our', 'out', 'has', 'his', 'how', 'its', 'may', 'who', 'did', 'yes', 'she', 'him', 'let', 'this',
        'that', 'with', 'from', 'have', 'what', 'when', 'your', 'about', 'which', 'there', 'their', 'does',
        'into', 'will', 'would', 'should', 'could', 'they', 'them', 'than', 'then', 'also', 'is', 'of',
        'to', 'in', 'on', 'at', 'it', 'be', 'as', 'by', 'or', 'an', 'we', 'do', 'if', 'me', 'my', 'so',
    ];

    public function __construct(private readonly array $config = []) {}

    public function name(): string
    {
        return 'fake';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function models(): array
    {
        return $this->config['models'] ?? ['demo-echo'];
    }

    public function chat(ChatRequest $request): ChatResult
    {
        $answer = $this->answer($request);

        return new ChatResult(
            content: $answer,
            model: $request->model,
            inputTokens: self::estimateTokens(($request->system ?? '').json_encode($request->toOpenAiMessages())),
            outputTokens: self::estimateTokens($answer),
            finishReason: 'stop',
        );
    }

    public function stream(ChatRequest $request): Generator
    {
        $result = $this->chat($request);

        foreach (preg_split('/(?<=\s)/u', $result->content) as $piece) {
            if ($piece !== '') {
                yield StreamEvent::delta($piece);
                if (! app()->runningUnitTests()) {
                    usleep(15_000); // make streaming visible in the UI
                }
            }
        }

        yield StreamEvent::usage($result->inputTokens, $result->outputTokens);
    }

    public function embed(array $texts, string $model, int $dimensions): array
    {
        return array_map(fn (string $text) => self::hashEmbedding($text, $dimensions), array_values($texts));
    }

    /** @return list<float> */
    public static function hashEmbedding(string $text, int $dimensions): array
    {
        $vector = array_fill(0, $dimensions, 0.0);

        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($text), $matches);

        $tokens = [];
        foreach ($matches[0] as $token) {
            if (mb_strlen($token) < 2 || in_array($token, self::STOPWORDS, true)) {
                continue;
            }
            // Crude stemming so "invoices" matches "invoice".
            $tokens[preg_replace('/(ing|es|s)$/u', '', $token) ?: $token] = true;
        }

        // Binary weights: a word counts once per text, so long repetitive
        // chunks don't drown out short relevant ones.
        foreach (array_keys($tokens) as $token) {
            $hash = crc32((string) $token);
            $index = $hash % $dimensions;
            $vector[$index] += ($hash & 1) ? 1.0 : -1.0;
        }

        $norm = sqrt(array_sum(array_map(fn ($v) => $v * $v, $vector)));

        if ($norm == 0.0) {
            $vector[0] = 1.0; // avoid a zero vector (undefined cosine)

            return $vector;
        }

        return array_map(fn ($v) => $v / $norm, $vector);
    }

    public static function estimateTokens(string $text): int
    {
        return (int) max(1, ceil(mb_strlen($text) / 4));
    }

    private function answer(ChatRequest $request): string
    {
        $question = trim((string) $request->lastUserMessage());

        if ($request->system && preg_match('/<source id="(\d+)"[^>]*>\s*(.*?)\s*<\/source>/s', $request->system, $m)) {
            $excerpt = trim(preg_replace('/\s+/', ' ', $m[2]));
            $sentences = preg_split('/(?<=[.!?])\s+/', $excerpt, 3);
            $summary = mb_strimwidth(implode(' ', array_slice($sentences, 0, 2)), 0, 400, '…');

            return "Based on your documents: {$summary} [{$m[1]}]\n\n"
                ."_This is the demo model. Set an OpenAI, Anthropic, Gemini or Ollama provider for real answers._";
        }

        return "You asked: \"{$question}\"\n\nI'm the demo model, so I can only echo questions and quote your "
            .'documents. Upload a document to see retrieval in action, or configure a real provider in the API .env.';
    }
}
