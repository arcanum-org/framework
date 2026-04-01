<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

/**
 * Default CLI output renderer.
 *
 * Auto-detects output format from data shape:
 *   - Single object/associative array → key-value pairs
 *   - List of objects/arrays → ASCII table (delegates to TableRenderer)
 *   - Scalar → plain text
 *   - Empty → empty string
 *
 * Returns a string. The kernel writes it to Output.
 */
class CliRenderer implements Renderer
{
    public function __construct(
        private readonly TableRenderer $tableRenderer = new TableRenderer(),
    ) {
    }

    public function render(mixed $data, string $dtoClass = ''): string
    {
        if ($data === null) {
            return '';
        }

        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        if (is_scalar($data)) {
            return (string) $data;
        }

        if (!is_array($data) || $data === []) {
            return '';
        }

        // List of arrays/objects — render as table
        if ($this->isTabular($data)) {
            return $this->tableRenderer->render($data);
        }

        // Associative array — render as key-value pairs
        return $this->renderKeyValue($data);
    }

    /**
     * Render an associative array as aligned key-value pairs.
     *
     * @param array<string|int, mixed> $data
     */
    private function renderKeyValue(array $data): string
    {
        $maxKeyLen = 0;
        foreach (array_keys($data) as $key) {
            $len = strlen((string) $key);
            if ($len > $maxKeyLen) {
                $maxKeyLen = $len;
            }
        }

        $lines = [];
        foreach ($data as $key => $value) {
            $formatted = is_scalar($value) || $value === null
                ? (string) $value
                : (string) json_encode($value, \JSON_UNESCAPED_SLASHES);
            $lines[] = sprintf('  %-' . $maxKeyLen . 's  %s', $key, $formatted);
        }

        return implode(\PHP_EOL, $lines);
    }

    /**
     * Check if data is a list of arrays or objects (tabular).
     *
     * @param array<mixed> $data
     */
    private function isTabular(array $data): bool
    {
        if (!array_is_list($data)) {
            return false;
        }

        foreach ($data as $row) {
            if (!is_array($row) && !is_object($row)) {
                return false;
            }
        }

        return true;
    }
}
