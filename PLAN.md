# Framework Completion Plan

This checklist tracks remaining work to complete all packages in the Arcanum framework. Each item is a small unit of work that includes 100% test coverage.

---

## Bug Fixes

- [x] Fix `Headers::cleanValues()` rejecting `'0'` as a header value — PHP's `empty('0')` returns true, so `Content-Length: 0` is rejected. Replace `empty()` with a strict check.
- [x] Fix `Response::withoutHeader()` mutating the original object — it should return an immutable copy per PSR-7. This also breaks `Server::sendSetCookieHeaders()`.
- [ ] Fix `EmptyStream::getMetadata($key)` returning `null` for any key — the no-arg path returns a metadata array with values, but passing a key always returns `null` instead of looking up the key in that array
- [ ] Fix `PrimitiveResolver` calling `implode(",", $type->getTypes())` on `ReflectionType` objects — should map to `getName()` first: `implode(",", array_map(fn($t) => $t->getName(), $type->getTypes()))`
- [ ] Fix `StandardProcessor` using loose `!$payload` check — should be `$payload === null` to avoid false positives on valid falsy objects
- [ ] Fix `RegistryTest::assertGetSetViaArrayAccess()` method name — missing `test` prefix so PHPUnit never runs it, leaving `Registry` array access silently untested
- [ ] Fix `ProviderRegistry` interface accepting only `Provider` while `Container::provider()` accepts `string|Provider` — update the interface to match the implementation
- [ ] Fix typo `testSimnpleProvider` → `testSimpleProvider` in `SimpleProviderTest`
- [ ] Fix typo `testSimnpleProvider` → `testSimpleProvider` in `PrototypeProviderTest`

---

## Hyper

- [x] Add CRLF injection prevention in header values (reject or strip `\r\n` sequences)
- [x] Add request target validation per RFC 7230 (origin-form, absolute-form, authority-form, asterisk-form)
- [x] Investigate PHPServerAdapter testability — not unit-testable: every method is a 1:1 wrapper around SAPI-dependent PHP built-ins (`header()`, `getallheaders()`, `ob_*`, etc.). The `ServerAdapter` interface exists so `Server` can be tested via mocks. Class is correctly marked `@codeCoverageIgnore`.
- [x] Add tests for `Server::request()` cookie filtering — not unit-testable: `request()` directly accesses `$_COOKIE`, `$_GET`, `$_POST` superglobals. The filtering logic (is_string key/value check) is trivial. Refactoring to inject superglobals would add complexity for minimal value.
- [x] Add tests for `Server::sendSetCookieHeaders()` (blocked by the `withoutHeader` mutation bug — unblocked once that bug is fixed)
- [ ] Fix `Port` constructor not storing type-converted value — promoted property `$value` stays as string when passed a string because `$value = (int)$value` reassigns the local parameter, not `$this->value`
- [ ] Fix `Port` validation error message — says "between 1 and 65535, or null" but validation allows 0 and constructor doesn't accept null
- [ ] Fix `Request::getRequestTarget()` returning stale cached value after `withUri()` — the cloned request inherits the cached `$requestTarget` and never recalculates from the new URI
- [ ] Add `ResponseTest` — Response has zero direct test coverage (only exercised indirectly via ServerTest)

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
- [ ] Add tests for `Bootstrap\Environment` (.env loading, Environment service registration)
- [ ] Add tests for `Bootstrap\Configuration` (config file loading from directory)
- [ ] Add tests for `Bootstrap\Logger` (handler/channel creation from config)
- [ ] Add tests for `Bootstrap\Exceptions` (error/exception/shutdown handler registration, memory reservation)

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

### River (Streams)

- [ ] Add test for `EmptyStream::getMetadata($key)` — verify it returns the correct value for valid metadata keys (currently always returns `null`)
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

- [ ] Review PSR-3 compliance and confirm complete — if gaps exist, address them
- [ ] Add tests for `Logger` (multi-channel routing, default channel fallback)
- [ ] Fix the 12 invalid `#[CoversClass]` annotations in `LoggerTest` that target `Monolog\Logger`

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

