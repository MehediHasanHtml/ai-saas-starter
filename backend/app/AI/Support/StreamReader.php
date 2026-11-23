<?php

namespace App\AI\Support;

use Generator;
use Psr\Http\Message\StreamInterface;

/**
 * Incremental readers for streamed HTTP bodies: newline-delimited JSON
 * (Ollama) and Server-Sent Events (OpenAI, Anthropic, Gemini).
 */
final class StreamReader
{
    /** @return Generator<int, string> */
    public static function lines(StreamInterface $body, int $chunkSize = 1024): Generator
    {
        $buffer = '';

        while (! $body->eof()) {
            $chunk = $body->read($chunkSize);
            if ($chunk === '') {
                continue;
            }

            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = rtrim(substr($buffer, 0, $pos), "\r");
                $buffer = substr($buffer, $pos + 1);
                yield $line;
            }
        }

        if ($buffer !== '') {
            yield rtrim($buffer, "\r");
        }
    }

    /**
     * Parse SSE frames. Yields ['event' => ?string, 'data' => string] per frame.
     *
     * @return Generator<int, array{event: ?string, data: string}>
     */
    public static function events(StreamInterface $body): Generator
    {
        $event = null;
        $data = [];

        foreach (self::lines($body) as $line) {
            if ($line === '') {
                if ($data !== []) {
                    yield ['event' => $event, 'data' => implode("\n", $data)];
                }
                $event = null;
                $data = [];

                continue;
            }

            if (str_starts_with($line, ':')) {
                continue; // comment / keep-alive
            }

            [$field, $value] = array_pad(explode(':', $line, 2), 2, '');
            $value = ltrim($value, ' ');

            if ($field === 'event') {
                $event = $value;
            } elseif ($field === 'data') {
                $data[] = $value;
            }
        }

        if ($data !== []) {
            yield ['event' => $event, 'data' => implode("\n", $data)];
        }
    }
}
