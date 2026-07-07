<?php

namespace App\Loader;

use App\Core\Exceptions\ValidationException;

class FileScanner
{
    /** @param string[] $ignorePatterns */
    public function __construct(private array $ignorePatterns = [])
    {
    }

    /** @return string[] */
    public function scan(string $directory, bool $recursive = false): array
    {
        $realPath = realpath($directory);

        if ($realPath === false) {
            throw new ValidationException("Directory not found: $directory");
        }

        $this->validatePath($realPath);

        $files = [];
        $iterator = $recursive
            ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($realPath))
            : new \FilesystemIterator($realPath);

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            if ($file->isDir()) {
                continue;
            }

            if ($file->getExtension() !== 'md') {
                continue;
            }

            $filePath = $file->getRealPath();

            if ($filePath === false) {
                continue;
            }

            if ($this->isIgnored($filePath)) {
                continue;
            }

            $files[] = $filePath;
        }

        sort($files);
        return $files;
    }

    private function validatePath(string $path): void
    {
        $baseDir = realpath(__DIR__ . '/../..');

        if ($baseDir === false) {
            return;
        }

        if (str_starts_with($path, $baseDir)) {
            return;
        }

        if (str_starts_with($path, sys_get_temp_dir())) {
            return;
        }

        throw new ValidationException("Path traversal detected: $path");
    }

    private function isIgnored(string $path): bool
    {
        foreach ($this->ignorePatterns as $pattern) {
            if (str_contains($path, $pattern)) {
                return true;
            }

            if (fnmatch($pattern, basename($path), FNM_NOESCAPE)) {
                return true;
            }
        }

        return false;
    }
}
