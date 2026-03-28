<?php

declare(strict_types=1);

namespace Arcanum\Test\Ignition;

use Arcanum\Cabinet\Application;
use Arcanum\Glitch\ExceptionHandler;
use Arcanum\Glitch\ExceptionRenderer;
use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\StatusCode;
use Arcanum\Ignition\Bootstrapper;
use Arcanum\Ignition\HyperKernel;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

#[CoversClass(HyperKernel::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(\Arcanum\Hyper\StatusCode::class)]
#[UsesClass(\Arcanum\Hyper\Phrase::class)]
final class HyperKernelTest extends TestCase
{
    // -----------------------------------------------------------
    // Constructor & directory accessors
    // -----------------------------------------------------------

    public function testRootDirectoryTrimsTrailingSlash(): void
    {
        // Arrange & Act
        $kernel = new HyperKernel('/app/');

        // Assert
        $this->assertSame('/app', $kernel->rootDirectory());
    }

    public function testConfigDirectoryDefaultsToConfigSubdirectory(): void
    {
        // Arrange & Act
        $kernel = new HyperKernel('/app');

        // Assert
        $this->assertSame('/app' . DIRECTORY_SEPARATOR . 'config', $kernel->configDirectory());
    }

    public function testFilesDirectoryDefaultsToFilesSubdirectory(): void
    {
        // Arrange & Act
        $kernel = new HyperKernel('/app');

        // Assert
        $this->assertSame('/app' . DIRECTORY_SEPARATOR . 'files', $kernel->filesDirectory());
    }

    public function testCustomConfigDirectory(): void
    {
        // Arrange & Act
        $kernel = new HyperKernel('/app', '/custom/config');

        // Assert
        $this->assertSame('/custom/config', $kernel->configDirectory());
    }

    public function testCustomFilesDirectory(): void
    {
        // Arrange & Act
        $kernel = new HyperKernel('/app', filesDirectory: '/custom/files');

        // Assert
        $this->assertSame('/custom/files', $kernel->filesDirectory());
    }

    // -----------------------------------------------------------
    // bootstrap()
    // -----------------------------------------------------------

    public function testBootstrapRunsBootstrappers(): void
    {
        // Arrange
        $kernel = new HyperKernel('/app');

        $bootstrapper = $this->createMock(Bootstrapper::class);
        $bootstrapper->expects($this->exactly(5))->method('bootstrap');

        $container = $this->createStub(Application::class);
        $container->method('get')->willReturn($bootstrapper);

        // Act
        $kernel->bootstrap($container);
    }

    public function testBootstrapOnlyRunsOnce(): void
    {
        // Arrange
        $kernel = new HyperKernel('/app');

        $bootstrapper = $this->createMock(Bootstrapper::class);
        $bootstrapper->expects($this->exactly(5))->method('bootstrap');

        $container = $this->createStub(Application::class);
        $container->method('get')->willReturn($bootstrapper);

        // Act
        $kernel->bootstrap($container);
        $kernel->bootstrap($container);
    }

    // -----------------------------------------------------------
    // requiredEnvironmentVariables()
    // -----------------------------------------------------------

    public function testRequiredEnvironmentVariablesDefaultsToEmpty(): void
    {
        // Arrange & Act
        $kernel = new HyperKernel('/app');

        // Assert
        $this->assertSame([], $kernel->requiredEnvironmentVariables());
    }

    // -----------------------------------------------------------
    // handle() — exception rendering
    // -----------------------------------------------------------

    public function testHandleRendersExceptionViaExceptionRenderer(): void
    {
        // Arrange — default handleRequest throws HttpException(NotFound)
        $kernel = new HyperKernel('/app');

        $response = $this->createStub(ResponseInterface::class);

        $renderer = $this->createMock(ExceptionRenderer::class);
        $renderer->expects($this->once())
            ->method('render')
            ->with($this->isInstanceOf(HttpException::class))
            ->willReturn($response);

        $bootstrapper = $this->createStub(Bootstrapper::class);

        $container = $this->createStub(Application::class);
        $container->method('has')->willReturnMap([
            [ExceptionHandler::class, false],
            [ExceptionRenderer::class, true],
        ]);
        $container->method('get')->willReturnCallback(
            fn(string $id) => $id === ExceptionRenderer::class ? $renderer : $bootstrapper
        );

        $kernel->bootstrap($container);

        // Act
        $result = $kernel->handle($this->createStub(ServerRequestInterface::class));

        // Assert
        $this->assertSame($response, $result);
    }

    public function testHandleReportsExceptionBeforeRendering(): void
    {
        // Arrange
        $kernel = new HyperKernel('/app');

        $handler = $this->createMock(ExceptionHandler::class);
        $handler->expects($this->once())
            ->method('handleException')
            ->with($this->isInstanceOf(HttpException::class));

        $renderer = $this->createStub(ExceptionRenderer::class);
        $renderer->method('render')->willReturn($this->createStub(ResponseInterface::class));

        $bootstrapper = $this->createStub(Bootstrapper::class);

        $container = $this->createStub(Application::class);
        $container->method('has')->willReturnMap([
            [ExceptionHandler::class, true],
            [ExceptionRenderer::class, true],
        ]);
        $container->method('get')->willReturnCallback(
            fn(string $id) => match ($id) {
                ExceptionHandler::class => $handler,
                ExceptionRenderer::class => $renderer,
                default => $bootstrapper,
            }
        );

        $kernel->bootstrap($container);

        // Act
        $kernel->handle($this->createStub(ServerRequestInterface::class));
    }

    public function testHandleRethrowsWhenNoRendererAvailable(): void
    {
        // Arrange
        $kernel = new HyperKernel('/app');

        $bootstrapper = $this->createStub(Bootstrapper::class);

        $container = $this->createStub(Application::class);
        $container->method('has')->willReturn(false);
        $container->method('get')->willReturn($bootstrapper);

        $kernel->bootstrap($container);

        // Assert
        $this->expectException(HttpException::class);

        // Act
        $kernel->handle($this->createStub(ServerRequestInterface::class));
    }

    public function testHandleWorksWithoutBootstrap(): void
    {
        // Arrange — no bootstrap called, so container is null
        $kernel = new HyperKernel('/app');

        // Assert — rethrows because no container means no renderer
        $this->expectException(HttpException::class);

        // Act
        $kernel->handle($this->createStub(ServerRequestInterface::class));
    }

    // -----------------------------------------------------------
    // terminate()
    // -----------------------------------------------------------

    public function testTerminateDoesNotThrow(): void
    {
        // Arrange
        $kernel = new HyperKernel('/app');

        // Act
        $kernel->terminate();

        // Assert
        $this->addToAssertionCount(1);
    }
}
