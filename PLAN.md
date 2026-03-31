# Framework Completion Plan

This checklist tracks remaining work to complete all packages in the Arcanum framework. Each item is a small unit of work that includes 100% test coverage.

---

## Bug Fixes

- [x] Fix `Headers::cleanValues()` rejecting `'0'` as a header value — PHP's `empty('0')` returns true, so `Content-Length: 0` is rejected. Replace `empty()` with a strict check.
- [x] Fix `Response::withoutHeader()` mutating the original object — it should return an immutable copy per PSR-7. This also breaks `Server::sendSetCookieHeaders()`.
- [x] Fix `EmptyStream::getMetadata($key)` returning `null` for any key — now looks up the key in the metadata array, returns `null` only for absent keys
- [x] Fix `PrimitiveResolver` calling `implode(",", $type->getTypes())` on `ReflectionType` objects — now maps to `getName()` via `ReflectionNamedType` check
- [x] Fix `StandardProcessor` using loose `!$payload` check — changed to `$payload === null`
- [x] Fix `RegistryTest::assertGetSetViaArrayAccess()` method name — renamed to `testGetSetViaArrayAccess()` so PHPUnit runs it
- [x] Fix `ProviderRegistry` interface accepting only `Provider` while `Container::provider()` accepts `string|Provider` — updated interface to `string|Provider`
- [x] Fix typo `testSimnpleProvider` → `testSimpleProvider` in `SimpleProviderTest`
- [x] Fix typo `testSimnpleProvider` → `testPrototypeProvider` in `PrototypeProviderTest`

---

## Hyper

- [x] Add CRLF injection prevention in header values (reject or strip `\r\n` sequences)
- [x] Add request target validation per RFC 7230 (origin-form, absolute-form, authority-form, asterisk-form)
- [x] Investigate PHPServerAdapter testability — not unit-testable: every method is a 1:1 wrapper around SAPI-dependent PHP built-ins (`header()`, `getallheaders()`, `ob_*`, etc.). The `ServerAdapter` interface exists so `Server` can be tested via mocks. Class is correctly marked `@codeCoverageIgnore`.
- [x] Add tests for `Server::request()` cookie filtering — not unit-testable: `request()` directly accesses `$_COOKIE`, `$_GET`, `$_POST` superglobals. The filtering logic (is_string key/value check) is trivial. Refactoring to inject superglobals would add complexity for minimal value.
- [x] Add tests for `Server::sendSetCookieHeaders()` (blocked by the `withoutHeader` mutation bug — unblocked once that bug is fixed)
- [x] Fix `Port` constructor not storing type-converted value — changed `$value = (int)$value` to `$this->value = (int)$value` to update the promoted property
- [x] Fix `Port` validation error message — corrected to "between 0 and 65535" (matches actual validation, no null mention)
- [x] Fix `Request::getRequestTarget()` returning stale cached value after `withUri()` — `withUri()` now sets `$request->requestTarget = null` on the clone so it recalculates from the new URI
- [x] Add `ResponseTest` — 18 tests covering status code, reason phrase, charset, headers (get/set/add/remove), body, protocol version, and immutability

---

## Glitch

- [x] Add `JsonReporter` — formats exceptions as JSON (status code, message, stack trace in debug mode only)
- [x] Add tests for `Handler` class (error→exception conversion, shutdown handling, reporter dispatch)
- [x] Add tests for `LogReporter` (per-exception-type log levels and channel routing)
- [x] Add tests for `Level` enum (isDeprecation, isFatal helpers)
- [x] Add `HttpException` class (`src/Glitch/HttpException.php`) — exception that carries an HTTP status code explicitly (e.g., `throw new HttpException(StatusCode::NotFound, 'Order not found')`). Used by Shodo renderers and the kernel to produce proper HTTP error responses
- [x] Define `ExceptionRenderer` interface (`src/Glitch/ExceptionRenderer.php`) — takes a `Throwable`, returns `ResponseInterface`. This is the contract between Glitch (error handling) and Shodo (rendering). Lives in Glitch because it's part of the error-handling lifecycle — Shodo implements it
- [x] Remove `JsonReporter` (`src/Glitch/JsonReporter.php`) — replaced by Shodo's `JsonExceptionRenderer`. The echo-based approach conflated reporting (internal recording) with responding (client-facing output)
- [x] Integrate `ExceptionRenderer` into `HyperKernel` (`src/Ignition/HyperKernel.php`) — on exception, dispatch to `Handler` for internal reporting (LogReporter, etc.), then use the container-resolved `ExceptionRenderer` to build and return a `ResponseInterface`

