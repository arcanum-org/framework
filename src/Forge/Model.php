<?php

declare(strict_types=1);

namespace Arcanum\Forge;

use Arcanum\Parchment\Reader;

/**
 * Dynamic object that maps method calls to SQL files.
 *
 * Each method name resolves to a `.sql` file in the Model directory:
 * `$model->insertOrder(userId: 1, total: 99.99)` reads `InsertOrder.sql`.
 *
 * Supports PHP named arguments, positional arguments, and mixed — the same
 * calling convention as a native PHP function. Named args match SQL bindings
 * by name (camelCase → snake_case). Positional args fill remaining bindings
 * in order of appearance.
 *
 * SQL content is cached in memory per-request. Read/write routing and @cast
 * annotations are handled automatically.
 */
final class Model
{
    /** @var array<string, string> SQL file contents keyed by method name. */
    private array $sqlCache = [];

    /** @var array<string, array<string, string>> Parsed @cast annotations keyed by method name. */
    private array $castCache = [];

    /** @var array<string, list<string>> Extracted binding names keyed by method name. */
    private array $bindingCache = [];

    public function __construct(
        private readonly string $directory,
        private readonly Connection $readConnection,
        private readonly Connection $writeConnection,
        private readonly Reader $reader = new Reader(),
    ) {
    }

    /**
     * @param array<int|string, mixed> $args
     */
    public function __call(string $method, array $args): Result
    {
        $sql = $this->loadSql($method);
        $bindings = $this->loadBindings($method, $sql);
        $params = $bindings !== [] ? Sql::resolveArgs($args, $bindings) : [];

        $isRead = Sql::isRead($sql);
        $connection = $isRead ? $this->readConnection : $this->writeConnection;

        $result = $connection->run($sql, $params);

        if ($isRead) {
            $casts = $this->loadCasts($method, $sql);

            if ($casts !== []) {
                return $result->withCasts($casts);
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

        try {
            $this->sqlCache[$method] = $this->reader->read($path);
        } catch (\RuntimeException) {
            throw new \RuntimeException(sprintf(
                "Model method '%s' not found — expected file: %s",
                $method,
                $path,
            ));
        }

        return $this->sqlCache[$method];
    }

    /**
     * @return list<string>
     */
    private function loadBindings(string $method, string $sql): array
    {
        if (isset($this->bindingCache[$method])) {
            return $this->bindingCache[$method];
        }

        $this->bindingCache[$method] = Sql::extractBindings($sql);

        return $this->bindingCache[$method];
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
