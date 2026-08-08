<?php

namespace App\Engine\Embedding;

class EmbeddingCache
{
    private \PDO $db;
    public function __construct(
        \PDO $db,
    ) {
        $this->db = $db;
    }

    /** @return list<float>|null */
    public function get(string $textHash, string $model, ?int $documentId = null, ?int $dimension = null): ?array
    {
        $sql = 'SELECT vector FROM embedding_cache WHERE hash = :hash AND model = :model';
        $params = [':hash' => $textHash, ':model' => $model];

        if ($documentId !== null) {
            $sql .= ' AND document_id = :document_id';
            $params[':document_id'] = $documentId;
        }

        if ($dimension !== null) {
            $sql .= ' AND dimension = :dimension';
            $params[':dimension'] = $dimension;
        }

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return json_decode($row['vector'], true);
    }

    /** @param list<float> $vector */
    public function set(string $textHash, string $model, int $dimension, array $vector, ?int $documentId = null): void
    {
        $stmt = $this->db->prepare(
            'INSERT OR REPLACE INTO embedding_cache (hash, vector, model, dimension, document_id, created_at) VALUES (:hash, :vector, :model, :dimension, :document_id, datetime(\'now\'))'
        );
        $stmt->bindValue(':hash', $textHash);
        $stmt->bindValue(':vector', json_encode($vector));
        $stmt->bindValue(':model', $model);
        $stmt->bindValue(':dimension', $dimension, \PDO::PARAM_INT);
        $stmt->bindValue(':document_id', $documentId, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function has(string $textHash, string $model, ?int $documentId = null, ?int $dimension = null): bool
    {
        $sql = 'SELECT 1 FROM embedding_cache WHERE hash = :hash AND model = :model';
        $params = [':hash' => $textHash, ':model' => $model];

        if ($documentId !== null) {
            $sql .= ' AND document_id = :document_id';
            $params[':document_id'] = $documentId;
        }

        if ($dimension !== null) {
            $sql .= ' AND dimension = :dimension';
            $params[':dimension'] = $dimension;
        }

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetch() !== false;
    }
}
