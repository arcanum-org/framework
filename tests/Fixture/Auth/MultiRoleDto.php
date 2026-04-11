<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Auth;

use Arcanum\Auth\Attribute\RequiresRole;

#[RequiresRole('admin', 'moderator')]
final class MultiRoleDto
{
}
