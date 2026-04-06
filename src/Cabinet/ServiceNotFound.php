<?php

declare(strict_types=1);

namespace Arcanum\Cabinet;

use Arcanum\Glitch\ArcanumException;
use Psr\Container\ContainerExceptionInterface;

class ServiceNotFound extends \InvalidArgumentException implements
    ArcanumException,
    ContainerExceptionInterface
{
    private ?string $suggestion = null;

    public function __construct(
        private readonly string $serviceName,
        string $message = '',
        \Throwable|null $previous = null,
    ) {
        parent::__construct(
            $message !== '' ? $message : "Service '{$serviceName}' not found",
            0,
            $previous,
        );
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function getTitle(): string
    {
        return 'Service Not Found';
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
