<?php

declare(strict_types=1);

namespace Arcanum\Glitch;

use Arcanum\Hyper\StatusCode;

class HttpException extends \RuntimeException
{
    public function __construct(
        private StatusCode $statusCode,
        string $message = '',
        \Throwable|null $previous = null,
    ) {
        parent::__construct(
            $message !== '' ? $message : $statusCode->reason()->value,
            $statusCode->value,
            $previous,
        );
    }

    public function getStatusCode(): StatusCode
    {
        return $this->statusCode;
    }
}
