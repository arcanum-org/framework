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
 * Pre-compilation passes (run before PHP compilation):
 * - @include 'path' — inlines referenced file contents
 * - extends / section / yield directives — layout inheritance
 */
final class TemplateCompiler
{
    private const HELPER_PATTERN = '([A-Z][a-zA-Z0-9]*)::(\w+)((?:\((?:[^()]*+|\((?:[^()]*+|\([^()]*+\))*\))*\)))';

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
     * 'content' section is rendered. This is used for HTMX partial swaps
     * where the layout wrapper (head, nav, footer) is not needed.
     */
    public function compile(
        string $source,
        string $templateDirectory = '',
        bool $fragment = false,
    ): string {
        if ($templateDirectory !== '') {
            $source = $this->resolveIncludes($source, $templateDirectory);
            $source = $fragment
                ? $this->resolveFragment($source, $templateDirectory)
                : $this->resolveLayout($source, $templateDirectory);
        }

        // Order matters: raw output before escaped output to avoid double-matching.
        // Helper calls must be rewritten before the raw/escaped passes consume them,
        // otherwise Name::method() would compile as a real PHP static call.
        $compiled = $source;

        // Directives: {{ @csrf }} — raw output, no escape (intentional HTML).
        $compiled = $this->replace(
            '/\{\{\s*@csrf\s*\}\}/',
            '<?= $__helpers[\'Html\']->csrf() ?>',
            $compiled,
        );

        // Helper calls in raw output: {{! Route::url('x') !}}
        $compiled = $this->replaceCallback(
            '/\{\{!\s*' . self::HELPER_PATTERN . '\s*!\}\}/s',
            fn (array $m) => '<?= $__helpers[\'' . $m[1] . '\']->' . $m[2] . $m[3] . ' ?>',
            $compiled,
        );

        // Raw output: {{! $expr !}}
        $compiled = $this->replace(
            '/\{\{!\s*(.+?)\s*!\}\}/s',
            '<?= $1 ?>',
            $compiled,
        );

        // Control structures with arguments (foreach, if, elseif, for, while).
        // The optional trailing colon is stripped.
        $compiled = $this->replace(
            '/\{\{\s*(foreach|if|elseif|for|while)\s*(\(.+?\))\s*:?\s*\}\}/s',
            '<?php $1$2: ?>',
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

        // Helper calls in escaped output: {{ Route::url('x') }}
        $compiled = $this->replaceCallback(
            '/\{\{\s*' . self::HELPER_PATTERN . '\s*\}\}/s',
            fn (array $m) => '<?= $__escape((string)($__helpers[\'' . $m[1] . '\']->' . $m[2] . $m[3] . ')) ?>',
            $compiled,
        );

        // Escaped output: {{ $expr }} — calls $__escape provided by the renderer
        $compiled = $this->replace(
            '/\{\{\s*(.+?)\s*\}\}/s',
            '<?= $__escape((string)($1)) ?>',
            $compiled,
        );

        return $compiled;
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

    // ------------------------------------------------------------------
    // Pre-compilation: @include
    // ------------------------------------------------------------------

    /**
     * Resolve {{ @include 'path' }} directives by inlining file contents.
     *
     * Paths are relative to the given base directory. Supports nesting
     * (included files may themselves contain @include directives).
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
            '/\{\{\s*@include\s+\'([^\']+)\'\s*\}\}/',
            function (array $matches) use ($baseDirectory, $depth): string {
                $path = $this->resolveIncludePath(
                    $matches[1],
                    $baseDirectory,
                );
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
    // Pre-compilation: @extends / @section / @yield
    // ------------------------------------------------------------------

    /**
     * Resolve layout inheritance.
     *
     * If the source starts with the extends directive, extract all
     * {{ @section 'name' }}...{{ @endsection }} blocks from the child,
     * load the layout file, and replace {{ @yield 'name' }} placeholders
     * with the section contents.
     */
    private function resolveLayout(
        string $source,
        string $templateDirectory,
    ): string {
        // Check for @extends directive (must appear at the start, ignoring whitespace).
        if (!preg_match('/^\s*\{\{\s*@extends\s+\'([^\']+)\'\s*\}\}/s', $source, $extendsMatch)) {
            return $source;
        }

        $layoutName = $extendsMatch[1];

        // Strip the @extends directive from the child source.
        $childSource = substr($source, strlen($extendsMatch[0]));

        // Extract sections from the child.
        $sections = $this->extractSections($childSource);

        // Load and resolve includes in the layout.
        $layoutPath = $this->resolveLayoutPath(
            $layoutName,
            $templateDirectory,
        );
        $layoutSource = $this->reader->read($layoutPath);
        $layoutSource = $this->resolveIncludes(
            $layoutSource,
            dirname($layoutPath),
        );

        // Collect yield names from the layout.
        preg_match_all(
            '/\{\{\s*@yield\s+\'([^\']+)\'\s*\}\}/s',
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

        // Replace @yield directives in the layout with section content.
        return $this->replaceCallback(
            '/\{\{\s*@yield\s+\'([^\']+)\'\s*\}\}/s',
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
        if (!preg_match('/^\s*\{\{\s*@extends\s+\'([^\']+)\'\s*\}\}/s', $source, $extendsMatch)) {
            return $source;
        }

        $childSource = substr($source, strlen($extendsMatch[0]));
        $sections = $this->extractSections($childSource);

        return $sections['content'] ?? '';
    }

    /**
     * Extract {{ @section 'name' }}...{{ @endsection }} blocks.
     *
     * @return array<string, string> Section name → content.
     */
    private function extractSections(string $source): array
    {
        $sections = [];

        preg_match_all(
            '/\{\{\s*@section\s+\'([^\']+)\'\s*\}\}(.*?)\{\{\s*@endsection\s*\}\}/s',
            $source,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $sections[$match[1]] = trim($match[2]);
        }

        return $sections;
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
