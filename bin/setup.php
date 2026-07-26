<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Utils\AppLogger;
use App\Utils\Config;
use App\Utils\Constant;

$log = AppLogger::instance();
$log->info('RAG-PHP-SQLite Setup');

$configPath = __DIR__ . '/../config/config.yaml';

if (!file_exists($configPath)) {
    $log->error('config/config.yaml not found. Create it from the template in README.md.');
    exit(1);
}

try {
    $config = new Config($configPath);
} catch (\Exception $e) {
    $log->error($e->getMessage());
    exit(1);
}

$dbPath = __DIR__ . '/../' . Constant::DEFAULT_DB_FILENAME;
$log->info("Initializing database: $dbPath");

try {
    $db = new \PDO("sqlite:$dbPath");
    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA foreign_keys = ON');

    $db->exec("
        CREATE TABLE IF NOT EXISTS documents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            path TEXT NOT NULL UNIQUE,
            hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS chunks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            document_id INTEGER NOT NULL,
            heading_path TEXT NOT NULL DEFAULT '',
            text TEXT NOT NULL,
            token_count INTEGER NOT NULL DEFAULT 0,
            hash TEXT NOT NULL,
            language TEXT NOT NULL DEFAULT '',
            embedding_model TEXT NOT NULL DEFAULT '',
            document_hash TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS embeddings (
            chunk_id INTEGER PRIMARY KEY,
            vector BLOB NOT NULL,
            embedding_model TEXT NOT NULL DEFAULT '',
            embedding_dimension INTEGER NOT NULL DEFAULT 0,
            embedding_version TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (chunk_id) REFERENCES chunks(id) ON DELETE CASCADE
        )
    ");

    $db->exec('CREATE INDEX IF NOT EXISTS idx_documents_path ON documents(path)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_documents_hash ON documents(hash)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_chunks_document_id ON chunks(document_id)');
    $db->exec("CREATE INDEX IF NOT EXISTS idx_embeddings_vector ON embeddings(vector)");

    $db->exec("
        CREATE TABLE IF NOT EXISTS embedding_cache (
            hash TEXT PRIMARY KEY,
            vector BLOB NOT NULL,
            model TEXT NOT NULL,
            dimension INTEGER NOT NULL,
            document_id INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");

    $db->exec('CREATE INDEX IF NOT EXISTS idx_cache_model ON embedding_cache(model)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_cache_document_id ON embedding_cache(document_id)');

    $log->pass('Database initialized successfully');
} catch (\Exception $e) {
    $log->error("Database initialization failed: " . $e->getMessage());
    exit(1);
}
