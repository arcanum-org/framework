# Arcanum Atlas

Atlas is Arcanum's convention-based CQRS router. It maps both HTTP requests and CLI commands to Query and Command handlers by converting path segments to PascalCase namespaces — no route files, no annotations, no configuration for the common case.

The same `ConventionResolver` drives both transports. HTTP and CLI routers just differ in how they determine intent:

- **HTTP**: the request method decides — GET → Query, PUT/POST/DELETE → Command
- **CLI**: an explicit prefix decides — `query:` → Query, `command:` → Command

## Convention routing

Path segments map directly to PHP namespaces under a configurable root (default `App\Domain`). The last segment becomes the class name; preceding segments become namespace levels.

**HTTP examples:**
```
GET    /shop/products.json      → App\Domain\Shop\Query\Products
PUT    /checkout/submit-payment  → App\Domain\Checkout\Command\SubmitPayment
POST   /checkout/submit-payment  → App\Domain\Checkout\Command\SubmitPayment (PostSubmitPaymentHandler)
DELETE /checkout/submit-payment  → App\Domain\Checkout\Command\SubmitPayment (DeleteSubmitPaymentHandler)
```

**CLI examples:**
```
query:shop:products              → App\Domain\Shop\Query\Products
command:checkout:submit-payment  → App\Domain\Checkout\Command\SubmitPayment
```

Kebab-case segments are converted to PascalCase automatically: `submit-payment` → `SubmitPayment`.

### HTTP method mapping

| Method  | Type    | Namespace    | Handler prefix | Default response |
|---------|---------|-------------|----------------|------------------|
| GET     | Query   | `Query\`    | (none)         | 200 + rendered body |
| PUT     | Command | `Command\`  | (none)         | void→204, DTO→201, null→202 |
| POST    | Command | `Command\`  | `Post`         | void→204, DTO→201, null→202 |
| PATCH   | Command | `Command\`  | `Patch`        | void→204, DTO→201, null→202 |
| DELETE  | Command | `Command\`  | `Delete`       | void→204, DTO→201, null→202 |

POST, PATCH, and DELETE look for a prefixed handler first (`PostSubmitPaymentHandler`, etc.) and fall back to the default handler if not found.

### Context-aware formats

The file extension in the URL determines the response format:

```
GET /shop/products.json  → JSON response
GET /shop/products.html  → HTML response
GET /shop/products.csv   → CSV response
GET /shop/products.txt   → plain text response
GET /shop/products       → default format (configured in formats.php)
```

The extension is stripped before route matching — all formats resolve to the same handler. Shodo's `FormatRegistry` selects the correct renderer.

## Shared convention system

Under the hood, both `HttpRouter` and `CliRouter` delegate to `ConventionResolver`. It has two entry points:

- `resolve(path, method, format)` — used by `HttpRouter`. The HTTP method determines the CQRS type (GET → Query, POST → Command, etc.).
- `resolveByType(path, typeNamespace, handlerPrefix, format)` — the transport-agnostic core. The caller provides the CQRS type directly. `CliRouter` uses this, passing `'Query'` or `'Command'` based on the `query:`/`command:` prefix.

This means convention routing is defined once and behaves identically on both transports. A DTO at `App\Domain\Shop\Query\Products` is reachable as both `GET /shop/products` and `query:shop:products`.

## CLI routing

`CliRouter` parses the `command:` or `query:` prefix from the first argument, converts the remaining colon-separated segments to a path, and delegates to `ConventionResolver::resolveByType()`:

```
command:contact:submit
  → prefix: command → typeNamespace: Command
  → path: contact/submit
  → App\Domain\Contact\Command\Submit
