<?php

namespace App\AI\Providers;

use App\AI\Contracts\ChatProvider;
use App\AI\Support\AiProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

abstract class BaseProvider implements ChatProvider
{
    public function __construct(protected readonly array $config) {}

    public function models(): array
    {
        return array_values($this->config['models'] ?? []);
    }

    public function isConfigured(): bool
    {
        return filled($this->config['api_key'] ?? null);
    }

    protected function http(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw AiProviderException::notConfigured($this->name());
        }

        return Http::baseUrl(rtrim($this->config['base_url'], '/'))
            ->acceptJson()
            ->asJson()
            ->timeout(config('ai.request_timeout', 120))
            ->connectTimeout(10)
            ->retry(2, 500, fn ($e) => $e instanceof \Illuminate\Http\Client\ConnectionException, throw: false);
    }

    /** Same request, but the body is not buffered (for SSE / NDJSON). */
    protected function streaming(): PendingRequest
    {
        return $this->http()->withOptions(['stream' => true]);
    }

    protected function ensureOk(Response $response): Response
    {
        if ($response->failed()) {
            throw AiProviderException::fromResponse($this->name(), $response);
        }

        return $response;
    }
}
