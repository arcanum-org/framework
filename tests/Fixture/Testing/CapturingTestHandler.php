<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Testing;

use Arcanum\Flow\River\EmptyStream;
use Arcanum\Hyper\Headers;
use Arcanum\Hyper\Message;
use Arcanum\Hyper\Response;
use Arcanum\Hyper\StatusCode;
use Arcanum\Hyper\Version;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CapturingTestHandler implements RequestHandlerInterface
{
    public ServerRequestInterface|null $captured = null;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->captured = $request;

        return new Response(
            new Message(
                new Headers(['Content-Type' => 'text/plain']),
                new EmptyStream(),
                Version::from('1.1'),
            ),
            StatusCode::OK,
        );
    }
}
