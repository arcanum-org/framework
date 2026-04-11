<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo;

use Arcanum\Shodo\ElementExtraction;
use Arcanum\Shodo\TemplateCompiler;
use Arcanum\Parchment\Reader;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(TemplateCompiler::class)]
#[CoversClass(ElementExtraction::class)]
#[UsesClass(Reader::class)]
final class ElementExtractionTest extends TestCase
{
    // ------------------------------------------------------------------
    // Basic extraction
    // ------------------------------------------------------------------

    public function testExtractsElementByIdOuterHtml(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = '<h1>Title</h1><div id="sidebar"><p>Hello</p></div><footer>F</footer>';

        // Act
        $result = $compiler->extractElementById($source, 'sidebar');

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('<div id="sidebar"><p>Hello</p></div>', $result->outerHtml);
    }

    public function testExtractsElementByIdInnerHtml(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = '<div id="sidebar"><p>Hello</p></div>';

        // Act
        $result = $compiler->extractElementById($source, 'sidebar');

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('<p>Hello</p>', $result->innerHtml);
    }

    public function testReturnsNullWhenIdNotFound(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = '<div id="other"><p>Hello</p></div>';

        // Act
        $result = $compiler->extractElementById($source, 'nonexistent');

        // Assert
        $this->assertNull($result);
    }

    // ------------------------------------------------------------------
    // Nested same-tag elements
    // ------------------------------------------------------------------

    public function testHandlesNestedSameTagElements(): void
    {
        // Arrange — outer div contains inner divs
        $compiler = new TemplateCompiler();
        $source = '<div id="outer"><div class="a"><div class="b">Deep</div></div></div>';

        // Act
        $result = $compiler->extractElementById($source, 'outer');

        // Assert — captures the full outer div including nested divs
        $this->assertNotNull($result);
        $this->assertSame(
            '<div id="outer"><div class="a"><div class="b">Deep</div></div></div>',
            $result->outerHtml,
        );
        $this->assertSame(
            '<div class="a"><div class="b">Deep</div></div>',
            $result->innerHtml,
        );
    }

    public function testExtractsInnerNestedElement(): void
    {
        // Arrange — target the inner element, not the outer
        $compiler = new TemplateCompiler();
        $source = '<div id="outer"><div id="inner"><p>Content</p></div></div>';

        // Act
        $result = $compiler->extractElementById($source, 'inner');

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('<div id="inner"><p>Content</p></div>', $result->outerHtml);
        $this->assertSame('<p>Content</p>', $result->innerHtml);
    }

    // ------------------------------------------------------------------
    // Void elements
    // ------------------------------------------------------------------

    public function testHandlesVoidElementImg(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = '<div><img id="hero" src="/hero.jpg"></div>';

        // Act
        $result = $compiler->extractElementById($source, 'hero');

        // Assert — void elements have no inner HTML
        $this->assertNotNull($result);
        $this->assertSame('<img id="hero" src="/hero.jpg">', $result->outerHtml);
        $this->assertSame('', $result->innerHtml);
    }

    public function testHandlesVoidElementInput(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = '<form><input id="email" type="email" name="email"></form>';

        // Act
        $result = $compiler->extractElementById($source, 'email');

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('<input id="email" type="email" name="email">', $result->outerHtml);
        $this->assertSame('', $result->innerHtml);
    }

    // ------------------------------------------------------------------
    // Template directives inside elements
    // ------------------------------------------------------------------

    public function testExtractsElementWithShodoDirectivesInside(): void
    {
        // Arrange — element contains {{ foreach }} and {{ if }}
        $compiler = new TemplateCompiler();
        $source = '<div id="list">{{ foreach $items as $item }}<p>{{ $item }}</p>{{ endforeach }}</div>';

        // Act
        $result = $compiler->extractElementById($source, 'list');

        // Assert — directives pass through as-is
        $this->assertNotNull($result);
        $this->assertStringContainsString('{{ foreach $items as $item }}', $result->innerHtml);
        $this->assertStringContainsString('{{ endforeach }}', $result->innerHtml);
    }

    public function testExtractsElementWithConditionalContent(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = '<div id="alert">{{ if $show }}<p>Warning!</p>{{ endif }}</div>';

        // Act
        $result = $compiler->extractElementById($source, 'alert');

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('{{ if $show }}<p>Warning!</p>{{ endif }}', $result->innerHtml);
    }

