<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper;

use Arcanum\Flow\Pipeline\Pipeline;
use Arcanum\Flow\Pipeline\StandardProcessor;
use Arcanum\Hyper\CallableHandler;
use Arcanum\Hyper\HttpMiddleware;
use Arcanum\Hyper\MiddlewareStage;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(HttpMiddleware::class)]
#[UsesClass(CallableHandler::class)]
#[UsesClass(MiddlewareStage::class)]
#[UsesClass(Pipeline::class)]
#[UsesClass(StandardProcessor::class)]
final class HttpMiddlewareTest extends TestCase
{
    /** @param array<string, MiddlewareInterface> $services */
    private function stubContainer(array $services = []): ContainerInterface
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            fn(string $id) => $services[$id] ?? throw new \RuntimeException("Service not found: $id")
        );
        return $container;
    }

    private function stubResponse(): ResponseInterface
    {
        return $this->createStub(ResponseInterface::class);
    }

    private function coreHandler(ResponseInterface $response): RequestHandlerInterface
    {
        return new CallableHandler(fn() => $response);
    }

    // -----------------------------------------------------------
    // No middleware — direct delegation
    // -----------------------------------------------------------

    public function testNoMiddlewareDelegatesToCoreHandler(): void
    {
        // Arrange
        $response = $this->stubResponse();
        $stack = new HttpMiddleware(
            $this->coreHandler($response),
            $this->stubContainer(),
        );

        // Act
        $result = $stack->handle($this->createStub(ServerRequestInterface::class));

        // Assert
        $this->assertSame($response, $result);
    }

    // -----------------------------------------------------------
    // Single middleware
    // -----------------------------------------------------------

    public function testSingleMiddlewareModifiesResponse(): void
    {
        // Arrange
        $coreResponse = $this->createMock(ResponseInterface::class);
        $modifiedResponse = $this->stubResponse();

        $coreResponse->expects($this->once())
            ->method('withHeader')
            ->with('X-Test', 'added')
            ->willReturn($modifiedResponse);

        $middleware = new class () implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                return $handler->handle($request)->withHeader('X-Test', 'added');
            }
        };

        $stack = new HttpMiddleware(
            $this->coreHandler($coreResponse),
            $this->stubContainer(),
        );
        $stack->add($middleware);

        // Act
        $result = $stack->handle($this->createStub(ServerRequestInterface::class));

        // Assert
        $this->assertSame($modifiedResponse, $result);
    }

    // -----------------------------------------------------------
    // Multiple middleware — onion ordering
    // -----------------------------------------------------------

    public function testMultipleMiddlewareExecutesInOnionOrder(): void
    {
        // Arrange — track execution order
        $order = [];

        $middlewareA = new class ($order) implements MiddlewareInterface {
            /** @param list<string> $order */
            public function __construct(public array &$order)
            {
            }

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                $this->order[] = 'A:before';
                $response = $handler->handle($request);
                $this->order[] = 'A:after';
                return $response;
            }
        };

        $middlewareB = new class ($order) implements MiddlewareInterface {
            /** @param list<string> $order */
            public function __construct(public array &$order)
            {
            }

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                $this->order[] = 'B:before';
                $response = $handler->handle($request);
                $this->order[] = 'B:after';
                return $response;
            }
        };

        $stack = new HttpMiddleware(
            new CallableHandler(function () use (&$order) {
                $order[] = 'core';
                return $this->stubResponse();
            }),
            $this->stubContainer(),
        );
        $stack->add($middlewareA);
        $stack->add($middlewareB);

        // Act
        $stack->handle($this->createStub(ServerRequestInterface::class));

        // Assert — A is outermost, B is inner
        $this->assertSame(['A:before', 'B:before', 'core', 'B:after', 'A:after'], $order);
    }

    // -----------------------------------------------------------
    // Short-circuit
    // -----------------------------------------------------------

    public function testMiddlewareCanShortCircuit(): void
    {
        // Arrange
        $shortCircuitResponse = $this->stubResponse();
        $coreReached = false;

        $middleware = new class ($shortCircuitResponse) implements MiddlewareInterface {
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

        $stack = new HttpMiddleware(
            new CallableHandler(function () use (&$coreReached) {
                $coreReached = true;
                return $this->stubResponse();
            }),
            $this->stubContainer(),
        );
        $stack->add($middleware);

        // Act
        $result = $stack->handle($this->createStub(ServerRequestInterface::class));

        // Assert
        $this->assertSame($shortCircuitResponse, $result);
        $this->assertFalse($coreReached);
    }

    // -----------------------------------------------------------
    // Lazy resolution from container
    // -----------------------------------------------------------

    public function testClassStringMiddlewareResolvedFromContainer(): void
    {
        // Arrange
        $resolved = false;

        $middleware = new class ($resolved) implements MiddlewareInterface {
            public function __construct(public bool &$resolved)
            {
            }

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                $this->resolved = true;
                return $handler->handle($request);
            }
        };

        $class = get_class($middleware);
        $container = $this->stubContainer([
            $class => $middleware,
        ]);

        $stack = new HttpMiddleware(
            $this->coreHandler($this->stubResponse()),
            $container,
        );
        $stack->add($class);

        // Act
        $stack->handle($this->createStub(ServerRequestInterface::class));

        // Assert
        $this->assertTrue($resolved);
    }

    // -----------------------------------------------------------
    // Non-MiddlewareInterface from container
    // -----------------------------------------------------------

    public function testThrowsWhenContainerReturnsNonMiddleware(): void
    {
        // Arrange — container is misconfigured: returns a stdClass for a
        // class-string that claims to be MiddlewareInterface.
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn(new \stdClass());

        $stack = new HttpMiddleware(
            $this->coreHandler($this->stubResponse()),
            $container,
        );
        $stack->add(MiddlewareInterface::class);

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not implement');

        // Act
        $stack->handle($this->createStub(ServerRequestInterface::class));
    }

    // -----------------------------------------------------------
    // Request modification propagates through chain
    // -----------------------------------------------------------

    public function testRequestModificationPropagatesThroughChain(): void
    {
        // Arrange
        $capturedAttribute = null;

        $adderMiddleware = new class () implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                return $handler->handle($request->withAttribute('added', 'by-middleware'));
            }
        };

        $readerMiddleware = new class ($capturedAttribute) implements MiddlewareInterface {
            public function __construct(public mixed &$captured)
            {
            }

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                $this->captured = $request->getAttribute('added');
                return $handler->handle($request);
            }
        };

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('withAttribute')->willReturnCallback(
            function (string $name, mixed $value) {
                $clone = $this->createStub(ServerRequestInterface::class);
                $clone->method('getAttribute')->willReturnCallback(
                    fn(string $n) => $n === $name ? $value : null
                );
                $clone->method('withAttribute')->willReturnCallback(
                    fn() => $clone
                );
                return $clone;
            }
        );

        $stack = new HttpMiddleware(
            $this->coreHandler($this->stubResponse()),
            $this->stubContainer(),
        );
        $stack->add($adderMiddleware);
        $stack->add($readerMiddleware);

        // Act
        $stack->handle($request);

        // Assert
        $this->assertSame('by-middleware', $capturedAttribute);
    }
}
