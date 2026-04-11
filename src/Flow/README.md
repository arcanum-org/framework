# Arcanum Flow

Flow is all about moving data through your application from point A to point B. It's built on one simple idea: **a thing that takes an object and does something with it**.

```php
interface Stage
{
    public function __invoke(object $payload): object|null;
}
```

That's it. A Stage is any callable that receives an object and returns an object (or null if it has nothing to return). Think of it like a function at a conveyor belt — something comes in, something goes out.

Everything in Flow builds on this concept, split into five subpackages:

## [Pipeline](Pipeline/) — stages in a line

Pipeline chains stages linearly. The output of stage 1 becomes the input of stage 2, and so on. If any stage returns null, the pipeline throws `Interrupted`.

```
$payload → [Stage A] → [Stage B] → [Stage C] → $result
```

```php
$pipeline = new Pipeline();
$pipeline
    ->pipe(fn($order) => tap($order, fn() => $order->validated = true))
    ->pipe(fn($order) => tap($order, fn() => $order->tax = $order->total * 0.08));
$result = $pipeline->send($order);
```

Named pipelines are managed via `PipelayerSystem`.

## [Continuum](Continuum/) — middleware

Continuum is middleware. Each stage wraps around the rest of the chain with a `$next` callback, enabling code to run both before and after the inner stages.

```
Pipeline:    A → B → C → done
Continuum:   A( B( C( done ) ) )
```

```php
$continuum = new Continuum();
$continuum->add(new AuthMiddleware());
$continuum->add(new LoggingMiddleware());
$result = $continuum->send($request);
```

Named middleware chains are managed via `ContinuationCollection`.

## [Conveyor](Conveyor/) — command bus with middleware

Conveyor combines Pipeline and Continuum to build a command bus. Dispatch an object, and it flows through before-middleware, a handler (found by convention: `PlaceOrder` → `PlaceOrderHandler`), and after-middleware.

```php
$bus = new MiddlewareBus($container);
$bus->before(new ValidateOrderMiddleware());
$bus->after(new LogResponseMiddleware());
$result = $bus->dispatch(new PlaceOrder(item: 'burger', qty: 2));
```

Ships with DTO validation middleware: `FinalFilter`, `PublicPropertyFilter`, `ReadOnlyPropertyFilter`, `NonStaticPropertyFilter`, `NonPublicMethodFilter`.

## [Sequence](Sequence/) — lazy and eager ordered iterables

Sequence is a separate concern from the data-flow subpackages. It ships a `Sequencer<T>` interface with two implementations — `Cursor` (lazy, single-pass, self-closing) and `Series` (eager, multi-pass) — so consumers can stream unbounded result sets row-by-row, or materialize into a multi-pass list when they actually need one. Forge's read path is the first consumer: `Connection::query()` returns a `Sequencer`.

```php
use Arcanum\Flow\Sequence\Cursor;

$cursor = Cursor::open(
    source: fn() => readRowsFromSomewhere(),
    onClose: fn() => releaseTheHandle(),
);

foreach ($cursor->filter(...)->map(...) as $row) {
    process($row);
}
// close callback fires exactly once, on completion, break, throw, or destruct
```

## [River](River/) — stream wrappers

River is a separate concern from the data-flow subpackages. It wraps PHP's low-level stream resources into PSR-7 `StreamInterface` objects with type safety, auto-cleanup, and proper error handling.

```php
$stream = new Stream(LazyResource::for('file.txt', 'r'));
$data = $stream->read(1024);
// auto-closes when $stream goes out of scope
```

Includes `CachingStream` (seekable wrapper for non-seekable sources), `TemporaryStream` (php://temp scratch space), `EmptyStream` (null object), and `Bank` (copy utilities).

## The hierarchy at a glance

```
Stage (basic: in → out)
├── Pipeline    (chain stages linearly: A → B → C)
├── Continuum   (nest stages as middleware: A( B( C() ) ))
├── Conveyor    (Pipeline + Continuum = command bus with middleware)
├── Sequence    (separate concern — lazy/eager ordered iterables)
└── River       (separate concern — PSR-7 stream I/O wrappers)
```

Each subpackage has its own detailed README with full examples and API documentation.
