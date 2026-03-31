<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

/**
 * Generates a plain text representation of arbitrary data.
 *
 * Used as a fallback when no co-located .txt template exists.
 * Renders associative arrays as "key: value" lines, sequential arrays
 * as one value per line, and nested structures with indentation.
 */
final class PlainTextFallback
{
    public function render(mixed $data): string
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
        $indent = str_repeat('  ', $depth);
        $lines = [];

        foreach ($data as $key => $value) {
            $rendered = $this->renderValue($value, $depth + 1);

            if (is_array($value) || is_object($value)) {
                $lines[] = $indent . $key . ':';
                if ($rendered !== '') {
                    $lines[] = $rendered;
                }
            } else {
                $lines[] = $indent . $key . ': ' . $rendered;
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array<int|string, mixed> $data
     */
    private function renderSequentialArray(array $data, int $depth): string
    {
        $indent = str_repeat('  ', $depth);
        $lines = [];

        foreach ($data as $value) {
            $rendered = $this->renderValue($value, $depth + 1);
            if (is_array($value) || is_object($value)) {
                $lines[] = $indent . '-';
                if ($rendered !== '') {
                    $lines[] = $rendered;
                }
            } else {
                $lines[] = $indent . '- ' . $rendered;
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
