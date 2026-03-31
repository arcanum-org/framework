<?php

declare(strict_types=1);

namespace Arcanum\Test\Ignition;

use Arcanum\Atlas\MiddlewareRegistry;
use Arcanum\Atlas\Route;
use Arcanum\Atlas\RouteMiddleware;
use Arcanum\Flow\Conveyor\Bus;
use Arcanum\Flow\Continuum\Progression;
use Arcanum\Hyper\HttpMiddleware;
use Arcanum\Ignition\RouteDispatcher;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(RouteDispatcher::class)]
#[UsesClass(MiddlewareRegistry::class)]
#[UsesClass(RouteMiddleware::class)]
#[UsesClass(Route::class)]
#[UsesClass(HttpMiddleware::class)]
#[UsesClass(\Arcanum\Hyper\MiddlewareStage::class)]
#[UsesClass(\Arcanum\Hyper\CallableHandler::class)]
#[UsesClass(\Arcanum\Flow\Pipeline\Pipeline::class)]
#[UsesClass(\Arcanum\Flow\Pipeline\StandardProcessor::class)]
#[UsesClass(\Arcanum\Flow\Continuum\Continuum::class)]
#[UsesClass(\Arcanum\Flow\Continuum\StandardAdvancer::class)]
final class RouteDispatcherTest extends TestCase
{
    public function testDispatchWithNoMiddlewareDelegatesToBus(): void
    {
        // Arrange
        $dto = new \stdClass();
        $result = new \stdClass();
        $route = new Route(dtoClass: 'App\\Query\\Status', handlerPrefix: '');

        $bus = $this->createMock(Bus::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($dto, prefix: '')
            ->willReturn($result);

        $registry = new MiddlewareRegistry();
        $container = $this->createStub(ContainerInterface::class);

        $dispatcher = new RouteDispatcher($container, $registry, $bus);

        // Act
        $actual = $dispatcher->dispatch($dto, $route);

        // Assert
        $this->assertSame($result, $actual);
    }

    public function testDispatchWithBeforeMiddlewareWrapsAroundBus(): void
    {
        // Arrange — before middleware that mutates the DTO
        $dto = new class () {
            public string $tag = '';
        };
        $result = new \stdClass();

        $route = new Route(dtoClass: 'App\\Command\\Submit', handlerPrefix: 'Post');

        $beforeMiddleware = new class () implements Progression {
            public function __invoke(object $payload, callable $next): void
            {
                $payload->tag = 'before'; // @phpstan-ignore property.notFound
                $next();
            }
        };

        $bus = $this->createMock(Bus::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (object $obj) use ($result) {
                // Verify the before middleware ran before the bus
                $this->assertSame('before', $obj->tag); // @phpstan-ignore property.notFound
                return $result;
            });

        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')
            ->willReturn($beforeMiddleware);

        $registry = new MiddlewareRegistry();
        $registry->register('App\\Command\\Submit', new RouteMiddleware(
            before: ['App\\Middleware\\TagBefore'],
        ));

        $dispatcher = new RouteDispatcher($container, $registry, $bus);

        // Act
        $actual = $dispatcher->dispatch($dto, $route);

        // Assert
        $this->assertSame($result, $actual);
    }

    public function testWrapHttpReturnsHandlerUnchangedWithNoMiddleware(): void
    {
        // Arrange
        $route = new Route(dtoClass: 'App\\Query\\Status');
        $handler = $this->createStub(RequestHandlerInterface::class);

        $registry = new MiddlewareRegistry();
        $container = $this->createStub(ContainerInterface::class);
        $bus = $this->createStub(Bus::class);

        $dispatcher = new RouteDispatcher($container, $registry, $bus);

        // Act
        $result = $dispatcher->wrapHttp($route, $handler);

        // Assert
        $this->assertSame($handler, $result);
    }

    public function testWrapHttpBuildsMiddlewareStack(): void
    {
        // Arrange
        $response = $this->createStub(ResponseInterface::class);

        $coreHandler = $this->createStub(RequestHandlerInterface::class);
        $coreHandler->method('handle')->willReturn($response);

        $request = $this->createStub(ServerRequestInterface::class);

        $httpMiddleware = $this->createStub(MiddlewareInterface::class);
        $httpMiddleware->method('process')
            ->willReturnCallback(fn(ServerRequestInterface $r, RequestHandlerInterface $h) => $h->handle($r));

        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn($httpMiddleware);

        $registry = new MiddlewareRegistry();
        $registry->register('App\\Query\\Status', new RouteMiddleware(
            http: ['App\\Http\\Middleware\\Auth'],
        ));

        $bus = $this->createStub(Bus::class);
        $route = new Route(dtoClass: 'App\\Query\\Status');

        $dispatcher = new RouteDispatcher($container, $registry, $bus);

        // Act
        $wrapped = $dispatcher->wrapHttp($route, $coreHandler);

        // Assert — returns a different handler (wrapped)
        $this->assertNotSame($coreHandler, $wrapped);
        $this->assertInstanceOf(HttpMiddleware::class, $wrapped);

        // Verify the wrapped stack is callable and produces the response
        $result = $wrapped->handle($request);
        $this->assertSame($response, $result);
    }
}
