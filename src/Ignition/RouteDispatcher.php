<?php

declare(strict_types=1);

namespace Arcanum\Ignition;

use Arcanum\Atlas\MiddlewareRegistry;
use Arcanum\Atlas\Route;
use Arcanum\Flow\Conveyor\Bus;
use Arcanum\Flow\Continuum\Continuum;
use Arcanum\Flow\Continuum\Progression;
use Arcanum\Flow\Pipeline\Pipeline;
use Arcanum\Hyper\HttpMiddleware;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Composes per-route middleware with the existing command bus.
 *
 * Two responsibilities:
 *   - dispatch(): wraps Bus::dispatch() in per-route before/after
 *     Progressions at the Conveyor layer.
 *   - wrapHttp(): wraps a request handler in per-route PSR-15
 *     middleware at the HTTP layer.
 */
final class RouteDispatcher
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly MiddlewareRegistry $middlewareRegistry,
        private readonly Bus $bus,
    ) {
    }

    /**
     * Dispatch a DTO through per-route middleware and the bus.
     *
     * Per-route before/after middleware wraps around the bus dispatch.
     * Global Conveyor middleware still runs inside the bus.
     */
    public function dispatch(object $dto, Route $route): object
    {
        $mw = $this->middlewareRegistry->for($route->dtoClass);

        if ($mw->before === [] && $mw->after === []) {
            return $this->bus->dispatch($dto, prefix: $route->handlerPrefix);
        }

        $pipeline = new Pipeline();

        if ($mw->before !== []) {
            $dispatchFlow = new Continuum();
            foreach ($mw->before as $class) {
                /** @var Progression $progression */
                $progression = $this->container->get($class);
                $dispatchFlow->add($progression);
            }
            $pipeline->pipe($dispatchFlow);
        }

        $pipeline->pipe(fn(object $obj): object => $this->bus->dispatch($obj, prefix: $route->handlerPrefix));

        if ($mw->after !== []) {
            $responseFlow = new Continuum();
            foreach ($mw->after as $class) {
                /** @var Progression $progression */
                $progression = $this->container->get($class);
                $responseFlow->add($progression);
            }
            $pipeline->pipe($responseFlow);
        }

        return $pipeline->send($dto);
    }

    /**
     * Wrap a core request handler with per-route HTTP middleware.
     *
     * Returns the handler unchanged if no HTTP middleware is declared.
     */
    public function wrapHttp(Route $route, RequestHandlerInterface $core): RequestHandlerInterface
    {
        $mw = $this->middlewareRegistry->for($route->dtoClass);

        if ($mw->http === []) {
            return $core;
        }

        $stack = new HttpMiddleware($core, $this->container);
        foreach ($mw->http as $class) {
            /** @var class-string<\Psr\Http\Server\MiddlewareInterface> $class */
            $stack->add($class);
        }

        return $stack;
    }
}
