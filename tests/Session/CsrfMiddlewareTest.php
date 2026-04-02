<?php

declare(strict_types=1);

namespace Arcanum\Test\Session;

use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\StatusCode;
use Arcanum\Session\CsrfMiddleware;
use Arcanum\Session\CsrfToken;
use Arcanum\Session\Flash;
use Arcanum\Session\Session;
use Arcanum\Session\SessionId;
use Arcanum\Session\SessionRegistry;
use Arcanum\Toolkit\Random;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(CsrfMiddleware::class)]
#[UsesClass(Session::class)]
#[UsesClass(SessionId::class)]
#[UsesClass(SessionRegistry::class)]
#[UsesClass(CsrfToken::class)]
#[UsesClass(Flash::class)]
#[UsesClass(Random::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(StatusCode::class)]
final class CsrfMiddlewareTest extends TestCase
{
    private function registryWithSession(): SessionRegistry
    {
        $registry = new SessionRegistry();
        $registry->set(new Session(SessionId::generate()));
        return $registry;
    }

    /**
     * @param array<string, string> $body
     * @param array<string, string> $headers
     */
    private function stubRequest(string $method, array $body = [], array $headers = []): ServerRequestInterface
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getParsedBody')->willReturn($body);
        $request->method('getHeaderLine')->willReturnCallback(
            fn(string $name) => $headers[$name] ?? '',
        );
        return $request;
    }

    private function stubHandler(): RequestHandlerInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);
        return $handler;
    }

    public function testGetRequestPassesThrough(): void
    {
        $registry = $this->registryWithSession();
        $middleware = new CsrfMiddleware($registry);

        $response = $middleware->process(
            $this->stubRequest('GET'),
            $this->stubHandler(),
        );

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testHeadRequestPassesThrough(): void
    {
        $registry = $this->registryWithSession();
        $middleware = new CsrfMiddleware($registry);

        $response = $middleware->process(
            $this->stubRequest('HEAD'),
            $this->stubHandler(),
        );

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testOptionsRequestPassesThrough(): void
    {
        $registry = $this->registryWithSession();
        $middleware = new CsrfMiddleware($registry);

        $response = $middleware->process(
            $this->stubRequest('OPTIONS'),
            $this->stubHandler(),
        );

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testPostWithValidTokenInBodyPasses(): void
    {
        $registry = $this->registryWithSession();
        $token = $registry->get()->csrfToken()->value;
        $middleware = new CsrfMiddleware($registry);

        $response = $middleware->process(
            $this->stubRequest('POST', ['_token' => $token]),
            $this->stubHandler(),
        );

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testPostWithValidTokenInHeaderPasses(): void
    {
        $registry = $this->registryWithSession();
        $token = $registry->get()->csrfToken()->value;
        $middleware = new CsrfMiddleware($registry);

        $response = $middleware->process(
            $this->stubRequest('POST', [], ['X-CSRF-TOKEN' => $token]),
            $this->stubHandler(),
        );

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testPostWithMissingTokenThrows403(): void
    {
        $registry = $this->registryWithSession();
        $middleware = new CsrfMiddleware($registry);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        $middleware->process(
            $this->stubRequest('POST'),
            $this->stubHandler(),
        );
    }

    public function testPostWithWrongTokenThrows403(): void
    {
        $registry = $this->registryWithSession();
        $middleware = new CsrfMiddleware($registry);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        $middleware->process(
            $this->stubRequest('POST', ['_token' => 'wrong-token']),
            $this->stubHandler(),
        );
    }

    public function testPutRequiresToken(): void
    {
        $registry = $this->registryWithSession();
        $middleware = new CsrfMiddleware($registry);

        $this->expectException(HttpException::class);

        $middleware->process(
            $this->stubRequest('PUT'),
            $this->stubHandler(),
        );
    }

    public function testPatchRequiresToken(): void
    {
        $registry = $this->registryWithSession();
        $middleware = new CsrfMiddleware($registry);

        $this->expectException(HttpException::class);

        $middleware->process(
            $this->stubRequest('PATCH'),
            $this->stubHandler(),
        );
    }

    public function testDeleteRequiresToken(): void
    {
        $registry = $this->registryWithSession();
        $middleware = new CsrfMiddleware($registry);

        $this->expectException(HttpException::class);

        $middleware->process(
            $this->stubRequest('DELETE'),
            $this->stubHandler(),
        );
    }

    public function testBearerTokenBypassesCsrf(): void
    {
        $registry = $this->registryWithSession();
        $middleware = new CsrfMiddleware($registry);

        $response = $middleware->process(
            $this->stubRequest('POST', [], ['Authorization' => 'Bearer some-api-token']),
            $this->stubHandler(),
        );

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testBodyTokenTakesPriorityOverHeader(): void
    {
        $registry = $this->registryWithSession();
        $token = $registry->get()->csrfToken()->value;
        $middleware = new CsrfMiddleware($registry);

        // Body has the right token, header has the wrong one.
        $response = $middleware->process(
            $this->stubRequest('POST', ['_token' => $token], ['X-CSRF-TOKEN' => 'wrong']),
            $this->stubHandler(),
        );

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }
}
