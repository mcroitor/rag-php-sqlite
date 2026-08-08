<?php

declare(strict_types=1);

namespace App\Web\Services;

use App\Engine\Storage\MetaStorage;
use App\Engine\Utils\DbFactory;

/**
 * Manages background indexing jobs: launches bin/index-worker.php via
 * proc_open and tracks progress through runtime/jobs marker files.
 */
class JobManager
{
    private string $root;
    private string $jobsDir;
    private MetaStorage $meta;

    public function __construct(string $root, MetaStorage $meta)
    {
        $this->root = $root;
        $this->jobsDir = $root . '/runtime/jobs';
        $this->meta = $meta;
    }

    /**
     * Start a background indexing job.
     *
     * @return array{job_id: string}
     */
    public function create(string $dir, bool $recursive = false, bool $incremental = true, string $base = DbFactory::DEFAULT_BASE): array
    {
        $this->ensureJobsDir();

        $base = DbFactory::normalize($base);
        $jobId = $this->generateJobId($base);
        $logPath = $this->jobsDir . '/' . $jobId . '.log';

        file_put_contents($logPath, '[' . date('Y-m-d H:i:s') . "] INFO: Job queued (id=$jobId, rag=$base)" . PHP_EOL, FILE_APPEND);
        $this->meta->recordJob($jobId, $base);

        $worker = $this->root . '/bin/index-worker.php';
        $command = escapeshellarg(PHP_BINARY)
            . ' ' . escapeshellarg($worker)
            . ' --job-id=' . escapeshellarg($jobId)
            . ' --dir=' . escapeshellarg($dir)
            . ' --recursive=' . ($recursive ? '1' : '0')
            . ' --incremental=' . ($incremental ? '1' : '0')
            . ' --rag=' . escapeshellarg($base);

        $stdoutFile = $this->jobsDir . '/' . $jobId . '.stdout.log';
        $stderrFile = $this->jobsDir . '/' . $jobId . '.stderr.log';
        $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';

        $descriptors = [
            0 => ['file', $nullDevice, 'r'],
            1 => ['file', $stdoutFile, 'w'],
            2 => ['file', $stderrFile, 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $this->root);

        if (!is_resource($process)) {
            file_put_contents($this->jobsDir . '/' . $jobId . '.error', 'Failed to start worker process');
            return ['job_id' => $jobId];
        }

        return ['job_id' => $jobId];
    }

    /**
     * Read the current state of a job.
     *
     * @return array{job_id: string, base: string, state: string, progress: int|null, log: list<string>, stats: array<string, mixed>|null, error: string|null}
     */
    public function status(string $jobId): array
    {
        $safeJobId = preg_replace('/[^A-Za-z0-9_\-]/', '', $jobId) ?? $jobId;
        $base = $this->jobsDir . '/' . $safeJobId;

        $logLines = file_exists($base . '.log') ? $this->tail($base . '.log', 200) : [];
        $done = file_exists($base . '.done');
        $error = file_exists($base . '.error');

        $state = $done ? 'done' : ($error ? 'error' : 'running');
        $stats = null;
        $errorMessage = null;

        if ($done) {
            $decoded = json_decode((string) file_get_contents($base . '.done'), true);
            $stats = is_array($decoded) ? $decoded : null;
        } elseif ($error) {
            $errorMessage = trim((string) file_get_contents($base . '.error'));
            $errorMessage = $errorMessage === '' ? 'Unknown error' : $errorMessage;
        }

        $progress = $this->extractProgress($logLines);

        return [
            'job_id' => $jobId,
            'base' => $this->meta->jobBase($jobId) ?? DbFactory::DEFAULT_BASE,
            'state' => $state,
            'progress' => $progress,
            'log' => $logLines,
            'stats' => $stats,
            'error' => $errorMessage,
        ];
    }

    /**
     * List all known jobs (newest first).
     *
     * @return list<array{job_id: string, base: string, state: string, progress: int|null}>
     */
    public function list(): array
    {
        if (!is_dir($this->jobsDir)) {
            return [];
        }

        $jobs = [];
        foreach (glob($this->jobsDir . '/*.log') ?: [] as $logPath) {
            $jobId = basename($logPath, '.log');

            if (str_ends_with($jobId, '.stdout') || str_ends_with($jobId, '.stderr')) {
                continue;
            }

            $status = $this->status($jobId);
            $jobs[] = [
                'job_id' => $status['job_id'],
                'base' => $status['base'],
                'state' => $status['state'],
                'progress' => $status['progress'],
            ];
        }

        usort($jobs, static fn (array $a, array $b): int => strcmp($b['job_id'], $a['job_id']));

        return $jobs;
    }

    /** @param list<string> $logLines */
    private function extractProgress(array $logLines): ?int
    {
        $progress = null;

        foreach ($logLines as $line) {
            if (preg_match('/\[(\d+)%\]/', $line, $matches) === 1) {
                $progress = (int) $matches[1];
            }
        }

        return $progress;
    }

    /** @return list<string> */
    private function tail(string $path, int $maxLines): array
    {
        $content = (string) file_get_contents($path);
        $lines = preg_split('/\r?\n/', $content) ?: [];
        $lines = array_values(array_filter($lines, static fn (string $l): bool => $l !== ''));

        return array_slice($lines, -$maxLines);
    }

    private function ensureJobsDir(): void
    {
        if (!is_dir($this->jobsDir)) {
            mkdir($this->jobsDir, 0777, true);
        }
    }

    private function generateJobId(string $base): string
    {
        $dbFile = DbFactory::path($this->root, $base);
        $seq = (string) (filesize($dbFile) ?: 0) . microtime(true);
        return 'job-' . substr(hash('sha256', $seq), 0, 12);
    }
}
