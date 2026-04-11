<?php

declare(strict_types=1);

namespace Arcanum\Testing;

use Arcanum\Cabinet\Application;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;
use Arcanum\Testing\Internal\TestRuneKernel;

/**
 * Fluent CLI surface for tests.
 *
 * `run(array $argv)` dispatches argv through a wrapped RuneKernel and
 * returns a `CliResult` carrying the exit code plus everything captured
 * on stdout/stderr. A fresh `BufferedOutput` is bound for each call so
 * captures never leak between commands.
 *
 * `setRunner()` installs a closure that the kernel delegates to for
 * non-empty argv, parallel to `HttpTestSurface::setCoreHandler()`. With
 * no runner installed, the empty-argv splash path still runs end-to-end
 * through the real RuneKernel — useful for round-trip verification.
 */
final class CliTestSurface
{
    private bool $bootstrapped = false;

    public function __construct(
        private readonly TestRuneKernel $kernel,
        private readonly Application $container,
    ) {
    }

    /**
     * @param (callable(Input, Output): int)|null $runner
     */
    public function setRunner(callable|null $runner): self
    {
        $this->kernel->setRunner($runner);

        return $this;
    }

    /**
     * @param list<string> $argv Raw CLI arguments, including the script name at index 0.
     */
    public function run(array $argv): CliResult
    {
        $output = new BufferedOutput();
        $this->container->instance(Output::class, $output);

        if (!$this->bootstrapped) {
            $this->kernel->bootstrap($this->container);
            $this->bootstrapped = true;
        }

        $exitCode = $this->kernel->handle($argv);

        return new CliResult($exitCode, $output->stdout(), $output->stderr());
    }
}
