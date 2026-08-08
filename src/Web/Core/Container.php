<?php

declare(strict_types=1);

namespace App\Web\Core;

use App\Engine\Embedding\OllamaEmbedding;
use App\Engine\Embedding\OllamaLLM;
use App\Engine\Prompt\PromptBuilder;
use App\Engine\Retrieval\VectorRetriever;
use App\Engine\Services\QueryService;
use App\Engine\Services\RAGService;
use App\Engine\Storage\SQLiteStorage;
use App\Engine\Utils\Config;
use App\Engine\Utils\DbFactory;
use App\Web\Services\StatsService;

/**
 * Lightweight service container for the web layer.
 * Lazily builds engine services from the shared configuration.
 */
class Container
{
    private string $root;
    private string $base;
    private ?Config $config = null;
    private ?\PDO $pdo = null;
    private ?SQLiteStorage $storage = null;
    private ?OllamaEmbedding $embedding = null;
    private ?OllamaLLM $llm = null;
    private ?QueryService $queryService = null;
    private ?RAGService $ragService = null;

    public function __construct(string $root, string $base = DbFactory::DEFAULT_BASE)
    {
        $this->root = $root;
        $this->base = DbFactory::normalize($base);
    }

    public function root(): string
    {
        return $this->root;
    }

    public function base(): string
    {
        return $this->base;
    }

    public function setBase(string $base): void
    {
        $base = DbFactory::normalize($base);

        if ($base === $this->base) {
            return;
        }

        $this->base = $base;
        $this->pdo = null;
        $this->storage = null;
        $this->queryService = null;
        $this->ragService = null;
    }

    public function config(): Config
    {
        if ($this->config === null) {
            $this->config = new Config($this->root . '/config/config.yaml');
        }

        return $this->config;
    }

    public function pdo(): \PDO
    {
        if ($this->pdo === null) {
            $this->pdo = DbFactory::pdo($this->root, $this->base);
        }

        return $this->pdo;
    }

    public function storage(): SQLiteStorage
    {
        if ($this->storage === null) {
            $this->storage = new SQLiteStorage($this->pdo());
        }

        return $this->storage;
    }

    public function embedding(): OllamaEmbedding
    {
        if ($this->embedding === null) {
            $config = $this->config();
            $this->embedding = new OllamaEmbedding(
                baseUrl: $config->getOllamaBaseUrl(),
                model: $config->getEmbeddingModel(),
                dimension: $config->getEmbeddingDimension(),
                retryCount: $config->getRetryCount(),
            );
        }

        return $this->embedding;
    }

    public function queryService(): QueryService
    {
        if ($this->queryService === null) {
            $this->queryService = new QueryService(
                $this->embedding(),
                $this->storage(),
                $this->config()->getThreshold(),
            );
        }

        return $this->queryService;
    }

    public function llm(): OllamaLLM
    {
        if ($this->llm === null) {
            $config = $this->config();
            $this->llm = new OllamaLLM(
                baseUrl: $config->getOllamaBaseUrl(),
                model: $config->getLlmModel(),
                temperature: $config->getTemperature(),
                numPredict: $config->getNumPredict(),
                timeout: $config->getTimeout(),
                fallbackContextWindow: $config->getContextWindow(),
            );
        }

        return $this->llm;
    }

    public function ragService(): RAGService
    {
        if ($this->ragService === null) {
            $config = $this->config();
            $retriever = new VectorRetriever(
                $this->embedding(),
                $this->storage(),
                $config->getThreshold(),
            );
            $promptBuilder = new PromptBuilder(maxContextTokens: $config->getContextWindow());

            $this->ragService = new RAGService($retriever, $promptBuilder, $this->llm());
        }

        return $this->ragService;
    }

    public function statsService(): StatsService
    {
        return new StatsService(
            $this->storage(),
            $this->config(),
            DbFactory::path($this->root, $this->base),
        );
    }
}
