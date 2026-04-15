<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Conveyor\Fixture;

/**
 * Handler with an unresolvable dependency — an interface with no binding.
 */
final class BrokenDepHandler
{
    public function __construct(
        private readonly \Psr\Log\LoggerInterface $logger,
    ) {
    }

    public function __invoke(BrokenDep $dto): BrokenDep
    {
        $this->logger->info('invoked');
        return $dto;
    }
}
