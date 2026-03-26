<?php

declare(strict_types=1);

namespace Arcanum\Flow\Conveyor\Middleware;

final class InvalidDTO extends \InvalidArgumentException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Invalid DTO: $message", $code, $previous);
    }
}
