<?php

declare(strict_types=1);

namespace Arcanum\Cabinet;

use Arcanum\Codex\ClassResolver;
use Arcanum\Codex\Resolver;
use Arcanum\Codex\Specifier;
use Arcanum\Toolkit\Strings;
use Arcanum\Flow\Pipeline\System;
use Arcanum\Flow\Pipeline\PipelayerSystem;
use Arcanum\Flow\Continuum\Collection;
use Arcanum\Flow\Continuum\ContinuationCollection;
use Arcanum\Flow\Continuum\Progression;

/**
 * Arcanum Application Container
 * -----------------------------
 *
 * The application container is the primary service container for the
 * Arcanum framework. It implements the Application Interface and provides
 * functionality for registering services, factories, providers, decorators,
 * prototypes, and middleware.
 *
 * Your application should create one of these in the bootstrap process.
 */
class Container implements Application, Specifier
{
    /**
     * ClassResolver used to build classes.
     */
    protected ClassResolver&Specifier $resolver;

    /**
     * @var array<string, Provider>
     */
    protected array $providers = [];

    /**
     * Tracks classes currently being resolved to detect circular dependencies.
     *
     * @var array<string, true>
     */
    private array $resolving = [];

    /**
     * @var \Arcanum\Flow\Pipeline\System
     */
    protected System $decorators;

    /**
     * @var \Arcanum\Flow\Continuum\Collection
     */
    protected Collection $middleware;

    /**
     * Container uses a resolver to instantiate services.
     */
    public function __construct(
        (ClassResolver&Specifier)|null $resolver = null,
        ?Collection $middleware = null,
        ?System $decorators = null
    ) {
        $this->resolver = $resolver ?? Resolver::forContainer($this);
        $this->middleware = $middleware ?? new ContinuationCollection();
        $this->decorators = $decorators ?? new PipelayerSystem();
    }

    /**
     * Register a service on the container.
     *
     * @param string $serviceName
     * @param class-string|null $implementation
     */
    public function service(string $serviceName, string|null $implementation = null): void
    {
        if ($implementation === null) {
            $implementation = $serviceName;
        }
        if (!class_exists($implementation)) {
            throw new ServiceNotFound(
                $serviceName,
                "Cannot register service '{$serviceName}'"
                    . " — class '{$implementation}' does not exist",
            );
        }
        $this->factory($serviceName, $this->simpleFactory($implementation));
    }

    /**
     * Register a service on the container while defining its dependencies.
     *
     * @param class-string $serviceName
     * @param class-string[] $dependencies
     */
    public function serviceWith(string $serviceName, array $dependencies): void
    {
        $this->factory($serviceName, function (Container $container) use ($serviceName, $dependencies) {
            return $container->resolver->resolveWith($serviceName, $dependencies);
        });
    }

    /**
     * Register a service factory on the container.
     *
     * @param string $serviceName
     */
    public function factory(string $serviceName, \Closure $factory): void
    {
        $this->provider($serviceName, SimpleProvider::fromFactory($factory));
    }

    /**
     * Register a service provider on the container.
     *
     * @param string $serviceName
     * @param Provider|class-string<Provider> $provider
     */
    public function provider(string $serviceName, string|Provider $provider): void
    {
        if (is_string($provider)) {
            // We need to proxy the provider through a factory so that we can
            // resolve it from the container when it is requested.
            $provider = SimpleProvider::fromFactory(function (Container $container) use ($provider) {
                $providerProvider = $container->resolver->resolve($provider);
                return $providerProvider($container);
            });
        }
        $this->providers[$serviceName] = $provider;
    }

    /**
     * Register a service instance on the container.
     *
     * @param string $serviceName
     */
    public function instance(string $serviceName, mixed $instance): void
    {
        $this->factory($serviceName, fn () => $instance);
    }

    /**
     * Register a prototype on the container.
     *
     * The container will create a new instance
     * of the service each time it is requested.
     *
     * @param class-string $serviceName
     */
    public function prototype(string $serviceName): void
    {
        $this->prototypeFactory($serviceName, $this->simpleFactory($serviceName));
    }

    /**
     * Register a prototype factory on the container.
     *
     * The container will create a new instance
     * of the service each time it is requested.
     *
     * @param string $serviceName
     */
    public function prototypeFactory(string $serviceName, \Closure $factory): void
    {
        $this->provider($serviceName, PrototypeProvider::fromFactory($factory));
    }

