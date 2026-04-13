# Hyper

Hyper is Arcanum's HTTP layer. It implements PSR-7 (HTTP messages), PSR-15 (middleware), and owns the rendering pipeline that turns handler results into HTTP responses. If Atlas decides *which* handler to call and Shodo decides *how* to format the output, Hyper is the bridge that turns raw PHP superglobals into typed request objects on the way in and typed response objects into bytes on the wire on the way out.

## You probably won't import this

In most Arcanum apps, your handlers never touch Hyper directly. You receive a typed DTO, return data, and the rendering pipeline produces the HTTP response for you:

```php
// app/Domain/Shop/Query/ProductsHandler.php

final class ProductsHandler
{
    public function __invoke(Products $query): array
    {
        // Just return data. Hyper + Shodo turn it into an HTTP response.
        return $this->products->findAll();
    }
}
```

Hyper matters when you're writing middleware, building a custom response renderer, hooking into lifecycle events, or debugging the request pipeline. If you're building a standard Arcanum app and things are working, you may never need this page. But when you need to understand *how* the request gets from the browser to your handler and back, this is the place.

## The request journey

Here's what happens from the moment PHP receives a request to the moment the response reaches the client:

```
$_SERVER, $_GET, $_POST, $_FILES, $_COOKIE
  → Server (parses superglobals via PHPServerAdapter)
    → ServerRequest (immutable PSR-7 object)
      → HyperKernel
        → RequestReceived event (mutable — listeners can enrich the request)
          → HttpMiddleware (PSR-15 onion: session, auth, CSRF, app middleware...)
            → RouteDispatcher → Atlas Router → handler
          → ResponseRenderer (Shodo formatter → Response)
        → RequestHandled event (read-only)
      → Server::composeResponse() (HEAD stripping, charset, cache headers)
    → Server::send() (headers + body to client, fastcgi_finish_request)
  → HyperKernel::terminate()
    → ResponseSent event (deferred work, metrics, cleanup)
```

Two classes anchor the beginning of this journey:

- **`Server`** is what `public/index.php` actually uses. It calls its `ServerAdapter` to parse superglobals into a `ServerRequest`, and later sends the `Response` back to the client.
- **`PHPServerAdapter`** wraps PHP's native functions (`getallheaders()`, `header()`, `fastcgi_finish_request()`, etc.) behind an interface. The adapter exists so the entire send path is testable — swap it for a mock and nothing touches real output.

## PSR-7 messages

Hyper implements the full PSR-7 specification. If you've worked with PSR-7 before, everything works the way you'd expect. If you haven't, the key idea is **immutability** — every `with*()` method returns a *new* object, leaving the original unchanged.

### Message

`Message` is the base that both requests and responses share. It holds headers, a body stream, and a protocol version.

```php
use Arcanum\Hyper\Message;
use Arcanum\Hyper\Headers;
use Arcanum\Hyper\Version;
use Arcanum\Flow\River\Stream;

$message = new Message(
    new Headers(['Content-Type' => ['application/json']]),
    new Stream(LazyResource::for('php://memory', 'w+')),
    Version::v11,
);

// Reading headers (case-insensitive)
$message->getHeaderLine('content-type'); // 'application/json'
$message->hasHeader('Content-Type');     // true
$message->getHeaders();                  // ['Content-Type' => ['application/json']]

// Headers are immutable — with*() returns a new Message
$withAuth = $message->withHeader('Authorization', 'Bearer token123');
```

### Headers

`Headers` extends `IgnoreCaseRegistry` from the [Gather](../Gather/README.md) package. Header names are case-insensitive per HTTP spec — `Content-Type`, `content-type`, and `CONTENT-TYPE` all refer to the same header. Header names and values are validated against RFC 7230.

### Request and ServerRequest

`Request` is the basic HTTP request (method, URI, headers, body). `ServerRequest` wraps a `Request` and adds server-specific data — the things that come from PHP's superglobals:

