<?php

declare(strict_types=1);

namespace DevKartic\LightKit\Env;

use Exception;

/**
 * Class EnvManager
 *
 * Lightweight .env file loader and accessor.
 *
 * Usage:
 *   EnvManager::load(__DIR__ . '/.env');
 *   $dbHost = EnvManager::get('DB_HOST', '127.0.0.1');
 */
final class EnvManager
{
    /** @var array<string,string> */
    protected static array $data = [];

    /**
     * Load environment variables from a .env file.
     *
     * @param string $path Path to .env file
     * @throws Exception if the file does not exist or contains invalid lines
     */
    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            throw new Exception("Env file not found: {$path}");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                throw new Exception("Invalid .env line: {$line}");
            }

            [$name, $value] = array_map('trim', explode('=', $line, 2));
            $value = trim($value, "\"'"); // remove quotes if any

            self::$data[$name] = $value;

            // Make available via getenv() and $_ENV
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
        }
    }

    /**
     * Get an environment variable.
     *
     * @param string $key Variable name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$data[$key] ?? getenv($key) ?: $default;
    }

    /**
     * Check if an environment variable exists.
     */
    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$data) || getenv($key) !== false;
    }
}
