<?php

namespace App\Embedding;

class EmbeddingCache
{
    /** @var array<string, list<float>> */
    private array $cache = [];

    /** @return list<float>|null */
    public function get(string $textHash): ?array
    {
        $key = $this->hashKey($textHash);

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        return null;
    }

    /** @param list<float> $vector */
    public function set(string $textHash, array $vector): void
    {
        $key = $this->hashKey($textHash);
        $this->cache[$key] = $vector;
    }

    public function has(string $textHash): bool
    {
        $key = $this->hashKey($textHash);
        return isset($this->cache[$key]);
    }

    private function hashKey(string $textHash): string
    {
        return $textHash;
    }
}