    /**
     * Register a decorator on the container.
     *
     * Decorators are applied to the service when it is requested from the
     * container for the first time.
     *
     * @param string $serviceName
     * @param callable(object): object $decorator
     */
    public function decorator(string $serviceName, callable $decorator): void
    {
        $this->decorators->pipe($serviceName, $decorator);
    }

    /**
     * Register a middleware on the container.
     *
     * Middleware are applied to the service every time it is requested from
     * the container.
     *
     * @param string $serviceName
     * @param Progression|class-string<Progression> $middleware
     */
    public function middleware(string $serviceName, string|Progression $middleware): void
    {
        if (is_string($middleware)) {
            $middleware = new MiddlewareProgression($middleware, $this->resolver);
        }
        $this->middleware->add($serviceName, $middleware);
    }

    /**
     * Define a specification for a service.
     *
     * This is often completely unnecessary, but can be useful for defining
     * services that have primitive dependencies, variadic dependencies, or
     * assigning different implementations of interface dependencies to different
     * services.
     *
     * @param class-string|array<class-string> $when
     */
    public function specify(string|array $when, string $needs, mixed $give): void
    {
        $this->resolver->specify($when, $needs, $give);
    }

    /**
     * @param class-string $serviceName
     */
    protected function simpleFactory(string $serviceName): \Closure
    {
        return fn(Container $container) => $container->resolver->resolve($serviceName);
    }

    /**
     * ArrayAccess methods
     */

    /**
     * offsetSet
     *
     * @param mixed $offset
     */
    public function offsetSet($offset, $value): void
    {
        if (!is_string($offset)) {
            throw new InvalidKey("Invalid key type: " . gettype($offset));
        }
        $this->providers[$offset] = SimpleProvider::fromFactory(fn() => $value);
    }

    /**
     * offsetExists
     *
     * @param string $offset
     */
    public function offsetExists($offset): bool
    {
        return isset($this->providers[$offset]);
    }

    /**
     * offsetUnset
     *
     * @param string $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->providers[$offset]);
    }

    /**
     * offsetGet
     *
     * This is where all the magic happens.
     *
     * @param mixed $offset
     * @throws InvalidKey
     */
    public function offsetGet($offset): mixed
    {
        if (!is_string($offset)) {
            throw new InvalidKey("Invalid key type: " . gettype($offset));
        }

        // Circular dependency detection.
        if (isset($this->resolving[$offset])) {
            $chain = array_keys($this->resolving);
            $chain[] = $offset;
            $this->resolving = [];
            throw new CircularDependency($chain);
        }

        $this->resolving[$offset] = true;

        try {
            // Provide the instance.
            $provider = $this->providers[$offset] ?? null;

            if ($provider === null) {
                if (!class_exists($offset)) {
                    $exception = new ServiceNotFound(
                        $offset,
                        "Service '{$offset}' is not registered and"
                            . " does not match an existing class",
                    );

                    $registered = array_keys($this->providers);
                    $closest = Strings::closestMatch($offset, $registered);

                    throw $exception->withSuggestion(
                        $closest !== null
                            ? "Did you mean '{$closest}'?"
                            : "Register it with \$container->service('{$offset}')"
                                . ' or check the class name for typos',
                    );
                }
                $provider = PrototypeProvider::fromFactory($this->simpleFactory($offset));
            }

            $instance = $provider($this);

            // Apply decorators.
            $instance = $this->applyDecorators($offset, $instance, $provider);

            // Apply middleware.
            return $this->middleware->send($offset, $instance);
        } finally {
            unset($this->resolving[$offset]);
        }
    }

    /**
     * Apply decorators to the instance.
     *
     * @param string $serviceName
     */
    protected function applyDecorators(string $serviceName, object $instance, Provider $provider): object
    {
        $instance = $this->decorators->send($serviceName, $instance);
        if (!$provider instanceof PrototypeProvider) {
            // Remove the decorators if the provider is not a prototype.
            $this->decorators->purge($serviceName);
            // Cache the instance if the provider is not a prototype.
            $this->providers[$serviceName] = SimpleProvider::fromFactory(fn() => $instance);
        }
        return $instance;
    }

    /**
     * ContainerInterface methods
     */

    /**
     * Get a service from the container.
     *
     * @param string $id
     */
    public function get(string $id): mixed
    {
        return $this->offsetGet($id);
    }

    /**
     * Check if a service exists in the container.
     *
     * @param string $id
     */
    public function has(string $id): bool
    {
        return $this->offsetExists($id);
    }
}
