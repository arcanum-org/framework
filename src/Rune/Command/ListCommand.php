<?php

declare(strict_types=1);

namespace Arcanum\Rune\Command;

use Arcanum\Atlas\CliRouteMap;
use Arcanum\Rune\Attribute\Description;
use Arcanum\Rune\BuiltInCommand;
use Arcanum\Rune\BuiltInRegistry;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;
use Arcanum\Toolkit\Strings;
use Symfony\Component\Finder\Finder;

/**
 * Built-in `list` command — discovers and displays all available commands and queries.
 *
 * Scans the app namespace directory for Command/ and Query/ classes,
 * and includes custom CLI routes from CliRouteMap.
 */
final class ListCommand implements BuiltInCommand
{
    /**
     * @param string $sourceDirectory The directory to scan for convention DTOs.
     * @param string $rootNamespace The root namespace for building FQCNs.
     */
    public function __construct(
        private readonly string $sourceDirectory,
        private readonly string $rootNamespace,
        private readonly CliRouteMap|null $routeMap = null,
        private readonly BuiltInRegistry|null $builtInRegistry = null,
    ) {
    }

    public function execute(Input $input, Output $output): int
    {
        $this->listBuiltIns($output);
        $this->listConventionRoutes($output);
        $this->listCustomRoutes($output);

        return ExitCode::Success->value;
    }

    private function listBuiltIns(Output $output): void
    {
        if ($this->builtInRegistry === null) {
            return;
        }

        $names = $this->builtInRegistry->names();
        if ($names === []) {
            return;
        }

        $output->writeLine('Built-in commands:');
        foreach ($names as $name) {
            $output->writeLine(sprintf('  %s', $name));
        }
        $output->writeLine('');
    }

    private function listConventionRoutes(Output $output): void
    {
        if (!is_dir($this->sourceDirectory)) {
            return;
        }

        $commands = [];
        $queries = [];

        $finder = new Finder();
        $finder->files()->name('*.php')->in($this->sourceDirectory);

        foreach ($finder as $file) {
            $relativePath = $file->getRelativePathname();
            $parts = explode(DIRECTORY_SEPARATOR, $relativePath);

            // Look for Command/ or Query/ in the path.
            $typeIndex = null;
            $type = null;
            foreach ($parts as $i => $part) {
                if ($part === 'Command') {
                    $typeIndex = $i;
                    $type = 'command';
                    break;
                }
                if ($part === 'Query') {
                    $typeIndex = $i;
                    $type = 'query';
                    break;
                }
            }

            if ($type === null || $typeIndex === null) {
                continue;
            }

            // Skip handler files.
            $filename = $file->getFilenameWithoutExtension();
            if (str_ends_with($filename, 'Handler')) {
                continue;
            }

            // Build the CLI command name from path segments.
            $domainParts = array_slice($parts, 0, $typeIndex);
            $classParts = array_slice($parts, $typeIndex + 1);
            $lastIndex = count($classParts) - 1;
            $classParts[$lastIndex] = $file->getFilenameWithoutExtension();

            $segments = array_merge(
                array_map(fn(string $s): string => Strings::kebab($s), $domainParts),
                array_map(fn(string $s): string => Strings::kebab($s), $classParts),
            );

            $name = $type . ':' . implode(':', $segments);
            $description = $this->classDescription($file);

            if ($type === 'command') {
                $commands[$name] = $description;
            } else {
                $queries[$name] = $description;
            }
        }

        if ($queries !== []) {
            $output->writeLine('Queries:');
            $this->writeEntries($output, $queries);
            $output->writeLine('');
        }

        if ($commands !== []) {
            $output->writeLine('Commands:');
            $this->writeEntries($output, $commands);
            $output->writeLine('');
        }
    }

    private function listCustomRoutes(Output $output): void
    {
        if ($this->routeMap === null) {
            return;
        }

        $names = $this->routeMap->names();
        if ($names === []) {
            return;
        }

        $output->writeLine('Custom CLI routes:');
        foreach ($names as $name) {
            $output->writeLine(sprintf('  %s', $name));
        }
        $output->writeLine('');
    }

    /**
     * @param array<string, string> $entries
     */
    private function writeEntries(Output $output, array $entries): void
    {
        ksort($entries);
        $keys = array_map('strlen', array_keys($entries));
        $maxLen = $keys !== [] ? max($keys) : 0;

        foreach ($entries as $name => $description) {
            if ($description !== '') {
                $output->writeLine(sprintf('  %-' . $maxLen . 's  %s', $name, $description));
            } else {
                $output->writeLine(sprintf('  %s', $name));
            }
        }
    }

    /**
     * Try to read #[Description] from a discovered class.
     */
    private function classDescription(\Symfony\Component\Finder\SplFileInfo $file): string
    {
        $relativePath = $file->getRelativePathname();
        $className = str_replace(
            [DIRECTORY_SEPARATOR, '.php'],
            ['\\', ''],
            $relativePath,
        );
        $fqcn = $this->rootNamespace . '\\' . $className;

        if (!class_exists($fqcn)) {
            return '';
        }

        $ref = new \ReflectionClass($fqcn);
        $attrs = $ref->getAttributes(Description::class);

        if ($attrs === []) {
            return '';
        }

        return $attrs[0]->newInstance()->text;
    }
}
