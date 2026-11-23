<?php

namespace Tests\Unit;

use App\Rag\Chunker;
use PHPUnit\Framework\TestCase;

class ChunkerTest extends TestCase
{
    public function test_short_text_is_a_single_chunk(): void
    {
        $this->assertSame(['Hello world.'], (new Chunker(200, 50))->split("  Hello world.  "));
    }

    public function test_empty_text_has_no_chunks(): void
    {
        $this->assertSame([], (new Chunker(200, 50))->split("  \n\n "));
    }

    public function test_long_text_respects_chunk_size_and_keeps_all_content(): void
    {
        $text = str_repeat('Invoices are due within 30 days. Refunds take five business days. ', 20)
            ."\n\n".str_repeat('Support is available around the clock. ', 10);

        $chunks = (new Chunker(200, 50))->split($text);

        $this->assertGreaterThan(5, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(200, mb_strlen($chunk));
        }
        $this->assertStringContainsString('Support is available', implode(' ', $chunks));
    }

    public function test_unbreakable_text_is_hard_split(): void
    {
        $chunks = (new Chunker(100, 10))->split(str_repeat('x', 250));

        $this->assertCount(3, $chunks);
        $this->assertSame(100, mb_strlen($chunks[0]));
    }

    public function test_overlap_must_be_smaller_than_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Chunker(100, 100);
    }
}
