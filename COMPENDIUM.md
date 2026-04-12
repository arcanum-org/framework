# Arcanum Compendium

A guided tour of what Arcanum is, what's in it, and how the pieces fit together. Written for the framework's own author six months from now and for anyone building documentation from it.

> **Maintenance note.** If any framework functionality changes, is added, or is removed, this file MUST be updated to match. Out-of-date entries here are worse than no entry at all. PLAN.md carries the same note.

---

## What Arcanum is

Arcanum is an opinionated, batteries-included **CQRS PHP framework** for building websites and services that stay readable and manageable at scale. The defining stance: every read is a Query, every write is a Command, each one is its own DTO with its own handler — discovered by convention, validated by attribute, dispatched through middleware. Handlers stay tiny because the framework absorbs the ceremony.

It is **not** an MVC framework and **not** an ORM. It is built for developers who want fast handlers, real SQL, and a small mental model that doesn't grow with the codebase.

Targets PHP 8.4+. PHPStan level 9 throughout. Comprehensive test coverage.

---

## The shape of an Arcanum app

A typical request walks through the framework like this:

```
HTTP request
  → HyperKernel (lifecycle events, middleware stack)
    → Atlas Router (URL → Route + DTO class)
      → Hydrator (request data → typed DTO)
        → Conveyor MiddlewareBus (ValidationGuard, AuthorizationGuard, before-middleware)
          → Handler (the small function that returns the result)
        → Conveyor MiddlewareBus (after-middleware)
      → ResponseRenderer (Shodo formatter → HTTP response)
  → terminate (fastcgi_finish_request, deferred listeners)
```

The CLI mirrors it: `RuneKernel` parses argv, routes through `CliRouter`, hydrates the DTO, dispatches through the same Conveyor bus, and writes the result via `Output`.

A typical CQRS app folder layout:

```
app/
├── Domain/
│   └── Shop/
│       ├── Command/
│       │   ├── PlaceOrder.php          # the DTO with constructor params + attributes
│       │   └── PlaceOrderHandler.php   # __invoke(PlaceOrder $cmd): void
│       ├── Query/
│       │   ├── Products.php
│       │   ├── ProductsHandler.php
│       │   └── Products.html           # co-located template
│       ├── Helpers.php                 # domain-scoped template helpers
│       └── Middleware.php              # domain-scoped middleware
├── Pages/
│   ├── Index.html                      # template-driven routes, no handler needed
│   └── _Header.html                    # underscore prefix = partial (include-only, not routed)
├── Http/Kernel.php
└── Cli/Kernel.php
```

---

## Front-end defaults

Arcanum has opinions about the front end. They aren't required — you can swap either one out — but they're chosen deliberately and the framework invests in them over time.

**htmx for interactivity.** htmx composes naturally with CQRS: every action is already its own URL with its own handler, which is exactly what htmx wants on the wire. There's no parallel client-state model to keep in sync, no JSON API mirroring the page routes, no JavaScript framework to learn. The `Arcanum\Htmx` package provides first-class support: `HtmxAwareResponseRenderer` picks the rendering shape automatically (full page, content section, or auto-extracted element by id from `HX-Target`). `HtmxRequest` gives handlers typed access to htmx headers. `ClientBroadcast` projects domain events as `HX-Trigger` response headers for cross-component refresh. `FragmentDirective` adds `{{ fragment 'id' }}` markers for explicit innerHTML opt-in. CSRF protection via a lightweight JS shim, auth-redirect handling for 401/403, and `Vary: HX-Request` for cache safety. Targets htmx 4 directly (pinned at `4.0.0-beta1`, configurable via `config/htmx.php`). See `src/Htmx/README.md` for the full package reference.

**Tailwind for styling.** Tailwind utility classes are visible at the call site — no hidden semantics, no jumping into a `.scss` file to find out what `.btn-primary` actually does. That's friendly for humans skimming a template *and* friendly for AI coding agents reading the same template, because the styles are right there in the markup as plain text. The starter ships Tailwind via the CDN play script in development, with a build pipeline (`composer css:build` / `css:watch`) for production. A guardrail in `public/index.php` logs a warning if the production build is missing and the CDN is being served instead.

**What Arcanum doesn't do.** No bundling, hashing, or transpiling. Asset tooling is its own world and the JS ecosystem already does it well — Arcanum just emits the right `<link>` and `<script>` tags via `AppHelper::cssTags()` and trusts the build. Bring your own bundler if you want one.

