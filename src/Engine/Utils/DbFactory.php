<?php

namespace App\Engine\Utils;

use App\Engine\Core\Exceptions\ConfigurationException;

/**
 * Central factory for RAG database connections.
 *
 * Base naming convention:
 *   - all bases (including default "rag") -> <root>/runtime/dbs/<base>.sqlite
 *
 * Usage:
 *   DbFactory::pdo($root, 'rag')      // default database
 *   DbFactory::pdo($root, 'docker')   // new/dedicated database
 */
class DbFactory
{
    public const DEFAULT_BASE = 'rag';

    public static function normalize(string $base): string
    {
        $base = strtolower(trim($base));
        $base = preg_replace('/[^a-z0-9_\-]/', '-', $base) ?? '';

        if ($base === '' || $base === '.' || $base === '..') {
            throw new ConfigurationException("Invalid database name: '$base'");
        }

        return $base;
    }

    public static function path(string $root, string $base): string
    {
        $base = self::normalize($base);

        return self::dbsDir($root) . DIRECTORY_SEPARATOR . $base . '.sqlite';
    }

    public static function exists(string $root, string $base): bool
    {
        return file_exists(self::path($root, $base));
    }

    public static function dbsDir(string $root): string
    {
        return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'dbs';
    }

    /**
     * Create/open a RAG database and return a configured PDO handle.
     *
     * Does not create the schema; call setup for that.
     */
    public static function pdo(string $root, string $base): \PDO
    {
        $path = self::path($root, $base);
        $dir = dirname($path);

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new ConfigurationException("Failed to create database directory: $dir");
            }
        }

        $pdo = new \PDO('sqlite:' . $path);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }

    /**
     * Read --rag=<base> (or --rag <base>) from CLI args.
     * @param list<string> $argv
     */
    public static function baseFromArgv(array $argv): string
    {
        foreach ($argv as $i => $arg) {
            if (str_starts_with($arg, '--rag=')) {
                return self::normalize(substr($arg, strlen('--rag=')));
            }

            if ($arg === '--rag') {
                return self::normalize((string) ($argv[$i + 1] ?? self::DEFAULT_BASE));
            }
        }

        return self::DEFAULT_BASE;
    }

    /**
     * Shortcut for CLI entry points: read --rag from argv and open the DB.
     *
     * @param list<string> $argv
     */
    public static function pdoFromArgv(string $root, array $argv): \PDO
    {
        return self::pdo($root, self::baseFromArgv($argv));
    }

    /**
     * List available RAG databases.
     *
     * @return list<array{name: string, path: string, size: int, exists: bool}>
     */
    public static function list(string $root): array
    {
        $result = [];

        $dir = self::dbsDir($root);

        if (is_dir($dir)) {
            foreach (glob($dir . '/*.sqlite') ?: [] as $path) {
                $name = basename($path, '.sqlite');
                $result[] = [
                    'name' => $name,
                    'path' => $path,
                    'size' => (int) filesize($path),
                    'exists' => true,
                ];
            }
        }

        usort($result, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $result;
    }
}
