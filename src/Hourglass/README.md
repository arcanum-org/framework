# Arcanum Hourglass

Hourglass is the time package. It owns everything Arcanum does with time — wall-clock readings, elapsed-time measurement, and the test doubles that make time-dependent code deterministic.

Two primitives live here:

- **Clock** — wall-clock time. "What time is it right now?"
- **Stopwatch** — process timeline. "How much time has passed between two points in this process?"

They're related but distinct. Clock is for timestamps, expiry, audit logs. Stopwatch is for profiling, lifecycle measurement, "rendered in X ms" footers.

## Clock

`Arcanum\Hourglass\Clock` extends [PSR-20 `Psr\Clock\ClockInterface`](https://www.php-fig.org/psr/psr-20/), so any code that already speaks PSR-20 works without ceremony. Arcanum code depends on the `Clock` interface so the package controls its own surface.

```php
use Arcanum\Hourglass\Clock;

final class TokenIssuer
{
    public function __construct(private Clock $clock) {}

    public function issue(): Token
    {
        $expiresAt = $this->clock->now()->modify('+1 hour');
        // ...
    }
}
```

### Implementations

- **`SystemClock`** — production. `now()` returns `new DateTimeImmutable('now')`.
- **`FrozenClock`** — test double. Constructed with a fixed `DateTimeImmutable`. `set()` replaces it; `advance(DateInterval)` moves it forward.

```php
$clock = new FrozenClock(new DateTimeImmutable('2026-04-07T12:00:00Z'));
$issuer = new TokenIssuer($clock);

$token = $issuer->issue();
// $token->expiresAt is exactly 2026-04-07T13:00:00Z, every test run.

$clock->advance(new DateInterval('PT2H'));
// Now the clock reads 14:00 — assertions about expiry are deterministic.
```

## Stopwatch

`Stopwatch` records labeled `Instant`s across a process lifetime. The constructor captures an `arcanum.start` instant; everything else is added with `mark()`.

```php
use Arcanum\Hourglass\Stopwatch;

$stopwatch = new Stopwatch(ARCANUM_START);  // explicit start time

$stopwatch->mark('boot.complete');
$stopwatch->mark('handler.start');
// ... handler runs ...
$stopwatch->mark('handler.complete');

$stopwatch->timeSince('arcanum.start');         // → 12.4  (ms)
$stopwatch->timeBetween('handler.start', 'handler.complete'); // → 3.1
```

### Why pass `ARCANUM_START`?

By the time the container resolves `Stopwatch`, autoloading and bootstrapping have already happened. If the constructor records `microtime(true)` itself, the recorded "start" is wrong by some milliseconds. Pass the value of a constant defined as the first line of the entry point and the timeline reflects reality:

```php
// public/index.php
define('ARCANUM_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';
// ... container, kernel, etc.
```

The container binds `Stopwatch` via a factory that reads the constant. The constructor signature `__construct(?float $startTime = null)` makes the explicit-time path the natural one.

### Instants

```php
final class Instant
{
    public readonly string $label;
    public readonly float $time;
}
```

`marks()` returns `list<Instant>` in insertion order. Duplicate labels are preserved — the same label can be recorded multiple times in a request, and the timeline is the truth.

### API

| Method | Returns | Notes |
|---|---|---|
| `mark(string $label, ?float $time = null)` | void | Records an instant. Time defaults to `microtime(true)`. |
| `has(string $label)` | bool | True if any instant with this label has been recorded. |
| `timeSince(string $label)` | `?float` ms | Elapsed since the **most recent** instant with this label. Null if missing. |
| `timeBetween(string $from, string $to)` | `?float` ms | Delta between the **first** occurrences of each label. Null if either missing. |
| `startTime()` | float | The microtime of `arcanum.start`. |
| `marks()` | `list<Instant>` | The full timeline, insertion-ordered. |

### Static accessor — `Stopwatch::current()`

Stopwatch is mandatory framework-wide. For DI-friendly call sites (kernels, bootstrappers, listeners), inject it through the container. For call sites where injection would be noisy — middleware, formatter boundaries, anywhere that wants to add one mark line without taking a constructor dependency — use the static accessor:

```php
use Arcanum\Hourglass\Stopwatch;

// Fire-and-forget tap on the installed Stopwatch — no-op if uninstalled.
Stopwatch::tap('middleware.cors.start');

// Or explicitly read the installed instance — throws if uninstalled.
Stopwatch::current()->mark('middleware.cors.start');
```

`Stopwatch::tap()` is the right call for write-only sites (middleware,
formatter boundaries, listeners). `Stopwatch::current()` is the right call
for read sites (welcome page footer, log-line phase context, debug toolbars)
where missing-Stopwatch should fail loudly.

Bootstrap installs the container-resolved instance via `Stopwatch::install($stopwatch)`. From that point on, `Stopwatch::current()` returns it. Calling `current()` before `install()` throws — tests fail loudly instead of silently no-oping.

Tests should `Stopwatch::uninstall()` in `tearDown()` and install a fresh instance per test that needs one.

### Built-in framework marks

The framework records these instants automatically once Stopwatch is wired through Bootstrap and the kernels:

| Mark | When |
|---|---|
| `arcanum.start` | First line of the entry point (via `ARCANUM_START`) |
| `boot.complete` | After all bootstrappers have run |
| `request.received` | `RequestReceived` listener (HTTP) |
| `handler.start` | Conveyor before-middleware boundary |
| `handler.complete` | Conveyor after-middleware boundary |
| `render.start` | Formatter `format()` entry |
| `render.complete` | Formatter `format()` exit |
| `request.handled` | `RequestHandled` listener |
| `response.sent` | After `fastcgi_finish_request()` — client connection released |
| `arcanum.complete` | End of `terminate()` — last thing the framework does before process exit |

The cost is single-digit nanoseconds per mark. Stopwatch is always on.

## At a glance

```
Hourglass
├── Clock (interface, extends Psr\Clock\ClockInterface)
├── SystemClock      — production
├── FrozenClock      — test double (set, advance)
├── Stopwatch        — process timeline recorder
└── Instant          — readonly label + time
```
