<?php

namespace App\Prompt;

use App\Core\Entities\RetrievalResult;

class ContextWindow
{
    private int $maxTokens;

    public function __construct(int $maxTokens = 4096)
    {
        $this->maxTokens = $maxTokens;
    }

    /** @param RetrievalResult[] $results @return RetrievalResult[] */
    public function fit(array $results, int $maxTokens): array
    {
        $fitted = [];
        $totalTokens = 0;

        $results = $this->sortByScoreDesc($results);

        foreach ($results as $result) {
            $chunkTokens = $result->getChunk()->getTokenCount();
            $overhead = 20;

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
        usort($results, fn($a, $b) => $b->getScore() <=> $a->getScore());
        return $results;
    }
}
