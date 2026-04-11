<?php

declare(strict_types=1);

// Admin directory middleware — applies to all handlers in Admin/.

return [
    'http' => ['AdminHttpMiddleware'],
    'before' => ['AdminBeforeMiddleware'],
];
