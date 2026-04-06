<?php

declare(strict_types=1);

namespace Arcanum\Vault;

use Arcanum\Glitch\ArcanumException;

class StoreNotFound extends \RuntimeException implements ArcanumException
{
    private ?string $suggestion = null;

    public function __construct(
        private readonly string $storeName,
        \Throwable|null $previous = null,
    ) {
        parent::__construct(
            "Cache store \"{$storeName}\" is not configured",
            0,
            $previous,
        );
    }

    public function getStoreName(): string
    {
        return $this->storeName;
    }

    public function getTitle(): string
    {
        return 'Cache Store Not Found';
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