**Swappable.** Both defaults are swappable. A team that wants Vue, React, or Alpine instead of htmx can remove the `Bootstrap\Htmx` bootstrapper and the three middleware from `config/middleware.php`; nothing in the framework requires them. Same for Tailwind — replace `AppHelper::cssTags()` with whatever your styling system needs and the rest of Arcanum doesn't care. The framework's job is the request lifecycle and the rendering boundary, not the front-end stack.

---

## The packages

23 framework packages, each with its own README. Grouped here by what they do.

### Foundation

- **Cabinet** — PSR-11 DI container. Services, factories, singletons, prototypes, decorators, middleware-on-services. Uses Codex for auto-wiring.
- **Codex** — Reflection-based class resolver. Recursively resolves constructor dependencies. Supports specifications (`when X needs Y give Z`).
- **Toolkit** — Small reusable utilities: `Strings` (case conversion, ASCII transliteration), `Random`, `Hex`, plus security primitives (`SodiumEncryptor`, `BcryptHasher`, `Argon2Hasher`, `SodiumSigner`).
- **Parchment** — Filesystem reader utilities.
- **Gather** — Typed key-value registries: `Configuration` (dot-notation), `Environment` (no serialize/clone), `IgnoreCaseRegistry` (HTTP headers).

### Data flow

- **Flow\Pipeline** — Linear stage chain (`object → object → object`).
- **Flow\Continuum** — Middleware pattern (each stage calls `$next`).
- **Flow\Conveyor** — Command bus. `MiddlewareBus` dispatches DTOs to handlers by naming convention (`PlaceOrder` → `PlaceOrderHandler`). Combines Pipeline + Continuum for before/after middleware.
- **Flow\River** — PSR-7 stream implementations.
- **Flow\Sequence** — Generic ordered iterables. `Sequencer<T>` interface; `Cursor<T>` (lazy, single-pass, self-closing) and `Series<T>` (eager, multi-pass).

### HTTP & CLI transports

- **Hyper** — PSR-7 HTTP messages, `HyperKernel`, response renderers (`Json`, `Html`, `Csv`, `PlainText`, `Markdown`, `EmptyResponseRenderer`, `JsonExceptionResponseRenderer`, `HtmlExceptionResponseRenderer`), `FormatRegistry`. Template-based renderers (`Html`, `PlainText`, `Markdown`) compose a `TemplateResolver` and resolve the template path before calling the formatter — resolution order: `resolveForStatus($dtoClass, $statusCode)` → `resolve($dtoClass)` → fallback. All renderers accept an optional `StatusCode` parameter for both the HTTP response and status-specific template lookup. `HtmlExceptionResponseRenderer` uses the same `TemplateEngine` as the success path — co-located error templates (`AddEntry.422.html`) and app-wide error templates (`errors/500.html`) both go through the engine. Error template variables: `$code`, `$title`, `$message`, `$errors` (validation), `$suggestion` (ArcanumException). Falls back to a built-in styled error page when no template exists. Lifecycle events: `RequestReceived` (mutable), `RequestHandled` / `RequestFailed` / `ResponseSent` (read-only).
- **Rune** — CLI infrastructure. `RuneKernel`, `Input` parsing, `Output`, `BuiltInRegistry`, `CliExceptionWriter`, `HelpWriter`. Lifecycle events: `CommandReceived`, `CommandHandled` / `CommandFailed` / `CommandCompleted` (all read-only) — parallel to Hyper's HTTP lifecycle events. Stopwatch marks: `command.received`, `command.handled`, `command.completed`. Built-in commands listed below.
- **Atlas** — Convention-based CQRS router. `HttpRouter`, `CliRouter`, `ConventionResolver`, `PageDiscovery`, `MiddlewareDiscovery`, `LocationResolver`, `UrlResolver`. Maps inputs to Query/Command namespaces; transport-agnostic core.
- **Ignition** — Bootstrap kernels. `HyperKernel` and `RuneKernel` extend the `Kernel` interface. Each runs a list of `Bootstrapper` classes once. Per-route middleware via `RouteDispatcher`. `Lifecycle` provides shared container-aware event dispatching and exception reporting for both kernels.
- **Shodo** — Output formatting with a clear three-layer architecture. **TemplateResolver** maps DTO classes to filesystem paths via PSR-4 convention; `resolveForStatus(dtoClass, statusCode, format)` resolves status-specific templates (co-located `{Dto}.{status}.{format}` → app-wide `errors/{status}.{format}` → null). **TemplateEngine** owns the mechanical render pipeline: compile (`TemplateCompiler`), cache (`TemplateCache`), execute. Four methods: `render()`, `renderFragment()` (content section only, no layout), `renderElement()` (element-by-id extraction), `renderSource()` (arbitrary source string). Handles lazy closure resolution — selective for partial renders, full for complete renders. **Formatters** (`HtmlFormatter`, `PlainTextFormatter`, `MarkdownFormatter`, `JsonFormatter`, `CsvFormatter`, `KeyValueFormatter`, `TableFormatter`) own data → variable conversion, escape function setup, and helper scoping; they receive a pre-resolved template path and delegate to the engine. When no template path is provided, bundled fallback templates (`src/Shodo/Templates/fallback.{html,txt,md}`) render generic data representations through the same engine — one rendering pipeline for everything. `TemplateCompiler` orchestrates registered `CompilerDirective` implementations in priority order via `compile()` and `compileFragment()`; five built-in directives: `IncludeDirective`, `LayoutDirective`, `MatchDirective`, `CsrfDirective`, `ControlFlowDirective`. Custom directives register through `DirectiveRegistry`. `CompilerContext` carries per-compile state and shared utilities (helper rewriting, file resolution, dependency tracking). Unknown `{{ keyword }}` patterns produce a clear `UnknownDirective` error. `HelperRegistry`, `HelperResolver` (domain-scoped via co-located `Helpers.php`), `HelperDiscovery`, plus the `#[WithHelper]` per-DTO attribute. Built-in helpers: `Html` (context-specific encoding: `url()` for URL scheme validation, `js()` for JavaScript string encoding, `attr()` for strict HTML attribute encoding, `css()` for CSS value encoding, plus `nonce()` and `classIf()`), `Csrf` (`field()` and `token()`, requires ActiveSession), `Format`, `Str`, `Arr`, `Route`.

