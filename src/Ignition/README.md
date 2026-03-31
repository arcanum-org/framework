# Arcanum Ignition

Ignition is the bootstrap kernel. It starts the application — loading environment variables, configuration, logging, error handling, routing, and middleware — then hands off to the HTTP layer. Every request begins here.

## How it works

`HyperKernel` is the base class your application extends. It implements PSR-15 `RequestHandlerInterface` and runs a sequence of bootstrappers before handling its first request:

```php
// public/index.php
$kernel = new \App\Http\Kernel(
    rootDirectory: dirname(__DIR__),
);
$kernel->bootstrap($container);
$response = $kernel->handle($request);
```

Your app's Kernel extends `HyperKernel` and implements `handleRequest()` — the core dispatch logic after bootstrapping and middleware:

```php
final class Kernel extends HyperKernel
{
    protected function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        // Route, hydrate, dispatch, render
    }
}
```

## Bootstrap sequence

Bootstrappers run once, in order, the first time `bootstrap()` is called. Each one receives the DI container and registers services:

| Order | Bootstrapper | Responsibility |
|---|---|---|
| 1 | `Environment` | Load `.env`, validate required vars, register `Environment` in container |
| 2 | `Configuration` | Load `config/*.php` files (or cache), register `Configuration` in container |
| 3 | `Routing` | Register router, format registry, renderers, page discovery, hydrator |
| 4 | `RouteMiddleware` | Discover per-route middleware (attributes + `Middleware.php` files), register `RouteDispatcher` |
| 5 | `Logger` | Build Monolog handlers and channels from config, register `Logger` |
| 6 | `Exceptions` | Set PHP error/exception/shutdown handlers, register `Handler` and reporters |
| 7 | `Middleware` | Register global HTTP middleware from config, append framework `Options` handler |

Double-bootstrap is prevented — calling `bootstrap()` twice is a no-op.

## Configuration caching

`ConfigurationCache` avoids re-reading config files on every request:

```php
// Cached to: files/cache/config.php
$cache = new ConfigurationCache('files/cache/config.php');

if ($cache->exists()) {
    $config = $cache->load();  // fast: single file include
} else {
    $config = /* scan config/*.php */;
    $cache->write($config);
}
```

The `Configuration` bootstrapper handles this automatically. Clear the cache when config files change:

```php
$cache->clear();
```

## Request handling

`HyperKernel::handle()` wraps the full request lifecycle:

1. **Prepare** — Parse JSON bodies, validate content
2. **Middleware** — Run global HTTP middleware stack (onion model)
3. **Dispatch** — Call `handleRequest()` (your app's core logic)
4. **Errors** — Catch exceptions, render via `ExceptionRenderer`, flow back through middleware

```
Request
│
├─ prepareRequest()      — JSON body parsing, validation
│  ├─ Global HTTP middleware (Cors, Options, Auth, ...)
│  │  ├─ handleRequest()  — your app's routing + dispatch
│  │  │
│  │  ├─ (or handleException() if dispatch threw)
│  ├─ Global HTTP middleware (response path)
│
Response
```

Both success and error responses flow back through the full middleware stack, so CORS headers and other response-modifying middleware always run.

### JSON body parsing

POST/PUT/PATCH requests with `application/json` content type are automatically parsed:

```php
// Malformed JSON → 400 Bad Request
// Valid JSON → parsed into request body
```

## RouteDispatcher

`RouteDispatcher` bridges per-route middleware with the command bus. It's used by your app's Kernel to dispatch DTOs through the correct middleware layers:

```php
// In your Kernel::handleRequest():
$result = $dispatcher->dispatch($dto, $route);     // Conveyor-layer middleware
$handler = $dispatcher->wrapHttp($route, $core);   // HTTP-layer middleware
$response = $handler->handle($request);
```

See the [Atlas README](../Atlas/README.md#route-middleware) for the full middleware execution order.

## Directory conventions

```
your-app/
├── config/
│   ├── app.php          — namespace, pages config
│   ├── cache.php        — cache toggle settings
│   ├── formats.php      — response format registry
│   ├── log.php          — logging handlers and channels
│   ├── middleware.php    — global HTTP middleware
│   └── routes.php       — custom routes, page format overrides
├── files/
│   ├── cache/
│   │   ├── config.php        — serialized configuration
│   │   ├── pages.php         — page discovery cache
│   │   ├── route_middleware.php — middleware discovery cache
│   │   └── templates/        — compiled template cache
│   └── logs/
│       └── app.log           — default log file
└── public/
    └── index.php             — entry point
```

Directories are configurable via the `HyperKernel` constructor:

```php
$kernel = new Kernel(
    rootDirectory: '/var/www/app',
    configDirectory: 'config',    // relative to root (default: 'config')
    filesDirectory: 'files',      // relative to root (default: 'files')
);
```

## The interfaces

- **Kernel** — `bootstrap()`, `rootDirectory()`, `configDirectory()`, `filesDirectory()`, `requiredEnvironmentVariables()`
- **Bootstrapper** — `bootstrap(Application $container): void`
- **Terminable** — `terminate(): void` — cleanup hook called after the response is sent

## At a glance

```
HyperKernel (implements Kernel, RequestHandlerInterface)
|-- bootstrap()       — run bootstrappers once
|-- handle()          — prepare → middleware → dispatch → error handling
|-- middleware()      — register global HTTP middleware
|-- prepareRequest()  — JSON parsing, validation
\-- handleRequest()   — abstract, implemented by your app

Bootstrappers (run in order):
|-- Environment     — .env loading + validation
|-- Configuration   — config file scanning + caching
|-- Routing         — router, formats, pages, hydrator
|-- RouteMiddleware — per-route middleware discovery
|-- Logger          — Monolog channels + handlers
|-- Exceptions      — PHP error/exception/shutdown hooks
\-- Middleware       — global HTTP middleware registration

ConfigurationCache — cache/load/clear serialized config
RouteDispatcher    — per-route middleware + bus composition
```
