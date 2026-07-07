<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Embedding\OllamaEmbedding;
use App\Services\QueryService;
use App\Storage\SQLiteStorage;
use App\Utils\AppLogger;
use App\Utils\Config;

set_error_handler(function ($severity, $message, $file, $line) {
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

$log = AppLogger::instance();

try {
    $config = new Config(__DIR__ . '/../config/config.yaml');
} catch (\Throwable $e) {
    $log->error($e->getMessage());
    exit(1);
}

\Mc\Arguments::Set([
    'query' => [
        'short' => 'q',
        'long' => 'query',
        'description' => 'Search text',
        'required' => true,
    ],
    'top-k' => [
        'short' => 'k',
        'long' => 'top-k',
        'description' => 'Number of results (default: 5)',
        'required' => false,
        'default' => null,
    ],
    'format' => [
        'short' => 'f',
        'long' => 'format',
        'description' => 'Output format: text, json (default: text)',
        'required' => false,
        'default' => 'text',
    ],
    'help' => [
        'short' => 'h',
        'long' => 'help',
        'description' => 'Show help',
        'required' => false,
    ],
]);

$args = isset($argv) ? $argv : [];
if (in_array('-h', $args, true) || in_array('--help', $args, true)) {
    echo "Search indexed documents using vector retrieval.\n\n";
    echo "Examples:\n";
    echo "  php bin/query.php -q \"std::vector\"\n";
    echo "  php bin/query.php -q \"docker compose\" -k 10 -f json\n";
    echo "  php bin/ask.php -q \"What is std::vector?\"\n\n";
    echo \Mc\Arguments::Help();
    exit(0);
}

try {
    \Mc\Arguments::Parse();
} catch (\InvalidArgumentException $e) {
    echo $e->getMessage() . "\n";
    echo \Mc\Arguments::Help();
    exit(1);
}

$topK = (int) (\Mc\Arguments::GetValue('top-k') ?: $config->getTopK());
$format = \Mc\Arguments::GetValue('format') ?: 'text';
$query = (string) \Mc\Arguments::GetValue('query');

$embedding = new OllamaEmbedding(
    baseUrl: $config->getOllamaBaseUrl(),
    model: $config->getEmbeddingModel(),
    dimension: $config->getEmbeddingDimension(),
    retryCount: $config->getRetryCount(),
);

$storage = new SQLiteStorage(__DIR__ . '/../rag.sqlite');

$service = new QueryService($embedding, $storage, $topK, $config->getThreshold());

$log->info("Searching: $query");

try {
    $results = $service->search($query, $topK);
} catch (\Throwable $e) {
    $log->error("Search error: " . $e->getMessage());
    exit(1);
}

if ($format === 'json') {
    $output = [];
    foreach ($results as $result) {
        $output[] = [
            'score' => $result->getScore(),
            'relevance' => $result->getScore(),
            'source' => $result->getSourcePath(),
            'heading' => $result->getHeadingPath(),
            'text' => $result->getChunk()->getText(),
        ];
    }
    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    if (empty($results)) {
        echo "No results found.\n";
    } else {
        foreach ($results as $i => $result) {
            echo "--- Result " . ($i + 1) . " (score: " . number_format($result->getScore(), 4) . ") ---\n";
            echo "Source: " . $result->getSourcePath() . "\n";
            echo "Relevance: " . number_format($result->getScore() * 100, 2) . "%\n";
            echo "Heading: " . $result->getHeadingPath() . "\n";
            echo $result->getChunk()->getText() . "\n\n";
        }
    }
}