### Persistence & state

- **Forge** — SQL files as first-class methods. `Connection` interface, `PdoConnection` (MySQL, PostgreSQL, SQLite), `ConnectionManager` with read/write split and domain mapping. `Model::__call` maps to `.sql` files with PHP named/positional/mixed args. `@cast` (int, float, bool, json) and `@param` annotations. `SqlScanner` for comment/string-aware parsing. Streaming via `Sequencer`/`Cursor`; writes return immutable `WriteResult`. `ModelGenerator` produces type-safe sub-model classes. **Migrations:** `Migrator`, `MigrationParser`, `MigrationRepository` under `Forge\Migration`. Plain `.sql` files with `-- @migrate up` / `-- @migrate down` pragmas. Timestamp-based versioning, checksum integrity validation, transactional by default (`-- @transaction off` opt-out). CLI: `migrate`, `migrate:rollback`, `migrate:status`, `migrate:create`.
- **Vault** — PSR-16 caching. Five drivers (`File`, `Array`, `Null`, `APCu`, `Redis`). `CacheManager` for named stores. `PrefixedCache` decorator. Framework caches (config, templates, pages, middleware, helpers) all live on Vault.
- **Session** — HTTP session management. Configurable drivers (file, cookie, cache). `ActiveSession` request-scoped holder. `SessionMiddleware` handles start/save/cookie. CSRF protection via `CsrfMiddleware` + `{{ csrf }}` directive.

### Identity & access

- **Auth** — Authentication and authorization. `Identity` interface, `SimpleIdentity`, `ActiveIdentity`. Guards: `SessionGuard`, `TokenGuard`, `CompositeGuard`. `AuthMiddleware` (HTTP), `CliAuthResolver` (CLI), `Prompter`, `CliSession` (encrypted file store). DTO authorization attributes: `#[RequiresAuth]`, `#[RequiresRole]`, `#[RequiresPolicy]`, plus the `Policy` interface. `AuthorizationGuard` runs them as Conveyor middleware.

### Validation, observability, throttling