```

### CLI route map

For DTOs that don't follow the convention, register aliases in `config/routes.php` under the `cli` key:

```php
return [
    'cli' => [
        'deploy' => [
            'class' => App\Infrastructure\Command\Deploy::class,
            'type' => 'command',
        ],
    ],
];
```

Custom CLI routes are checked before convention routing, just like custom HTTP routes.

### CLI-specific flags

- **`--help`** — intercepted before dispatch; returns a help route instead
- **`--format=FORMAT`** — extracted and passed through to `Route::$format` (default: `cli`)

See the [Rune README](../Rune/README.md) for full CLI documentation.

## Reverse URL resolution

`UrlResolver` converts a DTO class name back into a URL path — the inverse of `ConventionResolver`. This powers template helpers like `{{ Route::url('App\\Domain\\Query\\Health') }}`.

### Convention reversal

The resolver strips the root namespace, removes the `Query`/`Command` type segment, and converts PascalCase to kebab-case:

```
App\Domain\Shop\Query\ProductsFeatured  → /shop/products-featured
App\Domain\Query\Health                  → /health
App\Domain\Contact\Command\Submit        → /contact/submit
App\Pages\Docs\GettingStarted           → /docs/getting-started
```

### Custom route lookup

When a `RouteMap` is provided, the resolver checks for custom routes first via a reverse index (DTO class → path). If the DTO has a custom route registered, that path is returned instead of the convention-derived path. Falls back to convention for unregistered DTOs.

### Constructor

```php
$resolver = new UrlResolver(
    rootNamespace: 'App\\Domain',
    routeMap: $routeMap,              // optional — for custom route reverse lookup
    pagesNamespace: 'App\\Pages',     // optional — for page class resolution
);

$resolver->resolve('App\\Domain\\Shop\\Query\\Products'); // → '/shop/products'
```

## Pages

Pages are template-driven routes that live outside the Domain namespace. A page exists because a **template** exists — no handler needed, no config needed.

### Creating a page

The minimum to create a page is one file:

```
app/Pages/About.html
```

That's it. `GET /about` serves the template through the full middleware stack.

### Page discovery

`PageDiscovery` scans `app/Pages/` for `.html` template files during bootstrap and registers them as GET-only routes:

```
app/Pages/Index.html                  → GET /
app/Pages/About.html                  → GET /about
app/Pages/Docs/GettingStarted.html    → GET /docs/getting-started
```

PascalCase filenames are converted to kebab-case URL paths. `Index.html` at the root maps to `/`.

### Pages with data

Optionally add a PHP DTO class to provide default template data:

```php
// app/Pages/About.php
final class About
{
    public function __construct(
        public readonly string $title = 'About Us',
        public readonly string $company = 'Arcanum',
        public readonly int $foundedYear = 2024,
    ) {}
}
```

```html
<!-- app/Pages/About.html -->
<h1>{{ $title }}</h1>
<p>{{ $company }} — est. {{ $foundedYear }}</p>
```

The DTO's constructor defaults populate the template. Query parameters override them — `GET /about?title=Hello` sets `$title` to "Hello" in the template.

### Pages vs Queries

| | Pages | Queries |
|---|---|---|
| **Handler** | Never (framework-provided `PageHandler`) | Required |
| **DTO** | Optional (provides default data) | Optional (dynamic DTO available) |
| **Template** | Required | Optional (fallback renderer) |
| **Namespace** | `App\Pages` | `App\Domain\...\Query` |
| **Use for** | Presentation, static content | Data retrieval, business logic |

If a page needs logic — database calls, external APIs, computations — it should be a Query with a handler.

### Page format overrides

Pages default to `html` format. Override per-page in `config/routes.php`:

```php
return [
    'pages' => [
        '/' => 'json',  // root page serves JSON instead of HTML
    ],
];
```

## Route middleware

Arcanum supports per-route middleware at two layers — **HTTP** (PSR-15, can short-circuit) and **Conveyor** (Progression, runs around the handler). Middleware is co-located with the code it protects, not hidden in config files.

### Attributes on DTOs

Declare middleware directly on Command, Query, or Page DTO classes using PHP 8 attributes:

```php
use Arcanum\Atlas\Attribute\HttpMiddleware;
use Arcanum\Atlas\Attribute\Before;
use Arcanum\Atlas\Attribute\After;

#[HttpMiddleware(RequireAuth::class)]
#[HttpMiddleware(RateLimit::class)]
#[Before(ValidateInput::class)]
#[After(AuditLog::class)]
final class PlaceOrder
{
    public function __construct(
        public readonly string $item,
        public readonly int $qty,
    ) {}
}
```

| Attribute | Layer | Purpose |
|-----------|-------|---------|
| `#[HttpMiddleware]` | HTTP (PSR-15) | Wraps the request handler. Can short-circuit (return 401, 429, etc.) before the handler runs. |
| `#[Before]` | Conveyor (Progression) | Runs before the handler. Receives the DTO payload. Use for validation, sanitization, enrichment. |
| `#[After]` | Conveyor (Progression) | Runs after the handler. Receives the result. Use for response transformation, audit logging. |

