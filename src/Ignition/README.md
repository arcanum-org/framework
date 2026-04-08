# Arcanum Ignition

Ignition is the bootstrap package. It starts your application — loading environment variables, configuration, logging, error handling, routing, and middleware — then hands off to either the HTTP or CLI transport layer. Every request and every command begins here.

## Two kernels, one architecture

Arcanum has two entry points for two transports, and they share the same foundation:

| | **HyperKernel** (HTTP) | **RuneKernel** (CLI) |
|---|---|---|
| **Entry point** | `public/index.php` | `bin/arcanum` |
| **Input** | PSR-7 `ServerRequestInterface` | `$argv` (parsed into `Input`) |
| **Output** | PSR-7 `ResponseInterface` | `Output` (stdout/stderr) |
| **Routing** | Atlas `HttpRouter` | Atlas `CliRouter` |
| **Rendering** | Hyper response renderers | Shodo formatters directly |
| **Error display** | `JsonExceptionResponseRenderer` | `CliExceptionWriter` |

Both kernels implement the `Kernel` interface and run the same bootstrap sequence. The difference is which bootstrappers they include and how they deliver output.

## Getting started

### HTTP application

Your app extends `HyperKernel` and implements `handleRequest()`:

```php
// app/Http/Kernel.php
final class Kernel extends HyperKernel
{
    protected function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        // Route the request, hydrate a DTO, dispatch through the bus, render
    }
}
```

The entry point bootstraps and handles the request:

```php
// public/index.php
$container = require __DIR__ . '/../bootstrap/http.php';
$kernel = $container->get(Kernel::class);
$kernel->bootstrap($container);

$server = $container->get(Server::class);
$request = $server->request();
$response = $kernel->handle($request);
$server->send($response);

$kernel->terminate();
```

### CLI application

Your app extends `RuneKernel`. Most apps don't need to override anything:

```php
// app/Cli/Kernel.php
final class Kernel extends RuneKernel
{
    // Customize bootstrappers or built-in commands here if needed
}
```

The entry point is a simple PHP script:

```php
// bin/arcanum
$container = require __DIR__ . '/../bootstrap/cli.php';
$kernel = $container->get(Kernel::class);
$kernel->bootstrap($container);

exit($kernel->handle($argv));
```

`RuneKernel::handle()` returns an integer exit code — `0` for success, `1` for failure, `2` for invalid input.

## Bootstrap sequence

Bootstrappers run once, in order, the first time `bootstrap()` is called. Each one receives the DI container and registers services. Calling `bootstrap()` twice is a no-op.

### HTTP bootstrappers

| Order | Bootstrapper | What it does |
|---|---|---|
| 1 | `Stopwatch` | Builds the framework `Stopwatch`, reads `ARCANUM_START` if defined, registers it as a singleton, and installs it as the process-global. Must run first so every later bootstrapper can `tap()` instants. |
| 2 | `Environment` | Loads `.env` file, validates required vars, registers `Environment` in container |
| 3 | `Configuration` | Loads `config/*.php` files (or cache), registers `Configuration` in container |
| 4 | `Security` | Reads `APP_KEY`, registers `Encryptor`, `Signer`, and `Hasher` in container |
| 5 | `Cache` | Reads `config/cache.php`, registers `CacheManager` and default `CacheInterface` |
| 6 | `Database` | Reads `config/database.php`, registers `ConnectionManager`, `DomainContext`, `Database`. Skips if no config |
| 7 | `Sessions` | Reads `config/session.php`, registers session handler and `ActiveSession` |
| 8 | `Auth` | Reads `config/auth.php`, registers guards, `ActiveIdentity` |
| 9 | `Routing` | Registers router, page discovery, hydrator, Conveyor middleware |
| 10 | `Helpers` | Registers global helpers (built-in + `app/Helpers/Helpers.php`), `HelperRegistry`, `HelperResolver`, `HelperDiscovery` |
| 11 | `Formats` | Registers Shodo formatters, `FormatRegistry`, and the response renderers (`Json`, `Html`, `Csv`, `PlainText`, `Markdown`, exception renderers) |
| 12 | `RouteMiddleware` | Discovers per-route middleware from attributes and `Middleware.php` files |
| 13 | `Logger` | Builds Monolog handlers and channels from config |
| 14 | `Exceptions` | Sets PHP error/exception/shutdown handlers |
| 15 | `Middleware` | Registers global HTTP middleware from config |

