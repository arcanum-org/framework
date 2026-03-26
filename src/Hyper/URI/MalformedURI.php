<?php

declare(strict_types=1);

namespace Arcanum\Hyper\URI;

final class MalformedURI extends \InvalidArgumentException
{
    public function __construct(string $message = 'The URI is malformed.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Malformed URI: $message", $code, $previous);
    }
}
