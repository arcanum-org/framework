<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

/**
 * Base contract for converting data into output.
 *
 * Returns mixed intentionally — content renderers (JsonRenderer,
 * HtmlRenderer, CsvRenderer, CliRenderer, TableRenderer) return
 * strings. Each Kernel wraps the string into its transport's
 * response: HTTP kernels build a ResponseInterface, CLI kernels
 * write to Output.
 */
interface Renderer
{
    /**
     * Render the given data into output.
     */
    public function render(mixed $data): mixed;
}
