<?php

namespace App\Engine\Services;

use App\Engine\Core\Entities\RetrievalResult;
use App\Engine\Core\Interfaces\EmbeddingProvider;
use App\Engine\Core\Interfaces\StorageInterface;
use App\Engine\Utils\Constant;

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
