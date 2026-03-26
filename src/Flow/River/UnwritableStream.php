<?php

declare(strict_types=1);

namespace Arcanum\Flow\River;

final class UnwritableStream extends \RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Unwritable stream: $message", $code, $previous);
    }
}
