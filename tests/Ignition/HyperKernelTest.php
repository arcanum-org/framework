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
use Psr\EventDispatcher\EventDispatcherInterface;
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
        $bootstrapper->expects($this->exactly(14))->method('bootstrap');

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
        $bootstrapper->expects($this->exactly(14))->method('bootstrap');

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
            [EventDispatcherInterface::class, false],
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
            [EventDispatcherInterface::class, false],
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

    public function testPrepareRequestParsesJsonBody(): void
    {
        // Arrange
        $kernel = new CapturingKernel('/app');

        $bootstrapper = $this->createStub(Bootstrapper::class);
        $container = $this->createStub(Application::class);
        $container->method('has')->willReturn(false);
        $container->method('get')->willReturn($bootstrapper);
        $kernel->bootstrap($container);

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('__toString')->willReturn('{"name":"test","count":42}');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturnCallback(
            fn(string $name) => $name === 'Content-Type' ? 'application/json' : ''
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

        // Act
        $kernel->handle($request);

        // Assert
        $this->assertSame(
            ['name' => 'test', 'count' => 42],
            $kernel->capturedRequest?->getParsedBody(),
        );
    }

    public function testPrepareRequestThrows400ForMalformedJson(): void
    {
        // Arrange
        $kernel = new HyperKernel('/app');
        $bootstrapper = $this->createStub(Bootstrapper::class);
        $container = $this->createStub(Application::class);
        $container->method('has')->willReturn(false);
        $container->method('get')->willReturn($bootstrapper);
        $kernel->bootstrap($container);

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('__toString')->willReturn('{invalid json}');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturnCallback(
            fn(string $name) => $name === 'Content-Type' ? 'application/json' : ''
        );
        $request->method('getBody')->willReturn($stream);

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
        $kernel = new CapturingKernel('/app');

        $bootstrapper = $this->createStub(Bootstrapper::class);
        $container = $this->createStub(Application::class);
        $container->method('has')->willReturn(false);
        $container->method('get')->willReturn($bootstrapper);
        $kernel->bootstrap($container);

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('__toString')->willReturn('');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturnCallback(
            fn(string $name) => $name === 'Content-Type' ? 'application/json' : ''
        );
        $request->method('getBody')->willReturn($stream);

        // Act
        $kernel->handle($request);

        // Assert — request passed through without withParsedBody being called
        $this->assertNull($kernel->capturedRequest?->getParsedBody());
    }

    public function testPrepareRequestIgnoresNonJsonContentType(): void
    {
        // Arrange
        $kernel = new CapturingKernel('/app');

        $bootstrapper = $this->createStub(Bootstrapper::class);
        $container = $this->createStub(Application::class);
        $container->method('has')->willReturn(false);
        $container->method('get')->willReturn($bootstrapper);
        $kernel->bootstrap($container);

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('__toString')->willReturn('not json');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturnCallback(
            fn(string $name) => $name === 'Content-Type' ? 'text/plain' : ''
        );
        $request->method('getBody')->willReturn($stream);

        // Act
        $kernel->handle($request);

        // Assert — request passed through unchanged
        $this->assertNull($kernel->capturedRequest?->getParsedBody());
    }

    public function testPrepareRequestHandlesJsonWithCharset(): void
    {
        // Arrange
        $kernel = new CapturingKernel('/app');

        $bootstrapper = $this->createStub(Bootstrapper::class);
        $container = $this->createStub(Application::class);
        $container->method('has')->willReturn(false);
        $container->method('get')->willReturn($bootstrapper);
        $kernel->bootstrap($container);

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('__toString')->willReturn('{"key":"value"}');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturnCallback(
            fn(string $name) => $name === 'Content-Type'
                ? 'application/json; charset=utf-8'
                : ''
        );
        $request->method('getBody')->willReturn($stream);
        $request->method('withParsedBody')->willReturnCallback(
            function (mixed $data) {
                $clone = $this->createStub(ServerRequestInterface::class);
                $clone->method('getParsedBody')->willReturn($data);
                return $clone;
            }
        );

        // Act
        $kernel->handle($request);

        // Assert
        $this->assertSame(
            ['key' => 'value'],
            $kernel->capturedRequest?->getParsedBody(),
        );
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

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('__toString')->willReturn('"just a string"');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturnCallback(
            fn(string $name) => $name === 'Content-Type' ? 'application/json' : ''
        );
        $request->method('getBody')->willReturn($stream);

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
        $kernel = new CapturingKernel('/app');

        $bootstrapper = $this->createStub(Bootstrapper::class);
        $container = $this->createStub(Application::class);
        $container->method('has')->willReturn(false);
        $container->method('get')->willReturnCallback(
            fn(string $id) => $id === $class ? $middleware : $bootstrapper,
        );
        $kernel->bootstrap($container);
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
        $kernel = new CapturingKernel('/app');

        $bootstrapper = $this->createStub(Bootstrapper::class);
        $container = $this->createStub(Application::class);
        $container->method('has')->willReturn(false);
        $container->method('get')->willReturnCallback(
            fn(string $id) => $id === $class ? $middleware : $bootstrapper,
        );
        $kernel->bootstrap($container);
        $kernel->middleware($class);

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('__toString')->willReturn('{"from":"json"}');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturnCallback(
            fn(string $name) => $name === 'Content-Type' ? 'application/json' : ''
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
        $kernel = new CapturingKernel('/app');

        $bootstrapper = $this->createStub(Bootstrapper::class);
        $container = $this->createStub(Application::class);
        $container->method('has')->willReturn(false);
        $container->method('get')->willReturnCallback(
            fn(string $id) => $id === $class ? $middleware : $bootstrapper,
        );
        $kernel->bootstrap($container);
        $kernel->middleware($class);

        // Act
        $result = $kernel->handle($this->createStub(ServerRequestInterface::class));

        // Assert — middleware short-circuited, handler was never called
        $this->assertSame($earlyResponse, $result);
        $this->assertNull($kernel->capturedRequest);
    }

    public function testErrorResponseFlowsThroughMiddleware(): void
    {
        // Arrange — default handleRequest throws HttpException(NotFound),
        // which should be caught and rendered, then the response should
        // flow back through middleware.
        $middlewareRan = false;

        $middleware = new class ($middlewareRan) implements MiddlewareInterface {
            public function __construct(public bool &$ran)
            {
            }

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                $response = $handler->handle($request);
                $this->ran = true;
                return $response;
            }
        };

        $errorResponse = $this->createStub(ResponseInterface::class);

        $renderer = $this->createStub(ExceptionRenderer::class);
        $renderer->method('render')->willReturn($errorResponse);

        $bootstrapper = $this->createStub(Bootstrapper::class);

        $container = $this->createStub(Application::class);
        $container->method('has')->willReturnMap([
            [ExceptionHandler::class, false],
            [ExceptionRenderer::class, true],
            [EventDispatcherInterface::class, false],
        ]);

        $class = get_class($middleware);
        $container->method('get')->willReturnCallback(
            fn(string $id) => match ($id) {
                ExceptionRenderer::class => $renderer,
                $class => $middleware,
                default => $bootstrapper,
            }
        );

        $kernel = new HyperKernel('/app');
        $kernel->bootstrap($container);
        $kernel->middleware($class);

        // Act
        $kernel->handle($this->createStub(ServerRequestInterface::class));

        // Assert — middleware ran even though the handler threw an exception
        $this->assertTrue($middlewareRan);
    }

    public function testMalformedJsonErrorFlowsThroughMiddleware(): void
    {
        // Arrange — malformed JSON should produce a 400, and that 400
        // should flow through middleware.
        $middlewareRan = false;

        $middleware = new class ($middlewareRan) implements MiddlewareInterface {
            public function __construct(public bool &$ran)
            {
            }

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                $response = $handler->handle($request);
                $this->ran = true;
                return $response;
            }
        };

        $errorResponse = $this->createStub(ResponseInterface::class);

        $renderer = $this->createStub(ExceptionRenderer::class);
        $renderer->method('render')->willReturn($errorResponse);

        $bootstrapper = $this->createStub(Bootstrapper::class);

        $container = $this->createStub(Application::class);
        $container->method('has')->willReturnMap([
            [ExceptionHandler::class, false],
            [ExceptionRenderer::class, true],
            [EventDispatcherInterface::class, false],
        ]);

        $class = get_class($middleware);
        $container->method('get')->willReturnCallback(
            fn(string $id) => match ($id) {
                ExceptionRenderer::class => $renderer,
                $class => $middleware,
                default => $bootstrapper,
            }
        );

        $kernel = new HyperKernel('/app');
        $kernel->bootstrap($container);
        $kernel->middleware($class);

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('__toString')->willReturn('{invalid json}');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturnCallback(
            fn(string $name) => $name === 'Content-Type' ? 'application/json' : ''
        );
        $request->method('getBody')->willReturn($stream);

        // Act
        $kernel->handle($request);

        // Assert — middleware ran even for a prepareRequest error
        $this->assertTrue($middlewareRan);
    }

    public function testExceptionRendererHonoursDefaultFormatWhenNoExtension(): void
    {
        // Arrange — request a URL with no extension. The kernel should
        // consult formats.default (html in this case) and render the
        // exception through HtmlExceptionResponseRenderer, not the
        // generic JSON ExceptionRenderer.
        $thrown = new \RuntimeException('Boom from handler');

        $htmlResponse = $this->createStub(ResponseInterface::class);
        $jsonResponse = $this->createStub(ResponseInterface::class);

        $htmlRenderer = $this->createMock(\Arcanum\Hyper\HtmlExceptionResponseRenderer::class);
        $htmlRenderer->expects($this->once())
            ->method('render')
            ->with($thrown)
            ->willReturn($htmlResponse);

        $jsonRenderer = $this->createMock(ExceptionRenderer::class);
        $jsonRenderer->expects($this->never())->method('render');

        $config = $this->createStub(\Arcanum\Gather\Configuration::class);
        $config->method('get')->willReturnCallback(
            fn(string $key) => $key === 'formats.default' ? 'html' : null,
        );

        $bootstrapper = $this->createStub(Bootstrapper::class);

        $container = $this->createStub(Application::class);
        $container->method('has')->willReturnMap([
            [ExceptionHandler::class, false],
            [ExceptionRenderer::class, true],
            [\Arcanum\Hyper\HtmlExceptionResponseRenderer::class, true],
            [\Arcanum\Gather\Configuration::class, true],
            [EventDispatcherInterface::class, false],
        ]);
        $container->method('get')->willReturnCallback(
            fn(string $id) => match ($id) {
                ExceptionRenderer::class => $jsonRenderer,
                \Arcanum\Hyper\HtmlExceptionResponseRenderer::class => $htmlRenderer,
                \Arcanum\Gather\Configuration::class => $config,
                default => $bootstrapper,
            }
        );

        $kernel = new class ('/app', $thrown) extends HyperKernel {
            public function __construct(string $rootDirectory, private \Throwable $error)
            {
                parent::__construct($rootDirectory);
            }

            protected function handleRequest(ServerRequestInterface $request): ResponseInterface
            {
                throw $this->error;
            }
        };
        $kernel->bootstrap($container);

        $uri = $this->createStub(\Psr\Http\Message\UriInterface::class);
        $uri->method('getPath')->willReturn('/health');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);

        // Act
        $response = $kernel->handle($request);

        // Assert — HTML renderer was used because formats.default = 'html'
        $this->assertSame($htmlResponse, $response);
    }

    public function testExceptionThrownInMiddlewareIsCaughtAndRendered(): void
    {
        // Arrange — middleware throws before reaching the core handler.
        // The kernel must catch the exception and render a response,
        // not let it escape and leave the client with no body.
        $thrown = new \RuntimeException('Boom from middleware');

        $middleware = new class ($thrown) implements MiddlewareInterface {
            public function __construct(private \Throwable $error)
            {
            }

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                throw $this->error;
            }
        };

        $errorResponse = $this->createStub(ResponseInterface::class);

        $renderer = $this->createMock(ExceptionRenderer::class);
        $renderer->expects($this->once())
            ->method('render')
            ->with($thrown)
            ->willReturn($errorResponse);

        $exceptionHandler = $this->createMock(ExceptionHandler::class);
        $exceptionHandler->expects($this->once())
            ->method('handleException')
            ->with($thrown);

        $bootstrapper = $this->createStub(Bootstrapper::class);

        $container = $this->createStub(Application::class);
        $container->method('has')->willReturnMap([
            [ExceptionHandler::class, true],
            [ExceptionRenderer::class, true],
            [EventDispatcherInterface::class, false],
        ]);

        $class = get_class($middleware);
        $container->method('get')->willReturnCallback(
            fn(string $id) => match ($id) {
                ExceptionHandler::class => $exceptionHandler,
                ExceptionRenderer::class => $renderer,
                $class => $middleware,
                default => $bootstrapper,
            }
        );

        $kernel = new HyperKernel('/app');
        $kernel->bootstrap($container);
        $kernel->middleware($class);

        // Act
        $response = $kernel->handle($this->createStub(ServerRequestInterface::class));

        // Assert — the rendered error response was returned, not bubbled
        $this->assertSame($errorResponse, $response);
    }
}
