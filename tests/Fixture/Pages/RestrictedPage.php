<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Pages;

use Arcanum\Atlas\Attribute\AllowedFormats;

#[AllowedFormats('html')]
final class RestrictedPage
{
}
