<?php

namespace App\Engine\Utils;

use App\Engine\Core\Exceptions\ConfigurationException;

class Config
{
    /** @var array<string, mixed> */
    private array $data;

    public function __construct(string $path)
    {
        if (!file_exists($path)) {
            throw new ConfigurationException("Configuration file not found: $path");
        }

        $parsed = \yaml_parse_file($path);

        if ($parsed === false) {
            throw new ConfigurationException("Failed to parse configuration file: $path");
        }

        if (!is_array($parsed)) {
            throw new ConfigurationException("Configuration file did not return a valid section map: $path");
        }

        $this->data = $parsed;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->data;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public function getOllamaBaseUrl(): string
    {
        return $this->get('ollama.base_url', Constant::DEFAULT_OLLAMA_BASE_URL);
    }

    public function getEmbeddingModel(): string
    {
        return $this->get('embedding.model', Constant::OLLAMA_EMBED_MODEL);
    }

    public function getEmbeddingDimension(): int
    {
        $value = (int) $this->get('embedding.dimension', Constant::DEFAULT_EMBED_DIMENSION);
        return max(1, $value);
    }

    public function getMaxTokens(): int
    {
        return (int) $this->get('embedding.max_tokens', Constant::DEFAULT_EMBED_MAX_TOKENS);
    }

    public function getSafetyMargin(): int
    {
        return (int) $this->get('embedding.safety_margin', Constant::DEFAULT_EMBED_SAFETY_MARGIN);
    }

    public function getOverlap(): int
    {
        return (int) $this->get('embedding.overlap', Constant::DEFAULT_EMBED_OVERLAP);
    }

    public function getRetryCount(): int
    {
        return (int) $this->get('embedding.retry', Constant::DEFAULT_EMBED_RETRY);
    }

    public function getLlmModel(): string
    {
        return $this->get('llm.model', Constant::OLLAMA_LLM_MODEL);
    }

    public function getTemperature(): float
    {
        return (float) $this->get('llm.temperature', Constant::DEFAULT_LLM_TEMPERATURE);
    }

    public function getTopK(): int
    {
        $value = (int) $this->get('retrieval.top_k', Constant::DEFAULT_TOP_K);
        return max(1, $value);
    }

    public function getThreshold(): float
    {
        return (float) $this->get('retrieval.threshold', Constant::DEFAULT_THRESHOLD);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    public function getNumPredict(): int
    {
        return (int) $this->get('llm.num_predict', Constant::DEFAULT_LLM_NUM_PREDICT);
    }

    public function getContextWindow(): int
    {
        return (int) $this->get('llm.context_window', Constant::DEFAULT_LLM_CONTEXT_WINDOW);
    }

    public function getTimeout(): int
    {
        return (int) $this->get('llm.timeout', Constant::DEFAULT_LLM_TIMEOUT);
    }
}
