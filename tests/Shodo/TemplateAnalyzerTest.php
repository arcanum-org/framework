<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo;

use Arcanum\Shodo\TemplateAnalyzer;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TemplateAnalyzer::class)]
final class TemplateAnalyzerTest extends TestCase
{
    public function testDetectsUnusedVariable(): void
    {
        // Arrange — template uses $name but not $email
        $source = '<h1>{{ $name }}</h1>';

        // Act
        $unused = TemplateAnalyzer::findUnusedVariables($source, ['name', 'email']);

        // Assert
        $this->assertSame(['email'], $unused);
    }

    public function testDoesNotFlagUsedVariables(): void
    {
        // Arrange — template uses both
        $source = '{{ $name }} {{ $email }}';

        // Act
        $unused = TemplateAnalyzer::findUnusedVariables($source, ['name', 'email']);

        // Assert
        $this->assertSame([], $unused);
    }

    public function testIgnoresInternalVariables(): void
    {
        // Arrange — __escape and __helpers are internal, not user variables
        $source = '{{ $name }}';

        // Act
        $unused = TemplateAnalyzer::findUnusedVariables(
            $source,
            ['name', '__escape', '__helpers'],
        );

        // Assert — internals not reported as unused
        $this->assertSame([], $unused);
    }

    public function testHandlesForeachLoopVariables(): void
    {
        // Arrange — $items is used in the foreach declaration
        $source = '{{ foreach($items as $item) }}{{ $item }}{{ endforeach }}';

        // Act
        $unused = TemplateAnalyzer::findUnusedVariables($source, ['items']);

        // Assert
        $this->assertSame([], $unused);
    }

    public function testHandlesControlFlowVariables(): void
    {
        // Arrange — $show used in if condition
        $source = '{{ if($show) }}<p>Visible</p>{{ endif }}';

        // Act
        $unused = TemplateAnalyzer::findUnusedVariables($source, ['show']);

        // Assert
        $this->assertSame([], $unused);
    }

    public function testIgnoresDollarSignInRawText(): void
    {
        // Arrange — $50 appears in raw text, not inside {{ }}
        $source = '<p>Price is $50 off!</p><p>{{ $name }}</p>';

        // Act
        $unused = TemplateAnalyzer::findUnusedVariables($source, ['name', 'discount']);

        // Assert — $50 in raw text doesn't count, discount is still unused
        $this->assertSame(['discount'], $unused);
    }

    public function testIgnoresVariableLikeTextOutsideDelimiters(): void
    {
        // Arrange — documentation example with $variable in raw HTML
        $source = '<p>Use $name to access the variable</p>{{ $title }}';

        // Act
        $unused = TemplateAnalyzer::findUnusedVariables($source, ['title', 'name']);

        // Assert — $name in raw text doesn't count as usage
        $this->assertSame(['name'], $unused);
    }

    public function testReturnsEmptyWhenAllUsed(): void
    {
        // Arrange
        $source = '{{ $a }} {{ $b }} {{ $c }}';

        // Act
        $unused = TemplateAnalyzer::findUnusedVariables($source, ['a', 'b', 'c']);

        // Assert
        $this->assertSame([], $unused);
    }

    public function testReturnsEmptyWhenNoVariablesPassed(): void
    {
        // Arrange
        $source = '<p>Static content</p>';

        // Act
        $unused = TemplateAnalyzer::findUnusedVariables($source, []);

        // Assert
        $this->assertSame([], $unused);
    }

    public function testHandlesRawOutputDelimiters(): void
    {
        // Arrange — {{! !}} raw output also counts as usage
        $source = '{{! $html !}}';

        // Act
        $unused = TemplateAnalyzer::findUnusedVariables($source, ['html', 'extra']);

        // Assert
        $this->assertSame(['extra'], $unused);
    }

    public function testHandlesMultipleUnusedVariables(): void
    {
        // Arrange
        $source = '{{ $name }}';

        // Act
        $unused = TemplateAnalyzer::findUnusedVariables(
            $source,
            ['name', 'ssn', 'internal_notes', 'password'],
        );

        // Assert
        $this->assertSame(['ssn', 'internal_notes', 'password'], $unused);
    }

    public function testHandlesHelperCallsWithVariableArgs(): void
    {
        // Arrange — $price used as argument to a helper
        $source = '{{ Format::number($price, 2) }}';

        // Act
        $unused = TemplateAnalyzer::findUnusedVariables($source, ['price']);

        // Assert
        $this->assertSame([], $unused);
    }

    public function testHandlesTemplateWithNoDelimiters(): void
    {
        // Arrange — pure static content, no {{ }} at all
        $source = '<p>Hello world</p>';

        // Act
        $unused = TemplateAnalyzer::findUnusedVariables($source, ['name']);

        // Assert — variable is unused since template has no expressions
        $this->assertSame(['name'], $unused);
    }
}
