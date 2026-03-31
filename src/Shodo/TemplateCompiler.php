<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

/**
 * Stateless regex-based template compiler.
 *
 * Translates Arcanum template syntax into PHP. All directives live inside
 * {{ }} delimiters. The compiled output is valid PHP suitable for eval()
 * or writing to a cache file.
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

        // Escaped output: {{ $expr }}
        $compiled = $this->replace(
            '/\{\{\s*(.+?)\s*\}\}/s',
            '<?= htmlspecialchars((string)($1), ENT_QUOTES, \'UTF-8\') ?>',
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
