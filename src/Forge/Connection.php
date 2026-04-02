<?php

declare(strict_types=1);

namespace Arcanum\Forge;

/**
 * Contract for database connections.
 *
 * All Forge consumers (Model, Database, ConnectionManager) depend on this
 * interface, not a concrete implementation. The framework ships PdoConnection
 * as the default. App developers can provide their own implementation
 * wrapping Doctrine DBAL or any other database abstraction layer.
 */
interface Connection
{
    /**
     * Execute a SQL statement with named parameters and return a Result.
     *
     * @param array<string, mixed> $params
     */
    public function run(string $sql, array $params = []): Result;

    /**
     * Begin a transaction.
     */
    public function beginTransaction(): void;

    /**
     * Commit the current transaction.
     */
    public function commit(): void;

    /**
     * Roll back the current transaction.
     */
    public function rollBack(): void;
}
