<?php

declare(strict_types=1);

namespace Arcanum\Codex\Error;

use Arcanum\Glitch\ArcanumException;
use Psr\Container\ContainerExceptionInterface;

class Unresolvable extends \InvalidArgumentException implements
    ArcanumException,
    ContainerExceptionInterface
{
    private ?string $suggestion = null;

    public function getTitle(): string
    {
        return 'Unresolvable Dependency';
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
