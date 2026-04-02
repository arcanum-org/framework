<?php

declare(strict_types=1);

namespace Arcanum\Forge;

/**
 * Built-in Connection implementation wrapping PDO.
 *
 * Connects lazily on first use. Sets ERRMODE_EXCEPTION and FETCH_ASSOC
 * defaults. Ships with the framework for zero-dependency setups.
 */
final class PdoConnection implements Connection
{
    private ?\PDO $pdo = null;

    public function __construct(
        private readonly string $dsn,
        private readonly string $username = '',
        private readonly string $password = '',
        /** @var array<int, mixed> */
        private readonly array $options = [],
    ) {
    }

    public function run(string $sql, array $params = []): Result
    {
        $pdo = $this->pdo();

        $statement = $pdo->prepare($sql);
        $statement->execute($params);

        $isRead = Sql::isRead($sql);

        /** @var list<array<string, mixed>> $rows */
        $rows = $isRead ? $statement->fetchAll() : [];
        $affectedRows = $isRead ? 0 : $statement->rowCount();
        $lastInsertId = $isRead ? '' : ($pdo->lastInsertId() ?: '');

        return new Result(
            rows: $rows,
            affectedRows: $affectedRows,
            lastInsertId: $lastInsertId,
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
