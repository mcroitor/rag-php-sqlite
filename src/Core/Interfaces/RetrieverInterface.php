<?php

namespace App\Core\Interfaces;

use App\Core\Entities\RetrievalResult;

interface RetrieverInterface
{
    /** @return RetrievalResult[] */
    public function retrieve(string $query, int $topK = 5): array;
}
