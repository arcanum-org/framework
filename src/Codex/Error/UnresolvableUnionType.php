<?php

declare(strict_types=1);

namespace Arcanum\Codex\Error;

final class UnresolvableUnionType extends Unresolvable
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Unresolvable Union Type: $message", $code, $previous);
    }
}
