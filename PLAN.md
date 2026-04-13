# Framework Completion Plan

> **COMPENDIUM.md must stay in sync.** Any time framework functionality changes, is added, or is removed — new packages, renamed classes, dropped features, new CLI commands, new attributes, new built-in marks, anything a user-facing tour would mention — update `COMPENDIUM.md` in the same change. It's the source of truth for the eventual documentation site, and an out-of-date entry there is worse than no entry. Treat it like committing: not a follow-up, part of done.

---

## On Deck (needs planning and checklists)

These are the highest-impact items from the backlog. Each needs to be broken into a walkable checklist before work begins.

1. **Logging instrumentation** — details in checklist below.
2. **Todo App dogfood** — details in checklist below.

3. **Hyper README** — details in checklist below.
4. **PSR-18 HTTP Client** — details in checklist below.
5. **Integration test coverage** — details in checklist below.

---

### Logging instrumentation

Pre-1.0 blocker. The framework is silent in production — no request lifecycle, no routing decisions, no auth outcomes. Fix this by injecting `?LoggerInterface $logger = null` progressively across the stack, starting with the HTTP lifecycle and expanding outward.

**Principles:**

- **Log decisions, not data.** "Route resolved via convention" yes; request body, session contents, SQL results no. **Never log:** session IDs, auth tokens, passwords, request/response bodies. Rate-limit keys (typically IPs) are the app's responsibility to hash or truncate for GDPR — the framework logs the key it's given.
- **Null means silent.** `$this->logger?->method()` short-circuits when no logger is configured — zero allocation, zero cost.
- **One INFO line per request.** The "access log" is a single INFO entry when the request completes (method, path, status code). Everything else is DEBUG or NOTICE.
- **Correlate with a correlation ID.** Generate a short random ID at the start of `handle()` and include it as `'correlation_id'` in every log record's extra data via a Monolog processor. Without this, interleaved DEBUG lines from concurrent requests are useless. Implement as a `CorrelationProcessor` registered in Bootstrap\Logger so it applies to all channels automatically.
- **Exceptions are already handled.** Glitch `LogReporter` already logs exceptions at ERROR/CRITICAL via channel routing. Don't duplicate — the kernel logs lifecycle, Glitch logs failures.
- **Apps can bring their own logger.** The `LoggerInterface` binding uses a `has()` guard — if the app registered a custom PSR-3 implementation before Bootstrap\Logger runs, the framework respects it.
- **All code changes must pass `composer check`.** Every item must pass cs-fix, cs-check, phpstan, and phpunit before commit.

**Log levels:**

| Level | When | Examples |
|-------|------|---------|
| DEBUG | Resolution/lookup details, dev troubleshooting | Route resolved, session loaded, no identity, dispatching handler, command resolved |
| INFO | Lifecycle milestones, the "access log" | Request handled (status), command completed (exit code), migration applied, identity resolved |
| NOTICE | Noteworthy but expected conditions | Rate limit exceeded, session regenerated/invalidated, 405 method not allowed, 406 not acceptable |
| WARNING | Integrity issues | Migration checksum mismatch |
| ERROR | Runtime failures | Migration execution failed |

**Architecture notes:**

- **Factory-registered classes** (HttpRouter, AuthMiddleware): constructed with `new` in bootstrap closures, but the closures execute lazily at request time — after Logger bootstraps. Update each factory to resolve and pass the logger from the container.
- **PSR-15 middleware** (SessionMiddleware): resolved from the container by `HttpMiddleware` at dispatch time. Adding `?LoggerInterface` to the constructor is sufficient — Codex auto-wires it.
- **Conveyor Progressions** (AuthorizationGuard, ValidationGuard): constructed with `new` in Routing/CliRouting bootstrappers before Logger runs. Their rejections surface as exceptions that Glitch already reports. Skip — low logging value, high wiring cost.
- **Kernels** (HyperKernel, RuneKernel): constructed before the container exists. Resolve the logger lazily from the container after bootstrap.

#### Checklist

