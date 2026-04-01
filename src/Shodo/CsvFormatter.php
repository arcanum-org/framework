<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

/**
 * Formats data as a CSV string.
 *
 * Expects data as a list of associative arrays (rows). The keys of the
 * first row become the header row. Scalar data is wrapped in a single
 * row. Associative arrays (non-list) are rendered as key-value pairs.
 */
class CsvFormatter implements Formatter
{
    public function format(mixed $data, string $dtoClass = ''): string
    {
        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        if (!is_array($data)) {
            return $this->arrayToCsv([['value' => $data]]);
        }

        if ($data === []) {
            return '';
        }

        // List of arrays — tabular data
        if ($this->isTabular($data)) {
            /** @var list<array<string, mixed>> $data */
            return $this->arrayToCsv($data);
        }

        // Associative array — render as key-value pairs
        if ($this->isAssociative($data)) {
            return $this->arrayToCsv(
                array_map(
                    static fn(string|int $key, mixed $value): array => [
                        'key' => $key,
                        'value' => $value,
                    ],
                    array_keys($data),
                    array_values($data),
                ),
            );
        }

        // Sequential array of scalars — single column
        /** @var list<array<string, mixed>> $rows */
        $rows = array_map(
            static fn(mixed $value): array => ['value' => $value],
            $data,
        );
        return $this->arrayToCsv($rows);
    }

    /**
     * @param list<array<string|int, mixed>> $rows
     */
    private function arrayToCsv(array $rows): string
    {
        if ($rows === []) {
            return '';
        }

        $handle = fopen('php://memory', 'w+');
        if ($handle === false) {
            throw new \RuntimeException('Failed to open memory stream for CSV encoding');
        }

        // Header row from first row's keys
        $headers = array_keys($rows[0]);
        fputcsv($handle, $headers, escape: '\\');

        foreach ($rows as $row) {
            $flat = array_map(
                static fn(mixed $value): string => is_scalar($value) || $value === null
                    ? (string) $value
                    : json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES),
                $row,
            );
            fputcsv($handle, $flat, escape: '\\');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }

    /**
     * Check if data is a list of associative arrays (tabular).
     *
     * @param array<mixed> $data
     */
    private function isTabular(array $data): bool
    {
        if (!array_is_list($data)) {
            return false;
        }

        foreach ($data as $row) {
            if (!is_array($row)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<mixed> $array
     */
    private function isAssociative(array $array): bool
    {
        return !array_is_list($array);
    }
}
