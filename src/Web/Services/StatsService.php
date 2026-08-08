<?php

declare(strict_types=1);

namespace App\Web\Services;

use App\Engine\Storage\SQLiteStorage;
use App\Engine\Utils\Config;
use App\Engine\Utils\Constant;

/**
 * Statistics use-case for the web layer.
 */
class StatsService
{
    private SQLiteStorage $storage;
    private Config $config;
    private string $dbPath;

    public function __construct(SQLiteStorage $storage, Config $config, string $dbPath)
    {
        $this->storage = $storage;
        $this->config = $config;
        $this->dbPath = $dbPath;
    }

    /**
     * @return array{documents: int, chunks: int, embeddings: int, db_size: int, db_size_human: string, embedding: array{model: string, models: list<string>, dimension: int}, ollama: array{status: string, model: string, base_url: string, error: string|null}}
     */
    public function stats(): array
    {
        $dbSize = file_exists($this->dbPath) ? filesize($this->dbPath) : 0;

        return [
            'documents' => $this->storage->getDocumentCount(),
            'chunks' => $this->storage->getChunkCount(),
            'embeddings' => $this->storage->getEmbeddingCount(),
            'db_size' => $dbSize === false ? 0 : $dbSize,
            'db_size_human' => $this->formatBytes($dbSize === false ? 0 : $dbSize),
            'embedding' => [
                'model' => $this->config->getEmbeddingModel(),
                'models' => $this->storage->getEmbeddingModels(),
                'dimension' => $this->storage->getEmbeddingDimension(),
            ],
            'ollama' => $this->ollamaStatus(),
        ];
    }

    /**
     * @return array{status: string, model: string, base_url: string, error: string|null}
     */
    private function ollamaStatus(): array
    {
        $baseUrl = $this->config->getOllamaBaseUrl();
        $model = $this->config->getEmbeddingModel();

        try {
            $http = new \Mc\Http(rtrim($baseUrl, '/') . '/api/tags');
            $http->SetOption(CURLOPT_RETURNTRANSFER, true);
            $http->SetOption(CURLOPT_TIMEOUT, 5);
            $http->SetOption(CURLOPT_CONNECTTIMEOUT, Constant::HTTP_CONNECT_TIMEOUT);

            $response = $http->Get();

            if ($response === false) {
                return [
                    'status' => 'offline',
                    'model' => $model,
                    'base_url' => $baseUrl,
                    'error' => 'Connection failed',
                ];
            }

            $data = json_decode($response, true);
            $models = is_array($data) && isset($data['models']) && is_array($data['models']) ? $data['models'] : [];

            $installed = false;
            foreach ($models as $entry) {
                if (is_array($entry) && ($entry['name'] ?? '') === $model) {
                    $installed = true;
                    break;
                }
            }

            return [
                'status' => $installed ? 'ok' : 'model_missing',
                'model' => $model,
                'base_url' => $baseUrl,
                'error' => $installed ? null : "Model '{$model}' is not installed",
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'offline',
                'model' => $model,
                'base_url' => $baseUrl,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
