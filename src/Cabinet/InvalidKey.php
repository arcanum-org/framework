<?php

declare(strict_types=1);

namespace Arcanum\Cabinet;

use Arcanum\Glitch\ArcanumException;
use Psr\Container\ContainerExceptionInterface;

/**
 * Invalid Key Exception
 * ---------------------
 *
 * The invalid key exception is thrown when a key used to access a service
 * via the ArrayAccess interface is not a string.
 */
final class InvalidKey extends \InvalidArgumentException implements
    ArcanumException,
    ContainerExceptionInterface
{
    private string $suggestion;

    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Invalid Key: $message", $code, $previous);

        $this->suggestion = 'Container keys must be strings'
            . ' — typically a class name or interface name';
    }

    public function getTitle(): string
    {
        return 'Invalid Container Key';
    }

    public function getSuggestion(): string
    {
        return $this->suggestion;
    }
}
