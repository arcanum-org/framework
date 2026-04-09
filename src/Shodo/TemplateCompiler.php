<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

/**
 * Stateless regex-based template compiler.
 *
 * Translates Arcanum template syntax into PHP. All directives live inside
 * {{ }} delimiters. The compiled output is valid PHP suitable for eval()
 * or writing to a cache file.
 *
 * Escaped output ({{ $expr }}) emits a call to $__escape, a callable
 * injected by the renderer. This keeps the compiler format-agnostic —
 * HtmlRenderer provides htmlspecialchars, PlainTextRenderer provides
 * an identity function, etc.
 *
 * Helper calls use static-method syntax: {{ Route::url('x') }} compiles
 * to $__helpers['Route']->url('x'). The alias must start with an uppercase
 * letter; fully-qualified static calls (with backslashes) are left alone.
 *
 * Directives use a single bare-keyword convention. Inside `{{ }}`,
 * the first character tells the compiler what to do:
 *
 *   $       → variable expression: {{ $name }}, {{ $user->name }}
 *   A-Z     → helper call:         {{ Route::url('home') }}
 *   a-z     → directive:           {{ extends 'layout' }}, {{ if $foo }}, {{ csrf }}
 *
 * Mirrors PHP's own naming conventions, so no `@` prefix is needed.
 *
 * Pre-compilation passes (run before PHP compilation):
 * - include 'path' — inlines referenced file contents
 * - extends / section / yield directives — layout inheritance
 * - match / case / default — switch-style branching with implicit break
 */
final class TemplateCompiler
{
    /**
     * Standalone helper-call pattern, applied inside captured `{{ }}` /
     * `{{! !}}` / control-structure expression bodies.
     *
     * The lookbehind keeps the rewriter from clobbering names that look
     * like helpers but are not — specifically:
     *   - `\App\Foo::bar()`        — fully-qualified call (preceded by `\`)
     *   - `Namespace\Foo::bar()`   — partially-qualified call (preceded by `\`)
     *   - `$Format::method()`      — variable static call (preceded by `$`)
     *   - `MyFoo::bar()` inside an identifier — preceded by a word char
     *
     * The argument list allows up to three levels of nested parens, which
     * covers every realistic case (`Format::number(count($items), 0)`,
     * `Format::number(Math::min($a, $b), 2)`, etc.). Calls deeper than
     * three levels still rewrite correctly because each helper-call inside
     * the body will be matched by its own occurrence of the pattern — the
     * rewrite is one pass over the body, not recursive.
     */
    private const HELPER_CALL_PATTERN = '(?<![\\\\\w$])([A-Z][a-zA-Z0-9]*)::(\w+)'
        . '((?:\((?:[^()]*+|\((?:[^()]*+|\([^()]*+\))*\))*\)))';

    /**
     * Files touched by the most recent compile() call, in the order they
     * were resolved. Includes layouts and any nested @include partials —
     * but NOT the main template path itself, which the cache already
     * tracks via its own filename → mtime check.
     *
     * Reset at the start of every compile() call. Read by callers via
     * lastDependencies() to know what to invalidate the cache against.
     *
     * @var list<string>
     */
    private array $lastDependencies = [];

    /**
     * @param string $templatesDirectory Shared templates directory
     *     (e.g. app/Templates/). Used as a fallback when resolving
     *     layout and include paths that aren't found relative to
     *     the child template's directory.
     */
    public function __construct(
        private readonly \Arcanum\Parchment\Reader $reader = new \Arcanum\Parchment\Reader(),
        private readonly string $templatesDirectory = '',
    ) {
    }

    /**
     * Files the most recent compile() call read in addition to the main
     * template — layouts, includes, and any nested partials. Used by
     * callers like TemplateCache to record dependencies for later
     * freshness checks.
     *
     * @return list<string>
     */
    public function lastDependencies(): array
    {
        return $this->lastDependencies;
    }