---

## Ignition

- [x] Add configuration caching — cache parsed config arrays to avoid re-reading files on every request
- [x] Add environment validation — verify required env vars are set during bootstrap, fail fast with clear errors
- [ ] Add bootstrap lifecycle hooks — before/after events so the app can hook into the boot sequence
- [x] Add tests for `HyperKernel` (bootstrap sequence, directory accessors, terminate)
- [x] Add tests for `Bootstrap\Environment` (.env loading, missing .env graceful handling, required var validation, Environment factory registration)
- [x] Add tests for `Bootstrap\Configuration` (config file loading, file name as top-level key, empty directory, cache usage, cache skips file scanning)
- [x] Add tests for `Bootstrap\Logger` (QuillLogger/ChannelLogger registration, StreamHandler with defaults/custom path, ErrorLogHandler, SyslogHandler, multi-channel, multi-handler channels)
- [x] Add tests for `Bootstrap\Exceptions` (memory reservation, error_reporting, display_errors per environment, error/exception/shutdown handler delegation, memory freed on exception/shutdown, error_log fallback)

---

## Cabinet

- [ ] Add test for `Container::specify()` with array `when` parameter — only the string form is tested, the `string|array` union's array path has no coverage
- [ ] Add test for `Container` default constructor — no test creates a Container with `null` parameters to verify default `Resolver::forContainer`, `ContinuationCollection`, and `PipelayerSystem` initialization

---

## Codex

- [x] Fix nullable parameter resolution — `resolveClass()` return type changed from `object|array` to `object|array|null` so nullable parameters (e.g., `LoggerInterface|null $logger = null`) fall back to `null` when the dependency can't be resolved, instead of throwing a TypeError
- [x] Add tests for nullable parameter falling back to null when dependency is unresolvable
- [ ] Add test for `Resolver::resolve()` with callable that returns non-object — currently only valid closures are tested, no test for callable returning a primitive or null
- [ ] Add test for `Resolver::resolveWith()` with variadic constructor parameters — `resolveWith()` doesn't handle variadics like `resolveParameters()` does

---

## Echo

- [ ] Add test for listener that throws a non-`Interrupted` exception during dispatch — verify it propagates to the caller
- [ ] Add test for `Interrupted` exception during dispatch — verify the event is returned and propagation stops gracefully
- [ ] Add test for event mutation propagation through listener chain — verify listener 2 sees modifications made by listener 1

---

## Flow

### Conveyor (Command Bus)

- [x] Add `prefix` parameter to `Bus::dispatch()` and `MiddlewareBus::dispatch()` — defaults to `''`, prepended to the short class name (e.g., prefix `Delete` + `Namespace\SubmitPayment` → `Namespace\DeleteSubmitPaymentHandler`)
- [x] Add fallback logic to handler resolution — if the prefixed handler class does not exist in the container, fall back to the unprefixed handler
- [x] Add debug-mode warning log on fallback — when a prefixed handler is not found and Conveyor falls back, log a warning via optional `LoggerInterface`. Silent when `debug: false` or no logger provided.
- [x] Add tests for dispatch with empty prefix (default behavior, no change from current)
- [x] Add tests for dispatch with prefix resolving to a prefixed handler
- [x] Add tests for dispatch with prefix falling back to unprefixed handler
- [x] Add tests for debug-mode warning log on fallback
- [x] Add tests for dispatch with prefix where neither prefixed nor unprefixed handler exists — verify exception
- [x] Add `HandlerProxy` interface — allows DTOs to override handler resolution. `handlerNameFor()` checks this before `get_class()`.
- [x] Add `Command` dynamic DTO — implements `HandlerProxy`, provides `get()`/`has()`/`toArray()` and `__get`/`__isset` for dynamic property access. Used when a command handler exists without a paired DTO class.
- [x] Add `Query` dynamic DTO — same as `Command` but for query handlers.
- [x] Add handler-only route support in `HttpRouter` — when the DTO class doesn't exist but the Handler class does, the route resolves and the kernel creates a dynamic `Command` or `Query` from request data.

### River (Streams)

- [x] Add test for `EmptyStream::getMetadata($key)` — covered by the bug fix, tests verify correct values for valid keys and null for absent keys
- [ ] Add test for `Stream::read(0)` edge case — verify reading zero bytes returns empty string
- [ ] Add test for `CachingStream::seek()` with `SEEK_END` on an unseekable remote stream
- [x] Fix `CachingStream` not caching `php://input` — `getContents()` was only reading from the local cache without first pulling uncached data from the remote stream. Fixed to drain remote into local before returning. `__toString()` now rewinds before reading. Removed `$_SERVER['RAW_BODY']` workaround from the starter app.

