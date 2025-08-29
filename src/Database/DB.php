<?php

declare(strict_types=1);

namespace DevKartic\LightKit\Database;

use PDO;

/**
 * Facade-style DB class for quick access to QueryBuilder.
 * Usage:
 *  DB::table('users')->where('id', 1)->first();
 */
final class DB
{
    private static ?QueryBuilder $builder = null;

    /**
     * Initialize DB facade with PDO or QueryBuilder instance.
     */
    public static function init(QueryBuilder|PDO $connection): void
    {
        if ($connection instanceof PDO) {
            self::$builder = new QueryBuilder($connection);
        } else {
            self::$builder = $connection;
        }
    }

    /**
     * Initialize from EnvManager instance.
     */
    public static function fromEnv($env): void
    {
        self::$builder = QueryBuilder::fromEnv($env);
    }

    /**
     * Initialize from config array.
     */
    public static function fromConfig(array $config): void
    {
        self::$builder = QueryBuilder::fromConfig($config);
    }

    /** Proxy table() call to QueryBuilder */
    public static function table(string $table): QueryBuilder
    {
        self::ensureInitialized();
        return self::$builder->table($table);
    }

    private static function ensureInitialized(): void
    {
        if (self::$builder === null) {
            throw new \RuntimeException('DB facade is not initialized. Call DB::fromEnv() or DB::fromConfig() first.');
        }
    }
}
