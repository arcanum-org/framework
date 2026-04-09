<?php

declare(strict_types=1);

namespace Arcanum\Testing\Internal;

use Arcanum\Ignition\RuneKernel;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;

/**
 * RuneKernel subclass used by CliTestSurface.
 *
 * Empty bootstrapper list — TestKernel has already populated the shared
 * container, so the production CLI bootstrap chain (Environment, Auth,
 * CliRouting, etc.) would re-bind services and stomp the test bindings.
 * Calling bootstrap() still wires `$this->container` and binds
 * Transport::Cli.
 *
 * `setRunner()` installs a closure that handle() delegates to when a
 * non-empty argv arrives. Without one, the empty-argv splash path still
 * runs end-to-end through the parent kernel (which writes to whatever
 * `Output` is bound in the container).
 *
 * Internal to the Testing package — not part of the public API surface.
 *
 * @phpstan-type Runner callable(Input, Output): int
 */
final class TestRuneKernel extends RuneKernel
{
    /** @var class-string<\Arcanum\Ignition\Bootstrapper>[] */
    protected array $bootstrappers = [];

    /** @var (callable(Input, Output): int)|null */
    private $runner = null;

    /**
     * @param (callable(Input, Output): int)|null $runner
     */
    public function setRunner(callable|null $runner): void
    {
        $this->runner = $runner;
    }

    /**
     * @param list<string> $argv
     */
    public function handle(array $argv): int
    {
        if ($this->runner !== null && $argv !== [] && count($argv) > 1) {
            $input = Input::fromArgv($argv);
            /** @var Output $output */
            $output = $this->container->get(Output::class);
            return ($this->runner)($input, $output);
        }

        return parent::handle($argv);
    }
}
