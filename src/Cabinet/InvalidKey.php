<?php

declare(strict_types=1);

namespace Arcanum\Cabinet;

use Psr\Container\ContainerExceptionInterface;

/**
 * Invalid Key Exception
 * ---------------------
 *
 * The invalid key exception is thrown when a key used to access a service
 * via the ArrayAccess interface is not a string.
 */
final class InvalidKey extends \InvalidArgumentException implements ContainerExceptionInterface
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Invalid Key: $message", $code, $previous);
    }
}
