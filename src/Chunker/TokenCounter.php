<?php

namespace App\Chunker;

class TokenCounter
{
    public function count(string $text): int
    {
        $text = trim($text);

        if ($text === '') {
            return 0;
        }

        $wordCount = str_word_count($text, 0, 'UTF-8');
        $punctuationCount = preg_match_all('/[!?,;:.()\[\]{}"\'\x{2013}\x{2014}]/u', $text);

        return $wordCount + $punctuationCount;
    }
}
