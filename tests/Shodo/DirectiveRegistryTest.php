<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo;

use Arcanum\Shodo\CompilerContext;
use Arcanum\Shodo\CompilerDirective;
use Arcanum\Shodo\DirectiveRegistry;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DirectiveRegistry::class)]
final class DirectiveRegistryTest extends TestCase
{
    public function testAllReturnsEmptyArrayWhenNothingRegistered(): void
    {
        // Arrange
        $registry = new DirectiveRegistry();

        // Act & Assert
        $this->assertSame([], $registry->all());
    }

    public function testRegisterAndRetrieveDirective(): void
    {
        // Arrange
        $directive = $this->createDirective(['csrf'], 400);
        $registry = new DirectiveRegistry();
        $registry->register($directive);

        // Act
        $all = $registry->all();

        // Assert
        $this->assertCount(1, $all);
        $this->assertSame($directive, $all[0]);
    }

    public function testAllReturnsSortedByPriorityAscending(): void
    {
        // Arrange
        $high = $this->createDirective(['if'], 500);
        $low = $this->createDirective(['include'], 100);
        $mid = $this->createDirective(['match'], 300);

        $registry = new DirectiveRegistry();
        $registry->register($high);
        $registry->register($low);
        $registry->register($mid);

        // Act
        $all = $registry->all();

        // Assert
        $this->assertSame($low, $all[0]);
        $this->assertSame($mid, $all[1]);
        $this->assertSame($high, $all[2]);
    }

    public function testSortIsCachedUntilNewRegistration(): void
    {
        // Arrange
        $first = $this->createDirective(['csrf'], 400);
        $registry = new DirectiveRegistry();
        $registry->register($first);
        $registry->all(); // triggers sort

        $second = $this->createDirective(['include'], 100);
        $registry->register($second);

        // Act — sort must re-run after new registration
        $all = $registry->all();

        // Assert
        $this->assertSame($second, $all[0]);
        $this->assertSame($first, $all[1]);
    }

    public function testKeywordsReturnsEmptyWhenNothingRegistered(): void
    {
        // Arrange
        $registry = new DirectiveRegistry();

        // Act & Assert
        $this->assertSame([], $registry->keywords());
    }

    public function testKeywordsCollectsFromAllDirectives(): void
    {
        // Arrange
        $registry = new DirectiveRegistry();
        $registry->register($this->createDirective(['if', 'else', 'endif'], 500));
        $registry->register($this->createDirective(['csrf'], 400));

        // Act
        $keywords = $registry->keywords();

        // Assert
        sort($keywords);
        $this->assertSame(['csrf', 'else', 'endif', 'if'], $keywords);
    }

    public function testKeywordsDeduplicates(): void
    {
        // Arrange — two directives claiming the same keyword
        $registry = new DirectiveRegistry();
        $registry->register($this->createDirective(['foo'], 100));
        $registry->register($this->createDirective(['foo', 'bar'], 200));

        // Act
        $keywords = $registry->keywords();

        // Assert
        sort($keywords);
        $this->assertSame(['bar', 'foo'], $keywords);
    }

    /**
     * @param list<string> $keywords
     */
    private function createDirective(array $keywords, int $priority): CompilerDirective
    {
        return new class ($keywords, $priority) implements CompilerDirective {
            /** @param list<string> $kw */
            public function __construct(
                private readonly array $kw,
                private readonly int $pri,
            ) {
            }

            public function keywords(): array
            {
                return $this->kw;
            }

            public function priority(): int
            {
                return $this->pri;
            }

            public function process(string $source, CompilerContext $context): string
            {
                return $source;
            }
        };
    }
}
