# Arcanum Atlas

Atlas is the convention-based CQRS router. It maps HTTP requests to Query and Command handlers by converting URL path segments to PascalCase namespaces — no route files, no annotations, no configuration for the common case. The HTTP method determines intent: GET reads (Queries), PUT/POST/PATCH/DELETE writes (Commands).

## Convention routing

URL path segments map directly to PHP namespaces under a configurable root (default `App\Domain`). The last segment becomes the class name; preceding segments become namespace levels.

```
GET    /shop/products.json     → App\Domain\Shop\Query\Products + ProductsHandler
PUT    /checkout/submit-payment → App\Domain\Checkout\Command\SubmitPayment + SubmitPaymentHandler
POST   /checkout/submit-payment → App\Domain\Checkout\Command\SubmitPayment + PostSubmitPaymentHandler
DELETE /checkout/submit-payment → App\Domain\Checkout\Command\SubmitPayment + DeleteSubmitPaymentHandler
```

Kebab-case URL segments are converted to PascalCase automatically: `/submit-payment` → `SubmitPayment`.

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

Atlas uses precise HTTP status codes:

- **404 Not Found** — no route matches the path
- **405 Method Not Allowed** — path exists but HTTP method is wrong (includes `Allow` header)
- **406 Not Acceptable** — unsupported response format (from Shodo's `FormatRegistry`)

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
HttpRouter
|-- RouteMap (custom routes + auto-discovered pages)
|-- PageResolver (legacy page support)
\-- ConventionResolver (convention-based path → namespace)

PageDiscovery
|-- Scans app/Pages/ for *.html templates
|-- Registers as GET-only routes with isPage: true
\-- Caches discovery results

Route
|-- dtoClass: string (fully-qualified class name)
|-- handlerPrefix: string ('', 'Post', 'Patch', 'Delete')
|-- format: string ('json', 'html', 'csv', 'txt')
|-- isPage: bool

Convention mapping:
  GET /shop/new-products.json
  → path: /shop/new-products
  → format: json
  → namespace: App\Domain\Shop\Query\NewProducts
  → handler: NewProductsHandler
```
