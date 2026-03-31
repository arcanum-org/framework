<?php

declare(strict_types=1);

namespace Arcanum\Test\Atlas\Fixture\Middleware\Admin\Query;

use Arcanum\Atlas\Attribute\Before;

#[Before('AuditLogBeforeMiddleware')]
final class AuditLog
{
    public function __construct(
        public readonly int $page = 1,
    ) {
    }
}
