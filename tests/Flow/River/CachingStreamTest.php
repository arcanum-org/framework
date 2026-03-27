<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\River;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Arcanum\Flow\River\CachingStream;
use Arcanum\Flow\River\TemporaryStream;
use Arcanum\Flow\River\Stream;
use Arcanum\Flow\River\StreamResource;
use Arcanum\Gather\Registry;

#[CoversClass(CachingStream::class)]
#[UsesClass(TemporaryStream::class)]
#[UsesClass(Stream::class)]
#[UsesClass(StreamResource::class)]
#[UsesClass(Registry::class)]
final class CachingStreamTest extends TestCase
{
    public function testFromStream(): void
    {
        // Arrange
        $stream = $this->createStub(Stream::class);

        // Act
        $streamInterface = CachingStream::fromStream($stream);

        // Assert
        $this->assertInstanceOf(CachingStream::class, $streamInterface);
    }

    public function testFromStreamWithCache(): void
    {
        // Arrange
        $stream = $this->createStub(Stream::class);

        $cache = $this->createStub(Stream::class);

        // Act
        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Assert
        $this->assertInstanceOf(CachingStream::class, $streamInterface);
    }

    public function testDetach(): void
    {
        // Arrange
        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['detach'])
            ->getMock();

        $stream->expects($this->exactly(3))
            ->method('detach')
            ->willReturn(null);

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['detach'])
            ->getMock();

        $cache->expects($this->exactly(3))
            ->method('detach')
            ->willReturnOnConsecutiveCalls(fopen('php://memory', 'r'), null, null);

        // Act
        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Act

        $result = $streamInterface->detach();
        $again = $streamInterface->detach();

        $this->assertTrue(is_resource($result));
        $this->assertFalse(is_resource($again));
        $this->assertNull($streamInterface->detach());
    }

    public function testClose(): void
    {
        // Arrange
        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['close'])
            ->getMock();

        $stream->expects($this->once())
            ->method('close');

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['close'])
            ->getMock();

        $cache->expects($this->once())
            ->method('close');

        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Act
        $streamInterface->close();
    }

    public function testIsReadable(): void
    {
        // Arrange
        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isReadable'])
            ->getMock();

        $stream->expects($this->never())
            ->method('isReadable');

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isReadable'])
            ->getMock();

        $cache->expects($this->once())
            ->method('isReadable')
            ->willReturn(true);

        // Act
        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Act
        $result = $streamInterface->isReadable();

        // Assert
        $this->assertTrue($result);
    }

    public function testIsWritable(): void
    {
        // Arrange
        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isWritable'])
            ->getMock();

        $stream->expects($this->never())
            ->method('isWritable');

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isWritable'])
            ->getMock();

        $cache->expects($this->once())
            ->method('isWritable')
            ->willReturn(true);

        // Act
        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Act
        $result = $streamInterface->isWritable();

        // Assert
        $this->assertTrue($result);
    }

    public function testIsSeekable(): void
    {
        // Arrange
        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isSeekable'])
            ->getMock();

        $stream->expects($this->never())
            ->method('isSeekable');

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isSeekable'])
            ->getMock();

        $cache->expects($this->once())
            ->method('isSeekable')
            ->willReturn(true);

        // Act
        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Act
        $result = $streamInterface->isSeekable();

        // Assert
        $this->assertTrue($result);
    }

    public function testGetSize(): void
    {
        // Arrange
        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSize'])
            ->getMock();

        $stream->expects($this->once())
            ->method('getSize')
            ->willReturn(100);

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSize'])
            ->getMock();

        $cache->expects($this->once())
            ->method('getSize')
            ->willReturn(75);

        // Act
        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Act
        $result = $streamInterface->getSize();

        // Assert
        $this->assertSame(100, $result);
    }

    public function testGetSizeReturnsNullIfStreamReturnsNull(): void
    {
        // Arrange
        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSize'])
            ->getMock();

        $stream->expects($this->once())
            ->method('getSize')
            ->willReturn(null);

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSize'])
            ->getMock();

        $cache->expects($this->never())
            ->method('getSize');

        // Act
        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Act
        $result = $streamInterface->getSize();

        // Assert
        $this->assertNull($result);
    }

    public function testGetSizeReturnsNullIfCacheReturnsNull(): void
    {
                // Arrange
        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSize'])
            ->getMock();

        $stream->expects($this->once())
            ->method('getSize')
            ->willReturn(100);

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSize'])
            ->getMock();

        $cache->expects($this->once())
            ->method('getSize')
            ->willReturn(null);

        // Act
        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Act
        $result = $streamInterface->getSize();

        // Assert
        $this->assertNull($result);
    }

    public function testEOF(): void
    {
        // Arrange
        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['eof'])
            ->getMock();

        $stream->expects($this->once())
            ->method('eof')
            ->willReturn(true);

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['eof'])
            ->getMock();

        $cache->expects($this->once())
            ->method('eof')
            ->willReturn(true);

        // Act
        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Act
        $result = $streamInterface->eof();

        // Assert
        $this->assertTrue($result);
    }

    public function testTell(): void
    {
        // Arrange
        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['tell'])
            ->getMock();

        $stream->expects($this->never())
            ->method('tell');

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['tell'])
            ->getMock();

        $cache->expects($this->once())
            ->method('tell')
            ->willReturn(10);

        // Act
        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Act
        $result = $streamInterface->tell();

        // Assert
        $this->assertSame(10, $result);
    }

    public function testGetMetadata(): void
    {
        // Arrange
        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMetaData'])
            ->getMock();

        $stream->expects($this->never())
            ->method('getMetaData');

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMetaData'])
            ->getMock();

        $cache->expects($this->once())
            ->method('getMetaData')
            ->with(null)
            ->willReturn([]);

        // Act
        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Act
        $result = $streamInterface->getMetaData();

        // Assert
        $this->assertIsArray($result);
    }

    public function testGetMetaDataByKey(): void
    {
        // Arrange
        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMetaData'])
            ->getMock();

        $stream->expects($this->never())
            ->method('getMetaData');

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMetaData'])
            ->getMock();

        $cache->expects($this->once())
            ->method('getMetaData')
            ->with('eof')
            ->willReturn(true);

        // Act
        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Act
        $result = $streamInterface->getMetaData('eof');

        // Assert
        $this->assertTrue($result);
    }

    public function testGetContents(): void
    {
        // Arrange
        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getContents'])
            ->getMock();

        $stream->expects($this->never())
            ->method('getContents');

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getContents'])
            ->getMock();

        $cache->expects($this->once())
            ->method('getContents')
            ->willReturn('some content');

        // Act
        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Act
        $result = $streamInterface->getContents();

        // Assert
        $this->assertSame('some content', $result);
    }

    public function testToString(): void
    {
        // Arrange
        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getContents'])
            ->getMock();

        $stream->expects($this->never())
            ->method('getContents');

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getContents'])
            ->getMock();

        $cache->expects($this->once())
            ->method('getContents')
            ->willReturn('some content');

        // Act
        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Act
        $result = (string)$streamInterface;

        // Assert
        $this->assertSame('some content', $result);
    }

    public function testWrite(): void
    {
        // Arrange
        $content = 'some content';

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['tell', 'write'])
            ->getMock();

        $stream->expects($this->once())
            ->method('tell')
            ->willReturn(10);

        $stream->expects($this->never())
            ->method('write');

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['tell', 'write'])
            ->getMock();

        $cache->expects($this->once())
            ->method('write')
            ->with($content)
            ->willReturn(12);

        $cache->expects($this->once())
            ->method('tell')
            ->willReturn(100);

        // Act
        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Act
        $result = $streamInterface->write($content);

        // Assert
        $this->assertSame(12, $result);
    }

    public function testReadWithPrimedCache(): void
    {
        // Arrange
        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['read'])
            ->getMock();

        $stream->expects($this->never())
            ->method('read');

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['read'])
            ->getMock();

        $cache->expects($this->once())
            ->method('read')
            ->with(12)
            ->willReturn('some content');

        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Act
        $result = $streamInterface->read(12);

        // Assert
        $this->assertSame('some content', $result);
    }

    public function testReadWithSomeDataFromCacheAndRestFromRemoteStream(): void
    {
        // Arrange
        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['read'])
            ->getMock();

        $stream->expects($this->once())
            ->method('read')
            ->with(2)
            ->willReturn('nt');

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['read', 'write'])
            ->getMock();

        $cache->expects($this->once())
            ->method('read')
            ->with(12)
            ->willReturn('some conte');

        $cache->expects($this->once())
            ->method('write')
            ->with('nt');

        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Act
        $result = $streamInterface->read(12);

        // Assert
        $this->assertSame('some content', $result);
    }

    public function testReadAfterWritingSomeDataOverSourceButNotAll(): void
    {
        // Arrange
        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['read', 'write', 'tell'])
            ->getMock();

        $stream->expects($this->once())
            ->method('tell')
            ->willReturn(12);

        $streamReadMatcher = $this->exactly(2);
        $stream->expects($streamReadMatcher)
            ->method('read')
            ->willReturnCallback(function (int $bytes) use ($streamReadMatcher): string {
                switch ($streamReadMatcher->numberOfInvocations()) {
                    case 1:
                        $this->assertSame(12, $bytes);
                        return 'Hello World!';
                    case 2:
                        $this->assertSame(27, $bytes);
                        return ' 1 123456 1234 123 Horatio.';
                    default:
                        $this->fail('Unexpected invocation of read()');
                }
            });

        $stream->expects($this->never())
            ->method('write');

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['read', 'write', 'getSize', 'seek', 'tell'])
            ->getMock();

        $cache->expects($this->once())
            ->method('tell')
            ->willReturn(12);

        $cacheReadMatcher = $this->exactly(2);
        $cache->expects($cacheReadMatcher)
            ->method('read')
            ->willReturnCallback(function (int $data) use ($cacheReadMatcher): string {
                switch ($cacheReadMatcher->numberOfInvocations()) {
                    case 1:
                        $this->assertSame(12, $data);
                        return '';
                    case 2:
                        $this->assertSame(39, $data);
                        return 'Hello World! I barely Knew Ye.';
                    default:
                        $this->fail('Unexpected invocation of read()');
                }
            });


        $cacheWriteMatcher = $this->exactly(3);
        $cache->expects($cacheWriteMatcher)
            ->method('write')
            ->willReturnCallback(function (string $data) use ($cacheWriteMatcher): int {
                switch ($cacheWriteMatcher->numberOfInvocations()) {
                    case 1:
                        $this->assertSame('Hello World!', $data);
                        return 12;
                    case 2:
                        $this->assertSame(' I barely Knew Ye.', $data);
                        return 18;
                    case 3:
                        $this->assertSame(' Horatio.', $data);
                        return 9;
                    default:
                        $this->fail('Unexpected invocation count for write()');
                }
            });

        $cache->expects($this->once())
            ->method('getSize')
            ->willReturn(30);

        $cache->expects($this->once())
            ->method('seek')
            ->with(0);

        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Act
        $original = $streamInterface->read(12);

        $streamInterface->write(' I barely Knew Ye.');

        $streamInterface->seek(0);

        $result = $streamInterface->read(39);


        // Assert
        $this->assertSame('Hello World!', $original);
        $this->assertSame('Hello World! I barely Knew Ye. Horatio.', $result);
    }

    public function testSeekWithCache(): void
    {
        // Arrange
        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['seek'])
            ->getMock();

        $stream->expects($this->never())
            ->method('seek');

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['seek', 'getSize'])
            ->getMock();

        $cache->expects($this->once())
            ->method('seek')
            ->with(10, SEEK_SET);

        $cache->expects($this->once())
            ->method('getSize')
            ->willReturn(100);

        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Act
        $streamInterface->seek(10);
    }

    public function testRewind(): void
    {
        // Arrange
        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['seek'])
            ->getMock();

        $stream->expects($this->never())
            ->method('seek');

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['seek', 'getSize'])
            ->getMock();

        $cache->expects($this->once())
            ->method('seek')
            ->with(0, SEEK_SET);

        $cache->expects($this->once())
            ->method('getSize')
            ->willReturn(100);

        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Act
        $streamInterface->rewind();
    }

    public function testSeekWithSeekCur(): void
    {
        // Arrange
        $stream = $this->createStub(Stream::class);

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['tell', 'getSize', 'seek'])
            ->getMock();

        $cache->expects($this->once())
            ->method('tell')
            ->willReturn(10);

        $cache->expects($this->once())
            ->method('getSize')
            ->willReturn(100);

        $cache->expects($this->once())
            ->method('seek')
            ->with(15, SEEK_SET);

        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Act — seek 5 bytes forward from current position (10)
        $streamInterface->seek(5, SEEK_CUR);
    }

    public function testSeekWithSeekEnd(): void
    {
        // Arrange
        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSize'])
            ->getMock();

        $stream->expects($this->once())
            ->method('getSize')
            ->willReturn(100);

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSize', 'seek'])
            ->getMock();

        $cache->expects($this->once())
            ->method('getSize')
            ->willReturn(100);

        $cache->expects($this->once())
            ->method('seek')
            ->with(90, SEEK_SET);

        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Act — seek 10 bytes back from end (size 100)
        $streamInterface->seek(-10, SEEK_END);
    }

    public function testSeekWithInvalidWhenceThrows(): void
    {
        // Arrange
        $stream = $this->createStub(Stream::class);

        $cache = $this->createStub(Stream::class);

        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $streamInterface->seek(0, 999);
    }

    public function testSeekFirstThenReadWillStillCacheFromStream(): void
    {
        // Arrange
        $streamData = '1234567890Hello World!';

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['eof', 'read'])
            ->getMock();

        $stream->expects($this->once())
            ->method('eof')
            ->willReturn(false);

        $streamReadMatcher = $this->exactly(2);
        $stream->expects($streamReadMatcher)
            ->method('read')
            ->willReturnCallback(function (int $bytes) use ($streamData, $streamReadMatcher): string {
                switch ($streamReadMatcher->numberOfInvocations()) {
                    case 1:
                        $this->assertSame(10, $bytes);
                        return substr($streamData, 0, 10);
                    case 2:
                        $this->assertSame(12, $bytes);
                        return substr($streamData, 10, 12);
                    default:
                        $this->fail('Unexpected invocation of read()');
                }
            });

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['seek', 'read', 'write', 'getSize'])
            ->getMock();

        $cacheGetSizeMatcher = $this->exactly(3);
        $cache->expects($cacheGetSizeMatcher)
            ->method('getSize')
            ->willReturnCallback(function () use ($cacheGetSizeMatcher): int {
                switch ($cacheGetSizeMatcher->numberOfInvocations()) {
                    case 1:
                        return 0;
                    case 2:
                        return 22;
                    case 3:
                        return 22;
                    default:
                        $this->fail('Unexpected invocation of getSize()');
                }
            });

        $cacheReadMatcher = $this->exactly(3);
        $cache->expects($cacheReadMatcher)
            ->method('read')
            ->willReturnCallback(function (int $data) use ($cacheReadMatcher, $streamData): string {
                switch ($cacheReadMatcher->numberOfInvocations()) {
                    case 1:
                        $this->assertSame(10, $data);
                        return '';
                    case 2:
                        $this->assertSame(12, $data);
                        return '';
                    case 3:
                        $this->assertSame(10, $data);
                        return substr($streamData, 0, 10);
                    default:
                        $this->fail('Unexpected invocation of read()');
                }
            });

        $cacheWriteMatcher = $this->exactly(2);
        $cache->expects($cacheWriteMatcher)
            ->method('write')
            ->willReturnCallback(function (string $data) use ($cacheWriteMatcher, $streamData): int {
                switch ($cacheWriteMatcher->numberOfInvocations()) {
                    case 1:
                        $this->assertSame(substr($streamData, 0, 10), $data);
                        return 10;
                    case 2:
                        $this->assertSame(substr($streamData, 10, 12), $data);
                        return 12;
                    default:
                        $this->fail('Unexpected invocation count for write()');
                }
            });

        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Act
        $streamInterface->seek(10);
        $result = $streamInterface->read(12);
        $streamInterface->seek(0);
        $second = $streamInterface->read(10);

        // Assert
        $this->assertSame('Hello World!', $result);
        $this->assertSame('1234567890', $second);
    }

    public function testCopyTo(): void
    {
        // Arrange
        $streamData = '1234567890Hello World!';

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['eof', 'read'])
            ->getMock();

        $stream->expects($this->exactly(2))
            ->method('eof')
            ->willReturnOnConsecutiveCalls(false, true);

        $stream->expects($this->once())
            ->method('read')
            ->with(8192)
            ->willReturn($streamData);

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['read', 'write'])
            ->getMock();

        $cache->expects($this->once())
            ->method('read')
            ->with(8192)
            ->willReturn('');

        $cache->expects($this->once())
            ->method('write')
            ->with($streamData)
            ->willReturn(strlen($streamData));

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $target = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['write'])
            ->getMock();

        $target->expects($this->once())
            ->method('write')
            ->with($streamData)
            ->willReturn(strlen($streamData));

        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Assert
        $this->assertInstanceOf(CachingStream::class, $streamInterface);

        // Act
        /** @var CachingStream $streamInterface */
        $streamInterface->copyTo($target);
    }

    public function testCopyToIsSilentIfWriteFails(): void
    {
        // Arrange
        $streamData = '1234567890Hello World!';

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['eof', 'read'])
            ->getMock();

        $stream->expects($this->once())
            ->method('eof')
            ->willReturn(false);

        $stream->expects($this->once())
            ->method('read')
            ->with(8192)
            ->willReturn($streamData);

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['read', 'write'])
            ->getMock();

        $cache->expects($this->once())
            ->method('read')
            ->with(8192)
            ->willReturn('');

        $cache->expects($this->once())
            ->method('write')
            ->with($streamData)
            ->willReturn(strlen($streamData));

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $target = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['write'])
            ->getMock();

        $target->expects($this->once())
            ->method('write')
            ->with($streamData)
            ->willReturn(0);

        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Assert
        $this->assertInstanceOf(CachingStream::class, $streamInterface);

        // Act
        /** @var CachingStream $streamInterface */
        $streamInterface->copyTo($target);
    }

    public function testConsumeEverything(): void
    {
        // Arrange
        $streamData = '1234567890Hello World!';

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['eof', 'read'])
            ->getMock();

        $stream->expects($this->exactly(2))
            ->method('eof')
            ->willReturnOnConsecutiveCalls(false, true);

        $stream->expects($this->once())
            ->method('read')
            ->with(8192)
            ->willReturn($streamData);

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['read', 'write', 'tell'])
            ->getMock();

        $cache->expects($this->once())
            ->method('read')
            ->with(8192)
            ->willReturn('');

        $cache->expects($this->once())
            ->method('write')
            ->with($streamData)
            ->willReturn(strlen($streamData));

        $cache->expects($this->once())
            ->method('tell')
            ->willReturn(strlen($streamData));

        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Assert
        $this->assertInstanceOf(CachingStream::class, $streamInterface);

        // Act
        /** @var CachingStream $streamInterface */
        $bytes = $streamInterface->consumeEverything();

        // Assert
        $this->assertSame(strlen($streamData), $bytes);
    }

    public function testConsumeEverythingThrowsIfOpeningResourceFails(): void
    {
        // Arrange
        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['read'])
            ->getMock();

        $stream->expects($this->never())
            ->method('read');

        /** @var Stream&\PHPUnit\Framework\MockObject\MockObject */
        $cache = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['read', 'write', 'tell'])
            ->getMock();

        $cache->expects($this->never())
            ->method('read');

        $cache->expects($this->never())
            ->method('write');

        $cache->expects($this->never())
            ->method('tell');

        $streamInterface = CachingStream::fromStreamWithCache($stream, $cache);

        // Assert
        $this->assertInstanceOf(CachingStream::class, $streamInterface);
        $this->expectException(\RuntimeException::class);

        // Act
        /** @var CachingStream $streamInterface */
        $streamInterface->consumeEverything(false);
    }
}
