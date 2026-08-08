<?php

declare(strict_types=1);

namespace App\Engine\Storage;

use App\Engine\Utils\DbFactory;

/**
 * SQLite-backed registry for RAG database metadata: the active base,
 * the registry of known bases and the job history.
 */
class MetaStorage
{
    private string $root;
    private \PDO $pdo;

    public function __construct(string $root)
    {
        $this->root = $root;
        $this->pdo = $this->open();
        $this->schema();
    }

    public function pdo(): \PDO
    {
        return $this->pdo;
    }

    public function root(): string
    {
        return $this->root;
    }

    public function getActiveBase(): string
    {
        $active = $this->getSetting('active_base');

        return $active === null ? DbFactory::DEFAULT_BASE : DbFactory::normalize($active);
    }

    public function setActiveBase(string $base): void
    {
        $this->setSetting('active_base', DbFactory::normalize($base));
    }

    /**
     * List all known RAG databases plus the active flag.
     *
     * @return list<array{name: string, path: string, size: int, exists: bool, active: bool}>
     */
    public function listBases(): array
    {
        $active = $this->getActiveBase();
        $bases = [];

        foreach (DbFactory::list($this->root) as $entry) {
            $bases[] = [
                'name' => $entry['name'],
                'path' => $entry['path'],
                'size' => $entry['size'],
                'exists' => $entry['exists'],
                'active' => $entry['name'] === $active,
            ];
        }

        return $bases;
    }

    public function recordJob(string $jobId, string $base): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO jobs (job_id, base, created_at) VALUES (:job_id, :base, :created_at)'
        );
        $stmt->execute([
            'job_id' => $jobId,
            'base' => DbFactory::normalize($base),
            'created_at' => date('c'),
        ]);
    }

    public function jobBase(string $jobId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT base FROM jobs WHERE job_id = :job_id');
        $stmt->execute(['job_id' => $jobId]);
        $row = $stmt->fetch();

        return is_array($row) && isset($row['base']) ? (string) $row['base'] : null;
    }

    private function getSetting(string $key): ?string
    {
        $stmt = $this->pdo->prepare('SELECT value FROM settings WHERE key = :key');
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch();

        return is_array($row) && isset($row['value']) ? (string) $row['value'] : null;
    }

    private function setSetting(string $key, string $value): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO settings (key, value) VALUES (:key, :value)
             ON CONFLICT (key) DO UPDATE SET value = excluded.value'
        );
        $stmt->execute(['key' => $key, 'value' => $value]);
    }

    private function metaPath(): string
    {
        return rtrim($this->root, '/\\') . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'meta.sqlite';
    }

    private function open(): \PDO
    {
        $path = $this->metaPath();
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $pdo = new \PDO('sqlite:' . $path);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode = WAL');

        return $pdo;
    }

    private function schema(): void
    {
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS jobs (
    job_id     TEXT PRIMARY KEY,
    base       TEXT NOT NULL,
    created_at TEXT NOT NULL
);
SQL);
    }
}
