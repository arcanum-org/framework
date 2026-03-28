<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo;

use Arcanum\Shodo\Format;
use Arcanum\Shodo\FormatRegistry;
use Arcanum\Shodo\JsonRenderer;
use Arcanum\Shodo\Renderer;
use Arcanum\Shodo\UnsupportedFormat;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Container\ContainerInterface;

#[CoversClass(FormatRegistry::class)]
#[UsesClass(Format::class)]
#[UsesClass(UnsupportedFormat::class)]
#[UsesClass(\Arcanum\Glitch\HttpException::class)]
final class FormatRegistryTest extends TestCase
{
    private function jsonFormat(): Format
    {
        return new Format('json', 'application/json', JsonRenderer::class);
    }

    private function htmlFormat(): Format
    {
        return new Format('html', 'text/html', 'App\\Shodo\\HtmlRenderer');
    }

    // ---------------------------------------------------------------
    // Register and get
    // ---------------------------------------------------------------

    public function testRegisterAndGet(): void
    {
        // Arrange
        $container = $this->createStub(ContainerInterface::class);
        $registry = new FormatRegistry($container);
        $format = $this->jsonFormat();

        // Act
        $registry->register($format);
        $result = $registry->get('json');

        // Assert
        $this->assertSame($format, $result);
    }

    public function testGetThrowsUnsupportedFormatForUnregisteredExtension(): void
    {
        // Arrange
        $container = $this->createStub(ContainerInterface::class);
        $registry = new FormatRegistry($container);

        // Act & Assert
        $this->expectException(UnsupportedFormat::class);
        $this->expectExceptionMessage('Format "xml" is not supported');
        $registry->get('xml');
    }

    public function testUnsupportedFormatIs406(): void
    {
        // Arrange
        $container = $this->createStub(ContainerInterface::class);
        $registry = new FormatRegistry($container);

        // Act & Assert
        try {
            $registry->get('xml');
        } catch (UnsupportedFormat $e) {
            $this->assertSame(406, $e->getCode());
            return;
        }

        $this->fail('UnsupportedFormat was not thrown');
    }

    // ---------------------------------------------------------------
    // Has
    // ---------------------------------------------------------------

    public function testHasReturnsTrueForRegisteredFormat(): void
    {
        // Arrange
        $container = $this->createStub(ContainerInterface::class);
        $registry = new FormatRegistry($container);
        $registry->register($this->jsonFormat());

        // Act & Assert
        $this->assertTrue($registry->has('json'));
    }

    public function testHasReturnsFalseForUnregisteredFormat(): void
    {
        // Arrange
        $container = $this->createStub(ContainerInterface::class);
        $registry = new FormatRegistry($container);

        // Act & Assert
        $this->assertFalse($registry->has('xml'));
    }

    // ---------------------------------------------------------------
    // Remove
    // ---------------------------------------------------------------

    public function testRemoveUnregistersFormat(): void
    {
        // Arrange
        $container = $this->createStub(ContainerInterface::class);
        $registry = new FormatRegistry($container);
        $registry->register($this->jsonFormat());

        // Act
        $registry->remove('json');

        // Assert
        $this->assertFalse($registry->has('json'));
    }

    public function testRemoveNonExistentFormatDoesNotThrow(): void
    {
        // Arrange
        $container = $this->createStub(ContainerInterface::class);
        $registry = new FormatRegistry($container);

        // Act & Assert — should not throw
        $registry->remove('xml');
        $this->assertFalse($registry->has('xml'));
    }

    // ---------------------------------------------------------------
    // Renderer resolution
    // ---------------------------------------------------------------

    public function testRendererResolvesFromContainer(): void
    {
        // Arrange
        $jsonRenderer = new JsonRenderer();

        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')
            ->willReturn($jsonRenderer);

        $registry = new FormatRegistry($container);
        $registry->register($this->jsonFormat());

        // Act
        $renderer = $registry->renderer('json');

        // Assert
        $this->assertSame($jsonRenderer, $renderer);
        $this->assertInstanceOf(Renderer::class, $renderer);
    }

    public function testRendererThrowsForUnregisteredExtension(): void
    {
        // Arrange
        $container = $this->createStub(ContainerInterface::class);
        $registry = new FormatRegistry($container);

        // Act & Assert
        $this->expectException(UnsupportedFormat::class);
        $registry->renderer('xml');
    }

    // ---------------------------------------------------------------
    // Multiple formats
    // ---------------------------------------------------------------

    public function testMultipleFormatsRegistered(): void
    {
        // Arrange
        $container = $this->createStub(ContainerInterface::class);
        $registry = new FormatRegistry($container);
        $registry->register($this->jsonFormat());
        $registry->register($this->htmlFormat());

        // Act & Assert
        $this->assertTrue($registry->has('json'));
        $this->assertTrue($registry->has('html'));
        $this->assertSame('application/json', $registry->get('json')->contentType);
        $this->assertSame('text/html', $registry->get('html')->contentType);
    }

    public function testRegisterOverwritesExistingFormat(): void
    {
        // Arrange
        $container = $this->createStub(ContainerInterface::class);
        $registry = new FormatRegistry($container);
        $registry->register($this->jsonFormat());

        $override = new Format('json', 'application/json', 'App\\Custom\\JsonRenderer');

        // Act
        $registry->register($override);

        // Assert
        $this->assertSame('App\\Custom\\JsonRenderer', $registry->get('json')->rendererClass);
    }
}
