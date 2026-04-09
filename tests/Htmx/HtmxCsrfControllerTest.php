<?php

declare(strict_types=1);

namespace Arcanum\Test\Htmx;

use Arcanum\Htmx\HtmxCsrfController;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(HtmxCsrfController::class)]
#[UsesClass(\Arcanum\Hyper\Response::class)]
#[UsesClass(\Arcanum\Hyper\Message::class)]
#[UsesClass(\Arcanum\Hyper\Headers::class)]
#[UsesClass(\Arcanum\Hyper\StatusCode::class)]
#[UsesClass(\Arcanum\Flow\River\Stream::class)]
#[UsesClass(\Arcanum\Flow\River\LazyResource::class)]
#[UsesClass(\Arcanum\Gather\Registry::class)]
final class HtmxCsrfControllerTest extends TestCase
{
    public function testReturnsJavaScriptResponse(): void
    {
        // Arrange
        $controller = new HtmxCsrfController();

        // Act
        $response = $controller->handle();

        // Assert — correct content type
        $this->assertStringContainsString(
            'text/javascript',
            $response->getHeaderLine('Content-Type'),
        );
    }

    public function testResponseIsCacheable(): void
    {
        // Arrange
        $controller = new HtmxCsrfController();

        // Act
        $response = $controller->handle();

        // Assert
        $this->assertStringContainsString(
            'public',
            $response->getHeaderLine('Cache-Control'),
        );
        $this->assertStringContainsString(
            'max-age=',
            $response->getHeaderLine('Cache-Control'),
        );
    }

    public function testResponseBodyContainsCsrfTokenInjection(): void
    {
        // Arrange
        $controller = new HtmxCsrfController();

        // Act
        $response = $controller->handle();
        $body = (string) $response->getBody();

        // Assert — the shim reads the meta tag and attaches to configRequest
        $this->assertStringContainsString('csrf-token', $body);
        $this->assertStringContainsString('htmx:configRequest', $body);
        $this->assertStringContainsString('X-CSRF-TOKEN', $body);
    }

    public function testResponseBodySkipsGetRequests(): void
    {
        // Arrange
        $controller = new HtmxCsrfController();

        // Act
        $body = (string) $controller->handle()->getBody();

        // Assert — GET, HEAD, OPTIONS should not get tokens
        $this->assertStringContainsString("!== 'GET'", $body);
        $this->assertStringContainsString("!== 'HEAD'", $body);
        $this->assertStringContainsString("!== 'OPTIONS'", $body);
    }

    public function testResponseIs200(): void
    {
        // Arrange
        $controller = new HtmxCsrfController();

        // Act
        $response = $controller->handle();

        // Assert
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testContentLengthMatchesBody(): void
    {
        // Arrange
        $controller = new HtmxCsrfController();

        // Act
        $response = $controller->handle();
        $body = (string) $response->getBody();

        // Assert
        $this->assertSame(
            (string) strlen($body),
            $response->getHeaderLine('Content-Length'),
        );
    }
}
