<?php

namespace App\AI\Providers;

use App\AI\Data\ChatRequest;
use App\AI\Data\ChatResult;
use App\AI\Data\StreamEvent;
use App\AI\Support\StreamReader;
use Generator;
use Illuminate\Http\Client\PendingRequest;

/**
 * Anthropic Messages API. The system prompt is a top-level field and
 * messages must alternate user/assistant, starting with a user turn.
 */
class AnthropicProvider extends BaseProvider
{
    public function name(): string
    {
        return 'anthropic';
    }

    protected function http(): PendingRequest
    {
        return parent::http()->withHeaders([
            'x-api-key' => $this->config['api_key'],
            'anthropic-version' => $this->config['version'] ?? '2023-06-01',
        ]);
    }

    public function chat(ChatRequest $request): ChatResult
    {
        $json = $this->ensureOk($this->http()->post('/messages', $this->payload($request)))->json();

        $text = collect($json['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        return new ChatResult(
            content: $text,
            model: (string) ($json['model'] ?? $request->model),
            inputTokens: (int) data_get($json, 'usage.input_tokens', 0),
            outputTokens: (int) data_get($json, 'usage.output_tokens', 0),
            finishReason: $json['stop_reason'] ?? null,
        );
    }

    public function stream(ChatRequest $request): Generator
    {
        $response = $this->ensureOk($this->streaming()->post('/messages', $this->payload($request) + ['stream' => true]));

        $input = 0;
        $output = 0;

        foreach (StreamReader::events($response->toPsrResponse()->getBody()) as $event) {
            $json = json_decode($event['data'], true);
            if (! is_array($json)) {
                continue;
            }

            switch ($json['type'] ?? $event['event']) {
                case 'message_start':
                    $input = (int) data_get($json, 'message.usage.input_tokens', 0);
                    break;
                case 'content_block_delta':
                    if (data_get($json, 'delta.type') === 'text_delta') {
                        yield StreamEvent::delta((string) data_get($json, 'delta.text', ''));
                    }
                    break;
                case 'message_delta':
                    $output = (int) data_get($json, 'usage.output_tokens', $output);
                    break;
                case 'error':
                    throw new \App\AI\Support\AiProviderException('anthropic stream error: '.data_get($json, 'error.message', 'unknown'));
                case 'message_stop':
                    break 2;
            }
        }

        yield StreamEvent::usage($input, $output);
    }

    private function payload(ChatRequest $request): array
    {
        $messages = [];

        foreach ($request->messages as $message) {
            // Merge consecutive same-role turns; Anthropic requires alternation.
            $last = array_key_last($messages);
            if ($last !== null && $messages[$last]['role'] === $message->role) {
                $messages[$last]['content'] .= "\n\n".$message->content;
            } else {
                $messages[] = ['role' => $message->role, 'content' => $message->content];
            }
        }

        if ($messages !== [] && $messages[0]['role'] !== 'user') {
            array_shift($messages);
        }

        return array_filter([
            'model' => $request->model,
            'system' => $request->system,
            'messages' => $messages,
            'max_tokens' => $request->maxTokens,
            'temperature' => $request->temperature,
        ], fn ($value) => $value !== null);
    }
}