    /**
     * Render a template with variables using direct substitution.
     *
     * Unlike compile(), this does not produce eval-able PHP. It replaces
     * `{{! $var !}}` placeholders directly with variable values. Designed
     * for stubs and other templates where the output itself contains PHP
     * source code (which would conflict with eval's PHP tag processing).
     *
     * Only supports raw output (`{{! $var !}}`), not control structures
     * or escaped output — stubs don't need those.
     *
     * @param array<string, string> $variables
     */
    public function render(string $source, array $variables): string
    {
        foreach ($variables as $name => $value) {
            $source = str_replace('{{! $' . $name . ' !}}', $value, $source);
        }

        return $source;
    }

    /**
     * Compile template source into PHP.
     *
     * When $templateDirectory is provided, pre-compilation passes run first:
     * include inlining and extends/section/yield layout inheritance.
     *
     * When $fragment is true, layout inheritance is skipped — only the
     * 'content' section is rendered. This is used for htmx partial swaps
     * where the layout wrapper (head, nav, footer) is not needed.
     *
     * When $fragmentName is provided, the compiler extracts the named
     * fragment from within the content section and compiles only that.
     * Named fragments are declared with {{ fragment 'name' }}...{{ endfragment }}.
     * Returns an empty string when the fragment name doesn't exist.
     */
    public function compile(
        string $source,
        string $templateDirectory = '',
        bool $fragment = false,
        string $fragmentName = '',
    ): string {
        $this->lastDependencies = [];

        if ($templateDirectory !== '') {
            $source = $this->resolveIncludes($source, $templateDirectory);

            if ($fragmentName !== '') {
                $source = $this->resolveNamedFragment($source, $templateDirectory, $fragmentName);
            } elseif ($fragment) {
                $source = $this->resolveFragment($source, $templateDirectory);
            } else {
                $source = $this->resolveLayout($source, $templateDirectory);
            }
        }

        // Strip fragment delimiters — in the full-render path, fragments
        // are transparent containers (their content renders inline as part
        // of the section). Only the named-fragment compilation path above
        // extracts them; here we just remove the markers so they don't hit
        // the escaped-output fallback.
        $source = $this->stripFragmentDelimiters($source);

        // Match / case / default → PHP switch alt syntax with implicit
        // breaks. Done as a pre-pass because case fall-through needs to
        // see the entire block at once. Runs before the regex passes for
        // variables and helpers because the case bodies still contain
        // template syntax that those passes will compile.
        $source = $this->resolveMatch($source);

        $compiled = $source;

        // Directives: {{ csrf }} — raw output, no escape (intentional HTML).
        // Runs before the body-capture passes so the bare keyword is not
        // mistaken for a generic expression.
        $compiled = $this->replace(
            '/\{\{\s*csrf\s*\}\}/',
            '<?= $__helpers[\'Html\']->csrf() ?>',
            $compiled,
        );

        /*
         * Raw output: {{! $expr !}}
         *
         * Captures the body as a PHP expression, runs the helper-call
         * rewriter on it (so `{{! Html::csrf() !}}` and friends compile
         * through the same path as raw scalars), and emits a raw echo
         * tag. Must run before the escaped output pass so the `!`
         * boundaries are consumed first.
         */
        $compiled = $this->replaceCallback(
            '/\{\{!\s*(.+?)\s*!\}\}/s',
            fn (array $m) => '<?= ' . $this->rewriteHelperCalls($m[1]) . ' ?>',
            $compiled,
        );

        /*
         * Control structures with conditions (foreach, if, elseif, for, while).
         *
         * Three accepted forms:
         *   {{ if $foo > 0 }}        — preferred, paren-free
         *   {{ if ($foo > 0) }}      — also accepted
         *   {{ if ($foo > 0): }}     — also accepted (PHP alt syntax)
         *
         * Normalised to canonical PHP alt-syntax with explicit parens.
         * Helper calls inside the condition are rewritten so that
         * `{{ if Env::debugMode() }}` and `{{ foreach Wired::list() as $item }}`
         * resolve through the helper registry rather than compiling to
         * literal PHP static calls.
         */
        $compiled = $this->replaceCallback(
            '/\{\{\s*(foreach|if|elseif|for|while)(?:\s+|(?=\())(.+?)\s*\}\}/s',
            function (array $m): string {
                $expr = $this->normaliseControlExpression($m[2]);
                $expr = $this->rewriteHelperCalls($expr);
                return '<?php ' . $m[1] . ' (' . $expr . '): ?>';
            },
            $compiled,
        );

        // else (no arguments, no colon)
        $compiled = $this->replace(
            '/\{\{\s*else\s*\}\}/',
            '<?php else: ?>',
            $compiled,
        );

        // End tags: endforeach, endif, endfor, endwhile
        $compiled = $this->replace(
            '/\{\{\s*(endforeach|endif|endfor|endwhile)\s*\}\}/',
            '<?php $1; ?>',
            $compiled,
        );

        // Escaped output: {{ $expr }} — calls $__escape provided by the renderer.
        //
        // The body is treated as an arbitrary PHP expression. Helper-call
        // occurrences inside it are rewritten to $__helpers[...]->method(...),
        // so anything PHP allows after a method call — `[...]`, `->`, `?->`,
        // arithmetic, ternary, `??`, `instanceof`, nested helper calls — Just
        // Works because PHP itself parses the result. Multiple helper calls
        // in one expression compose: `{{ Format::number(Math::pi(), 2) }}`
        // rewrites both occurrences in a single pass.
        $compiled = $this->replaceCallback(
            '/\{\{\s*(.+?)\s*\}\}/s',
            fn (array $m) => '<?= $__escape((string)(' . $this->rewriteHelperCalls($m[1]) . ')) ?>',
            $compiled,
        );

        return $compiled;
    }

