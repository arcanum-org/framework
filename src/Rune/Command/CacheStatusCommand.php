<?php

declare(strict_types=1);

namespace Arcanum\Rune\Command;

use Arcanum\Rune\Attribute\Description;
use Arcanum\Rune\BuiltInCommand;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;
use Arcanum\Vault\CacheManager;

/**
 * Displays configured cache stores, drivers, and framework store assignments.
 */
#[Description('Show configured cache stores and framework assignments')]
final class CacheStatusCommand implements BuiltInCommand
{
    public function __construct(
        private readonly CacheManager|null $cacheManager = null,
    ) {
    }

    public function execute(Input $input, Output $output): int
    {
        if ($this->cacheManager === null) {
            $output->errorLine('CacheManager is not available.');
            return ExitCode::Failure->value;
        }

        $default = $this->cacheManager->defaultStoreName();
        $output->writeLine(sprintf('Default store: %s', $default));
        $output->writeLine('');

        $output->writeLine('Configured stores:');
        foreach ($this->cacheManager->storeNames() as $name) {
            $driver = $this->cacheManager->driverName($name);
            $marker = $name === $default ? ' (default)' : '';
            $output->writeLine(sprintf('  %-15s %s%s', $name, $driver, $marker));
        }

        $mapping = $this->cacheManager->frameworkStoreMapping();
        if ($mapping !== []) {
            $output->writeLine('');
            $output->writeLine('Framework store assignments:');
            foreach ($mapping as $purpose => $storeName) {
                $output->writeLine(sprintf('  %-15s → %s', $purpose, $storeName));
            }
        }

        return ExitCode::Success->value;
    }
}
