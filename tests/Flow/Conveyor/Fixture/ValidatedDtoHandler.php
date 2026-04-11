<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Conveyor\Fixture;

final class ValidatedDtoHandler
{
    public function __invoke(ValidatedDto $dto): DoSomethingResult
    {
        return new DoSomethingResult($dto->name);
    }
}
