<?php

declare(strict_types=1);

namespace Arcanum\Cabinet;

use Arcanum\Glitch\ArcanumException;
use Psr\Container\ContainerExceptionInterface;

class CircularDependency extends \RuntimeException implements
    ArcanumException,
    ContainerExceptionInterface
{
    /**
     * @param string[] $chain
     */
    public function __construct(
        private readonly array $chain,
        \Throwable|null $previous = null,
    ) {
        parent::__construct(
            'Circular dependency detected: ' . implode(' → ', $chain),
            0,
            $previous,
        );
    }

    /**
     * @return string[]
     */
    public function getChain(): array
    {
        return $this->chain;
    }

    public function getTitle(): string
    {
        return 'Circular Dependency';
    }

    public function getSuggestion(): ?string
    {
        return 'Check your dependency chain: '
            . implode(' → ', $this->chain)
            . ' — one of these services needs to be restructured'
            . ' to break the cycle';
    }
}
