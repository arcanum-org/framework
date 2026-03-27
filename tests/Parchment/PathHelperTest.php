<?php

declare(strict_types=1);

namespace Arcanum\Test\Parchment;

use Arcanum\Parchment\PathHelper;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PathHelper::class)]
final class PathHelperTest extends TestCase
{
    // -----------------------------------------------------------
    // normalize()
    // -----------------------------------------------------------

    public function testNormalizeResolvesDotSegments(): void
    {
        $this->assertSame('/foo/baz', PathHelper::normalize('/foo/bar/../baz'));
    }

    public function testNormalizeResolvesDotDot(): void
    {
        $this->assertSame('/foo', PathHelper::normalize('/foo/bar/..'));
    }

    public function testNormalizeResolvesSingleDot(): void
    {
        $this->assertSame('/foo/bar', PathHelper::normalize('/foo/./bar'));
    }

    public function testNormalizeFixesDoubleSlashes(): void
    {
        $this->assertSame('/foo/bar', PathHelper::normalize('/foo//bar'));
    }

    // -----------------------------------------------------------
    // resolve()
    // -----------------------------------------------------------

    public function testResolveRelativePathAgainstBase(): void
    {
        $this->assertSame('/home/user/file.txt', PathHelper::resolve('file.txt', '/home/user'));
    }

    public function testResolveAbsolutePathIgnoresBase(): void
    {
        $this->assertSame('/absolute/path', PathHelper::resolve('/absolute/path', '/home/user'));
    }

    // -----------------------------------------------------------
    // relative()
    // -----------------------------------------------------------

    public function testRelativeComputesRelativePath(): void
    {
        $this->assertSame('bar/file.txt', PathHelper::relative('/foo/bar/file.txt', '/foo'));
    }

    // -----------------------------------------------------------
    // extension()
    // -----------------------------------------------------------

    public function testExtensionReturnsFileExtension(): void
    {
        $this->assertSame('php', PathHelper::extension('/src/Foo.php'));
    }

    public function testExtensionReturnsEmptyForNoExtension(): void
    {
        $this->assertSame('', PathHelper::extension('/src/Makefile'));
    }

    public function testExtensionHandlesDottedPaths(): void
    {
        $this->assertSame('gz', PathHelper::extension('/archive.tar.gz'));
    }

    // -----------------------------------------------------------
    // filenameWithoutExtension()
    // -----------------------------------------------------------

    public function testFilenameWithoutExtension(): void
    {
        $this->assertSame('Foo', PathHelper::filenameWithoutExtension('/src/Foo.php'));
    }

    public function testFilenameWithoutExtensionNoExtension(): void
    {
        $this->assertSame('Makefile', PathHelper::filenameWithoutExtension('/src/Makefile'));
    }

    // -----------------------------------------------------------
    // directory()
    // -----------------------------------------------------------

    public function testDirectoryReturnsParent(): void
    {
        $this->assertSame('/foo/bar', PathHelper::directory('/foo/bar/file.txt'));
    }

    public function testDirectoryOfRoot(): void
    {
        $this->assertSame('/', PathHelper::directory('/file.txt'));
    }

    // -----------------------------------------------------------
    // join()
    // -----------------------------------------------------------

    public function testJoinCombinesSegments(): void
    {
        $this->assertSame('/foo/bar/baz', PathHelper::join('/foo', 'bar', 'baz'));
    }

    public function testJoinHandlesTrailingSlashes(): void
    {
        $this->assertSame('/foo/bar', PathHelper::join('/foo/', 'bar'));
    }

    // -----------------------------------------------------------
    // isAbsolute() / isRelative()
    // -----------------------------------------------------------

    public function testIsAbsoluteForAbsolutePath(): void
    {
        $this->assertTrue(PathHelper::isAbsolute('/foo/bar'));
    }

    public function testIsAbsoluteForRelativePath(): void
    {
        $this->assertFalse(PathHelper::isAbsolute('foo/bar'));
    }

    public function testIsRelativeForRelativePath(): void
    {
        $this->assertTrue(PathHelper::isRelative('foo/bar'));
    }

    public function testIsRelativeForAbsolutePath(): void
    {
        $this->assertFalse(PathHelper::isRelative('/foo/bar'));
    }
}
