<?php

namespace App\Engine\Retrieval;

use App\Engine\Core\Entities\RetrievalResult;
use App\Engine\Core\Interfaces\EmbeddingProvider;
use App\Engine\Core\Interfaces\RetrieverInterface;
use App\Engine\Core\Interfaces\StorageInterface;
use App\Engine\Utils\Constant;

class VectorRetriever implements RetrieverInterface
{
    public function __construct(
        private EmbeddingProvider $embedding,
        private StorageInterface $storage,
        private float $defaultThreshold = Constant::DEFAULT_THRESHOLD,
    ) {
    }

    /** @return RetrievalResult[] */
    public function retrieve(string $query, int $topK = 5): array
    {
        $queryVector = $this->embedding->embed($query);
        return $this->storage->searchSimilar($queryVector, $topK, $this->defaultThreshold);
    }
}
