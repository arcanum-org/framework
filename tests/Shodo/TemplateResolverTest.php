<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo;

use Arcanum\Parchment\FileSystem;
use Arcanum\Shodo\TemplateResolver;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(TemplateResolver::class)]
#[UsesClass(FileSystem::class)]
final class TemplateResolverTest extends TestCase
{
    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir() . '/arcanum_resolver_test_' . uniqid();
        mkdir($this->rootDir . '/app/Domain/Shop/Query', 0755, true);
        mkdir($this->rootDir . '/app/Pages', 0755, true);
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

    public function testResolvesExistingTemplate(): void
    {
        // Arrange
        $templatePath = $this->rootDir . '/app/Domain/Shop/Query/Products.html';
        file_put_contents($templatePath, '<p>products</p>');
        $resolver = new TemplateResolver($this->rootDir, 'App');

        // Act
        $result = $resolver->resolve('App\\Domain\\Shop\\Query\\Products');

        // Assert
        $this->assertSame($templatePath, $result);
    }

    public function testReturnsNullWhenTemplateDoesNotExist(): void
    {
        // Arrange
        $resolver = new TemplateResolver($this->rootDir, 'App');

        // Act
        $result = $resolver->resolve('App\\Domain\\Shop\\Query\\Missing');

        // Assert
        $this->assertNull($result);
    }

    public function testReturnsNullForEmptyDtoClass(): void
    {
        // Arrange
        $resolver = new TemplateResolver($this->rootDir, 'App');

        // Act
        $result = $resolver->resolve('');

        // Assert
        $this->assertNull($result);
    }

    public function testReturnsNullWhenClassDoesNotMatchRootNamespace(): void
    {
        // Arrange
        $resolver = new TemplateResolver($this->rootDir, 'App');

        // Act
        $result = $resolver->resolve('Other\\Namespace\\Thing');

        // Assert
        $this->assertNull($result);
    }

    public function testResolvesPageTemplate(): void
    {
        // Arrange
        $templatePath = $this->rootDir . '/app/Pages/Index.html';
        file_put_contents($templatePath, '<h1>Welcome</h1>');
        $resolver = new TemplateResolver($this->rootDir, 'App');

        // Act
        $result = $resolver->resolve('App\\Pages\\Index');

        // Assert
        $this->assertSame($templatePath, $result);
    }

    public function testResolvesSingleSegmentClass(): void
    {
        // Arrange
        $templatePath = $this->rootDir . '/app/Dashboard.html';
        mkdir(dirname($templatePath), 0755, true);
        file_put_contents($templatePath, '<h1>Dashboard</h1>');
        $resolver = new TemplateResolver($this->rootDir, 'App');

        // Act
        $result = $resolver->resolve('App\\Dashboard');

        // Assert
        $this->assertSame($templatePath, $result);
    }

    public function testResolvesDeeplyNestedClass(): void
    {
        // Arrange
        $dir = $this->rootDir . '/app/Domain/Shop/Catalog/Query';
        mkdir($dir, 0755, true);
        $templatePath = $dir . '/Featured.html';
        file_put_contents($templatePath, '<p>featured</p>');
        $resolver = new TemplateResolver($this->rootDir, 'App');

        // Act
        $result = $resolver->resolve('App\\Domain\\Shop\\Catalog\\Query\\Featured');

        // Assert
        $this->assertSame($templatePath, $result);
    }

    public function testCustomExtension(): void
    {
        // Arrange
        $templatePath = $this->rootDir . '/app/Pages/Index.txt';
        file_put_contents($templatePath, 'plain text');
        $resolver = new TemplateResolver($this->rootDir, 'App', extension: 'txt');

        // Act
        $result = $resolver->resolve('App\\Pages\\Index');

        // Assert
        $this->assertSame($templatePath, $result);
    }
}
