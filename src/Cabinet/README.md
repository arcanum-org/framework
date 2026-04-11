# Arcanum Cabinet
The Cabinet package provides a PSR-11 dependency injection container, along with the Application interface which you can use to register your application's services in several convienient ways.

## Simple Example
The simplest way to get started is with `Container::service()`. This method let's the container know about your service. The container will then figure out how to create your service, and any dependencies it may have, once you ask for it.

```php
// Create a container.
$container = new Container();

// Register a service.
$container->service(\App\YourService::class);

// Get your service. The container will create it for you.
$service = $container->get(\App\YourService::class);
```

A lot happened behind the scenes there. When you called `Container::service()` the container generated a factory for your service and wrapped it in a simple service provider. When you asked for your service, the container looked up the provider and invoked the factory to create your service using the powerful [`Arcanum\Codex`](https://github.com/arcanum-org/framework/tree/main/src/Codex) class resolver. The container then cached your service so that it could be reused later.

## Registering Factories
If you don't want Arcanum to generate a factory for you, you can register your own factory instead.

```php
// Register a service factory.
$container->factory(\App\YourService::class, fn()=> new \App\YourService("custom"));
```

With this approach, you are responsible for creating your service and any dependencies it may have. The container will give your factory to a simple service provider which will invoke the factory when you ask for your service.

## Registering Service Providers
If you need fine-grained control over how your service is created, you can register a custom service provider instead.

```php
// Define a service provider.
class CustomServiceProvider extends \Arcanum\Cabinet\Provider
{
    private \App\YourService|null $service;
    private string $custom;

    public function __construct(\Arcanum\Gather\Environment $environment)
    {
        if ($environment->asString("ENVIRONMENT") === "production") {
            $this->custom = "production";
        } else {
            $this->custom = "default";
        }
    }

    public function __invoke(Container $container): object;
    {
        if ($this->service === null) {
            $this->service = new \App\YourService($this->custom);
        }
        return $this->service;
    }
}

// Let the container know about your service provider so it can build it,
// including the `\Arcanum\Gather\Environment` dependency automatically.
$container->service(CustomServiceProvider::class);

/**
 * Register the provider for \App\YourService
 */
$container->provider(\App\YourService::class, CustomServiceProvider::class);
```

The `\Arcanum\Gather\Environment` dependency is just an example. You can inject any dependencies you need into your service provider, and if you let the container build your service provider, as we did above, it will automatically inject them.

## Registering Prototype Services
By default, the container will cache your service after it is created so that it can be reused later. If you want to create a new instance of your service every time it is requested, you can register it as a prototype service.

```php
// Register a prototype service.
$container->prototype(\App\YourService::class);

// Get your service. The container will create a new instance for you.
$serviceA = $container->get(\App\YourService::class);

// $serviceA and $serviceB are not the same instance.
$serviceB = $container->get(\App\YourService::class);
```

You can also register a prototype factory. This factory will be invoked every time your service is requested.

```php
// Register a prototype factory.
$container->prototypeFactory(\App\YourService::class, fn()=> new \App\YourService());

// Get your service. The container will invoke the factory for you.
$serviceA = $container->get(\App\YourService::class);

// $serviceA and $serviceB are not the same instance. The factory was invoked again.
$serviceB = $container->get(\App\YourService::class);
```

### Cached vs fresh instances

All registration methods except `prototype()` and `prototypeFactory()` **cache after the first call**. This includes `factory()` — the closure runs once, and the result is reused for every subsequent `get()`. This is intentional: most services are singletons.

| Method | Creates via | New instance each call? |
|---|---|---|
| `service()` | Auto-wired by Codex | No — cached after first creation |
| `factory()` | Your closure | No — closure runs once, result cached |
| `prototype()` | Auto-wired by Codex | **Yes** — new instance every `get()` |
| `prototypeFactory()` | Your closure | **Yes** — closure runs every `get()` |
| `instance()` | You provide it | N/A — already created |

If you expect `factory()` to run the closure on every call, use `prototypeFactory()` instead.

## Directly Registering Service Instances
Sometimes you already have an instance of your service and you just want to register it with the container. You can do that with `Container::instance()`.

```php
// Register an instance of your service.
$container->instance(\App\YourService::class, new \App\YourService());
```

## Decorating Services
You can decorate a service by registering a decorator for it. A Decorator is a function that accepts your service as its only argument and must return an object that will be used as your requested service. Decorators are invoked only when the service is instantiated for the first time.

```php
// Register a decorator for \App\YourService
$container->decorator(\App\YourService::class, function (object $service): object {
    // costomize your service however you like.
    return new \App\ProxyService($service);
});
```

Decorators operate in a pipeline, passing the result of one decorator function to the next. If you are not careful, you can end up with a decorator that returns an object that is not compatible with the service it is decorating. Arcanum uses the [PHPStan](https://phpstan.org/) static analysis tool on maximum strictness to avoid problems like these. We include it by default in your application's composer.json file, and we encourage you to use it as well.

## Registering Middleware
You can register middleware to be invoked every time a service is retrieved from the container. The Middleware implementation is delegated to the awesome [`Arcanum\Flow\Continuum`](https://github.com/arcanum-org/framework/tree/main/src/Flow/Continuum) package, so your middleware classes need to implement the `Arcanum\Flow\Continuum\Progression` interface.

Middleware is invoked in the order it is registered.

```php
// Register middleware.
$container->middleware(\App\YourService::class, \App\YourMiddleware::class);

// Or, optionally pass in an instance of your middleware.
$container->middleware(\App\YourService::class, new \App\YourMiddleware());
```

# Container API

```php
decorator(string $serviceName, callable $decorator): void
```

Register a decorator for a service.

| Argument | Type | Description
| --- | --- | ---
| `$serviceName` | `string` | The name of the service to decorate.
| `$decorator` | `callable(object): object` | A function that accepts the service as its only argument and returns an object that will be used as the service.
| **return** | `void` |

---

```php
instance(string $serviceName, mixed $instance): void
```

Register an instance of a service with the container.

| Argument | Type | Description
| --- | --- | ---
| `$serviceName` | `string` | The name of the service this instance is.
| `$instance` | `mixed` | An instance of the service.
| **return** | `void` |

---


```php
factory(string $serviceName, \Closure $factory): void
```

Register a service factory with the container.

| Argument | Type | Description
| --- | --- | ---
| `$serviceName` | `string` | The name of the service this factory creates.
| `$factory` | `\Closure` | A factory function that returns an instance of the service.
| **return** | `void` |

---


```php
get(string $id): mixed
```

Get a service from the container.

| Argument | Type | Description
| --- | --- | ---
| `$id` | `string` | The name of the service to get.
| **return** | `mixed` | The service instance.

---


```php
has(string $id): bool
```

Check if the container has a service.

| Argument | Type | Description
| --- | --- | ---
| `$id` | `string` | The name of the service to check for.
| **return** | `bool` | `true` if the container has the service, `false` otherwise.

---


```php
middleware(string $serviceName, string|Progression $middleware): void
```

Register middleware for a service.

| Argument | Type | Description
| --- | --- | ---
| `$serviceName` | `string` | The name of the service to apply middleware to.
| `$middleware` | `string|Progression` | The name of the middleware class or an instance of the middleware class.
| **return** | `void` |


---


```php
offsetExists($offset): bool
```

Check if the container has a service. This method is part of the `ArrayAccess` interface. You typically won't need to use it directly. Instead, use `Container::has()` or check for the service using array syntax (e.g. `isset($container['service'])`).

| Argument | Type | Description
| --- | --- | ---
| `$offset` | `string` | The name of the service to check for.
| **return** | `bool` | `true` if the container has the service, `false` otherwise.

---


```php
offsetGet($offset): mixed
```

Get a service from the container. This method is part of the `ArrayAccess` interface. You typically won't need to use it directly. Instead, use `Container::get()` or get the service using array syntax (e.g. `$container['service']`). Incidentally, this is the method that contains most of the logic for getting and resolving a service from the container.

| Argument | Type | Description
| --- | --- | ---
| `$offset` | `string` | The name of the service to get.
| **return** | `mixed` | The service instance.

---


```php
offsetSet($offset, $value): void
```

Set a service in the container. This method is part of the `ArrayAccess` interface. You typically won't need to use it directly. Instead, use `Container::instance()`, or set the service using array syntax (e.g. `$container['service'] = $instance`).

| Argument | Type | Description
| --- | --- | ---
| `$offset` | `string` | The name of the service to set.
| `$value` | `mixed` | The service instance.
| **return** | `void` |

---


```php
offsetUnset($offset): void
```

Unset a service in the container. This method is part of the `ArrayAccess` interface. You typically won't need to use it directly. Instead, unset the service using array syntax (e.g. `unset($container['service'])`).

| Argument | Type | Description
| --- | --- | ---
| `$offset` | `string` | The name of the service to unset.
| **return** | `void` |


---


```php
prototype(string $serviceName): void
```

Register a prototype service with the container. A new instance of the service will be created every time it is requested.

| Argument | Type | Description
| --- | --- | ---
| `$serviceName` | `string` | The name of the service to register.
| **return** | `void` |

---


```php
prototypeFactory(string $serviceName, \Closure $factory): void
```

Register a prototype factory with the container. The factory will be created every time the service is requested.

| Argument | Type | Description
| --- | --- | ---
| `$serviceName` | `string` | The name of the service this factory creates.
| `$factory` | `\Closure` | A factory function that returns an instance of the service.
| **return** | `void` |

---


```php
provider(string $serviceName, string|Provider $provider): void
```

Register a service provider with the container.

| Argument | Type | Description
| --- | --- | ---
| `$serviceName` | `string` | The name of the service this provider provides.
| `$provider` | `string` &#124; `Arcanum\Cabinet\Provider` | The name of the class that extends `Provider`, or an instance of the service provider.
| **return** | `void` |

---


```php
service(string $serviceName, string|null $implementation = null): void
```

Register a service with the container. If no implementation is provided, the service name will be used as the implementation.

| Argument | Type | Description
| --- | --- | ---
| `$serviceName` | `string` | The name of the service to register.
| `$implementation` | `string` &#124; `null` | The name of the class that implements the service. If not provided, the service name will be used.
| **return** | `void` |

---


```php
serviceWith(string $serviceName, array $dependencies): void
```

Register a service with the container and specify its dependencies.

| Argument | Type | Description
| --- | --- | ---
| `$serviceName` | `string` | The name of the service to register.
| `$dependencies` | `array` | An array of class strings that the service depends on.
| **return** | `void` |

---


```php
specify(string|array $when, string $needs, mixed $give): void;
```

Register a specification for one or more services. This is useful for specifying otherwise unresolvable dependencies, like primitives without default values, or interfaces where there are several different implementations and you want different services to use different implementations.

| Argument | Type | Description
| --- | --- | ---
| `$when` | `string` &#124; `array` | The name of the service or an array of service names to specify.
| `$needs` | `string` | Either the name of the dependency or a variable parameter name (e.g. `$foo`) to specify.
| `$give` | `mixed` | The value to give the dependency. This can be a class name, a primitive value, a closure that returns a value, or, in the case of variadics, an array of values.
| **return** | `void` |

Specify Examples:

```php
// Specify a primitive value for a dependency.
$container->specify(
    when: Person::class,
    needs: '$name',
    give: 'John Doe'
);

// Specify a class name for a dependency.
$container->specify(
    when: Location::class,
    needs: AddressInterface::class,
    give: ConcreteAddress::class
);

// Specify a closure for a dependency.
$container->specify(
    when: Location::class,
    needs: AddressInterface::class,
    give: fn() => new ConcreteAddress('123 Main St.')
);

// Specify a variadic dependency.
$container->specify(
    when: Shop::class,
    needs: Report::class,
    give: [
        SalesReport::class,
        InventoryReport::class,
        EmployeeReport::class
    ]
);

// Specify a dependency for multiple services.
$container->specify(
    when: [ Person::class, Location::class ],
    needs: AddressInterface::class,
    give: ConcreteAddress::class
);
```
