# Arcanum Flow\Sequence

A generic producer abstraction for ordered iterables. Two shapes implement it — `Cursor` (lazy, single-pass) and `Series` (eager, multi-pass) — behind a single interface that stays honest on both. Forge's read path is the first consumer: `Connection::query()` returns a `Sequencer<array<string, mixed>>` so handlers can stream unbounded result sets without buffering every row.

## Why

When Forge used to return a `Result` object that called `PDOStatement::fetchAll()` up front, every query was bounded by the size of the result set. A 100k-row export allocated 320 MB. A million-row batch job was impossible. You had no way to process rows one at a time without reaching past the abstraction and talking to PDO directly.

Streaming is the fix — but you can't hang streaming off the same type that also answers `count()` or `isEmpty()`, because those questions force materialization. The abstraction has to draw a line: **operations that are honest on a one-shot iterator stay on the interface; operations that force buffering live on the eager shape only.** You pick which shape you want, and the cost is named at the call site.

## The three types

```
Sequencer<T>   interface         — abstract ordered iterable
    ├── Cursor<T>                — lazy, single-pass
    └── Series<T>                — eager, multi-pass
```

`Sequencer<T>` extends `IteratorAggregate<int, T>`, so any sequencer works with `foreach` and `iterator_to_array`. The template is `@template-covariant` — every use of `T` is in a producer position (outputs, and parameters of callback parameters that cancel out to covariant), so `Sequencer<Dog>` is assignable to `Sequencer<Animal>`.

### Cursor — lazy, single-pass

Wraps a generator-producing closure plus a close callback. The cursor iterates the generator at most once and guarantees the close callback runs exactly once across every code path: full iteration, early `break`, thrown exception, or destruction without iteration.

```php
use Arcanum\Flow\Sequence\Cursor;

$cursor = Cursor::open(
    source: static function (): \Generator {
        $handle = fopen('large.csv', 'rb');
        try {
            while (($row = fgetcsv($handle)) !== false) {
                yield $row;
            }
        } finally {
            fclose($handle);
        }
    },
    onClose: static function (): void {
        // Anything the source's own finally block doesn't own.
    },
);

foreach ($cursor as $row) {
    process($row);
}
// close callback fires exactly once
```

Lazy operations — `map`, `filter`, `chunk`, `take` — mark the parent cursor consumed and return a new cursor sharing the same close latch. After deriving, the parent handle is dead; use the returned cursor.

```php
$totals = $cursor
    ->filter(fn(array $row) => $row['status'] === 'shipped')
    ->map(fn(array $row) => (float) $row['total'])
    ->chunk(1000);

foreach ($totals as $batch) {
    $worker->push(array_sum($batch));
}
```

None of those operators execute until the terminal `foreach`.

### Series — eager, multi-pass

Backed by a `list<T>`. All `Sequencer` operations work, plus the eager-only ones: `count()`, `all()`, `isEmpty()`. Iteration is multi-pass. `map`/`filter`/`chunk`/`take` return new `Series` instances eagerly.

```php
use Arcanum\Flow\Sequence\Series;

$series = new Series([1, 2, 3, 4, 5]);

$series->count();                               // 5
$series->all();                                 // [1, 2, 3, 4, 5]
$series->filter(fn($n) => $n > 2)->all();       // [3, 4, 5]
$series->map(fn($n) => $n * 10)->first();       // 10
```

### `toSeries()` — the escape hatch

When you have a `Sequencer` and you need a row count, multi-pass access, or random access, call `toSeries()`:

```php
$rows = $db->model->products(category: 'shoes');  // Sequencer

$series = $rows->toSeries();                      // walks the cursor once,
                                                  // returns a fresh Series
$count  = $series->count();
$first  = $series->first();
$all    = $series->all();
```

On a `Cursor`, `toSeries()` walks the underlying generator into a list and returns a new `Series`; it's legal only on a fresh cursor and throws `CursorAlreadyConsumed` if the cursor has already been iterated. On a `Series`, `toSeries()` returns `$this`. Either way, callers can write the method unconditionally.

