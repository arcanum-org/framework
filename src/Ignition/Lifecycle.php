<?php

declare(strict_types=1);

namespace Arcanum\Ignition;

use Arcanum\Cabinet\Application;
use Arcanum\Glitch\ExceptionHandler;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Shared lifecycle infrastructure for both HTTP and CLI kernels.
 *
 * Provides container-aware event dispatching and exception reporting
 * so that both HyperKernel and RuneKernel can fire lifecycle events
 * without duplicating the container-lookup ceremony.
 */
class Lifecycle
{
    public function __construct(
        private readonly Application $container,
    ) {
    }

    /**
     * Dispatch an event through the container's EventDispatcher.
     *
     * Returns the (possibly mutated) event. If no EventDispatcher is
     * registered, returns the event unchanged — lifecycle events are
     * optional, not load-bearing.
     */
    public function dispatch(object $event): object
    {
        if ($this->container->has(EventDispatcherInterface::class)) {
            /** @var EventDispatcherInterface $dispatcher */
            $dispatcher = $this->container->get(EventDispatcherInterface::class);
            return $dispatcher->dispatch($event);
        }

        return $event;
    }

    /**
     * Report an exception without rendering a response.
     *
     * Used for non-fatal errors (e.g., listener failures) where the
     * request or command should still complete successfully.
     */
    public function report(\Throwable $e): void
    {
        if ($this->container->has(ExceptionHandler::class)) {
            /** @var ExceptionHandler $handler */
            $handler = $this->container->get(ExceptionHandler::class);
            $handler->handleException($e);
        }
    }
}
