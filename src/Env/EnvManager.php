<?php

namespace DevKartic\LightKit\Env;

use Exception;

class EnvManager
{
    protected static array $data = [];

    /**
     * @throws Exception
     */
    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            throw new Exception("Env file not found: {$path}");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) continue; // skip comments

            [$name, $value] = array_map('trim', explode('=', $line, 2));

            // remove quotes if any
            $value = trim($value, "\"'");

            self::$data[$name] = $value;

            // make available to setenv() and $_ENV
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }

    public static function get(string $key, $default = null): ?string
    {
        return self::$data[$key] ?? getenv($key) ?? $default;
    }
}
