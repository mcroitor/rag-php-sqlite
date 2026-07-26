<?php

namespace App\Validator;

use App\Chunker\TokenCounter;
use App\Core\Entities\Chunk;
use App\Core\Exceptions\ValidationException;
use App\Utils\Constant;

class ChunkValidator
{
    private int $maxTokens;
    private TokenCounter $counter;

    public function __construct(int $maxTokens = Constant::DEFAULT_EMBED_MAX_TOKENS)
    {
        $this->maxTokens = $maxTokens;
        $this->counter = new TokenCounter();
    }

    public function validate(Chunk $chunk): bool
    {
        $this->checkTokenCount($chunk);
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

    private function checkEncodingValidity(Chunk $chunk): void
    {
        $text = $chunk->getText();

        if (!mb_check_encoding($text, 'UTF-8')) {
            throw new ValidationException("Chunk text is not valid UTF-8");
        }
    }
}
