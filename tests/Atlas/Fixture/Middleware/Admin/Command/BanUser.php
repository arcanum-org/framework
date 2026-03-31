<?php

declare(strict_types=1);

namespace Arcanum\Test\Atlas\Fixture\Middleware\Admin\Command;

use Arcanum\Atlas\Attribute\HttpMiddleware;
use Arcanum\Atlas\Attribute\Before;

#[HttpMiddleware('BanUserHttpMiddleware')]
#[Before('BanUserBeforeMiddleware')]
final class BanUser
{
    public function __construct(
        public readonly string $userId = '',
    ) {
    }
}
