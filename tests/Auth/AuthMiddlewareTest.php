<?php

declare(strict_types=1);

namespace Arcanum\Test\Auth;

use Arcanum\Auth\ActiveIdentity;
use Arcanum\Auth\AuthMiddleware;
use Arcanum\Auth\Guard;
use Arcanum\Auth\SimpleIdentity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(AuthMiddleware::class)]
#[UsesClass(ActiveIdentity::class)]
#[UsesClass(SimpleIdentity::class)]
final class AuthMiddlewareTest extends TestCase
{
    public function testResolvesIdentityAndSetsActiveIdentity(): void
    {
        $identity = new SimpleIdentity('user-1');
        $guard = $this->createStub(Guard::class);
        $guard->method('resolve')->willReturn($identity);

        $active = new ActiveIdentity();
        $middleware = new AuthMiddleware($guard, $active);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->createStub(ResponseInterface::class));

        $middleware->process($this->createStub(ServerRequestInterface::class), $handler);

        $this->assertTrue($active->has());
        $this->assertSame($identity, $active->get());
    }

    public function testLeavesActiveIdentityEmptyWhenGuardReturnsNull(): void
    {
        $guard = $this->createStub(Guard::class);
        $guard->method('resolve')->willReturn(null);

        $active = new ActiveIdentity();
        $middleware = new AuthMiddleware($guard, $active);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->createStub(ResponseInterface::class));

        $middleware->process($this->createStub(ServerRequestInterface::class), $handler);

        $this->assertFalse($active->has());
    }

    public function testAlwaysDelegatesToHandler(): void
    {
        $guard = $this->createStub(Guard::class);
        $guard->method('resolve')->willReturn(null);

        $expectedResponse = $this->createStub(ResponseInterface::class);
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($expectedResponse);

        $middleware = new AuthMiddleware($guard, new ActiveIdentity());

        $response = $middleware->process(
            $this->createStub(ServerRequestInterface::class),
            $handler,
        );

        $this->assertSame($expectedResponse, $response);
    }
}
