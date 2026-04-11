<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Auth;

use Arcanum\Auth\Attribute\RequiresPolicy;

#[RequiresPolicy(AllowPolicy::class)]
final class PolicyDto
{
}
