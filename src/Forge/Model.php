<?php

declare(strict_types=1);

namespace Arcanum\Forge;

use Arcanum\Parchment\Reader;

/**
 * Dynamic object that maps method calls to SQL files.
 *
 * Each method name resolves to a `.sql` file in the same directory as
 * the concrete class: `$model->insertOrder(userId: 1, total: 99.99)`
 * reads `InsertOrder.sql` from the directory containing the model class.
 *
 * Supports PHP named arguments, positional arguments, and mixed — the same
 * calling convention as a native PHP function. Named args match SQL bindings
 * by name (camelCase → snake_case). Positional args fill remaining bindings
 * in order of appearance.
 *
 * SQL content is cached in memory per-request. Read/write routing and @cast
 * annotations are handled automatically.
 *
 * Generated model classes extend this class and call execute() directly
 * with absolute SQL file paths, skipping the __call arg resolution.
 */
class Model
{
    /** @var array<string, string> SQL file contents keyed by path. */
    private array $sqlCache = [];

    /** @var array<string, array<string, string>> Parsed @cast annotations keyed by path. */
    private array $castCache = [];

    /** @var array<string, list<string>> Extracted binding names keyed by path. */
    private array $bindingCache = [];

    /** @var string|null Lazily resolved directory for __call dispatch. */
    private string|null $resolvedDirectory = null;

    /**
     * @param ConnectionManager $connections Named connection registry.
     * @param Reader $reader File reader.
     * @param string|null $connectionName Override connection name (for domain mapping / explicit overrides).
     * @param string|null $directory Explicit directory override (for testing). If null, derived via reflection.
     */
    public function __construct(
        private readonly ConnectionManager $connections,
        private readonly Reader $reader = new Reader(),
        private readonly string|null $connectionName = null,
        private readonly string|null $directory = null,
    ) {
    }

    /**
     * Magic method dispatch — resolves named/positional/mixed args then delegates to execute().
     *
     * Derives the SQL directory from the concrete class's file location via
     * reflection (cached after first call). This means ungenerated models
     * find SQL files next to wherever their class file lives on disk.
     *
     * @param array<int|string, mixed> $args
     */
    public function __call(string $method, array $args): Result
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $method)) {
            throw (new InvalidModelMethod($method))
                ->withSuggestion(
                    'Model method names must be valid PHP identifiers'
                        . ' (letters, numbers, underscores)',
                );
        }

        $dir = $this->resolveDirectory();
        $path = $dir . DIRECTORY_SEPARATOR . ucfirst($method) . '.sql';
        $sql = $this->loadSql($path);
        $bindings = $this->loadBindings($path, $sql);
        $params = $bindings !== [] ? Sql::resolveArgs($args, $bindings) : [];

        return $this->execute($path, $params);
    }

    /**
     * Execute a SQL file with pre-resolved parameters.
     *
     * This is the core execution path. Both __call (after arg resolution)
     * and generated model methods (with already-typed params) delegate here.
     * Generated methods pass the absolute path via __DIR__ . '/File.sql'.
     *
     * @param string $sqlPath Absolute path to the .sql file.
     * @param array<string, mixed> $params Named parameters keyed by SQL binding name.
     */
    protected function execute(string $sqlPath, array $params = []): Result
    {
        $sql = $this->loadSql($sqlPath);

        $isRead = Sql::isRead($sql);
        $connection = $isRead ? $this->readConnection() : $this->writeConnection();

        $result = $connection->run($sql, $params);

        if ($isRead) {
            $casts = $this->loadCasts($sqlPath, $sql);

            if ($casts !== []) {
                return $result->withCasts($casts);
            }
        }

        return $result;
    }

    /**
     * Resolve the directory containing SQL files for __call dispatch.
     *
     * If an explicit directory was provided (testing), returns that.
     * Otherwise, derives the directory from the concrete class's file
     * location via reflection (cached after first call).
     */
    private function resolveDirectory(): string
    {
        if ($this->resolvedDirectory !== null) {
            return $this->resolvedDirectory;
        }

        if ($this->directory !== null) {
            $this->resolvedDirectory = $this->directory;
            return $this->resolvedDirectory;
        }

        $reflection = new \ReflectionClass($this);
        $fileName = $reflection->getFileName();

        if ($fileName === false) {
            throw new \RuntimeException(
                'Cannot resolve SQL directory — model class has no file location.',
            );
        }

        $this->resolvedDirectory = dirname($fileName);

        return $this->resolvedDirectory;
    }

    private function loadSql(string $path): string
    {
        if (isset($this->sqlCache[$path])) {
            return $this->sqlCache[$path];
        }

        try {
            $this->sqlCache[$path] = $this->reader->read($path);
        } catch (\RuntimeException) {
            throw (new SqlFileNotFound($path))->withNearbySuggestion();
        }

        return $this->sqlCache[$path];
    }

    /**
     * @return list<string>
     */
    private function loadBindings(string $path, string $sql): array
    {
        if (isset($this->bindingCache[$path])) {
            return $this->bindingCache[$path];
        }

        $this->bindingCache[$path] = Sql::extractBindings($sql);

        return $this->bindingCache[$path];
    }

    /**
     * @return array<string, string>
     */
    private function loadCasts(string $path, string $sql): array
    {
        if (isset($this->castCache[$path])) {
            return $this->castCache[$path];
        }

        $this->castCache[$path] = Sql::parseCasts($sql);

        return $this->castCache[$path];
    }

    protected function readConnection(): Connection
    {
        if ($this->connectionName !== null) {
            return $this->connections->connection($this->connectionName);
        }

        return $this->connections->readConnection();
    }

    protected function writeConnection(): Connection
    {
        if ($this->connectionName !== null) {
            return $this->connections->connection($this->connectionName);
        }

        return $this->connections->writeConnection();
    }
}
