<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper;

use Arcanum\Flow\River\LazyResource;
use Arcanum\Flow\River\Stream;
use Arcanum\Hyper\Headers;
use Arcanum\Hyper\MarkdownResponseRenderer;
use Arcanum\Hyper\Message;
use Arcanum\Hyper\Response;
use Arcanum\Hyper\StatusCode;
use Arcanum\Hyper\Version;
use Arcanum\Parchment\FileSystem;
use Arcanum\Parchment\Reader;
use Arcanum\Parchment\Writer;
use Arcanum\Shodo\Formatters\MarkdownFallbackFormatter;
use Arcanum\Shodo\Formatters\MarkdownFormatter;
use Arcanum\Shodo\TemplateCache;
use Arcanum\Shodo\TemplateCompiler;
use Arcanum\Shodo\TemplateEngine;
use Arcanum\Shodo\TemplateResolver;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(MarkdownResponseRenderer::class)]
#[UsesClass(MarkdownFormatter::class)]
#[UsesClass(MarkdownFallbackFormatter::class)]
#[UsesClass(TemplateResolver::class)]
#[UsesClass(TemplateCompiler::class)]
#[UsesClass(TemplateEngine::class)]
#[UsesClass(TemplateCache::class)]
#[UsesClass(Reader::class)]
#[UsesClass(Writer::class)]
#[UsesClass(FileSystem::class)]
#[UsesClass(Headers::class)]
#[UsesClass(LazyResource::class)]
#[UsesClass(Message::class)]
#[UsesClass(Response::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(Stream::class)]
#[UsesClass(Version::class)]
final class MarkdownResponseRendererTest extends TestCase
{
    public function testRenderReturnsResponseWithMarkdownContentType(): void
    {
        // Arrange
        $formatter = $this->createFormatter();
        $renderer = new MarkdownResponseRenderer($formatter);

        // Act
        $response = $renderer->render(['name' => 'Arcanum']);

        // Assert
        $this->assertSame('text/markdown; charset=UTF-8', $response->getHeaderLine('Content-Type'));
    }

    public function testRenderReturns200Status(): void
    {
        // Arrange
        $formatter = $this->createFormatter();
        $renderer = new MarkdownResponseRenderer($formatter);

        // Act
        $response = $renderer->render(['name' => 'Arcanum']);

        // Assert
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRenderBodyContainsFormattedMarkdown(): void
    {
        // Arrange
        $formatter = $this->createFormatter();
        $renderer = new MarkdownResponseRenderer($formatter);

        // Act
        $response = $renderer->render(['name' => 'Arcanum', 'version' => '1.0']);
        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();

        // Assert
        $this->assertStringContainsString('**name:** Arcanum', $body);
        $this->assertStringContainsString('**version:** 1.0', $body);
    }

    public function testRenderSetsContentLength(): void
    {
        // Arrange
        $formatter = $this->createFormatter();
        $renderer = new MarkdownResponseRenderer($formatter);

        // Act
        $response = $renderer->render('Hello');
        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();

        // Assert
        $this->assertSame((string) strlen($body), $response->getHeaderLine('Content-Length'));
    }

    private function createFormatter(): MarkdownFormatter
    {
        $rootDir = sys_get_temp_dir() . '/arcanum_md_renderer_test_' . uniqid();
        mkdir($rootDir, 0755, true);

        $resolver = new TemplateResolver($rootDir, 'App', extension: 'md');
        $compiler = new TemplateCompiler();
        $cache = new TemplateCache('');
        $fallback = new MarkdownFallbackFormatter();

        $engine = new TemplateEngine(compiler: $compiler, cache: $cache);
        return new MarkdownFormatter($resolver, $engine, $fallback);
    }
}
