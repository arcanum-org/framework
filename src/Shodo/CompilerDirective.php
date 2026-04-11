<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

/**
 * A template directive that the Shodo compiler runs during compilation.
 *
 * Built-in directives ship with Shodo (if, foreach, extends, include, etc.).
 * Custom directives are registered by framework packages or app developers
 * through the DirectiveRegistry.
 *
 * Each directive claims one or more keywords and transforms the template
 * source in its process() method. Directives run in priority order (lower
 * first), then the compiler's expression output passes run last as the
 * catch-all for {{ $variable }} and {{! raw !}} syntax.
 */
interface CompilerDirective
{
    /**
     * Keywords this directive claims.
     *
     * Any {{ keyword ... }} pattern where the keyword appears in this list
     * is owned by this directive. The compiler uses this for unknown-keyword
     * detection: after all directives run, any remaining {{ lowercase_word }}
     * not claimed by any registered directive produces an error.
     *
     * @return list<string>
     */
    public function keywords(): array;

    /**
     * Execution priority — lower numbers run first.
     *
     * Built-in priorities use steps of 100:
     *   100 — include (file inlining, must run before layout)
     *   200 — layout (extends/section/yield)
     *   300 — match (pre-compilation, before main passes)
     *   400 — csrf (keyword, before expression catch-all)
     *   500 — control flow (if/foreach/for/while)
     *
     * Custom directives should pick a priority that places them
     * correctly relative to the built-ins. Gaps of 100 allow
     * insertion between any two built-ins.
     */
    public function priority(): int;

    /**
     * Transform the template source, consuming claimed keywords.
     *
     * Receives the full template source and a CompilerContext with
     * shared utilities (helper rewriting, file resolution, dependency
     * tracking, regex wrappers). Returns the transformed source.
     */
    public function process(string $source, CompilerContext $context): string;
}
