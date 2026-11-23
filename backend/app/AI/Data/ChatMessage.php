<?php

namespace App\AI\Data;

final class ChatMessage
{
    public function __construct(
        public readonly string $role, // 'user' | 'assistant'
        public readonly string $content,
    ) {}

    public static function user(string $content): self
    {
        return new self('user', $content);
    }

    public static function assistant(string $content): self
    {
        return new self('assistant', $content);
    }
}
