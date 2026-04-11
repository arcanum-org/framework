<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

use Arcanum\Glitch\ArcanumException;

/**
 * Thrown when a template contains a {{ keyword }} that no registered
 * directive claims.
 */
class UnknownDirective extends \RuntimeException implements ArcanumException
{
    private ?string $suggestion = null;

    /**
     * @param list<string> $keywords   The unknown keyword(s) found.
     * @param list<string> $registered All keywords claimed by registered directives.
     */
    public function __construct(array $keywords, array $registered = [])
    {
        $message = sprintf(
            'Unknown template directive(s): %s.',
            implode(', ', $keywords),
        );

        if ($registered !== []) {
            $available = implode(', ', $registered);
            $message .= sprintf(' Registered directive keywords: %s.', $available);
            $this->suggestion = "Available directive keywords: {$available}";
        }

        parent::__construct($message);
    }

    public function getTitle(): string
    {
        return 'Unknown Template Directive';
    }

    public function getSuggestion(): ?string
    {
        return $this->suggestion;
    }
}