### Pages

Pages are explicitly registered Query routes that bypass convention-based URL-to-namespace mapping. They live under a dedicated namespace (`App\Pages\` by default, configurable) without a `Query\` sub-namespace since Pages are always Queries. The root path `/` maps to `Index` by convention.

```
GET /                        → App\Pages\Index         + IndexHandler           (default format: html)
GET /thing.html              → App\Pages\Thing         + ThingHandler           (format: html)
GET /thing.json              → App\Pages\Thing         + ThingHandler           (format: json)
GET /docs/getting-started    → App\Pages\Docs\GettingStarted + GettingStartedHandler
```

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

### Manual Route Overrides

- [ ] Add route registration for manual overrides — allow explicit route→handler mappings when conventions don't fit
- [ ] Add tests for manual route registration overriding convention

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

- [ ] Add OPTIONS middleware — automatically responds with allowed methods for a given path and CORS headers, no application handler involved
- [ ] Add tests for OPTIONS response listing allowed methods for a convention route
- [ ] Add tests for OPTIONS CORS preflight headers

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

- [ ] Add global HTTP middleware stack to `HyperKernel` — a Continuum pipeline that processes every request before routing (for CORS, auth, content negotiation, etc.)
- [ ] Add configuration for global middleware registration — allow apps to declare ordered middleware in config or bootstrap
- [ ] Add tests for global middleware execution order
- [ ] Add tests for middleware short-circuiting (returning a response without hitting the router)

### Request → DTO Mapping

- [x] Implement `Codex\Hydrator` — constructs DTOs by matching associative array keys to constructor parameter names. Handles missing params via defaults, throws for required params with no data.
- [x] Add type coercion for scalar DTO properties — coerces string values to int (via `is_numeric`), float, bool (via `filter_var`), and string as needed by the constructor type hints
- [x] Add tests for query parameter injection into DTOs
- [ ] Add tests for request body injection into DTOs (pending Command implementation)
- [x] Add tests for type coercion (string → int, string → bool, etc.)
- [x] Add tests for missing required parameter, extra data ignored, default value fallback

### Query Response Serialization

Query handlers always return data. The response is rendered by the format-aware serializer (JSON, HTML, CSV, etc.) based on the extension extracted by context-aware routing. Status code is always 200.

- [ ] Define a response serializer interface — converts a handler's return value into a `ResponseInterface`
- [ ] Implement format-aware response serializer — selects a Shodo renderer based on the `Route`'s parsed format, delegates rendering, returns the `ResponseInterface` with format-appropriate headers and 200 status
- [ ] Add tests for format-aware renderer selection (format → renderer dispatch)
- [ ] Add tests for Query response with JSON format
- [ ] Add tests for Query response with HTML format
- [ ] Add tests for Query response with CSV format

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
- [x] Add `App\Pages\Index`, `App\Pages\IndexHandler`, and `App\Pages\IndexResult` — default homepage at root `/`
- [x] Add `App\Query\Health`, `App\Query\HealthHandler`, and `App\Query\HealthResult` — example convention-routed Query
- [x] Set up directory structure conventions — `app/Pages/`, `app/Query/` directories in the starter
- [x] Add `config/routes.php` — page registration moved from bootstrap to config file, bootstrap reads `$routes['pages']` and registers each path/format pair
- [ ] Add `config/formats.php` — configure enabled response formats and any renderer overrides
- [x] Add example Command — `PUT /contact/submit` → `App\Contact\Command\Submit` + `SubmitHandler`, demonstrates Command with DTO hydration from JSON body, void return→204
- [ ] Update `config/` with any new configuration files needed by routing or middleware

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

## Documentation

- [ ] Write `src/Toolkit/README.md` (once Toolkit has more than just Strings)
- [ ] Write `src/Glitch/README.md`
- [ ] Write `src/Ignition/README.md`
- [ ] Write `src/Quill/README.md`
- [x] Write `src/Parchment/README.md`
- [ ] Write `src/Atlas/README.md` (after package is built)
- [ ] Write `src/Shodo/README.md` (after package is built)
