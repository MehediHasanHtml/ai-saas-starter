<?php

namespace App\AI\Support;

use Illuminate\Http\Client\Response;
use RuntimeException;

class AiProviderException extends RuntimeException
{
    public static function fromResponse(string $provider, Response $response): self
    {
        $json = $response->json();
        $detail = data_get($json, 'error.message')
            ?? data_get($json, 'error')
            ?? data_get($json, 'message')
            ?? substr($response->body(), 0, 300);

        if (is_array($detail)) {
            $detail = json_encode($detail);
        }

        return new self("{$provider} API error ({$response->status()}): {$detail}", $response->status());
    }

    public static function notConfigured(string $provider): self
    {
        return new self("The {$provider} provider is not configured. Add its API key to the API's .env file.");
    }
}
