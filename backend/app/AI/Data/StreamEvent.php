<?php

namespace App\AI\Data;

/**
 * Yielded by ChatProvider::stream(): many "delta" events with text, then at
 * most one "usage" event with token counts reported by the provider.
 */
final class StreamEvent
{
    private function __construct(
        public readonly string $type,
        public readonly string $text = '',
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
    ) {}

    public static function delta(string $text): self
    {
        return new self('delta', text: $text);
    }

    public static function usage(int $input, int $output): self
    {
        return new self('usage', inputTokens: $input, outputTokens: $output);
    }

    public function isDelta(): bool
    {
        return $this->type === 'delta';
    }
}
