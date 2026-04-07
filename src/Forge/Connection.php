<?php

declare(strict_types=1);

namespace Arcanum\Forge;

use Arcanum\Flow\Sequence\Sequencer;

/**
 * Contract for database connections.
 *
 * All Forge consumers (Model, Database, ConnectionManager) depend on this
 * interface, not a concrete implementation. The framework ships PdoConnection
 * as the default. App developers can provide their own implementation
 * wrapping Doctrine DBAL or any other database abstraction layer.
 *
 * Reads and writes are dispatched through separate methods. Callers declare
 * their intent at the call site — there is no read/write sniffing inside
 * the connection.
 */
interface Connection
{
    /**
     * Execute a read-path SQL statement and return a lazy Sequencer of rows.
     *
     * Implementations should stream results rather than materializing them
     * up front. Callers who need multi-pass access or a row count can call
     * {@see Sequencer::toSeries()} to materialize into a Series at the cost
     * of buffering every row.
     *
     * @param array<string, mixed> $params
     * @return Sequencer<array<string, mixed>>
     */
    public function query(string $sql, array $params = []): Sequencer;

    /**
     * Execute a write-path SQL statement (INSERT, UPDATE, DELETE, DDL).
     *
     * @param array<string, mixed> $params
     */
    public function execute(string $sql, array $params = []): WriteResult;

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
