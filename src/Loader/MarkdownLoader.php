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

        $content = file_get_contents($realPath);

        if ($content === false) {
            throw new ValidationException("Failed to read file: $filePath");
        }

        $document = new Document();
        $document->setPath($realPath);
        $document->setHash(md5($content));

        return $document;
    }
}
