<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Formatters;

use Arcanum\Shodo\Formatter;

/**
 * Formats data as a JSON string.
 *
 * Uses JSON_HEX_TAG to escape < and > as \u003C / \u003E, preventing
 * XSS when JSON is embedded in HTML (e.g., </script> injection).
 * Throws on unencodable data.
 */
class JsonFormatter implements Formatter
{
    public function format(mixed $data, string $dtoClass = ''): string
    {
        return json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_HEX_TAG);
    }
}
