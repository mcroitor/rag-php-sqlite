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

\Mc\Arguments::Set([
    'confirm' => [
        'short' => 'y',
        'long' => 'confirm',
        'description' => 'Confirm clearing all data',
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
    echo "Clear all indexed data from the database.\n\n";
    echo \Mc\Arguments::Help();
    exit(0);
}

if (!\Mc\Arguments::GetValue('confirm')) {
    $log->warn('This will delete all indexed data. Use --confirm to proceed.');
    exit(1);
}

$root = dirname(__DIR__);
$base = (string) (\Mc\Arguments::GetValue('rag') ?: DbFactory::DEFAULT_BASE);
$dbPath = DbFactory::path($root, $base);

if (!DbFactory::exists($root, $base)) {
    $log->info("No database '$base' found. Nothing to clear.");
    exit(0);
}

$db = DbFactory::pdo($root, $base);

$storage = new SQLiteStorage($db);
$storage->clearAll();

$log->pass("All indexed data cleared from '$base' successfully.");
