<?php

namespace App\Core\Entities;

class RetrievalResult
{
    public function __construct(
        private Chunk $chunk,
        private float $score,
        private string $sourcePath = '',
        private string $headingPath = '',
    ) {
    }

    public function getChunk(): Chunk
    {
        return $this->chunk;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function getSourcePath(): string
    {
        return $this->sourcePath;
    }

    public function getHeadingPath(): string
    {
        return $this->headingPath;
    }
}