```php
// In middleware or a listener — you receive a ServerRequestInterface
$request->getMethod();                      // 'POST'
$request->getUri()->getPath();              // '/shop/products'
$request->getHeaderLine('Accept');          // 'text/html'
$request->getQueryParams();                 // ['page' => '2']
$request->getParsedBody();                  // ['name' => 'Widget']
$request->getCookieParams();                // ['session_id' => 'abc123']
$request->getServerParams();                // $_SERVER contents
$request->getAttribute('auth.identity');    // set by middleware

// Immutable — returns new instance
$enriched = $request->withAttribute('start_time', microtime(true));
```

You'll almost never construct these yourself — `Server::request()` builds them from the current PHP request. You interact with them in middleware or lifecycle event listeners.

### Response

`Response` wraps a `Message` with a status code and reason phrase:

```php
use Arcanum\Hyper\Response;
use Arcanum\Hyper\StatusCode;

// You rarely build responses by hand — renderers do this.
// But if you're writing middleware that short-circuits:
$response = new Response(
    new Message(
        new Headers(['Content-Type' => ['application/json']]),
        $bodyStream,
        Version::v11,
    ),
    StatusCode::OK,
);

$response->getStatusCode();    // 200
$response->getReasonPhrase();  // 'OK'

$notFound = $response->withStatus(StatusCode::NotFound->value);
```

## URI

The `URI` class implements PSR-7's `UriInterface`. Parse a URI string and access or modify any component:

```php
use Arcanum\Hyper\URI\URI;

$uri = new URI('https://example.com:8080/shop/products?page=2#results');

$uri->getScheme();    // 'https'
$uri->getHost();      // 'example.com'
$uri->getPort();      // 8080
$uri->getPath();      // '/shop/products'
$uri->getQuery();     // 'page=2'
$uri->getFragment();  // 'results'

// Immutable — with*() returns a new URI
$api = $uri->withScheme('http')->withPort(3000)->withPath('/api/v1');
(string) $api; // 'http://example.com:3000/api/v1?page=2#results'
```

Under the hood, each component is a value object (`Scheme`, `Host`, `Port`, `Path`, `Query`, `Fragment`, `UserInfo`, `Authority`) with its own validation and RFC-compliant percent-encoding. You won't usually need these directly — `URI` exposes everything through its PSR-7 methods.

`Spec` is the static utility that parses URI strings and builds URIs from `$_SERVER` parameters. `Server::request()` uses it internally.

## Status codes

Arcanum uses a `StatusCode` enum instead of raw integers. Every HTTP status code is a named case:

```php
use Arcanum\Hyper\StatusCode;

StatusCode::OK;                  // 200
StatusCode::Created;             // 201
StatusCode::NoContent;           // 204
StatusCode::NotFound;            // 404
StatusCode::UnprocessableEntity; // 422
StatusCode::TooManyRequests;     // 429

$status = StatusCode::NotFound;
$status->value;                  // 404
$status->reason();               // Phrase::NotFound ('Not Found')
$status->isInformational();      // false
```

The enum removes magic numbers from your codebase and makes intent clear in renderers, exception handlers, and middleware. See the [COMPENDIUM](../../COMPENDIUM.md) for why Arcanum embraces the full status code spectrum.

## File uploads

`UploadedFile` implements PSR-7's `UploadedFileInterface`. In practice, files arrive on the `ServerRequest`:

```php
// In a handler or middleware
$files = $request->getUploadedFiles();
$avatar = $files['avatar']; // UploadedFileInterface

$avatar->getClientFilename(); // 'photo.jpg'
$avatar->getClientMediaType(); // 'image/jpeg'
$avatar->getSize();            // 245760
$avatar->getError();           // UPLOAD_ERR_OK (0)

// Move to permanent storage
$avatar->moveTo('/var/uploads/photo.jpg');
```

