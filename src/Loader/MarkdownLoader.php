<?php

namespace App\Loader;

use App\Core\Entities\Document;
use App\Core\Exceptions\ValidationException;

class MarkdownLoader
{
    public function load(string $filePath): Document
    {
        $realPath = realpath($filePath);

        if ($realPath === false || !file_exists($realPath)) {
            throw new ValidationException("File not found: $filePath");
        }

        if (!is_readable($realPath)) {
            throw new ValidationException("File not readable: $filePath");
        }

        $content = $this->readLargeFile($realPath);

        $document = new Document();
        $document->setPath($realPath);
        $document->setHash(md5($content));

        return $document;
    }

    private function readLargeFile(string $path): string
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new ValidationException("Failed to read file: $path");
        }
        return $content;
    }
}
