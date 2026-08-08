<?php

namespace App\Engine\Core\Interfaces;

use App\Engine\Core\Entities\Chunk;
use App\Engine\Core\Entities\Document;

interface ChunkerInterface
{
    /** @return Chunk[] */
    public function chunk(Document $document): array;
}
