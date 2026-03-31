<?php

declare(strict_types=1);

namespace Arcanum\Test\Atlas\Fixture\Pages;

final class IndexHandler
{
    /** @return array<string, string> */
    public function __invoke(Index $page): array
    {
        return ['page' => 'index'];
    }
}
