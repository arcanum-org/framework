<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

/**
 * Base contract for converting data into output.
 *
 * The output type varies by context: ResponseInterface for HTTP,
 * stream or string for CLI. ExceptionRenderer is the first
 * specialization of this concept.
 */
interface Renderer
{
    /**
     * Render the given data into output.
     */
    public function render(mixed $data): mixed;
}
