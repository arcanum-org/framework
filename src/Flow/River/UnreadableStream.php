<?php

declare(strict_types=1);

namespace Arcanum\Flow\River;

final class UnreadableStream extends \RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Unreadable stream: $message", $code, $previous);
    }
}
