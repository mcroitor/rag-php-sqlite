<?php

namespace App\Engine\Services;

use App\Engine\Core\Entities\RetrievalResult;
use App\Engine\Core\Interfaces\LLMProvider;
use App\Engine\Core\Interfaces\RetrieverInterface;
use App\Engine\Prompt\PromptBuilder;

class RAGService
{
    public function __construct(
        private RetrieverInterface $retriever,
        private PromptBuilder $promptBuilder,
        private LLMProvider $llm,
    ) {
    }

    /** @param RetrievalResult[]|null $preRetrieved */
    public function ask(string $query, int $topK = 5, ?array $preRetrieved = null): string
    {
        $results = $preRetrieved ?? $this->retriever->retrieve($query, $topK);
        $prompt = $this->promptBuilder->build($query, $results);

        return $this->llm->generate($prompt);
    }

    /** @return RetrievalResult[] */
    public function getContext(string $query, int $topK = 5): array
    {
        return $this->retriever->retrieve($query, $topK);
    }
}
