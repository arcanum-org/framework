<?php

declare(strict_types=1);

namespace Arcanum\Flow\Conveyor;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Arcanum\Flow\Continuum\Continuum;
use Arcanum\Flow\Continuum\Continuation;
use Arcanum\Flow\Continuum\Progression;
use Arcanum\Flow\Pipeline\Pipeline;

class MiddlewareBus implements Bus
{
    /**
     * @param bool $debug When true, log a warning when a prefixed handler
     *                    is not found and the bus falls back to the unprefixed handler.
     */
    public function __construct(
        protected ContainerInterface $container,
        protected Continuation $dispatchFlow = new Continuum(),
        protected Continuation $responseFlow = new Continuum(),
        protected bool $debug = false,
        protected LoggerInterface|null $logger = null,
    ) {
    }

    /**
     * Add dispatch middleware to the bus.
     */
    public function before(Progression ...$middleware): void
    {
        foreach ($middleware as $layer) {
            $this->dispatchFlow = $this->dispatchFlow->add($layer);
        }
    }

    /**
     * Add response middleware to the bus.
     */
    public function after(Progression ...$middleware): void
    {
        foreach ($middleware as $layer) {
            $this->responseFlow = $this->responseFlow->add($layer);
        }
    }

    /**
     * Dispatch an object to a handler.
     */
    public function dispatch(object $object, string $prefix = ''): object
    {
        return (new Pipeline())
            ->pipe($this->dispatchFlow)
            ->pipe(function (object $object) use ($prefix) {
                $result = $this->handlerFor($object, $prefix)($object);
                if ($result === null) {
                    return new EmptyDTO();
                }
                if (!is_object($result)) {
                    return new QueryResult($result);
                }
                return $result;
            })
            ->pipe($this->responseFlow)
            ->send($object);
    }

    /**
     * Get the handler for an object.
     */
    protected function handlerFor(object $object, string $prefix = ''): callable
    {
        if ($prefix !== '') {
            $prefixedName = $this->handlerNameFor($object, $prefix);
            if ($this->container->has($prefixedName)) {
                /** @var callable */
                return $this->container->get($prefixedName);
            }

            if ($this->debug && $this->logger !== null) {
                $this->logger->warning(sprintf(
                    'Handler "%s" not found, falling back to "%s".',
                    $prefixedName,
                    $this->handlerNameFor($object),
                ));
            }
        }

        /** @var callable */
        return $this->container->get($this->handlerNameFor($object));
    }

    /**
     * Get the handler name for an object.
     *
     * When a prefix is provided, it is prepended to the short class name:
     * e.g., prefix 'Delete' + Namespace\DoSomething → Namespace\DeleteDoSomethingHandler
     *
     * @return class-string
     */
    protected function handlerNameFor(object $object, string $prefix = ''): string
    {
        $className = $object instanceof HandlerProxy
            ? $object->handlerBaseName()
            : get_class($object);

        if ($prefix === '') {
            /** @var class-string */
            return $className . 'Handler';
        }

        $lastBackslash = strrpos($className, '\\');
        if ($lastBackslash === false) {
            /** @var class-string */
            return $prefix . $className . 'Handler';
        }

        $namespace = substr($className, 0, $lastBackslash + 1);
        $shortName = substr($className, $lastBackslash + 1);

        /** @var class-string */
        return $namespace . $prefix . $shortName . 'Handler';
    }
}