### CLI bootstrappers

| Order | Bootstrapper | What it does |
|---|---|---|
| 1 | `Stopwatch` | Same as HTTP — shared. Runs first so the rest of the boot sequence can mark instants. |
| 2 | `Environment` | Same as HTTP — shared |
| 3 | `Configuration` | Same as HTTP — shared |
| 4 | `Security` | Same as HTTP — shared |
| 5 | `Cache` | Same as HTTP — shared |
| 6 | `Database` | Same as HTTP — shared. Skips if no config |
| 7 | `Auth` | Same as HTTP — shared |
| 8 | `CliRouting` | Registers CLI router, format registry, formatters, output, built-in commands, Conveyor middleware |
| 9 | `Logger` | Same as HTTP — shared |
| 10 | `Exceptions` | Same as HTTP — shared |

Notice that `Stopwatch`, `Environment`, `Configuration`, `Security`, `Cache`, `Database`, `Auth`, `Logger`, and `Exceptions` are **shared** between both transports. Only the routing, helper, format, and middleware bootstrappers differ — HTTP needs `Routing`, `Helpers`, `Formats`, `RouteMiddleware`, `Sessions`, and `Middleware`; CLI needs `CliRouting`.

### Lifecycle marks

Both kernels record built-in instants on the framework `Stopwatch` (from the [Hourglass](../Hourglass/README.md) package) at every important point in the request lifecycle:

| Mark | When | Recorded by |
|---|---|---|
| `arcanum.start` | Front controller line 1 (via `ARCANUM_START`) or Stopwatch construction | `Bootstrap\Hourglass` |
| `boot.complete` | After all bootstrappers finish | `HyperKernel`/`RuneKernel::bootstrap()` |
| `request.received` | Before any HTTP middleware runs | `HyperKernel::dispatchRequestReceived()` |
| `handler.start` | Conveyor `MiddlewareBus::dispatch()` entry | `Flow\Conveyor\MiddlewareBus` |
| `handler.complete` | Conveyor `MiddlewareBus::dispatch()` exit (in `finally`) | `Flow\Conveyor\MiddlewareBus` |
| `render.start` | `ResponseRenderer::render()` entry | the five HTTP response renderers |
| `render.complete` | `ResponseRenderer::render()` exit (in `finally`) | the five HTTP response renderers |
| `request.handled` | After the response is built, before `terminate()` | `HyperKernel::dispatchRequestHandled()` |
| `response.sent` | After `fastcgi_finish_request()` releases the client connection | `HyperKernel::terminate()` |
| `arcanum.complete` | End of `terminate()` — last thing the framework does before process exit | both kernels |

Read elapsed time from anywhere via the static accessor:

```php
use Arcanum\Hourglass\Stopwatch;

$totalProcessMs = Stopwatch::current()->timeBetween('arcanum.start', 'arcanum.complete');
$justTheRequest = Stopwatch::current()->timeSince('request.received');
```

The Hourglass README has the full Stopwatch API. Marking is always on; the cost per mark is single-digit nanoseconds.

### Customizing bootstrappers

Both kernels define their bootstrapper list as a protected property. Override it in your app's kernel to add, remove, or reorder:

```php
final class Kernel extends HyperKernel
{
    protected array $bootstrappers = [
        Bootstrap\Environment::class,
        Bootstrap\Configuration::class,
        Bootstrap\Routing::class,
        // Removed RouteMiddleware — not using per-route middleware
        Bootstrap\Logger::class,
        Bootstrap\Exceptions::class,
        Bootstrap\Middleware::class,
        \App\Bootstrap\CustomService::class,  // Your own bootstrapper
    ];
}
```

A custom bootstrapper just implements `Bootstrapper`:

```php
class CustomService implements Bootstrapper
{
    public function bootstrap(Application $container): void
    {
        $container->service(MyService::class);
    }
}
```

## HTTP request handling

`HyperKernel::handle()` wraps the full request lifecycle:

