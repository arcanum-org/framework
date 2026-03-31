<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo;

use Arcanum\Flow\River\LazyResource;
use Arcanum\Flow\River\Stream;
use Arcanum\Flow\River\StreamResource;
use Arcanum\Gather\IgnoreCaseRegistry;
use Arcanum\Gather\Registry;
use Arcanum\Hyper\Headers;
use Arcanum\Hyper\Message;
use Arcanum\Hyper\Response;
use Arcanum\Hyper\StatusCode;
use Arcanum\Hyper\Version;
use Arcanum\Shodo\JsonRenderer;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(JsonRenderer::class)]
#[UsesClass(Response::class)]
#[UsesClass(Message::class)]
#[UsesClass(Headers::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(Version::class)]
#[UsesClass(Stream::class)]
#[UsesClass(LazyResource::class)]
#[UsesClass(StreamResource::class)]
#[UsesClass(IgnoreCaseRegistry::class)]
#[UsesClass(Registry::class)]
final class JsonRendererTest extends TestCase
{
    private function decodeBody(ResponseInterface $response): mixed
    {
        $body = $response->getBody();
        $body->rewind();
        return json_decode($body->getContents(), true, 512, \JSON_THROW_ON_ERROR);
    }

    public function testRenderReturnsResponseInterface(): void
    {
        // Arrange
        $renderer = new JsonRenderer();

        // Act
        $response = $renderer->render(['key' => 'value']);

        // Assert
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testRenderSetsJsonContentType(): void
    {
        // Arrange
        $renderer = new JsonRenderer();

        // Act
        $response = $renderer->render(['key' => 'value']);

        // Assert
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testRenderSetsContentLength(): void
    {
        // Arrange
        $renderer = new JsonRenderer();
        $data = ['key' => 'value'];
        $json = json_encode($data, \JSON_UNESCAPED_SLASHES);
        $this->assertIsString($json);

        // Act
        $response = $renderer->render($data);

        // Assert
        $this->assertSame((string) strlen($json), $response->getHeaderLine('Content-Length'));
    }

    public function testRenderEncodesBodyAsJson(): void
    {
        // Arrange
        $renderer = new JsonRenderer();

        // Act
        $response = $renderer->render(['name' => 'Arcanum', 'version' => 1]);

        // Assert
        $this->assertSame(['name' => 'Arcanum', 'version' => 1], $this->decodeBody($response));
    }

    public function testRenderDefaultsToOkStatus(): void
    {
        // Arrange
        $renderer = new JsonRenderer();

        // Act
        $response = $renderer->render([]);

        // Assert
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRenderUsesProvidedStatusCode(): void
    {
        // Arrange
        $renderer = new JsonRenderer();

        // Act
        $response = $renderer->render(['error' => 'not found'], status: StatusCode::NotFound);

        // Assert
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testRenderUnescapesSlashes(): void
    {
        // Arrange
        $renderer = new JsonRenderer();

        // Act
        $response = $renderer->render(['url' => 'https://example.com/path']);
        $body = $response->getBody();
        $body->rewind();

        // Assert
        $this->assertStringContainsString('https://example.com/path', $body->getContents());
    }

    public function testRenderAcceptsDtoClassParameter(): void
    {
        // Arrange
        $renderer = new JsonRenderer();

        // Act
        $response = $renderer->render(['key' => 'value'], 'App\\Domain\\Query\\Health');

        // Assert — dtoClass is ignored, output is normal JSON
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(['key' => 'value'], $this->decodeBody($response));
    }

    public function testRenderThrowsOnUnencodableData(): void
    {
        // Arrange
        $renderer = new JsonRenderer();

        // Assert
        $this->expectException(\JsonException::class);

        // Act
        $renderer->render(fopen('php://memory', 'r'));
    }
}
