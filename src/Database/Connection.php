<?php

declare(strict_types=1);

namespace DevKartic\LightKit\Database;

use PDO;

final class Connection
{
    /**
     * Create a PDO instance from config array.
     * Config keys: driver, host, database, username, password, charset
     * charset can be either a pure charset (utf8mb4) or a collation (utf8mb4_general_ci)
     */
    public static function make(array $config): PDO
    {
        $driver   = $config['driver']   ?? 'mysql';
        $host     = $config['host']     ?? '127.0.0.1';
        $database = $config['database'] ?? '';
        $username = $config['username'] ?? 'root';
        $password = $config['password'] ?? '';
        $charset  = $config['charset']  ?? 'utf8mb4';

        // Split charset and collation if collation is provided
        $parts = explode('_', $charset, 2);
        $charsetOnly = $parts[0] ?? $charset;
        $collation   = str_contains($charset, '_') ? $charset : null;

        // Build DSN using only charset
        $dsn = sprintf("%s:host=%s;dbname=%s;charset=%s", $driver, $host, $database, $charsetOnly);

        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // If collation is set, apply it explicitly
        if ($collation) {
            $pdo->exec("SET NAMES '{$charsetOnly}' COLLATE '{$collation}'");
        }

        return $pdo;
    }

    /**
     * Create PDO instance from EnvManager.
     * Expects env keys: DB_DRIVER, DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET
     */
    public static function fromEnv($env): PDO
    {
        $driver   = $env->get('DB_DRIVER', 'mysql');
        $host     = $env->get('DB_HOST', '127.0.0.1');
        $database = $env->get('DB_NAME', '');
        $username = $env->get('DB_USER', 'root');
        $password = $env->get('DB_PASS', '');
        $charset  = $env->get('DB_CHARSET', 'utf8mb4');

        return self::make([
            'driver'   => $driver,
            'host'     => $host,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset'  => $charset,
        ]);
    }
}
