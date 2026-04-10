# Framework Completion Plan

> **COMPENDIUM.md must stay in sync.** Any time framework functionality changes, is added, or is removed — new packages, renamed classes, dropped features, new CLI commands, new attributes, new built-in marks, anything a user-facing tour would mention — update `COMPENDIUM.md` in the same change. It's the source of truth for the eventual documentation site, and an out-of-date entry there is worse than no entry. Treat it like committing: not a follow-up, part of done.

---

## Active Checklists

Concrete, walkable lists. Everything else in this file is informational — context, history, decisions, and future work that hasn't been broken into steps yet. When something becomes a checklist, it lives here.

### htmx package — `Arcanum\Htmx` — complete

First-class htmx 4 support: `HtmxAwareResponseRenderer` (three rendering modes: full page, content section, element-by-id extraction), `HtmxRequest` decorator, `ClientBroadcast` event projection to `HX-Trigger` headers, `FragmentDirective` for innerHTML opt-in via `{{ fragment 'id' }}`, CSRF JS shim, auth-redirect middleware, `Vary: HX-Request`, lazy template closures, Shodo custom directive system (`CompilerDirective` interface with 5 built-ins + `DirectiveRegistry`). Full README, COMPENDIUM updated, starter app integrated with guestbook demo. Smoke-tested end-to-end (surfaced the validation 500 bug below). See `src/Htmx/README.md` for the package reference. Design decisions archived in git history (commits on `claude-bang` branch, April 2026).

### Status-specific error templates — `{DtoClass}.{status}.{format}`

Convention-based error template resolution across all rendering pipelines and status codes. When an exception occurs during dispatch of a DTO, the renderer looks for a co-located error template before falling back to the app-wide and framework defaults.

**Resolution order (most specific first):**

1. **Co-located:** `app/Domain/Shop/Command/SendEmail.503.json` — this specific DTO, this status, this format
2. **App-wide:** `app/Templates/errors/503.json` — the app's default for this status + format
3. **Framework default:** the built-in exception renderer (current behavior)

**Naming convention:** `{DtoClass}.{statusCode}.{format}` — the DTO's filename with the HTTP status code and response format as extensions. Examples:
- `AddEntry.422.html` — validation errors for the guestbook command, rendered as HTML
- `PlaceOrder.503.html` — service unavailable error specific to order placement
- `Health.500.json` — custom JSON error shape for the health endpoint

**How it works:** The exception rendering chain already knows the DTO class (from the route), the status code (from the exception), and the response format (from the URL extension). `TemplateResolver` gains a `resolveError(string $dtoClass, int $statusCode, string $format)` method that checks co-located → app-wide → null. When non-null, the error renderer compiles and renders the template with error variables (`$code`, `$title`, `$message`, `$errors` for validation, `$suggestion` for ArcanumException). When null, falls back to the current built-in renderer.

**Works across all formats:** HTML, JSON, CSV, plain text, Markdown. The co-located convention is format-aware — `AddEntry.422.html` and `AddEntry.422.json` can coexist for the same DTO.

**htmx integration:** For htmx requests, the HTML error template is rendered as a fragment (no layout wrapper), making it safe to swap into the page. Combined with `hx-target-422` on the client, the developer has full control over where and how validation errors appear per form.

### Validation failure returns 500 instead of 422

Surfaced during the htmx smoke test (April 2026) and confirmed independently. When a command DTO fails validation (e.g., empty guestbook fields), the response message correctly says "Validation failed with 4 error(s)" but the HTTP status code is 500 instead of the expected 422 Unprocessable Entity. The `ValidationGuard` Conveyor middleware throws a `ValidationException` which should map to 422 via the exception renderer, but something in the rendering chain is swallowing the status code.

