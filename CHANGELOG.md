# Changelog

All notable changes to the Arcanum framework will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [0.1.0] - 2026-04-12

First tagged release. Everything below is "added" — there's no prior version to compare against. This release represents the framework as a coherent, tested, documented whole for the first time.

### Foundation

- **Cabinet** — PSR-11 DI container with services, factories, singletons, prototypes, decorators, and middleware-on-services. Uses Codex for auto-wiring.
- **Codex** — Reflection-based class resolver with recursive constructor dependency resolution and specifications (`when X needs Y give Z`).
- **Gather** — Typed key-value registries: `Configuration` (dot-notation), `Environment` (no serialize/clone), `IgnoreCaseRegistry` (HTTP headers).
- **Toolkit** — String utilities, random generation, hex encoding, and security primitives (`SodiumEncryptor`, `BcryptHasher`, `Argon2Hasher`, `SodiumSigner`).
- **Parchment** — Filesystem reader utilities.

### Data flow

- **Flow\Pipeline** — Linear stage chain.
- **Flow\Continuum** — Middleware pattern (each stage calls `$next`).
- **Flow\Conveyor** — Command bus. `MiddlewareBus` dispatches DTOs to handlers by naming convention. Combines Pipeline + Continuum for before/after middleware.
- **Flow\River** — PSR-7 stream implementations.
- **Flow\Sequence** — Generic ordered iterables: `Cursor` (lazy, single-pass, self-closing) and `Series` (eager, multi-pass).

### HTTP and CLI transports

- **Hyper** — PSR-7 HTTP messages, PSR-15 middleware chain, response renderers (JSON, HTML, PlainText, Markdown, CSV, Empty), exception renderers, `FormatRegistry`, `Server`/`ServerAdapter`, lifecycle events (`RequestReceived`, `RequestHandled`, `RequestFailed`, `ResponseSent`), file upload handling.
- **Rune** — CLI infrastructure: `RuneKernel`, `Input` parsing, `Output`, built-in commands (`list`, `help`, `login`/`logout`, `make:command`, `make:query`, `make:page`, `make:middleware`, `cache:clear`, `cache:status`, `forge:models`, `validate:models`, `validate:handlers`, `db:status`, `migrate`, `migrate:rollback`, `migrate:status`, `migrate:create`). Lifecycle events parallel to Hyper.
- **Atlas** — Convention-based CQRS router for HTTP and CLI. `HttpRouter`, `CliRouter`, `ConventionResolver`, `PageDiscovery`, `MiddlewareDiscovery`, `UrlResolver`.
- **Ignition** — Bootstrap kernels (`HyperKernel`, `RuneKernel`), per-route middleware via `RouteDispatcher`, shared `Lifecycle` for event dispatch and exception reporting.

### Templating and output

- **Shodo** — Three-layer template architecture: `TemplateResolver` (DTO-to-path mapping), `TemplateEngine` (compile, cache, execute), and formatters. Five built-in compiler directives (`include`, `layout`, `match`, `csrf`, control flow). `HelperRegistry` with domain-scoped helpers via co-located `Helpers.php`. Status-specific template resolution (`{Dto}.{status}.{format}`). Bundled fallback templates.

### Persistence and state

- **Forge** — SQL files as first-class methods. `Connection`/`PdoConnection` (MySQL, PostgreSQL, SQLite), `ConnectionManager` with read/write split. `Model::__call` maps to `.sql` files. `@cast`/`@param` annotations. Streaming via `Cursor`. `ModelGenerator` for type-safe sub-models. **Migrations:** `Migrator`, `MigrationParser`, `MigrationRepository` with plain `.sql` files, `-- @migrate up/down` pragmas, timestamp versioning, checksum integrity, transactional by default.
- **Vault** — PSR-16 caching with five drivers (File, Array, Null, APCu, Redis). `CacheManager`, `PrefixedCache` decorator.
- **Session** — HTTP session management with configurable drivers (file, cookie, cache). `SessionMiddleware`, CSRF protection via `CsrfMiddleware` + `{{ csrf }}` directive.

### Identity and access

- **Auth** — Authentication (`SessionGuard`, `TokenGuard`, `CompositeGuard`) and authorization (`#[RequiresAuth]`, `#[RequiresRole]`, `#[RequiresPolicy]`). `AuthMiddleware` for HTTP, `CliAuthResolver` + `CliSession` for CLI.

### Validation, observability, throttling

- **Validation** — Attribute-based validation on DTO constructor params. 11 built-in rules. `ValidationGuard` Conveyor middleware.
- **Glitch** — Error/exception/shutdown handling. `HttpException` with full `StatusCode` enum. `ArcanumException` with `getTitle()`/`getSuggestion()` (RFC 9457 forward-compatible).
- **Quill** — Multi-channel PSR-3 logger over Monolog. `CorrelationProcessor` tags every log record with a `correlation_id` per request/command cycle.
- **Echo** — PSR-14 event dispatcher with stoppable propagation and mutable events.
- **Throttle** — Rate limiting with `TokenBucket` and `SlidingWindow` strategies. `Quota` value object with `X-RateLimit-*` headers.
- **Hourglass** — `Clock` interface (PSR-20), `SystemClock`, `FrozenClock`, `Interval` helper, `Stopwatch` with labeled instants and built-in marks for HTTP and CLI lifecycles.

### Testing

- **Testing** — `TestKernel` with shared container, `FrozenClock`, `ArrayDriver` cache, `ActiveIdentity`. `HttpTestSurface` and `CliTestSurface` for end-to-end dispatch. `Factory::make()` for DTO generation from validation attributes.

### Front-end integration

- **Htmx** — First-class htmx 4 support. `HtmxAwareResponseRenderer`, `HtmxRequest`, `ClientBroadcast` (domain events as `HX-Trigger` headers), `FragmentDirective` (`{{ fragment 'id' }}`), CSRF JS shim, auth-redirect middleware. Config via `config/htmx.php`.

### Framework-level logging

- Null-safe `$this->logger?->method()` instrumentation across HyperKernel, RuneKernel, HttpRouter, CliRouter, RouteDispatcher, AuthMiddleware, SessionMiddleware, RateLimiter, and Migrator.
- `CorrelationProcessor` ties all log lines from a single request/command to one ID.
- One INFO line per request (the "access log"); everything else is DEBUG or NOTICE.

### Documentation

- README for every package (23 packages).
- COMPENDIUM.md — guided tour of the framework's architecture, conventions, and design decisions.
- PLAN.md — completion plan with checklists, tenets, and lessons learned.

[0.1.0]: https://github.com/arcanum-org/framework/releases/tag/v0.1.0
