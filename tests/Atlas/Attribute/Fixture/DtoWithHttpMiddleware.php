<?php

declare(strict_types=1);

namespace Arcanum\Test\Atlas\Attribute\Fixture;

use Arcanum\Atlas\Attribute\HttpMiddleware;

#[HttpMiddleware('App\\Http\\Middleware\\Auth')]
#[HttpMiddleware('App\\Http\\Middleware\\RateLimit')]
final class DtoWithHttpMiddleware
{
}
