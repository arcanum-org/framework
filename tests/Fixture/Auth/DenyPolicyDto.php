<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Auth;

use Arcanum\Auth\Attribute\RequiresPolicy;

#[RequiresPolicy(DenyPolicy::class)]
final class DenyPolicyDto
{
}
