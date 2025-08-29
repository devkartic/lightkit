<?php
declare(strict_types=1);

namespace DevKartic\LightKit\Database;

use PDO;
use PDOStatement;
use InvalidArgumentException;

/**
 * Lightweight, framework-agnostic QueryBuilder for PDO.
 *
 * Usage:
 *  $qb = new QueryBuilder($pdo);
 *  $users = $qb->table('users')
 *              ->select('id', 'name')
 *              ->where('status', 'active')
 *              ->orderBy('id', 'DESC')
 *              ->limit(10)
 *              ->get();
 *
 *  $id = $qb->table('users')->insert(['name' => 'John', 'email' => 'j@e.com']);
 *
 *  $affected = $qb->table('users')->where('id', $id)->update(['name' => 'Jane']);
 *
 *  $deleted = $qb->table('users')->where('id', $id)->delete();
 */
final class QueryBuilder
{
    private PDO $pdo;

    // Core state
    private ?string $table = null;
    private array $columns = ['*'];
    private array $wheres = [];           // [[boolean, sql, bindings], ...]
    private array $joins = [];            // [[type, table, first, operator, second], ...]
    private array $groupBys = [];
    private array $havings = [];          // [[boolean, sql, bindings], ...]
    private array $orders = [];           // [[column, direction], ...]
    private ?int $limit = null;
    private ?int $offset = null;

