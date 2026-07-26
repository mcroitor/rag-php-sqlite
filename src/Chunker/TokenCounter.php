<?php

namespace App\Chunker;

class TokenCounter
{
    /**
     * Estimate token count for text using improved heuristics.
     * Based on typical BPE tokenizer behavior (GPT, BERT, Qwen, etc.):
     * - English words: ~0.75 tokens per word
     * - CJK characters: ~1 token per character
     * - Numbers: ~1 token per 2-3 digits
     * - Punctuation: often merged with adjacent tokens or separate
     * - Whitespace: usually not counted separately
     */
    public function count(string $text): int
    {
        $text = trim($text);

        if ($text === '') {
            return 0;
        }

        $length = mb_strlen($text, 'UTF-8');

        // Count different character types
        $cjkCount = 0;
        $latinWordCount = 0;
        $cyrillicWordCount = 0;
        $digitCount = 0;
        $punctuationCount = 0;
        $otherCount = 0;

        // Process character by character for accurate classification
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            $codepoint = mb_ord($char, 'UTF-8');

            if ($this->isCJK($codepoint)) {
                $cjkCount++;
            } elseif ($this->isLatinLetter($codepoint)) {
                $latinWordCount++;
            } elseif ($this->isCyrillicLetter($codepoint)) {
                $cyrillicWordCount++;
            } elseif ($this->isDigit($codepoint)) {
                $digitCount++;
            } elseif ($this->isPunctuation($codepoint)) {
                $punctuationCount++;
            } else {
                $otherCount++;
            }
        }

        // Estimate tokens based on character type distribution
        // These ratios are approximations based on common BPE tokenizers
        $tokens = 0;

        // CJK: ~1 token per character (each char often its own token)
        $tokens += $cjkCount;

        // Latin words: ~0.75 tokens per "word unit" (groups of letters)
        // We estimate words by counting transitions from letter to non-letter
        $latinWords = $this->estimateWordCount($text, 'latin');
        $tokens += (int) ceil($latinWords * 0.75);

        // Cyrillic words: ~1 token per word (Cyrillic often splits more)
        $cyrillicWords = $this->estimateWordCount($text, 'cyrillic');
        $tokens += $cyrillicWords;

        // Digits: ~1 token per 2-3 digits
        $tokens += (int) ceil($digitCount / 2.5);

        // Punctuation: often merged, estimate ~0.3 tokens per punctuation char
        $tokens += (int) ceil($punctuationCount * 0.3);

        // Other (emoji, symbols, etc.): ~1 token each
        $tokens += $otherCount;

        // Minimum 1 token for non-empty text
        return max(1, $tokens);
    }

    private function isCJK(int $codepoint): bool
    {
        // CJK Unified Ideographs
        if ($codepoint >= 0x4E00 && $codepoint <= 0x9FFF) return true;
        // CJK Unified Ideographs Extension A
        if ($codepoint >= 0x3400 && $codepoint <= 0x4DBF) return true;
        // CJK Unified Ideographs Extension B
        if ($codepoint >= 0x20000 && $codepoint <= 0x2A6DF) return true;
        // CJK Compatibility Ideographs
        if ($codepoint >= 0xF900 && $codepoint <= 0xFAFF) return true;
        // CJK Symbols and Punctuation
        if ($codepoint >= 0x3000 && $codepoint <= 0x303F) return true;
        // Hiragana
        if ($codepoint >= 0x3040 && $codepoint <= 0x309F) return true;
        // Katakana
        if ($codepoint >= 0x30A0 && $codepoint <= 0x30FF) return true;
        // Katakana Phonetic Extensions
        if ($codepoint >= 0x31F0 && $codepoint <= 0x31FF) return true;
        // Hangul Syllables
        if ($codepoint >= 0xAC00 && $codepoint <= 0xD7AF) return true;
        // Hangul Jamo
        if ($codepoint >= 0x1100 && $codepoint <= 0x11FF) return true;
        // Hangul Compatibility Jamo
        if ($codepoint >= 0x3130 && $codepoint <= 0x318F) return true;

        return false;
    }

    private function isLatinLetter(int $codepoint): bool
    {
        // Basic Latin (ASCII letters)
        if (($codepoint >= 0x41 && $codepoint <= 0x5A) || ($codepoint >= 0x61 && $codepoint <= 0x7A)) return true;
        // Latin-1 Supplement
        if ($codepoint >= 0xC0 && $codepoint <= 0xFF) return true;
        // Latin Extended-A
        if ($codepoint >= 0x100 && $codepoint <= 0x17F) return true;
        // Latin Extended-B
        if ($codepoint >= 0x180 && $codepoint <= 0x24F) return true;

        return false;
    }

    private function isCyrillicLetter(int $codepoint): bool
    {
        // Cyrillic
        if ($codepoint >= 0x0400 && $codepoint <= 0x04FF) return true;
        // Cyrillic Supplement
        if ($codepoint >= 0x0500 && $codepoint <= 0x052F) return true;
        // Cyrillic Extended-A
        if ($codepoint >= 0x2DE0 && $codepoint <= 0x2DFF) return true;
        // Cyrillic Extended-B
        if ($codepoint >= 0xA640 && $codepoint <= 0xA69F) return true;

        return false;
    }

    private function isDigit(int $codepoint): bool
    {
        return $codepoint >= 0x30 && $codepoint <= 0x39;
    }

    private function isPunctuation(int $codepoint): bool
    {
        // Common punctuation ranges
        if ($codepoint >= 0x20 && $codepoint <= 0x2F) return true;  // !"#$%&'()*+,-./
        if ($codepoint >= 0x3A && $codepoint <= 0x40) return true;  // :;<=>?@
        if ($codepoint >= 0x5B && $codepoint <= 0x60) return true;  // [\]^_`
        if ($codepoint >= 0x7B && $codepoint <= 0x7E) return true;  // {|}~
        // General Punctuation
        if ($codepoint >= 0x2000 && $codepoint <= 0x206F) return true;
        // CJK punctuation already handled in isCJK

        return false;
    }

    private function estimateWordCount(string $text, string $script): int
    {
        $length = mb_strlen($text, 'UTF-8');
        if ($length === 0) {
            return 0;
        }

        $count = 0;
        $inWord = false;

        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            $codepoint = mb_ord($char, 'UTF-8');

            $isLetter = match ($script) {
                'latin' => $this->isLatinLetter($codepoint),
                'cyrillic' => $this->isCyrillicLetter($codepoint),
                default => false,
            };

            if ($isLetter && !$inWord) {
                $inWord = true;
                $count++;
            } elseif (!$isLetter) {
                $inWord = false;
            }
        }

        return $count;
    }
}