<?php

namespace Tests\Unit;

use App\AI\Support\StreamReader;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;

class StreamReaderTest extends TestCase
{
    public function test_parses_sse_events_across_chunk_boundaries(): void
    {
        $body = Utils::streamFor(
            "event: content_block_delta\ndata: {\"text\":\"Hel\"}\n\n: keep-alive\n\ndata: {\"text\":\"lo\"}\r\n\r\ndata: [DONE]\n\n"
        );

        $events = iterator_to_array(StreamReader::events($body), false);

        $this->assertCount(3, $events);
        $this->assertSame('content_block_delta', $events[0]['event']);
        $this->assertSame('{"text":"Hel"}', $events[0]['data']);
        $this->assertNull($events[1]['event']);
        $this->assertSame('[DONE]', $events[2]['data']);
    }

    public function test_reads_ndjson_lines(): void
    {
        $body = Utils::streamFor("{\"a\":1}\n{\"a\":2}\n{\"a\":3}");

        $lines = iterator_to_array(StreamReader::lines($body, 4), false);

        $this->assertSame(['{"a":1}', '{"a":2}', '{"a":3}'], $lines);
    }
}
