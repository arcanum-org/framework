<?php

declare(strict_types=1);

namespace Arcanum\Testing\Internal;

use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\StatusCode;
use Arcanum\Ignition\HyperKernel;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * HyperKernel subclass used by HttpTestSurface.
 *
 * Empty bootstrapper list — TestKernel has already populated the shared
 * container, so the production bootstrap chain (Environment, Configuration,
 * Sessions, etc.) would re-bind services and stomp the FrozenClock. Calling
 * bootstrap() still wires `$this->container` and binds Transport::Http.
 *
 * `setCoreHandler()` installs a PSR-15 handler that handleRequest() delegates
 * to. With no handler installed, the default base behavior (HttpException
 * 404 from `HyperKernel::handleRequest`) is preserved so unrouted requests
 * round-trip through the same exception rendering path production uses.
 *
 * Internal to the Testing package — not part of the public API surface.
 */
final class TestHyperKernel extends HyperKernel
{
    /** @var class-string<\Arcanum\Ignition\Bootstrapper>[] */
    protected array $bootstrappers = [];

    private RequestHandlerInterface|null $coreHandler = null;

    public function setCoreHandler(RequestHandlerInterface|null $handler): void
    {
        $this->coreHandler = $handler;
    }

    protected function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->coreHandler !== null) {
            return $this->coreHandler->handle($request);
        }

        throw new HttpException(StatusCode::NotFound);
    }
}
