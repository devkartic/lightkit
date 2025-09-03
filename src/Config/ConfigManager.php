<?php
declare(strict_types=1);

namespace DevKartic\LightKit\Config;

use DevKartic\LightKit\Database\DB;
use DevKartic\LightKit\Env\EnvManager;

class ConfigManager
{
    private static bool $initialized = false;
    private static ?EnvManager $env = null;

    public static function init(?string $envPath = null): void
    {
        if (self::$initialized) {
            return;
        }

        // Load environment variables
        self::$env = new EnvManager();
        $envFile = $envPath ?? dirname(__DIR__, 2) . '/.env';
        self::$env->load($envFile);

        // Initialize DB facade
        DB::fromEnv(self::$env);

        self::$initialized = true;
    }

    public static function env(): EnvManager
    {
        if (!self::$initialized) {
            self::init();
        }
        return self::$env;
    }
}
