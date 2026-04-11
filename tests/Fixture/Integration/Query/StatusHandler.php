<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Integration\Query;

final class StatusHandler
{
    /** @return array<string, mixed> */
    public function __invoke(Status $query): array
    {
        $data = ['status' => 'ok'];

        if ($query->verbose) {
            $data['version'] = '1.0.0';
            $data['uptime'] = 42;
        }

        return $data;
    }
}
