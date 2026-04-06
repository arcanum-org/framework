<?php

declare(strict_types=1);

namespace Arcanum\Forge;

use Arcanum\Glitch\ArcanumException;

class InvalidModelMethod extends \InvalidArgumentException implements ArcanumException
{
    private ?string $suggestion = null;

    public function __construct(
        private readonly string $method,
        \Throwable|null $previous = null,
    ) {
        parent::__construct(
            "Invalid model method name '{$method}'",
            0,
            $previous,
        );
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getTitle(): string
    {
        return 'Invalid Model Method';
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
