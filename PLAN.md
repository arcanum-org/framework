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

- [ ] Add test for `Resolver::resolve()` with callable that returns non-object — currently only valid closures are tested, no test for callable returning a primitive or null
- [ ] Add test for `Resolver::resolveWith()` with variadic constructor parameters — `resolveWith()` doesn't handle variadics like `resolveParameters()` does

---

## Echo

- [ ] Add test for listener that throws a non-`Interrupted` exception during dispatch — verify it propagates to the caller
- [ ] Add test for `Interrupted` exception during dispatch — verify the event is returned and propagation stops gracefully
- [ ] Add test for event mutation propagation through listener chain — verify listener 2 sees modifications made by listener 1

---

## Flow

- [ ] Add test for `EmptyStream::getMetadata($key)` — verify it returns the correct value for valid metadata keys (currently always returns `null`)
- [ ] Add test for `Stream::read(0)` edge case — verify reading zero bytes returns empty string
- [ ] Add test for `CachingStream::seek()` with `SEEK_END` on an unseekable remote stream
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

## New Package: Routing

The routing package maps incoming HTTP requests to handlers using convention-based discovery, so users don't need to define routes manually.

- [ ] Define `Router` interface — takes a ServerRequest, returns a matched route with handler
- [ ] Implement convention-based route resolution — map HTTP method + path to handler classes (e.g., `POST /orders` → `PlaceOrderHandler`, `GET /orders/{id}` → `GetOrderHandler`)
- [ ] Add path parameter extraction — parse `{id}`, `{slug}`, etc. from URI and inject into handler constructor
- [ ] Add route registration for manual overrides — allow explicit route→handler mappings when conventions don't fit
- [ ] Add middleware support — integrate with Flow's Continuum for per-route or global HTTP middleware
- [ ] Add routing bootstrapper for Ignition — auto-discovers handler classes and registers routes
- [ ] Add tests for convention-based routing
- [ ] Add tests for path parameter extraction
- [ ] Add tests for manual route registration
- [ ] Add tests for middleware integration

### Context-Aware Routing

The router strips file extensions from the URI path before matching, so `/shop/new-products.json`, `/shop/new-products.html`, and `/shop/new-products.csv` all resolve to the same Query handler. The extension is extracted and stored as the requested response format. After the handler returns data, the format determines which Shodo renderer produces the `ResponseInterface`.

- [ ] Add extension parsing to route resolution — strip `.json`, `.html`, `.csv`, etc. from the URI path before matching, store the extracted format on the matched route
- [ ] Default to a configurable fallback format when no extension is present (e.g., `json`)
- [ ] Add tests for extension stripping during route matching — verify the same handler is resolved regardless of extension
- [ ] Add tests for format extraction — verify the parsed format is available after matching
- [ ] Add tests for missing extension — verify fallback format is applied
- [ ] Add tests for unknown/unregistered extension — verify appropriate error (e.g., 406 Not Acceptable)

---

## CQRS Integration

These items bridge the gap between HTTP (Hyper) and command/query dispatch (Conveyor), completing the end-to-end CQRS request lifecycle.

### HTTP Middleware Pipeline

- [ ] Add global HTTP middleware stack to `HyperKernel` — a Continuum pipeline that processes every request before routing (for CORS, auth, content negotiation, etc.)
- [ ] Add configuration for global middleware registration — allow apps to declare ordered middleware in config or bootstrap
- [ ] Add tests for global middleware execution order
- [ ] Add tests for middleware short-circuiting (returning a response without hitting the router)

### Request → DTO Mapping

- [ ] Define a request deserializer interface — extracts path params, query params, and body from `ServerRequestInterface` into a keyed array
- [ ] Implement DTO hydrator — populates a command/query object's constructor or public properties from extracted request data
- [ ] Add type coercion for scalar DTO properties — cast string request values to int, float, bool as needed by the DTO constructor
- [ ] Add tests for path parameter injection into DTOs
- [ ] Add tests for query parameter injection into DTOs
- [ ] Add tests for request body injection into DTOs
- [ ] Add tests for type coercion (string → int, string → bool, etc.)

### Response Serialization

- [ ] Define a response serializer interface — converts a handler's return DTO into a `ResponseInterface`
- [ ] Implement format-aware response serializer — selects a Shodo renderer based on the route's parsed format, delegates rendering, and returns the `ResponseInterface` with format-appropriate headers
- [ ] Add status code resolution — map handler results to HTTP status codes (e.g., created resource → 201, null result → 204)
- [ ] Add tests for format-aware renderer selection (format → renderer dispatch)
- [ ] Add tests for status code resolution from handler results
- [ ] Add tests for empty/null handler results

### Handler Discovery

- [ ] Add handler auto-discovery — scan a configured namespace/directory for handler classes and register them in the Container
- [ ] Add handler registration in bootstrap — wire discovered handlers into the Container so Conveyor can resolve them
- [ ] Add tests for handler discovery from a namespace
- [ ] Add tests for handler resolution through Container → Conveyor pipeline

---

## Starter Project

Track updates to the starter app (`../arcanum/`) as framework features land.

- [ ] Update `bootstrap/http.php` to register `MiddlewareBus` (Conveyor) in the Container
- [ ] Update `bootstrap/http.php` to register the Router in the Container
- [ ] Update `App\HTTP\Kernel::handleRequest()` to dispatch requests through the Router → Conveyor pipeline instead of throwing 404
- [ ] Add example command handler (e.g., `GET /health` → `HealthCheckHandler`) to demonstrate the full CQRS lifecycle
- [ ] Add route configuration or handler directory convention to the starter
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

- [ ] Define `Format` value object — holds extension string, content type, and renderer class/instance for a single format (e.g., `json` → `application/json` → `JsonRenderer`)
- [ ] Define `FormatRegistry` interface — `register(Format $format): void`, `get(string $extension): Format`, `has(string $extension): bool`, `remove(string $extension): void`
- [ ] Implement `FormatRegistry` — stores formats keyed by extension, resolves renderers from the Container when needed
- [ ] Register built-in JSON format — extension `json`, content type `application/json`, uses `JsonRenderer`
- [ ] Add built-in HTML renderer — renders data into an HTML response (template integration point for apps)
- [ ] Register built-in HTML format — extension `html`, content type `text/html`, uses `HtmlRenderer`
- [ ] Add built-in CSV renderer — renders iterable/array data as CSV with proper escaping
- [ ] Register built-in CSV format — extension `csv`, content type `text/csv`, uses `CsvRenderer`
- [ ] Add built-in plain text renderer — renders data as plain text
- [ ] Register built-in plain text format — extension `txt`, content type `text/plain`, uses `PlainTextRenderer`
- [ ] Add format configuration — allow apps to enable/disable formats and override renderer classes via config (e.g., `config/formats.php`)
- [ ] Add format bootstrapper for Ignition — reads format config, registers enabled formats, applies renderer overrides
- [ ] Add tests for `Format` value object
- [ ] Add tests for `FormatRegistry` — register, get, has, remove
- [ ] Add tests for built-in JSON format registration and rendering
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
- [ ] Write `src/Routing/README.md` (after package is built)
- [ ] Write `src/Shodo/README.md` (after package is built)
