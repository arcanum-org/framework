<?php

declare(strict_types=1);

// Root-level directory middleware — applies to all handlers beneath.

return [
    'http' => ['RootHttpMiddleware'],
];
