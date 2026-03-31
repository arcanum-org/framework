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

    // ---------------------------------------------------------------
    // Config-driven scenarios
    // ---------------------------------------------------------------

    public function testAppDefinedCustomFormatWithCustomRenderer(): void
    {
        // Arrange — simulate an app registering a custom "yaml" format
        $yamlRenderer = $this->createStub(Renderer::class);

        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->onlyMethods(['get', 'has'])
            ->getMock();

        $container->expects($this->once())
            ->method('get')
            ->with('App\\Shodo\\YamlRenderer')
            ->willReturn($yamlRenderer);

        $registry = new FormatRegistry($container);

        // Act — register a custom format just as Bootstrap\Routing would from config
        $registry->register(new Format('yaml', 'application/x-yaml', 'App\\Shodo\\YamlRenderer'));

        // Assert
        $this->assertTrue($registry->has('yaml'));
        $this->assertSame('application/x-yaml', $registry->get('yaml')->contentType);
        $this->assertSame($yamlRenderer, $registry->renderer('yaml'));
    }

    public function testDisablingBuiltInFormatViaConfig(): void
    {
        // Arrange — register built-in formats, then remove one as config would
        $container = $this->createStub(ContainerInterface::class);
        $registry = new FormatRegistry($container);
        $registry->register($this->jsonFormat());
        $registry->register($this->htmlFormat());

        // Act — app config disables HTML format
        $registry->remove('html');

        // Assert — JSON still works, HTML throws 406
        $this->assertTrue($registry->has('json'));
        $this->assertFalse($registry->has('html'));
        $this->expectException(UnsupportedFormat::class);
        $registry->renderer('html');
    }

    public function testOverridingBuiltInFormatRendererViaConfig(): void
    {
        // Arrange — register built-in JSON, then override with custom renderer
        $customRenderer = $this->createStub(Renderer::class);

        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->onlyMethods(['get', 'has'])
            ->getMock();

        $container->expects($this->once())
            ->method('get')
            ->with('App\\Shodo\\CustomJsonRenderer')
            ->willReturn($customRenderer);

        $registry = new FormatRegistry($container);
        $registry->register($this->jsonFormat());

        // Act — app config overrides JSON with a custom renderer class
        $registry->register(new Format('json', 'application/json', 'App\\Shodo\\CustomJsonRenderer'));

        // Assert — same extension, same content type, different renderer
        $this->assertSame('application/json', $registry->get('json')->contentType);
        $this->assertSame('App\\Shodo\\CustomJsonRenderer', $registry->get('json')->rendererClass);
        $this->assertSame($customRenderer, $registry->renderer('json'));
    }
}