- **Validation** — Attribute-based validation on DTO constructor params. 11 built-in rules: `NotEmpty`, `MinLength`, `MaxLength`, `Min`, `Max`, `Email`, `Pattern`, `In`, `Url`, `Uuid`, `Callback`. `ValidationGuard` Conveyor middleware fires before handlers. Custom rules via the `Rule` interface.
- **Glitch** — Error/exception/shutdown handling. `ExceptionHandler`, `ExceptionRenderer`, `HttpException` with the full `StatusCode` enum. `ArcanumException` interface gives every framework exception a `getTitle()` and `getSuggestion()` (RFC 9457 forward-compatible).
- **Quill** — Multi-channel PSR-3 logger over Monolog. `ChannelLogger` for named channels.
- **Echo** — PSR-14 event dispatcher. Uses Flow Pipeline internally. Stoppable propagation, mutable events.
- **Throttle** — Rate limiting. `Throttler` interface, `TokenBucket` and `SlidingWindow` strategies, `RateLimiter`, `Quota` value object with `isAllowed()` and `headers()` (X-RateLimit-* + Retry-After).
- **Testing** — Test harness for app developers. `TestKernel` builds a shared Cabinet container with a `FrozenClock` (pinned at `2026-01-01`), an `ArrayDriver` cache, and an `ActiveIdentity`, then exposes lazy `http()` / `cli()` surfaces that wrap real `HyperKernel` / `RuneKernel` instances bootstrapping against that same container — so cross-transport tests share state and `actingAs($identity)` set on the parent kernel is visible from both. `HttpTestSurface` translates fluent `get/post/put/patch/delete` calls into real PSR-7 `ServerRequest`s with persistent `withHeader()`; `CliTestSurface::run(argv)` returns a `CliResult` (`exitCode`, `stdout`, `stderr`) backed by a fresh `BufferedOutput` per call. Both surfaces support fixture-handler injection (`setCoreHandler` / `setRunner`) for round-trip dispatch tests. `Factory::make($class, $overrides)` produces valid DTOs by composing `Codex\Hydrator` and synthesizing values from validation attributes for any constructor parameter without an override or default — recurses into nested DTOs, throws `FactoryException` (an `ArcanumException`) on `#[Pattern]`/`#[Callback]` with a "provide an override" hint. The internal kernels use empty bootstrappers lists so they don't re-run the production chain and stomp the test bindings; future production-bootstrapper opt-in (`withDatabase()`, etc.) is a builder-shape question for later.
- **Htmx** — First-class htmx 4 support. `HtmxAwareResponseRenderer` picks the rendering shape (full page, content section, element-by-id extraction). `HtmxRequest` decorator with typed accessors for all htmx headers. `ClientBroadcast` interface projects domain events as `HX-Trigger` headers; `EventCapture` + `HtmxEventTriggerMiddleware` wire them together. `FragmentDirective` adds `{{ fragment 'id' }}` for explicit innerHTML opt-in via the Shodo `CompilerDirective` system. `HtmxRequestMiddleware` (request context, Vary header, CSRF endpoint), `HtmxAuthRedirectMiddleware` (401/403 → `HX-Location`), `HtmxCsrfController` (JS shim), `HtmxHelper` (`Htmx::script()`, `Htmx::csrf()`). `HtmxResponse` immutable builder, `HtmxLocation` value object. Config via `config/htmx.php`.
- **Hourglass** — Time primitives. `Clock` interface extending PSR-20, `SystemClock`, `FrozenClock` (caller-controlled pinned clock — useful for replay, batch jobs, simulations, deterministic tests). `Bootstrap\Hourglass` registers `Clock::class → SystemClock` so any class taking a `Clock` constructor argument gets the wall-clock implementation auto-wired in production; tests pass a `FrozenClock` directly. **Adopted by Vault `ArrayDriver`/`FileDriver` for TTL math, Throttle `TokenBucket`/`SlidingWindow` for window math, and Auth `CliSession` for expiry** — those packages no longer call `time()` directly. `Interval` is a small static helper (`secondsIn(\DateInterval): int`, `ofSeconds(int): \DateInterval`) used by all four Vault drivers to normalize PSR-16 `DateInterval|int|null` TTLs into seconds — eliminates duplicated epoch-anchor incantations and pins down the documented `P1M`→31d / `P1Y`→365d behavior in one place. `Stopwatch` records labeled `Instant`s across the process lifetime. Built-in marks — HTTP: `arcanum.start`, `boot.complete`, `request.received`, `handler.start`/`complete`, `render.start`/`complete`, `request.handled`, `response.sent`, `arcanum.complete`. CLI: `arcanum.start`, `boot.complete`, `command.received`, `handler.start`/`complete`, `command.handled`, `command.completed`, `arcanum.complete`. Static accessor `Stopwatch::tap()` is write-only and no-ops when uninstalled; `Stopwatch::current()` throws when uninstalled (read sites should fail loudly). Stopwatch deliberately does *not* go through Clock — they model different things. Clock answers "what time is it?" (fakeable wall-clock now), Stopwatch answers "how much time has passed?" (elapsed-time telemetry). Conflating them would mean a test that froze Clock to assert TTL behavior would also freeze Stopwatch, hiding the real elapsed time the test wants to measure.