- [x] **Bootstrap\Logger — bind `LoggerInterface` + correlation processor** — Add `$container->service(LoggerInterface::class, QuillLogger::class)` with a `has()` guard so apps can bring their own PSR-3 implementation. Register a `CorrelationProcessor` (generic Monolog processor) that adds `'correlation_id'` to every log record's extra (generated once per kernel handle cycle). This is the prerequisite for all Codex auto-wiring of `?LoggerInterface` params and for correlating log lines across concurrent requests.
- [x] **HyperKernel + RuneKernel — lifecycle logging** — Resolve logger from container after bootstrap. Generate request/command ID and set on processor. HyperKernel: DEBUG `Request received` (method, path) at handle start; INFO `Request handled` (method, path, status) after response built. RuneKernel: DEBUG `Command received` (name) at handle start; INFO `Command completed` (name, exit code) after dispatch. Tests with mock logger via `expects()`.
- [x] **HttpRouter — route resolution logging** — Add `?LoggerInterface $logger = null` to constructor. Update factory in Bootstrap\Routing to pass logger from container. DEBUG `Route resolved` (type: custom/page/convention, DTO class, format). DEBUG `Route not found` before 404. NOTICE `Method not allowed` (path, method, allowed methods) before 405. NOTICE `Format not acceptable` (format, allowed) before 406. Tests for each log path + null logger.
- [x] **RouteDispatcher — dispatch logging** — Add `?LoggerInterface $logger = null` to constructor. DEBUG `Dispatching` (DTO class, handler prefix, before/after middleware count). Tests with mock logger.
- [x] **CliRouter — command routing logging** — Add `?LoggerInterface $logger = null` to constructor. Update factory in Bootstrap\CliRouting to pass logger. DEBUG `Command resolved` (type, DTO class). DEBUG `Command not found` (input name, suggestions if any). Tests.
- [x] **AuthMiddleware — authentication logging** — Add `?LoggerInterface $logger = null` to constructor. Update factory in Bootstrap\Auth to pass logger. INFO `Identity resolved` (guard type: session/token/composite — never identity details, never tokens). DEBUG `No identity resolved` (unauthenticated, not an error). Tests.
- [x] **SessionMiddleware — session lifecycle logging** — Add `?LoggerInterface $logger = null` to constructor (Codex auto-wires, no factory change needed). DEBUG `Session started` (new vs loaded — never log session ID). DEBUG `Session saved`. NOTICE `Session regenerated`. NOTICE `Session invalidated`. Tests.
- [x] **RateLimiter — throttle logging** — Add `?LoggerInterface $logger = null` to constructor. NOTICE `Rate limit exceeded` (key, limit, retry-after). DEBUG `Rate check passed` (key, remaining). Tests.
- [x] **Migrator — migration logging** — Add `?LoggerInterface $logger = null` to constructor. INFO `Migration applied` (filename, direction, elapsed ms). INFO `Migration rolled back` (filename, elapsed ms). WARNING `Checksum mismatch` (filename, expected, actual). ERROR `Migration failed` (filename, error). Tests.
- [x] **Harmonize existing log sites** — Review and align the three existing logging patterns: MiddlewareBus (debug-gated `->warning()`), TemplateEngine (null-safe `?->warning()`), Bootstrap\Routing (`has()` + explicit get). Standardize on null-safe `?->` with consistent context keys. Note: the starter app's `RequestLogger` listener becomes redundant once kernel lifecycle logging lands — document this in the starter app's README or remove the listener.
- [x] **COMPENDIUM.md + Quill README — document logging** — Add a "Framework logging" section to the COMPENDIUM describing what the framework logs, at what levels, the request ID correlation mechanism, and how to bring your own PSR-3 logger. Update the Quill README if needed.

### Todo App dogfood

Two parallel builds of the same Todo app, each from a different starting point. The Todo app is the vehicle; the journals and retrospective are the deliverables. **No framework changes unless there's a complete blocker** — record friction, don't fix it mid-flight.

**The Todo app (same features both times):**

- SQLite via Forge — `users`, `task_lists`, `tasks` tables via migrations
- Session auth — login page, session guard, `#[RequiresAuth]` on todo routes
- Task list CRUD — create, delete lists
- Task CRUD — add, toggle completion, delete tasks
- htmx front-end — no full page reloads for task operations, `ClientBroadcast` for count refresh
- Filter tasks — all / active / completed via htmx
- Vault caching — task counts per list, invalidated on mutations
- Tailwind styling, validation feedback on forms

