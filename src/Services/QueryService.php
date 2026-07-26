<?php

namespace App\Services;

use App\Core\Entities\RetrievalResult;
use App\Core\Interfaces\EmbeddingProvider;
use App\Core\Interfaces\StorageInterface;
use App\Utils\Constant;

class QueryService
{
    public function __construct(
        private EmbeddingProvider $embedding,
        private StorageInterface $storage,
        private float $defaultThreshold = Constant::DEFAULT_THRESHOLD,
    ) {
    }

    /** @return RetrievalResult[] */
    public function search(string $query, int $topK = 5): array
    {
        $queryVector = $this->embedding->embed($query);
        return $this->storage->searchSimilar($queryVector, $topK, $this->defaultThreshold);
    }
}
