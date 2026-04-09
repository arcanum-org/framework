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
 * Built-in directives ship with Shodo and are registered by default.
 * Custom directives can be registered by framework packages or app
 * developers through the DirectiveRegistry.
 */
final class TemplateCompiler
{
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

    private DirectiveRegistry $directives;

    /**
     * @param string $templatesDirectory Shared templates directory
     *     (e.g. app/Templates/). Used as a fallback when resolving
     *     layout and include paths that aren't found relative to
     *     the child template's directory.
     */
    public function __construct(
        private readonly \Arcanum\Parchment\Reader $reader = new \Arcanum\Parchment\Reader(),
        private readonly string $templatesDirectory = '',
        ?DirectiveRegistry $directives = null,
    ) {
        $this->directives = $directives ?? self::defaultDirectives();
    }

    /**
     * The directive registry.
     *
     * Exposed so framework packages and app developers can register
     * custom directives after construction (e.g. during bootstrap).
     */
    public function directives(): DirectiveRegistry
    {
        return $this->directives;
    }

    /**
     * Create the default registry with all built-in directives.
     */
    private static function defaultDirectives(): DirectiveRegistry
    {
        $registry = new DirectiveRegistry();
        $registry->register(new Directives\IncludeDirective());
        $registry->register(new Directives\LayoutDirective());
        $registry->register(new Directives\MatchDirective());
        $registry->register(new Directives\CsrfDirective());
        $registry->register(new Directives\ControlFlowDirective());

        return $registry;
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
     * HTML void elements — self-closing, never have children or a close tag.
     */
    private const VOID_ELEMENTS = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
        'link', 'meta', 'param', 'source', 'track', 'wbr',
    ];

