<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Auth;

use Arcanum\Auth\Attribute\RequiresAuth;

#[RequiresAuth]
final class AuthenticatedDto
{
}
