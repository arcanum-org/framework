<?php

declare(strict_types=1);

namespace Arcanum\Forge;

/**
 * Factory/registry for named database connections.
 *
 * Reads driver configuration and lazily instantiates connections on first
 * access. Supports named connections, read/write split, and domain-to-
 * connection mapping.
 */
final class ConnectionManager
{
    /** @var array<string, Connection> */
    private array $resolved = [];

    /**
     * @param string $defaultConnection The name of the default connection.
     * @param array<string, array<string, mixed>> $connections Connection configs keyed by name.
     * @param string|null $readConnection Name of the read replica connection, or null.
     * @param string|null $writeConnection Name of the write primary connection, or null.
     * @param array<string, string> $domains Maps domain names to connection names.
     */
    public function __construct(
        private readonly string $defaultConnection,
        private readonly array $connections,
        private readonly ConnectionFactory $factory,
        private readonly string|null $readConnection = null,
        private readonly string|null $writeConnection = null,
        private readonly array $domains = [],
    ) {
    }

    /**
     * Get a connection by name, or the default connection.
     */
    public function connection(string $name = ''): Connection
    {
        if ($name === '') {
            $name = $this->defaultConnection;
        }

        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        if (!isset($this->connections[$name])) {
            throw new \RuntimeException(
                sprintf('Database connection "%s" is not configured.', $name),
            );
        }

        $this->resolved[$name] = $this->factory->make($this->connections[$name]);

        return $this->resolved[$name];
    }

    /**
     * Get the connection assigned to a domain.
     *
     * Falls back to the default connection if the domain has no override.
     */
    public function connectionForDomain(string $domain): Connection
    {
        $name = $this->domains[$domain] ?? '';

        return $this->connection($name);
    }

    /**
     * Get the read replica connection.
     *
     * Returns the configured read connection if set, otherwise the named
     * or default connection.
     */
    public function readConnection(string $name = ''): Connection
    {
        if ($this->readConnection !== null) {
            return $this->connection($this->readConnection);
        }

        return $this->connection($name);
    }

    /**
     * Get the write primary connection.
     *
     * Returns the configured write connection if set, otherwise the named
     * or default connection.
     */
    public function writeConnection(string $name = ''): Connection
    {
        if ($this->writeConnection !== null) {
            return $this->connection($this->writeConnection);
        }

        return $this->connection($name);
    }

    /**
     * Get the default connection name.
     */
    public function defaultConnectionName(): string
    {
        return $this->defaultConnection;
    }

    /**
     * Get all configured connection names.
     *
     * @return list<string>
     */
    public function connectionNames(): array
    {
        return array_keys($this->connections);
    }

    /**
     * Get the driver name for a configured connection.
     */
    public function driverName(string $connectionName): string
    {
        $config = $this->connections[$connectionName] ?? [];
        $driver = $config['driver'] ?? 'unknown';
        return is_string($driver) ? $driver : 'unknown';
    }

    /**
     * Get the domain-to-connection mapping.
     *
     * @return array<string, string>
     */
    public function domainMapping(): array
    {
        return $this->domains;
    }
}
