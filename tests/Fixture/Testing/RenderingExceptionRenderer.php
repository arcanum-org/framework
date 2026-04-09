<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Testing;

use Arcanum\Flow\River\EmptyStream;
use Arcanum\Glitch\ExceptionRenderer;
use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\Headers;
use Arcanum\Hyper\Message;
use Arcanum\Hyper\Response;
use Arcanum\Hyper\StatusCode;
use Arcanum\Hyper\Version;
use Psr\Http\Message\ResponseInterface;

final class RenderingExceptionRenderer implements ExceptionRenderer
{
    public function render(\Throwable $e): ResponseInterface
    {
        $status = $e instanceof HttpException ? $e->getStatusCode() : StatusCode::InternalServerError;

        return new Response(
            new Message(new Headers(), new EmptyStream(), Version::from('1.1')),
            $status,
        );
    }
}
