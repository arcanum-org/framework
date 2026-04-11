<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

/**
 * Static analysis utilities for compiled templates.
 *
 * Used in dev mode to detect unused variables — data passed to a template
 * that the template never references. This can indicate data leakage
 * (sensitive data sent to a view unnecessarily) or a bug (typo in variable name).
 */
final class TemplateAnalyzer
{
    /**
     * Find variable keys that were passed but never referenced in the template.
     *
     * Scans only inside {{ }} and {{! !}} delimiters to avoid false positives
     * from $variable-like text in raw HTML or documentation content.
     *
     * @param list<string> $variableKeys Keys of variables passed to the template.
     * @return list<string> Keys that are not referenced in any template block.
     */
    public static function findUnusedVariables(string $templateSource, array $variableKeys): array
    {
        // Filter out internal variables — callers inject __escape, __helpers, etc.
        $userKeys = array_values(array_filter(
            $variableKeys,
            static fn(string $key): bool => !str_starts_with($key, '__'),
        ));

        if ($userKeys === []) {
            return [];
        }

        $usedVars = self::extractReferencedVariables($templateSource);

        return array_values(array_diff($userKeys, $usedVars));
    }

    /**
     * Log a warning about unused template variables.
     *
     * @param array<string, mixed> $variables All variables including internals.
     */
    public static function warnUnused(string $templateSource, array $variables, string $templatePath): void
    {
        $unused = self::findUnusedVariables($templateSource, array_keys($variables));

        if ($unused === []) {
            return;
        }

        $total = count(array_filter(
            array_keys($variables),
            static fn(string $key): bool => !str_starts_with($key, '__'),
        ));
        $usedCount = $total - count($unused);

        @trigger_error(sprintf(
            'Arcanum: template "%s" used %d of %d variables — unused: %s.',
            $templatePath,
            $usedCount,
            $total,
            implode(', ', $unused),
        ), \E_USER_NOTICE);
    }

    /**
     * Extract all $variable references from inside template delimiters.
     *
     * @return list<string> Unique variable names referenced in template blocks.
     */
    private static function extractReferencedVariables(string $source): array
    {
        // Match all {{ ... }} and {{! ... !}} blocks.
        if (preg_match_all('/\{\{!?\s*(.+?)\s*!?\}\}/s', $source, $blocks) === 0) {
            return [];
        }

        $allBlockContent = implode(' ', $blocks[1]);

        // Match $variable references inside the extracted block content.
        if (preg_match_all('/\$([a-zA-Z_]\w*)/', $allBlockContent, $vars) === 0) {
            return [];
        }

        return array_values(array_unique($vars[1]));
    }
}
