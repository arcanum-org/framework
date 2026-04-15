<?php

declare(strict_types=1);

namespace Arcanum\Rune\Command;

use Arcanum\Forge\ConnectionManager;
use Arcanum\Forge\Migration\MigrationRepository;
use Arcanum\Forge\Migration\Migrator;
use Arcanum\Rune\Attribute\Description;
use Arcanum\Rune\BuiltInCommand;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;

/**
 * Shows the status of all migrations (applied and pending).
 *
 * Usage: php arcanum migrate:status [--connection=NAME]
 */
#[Description('Show the status of all migrations')]
final class MigrateStatusCommand implements BuiltInCommand
{
    public function __construct(
        private readonly ConnectionManager|null $connections = null,
        private readonly string $rootDirectory = '',
        private readonly string $migrationsPath = '',
    ) {
    }

    public function execute(Input $input, Output $output): int
    {
        if ($this->connections === null) {
            $output->errorLine('Database is not configured.');
            return ExitCode::Failure->value;
        }

        $connectionName = $input->option('connection') ?? '';
        $connection = $this->connections->writeConnection($connectionName);
        $resolvedName = $connectionName !== '' ? $connectionName : $this->connections->defaultConnectionName();
        $driver = $this->connections->driverName($resolvedName);

        $repository = new MigrationRepository($connection, $driver);
        $migrationsPath = $this->migrationsPath !== ''
            ? $this->migrationsPath
            : $this->rootDirectory . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
        $migrator = new Migrator($connection, $repository, $migrationsPath);

        $status = $migrator->status();

        if ($status->applied === [] && $status->pending === []) {
            $output->writeLine('No migrations found.');
            return ExitCode::Success->value;
        }

        foreach ($status->applied as $applied) {
            $output->writeLine(sprintf(
                '  Applied    %s   %s',
                $applied->version,
                $this->nameFromFilename($applied->filename),
            ));
        }

        foreach ($status->pending as $pending) {
            $output->writeLine(sprintf(
                '  Pending    %s   %s',
                $pending->version,
                $pending->name,
            ));
        }

        return ExitCode::Success->value;
    }

    private function nameFromFilename(string $filename): string
    {
        // Strip version prefix and .sql extension: 20260409120000_create_users.sql → create_users
        if (preg_match('/^\d{14}_(.+)\.sql$/', $filename, $m)) {
            return $m[1];
        }

        return $filename;
    }
}
