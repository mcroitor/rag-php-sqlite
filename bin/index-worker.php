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

/**
 * Background indexing worker for the web UI.
 *
 * Usage:
 *   php bin/index-worker.php --job-id=<id> --dir=<path> [--recursive] [--incremental]
 *
 * Writes progress to runtime/jobs/<job-id>.log and creates a
 * <job-id>.done or <job-id>.error marker on completion.
 */

/**
 * @param string[] $argv
 */
function workerArg(array $argv, string $name, mixed $default = null): mixed
{
    $prefix = '--' . $name;

    foreach ($argv as $i => $arg) {
        if ($arg === $prefix) {
            return $argv[$i + 1] ?? $default;
        }

        if (str_starts_with($arg, $prefix . '=')) {
            return substr($arg, strlen($prefix . '='));
        }
    }

    return $default;
}

$argv = $_SERVER['argv'] ?? $argv ?? [];

set_error_handler(function ($severity, $message, $file, $line) {
    if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
        return true;
    }

    throw new \ErrorException($message, 0, $severity, $file, $line);
});

$jobId = (string) workerArg($argv, 'job-id', '');

if ($jobId === '') {
    fwrite(STDERR, "Missing --job-id argument\n");
    exit(2);
}

$dir = (string) workerArg($argv, 'dir', '');
$recursive = (bool) workerArg($argv, 'recursive', false);
$incremental = (bool) workerArg($argv, 'incremental', false);
$base = (string) workerArg($argv, 'rag', DbFactory::DEFAULT_BASE);

$root = dirname(__DIR__);
$jobsDir = $root . '/runtime/jobs';

if (!is_dir($jobsDir)) {
    mkdir($jobsDir, 0777, true);
}

$logPath = $jobsDir . '/' . $jobId . '.log';
$donePath = $jobsDir . '/' . $jobId . '.done';
$errorPath = $jobsDir . '/' . $jobId . '.error';

$log = AppLogger::instance($logPath);

try {
    $config = new Config($root . '/config/config.yaml');

    if ($dir === '' || !is_dir($dir)) {
        throw new RuntimeException("Directory not found: $dir");
    }

    $db = DbFactory::pdo($root, $base);
    $storage = new SQLiteStorage($db);
    $cache = new App\Engine\Embedding\EmbeddingCache($db);

    $embedding = new OllamaEmbedding(
        baseUrl: $config->getOllamaBaseUrl(),
        model: $config->getEmbeddingModel(),
        dimension: $config->getEmbeddingDimension(),
        retryCount: $config->getRetryCount(),
    );

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

    $log->info(sprintf(
        'Job started: dir=%s, recursive=%s, incremental=%s, rag=%s',
        $dir,
        $recursive ? 'yes' : 'no',
        $incremental ? 'yes' : 'no',
        $base,
    ));

    $stats = $service->indexDirectory($dir, $recursive, $incremental);

    $log->pass('JOB_RESULT ' . json_encode($stats, JSON_UNESCAPED_UNICODE));

    file_put_contents($donePath, json_encode($stats, JSON_UNESCAPED_UNICODE));

    exit(0);
} catch (\Throwable $e) {
    $log->error('Job failed: ' . $e->getMessage());
    file_put_contents($errorPath, $e->getMessage());

    exit(1);
}
