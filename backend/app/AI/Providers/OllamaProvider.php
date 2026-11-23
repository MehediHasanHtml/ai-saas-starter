<?php

namespace App\AI\Providers;

use App\AI\Contracts\EmbeddingProvider;
use App\AI\Data\ChatRequest;
use App\AI\Data\ChatResult;
use App\AI\Data\StreamEvent;
use App\AI\Support\AiProviderException;
use App\AI\Support\StreamReader;
use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Self-hosted models through Ollama's native API (no API key; NDJSON stream).
 */
class OllamaProvider extends BaseProvider implements EmbeddingProvider
{
    public function name(): string
    {
        return 'ollama';
    }

    public function isConfigured(): bool
    {
        return filled($this->config['base_url'] ?? null);
    }

    protected function http(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw AiProviderException::notConfigured($this->name());
        }

        return Http::baseUrl(rtrim($this->config['base_url'], '/'))
            ->asJson()
            ->timeout(config('ai.request_timeout', 120) * 2) // local models can be slow
            ->connectTimeout(5);
    }

    public function chat(ChatRequest $request): ChatResult
    {
        $json = $this->ensureOk($this->post($this->http(), '/api/chat', $this->payload($request) + ['stream' => false]))->json();

        return new ChatResult(
            content: (string) data_get($json, 'message.content', ''),
            model: (string) ($json['model'] ?? $request->model),
            inputTokens: (int) ($json['prompt_eval_count'] ?? 0),
            outputTokens: (int) ($json['eval_count'] ?? 0),
            finishReason: $json['done_reason'] ?? null,
        );
    }

    public function stream(ChatRequest $request): Generator
    {
        $response = $this->ensureOk($this->post($this->streaming(), '/api/chat', $this->payload($request) + ['stream' => true]));

        foreach (StreamReader::lines($response->toPsrResponse()->getBody()) as $line) {
            $json = json_decode($line, true);
            if (! is_array($json)) {
                continue;
            }

            if (isset($json['error'])) {
                throw new AiProviderException('ollama: '.$json['error']);
            }

            $text = data_get($json, 'message.content', '');
            if ($text !== '') {
                yield StreamEvent::delta($text);
            }

            if (! empty($json['done'])) {
                yield StreamEvent::usage((int) ($json['prompt_eval_count'] ?? 0), (int) ($json['eval_count'] ?? 0));
                break;
            }
        }
    }

    public function embed(array $texts, string $model, int $dimensions): array
    {
        $json = $this->ensureOk($this->post($this->http(), '/api/embed', [
            'model' => $model,
            'input' => array_values($texts),
            'dimensions' => $dimensions, // honoured by models that support truncation
        ]))->json();

        return array_map(fn ($v) => array_map('floatval', $v), $json['embeddings'] ?? []);
    }

    /**
     * Ollama runs as a separate local server, so "not running" is the usual
     * failure. Turn the raw cURL error into something a user can act on.
     */
    private function post(PendingRequest $http, string $path, array $body): Response
    {
        try {
            return $http->post($path, $body);
        } catch (ConnectionException) {
            throw new AiProviderException(
                "Can't reach Ollama at {$this->config['base_url']}. Start Ollama on that machine "
                ."(then run `ollama pull {$body['model']}`), or pick a different model."
            );
        }
    }

    private function payload(ChatRequest $request): array
    {
        return [
            'model' => $request->model,
            'messages' => $request->toOpenAiMessages(),
            'options' => [
                'temperature' => $request->temperature,
                'num_predict' => $request->maxTokens,
            ],
        ];
    }
}
