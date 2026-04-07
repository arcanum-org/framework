<?php

declare(strict_types=1);

namespace Arcanum\Forge;

use Arcanum\Flow\Sequence\Cursor;
use Arcanum\Flow\Sequence\Sequencer;

/**
 * Built-in Connection implementation wrapping PDO.
 *
 * Connects lazily on first use. Sets ERRMODE_EXCEPTION and FETCH_ASSOC
 * defaults. Ships with the framework for zero-dependency setups.
 *
 * Reads stream row-by-row through a Cursor — peak memory stays flat
 * regardless of result size. Writes return a WriteResult carrying the
 * affected-row count and last insert id.
 */
final class PdoConnection implements Connection
{
    private ?\PDO $pdo = null;

    /**
     * @param array<int, mixed> $options
     */
    public function __construct(
        private readonly string $dsn,
        private readonly string $username = '',
        private readonly string $password = '',
        private readonly array $options = [],
    ) {
    }

    /**
     * @return Sequencer<array<string, mixed>>
     */
    public function query(string $sql, array $params = []): Sequencer
    {
        $pdo = $this->pdo();

        $statement = $pdo->prepare($sql);
        $statement->execute($params);

        return Cursor::open(
            static function () use ($statement): \Generator {
                $index = 0;
                while (true) {
                    /** @var array<string, mixed>|false $row */
                    $row = $statement->fetch();
                    if ($row === false) {
                        return;
                    }
                    yield $index++ => $row;
                }
            },
            static function () use ($statement): void {
                $statement->closeCursor();
            },
        );
    }

    public function execute(string $sql, array $params = []): WriteResult
    {
        $pdo = $this->pdo();

        $statement = $pdo->prepare($sql);
        $statement->execute($params);

        return new WriteResult(
            affectedRows: $statement->rowCount(),
            lastInsertId: $pdo->lastInsertId() ?: '',
        );
    }

    public function beginTransaction(): void
    {
        $this->pdo()->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo()->commit();
    }

    public function rollBack(): void
    {
        $this->pdo()->rollBack();
    }

    /**
     * Whether this connection has been established.
     */
    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    private function pdo(): \PDO
    {
        if ($this->pdo === null) {
            $this->pdo = new \PDO(
                $this->dsn,
                $this->username,
                $this->password,
                $this->options + [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ],
            );
        }

        return $this->pdo;
    }
}
