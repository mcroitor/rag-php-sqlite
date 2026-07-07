<?php

namespace App\Storage;

class VectorSearch
{
    /** @param list<float> $vectorA @param list<float> $vectorB */
    public function cosineSimilarity(array $vectorA, array $vectorB): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $count = count($vectorA);

        for ($i = 0; $i < $count; $i++) {
            $a = (float) $vectorA[$i];
            $b = (float) $vectorB[$i];
            $dotProduct += $a * $b;
            $normA += $a * $a;
            $normB += $b * $b;
        }

        $denominator = sqrt($normA) * sqrt($normB);

        if ($denominator < 1e-10) {
            return 0.0;
        }

        return $dotProduct / $denominator;
    }

    /**
     * @param list<float> $queryVector
     * @param array<int, list<float>> $embeddings
     * @return array<int, array{id: int, score: float}>
     */
    public function search(array $queryVector, array $embeddings, int $topK, float $threshold): array
    {
        $results = [];

        foreach ($embeddings as $id => $vector) {
            $similarity = $this->cosineSimilarity($queryVector, $vector);

            if ($similarity >= $threshold) {
                $results[] = [
                    'id' => $id,
                    'score' => $similarity,
                ];
            }
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $topK);
    }
}