**What to record in each `JOURNAL.md`:**

- Every step taken, in order
- Rough time per step
- Pain points — unclear docs, missing features, confusing conventions, boilerplate
- Wins — things that "just worked," elegant patterns, small mental model
- Blockers — framework bugs, missing functionality, workarounds needed
- Missing scaffolding — `make:*` commands that should exist but don't (e.g., `make:seed`, `make:event`, `make:model`)
- Config mysteries — settings that were hard to discover or required reading framework source
- Error messages — which ones helped, which ones didn't
- Testing DX — was `TestKernel` / `HttpTestSurface` / `Factory` obvious? Did `actingAs()` work? What was missing?
- CSRF experience — did `CsrfMiddleware` + `{{ csrf }}` + htmx JS shim "just work" or require debugging?

#### Checklist

- [ ] **Build A: clone starter** — Clone `../arcanum/` into `../todo-from-starter/`. Strip the guestbook domain, welcome page helpers, and demo-specific code. Keep the infrastructure (kernels, config, layout, bootstrap, migrations setup). Record every step in `JOURNAL.md`.
- [ ] **Build A: build the todo app** — Schema, auth, Forge models, commands, queries, htmx templates, filtering, caching. Note the data seeding problem: migrations are SQL-only, so seeding a user with `password_hash()` requires a workaround (pre-computed hash in SQL, PHP seed script, or custom CLI command). Journal whatever solution you use and whether the framework should ship a seeder mechanism. Write a few tests using `TestKernel` / `HttpTestSurface` / `Factory` to exercise the Testing package's DX. Commit working features incrementally. Continue journaling: every pain point, every win, every workaround, every "I had to read framework source to figure this out."
- [ ] **Build B: from scratch** — New directory `../todo-from-scratch/`. `composer init`, `composer require arcanum-org/framework` (first test: is the package discoverable? is it on Packagist? does the README explain installation?). Create `public/index.php`, `bin/arcanum`, config files, kernels, `.env`, directory structure — everything from zero. Journal every step, especially: which config files were needed and how you discovered the required keys, what happened when a config file was missing (helpful error or cryptic crash?), which bootstrappers were required and in what order.
- [ ] **Build B: build the todo app** — Same features as Build A, including tests with the Testing package. Journal everything, especially: which steps were harder without the starter's scaffolding, which conventions took trial-and-error to discover, which error messages pointed to the fix vs. which were opaque.
- [ ] **Retrospective** — Compare both journals. Write `RETROSPECTIVE.md` (in this repo) covering: (1) framework features that worked well, (2) framework features that were hard to use or missing, (3) DX gaps — missing docs, unclear conventions, missing scaffolding, unhelpful errors, (4) ecosystem gaps — what a new user can't figure out without reading framework internals, (5) Testing package DX — was the test harness usable, what was missing. Extract concrete items and add to PLAN.md.

### Hyper README

The only core package without a README. Hyper is the biggest package — PSR-7 messages, URI handling, file uploads, response renderers, exception renderers, format registry, middleware chain, lifecycle events, server adapter. The audience is a junior developer exploring the framework to learn; lead with "what happens when an HTTP request arrives" and work outward from there.

#### Sections to cover

