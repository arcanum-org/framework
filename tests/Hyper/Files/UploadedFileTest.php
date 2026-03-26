<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper\Files;

use Arcanum\Hyper\Files\UploadedFile;
use Arcanum\Hyper\Files\Error;
use Arcanum\Hyper\Files\InvalidFile;
use Arcanum\Hyper\Files\Normalizer;
use Arcanum\Flow\River\StreamResource;
use Arcanum\Flow\River\Stream;
use Arcanum\Flow\River\LazyResource;
use Arcanum\Flow\River\Bank;
use Arcanum\Gather\Registry;
use Psr\Http\Message\StreamInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(UploadedFile::class)]
#[CoversClass(Normalizer::class)]
#[UsesClass(Error::class)]
#[UsesClass(Stream::class)]
#[UsesClass(StreamResource::class)]
#[UsesClass(InvalidFile::class)]
#[UsesClass(LazyResource::class)]
#[UsesClass(Bank::class)]
#[UsesClass(Registry::class)]
final class UploadedFileTest extends TestCase
{
    public function testUploadedFile(): void
    {
        // Arrange
        $uploadedFile = new UploadedFile(
            file: '',
            mode: '',
            clientFilename: 'testFilename',
            clientMediaType: 'testType',
            size: 123,
        );

        // Act
        $err = $uploadedFile->getError();
        $clientFilename = $uploadedFile->getClientFilename();
        $clientMediaType = $uploadedFile->getClientMediaType();
        $size = $uploadedFile->getSize();

        // Assert
        $this->assertSame(\UPLOAD_ERR_OK, $err);
        $this->assertSame('testFilename', $clientFilename);
        $this->assertSame('testType', $clientMediaType);
        $this->assertSame(123, $size);
    }

    public function testUploadedFileWithStreamInterface(): void
    {
        // Arrange
        $stream = $this->createStub(StreamInterface::class);
        $uploadedFile = new UploadedFile(
            file: $stream,
            mode: 'w+',
        );

        // Act
        $result = $uploadedFile->getStream();

        // Assert
        $this->assertSame($stream, $result);
    }

    public function testUploadedFileWithStreamResource(): void
    {
        // Arrange
        $resource = fopen('php://memory', 'w+');
        $streamResource = $this->getMockBuilder(StreamResource::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['export', 'isResource', 'streamGetMetaData'])
            ->getMock();

        $streamResource->expects($this->once())
            ->method('export')
            ->willReturn($resource);

        $streamResource->expects($this->once())
            ->method('isResource')
            ->willReturn(true);

        $streamResource->expects($this->once())
            ->method('streamGetMetaData')
            ->willReturn([
                'seekable' => true,
                'uri' => 'php://memory',
                'mode' => 'w+'
            ]);

        $uploadedFile = new UploadedFile(
            file: $streamResource,
            mode: 'w+',
        );

        // Act
        $result = $uploadedFile->getStream()->detach();

        // Assert
        $this->assertSame($resource, $result);
    }

    public function testUploadedFileWithNativeResource(): void
    {
        // Arrange
        $resource = fopen('php://memory', 'w+');
        if ($resource === false) {
            $this->fail('Failed to open memory resource');
        }
        $uploadedFile = new UploadedFile(
            file: $resource,
            mode: 'w+',
        );

        // Act
        $result = $uploadedFile->getStream()->detach();

        // Assert
        $this->assertSame($resource, $result);
    }

    public function testGetStreamThrowsInvalidFileIfErrorIsNotOK(): void
    {
        // Arrange
        $uploadedFile = new UploadedFile(
            file: '',
            mode: '',
            error: Error::UPLOAD_ERR_NO_FILE,
        );

        // Assert
        $this->expectException(InvalidFile::class);

        // Act
        $uploadedFile->getStream();
    }

    public function testGetStremWillReturnStreamIfFileIsStringAndErrorIsOK(): void
    {
        // Arrange
        $uploadedFile = new UploadedFile(
            file: \tempnam(\sys_get_temp_dir(), 'test.txt') ?: '',
            mode: 'w+',
            error: Error::UPLOAD_ERR_OK,
        );

        // Act
        $result = $uploadedFile->getStream();

        // Assert
        $this->assertInstanceOf(Stream::class, $result);
    }

    public function testGetStreamThrowsInvalidFileIfErrorIsOKAndFileIsNull(): void
    {
        // Arrange
        $uploadedFile = new UploadedFile(
            file: null,
            mode: 'w+',
            error: Error::UPLOAD_ERR_OK,
        );

        // Assert
        $this->expectException(InvalidFile::class);

        // Act
        $uploadedFile->getStream();
    }

