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
 */
final class TemplateCompiler
{
    private const HELPER_PATTERN = '([A-Z][a-zA-Z0-9]*)::(\w+)((?:\((?:[^()]*+|\((?:[^()]*+|\([^()]*+\))*\))*\)))';

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
     */
    public function compile(string $source): string
    {
        // Order matters: raw output before escaped output to avoid double-matching.
        // Helper calls must be rewritten before the raw/escaped passes consume them,
        // otherwise Name::method() would compile as a real PHP static call.
        $compiled = $source;

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
}
