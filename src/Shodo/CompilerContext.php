<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

use Arcanum\Parchment\Reader;

/**
 * Per-compilation context passed to every CompilerDirective.
 *
 * Carries the state and shared utilities that directives need:
 * file resolution, dependency tracking, helper rewriting, and
 * regex wrappers with error handling.
 *
 * A fresh context is created at the start of each compile() call.
 * The dependencies array is shared by reference so all directives
 * contribute to the same lastDependencies() list.
 */
final class CompilerContext
{
    /**
     * Helper-call pattern for rewriting Name::method(args) syntax.
     *
     * The lookbehind prevents rewriting:
     *   - \App\Foo::bar()      — fully-qualified (preceded by \)
     *   - Namespace\Foo::bar() — partially-qualified (preceded by \)
     *   - $Format::method()    — variable static call (preceded by $)
     *   - MyFoo::bar() inside an identifier — preceded by a word char
     *
     * Supports up to three levels of nested parens in the argument list.
     */
    public const HELPER_CALL_PATTERN = '(?<![\\\\\w$])([A-Z][a-zA-Z0-9]*)::(\w+)'
        . '((?:\((?:[^()]*+|\((?:[^()]*+|\([^()]*+\))*\))*\)))';

    /**
     * @param list<string> $dependencies Mutable reference — shared across all directives.
     */
    public function __construct(
        public readonly string $templateDirectory,
        public readonly string $templatesDirectory,
        public readonly bool $fragment,
        public readonly Reader $reader,
        private array &$dependencies,
    ) {
    }

    /**
     * Create a child context with a different template directory.
     *
     * Shares the same dependencies array (by reference) and reader,
     * so file tracking accumulates across the entire compilation.
     * Used by LayoutDirective to resolve includes within layouts.
     */
    public function withTemplateDirectory(string $templateDirectory): self
    {
        return new self(
            templateDirectory: $templateDirectory,
            templatesDirectory: $this->templatesDirectory,
            fragment: $this->fragment,
            reader: $this->reader,
            dependencies: $this->dependencies,
        );
    }

    /**
     * Record a file dependency for cache invalidation.
     */
    public function trackDependency(string $path): void
    {
        if (!in_array($path, $this->dependencies, true)) {
            $this->dependencies[] = $path;
        }
    }

    /**
     * Read a file's contents via the shared Reader.
     */
    public function readFile(string $path): string
    {
        return $this->reader->read($path);
    }

    /**
     * Try to find a file in a directory, with optional .html extension fallback.
     */
    public function findFile(string $path, string $directory): ?string
    {
        $absolute = $directory . DIRECTORY_SEPARATOR . $path;

        if (is_file($absolute)) {
            return $absolute;
        }

        if (pathinfo($path, PATHINFO_EXTENSION) === '') {
            $withExt = $absolute . '.html';
            if (is_file($withExt)) {
                return $withExt;
            }
        }

        return null;
    }

    /**
     * Rewrite helper-call occurrences inside an expression body.
     *
     * Each Name::method(args) match becomes $__helpers['Name']->method(args).
     * Recurses into nested calls so Format::number(Math::pi(), 2) rewrites both.
     */
    public function rewriteHelperCalls(string $body): string
    {
        return $this->replaceCallback(
            '/' . self::HELPER_CALL_PATTERN . '/s',
            function (array $m): string {
                $args = $this->rewriteHelperCalls($m[3]);
                return '$__helpers[\'' . $m[1] . '\']->' . $m[2] . $args;
            },
            $body,
        );
    }

    /**
     * Regex replace with error handling.
     */
    public function replace(string $pattern, string $replacement, string $subject): string
    {
        $result = preg_replace($pattern, $replacement, $subject);

        if ($result === null) {
            throw new \RuntimeException("Template compilation failed for pattern: $pattern");
        }

        return $result;
    }

    /**
     * Regex replace with callback and error handling.
     */
    public function replaceCallback(string $pattern, callable $callback, string $subject): string
    {
        $result = preg_replace_callback($pattern, $callback, $subject);

        if ($result === null) {
            throw new \RuntimeException("Template compilation failed for pattern: $pattern");
        }

        return $result;
    }
}
