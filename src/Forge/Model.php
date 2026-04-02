<?php

declare(strict_types=1);

namespace Arcanum\Forge;

/**
 * Dynamic object that maps method calls to SQL files.
 *
 * Each method name resolves to a `.sql` file in the Model directory:
 * `$model->insertOrder([...])` reads `InsertOrder.sql`. SQL content is
 * cached in memory per-request. Read/write routing and @cast annotations
 * are handled automatically.
 */
final class Model
{
    /** @var array<string, string> SQL file contents keyed by method name. */
    private array $sqlCache = [];

    /** @var array<string, array<string, string>> Parsed @cast annotations keyed by method name. */
    private array $castCache = [];

    public function __construct(
        private readonly string $directory,
        private readonly Connection $readConnection,
        private readonly Connection $writeConnection,
    ) {
    }

    /**
     * @param array<int, mixed> $args
     */
    public function __call(string $method, array $args): Result
    {
        $sql = $this->loadSql($method);
        /** @var array<string, mixed> $params */
        $params = $args[0] ?? [];

        $isRead = Sql::isRead($sql);
        $connection = $isRead ? $this->readConnection : $this->writeConnection;

        $result = $connection->run($sql, $params);

        if ($isRead) {
            $casts = $this->loadCasts($method, $sql);

            if ($casts !== []) {
                return new Result(
                    rows: Sql::applyCasts($result->rows(), $casts),
                    affectedRows: $result->affectedRows(),
                    lastInsertId: $result->lastInsertId(),
                );
            }
        }

        return $result;
    }

    private function loadSql(string $method): string
    {
        if (isset($this->sqlCache[$method])) {
            return $this->sqlCache[$method];
        }

        $filename = ucfirst($method) . '.sql';
        $path = $this->directory . DIRECTORY_SEPARATOR . $filename;

        if (!is_file($path)) {
            throw new \RuntimeException(sprintf(
                "Model method '%s' not found — expected file: %s",
                $method,
                $path,
            ));
        }

        $this->sqlCache[$method] = file_get_contents($path) ?: '';

        return $this->sqlCache[$method];
    }

    /**
     * @return array<string, string>
     */
    private function loadCasts(string $method, string $sql): array
    {
        if (isset($this->castCache[$method])) {
            return $this->castCache[$method];
        }

        $this->castCache[$method] = Sql::parseCasts($sql);

        return $this->castCache[$method];
    }
}
