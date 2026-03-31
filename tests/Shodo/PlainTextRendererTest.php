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
use Arcanum\Shodo\PlainTextFallback;
use Arcanum\Shodo\PlainTextRenderer;
use Arcanum\Shodo\TemplateCache;
use Arcanum\Shodo\TemplateCompiler;
use Arcanum\Shodo\TemplateResolver;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(PlainTextRenderer::class)]
#[UsesClass(TemplateResolver::class)]
#[UsesClass(TemplateCompiler::class)]
#[UsesClass(TemplateCache::class)]
#[UsesClass(PlainTextFallback::class)]
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
final class PlainTextRendererTest extends TestCase
{
    private string $rootDir;
    private string $cacheDir;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/arcanum_txt_renderer_test_' . uniqid();
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

    private function createRenderer(): PlainTextRenderer
    {
        $resolver = new TemplateResolver($this->rootDir, 'App', extension: 'txt');
        $compiler = new TemplateCompiler();
        $cache = new TemplateCache($this->cacheDir);
        $fallback = new PlainTextFallback();

        return new PlainTextRenderer($resolver, $compiler, $cache, $fallback);
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
        $response = $renderer->render(['key' => 'value']);

        // Assert
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testRenderSetsPlainTextContentType(): void
    {
        // Arrange
        $renderer = $this->createRenderer();

        // Act
        $response = $renderer->render(['key' => 'value']);

        // Assert
        $this->assertSame('text/plain; charset=UTF-8', $response->getHeaderLine('Content-Type'));
    }

    public function testRenderSetsContentLength(): void
    {
        // Arrange
        $renderer = $this->createRenderer();

        // Act
        $response = $renderer->render(['key' => 'value']);
        $body = $this->readBody($response);

        // Assert
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
        $body = $this->readBody($renderer->render(['name' => 'Arcanum'], 'App\\Domain\\Query\\Missing'));

        // Assert
        $this->assertStringContainsString('name: Arcanum', $body);
    }

    public function testRendersFallbackWhenDtoClassIsEmpty(): void
    {
        // Arrange
        $renderer = $this->createRenderer();

        // Act
        $body = $this->readBody($renderer->render(['status' => 'ok']));

        // Assert
        $this->assertStringContainsString('status: ok', $body);
    }

    public function testRendersTemplateWithArrayData(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.txt',
            'Welcome to {{ $name }}!',
        );
        $renderer = $this->createRenderer();

        // Act
        $body = $this->readBody($renderer->render(['name' => 'Arcanum'], 'App\\Pages\\Index'));

        // Assert
        $this->assertSame('Welcome to Arcanum!', $body);
    }

    public function testTemplateDoesNotEscapeHtmlCharacters(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.txt',
            'Value: {{ $value }}',
        );
        $renderer = $this->createRenderer();

        // Act
        $body = $this->readBody($renderer->render(['value' => '<b>bold</b>'], 'App\\Pages\\Index'));

        // Assert — no HTML escaping in plain text
        $this->assertSame('Value: <b>bold</b>', $body);
    }

    public function testRendersTemplateWithForeach(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.txt',
            '{{ foreach($items as $item) }}- {{ $item }}' . "\n" . '{{ endforeach }}',
        );
        $renderer = $this->createRenderer();

        // Act
        $body = $this->readBody($renderer->render(['items' => ['a', 'b']], 'App\\Pages\\Index'));

        // Assert
        $this->assertStringContainsString('- a', $body);
        $this->assertStringContainsString('- b', $body);
    }

    public function testRendersTemplateWithObjectData(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.txt',
            '{{ $name }} v{{ $version }}',
        );
        $obj = new class {
            public string $name = 'Arcanum';
            public int $version = 1;
        };
        $renderer = $this->createRenderer();

        // Act
        $body = $this->readBody($renderer->render($obj, 'App\\Pages\\Index'));

        // Assert
        $this->assertSame('Arcanum v1', $body);
    }
}
