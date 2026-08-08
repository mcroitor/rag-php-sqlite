<?php

declare(strict_types=1);

namespace App\Web;

use App\Engine\Utils\Constant;
use Mc\Sql\Database;

class Config
{
    private static string $www = 'http://localhost:8000';
    private static string $rootDir = __DIR__ . '/../../';
    private static string $appDir = __DIR__ . '/';
    private static ?Database $database = null;

    public static function load(): void
    {
        self::$database = new Database('sqlite:' . self::$rootDir . Constant::DEFAULT_DB_FILENAME);
    }

    public static function db(): Database
    {
        if (self::$database === null) {
            self::load();
        }

        return self::$database;
    }

    public static function www(): string
    {
        return self::$www;
    }

    public static function rootDir(): string
    {
        return self::$rootDir;
    }

    public static function appDir(): string
    {
        return self::$appDir;
    }

    public static function viewsDir(): string
    {
        return self::$appDir . 'views/';
    }
}
