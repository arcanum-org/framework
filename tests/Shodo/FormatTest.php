<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo;

use Arcanum\Shodo\Format;
use Arcanum\Shodo\JsonRenderer;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Format::class)]
final class FormatTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        // Arrange & Act
        $format = new Format(
            extension: 'json',
            contentType: 'application/json',
            rendererClass: JsonRenderer::class,
        );

        // Assert
        $this->assertSame('json', $format->extension);
        $this->assertSame('application/json', $format->contentType);
        $this->assertSame(JsonRenderer::class, $format->rendererClass);
    }
}
