<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo;

use Arcanum\Parchment\FileSystem;
use Arcanum\Parchment\Reader;
use Arcanum\Parchment\Writer;
use Arcanum\Shodo\TemplateCache;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(TemplateCache::class)]
#[UsesClass(Reader::class)]
#[UsesClass(Writer::class)]
#[UsesClass(FileSystem::class)]
final class TemplateCacheTest extends TestCase
{
    private string $cacheDir;
    private string $templateDir;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/arcanum_template_cache_test_' . uniqid();
        $this->cacheDir = $base . '/cache';
        $this->templateDir = $base . '/templates';
        mkdir($this->cacheDir, 0755, true);
        mkdir($this->templateDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->cacheDir);
        $this->removeDirectory($this->templateDir);
        // Remove the parent
        $parent = dirname($this->cacheDir);
        if (is_dir($parent)) {
            rmdir($parent);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = glob($dir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                is_dir($file) ? $this->removeDirectory($file) : unlink($file);
            }
        }
        rmdir($dir);
    }

    private function createTemplate(string $name, string $content): string
    {
        $path = $this->templateDir . '/' . $name;
        file_put_contents($path, $content);
        return $path;
    }

    public function testIsFreshReturnsFalseWhenCachingDisabled(): void
    {
        // Arrange
        $cache = new TemplateCache('');
        $templatePath = $this->createTemplate('page.html', '<p>hello</p>');

        // Act & Assert
        $this->assertFalse($cache->isFresh($templatePath));
    }

    public function testStoreIsNoOpWhenCachingDisabled(): void
    {
        // Arrange — when cacheDirectory is empty, store() must NOT try to
        // write to disk. Without this guard, the cache path becomes
        // /<md5>.php at the filesystem root, which fails on read-only
        // filesystems and pollutes / on writeable ones.
        $cache = new TemplateCache('');
        $templatePath = $this->createTemplate('page.html', '<p>hello</p>');

        // Act — must not throw
        $cache->store($templatePath, '<?php echo "compiled"; ?>');

        // Assert — nothing was written; isFresh still false
        $this->assertFalse($cache->isFresh($templatePath));
    }

    public function testIsFreshReturnsFalseWhenNoCacheFileExists(): void
    {
        // Arrange
        $cache = new TemplateCache($this->cacheDir);
        $templatePath = $this->createTemplate('page.html', '<p>hello</p>');

        // Act & Assert
        $this->assertFalse($cache->isFresh($templatePath));
    }

    public function testIsFreshReturnsTrueWhenCacheIsNewer(): void
    {
        // Arrange
        $cache = new TemplateCache($this->cacheDir);
        $templatePath = $this->createTemplate('page.html', '<p>hello</p>');

        // Set template mtime to the past
        touch($templatePath, time() - 10);

        // Store compiled PHP (will have current mtime)
        $cache->store($templatePath, '<?php echo "hello"; ?>');

        // Act & Assert
        $this->assertTrue($cache->isFresh($templatePath));
    }

    public function testIsFreshReturnsFalseWhenTemplateIsNewer(): void
    {
        // Arrange
        $cache = new TemplateCache($this->cacheDir);
        $templatePath = $this->createTemplate('page.html', '<p>hello</p>');

        // Store compiled PHP first
        $cache->store($templatePath, '<?php echo "hello"; ?>');

        // Touch the cache file to the past
        touch($cache->cachePath($templatePath), time() - 10);

        // Touch the template to now
        touch($templatePath);

        // Act & Assert
        $this->assertFalse($cache->isFresh($templatePath));
    }

    public function testStoreAndLoadRoundtrip(): void
    {
        // Arrange
        $cache = new TemplateCache($this->cacheDir);
        $templatePath = $this->createTemplate('page.html', '<p>original</p>');
        $compiled = '<?= htmlspecialchars($name) ?>';

        // Act
        $cache->store($templatePath, $compiled);
        $loaded = $cache->load($templatePath);

        // Assert
        $this->assertSame($compiled, $loaded);
    }

    public function testCachePathIsDeterministic(): void
    {
        // Arrange
        $cache = new TemplateCache($this->cacheDir);
        $templatePath = '/some/path/to/template.html';

        // Act
        $path1 = $cache->cachePath($templatePath);
        $path2 = $cache->cachePath($templatePath);

        // Assert
        $this->assertSame($path1, $path2);
        $this->assertStringEndsWith('.php', $path1);
        $this->assertStringStartsWith($this->cacheDir . DIRECTORY_SEPARATOR, $path1);
    }

    public function testCachePathDiffersForDifferentTemplates(): void
    {
        // Arrange
        $cache = new TemplateCache($this->cacheDir);

        // Act
        $path1 = $cache->cachePath('/one/template.html');
        $path2 = $cache->cachePath('/another/template.html');

        // Assert
        $this->assertNotSame($path1, $path2);
    }

    public function testClearRemovesCacheDirectory(): void
    {
        // Arrange
        $cache = new TemplateCache($this->cacheDir);
        $templatePath = $this->createTemplate('page.html', '<p>hello</p>');
        $cache->store($templatePath, '<?php echo "cached"; ?>');
        $this->assertFileExists($cache->cachePath($templatePath));

        // Act
        $cache->clear();

        // Assert
        $this->assertDirectoryDoesNotExist($this->cacheDir);
    }

    public function testClearDoesNothingWhenCachingDisabled(): void
    {
        // Arrange
        $cache = new TemplateCache('');

        // Act
        $cache->clear();

        // Assert — the real cache dir is still intact
        $this->assertDirectoryExists($this->cacheDir);
    }

    public function testClearDoesNothingWhenDirectoryDoesNotExist(): void
    {
        // Arrange
        $nonexistent = sys_get_temp_dir() . '/arcanum_nonexistent_' . uniqid();
        $cache = new TemplateCache($nonexistent);

        // Act
        $cache->clear();

        // Assert
        $this->assertDirectoryDoesNotExist($nonexistent);
    }
}
