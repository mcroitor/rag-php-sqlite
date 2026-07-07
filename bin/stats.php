<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Storage\SQLiteStorage;
use App\Utils\AppLogger;
use App\Utils\Config;

$log = AppLogger::instance();

try {
    $config = new Config(__DIR__ . '/../config/config.yaml');
} catch (\Throwable $e) {
    $log->error($e->getMessage());
    exit(1);
}

$dbPath = __DIR__ . '/../rag.sqlite';

if (!file_exists($dbPath)) {
    $log->error("Database not found. Run 'php bin/setup.php' first.");
    exit(1);
}

$storage = new SQLiteStorage($dbPath);

$docCount = $storage->getDocumentCount();
$chunkCount = $storage->getChunkCount();
$dbSize = filesize($dbPath);

echo "RAG Database Statistics\n";
echo str_repeat('-', 40) . "\n";
echo "Documents:     $docCount\n";
echo "Chunks:        $chunkCount\n";
echo "Database size: " . number_format($dbSize) . " bytes";

if ($dbSize > 1024 * 1024) {
    echo " (" . number_format($dbSize / (1024 * 1024), 2) . " MB)";
} elseif ($dbSize > 1024) {
    echo " (" . number_format($dbSize / 1024, 2) . " KB)";
}

echo "\n";
