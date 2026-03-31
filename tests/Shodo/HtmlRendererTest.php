<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo;

use Arcanum\Flow\River\LazyResource;
use Arcanum\Flow\River\Stream;
use Arcanum\Flow\River\StreamResource;
use Arcanum\Gather\IgnoreCaseRegistry;
use Arcanum\Gather\Registry;
use Arcanum\Hyper\Headers;
use Arcanum\Hyper\Message;
use Arcanum\Hyper\Response;
use Arcanum\Hyper\StatusCode;
use Arcanum\Hyper\Version;
use Arcanum\Parchment\FileSystem;
use Arcanum\Parchment\Reader;
use Arcanum\Parchment\Writer;
use Arcanum\Shodo\HtmlFallback;
use Arcanum\Shodo\HtmlRenderer;
use Arcanum\Shodo\TemplateCache;
use Arcanum\Shodo\TemplateCompiler;
use Arcanum\Shodo\TemplateResolver;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(HtmlRenderer::class)]
#[UsesClass(TemplateResolver::class)]
#[UsesClass(TemplateCompiler::class)]
#[UsesClass(TemplateCache::class)]
#[UsesClass(HtmlFallback::class)]
#[UsesClass(Response::class)]
#[UsesClass(Message::class)]
#[UsesClass(Headers::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(Version::class)]
#[UsesClass(Stream::class)]
#[UsesClass(LazyResource::class)]
#[UsesClass(StreamResource::class)]
#[UsesClass(IgnoreCaseRegistry::class)]
#[UsesClass(Registry::class)]
#[UsesClass(Reader::class)]
#[UsesClass(Writer::class)]
#[UsesClass(FileSystem::class)]
final class HtmlRendererTest extends TestCase
{
    private string $rootDir;
    private string $cacheDir;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/arcanum_html_renderer_test_' . uniqid();
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

    private function createRenderer(string $cacheDir = ''): HtmlRenderer
    {
        $resolver = new TemplateResolver($this->rootDir, 'App');
        $compiler = new TemplateCompiler();
        $cache = new TemplateCache($cacheDir ?: $this->cacheDir);
        $fallback = new HtmlFallback();

        return new HtmlRenderer($resolver, $compiler, $cache, $fallback);
    }

    private function readBody(ResponseInterface $response): string
    {
        $body = $response->getBody();
        $body->rewind();
        return $body->getContents();
    }

    public function testRenderReturnsResponseInterface(): void
    {
        // Arrange
        $renderer = $this->createRenderer();

        // Act
        $response = $renderer->render(['key' => 'value'], 'App\\Domain\\Query\\Health');

        // Assert
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testRenderSetsHtmlContentType(): void
    {
        // Arrange
        $renderer = $this->createRenderer();

        // Act
        $response = $renderer->render(['key' => 'value']);

        // Assert
        $this->assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
    }

    public function testRenderSetsContentLength(): void
    {
        // Arrange
        $renderer = $this->createRenderer();

        // Act
        $response = $renderer->render(['key' => 'value']);

        // Assert
        $body = $this->readBody($response);
        $this->assertSame((string) strlen($body), $response->getHeaderLine('Content-Length'));
    }

    public function testRenderReturns200Status(): void
    {
        // Arrange
        $renderer = $this->createRenderer();

        // Act
        $response = $renderer->render(['key' => 'value']);

        // Assert
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRendersFallbackWhenNoTemplate(): void
    {
        // Arrange
        $renderer = $this->createRenderer();

        // Act
        $response = $renderer->render(['name' => 'Arcanum'], 'App\\Domain\\Query\\Missing');
        $body = $this->readBody($response);

        // Assert
        $this->assertStringContainsString('<!DOCTYPE html>', $body);
        $this->assertStringContainsString('<dt>name</dt>', $body);
        $this->assertStringContainsString('Arcanum', $body);
    }

    public function testRendersFallbackWhenDtoClassIsEmpty(): void
    {
        // Arrange
        $renderer = $this->createRenderer();

        // Act
        $response = $renderer->render(['status' => 'ok']);
        $body = $this->readBody($response);

        // Assert
        $this->assertStringContainsString('<dt>status</dt>', $body);
        $this->assertStringContainsString('ok', $body);
    }

    public function testRendersTemplateWithArrayData(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<h1>{{ $title }}</h1>',
        );
        $renderer = $this->createRenderer();

        // Act
        $response = $renderer->render(['title' => 'Welcome'], 'App\\Pages\\Index');
        $body = $this->readBody($response);

        // Assert
        $this->assertStringContainsString('<h1>Welcome</h1>', $body);
    }

    public function testRendersTemplateWithObjectData(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<p>{{ $name }}</p>',
        );
        $obj = new class {
            public string $name = 'Arcanum';
        };
        $renderer = $this->createRenderer();

        // Act
        $response = $renderer->render($obj, 'App\\Pages\\Index');
        $body = $this->readBody($response);

        // Assert
        $this->assertStringContainsString('<p>Arcanum</p>', $body);
    }

