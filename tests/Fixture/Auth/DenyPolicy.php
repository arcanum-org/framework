<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Auth;

use Arcanum\Auth\Identity;
use Arcanum\Auth\Policy;

final class DenyPolicy implements Policy
{
    public function authorize(Identity $identity, object $dto): bool
    {
        return false;
    }
}
