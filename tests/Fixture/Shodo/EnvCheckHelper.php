<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Shodo;

final class EnvCheckHelper
{
    public function phpVersion(): string
    {
        return PHP_VERSION;
    }
}
