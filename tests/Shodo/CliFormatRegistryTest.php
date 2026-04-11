<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo;

use Arcanum\Shodo\CliFormatRegistry;
use Arcanum\Shodo\Formatters\JsonFormatter;
use Arcanum\Shodo\Formatters\KeyValueFormatter;
use Arcanum\Shodo\Formatters\TableFormatter;
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
                KeyValueFormatter::class => new KeyValueFormatter(),
                TableFormatter::class => new TableFormatter(),
                JsonFormatter::class => new JsonFormatter(),
                default => throw new \RuntimeException("Unexpected: $id"),
            },
        );

        $registry = new CliFormatRegistry($container);
        $registry->register('cli', KeyValueFormatter::class);
        $registry->register('table', TableFormatter::class);
        $registry->register('json', JsonFormatter::class);

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

    public function testFormatterResolvesFromContainer(): void
    {
        // Arrange
        $registry = $this->registryWithDefaults();

        // Act
        $formatter = $registry->formatter('cli');

        // Assert
        $this->assertInstanceOf(KeyValueFormatter::class, $formatter);
    }

    public function testFormatterResolvesTableFormatter(): void
    {
        // Arrange
        $registry = $this->registryWithDefaults();

        // Act
        $formatter = $registry->formatter('table');

        // Assert
        $this->assertInstanceOf(TableFormatter::class, $formatter);
    }

    public function testFormatterResolvesJsonFormatter(): void
    {
        // Arrange
        $registry = $this->registryWithDefaults();

        // Act
        $formatter = $registry->formatter('json');

        // Assert
        $this->assertInstanceOf(JsonFormatter::class, $formatter);
    }

    public function testFormatterThrowsForUnsupportedFormat(): void
    {
        // Arrange
        $registry = $this->registryWithDefaults();

        // Act & Assert
        $this->expectException(UnsupportedFormat::class);
        $registry->formatter('xml');
    }

    public function testRegisterOverwritesPreviousEntry(): void
    {
        // Arrange
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn(new TableFormatter());

        $registry = new CliFormatRegistry($container);
        $registry->register('default', KeyValueFormatter::class);
        $registry->register('default', TableFormatter::class);

        // Act
        $formatter = $registry->formatter('default');

        // Assert
        $this->assertInstanceOf(TableFormatter::class, $formatter);
    }
}
