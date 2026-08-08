<?php

namespace App\Engine\Embedding;

use App\Engine\Core\Exceptions\EmbeddingException;
use App\Engine\Core\Interfaces\EmbeddingProvider;
use App\Engine\Utils\Constant;

class OllamaEmbedding implements EmbeddingProvider
{
    private string $baseUrl;
    private string $model;
    private int $dimension;
    private int $retryCount;
    private int $timeout;
    private int $maxChars;

    public function __construct(
        string $baseUrl = Constant::DEFAULT_OLLAMA_BASE_URL,
        string $model = Constant::OLLAMA_EMBED_MODEL,
        int $dimension = Constant::DEFAULT_EMBED_DIMENSION,
        int $retryCount = Constant::DEFAULT_EMBED_RETRY,
        int $timeout = Constant::DEFAULT_EMBED_TIMEOUT,
        int $maxChars = Constant::DEFAULT_EMBED_MAX_CHARS,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->model = $model;
        $this->dimension = max(1, $dimension);
        $this->retryCount = max(1, $retryCount);
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
        $url = $this->baseUrl . '/api/embed';
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->retryCount; $attempt++) {
            try {
                $http = new \Mc\Http($url);
                $http->SetEncoder('json_encode');
                $http->SetOption(CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                $http->SetOption(CURLOPT_RETURNTRANSFER, true);
                $http->SetOption(CURLOPT_TIMEOUT, $this->timeout);
                $http->SetOption(CURLOPT_CONNECTTIMEOUT, Constant::HTTP_CONNECT_TIMEOUT);

                $response = $http->Post([
                    'model' => $this->model,
                    'input' => $this->truncate($text),
                    'dimensions' => $this->dimension,
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

                if (!isset($data['embeddings']) || !is_array($data['embeddings']) || count($data['embeddings']) === 0) {
                    throw new EmbeddingException("Response missing embedding data");
                }

                $vector = $data['embeddings'][0];
                $this->validateVector($vector);

                return $vector;

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

    /** @param mixed $vector */
    private function validateVector(mixed $vector): void
    {
        if (!is_array($vector)) {
            throw new EmbeddingException('Embedding vector is not an array');
        }
        foreach ($vector as $i => $val) {
            if (!is_numeric($val)) {
                throw new EmbeddingException("Embedding value at index {$i} is not numeric");
            }
        }
        if (count($vector) !== $this->dimension) {
            throw new EmbeddingException(
                "Embedding dimension mismatch: expected {$this->dimension}, got " . count($vector)
            );
        }
    }

    public function getDimension(): int
    {
        return $this->dimension;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    /** @param list<string> $texts @return array<int, list<float>> */
    public function embedBatch(array $texts): array
    {
        $url = $this->baseUrl . '/api/embed';
        $lastException = null;
        // Scale timeout reasonably: base timeout + small overhead per text, capped at 5 minutes
        $effectiveTimeout = min($this->timeout + count($texts) * 2, 300);

        for ($attempt = 1; $attempt <= $this->retryCount; $attempt++) {
            try {
                $http = new \Mc\Http($url);
                $http->SetEncoder('json_encode');
                $http->SetOption(CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                $http->SetOption(CURLOPT_RETURNTRANSFER, true);
                $http->SetOption(CURLOPT_TIMEOUT, $effectiveTimeout);
                $http->SetOption(CURLOPT_CONNECTTIMEOUT, Constant::HTTP_CONNECT_TIMEOUT);

                $response = $http->Post([
                    'model' => $this->model,
                    'input' => $texts,
                    'dimensions' => $this->dimension,
                ]);

                if ($response === false) {
                    throw new EmbeddingException("Batch HTTP request failed (attempt $attempt)");
                }

                $data = json_decode($response, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new EmbeddingException("Failed to decode batch response: " . json_last_error_msg());
                }

                if (!isset($data['embeddings']) || !is_array($data['embeddings'])) {
                    throw new EmbeddingException("Batch response missing embeddings data");
                }

                foreach ($data['embeddings'] as $i => $vector) {
                    if (!is_array($vector)) {
                        throw new EmbeddingException("Batch embedding at index {$i} is not an array");
                    }
                    $this->validateVector($vector);
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
}
