<?php

namespace App\Engine\Prompt;

use App\Engine\Core\Entities\RetrievalResult;
use App\Engine\Utils\Constant;

class ContextWindow
{
    private int $maxTokens;

    public function __construct(int $maxTokens = Constant::DEFAULT_MAX_CONTEXT_TOKENS)
    {
        $this->maxTokens = $maxTokens;
    }

    /** @param RetrievalResult[] $results @return RetrievalResult[] */
    public function fit(array $results, int $maxTokens): array
    {
        $fitted = [];
        $totalTokens = 0;

        // Work on a copy to avoid mutating the caller's array
        $sortedResults = $this->sortByScoreDesc($results);

        foreach ($sortedResults as $result) {
            $chunkTokens = $result->getChunk()->getTokenCount();
            $overhead = Constant::CONTEXT_OVERHEAD_PER_CHUNK;

            if ($totalTokens + $chunkTokens + $overhead <= $maxTokens) {
                $fitted[] = $result;
                $totalTokens += $chunkTokens + $overhead;
            }
        }

        return $fitted;
    }

    public function getMaxTokens(): int
    {
        return $this->maxTokens;
    }

    /** @param RetrievalResult[] $results @return RetrievalResult[] */
    private function sortByScoreDesc(array $results): array
    {
        $copy = $results;
        usort($copy, fn($a, $b) => $b->getScore() <=> $a->getScore());
        return $copy;
    }
}
