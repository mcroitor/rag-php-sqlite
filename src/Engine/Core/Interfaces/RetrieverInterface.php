<?php

namespace App\Engine\Core\Interfaces;

use App\Engine\Core\Entities\RetrievalResult;

interface RetrieverInterface
{
    /** @return RetrievalResult[] */
    public function retrieve(string $query, int $topK = 5): array;
}