1. **Prepare** — Parse JSON bodies, validate content type
2. **Middleware** — Run global HTTP middleware stack (onion model)
3. **Dispatch** — Call your app's `handleRequest()`
4. **Errors** — Catch exceptions, render via `ExceptionRenderer`, flow back through middleware

```
Request
│
├── prepareRequest()          — JSON body parsing
│   ├── Global middleware     — CORS, auth, rate limiting, ...
│   │   ├── handleRequest()   — your routing + dispatch logic
│   │   │
│   │   ├── (or handleException() on error)
│   ├── Global middleware     — response path
│
Response
```

Both success and error responses flow back through the full middleware stack, so response-modifying middleware (like CORS headers) always runs.

### JSON body parsing

Requests with `Content-Type: application/json` are automatically parsed into the request's parsed body. Malformed JSON returns **400 Bad Request**.

### Global middleware

Register middleware classes in your kernel's `bootstrap/http.php` or via the `Middleware` bootstrapper from `config/middleware.php`. Middleware executes in the order registered — first registered is the outermost layer.

```php
$kernel->middleware(\App\Http\Middleware\Cors::class);
$kernel->middleware(\App\Http\Middleware\Auth::class);
```

### Lifecycle events

The kernel dispatches events via Echo (PSR-14) at key points in the request lifecycle. Events are for observation — use middleware when you need to wrap or transform.

| Event | When | Mutable? |
|---|---|---|
| `RequestReceived` | Before middleware | Yes — `setRequest()` adds attributes (start time, request ID) |
| `RequestHandled` | After response produced | No — observe only |
| `RequestFailed` | Exception thrown | No — for reporting, not rendering |
| `ResponseSent` | After `terminate()` | No — post-response cleanup |

Events only dispatch when a `PSR-14 EventDispatcherInterface` is registered in the container. No overhead when no listeners exist.

**Guideline:** If you need before *and* after, or need to short-circuit — use middleware. If you need to react at one point — use an event listener.

```php
// Register a listener via Echo\Provider
$provider->listen(RequestReceived::class, function (RequestReceived $event) {
    $event->setRequest(
        $event->getRequest()->withAttribute('start_time', microtime(true))
    );
    return $event;
});

$provider->listen(RequestHandled::class, function (RequestHandled $event) {
    $start = $event->getRequest()->getAttribute('start_time');
    $duration = microtime(true) - $start;
    $logger->info('Request handled', [
        'method' => $event->getRequest()->getMethod(),
        'path' => $event->getRequest()->getUri()->getPath(),
        'status' => $event->getResponse()->getStatusCode(),
        'duration_ms' => round($duration * 1000, 2),
    ]);
    return $event;
});
```

`terminate()` should be called from `public/index.php` after sending the response. It calls `fastcgi_finish_request()` if available (releasing the client connection), then dispatches `ResponseSent`.

## CLI command handling

`RuneKernel::handle()` follows a similar pattern but without PSR-7 or middleware:

1. **Parse** — Convert `$argv` into an `Input` object (command name, flags, options)
2. **Built-ins** — Check for framework commands (`list`, `help`, `validate:handlers`)
3. **Route** — Map the command to a DTO class via `CliRouter`
4. **Help** — If `--help` flag is present, show parameter info and exit
5. **Hydrate** — Map `--key=value` flags to the DTO constructor
6. **Dispatch** — Send through the Conveyor bus (same bus HTTP uses)
7. **Render** — Format the result using the `CliFormatRegistry` and write to output

```
$argv
│
├── Input::fromArgv()          — parse command, flags, options
├── Check built-in commands    — list, help, validate:handlers
├── CliRouter::resolve()       — map to Route + DTO class
├── Hydrator::hydrate()        — flags → DTO
├── Bus::dispatch()            — same Conveyor bus as HTTP
├── CliFormatRegistry          — format result as cli/json/csv/table
│
Exit code (0 = success, 1 = failure, 2 = invalid)
```

Exceptions are caught and written to stderr via `CliExceptionWriter`. In debug mode (when `app.debug` is `true`), you get the full exception class, file, line, and stack trace. In production mode, you get a clean one-liner.

