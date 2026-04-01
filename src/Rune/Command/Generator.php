<?php

declare(strict_types=1);

namespace Arcanum\Rune\Command;

use Arcanum\Parchment\FileSystem;
use Arcanum\Parchment\Reader;
use Arcanum\Parchment\Writer;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Output;
use Arcanum\Shodo\TemplateCompiler;
use Arcanum\Toolkit\Strings;

/**
 * Base class for code generators.
 *
 * Provides stub rendering via Shodo, directory creation, overwrite
 * protection, and name parsing shared by all make:* commands.
 */
abstract class Generator
{
    protected readonly TemplateCompiler $compiler;
    protected readonly Writer $writer;
    protected readonly FileSystem $fileSystem;
    protected readonly Reader $reader;

    public function __construct(
        protected readonly string $rootDirectory,
        protected readonly string $rootNamespace,
        TemplateCompiler $compiler = new TemplateCompiler(),
        Writer $writer = new Writer(),
        FileSystem $fileSystem = new FileSystem(),
        Reader $reader = new Reader(),
    ) {
        $this->compiler = $compiler;
        $this->writer = $writer;
        $this->fileSystem = $fileSystem;
        $this->reader = $reader;
    }

    /**
     * Parse a slash-separated name into namespace segments and class name.
     *
     * "Contact/Submit" → ['Contact'], 'Submit'
     * "Admin/Users/BanUser" → ['Admin', 'Users'], 'BanUser'
     * "Submit" → [], 'Submit'
     *
     * @return array{list<string>, string} [segments, className]
     */
    protected function parseName(string $name): array
    {
        $parts = array_map(
            fn(string $s) => Strings::pascal($s),
            explode('/', $name),
        );

        $className = array_pop($parts);

        return [$parts, $className];
    }

    /**
     * Validate a generator name argument.
     */
    protected function validateName(string|null $name, Output $output): bool
    {
        if ($name === null || $name === '') {
            $output->errorLine('Name argument is required.');
            return false;
        }

        if (preg_match('/[^a-zA-Z0-9\/]/', $name)) {
            $output->errorLine(
                sprintf('Invalid name "%s". Use only letters, numbers, and forward slashes.', $name),
            );
            return false;
        }

        return true;
    }

    /**
     * Render a stub template with variables via Shodo's TemplateCompiler.
     *
     * Uses the compiler's render() method for direct substitution — stubs
     * contain PHP source code, so compile() (which produces eval-able PHP)
     * would conflict with PHP tags in the output.
     *
     * @param array<string, string> $variables
     */
    protected function renderStub(string $stubName, array $variables): string
    {
        $stubPath = __DIR__ . '/stubs/' . $stubName . '.stub';
        $source = $this->reader->read($stubPath);

        return $this->compiler->render($source, $variables);
    }

    /**
     * Write a file, refusing to overwrite existing files.
     *
     * @return bool True if written, false if file already exists.
     */
    protected function writeFile(string $path, string $content, Output $output): bool
    {
        if ($this->fileSystem->isFile($path)) {
            $output->errorLine(sprintf('File already exists: %s', $path));
            return false;
        }

        $dir = dirname($path);
        if (!$this->fileSystem->isDirectory($dir)) {
            $this->fileSystem->mkdir($dir);
        }

        $this->writer->write($path, $content);
        $output->writeLine(sprintf('Created: %s', $path));
        return true;
    }

    /**
     * Build an absolute file path from the root directory and relative segments.
     */
    protected function buildPath(string ...$segments): string
    {
        return $this->rootDirectory . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
    }

    /**
     * Build a namespace from the root namespace and additional segments.
     */
    protected function buildNamespace(string ...$segments): string
    {
        return $this->rootNamespace . '\\' . implode('\\', $segments);
    }

    protected function exitInvalid(): int
    {
        return ExitCode::Invalid->value;
    }

    protected function exitFailure(): int
    {
        return ExitCode::Failure->value;
    }

    protected function exitSuccess(): int
    {
        return ExitCode::Success->value;
    }
}
