<?php

declare(strict_types=1);

use DevKartic\LightKit\Database\Connection;
use DevKartic\LightKit\Env\EnvManager;

beforeAll(
/**
 * @throws Exception
 */ function () {
    // Load test environment (if your EnvManager uses a file)
    if (class_exists(EnvManager::class)) {
        $env = new EnvManager();
        $env->load(__DIR__ . '/../../.env');
    }
});

it('connects to a real MySQL database using Connection::make', function () {
    $pdo = Connection::make([
        'driver'   => getenv('DB_DRIVER'),
        'host'     => getenv('DB_HOST'),
        'database' => getenv('DB_NAME'),
        'username' => getenv('DB_USER'),
        'password' => getenv('DB_PASS'),
        'charset'  => getenv('DB_CHARSET'),
    ]);

    expect($pdo)->toBeInstanceOf(PDO::class)
        ->and($pdo->getAttribute(PDO::ATTR_ERRMODE))->toBe(PDO::ERRMODE_EXCEPTION);

    // Optional: run a simple query to ensure it works
    $stmt = $pdo->query('SELECT DATABASE() as db_name');
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    expect($row['db_name'])->toBe(getenv('DB_NAME'));
});

it('connects using Connection::fromEnv', function () {
    // Use the real EnvManager
    $env = new DevKartic\LightKit\Env\EnvManager();
    $env->load(__DIR__ . '/../../.env');

    $pdo = Connection::fromEnv($env);

    expect($pdo)->toBeInstanceOf(PDO::class)
        ->and($pdo->getAttribute(PDO::ATTR_ERRMODE))->toBe(PDO::ERRMODE_EXCEPTION);
});

it('throws RuntimeException for wrong credentials', function () {
    Connection::make([
        'driver'   => 'mysql',
        'host'     => getenv('DB_HOST'),
        'database' => getenv('DB_NAME'),
        'username' => 'wrong_user',
        'password' => 'wrong_pass',
        'charset'  => getenv('DB_CHARSET'),
    ]);
})->throws(RuntimeException::class);
