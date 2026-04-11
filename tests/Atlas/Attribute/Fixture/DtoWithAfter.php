<?php

declare(strict_types=1);

namespace Arcanum\Test\Atlas\Attribute\Fixture;

use Arcanum\Atlas\Attribute\After;

#[After('App\\Middleware\\AuditLog')]
final class DtoWithAfter
{
}
