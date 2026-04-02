<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

/**
 * Maps a file extension to a content type and renderer class.
 */
final class Format
{
    /**
     * @param string $extension The file extension (e.g., 'json', 'html', 'csv').
     * @param string $contentType The HTTP content type (e.g., 'application/json').
     * @param string $rendererClass The renderer class to use for this format.
     */
    public function __construct(
        public readonly string $extension,
        public readonly string $contentType,
        public readonly string $rendererClass,
    ) {
    }
}
