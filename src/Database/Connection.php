<?php

declare(strict_types=1);

namespace DevKartic\LightKit\Database;

use PDO;
use PDOException;
use RuntimeException;
use DevKartic\LightKit\Env\EnvManager;

/**
 * Class Connection
 *
 * Factory for creating PDO connections from config or environment variables.
 */
final class Connection
{
    /**
     * Create a PDO instance from a config array.
     *
     * @param array<string,mixed> $config Keys: driver, host, database, username, password, charset
     * @return PDO
     * @throws RuntimeException if connection fails
     */
    public static function make(array $config): PDO
    {
        $driver   = $config['driver']   ?? 'mysql';
        $host     = $config['host']     ?? '127.0.0.1';
        $database = $config['database'] ?? '';
        $username = $config['username'] ?? 'root';
        $password = $config['password'] ?? '';
        $charset  = $config['charset']  ?? 'utf8mb4';

        // Parse charset + collation if provided
        if (preg_match('/^([a-z0-9]+)(?:_(.+))?$/i', $charset, $m)) {
            $charsetOnly = $m[1];
            $collation   = $m[2] ?? null;
        } else {
            $charsetOnly = $charset;
            $collation   = null;
        }

        $dsn = sprintf("%s:host=%s;dbname=%s;charset=%s", $driver, $host, $database, $charsetOnly);

        try {
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection failed: " . $e->getMessage(), 0, $e);
        }

        if ($collation) {
            $pdo->exec("SET NAMES '{$charsetOnly}' COLLATE '{$collation}'");
        }

        return $pdo;
    }

    /**
     * Create PDO instance from EnvManager.
     *
     * Expected keys: DB_DRIVER, DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET
     */
    public static function fromEnv(EnvManager $env): PDO
    {
        return self::make([
            'driver'   => $env->get('DB_DRIVER', 'mysql'),
            'host'     => $env->get('DB_HOST', '127.0.0.1'),
            'database' => $env->get('DB_NAME', ''),
            'username' => $env->get('DB_USER', 'root'),
            'password' => $env->get('DB_PASS', ''),
            'charset'  => $env->get('DB_CHARSET', 'utf8mb4'),
        ]);
    }
}
