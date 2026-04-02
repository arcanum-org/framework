<?php

declare(strict_types=1);

namespace Arcanum\Forge;

/**
 * Developer-facing database service.
 *
 * Provides domain-scoped Model access, explicit transactions, and
 * connection overrides. Handlers receive this via DI and interact
 * with SQL files through the `model` property.
 *
 * Usage:
 *   $db->model->products(category: 'shoes')
 *   $db->connection('legacy')->model->activeUsers()
 *   $db->transaction(function (Database $db) { ... })
 *
 * @property-read Model $model
 */
final class Database
{
    private Model|null $modelInstance = null;

    /**
     * @param bool|null $autoForge True = auto-regenerate stale models, false = throw, null = skip.
     */
    public function __construct(
        private readonly ConnectionManager $connections,
        private readonly DomainContext $context,
        private readonly string $domainNamespace = '',
        private readonly bool|null $autoForge = null,
        private readonly ModelGenerator|null $generator = null,
        private readonly string|null $connectionOverride = null,
    ) {
    }

    /**
     * @return Model
     */
    public function __get(string $name): mixed
    {
        if ($name === 'model') {
            return $this->resolveModel();
        }

        throw new \RuntimeException(sprintf(
            "Undefined property: %s::\$%s",
            self::class,
            $name,
        ));
    }

    public function __isset(string $name): bool
    {
        return $name === 'model';
    }

    /**
     * Return a new Database instance bound to the named connection.
     *
     * Same domain scope — only the connection changes. Useful for
     * cross-database queries within the same domain.
     */
    public function connection(string $name): self
    {
        return new self(
            connections: $this->connections,
            context: $this->context,
            domainNamespace: $this->domainNamespace,
            autoForge: $this->autoForge,
            generator: $this->generator,
            connectionOverride: $name,
        );
    }

    /**
     * Execute a callback inside a transaction on the write connection.
     *
     * Commits on success, rolls back on exception. The exception is rethrown.
     */
    public function transaction(\Closure $callback): mixed
    {
        $write = $this->resolveWriteConnection();
        $write->beginTransaction();

        try {
            $result = $callback($this);
            $write->commit();

            return $result;
        } catch (\Throwable $e) {
            $write->rollBack();

            throw $e;
        }
    }

    private function resolveModel(): Model
    {
        if ($this->modelInstance !== null) {
            return $this->modelInstance;
        }

        $modelDir = $this->context->modelPath();
        $readConn = $this->resolveReadConnection();
        $writeConn = $this->resolveWriteConnection();

        // Check for a generated model class at {DomainNamespace}\{Domain}\Model.
        if ($this->domainNamespace !== '') {
            $generatedClass = $this->domainNamespace . '\\' . $this->context->get() . '\\Model';
            $classFile = $modelDir . DIRECTORY_SEPARATOR . '..'
                . DIRECTORY_SEPARATOR . 'Model.php';

            $this->handleStaleness($modelDir, $classFile, $generatedClass);

            if (class_exists($generatedClass) && is_subclass_of($generatedClass, Model::class)) {
                $this->modelInstance = new $generatedClass(
                    directory: $modelDir,
                    readConnection: $readConn,
                    writeConnection: $writeConn,
                );

                return $this->modelInstance;
            }
        }

        $this->modelInstance = new Model(
            directory: $modelDir,
            readConnection: $readConn,
            writeConnection: $writeConn,
        );

        return $this->modelInstance;
    }

    private function handleStaleness(
        string $modelDir,
        string $classFile,
        string $generatedClass,
    ): void {
        if ($this->autoForge === null || $this->generator === null) {
            return;
        }

        if (!$this->generator->isStale($modelDir, $classFile)) {
            return;
        }

        if ($this->autoForge) {
            $realPath = is_file($classFile)
                ? (realpath(dirname($classFile)) ?: dirname($classFile))
                : dirname($classFile);
            $outputPath = $realPath . DIRECTORY_SEPARATOR . 'Model.php';

            $this->generator->generateAndWrite($modelDir, $generatedClass, $outputPath);
            return;
        }

        throw new \RuntimeException(sprintf(
            "Model class '%s' is stale — run 'php arcanum forge:models' to regenerate.",
            $generatedClass,
        ));
    }

    private function resolveReadConnection(): Connection
    {
        if ($this->connectionOverride !== null) {
            return $this->connections->connection($this->connectionOverride);
        }

        $domain = $this->context->get();

        if (
            $this->connections->domainMapping() !== []
            && isset($this->connections->domainMapping()[$domain])
        ) {
            return $this->connections->connection(
                $this->connections->domainMapping()[$domain],
            );
        }

        return $this->connections->readConnection();
    }

    private function resolveWriteConnection(): Connection
    {
        if ($this->connectionOverride !== null) {
            return $this->connections->connection($this->connectionOverride);
        }

        $domain = $this->context->get();

        if (
            $this->connections->domainMapping() !== []
            && isset($this->connections->domainMapping()[$domain])
        ) {
            return $this->connections->connection(
                $this->connections->domainMapping()[$domain],
            );
        }

        return $this->connections->writeConnection();
    }
}
