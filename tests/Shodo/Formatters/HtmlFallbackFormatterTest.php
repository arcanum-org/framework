<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo\Formatters;

use Arcanum\Shodo\Formatters\HtmlFallbackFormatter;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HtmlFallbackFormatter::class)]
final class HtmlFallbackFormatterTest extends TestCase
{
    public function testRendersScalarString(): void
    {
        // Arrange
        $fallback = new HtmlFallbackFormatter();

        // Act
        $html = $fallback->format('Hello world');

        // Assert
        $this->assertStringContainsString('<p>Hello world</p>', $html);
    }

    public function testRendersScalarInteger(): void
    {
        // Arrange
        $fallback = new HtmlFallbackFormatter();

        // Act
        $html = $fallback->format(42);

        // Assert
        $this->assertStringContainsString('<p>42</p>', $html);
    }

    public function testEscapesHtmlInScalar(): void
    {
        // Arrange
        $fallback = new HtmlFallbackFormatter();

        // Act
        $html = $fallback->format('<script>alert("xss")</script>');

        // Assert
        $this->assertStringContainsString(
            '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;',
            $html,
        );
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testRendersAssociativeArray(): void
    {
        // Arrange
        $fallback = new HtmlFallbackFormatter();

        // Act
        $html = $fallback->format(['name' => 'Arcanum', 'version' => '1.0']);

        // Assert
        $this->assertStringContainsString('<dl>', $html);
        $this->assertStringContainsString('<dt>name</dt>', $html);
        $this->assertStringContainsString('<dd><p>Arcanum</p></dd>', $html);
        $this->assertStringContainsString('<dt>version</dt>', $html);
        $this->assertStringContainsString('<dd><p>1.0</p></dd>', $html);
    }

    public function testRendersSequentialArray(): void
    {
        // Arrange
        $fallback = new HtmlFallbackFormatter();

        // Act
        $html = $fallback->format(['apple', 'banana', 'cherry']);

        // Assert
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li><p>apple</p></li>', $html);
        $this->assertStringContainsString('<li><p>banana</p></li>', $html);
        $this->assertStringContainsString('<li><p>cherry</p></li>', $html);
    }

    public function testRendersNestedStructure(): void
    {
        // Arrange
        $fallback = new HtmlFallbackFormatter();

        // Act
        $html = $fallback->format([
            'user' => [
                'name' => 'Alice',
                'roles' => ['admin', 'editor'],
            ],
        ]);

        // Assert
        $this->assertStringContainsString('<dt>user</dt>', $html);
        $this->assertStringContainsString('<dt>name</dt>', $html);
        $this->assertStringContainsString('<dd><p>Alice</p></dd>', $html);
        $this->assertStringContainsString('<dt>roles</dt>', $html);
        $this->assertStringContainsString('<li><p>admin</p></li>', $html);
        $this->assertStringContainsString('<li><p>editor</p></li>', $html);
    }

    public function testRendersObjectPublicProperties(): void
    {
        // Arrange
        $fallback = new HtmlFallbackFormatter();
        $obj = new class {
            public string $name = 'Arcanum';
            public int $version = 1;
        };

        // Act
        $html = $fallback->format($obj);

        // Assert
        $this->assertStringContainsString('<dt>name</dt>', $html);
        $this->assertStringContainsString('<dd><p>Arcanum</p></dd>', $html);
        $this->assertStringContainsString('<dt>version</dt>', $html);
        $this->assertStringContainsString('<dd><p>1</p></dd>', $html);
    }

    public function testRendersEmptyArray(): void
    {
        // Arrange
        $fallback = new HtmlFallbackFormatter();

        // Act
        $html = $fallback->format([]);

        // Assert
        $this->assertStringNotContainsString('<dl>', $html);
        $this->assertStringNotContainsString('<ul>', $html);
        $this->assertStringNotContainsString('<p>', $html);
    }

    public function testRendersNull(): void
    {
        // Arrange
        $fallback = new HtmlFallbackFormatter();

        // Act
        $html = $fallback->format(null);

        // Assert
        $this->assertStringNotContainsString('<dl>', $html);
        $this->assertStringNotContainsString('<ul>', $html);
    }

    public function testOutputIsValidHtmlDocument(): void
    {
        // Arrange
        $fallback = new HtmlFallbackFormatter();

        // Act
        $html = $fallback->format(['key' => 'value']);

        // Assert
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<html lang="en">', $html);
        $this->assertStringContainsString('<meta charset="UTF-8">', $html);
        $this->assertStringContainsString('</html>', $html);
    }

    public function testEscapesKeysInAssociativeArray(): void
    {
        // Arrange
        $fallback = new HtmlFallbackFormatter();

        // Act
        $html = $fallback->format(['<b>key</b>' => 'value']);

        // Assert
        $this->assertStringContainsString('<dt>&lt;b&gt;key&lt;/b&gt;</dt>', $html);
    }

    public function testRendersBooleanValues(): void
    {
        // Arrange
        $fallback = new HtmlFallbackFormatter();

        // Act
        $html = $fallback->format(['enabled' => true, 'disabled' => false]);

        // Assert
        $this->assertStringContainsString('<dd><p>1</p></dd>', $html);
        // false casts to empty string
        $this->assertStringContainsString('<dt>disabled</dt>', $html);
    }
}
