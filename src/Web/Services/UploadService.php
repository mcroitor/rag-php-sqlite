<?php

declare(strict_types=1);

namespace App\Web\Services;

use App\Engine\Core\Exceptions\ValidationException;
use App\Engine\Utils\DbFactory;

/**
 * Handles upload of Markdown files for web-based indexing.
 * Files are stored under runtime/documents/{base}/{uuid}/{filename}.
 */
class UploadService
{
    private string $root;
    private string $base;
    private int $maxSizeBytes;

    public function __construct(string $root, string $base, int $maxSizeBytes = 10 * 1024 * 1024)
    {
        $this->root = rtrim($root, '/\\');
        $this->base = DbFactory::normalize($base);
        $this->maxSizeBytes = $maxSizeBytes;
    }

    /**
     * Returns the base upload directory for the current base.
     */
    public function dir(): string
    {
        return $this->root . DIRECTORY_SEPARATOR
            . 'runtime' . DIRECTORY_SEPARATOR
            . 'documents' . DIRECTORY_SEPARATOR
            . $this->base;
    }

    /**
     * Store uploaded markdown files.
     *
     * @param array<string, mixed> $files  $_FILES['files'] structure
     * @return list<string>  absolute paths of stored files
     */
    public function store(array $files): array
    {
        $stored = [];
        foreach ($this->normalize($files) as $file) {
            $stored[] = $this->storeOne($file);
        }
        return $stored;
    }

    /**
     * Convert $_FILES['files'] (multiple) into a list of file info arrays.
     *
     * @param array{name?: list<string>, tmp_name?: list<string>, error?: list<int>, size?: list<int>} $files
     * @return list<array{name: string, tmp: string, error: int, size: int}>
     */
    private function normalize(array $files): array
    {
        $names = $files['name'] ?? [];
        if ($names === []) {
            throw new ValidationException('No files uploaded');
        }

        $result = [];
        foreach ($files['name'] as $i => $name) {
            $result[] = [
                'name' => (string) $name,
                'tmp' => (string) ($files['tmp_name'][$i] ?? ''),
                'error' => (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($files['size'][$i] ?? 0),
            ];
        }
        return $result;
    }

    /** @param array{name: string, tmp: string, error: int, size: int} $file */
    private function storeOne(array $file): string
    {
        $originalName = basename($file['name']);

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new ValidationException("Upload failed for {$originalName} (error {$file['error']})");
        }

        if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'md') {
            throw new ValidationException("Only Markdown (.md) files are allowed: {$originalName}");
        }

        if ($file['size'] <= 0 || $file['size'] > $this->maxSizeBytes) {
            throw new ValidationException("File too large or empty: {$originalName}");
        }

        $uuid = bin2hex(random_bytes(16));
        $destDir = $this->dir() . DIRECTORY_SEPARATOR . $uuid;

        if (!is_dir($destDir) && !mkdir($destDir, 0777, true) && !is_dir($destDir)) {
            throw new \RuntimeException("Failed to create upload directory: {$destDir}");
        }

        // Sanitize filename
        $safeName = preg_replace('/[^A-Za-z0-9._\- ]/', '_', $originalName);
        if ($safeName === '') {
            $safeName = 'file.md';
        }

        $dest = $destDir . DIRECTORY_SEPARATOR . $safeName;

        if (!move_uploaded_file($file['tmp'], $dest)) {
            throw new \RuntimeException("Failed to store uploaded file: {$originalName}");
        }

        return $dest;
    }
}