`UploadedFiles::fromSuperGlobal()` normalizes PHP's notoriously awkward `$_FILES` structure into a clean nested array of `UploadedFile` instances. The `Error` enum maps PHP's `UPLOAD_ERR_*` constants to named cases with an `isOK()` helper.

## Response renderers

Renderers turn handler return data into HTTP responses. Each one owns a format — the content type, the encoding, and the `Content-Type` header. All renderers extend `ResponseRenderer` and implement a single method:

```php
abstract public function render(
    mixed $data,
    string $dtoClass = '',
    StatusCode $status = StatusCode::OK,
): ResponseInterface;
```

### The six renderers

| Renderer | Content-Type | Template? | Notes |
|---|---|---|---|
| `JsonResponseRenderer` | `application/json` | No | Wraps `JsonFormatter`. Most API responses. |
| `HtmlResponseRenderer` | `text/html` | Yes | Resolves template via `TemplateResolver`, delegates to `HtmlFormatter`. |
| `PlainTextResponseRenderer` | `text/plain` | Yes | Same pattern as HTML, with `PlainTextFormatter`. |
| `MarkdownResponseRenderer` | `text/markdown` | Yes | Same pattern, with `MarkdownFormatter`. |
| `CsvResponseRenderer` | `text/csv` | No | Wraps `CsvFormatter`. Tabular data. |
| `EmptyResponseRenderer` | (none) | No | Empty body. Used for void commands (204), accepted (202). |

Template-based renderers (HTML, PlainText, Markdown) compose two things:

1. A **`TemplateResolver`** that maps the DTO class to a filesystem path — co-located templates discovered by PSR-4 convention (see [Shodo README](../Shodo/README.md)).
2. A **Shodo `Formatter`** that converts data into a string using the resolved template.

Status-specific template resolution works here too. When the status code isn't 200, the renderer tries `resolveForStatus()` first — so `Products.422.html` is found before `Products.html` when the status is 422.

### How format selection works

You don't choose a renderer — the URL extension does. A request for `/shop/products.json` gets the JSON renderer; `/shop/products.csv` gets CSV; `/shop/products` (no extension) defaults to HTML. This is wired through `FormatRegistry`.

`FormatRegistry` maps file extensions to `Format` definitions. Each `Format` holds an extension, a content type, and the renderer class to resolve from the container:

```php
// This is what Bootstrap\Routing registers (simplified)
$registry->register(new Format('html', 'text/html', HtmlResponseRenderer::class));
$registry->register(new Format('json', 'application/json', JsonResponseRenderer::class));
$registry->register(new Format('csv', 'text/csv', CsvResponseRenderer::class));
```

When a request arrives, Atlas extracts the extension from the URL path. `FormatRegistry::renderer()` resolves the matching `ResponseRenderer` from the container. If the extension isn't registered, a 406 Not Acceptable is thrown — this is how Arcanum enforces format support.

DTOs can restrict their allowed formats with `#[AllowedFormats('json', 'csv')]`. The router checks this before dispatch and throws 406 for anything not in the list.

## Exception renderers

When something goes wrong, exception renderers turn `Throwable`s into proper HTTP error responses. There are two primary renderers — one for each content type a browser or API client might expect.

### JsonExceptionResponseRenderer

Produces structured JSON error responses:

```json
{
    "error": {
        "status": 404,
        "message": "The page you're looking for doesn't exist."
    }
}
```

In debug mode, the response includes `exception`, `file`, `line`, and `trace`. When `verboseErrors` is enabled, `ArcanumException` instances include their `title` and `suggestion` fields.

### HtmlExceptionResponseRenderer

Uses the same `TemplateEngine` as the success rendering path. Resolution order:

1. **Co-located template** — `AddEntry.422.html` next to your DTO (when the route was resolved before the error)
2. **App-wide template** — `app/Templates/errors/422.html`
3. **htmx fragment** — if the request has an `HX-Request` header and no app template exists, renders a minimal `<p>` or `<ul>` fragment (no layout, no styles) that htmx can swap into the existing page
4. **Built-in fallback** — a self-contained styled error page that renders even when the template engine is broken

