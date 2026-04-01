<?php

declare(strict_types=1);

namespace Arcanum\Rune\Command;

use Arcanum\Ignition\ConfigurationCache;
use Arcanum\Rune\Attribute\Description;
use Arcanum\Rune\BuiltInCommand;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;
use Arcanum\Shodo\TemplateCache;
use Arcanum\Vault\CacheManager;

/**
 * Clears application and framework caches.
 *
 * With no arguments, clears all configured Vault stores plus the framework's
 * independent caches (ConfigurationCache, TemplateCache). With `--store=NAME`,
 * clears only the specified store.
 */
#[Description('Clear application and framework caches')]
final class CacheClearCommand implements BuiltInCommand
{
    public function __construct(
        private readonly CacheManager|null $cacheManager = null,
        private readonly ConfigurationCache|null $configCache = null,
        private readonly TemplateCache|null $templateCache = null,
    ) {
    }

    public function execute(Input $input, Output $output): int
    {
        $storeName = $input->option('store');

        if ($storeName !== null) {
            return $this->clearStore($storeName, $output);
        }

        return $this->clearAll($output);
    }

    private function clearStore(string $name, Output $output): int
    {
        if ($this->cacheManager === null) {
            $output->errorLine('CacheManager is not available.');
            return ExitCode::Failure->value;
        }

        try {
            $this->cacheManager->store($name)->clear();
            $output->writeLine(sprintf('Cleared cache store: %s', $name));
        } catch (\RuntimeException $e) {
            $output->errorLine($e->getMessage());
            return ExitCode::Failure->value;
        }

        return ExitCode::Success->value;
    }

    private function clearAll(Output $output): int
    {
        if ($this->cacheManager !== null) {
            foreach ($this->cacheManager->storeNames() as $name) {
                $this->cacheManager->store($name)->clear();
                $output->writeLine(sprintf('Cleared cache store: %s', $name));
            }
        }

        if ($this->configCache !== null) {
            $this->configCache->clear();
            $output->writeLine('Cleared configuration cache');
        }

        if ($this->templateCache !== null) {
            $this->templateCache->clear();
            $output->writeLine('Cleared template cache');
        }

        $output->writeLine('All caches cleared.');
        return ExitCode::Success->value;
    }
}
