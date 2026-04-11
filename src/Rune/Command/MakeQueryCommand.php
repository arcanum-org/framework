<?php

declare(strict_types=1);

namespace Arcanum\Rune\Command;

use Arcanum\Rune\Attribute\Description;
use Arcanum\Rune\BuiltInCommand;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;

/**
 * Generates a Query DTO and its handler.
 *
 * Usage: php arcanum make:query Users/Find
 */
#[Description('Generate a Query DTO and handler')]
final class MakeQueryCommand extends Generator implements BuiltInCommand
{
    public function execute(Input $input, Output $output): int
    {
        $name = $input->argument(0);

        if (!$this->validateName($name, $output)) {
            return $this->exitInvalid();
        }

        [$segments, $className] = $this->parseName((string) $name);

        $namespaceParts = array_merge($segments, ['Query']);
        $namespace = $this->buildNamespace(...$namespaceParts);

        $dirParts = array_merge(['app', 'Domain'], $segments, ['Query']);
        $dir = $this->buildPath(...$dirParts);

        $dtoPath = $dir . DIRECTORY_SEPARATOR . $className . '.php';
        $handlerPath = $dir . DIRECTORY_SEPARATOR . $className . 'Handler.php';

        $variables = ['namespace' => $namespace, 'className' => $className];

        $dtoOk = $this->writeFile($dtoPath, $this->renderStub('query', $variables), $output);
        $handlerOk = $this->writeFile($handlerPath, $this->renderStub('query_handler', $variables), $output);

        return ($dtoOk && $handlerOk) ? $this->exitSuccess() : $this->exitFailure();
    }
}
