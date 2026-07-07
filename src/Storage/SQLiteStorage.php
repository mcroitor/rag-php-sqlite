<?php

namespace App\Storage;

use App\Core\Entities\Chunk;
use App\Core\Entities\Document;
use App\Core\Entities\RetrievalResult;
use App\Core\Exceptions\StorageException;
use App\Core\Interfaces\StorageInterface;

class SQLiteStorage implements StorageInterface
{
    private \SQLite3 $db;
    private VectorSearch $vectorSearch;

    public function __construct(string $dbPath)
    {
        $this->vectorSearch = new VectorSearch();

        $this->db = new \SQLite3($dbPath);
        $this->db->exec('PRAGMA journal_mode = WAL');
        $this->db->exec('PRAGMA foreign_keys = ON');
    }

    public function storeDocument(Document $document): int
    {
        $stmt = $this->db->prepare(
            'INSERT OR REPLACE INTO documents (path, hash, created_at) VALUES (:path, :hash, COALESCE(:created_at, datetime(\'now\')))'
        );
        $stmt->bindValue(':path', $document->getPath(), SQLITE3_TEXT);
        $stmt->bindValue(':hash', $document->getHash(), SQLITE3_TEXT);
        $stmt->bindValue(':created_at', $document->getCreatedAt() ?? date('Y-m-d H:i:s'), SQLITE3_TEXT);
        $stmt->execute();

        $id = $this->db->lastInsertRowID();
        $document->setId($id);

        return $id;
    }

    public function getDocumentByPath(string $path): ?Document
    {
        $stmt = $this->db->prepare('SELECT * FROM documents WHERE path = :path');
        $stmt->bindValue(':path', $path, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);

        if ($row === false) {
            return null;
        }

        $doc = new Document();
        $doc->setId((int) $row['id']);
        $doc->setPath($row['path']);
        $doc->setHash($row['hash']);
        $doc->setCreatedAt($row['created_at']);

        return $doc;
    }

    public function getDocumentHash(string $path): ?string
    {
        $doc = $this->getDocumentByPath($path);

        if ($doc === null) {
            return null;
        }

        return $doc->getHash();
    }

    public function storeChunk(Chunk $chunk): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO chunks (document_id, heading_path, text, token_count, hash, language, embedding_model, document_hash, chunk_hash)
             VALUES (:document_id, :heading_path, :text, :token_count, :hash, :language, :embedding_model, :document_hash, :chunk_hash)'
        );
        $stmt->bindValue(':document_id', $chunk->getDocumentId(), SQLITE3_INTEGER);
        $stmt->bindValue(':heading_path', $chunk->getHeadingPath(), SQLITE3_TEXT);
        $stmt->bindValue(':text', $chunk->getText(), SQLITE3_TEXT);
        $stmt->bindValue(':token_count', $chunk->getTokenCount(), SQLITE3_INTEGER);
        $stmt->bindValue(':hash', $chunk->getHash(), SQLITE3_TEXT);
        $stmt->bindValue(':language', $chunk->getLanguage(), SQLITE3_TEXT);
        $stmt->bindValue(':embedding_model', $chunk->getEmbeddingModel(), SQLITE3_TEXT);
        $stmt->bindValue(':document_hash', $chunk->getDocumentHash(), SQLITE3_TEXT);
        $stmt->bindValue(':chunk_hash', $chunk->getHash(), SQLITE3_TEXT);
        $stmt->execute();

        $id = $this->db->lastInsertRowID();
        $chunk->setId($id);

        return $id;
    }

    /** @param list<float> $vector */
    public function storeEmbedding(int $chunkId, array $vector, string $model, int $dimension, string $version): void
    {
        $stmt = $this->db->prepare(
            'INSERT OR REPLACE INTO embeddings (chunk_id, vector, embedding_model, embedding_dimension, embedding_version, created_at)
             VALUES (:chunk_id, :vector, :model, :dimension, :version, datetime(\'now\'))'
        );
        $stmt->bindValue(':chunk_id', $chunkId, SQLITE3_INTEGER);
        $stmt->bindValue(':vector', json_encode($vector), SQLITE3_TEXT);
        $stmt->bindValue(':model', $model, SQLITE3_TEXT);
        $stmt->bindValue(':dimension', $dimension, SQLITE3_INTEGER);
        $stmt->bindValue(':version', $version, SQLITE3_TEXT);
        $stmt->execute();
    }

    /** @param list<float> $queryVector @return RetrievalResult[] */
    public function searchSimilar(array $queryVector, int $topK, float $threshold): array
    {
        $results = $this->db->query(
            'SELECT c.id, c.document_id, c.heading_path, c.text, c.token_count, c.hash, c.language,
                    c.embedding_model, c.document_hash, c.chunk_hash,
                    e.vector, e.embedding_model AS emb_model, e.embedding_dimension
             FROM chunks c
             INNER JOIN embeddings e ON e.chunk_id = c.id'
        );

        $candidates = [];
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $vector = json_decode($row['vector'], true);

            if (!is_array($vector)) {
                continue;
            }

            $candidates[] = [
                'chunk' => $this->rowToChunk($row),
                'vector' => $vector,
            ];
        }

        $scored = [];
        foreach ($candidates as $candidate) {
            $score = $this->vectorSearch->cosineSimilarity($queryVector, $candidate['vector']);

            if ($score >= $threshold) {
                $scored[] = new RetrievalResult(
                    $candidate['chunk'],
                    $score,
                    $this->getDocumentPath($candidate['chunk']->getDocumentId()),
                    $candidate['chunk']->getHeadingPath(),
                );
            }
        }

        usort($scored, fn($a, $b) => $b->getScore() <=> $a->getScore());

        return array_slice($scored, 0, $topK);
    }

    public function beginTransaction(): void
    {
        $this->db->exec('BEGIN TRANSACTION');
    }

    public function commit(): void
    {
        $this->db->exec('COMMIT');
    }

    public function rollback(): void
    {
        $this->db->exec('ROLLBACK');
    }

    public function getDocumentCount(): int
    {
        $result = $this->db->querySingle('SELECT COUNT(*) FROM documents');
        return (int) $result;
    }

    public function getChunkCount(): int
    {
        $result = $this->db->querySingle('SELECT COUNT(*) FROM chunks');
        return (int) $result;
    }

    public function clearAll(): void
    {
        $this->db->exec('DELETE FROM embeddings');
        $this->db->exec('DELETE FROM chunks');
        $this->db->exec('DELETE FROM documents');
    }

    /** @param array<string, mixed> $row */
    private function rowToChunk(array $row): Chunk
    {
        $chunk = new Chunk();
        $chunk->setId((int) $row['id']);
        $chunk->setDocumentId((int) $row['document_id']);
        $chunk->setHeadingPath($row['heading_path']);
        $chunk->setText($row['text']);
        $chunk->setTokenCount((int) $row['token_count']);
        $chunk->setHash($row['hash']);
        $chunk->setLanguage($row['language'] ?? '');
        $chunk->setEmbeddingModel($row['embedding_model'] ?? '');
        $chunk->setDocumentHash($row['document_hash'] ?? '');
        return $chunk;
    }

    private function getDocumentPath(?int $documentId): string
    {
        if ($documentId === null) {
            return '';
        }

        $stmt = $this->db->prepare('SELECT path FROM documents WHERE id = :id');
        $stmt->bindValue(':id', $documentId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);

        return $row ? $row['path'] : '';
    }
}
