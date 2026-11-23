<?php

namespace App\AI\Providers;

use App\AI\Contracts\EmbeddingProvider;
use App\AI\Data\ChatRequest;
use App\AI\Data\ChatResult;
use App\AI\Data\StreamEvent;
use App\AI\Support\StreamReader;
use Generator;
use Illuminate\Http\Client\PendingRequest;

/**
 * Google Gemini (Generative Language API). Roles are "user" and "model".
 */
class GeminiProvider extends BaseProvider implements EmbeddingProvider
{
    public function name(): string
    {
        return 'gemini';
    }

    protected function http(): PendingRequest
    {
        return parent::http()->withHeaders(['x-goog-api-key' => $this->config['api_key']]);
    }

    public function chat(ChatRequest $request): ChatResult
    {
        $json = $this->ensureOk(
            $this->http()->post("/models/{$request->model}:generateContent", $this->payload($request))
        )->json();

        return new ChatResult(
            content: $this->text($json),
            model: $request->model,
            inputTokens: (int) data_get($json, 'usageMetadata.promptTokenCount', 0),
            outputTokens: (int) data_get($json, 'usageMetadata.candidatesTokenCount', 0),
            finishReason: data_get($json, 'candidates.0.finishReason'),
        );
    }

    public function stream(ChatRequest $request): Generator
    {
        $response = $this->ensureOk(
            $this->streaming()->post("/models/{$request->model}:streamGenerateContent?alt=sse", $this->payload($request))
        );

        $input = 0;
        $output = 0;

        foreach (StreamReader::events($response->toPsrResponse()->getBody()) as $event) {
            $json = json_decode($event['data'], true);
            if (! is_array($json)) {
                continue;
            }

            $text = $this->text($json);
            if ($text !== '') {
                yield StreamEvent::delta($text);
            }

            $input = (int) data_get($json, 'usageMetadata.promptTokenCount', $input);
            $output = (int) data_get($json, 'usageMetadata.candidatesTokenCount', $output);
        }

        yield StreamEvent::usage($input, $output);
    }

    public function embed(array $texts, string $model, int $dimensions): array
    {
        $json = $this->ensureOk($this->http()->post("/models/{$model}:batchEmbedContents", [
            'requests' => array_map(fn (string $text) => [
                'model' => "models/{$model}",
                'content' => ['parts' => [['text' => $text]]],
                'outputDimensionality' => $dimensions,
            ], array_values($texts)),
        ]))->json();

        return array_map(fn ($e) => array_map('floatval', $e['values']), $json['embeddings'] ?? []);
    }

    private function payload(ChatRequest $request): array
    {
        $contents = [];
        foreach ($request->messages as $message) {
            $contents[] = [
                'role' => $message->role === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message->content]],
            ];
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $request->temperature,
                'maxOutputTokens' => $request->maxTokens,
            ],
        ];

        if ($request->system) {
            $payload['systemInstruction'] = ['parts' => [['text' => $request->system]]];
        }

        return $payload;
    }

    private function text(array $json): string
    {
        return collect(data_get($json, 'candidates.0.content.parts', []))->pluck('text')->filter()->implode('');
    }
}