1. **What Hyper does** — one paragraph: PSR-7 HTTP messages, response rendering, middleware chain, lifecycle events. Hyper handles the HTTP boundary so the rest of the framework stays transport-agnostic.
2. **"You probably won't import this"** — Set expectations immediately: in most Arcanum apps, handlers never touch Hyper directly. You return a DTO, and the rendering pipeline produces the HTTP response. Hyper matters when you're writing middleware, custom renderers, or debugging the request lifecycle. This prevents a junior dev from thinking they need to construct Response objects in handlers.
3. **The request journey** — visual flow from raw PHP superglobals → `Server` → `ServerRequest` → middleware → handler → `ResponseRenderer` → `Response` → sent to client. Anchor the reader before diving into classes. Call out `Server` explicitly — it's what `public/index.php` actually uses to parse the request.
4. **PSR-7 messages** — Request, ServerRequest, Response, Message, Headers. Show how to read headers, query params, parsed body. Explain the immutable `with*()` pattern for anyone who hasn't seen PSR-7 before.
5. **URI** — URI class parses and builds URIs. Show `new URI('https://...')`, the `with*()` methods for each component. Mention the component value objects (Scheme, Host, Path, etc.) exist but most code just uses `URI` directly.
6. **Status codes** — The `StatusCode` enum and why Arcanum uses it instead of raw integers. Show `StatusCode::NotFound`, `$status->reason()`. Link to the COMPENDIUM's "HTTP status codes are part of the API" philosophy.
7. **File uploads** — `UploadedFile`, `UploadedFiles::fromSuperGlobal()`. Show how to access files in a handler. Mention the `Error` enum for upload error codes.
8. **Response renderers** — The 6 renderers (Json, Html, PlainText, Markdown, Csv, Empty). How `FormatRegistry` maps URL extensions to renderers. How template-based renderers compose `TemplateResolver` + `TemplateEngine` (link to Shodo README). The status-specific template resolution chain. Show a handler returning data and the renderer turning it into a response.
9. **Exception renderers** — `JsonExceptionResponseRenderer`, `HtmlExceptionResponseRenderer`, `ValidationExceptionRenderer` (decorator that adds 422-specific handling). How exceptions become proper error responses. Debug vs production mode. The htmx fragment fallback for error templates. Link to Glitch README.
10. **Format registry** — How `FormatRegistry` works, how 406 Not Acceptable happens, how to register custom formats in `config/formats.php`.
11. **Middleware** — `HttpMiddleware` builds a PSR-15 middleware chain. Show the onion model. `CallableHandler` wraps closures as handlers. The `Options` middleware auto-responds to OPTIONS with the `Allow` header.
12. **Lifecycle events** — `RequestReceived` (mutable — listeners can enrich the request), `RequestHandled` / `RequestFailed` / `ResponseSent` (read-only). Show a listener example. Link to Echo README.
13. **Server and ServerAdapter** — `Server` composes the `PHPServerAdapter` to build PSR-7 objects from `$_SERVER`, `$_GET`, `$_POST`, `$_FILES`, `$_COOKIE`. Explain why the adapter exists (testability — swap for a mock in tests). This is the bridge between PHP's superglobals and the PSR-7 world.
14. **#[HttpOnly] attribute** — marks DTOs as HTTP-only, rejected in CLI by TransportGuard.

#### Checklist

- [x] **Hyper README — request journey and PSR-7** — Sections 1–7: overview, "you probably won't import this" framing, request flow diagram with Server callout, PSR-7 messages, URI, status codes, file uploads. Cross-link to Shodo, Glitch, Echo READMEs where referenced. Enough for a reader to understand how requests arrive and what they look like inside the framework.
- [x] **Hyper README — rendering and middleware** — Sections 8–14: response renderers, exception renderers (including ValidationExceptionRenderer decorator), format registry, middleware chain, lifecycle events, server + adapter, #[HttpOnly]. Cross-link to related package READMEs. Completes the "response side" of the journey.

### PSR-18 HTTP Client

Any real app needs outgoing HTTP requests (APIs, OAuth, webhooks). Wrap an established library behind PSR-18 `ClientInterface` and provide PSR-17 factories over Hyper's existing PSR-7 classes. Ship a test mock so apps can assert outgoing calls without hitting the network.

**Architecture decisions:**

