<?php

declare(strict_types=1);

namespace Arcanum\Rune\Command;

use Arcanum\Rune\BuiltInCommand;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;
use Symfony\Component\Finder\Finder;

/**
 * Built-in `validate:handlers` command — verifies every DTO has a handler.
 *
 * Scans the source directory for Command/ and Query/ classes, checks that
 * each has a corresponding Handler class, and reports any missing handlers.
 */
final class ValidateHandlersCommand implements BuiltInCommand
{
    /**
     * @param string $sourceDirectory The directory to scan (contains the namespace root).
     * @param string $rootNamespace The root namespace for building FQCNs.
     */
    public function __construct(
        private readonly string $sourceDirectory,
        private readonly string $rootNamespace,
    ) {
    }

    public function execute(Input $input, Output $output): int
    {
        if (!is_dir($this->sourceDirectory)) {
            $output->errorLine(sprintf('Directory not found: %s', $this->sourceDirectory));
            return ExitCode::Failure->value;
        }

        $finder = new Finder();
        $finder->files()->name('*.php')->in($this->sourceDirectory);

        $missing = [];
        $checked = 0;

        foreach ($finder as $file) {
            $relativePath = $file->getRelativePathname();

            // Only check files inside Command/ or Query/ directories.
            if (
                !str_contains($relativePath, 'Command' . DIRECTORY_SEPARATOR)
                && !str_contains($relativePath, 'Query' . DIRECTORY_SEPARATOR)
            ) {
                continue;
            }

            // Skip handler files themselves.
            if (str_ends_with($file->getFilenameWithoutExtension(), 'Handler')) {
                continue;
            }

            // Build the FQCN.
            $className = str_replace(
                [DIRECTORY_SEPARATOR, '.php'],
                ['\\', ''],
                $relativePath,
            );
            $fqcn = $this->rootNamespace . '\\' . $className;
            $handlerFqcn = $fqcn . 'Handler';

            $checked++;

            if (!class_exists($handlerFqcn)) {
                $missing[] = $fqcn;
            }
        }

        if ($missing === []) {
            $output->writeLine(sprintf('All %d DTOs have handlers.', $checked));
            return ExitCode::Success->value;
        }

        $output->errorLine(sprintf('Found %d DTOs missing handlers:', count($missing)));
        foreach ($missing as $class) {
            $output->errorLine(sprintf('  %s → %sHandler not found', $class, $class));
        }

        return ExitCode::Failure->value;
    }
}
