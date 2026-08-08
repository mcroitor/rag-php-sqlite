<?php

namespace App\Engine\Loader;

use App\Engine\Core\Exceptions\ValidationException;

class FileScanner
{
    /** @param string[] $ignorePatterns */
    public function __construct(
        private array $ignorePatterns = [],
        private ?string $baseDir = null,
    ) {
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
        $baseDir = $this->baseDir ?? $this->detectBaseDir();

        if ($baseDir === null) {
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

    private function detectBaseDir(): ?string
    {
        $dir = __DIR__;

        while ($dir !== dirname($dir)) {
            if (file_exists($dir . '/composer.json') || file_exists($dir . '/vendor/autoload.php')) {
                return realpath($dir) ?: null;
            }

            $dir = dirname($dir);
        }

        return null;
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
