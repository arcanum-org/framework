<?php

declare(strict_types=1);

namespace Arcanum\Forge;

/**
 * Outcome of a write-path SQL execution (INSERT, UPDATE, DELETE, DDL).
 *
 * Immutable value object returned by {@see Connection::execute()}. Carries
 * only the two facts a write produces: the number of affected rows and the
 * last auto-increment id. Read results flow through {@see Connection::query()}
 * as a {@see \Arcanum\Flow\Sequence\Sequencer} instead.
 */
final class WriteResult
{
    public function __construct(
        private readonly int $affectedRows,
        private readonly string $lastInsertId = '',
    ) {
    }

    public function affectedRows(): int
    {
        return $this->affectedRows;
    }

    public function lastInsertId(): string
    {
        return $this->lastInsertId;
    }
}
