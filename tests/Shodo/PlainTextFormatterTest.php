<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo;

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

#[CoversClass(PlainTextFormatter::class)]
#[UsesClass(TemplateResolver::class)]
#[UsesClass(TemplateCompiler::class)]
#[UsesClass(TemplateCache::class)]
#[UsesClass(PlainTextFallback::class)]
#[UsesClass(Reader::class)]
#[UsesClass(Writer::class)]
#[UsesClass(FileSystem::class)]
final class PlainTextFormatterTest extends TestCase
{
    private string $rootDir;
    private string $cacheDir;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/arcanum_txt_formatter_test_' . uniqid();
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

    private function createFormatter(): PlainTextFormatter
    {
        $resolver = new TemplateResolver($this->rootDir, 'App', extension: 'txt');
        $compiler = new TemplateCompiler();
        $cache = new TemplateCache($this->cacheDir);
        $fallback = new PlainTextFallback();

        return new PlainTextFormatter($resolver, $compiler, $cache, $fallback);
    }

    public function testFormatReturnsNonEmptyOutput(): void
    {
        // Arrange
        $formatter = $this->createFormatter();

        // Act
        $result = $formatter->format(['key' => 'value']);

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
        $this->assertStringContainsString('name: Arcanum', $result);
    }

    public function testFormatsFallbackWhenDtoClassIsEmpty(): void
    {
        // Arrange
        $formatter = $this->createFormatter();

        // Act
        $result = $formatter->format(['status' => 'ok']);

        // Assert
        $this->assertStringContainsString('status: ok', $result);
    }

    public function testFormatsTemplateWithArrayData(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.txt',
            'Welcome to {{ $name }}!',
        );
        $formatter = $this->createFormatter();

        // Act
        $result = $formatter->format(['name' => 'Arcanum'], 'App\\Pages\\Index');

        // Assert
        $this->assertSame('Welcome to Arcanum!', $result);
    }

    public function testTemplateDoesNotEscapeHtmlCharacters(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.txt',
            'Value: {{ $value }}',
        );
        $formatter = $this->createFormatter();

        // Act
        $result = $formatter->format(['value' => '<b>bold</b>'], 'App\\Pages\\Index');

        // Assert — no HTML escaping in plain text
        $this->assertSame('Value: <b>bold</b>', $result);
    }

    public function testFormatsTemplateWithForeach(): void
    {
        // Arrange
        file_put_contents(
            $this->rootDir . '/app/Pages/Index.txt',
            '{{ foreach($items as $item) }}- {{ $item }}' . "\n" . '{{ endforeach }}',
        );
        $formatter = $this->createFormatter();

        // Act
        $result = $formatter->format(['items' => ['a', 'b']], 'App\\Pages\\Index');

        // Assert
        $this->assertStringContainsString('- a', $result);
        $this->assertStringContainsString('- b', $result);
    }

    public function testFormatsTemplateWithObjectData(): void
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
        $formatter = $this->createFormatter();

        // Act
        $result = $formatter->format($obj, 'App\\Pages\\Index');

        // Assert
        $this->assertSame('Arcanum v1', $result);
    }
}