    // ------------------------------------------------------------------
    // Quote styles and whitespace
    // ------------------------------------------------------------------

    public function testMatchesSingleQuotedId(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = "<div id='sidebar'><p>Hi</p></div>";

        // Act
        $result = $compiler->extractElementById($source, 'sidebar');

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('<p>Hi</p>', $result->innerHtml);
    }

    public function testMatchesIdWithSpacesAroundEquals(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = '<div id = "sidebar"><p>Hi</p></div>';

        // Act
        $result = $compiler->extractElementById($source, 'sidebar');

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('<p>Hi</p>', $result->innerHtml);
    }

    // ------------------------------------------------------------------
    // Different element types
    // ------------------------------------------------------------------

    public function testExtractsNonDivElements(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = '<section id="main"><article>Content</article></section>';

        // Act
        $result = $compiler->extractElementById($source, 'main');

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('<section id="main"><article>Content</article></section>', $result->outerHtml);
        $this->assertSame('<article>Content</article>', $result->innerHtml);
    }

    // ------------------------------------------------------------------
    // Self-closing tags (XHTML style)
    // ------------------------------------------------------------------

    public function testSkipsSelfClosingTagsInDepthCount(): void
    {
        // Arrange — self-closing div inside the target shouldn't confuse depth
        $compiler = new TemplateCompiler();
        $source = '<div id="container"><div /><p>Content</p></div>';

        // Act
        $result = $compiler->extractElementById($source, 'container');

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('<div id="container"><div /><p>Content</p></div>', $result->outerHtml);
    }

    // ------------------------------------------------------------------
    // Multiline elements
    // ------------------------------------------------------------------

    public function testExtractsMultilineElement(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = "<div id=\"card\">\n    <h2>Title</h2>\n    <p>Body</p>\n</div>";

        // Act
        $result = $compiler->extractElementById($source, 'card');

        // Assert
        $this->assertNotNull($result);
        $this->assertSame($source, $result->outerHtml);
        $this->assertSame("\n    <h2>Title</h2>\n    <p>Body</p>\n", $result->innerHtml);
    }

    // ------------------------------------------------------------------
    // Dynamic ids are skipped (literal only)
    // ------------------------------------------------------------------

    public function testDoesNotMatchRuntimeIdAgainstDynamicTemplate(): void
    {
        // Arrange — id in the template contains a Shodo expression.
        // A runtime value like "item-42" won't match the literal template
        // text "item-{{ $id }}".
        $compiler = new TemplateCompiler();
        $source = '<div id="item-{{ $id }}"><p>Dynamic</p></div>';

        // Act — searching for a runtime id finds no literal match
        $result = $compiler->extractElementById($source, 'item-42');

        // Assert
        $this->assertNull($result);
    }

    // ------------------------------------------------------------------
    // Multiple elements with different ids
    // ------------------------------------------------------------------

    public function testExtractsCorrectElementFromMultiple(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = '<div id="first"><p>One</p></div><div id="second"><p>Two</p></div><div id="third"><p>Three</p></div>';

        // Act
        $result = $compiler->extractElementById($source, 'second');

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('<div id="second"><p>Two</p></div>', $result->outerHtml);
        $this->assertSame('<p>Two</p>', $result->innerHtml);
    }

    // ------------------------------------------------------------------
    // Id attribute not at start of tag
    // ------------------------------------------------------------------

    public function testMatchesIdAfterOtherAttributes(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = '<div class="panel" data-foo="bar" id="target"><span>X</span></div>';

        // Act
        $result = $compiler->extractElementById($source, 'target');

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('<span>X</span>', $result->innerHtml);
    }

    // ------------------------------------------------------------------
    // Partial id match should NOT match
    // ------------------------------------------------------------------

    public function testDoesNotPartialMatchId(): void
    {
        // Arrange — id="sidebar-extended" should not match target "sidebar"
        $compiler = new TemplateCompiler();
        $source = '<div id="sidebar-extended"><p>Content</p></div>';

        // Act
        $result = $compiler->extractElementById($source, 'sidebar');

        // Assert
        $this->assertNull($result);
    }
}
