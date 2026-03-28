<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\StatusCode;

/**
 * Thrown when a requested format is not registered.
 *
 * Maps to HTTP 406 Not Acceptable.
 */
class UnsupportedFormat extends HttpException
{
    public function __construct(string $extension)
    {
        parent::__construct(
            StatusCode::NotAcceptable,
            sprintf('Format "%s" is not supported.', $extension),
        );
    }
}
