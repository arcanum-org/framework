<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo\Formatters;

use Arcanum\Shodo\Formatters\JsonFormatter;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(JsonFormatter::class)]
final class JsonFormatterTest extends TestCase
{
    public function testFormatReturnsJsonString(): void
    {
        // Arrange
        $formatter = new JsonFormatter();

        // Act
        $result = $formatter->format(['key' => 'value']);

        // Assert
        $this->assertSame('{"key":"value"}', $result);
    }

    public function testFormatEncodesArrayData(): void
    {
        // Arrange
        $formatter = new JsonFormatter();

        // Act
        $result = $formatter->format(['name' => 'Arcanum', 'version' => 1]);

        // Assert
        $decoded = json_decode($result, true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(['name' => 'Arcanum', 'version' => 1], $decoded);
    }

    public function testFormatUnescapesSlashes(): void
    {
        // Arrange
        $formatter = new JsonFormatter();

        // Act
        $result = $formatter->format(['url' => 'https://example.com/path']);

        // Assert
        $this->assertStringContainsString('https://example.com/path', $result);
    }

    public function testFormatEncodesEmptyArray(): void
    {
        // Arrange
        $formatter = new JsonFormatter();

        // Act
        $result = $formatter->format([]);

        // Assert
        $this->assertSame('[]', $result);
    }

    public function testFormatEncodesScalar(): void
    {
        // Arrange
        $formatter = new JsonFormatter();

        // Act
        $result = $formatter->format('hello');

        // Assert
        $this->assertSame('"hello"', $result);
    }

    public function testFormatEncodesNull(): void
    {
        // Arrange
        $formatter = new JsonFormatter();

        // Act
        $result = $formatter->format(null);

        // Assert
        $this->assertSame('null', $result);
    }

    public function testFormatIgnoresDtoClass(): void
    {
        // Arrange
        $formatter = new JsonFormatter();

        // Act
        $result = $formatter->format(['key' => 'value'], 'App\\Domain\\Query\\Health');

        // Assert
        $this->assertSame('{"key":"value"}', $result);
    }

    public function testFormatThrowsOnUnencodableData(): void
    {
        // Arrange
        $formatter = new JsonFormatter();

        // Assert
        $this->expectException(\JsonException::class);

        // Act
        $formatter->format(fopen('php://memory', 'r'));
    }
}