---

## Conventions that hold it all together

Arcanum's small mental model comes from a handful of conventions that show up everywhere. Learn these once and most of the framework explains itself.

### Naming derives behavior

- `Foo` DTO → `FooHandler` class. The Conveyor bus finds the handler by appending `Handler` to the DTO's class name.
- `app/Domain/Shop/Query/Products.php` → URL `/shop/products`. Atlas walks the namespace; PSR-4 paths become URL paths via PascalCase → kebab-case.
- `Products.php` (Query DTO) → `Products.html` (template) co-located in the same directory. Shodo finds the template by file extension next to the DTO.
- `Products.html` → also `Products.json`, `Products.csv`, `Products.md`, `Products.txt`. Same handler, five formatters, picked by URL extension.
- `_guestbook-form.html` — files starting with `_` are **partials**: include-only templates skipped by `PageDiscovery`, reachable via `{{ include }}`. Works in `app/Pages/`, `app/Templates/`, or any template directory.
- `AddEntry.422.html` — **status-specific template** co-located with the DTO. Resolution chain: co-located `{Dto}.{status}.{format}` → app-wide `app/Templates/errors/{status}.{format}` → framework default. Works for any status code and format (`PlaceOrder.201.html`, `Health.500.json`). Error templates receive `$code`, `$title`, `$message`, `$errors` (validation), `$suggestion` (ArcanumException). For htmx requests, error templates render as fragments (no layout).

### What's inside `{{ }}`

Shodo templates have one rule for what's inside the delimiters, distinguished by the first character:

| Starts with | Meaning | Example |
|---|---|---|
| `$` | Variable expression | `{{ $name }}`, `{{ $user->email }}` |
| Uppercase letter | Helper call | `{{ Route::url('home') }}` |
| Lowercase keyword | Directive | `{{ if $foo }}`, `{{ csrf }}`, `{{ extends 'layout' }}` |

The body of `{{ }}` is an arbitrary PHP expression. Helper calls inside it are rewritten recursively, so array access, method chains, ternaries, null coalesce, and nested helpers all compose.

### Attributes carry metadata

- `#[NotEmpty]`, `#[Email]`, `#[MinLength(8)]`, etc. on DTO constructor params → `ValidationGuard` rejects invalid input with 422.
- `#[RequiresAuth]`, `#[RequiresRole('admin')]`, `#[RequiresPolicy(Foo::class)]` on DTO classes → `AuthorizationGuard` rejects unauthorized requests with 401/403.
- `#[AllowedFormats('json', 'csv')]` on a Query DTO → 406 Not Acceptable for any other format.
- `#[WithHelper(EnvCheckHelper::class, alias: 'Env')]` on a DTO → that helper is available in the DTO's template under that alias.
- `#[CliOnly]` / `#[HttpOnly]` → `TransportGuard` rejects the DTO from the wrong transport.

### Convention-based discovery

Two file conventions Arcanum picks up automatically. They look similar but the discovery rules are **not the same** — read carefully.

**`Helpers.php`** has two distinct loading mechanisms:

- `app/Helpers/Helpers.php` — a hardcoded special path. `Bootstrap\Helpers` loads it explicitly and registers everything in it as a **global** helper, available to every template including Pages. This is the file the starter ships and how an `App` alias gets registered for use across the app.
- `app/Domain/<Anything>/Helpers.php` — discovered by `HelperDiscovery`, which walks the `app/Domain/` subtree only. The file's path becomes a namespace prefix, so `app/Domain/Shop/Helpers.php` applies to DTOs under `App\Domain\Shop\*`. Deeper directories override shallower ones (`Checkout/Helpers.php` wins over `Shop/Helpers.php` for `Checkout` DTOs).
- **Pages cannot have their own scoped `Helpers.php`** via discovery — `HelperDiscovery` only walks `app/Domain/`. Pages get the global helpers from `app/Helpers/Helpers.php`, plus whatever they declare via `#[WithHelper]` on the Page DTO class itself. For per-DTO helpers, `#[WithHelper]` is the right tool — see the welcome page's `Index.php` for an example.

