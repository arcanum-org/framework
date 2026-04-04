<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Integration\Command;

use Arcanum\Test\Fixture\Integration\Query\Status;

/**
 * Returns a Query DTO to signal where the created resource can be read.
 * The framework uses this to build a Location header on the 201 response.
 */
final class CreateItemHandler
{
    public function __invoke(CreateItem $command): Status
    {
        return new Status(verbose: true);
    }
}
