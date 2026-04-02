<?php

declare(strict_types=1);

namespace Arcanum\Forge;

/**
 * Wraps the outcome of a SQL execution with typed accessors.
 *
 * Every Connection::run() call returns a Result, regardless of query type.
 * The caller picks the shape: rows(), first(), scalar(), affectedRows(), etc.
 */
final class Result
{
    /** @var array<string, string> */
    private array $casts = [];

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(
        private readonly array $rows = [],
        private readonly int $affectedRows = 0,
        private readonly string $lastInsertId = '',
    ) {
    }

    /**
     * Return a new Result that applies type casts when rows are accessed.
     *
     * Casts are applied lazily per-row — the underlying row data is not copied.
     *
     * @param array<string, string> $casts Column → type map from Sql::parseCasts().
     */
    public function withCasts(array $casts): self
    {
        $result = clone $this;
        $result->casts = $casts;

        return $result;
    }

    /**
     * All rows as associative arrays.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        if ($this->casts === []) {
            return $this->rows;
        }

        return array_map($this->castRow(...), $this->rows);
    }

    /**
     * First row, or null if empty.
     *
     * @return array<string, mixed>|null
     */
    public function first(): array|null
    {
        if (!isset($this->rows[0])) {
            return null;
        }

        return $this->casts !== [] ? $this->castRow($this->rows[0]) : $this->rows[0];
    }

    /**
     * First column of the first row.
     *
     * @throws \RuntimeException If no rows were returned.
     */
    public function scalar(): mixed
    {
        $row = $this->first();

        if ($row === null) {
            throw new \RuntimeException('Cannot get scalar value from an empty result set.');
        }

        return array_values($row)[0];
    }

    /**
     * Whether zero rows were returned or affected.
     */
    public function isEmpty(): bool
    {
        return $this->rows === [] && $this->affectedRows === 0;
    }

    /**
     * Number of rows affected by a write operation.
     */
    public function affectedRows(): int
    {
        return $this->affectedRows;
    }

    /**
     * Last auto-increment ID from an insert.
     */
    public function lastInsertId(): string
    {
        return $this->lastInsertId;
    }

    /**
     * Number of rows returned.
     */
    public function count(): int
    {
        return count($this->rows);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function castRow(array $row): array
    {
        foreach ($this->casts as $column => $type) {
            if (array_key_exists($column, $row)) {
                $row[$column] = Sql::castValue($row[$column], $type);
            }
        }

        return $row;
    }
}
