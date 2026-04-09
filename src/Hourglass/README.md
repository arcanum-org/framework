# Arcanum Hourglass

Hourglass is the time package. It owns everything Arcanum does with time — wall-clock readings, elapsed-time measurement, interval conversions, and the test doubles that make time-dependent code deterministic.

Three primitives live here:

- **Clock** — wall-clock time. "What time is it right now?"
- **Stopwatch** — process timeline. "How much time has passed between two points in this process?"
- **Interval** — `DateInterval` ↔ seconds conversion. "How many seconds is this interval, and vice versa?"

They're related but distinct. Clock is for timestamps, expiry, audit logs. Stopwatch is for profiling, lifecycle measurement, "rendered in X ms" footers. Interval is the small piece of glue between PHP's `\DateInterval` representation and the integer seconds that PSR-16 caches, schedulers, and rate limiters actually want to work with.

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

## Interval

`Arcanum\Hourglass\Interval` is a small stateless utility for converting between PHP's `\DateInterval` and integer seconds. PSR-16 caches and most TTL-shaped APIs accept `\DateInterval|int|null`, and the integer branch is straightforward — but the `DateInterval` branch needs a normalization step. Hourglass owns that normalization so individual cache drivers, throttlers, and schedulers don't each re-implement it.

```php
use Arcanum\Hourglass\Interval;

Interval::secondsIn(new \DateInterval('PT1H'));   // 3600
Interval::secondsIn(new \DateInterval('PT5M30S')); // 330

$ttl = Interval::ofSeconds(3600);                  // \DateInterval representing 1 hour
$cache->set('key', 'value', $ttl);
```

### API

| Method | Returns | Notes |
|---|---|---|
| `secondsIn(\DateInterval $interval)` | `int` | Total seconds in the interval. |
| `ofSeconds(int $seconds)` | `\DateInterval` | Construct an interval of `$seconds` seconds. Negative inputs clamp to zero. |

### How `secondsIn` handles months and years

The conversion anchors a `DateTime` at the unix epoch (timestamp `0`), adds the interval, and reads the resulting timestamp. For hour/minute/second/day intervals — the overwhelmingly common case — this is exact: `PT1H` is 3600, `P1D` is 86400, `P2DT3H` is 183600.

For month and year components, "how many seconds in a month?" doesn't have a single right answer. The epoch anchor pins it: `P1M` (1 month) added to Jan 1, 1970 lands on Feb 1, 1970 — 31 days, so `Interval::secondsIn(new \DateInterval('P1M'))` is `2_678_400`. Similarly `P1Y` resolves to 365 days because 1970 is non-leap. If your code is doing calendar arithmetic that needs accurate month/year handling, use `\DateTimeImmutable::add()` directly against a known reference date instead — `Interval::secondsIn` is for fixed-length conversions like cache TTLs and rate-limit windows.

### Why no `Interval` value object?

Hourglass deliberately does **not** ship a class that extends `\DateInterval` or wraps it. Both options were considered and rejected:

- **Subclass `\DateInterval`** — would inherit the parent's mutable public properties (`$y, $m, $d, $h, $i, $s`), violating the package's "immutable value objects only" preference. It also wouldn't reduce the cache-driver duplication on its own, because the drivers receive raw `\DateInterval` from PSR-16 and still need a function that operates on the parent type.
- **Wrap `\DateInterval`** — over-engineered for a one-method conversion, and forces every caller to choose between the wrapper and the raw type.

The static helper does the actual job (drivers stop duplicating the conversion) with the smallest surface. If a discoverable instance method like `$interval->toSeconds()` becomes worth its weight later, adding it is a one-line change.

## At a glance

```
Hourglass
├── Clock (interface, extends Psr\Clock\ClockInterface)
├── SystemClock      — production
├── FrozenClock      — test double (set, advance)
├── Stopwatch        — process timeline recorder
├── Instant          — readonly label + time
└── Interval         — \DateInterval ↔ seconds (static helper)
```
