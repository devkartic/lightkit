<?php

declare(strict_types=1);

namespace DevKartic\LightKit\Database;

use PDO;
use RuntimeException;
use DevKartic\LightKit\Env\EnvManager;

/**
 * Class DB
 *
 * Facade-style access to QueryBuilder.
 *
 * Usage:
 *   DB::init($pdo);
 *   $users = DB::table('users')->where('id', 1)->first();
 */
final class DB
{
    private static ?QueryBuilder $builder = null;

    /**
     * Initialize DB facade with PDO or QueryBuilder instance.
     */
    public static function init(QueryBuilder|PDO $connection): void
    {
        self::$builder = $connection instanceof PDO
            ? new QueryBuilder($connection)
            : $connection;
    }

    /**
     * Initialize from EnvManager instance.
     */
    public static function fromEnv(EnvManager $env): void
    {
        self::$builder = QueryBuilder::fromEnv($env);
    }

    /**
     * Initialize from config array.
     *
     * @param array<string,mixed> $config
     */
    public static function fromConfig(array $config): void
    {
        self::$builder = QueryBuilder::fromConfig($config);
    }

    /**
     * Start a query on a table.
     */
    public static function table(string $table): QueryBuilder
    {
        self::ensureInitialized();
        return self::$builder->table($table);
    }

    /**
     * Get the current QueryBuilder instance directly.
     */
    public static function query(): QueryBuilder
    {
        self::ensureInitialized();
        return self::$builder;
    }

    private static function ensureInitialized(): void
    {
        if (self::$builder === null) {
            throw new RuntimeException(
                'DB facade is not initialized. Call DB::init(), DB::fromEnv(), or DB::fromConfig() first.'
            );
        }
    }
}
