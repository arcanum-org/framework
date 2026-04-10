<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo;

use Arcanum\Parchment\FileSystem;
use Arcanum\Parchment\Reader;
use Arcanum\Parchment\Writer;
use Arcanum\Shodo\ElementExtraction;
use Arcanum\Shodo\TemplateAnalyzer;
use Arcanum\Shodo\TemplateCache;
use Arcanum\Shodo\TemplateCompiler;
use Arcanum\Shodo\TemplateEngine;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(TemplateEngine::class)]
#[UsesClass(TemplateCompiler::class)]
#[UsesClass(TemplateCache::class)]
#[UsesClass(TemplateAnalyzer::class)]
#[UsesClass(ElementExtraction::class)]
#[UsesClass(Reader::class)]
#[UsesClass(Writer::class)]
#[UsesClass(FileSystem::class)]
final class TemplateEngineTest extends TestCase
{
    private string $templateDir;
    private string $cacheDir;

    private \Closure $escape;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/arcanum_template_engine_test_' . uniqid();
        $this->templateDir = $base . '/templates';
        $this->cacheDir = $base . '/cache';
        mkdir($this->templateDir, 0755, true);
        mkdir($this->cacheDir, 0755, true);

        $this->escape = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(dirname($this->templateDir));
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

    private function createEngine(
        bool $debug = false,
        ?LoggerInterface $logger = null,
    ): TemplateEngine {
        return new TemplateEngine(
            compiler: new TemplateCompiler(),
            cache: new TemplateCache($this->cacheDir),
            debug: $debug,
            logger: $logger,
        );
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function vars(array $extra = []): array
    {
        return array_merge([
            '__escape' => $this->escape,
            '__helpers' => [],
        ], $extra);
    }

    // -----------------------------------------------------------
    // render()
    // -----------------------------------------------------------

    public function testRenderCompilesAndExecutesTemplate(): void
    {
        // Arrange
        $templatePath = $this->templateDir . '/page.html';
        file_put_contents($templatePath, '<h1>{{ $title }}</h1>');
        $engine = $this->createEngine();

        // Act
        $result = $engine->render($templatePath, $this->vars(['title' => 'Welcome']));

        // Assert
        $this->assertStringContainsString('<h1>Welcome</h1>', $result);
    }

    public function testRenderUsesCacheOnSecondCall(): void
    {
        // Arrange
        $templatePath = $this->templateDir . '/cached.html';
        file_put_contents($templatePath, '<p>{{ $name }}</p>');
        $engine = $this->createEngine();

        // Act — first call compiles and caches
        $engine->render($templatePath, $this->vars(['name' => 'first']));

        // Assert — cache entry is fresh
        $cache = new TemplateCache($this->cacheDir);
        $this->assertTrue($cache->isFresh($templatePath));

        // Act — second call uses cache
        $result = $engine->render($templatePath, $this->vars(['name' => 'second']));

        // Assert
        $this->assertStringContainsString('<p>second</p>', $result);
    }

    // -----------------------------------------------------------
    // renderFragment()
    // -----------------------------------------------------------

    public function testRenderFragmentSkipsLayout(): void
    {
        // Arrange — layout wraps content in <html>, child extends it
        $layoutPath = $this->templateDir . '/layout.html';
        file_put_contents($layoutPath, "<html>{{ yield 'content' }}</html>");

        $childPath = $this->templateDir . '/child.html';
        file_put_contents(
            $childPath,
            "{{ extends 'layout' }}\n{{ section 'content' }}<p>Inner</p>{{ endsection }}",
        );
        $engine = $this->createEngine();

        // Act
        $result = $engine->renderFragment($childPath, $this->vars());

        // Assert — content section only, no layout wrapper
        $this->assertStringContainsString('<p>Inner</p>', $result);
        $this->assertStringNotContainsString('<html>', $result);
    }

    // -----------------------------------------------------------
    // renderElement()
    // -----------------------------------------------------------

    public function testRenderElementExtractsById(): void
    {
        // Arrange — template has two sibling divs with ids
        $templatePath = $this->templateDir . '/page.html';
        file_put_contents(
            $templatePath,
            '<div id="main"><p>{{ $title }}</p></div>'
                . '<div id="sidebar"><p>{{ $greeting }}</p></div>',
        );
        $engine = $this->createEngine();

        // Act
        $result = $engine->renderElement(
            $templatePath,
            'sidebar',
            $this->vars(['greeting' => 'Hello']),
        );

        // Assert — only the sidebar element (outerHTML)
        $this->assertStringContainsString('<div id="sidebar">', $result);
        $this->assertStringContainsString('<p>Hello</p>', $result);
        $this->assertStringNotContainsString('<div id="main">', $result);
    }

    public function testRenderElementUsesCache(): void
    {
        // Arrange
        $templatePath = $this->templateDir . '/cached-el.html';
        file_put_contents(
            $templatePath,
            '<div id="box"><span>{{ $name }}</span></div>',
        );
        $engine = $this->createEngine();

        // Act — first call compiles and caches
        $engine->renderElement(
            $templatePath,
            'box',
            $this->vars(['name' => 'first']),
        );

        // Assert — cache entry is fresh for this element
        $cache = new TemplateCache($this->cacheDir);
        $this->assertTrue($cache->isFresh($templatePath, 'box'));

        // Act — second call uses cache
        $result = $engine->renderElement(
            $templatePath,
            'box',
            $this->vars(['name' => 'second']),
        );

        // Assert
        $this->assertStringContainsString('second', $result);
    }

    public function testRenderElementFallsBackToContentSection(): void
    {
        // Arrange — template uses layout; requested id does not exist
        $layoutPath = $this->templateDir . '/layout.html';
        file_put_contents($layoutPath, "<html>{{ yield 'content' }}</html>");

        $childPath = $this->templateDir . '/child.html';
        file_put_contents(
            $childPath,
            "{{ extends 'layout' }}\n{{ section 'title' }}T{{ endsection }}\n"
                . "{{ section 'content' }}<p>Full content</p>{{ endsection }}",
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('not found'),
                $this->callback(fn(array $ctx) => ($ctx['id'] ?? '') === 'nonexistent'),
            );

        $engine = $this->createEngine(logger: $logger);

        // Act
        $result = $engine->renderElement(
            $childPath,
            'nonexistent',
            $this->vars(),
        );

        // Assert — falls back to content section, no layout wrapper
        $this->assertStringContainsString('<p>Full content</p>', $result);
        $this->assertStringNotContainsString('<html>', $result);
    }

    public function testRenderElementEscapesOutput(): void
    {
        // Arrange
        $templatePath = $this->templateDir . '/escape.html';
        file_put_contents(
            $templatePath,
            '<div id="box"><p>{{ $name }}</p></div>',
        );
        $engine = $this->createEngine();

        // Act
        $result = $engine->renderElement(
            $templatePath,
            'box',
            $this->vars(['name' => '<script>xss</script>']),
        );

        // Assert
        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>xss', $result);
    }

    // -----------------------------------------------------------
    // renderSource()
    // -----------------------------------------------------------

    public function testRenderSourceCompilesArbitrarySource(): void
    {
        // Arrange
        $engine = $this->createEngine();

        // Act
        $result = $engine->renderSource(
            '<p>{{ $name }}</p>',
            $this->templateDir,
            $this->vars(['name' => 'Alice']),
        );

        // Assert
        $this->assertSame('<p>Alice</p>', $result);
    }

    // -----------------------------------------------------------
    // Selective closure resolution
    // -----------------------------------------------------------

    public function testClosuresResolvedSelectivelyOnRenderElement(): void
    {
        // Arrange — template has two sections, we extract only "sidebar"
        $templatePath = $this->templateDir . '/closures.html';
        file_put_contents(
            $templatePath,
            '<div id="main"><p>{{ $title }}</p></div>'
                . '<div id="sidebar"><p>{{ $greeting }}</p></div>',
        );
        $engine = $this->createEngine();
        $invoked = [];

        // Act — render only the sidebar element
        $result = $engine->renderElement(
            $templatePath,
            'sidebar',
            $this->vars([
                'title' => function () use (&$invoked) {
                    $invoked[] = 'title';
                    return 'Page Title';
                },
                'greeting' => function () use (&$invoked) {
                    $invoked[] = 'greeting';
                    return 'Hello';
                },
            ]),
        );

        // Assert — only 'greeting' invoked (referenced in sidebar),
        // 'title' skipped (only in #main, not rendered)
        $this->assertSame(['greeting'], $invoked);
        $this->assertStringContainsString('<p>Hello</p>', $result);
        $this->assertStringNotContainsString('Page Title', $result);
    }

    public function testClosuresResolvedSelectivelyOnRenderSource(): void
    {
        // Arrange
        $engine = $this->createEngine();
        $invoked = [];

        // Act — source only references $name
        $result = $engine->renderSource(
            '<p>{{ $name }}</p>',
            $this->templateDir,
            $this->vars([
                'name' => function () use (&$invoked) {
                    $invoked[] = 'name';
                    return 'Bob';
                },
                'unused' => function () use (&$invoked) {
                    $invoked[] = 'unused';
                    return 'should not run';
                },
            ]),
        );

        // Assert — only 'name' invoked
        $this->assertSame(['name'], $invoked);
        $this->assertStringContainsString('<p>Bob</p>', $result);
    }

    public function testClosuresFullyResolvedOnRender(): void
    {
        // Arrange
        $templatePath = $this->templateDir . '/full.html';
        file_put_contents(
            $templatePath,
            '<p>{{ $name }}</p><p>{{ $count }}</p>',
        );
        $engine = $this->createEngine();
        $invoked = [];

        // Act
        $result = $engine->render(
            $templatePath,
            $this->vars([
                'name' => function () use (&$invoked) {
                    $invoked[] = 'name';
                    return 'Alice';
                },
                'count' => function () use (&$invoked) {
                    $invoked[] = 'count';
                    return '42';
                },
            ]),
        );

        // Assert — both closures invoked on full render
        $this->assertSame(['name', 'count'], $invoked);
        $this->assertStringContainsString('<p>Alice</p>', $result);
        $this->assertStringContainsString('<p>42</p>', $result);
    }
}