- [x] **Investigate and fix validation status code.** Root cause: `HyperKernel::handleException()` resolves `HtmlExceptionResponseRenderer` directly for HTML requests, bypassing the `ValidationExceptionRenderer` decorator chain. Both `HtmlExceptionResponseRenderer` and `JsonExceptionResponseRenderer` only checked `instanceof HttpException` for status codes, falling through to 500. Fix: both renderers now check for `ValidationException` explicitly via `match` expression and map it to 422.

### Welcome page — nice-to-haves (deferred)

The Index redesign landed (nine-section structure, real diagnostics, CSS-only tabs, copy buttons, ASCII rune). The leftovers are explicitly optional:

- [ ] **Tailwind styling on active tabs not working.** The CQRS demo tabs lost their active-tab styling (copper text, background, border). Unrelated to htmx changes — confirmed by reverting to pre-session markup. Likely a Tailwind CDN update.
- [ ] **Diagnostic rows link to configuration docs** — every non-green row in the welcome page Application column (and any Environment row that's red) should link out to the relevant Arcanum configuration doc when clicked. A yellow "Session driver — not configured (optional — required for CSRF)" line should link to the session config guide; a red "Cache driver — config broken" line should link to the cache config guide; "Database — not configured" links to database setup; etc. Cheap UX win once the docs site exists. Blocked on the documentation site itself — defer until real docs URLs exist (same blocker as the placeholder URL cleanup below).
- [ ] **Syntax highlighting in code blocks** — the welcome page's incantation card and CQRS demo tabs render plain monochrome `<pre><code>` blocks. Adding color hinting (PHP keywords, strings, attributes) would meaningfully improve the first impression. Low priority. Look for a small client-side library — Prism.js, highlight.js, or shiki — that loads from CDN with a single script tag and a stylesheet, no build step required. Constraints: must respect dark mode (the page already toggles `.dark` on `<html>`), must not be a heavy dependency (the welcome page is the only consumer for now), and must not require running a Node toolchain to use. This isn't welcome-page-only — Shodo's documentation, README code blocks, and any future docs page would all benefit. When picking a library, prefer one that handles PHP, HTML, SQL, and shell well since those are the four languages the framework's docs use most.
- [ ] **`?debug=1` bootstrap visualization** — replaces welcome banner with bootstrap order list when the query param is set. Easter egg.
- [ ] **Placeholder URL cleanup** — replace `https://example.com/{docs,tutorial,api,discussions}` references in the Index page with real URLs once real docs / tutorial / community channels exist. GitHub repo links are already real.

---

## Pre-1.0 Required

- **Context-specific output encoding** — Shodo's `{{ }}` provides HTML entity encoding via `htmlspecialchars(ENT_QUOTES, UTF-8)`. This is correct for HTML body text and quoted HTML attributes. It is **not** correct for URL, JavaScript, or CSS contexts, each of which requires its own encoding per OWASP XSS Prevention guidelines. Today's starter app is safe because it never places user data in these contexts, but the framework provides no guard rails if an app developer does. Before 1.0, Arcanum needs:
  - **URL sanitization helper** — `Html::url($href)` or similar that rejects `javascript:` and `data:` URI schemes. This is the most likely footgun — a handler passes a user-supplied URL to a template, the template puts it in `href="{{ $url }}"`, and HTML encoding prevents attribute breakout but not scheme injection. The helper should validate the scheme (allow `http`, `https`, `mailto`, `tel`, and relative paths; reject everything else) and HTML-encode the result. Use it in templates as `href="{{ Html::url($link) }}"`.
  - **JavaScript encoding helper** — `Html::js($value)` for the rare case where a variable is placed in a JavaScript string context (`<script>var x = '...'</script>` or JSON embedded in a `<script>` tag). Should use `\uXXXX` Unicode encoding per OWASP.
  - **Documentation** — clearly state that `{{ }}` is HTML encoding only, explain the five OWASP contexts, and point developers to the context-specific helpers. The Shodo README and the framework's security documentation should cover this.
  - CSS encoding is lowest priority — inline `style` attributes with user data are rare and almost always a design mistake. Document the risk; add a helper later if demand surfaces.

  Surfaced during a systematic OWASP XSS audit (April 2026). The three concrete escaping fixes from that audit (HtmlHelper::csrf token escaping, HtmxHelper::script attribute escaping, JsonFormatter full HEX flags) are already landed.

- **Logging instrumentation** — The framework is too quiet. Almost nothing logs. A developer running an Arcanum app in production has no visibility into what the framework is doing unless something throws. Before 1.0, instrument the framework with PSR-3 logging via Quill at key decision points. Guiding principle: log *decisions*, not *data* — a log line should tell you *what the framework decided to do and why*, not dump request bodies or SQL results. Use appropriate levels (debug for routine decisions, info for lifecycle milestones, warning for fall-throughs and degraded states, error for caught failures). Candidate instrumentation sites:
  - **Bootstrap chain** — which bootstrappers ran and in what order (debug). Slow bootstrappers (info with elapsed time).
  - **Routing** — which DTO class a request resolved to, or that no route matched (debug). Wrong HTTP method → 405 (info).
  - **Middleware** — which middleware ran for a request (debug). Middleware that short-circuited (info with reason).
  - **Conveyor dispatch** — handler class resolved, validation/auth guard decisions (debug). Validation failure details (info).
  - **Rendering** — which formatter and template were used, cache hit vs miss (debug). Fragment extraction fall-through (warning, already exists in HtmlFormatter).
  - **Migrations** — each migration applied or rolled back (info). Checksum mismatch (warning).
  - **Auth** — guard decisions: authenticated vs rejected, which guard, which identity (info). Session created/destroyed (debug).
  - **Cache** — Vault store hits/misses at debug level. Framework cache rebuilds (config, templates, pages, middleware, helpers) at info.
  - **Throttle** — rate limit decisions: allowed vs rejected, remaining quota (debug). Limit exceeded (info with client identifier).
  - **Lifecycle events** — RequestReceived, RequestHandled, RequestFailed, ResponseSent already exist as Echo events. Log them at debug with timing from Stopwatch marks.
  - **Exceptions** — Glitch already handles rendering, but the decision of *which* renderer was chosen and what status code was returned should log at info.

  Implementation approach: inject `?LoggerInterface` as a nullable constructor parameter (same pattern HtmlFormatter already uses). Null means silent — no performance cost when logging is disabled. The starter app's `config/log.php` configures Quill channels; framework packages log to a `framework` channel by default. This is a long-tail effort like integration tests — instrument progressively, not all at once. Start with the HTTP lifecycle (routing → dispatch → render → response) since that's the most visible path.

## Long-Distance Future

- **Reserved-filename collision in `app/Pages/`** — Any convention-based discovery file inside `app/Pages/` collides with a potential Page URL route. Today `app/Pages/Middleware.php` is picked up by `MiddlewareDiscovery` as scoped middleware for `App\Pages\*`, which means a developer who wants to make `/middleware.html` a real page by creating `app/Pages/Middleware.php` will either silently get a middleware config file instead of a page or hit a confusing runtime error when `PageDiscovery` and `MiddlewareDiscovery` disagree about what the file is. Same problem will hit `Helpers.php` once the discovery alignment below lands. The fix has two parts:

  1. **Pages get a per-DTO middleware attribute**, parallel to `#[WithHelper]`. A new `#[WithMiddleware(SomeMiddleware::class)]` declared on a Page DTO class lets a Page opt into middleware without needing a co-located `Middleware.php` file at all. This is the right ergonomic for Pages because each Page is already its own DTO; the attribute lives on the class that needs it.
  2. **`PageDiscovery`, `HelperDiscovery`, and `MiddlewareDiscovery` need to know about each other.** The page discovery walker should reserve `Middleware.php` and `Helpers.php` as non-Page filenames (skip them when scanning for Page DTOs), and the helper / middleware discovery walkers should *not* scan `app/Pages/` for their reserved files. Belt and braces — either side alone leaves a footgun for the other.

  Bigger alternative: change the reserved filenames to something less likely to collide with a real URL — `_middleware.php` / `.middleware.php` / `middleware.config.php`. Less appealing because the current names read naturally and the collision is narrow (only matters inside `app/Pages/`). Stick with the per-DTO attribute + cross-aware discovery.

- **Move global helpers to `config/helpers.php`, drop the special path** — Today `Bootstrap\Helpers` reads a hardcoded `app/Helpers/Helpers.php` and registers everything in it as global helpers. That's a one-off mechanism with no parallel anywhere else in the framework. Replace it with `config/helpers.php` — a config file that returns an alias → class map and gets loaded by `Bootstrap\Helpers` the same way `Bootstrap\Middleware` already loads `config/middleware.php`. After the change:
    - **Global helpers** live in `config/helpers.php`. Returns `['App' => AppHelper::class, 'Format' => FormatHelper::class, ...]`. Parallels `config/middleware.php` exactly.
    - **Domain-scoped helpers** keep the existing `Helpers.php` discovery convention under `app/Domain/<X>/`. No change to that path; it's already the right ergonomic for namespace-scoped helpers.
    - **The hardcoded `app/Helpers/Helpers.php` read goes away.** Cleaner Bootstrap, no special paths.
    - **Per-DTO helpers** keep using `#[WithHelper]` on the DTO class.

  This is the cleanest version of the "fix the discovery asymmetry" idea. Instead of teaching `HelperDiscovery` to walk all of `app/` (which would create the Pages collision documented above), keep `HelperDiscovery` scoped to `app/Domain/` and route the global case through config where it belongs. Three distinct mechanisms, three distinct purposes, no collision potential, no special paths. Still must land **alongside** the Pages reserved-filename collision fix above so per-Page middleware via `#[WithMiddleware]` is in place at the same time.
- **`cache:clear --store=NAME` accepts framework cache names** — Today the `--store` flag only routes through `CacheManager::store()`, so it can target any Vault store by name (`--store=app`, `--store=throttle`) but not the structured framework caches (`ConfigurationCache`, `TemplateCache`, page discovery, middleware discovery). Extend `CacheClearCommand::clearStore()` with a small switch that recognizes well-known framework targets (`templates`, `config`, `pages`, `middleware`, etc.) and routes them to the right `Clearable` instead of `CacheManager`. Same flag, broader semantics — `php arcanum cache:clear --store=templates` should Just Work without forcing the user to learn whether something is a Vault store or a framework cache. Low priority; today's "clear everything" path handles the common case.
- **Shodo verbatim / skip directive** — A `{{ skip }} ... {{ resume }}` pragma (working name; could be `{{ verbatim }}`, `{{ raw }}`, `{{ literal }}`) that tells the compiler "do not parse anything between these markers." Surfaced while writing the welcome page's CQRS code examples — a `<pre><code>` block that contains literal `{{ $name }}` text gets compiled as a real Shodo directive unless you escape every brace with `&#123;` HTML entities. That works but is hostile for anyone documenting Shodo *with* Shodo, and the pain compounds for a full documentation site (every code sample with template syntax becomes a HTML-entity exercise). The directive should be a pre-pass: capture the inside, replace with a unique placeholder token, run the rest of the compiler, then restore the captured content untouched at the end. Works correctly with nested example blocks, layout `extends`, and htmx fragment rendering. Low priority — entity escaping covers the current need — but the moment Arcanum is used to build its own documentation site, this becomes a must-have. Consider whether it should also support escaping inside `{{ if }}` conditions and similar directive bodies.
- **FastCGI / post-response work patterns** — Arcanum currently calls `fastcgi_finish_request()` in `HyperKernel::terminate()` and dispatches `ResponseSent` afterwards, but there is no formal "deferred work" abstraction beyond the listener — no queueing semantics, no per-listener time budget, no documentation of what is and isn't safe to do post-response, no story for non-FCGI SAPIs (CLI workers, RoadRunner, FrankenPHP, Swoole) that lack `fastcgi_finish_request()` entirely. Worth a focused pass: document the contract, decide whether to formalize a `DeferredWork` hook above raw `ResponseSent` listeners, and consider how `arcanum.complete` should behave under long-running runtimes where there is no "process exit" per request. Until then, `arcanum.complete` measures what it measures (end of `terminate()`), and consumers should treat it as "framework work done" rather than "process exit".
- **Hyper README** — document PSR-7 message classes, response renderers, exception renderers, format registry, file uploads, URI handling. Currently the only core package without a README.
- **RFC 9457 Problem Details for HTTP APIs** — standardized JSON error response format (`application/problem+json`). Forward-compatible with the `ArcanumException` interface. When ready, it's a renderer change — exception infrastructure is already in place.
- ~~**Database migrations**~~ — Landed. `src/Forge/Migration/` with `MigrationParser`, `MigrationRepository`, `Migrator`. CLI commands: `migrate`, `migrate:rollback`, `migrate:status`, `migrate:create`. Plain `.sql` files with `-- @migrate up` / `-- @migrate down` comment pragmas, consistent with Forge's `-- @cast` annotations. Timestamp-based naming, checksum validation (Flyway-style), transactional by default with `-- @transaction off` opt-out. Files live in `migrations/` at app root.
- **PSR-18 HTTP Client** — Arcanum has no way to make outgoing HTTP requests. Any app that consumes an external API, verifies an OAuth token, sends a webhook, or calls a microservice needs an HTTP client. The framework should ship a thin `Arcanum\Http\Client` package that implements PSR-18 (`ClientInterface`) and PSR-17 (HTTP factories for building requests). Design principles:
  - **Wrap an established library** — don't build an HTTP client from scratch. cURL is a C extension, not a PHP abstraction. Guzzle and Symfony HttpClient are the two mature options. Prefer Symfony HttpClient (`symfony/http-client`) — it's lighter, supports PSR-18 natively via `Psr18Client`, and doesn't carry Guzzle's promise/async complexity that Arcanum doesn't need.
  - **PSR-18 as the contract** — handlers and services type-hint `Psr\Http\Client\ClientInterface`. The framework binds the concrete implementation. Swappable without touching app code.
  - **PSR-17 factories** — ship `RequestFactory`, `ResponseFactory`, `StreamFactory`, `UriFactory` implementing the PSR-17 interfaces. These are trivial wrappers over Hyper's existing PSR-7 classes (`ServerRequest`, `Response`, `Uri`, streams from Flow\River`). Register them in the container so any PSR-17-aware library can build HTTP messages.
  - **Bootstrap registration** — `Bootstrap\HttpClient` registers `ClientInterface`, the PSR-17 factories, and reads config from `config/http.php` (base URL, timeout, default headers, retry policy).
  - **Testing** — `TestKernel` should provide a mock/recording client (similar to Symfony's `MockHttpClient`) so integration tests can stub external API calls without hitting the network.
  - **Logging** — outgoing requests should log through PSR-3 at debug level (method, URL, status code, elapsed time). Aligns with the logging instrumentation plan.

  Becomes urgent when the Todo App dogfood surfaces the first "call an external API" need, or when Auth needs to verify tokens against an external provider.

  **PSR-17 HTTP Factories** ship as part of this work. Trivial one-method wrappers over Hyper's existing PSR-7 constructors — `RequestFactory`, `ResponseFactory`, `StreamFactory`, `UriFactory`. They exist so third-party libraries (including the PSR-18 client itself) can create HTTP messages without importing Hyper's concrete classes. A dependency firewall: pull in any PSR-17-aware library and it builds messages using Arcanum's own types automatically.

- **PSR-13 Hypermedia Links** — Implement `LinkInterface` and `LinkProviderInterface` so handlers can express relationships and available actions alongside their data. Handlers return links; middleware serializes them into HTTP `Link` headers on every response regardless of format. Two motivating use cases:

  1. **Command responses are empty but not meaningless.** A 204 No Content or 201 Created has no body, but `Link` headers can tell the client what happened and what's available next: `rel="self"` for the created resource, `rel="collection"` for the parent list, `rel="cancel"` if the action is reversible. The `Location` header already does this for 201 — `Link` headers generalize the pattern to every command outcome.
  2. **HTML responses benefit too.** Templates have `<a>` and `hx-*` attributes, but `Link` headers add machine-readable semantics on top. AI agents consuming an Arcanum app can follow `rel="next"` without parsing HTML. Non-htmx HTML apps get navigation hints for free. For htmx apps the headers don't hurt — they're invisible to the browser and only add data for programmatic consumers.

  Design: a `Links` value object (implementing `LinkProviderInterface`) that handlers return alongside their data. A `LinkHeaderMiddleware` serializes them into RFC 8288 `Link` headers on every response. Each formatter can optionally embed links in the body too (`_links` in JSON, inline links in Markdown). Pagination (`rel="next"`, `rel="prev"`) is the first concrete feature built on top — it's the most common use case and benefits every format.

- **Queue/Job system** — async processing with drivers (Redis, database, SQS).
- **`TestKernel` transactional database wrapping** — Symfony's DAMA DoctrineTestBundle and similar tools across the PHP ecosystem solve the same problem: keep test database state isolated by wrapping each test in a transaction and rolling back at teardown. When Forge grows real-database integration tests, `TestKernel` should ship a transactional opt-in (`->withTransactionalDatabase()` or similar) that does the same. Out of scope for the current testing-utilities arc — Arcanum doesn't yet have enough Forge usage to justify the design — but worth flagging now so the precedent isn't forgotten when the need arises. The pattern is well-trodden across the PHP ecosystem.
- **Refactor `HyperKernel` and `RuneKernel` onto a shared `AbstractKernel` base** — Discovery for the testing-utilities arc surfaced that the two production kernels share ~80% of their structure: identical constructor signature, identical `isBootstrapped` flag, identical four directory accessors, identical bootstrap loop (only differing in which `Transport` enum gets bound at line 1), identical `Stopwatch::tap('boot.complete')`, identical `Stopwatch::tap('arcanum.complete')` in `terminate()`. The bootstrapper lists overlap entirely except for the HTTP-specific entries (`Sessions`, `Routing`, `Helpers`, `Formats`, `RouteMiddleware`, `Middleware`). Only `handle()` genuinely diverges (PSR-7 in / PSR-7 out vs argv in / int out). An `AbstractKernel` base class could collapse the duplication. TestKernel now exercises both production kernels through composition (via `HttpTestSurface` / `CliTestSurface`), so the test surface is in place to verify the refactor doesn't break behavior. Treat this as a pure refactor; no API changes.
- **Build out integration test coverage** — `tests/Integration/` currently has only 2 files (`CqrsLifecycleTest`, `HelperResolutionTest`). The framework's testing culture is heavily biased toward fine-grained unit tests with mocked dependencies, which catches narrow regressions but misses interaction bugs (convention-based discovery, bootstrapper ordering, route → handler → renderer round trips, transport guard behavior, validation flow, error rendering paths, htmx fragment rendering, CSRF middleware integration, lifecycle event ordering). Once `Arcanum\Testing\TestKernel` exists, writing integration tests becomes cheap and we should aggressively expand coverage. This is a long-tail effort, not a single deliverable — every feature added going forward should land with at least one integration test alongside its unit tests, and existing features should get retrofit coverage as time permits. Promote to a checklist when there's a concrete batch ready to execute.
- **Internationalization** — translation strings, locale detection, pluralization.
- **Task scheduling** — `schedule:run` cron dispatcher.
- **Mail/Notifications** — thin wrappers or Symfony Mailer integration.
- **Todo App dogfood** — build a fully-featured Todo app twice: once from scratch (no starter app), once using the starter app as a base. Both versions: SQLite via Forge, Vault caching, auth with sessions, Tailwind + htmx front-end. Full CRUD, task lists, completion toggling, filtering. Step-by-step, experiencing the framework as an app developer would. Then write a retrospective: pain points, what worked, what didn't, friction in the DX, missing features, surprising gaps. The retrospective feeds back into new plan items. This is how we find what we can't see from the framework side.
- **Arcanum Wizard** — interactive project scaffolding tool (`composer create-project` or standalone script). Guides a developer through setting up a new Arcanum app: project name, database driver, cache driver, auth, Tailwind + htmx, session config, etc. Generates `config/` files, `composer.json`, directory structure, and a working entry point. **Must wait until after the Todo App dogfood and retrospective** — we need to know what the real setup experience is before we try to automate it.

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
- **Compose real production code in test harnesses; never reimplement it.** `TestKernel` wraps real `HyperKernel` and `RuneKernel` instances rather than parallel implementations, so future bootstrapper additions and lifecycle changes flow through automatically. The wrapped kernels use empty bootstrappers lists so they don't stomp the test bindings, but every other code path — `prepareRequest()`, `sendThroughMiddleware()`, exception rendering, lifecycle event dispatch, `terminate()` — runs the way it does in production. Same lesson applies to `Factory`, which composes `Codex\Hydrator` instead of reimplementing the constructor reflection walk. The right amount of test scaffolding is "the smallest pre-pass that lets production code run."

---

## Benchmarking Guide

Methodology for measuring per-component PHP code paths in Arcanum. Use this when you want to know "how fast is *this class / method / pipeline*?", not "how many requests/second can the framework serve?" Those are different questions and need different tools.

**Why per-script (and not HTTP).** The HTTP-level approach (nginx + FPM + `ab`/`hey`) suffers from systemic noise sources that take more effort to control than the code-under-test does to measure: TIME_WAIT exhaustion, FPM worker scheduling, opcache warmup curves, JIT trace cache settling, FastCGI overhead, macOS thermal management, and a bimodal slow/fast-band signal we never fully explained. Per-script benches dodge all of that by launching a fresh `php` process per measurement and timing the whole invocation. Trade-off: you can't measure sustained-process effects, only per-component cost. For framework-level questions that's the right trade.

### Tool

**hyperfine** (`brew install hyperfine`) — runs each command many times, reports `mean ± stddev`, flags outliers and "first run was significantly slower" warnings. Trust its statistics; don't average runs by hand.

### Required environment guards

Every bench script must start with this block. The guards catch the silent failure modes *before* measurement starts:

```php
<?php
declare(strict_types=1);

if (!extension_loaded('Zend OPcache') || !ini_get('opcache.enable_cli')) {
    throw new RuntimeException('Bench requires opcache + opcache.enable_cli=1');
}
if (extension_loaded('xdebug')) {
    throw new RuntimeException('Bench must run without xdebug loaded');
}
$status = opcache_get_status(false);
if (!is_array($status) || ($status['jit']['enabled'] ?? false) !== true) {
    throw new RuntimeException('Bench requires JIT enabled — some extension is hooking zend_execute_ex (pcov? blackfire?)');
}
```

The **JIT-enabled check** is the load-bearing one. Any extension that overrides `zend_execute_ex` (xdebug, pcov, blackfire, ...) silently disables JIT, which means every measurement is wrong by ~30%+ before you even start. The check catches all of them at once. Without it the methodology is unsound.

### Required php flags on every invocation

```
php -d opcache.enable_cli=1 \
    -d opcache.jit=tracing \
    -d opcache.jit_buffer_size=64M \
    -d pcov.enabled=0 \
    bench/foo.php
```

`pcov.enabled=0` is the critical one for this machine — pcov ships enabled for `composer phpunit` coverage and it hooks `zend_execute_ex`, killing JIT. The flag disables it per-invocation; system state is untouched. If a different machine has blackfire or some other offender, add the equivalent disable flag.

**Don't disable pcov system-wide.** The fail-loud JIT guard means forgetting the flag throws immediately, so per-invocation is always safe and "remember to re-enable" is never a risk.

**zsh gotcha.** zsh doesn't word-split unquoted `$VAR` like bash does. Stashing the flags in a `PHP_OPTS=...` variable and then writing `php $PHP_OPTS bench/foo.php` will pass *one* big string argument and PHP will silently ignore most of it — the JIT guard will then catch it, but you'll waste time debugging. Use an array, `${=PHP_OPTS}`, or just inline the flags. Inline is simplest and what hyperfine wants anyway.

### Iteration tuning

PHP startup is ~50ms per `php script.php` invocation. To make startup negligible, **each bench must run for 800–1000ms or longer**. At 1000ms total, 50ms of startup is 5% — small enough to ignore.

Tune iteration count by running hyperfine itself with a tiny iteration set:

```
hyperfine --runs 3 --warmup 1 \
  'php -d opcache.enable_cli=1 -d opcache.jit=tracing -d opcache.jit_buffer_size=64M -d pcov.enabled=0 bench/foo.php'
```

Read the mean, scale `$iterations` in the script up or down, repeat until you land in the band. **Do not use `/usr/bin/time` for tuning** — it's a different measurement environment from the real run. Use hyperfine for both tuning and measurement so both happen under identical conditions.

### Defeating opcache optimization

Opcache will inline trivial returns and dead-code-eliminate any work whose result isn't observed. Two rules:

1. **Functions with constant returns must include a non-foldable expression.** Example:
   ```php
   function foo(): int {
       ['opcache cannot inline this'][0]; // breaks constant folding
       return 1;
   }
   ```
   Without this, opcache turns `$num += foo()` into `$num += 1` and you measure addition, not function-call overhead.
2. **The accumulator must be observed at the end.** `var_dump($accumulator)` works. Without it, opcache will dead-code-eliminate the entire loop body and you'll measure ~nothing.

Both are easy to forget, both fail silently — the bench just runs ~10× faster than reality and you don't notice until the numbers stop making sense.

### Reading hyperfine output

- **Trust the mean ± stddev.** No more averaging-by-hand or picking medians from 5 runs. One hyperfine invocation per bench.
- **Stop on warnings.** If hyperfine prints "Statistical outliers detected" or "The first benchmark run was significantly slower," the dev machine was under load. Stop, wait, rerun until clean. Never report numbers from a run that warned.
- **Comparing benches with different iteration counts:** hyperfine's `X times faster than Y` summary line is **meaningless** in that case. It compares total wall time, not per-iteration cost. Either pin all benches to the same iteration count, or compute per-iteration cost yourself: `(mean − ~50ms PHP startup) / iterations`.

### What this method can NOT measure

- **Sustained-process effects.** FPM worker warmup, opcache hit-rate over time, JIT trace cache settling, anything that takes hundreds of requests to stabilize. One process per measurement means cold-start every time.
- **HTTP/transport overhead.** No FastCGI, no nginx, no socket churn. If a real request's bottleneck lives in the FPM pipeline, this won't see it.
- **Code paths cheaper than ~50–100 ns per iteration.** Below that you can't get above the noise floor without absurd iteration counts.

For HTTP-level questions you'd need a different methodology — and any such methodology has to control for the bimodal slow/fast-band signal first. We don't have a good answer for that yet.

### Examples

`bench/heavy_validation.php`, `bench/many_params.php`, `bench/full_pipeline.php` — three working bench scripts covering the validator, hydrator, and the full Conveyor middleware pipeline (`Hydrator → ValidationGuard → AuthorizationGuard → TransportGuard → Handler`). Use them as templates. The full pipeline lands at ~8.6 µs per dispatch on this machine, which means the framework's per-command overhead is essentially free compared to any I/O a real handler will do.

