<?php

namespace App\Embedding;

use App\Core\Exceptions\EmbeddingException;
use App\Core\Interfaces\EmbeddingProvider;

class OllamaEmbedding implements EmbeddingProvider
{
    private string $baseUrl;
    private string $model;
    private int $dimension;
    private int $retryCount;
    private int $timeout;
    private int $maxChars;

    public function __construct(
        string $baseUrl = 'http://localhost:11434',
        string $model = 'nomic-embed-text',
        int $dimension = 768,
        int $retryCount = 3,
        int $timeout = 30,
        int $maxChars = 24000,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->model = $model;
        $this->dimension = $dimension;
        $this->retryCount = $retryCount;
        $this->timeout = $timeout;
        $this->maxChars = $maxChars;
    }

    private function truncate(string $text): string
    {
        if (mb_strlen($text) <= $this->maxChars) {
            return $text;
        }

        return mb_substr($text, 0, $this->maxChars);
    }

    /** @return list<float> */
    public function embed(string $text): array
    {
        $url = $this->baseUrl . '/api/embeddings';
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->retryCount; $attempt++) {
            try {
                $http = new \Mc\Http($url);
                $http->SetEncoder('json_encode');
                $http->SetOption(CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                $http->SetOption(CURLOPT_RETURNTRANSFER, true);
                $http->SetOption(CURLOPT_TIMEOUT, $this->timeout);
                $http->SetOption(CURLOPT_CONNECTTIMEOUT, 10);

                $response = $http->Post([
                    'model' => $this->model,
                    'prompt' => $this->truncate($text),
                ]);

                if ($response === false) {
                    throw new EmbeddingException("HTTP request failed (attempt $attempt)");
                }

                $data = json_decode($response, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new EmbeddingException("Failed to decode response: " . json_last_error_msg());
                }

                if (isset($data['error'])) {
                    throw new EmbeddingException("Ollama error: " . $data['error']);
                }

                if (!isset($data['embedding'])) {
                    throw new EmbeddingException("Response missing embedding data");
                }

                return $data['embedding'];

            } catch (EmbeddingException $e) {
                $lastException = $e;
                if ($attempt < $this->retryCount) {
                    $delay = pow(2, $attempt);
                    sleep($delay);
                }
            }
        }

        throw new EmbeddingException(
            "Embedding failed after {$this->retryCount} attempts: " . $lastException->getMessage()
        );
    }

    /** @param list<string> $texts @return array<int, list<float>> */
    public function embedBatch(array $texts): array
    {
        $url = $this->baseUrl . '/api/embed';
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->retryCount; $attempt++) {
            try {
                $http = new \Mc\Http($url);
                $http->SetEncoder('json_encode');
                $http->SetOption(CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                $http->SetOption(CURLOPT_RETURNTRANSFER, true);
                $http->SetOption(CURLOPT_TIMEOUT, $this->timeout * count($texts));
                $http->SetOption(CURLOPT_CONNECTTIMEOUT, 10);

                $response = $http->Post([
                    'model' => $this->model,
                    'input' => $texts,
                ]);

                if ($response === false) {
                    throw new EmbeddingException("Batch HTTP request failed (attempt $attempt)");
                }

                $data = json_decode($response, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new EmbeddingException("Failed to decode batch response: " . json_last_error_msg());
                }

                if (!isset($data['embeddings'])) {
                    throw new EmbeddingException("Batch response missing embeddings data");
                }

                return $data['embeddings'];

            } catch (EmbeddingException $e) {
                $lastException = $e;
                if ($attempt < $this->retryCount) {
                    $delay = pow(2, $attempt);
                    sleep($delay);
                }
            }
        }

        throw new EmbeddingException(
            "Batch embedding failed after {$this->retryCount} attempts: " . $lastException->getMessage()
        );
    }

    public function getDimension(): int
    {
        return $this->dimension;
    }

    public function getModel(): string
    {
        return $this->model;
    }
}