### General

- [ ] Add test for `MiddlewareBus::dispatch()` when handler class is not found in container — verify exception behavior

---

## Gather

- [ ] Add tests for `Configuration::asAlpha()`, `asAlnum()`, `asDigits()` with dot-notation keys
- [ ] Add tests for `IgnoreCaseRegistry::asAlpha()`, `asAlnum()`, `asDigits()` — verify case-insensitive coercion
- [ ] Add tests for `Environment` inherited methods (`get()`, `has()`, `set()`, `count()`) — verify they work alongside the security overrides
- [ ] Add test for `Configuration::set()` where an intermediate path value is a scalar, not an array — verify scalar is overwritten with nested structure

---

## Quill

- [x] Review PSR-3 compliance and confirm complete — Logger and Channel both implement LoggerInterface correctly. All 8 severity methods, `log()`, context forwarding, and Stringable messages work. No gaps found.
- [x] Add tests for `Logger` (multi-channel routing, default channel fallback, context forwarding, Stringable messages, non-existent channel exception) and `Channel` (name property, context forwarding, Stringable messages)
- [x] Fix invalid `#[UsesClass(\Monolog\Logger::class)]` annotation in `LoggerTest` — removed; third-party classes don't belong in coverage metadata

---

## Parchment

Parchment is the filesystem abstraction layer. It delegates to Symfony Finder and Symfony Filesystem under the hood rather than reimplementing their functionality.

- [x] Add `Reader` — read file contents (string, lines, JSON decode). Uses PHP file functions for reads
- [x] Add `Writer` — write file contents (string, JSON encode, append). Delegates to Symfony Filesystem's `dumpFile` for atomic writes
- [x] Add `FileSystem` — copy, move, delete files and directories. Delegates to Symfony Filesystem
- [x] ~~Add `PathHelper`~~ — removed; Symfony's `Path` class is already a clean static API. Use `Symfony\Component\Filesystem\Path` directly
- [x] Add `TempFile` — create and auto-clean temporary files. Uses Symfony Filesystem's `tempnam`
- [x] ~~Add `AtomicWriter`~~ — removed; `Writer::write()` already delegates to Symfony's `dumpFile()` which is atomic (temp file + rename)
- [x] Migrate `ConfigurationCache` to use Parchment — replace raw `file_put_contents`, `is_file`, `unlink`, `mkdir` calls with Parchment's `Writer`, `FileSystem`, and `Reader`

---

## New Package: Atlas

Atlas is the routing package. It maps inputs to Query and Command handlers using convention-based discovery, so users don't need to define routes manually. The core mapping — path segments to PascalCase namespaces, Query vs Command split — is transport-agnostic. HTTP is the first input source; CLI routing is a future input source that will reuse the same convention system. Atlas enforces an opinionated CQRS split: reads are always Queries, writes are always Commands.

### Convention System

