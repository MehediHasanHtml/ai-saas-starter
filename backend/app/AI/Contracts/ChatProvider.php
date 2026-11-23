<?php

namespace App\AI\Contracts;

use App\AI\Data\ChatRequest;
use App\AI\Data\ChatResult;
use App\AI\Data\StreamEvent;
use Generator;

interface ChatProvider
{
    /** Stable provider key, e.g. "openai". */
    public function name(): string;

    /** True when credentials / endpoint are configured. */
    public function isConfigured(): bool;

    /** @return list<string> */
    public function models(): array;

    public function chat(ChatRequest $request): ChatResult;

    /** @return Generator<int, StreamEvent> */
    public function stream(ChatRequest $request): Generator;
}
