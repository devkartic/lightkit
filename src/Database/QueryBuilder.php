<?php

declare(strict_types=1);

namespace DevKartic\LightKit\Database;

use InvalidArgumentException;
use PDO;
use PDOStatement;

/**
 * Lightweight, framework-agnostic QueryBuilder for PDO.
 *
 * Features:
 *  - Fluent API for SELECT/INSERT/UPDATE/DELETE
 *  - Safe parameter binding (prevents SQL injection)
 *  - Supports WHERE, JOINs, GROUP BY, HAVING, ORDER, LIMIT, OFFSET
 *  - Debugging helpers (toSql, toRawSql)
 *  - Prevents accidental UPDATE/DELETE without WHERE
 */
final class QueryBuilder
{
    private PDO $pdo;
    private string $table = '';
    private array $columns = ['*'];
    private array $wheres = [];
    private array $bindings = [];
    private array $joins = [];
    private array $orders = [];
    private array $groupBys = [];
    private array $havings = [];
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create instance from EnvManager.
     */
    public static function fromEnv(\DevKartic\LightKit\Env\EnvManager $env): self
    {
        return new self(Connection::fromEnv($env));
    }

    /**
     * Create instance from config array.
     */
    public static function fromConfig(array $config): self
    {
        return new self(Connection::make($config));
    }

    /**
     * Set the working table.
     */
    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Set selected columns.
     */
    public function select(string ...$columns): self
    {
        $this->columns = $columns ?: ['*'];
        return $this;
    }

    /**
     * Add raw select expression (e.g. COUNT(*)).
     */
    public function selectRaw(string $expression): self
    {
        $this->columns[] = $expression;
        return $this;
    }

    /**
     * Add a WHERE condition.
     */
    public function where(string $column, string $operator, mixed $value = null): self
    {
        // Support shorthand ->where('id', 1)
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $placeholder = '?';
        $this->bindings[] = $value;
        $this->wheres[] = "{$this->wrap($column)} {$operator} {$placeholder}";

        return $this;
    }

    /**
     * Add an OR WHERE condition.
     */
    public function orWhere(string $column, string $operator, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $placeholder = '?';
        $this->bindings[] = $value;
        $this->wheres[] = "OR {$this->wrap($column)} {$operator} {$placeholder}";

        return $this;
    }

    /**
     * WHERE IN condition.
     */
    public function whereIn(string $column, array $values): self
    {
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $this->bindings = array_merge($this->bindings, $values);
        $this->wheres[] = "{$this->wrap($column)} IN ({$placeholders})";

        return $this;
    }

    /**
     * WHERE NULL condition.
     */
    public function whereNull(string $column): self
    {
        $this->wheres[] = "{$this->wrap($column)} IS NULL";
        return $this;
    }

    /**
     * WHERE NOT NULL condition.
     */
    public function whereNotNull(string $column): self
    {
        $this->wheres[] = "{$this->wrap($column)} IS NOT NULL";
        return $this;
    }

    /**
     * WHERE BETWEEN condition.
     */
    public function whereBetween(string $column, mixed $start, mixed $end): self
    {
        $this->wheres[] = "{$this->wrap($column)} BETWEEN ? AND ?";
        $this->bindings[] = $start;
        $this->bindings[] = $end;

        return $this;
    }

    /**
     * Add a JOIN clause.
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = "{$type} JOIN {$table} ON {$this->wrap($first)} {$operator} {$this->wrap($second)}";
        return $this;
    }

    /**
     * Add an ORDER BY clause.
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orders[] = "{$this->wrap($column)} {$direction}";
        return $this;
    }

    /**
     * Add a GROUP BY clause.
     */
    public function groupBy(string ...$columns): self
    {
        foreach ($columns as $col) {
            $this->groupBys[] = $this->wrap($col);
        }
        return $this;
    }

    /**
     * Add a HAVING clause.
     */
    public function having(string $column, string $operator, mixed $value): self
    {
        $this->havings[] = "{$this->wrap($column)} {$operator} ?";
        $this->bindings[] = $value;
        return $this;
    }