    /**
     * Rewrite every helper-call occurrence inside an expression body.
     *
     * Each `Name::method(args)` match becomes `$__helpers['Name']->method(args)`.
     * Names that are part of a fully-qualified call (`\App\Foo::bar()`),
     * a variable static call (`$Format::method()`), or an identifier suffix
     * are left alone — see HELPER_CALL_PATTERN for the lookbehind details.
     */
    private function rewriteHelperCalls(string $body): string
    {
        return $this->replaceCallback(
            '/' . self::HELPER_CALL_PATTERN . '/s',
            function (array $m): string {
                // Recurse into the captured argument list so nested helper
                // calls compose: Format::number(Math::pi(), 2) rewrites both
                // helpers in a single pass over the body.
                $args = $this->rewriteHelperCalls($m[3]);
                return '$__helpers[\'' . $m[1] . '\']->' . $m[2] . $args;
            },
            $body,
        );
    }

    private function replace(string $pattern, string $replacement, string $subject): string
    {
        $result = preg_replace($pattern, $replacement, $subject);

        if ($result === null) {
            throw new \RuntimeException("Template compilation failed for pattern: $pattern");
        }

        return $result;
    }

    private function replaceCallback(string $pattern, callable $callback, string $subject): string
    {
        $result = preg_replace_callback($pattern, $callback, $subject);

        if ($result === null) {
            throw new \RuntimeException("Template compilation failed for pattern: $pattern");
        }

        return $result;
    }

    /**
     * Normalise a control-structure expression to its bare form.
     *
     * Strips a trailing `:` (the developer wrote PHP alt-syntax style)
     * and a single layer of outer parens (only when balanced as a true
     * wrapping pair, not just two separately-grouped sub-expressions).
     * The compiler then re-wraps the cleaned expression in canonical
     * `(EXPR):` form.
     */
    private function normaliseControlExpression(string $raw): string
    {
        $expr = trim($raw);

        // Strip a trailing PHP alt-syntax colon if present.
        if (str_ends_with($expr, ':')) {
            $expr = trim(substr($expr, 0, -1));
        }

        // Strip a single layer of outer parens if they wrap the whole
        // expression. Walk char by char and verify the opening paren is
        // paired with the closing one (not with something internal).
        if (
            strlen($expr) >= 2
            && $expr[0] === '('
            && $expr[strlen($expr) - 1] === ')'
            && $this->outerParensWrapWholeExpression($expr)
        ) {
            $expr = trim(substr($expr, 1, -1));
        }

        return $expr;
    }

