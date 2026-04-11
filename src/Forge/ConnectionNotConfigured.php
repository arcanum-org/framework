<?php

declare(strict_types=1);

namespace Arcanum\Forge;

use Arcanum\Glitch\ArcanumException;

class ConnectionNotConfigured extends \RuntimeException implements ArcanumException
{
    private ?string $suggestion = null;

    public function __construct(
        private readonly string $connectionName,
        \Throwable|null $previous = null,
    ) {
        parent::__construct(
            "Database connection \"{$connectionName}\" is not configured",
            0,
            $previous,
        );
    }

    public function getConnectionName(): string
    {
        return $this->connectionName;
    }

    public function getTitle(): string
    {
        return 'Connection Not Configured';
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
