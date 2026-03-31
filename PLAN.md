# Framework Completion Plan

This checklist tracks remaining work to complete all packages in the Arcanum framework. Each item is a small unit of work that includes 100% test coverage.

**Progress: 200 done, 22 remaining.**

---

## Completed Work

### Bug Fixes

- [x] Fix `Headers::cleanValues()` rejecting `'0'` as a header value — PHP's `empty('0')` returns true, so `Content-Length: 0` is rejected. Replace `empty()` with a strict check.
- [x] Fix `Response::withoutHeader()` mutating the original object — it should return an immutable copy per PSR-7. This also breaks `Server::sendSetCookieHeaders()`.
- [x] Fix `EmptyStream::getMetadata($key)` returning `null` for any key — now looks up the key in the metadata array, returns `null` only for absent keys
- [x] Fix `PrimitiveResolver` calling `implode(",", $type->getTypes())` on `ReflectionType` objects — now maps to `getName()` via `ReflectionNamedType` check
- [x] Fix `StandardProcessor` using loose `!$payload` check — changed to `$payload === null`
- [x] Fix `RegistryTest::assertGetSetViaArrayAccess()` method name — renamed to `testGetSetViaArrayAccess()` so PHPUnit runs it
- [x] Fix `ProviderRegistry` interface accepting only `Provider` while `Container::provider()` accepts `string|Provider` — updated interface to `string|Provider`
- [x] Fix typo `testSimnpleProvider` → `testSimpleProvider` in `SimpleProviderTest`
- [x] Fix typo `testSimnpleProvider` → `testPrototypeProvider` in `PrototypeProviderTest`

### Hyper

- [x] Add CRLF injection prevention in header values (reject or strip `\r\n` sequences)
- [x] Add request target validation per RFC 7230 (origin-form, absolute-form, authority-form, asterisk-form)
- [x] Investigate PHPServerAdapter testability — not unit-testable: every method is a 1:1 wrapper around SAPI-dependent PHP built-ins. Class is correctly marked `@codeCoverageIgnore`.
- [x] Add tests for `Server::request()` cookie filtering — not unit-testable: directly accesses superglobals
- [x] Add tests for `Server::sendSetCookieHeaders()`
- [x] Fix `Port` constructor not storing type-converted value
- [x] Fix `Port` validation error message
- [x] Fix `Request::getRequestTarget()` returning stale cached value after `withUri()`
- [x] Add `ResponseTest` — 18 tests covering status code, reason phrase, charset, headers, body, protocol version, and immutability

### Glitch

- [x] Add `HttpException` class — exception that carries an HTTP status code
- [x] Define `ExceptionRenderer` interface — takes `Throwable`, returns `ResponseInterface`
- [x] Implement `JsonExceptionRenderer` — converts exceptions to JSON error responses
- [x] Integrate `ExceptionRenderer` into `HyperKernel`
- [x] Add tests for `Handler`, `LogReporter`, `Level` enum
- [x] Remove `JsonReporter` — replaced by `JsonExceptionRenderer`

### Ignition

- [x] Add configuration caching
- [x] Add environment validation
- [x] Add `Bootstrap\Routing` — registers Atlas routing and Shodo format registry in the container
- [x] Add tests for `HyperKernel`, `Bootstrap\Environment`, `Bootstrap\Configuration`, `Bootstrap\Logger`, `Bootstrap\Exceptions`

### Codex

- [x] Fix nullable parameter resolution — `resolveClass()` returns `object|array|null`
- [x] Add tests for nullable parameter fallback

### Quill

- [x] Review PSR-3 compliance — complete
- [x] Add tests for `Logger` and `Channel`

### Parchment

- [x] Add `Reader`, `Writer`, `FileSystem`, `TempFile`, `Searcher`
- [x] Migrate `ConfigurationCache` to use Parchment

### Atlas (Routing)

