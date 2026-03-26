# Arcanum Flow

Flow is all about moving data through your application from point A to point B. It's built on one simple idea: **a thing that takes an object and does something with it**.

```php
interface Stage
{
    public function __invoke(object $payload): object|null;
}
```

That's it. A Stage is any callable that receives an object and returns an object (or null if it has nothing to return). Think of it like a function at a conveyor belt — something comes in, something goes out.

Everything in Flow builds on this concept.

## Pipeline — stages in a line

A Pipeline chains multiple Stages together, one after another. The output of stage 1 becomes the input of stage 2, and so on.

```
$payload → [Stage A] → [Stage B] → [Stage C] → $result
```

In code:

```php
$pipeline = new Pipeline();
$pipeline
    ->pipe(function (object $order) {
        $order->validated = true;
        return $order;
    })
    ->pipe(function (object $order) {
        $order->tax = $order->total * 0.08;
        return $order;
    })
    ->pipe(function (object $order) {
        $order->total += $order->tax;
        return $order;
    });

$result = $pipeline->send($order);
```

Each stage **must** return the payload. If any stage returns null, the pipeline throws `Interrupted` — it means something broke the chain.

The `StandardProcessor` is the engine that loops through the stages:

```php
foreach ($stages as $stage) {
    $payload = $stage($payload);
}
```

Simple. Linear. No branching.

You can also organize named pipelines using `PipelayerSystem`, which acts as a registry:

```php
$system = new PipelayerSystem();
$system->pipe('validation', $validateStage);
$system->pipe('validation', $sanitizeStage);
$result = $system->send('validation', $payload);
```

## Continuum — middleware

A Continuum is like a Pipeline, but each stage gets a **`$next` callback** it must call to let the chain continue. This is the same pattern as middleware in Laravel or Express.js.

Each stage implements `Progression`:

```php
interface Progression
{
    public function __invoke(object $payload, callable $next): void;
}
```

Here's a real-world example — say you want to log before and after something happens:

```php
class LoggingMiddleware implements Progression
{
    public function __invoke(object $payload, callable $next): void
    {
        echo "Before: processing {$payload->name}\n";
        $next();  // let the rest of the chain run
        echo "After: done with {$payload->name}\n";
    }
}
```

The key difference from Pipeline:
- **Pipeline**: each stage transforms and returns. It's a straight line.
- **Continuum**: each stage wraps around the rest. It's like nesting dolls.

```
Pipeline:    A → B → C → done
Continuum:   A( B( C( done ) ) )
```

With Continuum, stage A runs some code, calls `$next()` which runs stage B, which calls `$next()` which runs stage C. Then C finishes, B finishes, A finishes. This means A can run code **both before and after** the inner stages.

If a stage forgets to call `$next()`, the `StandardAdvancer` throws `Interrupted` — because the chain was broken.

You can organize named continuums using `ContinuationCollection`:

```php
$collection = new ContinuationCollection();
$collection->add('auth', new CheckPermissionsMiddleware());
$collection->add('auth', new LogAccessMiddleware());
$result = $collection->send('auth', $request);
```

## Conveyor — command bus with middleware

The Conveyor combines Pipeline and Continuum to build a **command bus**. A command bus is a pattern where you send an object (a "command" or "message") and something handles it.

Think of ordering food at a restaurant:
1. **Before middleware** (Continuum): the host checks your reservation, the waiter takes your order — stuff that happens before cooking.
2. **Handler**: the kitchen cooks your food — the actual work.
3. **After middleware** (Continuum): the waiter checks the plate, adds garnish — stuff that happens after.

In code:

```php
$bus = new MiddlewareBus($container);

// Add validation middleware that runs BEFORE the handler
$bus->before(new ValidateOrderMiddleware());

// Add logging middleware that runs AFTER the handler
$bus->after(new LogResponseMiddleware());

// Dispatch a command — finds and calls PlaceOrderHandler automatically
$result = $bus->dispatch(new PlaceOrder(item: 'burger', qty: 2));
```

When you call `dispatch()`, here's what happens internally:

```php
(new Pipeline())
    ->pipe($this->dispatchFlow)     // 1. run "before" middleware (Continuum)
    ->pipe(function ($object) {
        return $this->handlerFor($object)($object);  // 2. find and call the handler
    })
    ->pipe($this->responseFlow)     // 3. run "after" middleware (Continuum)
    ->send($object);
```

The handler is found by convention: for a `PlaceOrder` command, it looks up `PlaceOrderHandler` from the container.

The Conveyor middleware filters (like `PublicPropertyFilter`, `ReadOnlyPropertyFilter`) are Progressions that validate the structure of DTOs passing through the bus — e.g., making sure command objects only have public readonly properties.

## River — stream wrappers

River is a separate concern from the others. It wraps PHP's low-level stream resources (`fopen`, `fread`, `fwrite`, etc.) into proper objects that implement PSR-7's `StreamInterface`.

Why? Raw PHP streams are messy:

```php
// Raw PHP — no type safety, easy to forget cleanup
$fp = fopen('file.txt', 'r');
$data = fread($fp, 1024);
fclose($fp);  // hope you don't forget this
```

With River:

```php
// Type-safe, auto-closes on destruct
$stream = new Stream(new StreamResource(fopen('file.txt', 'r')));
$data = $stream->read(1024);
// stream closes automatically when $stream goes out of scope
```

Notable classes:
- **Stream** — the core wrapper around PHP resource streams with methods for reading, writing, seeking, and metadata inspection.
- **CachingStream** — wraps a non-seekable stream (like an HTTP response) and caches what it reads, so you can seek back through it.
- **TemporaryStream** — a stream backed by `php://temp` for scratch work.
- **EmptyStream** — a stream that's always empty (null object pattern).
- **Bank** — static utility class for copying between streams.

## The hierarchy at a glance

```
Stage (basic: in -> out)
|-- Pipeline    (chain stages linearly: A -> B -> C)
|-- Continuum   (nest stages as middleware: A( B( C() ) ))
|-- Conveyor    (Pipeline + Continuum = command bus with middleware)
\-- River       (totally separate — stream I/O wrappers)
```
