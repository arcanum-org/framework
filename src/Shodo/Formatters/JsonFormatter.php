<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Formatters;

use Arcanum\Shodo\Formatter;

/**
 * Formats data as a JSON string.
 *
 * Uses JSON_HEX_* flags to escape HTML-significant characters, preventing
 * XSS when JSON is embedded in HTML (e.g., </script> injection, attribute
 * breakout). Throws on unencodable data.
 */
class JsonFormatter implements Formatter
{
    public function format(mixed $data, string $templatePath = '', string $dtoClass = ''): string
    {
        return json_encode(
            $data,
            \JSON_THROW_ON_ERROR
            | \JSON_UNESCAPED_SLASHES
            | \JSON_HEX_TAG
            | \JSON_HEX_AMP
            | \JSON_HEX_APOS
            | \JSON_HEX_QUOT,
        );
    }
}
