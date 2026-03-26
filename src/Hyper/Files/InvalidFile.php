<?php

declare(strict_types=1);

namespace Arcanum\Hyper\Files;

final class InvalidFile extends \InvalidArgumentException
{
    public function __construct(string $message = 'The file is invalid.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Invalid File: $message", $code, $previous);
    }
}
