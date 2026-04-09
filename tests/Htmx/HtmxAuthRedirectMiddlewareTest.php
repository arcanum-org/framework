<?php

declare(strict_types=1);

namespace Arcanum\Test\Htmx;

use Arcanum\Htmx\HtmxAuthRedirectMiddleware;
use Arcanum\Htmx\HtmxResponse;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(HtmxAuthRedirectMiddleware::class)]
#[UsesClass(HtmxResponse::class)]
final class HtmxAuthRedirectMiddlewareTest extends TestCase
{
    /**
     * @param array<string, string> $requestHeaders
     */
    private function process(
        array $requestHeaders,
        int $statusCode,
        string $loginUrl = '/login',
        bool $useRefresh = false,
    ): ResponseInterface {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('hasHeader')
            ->willReturnCallback(fn(string $n) => isset($requestHeaders[$n]));

        $headers = [];
        $makeStub = function () use (&$headers, &$makeStub, $statusCode): ResponseInterface {
            $stub = $this->createStub(ResponseInterface::class);
            $stub->method('getStatusCode')->willReturn($statusCode);
            $stub->method('hasHeader')
                ->willReturnCallback(fn(string $n) => isset($headers[$n]));
            $stub->method('getHeaderLine')
                ->willReturnCallback(fn(string $n) => $headers[$n] ?? '');
            $stub->method('withHeader')
                ->willReturnCallback(function (string $n, string $v) use (&$headers, $makeStub) {
                    $headers[$n] = $v;
                    return $makeStub();
                });
            return $stub;
        };
        $response = $makeStub();

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $middleware = new HtmxAuthRedirectMiddleware($loginUrl, $useRefresh);

        return $middleware->process($request, $handler);
    }

    public function testPassesThroughNonHtmxRequest(): void
    {
        // Arrange & Act
        $response = $this->process([], 401);

        // Assert — no htmx headers added
        $this->assertFalse($response->hasHeader('HX-Location'));
        $this->assertFalse($response->hasHeader('HX-Refresh'));
    }

    public function testPassesThroughNon401Or403(): void
    {
        // Arrange & Act
        $response = $this->process(['HX-Request' => 'true'], 200);

        // Assert
        $this->assertFalse($response->hasHeader('HX-Location'));
    }

    public function testRedirectsToLoginOn401(): void
    {
        // Arrange & Act
        $response = $this->process(['HX-Request' => 'true'], 401);

        // Assert
        $this->assertSame('/login', $response->getHeaderLine('HX-Location'));
    }

    public function testRedirectsToLoginOn403(): void
    {
        // Arrange & Act
        $response = $this->process(['HX-Request' => 'true'], 403);

        // Assert
        $this->assertSame('/login', $response->getHeaderLine('HX-Location'));
    }

    public function testCustomLoginUrl(): void
    {
        // Arrange & Act
        $response = $this->process(['HX-Request' => 'true'], 401, '/auth/signin');

        // Assert
        $this->assertSame('/auth/signin', $response->getHeaderLine('HX-Location'));
    }

    public function testRefreshMode(): void
    {
        // Arrange & Act
        $response = $this->process(
            ['HX-Request' => 'true'],
            401,
            '/login',
            true,
        );

        // Assert
        $this->assertSame('true', $response->getHeaderLine('HX-Refresh'));
        $this->assertFalse($response->hasHeader('HX-Location'));
    }
}
