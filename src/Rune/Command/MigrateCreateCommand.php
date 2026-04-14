<?php

declare(strict_types=1);

namespace Arcanum\Rune\Command;

use Arcanum\Parchment\FileSystem;
use Arcanum\Parchment\Writer;
use Arcanum\Rune\Attribute\Description;
use Arcanum\Rune\BuiltInCommand;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;

/**
 * Scaffolds a new migration file with the correct timestamp and markers.
 *
 * Usage: php arcanum migrate:create create_users
 */
#[Description('Create a new migration file')]
final class MigrateCreateCommand implements BuiltInCommand
{
    public function __construct(
        private readonly string $rootDirectory,
        private readonly Writer $writer = new Writer(),
        private readonly FileSystem $fileSystem = new FileSystem(),
    ) {
    }

    public function execute(Input $input, Output $output): int
    {
        $name = $input->argument(0);

        if ($name === null || $name === '') {
            $output->errorLine('Usage: migrate:create <name>');
            $output->errorLine('  Example: migrate:create create_users');
            return ExitCode::Invalid->value;
        }

        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $output->errorLine(sprintf(
                'Invalid migration name "%s". Use lowercase letters, numbers, and underscores.',
                $name,
            ));
            return ExitCode::Invalid->value;
        }

        $migrationsDir = $this->rootDirectory . DIRECTORY_SEPARATOR . 'migrations';

        if (!is_dir($migrationsDir)) {
            $this->fileSystem->mkdir($migrationsDir, 0755);
        }

        $version = (new \DateTimeImmutable())->format('YmdHisv');
        $filename = $version . '_' . $name . '.sql';
        $path = $migrationsDir . DIRECTORY_SEPARATOR . $filename;

        $stub = <<<'SQL'
            -- @migrate up


            -- @migrate down

            SQL;

        // Remove the leading indentation from the heredoc.
        $stub = implode("\n", array_map('ltrim', explode("\n", $stub)));

        $this->writer->write($path, $stub);

        $output->writeLine(sprintf('Created: migrations/%s', $filename));

        return ExitCode::Success->value;
    }
}