    /**
     * Limit the number of results.
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Offset the results.
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Get all results.
     */
    public function get(): array
    {
        [$sql, $bindings] = $this->toSql();
        $stmt = $this->execute($sql, $bindings);
        $results = $stmt->fetchAll();
        $this->reset();
        return $results;
    }

    /**
     * Get the first result.
     */
    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    /**
     * Insert a new record.
     */
    public function insert(array $data): bool
    {
        if (empty($data)) {
            throw new InvalidArgumentException('Insert data cannot be empty');
        }

        $columns = implode(', ', array_map([$this, 'wrap'], array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->execute($sql, array_values($data));

        $this->reset();
        return $stmt->rowCount() > 0;
    }

    /**
     * Update existing records.
     */
    public function update(array $data): bool
    {
        if (empty($data)) {
            throw new InvalidArgumentException('Update data cannot be empty');
        }
        if (empty($this->wheres)) {
            throw new InvalidArgumentException('UPDATE without WHERE is not allowed');
        }

        $set = implode(', ', array_map(fn($col) => "{$this->wrap($col)} = ?", array_keys($data)));

        $sql = "UPDATE {$this->table} SET {$set} " . $this->compileWheres();
        $bindings = array_merge(array_values($data), $this->bindings);

        $stmt = $this->execute($sql, $bindings);
        $this->reset();

        return $stmt->rowCount() > 0;
    }

    /**
     * Delete records.
     */
    public function delete(): bool
    {
        if (empty($this->wheres)) {
            throw new InvalidArgumentException('DELETE without WHERE is not allowed');
        }

        $sql = "DELETE FROM {$this->table} " . $this->compileWheres();
        $stmt = $this->execute($sql, $this->bindings);

        $this->reset();
        return $stmt->rowCount() > 0;
    }

    /**
     * Compile the current query to SQL with bindings.
     */
    public function toSql(): array
    {
        $sql = "SELECT " . implode(', ', array_map([$this, 'wrap'], $this->columns))
            . " FROM {$this->table}";

        if ($this->joins) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        if ($this->wheres) {
            $sql .= ' ' . $this->compileWheres();
        }
        if ($this->groupBys) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBys);
        }
        if ($this->havings) {
            $sql .= ' HAVING ' . implode(' AND ', $this->havings);
        }
        if ($this->orders) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return [$sql, $this->bindings];
    }

    /**
     * Get SQL with bound values interpolated (for debugging).
     */
    public function toRawSql(): string
    {
        [$sql, $bindings] = $this->toSql();
        foreach ($bindings as $binding) {
            $binding = $this->pdo->quote((string)$binding);
            $sql = preg_replace('/\?/', $binding, $sql, 1);
        }
        return $sql;
    }

    /**
     * Execute a raw query.
     */
    public function raw(string $sql, array $bindings = []): PDOStatement
    {
        return $this->execute($sql, $bindings);
    }

    private function compileWheres(): string
    {
        if (!$this->wheres) return '';
        $sql = 'WHERE ' . preg_replace('/^OR /', '', implode(' ', $this->wheres));
        return $sql;
    }

    private function execute(string $sql, array $bindings): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt;
    }

    private function wrap(string $value): string
    {
        if ($this->isExpression($value)) {
            return $value;
        }
        return "`{$value}`";
    }

    private function isExpression(string $value): bool
    {
        // crude check for SQL functions/operators
        return (bool)preg_match('/\s|\(|\)|\*|,/', $value);
    }

    /**
     * Reset builder state after query.
     */
    private function reset(): void
    {
        $this->columns = ['*'];
        $this->wheres = [];
        $this->bindings = [];
        $this->joins = [];
        $this->orders = [];
        $this->groupBys = [];
        $this->havings = [];
        $this->limit = null;
        $this->offset = null;
        // keep $this->table persistent for reuse
    }
}
