<?php

declare(strict_types=1);

namespace Arcanum\Rune\Command;

use Arcanum\Forge\ModelGenerator;
use Arcanum\Rune\Attribute\Description;
use Arcanum\Rune\BuiltInCommand;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;

/**
 * Scans all domain Model/ directories and generates typed Model classes.
 *
 * Usage: php arcanum forge:models
 */
#[Description('Generate typed Model classes from SQL files')]
final class ForgeModelsCommand implements BuiltInCommand
{
    public function __construct(
        private readonly string $domainRoot,
        private readonly string $domainNamespace,
        private readonly ModelGenerator $generator = new ModelGenerator(),
    ) {
    }

    public function execute(Input $input, Output $output): int
    {
        $domains = $this->discoverDomains();

        if ($domains === []) {
            $output->writeLine('No Model directories found.');
            return ExitCode::Success->value;
        }

        $generated = 0;

        foreach ($domains as $domain => $modelDir) {
            $namespace = $this->domainNamespace . '\\' . $domain . '\\Model\\Model';

            // Root-level Model (only root-level SQL files).
            $outputPath = $modelDir . DIRECTORY_SEPARATOR . 'Model.php';

            if ($this->generator->generateAndWrite($modelDir, $namespace, $outputPath)) {
                $output->writeLine(sprintf('Generated: %s', $outputPath));
                $generated++;
            }

            // Sub-models (subdirectories with SQL files).
            $subDirs = $this->generator->discoverSubModelDirs($modelDir);

            foreach ($subDirs as $dirName => $subDir) {
                $subNamespace = $namespace . '\\' . $dirName . '\\' . $dirName;
                $subOutput = $subDir . DIRECTORY_SEPARATOR . $dirName . '.php';

                if ($this->generator->generateAndWriteSubModel($subDir, $subNamespace, $subOutput)) {
                    $output->writeLine(sprintf('Generated: %s', $subOutput));
                    $generated++;
                }
            }
        }

        $output->writeLine(sprintf('%d model(s) generated.', $generated));

        return ExitCode::Success->value;
    }

    /**
     * Discover all domain directories that contain a Model/ subdirectory with SQL files.
     *
     * @return array<string, string> Domain name → Model directory path.
     */
    private function discoverDomains(): array
    {
        if (!is_dir($this->domainRoot)) {
            return [];
        }

        $domains = [];

        $this->scanDirectory($this->domainRoot, '', $domains);

        ksort($domains);

        return $domains;
    }

    /**
     * @param array<string, string> $domains
     */
    private function scanDirectory(string $dir, string $prefix, array &$domains): void
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
                if ($this->hasSqlFiles($path)) {
                    $domain = ltrim($prefix, '\\');
                    if ($domain !== '') {
                        $domains[$domain] = $path;
                    }
                }
                continue;
            }

            // Skip CQRS directories — they're inside domains, not domains themselves.
            if (in_array($entry, ['Command', 'Query'], true)) {
                continue;
            }

            $this->scanDirectory(
                $path,
                $prefix !== '' ? $prefix . '\\' . $entry : $entry,
                $domains,
            );
        }
    }

    /**
     * Check if a Model/ directory has SQL files (root-level or in subdirectories).
     */
    private function hasSqlFiles(string $modelDir): bool
    {
        $rootSql = glob($modelDir . DIRECTORY_SEPARATOR . '*.sql');
        if ($rootSql !== false && $rootSql !== []) {
            return true;
        }

        return $this->generator->discoverSubModelDirs($modelDir) !== [];
    }
}