    /**
     * Extract an HTML element by its id attribute from raw template source.
     *
     * Searches the source for an element with the given literal id value,
     * determines the tag name, and depth-counts open/close tags of that
     * type to find the matching close tag. Returns an ElementExtraction
     * with both the outerHTML (full element) and innerHTML (children only),
     * or null when no element with that id exists.
     *
     * Works on raw template source — Shodo directives ({{ if }}, {{ foreach }},
     * etc.) are treated as opaque text and pass through. The extraction
     * operates on HTML structure only.
     *
     * Supports nested ids naturally — extracting 'outer' returns everything
     * including inner elements with their own ids.
     *
     * Skips dynamic ids (id="{{ $foo }}") — only literal id="value" matches.
     */
    public function extractElementById(string $source, string $id): ?ElementExtraction
    {
        // Find the opening tag that contains id="$id" or id='$id'.
        // The pattern matches the tag name and everything up to the >.
        $escapedId = preg_quote($id, '/');
        $pattern = '/<([a-zA-Z][a-zA-Z0-9]*)\b([^>]*?\bid\s*=\s*["\']' . $escapedId . '["\'][^>]*)>/s';

        if (!preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $tagName = strtolower($match[1][0]);
        $openTagStart = $match[0][1];
        $openTagEnd = $openTagStart + strlen($match[0][0]);

        // Void elements have no children and no close tag.
        if (in_array($tagName, self::VOID_ELEMENTS, true)) {
            return new ElementExtraction(
                outerHtml: $match[0][0],
                innerHtml: '',
            );
        }

        // Depth-count to find the matching close tag.
        $depth = 1;
        $pos = $openTagEnd;
        $len = strlen($source);

        while ($depth > 0 && $pos < $len) {
            // Find the next opening or closing tag of the same name.
            $nextTag = $this->findNextTag($source, $tagName, $pos);

            if ($nextTag === null) {
                // No more tags found — unclosed element. Return what we have
                // up to end of source as a best-effort extraction.
                break;
            }

            if ($nextTag['type'] === 'open') {
                $depth++;
            } else {
                $depth--;
            }

            $pos = $nextTag['end'];
        }

        $outerHtml = substr($source, $openTagStart, $pos - $openTagStart);
        $innerHtml = substr($source, $openTagEnd, $pos - strlen("</$tagName>") - $openTagEnd);

        return new ElementExtraction(
            outerHtml: $outerHtml,
            innerHtml: $innerHtml,
        );
    }

    /**
     * Find the next opening or closing tag of the given name after $offset.
     *
     * Returns ['type' => 'open'|'close', 'end' => int] or null.
     * Skips self-closing tags (e.g. <div />) since they don't affect depth.
     *
     * @return array{type: 'open'|'close', end: int}|null
     */
    private function findNextTag(string $source, string $tagName, int $offset): ?array
    {
        $len = strlen($source);

        while ($offset < $len) {
            $ltPos = strpos($source, '<', $offset);
            if ($ltPos === false) {
                return null;
            }

            // Check for closing tag: </tagName>
            $closePrefix = '</' . $tagName;
            if (
                substr_compare($source, $closePrefix, $ltPos, strlen($closePrefix), true) === 0
                && isset($source[$ltPos + strlen($closePrefix)])
                && ($source[$ltPos + strlen($closePrefix)] === '>'
                    || ctype_space($source[$ltPos + strlen($closePrefix)]))
            ) {
                $gtPos = strpos($source, '>', $ltPos);
                if ($gtPos === false) {
                    return null;
                }
                return ['type' => 'close', 'end' => $gtPos + 1];
            }

            // Check for opening tag: <tagName (with word boundary)
            $openPrefix = '<' . $tagName;
            if (
                substr_compare($source, $openPrefix, $ltPos, strlen($openPrefix), true) === 0
                && isset($source[$ltPos + strlen($openPrefix)])
                && !ctype_alnum($source[$ltPos + strlen($openPrefix)])
                && $source[$ltPos + strlen($openPrefix)] !== '-'
            ) {
                $gtPos = strpos($source, '>', $ltPos);
                if ($gtPos === false) {
                    return null;
                }

                // Skip self-closing tags: <tag ... />
                if ($source[$gtPos - 1] === '/') {
                    $offset = $gtPos + 1;
                    continue;
                }

                return ['type' => 'open', 'end' => $gtPos + 1];
            }

            // Not a matching tag — advance past this <
            $offset = $ltPos + 1;
        }

        return null;
    }

    /**
     * Compile template source into PHP.
     *
     * Runs registered directives in priority order, then compiles
     * expression output (raw and escaped) as the catch-all final passes.
     *
     * When $fragment is true, layout inheritance is skipped — only the
     * 'content' section is rendered. This is used for htmx partial swaps
     * where the layout wrapper (head, nav, footer) is not needed.
     */
    public function compile(
        string $source,
        string $templateDirectory = '',
        bool $fragment = false,
    ): string {
        $this->lastDependencies = [];

        $context = new CompilerContext(
            templateDirectory: $templateDirectory,
            templatesDirectory: $this->templatesDirectory,
            fragment: $fragment,
            reader: $this->reader,
            dependencies: $this->lastDependencies,
        );

        // Run directives in priority order.
        foreach ($this->directives->all() as $directive) {
            $source = $directive->process($source, $context);
        }

        /*
         * Raw output: {{! $expr !}}
         *
         * Captures the body as a PHP expression, runs the helper-call
         * rewriter on it (so `{{! Html::csrf() !}}` and friends compile
         * through the same path as raw scalars), and emits a raw echo
         * tag. Must run before the escaped output pass so the `!`
         * boundaries are consumed first.
         */
        $source = $context->replaceCallback(
            '/\{\{!\s*(.+?)\s*!\}\}/s',
            fn (array $m) => '<?= ' . $context->rewriteHelperCalls($m[1]) . ' ?>',
            $source,
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
        $source = $context->replaceCallback(
            '/\{\{\s*(.+?)\s*\}\}/s',
            fn (array $m) => '<?= $__escape((string)(' . $context->rewriteHelperCalls($m[1]) . ')) ?>',
            $source,
        );

        return $source;
    }
}