## Transport identity

Each kernel registers a `Transport` enum value in the container during bootstrap:

```php
// HyperKernel registers:
$container->instance(Transport::class, Transport::Http);

// RuneKernel registers:
$container->instance(Transport::class, Transport::Cli);
```

This lets transport-aware middleware (like `TransportGuard`) check which transport is active. A DTO marked `#[CliOnly]` will be rejected on HTTP, and vice versa for `#[HttpOnly]`.

## RouteDispatcher

`RouteDispatcher` bridges per-route middleware with the command bus. It's used by your HTTP kernel to dispatch DTOs through the correct middleware layers:

```php
// In your Kernel::handleRequest():
$result = $dispatcher->dispatch($dto, $route);     // Conveyor-layer middleware
$handler = $dispatcher->wrapHttp($route, $core);   // HTTP-layer middleware
$response = $handler->handle($request);
```

See the [Atlas README](../Atlas/README.md#route-middleware) for the full middleware execution order.

## Configuration caching

The `Configuration` bootstrapper can cache all config files into a single serialized file, avoiding the cost of reading every `config/*.php` file on each request:

```php
// Cached to: files/cache/config.php
// Controlled by config/cache.php:
'config' => [
    'enabled' => true,
],
```

The cache is automatically invalidated when config files change. To clear it manually, delete the cache file or use `ConfigurationCache::clear()`.

## Directory conventions

```
your-app/
├── config/
│   ├── app.php          — namespace, pages config, debug mode
│   ├── cache.php        — cache toggle settings
│   ├── formats.php      — response format registry
│   ├── log.php          — logging handlers and channels
│   ├── middleware.php    — global HTTP middleware
│   └── routes.php       — custom routes, CLI aliases, page overrides
├── files/
│   ├── cache/
│   │   ├── config.php        — serialized configuration
│   │   ├── pages.php         — page discovery cache
│   │   ├── route_middleware.php — middleware discovery cache
│   │   └── templates/        — compiled template cache
│   └── logs/
│       └── app.log           — default log file
├── bin/
│   └── arcanum               — CLI entry point
└── public/
    └── index.php             — HTTP entry point
```

Directories are configurable via the kernel constructor:

```php
$kernel = new Kernel(
    rootDirectory: '/var/www/app',
    configDirectory: '/var/www/app/config',  // default: rootDirectory/config
    filesDirectory: '/var/www/app/files',    // default: rootDirectory/files
);
```

## The interfaces

- **Kernel** — the core contract: `bootstrap()`, `rootDirectory()`, `configDirectory()`, `filesDirectory()`, `requiredEnvironmentVariables()`. Both `HyperKernel` and `RuneKernel` implement this.
- **Bootstrapper** — `bootstrap(Application $container): void`. Implement this to create your own bootstrappers.
- **Terminable** — `terminate(): void`. A cleanup hook called after the response is sent (HTTP) or after the command completes (CLI).
- **Transport** — enum with `Http` and `Cli` cases. Registered in the container by each kernel.

## At a glance

```
Kernel interface
├── HyperKernel (HTTP)
│   ├── bootstrap()       — run HTTP bootstrappers once
│   ├── handle()          — prepare → middleware → dispatch → errors
│   ├── middleware()       — register global HTTP middleware
│   ├── prepareRequest()  — JSON parsing
│   └── handleRequest()   — abstract, your app implements this
│
└── RuneKernel (CLI)
    ├── bootstrap()       — run CLI bootstrappers once
    ├── handle()          — parse → route → hydrate → dispatch → render
    ├── handleInput()     — built-ins, routing, dispatch
    └── renderResult()    — format output via CliFormatRegistry

Shared bootstrappers:        HTTP-only:            CLI-only:
├── Stopwatch                ├── Sessions          └── CliRouting
├── Environment              ├── Routing
├── Configuration            ├── Helpers
├── Security                 ├── Formats
├── Cache                    ├── RouteMiddleware
├── Database                 └── Middleware
├── Auth
├── Logger
└── Exceptions

Transport enum: Http | Cli
ConfigurationCache — cache/load/clear serialized config
RouteDispatcher — per-route middleware + bus composition (HTTP)
```