- **PSR-17 factories over Hyper classes.** `RequestFactory` composes `Message` + `Request` + `URI`. `ResponseFactory` composes `Message` + `Response`. `UriFactory` delegates to `new URI($string)`. `StreamFactory` needs a PSR-7 `StreamInterface` implementation — River's `Stream` has every required method but doesn't declare `implements StreamInterface`. **Decision: extend River\Stream to implement the interface.** One `implements` addition makes all of River PSR-7 compatible with no adapter overhead.
- **PSR-18 client wraps Guzzle, but Guzzle is optional.** Guzzle is currently a dev/transitive dependency, not a production one. The framework must not force every app to install Guzzle. Instead: `composer.json` adds Guzzle as a `suggest`, `Bootstrap\HttpClient` checks `class_exists(GuzzleHttp\Client::class)` before registering. Apps that need outgoing HTTP do `composer require guzzlehttp/guzzle`. Apps that don't pay nothing. The wrapper maps Guzzle exceptions to PSR-18 `NetworkException`/`RequestException`.
- **Convenience methods alongside PSR-18.** `sendRequest(RequestInterface)` is the PSR-18 contract. The wrapper also provides `get($url, $headers)`, `post($url, $body, $headers)`, etc. that build the request internally — less verbose for common cases.
- **TestClient for app testing.** Records outgoing requests, returns pre-configured responses. Registered in the container during tests. Same pattern as `FrozenClock` and `ArrayDriver` — deterministic, no side effects.
- **Lives at `src/Hyper/Client/`** as a sub-namespace of Hyper, since it builds on the same PSR-7 classes.

**Security:**

- **TLS verification must default to on.** The Guzzle wrapper must never disable SSL verification by default. If `config/http.php` exposes a `verify` option, it defaults to `true`. The README warns against disabling in production.
- **Sensitive header scrubbing in logs.** When logging integration lands, outgoing request logging must strip `Authorization`, `Cookie`, `X-Api-Key`, and other sensitive headers from log context by default. Configurable blacklist.

#### Checklist

- [ ] **River\Stream — implement `StreamInterface`** — Add `implements StreamInterface` to `Flow\River\Stream`. It already has every method; this is a declaration, not a rewrite. Adjust any signature mismatches. Update River tests. This unblocks PSR-17 StreamFactory.
- [ ] **PSR-17 factories** — `RequestFactory`, `ResponseFactory`, `UriFactory`, `StreamFactory` over Hyper's existing PSR-7 classes. Add `psr/http-factory` as a direct dependency. Tests for each factory.
- [ ] **PSR-18 client** — `HttpClient implements ClientInterface` wrapping Guzzle. Constructor takes Guzzle `ClientInterface` (or builds a default if Guzzle is installed). Convenience methods: `get()`, `post()`, `put()`, `patch()`, `delete()`. Maps Guzzle exceptions to PSR-18 `NetworkException`/`RequestException`. Add `psr/http-client` as a direct dependency; add `guzzlehttp/guzzle` as a `suggest`. Config via `config/http.php` (base_uri, timeout, headers, verify defaults to `true`). Tests with Guzzle mock handler.
- [ ] **TestClient** — `TestClient implements ClientInterface` for app tests. `addResponse(ResponseInterface)` queues responses. `assertSent(callable)` checks recorded requests. `assertNothingSent()`. Register in container when `TestKernel` is used. Tests.
- [ ] **Bootstrap registration** — `Bootstrap\HttpClient` registers factories and client in the container. PSR-17 interfaces bound to Hyper factories. PSR-18 `ClientInterface` bound to the Guzzle wrapper only if `class_exists(GuzzleHttp\Client::class)` — skip gracefully otherwise. `has()` guards so apps can bring their own. Sensitive header scrubbing list for future logging integration (`Authorization`, `Cookie`, `X-Api-Key`).
- [ ] **README** — What it does, when to use it, sending requests (PSR-18 and convenience methods), handling responses, testing with TestClient, configuration, TLS verification warning, swapping implementations.

### Integration test coverage

Only 2 integration tests exist today (`CqrsLifecycleTest`, `HelperResolutionTest`). Unit tests miss interaction bugs: discovery ordering, bootstrap sequencing, round-trip rendering, lifecycle event flow. `TestKernel` already provides `HttpTestSurface` and `CliTestSurface` — expanding coverage is cheap. This is also a long-tail principle: every new feature should land with at least one integration test.

**What exists today:**
- `CqrsLifecycleTest` — HTTP request → routing → hydration → dispatch → render (JSON/CSV/HTML), command 204/201, per-route middleware.
- `HelperResolutionTest` — global + domain-scoped helpers through real bootstrappers.
- `TestKernel` — shared container with FrozenClock, ArrayDriver cache, ActiveIdentity, lazy HTTP/CLI surfaces, `actingAs()`.

