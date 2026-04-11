<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper;

use Arcanum\Hyper\CallableHandler;
use Arcanum\Hyper\MiddlewareStage;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(MiddlewareStage::class)]
#[UsesClass(CallableHandler::class)]
final class MiddlewareStageTest extends TestCase
{
    public function testWrapsHandlerWithMiddleware(): void
    {
        // Arrange
        $innerResponse = $this->createMock(ResponseInterface::class);
        $wrappedResponse = $this->createStub(ResponseInterface::class);

        $innerResponse->expects($this->once())
            ->method('withHeader')
            ->with('X-Added', 'yes')
            ->willReturn($wrappedResponse);

        $inner = new CallableHandler(fn() => $innerResponse);

        $middleware = new class () implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                return $handler->handle($request)->withHeader('X-Added', 'yes');
            }
        };

        $stage = new MiddlewareStage($middleware);

        // Act — stage takes a handler and returns a wrapped handler
        $wrapped = $stage($inner);

        // Assert — the wrapped handler is a RequestHandlerInterface
        $this->assertInstanceOf(RequestHandlerInterface::class, $wrapped);

        // Act — invoke the wrapped handler
        $result = $wrapped->handle($this->createStub(ServerRequestInterface::class));

        // Assert
        $this->assertSame($wrappedResponse, $result);
    }

    public function testShortCircuitDoesNotCallInnerHandler(): void
    {
        // Arrange
        $earlyResponse = $this->createStub(ResponseInterface::class);
        $innerReached = false;

        $inner = new CallableHandler(function () use (&$innerReached) {
            $innerReached = true;
            return $this->createStub(ResponseInterface::class);
        });

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

        $stage = new MiddlewareStage($middleware);

        // Act
        $wrapped = $stage($inner);
        $result = $wrapped->handle($this->createStub(ServerRequestInterface::class));

        // Assert
        $this->assertSame($earlyResponse, $result);
        $this->assertFalse($innerReached);
    }

    public function testPassesRequestToMiddleware(): void
    {
        // Arrange
        $request = $this->createStub(ServerRequestInterface::class);
        $capturedRequest = null;

        $middleware = new class ($capturedRequest) implements MiddlewareInterface {
            public function __construct(public mixed &$captured)
            {
            }

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                $this->captured = $request;
                return $handler->handle($request);
            }
        };

        $inner = new CallableHandler(fn() => $this->createStub(ResponseInterface::class));
        $stage = new MiddlewareStage($middleware);

        // Act
        $wrapped = $stage($inner);
        $wrapped->handle($request);

        // Assert
        $this->assertSame($request, $capturedRequest);
    }
}
