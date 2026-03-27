<?php

declare(strict_types=1);

namespace Arcanum\Test\Parchment;

use Arcanum\Parchment\Reader;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Reader::class)]
final class ReaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/arcanum_reader_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tempDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    // -----------------------------------------------------------
    // read()
    // -----------------------------------------------------------

    public function testReadReturnsFileContents(): void
    {
        // Arrange
        $path = $this->tempDir . '/test.txt';
        file_put_contents($path, 'hello world');
        $reader = new Reader();

        // Act
        $contents = $reader->read($path);

        // Assert
        $this->assertSame('hello world', $contents);
    }

    public function testReadReturnsEmptyStringForEmptyFile(): void
    {
        // Arrange
        $path = $this->tempDir . '/empty.txt';
        file_put_contents($path, '');
        $reader = new Reader();

        // Act
        $contents = $reader->read($path);

        // Assert
        $this->assertSame('', $contents);
    }

    public function testReadThrowsForNonexistentFile(): void
    {
        // Arrange
        $reader = new Reader();

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read file');

        // Act
        $reader->read($this->tempDir . '/nonexistent.txt');
    }

    // -----------------------------------------------------------
    // lines()
    // -----------------------------------------------------------

    public function testLinesReturnsArrayOfLines(): void
    {
        // Arrange
        $path = $this->tempDir . '/lines.txt';
        file_put_contents($path, "line one\nline two\nline three");
        $reader = new Reader();

        // Act
        $lines = $reader->lines($path);

        // Assert
        $this->assertSame(['line one', 'line two', 'line three'], $lines);
    }

    public function testLinesStripsNewlines(): void
    {
        // Arrange
        $path = $this->tempDir . '/crlf.txt';
        file_put_contents($path, "first\r\nsecond\r\n");
        $reader = new Reader();

        // Act
        $lines = $reader->lines($path);

        // Assert
        $this->assertSame(['first', 'second'], $lines);
    }

    public function testLinesReturnsEmptyArrayForEmptyFile(): void
    {
        // Arrange
        $path = $this->tempDir . '/empty.txt';
        file_put_contents($path, '');
        $reader = new Reader();

        // Act
        $lines = $reader->lines($path);

        // Assert
        $this->assertSame([], $lines);
    }

    public function testLinesThrowsForNonexistentFile(): void
    {
        // Arrange
        $reader = new Reader();

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read file');

        // Act
        $reader->lines($this->tempDir . '/nonexistent.txt');
    }

    public function testLinesThrowsForDirectory(): void
    {
        // Arrange
        $reader = new Reader();

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is a directory');

        // Act
        $reader->lines($this->tempDir);
    }

    // -----------------------------------------------------------
    // require()
    // -----------------------------------------------------------

    public function testRequireReturnsFileResult(): void
    {
        // Arrange
        $path = $this->tempDir . '/config.php';
        file_put_contents($path, '<?php return ["key" => "value"];');
        $reader = new Reader();

        // Act
        $result = $reader->require($path);

        // Assert
        $this->assertSame(['key' => 'value'], $result);
    }

    public function testRequireThrowsForNonexistentFile(): void
    {
        // Arrange
        $reader = new Reader();

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to require file');

        // Act
        $reader->require($this->tempDir . '/nonexistent.php');
    }

    // -----------------------------------------------------------
    // json()
    // -----------------------------------------------------------

    public function testJsonDecodesFile(): void
    {
        // Arrange
        $path = $this->tempDir . '/data.json';
        file_put_contents($path, '{"name": "Arcanum", "version": 1}');
        $reader = new Reader();

        // Act
        $data = $reader->json($path);

        // Assert
        $this->assertSame(['name' => 'Arcanum', 'version' => 1], $data);
    }

    public function testJsonDecodesArray(): void
    {
        // Arrange
        $path = $this->tempDir . '/array.json';
        file_put_contents($path, '[1, 2, 3]');
        $reader = new Reader();

        // Act
        $data = $reader->json($path);

        // Assert
        $this->assertSame([1, 2, 3], $data);
    }

    public function testJsonThrowsForInvalidJson(): void
    {
        // Arrange
        $path = $this->tempDir . '/bad.json';
        file_put_contents($path, '{invalid json}');
        $reader = new Reader();

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON in file');

        // Act
        $reader->json($path);
    }

    public function testJsonThrowsForNonexistentFile(): void
    {
        // Arrange
        $reader = new Reader();

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read file');

        // Act
        $reader->json($this->tempDir . '/nonexistent.json');
    }

    // -----------------------------------------------------------
    // exists()
    // -----------------------------------------------------------

    public function testExistsReturnsTrueForExistingFile(): void
    {
        // Arrange
        $path = $this->tempDir . '/exists.txt';
        file_put_contents($path, 'content');
        $reader = new Reader();

        // Assert
        $this->assertTrue($reader->exists($path));
    }

    public function testExistsReturnsFalseForNonexistentFile(): void
    {
        // Arrange
        $reader = new Reader();

        // Assert
        $this->assertFalse($reader->exists($this->tempDir . '/nope.txt'));
    }

    public function testExistsReturnsFalseForDirectory(): void
    {
        // Arrange
        $reader = new Reader();

        // Assert
        $this->assertFalse($reader->exists($this->tempDir));
    }
}
