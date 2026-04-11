<?php

declare(strict_types=1);

namespace Arcanum\Atlas;

use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\StatusCode;

class MethodNotAllowed extends HttpException
{
    /**
     * @param list<string> $allowedMethods The HTTP methods allowed for this route.
     */
    public function __construct(
        private readonly array $allowedMethods,
        string $message = '',
    ) {
        parent::__construct(
            StatusCode::MethodNotAllowed,
            $message !== '' ? $message : sprintf(
                'Method not allowed. Allowed methods: %s.',
                implode(', ', $this->allowedMethods),
            ),
        );
    }

    /**
     * @return list<string>
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
