<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Embedding\OllamaEmbedding;
use App\Embedding\OllamaLLM;
use App\Prompt\PromptBuilder;
use App\Retrieval\VectorRetriever;
use App\Services\RAGService;
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
        'description' => 'Question text',
        'required' => true,
    ],
    'top-k' => [
        'short' => 'k',
        'long' => 'top-k',
        'description' => 'Number of context results (default: 5)',
        'required' => false,
        'default' => null,
    ],
    'debug-llm' => [
        'short' => 'd',
        'long' => 'debug-llm',
        'description' => 'Print raw LLM generation metadata',
        'required' => false,
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
    echo "Ask questions using RAG (retrieval + LLM).\n\n";
    echo "Examples:\n";
    echo "  php bin/ask.php -q \"What is std::vector?\"\n";
    echo "  php bin/ask.php -q \"What is Docker Compose?\" -k 8\n";
    echo "  php bin/ask.php -q \"What is Docker Compose?\" --debug-llm\n\n";
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
$query = (string) \Mc\Arguments::GetValue('query');
$debugLlm = (bool) \Mc\Arguments::GetValue('debug-llm');

$embedding = new OllamaEmbedding(
    baseUrl: $config->getOllamaBaseUrl(),
    model: $config->getEmbeddingModel(),
    dimension: $config->getEmbeddingDimension(),
    retryCount: $config->getRetryCount(),
);

$storage = new SQLiteStorage(__DIR__ . '/../rag.sqlite');
$llm = new OllamaLLM(
    baseUrl: $config->getOllamaBaseUrl(),
    model: $config->getLlmModel(),
    temperature: $config->getTemperature(),
);

$retriever = new VectorRetriever($embedding, $storage, $topK, $config->getThreshold());
$promptBuilder = new PromptBuilder(maxContextTokens: 4096);
$rag = new RAGService($retriever, $promptBuilder, $llm);

$log->info("Asking: $query");

try {
    $context = $rag->getContext($query, $topK);

    if (empty($context)) {
        $log->warn('No relevant context found for this query.');
        $log->warn('Try lowering retrieval.threshold in config/config.yaml (e.g. 0.45-0.65) and re-index if needed.');
        echo "No results found.\n";
        exit(0);
    }

    $answer = $rag->ask($query, $topK);

    if ($debugLlm) {
        $meta = $llm->getLastResponseMeta();
        if ($meta !== null) {
            echo "\nLLM debug:\n";
            echo json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
    }

    if (trim($answer) === '') {
        $log->warn('LLM returned an empty answer.');
        echo "No answer generated.\n";
        exit(0);
    }

    echo $answer . PHP_EOL;

    echo "\nSources:\n";
    foreach ($context as $i => $result) {
        $relevance = number_format($result->getScore() * 100, 2);
        echo ($i + 1) . ". Relevance: {$relevance}% | Source: " . $result->getSourcePath() . " | Heading: " . $result->getHeadingPath() . "\n";
    }
} catch (\Throwable $e) {
    if ($debugLlm) {
        $meta = $llm->getLastResponseMeta();
        if ($meta !== null) {
            echo "\nLLM debug:\n";
            echo json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    $log->error("RAG error: " . $e->getMessage());
    exit(1);
}