All three attributes are repeatable — stack as many as needed. They accept a single class-string argument.

### Format restriction

Use `#[AllowedFormats]` to restrict which response formats a DTO accepts. Requesting a disallowed format returns **406 Not Acceptable**. DTOs without the attribute accept all registered formats (backwards compatible).

```php
use Arcanum\Atlas\Attribute\AllowedFormats;

#[AllowedFormats('json', 'html')]
final class Products
{
    public function __construct(
        public readonly string $category = '',
    ) {}
}
```

```
GET /shop/products.json  → 200 OK
GET /shop/products.html  → 200 OK
GET /shop/products.csv   → 406 Not Acceptable
```

Works on Queries, Commands, and Pages. Format strings are case-insensitive (`'JSON'` and `'json'` are equivalent). The check runs in the router before hydration or handler dispatch.

### Co-located Middleware.php files

Place a `Middleware.php` file in any domain directory to apply middleware to **all** handlers beneath it:

```
app/Domain/Admin/
├── Command/
│   ├── BanUser.php
│   └── BanUserHandler.php
├── Query/
│   └── AuditLog.php
└── Middleware.php          ← applies to everything in Admin/
```

The file returns an array with middleware class-strings for each layer:

```php
<?php

declare(strict_types=1);

// app/Domain/Admin/Middleware.php
return [
    'http' => [
        \App\Http\Middleware\RequireAdmin::class,
    ],
    'before' => [
        \App\Domain\Admin\Middleware\AdminAuditBefore::class,
    ],
    'after' => [
        \App\Domain\Admin\Middleware\AdminAuditAfter::class,
    ],
];
```

All three keys are optional. A file that only declares `http` middleware is valid.

`Middleware.php` files apply to all DTOs in the directory and all subdirectories. Multiple levels are supported — middleware at shallower directories wraps middleware at deeper directories.

### Execution order

Directory middleware is **outer** (runs first on the way in, last on the way out). Attribute middleware is **inner** (closest to the handler). Global middleware wraps everything.

For a request to `App\Domain\Admin\Command\BanUser` with a root `Middleware.php`, an `Admin/Middleware.php`, and attributes on `BanUser`:

```
 Request
 │
 ├─ 1.  Global HTTP middleware          ← config/middleware.php
 │  ├─ 2.  Root Middleware.php [http]    ← app/Domain/Middleware.php
 │  │  ├─ 3.  Admin Middleware.php [http]  ← app/Domain/Admin/Middleware.php
 │  │  │  ├─ 4.  #[HttpMiddleware] on DTO   ← BanUser.php attributes
 │  │  │  │
 │  │  │  │  ── Hydration ──
 │  │  │  │
 │  │  │  │  ├─ 5.  Root Middleware.php [before]
 │  │  │  │  │  ├─ 6.  Admin Middleware.php [before]
 │  │  │  │  │  │  ├─ 7.  #[Before] on DTO
 │  │  │  │  │  │  │  ├─ 8.  Global Conveyor before    ← MiddlewareBus.before()
 │  │  │  │  │  │  │  │
 │  │  │  │  │  │  │  │      ▼ Handler ▼
 │  │  │  │  │  │  │  │
 │  │  │  │  │  │  │  ├─ 9.  Global Conveyor after     ← MiddlewareBus.after()
 │  │  │  │  │  │  ├─ 10. #[After] on DTO
 │  │  │  │  │  ├─ 11. Admin Middleware.php [after]
 │  │  │  │  ├─ 12. Root Middleware.php [after]
 │  │  │  │
 │  │  │  ├─ 4.  #[HttpMiddleware] (response path)
 │  │  ├─ 3.  Admin Middleware.php [http] (response path)
 │  ├─ 2.  Root Middleware.php [http] (response path)
 ├─ 1.  Global HTTP middleware (response path)
 │
 Response
```

