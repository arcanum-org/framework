<?php

declare(strict_types=1);

namespace Arcanum\Codex;

use Arcanum\Codex\Event\CodexEvent;
use Psr\Container\ContainerInterface;

class Resolver implements ClassResolver, Specifier
{
    /**
     * List of Codex\EventDispatcher instances.
     *
     * @var EventDispatcher[]
     */
    protected array $eventDispatchers = [];

    /**
     * List of specifications the resolver will use to resolve dependencies
     * before attempting to resolve them itself.
     *
     * @var array<class-string, array<string, mixed>>
     */
    private $specifications = [];

    /**
     * Tracks classes currently being resolved to detect circular dependencies.
     *
     * @var array<string, true>
     */
    private array $resolving = [];

    /**
     * Resolver uses a container to resolve dependencies.
     */
    private function __construct(
        private ContainerInterface $container
    ) {
    }

    /**
     * Create a new resolver.
     */
    public static function forContainer(ContainerInterface $container): self
    {
        return new self($container);
    }

    /**
     * Resolve a class
     *
     * @template T of object
     * @param class-string<T>|(callable(ContainerInterface): T) $className
     * @return T
     */
    public function resolve(string|callable $className, bool $isDependency = false): object
    {
        // To resolve a callable, we just call it with the container.
        if (is_callable($className)) {
            /** @var T */
            $instance = $className($this->container);
            $this->notify(new Event\ClassRequested(get_class($instance)));
            return $this->finalize($instance);
        }

        // notify listeners that a class was requested
        $this->notify(new Event\ClassRequested($className));

        // first try to get the class from the container
        if ($isDependency && $this->container->has($className)) {
            /** @var T */
            $instance = $this->container->get($className);
            return $this->finalize($instance);
        }

        // Circular dependency detection — only for reflection-based resolution.
        if (isset($this->resolving[$className])) {
            $chain = array_keys($this->resolving);
            $chain[] = $className;
            $this->resolving = [];
            throw new \RuntimeException(sprintf(
                'Circular dependency detected: %s',
                implode(' → ', $chain),
            ));
        }

        $this->resolving[$className] = true;

        try {
            /** @var T */
            return $this->doResolve($className);
        } finally {
            unset($this->resolving[$className]);
        }
    }

    /**
     * @param class-string $className
     */
    private function doResolve(string $className): object
    {
        $image = new \ReflectionClass($className);

        // If it is not instantiable, we cannot resolve it.
        if (!$image->isInstantiable()) {
            throw new Error\UnresolvableClass(message: $className);
        }

        $constructor = $image->getConstructor();

        // If it has no constructor, we can just instantiate it.
        if ($constructor === null) {
            return $this->finalize(new $className());
        }

        $parameters = $constructor->getParameters();

        // If it has a constructor, but no parameters, we can just instantiate it.
        if (count($parameters) === 0) {
            return $this->finalize(new $className());
        }

        // Otherwise, we need to resolve the parameters as dependencies.
        $dependencies = $this->resolveParameters($parameters, $className);

        /** @var object $instance */
        $instance = $image->newInstanceArgs($dependencies);
        return $this->finalize($instance);
    }

    /**
     * Instruct the resolver on how to resolve a particular dependency.
     *
     * @param class-string|array<class-string> $when
     * @param string $needs Either a class name or a variable name.
     * @param mixed $give
     */
    public function specify(string|array $when, string $needs, mixed $give): void
    {
        if (is_array($when)) {
            foreach ($when as $name) {
                $this->specify($name, $needs, $give);
            }
            return;
        }
        $this->specifications[$when][$needs] = $give;
    }

    /**
     * @param \ReflectionParameter[] $parameters
     * @return mixed[]
     */
    protected function resolveParameters(array $parameters, string $serviceName): array
    {
        $dependencies = [];
        foreach ($parameters as $parameter) {
            $dependency = $this->resolveParameter($parameter, $serviceName);
            if ($parameter->isVariadic()) {
                $dependencies = array_merge($dependencies, (array)$dependency);
            } else {
                $dependencies[] = $dependency;
            }
        }

        return $dependencies;
    }

    /**
     * Resolve a parameter
     */
    protected function resolveParameter(\ReflectionParameter $parameter, string $serviceName): mixed
    {

        // Get the specifications for the service, if any.
        $specifications = $this->specifications[$serviceName] ?? [];

        // Get the name of the parameter.
        $parameterName = $parameter->getName();

        // check for variable specifications
        if (array_key_exists('$' . $parameterName, $specifications)) {
            // Variable specifications are always returned as-is.
            // This is likely a primitive value, but it could be anything.
            return $specifications['$' . $parameterName];
        }

        // check for class specifications
        $dependencyName = ClassNameResolver::resolve($parameter);
        if ($dependencyName === null) {
            // if there is no class name, we'll try to resolve it as a primitive
            return PrimitiveResolver::resolve($parameter);
        }

        if (isset($specifications[$dependencyName])) {
            // If there is a specification for the dependency, we'll resolve it.
            return $this->resolveSpecification($specifications[$dependencyName]);
        }

        // If there is no specification, we'll try to resolve it as a class.
        return $this->resolveClass($parameter, $dependencyName);
    }

