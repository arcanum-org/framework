<?php

declare(strict_types=1);

namespace Arcanum\Test\Atlas\Attribute\Fixture;

use Arcanum\Atlas\Attribute\AllowedFormats;

#[AllowedFormats('json', 'html')]
final class DtoWithAllowedFormats
{
}