    public function testRendersTemplateWithScalarData(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<p>{{ $data }}</p>',
        );
        $renderer = $this->createRenderer();

        // Act
        $response = $renderer->render('hello', 'App\\Pages\\Index');
        $body = $this->readBody($response);

        // Assert
        $this->assertStringContainsString('<p>hello</p>', $body);
    }

    public function testTemplateEscapesOutput(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<p>{{ $name }}</p>',
        );
        $renderer = $this->createRenderer();

        // Act
        $response = $renderer->render(['name' => '<script>xss</script>'], 'App\\Pages\\Index');
        $body = $this->readBody($response);

        // Assert
        $this->assertStringContainsString('&lt;script&gt;xss&lt;/script&gt;', $body);
        $this->assertStringNotContainsString('<script>', $body);
    }

    public function testTemplateRawOutput(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<div>{{! $html !}}</div>',
        );
        $renderer = $this->createRenderer();

        // Act
        $response = $renderer->render(['html' => '<b>bold</b>'], 'App\\Pages\\Index');
        $body = $this->readBody($response);

        // Assert
        $this->assertStringContainsString('<div><b>bold</b></div>', $body);
    }

    public function testTemplateWithForeach(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '{{ foreach($items as $item) }}<li>{{ $item }}</li>{{ endforeach }}',
        );
        $renderer = $this->createRenderer();

        // Act
        $response = $renderer->render(['items' => ['a', 'b', 'c']], 'App\\Pages\\Index');
        $body = $this->readBody($response);

        // Assert
        $this->assertStringContainsString('<li>a</li>', $body);
        $this->assertStringContainsString('<li>b</li>', $body);
        $this->assertStringContainsString('<li>c</li>', $body);
    }

    public function testTemplateWithConditional(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '{{ if($show) }}<p>visible</p>{{ else }}<p>hidden</p>{{ endif }}',
        );
        $renderer = $this->createRenderer();

        // Act
        $shown = $renderer->render(['show' => true], 'App\\Pages\\Index');
        $hidden = $renderer->render(['show' => false], 'App\\Pages\\Index');

        // Assert
        $this->assertStringContainsString('<p>visible</p>', $this->readBody($shown));
        $this->assertStringContainsString('<p>hidden</p>', $this->readBody($hidden));
    }

    public function testCachedTemplateIsUsedOnSecondRender(): void
    {
        // Arrange
        $templatePath = $this->rootDir . '/app/Pages/Index.html';
        file_put_contents($templatePath, '<p>{{ $name }}</p>');
        $renderer = $this->createRenderer();

        // Act — first render compiles and caches
        $renderer->render(['name' => 'first'], 'App\\Pages\\Index');

        // Verify cache file exists
        $cache = new TemplateCache($this->cacheDir);
        $this->assertTrue($cache->isFresh($templatePath));

        // Act — second render uses cache
        $response = $renderer->render(['name' => 'second'], 'App\\Pages\\Index');
        $body = $this->readBody($response);

        // Assert
        $this->assertStringContainsString('<p>second</p>', $body);
    }

    public function testRendersWithoutCaching(): void
    {
        // Arrange — empty cache dir disables caching
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.html',
            '<p>{{ $name }}</p>',
        );
        $renderer = $this->createRenderer(cacheDir: '');

        // Act
        $response = $renderer->render(['name' => 'Arcanum'], 'App\\Pages\\Index');
        $body = $this->readBody($response);

        // Assert
        $this->assertStringContainsString('<p>Arcanum</p>', $body);
    }
}
