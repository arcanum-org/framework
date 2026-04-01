<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune;

use Arcanum\Cabinet\Application;
use Arcanum\Ignition\Bootstrapper;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\RuneKernel;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(RuneKernel::class)]
#[UsesClass(ExitCode::class)]
final class RuneKernelTest extends TestCase
{
    // ---------------------------------------------------------------
    // Constructor & directory accessors
    // ---------------------------------------------------------------

    public function testRootDirectoryIsStored(): void
    {
        // Arrange & Act
        $kernel = new RuneKernel('/app');

        // Assert
        $this->assertSame('/app', $kernel->rootDirectory());
    }

    public function testConfigDirectoryDefaultsToConfigSubdirectory(): void
    {
        // Arrange & Act
        $kernel = new RuneKernel('/app');

        // Assert
        $this->assertSame('/app' . DIRECTORY_SEPARATOR . 'config', $kernel->configDirectory());
    }

    public function testFilesDirectoryDefaultsToFilesSubdirectory(): void
    {
        // Arrange & Act
        $kernel = new RuneKernel('/app');

        // Assert
        $this->assertSame('/app' . DIRECTORY_SEPARATOR . 'files', $kernel->filesDirectory());
    }

    public function testCustomConfigDirectory(): void
    {
        // Arrange & Act
        $kernel = new RuneKernel('/app', '/custom/config');

        // Assert
        $this->assertSame('/custom/config', $kernel->configDirectory());
    }

    public function testCustomFilesDirectory(): void
    {
        // Arrange & Act
        $kernel = new RuneKernel('/app', filesDirectory: '/custom/files');

        // Assert
        $this->assertSame('/custom/files', $kernel->filesDirectory());
    }

    public function testRootDirectoryTrimsTrailingSlash(): void
    {
        // Arrange & Act
        $kernel = new RuneKernel('/app/');

        // Assert
        $this->assertSame('/app' . DIRECTORY_SEPARATOR . 'config', $kernel->configDirectory());
        $this->assertSame('/app' . DIRECTORY_SEPARATOR . 'files', $kernel->filesDirectory());
    }

    // ---------------------------------------------------------------
    // bootstrap()
    // ---------------------------------------------------------------

    public function testBootstrapRunsBootstrappers(): void
    {
        // Arrange — RuneKernel has 4 bootstrappers (Environment, Configuration, Logger, Exceptions)
        $kernel = new RuneKernel('/app');

        $bootstrapper = $this->createMock(Bootstrapper::class);
        $bootstrapper->expects($this->exactly(4))->method('bootstrap');

        $container = $this->createStub(Application::class);
        $container->method('get')->willReturn($bootstrapper);

        // Act
        $kernel->bootstrap($container);
    }

    public function testBootstrapOnlyRunsOnce(): void
    {
        // Arrange
        $kernel = new RuneKernel('/app');

        $bootstrapper = $this->createMock(Bootstrapper::class);
        $bootstrapper->expects($this->exactly(4))->method('bootstrap');

        $container = $this->createStub(Application::class);
        $container->method('get')->willReturn($bootstrapper);

        // Act
        $kernel->bootstrap($container);
        $kernel->bootstrap($container);
    }

    // ---------------------------------------------------------------
    // requiredEnvironmentVariables()
    // ---------------------------------------------------------------

    public function testRequiredEnvironmentVariablesDefaultsToEmpty(): void
    {
        // Arrange & Act
        $kernel = new RuneKernel('/app');

        // Assert
        $this->assertSame([], $kernel->requiredEnvironmentVariables());
    }

    // ---------------------------------------------------------------
    // handle()
    // ---------------------------------------------------------------

    public function testHandleReturnsSuccessExitCode(): void
    {
        // Arrange
        $kernel = new RuneKernel('/app');

        // Act
        $exitCode = $kernel->handle(['bin/arcanum', 'query:health']);

        // Assert
        $this->assertSame(ExitCode::Success->value, $exitCode);
    }

    // ---------------------------------------------------------------
    // terminate()
    // ---------------------------------------------------------------

    public function testTerminateDoesNotThrow(): void
    {
        // Arrange
        $kernel = new RuneKernel('/app');

        // Act
        $kernel->terminate();

        // Assert — terminate is a no-op, reaching here means it didn't throw
        $this->expectNotToPerformAssertions();
    }
}
