<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Formatters;

use Arcanum\Shodo\Formatter;

/**
 * Generates a Markdown representation of arbitrary data.
 *
 * Used as a fallback when no co-located .md template exists.
 * Renders associative arrays as bold-key lines, sequential arrays
 * as bulleted lists, and nested structures with headings.
 */
final class MarkdownFallbackFormatter implements Formatter
{
    public function format(mixed $data, string $dtoClass = ''): string
    {
        return rtrim($this->renderValue($data, 0));
    }

    private function renderValue(mixed $value, int $depth): string
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (is_array($value)) {
            if ($value === []) {
                return '';
            }

            if ($this->isAssociative($value)) {
                return $this->renderAssociativeArray($value, $depth);
            }

            return $this->renderSequentialArray($value, $depth);
        }

        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (!is_scalar($value)) {
            return '';
        }

        return (string) $value;
    }

    /**
     * @param array<string|int, mixed> $data
     */
    private function renderAssociativeArray(array $data, int $depth): string
    {
        $lines = [];

        foreach ($data as $key => $value) {
            $rendered = $this->renderValue($value, $depth + 1);

            if (is_array($value) || is_object($value)) {
                $heading = str_repeat('#', min($depth + 2, 6));
                $lines[] = $heading . ' ' . $key;
                if ($rendered !== '') {
                    $lines[] = '';
                    $lines[] = $rendered;
                }
            } else {
                $lines[] = '**' . $key . ':** ' . $rendered;
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array<int|string, mixed> $data
     */
    private function renderSequentialArray(array $data, int $depth): string
    {
        $lines = [];

        foreach ($data as $value) {
            $rendered = $this->renderValue($value, $depth + 1);
            if (is_array($value) || is_object($value)) {
                $lines[] = '-';
                if ($rendered !== '') {
                    $lines[] = $rendered;
                }
            } else {
                $lines[] = '- ' . $rendered;
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array<mixed> $array
     */
    private function isAssociative(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
