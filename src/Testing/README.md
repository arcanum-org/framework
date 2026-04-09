# Arcanum Testing

Test harness utilities for Arcanum apps. Ships everything an app developer needs to write a handler test without hand-rolling a Cabinet container, faking PSR-7 requests, or stubbing identities and clocks.

Two pieces:

- **`TestKernel`** — a real Cabinet container plus lazy HTTP and CLI surfaces that wrap real `HyperKernel` and `RuneKernel` instances. One container, two transports, shared state.
- **`Factory`** — a reflection-based DTO generator that produces valid instances by reading the validation attributes off constructor parameters. Composes `Codex\Hydrator` so test data flows through the same coercion path as production request data.

Loaded via production `autoload`, not `autoload-dev` — `Arcanum\Testing\` is part of the framework's public API surface and ships in every install.

## TestKernel

`TestKernel` builds a shared Cabinet container up front with the bindings every test needs:

- **`Hourglass\Clock`** → `FrozenClock` pinned at `2026-01-01T00:00:00+00:00` (overridable).
- **`Psr\SimpleCache\CacheInterface`** → `Vault\ArrayDriver` sharing the frozen clock so TTL math is deterministic.
- **`Auth\ActiveIdentity`** → a fresh request-scoped holder.

```php
use Arcanum\Testing\TestKernel;
use Arcanum\Auth\SimpleIdentity;

$kernel = new TestKernel();
$kernel->actingAs(new SimpleIdentity('alice'));
```

`actingAs(Identity)` is a chained setter following a widely-used convention across PHP test harnesses. The identity lives on the shared `ActiveIdentity` so both the HTTP and CLI surfaces see it.

### Constructor overrides

```php
use Arcanum\Hourglass\FrozenClock;
use Arcanum\Vault\ArrayDriver;

$clock = new FrozenClock(new DateTimeImmutable('2030-06-15T12:00:00Z'));
$kernel = new TestKernel(
    clock: $clock,
    cache: new ArrayDriver($clock),
    rootDirectory: __DIR__ . '/fixture-app',
);
```

`rootDirectory` is forwarded to the wrapped `HyperKernel`/`RuneKernel` when the lazy surfaces are constructed. It defaults to `'/app'` when omitted, which is fine for tests that don't touch the filesystem.

### Accessors

| Method | Returns |
|---|---|
| `container()` | The shared `Cabinet\Application`. Resolve or override services here. |
| `clock()` | The bound `Clock`. Cast to `FrozenClock` to call `advance()`. |
| `cache()` | The bound `CacheInterface`. |
| `rootDirectory()` | The configured root directory, or `null`. |
| `actingAs(Identity)` | Chained — sets the active identity and returns `$this`. |
| `http()` | Lazy `HttpTestSurface`. Memoized. |
| `cli()` | Lazy `CliTestSurface`. Memoized. |

The HTTP and CLI surfaces are built on first access. Most tests touch one transport; a CLI-only test never pays for the HyperKernel it doesn't use.

### Cross-transport state

Both surfaces bootstrap against the same container, so anything set on `TestKernel` (or written by one surface) is visible from the other:

```php
$kernel = new TestKernel();
$kernel->actingAs(new SimpleIdentity('alice'));

$kernel->cli()->run(['arcanum', 'whoami']); // sees alice
$kernel->http()->get('/api/me');             // also sees alice
```

## HttpTestSurface

Translates fluent test calls into real PSR-7 `ServerRequest` objects and dispatches them through a wrapped `HyperKernel`. The kernel goes through the same exception-handling, middleware, and lifecycle paths production uses, so tests observe what real handlers observe.

```php
$response = $kernel->http()
    ->withHeader('Content-Type', 'application/json')
    ->post('/items', '{"name":"Widget"}');

