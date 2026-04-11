<?php

declare(strict_types=1);

namespace Arcanum\Flow\Conveyor;

use Arcanum\Glitch\ArcanumException;

class HandlerNotFound extends \RuntimeException implements ArcanumException
{
    public function __construct(
        private readonly string $dtoClass,
        private readonly string $handlerClass,
        \Throwable|null $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'No handler found for %s — expected class %s'
                    . ' with an __invoke() method',
                $dtoClass,
                $handlerClass,
            ),
            0,
            $previous,
        );
    }

    public function getDtoClass(): string
    {
        return $this->dtoClass;
    }

    public function getHandlerClass(): string
    {
        return $this->handlerClass;
    }

    public function getTitle(): string
    {
        return 'Handler Not Found';
    }

    public function getSuggestion(): ?string
    {
        return "Create {$this->handlerClass} with a public __invoke()"
            . " method, or run 'php arcanum validate:handlers'"
            . " to check registration";
    }
}
