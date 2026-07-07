<?php

namespace App\Validator;

use App\Chunker\TokenCounter;
use App\Core\Entities\Chunk;
use App\Core\Exceptions\ValidationException;

class ChunkValidator
{
    private int $maxTokens;
    private int $safetyMargin;
    private TokenCounter $counter;

    public function __construct(int $maxTokens = 1500, int $safetyMargin = 300)
    {
        $this->maxTokens = $maxTokens;
        $this->safetyMargin = $safetyMargin;
        $this->counter = new TokenCounter();
    }

    public function validate(Chunk $chunk): bool
    {
        $this->checkTokenCount($chunk);
        $this->checkSafetyMargin($chunk);
        $this->checkEncodingValidity($chunk);

        return true;
    }

    private function checkTokenCount(Chunk $chunk): void
    {
        $tokens = $this->counter->count($chunk->getText());

        if ($tokens > $this->maxTokens) {
            throw new ValidationException(
                "Chunk exceeds max token limit: {$tokens} > {$this->maxTokens}"
            );
        }
    }

    private function checkSafetyMargin(Chunk $chunk): void
    {
        $tokens = $this->counter->count($chunk->getText());
        $effectiveLimit = $this->maxTokens - $this->safetyMargin;

        if ($tokens > $effectiveLimit) {
            throw new ValidationException(
                "Chunk exceeds safety margin: {$tokens} > {$effectiveLimit} " .
                "(max: {$this->maxTokens}, margin: {$this->safetyMargin})"
            );
        }
    }

    private function checkEncodingValidity(Chunk $chunk): void
    {
        $text = $chunk->getText();

        if (!mb_check_encoding($text, 'UTF-8')) {
            throw new ValidationException("Chunk text is not valid UTF-8");
        }
    }
}
