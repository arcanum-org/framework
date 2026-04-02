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
     * All rows as associative arrays.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    /**
     * First row, or null if empty.
     *
     * @return array<string, mixed>|null
     */
    public function first(): array|null
    {
        return $this->rows[0] ?? null;
    }

    /**
     * First column of the first row.
     *
     * @throws \RuntimeException If no rows were returned.
     */
    public function scalar(): mixed
    {
        if ($this->rows === []) {
            throw new \RuntimeException('Cannot get scalar value from an empty result set.');
        }

        return array_values($this->rows[0])[0];
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
}
