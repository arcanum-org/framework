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
 * independent caches (ConfigurationCache, TemplateCache) and any stray
 * subdirectories under files/cache/ that aren't reached by either of the
 * structured paths above. With `--store=NAME`, clears only the specified store.
 */
#[Description('Clear application and framework caches')]
final class CacheClearCommand implements BuiltInCommand
{
    public function __construct(
        private readonly CacheManager|null $cacheManager = null,
        private readonly ConfigurationCache|null $configCache = null,
        private readonly TemplateCache|null $templateCache = null,
        private readonly string $frameworkCacheDirectory = '',
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

        $this->clearStrayCacheDirectories($output);

        $output->writeLine('All caches cleared.');
        return ExitCode::Success->value;
    }

    /**
     * Walk the framework cache directory and clear any subdirectories that
     * weren't already handled by the structured clears above.
     *
     * Catches helper discovery, page discovery, middleware discovery, and
     * any future framework cache surfaces that don't have a dedicated
     * Clearable injected here. Application caches like the file driver
     * (typically files/cache/app/) are reached via $cacheManager and so
     * already cleared above.
     */
    private function clearStrayCacheDirectories(Output $output): void
    {
        if ($this->frameworkCacheDirectory === '' || !is_dir($this->frameworkCacheDirectory)) {
            return;
        }

        $entries = scandir($this->frameworkCacheDirectory);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $this->frameworkCacheDirectory . DIRECTORY_SEPARATOR . $entry;

            if (!is_dir($path)) {
                continue;
            }

            // Skip directories already handled by the structured clears
            // above so we don't double-report them.
            if (in_array($entry, ['app', 'templates'], true)) {
                continue;
            }

            $this->deleteDirectoryContents($path);
            $output->writeLine(sprintf('Cleared cache directory: %s', $entry));
        }
    }

    /**
     * Recursively delete the contents of a directory, but leave the
     * directory itself in place so file drivers don't lose their root
     * on the next request.
     */
    private function deleteDirectoryContents(string $directory): void
    {
        $entries = scandir($directory);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($path)) {
                $this->deleteDirectoryContents($path);
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
    }
}
