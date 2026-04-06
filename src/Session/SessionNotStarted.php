<?php

declare(strict_types=1);

namespace Arcanum\Session;

use Arcanum\Glitch\ArcanumException;

class SessionNotStarted extends \RuntimeException implements ArcanumException
{
    public function __construct(\Throwable|null $previous = null)
    {
        parent::__construct(
            'No active session',
            0,
            $previous,
        );
    }

    public function getTitle(): string
    {
        return 'Session Not Started';
    }

    public function getSuggestion(): ?string
    {
        return 'Ensure SessionMiddleware is registered'
            . ' in the HTTP middleware stack';
    }
}
