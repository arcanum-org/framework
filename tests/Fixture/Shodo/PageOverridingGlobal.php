<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Shodo;

use Arcanum\Shodo\Attribute\WithHelper;

#[WithHelper(EnvCheckHelper::class, 'Format')]
final class PageOverridingGlobal
{
}
