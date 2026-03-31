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
 */
final class TemplateCompiler
{
    /**
     * Compile template source into PHP.
     */
    public function compile(string $source): string
    {
        // Order matters: raw output before escaped output to avoid double-matching.
        $compiled = $source;

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
}
