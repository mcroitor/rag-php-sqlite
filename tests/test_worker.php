<?php

/**
 * Minimal fake worker for JobManager tests.
 * Mirrors bin/index-worker.php protocol: writes runtime/<id>.log lines and
 * creates a <id>.done marker on completion.
 *
 * Usage: php tests/test_worker.php --job-id=<id> --jobs-dir=<dir>
 */

function arg(array $argv, string $name, mixed $default = null): mixed
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

$jobId = (string) arg($argv, 'job-id', '');

if ($jobId === '') {
    fwrite(STDERR, "Missing --job-id argument\n");
    exit(2);
}

$jobsDir = (string) arg($argv, 'jobs-dir', dirname(__DIR__) . '/runtime/jobs');

if (!is_dir($jobsDir)) {
    mkdir($jobsDir, 0777, true);
}

$logPath = $jobsDir . '/' . $jobId . '.log';

file_put_contents($logPath, "2026-01-01 00:00:00\tINFO: Job started\n", FILE_APPEND);
sleep(1);
file_put_contents($logPath, "2026-01-01 00:00:01\tPASS: Done\n", FILE_APPEND);
file_put_contents($jobsDir . '/' . $jobId . '.done', json_encode(['processed' => 1, 'chunks' => 1]));

exit(0);
