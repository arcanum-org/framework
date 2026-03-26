<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\River;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Arcanum\Flow\River\StreamResource;
use Arcanum\Flow\River\InvalidSource;

#[CoversClass(StreamResource::class)]
#[UsesClass(InvalidSource::class)]
final class StreamResourceTest extends TestCase
{
    public function testStreamResource(): void
    {
        // Arrange
        $resource = fopen('php://memory', 'r');
        if ($resource === false) {
            throw new \Exception('Could not open stream');
        }
        $streamResource = StreamResource::wrap($resource);

        // Act
        $isResource = $streamResource->isResource();

        // Assert
        $this->assertTrue($isResource);

        // Cleanup
        fclose($resource);
    }

    public function testWrapComplainsIfResourceIsClosed(): void
    {
        // Arrange
        $resource = fopen('php://memory', 'r');
        if ($resource === false) {
            throw new \Exception('Could not open stream');
        }
        fclose($resource);

        // Act
        $this->expectException(InvalidSource::class);
        $this->expectExceptionMessage('Stream source must be a live resource');

        StreamResource::wrap($resource);
    }

    public function testExportReturnsTheResource(): void
    {
        // Arrange
        $resource = fopen('php://memory', 'r');
        if ($resource === false) {
            throw new \Exception('Could not open stream');
        }
        $streamResource = StreamResource::wrap($resource);

        // Act
        $exportedResource = $streamResource->export();

        // Assert
        $this->assertSame($resource, $exportedResource);

        // Cleanup
        fclose($resource);
    }
}
