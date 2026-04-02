<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo\Formatters;

use Arcanum\Parchment\FileSystem;
use Arcanum\Parchment\Reader;
use Arcanum\Parchment\Writer;
use Arcanum\Shodo\HelperRegistry;
use Arcanum\Shodo\Helpers\HelperResolver;
use Arcanum\Shodo\Formatters\HtmlFallbackFormatter;
use Arcanum\Shodo\Formatters\HtmlFormatter;
use Arcanum\Shodo\TemplateCache;
use Arcanum\Shodo\TemplateCompiler;
use Arcanum\Shodo\TemplateResolver;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(HtmlFormatter::class)]
#[UsesClass(HelperRegistry::class)]
#[UsesClass(HelperResolver::class)]
#[UsesClass(TemplateResolver::class)]
#[UsesClass(TemplateCompiler::class)]
#[UsesClass(TemplateCache::class)]
#[UsesClass(HtmlFallbackFormatter::class)]
#[UsesClass(Reader::class)]
#[UsesClass(Writer::class)]
#[UsesClass(FileSystem::class)]
final class HtmlFormatterTest extends TestCase
{
    private string $rootDir;
    private string $cacheDir;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/arcanum_html_formatter_test_' . uniqid();
        $this->rootDir = $base;
        $this->cacheDir = $base . '/cache';
        mkdir($this->rootDir . '/app/Domain/Query', 0755, true);
        mkdir($this->rootDir . '/app/Pages', 0755, true);
        mkdir($this->cacheDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootDir);
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
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createFormatter(
        string $cacheDir = '',
        ?HelperResolver $helpers = null,
    ): HtmlFormatter {
        $resolver = new TemplateResolver($this->rootDir, 'App');
        $compiler = new TemplateCompiler();
        $cache = new TemplateCache($cacheDir ?: $this->cacheDir);
        $fallback = new HtmlFallbackFormatter();

        return new HtmlFormatter($resolver, $compiler, $cache, $fallback, helpers: $helpers);
    }

    public function testFormatReturnsNonEmptyOutput(): void
    {
        // Arrange
        $formatter = $this->createFormatter();

        // Act
        $result = $formatter->format(['key' => 'value'], 'App\\Domain\\Query\\Health');

        // Assert
        $this->assertNotSame('', $result);
    }

    public function testFormatsFallbackWhenNoTemplate(): void
    {
        // Arrange
        $formatter = $this->createFormatter();

        // Act
        $result = $formatter->format(['name' => 'Arcanum'], 'App\\Domain\\Query\\Missing');

        // Assert
        $this->assertStringContainsString('<!DOCTYPE html>', $result);
        $this->assertStringContainsString('<dt>name</dt>', $result);
        $this->assertStringContainsString('Arcanum', $result);
    }

    public function testFormatsFallbackWhenDtoClassIsEmpty(): void
    {
        // Arrange
        $formatter = $this->createFormatter();

        // Act
        $result = $formatter->format(['status' => 'ok']);

        // Assert
        $this->assertStringContainsString('<dt>status</dt>', $result);
        $this->assertStringContainsString('ok', $result);
    }

    public function testFormatsTemplateWithArrayData(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<h1>{{ $title }}</h1>',
        );
        $formatter = $this->createFormatter();

        // Act
        $result = $formatter->format(['title' => 'Welcome'], 'App\\Pages\\Index');