Error templates receive these variables: `$code`, `$title`, `$message`, `$errors` (validation field errors), `$suggestion` (from `ArcanumException`). In debug mode: `$exception`, `$file`, `$line`, `$trace`.

### ValidationExceptionRenderer

A decorator that intercepts `ValidationException` and returns a clean 422 JSON response with field-level errors:

```json
{
    "errors": {
        "name": ["The name field is required."],
        "email": ["The email field must be a valid email address."]
    }
}
```

All other exceptions pass through to the inner renderer. This is the default for JSON error responses — it wraps `JsonExceptionResponseRenderer` so validation errors get their own shape while everything else gets the standard error envelope.

## Middleware

Hyper's middleware follows the PSR-15 onion model. `HttpMiddleware` chains middleware around a core request handler:

```php
use Arcanum\Hyper\HttpMiddleware;

$stack = new HttpMiddleware($coreHandler, $container);
$stack->add(SessionMiddleware::class);  // outermost — runs first in, last out
$stack->add(AuthMiddleware::class);
$stack->add(CsrfMiddleware::class);     // innermost — runs last in, first out

$response = $stack->handle($request);
```

Middleware executes in the order added. The first middleware added is the outermost layer — it sees the raw request first and the final response last:

```
SessionMiddleware (in) →
  AuthMiddleware (in) →
    CsrfMiddleware (in) →
      handler
    CsrfMiddleware (out) ←
  AuthMiddleware (out) ←
SessionMiddleware (out) ←
```

Class-string middleware is resolved from the [Cabinet](../Cabinet/README.md) container at dispatch time, so middleware can have constructor dependencies injected. Middleware can short-circuit by returning a response without calling `$handler->handle()`.

`CallableHandler` wraps a closure as a PSR-15 `RequestHandlerInterface` — useful when you need a quick handler without a class:

```php
use Arcanum\Hyper\CallableHandler;

$handler = new CallableHandler(
    fn(ServerRequestInterface $request) => new Response(/* ... */)
);
```

### Options middleware

The built-in `Options` middleware auto-handles HTTP OPTIONS requests. It asks the [Atlas](../Atlas/README.md) router which methods are available for the path and returns a 204 No Content with the `Allow` header:

```
OPTIONS /shop/products HTTP/1.1

HTTP/1.1 204 No Content
Allow: GET, POST, OPTIONS
```

It's registered as the innermost middleware layer by the framework, so app middleware (CORS headers, for example) can add headers on the way out. Returns 404 for paths that don't resolve to any route.

## Lifecycle events

Hyper dispatches four events during the request lifecycle via [Echo](../Echo/README.md). You can listen for these to add cross-cutting behavior without touching middleware.

| Event | When | Mutable? |
|---|---|---|
| `RequestReceived` | Request enters the kernel, before middleware | Yes — `setRequest()` lets listeners enrich the request |
| `RequestHandled` | Middleware + handler produced a response | No — observe only (logging, metrics) |
| `RequestFailed` | An exception was thrown during handling | No — [Glitch](../Glitch/README.md) still handles rendering |
| `ResponseSent` | Response sent to client, during `terminate()` | No — runs after `fastcgi_finish_request()` |

Example listener:

```php
use Arcanum\Hyper\Event\RequestReceived;

class AddRequestTiming
{
    public function __invoke(RequestReceived $event): void
    {
        $event->setRequest(
            $event->getRequest()->withAttribute('start_time', microtime(true))
        );
    }
}
```

`RequestReceived` is the only mutable event — it exists specifically so listeners can add request attributes that flow through the entire middleware and handler chain. The other three are read-only observation points.

