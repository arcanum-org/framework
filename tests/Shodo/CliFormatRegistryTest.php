<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo;

use Arcanum\Shodo\CliFormatRegistry;
use Arcanum\Shodo\CliRenderer;
use Arcanum\Shodo\JsonRenderer;
use Arcanum\Shodo\TableRenderer;
use Arcanum\Shodo\UnsupportedFormat;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Container\ContainerInterface;

#[CoversClass(CliFormatRegistry::class)]
#[UsesClass(UnsupportedFormat::class)]
final class CliFormatRegistryTest extends TestCase
{
    private function registryWithDefaults(): CliFormatRegistry
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            fn(string $id): object => match ($id) {
                CliRenderer::class => new CliRenderer(),
                TableRenderer::class => new TableRenderer(),
                JsonRenderer::class => new JsonRenderer(),
                default => throw new \RuntimeException("Unexpected: $id"),
            },
        );

        $registry = new CliFormatRegistry($container);
        $registry->register('cli', CliRenderer::class);
        $registry->register('table', TableRenderer::class);
        $registry->register('json', JsonRenderer::class);

        return $registry;
    }

    public function testHasReturnsTrueForRegisteredFormat(): void
    {
        // Arrange
        $registry = $this->registryWithDefaults();

        // Act & Assert
        $this->assertTrue($registry->has('cli'));
        $this->assertTrue($registry->has('table'));
        $this->assertTrue($registry->has('json'));
    }

    public function testHasReturnsFalseForUnregisteredFormat(): void
    {
        // Arrange
        $registry = $this->registryWithDefaults();

        // Act & Assert
        $this->assertFalse($registry->has('xml'));
    }

    public function testRendererResolvesFromContainer(): void
    {
        // Arrange
        $registry = $this->registryWithDefaults();

        // Act
        $renderer = $registry->renderer('cli');

        // Assert
        $this->assertInstanceOf(CliRenderer::class, $renderer);
    }

    public function testRendererResolvesTableRenderer(): void
    {
        // Arrange
        $registry = $this->registryWithDefaults();

        // Act
        $renderer = $registry->renderer('table');

        // Assert
        $this->assertInstanceOf(TableRenderer::class, $renderer);
    }

    public function testRendererResolvesJsonRenderer(): void
    {
        // Arrange
        $registry = $this->registryWithDefaults();

        // Act
        $renderer = $registry->renderer('json');

        // Assert
        $this->assertInstanceOf(JsonRenderer::class, $renderer);
    }

    public function testRendererThrowsForUnsupportedFormat(): void
    {
        // Arrange
        $registry = $this->registryWithDefaults();

        // Act & Assert
        $this->expectException(UnsupportedFormat::class);
        $registry->renderer('xml');
    }

    public function testRegisterOverwritesPreviousEntry(): void
    {
        // Arrange
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn(new TableRenderer());

        $registry = new CliFormatRegistry($container);
        $registry->register('default', CliRenderer::class);
        $registry->register('default', TableRenderer::class);

        // Act
        $renderer = $registry->renderer('default');

        // Assert
        $this->assertInstanceOf(TableRenderer::class, $renderer);
    }
}
