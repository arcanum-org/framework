<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

/**
 * Thrown when a requested template helper alias is not registered.
 */
class UnknownHelper extends \RuntimeException
{
    /**
     * @param list<string> $registered
     */
    public function __construct(string $alias, array $registered = [])
    {
        $message = sprintf('Template helper "%s" is not registered.', $alias);

        if ($registered !== []) {
            $message .= sprintf(' Registered helpers: %s.', implode(', ', $registered));
        }

        parent::__construct($message);
    }
}
