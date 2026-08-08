<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Engine\Chunker\SemanticChunker;
use App\Engine\Embedding\OllamaEmbedding;
use App\Engine\Loader\FileScanner;
use App\Engine\Loader\MarkdownLoader;
use App\Engine\Parser\MarkdownParser;
use App\Engine\Services\IndexingService;
use App\Engine\Storage\SQLiteStorage;
use App\Engine\Utils\AppLogger;
use App\Engine\Utils\Config;
use App\Engine\Utils\Constant;
use App\Engine\Utils\DbFactory;
use App\Engine\Validator\ChunkValidator;

$log = AppLogger::instance();

try {
    $config = new Config(__DIR__ . '/../config/config.yaml');
} catch (\Throwable $e) {
    $log->error($e->getMessage());
    exit(1);
}

\Mc\Arguments::Set([
    'dir' => [
        'short' => 'd',
        'long' => 'dir',
        'description' => 'Directory containing Markdown files',
        'required' => true,
        'default' => null,
    ],
    'recursive' => [
        'short' => 'r',
        'long' => 'recursive',
        'description' => 'Scan directories recursively',
        'required' => false,
    ],
    'incremental' => [
        'short' => 'i',
        'long' => 'incremental',
        'description' => 'Incremental re-index (skip unchanged files)',
        'required' => false,
    ],
    'rag' => [
        'short' => 'r',
        'long' => 'rag',
        'description' => 'RAG database name (default: rag)',
        'required' => false,
        'default' => DbFactory::DEFAULT_BASE,
    ],
    'help' => [
        'short' => 'h',
        'long' => 'help',
        'description' => 'Show help',
        'required' => false,
    ],
]);

try {
    \Mc\Arguments::Parse();
} catch (\InvalidArgumentException $e) {
    echo $e->getMessage() . "\n";
    echo \Mc\Arguments::Help();
    exit(1);
}

if (\Mc\Arguments::GetValue('help')) {
    echo "Index Markdown documents into the RAG database.\n\n";
    echo \Mc\Arguments::Help();
    exit(0);
}

$dir = \Mc\Arguments::GetValue('dir');
$recursive = (bool) \Mc\Arguments::GetValue('recursive');
$incremental = (bool) \Mc\Arguments::GetValue('incremental');
$base = (string) (\Mc\Arguments::GetValue('rag') ?: DbFactory::DEFAULT_BASE);

if (!is_dir($dir)) {
    $log->error("Directory not found: $dir");
    exit(1);
}

$embedding = new OllamaEmbedding(
    baseUrl: $config->getOllamaBaseUrl(),
    model: $config->getEmbeddingModel(),
    dimension: $config->getEmbeddingDimension(),
    retryCount: $config->getRetryCount(),
);

$db = DbFactory::pdo(dirname(__DIR__), $base);

$storage = new SQLiteStorage($db);
$cache = new \App\Engine\Embedding\EmbeddingCache($db);

$service = new IndexingService(
    scanner: new FileScanner(),
    loader: new MarkdownLoader(),
    parser: new MarkdownParser(),
    chunker: new SemanticChunker(
        maxTokens: $config->getMaxTokens(),
        overlap: $config->getOverlap(),
        safetyMargin: $config->getSafetyMargin(),
    ),
    validator: new ChunkValidator(
        maxTokens: $config->getMaxTokens(),
    ),
    embedding: $embedding,
    storage: $storage,
    cache: $cache,
);

$log->info('Starting indexing...');
$stats = $service->indexDirectory($dir, $recursive, $incremental);

echo "\nSummary:\n";
echo "  Processed: {$stats['processed']}\n";
echo "  Skipped:   {$stats['skipped']}\n";
echo "  Failed:    {$stats['failed']}\n";
echo "  Chunks:    {$stats['chunks']}\n";
