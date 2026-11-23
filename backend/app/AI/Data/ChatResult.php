<?php

namespace App\AI\Data;

final class ChatResult
{
    public function __construct(
        public readonly string $content,
        public readonly string $model,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly ?string $finishReason = null,
    ) {}
}
