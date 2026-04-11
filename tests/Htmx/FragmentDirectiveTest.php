<?php

declare(strict_types=1);

namespace Arcanum\Test\Htmx;

use Arcanum\Htmx\FragmentDirective;
use Arcanum\Parchment\Reader;
use Arcanum\Shodo\CompilerContext;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(FragmentDirective::class)]
#[UsesClass(CompilerContext::class)]
final class FragmentDirectiveTest extends TestCase
{
    // ------------------------------------------------------------------
    // Interface
    // ------------------------------------------------------------------

    public function testKeywordsClaimsFragmentAndEndfragment(): void
    {
        // Arrange
        $directive = new FragmentDirective();

        // Act & Assert
        $this->assertSame(['fragment', 'endfragment'], $directive->keywords());
    }

    public function testPriorityIs350(): void
    {
        // Arrange
        $directive = new FragmentDirective();

        // Act & Assert
        $this->assertSame(350, $directive->priority());
    }

    // ------------------------------------------------------------------
    // process() — strips markers during compilation
    // ------------------------------------------------------------------

    public function testProcessStripsFragmentMarkers(): void
    {
        // Arrange
        $directive = new FragmentDirective();
        $context = $this->createContext();
        $source = '<div id="main">{{ fragment \'main\' }}<p>Hello</p>{{ endfragment }}</div>';

        // Act
        $result = $directive->process($source, $context);

        // Assert
        $this->assertSame('<div id="main"><p>Hello</p></div>', $result);
    }

    public function testProcessStripsMultipleFragments(): void
    {
        // Arrange
        $directive = new FragmentDirective();
        $context = $this->createContext();
        $source = '{{ fragment \'a\' }}A{{ endfragment }} and {{ fragment \'b\' }}B{{ endfragment }}';

        // Act
        $result = $directive->process($source, $context);

        // Assert
        $this->assertSame('A and B', $result);
    }

    public function testProcessLeavesSourceUnchangedWithoutMarkers(): void
    {
        // Arrange
        $directive = new FragmentDirective();
        $context = $this->createContext();
        $source = '<div id="main"><p>Hello</p></div>';

        // Act
        $result = $directive->process($source, $context);

        // Assert
        $this->assertSame($source, $result);
    }

    public function testProcessHandlesWhitespaceInMarkers(): void
    {
        // Arrange
        $directive = new FragmentDirective();
        $context = $this->createContext();
        $source = '{{  fragment  \'sidebar\'  }}<nav>Nav</nav>{{  endfragment  }}';

        // Act
        $result = $directive->process($source, $context);

        // Assert
        $this->assertSame('<nav>Nav</nav>', $result);
    }

    // ------------------------------------------------------------------
    // extractFragment() — finds inner content from raw source
    // ------------------------------------------------------------------

    public function testExtractFragmentReturnsInnerContent(): void
    {
        // Arrange
        $source = '<div id="main">{{ fragment \'main\' }}<p>Hello</p>{{ endfragment }}</div>';

        // Act
        $result = FragmentDirective::extractFragment($source, 'main');

        // Assert
        $this->assertSame('<p>Hello</p>', $result);
    }

    public function testExtractFragmentReturnsNullWhenNotFound(): void
    {
        // Arrange
        $source = '<div id="main"><p>Hello</p></div>';

        // Act
        $result = FragmentDirective::extractFragment($source, 'main');

        // Assert
        $this->assertNull($result);
    }

    public function testExtractFragmentReturnsNullForDifferentId(): void
    {
        // Arrange
        $source = '{{ fragment \'sidebar\' }}<nav>Nav</nav>{{ endfragment }}';

        // Act
        $result = FragmentDirective::extractFragment($source, 'main');

        // Assert
        $this->assertNull($result);
    }

    public function testExtractFragmentReturnsCorrectContentWithMultipleFragments(): void
    {
        // Arrange
        $source = '{{ fragment \'a\' }}AAA{{ endfragment }}'
            . '{{ fragment \'b\' }}BBB{{ endfragment }}';

        // Act
        $result = FragmentDirective::extractFragment($source, 'b');

        // Assert
        $this->assertSame('BBB', $result);
    }

    public function testExtractFragmentPreservesTemplateExpressions(): void
    {
        // Arrange
        $source = '{{ fragment \'list\' }}'
            . '{{ foreach $items as $item }}<li>{{ $item }}</li>{{ endforeach }}'
            . '{{ endfragment }}';

        // Act
        $result = FragmentDirective::extractFragment($source, 'list');

        // Assert
        $this->assertSame(
            '{{ foreach $items as $item }}<li>{{ $item }}</li>{{ endforeach }}',
            $result,
        );
    }

    public function testExtractFragmentHandlesMultilineContent(): void
    {
        // Arrange
        $source = "{{ fragment 'content' }}\n<h1>Title</h1>\n<p>Body</p>\n{{ endfragment }}";

        // Act
        $result = FragmentDirective::extractFragment($source, 'content');

        // Assert
        $this->assertSame("\n<h1>Title</h1>\n<p>Body</p>\n", $result);
    }

    public function testExtractFragmentWithSpecialCharsInId(): void
    {
        // Arrange — id with hyphens (common in HTML)
        $source = "{{ fragment 'search-results' }}<ul>Items</ul>{{ endfragment }}";

        // Act
        $result = FragmentDirective::extractFragment($source, 'search-results');

        // Assert
        $this->assertSame('<ul>Items</ul>', $result);
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