**What's missing:**

| Gap | Why it matters |
|-----|----------------|
| CLI lifecycle | Zero CLI integration tests. Routing, dispatch, output formatting, exit codes untested end-to-end. |
| htmx rendering | HX-Request header → fragment extraction is a key user-facing path with no integration test. |
| Error responses | 404, 405, 406, 422, 500 across JSON and HTML formats — only unit-tested in isolation. |
| Session + auth | Session middleware → auth middleware → AuthorizationGuard flow never tested as a chain. |
| Lifecycle events | Events fire in unit tests, but never verified in a full middleware → handler → render pass. |
| Template rendering | HTTP request → TemplateResolver → TemplateCompiler → TemplateEngine → response never tested end-to-end. |

**Fixture convention:** Integration test fixtures (DTOs, handlers, templates, middleware) live in `tests/Integration/Fixture/`, mirroring the pattern established by `CqrsLifecycleTest`. Some tests (SessionAuth, Template) require real middleware chains — use `TestKernel` with selective bootstrapper setup or manual wiring as needed.

#### Checklist

- [ ] **CliIntegrationTest** — CLI routing → hydration → dispatch → output rendering → exit codes. Cover: convention-based command, query with formatted output, built-in command (`list`), unknown command (error + suggestion), `--help` flag.
- [ ] **HtmxIntegrationTest** — Request with `HX-Request` header → fragment rendering (content section only, no layout). Cover: `HX-Target` → element-by-id extraction, `ClientBroadcast` → `HX-Trigger` header on response, `Vary: HX-Request` header.
- [ ] **ErrorResponseIntegrationTest** — Full error lifecycle across formats. Cover: 404 (no route), 405 (wrong method → correct Allow header), 406 (unsupported format), 422 (validation failure → field errors), 500 (unhandled exception). Verify both JSON and HTML renderers produce correct status codes and content.
- [ ] **SessionAuthIntegrationTest** — Session start → identity set → AuthorizationGuard enforcement → CSRF validation. Cover: unauthenticated request to `#[RequiresAuth]` → 401, authenticated request → passes, wrong role → 403, policy check with DTO data, CSRF token round-trip (session → form → validation). This test requires SessionMiddleware + AuthMiddleware + CsrfMiddleware + the Conveyor pipeline wired together — document the setup pattern for future complex integration tests.
- [ ] **MiddlewareOrderIntegrationTest** — Verify middleware executes in registered order. Register middleware A, B, C; verify execution order is A → B → C → handler → C → B → A (onion model). Catches ordering bugs that unit tests miss.
- [ ] **LifecycleEventIntegrationTest** — Verify all events fire in correct order during a full HTTP request. Cover: `RequestReceived` (mutable — verify listener can enrich request), `RequestHandled` (verify request + response pair), `RequestFailed` (verify exception captured), `ResponseSent` (verify fires in terminate). Parallel test for CLI events (`CommandReceived`, `CommandHandled`, `CommandFailed`, `CommandCompleted`).
- [ ] **TemplateRenderIntegrationTest** — HTTP request → template resolution → compilation → rendering with helpers. Cover: co-located template found, layout extends, helper calls resolve, status-specific template (422 error template).

---

## Completed Work

One-line summaries. Details are in git history and the COMPENDIUM.

- **htmx package** — First-class htmx 4 support: `HtmxAwareResponseRenderer`, `HtmxRequest`, `ClientBroadcast`, `FragmentDirective`, CSRF JS shim, auth-redirect middleware. See `src/Htmx/README.md`.
- **Validation error handling & status-specific templates** — 500→422 fix, `{Dto}.{status}.{format}` resolution chain, co-located and app-wide error templates, htmx fragment fallback, underscore partial convention.
- **Rendering pipeline refactor** — Extracted `TemplateEngine` from HtmlFormatter god object (5 phases). Formatters compose engine, renderers compose resolver, fallback formatters replaced with bundled templates (-909 lines). One rendering path for everything.
- **Guestbook first-run experience** — Graceful degradation when database missing or table not migrated.
- **Starter app guestbook validation demo** — Shared form partial, `AddEntry.422.html`, Idiomorph `outerMorph` preserving input values, automatic DTO class threading via `RouteDispatcher`.
- **Kernel lifecycle events** — `CommandReceived/Handled/Failed/Completed` events for RuneKernel, shared `Lifecycle` class for event dispatch and exception reporting, Stopwatch marks for CLI path. HyperKernel simplified (511→448 lines, -5 methods).
- **Context-specific output encoding** — `Html::url()` (scheme validation), `Html::js/attr/css()` (OWASP encoding). `CsrfHelper` split from `HtmlHelper` (`Csrf::field()`, `Csrf::token()`).
- **Database migrations** — `Migrator`, `MigrationParser`, `MigrationRepository`. Plain `.sql` files with `-- @migrate up/down` pragmas. CLI: `migrate`, `migrate:rollback`, `migrate:status`, `migrate:create`.

