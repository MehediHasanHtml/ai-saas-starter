<?php

namespace App\AI\Data;

/**
 * Provider-neutral chat request. Each provider maps this onto its own API
 * shape (OpenAI messages, Anthropic system + messages, Gemini contents...).
 */
final class ChatRequest
{
    /**
     * @param  list<ChatMessage>  $messages
     */
    public function __construct(
        public readonly string $model,
        public readonly array $messages,
        public readonly ?string $system = null,
        public readonly float $temperature = 0.3,
        public readonly int $maxTokens = 1024,
    ) {}

    /** OpenAI-style messages with the system prompt first. */
    public function toOpenAiMessages(): array
    {
        $messages = [];

        if ($this->system) {
            $messages[] = ['role' => 'system', 'content' => $this->system];
        }

        foreach ($this->messages as $message) {
            $messages[] = ['role' => $message->role, 'content' => $message->content];
        }

        return $messages;
    }

    public function lastUserMessage(): ?string
    {
        for ($i = count($this->messages) - 1; $i >= 0; $i--) {
            if ($this->messages[$i]->role === 'user') {
                return $this->messages[$i]->content;
            }
        }

        return null;
    }
}
