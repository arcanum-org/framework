<?php

declare(strict_types=1);

namespace Arcanum\Rune\Command;

use Arcanum\Forge\ConnectionManager;
use Arcanum\Forge\Migration\MigrationFile;
use Arcanum\Forge\Migration\MigrationRepository;
use Arcanum\Forge\Migration\Migrator;
use Arcanum\Rune\Attribute\Description;
use Arcanum\Rune\BuiltInCommand;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;

/**
 * Reverts the most recent migration(s).
 *
 * Usage: php arcanum migrate:rollback [--step=N] [--connection=NAME]
 */
#[Description('Revert the most recent migration(s)')]
final class MigrateRollbackCommand implements BuiltInCommand
{
    public function __construct(
        private readonly ConnectionManager|null $connections = null,
        private readonly string $rootDirectory = '',
    ) {
    }

    public function execute(Input $input, Output $output): int
    {
        if ($this->connections === null) {
            $output->errorLine('Database is not configured.');
            return ExitCode::Failure->value;
        }

        $steps = (int) ($input->option('step') ?? '1');
        if ($steps < 1) {
            $output->errorLine('--step must be a positive integer.');
            return ExitCode::Invalid->value;
        }

        $connectionName = $input->option('connection') ?? '';
        $connection = $this->connections->writeConnection($connectionName);
        $resolvedName = $connectionName !== '' ? $connectionName : $this->connections->defaultConnectionName();
        $driver = $this->connections->driverName($resolvedName);

        $repository = new MigrationRepository($connection, $driver);
        $migrationsPath = $this->rootDirectory . DIRECTORY_SEPARATOR . 'migrations';
        $migrator = new Migrator($connection, $repository, $migrationsPath);

        $result = $migrator->rollback($steps, function (MigrationFile $file, float $elapsedMs) use ($output): void {
            $output->writeLine(sprintf(
                'Rolled back: %s (%dms)',
                $file->filename,
                (int) $elapsedMs,
            ));
        });

        if ($result->hasErrors()) {
            foreach ($result->errors as $error) {
                $output->errorLine($error);
            }
            return ExitCode::Failure->value;
        }

        if ($result->ran === []) {
            $output->writeLine('Nothing to roll back.');
        }

        return ExitCode::Success->value;
    }
}
