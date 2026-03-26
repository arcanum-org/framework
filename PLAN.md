# Framework Completion Plan

This checklist tracks remaining work to complete all packages in the Arcanum framework. Each item is a small unit of work that includes 100% test coverage.

---

## Bug Fixes

- [x] Fix `Headers::cleanValues()` rejecting `'0'` as a header value — PHP's `empty('0')` returns true, so `Content-Length: 0` is rejected. Replace `empty()` with a strict check.
- [x] Fix `Response::withoutHeader()` mutating the original object — it should return an immutable copy per PSR-7. This also breaks `Server::sendSetCookieHeaders()`.

---

## Hyper

- [x] Add CRLF injection prevention in header values (reject or strip `\r\n` sequences)
- [ ] Add request target validation per RFC 7230 (origin-form, absolute-form, authority-form, asterisk-form)
- [ ] Investigate PHPServerAdapter testability — if possible, add tests; if not, document why
- [ ] Add tests for `Server::request()` cookie filtering (lines 60-61, currently untestable due to `$_COOKIE` superglobal)
- [ ] Add tests for `Server::sendSetCookieHeaders()` (blocked by the `withoutHeader` mutation bug — unblocked once that bug is fixed)

---

## Glitch

- [ ] Add `JsonReporter` — formats exceptions as JSON (status code, message, stack trace in debug mode only)
- [ ] Add tests for `Handler` class (error→exception conversion, shutdown handling, reporter dispatch)
- [ ] Add tests for `LogReporter` (per-exception-type log levels and channel routing)
- [ ] Add tests for `Level` enum (isDeprecation, isFatal helpers)

---

## Ignition

- [ ] Add configuration caching — cache parsed config arrays to avoid re-reading files on every request
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

## Documentation

- [ ] Write `src/Toolkit/README.md` (once Toolkit has more than just Strings)
- [ ] Write `src/Glitch/README.md`
- [ ] Write `src/Ignition/README.md`
- [ ] Write `src/Quill/README.md`
- [ ] Write `src/Parchment/README.md`
- [ ] Write `src/CQRS/README.md` (after package is built)
- [ ] Write `src/Routing/README.md` (after package is built)
