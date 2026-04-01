<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

/**
 * Formats data as a JSON string.
 *
 * Pretty-prints with unescaped slashes. Throws on unencodable data.
 */
class JsonFormatter implements Formatter
{
    public function format(mixed $data, string $dtoClass = ''): string
    {
        return json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }
}
