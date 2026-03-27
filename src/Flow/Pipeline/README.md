# Arcanum Flow: Pipeline

Pipeline chains stages in a straight line. The output of one stage becomes the input of the next. No branching, no wrapping — just a linear sequence of transformations.

## How it works

A `Pipeline` collects stages via `pipe()` and runs them in order via `send()`:

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

Each stage **must** return the payload (or a transformed version of it). If any stage returns null, the pipeline throws `Interrupted` — the chain is broken.

A Pipeline is itself a `Stage`, so you can nest pipelines inside other pipelines.

## StandardProcessor — the engine

`StandardProcessor` is what actually loops through the stages:

```php
foreach ($stages as $stage) {
    $payload = $stage($payload);
    if ($payload === null) {
        throw new Interrupted("...");
    }
}
return $payload;
```

You can swap in a different `Processor` if you need custom execution logic — just pass it to the Pipeline constructor.

## PipelayerSystem — named pipelines

When you need multiple named pipelines (e.g., one for "validation", one for "transformation"), `PipelayerSystem` acts as a registry:

```php
$system = new PipelayerSystem();
$system->pipe('validation', $validateStage);
$system->pipe('validation', $sanitizeStage);
$system->pipe('transform', $normalizeStage);

$result = $system->send('validation', $payload);
```

Pipelines are created lazily — the first time you `pipe()` to a name, a new `Pipeline` is created automatically. You can also retrieve a pipeline directly with `pipeline()` or remove one with `purge()`.

## The interfaces

- **Stage** — the foundation: `__invoke(object $payload): object|null`
- **Sender** — extends Stage: adds `send(object $payload): object`
- **Pipelayer** — extends Sender: adds `pipe(callable|Stage $stage): Pipelayer`
- **Processor** — the execution engine: `process(object $payload, callable ...$stages): object`
- **System** — named pipeline registry: `pipe()`, `send()`, `pipeline()`, `purge()`

## At a glance

```
$payload → [Stage A] → [Stage B] → [Stage C] → $result

Pipeline (implements Pipelayer)
\-- StandardProcessor (loops through stages)

PipelayerSystem (implements System)
\-- Manages named Pipeline instances
```
