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

## Global vs per-route middleware

The `before()` and `after()` methods on `MiddlewareBus` register **global** Conveyor middleware — it runs for every dispatch. For middleware that only applies to specific handlers, use per-route middleware via PHP attributes on your DTO classes:

```php
use Arcanum\Atlas\Attribute\Before;
use Arcanum\Atlas\Attribute\After;

#[Before(ValidateOrderInput::class)]
#[After(OrderAuditLog::class)]
final class PlaceOrder
{
    public function __construct(
        public readonly string $item,
        public readonly int $qty,
    ) {}
}
```

Per-route middleware wraps the bus dispatch from the outside. The execution order is:

```
Per-route Before → Global Before → Handler → Global After → Per-route After
```

Per-route middleware is resolved by `RouteDispatcher`, which composes with the existing `MiddlewareBus`. See the [Atlas README](../Atlas/README.md#route-middleware) for the full execution order diagram, including HTTP-layer middleware, directory-scoped `Middleware.php` files, and the ordering rules.

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

### Framework middleware

Framework-provided middleware are registered automatically by the bootstrappers:

- **`AuthorizationGuard`** — checks `#[RequiresAuth]`, `#[RequiresRole]`, and `#[RequiresPolicy]` attributes on DTOs. Throws 401/403 on HTTP, `RuntimeException` on CLI. See the [Auth README](../../Auth/README.md).
- **`TransportGuard`** — enforces `#[CliOnly]` and `#[HttpOnly]` attributes. Rejects cross-transport dispatch (e.g., HTTP request to a CLI-only DTO → 405).
- **`ValidationGuard`** — runs validation rules declared as attributes on DTO constructor parameters. Throws `ValidationException` on failure (rendered as 422 on HTTP, field-level errors on CLI). See the [Validation README](../../Validation/README.md).
- **`DomainContextMiddleware`** — sets the domain context from the DTO's namespace for database scoping. Only registered when a database is configured. See the [Forge README](../../Forge/README.md).

All are `Progression` middleware registered via `$bus->before()`.

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

## Command response conventions

In CQRS, commands represent intent — "place this order", "ban this user", "queue this email". The handler executes the intent, but the **return type** signals what happened. Arcanum reads the handler's PHP return type declaration to determine the HTTP response:

| Return type | Returns | HTTP status | Meaning |
|---|---|---|---|
| `: void` | (nothing) | **204 No Content** | Done. Nothing to report. |
| `: ?Foo` | `null` | **202 Accepted** | Accepted for processing (async, queued, deferred). |
| `: ?Foo` | `Foo` | **201 Created** | Created a resource. |
| `: Foo` | `Foo` | **201 Created** | Created a resource. |
| `: int` | `42` | **201 Created** | Created — scalar identifier. |

The return type is the signal. You don't need to remember status codes or use special annotations — just write the return type you mean.

### Fire-and-forget (204)

The command does its work synchronously and has nothing to report. Most commands fall here:

```php
class BanUserHandler
{
    public function __invoke(BanUser $command): void
    {
        $this->users->ban($command->userId);
        // done — nothing to return
    }
}
```

### Accepted for processing (202)

The command was validated and accepted, but the actual work happens later — a queue, a background job, an external system. Declare a nullable return type and return `null`:

```php
class SendWelcomeEmailHandler
{
    public function __invoke(SendWelcomeEmail $command): ?EmailReceipt
    {
        $this->queue->push($command);
        return null;
        // → 202 Accepted: "I got it, I'll handle it later"
    }
}
```

The nullable type signals to both the framework and to other developers reading the code: this handler *might* return a result, but `null` means "accepted, pending".

### Created (201)

The command created something and returns an identifier or value object:

```php
class CreateUserHandler
{
    public function __invoke(CreateUser $command): UserId
    {
        $user = $this->users->create($command->name, $command->email);
        return $user->id;
        // → 201 Created
    }
}
```

### Why commands don't return response bodies

In CQRS, commands change state and queries read state. If a command returns a full object (the created user with all fields), you're mixing both concerns — the command handler becomes a query in disguise. This leads to:

- **Coupling** — the command handler must know what shape the client expects, which changes independently of the write logic.
- **Inconsistency** — the "created" representation may differ from what a proper query returns (stale caches, computed fields, related data).
- **Complexity** — the handler grows to satisfy read concerns it shouldn't own.

Instead, return an identifier (201) and let the client query for the full resource. Or return nothing (204) if the client doesn't need confirmation beyond "it worked".

### How it works internally

After calling the handler, the bus reflects on `__invoke`'s return type:

- **`void`** or no return type → wraps in `EmptyDTO` (kernel maps to 204)
- **Nullable type, returned `null`** → wraps in `AcceptedDTO` (kernel maps to 202)
- **Object returned** → passes through as-is (kernel maps to 201)
- **Scalar/array returned** → wraps in `QueryResult` (kernel maps to 201)

Handlers with no return type declaration are treated as `void` for backwards compatibility.

## Result wrappers

### EmptyDTO

Sentinel for `void` handlers. The bus wraps the result in `EmptyDTO` — a final class with no properties — so the pipeline doesn't break on a null return. The kernel maps this to **204 No Content**.

### AcceptedDTO

Sentinel for nullable handlers that returned `null`. The kernel maps this to **202 Accepted**. Distinct from `EmptyDTO` so the framework can express "nothing happened yet" differently from "the work is done".

### QueryResult

Wraps non-object handler return values (arrays, scalars) so they can flow through the pipeline. The kernel unwraps it before rendering.

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
|-- Handler lookup (convention: ClassName + "Handler")
\-- Return type reflection (void → EmptyDTO, nullable null → AcceptedDTO)

Dynamic DTOs (all extend DynamicDTO):
|-- Command — handler-only commands, wraps request body
|-- Query   — handler-only queries, wraps query params
\-- Page    — template-driven pages, routes to PageHandler

Result wrappers:
|-- EmptyDTO    — void handler → 204 No Content
|-- AcceptedDTO — nullable null → 202 Accepted
\-- QueryResult — non-object return → wraps for pipeline

Command return type → HTTP status:
  : void        → 204 No Content
  : ?Foo → null → 202 Accepted
  : ?Foo → Foo  → 201 Created
  : Foo         → 201 Created

Middleware Filters:
|-- FinalFilter
|-- PublicPropertyFilter
|-- ReadOnlyPropertyFilter
|-- NonStaticPropertyFilter
\-- NonPublicMethodFilter
```
