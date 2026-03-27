<?php

declare(strict_types=1);

namespace Arcanum\Test\Parchment;

use Arcanum\Parchment\Writer;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Writer::class)]
final class WriterTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/arcanum_writer_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    // -----------------------------------------------------------
    // write()
    // -----------------------------------------------------------

    public function testWriteCreatesFileWithContents(): void
    {
        // Arrange
        $writer = new Writer();
        $path = $this->tempDir . '/test.txt';

        // Act
        $writer->write($path, 'hello world');

        // Assert
        $this->assertFileExists($path);
        $this->assertSame('hello world', file_get_contents($path));
    }

    public function testWriteOverwritesExistingFile(): void
    {
        // Arrange
        $writer = new Writer();
        $path = $this->tempDir . '/overwrite.txt';
        file_put_contents($path, 'old content');

        // Act
        $writer->write($path, 'new content');

        // Assert
        $this->assertSame('new content', file_get_contents($path));
    }

    public function testWriteCreatesDirectoryIfNotExists(): void
    {
        // Arrange
        $writer = new Writer();
        $path = $this->tempDir . '/nested/deep/file.txt';

        // Act
        $writer->write($path, 'nested content');

        // Assert
        $this->assertFileExists($path);
        $this->assertSame('nested content', file_get_contents($path));
    }

    public function testWriteHandlesEmptyString(): void
    {
        // Arrange
        $writer = new Writer();
        $path = $this->tempDir . '/empty.txt';

        // Act
        $writer->write($path, '');

        // Assert
        $this->assertFileExists($path);
        $this->assertSame('', file_get_contents($path));
    }

    // -----------------------------------------------------------
    // append()
    // -----------------------------------------------------------

    public function testAppendAddsToExistingFile(): void
    {
        // Arrange
        $writer = new Writer();
        $path = $this->tempDir . '/append.txt';
        file_put_contents($path, 'first');

        // Act
        $writer->append($path, ' second');

        // Assert
        $this->assertSame('first second', file_get_contents($path));
    }

    public function testAppendCreatesFileIfNotExists(): void
    {
        // Arrange
        $writer = new Writer();
        $path = $this->tempDir . '/new_append.txt';

        // Act
        $writer->append($path, 'content');

        // Assert
        $this->assertFileExists($path);
        $this->assertSame('content', file_get_contents($path));
    }

    public function testAppendCreatesDirectoryIfNotExists(): void
    {
        // Arrange
        $writer = new Writer();
        $path = $this->tempDir . '/nested/append.txt';

        // Act
        $writer->append($path, 'nested');

        // Assert
        $this->assertFileExists($path);
        $this->assertSame('nested', file_get_contents($path));
    }

    public function testMultipleAppendsAccumulate(): void
    {
        // Arrange
        $writer = new Writer();
        $path = $this->tempDir . '/multi.txt';

        // Act
        $writer->append($path, "line1\n");
        $writer->append($path, "line2\n");
        $writer->append($path, "line3\n");

        // Assert
        $this->assertSame("line1\nline2\nline3\n", file_get_contents($path));
    }

    // -----------------------------------------------------------
    // json()
    // -----------------------------------------------------------

    public function testJsonWritesEncodedData(): void
    {
        // Arrange
        $writer = new Writer();
        $path = $this->tempDir . '/data.json';
        $data = ['name' => 'Arcanum', 'version' => 1];

        // Act
        $writer->json($path, $data);

        // Assert
        $contents = file_get_contents($path);
        $this->assertIsString($contents);
        $decoded = json_decode($contents, true);
        $this->assertSame($data, $decoded);
    }

    public function testJsonUsesUnescapedSlashesByDefault(): void
    {
        // Arrange
        $writer = new Writer();
        $path = $this->tempDir . '/slashes.json';

        // Act
        $writer->json($path, ['url' => 'http://example.com/path']);

        // Assert
        $contents = file_get_contents($path);
        $this->assertIsString($contents);
        $this->assertStringContainsString('http://example.com/path', $contents);
        $this->assertStringNotContainsString('\\/', $contents);
    }

    public function testJsonUsesPrettyPrintByDefault(): void
    {
        // Arrange
        $writer = new Writer();
        $path = $this->tempDir . '/pretty.json';

        // Act
        $writer->json($path, ['key' => 'value']);

        // Assert
        $contents = file_get_contents($path);
        $this->assertIsString($contents);
        $this->assertStringContainsString("\n", $contents);
    }

    public function testJsonAcceptsCustomFlags(): void
    {
        // Arrange
        $writer = new Writer();
        $path = $this->tempDir . '/compact.json';

        // Act — no pretty print
        $writer->json($path, ['key' => 'value'], 0);

        // Assert
        $contents = file_get_contents($path);
        $this->assertIsString($contents);
        // Compact JSON + trailing newline
        $this->assertSame('{"key":"value"}' . \PHP_EOL, $contents);
    }

    public function testJsonThrowsForUnencodableData(): void
    {
        // Arrange
        $writer = new Writer();
        $path = $this->tempDir . '/bad.json';

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to encode JSON');

        // Act — INF is not JSON-encodable
        $writer->json($path, ['value' => \INF]);
    }

    public function testJsonAppendsTrailingNewline(): void
    {
        // Arrange
        $writer = new Writer();
        $path = $this->tempDir . '/newline.json';

        // Act
        $writer->json($path, ['key' => 'value']);

        // Assert
        $contents = file_get_contents($path);
        $this->assertIsString($contents);
        $this->assertStringEndsWith(\PHP_EOL, $contents);
    }

    // -----------------------------------------------------------
    // IOException error paths
    // -----------------------------------------------------------

    public function testWriteThrowsRuntimeExceptionOnIOException(): void
    {
        // Arrange
        $fs = $this->createMock(Filesystem::class);
        $fs->expects($this->once())
            ->method('dumpFile')
            ->willThrowException(new IOException('disk full'));

        $writer = new Writer($fs);

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to write file: /some/path');

        // Act
        $writer->write('/some/path', 'content');
    }

    public function testAppendThrowsRuntimeExceptionOnIOException(): void
    {
        // Arrange
        $fs = $this->createMock(Filesystem::class);
        $fs->expects($this->once())
            ->method('appendToFile')
            ->willThrowException(new IOException('permission denied'));

        $writer = new Writer($fs);

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to append to file: /some/path');

        // Act
        $writer->append('/some/path', 'content');
    }
}
