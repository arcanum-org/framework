<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo\Formatters;

use Arcanum\Parchment\FileSystem;
use Arcanum\Parchment\Reader;
use Arcanum\Parchment\Writer;
use Arcanum\Shodo\HelperRegistry;
use Arcanum\Shodo\HelperResolver;
use Arcanum\Shodo\Formatters\HtmlFallbackFormatter;
use Arcanum\Shodo\Formatters\HtmlFormatter;
use Arcanum\Shodo\TemplateCache;
use Arcanum\Shodo\TemplateCompiler;
use Arcanum\Shodo\TemplateAnalyzer;
use Arcanum\Shodo\TemplateEngine;
use Arcanum\Shodo\TemplateResolver;
use Psr\Log\LoggerInterface;
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
#[UsesClass(TemplateEngine::class)]
#[UsesClass(TemplateAnalyzer::class)]
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
        bool $debug = false,
        ?LoggerInterface $logger = null,
    ): HtmlFormatter {
        $resolver = new TemplateResolver($this->rootDir, 'App');
        $engine = new TemplateEngine(
            compiler: new TemplateCompiler(),
            cache: new TemplateCache($cacheDir ?: $this->cacheDir),
            debug: $debug,
            logger: $logger,
        );
        $fallback = new HtmlFallbackFormatter();

        return new HtmlFormatter(
            $resolver,
            $engine,
            $fallback,
            helpers: $helpers,
        );
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

    public function testCsrfDirectiveProducesHiddenInput(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<form>{{ csrf }}</form>',
        );
        $helper = new class {
            public function csrf(): string
            {
                return '<input type="hidden" name="_token" value="abc123">';
            }
        };
        $registry = new HelperRegistry();
        $registry->register('Html', $helper);
        $resolver = new HelperResolver($registry);
        $formatter = $this->createFormatter(helpers: $resolver);

        // Act
        $result = $formatter->format([], 'App\\Pages\\Index');

        // Assert
        $this->assertStringContainsString(
            '<form><input type="hidden" name="_token" value="abc123"></form>',
            $result,
        );
    }

    public function testDebugModeThrowsOnUndefinedTemplateVariable(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<p>{{ $usernme }}</p>',
        );
        $formatter = $this->createFormatter(debug: true);

        // Suppress unused-variable notice (we're testing undefined detection)
        set_error_handler(static fn() => true, \E_USER_NOTICE);

        // Act & Assert
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Undefined template variable "$usernme"');

            $formatter->format(['username' => 'Alice'], 'App\\Pages\\Index');
        } finally {
            restore_error_handler();
        }
    }

    public function testDebugModeListsAvailableVariables(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<p>{{ $typo }}</p>',
        );
        $formatter = $this->createFormatter(debug: true);

        // Suppress unused-variable notice (we're testing undefined detection)
        set_error_handler(static fn() => true, \E_USER_NOTICE);

        // Act & Assert
        try {
            $formatter->format(['name' => 'Alice', 'email' => 'a@b.com'], 'App\\Pages\\Index');
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('name', $e->getMessage());
            $this->assertStringContainsString('email', $e->getMessage());
        } finally {
            restore_error_handler();
        }
    }

    public function testProductionModeSilentlyRendersEmptyForUndefinedVariable(): void
    {
        // Arrange — debug: false (default)
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<p>{{ $missing }}</p>',
        );
        $formatter = $this->createFormatter();

        // Act — should not throw
        $result = @$formatter->format(['name' => 'Alice'], 'App\\Pages\\Index');

        // Assert — renders without crashing (empty value)
        $this->assertStringContainsString('<p>', $result);
    }

    // -----------------------------------------------------------
    // Unused template variable warnings
    // -----------------------------------------------------------

    public function testDebugModeTriggersNoticeForUnusedVariables(): void
    {
        // Arrange — template uses $name but not $ssn
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<h1>{{ $name }}</h1>',
        );
        $formatter = $this->createFormatter(debug: true);

        // Act — capture E_USER_NOTICE
        $noticed = '';
        set_error_handler(static function (int $severity, string $message) use (&$noticed): bool {
            if ($severity === \E_USER_NOTICE) {
                $noticed = $message;
            }
            return true;
        });

        $formatter->format(['name' => 'Alice', 'ssn' => '123-45-6789'], 'App\\Pages\\Index');
        restore_error_handler();

        // Assert
        $this->assertStringContainsString('ssn', $noticed);
        $this->assertStringContainsString('unused', strtolower($noticed));
    }

    public function testNonDebugModeDoesNotTriggerUnusedNotice(): void
    {
        // Arrange — debug: false (default)
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<h1>{{ $name }}</h1>',
        );
        $formatter = $this->createFormatter();

        // Act — capture any notices
        $noticed = '';
        set_error_handler(static function (int $severity, string $message) use (&$noticed): bool {
            if ($severity === \E_USER_NOTICE) {
                $noticed = $message;
            }
            return true;
        });

        $formatter->format(['name' => 'Alice', 'ssn' => '123-45-6789'], 'App\\Pages\\Index');
        restore_error_handler();

        // Assert — no notice triggered
        $this->assertSame('', $noticed);
    }

    public function testResolveTemplateReturnsPathForExistingTemplate(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<p>Hello</p>',
        );
        $formatter = $this->createFormatter();

        // Act
        $result = $formatter->resolveTemplate('App\\Pages\\Index');

        // Assert
        $this->assertSame($this->rootDir . '/app/Pages/Index.html', $result);
    }

    public function testResolveTemplateReturnsNullForMissingTemplate(): void
    {
        // Arrange
        $formatter = $this->createFormatter();

        // Act & Assert
        $this->assertNull($formatter->resolveTemplate('App\\Pages\\Missing'));
    }

    // -----------------------------------------------------------
    // Lazy closure resolution
    // -----------------------------------------------------------

    public function testClosuresAreInvokedOnFullRender(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<p>{{ $name }}</p><p>{{ $count }}</p>',
        );
        $formatter = $this->createFormatter();
        $invoked = [];

        // Act
        $result = $formatter->format([
            'name' => function () use (&$invoked) {
                $invoked[] = 'name';
                return 'Alice';
            },
            'count' => function () use (&$invoked) {
                $invoked[] = 'count';
                return '42';
            },
        ], 'App\\Pages\\Index');

        // Assert — both closures invoked, values rendered
        $this->assertSame(['name', 'count'], $invoked);
        $this->assertStringContainsString('<p>Alice</p>', $result);
        $this->assertStringContainsString('<p>42</p>', $result);
    }

    public function testPlainValuesPassThroughUnchanged(): void
    {
        // Arrange — mix of closures and plain values
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<p>{{ $name }}</p><p>{{ $role }}</p>',
        );
        $formatter = $this->createFormatter();

        // Act
        $result = $formatter->format([
            'name' => fn() => 'Alice',
            'role' => 'admin',
        ], 'App\\Pages\\Index');

        // Assert — both rendered correctly
        $this->assertStringContainsString('<p>Alice</p>', $result);
        $this->assertStringContainsString('<p>admin</p>', $result);
    }

    public function testClosureExceptionSurfacesBeforeTemplateExecution(): void
    {
        // Arrange — closure that throws
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<p>{{ $data }}</p>',
        );
        $formatter = $this->createFormatter();

        // Act & Assert — exception surfaces cleanly, not from eval
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection failed');

        $formatter->format([
            'data' => fn() => throw new \RuntimeException('Database connection failed'),
        ], 'App\\Pages\\Index');
    }

    // -----------------------------------------------------------
    // Status-specific template resolution
    // -----------------------------------------------------------

    public function testFormatUsesStatusSpecificTemplateWhenProvided(): void
    {
        // Arrange — co-located Products.422.html exists
        file_put_contents(
            $this->rootDir . '/app/Domain/Query/Products.html',
            '<p>{{ $name }}</p>',
        );
        file_put_contents(
            $this->rootDir . '/app/Domain/Query/Products.422.html',
            '<p class="error">{{ $message }}</p>',
        );
        $formatter = $this->createFormatter();

        // Act — pass statusCode 422
        $result = $formatter->format(
            ['message' => 'Validation failed'],
            'App\\Domain\\Query\\Products',
            422,
        );

        // Assert — renders the 422 template, not the default
        $this->assertStringContainsString('<p class="error">Validation failed</p>', $result);
    }

    public function testFormatFallsBackToDefaultTemplateWhenNoStatusMatch(): void
    {
        // Arrange — only Products.html exists, no Products.500.html
        file_put_contents(
            $this->rootDir . '/app/Domain/Query/Products.html',
            '<p>{{ $name }}</p>',
        );
        $formatter = $this->createFormatter();

        // Act — pass statusCode 500, no matching template
        $result = $formatter->format(
            ['name' => 'Arcanum'],
            'App\\Domain\\Query\\Products',
            500,
        );

        // Assert — falls back to default template
        $this->assertStringContainsString('<p>Arcanum</p>', $result);
    }

    public function testFormatWithStatusCodeZeroUsesDefaultTemplate(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Domain/Query/Products.html',
            '<p>{{ $name }}</p>',
        );
        $formatter = $this->createFormatter();

        // Act — statusCode 0 means "use default" (same as omitting it)
        $result = $formatter->format(
            ['name' => 'Arcanum'],
            'App\\Domain\\Query\\Products',
            0,
        );

        // Assert
        $this->assertStringContainsString('<p>Arcanum</p>', $result);
    }
}
