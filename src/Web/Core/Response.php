<?php

declare(strict_types=1);

namespace App\Web\Core;

use App\Engine\Core\Exceptions\ConfigurationException;
use App\Engine\Core\Exceptions\EmbeddingException;
use App\Engine\Core\Exceptions\StorageException;
use App\Engine\Core\Exceptions\ValidationException;

/**
 * HTTP/JSON response helpers for the web layer.
 */
class Response
{
    /** @param mixed $data */
    public static function json($data, int $status = 200): string
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return $encoded === false ? '{}' : $encoded;
    }

    /**
     * Uniform error payload: {error: {code, message, status}}.
     *
     * @param array<string, mixed> $meta
     */
    public static function error(string $code, string $message, int $status = 500, array $meta = []): string
    {
        $payload = [
            'error' => [
                'code' => $code,
                'message' => $message,
                'status' => $status,
            ],
        ];

        if ($meta !== []) {
            $payload['error']['meta'] = $meta;
        }

        return self::json($payload, $status);
    }

    /**
     * Map engine exceptions to HTTP status codes.
     */
    public static function errorFromThrowable(\Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return self::error('validation_error', $e->getMessage(), 400);
        }

        if ($e instanceof EmbeddingException) {
            return self::error('embedding_error', $e->getMessage(), 502);
        }

        if ($e instanceof ConfigurationException) {
            return self::error('configuration_error', $e->getMessage(), 500);
        }

        if ($e instanceof StorageException) {
            return self::error('storage_error', $e->getMessage(), 500);
        }

        return self::error('internal_error', $e->getMessage(), 500);
    }
}
