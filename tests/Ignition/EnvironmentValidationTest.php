<?php

declare(strict_types=1);

namespace Arcanum\Test\Ignition;

use Arcanum\Ignition\Bootstrap\Environment;
use Arcanum\Ignition\HyperKernel;
use Arcanum\Ignition\Kernel;
use Arcanum\Cabinet\Application;
use Dotenv\Exception\ValidationException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Environment::class)]
#[UsesClass(HyperKernel::class)]
final class EnvironmentValidationTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/arcanum_env_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        mkdir($this->tempDir . '/config', 0755, true);
        mkdir($this->tempDir . '/files', 0755, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->tempDir . '/.env');
        @rmdir($this->tempDir . '/config');
        @rmdir($this->tempDir . '/files');
        @rmdir($this->tempDir);
    }

    public function testBootstrapPassesWhenRequiredVarsAreSet(): void
    {
        // Arrange
        file_put_contents($this->tempDir . '/.env', "APP_NAME=Test\nAPP_KEY=secret\n");

        $kernel = new class ($this->tempDir) extends HyperKernel {
            protected array $requiredEnvironmentVariables = ['APP_NAME', 'APP_KEY'];
        };

        $container = $this->createMock(Application::class);
        $container->expects($this->once())
            ->method('get')
            ->with(Kernel::class)
            ->willReturn($kernel);

        $bootstrapper = new Environment();

        // Act — should not throw
        $bootstrapper->bootstrap($container);

        // Assert
        $this->assertSame('Test', $_ENV['APP_NAME']);
    }

    public function testBootstrapThrowsWhenRequiredVarIsMissing(): void
    {
        // Arrange
        file_put_contents($this->tempDir . '/.env', "APP_NAME=Test\n");

        $kernel = new class ($this->tempDir) extends HyperKernel {
            protected array $requiredEnvironmentVariables = ['APP_NAME', 'MISSING_VAR'];
        };

        $container = $this->createMock(Application::class);
        $container->expects($this->once())
            ->method('get')
            ->with(Kernel::class)
            ->willReturn($kernel);

        $bootstrapper = new Environment();

        // Assert
        $this->expectException(ValidationException::class);

        // Act
        $bootstrapper->bootstrap($container);
    }

    public function testBootstrapSkipsValidationWhenNoRequiredVars(): void
    {
        // Arrange
        file_put_contents($this->tempDir . '/.env', "APP_NAME=Test\n");

        $kernel = new HyperKernel($this->tempDir);

        $container = $this->createMock(Application::class);
        $container->expects($this->once())
            ->method('get')
            ->with(Kernel::class)
            ->willReturn($kernel);

        $bootstrapper = new Environment();

        // Act — should not throw
        $bootstrapper->bootstrap($container);

        // Assert
        $this->assertSame([], $kernel->requiredEnvironmentVariables());
    }
}
