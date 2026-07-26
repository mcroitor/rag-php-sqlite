<?php

namespace App\Utils;

use Mc\Logger;

class AppLogger
{
    private static ?AppLogger $instance = null;
    private Logger $logger;
    private bool $debugEnabled = false;

    private function __construct(?string $logFile = null)
    {
        $this->logger = new Logger($logFile ?? 'php://stdout');
    }

    public static function instance(?string $logFile = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($logFile);
        }
        return self::$instance;
    }

    /** Reset singleton (for testing / reconfiguration). */
    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    public function enableDebug(bool $enable = true): void
    {
        $this->debugEnabled = $enable;
        $this->logger->enableDebug($enable);
    }

    public function info(string $message): void
    {
        $this->logger->info($message);
    }

    public function pass(string $message): void
    {
        $this->logger->pass($message);
    }

    public function warn(string $message): void
    {
        $this->logger->warn($message);
    }

    public function error(string $message): void
    {
        $this->logger->error($message);
    }

    public function fail(string $message): void
    {
        $this->logger->fail($message);
    }

    public function debug(string $message): void
    {
        if ($this->debugEnabled) {
            $this->logger->debug($message, true);
        }
    }
}
