<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Shodo;

use Arcanum\Shodo\Attribute\WithHelper;

#[WithHelper(EnvCheckHelper::class, 'EnvCheck')]
#[WithHelper(IncantationHelper::class, 'Tip')]
final class PageWithHelpers
{
}
