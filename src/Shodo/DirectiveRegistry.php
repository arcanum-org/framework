<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

/**
 * Holds registered compiler directives and returns them sorted by priority.
 *
 * Built-in directives are registered by the TemplateCompiler's default
 * constructor. Framework packages and app developers register custom
 * directives through the same registry.
 */
final class DirectiveRegistry
{
    /** @var list<CompilerDirective> */
    private array $directives = [];

    private bool $sorted = false;

    /**
     * Register a directive.
     */
    public function register(CompilerDirective $directive): void
    {
        $this->directives[] = $directive;
        $this->sorted = false;
    }

    /**
     * All registered directives, sorted by priority (ascending).
     *
     * @return list<CompilerDirective>
     */
    public function all(): array
    {
        if (!$this->sorted) {
            usort(
                $this->directives,
                static fn (CompilerDirective $a, CompilerDirective $b) => $a->priority() <=> $b->priority(),
            );
            $this->sorted = true;
        }

        return $this->directives;
    }

    /**
     * All keywords claimed by all registered directives.
     *
     * Used by the compiler for unknown-keyword detection.
     *
     * @return list<string>
     */
    public function keywords(): array
    {
        $keywords = [];

        foreach ($this->directives as $directive) {
            foreach ($directive->keywords() as $keyword) {
                $keywords[] = $keyword;
            }
        }

        return array_values(array_unique($keywords));
    }
}
