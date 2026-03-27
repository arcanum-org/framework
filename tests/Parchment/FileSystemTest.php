<?php

declare(strict_types=1);

namespace Arcanum\Test\Parchment;

use Arcanum\Parchment\FileSystem;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FileSystem::class)]
final class FileSystemTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/arcanum_fs_test_' . uniqid();
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
    // copy()
    // -----------------------------------------------------------

    public function testCopyCreatesNewFile(): void
    {
        // Arrange
        $fs = new FileSystem();
        $source = $this->tempDir . '/source.txt';
        $target = $this->tempDir . '/target.txt';
        file_put_contents($source, 'content');

        // Act
        $fs->copy($source, $target);

        // Assert
        $this->assertFileExists($target);
        $this->assertSame('content', file_get_contents($target));
        $this->assertFileExists($source);
    }

    public function testCopyThrowsForNonexistentSource(): void
    {
        // Arrange
        $fs = new FileSystem();

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to copy');

        // Act
        $fs->copy($this->tempDir . '/nope.txt', $this->tempDir . '/target.txt');
    }

    // -----------------------------------------------------------
    // move()
    // -----------------------------------------------------------

    public function testMoveRelocatesFile(): void
    {
        // Arrange
        $fs = new FileSystem();
        $source = $this->tempDir . '/original.txt';
        $target = $this->tempDir . '/moved.txt';
        file_put_contents($source, 'content');

        // Act
        $fs->move($source, $target);

        // Assert
        $this->assertFileExists($target);
        $this->assertSame('content', file_get_contents($target));
        $this->assertFileDoesNotExist($source);
    }

    public function testMoveThrowsForNonexistentSource(): void
    {
        // Arrange
        $fs = new FileSystem();

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to move');

        // Act
        $fs->move($this->tempDir . '/nope.txt', $this->tempDir . '/target.txt');
    }

    public function testMoveWithOverwrite(): void
    {
        // Arrange
        $fs = new FileSystem();
        $source = $this->tempDir . '/source.txt';
        $target = $this->tempDir . '/target.txt';
        file_put_contents($source, 'new');
        file_put_contents($target, 'old');

        // Act
        $fs->move($source, $target, overwrite: true);

        // Assert
        $this->assertSame('new', file_get_contents($target));
        $this->assertFileDoesNotExist($source);
    }

    // -----------------------------------------------------------
    // delete()
    // -----------------------------------------------------------

    public function testDeleteRemovesFile(): void
    {
        // Arrange
        $fs = new FileSystem();
        $path = $this->tempDir . '/delete_me.txt';
        file_put_contents($path, 'content');

        // Act
        $fs->delete($path);

        // Assert
        $this->assertFileDoesNotExist($path);
    }

    public function testDeleteRemovesDirectoryRecursively(): void
    {
        // Arrange
        $fs = new FileSystem();
        $dir = $this->tempDir . '/subdir';
        mkdir($dir);
        file_put_contents($dir . '/file.txt', 'content');

        // Act
        $fs->delete($dir);

        // Assert
        $this->assertDirectoryDoesNotExist($dir);
    }

    public function testDeleteDoesNotThrowForNonexistentPath(): void
    {
        // Arrange
        $fs = new FileSystem();

        // Act — Symfony's remove silently ignores nonexistent paths
        $fs->delete($this->tempDir . '/nonexistent');

        // Assert
        $this->addToAssertionCount(1);
    }

    // -----------------------------------------------------------
    // mkdir()
    // -----------------------------------------------------------

    public function testMkdirCreatesDirectory(): void
    {
        // Arrange
        $fs = new FileSystem();
        $dir = $this->tempDir . '/new_dir';

        // Act
        $fs->mkdir($dir);

        // Assert
        $this->assertDirectoryExists($dir);
    }

    public function testMkdirCreatesNestedDirectories(): void
    {
        // Arrange
        $fs = new FileSystem();
        $dir = $this->tempDir . '/a/b/c';

        // Act
        $fs->mkdir($dir);

        // Assert
        $this->assertDirectoryExists($dir);
    }

    public function testMkdirDoesNotThrowIfAlreadyExists(): void
    {
        // Arrange
        $fs = new FileSystem();
        $dir = $this->tempDir . '/existing';
        mkdir($dir);

        // Act
        $fs->mkdir($dir);

        // Assert
        $this->assertDirectoryExists($dir);
    }

    // -----------------------------------------------------------
    // exists()
    // -----------------------------------------------------------

    public function testExistsReturnsTrueForFile(): void
    {
        // Arrange
        $fs = new FileSystem();
        $path = $this->tempDir . '/file.txt';
        file_put_contents($path, 'content');

        // Assert
        $this->assertTrue($fs->exists($path));
    }

    public function testExistsReturnsTrueForDirectory(): void
    {
        // Arrange
        $fs = new FileSystem();

        // Assert
        $this->assertTrue($fs->exists($this->tempDir));
    }

    public function testExistsReturnsFalseForNonexistentPath(): void
    {
        // Arrange
        $fs = new FileSystem();

        // Assert
        $this->assertFalse($fs->exists($this->tempDir . '/nope'));
    }

    // -----------------------------------------------------------
    // isDirectory()
    // -----------------------------------------------------------

    public function testIsDirectoryReturnsTrueForDirectory(): void
    {
        // Arrange
        $fs = new FileSystem();

        // Assert
        $this->assertTrue($fs->isDirectory($this->tempDir));
    }

    public function testIsDirectoryReturnsFalseForFile(): void
    {
        // Arrange
        $fs = new FileSystem();
        $path = $this->tempDir . '/file.txt';
        file_put_contents($path, 'content');

        // Assert
        $this->assertFalse($fs->isDirectory($path));
    }

    // -----------------------------------------------------------
    // isFile()
    // -----------------------------------------------------------

    public function testIsFileReturnsTrueForFile(): void
    {
        // Arrange
        $fs = new FileSystem();
        $path = $this->tempDir . '/file.txt';
        file_put_contents($path, 'content');

        // Assert
        $this->assertTrue($fs->isFile($path));
    }

    public function testIsFileReturnsFalseForDirectory(): void
    {
        // Arrange
        $fs = new FileSystem();

        // Assert
        $this->assertFalse($fs->isFile($this->tempDir));
    }
}
