<?php

namespace App\Services;

use App\Core\Entities\RetrievalResult;
use App\Core\Interfaces\EmbeddingProvider;
use App\Core\Interfaces\StorageInterface;

class QueryService
{
    public function __construct(
        private EmbeddingProvider $embedding,
        private StorageInterface $storage,
        private int $defaultTopK = 5,
        private float $defaultThreshold = 0.75,
    ) {
    }

    /** @return RetrievalResult[] */
    public function search(string $query, int $topK = 5): array
    {
        $queryVector = $this->embedding->embed($query);
        return $this->storage->searchSimilar($queryVector, $topK, $this->defaultThreshold);
    }
}
