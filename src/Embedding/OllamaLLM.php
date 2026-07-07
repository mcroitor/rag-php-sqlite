<?php

namespace App\Embedding;

use App\Core\Exceptions\EmbeddingException;
use App\Core\Interfaces\LLMProvider;

class OllamaLLM implements LLMProvider
{
    private string $baseUrl;
    private string $model;
    private float $temperature;
    private int $timeout;
    private int $retryCount;
    /** @var array<string, mixed>|null */
    private ?array $lastResponseMeta = null;

    public function __construct(
        string $baseUrl = 'http://localhost:11434',
        string $model = 'qwen3.5:2b',
        float $temperature = 0.7,
        int $timeout = 120,
        int $retryCount = 3,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->model = $model;
        $this->temperature = $temperature;
        $this->timeout = $timeout;
        $this->retryCount = $retryCount;
    }

    public function generate(string $prompt): string
    {
        $url = $this->baseUrl . '/api/generate';
        $lastException = null;
        $this->lastResponseMeta = null;

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
                    'prompt' => $prompt,
                    'options' => [
                        'temperature' => $this->temperature,
                        'num_predict' => 512,
                    ],
                    'stream' => false,
                ]);

                if ($response === false) {
                    throw new EmbeddingException("LLM HTTP request failed (attempt $attempt)");
                }

                $data = json_decode($response, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new EmbeddingException("Failed to decode LLM response: " . json_last_error_msg());
                }

                if (isset($data['error'])) {
                    throw new EmbeddingException("Ollama error: " . $data['error']);
                }

                $this->lastResponseMeta = [
                    'model' => $data['model'] ?? $this->model,
                    'done' => isset($data['done']) ? (bool) $data['done'] : null,
                    'done_reason' => $data['done_reason'] ?? null,
                    'total_duration' => $data['total_duration'] ?? null,
                    'load_duration' => $data['load_duration'] ?? null,
                    'prompt_eval_count' => $data['prompt_eval_count'] ?? null,
                    'prompt_eval_duration' => $data['prompt_eval_duration'] ?? null,
                    'eval_count' => $data['eval_count'] ?? null,
                    'eval_duration' => $data['eval_duration'] ?? null,
                ];

                $content = '';

                if (isset($data['response']) && is_string($data['response'])) {
                    $content = trim($data['response']);
                } elseif (isset($data['message']['content']) && is_string($data['message']['content'])) {
                    $content = trim($data['message']['content']);
                }

                if ($content === '') {
                    $doneReason = isset($data['done_reason']) ? (string) $data['done_reason'] : 'unknown';
                    throw new EmbeddingException("LLM returned empty response (done_reason: {$doneReason})");
                }

                return $content;

            } catch (EmbeddingException $e) {
                $lastException = $e;
                if ($attempt < $this->retryCount) {
                    $delay = pow(2, $attempt);
                    sleep($delay);
                }
            }
        }

        throw new EmbeddingException(
            "LLM generation failed after {$this->retryCount} attempts: " . $lastException->getMessage()
        );
    }

    /** @return array<string, mixed>|null */
    public function getLastResponseMeta(): ?array
    {
        return $this->lastResponseMeta;
    }
}
