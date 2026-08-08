<?php

namespace App\Engine\Parser;

use App\Engine\Core\Entities\Document;
use App\Engine\Core\Exceptions\ValidationException;

class MarkdownParser
{
    private const HEADER_PATTERN = '/^(#{1,3})\s+(.+)$/m';

    /** @return array<int, array{heading: string, level: int, content: string}> */
    public function parse(Document $document): array
    {
        $path = $document->getPath();

        if (!file_exists($path)) {
            throw new ValidationException("Document file not found: $path");
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new ValidationException("Failed to read document: $path");
        }

        return $this->parseContent($content);
    }

    /** @return array<int, array{heading: string, level: int, content: string}> */
    public function parseContent(string $content): array
    {
        $sections = [];
        $lines = explode("\n", $content);
        $currentHeading = '';
        $currentLevel = 0;
        $currentContent = '';
        $inCodeBlock = false;

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '```')) {
                $inCodeBlock = !$inCodeBlock;
                $currentContent .= $line . "\n";
                continue;
            }

            if ($inCodeBlock) {
                $currentContent .= $line . "\n";
                continue;
            }

            if (preg_match(self::HEADER_PATTERN, $line, $matches)) {
                if ($currentContent !== '' || $currentHeading !== '') {
                    $sections[] = [
                        'heading' => $currentHeading,
                        'level' => $currentLevel,
                        'content' => trim($currentContent),
                    ];
                }

                $currentLevel = strlen($matches[1]);
                $currentHeading = trim($matches[2]);
                $currentContent = '';
            } else {
                $currentContent .= $line . "\n";
            }
        }

        if ($currentContent !== '' || $currentHeading !== '') {
            $sections[] = [
                'heading' => $currentHeading,
                'level' => $currentLevel,
                'content' => trim($currentContent),
            ];
        }

        return $sections;
    }
}