- [x] Convention-based URL-to-namespace routing (`ConventionResolver`)
- [x] `HttpRouter` with resolution priority: custom routes > pages > convention
- [x] `Route` value object with `isQuery()`, `isCommand()`, `isPage()`, `withFormat()`
- [x] `RouteMap` for explicit path → class mappings with `isPage` propagation
- [x] `PageDiscovery` — scans `app/Pages/` for `.html` templates, registers as GET-only routes
- [x] `PageResolver` for legacy page support
- [x] Context-aware format routing via file extensions
- [x] OPTIONS middleware with `Allow` header
- [x] Handler-only routes with dynamic Command/Query DTOs
- [x] 404/405 distinction for convention routes
- [x] Config-based custom route and page format override registration

### Shodo (Rendering)

- [x] `Renderer` interface — base contract for converting data to output
- [x] `JsonRenderer` — pure JSON serializer
- [x] `HtmlRenderer` — template-based with co-located `.html` discovery
- [x] `PlainTextRenderer` — template-based with co-located `.txt` discovery, identity escape
- [x] `CsvRenderer` — pure tabular serializer
- [x] `EmptyResponseRenderer` — status-code-only for commands
- [x] `JsonExceptionRenderer` — exceptions as JSON with debug/production modes
- [x] `TemplateCompiler` — regex-based `{{ }}` syntax → PHP, format-agnostic `$__escape`
- [x] `TemplateCache` — compiled template caching with mtime freshness
- [x] `TemplateResolver` — PSR-4 convention DTO class → template file
- [x] `HtmlFallback` / `PlainTextFallback` — generic data dumps when no template exists
- [x] `FormatRegistry` — maps extensions to renderers, resolves from container
- [x] Format configuration via `config/formats.php`

### CQRS Integration

- [x] Global HTTP middleware stack (`HttpMiddleware` in HyperKernel)
- [x] `Hydrator` — request data → DTO mapping with type coercion
- [x] Query response serialization via `FormatRegistry`
- [x] `EmptyResponseRenderer` for command responses (void→204, DTO→201)
- [x] JSON body parsing in `HyperKernel::prepareRequest()`
- [x] Error responses flow through full middleware stack

### Conveyor

- [x] Handler prefix dispatch (POST→`Post`, DELETE→`Delete`, etc.) with fallback
- [x] `HandlerProxy` interface for dynamic DTOs
- [x] `DynamicDTO` base class — shared Registry wrapper for Command, Query, Page
- [x] `Command` / `Query` dynamic DTOs for handler-only routes
- [x] `Page` dynamic DTO — fixed `handlerBaseName()` → `PageHandler`
- [x] `PageHandler` — framework-provided handler for all pages
- [x] `QueryResult` wrapper for non-object handler returns
- [x] DTO validation middleware filters

### Starter Project

- [x] Full CQRS pipeline: Router → Hydrator → Conveyor → Renderer
- [x] Example Query (`Health`), Command (`Contact/Submit`), Page (`Index`)
- [x] Pages are template-driven — `Index.html` + optional `Index.php` DTO with defaults
- [x] Config files: `app.php`, `routes.php`, `formats.php`, `middleware.php`, `cache.php`
- [x] CORS middleware example

### Documentation

- [x] Write `src/Parchment/README.md`
- [x] Write `src/Atlas/README.md`
- [x] Write `src/Shodo/README.md`
- [x] Update `src/Flow/Conveyor/README.md` — DynamicDTO, Page, PageHandler sections

---

## Remaining Work

### Coverage Gaps

Small, isolated test cases for edge cases and untested paths.

**Cabinet:**
- [x] Add test for `Container::specify()` with array `when` parameter — only the string form is tested
- [x] Add test for `Container` default constructor — no test creates a Container with `null` parameters

**Codex:**
- [x] Add test for `Resolver::resolve()` with callable that returns non-object
- [x] Add test for `Resolver::resolveWith()` with variadic constructor parameters — also fixed `resolveWith()` to handle variadic params

**Echo:**
- [x] Add test for listener that throws a non-`Interrupted` exception during dispatch
- [x] Add test for `Interrupted` exception during dispatch — verify event is returned and propagation stops
- [x] Add test for event mutation propagation through listener chain

