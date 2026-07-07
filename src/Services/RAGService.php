<?php

namespace App\Services;

use App\Core\Entities\RetrievalResult;
use App\Core\Interfaces\LLMProvider;
use App\Core\Interfaces\RetrieverInterface;
use App\Prompt\PromptBuilder;

class RAGService
{
    public function __construct(
        private RetrieverInterface $retriever,
        private PromptBuilder $promptBuilder,
        private LLMProvider $llm,
    ) {
    }

    public function ask(string $query, int $topK = 5): string
    {
        $results = $this->retriever->retrieve($query, $topK);
        $prompt = $this->promptBuilder->build($query, $results);

        return $this->llm->generate($prompt);
    }

    /** @return RetrievalResult[] */
    public function getContext(string $query, int $topK = 5): array
    {
        return $this->retriever->retrieve($query, $topK);
    }
}
