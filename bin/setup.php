<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Utils\AppLogger;
use App\Utils\Config;

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

$dbPath = __DIR__ . '/../rag.sqlite';
$log->info("Initializing database: $dbPath");

try {
    $db = new \SQLite3($dbPath);

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
            chunk_hash TEXT NOT NULL DEFAULT '',
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
    $db->exec('CREATE INDEX IF NOT EXISTS idx_chunks_hash ON chunks(hash)');

    $db->close();

    $log->pass('Database initialized successfully');
} catch (\Exception $e) {
    $log->error("Database initialization failed: " . $e->getMessage());
    exit(1);
}
