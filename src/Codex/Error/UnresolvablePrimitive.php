<?php

declare(strict_types=1);

namespace Arcanum\Codex\Error;

final class UnresolvablePrimitive extends Unresolvable
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Unresolvable Primitive: $message", $code, $previous);
    }
}