---

## Welcome page — nice-to-haves (deferred)

- [ ] **Diagnostic rows link to configuration docs** — blocked on docs site existing.
- [x] **Syntax highlighting in code blocks** — highlight.js from CDN with custom Arcanum-branded theme (warm copper keywords, forest green strings, amber literals, slate blue class/function names). Dark mode via `.dark` CSS scoping, no theme-swap JS needed.
- [ ] **`?debug=1` bootstrap visualization** — easter egg.
- [ ] **Placeholder URL cleanup** — replace `example.com` references with real docs URLs.
- [ ] **Replace custom copy-to-clipboard JS with htmx** — or at least move JS out of inline handlers.

---

## Long-Distance Future

- **Shodo context-aware auto-escaping** — Replace regex-based compiler with an HTML-aware tokenizer that detects variable context (body text, attribute, href, script, style, event handler) and applies the correct encoding automatically. The manual helpers shipped pre-1.0 remain useful as escape hatches. Major architectural change.
- **Shodo `Template\` namespace consolidation** — Move `TemplateAnalyzer`, `TemplateCache`, `TemplateCompiler`, `TemplateEngine`, `TemplateResolver` to `Arcanum\Shodo\Template\{Analyzer, Cache, Compiler, Engine, Resolver}`. Mechanical rename — easier before external consumers exist.
- **Reserved-filename collision in `app/Pages/`** — `Middleware.php` and `Helpers.php` collide with potential Page URL routes. Fix: `#[WithMiddleware]` per-DTO attribute for Pages + cross-aware discovery (PageDiscovery reserves those filenames, MiddlewareDiscovery/HelperDiscovery skip `app/Pages/`).
- **Move global helpers to `config/helpers.php`** — Replace hardcoded `app/Helpers/Helpers.php` read with a config file paralleling `config/middleware.php`. Must land alongside the Pages collision fix.
- **`cache:clear --store=NAME` accepts framework cache names** — Extend to recognize `templates`, `config`, `pages`, `middleware` and route to the right `Clearable`.
- **Shodo verbatim directive** — `{{ skip }}...{{ resume }}` to prevent template compilation inside code examples. Pre-pass: capture, placeholder, compile, restore.
- **FastCGI / post-response work patterns** — Document the contract, consider `DeferredWork` abstraction, handle non-FCGI SAPIs (RoadRunner, FrankenPHP, Swoole).
- **RFC 9457 Problem Details** — `application/problem+json` error responses. Forward-compatible with `ArcanumException`.
- **PSR-13 Hypermedia Links** — `LinkInterface`/`LinkProviderInterface` for handler-declared relationships. `LinkHeaderMiddleware` serializes to RFC 8288 `Link` headers. Pagination as first concrete use case.
- **Queue/Job system** — async processing with drivers (Redis, database, SQS).
- **`TestKernel` transactional database wrapping** — Wrap each test in a transaction, rollback at teardown.
- **`AbstractKernel` base class** — Deduplicate constructor, directory accessors, bootstrap loop between HyperKernel and RuneKernel (~80 lines). Pure refactor.
- **Internationalization** — translation strings, locale detection, pluralization.
- **Task scheduling** — `schedule:run` cron dispatcher.
- **Mail/Notifications** — thin wrappers or Symfony Mailer integration.
- **Arcanum Wizard** — Interactive project scaffolding. Must wait until after the Todo App dogfood.