    /**
     * Resolve a specification.
     *
     * The specification might be a class-string, an array of
     * class-strings, or a callable that returns a fully resolved
     * value. If it's anything else, it will be returned as-is.
     */
    protected function resolveSpecification(mixed $specification): mixed
    {
        if (is_array($specification)) {
            // If the specification is an array of class strings, we'll resolve each item
            // in the array, and return an array of resolved values.
            return array_map([$this, 'resolveSpecification'], $specification);
        }

        // If the specification is a class string we'll resolve it.
        if (is_string($specification) && class_exists($specification)) {
            /** @var class-string $specification */
            return $this->resolve($specification, true);
        }

        if (is_callable($specification)) {
            // If the specification is a callable, we'll call it with the container.
            /** @var callable(ContainerInterface): object $specification */
            return $this->resolve($specification, true);
        }

        // If the specification is anything else, we'll just return it as-is.
        return $specification;
    }

    /**
     * Resolve a class with arguments.
     *
     * @template T of object
     * @param class-string<T> $className
     * @param class-string[] $arguments
     * @return T
     * @throws Error\UnresolvableClass
     */
    public function resolveWith(string $className, array $arguments): object
    {
        $image = new \ReflectionClass($className);

        // If it is not instantiable, we cannot resolve it.
        if (!$image->isInstantiable()) {
            throw new Error\UnresolvableClass(message: $className);
        }

        $constructor = $image->getConstructor();

        // If it has no constructor, we should just resolve it.
        if ($constructor === null) {
            return $this->resolve($className);
        }

        $parameters = $constructor->getParameters();

        // If it has a constructor, but no parameters, we should just resolve it.
        if (count($parameters) === 0) {
            return $this->resolve($className);
        }

        // Since we are going to resolve the dependencies, let's first
        // notify listeners that a class was requested.
        $this->notify(new Event\ClassRequested($className));

        // Now we can resolve the dependencies.
        $dependencies = [];
        foreach ($parameters as $index => $parameter) {
            if ($parameter->isVariadic()) {
                foreach (array_slice($arguments, $index) as $variadicArgument) {
                    $dependencies[] = $this->resolve($variadicArgument, isDependency: true);
                }
                break;
            }
            $argument = $arguments[$index] ?? null;
            $dependencies[] = match (true) {
                $argument !== null => $this->resolve($argument, isDependency: true),
                $parameter->isDefaultValueAvailable() => $parameter->getDefaultValue(),
                default => throw new Error\UnresolvableClass(
                    "$className requires a parameter at index $index, but none was provided."
                )
            };
        }

        /** @var T */
        $instance = $image->newInstanceArgs($dependencies);
        return $this->finalize($instance);
    }

    /**
     * @param class-string $name
     * @return object|array<object>|null
     */
    protected function resolveClass(\ReflectionParameter $parameter, string $name): object|array|null
    {
        if ($parameter->isVariadic()) {
            // If we have gotten this far, then there were no specifications provided for the
            // variadic parameter. We'll just return an empty array.
            return [];
        }

        try {
            return $this->resolve(
                className: $name,
                isDependency: true
            );
        } catch (Error\Unresolvable $e) {
            // handle the optional values on the parameter if it is not resolvable.
            if ($parameter->isDefaultValueAvailable()) {
                /** @var object|array<object>|null */
                return $parameter->getDefaultValue();
            }
            throw $e;
        }
    }

    /**
     * Finalize an instance.
     *
     * if $instance is a Codex\EventDispatcher, it will be added to the list of event dispatchers.
     *
     * @template T of object
     * @param T $instance
     * @return T
     */
    protected function finalize(object $instance): object
    {
        if ($instance instanceof EventDispatcher) {
            $this->eventDispatchers[] = $instance;
        }

        foreach ($this->eventDispatchers as $dispatcher) {
            $dispatcher->dispatch(new Event\ClassResolved($instance));
        }

        return $instance;
    }

    /**
     * Notify listeners of an event.
     */
    protected function notify(CodexEvent $event): void
    {
        foreach ($this->eventDispatchers as $dispatcher) {
            $dispatcher->dispatch($event);
        }
    }
}
