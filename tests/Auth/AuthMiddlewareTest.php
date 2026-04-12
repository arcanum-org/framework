<?php

declare(strict_types=1);

namespace Arcanum\Test\Auth;

use Arcanum\Auth\ActiveIdentity;
use Arcanum\Auth\AuthMiddleware;
use Arcanum\Auth\CompositeGuard;
use Arcanum\Auth\Guard;
use Arcanum\Auth\SessionGuard;
use Arcanum\Auth\SimpleIdentity;
use Arcanum\Auth\TokenGuard;
use Arcanum\Session\ActiveSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(AuthMiddleware::class)]
#[UsesClass(ActiveIdentity::class)]
#[UsesClass(CompositeGuard::class)]
#[UsesClass(SimpleIdentity::class)]
#[UsesClass(TokenGuard::class)]
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

    // -----------------------------------------------------------
    // Token authentication request attribute
    // -----------------------------------------------------------

    /**
     * Build a request stub that supports withAttribute() by tracking
     * attributes in an array and returning a new stub with getAttribute().
     *
     * @param array<string, string> $headers
     */
    private function requestWithAttributeSupport(array $headers = []): ServerRequestInterface
    {
        /** @var array<string, mixed> $attributes */
        $attributes = [];

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturnCallback(
            fn(string $name) => $headers[$name] ?? '',
        );
        $request->method('getAttribute')->willReturnCallback(
            fn(string $name, mixed $default = null) => $attributes[$name] ?? $default,
        );
        $request->method('withAttribute')->willReturnCallback(
            function (string $name, mixed $value) use (&$attributes, $headers) {
                $attributes[$name] = $value;
                return $this->requestWithStoredAttributes($headers, $attributes);
            }
        );

        return $request;
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $attributes
     */
    private function requestWithStoredAttributes(array $headers, array &$attributes): ServerRequestInterface
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturnCallback(
            fn(string $name) => $headers[$name] ?? '',
        );
        $request->method('getAttribute')->willReturnCallback(
            fn(string $name, mixed $default = null) => $attributes[$name] ?? $default,
        );
        $request->method('withAttribute')->willReturnCallback(
            function (string $name, mixed $value) use ($headers, &$attributes) {
                $attributes[$name] = $value;
                return $this->requestWithStoredAttributes($headers, $attributes);
            }
        );

        return $request;
    }

    public function testSetsTokenAuthAttributeForTokenGuard(): void
    {
        // Arrange
        $guard = new TokenGuard(fn(string $token) => new SimpleIdentity('user-1'));
        $middleware = new AuthMiddleware($guard, new ActiveIdentity());

        $request = $this->requestWithAttributeSupport(['Authorization' => 'Bearer valid-token']);

        $capturedRequest = null;
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(
            function (ServerRequestInterface $r) use (&$capturedRequest) {
                $capturedRequest = $r;
                return $this->createStub(ResponseInterface::class);
            }
        );

        // Act
        $middleware->process($request, $handler);

        // Assert
        $this->assertNotNull($capturedRequest);
        $this->assertTrue($capturedRequest->getAttribute('auth.token_authenticated'));
    }

    public function testDoesNotSetAttributeForSessionGuard(): void
    {
        // Arrange — generic guard stub (not TokenGuard)
        $guard = $this->createStub(Guard::class);
        $guard->method('resolve')->willReturn(new SimpleIdentity('user-1'));
        $middleware = new AuthMiddleware($guard, new ActiveIdentity());

        $request = $this->requestWithAttributeSupport();

        $capturedRequest = null;
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(
            function (ServerRequestInterface $r) use (&$capturedRequest) {
                $capturedRequest = $r;
                return $this->createStub(ResponseInterface::class);
            }
        );

        // Act
        $middleware->process($request, $handler);

        // Assert
        $this->assertNotNull($capturedRequest);
        $this->assertNull($capturedRequest->getAttribute('auth.token_authenticated'));
    }

    public function testSetsTokenAuthAttributeForCompositeGuardWithTokenMatch(): void
    {
        // Arrange — session guard returns null, token guard returns identity
        $sessionGuard = $this->createStub(Guard::class);
        $sessionGuard->method('resolve')->willReturn(null);

        $tokenGuard = new TokenGuard(fn(string $token) => new SimpleIdentity('user-1'));
        $composite = new CompositeGuard($sessionGuard, $tokenGuard);
        $middleware = new AuthMiddleware($composite, new ActiveIdentity());

        $request = $this->requestWithAttributeSupport(['Authorization' => 'Bearer valid-token']);

        $capturedRequest = null;
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(
            function (ServerRequestInterface $r) use (&$capturedRequest) {
                $capturedRequest = $r;
                return $this->createStub(ResponseInterface::class);
            }
        );

        // Act
        $middleware->process($request, $handler);

        // Assert
        $this->assertNotNull($capturedRequest);
        $this->assertTrue($capturedRequest->getAttribute('auth.token_authenticated'));
    }

    public function testDoesNotSetAttributeForCompositeGuardWithSessionMatch(): void
    {
        // Arrange — session guard returns identity (token guard never runs)
        $sessionGuard = $this->createStub(Guard::class);
        $sessionGuard->method('resolve')->willReturn(new SimpleIdentity('user-1'));

        $tokenGuard = new TokenGuard(fn(string $token) => null);
        $composite = new CompositeGuard($sessionGuard, $tokenGuard);
        $middleware = new AuthMiddleware($composite, new ActiveIdentity());

        $request = $this->requestWithAttributeSupport();

        $capturedRequest = null;
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(
            function (ServerRequestInterface $r) use (&$capturedRequest) {
                $capturedRequest = $r;
                return $this->createStub(ResponseInterface::class);
            }
        );

        // Act
        $middleware->process($request, $handler);

        // Assert
        $this->assertNotNull($capturedRequest);
        $this->assertNull($capturedRequest->getAttribute('auth.token_authenticated'));
    }

    public function testDoesNotSetAttributeWhenNoIdentityResolved(): void
    {
        // Arrange — token guard returns null (invalid token)
        $guard = new TokenGuard(fn(string $token) => null);
        $middleware = new AuthMiddleware($guard, new ActiveIdentity());

        $request = $this->requestWithAttributeSupport(['Authorization' => 'Bearer invalid-token']);

        $capturedRequest = null;
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(
            function (ServerRequestInterface $r) use (&$capturedRequest) {
                $capturedRequest = $r;
                return $this->createStub(ResponseInterface::class);
            }
        );

        // Act
        $middleware->process($request, $handler);

        // Assert — no identity resolved, no attribute set
        $this->assertNotNull($capturedRequest);
        $this->assertNull($capturedRequest->getAttribute('auth.token_authenticated'));
    }

    // -----------------------------------------------------------
    // Logger instrumentation
    // -----------------------------------------------------------

    public function testLogsIdentityResolved(): void
    {
        // Arrange
        $identity = new SimpleIdentity('user-1');
        $guard = $this->createStub(Guard::class);
        $guard->method('resolve')->willReturn($identity);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('Identity resolved', $this->callback(function (array $context): bool {
                return isset($context['guard']) && is_string($context['guard']);
            }));

        $active = new ActiveIdentity();
        $middleware = new AuthMiddleware($guard, $active, $logger);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->createStub(ResponseInterface::class));

        // Act
        $middleware->process($this->createStub(ServerRequestInterface::class), $handler);
    }

    public function testLogsNoIdentityResolved(): void
    {
        // Arrange
        $guard = $this->createStub(Guard::class);
        $guard->method('resolve')->willReturn(null);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with('No identity resolved');

        $active = new ActiveIdentity();
        $middleware = new AuthMiddleware($guard, $active, $logger);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->createStub(ResponseInterface::class));

        // Act
        $middleware->process($this->createStub(ServerRequestInterface::class), $handler);
    }
}
