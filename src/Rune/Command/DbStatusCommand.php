<?php

declare(strict_types=1);

namespace Arcanum\Rune\Command;

use Arcanum\Forge\ConnectionManager;
use Arcanum\Rune\Attribute\Description;
use Arcanum\Rune\BuiltInCommand;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;

/**
 * Displays database connection status, configuration, and discovered models.
 *
 * Usage: php arcanum db:status
 */
#[Description('Show database connections, status, and discovered models')]
final class DbStatusCommand implements BuiltInCommand
{
    public function __construct(
        private readonly ConnectionManager|null $connections = null,
        private readonly string $domainRoot = '',
    ) {
    }

    public function execute(Input $input, Output $output): int
    {
        if ($this->connections === null) {
            $output->errorLine('Database is not configured.');
            return ExitCode::Failure->value;
        }

        $this->showConnections($this->connections, $output);
        $this->testConnectivity($this->connections, $output);
        $this->showModels($output);

        return ExitCode::Success->value;
    }

    private function showConnections(ConnectionManager $connections, Output $output): void
    {
        $default = $connections->defaultConnectionName();
        $output->writeLine(sprintf('Default connection: %s', $default));
        $output->writeLine('');

        $output->writeLine('Configured connections:');
        foreach ($connections->connectionNames() as $name) {
            $driver = $connections->driverName($name);
            $marker = $name === $default ? ' (default)' : '';
            $output->writeLine(sprintf('  %-15s %s%s', $name, $driver, $marker));
        }

        $domains = $connections->domainMapping();
        if ($domains !== []) {
            $output->writeLine('');
            $output->writeLine('Domain mappings:');
            foreach ($domains as $domain => $connName) {
                $output->writeLine(sprintf('  %-15s → %s', $domain, $connName));
            }
        }
    }

    private function testConnectivity(ConnectionManager $connections, Output $output): void
    {
        $output->writeLine('');
        $output->writeLine('Connectivity:');

        foreach ($connections->connectionNames() as $name) {
            try {
                $conn = $connections->connection($name);
                $conn->query('SELECT 1')->first();
                $output->writeLine(sprintf('  %-15s OK', $name));
            } catch (\Throwable $e) {
                $output->writeLine(sprintf(
                    '  %-15s FAILED — %s',
                    $name,
                    $e->getMessage(),
                ));
            }
        }
    }

    private function showModels(Output $output): void
    {
        if ($this->domainRoot === '' || !is_dir($this->domainRoot)) {
            return;
        }

        $models = $this->discoverModels();

        if ($models === []) {
            return;
        }

        $output->writeLine('');
        $output->writeLine('Model directories:');

        foreach ($models as $domain => $count) {
            $output->writeLine(sprintf('  %-15s %d SQL file(s)', $domain, $count));
        }
    }

    /**
     * @return array<string, int> Domain name → SQL file count.
     */
    private function discoverModels(): array
    {
        $models = [];
        $this->scanDirectory($this->domainRoot, '', $models);
        ksort($models);

        return $models;
    }

    /**
     * @param array<string, int> $models
     */
    private function scanDirectory(string $dir, string $prefix, array &$models): void
    {
        $entries = scandir($dir);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $entry;

            if (!is_dir($path)) {
                continue;
            }

            if ($entry === 'Model') {
                $sqlFiles = glob($path . DIRECTORY_SEPARATOR . '*.sql');
                $count = $sqlFiles !== false ? count($sqlFiles) : 0;
                if ($count > 0) {
                    $domain = ltrim($prefix, '\\');
                    if ($domain !== '') {
                        $models[$domain] = $count;
                    }
                }
                continue;
            }

            if (in_array($entry, ['Command', 'Query'], true)) {
                continue;
            }

            $this->scanDirectory(
                $path,
                $prefix !== '' ? $prefix . '\\' . $entry : $entry,
                $models,
            );
        }
    }
}
