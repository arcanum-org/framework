<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Directives;

use Arcanum\Shodo\CompilerContext;
use Arcanum\Shodo\CompilerDirective;

/**
 * Compiles control structures: if, elseif, else, endif, foreach, endforeach,
 * for, endfor, while, endwhile.
 *
 * Three accepted forms for conditions:
 *   {{ if $foo > 0 }}        — preferred, paren-free
 *   {{ if ($foo > 0) }}      — also accepted
 *   {{ if ($foo > 0): }}     — also accepted (PHP alt syntax)
 *
 * All normalise to canonical PHP alt-syntax with explicit parens.
 * Helper calls inside conditions are rewritten through the helper registry.
 */
final class ControlFlowDirective implements CompilerDirective
{
    public function keywords(): array
    {
        return [
            'foreach', 'endforeach',
            'if', 'elseif', 'else', 'endif',
            'for', 'endfor',
            'while', 'endwhile',
        ];
    }

    public function priority(): int
    {
        return 500;
    }

    public function process(string $source, CompilerContext $context): string
    {
        // Control structures with conditions (foreach, if, elseif, for, while).
        $source = $context->replaceCallback(
            '/\{\{\s*(foreach|if|elseif|for|while)(?:\s+|(?=\())(.+?)\s*\}\}/s',
            function (array $m) use ($context): string {
                $expr = $this->normaliseControlExpression($m[2]);
                $expr = $context->rewriteHelperCalls($expr);
                return '<?php ' . $m[1] . ' (' . $expr . '): ?>';
            },
            $source,
        );

        // else (no arguments, no colon)
        $source = $context->replace(
            '/\{\{\s*else\s*\}\}/',
            '<?php else: ?>',
            $source,
        );

        // End tags: endforeach, endif, endfor, endwhile
        $source = $context->replace(
            '/\{\{\s*(endforeach|endif|endfor|endwhile)\s*\}\}/',
            '<?php $1; ?>',
            $source,
        );

        return $source;
    }

    /**
     * Normalise a control-structure expression to its bare form.
     *
     * Strips a trailing `:` (the developer wrote PHP alt-syntax style)
     * and a single layer of outer parens (only when balanced as a true
     * wrapping pair, not just two separately-grouped sub-expressions).
     */
    private function normaliseControlExpression(string $raw): string
    {
        $expr = trim($raw);

        if (str_ends_with($expr, ':')) {
            $expr = trim(substr($expr, 0, -1));
        }

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
}
