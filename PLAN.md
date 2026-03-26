# Framework Completion Plan

This checklist tracks remaining work to complete all packages in the Arcanum framework. Each item is a small unit of work that includes 100% test coverage.

---

## Bug Fixes

- [x] Fix `Headers::cleanValues()` rejecting `'0'` as a header value — PHP's `empty('0')` returns true, so `Content-Length: 0` is rejected. Replace `empty()` with a strict check.
- [x] Fix `Response::withoutHeader()` mutating the original object — it should return an immutable copy per PSR-7. This also breaks `Server::sendSetCookieHeaders()`.

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
- [ ] Add `HttpException` class (`src/Glitch/HttpException.php`) — exception that carries an HTTP status code explicitly (e.g., `throw new HttpException(StatusCode::NotFound, 'Order not found')`). Used by Shodo renderers and the kernel to produce proper HTTP error responses
- [ ] Define `ExceptionRenderer` interface (`src/Glitch/ExceptionRenderer.php`) — takes a `Throwable`, returns `ResponseInterface`. This is the contract between Glitch (error handling) and Shodo (rendering). Lives in Glitch because it's part of the error-handling lifecycle — Shodo implements it
- [ ] Remove `JsonReporter` (`src/Glitch/JsonReporter.php`) — replaced by Shodo's `JsonRenderer`. The echo-based approach conflated reporting (internal recording) with responding (client-facing output)
- [ ] Integrate `ExceptionRenderer` into `HyperKernel` (`src/Ignition/HyperKernel.php`) — on exception, dispatch to `Handler` for internal reporting (LogReporter, etc.), then use the container-resolved `ExceptionRenderer` to build and return a `ResponseInterface`

---

## Ignition

- [x] Add configuration caching — cache parsed config arrays to avoid re-reading files on every request
- [ ] Add environment validation — verify required env vars are set during bootstrap, fail fast with clear errors
- [ ] Add bootstrap lifecycle hooks — before/after events so the app can hook into the boot sequence
- [ ] Add tests for `HyperKernel` (bootstrap sequence, directory accessors, terminate)
- [ ] Add tests for `Bootstrap\Environment` (.env loading, Environment service registration)
- [ ] Add tests for `Bootstrap\Configuration` (config file loading from directory)
- [ ] Add tests for `Bootstrap\Logger` (handler/channel creation from config)
- [ ] Add tests for `Bootstrap\Exceptions` (error/exception/shutdown handler registration, memory reservation)

---

## Quill

- [ ] Review PSR-3 compliance and confirm complete — if gaps exist, address them
- [ ] Add tests for `Logger` (multi-channel routing, default channel fallback)
- [ ] Fix the 12 invalid `#[CoversClass]` annotations in `LoggerTest` that target `Monolog\Logger`

---

## Parchment

- [ ] Add `Reader` — read file contents (string, lines, JSON decode)
- [ ] Add `Writer` — write file contents (string, JSON encode, append)
- [ ] Add `FileSystem` — copy, move, delete files and directories
- [ ] Add `PathHelper` — normalize paths, resolve relative paths, extract extensions
- [ ] Add `TempFile` — create and auto-clean temporary files
- [ ] Add `AtomicWriter` — write to temp file then rename, preventing partial writes

---

## New Package: CQRS

The CQRS package provides the high-level Command/Query separation pattern, built on top of Flow's Conveyor (MiddlewareBus) for dispatch and handler resolution.

### Commands (write operations)

- [ ] Define `Command` interface or base class — marks an object as a command (state mutation)
- [ ] Define `CommandHandler` interface — handles a command, returns void or result
- [ ] Define `CommandBus` — wraps MiddlewareBus, dispatches commands to handlers by convention (`PlaceOrder` → `PlaceOrderHandler`)
- [ ] Add default validation middleware for commands (e.g., FinalFilter, ReadOnlyPropertyFilter from Conveyor)
- [ ] Add tests for full command dispatch lifecycle

### Queries (read operations)

- [ ] Define `Query` interface or base class — marks an object as a query (read-only)
- [ ] Define `QueryHandler` interface — handles a query, returns a result
- [ ] Define `QueryBus` — wraps MiddlewareBus, dispatches queries to handlers by convention
- [ ] Add tests for full query dispatch lifecycle

### Integration

- [ ] Add CQRS bootstrapper for Ignition — auto-registers CommandBus and QueryBus in the container

---

## New Package: Routing

The routing package maps incoming HTTP requests to Command/Query objects using convention-based discovery, so users don't need to define routes manually.

- [ ] Define `Router` interface — takes a ServerRequest, returns a Command or Query object
- [ ] Implement convention-based route resolution — map HTTP method + path to Command/Query classes (e.g., `POST /orders` → `PlaceOrderCommand`, `GET /orders/{id}` → `GetOrderQuery`)
- [ ] Add path parameter extraction — parse `{id}`, `{slug}`, etc. from URI and inject into Command/Query constructor
- [ ] Add route registration for manual overrides — allow explicit route→handler mappings when conventions don't fit
- [ ] Add middleware support — integrate with Flow's Continuum for per-route or global HTTP middleware
- [ ] Add routing bootstrapper for Ignition — auto-discovers Command/Query classes and registers routes
- [ ] Add tests for convention-based routing
- [ ] Add tests for path parameter extraction
- [ ] Add tests for manual route registration
- [ ] Add tests for middleware integration

---

## New Package: Shodo

Shodo (書道, "the way of writing") is the output rendering package. It converts data into a consumable form — whether that's an HTTP response, CLI output, or any other output target. Where Reporter (Glitch) is about internal recording — logging, alerting, tracking — Shodo is about producing output for the end consumer. The output format (JSON, HTML, plain text) and the delivery target (HTTP response, stdout, stderr) are both Shodo's domain.

### Exception rendering (HTTP)

- [ ] Implement `JsonRenderer` (`src/Shodo/JsonRenderer.php`) — implements `Glitch\ExceptionRenderer`, builds a `Hyper\Response` with `Content-Type: application/json`, proper HTTP status code from `HttpException` or exception code, and stack trace only in debug mode
- [ ] Add tests for `JsonRenderer` — verify JSON structure, Content-Type header, status code mapping from `HttpException`, debug vs. production output, and that the returned object is a valid `ResponseInterface`

### Foundation

- [ ] Define `Renderer` interface (`src/Shodo/Renderer.php`) — base contract for converting data into output. The output type varies by context: `ResponseInterface` for HTTP, stream/string for CLI. `ExceptionRenderer` is the first specialization
- [ ] Implement `JsonResponse` helper (`src/Shodo/JsonResponse.php`) — factory for building JSON HTTP responses with proper headers. Used by `JsonRenderer` and available for general use (e.g., rendering query results)

---

## Documentation

- [ ] Write `src/Toolkit/README.md` (once Toolkit has more than just Strings)
- [ ] Write `src/Glitch/README.md`
- [ ] Write `src/Ignition/README.md`
- [ ] Write `src/Quill/README.md`
- [ ] Write `src/Parchment/README.md`
- [ ] Write `src/CQRS/README.md` (after package is built)
- [ ] Write `src/Routing/README.md` (after package is built)
- [ ] Write `src/Shodo/README.md` (after package is built)
