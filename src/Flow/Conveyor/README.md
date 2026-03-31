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

## Dynamic DTOs

Not every route needs a dedicated DTO class. When only a handler exists (no paired DTO), the framework creates a dynamic DTO that wraps the request data and routes to the correct handler via `HandlerProxy`.

All three dynamic DTO types extend `DynamicDTO`, which provides typed data access via Gather's `Coercible` interface:

### Command

For handler-only command routes. Wraps the request body:

```php
class MakePaymentHandler {
    public function __invoke(Command $command): void {
        $amount = $command->asFloat('amount');
        $currency = $command->asString('currency');
    }
}
```

### Query

For handler-only query routes. Wraps query parameters:

```php
class ProductsHandler {
    public function __invoke(Query $query): array {
        $page = $query->asInt('page', 1);
        $category = $query->asString('category');
    }
}
```

### Page

For template-driven pages (no custom handler). All pages route to the framework-provided `PageHandler`, which simply returns the wrapped data for the template:

```php
// No handler needed — just a template at app/Pages/About.html
// The Page DTO wraps query params or dedicated DTO data
$dto = new Page('App\\Pages\\About', ['title' => 'About Us']);
$result = $bus->dispatch($dto); // → PageHandler → ['title' => 'About Us']
```

### DynamicDTO base class

The shared foundation. Wraps a `Registry` internally and delegates all typed accessors:

```php
$dto->get('key');              // mixed — raw value
$dto->has('key');              // bool
$dto->toArray();               // array<string, mixed>
$dto->asString('key');         // string (with fallback)
$dto->asInt('key', 0);        // int (with fallback)
$dto->asFloat('key', 0.0);    // float
$dto->asBool('key', false);   // bool
$dto->asAlpha('key');          // alphabetic characters only
$dto->asAlnum('key');          // alphanumeric characters only
$dto->asDigits('key');         // digits only
```

### HandlerProxy

Dynamic DTOs implement `HandlerProxy` to override handler resolution. Instead of deriving the handler from `get_class($dto)` (which would yield `CommandHandler`), the proxy provides the virtual DTO class name:

```php
// Command wrapping a MakePayment route:
$command = new Command('App\\Store\\Command\\MakePayment', $data);
$command->handlerBaseName(); // → 'App\Store\Command\MakePayment'
// Resolves to: MakePaymentHandler

// Page — always routes to PageHandler:
$page = new Page('App\\Pages\\About', $data);
$page->handlerBaseName(); // → 'Arcanum\Flow\Conveyor\Page'
// Resolves to: PageHandler
```

## EmptyDTO

When a handler returns null (void handlers), the bus wraps the result in `EmptyDTO` — a final class with no properties. This prevents the Pipeline from throwing `Interrupted` on a null return.

## QueryResult

When a handler returns a non-object value (array, scalar), the bus wraps it in `QueryResult` so it can flow through the pipeline. The kernel unwraps it before rendering.

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

Dynamic DTOs (all extend DynamicDTO):
|-- Command — handler-only commands, wraps request body
|-- Query   — handler-only queries, wraps query params
\-- Page    — template-driven pages, routes to PageHandler

Special DTOs:
|-- EmptyDTO    — void handler result sentinel
\-- QueryResult — non-object handler result wrapper

Middleware Filters:
|-- FinalFilter
|-- PublicPropertyFilter
|-- ReadOnlyPropertyFilter
|-- NonStaticPropertyFilter
\-- NonPublicMethodFilter
```
