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

    public function __construct(
        private readonly ConnectionManager $connections,
        private readonly DomainContext $context,
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

        $this->modelInstance = new Model(
            directory: $this->context->modelPath(),
            readConnection: $this->resolveReadConnection(),
            writeConnection: $this->resolveWriteConnection(),
        );

        return $this->modelInstance;
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