URL path segments map directly to PHP namespaces under a configurable root (default `App`). Kebab-case URL segments are converted to PascalCase class names. The last segment becomes the class name; all preceding segments become namespace levels. The HTTP method determines whether the `Query\` or `Command\` namespace is inserted.

```
GET    /catalog/products/featured.json  → App\Catalog\Query\Products\Featured + FeaturedHandler
PUT    /checkout/submit-payment         → App\Checkout\Command\SubmitPayment  + SubmitPaymentHandler
POST   /checkout/submit-payment         → App\Checkout\Command\SubmitPayment  + PostSubmitPaymentHandler (or fallback to SubmitPaymentHandler)
DELETE /checkout/submit-payment         → App\Checkout\Command\SubmitPayment  + DeleteSubmitPaymentHandler (or fallback to SubmitPaymentHandler)
PATCH  /checkout/submit-payment         → App\Checkout\Command\SubmitPayment  + PatchSubmitPaymentHandler (or fallback to SubmitPaymentHandler)
```

**HTTP method constraints:**

| Method  | Type    | DTO namespace | Handler prefix | Default status                              |
|---------|---------|---------------|----------------|---------------------------------------------|
| GET     | Query   | `Query\`      | (none)         | 200 + rendered body                         |
| PUT     | Command | `Command\`    | (none/`Put`)   | void→204, scalar/DTO→201, null→202          |
| POST    | Command | `Command\`    | `Post`         | void→204, scalar/DTO→201, null→202          |
| PATCH   | Command | `Command\`    | `Patch`        | void→204, scalar/DTO→201, null→202          |
| DELETE  | Command | `Command\`    | `Delete`       | void→204, scalar/DTO→201, null→202          |
| OPTIONS | —       | —             | —              | Handled by framework middleware, no handler |

**Command handler return type conventions:**
- `void` → 204 No Content (command completed, nothing to report)
- scalar or DTO → 201 Created (something was created, return a reference)
- `null` → 202 Accepted (command accepted, processing deferred)
- Command responses have no body by default. An opt-in configuration escape hatch allows response bodies for users who need them, with documentation guiding against forcing REST/MVC patterns into CQRS defaults.

**Method-specific handler resolution:** All mutating methods share the same Command DTO. PUT is the default — `SubmitPaymentHandler` handles PUT. POST, PATCH, and DELETE look for a prefixed handler first (`PostSubmitPaymentHandler`, etc.) and fall back to the default handler. Fallback resolution and the debug-mode warning log live in Conveyor, not the router. `PutSubmitPaymentHandler` is valid but redundant.

### Router Interface

- [x] Define `Router` interface — takes an `object` input source, returns a `Route`. Transport-agnostic — concrete implementations adapt specific input sources (e.g., `HttpRouter` adapts `ServerRequestInterface`)
- [x] Implement `HttpRouter` — adapts `ServerRequestInterface` for the `Router` interface. Extracts path and method from the request, parses file extension for format, delegates to `ConventionResolver`
- [x] Define `Route` value object — holds the DTO class name, handler prefix, and response format string. Immutable with `withFormat()`. `isQuery()` and `isCommand()` derived from the DTO namespace.
- [x] Add tests for `Route` value object

### Convention-Based Resolution

- [x] Implement URL-to-namespace mapping via `ConventionResolver` — convert kebab-case path segments to PascalCase (uses Toolkit's `Strings::pascal()`), last segment becomes class name, preceding segments become namespace levels
- [x] Add configurable root namespace — default `App`, set via constructor (follows Composer autoloader convention)
- [x] Add HTTP method → namespace insertion — GET inserts `Query\`, PUT/POST/PATCH/DELETE insert `Command\`
- [x] Add HTTP method → handler prefix mapping — POST→`Post`, PATCH→`Patch`, DELETE→`Delete`, PUT/GET→empty string
- [x] Add `UnresolvableRoute` exception — thrown when path resolves to empty segments (e.g., `/`)
- [x] Add tests for kebab-case to PascalCase conversion
- [x] Add tests for single-segment paths (e.g., `GET /dashboard` → `App\Query\Dashboard`)
- [x] Add tests for multi-segment paths (e.g., `GET /catalog/products/featured` → `App\Catalog\Query\Products\Featured`)
- [x] Add tests for GET → Query namespace insertion
- [x] Add tests for PUT → Command namespace insertion
- [x] Add tests for POST/PATCH/DELETE → Command namespace insertion with handler prefix
- [x] Add tests for configurable root namespace

### Pages (legacy — being replaced by Custom Routes)

The original page system used explicit registration in config. This is being replaced by auto-discovered pages built on the custom route system (see Custom Routes section above). The items below are the original implementation that will be refactored.

- [x] Add `PageResolver` with page registration — `register()` stores path-to-format mappings, `resolve()` maps path segments to PascalCase classes under the Pages namespace, `has()` checks registration
- [x] Add configurable Pages namespace — default `App\Pages`, set via constructor
- [x] Add configurable default format for Pages — default `html`, overridable per-page via `register($path, format:)` or globally via constructor
- [x] Add root path `/` convention — maps to `Index` class within the Pages namespace
- [x] Integrate `PageResolver` into `HttpRouter` — pages checked before convention routing, extension format overrides page default, falls back to convention for unregistered paths
- [x] Add tests for root path `/` → Pages Index resolution
- [x] Add tests for single-segment page (e.g., `/thing` → `App\Pages\Thing`)
- [x] Add tests for nested page (e.g., `/docs/getting-started` → `App\Pages\Docs\GettingStarted`)
- [x] Add tests for page default format (html) and per-page format override
- [x] Add tests for page with context-aware format extension (e.g., `/thing.json`)
- [x] Add tests for unregistered page path returning 404

### Custom Routes

Custom routes are explicit path → class mappings that bypass convention-based resolution. They are the general mechanism for routing paths that don't fit the convention system. Pages are a convenience layer built on top of custom routes.

Priority order: custom routes (including auto-discovered pages) > convention routing.

```
# Custom route examples
GET /this/is/custom       → App\Domain\Something\Different\Query\Custom
PUT /legacy/endpoint      → App\Domain\Compat\Command\LegacyEndpoint
GET /dashboard            → App\Domain\Admin\Query\Dashboard
```

#### Custom Route Registration

- [x] Add `RouteMap` to Atlas — stores explicit path+method(s) → Route mappings. Supports `register(path, dtoClass, methods, format)` and `resolve(path, method)`.
- [x] Integrate `RouteMap` into `HttpRouter` — checked before convention routing. Returns the matched Route directly, no convention resolution needed.
- [x] Add `allowedMethods()` support for custom routes — `HttpRouter::allowedMethods()` checks RouteMap before convention namespaces.
- [x] Add config-based custom route registration — `config/routes.php` gains a `custom` key with explicit path → class mappings, loaded by `Bootstrap\Routing`.
- [x] Add tests for custom route overriding convention
- [x] Add tests for custom route with multiple methods
- [x] Add tests for custom route 405 when path exists but method doesn't match
- [x] Add tests for custom route priority over convention

#### Pages (built on Custom Routes)

Pages live outside the Domain namespace (default `App\Pages`) since they are not convention-routed CQRS handlers. Pages are auto-discovered from the filesystem — creating a class *is* registering the route. The framework scans the pages directory during bootstrap, derives paths from class names via `Strings::kebab()`, and registers them as GET-only custom routes.

```
app/Pages/Index.php                  → GET /                   (class: App\Pages\Index)
app/Pages/Thing.php                  → GET /thing              (class: App\Pages\Thing)
app/Pages/Docs/GettingStarted.php   → GET /docs/getting-started (class: App\Pages\Docs\GettingStarted)
```

- [x] Refactor page registration to use `PageDiscovery` + `RouteMap` — `PageDiscovery` scans the pages directory and registers each page as a GET-only custom route in `RouteMap`. Replaces manual `PageResolver` registration.
- [x] Add page auto-discovery — scan the pages namespace directory during bootstrap, derive paths from class names via `Strings::kebab()`. Nested directories map to nested path segments. Handler classes are skipped.
- [x] Add page route caching — cache discovered page routes to avoid filesystem scanning on every request. Cache file written on first scan, loaded on subsequent requests.
- [x] Move Pages out of Domain in the starter app — pages live in `App\Pages` (outside Domain) to avoid namespace collision with convention-routed paths.
- [x] Add config overrides for auto-discovered pages — `config/routes.php` `pages` key overrides default format for auto-discovered pages.
- [x] Add tests for page auto-discovery from directory scanning
- [x] Add tests for page path derivation (PascalCase → kebab-case)
- [x] Add tests for nested page directory scanning
- [x] Add tests for page route caching (cache hit skips filesystem scan)
- [x] Add tests for config override of auto-discovered page format

### Route Middleware

- [ ] Add per-route middleware support — integrate with Flow's Continuum for route-specific middleware
- [ ] Add tests for per-route middleware execution

### Context-Aware Routing

The router strips file extensions from the URI path before matching, so `/shop/new-products.json`, `/shop/new-products.html`, and `/shop/new-products.csv` all resolve to the same Query handler. The extension is extracted and stored as the requested response format on the matched `Route`. After the handler returns data, the format determines which Shodo renderer produces the `ResponseInterface`.

- [x] Add extension parsing to route resolution — `HttpRouter::parseExtension()` strips `.json`, `.html`, `.csv`, etc. from the URI path before matching, stores the extracted format on the `Route`
- [x] Default to a configurable fallback format when no extension is present — `HttpRouter` constructor accepts `defaultFormat` (default `json`, overridable e.g., to `html` for Pages)
- [x] Add tests for extension stripping during route matching — verify the same handler is resolved regardless of extension
- [x] Add tests for format extraction — verify the parsed format is available on the `Route`
- [x] Add tests for missing extension — verify fallback format is applied
- [x] Add tests for unknown/unregistered extension — `UnsupportedFormat` exception (406 Not Acceptable) thrown by `FormatRegistry::get()` and `renderer()`

### OPTIONS Handling

- [x] Add OPTIONS middleware — `Hyper\Middleware\Options` intercepts OPTIONS requests, queries `HttpRouter::allowedMethods()` for the path, returns 204 with `Allow` header. Auto-registered as innermost framework middleware by `Bootstrap\Middleware`. CORS headers handled by app middleware (e.g., starter app's `Cors` middleware) on the way out.
- [x] Add tests for OPTIONS response listing allowed methods for a convention route
- [x] Add tests for OPTIONS CORS preflight headers

### Routing Bootstrapper

- [x] Add `Ignition\Bootstrap\Routing` bootstrapper — reads `config/routes.php` (namespace, pages_namespace, pages) and `config/formats.php` (default format, format definitions) to register ConventionResolver, PageResolver, HttpRouter, FormatRegistry, JsonRenderer, and Hydrator in the container. Throws if required namespace config is missing. Added to HyperKernel bootstrapper sequence after Configuration.
- [x] Add tests for bootstrapper loading page registrations from config
- [x] Add tests for bootstrapper throwing on missing namespace config
- [x] Add tests for bootstrapper loading format definitions from config
- [x] Add tests for bootstrapper registering all core services (Router, ConventionResolver, PageResolver, FormatRegistry, Hydrator, JsonRenderer)
- [x] Add tests for configurable root namespace via app.namespace
- [x] Add tests for configurable default format via formats.default

---

## CQRS Integration

These items bridge the gap between HTTP (Hyper) and command/query dispatch (Conveyor), completing the end-to-end CQRS request lifecycle.

### HTTP Middleware Pipeline

- [x] Add global HTTP middleware stack to `HyperKernel` — `HttpMiddleware` built on Flow's Pipeline (not Continuum, since PSR-15 middleware may short-circuit). `MiddlewareStage` adapts each `MiddlewareInterface` into a Pipeline Stage. `CallableHandler` bridges closures to `RequestHandlerInterface`.
- [x] Add configuration for global middleware registration — `Bootstrap\Middleware` reads `config/middleware.php` `global` key and registers middleware on the kernel
- [x] Add tests for global middleware execution order
- [x] Add tests for middleware short-circuiting (returning a response without hitting the router)

### Request → DTO Mapping

- [x] Implement `Codex\Hydrator` — constructs DTOs by matching associative array keys to constructor parameter names. Handles missing params via defaults, throws for required params with no data.
- [x] Add type coercion for scalar DTO properties — coerces string values to int (via `is_numeric`), float, bool (via `filter_var`), and string as needed by the constructor type hints
- [x] Add tests for query parameter injection into DTOs
- [x] Add tests for request body injection into DTOs — integration tests verify JSON body → Hydrator → Command DTO flow, including default values and full dispatch lifecycle
- [x] Add tests for type coercion (string → int, string → bool, etc.)
- [x] Add tests for missing required parameter, extra data ignored, default value fallback

### Query Response Serialization

Query handlers always return data. The response is rendered by the format-aware serializer (JSON, HTML, CSV, etc.) based on the extension extracted by context-aware routing. Status code is always 200. The `FormatRegistry` already handles renderer selection — `$formats->renderer($route->format)->render($result)` is the pattern used by the starter kernel. A separate response serializer interface is not needed.

- [x] ~~Define a response serializer interface~~ — not needed, `FormatRegistry::renderer()` already selects the right renderer based on the route's format
- [x] ~~Implement format-aware response serializer~~ — the kernel calls `FormatRegistry::renderer()` directly, no wrapper needed
- [x] Add integration tests for Query response with JSON format — verify the full flow from Route → FormatRegistry → JsonRenderer → ResponseInterface, with and without query params
- [ ] Add integration tests for Query response with HTML format (after HtmlRenderer is built)
- [ ] Add integration tests for Query response with CSV format (after CsvRenderer is built)

### Command Response Serialization

Command handlers signal intent through their return type. Commands have no response body by default — the status code communicates the outcome. An opt-in configuration escape hatch allows response bodies for users who need them, with documentation guiding against forcing REST/MVC patterns into CQRS defaults.

- [x] Implement `Shodo\EmptyResponseRenderer` — generic renderer that produces an empty-body response with a configurable status code (defaults to 204). Command-specific logic (which status code to use) lives in the app kernel, not the framework.
- [ ] Add `Location` header for 201 responses — set if the framework can resolve a URL from the returned identifier
- [ ] Add opt-in configuration for command response bodies — disabled by default, allows commands to return rendered content when enabled
- [ ] Add documentation guidance — explain CQRS command conventions and why response bodies are discouraged
- [x] Add tests for `void` handler (EmptyDTO) → 204 No Content with empty body
- [x] Add tests for DTO return → 201 Created with empty body
- [ ] Add tests for scalar return → 201 Created with empty body and Location header (pending Location header implementation)
- [ ] Add tests for `null` return → 202 Accepted with empty body (pending void/null distinction in Conveyor)
- [ ] Add tests for opt-in response body configuration

### Handler Discovery

- [ ] Add handler auto-discovery — scan a configured namespace/directory for handler classes and register them in the Container
- [ ] Add handler registration in bootstrap — wire discovered handlers into the Container so Conveyor can resolve them
- [ ] Add tests for handler discovery from a namespace
- [ ] Add tests for handler resolution through Container → Conveyor pipeline

---

## Starter Project

Track updates to the starter app (`../arcanum/`) as framework features land.

- [x] Update `bootstrap/http.php` to register `MiddlewareBus` (Conveyor) in the Container — including `ContainerInterface`, `$debug`, and `$logger` specifications
- [x] Update `bootstrap/http.php` to register the Router in the Container — `ConventionResolver`, `PageResolver` (with root `/` page), and `HttpRouter` factory
- [x] Update `bootstrap/http.php` to register `JsonRenderer` in the Container
- [x] Update `App\HTTP\Kernel::handleRequest()` to dispatch requests through the Router → Conveyor → JsonRenderer pipeline (MVP: Query-only, JSON-only)
- [x] Add `App\Pages\Index` and `App\Pages\IndexHandler` — default homepage at root `/`, returns array (no result class needed)
- [x] Add `App\Query\Health` and `App\Query\HealthHandler` — example convention-routed Query with `?verbose=true` param, returns array
- [x] Set up directory structure conventions — `app/Pages/`, `app/Query/` directories in the starter
- [x] Add `config/routes.php` — page registration moved from bootstrap to config file, bootstrap reads `$routes['pages']` and registers each path/format pair
- [x] Add `config/formats.php` — configure enabled response formats and any renderer overrides
- [x] Add example Command — `PUT /contact/submit` → `App\Contact\Command\Submit` + `SubmitHandler`, demonstrates Command with DTO hydration from JSON body, void return→204
- [x] Add `config/middleware.php` with a `global` key and an example middleware (e.g., CORS headers) to demonstrate `HttpMiddleware` integration
- [x] Separate domain code from application infrastructure — moved Commands, Queries into `app/Domain/`, convention resolver root changed to `App\Domain`
- [x] Move Pages out of Domain — pages live in `App\Pages` (outside Domain) to avoid namespace collision with convention-routed paths
- [x] Remove explicit page registration from `config/routes.php` — pages are auto-discovered, config is format overrides only
- [x] Add example custom route to `config/routes.php` — commented-out example demonstrating explicit path → class mapping

---

## New Package: Shodo

Shodo (書道, "the way of writing") is the output rendering package. It converts data into a consumable form — whether that's an HTTP response, CLI output, or any other output target. Where Reporter (Glitch) is about internal recording — logging, alerting, tracking — Shodo is about producing output for the end consumer. The output format (JSON, HTML, plain text) and the delivery target (HTTP response, stdout, stderr) are both Shodo's domain.

### Exception rendering (HTTP)

### Foundation

- [x] Define `Renderer` interface (`src/Shodo/Renderer.php`) — base contract for converting data into output. The output type varies by context: `ResponseInterface` for HTTP, stream/string for CLI
- [x] Implement `JsonRenderer` (`src/Shodo/JsonRenderer.php`) — general-purpose renderer that converts any data into a JSON `ResponseInterface` with proper headers. Used by `JsonExceptionRenderer` and available for general use (e.g., rendering query results)

### Exception rendering (HTTP)

- [x] Implement `JsonExceptionRenderer` (`src/Shodo/JsonExceptionRenderer.php`) — implements `Glitch\ExceptionRenderer`, converts a `Throwable` into a data payload and delegates to `JsonRenderer`. Maps `HttpException` to its status code, includes stack trace only in debug mode
- [x] Add tests for `JsonExceptionRenderer` — verify JSON structure, Content-Type header, status code mapping from `HttpException`, debug vs. production output, and that the returned object is a valid `ResponseInterface`

### Format Registry

The format registry maps file extensions to renderers and content types. It is the bridge between context-aware routing (which extracts the format) and response serialization (which needs the right renderer). Applications can enable/disable built-in formats, register custom formats, and override renderers for existing formats.

- [x] Define `Format` value object — holds extension, content type, and renderer class string
- [x] Define and implement `FormatRegistry` — `register()`, `get()`, `has()`, `remove()`, and `renderer()` which resolves from the Container. Throws `UnsupportedFormat` (HTTP 406) for unregistered extensions.
- [x] Register built-in JSON format in starter — extension `json`, content type `application/json`, uses `JsonRenderer`
- [x] Wire `FormatRegistry` into starter kernel — replaces hardcoded `JsonRenderer` with `$formats->renderer($route->format)`
- [ ] Add built-in HTML renderer — renders data into an HTML response (template integration point for apps)
- [ ] Register built-in HTML format — extension `html`, content type `text/html`, uses `HtmlRenderer`
- [ ] Add built-in CSV renderer — renders iterable/array data as CSV with proper escaping
- [ ] Register built-in CSV format — extension `csv`, content type `text/csv`, uses `CsvRenderer`
- [ ] Add built-in plain text renderer — renders data as plain text
- [ ] Register built-in plain text format — extension `txt`, content type `text/plain`, uses `PlainTextRenderer`
- [x] Add format configuration — apps define formats in `config/formats.php` with extension, content_type, and renderer class. The Routing bootstrapper reads this and registers them in the FormatRegistry.
- [x] Add format bootstrapper for Ignition — consolidated into `Bootstrap\Routing` which handles both route and format config
- [x] Add tests for `Format` value object
- [x] Add tests for `FormatRegistry` — register, get, has, remove, renderer resolution, unsupported format (406)
- [x] Add tests for built-in JSON format registration and rendering
- [ ] Add tests for built-in HTML format registration and rendering
- [ ] Add tests for built-in CSV format registration and rendering
- [ ] Add tests for built-in plain text format registration and rendering
- [ ] Add tests for app-defined custom format with custom renderer
- [ ] Add tests for disabling a built-in format via config
- [ ] Add tests for overriding a built-in format's renderer via config

---

## Missing Coverage (discovered during this session)

Items built during the session that need tests or plan tracking:

- [x] Add tests for `Flow\Conveyor\QueryResult` — wrapper for non-object handler returns. Dedicated unit tests for array, string, int, null, bool, float wrapping.
- [x] Add 404 handling for non-existent convention routes — `HttpRouter` now throws `HttpException(NotFound)` when neither Query nor Command DTO class exists for a path, instead of letting the container blow up with a 500.
- [x] Add JSON body parsing to `HyperKernel::prepareRequest()` — parses `application/json` request bodies into `parsedBody` before the app's `handleRequest()` sees the request. Throws `HttpException(BadRequest)` for malformed JSON. Removed manual JSON decoding from the starter app kernel.
- [x] Add HTTP method enforcement — `HttpRouter` now validates that the resolved DTO class exists. Pages reject non-GET with 405. Convention routes check the alternate namespace (Query↔Command) to distinguish 405 Method Not Allowed from 404 Not Found. Added `MethodNotAllowed` exception extending `HttpException` with allowed methods list for RFC 7231 `Allow` header.
- [ ] Revisit `Renderer` interface return type — currently uses `mixed`. Consider whether a typed alternative (e.g., `ResponseInterface` for HTTP renderers) is feasible without breaking the transport-agnostic design.
- [x] Fix error responses bypassing middleware — exception handling now lives inside the core handler, so error responses (404, 405, 400, 500) flow back through the full middleware stack. `prepareRequest` errors also run through middleware via a fixed-response handler. Every response — success or error — passes through all middleware layers.

---

## Open Questions (needs discussion before implementation)

These items need design decisions before they can be worked on:

- **Bootstrap lifecycle hooks** — What does "before/after events so the app can hook into the boot sequence" mean concretely? Events before/after each individual bootstrapper? Before/after the entire sequence? Who consumes these — the app kernel, service providers, middleware? What's the use case that config and the existing bootstrapper sequence can't handle?
- **Handler auto-discovery** — Is this actually needed? Codex already resolves handler classes on demand via reflection. Pre-scanning the filesystem to register handlers in the Container adds complexity and startup cost. What use case requires pre-registration that on-demand resolution can't serve?
- ~~**Manual route overrides**~~ — Resolved: custom routes are the general mechanism (path+methods → explicit class mapping). Pages are a convenience layer that auto-discovers classes and registers them as GET-only custom routes. Priority: custom routes > convention. Registered via config and auto-discovery.
- **HTML renderer and templates** — Does the framework ship a template engine, integrate with an existing one (Blade, Twig, Plates), or just provide a bare `HtmlRenderer` that the app overrides? This affects the built-in HTML format registration — we can't ship an HTML renderer without deciding the template strategy.
- **Opt-in command response bodies** — What does the configuration look like? Per-handler attribute? Global config toggle? Per-route config? What format is the response body rendered in — always JSON, or format-aware like Queries?

---

## Documentation

- [ ] Write `src/Toolkit/README.md` (once Toolkit has more than just Strings)
- [ ] Write `src/Glitch/README.md`
- [ ] Write `src/Ignition/README.md`
- [ ] Write `src/Quill/README.md`
- [x] Write `src/Parchment/README.md`
- [ ] Write `src/Atlas/README.md` (after package is built)
- [ ] Write `src/Shodo/README.md` (after package is built)
