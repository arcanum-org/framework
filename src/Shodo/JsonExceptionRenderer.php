<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

use Arcanum\Glitch\ExceptionRenderer;
use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\StatusCode;
use Psr\Http\Message\ResponseInterface;

class JsonExceptionRenderer implements ExceptionRenderer
{
    public function __construct(
        private JsonRenderer $renderer,
        private bool $debug = false,
    ) {
    }

    public function render(\Throwable $e): ResponseInterface
    {
        $status = $e instanceof HttpException
            ? $e->getStatusCode()
            : StatusCode::InternalServerError;

        $payload = [
            'error' => [
                'status' => $status->value,
                'message' => $e->getMessage(),
            ],
        ];

        if ($this->debug) {
            $payload['error']['exception'] = get_class($e);
            $payload['error']['file'] = $e->getFile();
            $payload['error']['line'] = $e->getLine();
            $payload['error']['trace'] = $e->getTrace();
        }

        return $this->renderer->render($payload, status: $status);
    }
}
