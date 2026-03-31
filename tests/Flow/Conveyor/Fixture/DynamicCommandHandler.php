<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Conveyor\Fixture;

use Arcanum\Flow\Conveyor\Command;

final class DynamicCommandHandler
{
    public function __invoke(Command $command): DynamicCommandResult
    {
        /** @var string $name */
        $name = $command->get('name', 'unknown');
        return new DynamicCommandResult($name);
    }
}
