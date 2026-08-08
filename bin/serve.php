<?php

require_once __DIR__ . '/../vendor/autoload.php';

$host = '127.0.0.1';
$port = 8000;

$argv = $_SERVER['argv'] ?? [];

if (isset($argv[1]) && is_string($argv[1]) && $argv[1] !== '') {
    $parts = explode(':', $argv[1]);

    if ($parts[0] !== '') {
        $host = $parts[0];
    }

    if (isset($parts[1]) && is_numeric($parts[1])) {
        $port = (int) $parts[1];
    }
}

$root = dirname(__DIR__);
$docRoot = $root . '/public';
$router = $root . '/public/index.php';

echo "RAG-PHP-SQLite Web UI\n";
echo "  URL:      http://{$host}:{$port}\n";
echo "  Doc root: {$docRoot}\n";
echo "  Press Ctrl+C to stop.\n\n";

$server = $host . ':' . $port;

$cmd = escapeshellarg(PHP_BINARY)
    . ' -S ' . escapeshellarg($server)
    . ' -t ' . escapeshellarg($docRoot)
    . ' ' . escapeshellarg($router);

passthru($cmd);
