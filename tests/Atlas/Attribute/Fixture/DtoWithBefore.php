<?php

declare(strict_types=1);

namespace Arcanum\Test\Atlas\Attribute\Fixture;

use Arcanum\Atlas\Attribute\Before;

#[Before('App\\Middleware\\ValidateInput')]
final class DtoWithBefore
{
}
