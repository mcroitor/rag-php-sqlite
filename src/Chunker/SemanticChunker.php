<?php

namespace App\Chunker;

use App\Core\Entities\Chunk;
use App\Core\Entities\Document;
use App\Core\Interfaces\ChunkerInterface;
use App\Utils\Constant;

class SemanticChunker implements ChunkerInterface
{
    private int $effectiveMaxTokens;
    private int $overlap;
    private TokenCounter $counter;

    public function __construct(int $maxTokens = Constant::DEFAULT_EMBED_MAX_TOKENS, int $overlap = Constant::DEFAULT_EMBED_OVERLAP, int $safetyMargin = Constant::DEFAULT_EMBED_SAFETY_MARGIN)
    {
        $this->effectiveMaxTokens = max(1, $maxTokens - $safetyMargin);
        $this->overlap = $overlap;
        $this->counter = new TokenCounter();
    }

    /** @return Chunk[] */
    public function chunk(Document $document): array
    {
        $path = $document->getPath();
        if (!file_exists($path) || !is_readable($path)) {
            return [];
        }
        $content = file_get_contents($path);
        if ($content === false) {
            return [];
        }
        return $this->chunkText($content, $document);
    }

    /** @return Chunk[] */
    public function chunkText(string $text, ?Document $document = null): array
    {
        $sections = $this->splitByHeaders($text);
        $chunks = [];

        foreach ($sections as $section) {
            $sectionChunks = $this->processSection($section);
            foreach ($sectionChunks as $chunkData) {
                $chunk = new Chunk();
                $chunk->setHeadingPath($chunkData['heading_path'] ?? '');

                $chunk->setHash(md5($chunkData['text']));

                if ($document !== null) {
                    $chunk->setDocumentId($document->getId());
                }

                $chunk->setText($chunkData['text']);
                $chunk->setTokenCount($chunkData['token_count']);

                if ($document !== null) {
                    $chunk->setDocumentHash($document->getHash());
                }

                $chunks[] = $chunk;
            }
        }

        return $chunks;
    }

    /** @return array<int, array{heading: string, level: int, content: string}> */
    private function splitByHeaders(string $text): array
    {
        $lines = explode("\n", $text);
        $sections = [];
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

            if (preg_match('/^(#{1,3})\s+(.+)$/', trim($line), $matches)) {
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

    /** @param array{heading: string, level: int, content: string} $section @return array<int, array{text: string, token_count: int, heading_path: string}> */
    private function processSection(array $section): array
    {
        $text = $section['content'];
        $heading = $section['heading'];
        $headingPath = $heading;

        if ($this->counter->count($text) <= $this->effectiveMaxTokens) {
            return [
                [
                    'text' => $text,
                    'token_count' => $this->counter->count($text),
                    'heading_path' => $headingPath,
                ],
            ];
        }

        return $this->splitByParagraphs($text, $headingPath);
    }

    /** @return array<int, array{text: string, token_count: int, heading_path: string}> */
    private function splitByParagraphs(string $text, string $headingPath): array
    {
        $paragraphs = preg_split('/\n\s*\n/', $text);
        $chunks = [];
        $currentChunk = '';
        $currentTokens = 0;

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            $paraTokens = $this->counter->count($paragraph);

            if ($paraTokens > $this->effectiveMaxTokens) {
                if ($currentChunk !== '') {
                    $chunks[] = [
                        'text' => trim($currentChunk),
                        'token_count' => $currentTokens,
                        'heading_path' => $headingPath,
                    ];
                    $currentChunk = '';
                    $currentTokens = 0;
                }

                $splitChunks = $this->splitBySentences($paragraph, $headingPath);
                $chunks = array_merge($chunks, $splitChunks);
                continue;
            }

            if ($currentTokens + $paraTokens > $this->effectiveMaxTokens) {
                $chunks[] = [
                    'text' => trim($currentChunk),
                    'token_count' => $currentTokens,
                    'heading_path' => $headingPath,
                ];
                $currentChunk = $this->overlap > 0
                    ? $this->getOverlapText($currentChunk)
                    : '';
                $currentTokens = $this->counter->count($currentChunk);
            }

            $currentChunk .= $paragraph . "\n\n";
            $currentTokens += $paraTokens;
        }

        if ($currentChunk !== '') {
            $chunks[] = [
                'text' => trim($currentChunk),
                'token_count' => $currentTokens,
                'heading_path' => $headingPath,
            ];
        }

        return $chunks;
    }

    /** @return array<int, array{text: string, token_count: int, heading_path: string}> */
    private function splitBySentences(string $text, string $headingPath): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', $text);
        $chunks = [];
        $currentChunk = '';
        $currentTokens = 0;

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '') {
                continue;
            }

            $sentTokens = $this->counter->count($sentence);

            if ($sentTokens > $this->effectiveMaxTokens) {
                if ($currentChunk !== '') {
                    $chunks[] = [
                        'text' => trim($currentChunk),
                        'token_count' => $currentTokens,
                        'heading_path' => $headingPath,
                    ];
                    $currentChunk = '';
                    $currentTokens = 0;
                }

                $splitChunks = $this->splitByChars($sentence, $headingPath);
                $chunks = array_merge($chunks, $splitChunks);
                continue;
            }

            if ($currentTokens + $sentTokens > $this->effectiveMaxTokens) {
                $chunks[] = [
                    'text' => trim($currentChunk),
                    'token_count' => $currentTokens,
                    'heading_path' => $headingPath,
                ];
                $currentChunk = $this->overlap > 0
                    ? $this->getOverlapText($currentChunk)
                    : '';
                $currentTokens = $this->counter->count($currentChunk);
            }

            $currentChunk .= $sentence . ' ';
            $currentTokens += $sentTokens;
        }

        if ($currentChunk !== '') {
            $chunks[] = [
                'text' => trim($currentChunk),
                'token_count' => $currentTokens,
                'heading_path' => $headingPath,
            ];
        }

        return $chunks;
    }

    /** @return array<int, array{text: string, token_count: int, heading_path: string}> */
    private function splitByChars(string $text, string $headingPath): array
    {
        $chunks = [];
        $avgCharPerToken = Constant::AVG_CHAR_PER_TOKEN;
        $maxChars = $this->effectiveMaxTokens * $avgCharPerToken;
        $textLength = mb_strlen($text);

        for ($i = 0; $i < $textLength; $i += $maxChars) {
            $part = mb_substr($text, $i, $maxChars);
            $chunks[] = [
                'text' => trim($part),
                'token_count' => $this->counter->count($part),
                'heading_path' => $headingPath,
            ];
        }

        return $chunks;
    }

    private function getOverlapText(string $text): string
    {
        $words = preg_split('/\s+/', trim($text));
        $overlapWords = (int) ($this->overlap / 2);

        if ($overlapWords <= 0 || count($words) <= $overlapWords) {
            return '';
        }

        return implode(' ', array_slice($words, -$overlapWords)) . "\n\n";
    }
}
