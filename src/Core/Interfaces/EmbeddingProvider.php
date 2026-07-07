<?php

namespace App\Core\Interfaces;

interface EmbeddingProvider
{
    /** @return list<float> */
    public function embed(string $text): array;

    /** @param list<string> $texts @return array<int, list<float>> */
    public function embedBatch(array $texts): array;

    public function getDimension(): int;

    public function getModel(): string;
}
