<?php

namespace App\Engine\Embedding;

use App\Engine\Core\Exceptions\EmbeddingException;
use App\Engine\Core\Interfaces\LLMProvider;
use App\Engine\Utils\AppLogger;
use App\Engine\Utils\Constant;

class OllamaLLM implements LLMProvider
{
    private string $baseUrl;
    private string $model;
    private float $temperature;
    private int $numPredict;
    private int $timeout;
    private int $retryCount;
    private int $fallbackContextWindow;
    /** @var array<string, mixed>|null */
    private ?array $lastResponseMeta = null;
    private ?int $detectedContextWindow = null;

    public function __construct(
        string $baseUrl = Constant::DEFAULT_OLLAMA_BASE_URL,
        string $model = Constant::OLLAMA_LLM_MODEL,
        float $temperature = Constant::DEFAULT_LLM_TEMPERATURE,
        int $timeout = Constant::DEFAULT_LLM_TIMEOUT,
        int $numPredict = Constant::DEFAULT_LLM_NUM_PREDICT,
        int $retryCount = Constant::DEFAULT_EMBED_RETRY,
        int $fallbackContextWindow = Constant::DEFAULT_LLM_CONTEXT_WINDOW,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->model = $model;
        $this->temperature = $temperature;
        $this->timeout = $timeout;
        $this->numPredict = $numPredict;
        $this->retryCount = max(1, $retryCount);
        $this->fallbackContextWindow = $fallbackContextWindow;
    }

    public function generate(string $prompt): string
    {
        $url = $this->baseUrl . '/api/generate';
        $lastException = null;
        $this->lastResponseMeta = null;
        $log = AppLogger::instance();

        $numCtx = $this->detectContextWindow();
        $estimatedTokens = (int) (strlen($prompt) / Constant::AVG_CHAR_PER_TOKEN);
        $log->info("LLM model: {$this->model}, num_ctx: {$numCtx}, num_predict: {$this->numPredict}");
        $log->info("LLM prompt: ~{$estimatedTokens} tokens (" . strlen($prompt) . " chars)");

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
                    'prompt' => $prompt,
                    'options' => [
                        'temperature' => $this->temperature,
                        'num_predict' => $this->numPredict,
                        'num_ctx' => $numCtx,
                        'think' => false,
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
                    $promptEval = $this->lastResponseMeta['prompt_eval_count'] ?? 'null';
                    $evalCount = $this->lastResponseMeta['eval_count'] ?? 'null';
                    $log->error("LLM empty response: done_reason={$doneReason}, prompt_eval_count={$promptEval}, eval_count={$evalCount}, prompt_chars=" . strlen($prompt));
                    throw new EmbeddingException("LLM returned empty response (done_reason: {$doneReason}, prompt_eval_count: {$promptEval}, eval_count: {$evalCount})");
                }

                $evalCount = $this->lastResponseMeta['eval_count'] ?? '?';
                $log->info("LLM response: eval_count={$evalCount}, done_reason={$this->lastResponseMeta['done_reason']}");

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

    public function detectContextWindow(): int
    {
        if ($this->detectedContextWindow !== null) {
            return $this->detectedContextWindow;
        }

        $log = AppLogger::instance();

        try {
            $url = $this->baseUrl . '/api/show';
            $http = new \Mc\Http($url);
            $http->SetEncoder('json_encode');
            $http->SetOption(CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $http->SetOption(CURLOPT_RETURNTRANSFER, true);
            $http->SetOption(CURLOPT_TIMEOUT, Constant::HTTP_CONNECT_TIMEOUT);
            $http->SetOption(CURLOPT_CONNECTTIMEOUT, Constant::HTTP_CONNECT_TIMEOUT);

            $response = $http->Post([
                'model' => $this->model,
            ]);

            if ($response === false) {
                $log->warn("Failed to query /api/show, using fallback context_window: {$this->fallbackContextWindow}");
                $this->detectedContextWindow = $this->fallbackContextWindow;
                return $this->detectedContextWindow;
            }

            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                $log->warn("Invalid /api/show response (not JSON), using fallback context_window: {$this->fallbackContextWindow}");
                $this->detectedContextWindow = $this->fallbackContextWindow;
                return $this->detectedContextWindow;
            }

            $numCtx = null;

            // Try multiple key locations for context window
            if (isset($data['details']['num_ctx']) && is_numeric($data['details']['num_ctx'])) {
                $numCtx = (int) $data['details']['num_ctx'];
            } elseif (isset($data['model_info']) && is_array($data['model_info'])) {
                foreach ($data['model_info'] as $key => $value) {
                    if (str_contains($key, 'context_length') && is_numeric($value)) {
                        $numCtx = (int) $value;
                        break;
                    }
                    if (str_contains($key, 'context_len') && is_numeric($value)) {
                        $numCtx = (int) $value;
                        break;
                    }
                }
            }

            if ($numCtx !== null && $numCtx > 0) {
                // Cap at configured limit to avoid slow generation on large-context models
                if ($this->fallbackContextWindow > 0 && $numCtx > $this->fallbackContextWindow) {
                    $log->info("Detected model context window: {$numCtx}, capping to configured: {$this->fallbackContextWindow}");
                    $numCtx = $this->fallbackContextWindow;
                }
                $this->detectedContextWindow = $numCtx;
                $log->info("Using context window: {$numCtx}");
            } else {
                // Log the response keys for debugging
                $keys = implode(', ', array_keys($data));
                $log->warn("Context window not found in /api/show. Response keys: {$keys}. Using fallback: {$this->fallbackContextWindow}");
                $this->detectedContextWindow = $this->fallbackContextWindow;
            }

        } catch (\Throwable $e) {
            $this->detectedContextWindow = $this->fallbackContextWindow;
            $log->warn("Context window detection failed: {$e->getMessage()}, using fallback: {$this->fallbackContextWindow}");
        }

        return $this->detectedContextWindow;
    }

    /** @return array<string, mixed>|null */
    public function getLastResponseMeta(): ?array
    {
        return $this->lastResponseMeta;
    }
}
