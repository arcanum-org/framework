<?php

declare(strict_types=1);

namespace Arcanum\Forge;

use Arcanum\Gather\Configuration;

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
        $cfg = new Configuration($config);
        $driver = $cfg->asString('driver');

        return match ($driver) {
            'mysql' => $this->buildMysql($cfg),
            'pgsql' => $this->buildPgsql($cfg),
            'sqlite' => $this->buildSqlite($cfg),
            default => throw new \RuntimeException(
                sprintf('Unsupported database driver "%s".', $driver),
            ),
        };
    }

    private function buildMysql(Configuration $cfg): Connection
    {
        $socket = $cfg->asString('unix_socket');

        if ($socket !== '') {
            $dsn = sprintf(
                'mysql:unix_socket=%s;dbname=%s',
                $socket,
                $cfg->asString('database'),
            );
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s',
                $cfg->asString('host', '127.0.0.1'),
                $cfg->asString('port', '3306'),
                $cfg->asString('database'),
            );
        }

        $charset = $cfg->asString('charset');
        if ($charset !== '') {
            $dsn .= ';charset=' . $charset;
        }

        return new PdoConnection(
            dsn: $dsn,
            username: $cfg->asString('username'),
            password: $cfg->asString('password'),
        );
    }

    private function buildPgsql(Configuration $cfg): Connection
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $cfg->asString('host', '127.0.0.1'),
            $cfg->asString('port', '5432'),
            $cfg->asString('database'),
        );

        return new PdoConnection(
            dsn: $dsn,
            username: $cfg->asString('username'),
            password: $cfg->asString('password'),
        );
    }

    private function buildSqlite(Configuration $cfg): Connection
    {
        $database = $cfg->asString('database', ':memory:');

        if ($database === ':memory:') {
            $dsn = 'sqlite::memory:';
        } else {
            $dsn = 'sqlite:' . $database;
        }

        return new PdoConnection(dsn: $dsn);
    }
}