**Flow — River:**
- [x] Add test for `Stream::read(0)` edge case — already existed at `StreamTest::testReadReturnsEmptyStringIfLengthToReadIsZero`
- [x] Add test for `CachingStream::seek()` with `SEEK_END` on an unseekable remote stream

**Flow — Conveyor:**
- [x] Add test for `MiddlewareBus::dispatch()` when handler class is not found in container

**Gather:**
- [x] Add tests for `Configuration::asAlpha()`, `asAlnum()`, `asDigits()` with dot-notation keys
- [x] Add tests for `IgnoreCaseRegistry::asAlpha()`, `asAlnum()`, `asDigits()` — verify case-insensitive coercion
- [x] Add tests for `Environment` inherited methods (`get()`, `has()`, `set()`, `count()`)
- [x] Add test for `Configuration::set()` where an intermediate path value is a scalar, not an array

### Integration Tests

Now that all renderers are built, these integration tests are unblocked:

- [ ] Add integration tests for Query response with HTML format
- [ ] Add integration tests for Query response with CSV format

### Format Registry Tests

- [ ] Add tests for app-defined custom format with custom renderer
- [ ] Add tests for disabling a built-in format via config
- [ ] Add tests for overriding a built-in format's renderer via config

### Command Response Enhancements

- [ ] Add `Location` header for 201 responses — set if the framework can resolve a URL from the returned identifier
- [ ] Add tests for scalar return → 201 Created with empty body and Location header
- [ ] Add tests for `null` return → 202 Accepted with empty body (pending void/null distinction in Conveyor)
- [ ] Add opt-in configuration for command response bodies — disabled by default
- [ ] Add tests for opt-in response body configuration
- [ ] Add documentation guidance — explain CQRS command conventions and why response bodies are discouraged

### Route Middleware

- [ ] Add per-route middleware support — integrate with Flow's Continuum for route-specific middleware
- [ ] Add tests for per-route middleware execution

### Design Considerations

Items flagged for future discussion. Not blocking — the framework works without them.

- [ ] Revisit `Renderer` interface return type — currently uses `mixed`. Consider `ResponseInterface` for HTTP renderers without breaking transport-agnostic design.

### Documentation

- [ ] Write `src/Toolkit/README.md` (once Toolkit has more than just Strings)
- [ ] Write `src/Glitch/README.md`
- [ ] Write `src/Ignition/README.md`
- [ ] Write `src/Quill/README.md`

---

## Open Questions

These items need design decisions before they can be worked on:

- **Bootstrap lifecycle hooks** — What does "before/after events so the app can hook into the boot sequence" mean concretely? Events before/after each individual bootstrapper? Before/after the entire sequence? Who consumes these — the app kernel, service providers, middleware? What's the use case that config and the existing bootstrapper sequence can't handle?
- **Handler auto-discovery** — Is this actually needed? Codex already resolves handler classes on demand via reflection. Pre-scanning the filesystem to register handlers in the Container adds complexity and startup cost. What use case requires pre-registration that on-demand resolution can't serve?
- **Opt-in command response bodies** — What does the configuration look like? Per-handler attribute? Global config toggle? Per-route config? What format is the response body rendered in — always JSON, or format-aware like Queries?

## Resolved Questions

- ~~**Manual route overrides**~~ — Custom routes are the general mechanism (path+methods → explicit class mapping). Pages are a convenience layer that auto-discovers templates and registers them as GET-only custom routes. Priority: custom routes > convention.
- ~~**HTML renderer and templates**~~ — Custom micro template compiler in Shodo. Templates co-located with DTOs, `{{ }}` syntax compiled to PHP and cached. Format-agnostic `$__escape` injected by each renderer.
- ~~**Static file pages**~~ — Pages are template-driven. A `.html` template in `app/Pages/` is all that's needed. Pages flow through Conveyor via `Page` DTO and `PageHandler`. Optional DTO provides default data. Query params hydrated into template variables.
