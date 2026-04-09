<?php

declare(strict_types=1);

namespace Arcanum\Test\Htmx;

use Arcanum\Htmx\HtmxAwareResponseRenderer;
use Arcanum\Htmx\HtmxRequest;
use Arcanum\Htmx\HtmxRequestMiddleware;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(HtmxRequestMiddleware::class)]
#[UsesClass(HtmxRequest::class)]
final class HtmxRequestMiddlewareTest extends TestCase
{
    /**
     * @param array<string, string> $requestHeaders
     * @param array<string, list<string>> $responseHeaders shared header store
     */
    private function process(
        array $requestHeaders = [],
        bool $addVaryHeader = true,
        array &$responseHeaders = [],
    ): ResponseInterface {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('hasHeader')
            ->willReturnCallback(fn(string $n) => isset($requestHeaders[$n]));
        $request->method('getHeaderLine')
            ->willReturnCallback(fn(string $n) => $requestHeaders[$n] ?? '');

        $response = $this->createStub(ResponseInterface::class);
        $response->method('withAddedHeader')
            ->willReturnCallback(function (string $n, string $v) use (&$responseHeaders, $response) {
                $responseHeaders[$n][] = $v;
                return $response;
            });
        $response->method('getHeaderLine')
            ->willReturnCallback(fn(string $n) => implode(', ', $responseHeaders[$n] ?? []));
        $response->method('hasHeader')
            ->willReturnCallback(fn(string $n) => isset($responseHeaders[$n]));

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $renderer = $this->createMock(HtmxAwareResponseRenderer::class);

        if (isset($requestHeaders['HX-Request'])) {
            $renderer->expects($this->once())
                ->method('setHtmxRequest')
                ->with($this->isInstanceOf(HtmxRequest::class));
        } else {
            $renderer->expects($this->never())
                ->method('setHtmxRequest');
        }

        $middleware = new HtmxRequestMiddleware($renderer, $addVaryHeader);

        return $middleware->process($request, $handler);
    }

    public function testSetsHtmxRequestOnRendererForHtmxRequest(): void
    {
        // Act — the mock expectation above verifies setHtmxRequest was called
        $this->process(['HX-Request' => 'true']);
    }

    public function testDoesNotSetHtmxRequestForNormalRequest(): void
    {
        // Act — the mock expectation above verifies setHtmxRequest was NOT called
        $this->process();
    }

    public function testAddsVaryHeaderByDefault(): void
    {
        // Arrange & Act
        $headers = [];
        $this->process(['HX-Request' => 'true'], true, $headers);

        // Assert
        $this->assertContains('HX-Request', $headers['Vary'] ?? []);
    }

    public function testAddsVaryHeaderForNonHtmxRequestToo(): void
    {
        // Arrange & Act — Vary should be added regardless (cache correctness)
        $headers = [];
        $this->process([], true, $headers);

        // Assert
        $this->assertContains('HX-Request', $headers['Vary'] ?? []);
    }

    public function testVaryHeaderCanBeDisabled(): void
    {
        // Arrange & Act
        $headers = [];
        $this->process(['HX-Request' => 'true'], false, $headers);

        // Assert
        $this->assertArrayNotHasKey('Vary', $headers);
    }
}