        // Assert
        $this->assertStringContainsString('<h1>Welcome</h1>', $result);
    }

    public function testFormatsTemplateWithObjectData(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<p>{{ $name }}</p>',
        );
        $obj = new class {
            public string $name = 'Arcanum';
        };
        $formatter = $this->createFormatter();

        // Act
        $result = $formatter->format($obj, 'App\\Pages\\Index');

        // Assert
        $this->assertStringContainsString('<p>Arcanum</p>', $result);
    }

    public function testFormatsTemplateWithScalarData(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<p>{{ $data }}</p>',
        );
        $formatter = $this->createFormatter();

        // Act
        $result = $formatter->format('hello', 'App\\Pages\\Index');

        // Assert
        $this->assertStringContainsString('<p>hello</p>', $result);
    }

    public function testTemplateEscapesOutput(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<p>{{ $name }}</p>',
        );
        $formatter = $this->createFormatter();

        // Act
        $result = $formatter->format(['name' => '<script>xss</script>'], 'App\\Pages\\Index');

        // Assert
        $this->assertStringContainsString('&lt;script&gt;xss&lt;/script&gt;', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testTemplateRawOutput(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<div>{{! $html !}}</div>',
        );
        $formatter = $this->createFormatter();

        // Act
        $result = $formatter->format(['html' => '<b>bold</b>'], 'App\\Pages\\Index');

        // Assert
        $this->assertStringContainsString('<div><b>bold</b></div>', $result);
    }

    public function testTemplateWithForeach(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '{{ foreach($items as $item) }}<li>{{ $item }}</li>{{ endforeach }}',
        );
        $formatter = $this->createFormatter();

        // Act
        $result = $formatter->format(['items' => ['a', 'b', 'c']], 'App\\Pages\\Index');

        // Assert
        $this->assertStringContainsString('<li>a</li>', $result);
        $this->assertStringContainsString('<li>b</li>', $result);
        $this->assertStringContainsString('<li>c</li>', $result);
    }

    public function testTemplateWithConditional(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '{{ if($show) }}<p>visible</p>{{ else }}<p>hidden</p>{{ endif }}',
        );
        $formatter = $this->createFormatter();

        // Act
        $shown = $formatter->format(['show' => true], 'App\\Pages\\Index');
        $hidden = $formatter->format(['show' => false], 'App\\Pages\\Index');

        // Assert
        $this->assertStringContainsString('<p>visible</p>', $shown);
        $this->assertStringContainsString('<p>hidden</p>', $hidden);
    }

    public function testCachedTemplateIsUsedOnSecondFormat(): void
    {
        // Arrange
        $templatePath = $this->rootDir . '/app/Pages/Index.html';
        file_put_contents($templatePath, '<p>{{ $name }}</p>');
        $formatter = $this->createFormatter();

        // Act — first format compiles and caches
        $formatter->format(['name' => 'first'], 'App\\Pages\\Index');

        // Verify cache file exists
        $cache = new TemplateCache($this->cacheDir);
        $this->assertTrue($cache->isFresh($templatePath));

        // Act — second format uses cache
        $result = $formatter->format(['name' => 'second'], 'App\\Pages\\Index');

        // Assert
        $this->assertStringContainsString('<p>second</p>', $result);
    }

    public function testFormatsWithoutCaching(): void
    {
        // Arrange — empty cache dir disables caching
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<p>{{ $name }}</p>',
        );
        $formatter = $this->createFormatter(cacheDir: '');

        // Act
        $result = $formatter->format(['name' => 'Arcanum'], 'App\\Pages\\Index');

        // Assert
        $this->assertStringContainsString('<p>Arcanum</p>', $result);
    }

    // -----------------------------------------------------------
    // Template helpers
    // -----------------------------------------------------------

    public function testHelperCallResolvesFromResolver(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<a href="{{ Route::url(\'health\') }}">Health</a>',
        );
        $helper = new class {
            public function url(string $name): string
            {
                return '/api/' . $name;
            }
        };
        $registry = new HelperRegistry();
        $registry->register('Route', $helper);
        $resolver = new HelperResolver($registry);
        $formatter = $this->createFormatter(helpers: $resolver);

        // Act
        $result = $formatter->format([], 'App\\Pages\\Index');

        // Assert
        $this->assertStringContainsString('<a href="/api/health">Health</a>', $result);
    }

    public function testHelperOutputIsHtmlEscaped(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<p>{{ Str::value() }}</p>',
        );
        $helper = new class {
            public function value(): string
            {
                return '<script>xss</script>';
            }
        };
        $registry = new HelperRegistry();
        $registry->register('Str', $helper);
        $resolver = new HelperResolver($registry);
        $formatter = $this->createFormatter(helpers: $resolver);

        // Act
        $result = $formatter->format([], 'App\\Pages\\Index');

        // Assert
        $this->assertStringContainsString('&lt;script&gt;xss&lt;/script&gt;', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testTemplateWithoutHelpersStillWorks(): void
    {
        // Arrange — no resolver passed (null)
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<p>{{ $name }}</p>',
        );
        $formatter = $this->createFormatter();

        // Act
        $result = $formatter->format(['name' => 'Arcanum'], 'App\\Pages\\Index');

        // Assert
        $this->assertStringContainsString('<p>Arcanum</p>', $result);
    }
}
