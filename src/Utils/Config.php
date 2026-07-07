<?php

namespace App\Utils;

use App\Core\Exceptions\ConfigurationException;

class Config
{
    /** @var array<string, mixed> */
    private array $data;

    public function __construct(string $path)
    {
        if (!file_exists($path)) {
            throw new ConfigurationException("Configuration file not found: $path");
        }

        $parsed = yaml_parse_file($path);

        if ($parsed === false) {
            throw new ConfigurationException("Failed to parse configuration file: $path");
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
        return $this->get('ollama.base_url', 'http://localhost:11434');
    }

    public function getEmbeddingModel(): string
    {
        return $this->get('embedding.model', 'nomic-embed-text');
    }

    public function getEmbeddingDimension(): int
    {
        return (int) $this->get('embedding.dimension', 768);
    }

    public function getMaxTokens(): int
    {
        return (int) $this->get('embedding.max_tokens', 1500);
    }

    public function getSafetyMargin(): int
    {
        return (int) $this->get('embedding.safety_margin', 300);
    }

    public function getRetryCount(): int
    {
        return (int) $this->get('embedding.retry', 3);
    }

    public function getLlmModel(): string
    {
        return $this->get('llm.model', 'qwen3.5:2b');
    }

    public function getTemperature(): float
    {
        return (float) $this->get('llm.temperature', 0.7);
    }

    public function getTopK(): int
    {
        return (int) $this->get('retrieval.top_k', 5);
    }

    public function getThreshold(): float
    {
        return (float) $this->get('retrieval.threshold', 0.75);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }
}
