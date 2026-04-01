<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

/**
 * CLI-specific JSON renderer that returns a pretty-printed JSON string.
 *
 * Unlike JsonRenderer (which returns a ResponseInterface for HTTP),
 * this renderer returns a plain string for CLI output.
 */
class CliJsonRenderer implements Renderer
{
    public function render(mixed $data, string $dtoClass = ''): string
    {
        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        return (string) json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
    }
}
