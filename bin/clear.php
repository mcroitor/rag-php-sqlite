<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Storage\SQLiteStorage;
use App\Utils\AppLogger;
use App\Utils\Config;
use App\Utils\Constant;

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

$dbPath = __DIR__ . '/../' . Constant::DEFAULT_DB_FILENAME;

if (!file_exists($dbPath)) {
    $log->info('No database found. Nothing to clear.');
    exit(0);
}

$db = new \PDO("sqlite:" . $dbPath);

$storage = new SQLiteStorage($db);
$storage->clearAll();

$log->pass('All indexed data cleared successfully.');
