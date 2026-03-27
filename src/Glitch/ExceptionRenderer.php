<?php

declare(strict_types=1);

namespace Arcanum\Glitch;

use Psr\Http\Message\ResponseInterface;

interface ExceptionRenderer
{
    /**
     * Render an exception into an HTTP response.
     */
    public function render(\Throwable $e): ResponseInterface;
}
