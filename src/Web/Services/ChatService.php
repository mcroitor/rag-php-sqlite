<?php

declare(strict_types=1);

namespace App\Web\Services;

use App\Engine\Services\RAGService;

/**
 * RAG chat use-case for the web layer.
 */
class ChatService
{
    private RAGService $rag;

    public function __construct(RAGService $rag)
    {
        $this->rag = $rag;
    }

    /**
     * @return array{query: string, top_k: int, answer: string, sources: list<array{score: float, relevance: float, source: string, heading: string, token_count: int}>}
     */
    public function ask(string $query, int $topK = 5): array
    {
        $context = $this->rag->getContext($query, $topK);

        $mapped = [];
        foreach ($context as $result) {
            $mapped[] = [
                'score' => round($result->getScore(), 4),
                'relevance' => round($result->getScore() * 100, 2),
                'source' => $result->getSourcePath(),
                'heading' => $result->getHeadingPath(),
                'token_count' => $result->getChunk()->getTokenCount(),
            ];
        }

        $answer = $this->rag->ask($query, $topK, $context);

        return [
            'query' => $query,
            'top_k' => $topK,
            'answer' => $answer,
            'sources' => $mapped,
        ];
    }
}
