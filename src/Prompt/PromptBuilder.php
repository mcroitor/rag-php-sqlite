<?php

namespace App\Prompt;

use App\Core\Entities\RetrievalResult;
use App\Utils\Constant;

class PromptBuilder
{
    private ContextWindow $contextWindow;
    private int $maxContextTokens;

    public function __construct(?ContextWindow $contextWindow = null, int $maxContextTokens = Constant::DEFAULT_MAX_CONTEXT_TOKENS)
    {
        $this->contextWindow = $contextWindow ?? new ContextWindow($maxContextTokens);
        $this->maxContextTokens = $maxContextTokens;
    }

    /** @param RetrievalResult[] $results */
    public function build(string $query, array $results): string
    {
        $fitted = $this->contextWindow->fit($results, $this->maxContextTokens);

        $context = '';
        $seenHashes = [];

        foreach ($fitted as $result) {
            $chunk = $result->getChunk();
            $hash = $chunk->getHash();

            if (isset($seenHashes[$hash])) {
                continue;
            }
            $seenHashes[$hash] = true;

            $sourcePath = $result->getSourcePath();
            $headingPath = $result->getHeadingPath();

            $context .= "SOURCE:\n";
            $context .= "{$sourcePath} > {$headingPath}\n\n";
            $context .= "TEXT:\n";
            $context .= $chunk->getText() . "\n\n";
        }

        $prompt = "Answer the following question based on the provided context.\n\n";
        $prompt .= "Context:\n" . trim($context) . "\n\n";
        $prompt .= "Question: {$query}\n";
        $prompt .= "Answer:";

        return $prompt;
    }
}
