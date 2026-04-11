<?php

declare(strict_types=1);

namespace Arcanum\Atlas;

use Arcanum\Glitch\ArcanumException;

class UnresolvableRoute extends \RuntimeException implements ArcanumException
{
    private ?string $suggestion = null;

    public function getTitle(): string
    {
        return 'Route Not Found';
    }

    public function getSuggestion(): ?string
    {
        return $this->suggestion;
    }

    public function withSuggestion(string $suggestion): static
    {
        $this->suggestion = $suggestion;

        return $this;
    }
}
