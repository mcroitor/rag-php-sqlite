<?php

namespace App\Engine\Core\Interfaces;

use App\Engine\Core\Entities\Chunk;
use App\Engine\Core\Entities\Document;
use App\Engine\Core\Entities\RetrievalResult;

interface StorageInterface
{
    public function storeDocument(Document $document): int;

    public function getDocumentByPath(string $path): ?Document;

    public function getDocumentIdByPath(string $path): ?int;

    public function getDocumentHash(string $path): ?string;

    /**
     * List stored documents (newest first).
     *
     * @return list<array{id: int, path: string, hash: string, created_at: string, chunks: int}>
     */
    public function listDocuments(?int $limit = null, ?int $offset = null): array;

    public function deleteDocumentById(int $id): bool;

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