    /**
     * True if the first `(` is paired with the last `)` — i.e. the parens
     * wrap the whole expression rather than two separate sub-expressions.
     *
     * For "(a)" → true. For "(a) || (b)" → false (depth hits 0 mid-way).
     */
    private function outerParensWrapWholeExpression(string $expr): bool
    {
        $length = strlen($expr);
        $depth = 0;

        for ($i = 0; $i < $length; $i++) {
            $char = $expr[$i];
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0 && $i < $length - 1) {
                    return false;
                }
            }
        }

        return $depth === 0;
    }

    // ------------------------------------------------------------------
    // Pre-compilation: include
    // ------------------------------------------------------------------

    /**
     * Resolve {{ include 'path' }} directives by inlining file contents.
     *
     * Paths are relative to the given base directory. Supports nesting
     * (included files may themselves contain include directives).
     * Guards against circular includes with a depth limit.
     */
    private function resolveIncludes(
        string $source,
        string $baseDirectory,
        int $depth = 0,
    ): string {
        if ($depth > 10) {
            throw new \RuntimeException(
                'Include depth limit exceeded (max 10)'
                    . ' — check for circular includes',
            );
        }

        return $this->replaceCallback(
            '/\{\{\s*include\s+\'([^\']+)\'\s*\}\}/',
            function (array $matches) use ($baseDirectory, $depth): string {
                $path = $this->resolveIncludePath(
                    $matches[1],
                    $baseDirectory,
                );
                $this->trackDependency($path);
                $contents = $this->reader->read($path);

                return $this->resolveIncludes(
                    $contents,
                    dirname($path),
                    $depth + 1,
                );
            },
            $source,
        );
    }

    /**
     * Append a dependency to the deps list, deduplicated.
     */
    private function trackDependency(string $path): void
    {
        if (!in_array($path, $this->lastDependencies, true)) {
            $this->lastDependencies[] = $path;
        }
    }

    /**
     * Resolve an include path.
     *
     * Resolution order:
     * 1. Relative to the current template's directory
     * 2. Relative to the configured templates directory (if set)
     *
     * Tries each location with the exact path first, then with .html
     * appended if no extension was given.
     */
    private function resolveIncludePath(
        string $path,
        string $baseDirectory,
    ): string {
        $resolved = $this->findFile($path, $baseDirectory);
        if ($resolved !== null) {
            return $resolved;
        }

        if ($this->templatesDirectory !== '') {
            $resolved = $this->findFile($path, $this->templatesDirectory);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        $searched = $baseDirectory;
        if ($this->templatesDirectory !== '') {
            $searched .= ', ' . $this->templatesDirectory;
        }

        throw new \RuntimeException(sprintf(
            'Include file not found: %s (searched: %s)',
            $path,
            $searched,
        ));
    }

    /**
     * Try to find a file in a directory, with optional .html extension.
     */
    private function findFile(string $path, string $directory): ?string
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

    // ------------------------------------------------------------------
    // Pre-compilation: extends / section / yield
    // ------------------------------------------------------------------

    /**
     * Resolve layout inheritance.
     *
     * If the source starts with the extends directive, extract all
     * {{ section 'name' }}...{{ endsection }} blocks from the child,
     * load the layout file, and replace {{ yield 'name' }} placeholders
     * with the section contents.
     */
    private function resolveLayout(
        string $source,
        string $templateDirectory,
    ): string {
        // Check for extends directive (must appear at the start, ignoring whitespace).
        if (!preg_match('/^\s*\{\{\s*extends\s+\'([^\']+)\'\s*\}\}/s', $source, $extendsMatch)) {
            return $source;
        }

        $layoutName = $extendsMatch[1];

        // Strip the extends directive from the child source.
        $childSource = substr($source, strlen($extendsMatch[0]));

        // Extract sections from the child.
        $sections = $this->extractSections($childSource);

        // Load and resolve includes in the layout.
        $layoutPath = $this->resolveLayoutPath(
            $layoutName,
            $templateDirectory,
        );
        $this->trackDependency($layoutPath);
        $layoutSource = $this->reader->read($layoutPath);
        $layoutSource = $this->resolveIncludes(
            $layoutSource,
            dirname($layoutPath),
        );

        // Collect yield names from the layout.
        preg_match_all(
            '/\{\{\s*yield\s+\'([^\']+)\'\s*\}\}/s',
            $layoutSource,
            $yieldMatches,
        );
        $yieldNames = $yieldMatches[1];

        // Warn about sections defined in the child that don't match
        // any yield in the layout — almost always a typo.
        $unusedSections = array_diff(
            array_keys($sections),
            $yieldNames,
        );
        if ($unusedSections !== []) {
            $available = $yieldNames !== []
                ? 'Available yields: ' . implode(', ', $yieldNames)
                : 'The layout has no yield directives';

            throw new \RuntimeException(sprintf(
                'Template defines section(s) not found in layout: %s. %s',
                implode(', ', $unusedSections),
                $available,
            ));
        }

        // Replace yield directives in the layout with section content.
        return $this->replaceCallback(
            '/\{\{\s*yield\s+\'([^\']+)\'\s*\}\}/s',
            function (array $matches) use ($sections): string {
                return $sections[$matches[1]] ?? '';
            },
            $layoutSource,
        );
    }

    /**
     * Resolve fragment mode: extract only the 'content' section, skip layout.
     *
     * When a template declares a layout via the extends directive, fragment
     * mode returns the content section directly without wrapping in the
     * layout. If there's no extends directive, the source passes through.
     */
    private function resolveFragment(
        string $source,
        string $templateDirectory,
    ): string {
        if (!preg_match('/^\s*\{\{\s*extends\s+\'([^\']+)\'\s*\}\}/s', $source, $extendsMatch)) {
            return $source;
        }

        $childSource = substr($source, strlen($extendsMatch[0]));
        $sections = $this->extractSections($childSource);

        return $sections['content'] ?? '';
    }

    /**
     * Remove {{ fragment 'name' }} and {{ endfragment }} markers, keeping
     * the content between them intact. Used in the full-render path where
     * fragments are transparent — their content is part of the section.
     *
     * Validates structure first so unclosed/nested fragments fail loudly
     * even when the template is rendered in full mode.
     */
    private function stripFragmentDelimiters(string $source): string
    {
        $this->validateFragmentBlocks($source);

        $source = $this->replace(
            '/\{\{\s*fragment\s+\'[^\']+\'\s*\}\}/',
            '',
            $source,
        );

        return $this->replace(
            '/\{\{\s*endfragment\s*\}\}/',
            '',
            $source,
        );
    }

    /**
     * Resolve a named fragment from the content section, skipping the layout.
     *
     * Named fragments are declared inside a section with
     * {{ fragment 'name' }}...{{ endfragment }}. This method extracts
     * the content section (like resolveFragment), then finds the named
     * fragment within it. Returns an empty string when the fragment
     * doesn't exist — the renderer handles fall-through.
     */
    private function resolveNamedFragment(
        string $source,
        string $templateDirectory,
        string $fragmentName,
    ): string {
        // For templates without a layout, search the entire source.
        if (!preg_match('/^\s*\{\{\s*extends\s+\'([^\']+)\'\s*\}\}/s', $source, $extendsMatch)) {
            $fragments = $this->extractFragments($source);

            return $fragments[$fragmentName] ?? '';
        }

        // Extract the content section first, then find the fragment within it.
        $childSource = substr($source, strlen($extendsMatch[0]));
        $sections = $this->extractSections($childSource);
        $content = $sections['content'] ?? '';

        $fragments = $this->extractFragments($content);

        return $fragments[$fragmentName] ?? '';
    }

    /**
     * Extract {{ fragment 'name' }}...{{ endfragment }} blocks from source.
     *
     * Validates that all opened fragments are closed and that no fragment
     * blocks are nested (nesting is disallowed — fragments are flat regions).
     *
     * @return array<string, string> Fragment name → content (untrimmed).
     */
    private function extractFragments(string $source): array
    {
        // Validate: check for unclosed or nested fragments.
        $this->validateFragmentBlocks($source);

        $fragments = [];

        preg_match_all(
            '/\{\{\s*fragment\s+\'([^\']+)\'\s*\}\}(.*?)\{\{\s*endfragment\s*\}\}/s',
            $source,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $fragments[$match[1]] = $match[2];
        }

        return $fragments;
    }

    /**
     * Validate that fragment blocks are well-formed.
     *
     * Checks for:
     * - Unclosed {{ fragment 'name' }} without a matching {{ endfragment }}
     * - {{ endfragment }} without a preceding {{ fragment 'name' }}
     * - Nested fragment blocks (not allowed)
     *
     * @throws \RuntimeException On any structural error.
     */
    private function validateFragmentBlocks(string $source): void
    {
        // Find all fragment-related directives in order of appearance.
        preg_match_all(
            '/\{\{\s*(fragment\s+\'([^\']+)\'|endfragment)\s*\}\}/',
            $source,
            $matches,
            PREG_SET_ORDER,
        );

        $openName = null;

        foreach ($matches as $match) {
            $directive = $match[1];

            if (isset($match[2])) {
                $name = $match[2];

                if ($openName !== null) {
                    throw new \RuntimeException(sprintf(
                        'Nested fragment blocks are not allowed:'
                            . " '{{ fragment '%s' }}' opened inside '{{ fragment '%s' }}'."
                            . ' Close the outer fragment first.',
                        $name,
                        $openName,
                    ));
                }

                $openName = $name;
            } else {
                // endfragment
                if ($openName === null) {
                    throw new \RuntimeException(
                        "'{{ endfragment }}' found without a matching '{{ fragment }}' directive.",
                    );
                }

                $openName = null;
            }
        }

        if ($openName !== null) {
            throw new \RuntimeException(sprintf(
                "Unclosed fragment block: '{{ fragment '%s' }}' has no matching '{{ endfragment }}'.",
                $openName,
            ));
        }
    }

    /**
     * Extract {{ section 'name' }}...{{ endsection }} blocks.
     *
     * @return array<string, string> Section name → content.
     */
    private function extractSections(string $source): array
    {
        $sections = [];

        preg_match_all(
            '/\{\{\s*section\s+\'([^\']+)\'\s*\}\}(.*?)\{\{\s*endsection\s*\}\}/s',
            $source,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $sections[$match[1]] = trim($match[2]);
        }

        return $sections;
    }

    // ------------------------------------------------------------------
    // Pre-compilation: match / case / default / endmatch
    // ------------------------------------------------------------------

    /**
     * Resolve match / case / default blocks into PHP switch alt-syntax
     * with implicit `break` after each case.
     *
     * Input:
     *   {{ match $status }}
     *       {{ case 'pending', 'active' }}<span>active</span>
     *       {{ case 'closed' }}<span>closed</span>
     *       {{ default }}<span>unknown</span>
     *   {{ endmatch }}
     *
     * Output:
     *   <?php switch ($status): ?>
     *       <?php case 'pending': ?><?php case 'active': ?><span>active</span><?php break; ?>
     *       <?php case 'closed': ?><span>closed</span><?php break; ?>
     *       <?php default: ?><span>unknown</span><?php break; ?>
     *   <?php endswitch; ?>
     *
     * Comma-separated values in `case` map to PHP fall-through case lists.
     * The case body still contains template syntax that the main compile
     * pass picks up afterwards.
     */
    private function resolveMatch(string $source): string
    {
        return $this->replaceCallback(
            '/\{\{\s*match\s+(.+?)\s*\}\}(.*?)\{\{\s*endmatch\s*\}\}/s',
            function (array $matches): string {
                $subject = trim($matches[1]);
                $body = $matches[2];

                $output = '<?php switch (' . $subject . '): ?>';

                // Split body on case/default markers, keeping them.
                $parts = preg_split(
                    '/(\{\{\s*(?:case\s+[^}]+?|default)\s*\}\})/s',
                    $body,
                    -1,
                    PREG_SPLIT_DELIM_CAPTURE,
                );

                if ($parts === false || $parts === []) {
                    return $output . '<?php endswitch; ?>';
                }

                // Anything before the first case marker is dropped (it's
                // just whitespace inside the match block, before any arm).
                $count = count($parts);
                for ($i = 1; $i < $count; $i += 2) {
                    $marker = $parts[$i];
                    $caseBody = $parts[$i + 1] ?? '';

                    if (preg_match('/\{\{\s*case\s+(.+?)\s*\}\}/s', $marker, $caseMatch)) {
                        $values = $this->splitCaseValues($caseMatch[1]);
                        foreach ($values as $value) {
                            $output .= '<?php case ' . $value . ': ?>';
                        }
                    } elseif (preg_match('/\{\{\s*default\s*\}\}/', $marker)) {
                        $output .= '<?php default: ?>';
                    }

                    $output .= $caseBody . '<?php break; ?>';
                }

                return $output . '<?php endswitch; ?>';
            },
            $source,
        );
    }

    /**
     * Split a case clause's value list on commas, respecting strings,
     * parens, and brackets so we don't break up things like
     *   `[1, 2], 'a, b'`
     * into the wrong pieces.
     *
     * @return list<string>
     */
    private function splitCaseValues(string $list): array
    {
        $values = [];
        $buffer = '';
        $depth = 0;
        $inString = null;
        $length = strlen($list);

        for ($i = 0; $i < $length; $i++) {
            $char = $list[$i];

            if ($inString !== null) {
                $buffer .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $buffer .= $list[++$i];
                    continue;
                }
                if ($char === $inString) {
                    $inString = null;
                }
                continue;
            }

            if ($char === "'" || $char === '"') {
                $inString = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === '(' || $char === '[') {
                $depth++;
                $buffer .= $char;
                continue;
            }

            if ($char === ')' || $char === ']') {
                $depth--;
                $buffer .= $char;
                continue;
            }

            if ($char === ',' && $depth === 0) {
                $values[] = trim($buffer);
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $buffer = trim($buffer);
        if ($buffer !== '') {
            $values[] = $buffer;
        }

        return $values;
    }

    /**
     * Resolve a layout file path.
     *
     * Resolution order:
     * 1. Relative to the child template's directory
     * 2. Relative to the configured templates directory (if set)
     *
     * This means co-located layouts (next to the child) take precedence,
     * with the shared templates directory as the standard fallback.
     */
    private function resolveLayoutPath(
        string $name,
        string $startDirectory,
    ): string {
        $resolved = $this->findFile($name, $startDirectory);
        if ($resolved !== null) {
            return $resolved;
        }

        if ($this->templatesDirectory !== '') {
            $resolved = $this->findFile($name, $this->templatesDirectory);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        $searched = $startDirectory;
        if ($this->templatesDirectory !== '') {
            $searched .= ', ' . $this->templatesDirectory;
        }

        throw new \RuntimeException(sprintf(
            'Layout file not found: %s (searched: %s)',
            $name,
            $searched,
        ));
    }
}
