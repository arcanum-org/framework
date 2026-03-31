<?php

declare(strict_types=1);

namespace Arcanum\Test\Ignition;

use Arcanum\Cabinet\Application;
use Arcanum\Glitch\ExceptionHandler;
use Arcanum\Glitch\ExceptionRenderer;
use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\CallableHandler;
use Arcanum\Hyper\HttpMiddleware;
use Arcanum\Hyper\MiddlewareStage;
use Arcanum\Hyper\StatusCode;
use Arcanum\Ignition\Bootstrapper;
use Arcanum\Ignition\HyperKernel;
use Arcanum\Test\Fixture\CapturingKernel;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(HyperKernel::class)]
#[UsesClass(CallableHandler::class)]
#[UsesClass(HttpMiddleware::class)]
#[UsesClass(MiddlewareStage::class)]
#[UsesClass(\Arcanum\Flow\Pipeline\Pipeline::class)]
#[UsesClass(\Arcanum\Flow\Pipeline\StandardProcessor::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(StatusCode::class)]
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
        $bootstrapper->expects($this->exactly(6))->method('bootstrap');

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
        $bootstrapper->expects($this->exactly(6))->method('bootstrap');

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

    public function testHandleThrowsWhenNotBootstrapped(): void
    {
        // Arrange — no bootstrap called, so container is uninitialized
        $kernel = new HyperKernel('/app');

        // Assert — accessing $this->container before bootstrap throws Error
        $this->expectException(\Error::class);

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

    // -----------------------------------------------------------
    // prepareRequest() — JSON body parsing
    // -----------------------------------------------------------

    private function stubJsonRequest(string $body, string $contentType = 'application/json'): ServerRequestInterface
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('__toString')->willReturn($body);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturnCallback(
            fn(string $name) => $name === 'Content-Type' ? $contentType : ''
        );
        $request->method('getBody')->willReturn($stream);
        $request->method('withParsedBody')->willReturnCallback(
            function (mixed $data) {
                $clone = $this->createStub(ServerRequestInterface::class);
                $clone->method('getParsedBody')->willReturn($data);
                $clone->method('getHeaderLine')->willReturnCallback(
                    fn(string $name) => $name === 'Content-Type' ? 'application/json' : ''
                );
                return $clone;
            }
        );

        return $request;
    }

    /**
     * Create a kernel that captures the prepared request instead of throwing.
     */
    private function capturingKernel(): CapturingKernel
    {
        return new CapturingKernel('/app');
    }

    public function testPrepareRequestParsesJsonBody(): void
    {
        // Arrange
        $kernel = $this->capturingKernel();
        $request = $this->stubJsonRequest('{"name":"test","count":42}');

        // Act
        $kernel->handle($request);

        // Assert
        $this->assertSame(['name' => 'test', 'count' => 42], $kernel->capturedRequest?->getParsedBody());
    }

    public function testPrepareRequestThrows400ForMalformedJson(): void
    {
        // Arrange — bootstrap with a container that has no renderer, so exception rethrows
        $kernel = new HyperKernel('/app');
        $bootstrapper = $this->createStub(Bootstrapper::class);
        $container = $this->createStub(Application::class);
        $container->method('has')->willReturn(false);
        $container->method('get')->willReturn($bootstrapper);
        $kernel->bootstrap($container);

        $request = $this->stubJsonRequest('{invalid json}');

        // Act & Assert
        try {
            $kernel->handle($request);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(StatusCode::BadRequest, $e->getStatusCode());
            $this->assertSame('Malformed JSON body.', $e->getMessage());
        }
    }

    public function testPrepareRequestLeavesEmptyJsonBodyAlone(): void
    {
        // Arrange
        $kernel = $this->capturingKernel();
        $request = $this->stubJsonRequest('');

        // Act
        $kernel->handle($request);

        // Assert — request passed through without withParsedBody being called
        $this->assertNull($kernel->capturedRequest?->getParsedBody());
    }

    public function testPrepareRequestIgnoresNonJsonContentType(): void
    {
        // Arrange
        $kernel = $this->capturingKernel();
        $request = $this->stubJsonRequest('not json', 'text/plain');

        // Act
        $kernel->handle($request);

        // Assert — request passed through unchanged
        $this->assertNull($kernel->capturedRequest?->getParsedBody());
    }

    public function testPrepareRequestHandlesJsonWithCharset(): void
    {
        // Arrange — Content-Type: application/json; charset=utf-8
        $kernel = $this->capturingKernel();
        $request = $this->stubJsonRequest('{"key":"value"}', 'application/json; charset=utf-8');

        // Act
        $kernel->handle($request);

        // Assert
        $this->assertSame(['key' => 'value'], $kernel->capturedRequest?->getParsedBody());
    }

    public function testPrepareRequestThrows400ForJsonScalar(): void
    {
        // Arrange — valid JSON but decodes to a scalar, not an array
        $kernel = new HyperKernel('/app');
        $bootstrapper = $this->createStub(Bootstrapper::class);
        $container = $this->createStub(Application::class);
        $container->method('has')->willReturn(false);
        $container->method('get')->willReturn($bootstrapper);
        $kernel->bootstrap($container);

        $request = $this->stubJsonRequest('"just a string"');

        // Act & Assert
        try {
            $kernel->handle($request);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(StatusCode::BadRequest, $e->getStatusCode());
        }
    }

    // -----------------------------------------------------------
    // Middleware pipeline integration
    // -----------------------------------------------------------

    /**
     * Bootstrap a CapturingKernel with a container that supports middleware resolution.
     *
     * @param array<string, MiddlewareInterface> $middlewareServices
     */
    private function kernelWithMiddleware(array $middlewareServices = []): CapturingKernel
    {
        $kernel = $this->capturingKernel();

        $bootstrapper = $this->createStub(Bootstrapper::class);

        $container = $this->createStub(Application::class);
        $container->method('has')->willReturn(false);
        $container->method('get')->willReturnCallback(
            fn(string $id) => $middlewareServices[$id] ?? $bootstrapper
        );

        $kernel->bootstrap($container);

        return $kernel;
    }

    public function testMiddlewareExecutesAroundHandleRequest(): void
    {
        // Arrange
        $order = [];

        $middleware = new class ($order) implements MiddlewareInterface {
            /** @param list<string> $order */
            public function __construct(public array &$order)
            {
            }

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                $this->order[] = 'middleware:before';
                $response = $handler->handle($request);
                $this->order[] = 'middleware:after';
                return $response;
            }
        };

        $class = get_class($middleware);
        $kernel = $this->kernelWithMiddleware([
            $class => $middleware,
        ]);
        $kernel->middleware($class);

        // Act
        $kernel->handle($this->createStub(ServerRequestInterface::class));

        // Assert — middleware ran around the handler
        $this->assertSame(['middleware:before', 'middleware:after'], $order);
        $this->assertNotNull($kernel->capturedRequest);
    }

    public function testMiddlewareReceivesPreparedRequest(): void
    {
        // Arrange
        $capturedByMiddleware = null;

        $middleware = new class ($capturedByMiddleware) implements MiddlewareInterface {
            public function __construct(public mixed &$captured)
            {
            }

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                $this->captured = $request->getParsedBody();
                return $handler->handle($request);
            }
        };

        $class = get_class($middleware);
        $kernel = $this->kernelWithMiddleware([
            $class => $middleware,
        ]);
        $kernel->middleware($class);

        $request = $this->stubJsonRequest('{"from":"json"}');

        // Act
        $kernel->handle($request);

        // Assert — middleware saw the parsed JSON body
        $this->assertSame(['from' => 'json'], $capturedByMiddleware);
    }

    public function testMiddlewareCanShortCircuit(): void
    {
        // Arrange
        $earlyResponse = $this->createStub(ResponseInterface::class);

        $middleware = new class ($earlyResponse) implements MiddlewareInterface {
            public function __construct(private ResponseInterface $response)
            {
            }

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                return $this->response;
            }
        };

        $class = get_class($middleware);
        $kernel = $this->kernelWithMiddleware([
            $class => $middleware,
        ]);
        $kernel->middleware($class);

        // Act
        $result = $kernel->handle($this->createStub(ServerRequestInterface::class));

        // Assert — middleware short-circuited, handler was never called
        $this->assertSame($earlyResponse, $result);
        $this->assertNull($kernel->capturedRequest);
    }
}
