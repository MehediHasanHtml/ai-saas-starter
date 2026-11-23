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
 * OpenAI Chat Completions + Embeddings. Also works with any OpenAI-compatible
 * endpoint (Azure OpenAI gateways, Groq, Together, vLLM, LM Studio...) by
 * changing OPENAI_BASE_URL.
 */
class OpenAiProvider extends BaseProvider implements EmbeddingProvider
{
    public function name(): string
    {
        return 'openai';
    }

    protected function http(): PendingRequest
    {
        $request = parent::http()->withToken($this->config['api_key']);

        if (! empty($this->config['organization'])) {
            $request->withHeaders(['OpenAI-Organization' => $this->config['organization']]);
        }

        return $request;
    }

    public function chat(ChatRequest $request): ChatResult
    {
        $json = $this->ensureOk($this->http()->post('/chat/completions', $this->payload($request)))->json();

        return new ChatResult(
            content: (string) data_get($json, 'choices.0.message.content', ''),
            model: (string) data_get($json, 'model', $request->model),
            inputTokens: (int) data_get($json, 'usage.prompt_tokens', 0),
            outputTokens: (int) data_get($json, 'usage.completion_tokens', 0),
            finishReason: data_get($json, 'choices.0.finish_reason'),
        );
    }

    public function stream(ChatRequest $request): Generator
    {
        $response = $this->ensureOk($this->streaming()->post('/chat/completions', $this->payload($request) + [
            'stream' => true,
            'stream_options' => ['include_usage' => true],
        ]));

        foreach (StreamReader::events($response->toPsrResponse()->getBody()) as $event) {
            if ($event['data'] === '[DONE]') {
                break;
            }

            $json = json_decode($event['data'], true);
            if (! is_array($json)) {
                continue;
            }

            $text = data_get($json, 'choices.0.delta.content');
            if (is_string($text) && $text !== '') {
                yield StreamEvent::delta($text);
            }

            if ($usage = data_get($json, 'usage')) {
                yield StreamEvent::usage((int) ($usage['prompt_tokens'] ?? 0), (int) ($usage['completion_tokens'] ?? 0));
            }
        }
    }

    public function embed(array $texts, string $model, int $dimensions): array
    {
        $json = $this->ensureOk($this->http()->post('/embeddings', [
            'model' => $model,
            'input' => array_values($texts),
            'dimensions' => $dimensions, // text-embedding-3-* support shortening
        ]))->json();

        $data = collect($json['data'] ?? [])->sortBy('index')->values();

        return $data->map(fn ($row) => array_map('floatval', $row['embedding']))->all();
    }

    private function payload(ChatRequest $request): array
    {
        return [
            'model' => $request->model,
            'messages' => $request->toOpenAiMessages(),
            'temperature' => $request->temperature,
            'max_completion_tokens' => $request->maxTokens,
        ];
    }
}