$this->assertSame(201, $response->getStatusCode());
```

### Verb methods

| Method | Body |
|---|---|
| `get(path)` | none |
| `post(path, body = null)` | optional |
| `put(path, body = null)` | optional |
| `patch(path, body = null)` | optional |
| `delete(path, body = null)` | optional |

The path may include a query string (`/items?limit=10`) — it's parsed and exposed via `getQueryParams()`. JSON request bodies are decoded by the kernel's `prepareRequest()` and surface via `getParsedBody()`, same as production.

### `withHeader()` persists

Headers set via `withHeader(name, value)` persist across requests on the same surface — same ergonomics as configuring an HTTP client. Set the headers once per test, dispatch as many requests as you need.

### `setCoreHandler()` for fixture dispatch

`setCoreHandler(RequestHandlerInterface)` installs a PSR-15 handler the wrapped kernel delegates to. Without one, every request renders the kernel's standard 404 path through the registered `ExceptionRenderer` — useful for verifying the round-trip works end-to-end.

```php
$handler = new MyHandler();
$response = $kernel->http()
    ->setCoreHandler($handler)
    ->get('/widgets');
```

## CliTestSurface

Mirror of `HttpTestSurface` for CLI commands. `run(array $argv)` dispatches argv through a wrapped `RuneKernel` and returns a `CliResult`:

```php
use Arcanum\Testing\CliResult;

$result = $kernel->cli()->run(['arcanum', 'list']);

$this->assertSame(0, $result->exitCode);
$this->assertStringContainsString('list', $result->stdout);
$this->assertSame('', $result->stderr);
```

### `CliResult`

Immutable record with three fields: `int $exitCode`, `string $stdout`, `string $stderr`. A fresh `BufferedOutput` is bound for each `run()` call, so captures never bleed across invocations even when the same surface dispatches multiple commands in one test.

### `setRunner()` for fixture dispatch

Parallel to `HttpTestSurface::setCoreHandler()`. Installs a `(callable(Input, Output): int)` the kernel delegates to for non-empty argv. Without a runner, the empty-argv splash path falls through to the real `RuneKernel::handle()` so the round-trip is observable.

```php
$result = $kernel->cli()
    ->setRunner(function ($input, $output): int {
        $output->writeLine('hello ' . $input->command());
        return 0;
    })
    ->run(['arcanum', 'greet']);
```

## Factory

`Factory::make()` produces valid DTO instances for tests by synthesizing values from the validation attributes on each constructor parameter. Composes `Codex\Hydrator`: the synthesis pre-pass builds a `$data` array, then Hydrator walks the constructor, applies overrides + defaults, and coerces scalars. Hydrator passes object-valued data through unchanged, so pre-built nested DTOs round-trip cleanly — letting Factory recurse into nested DTO parameters.

```php
use Arcanum\Testing\Factory;

$factory = new Factory();

$dto = $factory->make(PlaceOrder::class);
// → fully valid PlaceOrder with synthesized fields

$dto = $factory->make(PlaceOrder::class, ['email' => 'alice@arcanum.dev']);
// → overrides take precedence; everything else is synthesized
```

### What gets synthesized

| Type | Default | Honored attributes |
|---|---|---|
| `string` | `'test'` | `#[Email]`, `#[Url]`, `#[Uuid]`, `#[In]`, `#[MinLength]`, `#[MaxLength]` (combined) |
| `int` | `1` | `#[Min]`, `#[Max]`, `#[In]` |
| `float` | `1.0` | `#[Min]`, `#[Max]`, `#[In]` |
| `bool` | `true` | `#[In]` |
| `array` | `['x']` | `#[In]` |
| Nullable scalar (no rules) | `null` | — |
| Nested user class | recursive `Factory::make()` | — |

Parameters with overrides are skipped. Parameters with default values are skipped (so Hydrator uses the default). `#[NotEmpty]` is satisfied implicitly by the non-empty defaults.

### Rules that need an override

Two rule classes are intentionally not auto-generatable and trigger `FactoryException`:

- **`#[Pattern]`** — arbitrary regular expressions are user-payload-dependent.
- **`#[Callback]`** — arbitrary callables can validate anything.

The fix is the same in both cases: pass an explicit override.

```php
$dto = $factory->make(SkuLookup::class, ['code' => 'ABC-1234']);
```

`FactoryException` implements `Glitch\ArcanumException` and carries a fix suggestion that points back at the `make()` overrides argument.

### Custom Hydrator

```php
$factory = new Factory(new MyHydrator());
```

The constructor takes `Hydrator $hydrator = new Hydrator()` for ergonomics. Most tests just use the default.