    // Misc
    private array $bindings = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }


    /**
     * Factory: create a QueryBuilder from EnvManager instance.
     */
    public static function fromEnv($env): self
    {
        $pdo = Connection::fromEnv($env);
        return new self($pdo);
    }


    /**
     * Factory: create a QueryBuilder from config array.
     */
    public static function fromConfig(array $config): self
    {
        $pdo = Connection::make($config);
        return new self($pdo);
    }

    /**
     * Start a new query targeting a specific table.
     */
    public function table(string $table): self
    {
        $this->reset();
        $this->table = $table;
        return $this;
    }

    /** Select columns (defaults to *). */
    public function select(string ...$columns): self
    {
        if ($columns) {
            $this->columns = $columns;
        }
        return $this;
    }

    /** Raw select expression helper (e.g. selectRaw('COUNT(*) AS cnt')). */
    public function selectRaw(string $expression): self
    {
        if ($this->columns === ['*']) {
            $this->columns = [];
        }
        $this->columns[] = $expression;
        return $this;
    }

    /**
     * Add a WHERE clause. Supports where('col', 'op', value) and where('col', value).
     */
    public function where(string $column, $operatorOrValue, $value = null, string $boolean = 'AND'): self
    {
        if ($value === null) {
            $operator = '=';
            $val = $operatorOrValue;
        } else {
            $operator = (string)$operatorOrValue;
            $val = $value;
        }

        $this->wheres[] = [strtoupper($boolean), sprintf('%s %s ?', $this->wrap($column), $operator), [$val]];
        return $this;
    }

    public function orWhere(string $column, $operatorOrValue, $value = null): self
    {
        return $this->where($column, $operatorOrValue, $value, 'OR');
    }

    public function whereNull(string $column, string $boolean = 'AND', bool $not = false): self
    {
        $this->wheres[] = [strtoupper($boolean), sprintf('%s IS %sNULL', $this->wrap($column), $not ? 'NOT ' : ''), []];
        return $this;
    }

    public function whereNotNull(string $column, string $boolean = 'AND'): self
    {
        return $this->whereNull($column, $boolean, true);
    }

    public function whereIn(string $column, array $values, string $boolean = 'AND', bool $not = false): self
    {
        if (empty($values)) {
            // Empty IN should never match; use a safe shortcut
            $this->wheres[] = [strtoupper($boolean), $not ? '1=1' : '1=0', []];
            return $this;
        }
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $this->wheres[] = [strtoupper($boolean), sprintf('%s %sIN (%s)', $this->wrap($column), $not ? 'NOT ' : '', $placeholders), array_values($values)];
        return $this;
    }

    public function whereNotIn(string $column, array $values, string $boolean = 'AND'): self
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    public function whereBetween(string $column, $from, $to, string $boolean = 'AND', bool $not = false): self
    {
        $this->wheres[] = [strtoupper($boolean), sprintf('%s %sBETWEEN ? AND ?', $this->wrap($column), $not ? 'NOT ' : ''), [$from, $to]];
        return $this;
    }

    public function orWhereBetween(string $column, $from, $to): self
    {
        return $this->whereBetween($column, $from, $to, 'OR');
    }

    /** JOIN helpers */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = [strtoupper($type), $table, $first, $operator, $second];
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    /** GROUP BY / HAVING */
    public function groupBy(string ...$columns): self
    {
        foreach ($columns as $c) {
            $this->groupBys[] = $this->wrap($c);
        }
        return $this;
    }

    public function having(string $column, string $operator, $value, string $boolean = 'AND'): self
    {
        $this->havings[] = [strtoupper($boolean), sprintf('%s %s ?', $this->wrap($column), $operator), [$value]];
        return $this;
    }

    public function orHaving(string $column, string $operator, $value): self
    {
        return $this->having($column, $operator, $value, 'OR');
    }

    /** ORDER / LIMIT */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = [$this->wrap($column), $dir];
        return $this;
    }

    public function limit(int $limit): self
    {
        if ($limit < 0) throw new InvalidArgumentException('Limit must be >= 0');
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        if ($offset < 0) throw new InvalidArgumentException('Offset must be >= 0');
        $this->offset = $offset;
        return $this;
    }

    /** Execute SELECT and return all rows as associative arrays. */
    public function get(): array
    {
        [$sql, $bindings] = $this->compileSelect();
        $stmt = $this->execute($sql, $bindings);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->resetAfterRun();
        return $rows;
    }

    /** Return first row or null. */
    public function first(): ?array
    {
        $this->limit = $this->limit ?? 1;
        $rows = $this->get();
        return $rows[0] ?? null;
    }

    /** Return COUNT(*) for current query (ignores selected columns). */
    public function count(): int
    {
        $originalColumns = $this->columns;
        $this->columns = ['COUNT(*) AS aggregate'];
        $row = $this->first();
        $this->columns = $originalColumns;
        return (int)($row['aggregate'] ?? 0);
    }

    /** Insert a row; returns lastInsertId (string, as per PDO). */
    public function insert(array $data): string
    {
        if (!$this->table) throw new InvalidArgumentException('No table selected for insert');
        if (empty($data)) throw new InvalidArgumentException('Insert data cannot be empty');

        $columns = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $colSql = implode(', ', array_map([$this, 'wrap'], $columns));

        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $this->wrap($this->table), $colSql, $placeholders);
        $this->execute($sql, array_values($data));
        $this->resetAfterRun();
        return $this->pdo->lastInsertId();
    }

    /** Update rows matching current WHERE; returns affected row count. */
    public function update(array $data): int
    {
        if (!$this->table) throw new InvalidArgumentException('No table selected for update');
        if (empty($data)) throw new InvalidArgumentException('Update data cannot be empty');

        [$whereSql, $whereBindings] = $this->compileWhere();

        $sets = [];
        $bindings = [];
        foreach ($data as $col => $val) {
            $sets[] = sprintf('%s = ?', $this->wrap((string)$col));
            $bindings[] = $val;
        }
        $sql = sprintf('UPDATE %s SET %s%s', $this->wrap($this->table), implode(', ', $sets), $whereSql);

        $stmt = $this->execute($sql, array_merge($bindings, $whereBindings));
        $count = $stmt->rowCount();
        $this->resetAfterRun();
        return $count;
    }

    /** Delete rows matching current WHERE; returns affected row count. */
    public function delete(): int
    {
        if (!$this->table) throw new InvalidArgumentException('No table selected for delete');
        [$whereSql, $whereBindings] = $this->compileWhere();

        $sql = sprintf('DELETE FROM %s%s', $this->wrap($this->table), $whereSql);
        $stmt = $this->execute($sql, $whereBindings);
        $count = $stmt->rowCount();
        $this->resetAfterRun();
        return $count;
    }

    /** Build the SQL without executing, returns [sql, bindings]. */
    public function toSql(): array
    {
        return $this->compileSelect();
    }

    /** ---------------------------------- */
    /** Internals                          */
    /** ---------------------------------- */

    private function compileSelect(): array
    {
        if (!$this->table) throw new InvalidArgumentException('No table selected for select');

        $sql = 'SELECT ' . ($this->columns ? implode(', ', array_map(fn($c) => $this->isExpression($c) ? $c : $this->wrap($c), $this->columns)) : '*');
        $sql .= ' FROM ' . $this->wrap($this->table);

        // Joins
        foreach ($this->joins as [$type, $table, $first, $operator, $second]) {
            $sql .= sprintf(' %s JOIN %s ON %s %s %s', $type, $this->wrap($table), $this->wrap($first), $operator, $this->wrap($second));
        }

        // Where
        [$whereSql, $whereBindings] = $this->compileWhere();
        $sql .= $whereSql;

        // Group By / Having
        if ($this->groupBys) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBys);
        }
        if ($this->havings) {
            $sql .= ' HAVING ' . $this->compileBooleanChain($this->havings, $havingBindings);
        } else {
            $havingBindings = [];
        }

        // Order / Limit
        if ($this->orders) {
            $pieces = array_map(fn($o) => $o[0] . ' ' . $o[1], $this->orders);
            $sql .= ' ORDER BY ' . implode(', ', $pieces);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . (int)$this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . (int)$this->offset;
        }

        $bindings = array_merge($whereBindings, $havingBindings ?? []);
        return [$sql, $bindings];
    }

    private function compileWhere(): array
    {
        if (empty($this->wheres)) {
            return ['', []];
        }
        $sql = ' WHERE ' . $this->compileBooleanChain($this->wheres, $bindings);
        return [$sql, $bindings];
    }

    /**
     * Build boolean chains like: expr1 AND expr2 OR expr3
     * Also collects bindings.
     */
    private function compileBooleanChain(array $parts, ?array &$outBindings = null): string
    {
        $sql = '';
        $bindings = [];
        foreach ($parts as $i => [$bool, $expr, $binds]) {
            $prefix = ($i === 0) ? '' : ' ' . $bool . ' ';
            $sql .= $prefix . $expr;
            foreach ($binds as $b) { $bindings[] = $b; }
        }
        if ($outBindings !== null) {
            $outBindings = $bindings;
        }
        return $sql;
    }

    /** Execute a prepared statement */
    private function execute(string $sql, array $bindings): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        foreach (array_values($bindings) as $i => $value) {
            // PDO placeholders are 1-indexed
            $stmt->bindValue($i + 1, $value);
        }
        $stmt->execute();
        return $stmt;
    }

    /** Reset all query state except the PDO connection. */
    private function reset(): void
    {
        $this->table = null;
        $this->columns = ['*'];
        $this->wheres = [];
        $this->joins = [];
        $this->groupBys = [];
        $this->havings = [];
        $this->orders = [];
        $this->limit = null;
        $this->offset = null;
        $this->bindings = [];
    }

    /** Reset state after a run to avoid leaking conditions into the next query. */
    private function resetAfterRun(): void
    {
        $this->columns = ['*'];
        $this->wheres = [];
        $this->joins = [];
        $this->groupBys = [];
        $this->havings = [];
        $this->orders = [];
        $this->limit = null;
        $this->offset = null;
        $this->bindings = [];
        // keep $this->table so builder can be reused for the same table conveniently
    }

    /** Wrap identifiers with backticks, supporting dotted identifiers table.column */
    private function wrap(string $identifier): string
    {
        // If it looks like a raw expression, leave it.
        if ($this->isExpression($identifier)) {
            return $identifier;
        }
        $segments = explode('.', $identifier);
        $segments = array_map(fn($s) => $s === '*' ? '*' : '`' . str_replace('`', '``', $s) . '`', $segments);
        return implode('.', $segments);
    }

    private function isExpression(string $value): bool
    {
        // Consider anything containing parentheses or spaces or operators as raw (best-effort)
        return (bool)preg_match('/[\(\)\s+\-\/*]/', $value);
    }
}
