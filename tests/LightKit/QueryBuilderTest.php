<?php

namespace Tests\LightKit;
use DevKartic\LightKit\Database\QueryBuilder;
use PDO;
use InvalidArgumentException;

beforeEach(function () {
    // Create in-memory DB and table
    $this->pdo = new PDO('sqlite::memory:');
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $this->pdo->exec("
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            age INTEGER
        )
    ");

    // Seed some data
    $stmt = $this->pdo->prepare("INSERT INTO users (name, age) VALUES (?, ?)");
    $stmt->execute(['Alice', 25]);
    $stmt->execute(['Bob', 30]);
    $stmt->execute(['Charlie', 35]);

    $this->qb = new QueryBuilder($this->pdo);
});

it('can select all records', function () {
    $rows = $this->qb->table('users')->get();

    expect($rows)->toHaveCount(3);
    expect($rows[0])->toHaveKeys(['id', 'name', 'age']);
});

it('can filter with where condition', function () {
    $row = $this->qb
        ->table('users')
        ->where('name', 'Alice')
        ->first();

    expect($row['name'])->toBe('Alice');
});

it('can insert a record', function () {
    $inserted = $this->qb
        ->table('users')
        ->insert(['name' => 'David', 'age' => 28]);

    expect($inserted)->toBeTrue();

    $all = $this->pdo->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);
    expect($all)->toHaveCount(4);
});

it('throws on empty insert data', function () {
    $this->qb->table('users')->insert([]);
})->throws(InvalidArgumentException::class);

it('can update a record with where condition', function () {
    $updated = $this->qb
        ->table('users')
        ->where('name', 'Bob')
        ->update(['age' => 32]);

    expect($updated)->toBeTrue();

    $bob = $this->pdo->query("SELECT * FROM users WHERE name='Bob'")->fetch(PDO::FETCH_ASSOC);
    expect($bob['age'])->toBe(32);
});

it('throws when update is called without where', function () {
    $this->qb->table('users')->update(['age' => 40]);
})->throws(InvalidArgumentException::class);

it('can delete with where condition', function () {
    $deleted = $this->qb
        ->table('users')
        ->where('name', 'Alice')
        ->delete();

    expect($deleted)->toBeTrue();

    $all = $this->pdo->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);
    expect($all)->toHaveCount(2);
});

it('throws when delete is called without where', function () {
    $this->qb->table('users')->delete();
})->throws(InvalidArgumentException::class);

it('can build complex query', function () {
    [$sql, $bindings] = $this->qb
        ->table('users')
        ->select('id', 'name')
        ->where('age', '>', 25)
        ->orderBy('age', 'DESC')
        ->limit(2)
        ->toSql();

    expect($sql)->toContain('SELECT `id`, `name` FROM `users` WHERE `age` > ? ORDER BY `age` DESC LIMIT 2')
        ->and($bindings)->toBe([25]);
});

it('can interpolate raw SQL for debugging', function () {
    $qb = $this->qb
        ->table('users')
        ->where('name', 'Alice');

    $raw = $qb->toRawSql();

    expect($raw)->toContain("SELECT * FROM `users` WHERE `name` =");
});
