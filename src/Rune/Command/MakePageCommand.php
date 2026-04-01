<?php

declare(strict_types=1);

namespace Arcanum\Rune\Command;

use Arcanum\Rune\Attribute\Description;
use Arcanum\Rune\BuiltInCommand;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;
use Arcanum\Toolkit\Strings;

/**
 * Generates a Page DTO and its HTML template.
 *
 * Usage: php arcanum make:page About
 *        php arcanum make:page Docs/GettingStarted
 */
#[Description('Generate a Page DTO and HTML template')]
final class MakePageCommand extends Generator implements BuiltInCommand
{
    public function __construct(
        string $rootDirectory,
        string $rootNamespace,
        private readonly string $pagesNamespace = '',
        private readonly string $pagesDirectory = '',
    ) {
        parent::__construct($rootDirectory, $rootNamespace);
    }

    public function execute(Input $input, Output $output): int
    {
        $name = $input->argument(0);

        if (!$this->validateName($name, $output)) {
            return $this->exitInvalid();
        }

        [$segments, $className] = $this->parseName((string) $name);

        $pagesNs = $this->pagesNamespace !== '' ? $this->pagesNamespace : $this->rootNamespace . '\\Pages';
        $pagesDir = $this->pagesDirectory !== '' ? $this->pagesDirectory : 'app' . DIRECTORY_SEPARATOR . 'Pages';

        $namespace = $segments !== [] ? $pagesNs . '\\' . implode('\\', $segments) : $pagesNs;
        $title = Strings::title(preg_replace('/([a-z])([A-Z])/', '$1 $2', $className) ?? $className);

        $dirParts = array_merge([$pagesDir], $segments);
        $dir = $this->buildPath(...$dirParts);

        $dtoPath = $dir . DIRECTORY_SEPARATOR . $className . '.php';
        $templatePath = $dir . DIRECTORY_SEPARATOR . $className . '.html';

        $dtoOk = $this->writeFile(
            $dtoPath,
            $this->renderStub('page', ['namespace' => $namespace, 'className' => $className, 'title' => $title]),
            $output,
        );

        // Template stub is raw (not compiled through Shodo — it IS a Shodo template).
        $templateContent = $this->reader->read(__DIR__ . '/stubs/page_template.stub');
        $templateOk = $this->writeFile($templatePath, $templateContent, $output);

        return ($dtoOk && $templateOk) ? $this->exitSuccess() : $this->exitFailure();
    }
}
