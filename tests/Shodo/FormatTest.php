<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo;

use Arcanum\Hyper\JsonResponseRenderer;
use Arcanum\Shodo\Format;
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
            rendererClass: JsonResponseRenderer::class,
        );

        // Assert
        $this->assertSame('json', $format->extension);
        $this->assertSame('application/json', $format->contentType);
        $this->assertSame(JsonResponseRenderer::class, $format->rendererClass);
    }
}
