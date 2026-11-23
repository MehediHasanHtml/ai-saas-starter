<?php

namespace App\AI\Contracts;

interface EmbeddingProvider
{
    /**
     * @param  list<string>  $texts
     * @return list<list<float>> one vector per input, same order
     */
    public function embed(array $texts, string $model, int $dimensions): array;
}
