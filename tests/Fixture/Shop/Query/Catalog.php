<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Shop\Query;

use Arcanum\Atlas\Attribute\AllowedFormats;

#[AllowedFormats('json', 'html', 'csv')]
final class Catalog
{
}
