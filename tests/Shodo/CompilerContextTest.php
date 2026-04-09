<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo;

use Arcanum\Parchment\Reader;
use Arcanum\Shodo\CompilerContext;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CompilerContext::class)]
final class CompilerContextTest extends TestCase
{
    // ------------------------------------------------------------------
    // rewriteHelperCalls
    // ------------------------------------------------------------------

    public function testRewriteHelperCallsTransformsSimpleCall(): void
    {
        // Arrange
        $context = $this->createContext();

        // Act
        $result = $context->rewriteHelperCalls("Route::url('home')");

        // Assert
        $this->assertSame("\$__helpers['Route']->url('home')", $result);
    }

    public function testRewriteHelperCallsHandlesNestedCalls(): void
    {
        // Arrange
        $context = $this->createContext();

        // Act
        $result = $context->rewriteHelperCalls("Format::number(Math::pi(), 2)");

        // Assert
        $this->assertSame(
            "\$__helpers['Format']->number(\$__helpers['Math']->pi(), 2)",
            $result,
        );
    }

    public function testRewriteHelperCallsLeavesFullyQualifiedAlone(): void
    {
        // Arrange
        $context = $this->createContext();

        // Act
        $result = $context->rewriteHelperCalls('\App\Foo::bar()');

        // Assert
        $this->assertSame('\App\Foo::bar()', $result);
    }

    public function testRewriteHelperCallsLeavesVariableStaticCallAlone(): void
    {
        // Arrange
        $context = $this->createContext();

        // Act
        $result = $context->rewriteHelperCalls('$Foo::method()');

        // Assert
        $this->assertSame('$Foo::method()', $result);
    }

    // ------------------------------------------------------------------
    // trackDependency
    // ------------------------------------------------------------------

    public function testTrackDependencySharesReferenceWithCaller(): void
    {
        // Arrange
        $deps = [];
        $context = new CompilerContext(
            templateDirectory: '/tmp',
            templatesDirectory: '',
            fragment: false,
            reader: new Reader(),
            dependencies: $deps,
        );

        // Act
        $context->trackDependency('/tmp/layout.html');

        // Assert — the caller's array is mutated
        $this->assertSame(['/tmp/layout.html'], $deps);
    }

    public function testTrackDependencyDeduplicates(): void
    {
        // Arrange
        $deps = [];
        $context = new CompilerContext(
            templateDirectory: '/tmp',
            templatesDirectory: '',
            fragment: false,
            reader: new Reader(),
            dependencies: $deps,
        );

        // Act
        $context->trackDependency('/tmp/layout.html');
        $context->trackDependency('/tmp/layout.html');

        // Assert
        $this->assertSame(['/tmp/layout.html'], $deps);
    }

    // ------------------------------------------------------------------
    // findFile
    // ------------------------------------------------------------------

    public function testFindFileReturnsPathWhenFileExists(): void
    {
        // Arrange
        $context = $this->createContext();
        $dir = dirname(__DIR__) . '/Fixture/Templates';

        // Act
        $result = $context->findFile('layout.html', $dir);

        // Assert
        $this->assertSame($dir . DIRECTORY_SEPARATOR . 'layout.html', $result);
    }

    public function testFindFileAppendsHtmlExtension(): void
    {
        // Arrange
        $context = $this->createContext();
        $dir = dirname(__DIR__) . '/Fixture/Templates';

        // Act
        $result = $context->findFile('layout', $dir);

        // Assert
        $this->assertSame($dir . DIRECTORY_SEPARATOR . 'layout.html', $result);
    }

    public function testFindFileReturnsNullWhenNotFound(): void
    {
        // Arrange
        $context = $this->createContext();

        // Act
        $result = $context->findFile('nonexistent.html', '/tmp');

        // Assert
        $this->assertNull($result);
    }

    // ------------------------------------------------------------------
    // withTemplateDirectory
    // ------------------------------------------------------------------

    public function testWithTemplateDirectorySharesDependencies(): void
    {
        // Arrange
        $deps = [];
        $context = new CompilerContext(
            templateDirectory: '/original',
            templatesDirectory: '/shared',
            fragment: false,
            reader: new Reader(),
            dependencies: $deps,
        );

        // Act
        $child = $context->withTemplateDirectory('/child');
        $child->trackDependency('/child/partial.html');

        // Assert — both contexts share the same deps array
        $this->assertSame('/child', $child->templateDirectory);
        $this->assertSame('/shared', $child->templatesDirectory);
        $this->assertSame(['/child/partial.html'], $deps);
    }

    // ------------------------------------------------------------------
    // replace / replaceCallback
    // ------------------------------------------------------------------

    public function testReplacePerformsRegexReplacement(): void
    {
        // Arrange
        $context = $this->createContext();

        // Act
        $result = $context->replace('/foo/', 'bar', 'foo baz foo');

        // Assert
        $this->assertSame('bar baz bar', $result);
    }

    public function testReplaceCallbackPerformsRegexCallback(): void
    {
        // Arrange
        $context = $this->createContext();

        // Act
        $result = $context->replaceCallback(
            '/(\w+)/',
            fn (array $m) => strtoupper($m[1]),
            'foo bar',
        );

        // Assert
        $this->assertSame('FOO BAR', $result);
    }

    public function testReplaceThrowsOnInvalidPattern(): void
    {
        // Arrange
        $context = $this->createContext();

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        @$context->replace('/(?:/', 'x', 'input');
    }

    private function createContext(): CompilerContext
    {
        $deps = [];

        return new CompilerContext(
            templateDirectory: '',
            templatesDirectory: '',
            fragment: false,
            reader: new Reader(),
            dependencies: $deps,
        );
    }
}
