<?php

declare(strict_types=1);

namespace Arcanum\Test\Parchment;

use Arcanum\Parchment\TempFile;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TempFile::class)]
final class TempFileTest extends TestCase
{
    public function testCreatesFileOnConstruction(): void
    {
        // Act
        $temp = new TempFile();

        // Assert
        $this->assertFileExists($temp->path());
    }

    public function testCreatesFileInSpecifiedDirectory(): void
    {
        // Arrange
        $dir = realpath(sys_get_temp_dir());
        $this->assertIsString($dir);

        // Act
        $temp = new TempFile(directory: $dir);

        // Assert
        $this->assertStringStartsWith($dir, $temp->path());
    }

    public function testCreatesFileWithPrefix(): void
    {
        // Act
        $temp = new TempFile(prefix: 'myapp_');

        // Assert
        $this->assertStringContainsString('myapp_', basename($temp->path()));
    }

    public function testWriteAndRead(): void
    {
        // Arrange
        $temp = new TempFile();

        // Act
        $temp->write('hello world');
        $contents = $temp->read();

        // Assert
        $this->assertSame('hello world', $contents);
    }

    public function testWriteOverwritesPreviousContents(): void
    {
        // Arrange
        $temp = new TempFile();
        $temp->write('first');

        // Act
        $temp->write('second');

        // Assert
        $this->assertSame('second', $temp->read());
    }

    public function testDeleteRemovesFile(): void
    {
        // Arrange
        $temp = new TempFile();
        $path = $temp->path();
        $this->assertFileExists($path);

        // Act
        $temp->delete();

        // Assert
        $this->assertFileDoesNotExist($path);
    }

    public function testDeleteIsIdempotent(): void
    {
        // Arrange
        $temp = new TempFile();

        // Act — calling delete twice should not throw
        $temp->delete();
        $temp->delete();

        // Assert
        $this->assertFileDoesNotExist($temp->path());
    }

    public function testDestructorCleansUp(): void
    {
        // Arrange
        $temp = new TempFile();
        $path = $temp->path();
        $this->assertFileExists($path);

        // Act
        unset($temp);

        // Assert
        $this->assertFileDoesNotExist($path);
    }

    public function testReadEmptyFile(): void
    {
        // Arrange
        $temp = new TempFile();

        // Act
        $contents = $temp->read();

        // Assert
        $this->assertSame('', $contents);
    }

    public function testDefaultsToSystemTempDirectory(): void
    {
        // Arrange
        $sysTmp = realpath(sys_get_temp_dir());
        $this->assertIsString($sysTmp);

        // Act
        $temp = new TempFile();

        // Assert
        $this->assertStringStartsWith($sysTmp, realpath($temp->path()) ?: $temp->path());
    }
}
