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
    'id' => [
        'long' => 'id',
        'description' => 'Document id to remove',
        'required' => false,
        'default' => '',
    ],
    'path' => [
        'long' => 'path',
        'description' => 'Document path to remove',
        'required' => false,
        'default' => '',
    ],
    'confirm' => [
        'short' => 'y',
        'long' => 'confirm',
        'description' => 'Confirm removal',
        'required' => false,
    ],
    'rag' => [
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

$argvList = $_SERVER['argv'] ?? [];
if (in_array('-h', $argvList, true) || in_array('--help', $argvList, true)) {
    echo "Remove an indexed document and all of its chunks/embeddings.\n\n";
    echo "Examples:\n";
    echo "  php bin/remove.php --id=42 --confirm\n";
    echo "  php bin/remove.php --path=/path/to/doc.md --confirm --rag=test\n";
    echo "  php bin/list.php  # list documents to find the id\n\n";
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
$id = \Mc\Arguments::GetValue('id');
$path = \Mc\Arguments::GetValue('path');

if (($id === null || $id === '') && ($path === null || $path === '')) {
    $log->error('Provide --id=<id> or --path=<path> to remove a document.');
    exit(1);
}

if (!DbFactory::exists($root, $base)) {
    $log->error("Database '$base' not found. Run 'php bin/setup.php --rag=$base' first.");
    exit(1);
}

$db = DbFactory::pdo($root, $base);
$storage = new SQLiteStorage($db);

$target = null;

if ($id !== null && $id !== '') {
    $target = [
        'id' => (int) $id,
        'path' => $storage->getDocumentPathById((int) $id),
    ];

    if ($target['path'] === '') {
        $log->error("Document with id=$id not found in '$base'.");
        exit(1);
    }
} else {
    $target = [
        'id' => $storage->getDocumentIdByPath((string) $path),
        'path' => (string) $path,
    ];

    if ($target['id'] === null) {
        $log->error("Document not found: {$target['path']}");
        exit(1);
    }
}

if (!\Mc\Arguments::GetValue('confirm')) {
    $log->warn("About to remove document #{$target['id']} from '$base':");
    $log->warn("  {$target['path']}");
    $log->warn('This will delete its chunks and embeddings. Use --confirm to proceed.');
    exit(1);
}

$removed = $storage->deleteDocumentById($target['id']);

if ($removed) {
    $log->pass("Removed document #{$target['id']}: {$target['path']}");
} else {
    $log->error("Failed to remove document #{$target['id']}.");
    exit(1);
}
