<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Directives;

use Arcanum\Shodo\CompilerContext;
use Arcanum\Shodo\CompilerDirective;

/**
 * Resolves {{ match }}/{{ case }}/{{ default }}/{{ endmatch }} blocks
 * into PHP switch alt-syntax with implicit break after each case.
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
 *       ...
 *   <?php endswitch; ?>
 */
final class MatchDirective implements CompilerDirective
{
    public function keywords(): array
    {
        return ['match', 'case', 'default', 'endmatch'];
    }

    public function priority(): int
    {
        return 300;
    }

    public function process(string $source, CompilerContext $context): string
    {
        return $context->replaceCallback(
            '/\{\{\s*match\s+(.+?)\s*\}\}(.*?)\{\{\s*endmatch\s*\}\}/s',
            function (array $matches): string {
                $subject = trim($matches[1]);
                $body = $matches[2];

                $output = '<?php switch (' . $subject . '): ?>';

                $parts = preg_split(
                    '/(\{\{\s*(?:case\s+[^}]+?|default)\s*\}\})/s',
                    $body,
                    -1,
                    PREG_SPLIT_DELIM_CAPTURE,
                );

                if ($parts === false || $parts === []) {
                    return $output . '<?php endswitch; ?>';
                }

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
     * parens, and brackets.
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
}
