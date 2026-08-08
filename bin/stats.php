<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Engine\Storage\SQLiteStorage;
use App\Engine\Utils\AppLogger;
use App\Engine\Utils\Config;
use App\Engine\Utils\Constant;
use App\Engine\Utils\DbFactory;

$log = AppLogger::instance();

try {
    $config = new Config(__DIR__ . '/../config/config.yaml');
} catch (\Throwable $e) {
    $log->error($e->getMessage());
    exit(1);
}

$root = dirname(__DIR__);
$argv = $_SERVER['argv'] ?? [];
$base = DbFactory::baseFromArgv($argv);
$dbPath = DbFactory::path($root, $base);

if (!DbFactory::exists($root, $base)) {
    $log->error("Database '$base' not found. Run 'php bin/setup.php --rag=$base' first.");
    exit(1);
}

$db = DbFactory::pdo($root, $base);

$storage = new SQLiteStorage($db);

$docCount = $storage->getDocumentCount();
$chunkCount = $storage->getChunkCount();
$embedCount = $storage->getEmbeddingCount();
$dbSize = filesize($dbPath);

echo "RAG Database Statistics (base: $base)\n";
echo str_repeat('-', 40) . "\n";
echo "Documents:     $docCount\n";
echo "Chunks:        $chunkCount\n";
echo "Embeddings:    $embedCount\n";
echo "Database size: " . number_format($dbSize) . " bytes";

if ($dbSize > 1024 * 1024) {
    echo " (" . number_format($dbSize / (1024 * 1024), 2) . " MB)";
} elseif ($dbSize > 1024) {
    echo " (" . number_format($dbSize / 1024, 2) . " KB)";
}

echo "\n";
