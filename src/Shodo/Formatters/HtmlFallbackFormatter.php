<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Formatters;

use Arcanum\Shodo\Formatter;

/**
 * Generates a simple valid HTML document from arbitrary data.
 *
 * Used as a fallback when no co-located template exists for a handler.
 * Renders associative arrays as definition lists, sequential arrays as
 * unordered lists, objects by their public properties, and scalars as
 * paragraphs. All output is HTML-escaped.
 */
final class HtmlFallbackFormatter implements Formatter
{
    public function format(mixed $data, string $dtoClass = ''): string
    {
        $body = $this->renderValue($data);

        return '<!DOCTYPE html>'
            . '<html lang="en">'
            . '<head><meta charset="UTF-8"><title>Response</title></head>'
            . '<body>' . $body . '</body>'
            . '</html>';
    }

    private function renderValue(mixed $value): string
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (is_array($value)) {
            if ($value === []) {
                return '';
            }

            if ($this->isAssociative($value)) {
                return $this->renderAssociativeArray($value);
            }

            return $this->renderSequentialArray($value);
        }

        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return '<p>' . ($value ? '1' : '') . '</p>';
        }

        if (!is_scalar($value)) {
            return '';
        }

        return '<p>' . $this->escape((string) $value) . '</p>';
    }

    /**
     * @param array<string|int, mixed> $data
     */
    private function renderAssociativeArray(array $data): string
    {
        $html = '<dl>';
        foreach ($data as $key => $value) {
            $html .= '<dt>' . $this->escape((string) $key) . '</dt>';
            $html .= '<dd>' . $this->renderValue($value) . '</dd>';
        }
        $html .= '</dl>';

        return $html;
    }

    /**
     * @param array<int|string, mixed> $data
     */
    private function renderSequentialArray(array $data): string
    {
        $html = '<ul>';
        foreach ($data as $value) {
            $html .= '<li>' . $this->renderValue($value) . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    /**
     * @param array<mixed> $array
     */
    private function isAssociative(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
