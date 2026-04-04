<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo\Formatters;

use Arcanum\Shodo\Formatters\MarkdownFallbackFormatter;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MarkdownFallbackFormatter::class)]
final class MarkdownFallbackFormatterTest extends TestCase
{
    public function testRendersScalarString(): void
    {
        // Arrange
        $fallback = new MarkdownFallbackFormatter();

        // Act
        $text = $fallback->format('Hello world');

        // Assert
        $this->assertSame('Hello world', $text);
    }

    public function testRendersScalarInteger(): void
    {
        // Arrange
        $fallback = new MarkdownFallbackFormatter();

        // Act
        $text = $fallback->format(42);

        // Assert
        $this->assertSame('42', $text);
    }

    public function testRendersBooleanTrue(): void
    {
        // Arrange
        $fallback = new MarkdownFallbackFormatter();

        // Act
        $text = $fallback->format(true);

        // Assert
        $this->assertSame('true', $text);
    }

    public function testRendersBooleanFalse(): void
    {
        // Arrange
        $fallback = new MarkdownFallbackFormatter();

        // Act
        $text = $fallback->format(false);

        // Assert
        $this->assertSame('false', $text);
    }

    public function testRendersAssociativeArrayWithBoldKeys(): void
    {
        // Arrange
        $fallback = new MarkdownFallbackFormatter();

        // Act
        $text = $fallback->format(['name' => 'Arcanum', 'version' => '1.0']);

        // Assert
        $this->assertSame("**name:** Arcanum\n**version:** 1.0", $text);
    }

    public function testRendersSequentialArrayAsBulletedList(): void
    {
        // Arrange
        $fallback = new MarkdownFallbackFormatter();

        // Act
        $text = $fallback->format(['apple', 'banana', 'cherry']);

        // Assert
        $this->assertSame("- apple\n- banana\n- cherry", $text);
    }

    public function testRendersNestedStructureWithHeadings(): void
    {
        // Arrange
        $fallback = new MarkdownFallbackFormatter();

        // Act
        $text = $fallback->format([
            'user' => [
                'name' => 'Alice',
                'roles' => ['admin', 'editor'],
            ],
        ]);

        // Assert
        $this->assertStringContainsString('## user', $text);
        $this->assertStringContainsString('**name:** Alice', $text);
        $this->assertStringContainsString('### roles', $text);
        $this->assertStringContainsString('- admin', $text);
        $this->assertStringContainsString('- editor', $text);
    }

    public function testRendersObjectPublicProperties(): void
    {
        // Arrange
        $fallback = new MarkdownFallbackFormatter();
        $obj = new class {
            public string $name = 'Arcanum';
            public int $version = 1;
        };

        // Act
        $text = $fallback->format($obj);

        // Assert
        $this->assertSame("**name:** Arcanum\n**version:** 1", $text);
    }

    public function testRendersEmptyArray(): void
    {
        // Arrange
        $fallback = new MarkdownFallbackFormatter();

        // Act
        $text = $fallback->format([]);

        // Assert
        $this->assertSame('', $text);
    }

    public function testRendersNull(): void
    {
        // Arrange
        $fallback = new MarkdownFallbackFormatter();

        // Act
        $text = $fallback->format(null);

        // Assert
        $this->assertSame('', $text);
    }

    public function testHeadingDepthCapsAtSix(): void
    {
        // Arrange
        $fallback = new MarkdownFallbackFormatter();

        // Act — 5 levels of nesting would produce ####### (7) without cap
        $text = $fallback->format([
            'a' => ['b' => ['c' => ['d' => ['e' => ['f' => 'deep']]]]],
        ]);

        // Assert — deepest heading should be ###### (6), not deeper
        $this->assertStringContainsString('###### e', $text);
        $this->assertStringNotContainsString('#######', $text);
    }
}
