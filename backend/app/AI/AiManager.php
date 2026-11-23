<?php

namespace App\AI;

use App\AI\Contracts\ChatProvider;
use App\AI\Contracts\EmbeddingProvider;
use App\AI\Providers\AnthropicProvider;
use App\AI\Providers\FakeProvider;
use App\AI\Providers\GeminiProvider;
use App\AI\Providers\OllamaProvider;
use App\AI\Providers\OpenAiProvider;
use App\Billing\Plan;
use Illuminate\Support\Manager;
use InvalidArgumentException;

/**
 * Resolves AI providers by key:
 *
 *   app(AiManager::class)->provider('anthropic')->stream($request);
 *   app(AiManager::class)->embedder()->embed([...], $model, $dims);
 *
 * Register a custom provider at runtime with ->extend('mistral', fn () => ...).
 *
 * @method ChatProvider driver(?string $driver = null)
 */
class AiManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('ai.default', 'fake');
    }

    public function provider(?string $name = null): ChatProvider
    {
        return $this->driver($name);
    }

    public function embedder(): EmbeddingProvider
    {
        $provider = $this->driver($this->config->get('ai.embeddings.provider', 'fake'));

        if (! $provider instanceof EmbeddingProvider) {
            throw new InvalidArgumentException("Provider [{$provider->name()}] does not support embeddings.");
        }

        return $provider;
    }

    /** All provider keys defined in config/ai.php. */
    public function available(): array
    {
        return array_keys($this->config->get('ai.providers', []));
    }

    public function has(string $name): bool
    {
        return in_array($name, $this->available(), true) || isset($this->customCreators[$name]);
    }

    /**
     * Catalogue for the model picker, filtered by plan.
     *
     * @return list<array{provider: string, label: string, configured: bool, allowed: bool, models: list<string>}>
     */
    public function catalogue(?Plan $plan = null): array
    {
        $out = [];

        foreach ($this->available() as $key) {
            $provider = $this->provider($key);
            $out[] = [
                'provider' => $key,
                'label' => $this->config->get("ai.providers.{$key}.label", ucfirst($key)),
                'configured' => $provider->isConfigured(),
                'allowed' => $plan ? $plan->allowsProvider($key) : true,
                'models' => $provider->models(),
            ];
        }

        return $out;
    }

    protected function createOpenaiDriver(): ChatProvider
    {
        return new OpenAiProvider($this->providerConfig('openai'));
    }

    protected function createAnthropicDriver(): ChatProvider
    {
        return new AnthropicProvider($this->providerConfig('anthropic'));
    }

    protected function createGeminiDriver(): ChatProvider
    {
        return new GeminiProvider($this->providerConfig('gemini'));
    }

    protected function createOllamaDriver(): ChatProvider
    {
        return new OllamaProvider($this->providerConfig('ollama'));
    }

    protected function createFakeDriver(): ChatProvider
    {
        return new FakeProvider($this->providerConfig('fake'));
    }

    private function providerConfig(string $name): array
    {
        return $this->config->get("ai.providers.{$name}", []);
    }
}
