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
 * - @extends 'layout' / @section 'name' / @yield 'name' — layout inheritance
 */
final class TemplateCompiler
{
    private const HELPER_PATTERN = '([A-Z][a-zA-Z0-9]*)::(\w+)((?:\((?:[^()]*+|\((?:[^()]*+|\([^()]*+\))*\))*\)))';

    public function __construct(
        private readonly \Arcanum\Parchment\Reader $reader = new \Arcanum\Parchment\Reader(),
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
     * @include inlining and @extends/@section/@yield layout inheritance.
     */
    public function compile(string $source, string $templateDirectory = ''): string
    {
        if ($templateDirectory !== '') {
            $source = $this->resolveIncludes($source, $templateDirectory);
            $source = $this->resolveLayout($source, $templateDirectory);
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
     * Resolve an include path relative to the base directory.
     *
     * Tries the path as-is first, then appends common extensions.
     */
    private function resolveIncludePath(
        string $path,
        string $baseDirectory,
    ): string {
        $absolute = $baseDirectory . DIRECTORY_SEPARATOR . $path;

        if (is_file($absolute)) {
            return $absolute;
        }

        // Try with .html extension if no extension was given.
        if (pathinfo($path, PATHINFO_EXTENSION) === '') {
            $withExt = $absolute . '.html';
            if (is_file($withExt)) {
                return $withExt;
            }
        }

        throw new \RuntimeException(sprintf(
            'Include file not found: %s (resolved from %s)',
            $path,
            $baseDirectory,
        ));
    }

    // ------------------------------------------------------------------
    // Pre-compilation: @extends / @section / @yield
    // ------------------------------------------------------------------

    /**
     * Resolve layout inheritance.
     *
     * If the source starts with {{ @extends 'layout' }}, extract all
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
     * Walks up from the template directory looking for the layout file,
     * trying each directory until the application root.
     */
    private function resolveLayoutPath(
        string $name,
        string $startDirectory,
    ): string {
        $directory = $startDirectory;

        // Try with and without .html extension.
        $candidates = pathinfo($name, PATHINFO_EXTENSION) === ''
            ? [$name . '.html', $name]
            : [$name];

        // Walk up directories looking for the layout.
        $previousDirectory = '';
        while ($directory !== $previousDirectory) {
            foreach ($candidates as $candidate) {
                $path = $directory . DIRECTORY_SEPARATOR . $candidate;
                if (is_file($path)) {
                    return $path;
                }
            }
            $previousDirectory = $directory;
            $directory = dirname($directory);
        }

        throw new \RuntimeException(sprintf(
            'Layout file not found: %s (searched from %s)',
            $name,
            $startDirectory,
        ));
    }
}
