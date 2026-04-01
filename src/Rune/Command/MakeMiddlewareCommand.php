<?php

declare(strict_types=1);

namespace Arcanum\Rune\Command;

use Arcanum\Rune\Attribute\Description;
use Arcanum\Rune\BuiltInCommand;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;

/**
 * Generates an HTTP middleware class.
 *
 * Usage: php arcanum make:middleware RateLimit
 */
#[Description('Generate an HTTP middleware class')]
final class MakeMiddlewareCommand extends Generator implements BuiltInCommand
{
    public function execute(Input $input, Output $output): int
    {
        $name = $input->argument(0);

        if (!$this->validateName($name, $output)) {
            return $this->exitInvalid();
        }

        [$segments, $className] = $this->parseName((string) $name);

        $namespaceParts = array_merge(['Http', 'Middleware'], $segments);
        $namespace = $this->buildNamespace(...$namespaceParts);

        $dirParts = array_merge(['app', 'Http', 'Middleware'], $segments);
        $path = $this->buildPath(...$dirParts) . DIRECTORY_SEPARATOR . $className . '.php';

        $ok = $this->writeFile(
            $path,
            $this->renderStub('middleware', ['namespace' => $namespace, 'className' => $className]),
            $output,
        );

        return $ok ? $this->exitSuccess() : $this->exitFailure();
    }
}
