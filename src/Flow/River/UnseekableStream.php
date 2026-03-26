<?php

declare(strict_types=1);

namespace Arcanum\Flow\River;

final class UnseekableStream extends \InvalidArgumentException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Unseekable stream: $message", $code, $previous);
    }
}
