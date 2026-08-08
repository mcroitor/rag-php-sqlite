<?php

declare(strict_types=1);

namespace App\Web\Services;

use App\Engine\Services\QueryService;

/**
 * Search use-case for the web layer.
 */
class SearchService
{
    private QueryService $query;

    public function __construct(QueryService $query)
    {
        $this->query = $query;
    }

    /**
     * @return array{query: string, top_k: int, results: list<array{score: float, relevance: float, source: string, heading: string, text: string, token_count: int}>}
     */
    public function search(string $query, int $topK = 5): array
    {
        $results = $this->query->search($query, $topK);

        $mapped = [];
        foreach ($results as $result) {
            $mapped[] = [
                'score' => round($result->getScore(), 4),
                'relevance' => round($result->getScore() * 100, 2),
                'source' => $result->getSourcePath(),
                'heading' => $result->getHeadingPath(),
                'text' => $result->getChunk()->getText(),
                'token_count' => $result->getChunk()->getTokenCount(),
            ];
        }

        return [
            'query' => $query,
            'top_k' => $topK,
            'results' => $mapped,
        ];
    }
}
