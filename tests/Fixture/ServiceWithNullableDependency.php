<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture;

use Psr\Log\LoggerInterface;

final class ServiceWithNullableDependency
{
    public function __construct(
        public readonly string $name,
        public readonly LoggerInterface|null $logger = null,
    ) {
    }
}