`ResponseSent` fires during `HyperKernel::terminate()`, after `fastcgi_finish_request()` has already flushed the response to the client. This is the place for deferred work — logging, metrics, cleanup — that shouldn't slow down the response.

## Server and ServerAdapter

`Server` is the entry point for every HTTP request. It does two things:

1. **Builds the request.** `Server::request()` reads PHP's superglobals (`$_SERVER`, `$_GET`, `$_POST`, `$_FILES`, `$_COOKIE`) through its `ServerAdapter` and assembles them into an immutable `ServerRequest`.

2. **Sends the response.** `Server::send()` emits headers, writes the body, and calls `fastcgi_finish_request()` (or `litespeed_finish_request()`, or flushes output buffers) to hand the response to the web server.

Between those two steps, `Server::composeResponse()` normalizes the response: stripping the body from HEAD requests, removing `Content-Type` from 1xx responses, appending `charset` to text content types, and adding `Pragma`/`Expires` for HTTP/1.0 cache control.

`PHPServerAdapter` is the production implementation — it calls PHP's native functions directly. The `ServerAdapter` interface exists so you can swap in a test double that captures output instead of writing to the real response stream.

## `#[HttpOnly]` attribute

Marks a DTO as HTTP-only. When the `TransportGuard` middleware is active, CLI dispatch of a DTO with this attribute is rejected with a clear error:

```php
use Arcanum\Hyper\Attribute\HttpOnly;

#[HttpOnly]
final class UploadAvatar
{
    public function __construct(
        public readonly string $userId,
    ) {}
}
```

The CLI counterpart is `#[CliOnly]` from the [Rune](../Rune/README.md) package.

## At a glance

```
Server
├── request() → ServerRequest (via PHPServerAdapter + superglobals)
├── composeResponse() → normalize before sending
└── send() → headers + body + fastcgi_finish_request

Message (headers, body, protocol version)
├── Request (method, URI) → ServerRequest (server params, cookies, query, body, files)
└── Response (status code, reason phrase, charset)

Headers (case-insensitive, RFC 7230 validated, extends IgnoreCaseRegistry)

URI (PSR-7 UriInterface)
├── Scheme, Host, Port, Path, Query, Fragment, UserInfo, Authority
└── Spec (parsing, fromServerParams)

StatusCode enum → Phrase enum (reason phrases)
RequestMethod enum (GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS, TRACE, CONNECT)
Version enum (0.9, 1.0, 1.1, 2.0)

ResponseRenderer (abstract)
├── JsonResponseRenderer (JsonFormatter)
├── HtmlResponseRenderer (HtmlFormatter + TemplateResolver)
├── PlainTextResponseRenderer (PlainTextFormatter + TemplateResolver)
├── MarkdownResponseRenderer (MarkdownFormatter + TemplateResolver)
├── CsvResponseRenderer (CsvFormatter)
└── EmptyResponseRenderer (204, 202)

ExceptionRenderer (interface)
├── JsonExceptionResponseRenderer (debug/verbose modes)
├── HtmlExceptionResponseRenderer (app templates → htmx fragment → built-in fallback)
└── ValidationExceptionRenderer (decorator, 422 field errors)

FormatRegistry (extension → Format → renderer)

HttpMiddleware (PSR-15 onion via Flow Pipeline)
├── Middleware\Options (auto OPTIONS + Allow header)
└── CallableHandler (closure → RequestHandlerInterface)

MiddlewareStage (adapts PSR-15 middleware to Pipeline Stage)

Events
├── RequestReceived (mutable)
├── RequestHandled (read-only)
├── RequestFailed (read-only)
└── ResponseSent (read-only, post-response)

Files
├── UploadedFile (PSR-7 UploadedFileInterface)
├── UploadedFiles (normalizes $_FILES)
├── Normalizer (spec → UploadedFile)
├── Error enum (UPLOAD_ERR_* constants)
└── InvalidFile (exception)

Attribute\HttpOnly (#[HttpOnly] — reject from CLI)
```
