<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

use Arcanum\Glitch\ArcanumException;

/**
 * Thrown when a requested template helper alias is not registered.
 */
class UnknownHelper extends \RuntimeException implements ArcanumException
{
    private ?string $suggestion = null;

    /**
     * @param list<string> $registered
     */
    public function __construct(string $alias, array $registered = [])
    {
        $message = sprintf('Template helper "%s" is not registered.', $alias);

        if ($registered !== []) {
            $available = implode(', ', $registered);
            $message .= sprintf(' Registered helpers: %s.', $available);
            $this->suggestion = "Available helpers: {$available}";
        }

        parent::__construct($message);
    }

    public function getTitle(): string
    {
        return 'Unknown Template Helper';
    }

    public function getSuggestion(): ?string
    {
        return $this->suggestion;
    }
}
