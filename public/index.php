<?php

declare(strict_types=1);

use App\Engine\Utils\AppLogger;
use App\Web\Application;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * LLM generation can take over the default 30s web-server limit.
 * Allow up to the configured Ollama timeout (seconds) plus margin.
 */
$maxExecution = max(300, (int) (getenv('RAG_MAX_EXECUTION_TIME') ?: 300));
if (function_exists('set_time_limit')) {
    set_time_limit($maxExecution);
}

/**
 * Convert non-deprecation errors to exceptions; swallow deprecation
 * notices (e.g. vendor curl_close) so they never leak into HTTP bodies.
 */
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
        AppLogger::instance()->debug("Deprecated: $message ($file:$line)");
        return true;
    }

    throw new \ErrorException($message, 0, $severity, $file, $line);
});

/**
 * Serve existing static assets directly (with correct MIME types).
 * Returning false tells the built-in server to handle the file itself.
 */
$uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($uri !== '/') {
    $staticFile = __DIR__ . str_replace(['..', '//'], ['', '/'], $uri);
    if (is_file($staticFile)) {
        return false;
    }
}

$app = new Application(dirname(__DIR__));

echo $app->run();