---

## Lessons & Tenets

The framework's load-bearing decisions, distilled. Not an inventory — git history is the inventory. These are the things to remember when making future calls.

### What Arcanum is

- **CQRS strictness pays off.** Commands return `EmptyDTO` (204), `AcceptedDTO` (202), or a `Query` DTO that becomes a `201 Created` with a `Location` header — never response bodies. Queries return data. The boundary stays clean and handlers stay tiny because the framework absorbs the ceremony (ValidationGuard, AuthorizationGuard, DomainContext, the Conveyor middleware stack).
- **SQL is a first-class citizen.** Forge maps `__call` to `.sql` files with `@cast` annotations. No query builder, no ORM — both fight CQRS. Generated sub-models give handlers fully type-safe injection without losing the "SQL is the source of truth" property.
- **Streaming is the default.** `Flow\Sequence\Cursor` streams row-by-row at constant memory (6.3 KB at 500k rows vs 1.6 GB eager). `toSeries()` is the explicit opt-in to materialize.
- **HTTP status codes are part of the API.** 204/201/202/405/406/422/429 — never collapse to 200/404/500. The exception infrastructure carries titles + suggestions and is RFC 9457 forward-compatible.
- **Build-time validation beats runtime discovery.** `validate:handlers`, `validate:models`, page discovery cache, bootstrapper-order enforcement. Cheaper than reflection-walking on every request, and the failure modes name the actual problem.
- **Front-end defaults are opinions, not requirements.** htmx for interactivity (composes naturally with CQRS — every action is its own URL, no client-state mirror, no JSON API mirroring the page routes). Tailwind for styling (utility classes are AI-readable with no hidden semantics — friendly to humans *and* to AI agents reading the same template). Arcanum doesn't compile, bundle, hash, or transpile assets — that's its own world. Both defaults are swappable, both will get more first-class framework support over time. Captured in the COMPENDIUM's "Front-end defaults" section.

### Design lessons learned the hard way

- **Discipline beats ceremony for singletons.** Stopwatch is singleton-by-bootstrap-convention, not enforced by the class. Tests can construct private instances; library code can have private timelines. The bootstrap is the single source of truth.
- **Static accessors earn their keep at write-only sites.** `Stopwatch::tap()` no-ops when uninstalled (right for write-only call sites — middleware, formatter boundaries, listeners). `Stopwatch::current()` throws loudly when uninstalled (right for read sites — log lines, debug toolbars). Different ergonomics for different access patterns.
- **Production code is never "test code" in its own docs.** `FrozenClock` is a pinned clock — useful for replay, batch jobs, simulations, deterministic tests. Don't bake the test framing into the API surface; it shrinks the audience for no reason.
- **Explicit beats implicit when names matter.** The `#[WithHelper]` auto-strip experiment (`EnvCheckHelper` → `EnvCheck`) confused even its own author. Explicit aliases everywhere. Same lesson applies to `Helpers.php` files and `HelperRegistry::register`.
- **Treat the inside of `{{ }}` as a PHP expression.** Helper-call rewriting runs as a recursive `preg_replace_callback` *inside* the captured body, not as an outer regex anchored to `\{\{` and `\}\}`. Anything PHP allows after a method call composes naturally; nested helper calls compose; control-structure conditions get rewritten too (closing a latent bug).
- **PHP `//` comments terminate at `?>`.** A docblock that mentions `<?= ?>` literally will break the file's parse — the lexer needs to switch out of PHP mode. Use `/* */` blocks for any prose containing template markers.
- **Compose real production code in test harnesses; never reimplement it.** `TestKernel` wraps real `HyperKernel` and `RuneKernel` instances rather than parallel implementations, so future bootstrapper additions and lifecycle changes flow through automatically. The right amount of test scaffolding is "the smallest pre-pass that lets production code run."

---

## Benchmarking

See `contrib/BENCHMARKING.md` for the full methodology guide (hyperfine, environment guards, iteration tuning, opcache defeat, reading results). Existing bench scripts: `bench/heavy_validation.php`, `bench/many_params.php`, `bench/full_pipeline.php`.