**`Middleware.php`** uses a different rule. `MiddlewareDiscovery` walks all of `app/` (not just `app/Domain/`), so the conventional namespace-prefix scoping works in any directory:

- `app/Middleware.php` — applies to every DTO under `App\*`, including Pages.
- `app/Domain/Shop/Middleware.php` — applies only to `App\Domain\Shop\*`.
- `app/Pages/Middleware.php` — applies only to Pages.

Returns a list of middleware class-strings rather than an alias → class map.

> **Note on the asymmetry.** That `Helpers.php` has a special hardcoded global path while `Middleware.php` uses uniform namespace-prefix discovery is a design wart, not an intentional distinction. Tracked under PLAN.md long-distance future as something to align — the natural fix is to teach `HelperDiscovery` to walk `app/` like `MiddlewareDiscovery` does, then drop the special path in `Bootstrap\Helpers`.

### HTTP status codes are part of the API

Arcanum embraces the full status code spectrum. Commands return `EmptyDTO` (204), `AcceptedDTO` (202), or a Query DTO that becomes 201 Created with a Location header. Wrong HTTP method = 405 (not 404). Unsupported format = 406 (not 400). Validation failure = 422. Rate limit = 429.

---

## The CLI surface (`bin/arcanum`)

The starter app's CLI entry point. Built-in commands shipped by Rune:

| Command | What it does |
|---|---|
| `list` | Show all available commands |
| `help <command>` | Show parameter info for a command |
| `login` / `logout` | CLI session auth |
| `make:key` | Generate an APP_KEY |
| `make:command <name>` | Scaffold a Command DTO + handler |
| `make:query <name>` | Scaffold a Query DTO + handler + template |
| `make:page <name>` | Scaffold a Page DTO + template |
| `make:middleware <name>` | Scaffold a middleware class |
| `cache:clear` | Clear all framework caches and Vault stores. `--store=NAME` targets one Vault store. |
| `cache:status` | Show cache configuration and current state |
| `forge:models` | Generate Forge model classes from `.sql` files |
| `validate:models` | Verify generated Forge models are up to date |
| `validate:handlers` | Verify every Command/Query DTO has a registered handler |
| `db:status` | Show database connection status |
| `migrate` | Run all pending database migrations |
| `migrate:rollback` | Revert the most recent migration(s). `--step=N` for multiple. |
| `migrate:status` | Show applied and pending migrations |
| `migrate:create <name>` | Scaffold a new migration file with timestamp |

App developers add their own commands by defining DTOs under `app/Cli/Command/` (or wherever `app.namespace` points) and the same Conveyor bus dispatches them.

---

## Testing today

The framework itself uses PHPUnit 13 with the `#[CoversClass]` attribute on every test class (strict coverage required). Tests mirror `src/` structure: `src/Hyper/Headers.php` → `tests/Hyper/HeadersTest.php`. Arrange-Act-Assert pattern throughout.

What apps get **today** for testing their own code: the `Testing` package. `TestKernel` plus `Factory` plus `HttpTestSurface` / `CliTestSurface` cover the common cases — handler dispatch through a real kernel, deterministic time via `FrozenClock`, identity via `actingAs()`, in-memory cache via `ArrayDriver`, fixture DTO generation that respects validation attributes. Migration of the existing ad-hoc patterns (`tests/Integration/CqrsLifecycleTest`, `CapturingKernel`, the starter app's `FixtureKernel`) onto `TestKernel` is the next step in the arc — see PLAN.md.

---

## Where to read next

- **`PLAN.md`** — what's been built, what's coming, and the load-bearing decisions distilled into tenets.
- **`src/<Package>/README.md`** — the authoritative reference for each package. Cabinet, Codex, Atlas, Forge, Shodo, Hourglass, and the rest each have their own.
- **[arcanum-org/arcanum](https://github.com/arcanum-org/arcanum)** (the starter app) — getting-started guide, quick-start, directory conventions.