**The cost of materialization is named at the call site.** That's the whole point of keeping `count()` off the interface — a caller that writes `$rows->toSeries()->count()` is making an explicit choice to buffer every row, not accidentally stumbling into it through an innocent-looking accessor.

## What lives where

| Operation | `Sequencer` | `Series` only | Reason |
|---|---|---|---|
| `getIterator()` | ✓ | — | `foreach`-compatible on both |
| `first(): ?T` | ✓ | — | O(1) — Cursor peeks one row and closes |
| `each(callable)` | ✓ | — | Terminal iteration |
| `map(callable)` | ✓ | — | Lazy on Cursor, eager on Series |
| `filter(callable)` | ✓ | — | Lazy on Cursor, eager on Series |
| `chunk(int)` | ✓ | — | Lazy on Cursor, eager on Series |
| `take(int)` | ✓ | — | Lazy on Cursor, eager on Series |
| `toSeries()` | ✓ | — | Walks a Cursor; returns `$this` on Series |
| `count()` | — | ✓ | Would materialize a Cursor silently |
| `all()` | — | ✓ | Eager-only dump |
| `isEmpty()` | — | ✓ | Can't answer without consuming at least one row |

## Cursor contract in detail

- **Single-pass.** Throws `CursorAlreadyConsumed` on a second iteration, or on `toSeries()` / `first()` / `map()` / `filter()` / `chunk()` / `take()` after any prior iteration or derivation.
- **Self-closing.** `getIterator()` wraps the source generator in `try`/`finally` that invokes the close latch. The latch runs the close callback exactly once across all paths.
- **Destructor-safe.** A cursor that goes out of scope without ever being iterated still runs its close callback on destruction. A cursor that has already been iterated (or derived from) skips the destructor close — the latch has already fired, or been transferred to a derived cursor.
- **Derived cursors share the latch.** `$cursor->map($fn)` returns a new cursor wrapping the same source generator with the transform applied, sharing the parent's close latch. When iteration finishes (or throws, or breaks), the shared latch fires once. The original parent handle is marked consumed.

## Streaming benchmark

The eager-Result shape Forge used to ship scaled linearly in memory. Generators stay flat regardless of result size.

Measured with subprocess isolation per cell, 20-column rows, sqlite in-memory, mean of 5–500 iterations depending on size:

| Rows | Eager time | Generator time | Eager peak memory | Generator peak memory |
|---|---|---|---|---|
| 1 | 0.0119 ms | 0.0124 ms | 5.7 KB | 4.6 KB |
| 10 | 0.0344 ms | 0.0338 ms | 33.7 KB | 6.2 KB |
| 100 | 0.254 ms | 0.239 ms | 329 KB | 6.3 KB |
| 1,000 | 2.39 ms | 2.26 ms | 3.20 MB | 6.3 KB |
| 10,000 | 26.3 ms | 22.7 ms | 32.1 MB | 6.3 KB |
| 100,000 | 288 ms | 237 ms | 320 MB | 6.3 KB |
| 500,000 | 1629 ms | 1208 ms | 1598 MB | 6.3 KB |

Generator is equal or faster at every row count from 10 up. Memory scales linearly for eager and **stays flat at 6.3 KB for the generator regardless of result size.** Streaming is never meaningfully slower and always uses constant memory — which is why Forge's read path streams by default and makes materialization an opt-in.

## When to use which

| You have | Pick |
|---|---|
| A database cursor, file handle, or any one-shot iterator | `Cursor` |
| A fixture, a test double, an already-in-memory list | `Series` |
| A Sequencer and you need `count()` / random access | Call `->toSeries()` on it |
| A streaming pipeline with lazy transforms | `Cursor` + `map`/`filter`/`chunk`/`take` |
| A small result set you'll iterate multiple times | Stream to `Cursor`, then `->toSeries()` once |

## At a glance

```
Flow/Sequence/
    Sequencer             — interface; @template-covariant T
    Cursor                — lazy, single-pass; self-closing via CloseLatch
    Series                — eager, multi-pass; list-backed
    CloseLatch            — internal one-shot close guard (shared across derived cursors)
    CursorAlreadyConsumed — LogicException (implements ArcanumException)
```
