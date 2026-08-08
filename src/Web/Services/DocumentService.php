<?php

declare(strict_types=1);

namespace App\Web\Services;

use App\Engine\Storage\SQLiteStorage;

/**
 * Document listing/removal use-cases for the web layer.
 */
class DocumentService
{
    private SQLiteStorage $storage;

    public function __construct(SQLiteStorage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * @return array{total: int, count: int, offset: int, documents: list<array{id: int, path: string, hash: string, created_at: string, chunks: int}>}
     */
    public function list(int $limit = 50, int $offset = 0, string $search = ''): array
    {
        $documents = $this->storage->listDocuments($limit, $offset);

        if ($search !== '') {
            $documents = array_values(array_filter(
                $documents,
                static fn(array $doc): bool => str_contains(strtolower($doc['path']), strtolower($search)),
            ));
        }

        return [
            'total' => $this->storage->getDocumentCount(),
            'count' => count($documents),
            'offset' => $offset,
            'documents' => $documents,
        ];
    }

    /**
     * @return array{removed: bool, id: int, path: string}
     */
    public function removeById(int $id): array
    {
        $path = $this->storage->getDocumentPathById($id);

        if ($path === '') {
            throw new \App\Engine\Core\Exceptions\ValidationException("Document with id=$id not found");
        }

        $removed = $this->storage->deleteDocumentById($id);

        return ['removed' => $removed, 'id' => $id, 'path' => $path];
    }

    public function pathById(int $id): string
    {
        $path = $this->storage->getDocumentPathById($id);

        if ($path === '') {
            throw new \App\Engine\Core\Exceptions\ValidationException("Document with id=$id not found");
        }

        return $path;
    }
}
