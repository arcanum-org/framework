# Arcanum Flow: Continuum

Continuum is middleware. Each stage wraps around the rest of the chain, running code before and after the inner stages. It's the same pattern as middleware in Laravel or Express.js.

## How it works

Each stage implements `Progression` — it receives the payload and a `$next` callback:

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

The key idea: a Progression **must** call `$next()` to continue the chain. If it doesn't, `StandardAdvancer` throws `Interrupted` — the chain was broken.

This means each stage can run logic **both before and after** the inner stages:

```
Pipeline:    A → B → C → done
Continuum:   A( B( C( done ) ) )
```

Stage A starts, calls `$next()` which enters B, which calls `$next()` which enters C. Then C finishes, B finishes, A finishes. The call stack unwinds naturally.

## Continuum — the middleware chain

```php
$continuum = new Continuum();
$continuum
    ->add(new AuthMiddleware())
    ->add(new LoggingMiddleware())
    ->add(new TimingMiddleware());

$result = $continuum->send($request);
```

Stages run in the order they're added. A Continuum is itself a `Stage`, so it can be used inside a Pipeline or another Continuum.

## StandardAdvancer — the engine

`StandardAdvancer` builds nested closures from the progressions. It reverses the array and wraps each one around the next, creating the onion-like execution:

```php
// Internally, for stages [A, B, C]:
$next = function() {};                    // innermost no-op
$next = function() use ($C, $next) { ... }; // C wraps the no-op
$next = function() use ($B, $next) { ... }; // B wraps C
$next = function() use ($A, $next) { ... }; // A wraps B
$next($payload);                            // start the chain
```

If a Progression doesn't call `$next()`, the advancer detects it and throws `Interrupted`.

## ContinuationCollection — named middleware chains

When you need multiple named middleware chains, `ContinuationCollection` acts as a registry:

```php
$collection = new ContinuationCollection();
$collection->add('auth', new CheckPermissionsMiddleware());
$collection->add('auth', new LogAccessMiddleware());
$collection->add('validation', new InputSanitizerMiddleware());

$result = $collection->send('auth', $request);
```

Continuums are created lazily — the first `add()` to a name creates a new `Continuum` automatically. You can retrieve one directly with `continuation()`.

## Pipeline vs. Continuum

Use **Pipeline** when each stage transforms data and passes it along — a straight line of processing.

Use **Continuum** when stages need to wrap around the rest of the chain — running setup/teardown, enforcing policies, or measuring timing across the entire inner execution.

## The interfaces

- **Progression** — a middleware stage: `__invoke(object $payload, callable $next): void`
- **Continuation** — extends Sender: adds `add(Progression $stage): Continuation`
- **Advancer** — the execution engine: `advance(object $payload, Progression ...$stages): object`
- **Collection** — named middleware registry: `add()`, `send()`, `continuation()`

## At a glance

```
A( B( C( done ) ) )  — each stage wraps the next

Continuum (implements Continuation)
\-- StandardAdvancer (builds nested closures)

ContinuationCollection (implements Collection)
\-- Manages named Continuum instances
```
