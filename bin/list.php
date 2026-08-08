<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Engine\Storage\SQLiteStorage;
use App\Engine\Utils\AppLogger;
use App\Engine\Utils\Config;
use App\Engine\Utils\DbFactory;

$log = AppLogger::instance();

try {
    $config = new Config(__DIR__ . '/../config/config.yaml');
} catch (\Throwable $e) {
    $log->error($e->getMessage());
    exit(1);
}

\Mc\Arguments::Set([
    'rag' => [
        'long' => 'rag',
        'description' => 'RAG database name (default: rag)',
        'required' => false,
        'default' => DbFactory::DEFAULT_BASE,
    ],
    'limit' => [
        'long' => 'limit',
        'description' => 'Maximum number of documents to list (default: 50)',
        'required' => false,
        'default' => '50',
    ],
    'offset' => [
        'long' => 'offset',
        'description' => 'Skip N documents (default: 0)',
        'required' => false,
        'default' => '0',
    ],
    'search' => [
        'long' => 'search',
        'description' => 'Filter by substring of the document path',
        'required' => false,
        'default' => '',
    ],
    'format' => [
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

$argvList = $_SERVER['argv'] ?? [];
if (in_array('-h', $argvList, true) || in_array('--help', $argvList, true)) {
    echo "List indexed documents.\n\n";
    echo "Examples:\n";
    echo "  php bin/list.php\n";
    echo "  php bin/list.php --rag=test\n";
    echo "  php bin/list.php --limit=100 --offset=20\n";
    echo "  php bin/list.php --search=src/docs --format=json\n\n";
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

$root = dirname(__DIR__);
$base = (string) (\Mc\Arguments::GetValue('rag') ?: DbFactory::DEFAULT_BASE);
$limit = max(1, (int) (\Mc\Arguments::GetValue('limit') ?: 50));
$offset = max(0, (int) (\Mc\Arguments::GetValue('offset') ?: 0));
$search = (string) (\Mc\Arguments::GetValue('search') ?: '');
$format = (string) (\Mc\Arguments::GetValue('format') ?: 'text');

if (!DbFactory::exists($root, $base)) {
    $log->error("Database '$base' not found. Run 'php bin/setup.php --rag=$base' first.");
    exit(1);
}

$db = DbFactory::pdo($root, $base);
$storage = new SQLiteStorage($db);

$documents = $storage->listDocuments($limit, $offset);

if ($search !== '') {
    $documents = array_values(array_filter(
        $documents,
        static fn (array $doc): bool => str_contains(strtolower($doc['path']), strtolower($search)),
    ));
}

$total = $storage->getDocumentCount();

if ($format === 'json') {
    echo json_encode([
        'base' => $base,
        'total' => $total,
        'count' => count($documents),
        'offset' => $offset,
        'documents' => $documents,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

echo "Documents in '$base' (total: $total, showing " . count($documents) . ")\n";
echo str_repeat('-', 40) . "\n";

if (count($documents) === 0) {
    echo "No documents found.\n";
    exit(0);
}

foreach ($documents as $doc) {
    echo "  [#{$doc['id']}] {$doc['path']}";
    echo " (chunks: {$doc['chunks']})";
    echo " {$doc['created_at']}\n";
}
