<?php

declare(strict_types=1);

namespace Arcanum\Test\Testing;

use Arcanum\Auth\SimpleIdentity;
use Arcanum\Rune\Output;
use Arcanum\Testing\BufferedOutput;
use Arcanum\Testing\CliResult;
use Arcanum\Testing\CliTestSurface;
use Arcanum\Testing\Internal\TestRuneKernel;
use Arcanum\Testing\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CliTestSurface::class)]
#[CoversClass(BufferedOutput::class)]
#[CoversClass(CliResult::class)]
#[CoversClass(TestRuneKernel::class)]
final class CliTestSurfaceTest extends TestCase
{
    public function testKernelCliReturnsMemoizedSurface(): void
    {
        $kernel = new TestKernel();

        $first = $kernel->cli();
        $second = $kernel->cli();

        $this->assertInstanceOf(CliTestSurface::class, $first);
        $this->assertSame($first, $second);
    }

    public function testRunDispatchesToInstalledRunner(): void
    {
        $kernel = new TestKernel();
        $surface = $kernel->cli()->setRunner(function ($input, Output $output): int {
            $output->writeLine('hello ' . $input->command());
            $output->errorLine('warn');
            return 7;
        });

        $result = $surface->run(['arcanum', 'greet']);

        $this->assertInstanceOf(CliResult::class, $result);
        $this->assertSame(7, $result->exitCode);
        $this->assertSame('hello greet' . PHP_EOL, $result->stdout);
        $this->assertSame('warn' . PHP_EOL, $result->stderr);
    }

    public function testEmptyArgvFallsThroughToParentSplash(): void
    {
        // No runner installed and only the script name → parent RuneKernel
        // writes the splash to the bound BufferedOutput.
        $kernel = new TestKernel();

        $result = $kernel->cli()->run(['arcanum']);

        $this->assertSame(0, $result->exitCode);
        $this->assertStringContainsString('Arcanum', $result->stdout);
        $this->assertStringContainsString('Usage:', $result->stdout);
        $this->assertSame('', $result->stderr);
    }

    public function testEachRunGetsAFreshOutputBuffer(): void
    {
        $kernel = new TestKernel();
        $surface = $kernel->cli()->setRunner(function ($input, Output $output): int {
            $output->write($input->command());
            return 0;
        });

        $first = $surface->run(['arcanum', 'one']);
        $second = $surface->run(['arcanum', 'two']);

        $this->assertSame('one', $first->stdout);
        $this->assertSame('two', $second->stdout);
    }

    public function testCrossTransportSharesContainerAndIdentity(): void
    {
        // The whole point of memoizing both surfaces against one container:
        // an actingAs() set up before either surface is touched is visible
        // through both http() and cli().
        $kernel = new TestKernel();
        $alice = new SimpleIdentity('alice');
        $kernel->actingAs($alice);

        $kernel->cli()->setRunner(function () use ($kernel, $alice): int {
            $active = $kernel->container()->get(\Arcanum\Auth\ActiveIdentity::class);
            return $active instanceof \Arcanum\Auth\ActiveIdentity && $active->resolve() === $alice ? 0 : 1;
        });

        $result = $kernel->cli()->run(['arcanum', 'whoami']);

        $this->assertSame(0, $result->exitCode);
    }

    public function testRunnerCanBeClearedByPassingNull(): void
    {
        $kernel = new TestKernel();
        $surface = $kernel->cli()->setRunner(fn() => 5);

        $surface->setRunner(null);

        $result = $surface->run(['arcanum']);

        // Falls back to the splash path → exit 0.
        $this->assertSame(0, $result->exitCode);
        $this->assertStringContainsString('Usage:', $result->stdout);
    }
}