**Key rules:**
- Shallower directories wrap deeper directories (outermost first)
- Directory middleware wraps attribute middleware
- HTTP middleware wraps Conveyor middleware
- Global middleware wraps per-route middleware
- HTTP middleware can short-circuit (return early without calling the handler)
- Conveyor middleware (`Progression`) must call `$next()`

### Discovery and caching

`MiddlewareDiscovery` scans the app directory at bootstrap time, just like `PageDiscovery` scans for pages. Results are cached to avoid filesystem scanning on every request.

Cache configuration in `config/cache.php`:

```php
return [
    'route_middleware' => [
        'enabled' => true,  // set to false in development to always re-scan
    ],
];
```

### Middleware that applies to everything

If you want middleware on *every* handler in your app (not just global HTTP middleware), place a `Middleware.php` at the root of your app directory:

```
app/
├── Domain/
│   └── ...
├── Pages/
│   └── ...
└── Middleware.php          ← applies to all commands, queries, and pages
```

This is useful for Conveyor-level concerns (validation, audit logging) that should run for every dispatch but aren't HTTP-specific.

## Custom routes

Custom routes are explicit path → class mappings that bypass convention-based resolution. Use them for paths that don't fit the convention system:

```php
// config/routes.php
return [
    'custom' => [
        '/dashboard' => [
            'class' => 'App\\Domain\\Admin\\Query\\Dashboard',
            'methods' => ['GET'],
            'format' => 'html',
        ],
        '/legacy/endpoint' => [
            'class' => 'App\\Domain\\Compat\\Command\\LegacyEndpoint',
            'methods' => ['PUT', 'POST'],
        ],
    ],
];
```

Priority order: custom routes (including auto-discovered pages) > convention routing.

## Route resolution

`HttpRouter` resolves requests in this order:

1. **Custom routes** (including pages) — checked first via `RouteMap`
2. **Legacy pages** — checked via `PageResolver` (backwards compatibility)
3. **Convention routes** — `ConventionResolver` maps path + method to namespace

Each step produces a `Route` value object with `dtoClass`, `handlerPrefix`, `format`, and `isPage` flag.

### Error handling

Atlas uses precise error responses on both transports:

**HTTP:**
- **404 Not Found** — no route matches the path
- **405 Method Not Allowed** — path exists but HTTP method is wrong (includes `Allow` header)
- **406 Not Acceptable** — unsupported response format

**CLI:**
- `UnresolvableRoute` exception with a clear message (e.g., "No query found for ...")
- Exit code 2 (Invalid) for routing errors, exit code 1 for runtime errors

## Configuration

### `config/app.php`

```php
return [
    'namespace' => 'App\\Domain',       // Root namespace for convention routing
    'pages_namespace' => 'App\\Pages',  // Namespace for auto-discovered pages
    'pages_directory' => 'app/Pages',   // Directory to scan for page templates
];
```

### `config/routes.php`

```php
return [
    'custom' => [
        // Explicit path → class mappings
    ],
    'pages' => [
        // Path → format overrides for auto-discovered pages
        '/' => 'json',
    ],
];
```

## At a glance

```
ConventionResolver (shared core — transport-agnostic)
├── resolve(path, method, format)        ← called by HttpRouter
└── resolveByType(path, type, prefix)    ← called by CliRouter

HttpRouter
├── RouteMap (custom routes + auto-discovered pages)
├── PageResolver (legacy page support)
└── ConventionResolver

CliRouter
├── CliRouteMap (custom CLI aliases)
└── ConventionResolver

PageDiscovery
├── Scans app/Pages/ for *.html templates
├── Registers as GET-only routes with isPage: true
└── Caches discovery results

Route (shared value object)
├── dtoClass: string (fully-qualified class name)
├── handlerPrefix: string ('', 'Post', 'Patch', 'Delete')
├── format: string ('json', 'html', 'csv', 'txt', 'cli')
├── isPage: bool
└── isHelp: bool (CLI --help flag)

HTTP convention mapping:
  GET /shop/new-products.json
  → App\Domain\Shop\Query\NewProducts + NewProductsHandler

CLI convention mapping:
  query:shop:new-products
  → App\Domain\Shop\Query\NewProducts + NewProductsHandler
```
