<?php

declare(strict_types=1);

namespace Arcanum\Test\Ignition\Bootstrap;

use Arcanum\Cabinet\Container;
use Arcanum\Gather\Environment as GatherEnvironment;
use Arcanum\Ignition\Bootstrap\Environment;
use Arcanum\Ignition\Kernel;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Environment::class)]
#[UsesClass(Container::class)]
#[UsesClass(\Arcanum\Cabinet\SimpleProvider::class)]
#[UsesClass(\Arcanum\Cabinet\PrototypeProvider::class)]
#[UsesClass(\Arcanum\Codex\Resolver::class)]
#[UsesClass(\Arcanum\Codex\Event\ClassRequested::class)]
#[UsesClass(GatherEnvironment::class)]
#[UsesClass(\Arcanum\Gather\Registry::class)]
final class EnvironmentTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/arcanum_env_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        $envFile = $this->tempDir . '/.env';
        if (file_exists($envFile)) {
            unlink($envFile);
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }

        // Clean up any env vars we set
        unset($_ENV['ARCANUM_TEST_VAR']);
        unset($_ENV['ARCANUM_REQUIRED_VAR']);
        putenv('ARCANUM_TEST_VAR');
        putenv('ARCANUM_REQUIRED_VAR');
    }

    /**
     * @param string[] $requiredVars
     */
    private function buildContainer(string $rootDir, array $requiredVars = []): Container
    {
        $container = new Container();
        $container->instance(\Arcanum\Cabinet\Application::class, $container);

        $kernel = $this->createStub(Kernel::class);
        $kernel->method('rootDirectory')->willReturn($rootDir);
        $kernel->method('requiredEnvironmentVariables')->willReturn($requiredVars);
        $container->instance(Kernel::class, $kernel);

        return $container;
    }

    public function testRegistersEnvironmentFactory(): void
    {
        // Arrange
        $container = $this->buildContainer($this->tempDir);
        $bootstrapper = new Environment();

        // Act
        $bootstrapper->bootstrap($container);

        // Assert
        $this->assertTrue($container->has(GatherEnvironment::class));
    }

    public function testEnvironmentFactoryReturnsGatherEnvironment(): void
    {
        // Arrange
        $container = $this->buildContainer($this->tempDir);
        $bootstrapper = new Environment();

        // Act
        $bootstrapper->bootstrap($container);

        /** @var GatherEnvironment $env */
        $env = $container->get(GatherEnvironment::class);

        // Assert
        $this->assertInstanceOf(GatherEnvironment::class, $env);
    }

    public function testLoadsEnvFileWhenPresent(): void
    {
        // Arrange
        file_put_contents($this->tempDir . '/.env', "ARCANUM_TEST_VAR=hello_from_env\n");
        $container = $this->buildContainer($this->tempDir);
        $bootstrapper = new Environment();

        // Act
        $bootstrapper->bootstrap($container);

        // Assert
        $this->assertSame('hello_from_env', $_ENV['ARCANUM_TEST_VAR'] ?? null);
    }

    public function testHandlesMissingEnvFileGracefully(): void
    {
        // Arrange — no .env file in the temp dir
        $container = $this->buildContainer($this->tempDir);
        $bootstrapper = new Environment();

        // Act — should not throw
        $bootstrapper->bootstrap($container);

        // Assert
        $this->assertTrue($container->has(GatherEnvironment::class));
    }

    public function testValidatesRequiredEnvironmentVariables(): void
    {
        // Arrange — set a required var via .env
        file_put_contents($this->tempDir . '/.env', "ARCANUM_REQUIRED_VAR=present\n");
        $container = $this->buildContainer($this->tempDir, ['ARCANUM_REQUIRED_VAR']);
        $bootstrapper = new Environment();

        // Act — should not throw because the var is present
        $bootstrapper->bootstrap($container);

        // Assert
        $this->assertSame('present', $_ENV['ARCANUM_REQUIRED_VAR'] ?? null);
    }

    public function testThrowsWhenRequiredVariableIsMissing(): void
    {
        // Arrange — no .env file, so the required var won't exist
        $container = $this->buildContainer($this->tempDir, ['ARCANUM_MISSING_VAR_' . uniqid()]);
        $bootstrapper = new Environment();

        // Act & Assert
        $this->expectException(\Dotenv\Exception\ValidationException::class);
        $bootstrapper->bootstrap($container);
    }

    public function testNoValidationWhenRequiredVarsEmpty(): void
    {
        // Arrange
        $container = $this->buildContainer($this->tempDir, []);
        $bootstrapper = new Environment();

        // Act — should not throw
        $bootstrapper->bootstrap($container);

        // Assert
        $this->assertTrue($container->has(GatherEnvironment::class));
    }
}
