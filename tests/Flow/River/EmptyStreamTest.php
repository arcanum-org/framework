<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\River;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Arcanum\Flow\River\EmptyStream;

#[CoversClass(EmptyStream::class)]
final class EmptyStreamTest extends TestCase
{
    public function testDetach(): void
    {
        // Arrange
        $stream = new EmptyStream();

        // Act
        $result = $stream->detach();

        // Assert
        $this->assertNull($result);
    }

    public function testIsReadable(): void
    {
        // Arrange
        $stream = new EmptyStream();

        // Act
        $result = $stream->isReadable();

        // Assert
        $this->assertTrue($result);
    }

    public function testIsWritable(): void
    {
        // Arrange
        $stream = new EmptyStream();

        // Act
        $result = $stream->isWritable();

        // Assert
        $this->assertFalse($result);
    }

    public function testIsSeekable(): void
    {
        // Arrange
        $stream = new EmptyStream();

        // Act
        $result = $stream->isSeekable();

        // Assert
        $this->assertFalse($result);
    }

    public function testEOF(): void
    {
        // Arrange
        $stream = new EmptyStream();

        // Act
        $result = $stream->eof();

        // Assert
        $this->assertTrue($result);
    }

    public function testSeekAndTell(): void
    {
        // Arrange
        $stream = new EmptyStream();

        // Act
        $stream->seek(100);

        // Assert
        $this->assertSame(0, $stream->tell());
    }

    public function testRewind(): void
    {
        // Arrange
        $stream = new EmptyStream();

        // Act
        $stream->rewind();

        // Assert
        $this->assertSame(0, $stream->tell());
    }

    public function testGetMetadata(): void
    {
        // Arrange
        $stream = new EmptyStream();

        // Act
        $result = $stream->getMetadata();

        // Assert
        $this->assertSame([
            'timed_out' => false,
            'blocked' => false,
            'eof' => true,
            'unread_bytes' => 0,
            'stream_type' => 'EMPTY',
            'wrapper_type' => 'EMPTY',
            'wrapper_data' =>  null,
            'mode' => 'rb',
            'seekable' => false,
            'uri' => ''
        ], $result);
    }

    public function testGetMetaDataByKeyReturnsValue(): void
    {
        // Arrange
        $stream = new EmptyStream();

        // Act & Assert
        $this->assertFalse($stream->getMetadata('timed_out'));
        $this->assertTrue($stream->getMetadata('eof'));
        $this->assertSame(0, $stream->getMetadata('unread_bytes'));
        $this->assertSame('EMPTY', $stream->getMetadata('stream_type'));
        $this->assertSame('rb', $stream->getMetadata('mode'));
        $this->assertSame('', $stream->getMetadata('uri'));
    }

    public function testGetMetaDataByOptionalKeyReturnsNull(): void
    {
        // Arrange
        $stream = new EmptyStream();

        // Act — 'crypto' is an optional metadata key not set on EmptyStream
        $result = $stream->getMetadata('crypto');

        // Assert
        $this->assertNull($result);
    }

    public function testGetContents(): void
    {
        // Arrange
        $stream = new EmptyStream();

        // Act
        $result = $stream->getContents();

        // Assert
        $this->assertSame('', $result);
    }

    public function testToString(): void
    {
        // Arrange
        $stream = new EmptyStream();

        // Act
        $result = (string) $stream;

        // Assert
        $this->assertSame('', $result);
    }

    public function testGetSize(): void
    {
        // Arrange
        $stream = new EmptyStream();

        // Act
        $result = $stream->getSize();

        // Assert
        $this->assertSame(0, $result);
    }

    public function testRead(): void
    {
        // Arrange
        $stream = new EmptyStream();

        // Act
        $result = $stream->read(100);

        // Assert
        $this->assertSame('', $result);
    }

    public function testWrite(): void
    {
        // Arrange
        $stream = new EmptyStream();

        // Act
        $result = $stream->write('test');

        // Assert
        $this->assertSame(0, $result);
    }

    public function testCopyTo(): void
    {
        // Arrange
        $stream = new EmptyStream();

        // Act
        $stream->copyTo(new EmptyStream());

        // Assert
        $this->expectNotToPerformAssertions();
    }

    public function testClose(): void
    {
        // Arrange
        $stream = new EmptyStream();

        // Act
        $stream->close();

        // Assert
        $this->expectNotToPerformAssertions();
    }
}
