<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper;

use Arcanum\Flow\River\LazyResource;
use Arcanum\Flow\River\Stream;
use Arcanum\Flow\River\StreamResource;
use Arcanum\Gather\IgnoreCaseRegistry;
use Arcanum\Gather\Registry;
use Arcanum\Hyper\Headers;
use Arcanum\Hyper\Message;
use Arcanum\Hyper\PlainTextResponseRenderer;
use Arcanum\Hyper\Response;
use Arcanum\Hyper\StatusCode;
use Arcanum\Hyper\Version;
use Arcanum\Parchment\FileSystem;
use Arcanum\Parchment\Reader;
use Arcanum\Parchment\Writer;
use Arcanum\Shodo\PlainTextFallback;
use Arcanum\Shodo\PlainTextFormatter;
use Arcanum\Shodo\TemplateCache;
use Arcanum\Shodo\TemplateCompiler;
use Arcanum\Shodo\TemplateResolver;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(PlainTextResponseRenderer::class)]
#[UsesClass(PlainTextFormatter::class)]
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
final class PlainTextResponseRendererTest extends TestCase
{
    private string $rootDir;
    private string $cacheDir;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/arcanum_txt_response_renderer_test_' . uniqid();
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

    private function createRenderer(): PlainTextResponseRenderer
    {
        $formatter = new PlainTextFormatter(
            resolver: new TemplateResolver($this->rootDir, 'App', extension: 'txt'),
            compiler: new TemplateCompiler(),
            cache: new TemplateCache($this->cacheDir),
            fallback: new PlainTextFallback(),
        );
        return new PlainTextResponseRenderer($formatter);
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

    public function testRendersTemplateWhenAvailable(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.txt',
            'Hello {{ $name }}!',
        );
        $renderer = $this->createRenderer();

        // Act
        $body = $this->readBody($renderer->render(['name' => 'World'], 'App\\Pages\\Index'));

        // Assert
        $this->assertSame('Hello World!', $body);
    }

    public function testRendersFallbackWhenNoTemplate(): void
    {
        // Arrange
        $renderer = $this->createRenderer();

        // Act
        $body = $this->readBody($renderer->render(['status' => 'ok'], 'App\\Missing'));

        // Assert
        $this->assertStringContainsString('status: ok', $body);
    }

    public function testBodyMatchesFormatterOutput(): void
    {
        // Arrange
        $formatter = new PlainTextFormatter(
            resolver: new TemplateResolver($this->rootDir, 'App', extension: 'txt'),
            compiler: new TemplateCompiler(),
            cache: new TemplateCache($this->cacheDir),
            fallback: new PlainTextFallback(),
        );
        $renderer = new PlainTextResponseRenderer($formatter);
        $data = ['version' => '1.0'];

        // Act
        $expected = $formatter->format($data);
        $body = $this->readBody($renderer->render($data));

        // Assert
        $this->assertSame($expected, $body);
    }
}
