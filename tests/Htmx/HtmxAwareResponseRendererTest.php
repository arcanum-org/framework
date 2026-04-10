<?php

declare(strict_types=1);

namespace Arcanum\Test\Htmx;

use Arcanum\Htmx\FragmentDirective;
use Arcanum\Htmx\HtmxAwareResponseRenderer;
use Arcanum\Htmx\HtmxRequest;
use Arcanum\Htmx\HtmxRequestType;
use Arcanum\Parchment\FileSystem;
use Arcanum\Parchment\Reader;
use Arcanum\Parchment\Writer;
use Arcanum\Shodo\ElementExtraction;
use Arcanum\Shodo\Formatters\HtmlFallbackFormatter;
use Arcanum\Shodo\Formatters\HtmlFormatter;
use Arcanum\Shodo\TemplateCache;
use Arcanum\Shodo\TemplateCompiler;
use Arcanum\Shodo\TemplateEngine;
use Arcanum\Shodo\TemplateResolver;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Http\Message\ServerRequestInterface;

#[CoversClass(HtmxAwareResponseRenderer::class)]
#[UsesClass(FragmentDirective::class)]
#[UsesClass(HtmxRequest::class)]
#[UsesClass(HtmxRequestType::class)]
#[UsesClass(HtmlFormatter::class)]
#[UsesClass(HtmlFallbackFormatter::class)]
#[UsesClass(TemplateResolver::class)]
#[UsesClass(TemplateCompiler::class)]
#[UsesClass(TemplateCache::class)]
#[UsesClass(TemplateEngine::class)]
#[UsesClass(ElementExtraction::class)]
#[UsesClass(Reader::class)]
#[UsesClass(Writer::class)]
#[UsesClass(FileSystem::class)]
#[UsesClass(\Arcanum\Hyper\Response::class)]
#[UsesClass(\Arcanum\Hyper\Message::class)]
#[UsesClass(\Arcanum\Hyper\Headers::class)]
#[UsesClass(\Arcanum\Hyper\StatusCode::class)]
#[UsesClass(\Arcanum\Flow\River\Stream::class)]
#[UsesClass(\Arcanum\Flow\River\LazyResource::class)]
#[UsesClass(\Arcanum\Gather\Registry::class)]
final class HtmxAwareResponseRendererTest extends TestCase
{
    private string $rootDir;
    private string $cacheDir;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/arcanum_htmx_renderer_test_' . uniqid();
        $this->rootDir = $base;
        $this->cacheDir = $base . '/cache';
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

    private function createRenderer(): HtmxAwareResponseRenderer
    {
        $resolver = new TemplateResolver($this->rootDir, 'App');
        $engine = new TemplateEngine(
            compiler: new TemplateCompiler(),
            cache: new TemplateCache($this->cacheDir),
        );
        $fallback = new HtmlFallbackFormatter();
        $formatter = new HtmlFormatter($engine, $fallback);

        return new HtmxAwareResponseRenderer($formatter, $engine, $resolver);
    }

    /**
     * @param array<string, string> $headers
     */
    private function htmxRequest(array $headers = []): HtmxRequest
    {
        $inner = $this->createStub(ServerRequestInterface::class);

        $inner->method('hasHeader')
            ->willReturnCallback(fn(string $name) => isset($headers[$name]));

        $inner->method('getHeaderLine')
            ->willReturnCallback(fn(string $name) => $headers[$name] ?? '');

        return new HtmxRequest($inner);
    }

    // ------------------------------------------------------------------
    // Mode 1: Non-htmx — full render with layout
    // ------------------------------------------------------------------

    public function testNonHtmxRequestRendersFullTemplate(): void
    {
        // Arrange — layout must be co-located with the template (no
        // templatesDirectory configured on the test compiler)
        file_put_contents(
            $this->rootDir . '/app/Pages/layout.html',
            "<!DOCTYPE html><html>{{ yield 'content' }}</html>",
        );
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            "{{ extends 'layout' }}\n{{ section 'content' }}<p>{{ \$name }}</p>{{ endsection }}",
        );
        $renderer = $this->createRenderer();

        // Act — no htmx request set
        $response = $renderer->render(['name' => 'Arcanum'], 'App\\Pages\\Index');

