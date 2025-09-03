<?php
declare(strict_types=1);

namespace DevKartic\LightKit\Config;

use DevKartic\LightKit\Database\DB;
use DevKartic\LightKit\Env\EnvManager;

class ConfigManager
{
    private static bool $initialized = false;
    private static ?EnvManager $env = null;

    /**
     * Initialize the application environment & DB connection
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return; // prevent double initialization
        }

        // Load Composer autoload (resolve dynamically)
        $autoload = __DIR__ . '/../../vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }

        // Load environment variables
        self::$env = new EnvManager();
        self::$env->load(__DIR__ . '/../../.env');

        // Initialize DB facade
        DB::fromEnv(self::$env);

        self::$initialized = true;
    }

    /**
     * Get Env Manager instance
     */
    public static function env(): EnvManager
    {
        if (!self::$initialized) {
            self::init();
        }
        return self::$env;
    }
}
