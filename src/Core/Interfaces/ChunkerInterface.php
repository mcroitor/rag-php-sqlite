<?php

namespace App\Core\Interfaces;

use App\Core\Entities\Chunk;
use App\Core\Entities\Document;

interface ChunkerInterface
{
    /** @return Chunk[] */
    public function chunk(Document $document): array;
}