    public function testFromSimpleSpec(): void
    {
        // Arrange
        // $_FILES fixture from https://www.php-fig.org/psr/psr-7/ spec
        $files = [
            'avatar' => [
                'tmp_name' => 'phpUxcOty',
                'name' => 'my-avatar.png',
                'size' => 90996,
                'type' => 'image/png',
                'error' => 0,
            ]
        ];

        // Act
        $result = Normalizer::fromSpec(
            tmpName: $files['avatar']['tmp_name'],
            size: $files['avatar']['size'],
            error: $files['avatar']['error'],
            clientFilename: $files['avatar']['name'],
            clientMediaType: $files['avatar']['type'],
        );

        // Assert
        $this->assertInstanceOf(UploadedFile::class, $result);
    }

    public function testMoveToThrowsInvalidFileIfTargetPathIsEmpty(): void
    {
        // Arrange
        $uploadedFile = new UploadedFile(
            file: '/tmp-file',
            mode: 'w+',
            error: Error::UPLOAD_ERR_OK,
        );

        // Assert
        $this->expectException(InvalidFile::class);

        // Act
        $uploadedFile->moveTo(''); /** @phpstan-ignore argument.type */
    }

    public function testMoveToThrowsInvalidFileIfErrorIsNotOK(): void
    {
        // Arrange
        $uploadedFile = new UploadedFile(
            file: '/tmp-file',
            mode: 'w+',
            error: Error::UPLOAD_ERR_NO_FILE,
        );

        // Assert
        $this->expectException(InvalidFile::class);

        // Act
        $uploadedFile->moveTo('/tmp-file-2');
    }

    public function testMoveToOnStreamInterface(): void
    {
        // Arrange
        $target = \sys_get_temp_dir() . '/phpunit-target';
        $stream = $this->getMockBuilder(StreamInterface::class)
            ->getMock();
        $stream->method('isReadable')->willReturn(true);
        $stream->method('isSeekable')->willReturn(true);

        $stream->expects($this->exactly(3))
            ->method('eof')
            ->willReturnOnConsecutiveCalls(false, true, true);

        $stream->method('read')->willReturn('test');
        $stream->method('getSize')->willReturn(4);

        $uploadedFile = new UploadedFile(
            file: $stream,
            mode: 'w+',
            error: Error::UPLOAD_ERR_OK,
        );

        // Act
        $uploadedFile->moveTo($target);

        // Assert
        $this->assertFileExists($target);
        $this->assertSame('test', file_get_contents($target));

        // Cleanup
        unlink($target);
    }

    public function testMoveToWithFilePath(): void
    {
        // Arrange
        $source = \tempnam(\sys_get_temp_dir(), 'phpunit-src');
        $this->assertNotFalse($source);
        file_put_contents($source, 'hello');
        $target = \sys_get_temp_dir() . '/phpunit-native-move-target';

        $uploadedFile = new UploadedFile(
            file: $source,
            mode: 'r',
            error: Error::UPLOAD_ERR_OK,
        );

        // Act
        $uploadedFile->moveTo($target);

        // Assert
        $this->assertFileExists($target);
        $this->assertSame('hello', file_get_contents($target));

        // Cleanup
        unlink($target);
    }

    public function testMoveToThrowsInvalidFileIfAlreadyMoved(): void
    {
        // Arrange
        $source = \tempnam(\sys_get_temp_dir(), 'phpunit-src');
        $this->assertNotFalse($source);
        file_put_contents($source, 'hello');
        $target = \sys_get_temp_dir() . '/phpunit-already-moved-target';

        $uploadedFile = new UploadedFile(
            file: $source,
            mode: 'r',
            error: Error::UPLOAD_ERR_OK,
        );

        $uploadedFile->moveTo($target);

        // Assert
        $this->expectException(InvalidFile::class);

        // Act
        $uploadedFile->moveTo($target);
    }

    public function testMarshallFileThrowsInvalidFileForInvalidType(): void
    {
        // Assert
        $this->expectException(InvalidFile::class);

        // Act
        new UploadedFile(
            file: 12345, /** @phpstan-ignore argument.type */
            mode: 'r',
            error: Error::UPLOAD_ERR_OK,
        );
    }

    public function testMoveToThrowsRuntimeExceptionIfThingsGoHaywire(): void
    {
        // Arrange
        $target = \sys_get_temp_dir() . '/phpunit-target';
        $stream = $this->getMockBuilder(StreamInterface::class)
            ->getMock();
        $stream->method('isReadable')->willReturn(true);
        $stream->method('isSeekable')->willReturn(true);

        $stream->expects($this->exactly(3))
            ->method('eof')
            ->willReturnOnConsecutiveCalls(false, true, false);

        $stream->method('read')->willReturn('test');
        $stream->method('getSize')->willReturn(4);

        $uploadedFile = new UploadedFile(
            file: $stream,
            mode: 'w+',
            error: Error::UPLOAD_ERR_OK,
        );

        // Assert
        $this->expectException(\RuntimeException::class);

        // Act
        $uploadedFile->moveTo($target);
    }
}
