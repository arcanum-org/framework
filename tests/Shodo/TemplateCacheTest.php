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

    public function testStoreWritesDependencyHeaderWhenDepsProvided(): void
    {
        // Arrange
        $cache = new TemplateCache($this->cacheDir);
        $templatePath = $this->createTemplate('page.html', '<p>hello</p>');
        $layoutPath = $this->createTemplate('layout.html', '<html></html>');

        // Act
        $cache->store(
            $templatePath,
            '<?php echo "compiled"; ?>',
            [$layoutPath],
        );

        // Assert — file starts with the deps header line
        $contents = file_get_contents($cache->cachePath($templatePath));
        $this->assertNotFalse($contents);
        $this->assertStringStartsWith('<?php /* arcanum-deps: ', $contents);
        $this->assertStringContainsString($layoutPath, $contents);
    }

    public function testStoreOmitsHeaderWhenNoDepsProvided(): void
    {
        // Arrange
        $cache = new TemplateCache($this->cacheDir);
        $templatePath = $this->createTemplate('page.html', '<p>hello</p>');

        // Act
        $cache->store($templatePath, '<?php echo "compiled"; ?>');

        // Assert — no header, file is unchanged compile output
        $contents = file_get_contents($cache->cachePath($templatePath));
        $this->assertSame('<?php echo "compiled"; ?>', $contents);
    }

    public function testLoadStripsDependencyHeader(): void
    {
        // Arrange
        $cache = new TemplateCache($this->cacheDir);
        $templatePath = $this->createTemplate('page.html', '<p>hello</p>');
        $layoutPath = $this->createTemplate('layout.html', '<html></html>');
        $cache->store($templatePath, '<?php echo "compiled"; ?>', [$layoutPath]);

        // Act
        $loaded = $cache->load($templatePath);

        // Assert — header gone, only the original compile output remains
        $this->assertSame('<?php echo "compiled"; ?>', $loaded);
    }

    public function testIsFreshReturnsFalseWhenTrackedDependencyIsNewer(): void
    {
        // Arrange — store with a layout dep, then make the layout newer
        // than the cache file. The cache should report stale.
        $cache = new TemplateCache($this->cacheDir);
        $templatePath = $this->createTemplate('page.html', '<p>hello</p>');
        $layoutPath = $this->createTemplate('layout.html', '<html></html>');

        // Make the layout older first so store() captures a baseline.
        touch($layoutPath, time() - 100);
        touch($templatePath, time() - 100);

        $cache->store($templatePath, '<?php echo "compiled"; ?>', [$layoutPath]);

        // Backdate the cache file so the layout edit looks "newer"
        touch($cache->cachePath($templatePath), time() - 100);

        // Now bump the layout's mtime to "now"
        touch($layoutPath, time());

        // Act & Assert
        $this->assertFalse($cache->isFresh($templatePath));
    }

    public function testIsFreshReturnsTrueWhenAllDependenciesAreOlder(): void
    {
        // Arrange
        $cache = new TemplateCache($this->cacheDir);
        $templatePath = $this->createTemplate('page.html', '<p>hello</p>');
        $layoutPath = $this->createTemplate('layout.html', '<html></html>');
        $partialPath = $this->createTemplate('nav.html', '<nav></nav>');

        // All sources older than the cache.
        touch($layoutPath, time() - 100);
        touch($partialPath, time() - 100);
        touch($templatePath, time() - 100);

        $cache->store(
            $templatePath,
            '<?php echo "compiled"; ?>',
            [$layoutPath, $partialPath],
        );

        // Act & Assert
        $this->assertTrue($cache->isFresh($templatePath));
    }

    public function testIsFreshReturnsFalseWhenTrackedDependencyIsDeleted(): void
    {
        // Arrange — store with a dep, then delete the dep file. We treat
        // a missing dep as stale so the recompile surfaces the missing
        // include error properly instead of serving stale cached output.
        $cache = new TemplateCache($this->cacheDir);
        $templatePath = $this->createTemplate('page.html', '<p>hello</p>');
        $layoutPath = $this->createTemplate('layout.html', '<html></html>');

        $cache->store($templatePath, '<?php echo "compiled"; ?>', [$layoutPath]);

        unlink($layoutPath);

        // Act & Assert
        $this->assertFalse($cache->isFresh($templatePath));
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

    // ------------------------------------------------------------------
    // Fragment-keyed cache entries
    // ------------------------------------------------------------------

    public function testFragmentCachePathDiffersFromWholeTemplate(): void
    {
        // Arrange
        $cache = new TemplateCache($this->cacheDir);
        $templatePath = '/some/path/template.html';

        // Act
        $wholePath = $cache->cachePath($templatePath);
        $fragmentPath = $cache->cachePath($templatePath, 'sidebar');

        // Assert — different cache files for the same template
        $this->assertNotSame($wholePath, $fragmentPath);
    }

    public function testFragmentCachePathIsDeterministic(): void
    {
        // Arrange
        $cache = new TemplateCache($this->cacheDir);
        $templatePath = '/some/path/template.html';

        // Act
        $path1 = $cache->cachePath($templatePath, 'sidebar');
        $path2 = $cache->cachePath($templatePath, 'sidebar');

        // Assert
        $this->assertSame($path1, $path2);
    }

    public function testFragmentCachePathDiffersPerFragmentName(): void
    {
        // Arrange
        $cache = new TemplateCache($this->cacheDir);
        $templatePath = '/some/path/template.html';

        // Act
        $sidebarPath = $cache->cachePath($templatePath, 'sidebar');
        $listPath = $cache->cachePath($templatePath, 'product-list');

        // Assert
        $this->assertNotSame($sidebarPath, $listPath);
    }

    public function testFragmentStoreAndLoadRoundtrip(): void
    {
        // Arrange
        $cache = new TemplateCache($this->cacheDir);
        $templatePath = $this->createTemplate('page.html', '<p>original</p>');
        $compiled = '<aside><?= $__escape((string)($title)) ?></aside>';

        // Act
        $cache->store($templatePath, $compiled, [], 'sidebar');
        $loaded = $cache->load($templatePath, 'sidebar');

        // Assert
        $this->assertSame($compiled, $loaded);
    }

    public function testFragmentCacheIsFreshIndependently(): void
    {
        // Arrange
        $cache = new TemplateCache($this->cacheDir);
        $templatePath = $this->createTemplate('page.html', '<p>hello</p>');
        touch($templatePath, time() - 10);

        // Store whole-template and fragment entries
        $cache->store($templatePath, '<?php echo "whole"; ?>');
        $cache->store($templatePath, '<?php echo "fragment"; ?>', [], 'sidebar');

        // Act & Assert — both are fresh
        $this->assertTrue($cache->isFresh($templatePath));
        $this->assertTrue($cache->isFresh($templatePath, 'sidebar'));
    }

    public function testFragmentCacheIsFreshReturnsFalseWhenNotCached(): void
    {
        // Arrange — whole template is cached, but fragment is not
        $cache = new TemplateCache($this->cacheDir);
        $templatePath = $this->createTemplate('page.html', '<p>hello</p>');
        touch($templatePath, time() - 10);
        $cache->store($templatePath, '<?php echo "whole"; ?>');

        // Act & Assert
        $this->assertTrue($cache->isFresh($templatePath));
        $this->assertFalse($cache->isFresh($templatePath, 'sidebar'));
    }

    public function testFragmentCacheInheritsDependencyTracking(): void
    {
        // Arrange
        $cache = new TemplateCache($this->cacheDir);
        $templatePath = $this->createTemplate('page.html', '<p>hello</p>');
        $layoutPath = $this->createTemplate('layout.html', '<html></html>');

        touch($templatePath, time() - 100);
        touch($layoutPath, time() - 100);

        $cache->store($templatePath, '<?php echo "frag"; ?>', [$layoutPath], 'sidebar');

        // Act & Assert — fresh when deps are older
        $this->assertTrue($cache->isFresh($templatePath, 'sidebar'));

        // Bump the layout — fragment cache should go stale
        touch($cache->cachePath($templatePath, 'sidebar'), time() - 100);
        touch($layoutPath, time());

        $this->assertFalse($cache->isFresh($templatePath, 'sidebar'));
    }
}