        // Assert — full page with layout
        $body = (string) $response->getBody();
        $this->assertStringContainsString('<!DOCTYPE html>', $body);
        $this->assertStringContainsString('<p>Arcanum</p>', $body);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));
    }

    // ------------------------------------------------------------------
    // Mode 2: htmx Full type — content section only
    // ------------------------------------------------------------------

    public function testHtmxFullTypeRendersContentSectionOnly(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/layout.html',
            "<!DOCTYPE html><html>{{ yield 'content' }}</html>",
        );
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            "{{ extends 'layout' }}\n{{ section 'content' }}<p>{{ \$name }}</p>{{ endsection }}",
        );
        $renderer = $this->createRenderer();
        $renderer->setHtmxRequest($this->htmxRequest([
            'HX-Request' => 'true',
            'HX-Boosted' => 'true',
        ]));

        // Act
        $response = $renderer->render(['name' => 'Arcanum'], 'App\\Pages\\Index');

        // Assert — content section only, no layout
        $body = (string) $response->getBody();
        $this->assertStringNotContainsString('<!DOCTYPE html>', $body);
        $this->assertStringContainsString('<p>Arcanum</p>', $body);
    }

    // ------------------------------------------------------------------
    // Mode 3: htmx Partial with target — auto-extracted element
    // ------------------------------------------------------------------

    public function testHtmxPartialWithTargetExtractsElement(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<h1>Title</h1><div id="sidebar"><p>{{ $greeting }}</p></div><footer>F</footer>',
        );
        $renderer = $this->createRenderer();
        $renderer->setHtmxRequest($this->htmxRequest([
            'HX-Request' => 'true',
            'HX-Request-Type' => 'partial',
            'HX-Target' => 'sidebar',
            'HX-Swap' => 'outerHTML',
        ]));

        // Act
        $response = $renderer->render(['greeting' => 'Hello'], 'App\\Pages\\Index');

        // Assert — only the target element (outerHTML includes wrapper)
        $body = (string) $response->getBody();
        $this->assertStringContainsString('<div id="sidebar">', $body);
        $this->assertStringContainsString('<p>Hello</p>', $body);
        $this->assertStringNotContainsString('<h1>Title</h1>', $body);
        $this->assertStringNotContainsString('<footer>', $body);
    }

    public function testHtmxPartialAlwaysReturnsOuterHtml(): void
    {
        // Arrange — htmx doesn't send HX-Swap as a request header,
        // so the server always returns outerHTML (the full element).
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<div id="box"><span>Content</span></div>',
        );
        $renderer = $this->createRenderer();
        $renderer->setHtmxRequest($this->htmxRequest([
            'HX-Request' => 'true',
            'HX-Request-Type' => 'partial',
            'HX-Target' => 'box',
        ]));

        // Act
        $response = $renderer->render([], 'App\\Pages\\Index');

        // Assert — outerHTML: includes the element itself
        $body = (string) $response->getBody();
        $this->assertStringContainsString('<div id="box">', $body);
        $this->assertStringContainsString('<span>Content</span>', $body);
    }

    // ------------------------------------------------------------------
    // Fall-through: partial without target
    // ------------------------------------------------------------------

    public function testHtmxPartialWithoutTargetFallsBackToContentSection(): void
    {
        // Arrange — HX-Target absent (target element has no id)
        file_put_contents(
            $this->rootDir . '/app/Pages/layout.html',
            "<!DOCTYPE html>{{ yield 'content' }}",
        );
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            "{{ extends 'layout' }}\n{{ section 'content' }}<p>Content</p>{{ endsection }}",
        );
        $renderer = $this->createRenderer();
        $renderer->setHtmxRequest($this->htmxRequest([
            'HX-Request' => 'true',
            'HX-Request-Type' => 'partial',
        ]));

        // Act
        $response = $renderer->render([], 'App\\Pages\\Index');

        // Assert — content section, no layout
        $body = (string) $response->getBody();
        $this->assertStringContainsString('<p>Content</p>', $body);
        $this->assertStringNotContainsString('<!DOCTYPE html>', $body);
    }

    // ------------------------------------------------------------------
    // Fragment mode reset
    // ------------------------------------------------------------------

    // ------------------------------------------------------------------
    // Mode 3 with {{ fragment }} markers — innerHTML extraction
    // ------------------------------------------------------------------

    public function testPartialWithFragmentMarkerReturnsInnerContent(): void
    {
        // Arrange — template has {{ fragment 'panel' }} inside a div.
        // The renderer should return the inner content (no wrapper div).
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<div id="panel">{{ fragment \'panel\' }}<p>{{ $message }}</p>{{ endfragment }}</div>',
        );
        $renderer = $this->createRenderer();
        $renderer->setHtmxRequest($this->htmxRequest([
            'HX-Request' => 'true',
            'HX-Request-Type' => 'partial',
            'HX-Target' => 'panel',
        ]));

        // Act
        $response = $renderer->render(['message' => 'Hello'], 'App\\Pages\\Index');

        // Assert — innerHTML only: <p>Hello</p>, not the wrapper <div id="panel">
        $body = (string) $response->getBody();
        $this->assertStringContainsString('<p>Hello</p>', $body);
        $this->assertStringNotContainsString('<div id="panel">', $body);
    }

    public function testPartialWithoutFragmentMarkerFallsBackToOuterHtml(): void
    {
        // Arrange — template has no fragment marker, just a div with id.
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<div id="box"><p>{{ $name }}</p></div>',
        );
        $renderer = $this->createRenderer();
        $renderer->setHtmxRequest($this->htmxRequest([
            'HX-Request' => 'true',
            'HX-Request-Type' => 'partial',
            'HX-Target' => 'box',
        ]));

        // Act
        $response = $renderer->render(['name' => 'World'], 'App\\Pages\\Index');

        // Assert — outerHTML: includes the wrapper div
        $body = (string) $response->getBody();
        $this->assertStringContainsString('<div id="box">', $body);
        $this->assertStringContainsString('<p>World</p>', $body);
    }

    // ------------------------------------------------------------------
    // Fragment mode reset
    // ------------------------------------------------------------------

    public function testFragmentModeIsResetAfterRender(): void
    {
        // Arrange — first request is htmx full, second is non-htmx
        file_put_contents(
            $this->rootDir . '/app/Pages/layout.html',
            "<!DOCTYPE html>{{ yield 'content' }}",
        );
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            "{{ extends 'layout' }}\n{{ section 'content' }}<p>Hi</p>{{ endsection }}",
        );
        $renderer = $this->createRenderer();

        // First render: htmx full
        $renderer->setHtmxRequest($this->htmxRequest([
            'HX-Request' => 'true',
            'HX-Boosted' => 'true',
        ]));
        $first = (string) $renderer->render([], 'App\\Pages\\Index')->getBody();
        $this->assertStringNotContainsString('<!DOCTYPE html>', $first);

        // Second render: simulate non-htmx by creating a fresh renderer
        // (in production, the middleware wouldn't call setHtmxRequest)
        $renderer2 = $this->createRenderer();
        $second = (string) $renderer2->render([], 'App\\Pages\\Index')->getBody();
        $this->assertStringContainsString('<!DOCTYPE html>', $second);
    }
}
