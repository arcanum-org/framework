<?php

declare(strict_types=1);

namespace Arcanum\Forge;

/**
 * Creates Connection instances from configuration arrays.
 *
 * Supports mysql, pgsql, and sqlite drivers. Builds the DSN string from
 * config keys (host, port, database, unix_socket, charset, etc.).
 */
final class ConnectionFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public function make(array $config): Connection
    {
        $driver = $this->string($config, 'driver', '');

        return match ($driver) {
            'mysql' => $this->buildMysql($config),
            'pgsql' => $this->buildPgsql($config),
            'sqlite' => $this->buildSqlite($config),
            default => throw new \RuntimeException(
                sprintf('Unsupported database driver "%s".', $driver),
            ),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private function buildMysql(array $config): Connection
    {
        $socket = $this->string($config, 'unix_socket', '');

        if ($socket !== '') {
            $dsn = sprintf(
                'mysql:unix_socket=%s;dbname=%s',
                $socket,
                $this->string($config, 'database', ''),
            );
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s',
                $this->string($config, 'host', '127.0.0.1'),
                $this->string($config, 'port', '3306'),
                $this->string($config, 'database', ''),
            );
        }

        $charset = $this->string($config, 'charset', '');
        if ($charset !== '') {
            $dsn .= ';charset=' . $charset;
        }

        return new Connection(
            dsn: $dsn,
            username: $this->string($config, 'username', ''),
            password: $this->string($config, 'password', ''),
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function buildPgsql(array $config): Connection
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $this->string($config, 'host', '127.0.0.1'),
            $this->string($config, 'port', '5432'),
            $this->string($config, 'database', ''),
        );

        return new Connection(
            dsn: $dsn,
            username: $this->string($config, 'username', ''),
            password: $this->string($config, 'password', ''),
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function buildSqlite(array $config): Connection
    {
        $database = $this->string($config, 'database', ':memory:');

        if ($database === ':memory:') {
            $dsn = 'sqlite::memory:';
        } else {
            $dsn = 'sqlite:' . $database;
        }

        return new Connection(dsn: $dsn);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function string(array $config, string $key, string $default): string
    {
        $value = $config[$key] ?? $default;

        if (is_int($value)) {
            return (string) $value;
        }

        return is_string($value) ? $value : $default;
    }
}
