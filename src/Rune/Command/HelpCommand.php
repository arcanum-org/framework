<?php

declare(strict_types=1);

namespace Arcanum\Rune\Command;

use Arcanum\Atlas\Router;
use Arcanum\Rune\BuiltInCommand;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\HelpWriter;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;

/**
 * Built-in `help` command — alias for `<command> --help`.
 *
 * Usage: php arcanum help query:health
 */
final class HelpCommand implements BuiltInCommand
{
    public function __construct(
        private readonly Router $router,
    ) {
    }

    public function execute(Input $input, Output $output): int
    {
        $target = $input->argument(0);

        if ($target === null || $target === '') {
            $output->errorLine('Usage: php arcanum help <command:|query:><name>');
            return ExitCode::Invalid->value;
        }

        // Create a synthetic Input with --help flag to reuse router resolution.
        $helpInput = new Input($target, flags: ['help' => true]);
        $route = $this->router->resolve($helpInput);

        $writer = new HelpWriter($output);
        $writer->write($target, $route->dtoClass, $route->isCommand());

        return ExitCode::Success->value;
    }
}
