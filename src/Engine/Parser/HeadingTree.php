<?php

namespace App\Engine\Parser;

class HeadingTree
{
    /** @var array<int, array{level: int, text: string, content: string, children: array}> */
    private array $nodes = [];

    public function addHeading(int $level, string $text, string $content): void
    {
        $this->nodes[] = [
            'level' => $level,
            'text' => $text,
            'content' => $content,
            'children' => [],
        ];
    }

    /** @return array<int, array{heading_path: string, level: int, content: string}> */
    public function flatten(): array
    {
        $sections = [];
        $stack = [];

        foreach ($this->nodes as $node) {
            while (!empty($stack) && end($stack)['level'] >= $node['level']) {
                array_pop($stack);
            }

            $stack[] = $node;

            $headingPath = implode(' > ', array_map(fn($n) => $n['text'], $stack));

            // Combine parent content with current node content
            $combinedContent = '';
            foreach ($stack as $stackNode) {
                if ($stackNode['content'] !== '') {
                    $combinedContent .= $stackNode['content'] . "\n\n";
                }
            }
            $combinedContent = trim($combinedContent);

            $sections[] = [
                'heading_path' => $headingPath,
                'level' => $node['level'],
                'content' => $combinedContent,
            ];
        }

        return $sections;
    }

    /** @return array<int, array{level: int, text: string, content: string, children: array}> */
    public function getNodes(): array
    {
        return $this->nodes;
    }
}
