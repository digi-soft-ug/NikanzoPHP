<?php

declare(strict_types=1);

namespace Nikanzo\Core\Database;

use PDO;
use PDOStatement;

/**
 * Minimal fluent SQL query builder backed by PDO.
 *
 * All user-supplied values go through prepared statement bindings — no raw
 * interpolation is ever performed. The builder is immutable between calls:
 * each fluent method returns $this for chaining and the query is only
 * executed when you call get(), first(), count(), insert(), update(), or delete().
 *
 * Example:
 *   $users = (new QueryBuilder($pdo, 'users'))
 *       ->select('id', 'name', 'email')
 *       ->where('active', 1)
 *       ->orderBy('name')
 *       ->limit(10)
 *       ->get();
 */
final class QueryBuilder
{
    private string $table;
    /** @var list<string> */
    private array $selects = ['*'];
    /** @var list<array{column: string, operator: string, value: mixed, boolean: string}> */
    private array $wheres = [];
    /** @var list<array{column: string, direction: string}> */
    private array $orders = [];
    /** @var list<string> */
    private array $groups = [];
    private ?int $limitVal  = null;
    private ?int $offsetVal = null;
    /** @var array<string, mixed> */
    private array $bindings = [];
    private int $paramIndex = 0;

    public function __construct(private readonly PDO $pdo, string $table)
    {
        $this->table = $table;
    }

    // ── SELECT ────────────────────────────────────────────────────────────────

    public function select(string ...$columns): static
    {
        $this->selects = $columns === [] ? ['*'] : $columns;

        return $this;
    }

    // ── WHERE ─────────────────────────────────────────────────────────────────

    public function where(string $column, mixed $value, string $operator = '='): static
    {
        return $this->addWhere($column, $operator, $value, 'AND');
    }

    public function orWhere(string $column, mixed $value, string $operator = '='): static
    {
        return $this->addWhere($column, $operator, $value, 'OR');
    }

    public function whereIn(string $column, array $values): static
    {
        if ($values === []) {
            $this->wheres[] = ['raw' => '1=0', 'boolean' => 'AND'];

            return $this;
        }

        $placeholders = [];
        foreach ($values as $v) {
            $key = $this->nextParam();
            $this->bindings[$key] = $v;
            $placeholders[]       = ':' . $key;
        }

        $this->wheres[] = [
            'raw'     => sprintf('%s IN (%s)', $this->quoteColumn($column), implode(', ', $placeholders)),
            'boolean' => 'AND',
        ];

        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->wheres[] = ['raw' => $this->quoteColumn($column) . ' IS NULL', 'boolean' => 'AND'];

        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->wheres[] = ['raw' => $this->quoteColumn($column) . ' IS NOT NULL', 'boolean' => 'AND'];

        return $this;
    }

    // ── ORDER / GROUP / LIMIT ─────────────────────────────────────────────────

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $direction      = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = ['column' => $column, 'direction' => $direction];

        return $this;
    }

    public function groupBy(string ...$columns): static
    {
        $this->groups = $columns;

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limitVal = $limit;

        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offsetVal = $offset;

        return $this;
    }

    // ── FETCH ─────────────────────────────────────────────────────────────────

    /**
     * @return list<array<string, mixed>>
     */
    public function get(): array
    {
        $stmt = $this->execute($this->buildSelect());

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        $result = $this->limit(1)->get();

        return $result[0] ?? null;
    }

    public function count(): int
    {
        $original      = $this->selects;
        $this->selects = ['COUNT(*) AS _count'];
        $stmt          = $this->execute($this->buildSelect());
        $this->selects = $original;
        $row           = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['_count'] ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int|string $id, string $primaryKey = 'id'): ?array
    {
        return $this->where($primaryKey, $id)->first();
    }

    // ── WRITE ─────────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): string|false
    {
        $columns      = array_keys($data);
        $placeholders = [];

        foreach ($data as $column => $value) {
            $key                    = $this->nextParam();
            $this->bindings[$key]   = $value;
            $placeholders[]         = ':' . $key;
        }

        $sql  = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteColumn($this->table),
            implode(', ', array_map([$this, 'quoteColumn'], $columns)),
            implode(', ', $placeholders)
        );

        $this->execute($sql);

        return $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(array $data): int
    {
        $sets = [];
        foreach ($data as $column => $value) {
            $key                  = $this->nextParam();
            $this->bindings[$key] = $value;
            $sets[]               = $this->quoteColumn($column) . ' = :' . $key;
        }

        $sql  = sprintf('UPDATE %s SET %s', $this->quoteColumn($this->table), implode(', ', $sets));
        $sql .= $this->buildWhere();

        return $this->execute($sql)->rowCount();
    }

    public function delete(): int
    {
        $sql = 'DELETE FROM ' . $this->quoteColumn($this->table) . $this->buildWhere();

        return $this->execute($sql)->rowCount();
    }

    // ── RAW ───────────────────────────────────────────────────────────────────

    /**
     * Run a raw query with optional bindings.
     *
     * @param array<string, mixed> $bindings
     * @return list<array<string, mixed>>
     */
    public function raw(string $sql, array $bindings = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── INTERNALS ─────────────────────────────────────────────────────────────

    private function buildSelect(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->selects)
            . ' FROM ' . $this->quoteColumn($this->table);

        $sql .= $this->buildWhere();

        if ($this->groups !== []) {
            $sql .= ' GROUP BY ' . implode(', ', array_map([$this, 'quoteColumn'], $this->groups));
        }

        if ($this->orders !== []) {
            $parts = array_map(
                fn (array $o) => $this->quoteColumn($o['column']) . ' ' . $o['direction'],
                $this->orders
            );
            $sql .= ' ORDER BY ' . implode(', ', $parts);
        }

        if ($this->limitVal !== null) {
            $sql .= ' LIMIT ' . $this->limitVal;
        }

        if ($this->offsetVal !== null) {
            $sql .= ' OFFSET ' . $this->offsetVal;
        }

        return $sql;
    }

    private function buildWhere(): string
    {
        if ($this->wheres === []) {
            return '';
        }

        $parts = [];
        foreach ($this->wheres as $i => $where) {
            $raw  = $where['raw'] ?? null;
            $part = $raw ?? sprintf(
                '%s %s :%s',
                $this->quoteColumn($where['column']),
                $where['operator'],
                $where['param']
            );

            $parts[] = ($i === 0 ? '' : ($where['boolean'] . ' ')) . $part;
        }

        return ' WHERE ' . implode(' ', $parts);
    }

    private function addWhere(string $column, string $operator, mixed $value, string $boolean): static
    {
        $key                  = $this->nextParam();
        $this->bindings[$key] = $value;
        $this->wheres[]       = [
            'column'   => $column,
            'operator' => $operator,
            'param'    => $key,
            'boolean'  => $boolean,
        ];

        return $this;
    }

    private function execute(string $sql): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);

        return $stmt;
    }

    private function quoteColumn(string $column): string
    {
        if ($column === '*' || str_contains($column, '.') || str_contains($column, '(')) {
            return $column;
        }

        return '`' . str_replace('`', '``', $column) . '`';
    }

    private function nextParam(): string
    {
        return 'p' . (++$this->paramIndex);
    }
}
