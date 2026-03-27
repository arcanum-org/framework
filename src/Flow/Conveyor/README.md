# Arcanum Flow: Conveyor

Conveyor is a command bus. It combines Pipeline and Continuum to dispatch objects to handlers with before/after middleware. This is the foundation for the framework's CQRS pattern — commands and queries flow through the Conveyor to reach their handlers.

## How it works

`MiddlewareBus` is the core class. You dispatch an object, and it:

1. Runs **before** middleware (a Continuum)
2. Finds and calls the **handler** (by naming convention)
3. Runs **after** middleware (a Continuum)

```php
$bus = new MiddlewareBus($container);

// Middleware that runs before the handler
$bus->before(new ValidateOrderMiddleware());

// Middleware that runs after the handler
$bus->after(new LogResponseMiddleware());

// Dispatch — finds PlaceOrderHandler automatically
$result = $bus->dispatch(new PlaceOrder(item: 'burger', qty: 2));
```

## Handler resolution by convention

When you dispatch a `PlaceOrder` object, the bus looks for `PlaceOrderHandler` in the container. The convention is simple: append "Handler" to the class name.

```php
// Dispatching this:
$bus->dispatch(new PlaceOrder(...));

// Resolves to this handler:
class PlaceOrderHandler {
    public function __invoke(PlaceOrder $command): PlaceOrderResult {
        // do the work
    }
}
```

The handler is resolved from the PSR-11 container, so all its dependencies are injected automatically.

## The internal pipeline

Under the hood, `dispatch()` builds a Pipeline with three stages:

```php
(new Pipeline())
    ->pipe($this->dispatchFlow)     // 1. before middleware (Continuum)
    ->pipe(function ($object) {
        return $handler($object);   // 2. handler
    })
    ->pipe($this->responseFlow)     // 3. after middleware (Continuum)
    ->send($object);
```

If the handler returns null, it's wrapped in an `EmptyDTO` so the pipeline doesn't break.

## DTO validation middleware

Conveyor ships with middleware filters that validate the structure of objects passing through the bus. These are useful for enforcing that command and query objects are well-formed DTOs:

```php
$bus->before(
    new FinalFilter(),              // class must be declared final
    new PublicPropertyFilter(),     // no private/protected properties
    new ReadOnlyPropertyFilter(),   // all properties must be readonly
    new NonStaticPropertyFilter(),  // no static properties
    new NonPublicMethodFilter(),    // no public methods (except constructor)
);
```

Each filter is a `Progression` (Continuum middleware). If validation fails, it throws `InvalidDTO` with a descriptive message. If validation passes, it calls `$next()` to continue the chain.

These filters enforce a clean DTO pattern: final classes with only public readonly properties and no methods beyond the constructor. This keeps command/query objects as pure data carriers.

## EmptyDTO

When a handler returns null (void handlers), the bus wraps the result in `EmptyDTO` — a final class with no properties. This prevents the Pipeline from throwing `Interrupted` on a null return.

## The interfaces

- **Bus** — the dispatch contract: `dispatch(object $object): object`
- **Progression** — middleware stage (from Continuum): `__invoke(object $payload, callable $next): void`
- **InvalidDTO** — thrown when a DTO validation filter fails

## At a glance

```
dispatch(PlaceOrder)
    → before middleware (Continuum)
    → PlaceOrderHandler (resolved from container)
    → after middleware (Continuum)
    → result

MiddlewareBus (implements Bus)
|-- dispatchFlow (Continuation — before middleware)
|-- responseFlow (Continuation — after middleware)
\-- Handler lookup (convention: ClassName + "Handler")

Middleware Filters:
|-- FinalFilter
|-- PublicPropertyFilter
|-- ReadOnlyPropertyFilter
|-- NonStaticPropertyFilter
\-- NonPublicMethodFilter
```
