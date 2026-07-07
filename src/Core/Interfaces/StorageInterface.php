<?php

namespace App\Core\Interfaces;

use App\Core\Entities\Chunk;
use App\Core\Entities\Document;
use App\Core\Entities\RetrievalResult;

interface StorageInterface
{
    public function storeDocument(Document $document): int;

    public function getDocumentByPath(string $path): ?Document;

    public function getDocumentHash(string $path): ?string;

    public function storeChunk(Chunk $chunk): int;

    /** @param list<float> $vector */
    public function storeEmbedding(int $chunkId, array $vector, string $model, int $dimension, string $version): void;

    /** @param list<float> $queryVector @return RetrievalResult[] */
    public function searchSimilar(array $queryVector, int $topK, float $threshold): array;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollback(): void;

    public function getDocumentCount(): int;

    public function getChunkCount(): int;

    public function clearAll(): void;
}
