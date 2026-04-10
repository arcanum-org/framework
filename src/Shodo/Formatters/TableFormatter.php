<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Formatters;

use Arcanum\Shodo\Formatter;

/**
 * Formats list data as an ASCII table.
 *
 * Auto-detects columns from the keys of the first row. Objects are
 * converted to associative arrays via get_object_vars(). Non-tabular
 * data is rendered as a single-column table.
 */
class TableFormatter implements Formatter
{
    public function format(mixed $data, string $dtoClass = '', int $statusCode = 0): string
    {
        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        if (!is_array($data) || $data === []) {
            return '';
        }

        $rows = $this->normalizeRows($data);

        if ($rows === []) {
            return '';
        }

        $headers = array_keys($rows[0]);
        $widths = $this->calculateWidths($headers, $rows);

        $lines = [];
        $lines[] = $this->renderLine($widths, '┌', '┬', '┐');
        $lines[] = $this->renderRow($headers, $widths);
        $lines[] = $this->renderLine($widths, '├', '┼', '┤');

        foreach ($rows as $row) {
            $values = array_map(
                static fn(string $key): string => $row[$key],
                $headers,
            );
            $lines[] = $this->renderRow($values, $widths);
        }

        $lines[] = $this->renderLine($widths, '└', '┴', '┘');

        return implode(\PHP_EOL, $lines);
    }

    /**
     * Normalize input into a list of string-keyed string-valued arrays.
     *
     * @param array<mixed> $data
     * @return list<array<string, string>>
     */
    private function normalizeRows(array $data): array
    {
        // List of arrays/objects — tabular
        if (array_is_list($data) && (is_array($data[0]) || is_object($data[0]))) {
            $rows = [];
            foreach ($data as $item) {
                if (is_object($item)) {
                    $item = get_object_vars($item);
                }
                if (is_array($item)) {
                    $rows[] = array_map(
                        static fn(mixed $v): string => self::stringify($v),
                        $item,
                    );
                }
            }
            return $rows;
        }

        // Associative array — key-value table
        if (!array_is_list($data)) {
            $rows = [];
            foreach ($data as $key => $value) {
                $rows[] = ['key' => (string) $key, 'value' => self::stringify($value)];
            }
            return $rows;
        }

        // List of scalars — single column
        $rows = [];
        foreach ($data as $value) {
            $rows[] = ['value' => self::stringify($value)];
        }
        return $rows;
    }

    /**
     * Calculate column widths from headers and data.
     *
     * @param list<string> $headers
     * @param list<array<string, string>> $rows
     * @return list<int>
     */
    private function calculateWidths(array $headers, array $rows): array
    {
        $widths = array_map(static fn(string $h): int => strlen($h), $headers);

        foreach ($rows as $row) {
            foreach ($headers as $i => $header) {
                $len = strlen($row[$header]);
                if ($len > $widths[$i]) {
                    $widths[$i] = $len;
                }
            }
        }

        return $widths;
    }

    /**
     * @param list<string> $cells
     * @param list<int> $widths
     */
    private function renderRow(array $cells, array $widths): string
    {
        $parts = [];
        foreach ($cells as $i => $cell) {
            $parts[] = ' ' . str_pad($cell, $widths[$i]) . ' ';
        }
        return '│' . implode('│', $parts) . '│';
    }

    /**
     * @param list<int> $widths
     */
    private function renderLine(array $widths, string $left, string $mid, string $right): string
    {
        $parts = array_map(
            static fn(int $w): string => str_repeat('─', $w + 2),
            $widths,
        );
        return $left . implode($mid, $parts) . $right;
    }

    private static function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return (string) json_encode($value, \JSON_UNESCAPED_SLASHES);
    }
}
