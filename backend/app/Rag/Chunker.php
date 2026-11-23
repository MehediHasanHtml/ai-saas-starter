<?php

namespace App\Rag;

/**
 * Recursive character splitter (framework-free, unit-tested).
 *
 * Splits on the coarsest separator that keeps pieces under the target size
 * (paragraphs → lines → sentences → words), then packs pieces into chunks
 * with a character overlap so context isn't lost at chunk boundaries.
 */
final class Chunker
{
    private const SEPARATORS = ["\n\n", "\n", '. ', '? ', '! ', '; ', ', ', ' '];

    public function __construct(
        private readonly int $chunkSize = 1200,
        private readonly int $overlap = 200,
    ) {
        if ($overlap >= $chunkSize) {
            throw new \InvalidArgumentException('Chunk overlap must be smaller than chunk size.');
        }
    }

    /**
     * @return list<string>
     */
    public function split(string $text): array
    {
        $text = $this->normalize($text);

        if ($text === '') {
            return [];
        }

        if (mb_strlen($text) <= $this->chunkSize) {
            return [$text];
        }

        return $this->merge($this->pieces($text, 0));
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Break text into pieces no longer than chunkSize.
     *
     * @return list<string>
     */
    private function pieces(string $text, int $level): array
    {
        if (mb_strlen($text) <= $this->chunkSize) {
            return [$text];
        }

        if ($level >= count(self::SEPARATORS)) {
            // No separator left: hard split.
            return mb_str_split($text, $this->chunkSize);
        }

        $separator = self::SEPARATORS[$level];
        $parts = explode($separator, $text);

        if (count($parts) === 1) {
            return $this->pieces($text, $level + 1);
        }

        $out = [];
        $last = count($parts) - 1;
        foreach ($parts as $i => $part) {
            // Re-attach the separator so concatenating pieces restores the text.
            $piece = $i < $last ? $part.$separator : $part;
            if (trim($piece) === '') {
                continue;
            }
            array_push($out, ...$this->pieces($piece, $level + 1));
        }

        return $out;
    }

    /**
     * Pack pieces into chunks of ~chunkSize with ~overlap characters carried
     * over from the previous chunk.
     *
     * @param  list<string>  $pieces
     * @return list<string>
     */
    private function merge(array $pieces): array
    {
        $chunks = [];
        $current = [];
        $length = 0;

        foreach ($pieces as $piece) {
            $pieceLength = mb_strlen($piece);

            if ($length + $pieceLength > $this->chunkSize && $current !== []) {
                $chunks[] = trim(implode('', $current));

                // Drop pieces from the front until we're within the overlap budget.
                while ($current !== [] && ($length > $this->overlap || $length + $pieceLength > $this->chunkSize)) {
                    $length -= mb_strlen(array_shift($current));
                }
            }

            $current[] = $piece;
            $length += $pieceLength;
        }

        if ($current !== []) {
            $chunks[] = trim(implode('', $current));
        }

        return array_values(array_filter($chunks, fn ($c) => $c !== ''));
    }
}
