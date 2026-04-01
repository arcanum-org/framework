<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper;

use Arcanum\Flow\River\EmptyStream;
use Arcanum\Gather\IgnoreCaseRegistry;
use Arcanum\Gather\Registry;
use Arcanum\Hyper\EmptyResponseRenderer;
use Arcanum\Hyper\Headers;
use Arcanum\Hyper\Message;
use Arcanum\Hyper\Response;
use Arcanum\Hyper\StatusCode;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(EmptyResponseRenderer::class)]
#[UsesClass(EmptyStream::class)]
#[UsesClass(Headers::class)]
#[UsesClass(Message::class)]
#[UsesClass(Response::class)]
#[UsesClass(IgnoreCaseRegistry::class)]
#[UsesClass(Registry::class)]
final class EmptyResponseRendererTest extends TestCase
{
    public function testDefaultsTo204NoContent(): void
    {
        // Arrange
        $renderer = new EmptyResponseRenderer();

        // Act
        $response = $renderer->render();

        // Assert
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testBodyIsEmpty(): void
    {
        // Arrange
        $renderer = new EmptyResponseRenderer();

        // Act
        $response = $renderer->render();

        // Assert
        $this->assertSame('', (string) $response->getBody());
    }

    public function testCustomStatusCode(): void
    {
        // Arrange
        $renderer = new EmptyResponseRenderer();

        // Act
        $response = $renderer->render(StatusCode::Created);

        // Assert
        $this->assertSame(201, $response->getStatusCode());
    }

    public function testAcceptedStatusCode(): void
    {
        // Arrange
        $renderer = new EmptyResponseRenderer();

        // Act
        $response = $renderer->render(StatusCode::Accepted);

        // Assert
        $this->assertSame(202, $response->getStatusCode());
    }

    public function testReturnsValidResponseInterface(): void
    {
        // Arrange
        $renderer = new EmptyResponseRenderer();

        // Act
        $response = $renderer->render();

        // Assert
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }
}
