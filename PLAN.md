# Framework Completion Plan

---

## Completed Work

2209 tests, PHPStan level 9 clean.

<details>
<summary>Core packages (click to expand)</summary>

Cabinet, Codex, Echo, Flow (Pipeline, Continuum, Conveyor, River), Gather, Glitch, Hyper, Ignition, Atlas, Shodo, Rune, Parchment, Quill, Toolkit. Full test coverage, all READMEs written.

</details>

<details>
<summary>1. Security Primitives — Toolkit (click to expand)</summary>

Encryption (SodiumEncryptor, XSalsa20-Poly1305), hashing (BcryptHasher, Argon2Hasher), random (Random utility), HMAC signing (SodiumSigner). Bootstrap\Security wires from APP_KEY. `make:key` CLI command.

</details>

<details>
<summary>2. Validation — new package (click to expand)</summary>

Attribute-based validation on DTO constructor params. 10 built-in rules (NotEmpty, MinLength, MaxLength, Min, Max, Email, Pattern, In, Url, Uuid, Callback). ValidationGuard Conveyor middleware fires before handlers. 422 on HTTP, field-level errors on CLI. Custom rules via Rule interface.

</details>

<details>
<summary>3. Caching — Vault (click to expand)</summary>

PSR-16 caching with 5 drivers (File, Array, Null, APCu, Redis). CacheManager for named stores. PrefixedCache decorator. Framework caches (config, templates, pages, middleware) migrated onto Vault. `cache:clear` and `cache:status` CLI commands.

</details>

<details>
<summary>4. Scaffolding Generators (click to expand)</summary>

Generator base class with stub templates (app-level overrides supported). `make:command`, `make:query`, `make:page`, `make:middleware`. Stubs use Shodo TemplateCompiler.

</details>

<details>
<summary>5. Sessions (click to expand)</summary>

HTTP session management with configurable drivers (file, cookie, cache). ActiveSession request-scoped holder. SessionMiddleware handles start/save/cookie. CSRF protection via CsrfMiddleware + `@csrf` template directive. Bootstrap\Sessions wires from config/session.php.

</details>

<details>
<summary>6. Auth & Authorization (click to expand)</summary>

Identity interface, Guards (Session, Token, Composite), AuthMiddleware (HTTP), CliAuthResolver (CLI). Authorization via DTO attributes (#[RequiresAuth], #[RequiresRole], #[RequiresPolicy]). AuthorizationGuard Conveyor middleware. CLI sessions: Prompter, CliSession (encrypted file store), LoginCommand, LogoutCommand. Priority chain: --token → session → ARCANUM_TOKEN env. CSRF/Auth coordination: AuthMiddleware sets PSR-7 request attribute for token-authenticated requests; CsrfMiddleware skips CSRF for those. Guard config supports array syntax: `'guard' => ['session', 'token']`.

</details>

<details>
<summary>7. HTTP Client (click to expand)</summary>

PSR-18 HTTP client wrapper. Complete.

</details>

<details>
<summary>8. Custom App Scripts (click to expand)</summary>

Rune extension for app-defined CLI scripts. Complete.

</details>

<details>
<summary>9. Persistence — Forge (click to expand)</summary>

SQL files as first-class methods. Connection interface with PdoConnection (MySQL, PostgreSQL, SQLite). ConnectionManager with read/write split and domain mapping. Model maps `__call` to .sql files with PHP named/positional/mixed arg support. `@cast` (int, float, bool, json) and `@param` annotations. Result with lazy withCasts(). Sql utility with SqlScanner for comment/string-aware parsing. Database service with domain-scoped model access and transactions. DomainContext + DomainContextMiddleware for automatic domain scoping. ModelGenerator with stub templates and app-level overrides. forge:models, validate:models, db:status CLI commands. Dev-mode auto-regeneration with configurable auto_forge. Bootstrap\Database wires from config/database.php.

**Performance TODO:** `Result::rows()` with casts uses `array_map`, copying the row array. Investigate lazy iteration for large result sets.

</details>

<details>
<summary>10. Template Helpers — Shodo extension (click to expand)</summary>

Static-method-call syntax in templates: `{{ Route::url('...') }}`, `{{ Format::number($price, 2) }}`. HelperRegistry, HelperResolver (domain-scoped via co-located `Helpers.php` files), HelperDiscovery. Five built-in helper groups: Route, Format, Str, Html, Arr. `{{ @csrf }}` directive. Compiler rewrites `Name::method(...)` to `$__helpers['Name']->method(...)`.

</details>

<details>
<summary>Shodo/Hyper rendering refactor (click to expand)</summary>

Shodo decoupled from Hyper — formatters produce strings, response renderers build HTTP responses. Five phases: interface extraction, ResponseRenderer classes, old code deletion, Bootstrap rewiring, pipeline verification.

</details>

<details>
<summary>Security fixes (click to expand)</summary>

All complete: Bearer token CSRF bypass removed, CSRF/Auth coordination via request attributes, `#[Url]` restricted to http/https, Model path traversal fixed, JsonFormatter JSON_HEX_TAG added, template eval() security documented, Pattern regex ReDoS documented.

</details>

<details>
<summary>DX guardrails (click to expand)</summary>

All complete: ValidationGuard missing detection, `#[AllowedFormats]` attribute (406 Not Acceptable), unused template variable warning (TemplateAnalyzer), handler error messages, `validate:handlers` promotion, circular dependency detection, template undefined variable errors, `factory()` caching documented, bootstrapper ordering enforcement, page discovery warning.

</details>

<details>
<summary>Additional features (click to expand)</summary>

- **Markdown formatter** — template-based with `.md` files, identity escape, fallback renderer, MarkdownResponseRenderer.
- **Command response Location headers** — LocationResolver builds URLs from returned Query DTO instances (class → path, properties → query params). 201 Created + Location header.
- **Bootstrap\Routing split** — split into Bootstrap\Formats, Bootstrap\Routing (slimmed), Bootstrap\Helpers.
- **SqlScanner** — extracted character-level SQL lexer from Forge\Sql::extractBindings() into reusable SqlScanner class.

</details>

<details>
<summary>Starter project (click to expand)</summary>

Full CQRS pipeline: Router → Hydrator → Conveyor → Renderer. Example Query (Health), Command (Contact/Submit), Page (Index, Contact). HTTP + CLI entry points. Config files with comments. Getting-started README covering quick start, CQRS concepts, directory structure, validation, auth, response formats, testing, and development workflow. Example test (HealthHandlerTest).

</details>

---

## Upcoming Work

### Starter app — index page redesign

Researched the Symfony 8 and CakePHP 5 welcome pages for comparison. Symfony is polished and resource-focused (welcome banner, "Next Step" CTA, three columns of links). CakePHP is diagnostic and reassuring (version banner, environment/filesystem/database health checks with green and red bullets, link directories). Our current index is a hero + two CTAs + a CQRS explainer card grid — sparse, no version, no environment info, no resource links. New users get no signal that the framework is healthy or where to look for more information.

The new index combines all three: Symfony's polish, CakePHP's diagnostic checklist, and our CQRS explainer (which is uniquely valuable — neither competitor explains its own mental model on the first page).

**Wireframe:**

```
┌────────────────────────────────────────────┐
│ Welcome to Arcanum {version}               │
│ Tagline                                     │
│ This page lives at app/Pages/Index.html —  │
│ replace it to make it your own.             │
└────────────────────────────────────────────┘

┌────────────────────────────────────────────┐
│ NEXT STEP                                   │
│ → Read the Getting Started guide            │
│ Or generate your first page:                │
│   php bin/arcanum make:page Home            │
└────────────────────────────────────────────┘

┌──────────────┐  ┌──────────────────────────┐
│ ENVIRONMENT  │  │ APPLICATION              │
│ ✓ PHP 8.4.3  │  │ ✓ Cache: file (writable) │
│ ✓ ext-sodium │  │ ✓ Logs: writable         │
│ ✓ files/ wr  │  │ ✓ Sessions: file         │
│ ⚠ CSS bundle │  │ ✓ Database: sqlite       │
│   not built  │  │ ✓ Debug mode ON          │
└──────────────┘  └──────────────────────────┘

──── How Arcanum Works (existing CQRS cards) ────

┌──────────┐ ┌─────────────┐ ┌──────────┐
│ LEARN    │ │ COMMUNITY   │ │ BUILD    │
│ Docs     │ │ GitHub      │ │ make:cmd │
│ Tutorial │ │ Issues      │ │ make:qry │
│ API ref  │ │ Discussions │ │ make:page│
└──────────┘ └─────────────┘ └──────────┘
```

**Design decisions:**

- **Static checks**, not cached. The index page only renders in dev mode and is called rarely; running `file_exists()` and `extension_loaded()` per page load is cheap. Keeps the page accurate without a manual cache bust after install.
- **Page is not auto-disabled in production.** The user replaces `app/Pages/Index.html` themselves. No magic — replacing the file is the explicit signal that they're making the app their own.
- **Status checks live in a new `EnvCheck` helper** under `app/Helpers/EnvCheckHelper.php`, registered via `app/Helpers/Helpers.php` as the `Env` alias. Returns booleans, version strings, and status records per check. Keeps `AppHelper` focused on view-related concerns.
- **Framework version from `Composer\InstalledVersions::getVersion('arcanum-org/framework')`** — the standard PSR-compatible way, no need to parse files.

**Plan items — starter app:**

- [ ] **`App\Helpers\EnvCheckHelper`** — registered as the `Env` alias. Methods: `phpVersion()`, `phpVersionOk()`, `extensions(): array<string,bool>`, `filesWritable()`, `cacheWritable()`, `logsWritable()`, `sessionsWritable()`, `cacheDriver()`, `sessionDriver()`, `databaseConnection(): ?string` (returns the working driver name or null), `cssBuilt()`, `debugMode()`, `frameworkVersion()`.
- [ ] **Index page redesign** — rewrite `app/Pages/Index.html` to match the wireframe. Five sections: welcome banner, next step CTA, environment + application checks (two-column grid), CQRS cards (existing, polish), resource link grid (three columns).
- [ ] **`Index.php` DTO** — keep `name` and `message`, add nothing else. The helper does the data lookup, the DTO stays simple.
- [ ] **CSS for status bullets** — green check, yellow warning, red cross variants. Inline Tailwind classes; no new CSS file needed.
- [ ] **Use placeholder example.com URLs** for documentation links. Create a separate plan item to swap them out once real docs exist.
- [ ] **Smoke test** — confirm the page renders correctly with all checks green on a fresh starter app, and that a contrived failure (e.g. chmod -w on `files/cache/`) flips the right bullet to red.

**Plan items — placeholder URL cleanup (deferred until real docs exist):**

- [ ] **Replace `https://example.com/docs`** in starter app Index page with real documentation URL.
- [ ] **Replace `https://example.com/tutorial`** with real tutorial URL.
- [ ] **Replace `https://example.com/api`** with real API reference URL.
- [ ] **Replace `https://example.com/discussions`** with real community URL (Discord/Slack/GitHub Discussions).

GitHub source and issues URLs will use the real `arcanum-org/framework` repo links — those exist already.

### Shodo conditionals

The template compiler currently has no conditional directives. Templates that need to branch on runtime state have to push the entire branch into a helper that returns a raw HTML string (which is what we did for `App::cssTags()`). That works but pulls layout-shaped logic into PHP classes for no good reason.

**`@if` / `@elseif` / `@else`** (foundation):

```
{{ @if App::debug() }}
    <script src="https://cdn.tailwindcss.com"></script>
{{ @elseif App::cssBuilt() }}
    <link rel="stylesheet" href="/css/app.min.css">
{{ @else }}
    <!-- no styles -->
{{ @endif }}
```

Compiles to vanilla PHP `if`/`elseif`/`else`. Conditions are full PHP expressions — same model as `{{ $expr }}` interpolation. Templates are already compiled to PHP, so this opens no door that isn't already open.

**`@match` / `@case` / `@default`** (sugar on top):

```
{{ @match $status }}
    {{ @case 'pending', 'active' }}
        <span class="text-success">Active</span>
    {{ @case 'closed' }}
        <span class="text-error">Closed</span>
    {{ @default }}
        <span class="text-stone">Unknown</span>
{{ @endmatch }}
```

Compiles to a PHP `switch` statement (with implicit `break` after each case). Strictly an equality-match-against-subject construct — not a free-form pattern matcher (free-form is just `@if` in different clothing). Comma-separated values in `@case` map to fall-through case lists. The subject is evaluated once.

PHP's native `match` expression can't be used directly because match arms must be expressions, not statement bodies. The compiler emits `switch` under the hood, but the directive is named `@match` because it's the closer mental model and avoids conflating with C-style switch fall-through.

**Plan items:**

- [ ] **`@if` / `@elseif` / `@else`** — directive parsing in `TemplateCompiler`, compiles to PHP if/elseif/else with raw PHP expression conditions. Supports nested ifs.
- [ ] **`@match` / `@case` / `@default` / `@endmatch`** — compiles to PHP switch with implicit breaks. Comma-separated values in @case map to fall-through cases.
- [ ] **Tests** — both directives, nesting, inside layouts/sections, inside includes, mixed with `{{ }}` interpolation.
- [ ] **Update Shodo README** — document directives, condition expression rules, when to use `@if` vs `@match`.

### Cache management gaps

Surfaced while wiring up the Tailwind production build. The template helper `App::cssTags()` reads `file_exists()` at render time, but compiled templates inline the layout content at build time, so the helper call only takes effect after the cache is invalidated. `cache:clear` doesn't currently clean the templates cache, which made the helper appear broken until manually nuked.

- [ ] **`cache:clear` should clear the templates cache** — currently iterates `Vault` stores and the configuration cache, but skips `files/cache/templates/`. Either route template compilation through Vault, or have `cache:clear` walk all framework cache directories under `files/cache/`.
- [ ] **`cache:clear` should clear the helper discovery cache** — the framework store named `helpers` (used by `HelperDiscovery`) is cached separately and survives `cache:clear`. Same fix scope as the templates cache.
- [ ] **Audit framework cache surfaces** — list every place the framework writes a cache (templates, helpers, page discovery, middleware discovery, configuration, etc.), confirm `cache:clear` reaches all of them, and document the inventory in the Vault README.
- [ ] **Template cache invalidation on layout change** — when a child template `@extends` a layout, the layout's content is inlined into the compiled child. Editing the layout doesn't invalidate the children. Either checksum the layout into the child's cache key, or invalidate dependents on layout change. This is what made the Tailwind helper iteration painful — every layout edit required a manual `rm -rf files/cache/templates/`.

### Starter app

- [x] **Add database example** — Contact domain persists to SQLite via Forge. Model/ directory with Save.sql, FindAll.sql, CreateTable.sql. New Messages query reads submissions back. config/database.php with SQLite connection.

### Forge Sub-Model Redesign ✓

Subdirectories in `Model/` become independent, autowireable model classes. Generated classes have zero constructors — they inherit `(ConnectionManager)` from the base class. Methods pass `__DIR__ . '/File.sql'` directly to `execute()`. The base class `__call` derives its directory via reflection.

Single stub for all models. `forge:models` and `validate:models` handle both root and sub-model generation/validation. Handlers inject generated model classes directly for full type safety. `$db->model` still works for backwards compatibility.

### 11. Rate Limiting — Throttle (new package)

New `Throttle` package under `src/Throttle/`. Depends on `Psr\SimpleCache\CacheInterface` (Vault). Two strategies: token bucket and sliding window. Starter app gets an example middleware.

**Algorithms:**

- **Token bucket** — store `{tokens, lastRefill}`. Tokens refill at a steady rate up to a max. Each request costs one token. Allows controlled bursts.
- **Sliding window** — store `{count, windowStart}` for current and previous windows. Weight the previous window's count by overlap fraction. No burst allowance, strict.

**Framework (Throttle package):**

- [x] **`RateLimiter` class** — main entry point. Takes `CacheInterface` in constructor. `attempt(string $key, int $limit, int $windowSeconds): Quota` checks and decrements. Configurable strategy (token bucket default, sliding window option).
- [x] **`Quota` value object** — immutable result of an attempt: `$allowed` (bool), `$remaining` (int), `$limit` (int), `$resetAt` (int, epoch). Methods: `isAllowed()`, `headers()` (returns array of `X-RateLimit-*` and `Retry-After` headers).
- [x] **`TokenBucket`** — implements token bucket algorithm against cache get/set with TTL.
- [x] **`SlidingWindow`** — implements sliding window algorithm against cache get/set with TTL.
- [x] **`Throttler` interface** — `attempt(CacheInterface $cache, string $key, int $limit, int $windowSeconds): Quota`.
- [x] **Tests** — both strategies, edge cases (first request, limit reached, window rollover, TTL expiry). Use `ArrayDriver` from Vault.
- [x] **Throttle README** — document algorithms, usage, configuration, header conventions.

**Starter app:**

- [x] **`RateLimit` middleware** — `App\Http\Middleware\RateLimit`. Extracts key from request (IP address). Calls `RateLimiter::attempt()`. On reject: throws `HttpException(StatusCode::TooManyRequests)`. On allow: adds `X-RateLimit-*` headers to response.
- [x] **Register in `config/middleware.php`** — add to global middleware stack.
- [x] **Add `throttle` config** — `config/throttle.php` or section in existing config. Limit, window, strategy.

**HTTP headers (added to successful responses and 429s):**

- `X-RateLimit-Limit` — max requests allowed
- `X-RateLimit-Remaining` — requests left in current window
- `X-RateLimit-Reset` — epoch time when window resets
- `Retry-After` — seconds until client should retry (429 only)

### 12. Kernel Lifecycle Events

HTTP lifecycle events dispatched via Echo. Listeners observe the request/response flow without being in the middleware stack. Middleware stays for things that wrap or transform (auth, CSRF, rate limiting). Events are for things that react at a specific point (logging, metrics, audit trails, post-response work).

**Guideline for developers:** If you need before *and* after, or need to short-circuit — use middleware. If you need to react at one point — use an event listener.

**Events (Hyper\Event):**

| Event | Carries | When |
|---|---|---|
| `RequestReceived` | `ServerRequestInterface` (mutable) | Request enters kernel, before middleware |
| `RequestHandled` | `ServerRequestInterface`, `ResponseInterface` (read-only) | After middleware + handler, response exists |
| `RequestFailed` | `ServerRequestInterface`, `Throwable` | Exception thrown during handling |
| `ResponseSent` | `ServerRequestInterface`, `ResponseInterface` (read-only) | After response bytes sent to client |

Design decisions:
- `RequestReceived` allows mutation (listeners can add request attributes, e.g., start time, request ID). Returns the (possibly modified) request to the kernel.
- `RequestHandled` and `ResponseSent` are read-only — middleware is the last word on the response. No mutation after the stack.
- `RequestFailed` is observational — Glitch still handles exception rendering. Listeners are for reporting, metrics, notifications.
- `ResponseSent` fires after `fastcgi_finish_request()` if available, otherwise at script end. Documented as best-effort post-response.

**Framework:**

- [x] **`Hyper\Event\RequestReceived`** — carries `ServerRequestInterface`. Mutable: listener can replace the request via `setRequest()`.
- [x] **`Hyper\Event\RequestHandled`** — carries request + response. Read-only.
- [x] **`Hyper\Event\RequestFailed`** — carries request + throwable. Read-only.
- [x] **`Hyper\Event\ResponseSent`** — carries request + response. Read-only.
- [x] **Update `HyperKernel::handle()`** — dispatch `RequestReceived` before middleware, `RequestHandled` after, `RequestFailed` on exception.
- [x] **Add `HyperKernel::terminate()`** — dispatches `ResponseSent`. Calls `fastcgi_finish_request()` if available.
- [x] **Update starter app `public/index.php`** — already calls `$kernel->terminate()`.
- [x] **Tests** — verify events fire at the right points, verify request mutation propagates, verify exception events fire on failure.
- [x] **Update Ignition README** — documented lifecycle events, event table, when to use events vs middleware, listener examples.

**Starter app — request logging listener:**

- [x] **`App\Http\Listener\RequestLogger`** — listens to `RequestReceived` (records start time on request attribute) and `RequestHandled` (logs method, path, status, duration). Uses `LoggerInterface`. Log level by status: 2xx→info, 4xx→warning, 5xx→error.
- [x] **Register listener** — via Echo subscriber registration in bootstrap or config.
- [x] **Add `requests` channel to `config/log.php`** — separate log file for HTTP access logs.

### 13. Default Styling & Front-End Integration

Visual design system defined in `DESIGN.md` (committed). Framework ships self-contained error pages. Starter app uses Tailwind CSS + HTMX — CDN for dev, build for prod. HTMX integrates naturally with CQRS: commands return 204/201+Location, a small middleware translates Location to HX-Location for HTMX-driven navigation.

**Design system:**

- [x] **`DESIGN.md`** — warm editorial design: parchment canvas, Lora headings, Inter body, burnt copper accent, dark mode. Google Stitch format.

**Framework — HTML error pages:**

- [x] **`HtmlExceptionResponseRenderer`** — renders exceptions as styled HTML instead of JSON. Self-contained inline styles following DESIGN.md. Displays: status code (display heading in copper), error title, helpful description, "Go back" / "Go home" links. Debug mode adds: exception class, file:line, stack trace in a collapsible code block.
- [x] **Default error templates** — HtmlExceptionResponseRenderer ships friendly default descriptions for common status codes (400, 401, 403, 404, 405, 406, 422, 429, 500, 503). Used when no custom exception message is provided. Inline-styled, no external CSS dependency.
- [x] **App override mechanism** — HtmlExceptionResponseRenderer checks for `{errorTemplatesDirectory}/{code}.html` before rendering the built-in page. Templates receive $code, $title, $message, $suggestion, and debug variables.
- [x] **`HtmlFallbackFormatter` styling** — updated with DESIGN.md inline styles: parchment background, Inter font, warm colors, dark mode. Container with max-width 720px.
- [x] **Tests** — verify HTML rendering, debug vs production output, app override loading, friendly descriptions, fallback formatter styling.

**Starter app — Tailwind CSS:**

- [x] **`tailwind.config.js`** — maps DESIGN.md tokens to Tailwind: custom colors, font families, spacing scale.
- [x] **CDN play script in `<head>`** — Tailwind CDN play script with inline config matching the full config file. Marked for replacement with built CSS in production.
- [x] **`public/css/app.css`** — Tailwind entry file with `@tailwind` directives. Pending production build setup.
- [x] **Production build** — document Tailwind CLI standalone or Vite setup. Add `composer css:build` and `composer css:watch`.
- [x] **Production guardrail** — warn when CDN play script is detected in production. Logs a WARNING from `public/index.php` when `APP_DEBUG=false` and `public/css/app.min.css` is missing.
- [x] **Dark mode** — Tailwind `darkMode: 'class'` strategy. Dark mode toggle in nav persisted to `localStorage`. OS preference detection on first load.

**Starter app — HTMX:**

Prerequisites (framework — Shodo changes, must complete first):

- [x] **Shodo layout support** — add `{{ @extends 'layout' }}` and `{{ @section 'name' }}...{{ @endsection }}` directives to `TemplateCompiler`. A layout template defines `{{ @yield 'name' }}` slots. The child template declares which layout it extends and fills the sections. Layout resolution: co-located `layout.html` in the same directory, then parent directories, then a configurable default path.
- [x] **Shodo `@include` directive** — add `{{ @include 'partials/nav' }}` to `TemplateCompiler`. Resolves relative to the current template's directory. For reusable fragments (nav, footer) shared across pages.
- [x] **Shodo fragment rendering** — when the `HX-Request` header is present, Shodo renders only the content section (skipping the layout wrapper). This means the same template serves both full-page loads and HTMX partial swaps. Lives in the framework: `HtmlFormatter::setFragment()` called by middleware, compiler resolves fragment mode.
- [x] **Shodo tests** — test layout inheritance, section filling, include resolution, fragment-only rendering.
- [x] **Update Shodo README** — document layouts, sections, includes, and HTMX fragment behavior.

Starter app (depends on Shodo changes above):

- [x] **HTMX CDN script in `<head>`** — HTMX 2.0.4 pinned with integrity hash in base layout.
- [x] **`HtmxMiddleware`** — detects `HX-Request` header, enables fragment rendering, copies Location to HX-Location.
- [x] **Update Contact form** — HTMX-enhanced: `hx-post` on form, messages div refreshes via `hx-trigger="load, refresh"` after submission.
- [x] **Update Index page** — hero section with CQRS explainer cards, styled with Tailwind.

**Starter app — base layout (depends on Shodo changes above):**

- [x] **Base layout template** — `app/Templates/layout.html`: shared HTML shell with Tailwind CDN, HTMX, Google Fonts, dark mode toggle. Nav and footer via `@include`.
- [x] **Navigation partial** — `app/Templates/partials/nav.html`: styled nav with links to Home, Contact, Health, and dark mode toggle icon.
- [x] **Footer partial** — `app/Templates/partials/footer.html`: minimal footer with "Built with Arcanum" link.

**Starter app — styled pages:**

- [x] **Index page** — hero section with framework name, tagline, CTA buttons, and CQRS explainer cards.
- [x] **Contact page** — styled form with HTMX submission, inline messages list loaded via HTMX.
- [x] **Messages page** — styled card list of contact submissions, loaded via HTMX fragment.
- [x] **Health check** — styled card with status indicator and JSON link.

**Starter app — documentation:**

- [x] **README section on front-end** — documents Tailwind + HTMX setup, CDN vs build, dark mode, templates/layouts, HTMX patterns with CQRS, production build instructions.
- [x] **Production deployment checklist** — documented in README front-end section.

### 14. Error Message Personality Pass

Every framework error should: (1) say what went wrong clearly, (2) suggest what to do about it, (3) have personality without sacrificing precision. Voice: direct, uses contractions, addresses the developer as a peer, always ends with actionable information. Warm but not cute — a colleague, not a mascot.

**Design decisions:**

- **Named exceptions over generic PHP built-ins.** Each distinct error case gets its own class (`SqlFileNotFound`, `HandlerNotFound`, `ServiceNotFound`). Each extends the semantically correct PHP built-in (`RuntimeException`, `InvalidArgumentException`, `LogicException`). All implement `ArcanumException` interface.
- **`ArcanumException` interface in Glitch.** Two methods: `getTitle(): string` (stable human-readable category, e.g., "SQL File Not Found") and `getSuggestion(): ?string` (optional fix hint, shown when `verbose_errors` is enabled). Forward-compatible with RFC 9457 Problem Details — `title` maps to RFC `title`, `getMessage()` maps to `detail`, class name can derive `type` URI later.
- **`app.verbose_errors` config.** Independent from `app.debug` (defaults to `app.debug` value if unset). Controls whether suggestions are shown. The core message (what went wrong) is always present. Stack traces are still controlled by `app.debug` separately.
- **Suggestions computed at throw site.** The throw site has the context — nearby files for "did you mean?", available methods, registered services, allowed formats. The exception carries the suggestion; the renderer decides whether to display it.

**Framework — exception infrastructure:**

- [x] **`ArcanumException` interface** — in Glitch. `getTitle(): string` and `getSuggestion(): ?string`. Any exception class can implement it.
- [x] **`HasSuggestion` trait** — skipped; each exception implements ArcanumException directly. Boilerplate is minimal. Can revisit with an abstract base class if it becomes verbose.
- [x] **`app.verbose_errors` config** — add to Bootstrap. Defaults to `app.debug` if not set. Available via `Configuration` for renderers and error handlers.
- [x] **Update `JsonExceptionResponseRenderer`** — if exception implements `ArcanumException`: include `title` in JSON output always, include `suggestion` when `verbose_errors` is enabled. Forward-compatible with RFC 9457 shape.
- [x] **Update `HtmlExceptionResponseRenderer`** — (from section 13) render suggestion below the error message when `verbose_errors` is enabled. Styled as a helpful aside, not an error.
- [x] **Tests** — verify suggestion is shown/hidden based on config, verify `ArcanumException` interface, verify JSON and HTML renderers respect the toggle.

**Framework — named exceptions per package:**

Each package replaces generic `throw new \RuntimeException(...)` with named exception classes. Not every throw site needs a unique class — group by error category. Every named exception implements `ArcanumException`, extends the appropriate PHP built-in, and provides a clear `getTitle()`.

- [x] **Glitch** — `HttpException` already exists. Add `ArcanumException` interface to it. Add title derived from status code.
- [x] **Cabinet** — `ServiceNotFound`, `CircularDependency`. Suggestions: "Did you register it?", "Check your dependency chain: A → B → C → A."
- [x] **Codex** — `UnresolvableParameter`, `ClassNotFound`. Suggestions: "Parameter $x has no type hint and no default — add a type or register a specification."
- [x] **Forge** — `SqlFileNotFound`, `InvalidModelMethod`, `ConnectionNotConfigured`, `UnsupportedDriver`. Suggestions: list nearby SQL files, list configured connections, list supported drivers.
- [x] **Atlas** — `UnresolvableRoute` updated with ArcanumException, `MethodNotAllowed` inherits from HttpException. Suggestions: "Run `validate:handlers` to check registration."
- [x] **Shodo** — `UnknownHelper` and `UnsupportedFormat` updated with ArcanumException. UnknownHelper lists available helpers.
- [x] **Hyper** — `HttpException` already implements ArcanumException; FormatRegistry throws 406 with reason phrase as title.
- [x] **Vault** — `StoreNotFound` created, `InvalidArgument` updated with ArcanumException. StoreNotFound lists configured stores.
- [x] **Flow** — `HandlerNotFound` (Conveyor) created with suggestion to create handler class or run validate:handlers.
- [x] **Gather** — Gather exceptions are LogicException for singleton protection (clone/unserialize) — not developer-facing errors. Skipped.
- [x] **Session** — `SessionNotStarted` created. Suggests registering SessionMiddleware.
- [x] **Auth** — Auth rejects via HttpException (already ArcanumException). No GuardNotFound throw site exists. Skipped.
- [x] **Remaining packages** (Ignition, Quill, Parchment, Toolkit, Rune) — audited. Ignition bootstrap errors already have actionable messages but fire before renderers exist. Parchment/Toolkit are low-level I/O — not developer-facing. No changes needed.

**Framework — message rewrite pass:**

- [x] **Audit all throw sites** — scan `throw new` across `src/`. For each: is the message clear? Does it say what went wrong and point toward a fix? Rewrite dry messages.
- [x] **Add "did you mean?" logic** — `Strings::closestMatch()` utility using Levenshtein distance. Wired into SqlFileNotFound, ServiceNotFound, ConnectionNotConfigured, StoreNotFound.
- [x] **Consistent message format** — every message follows: "[What went wrong] — [actionable context]." No periods at the end of single-sentence messages. Contractions allowed. No "Error:" or "Exception:" prefixes.

**Starter app:**

- [x] **`app.verbose_errors` in config/app.php** — add key, default to `app.debug`.
- [x] **Update README** — document error message conventions for app developers writing their own exceptions.

---

## Long-Distance Future

- **Hyper README** — document PSR-7 message classes, response renderers, exception renderers, format registry, file uploads, URI handling. Currently the only core package without a README.
- **RFC 9457 Problem Details for HTTP APIs** — standardized JSON error response format (`application/problem+json`). Forward-compatible with the `ArcanumException` interface (#14). When ready, it's a renderer change — exception infrastructure is already in place. See https://www.rfc-editor.org/rfc/rfc9457.html
- **Queue/Job system** — async processing with drivers (Redis, database, SQS)
- **Testing utilities** — DTO factories, service fakes, TestKernel
- **Internationalization** — translation strings, locale detection, pluralization
- **Task scheduling** — `schedule:run` cron dispatcher
- **Mail/Notifications** — thin wrappers or Symfony Mailer integration
- **Todo App dogfood** — build a fully-featured Todo app twice: once from scratch (no starter app), once using the starter app as a base. Both versions: SQLite via Forge, Vault caching, auth with sessions, Tailwind + HTMX front-end. Full CRUD, task lists, completion toggling, filtering. Step-by-step, experiencing the framework as an app developer would. Then write a retrospective: pain points, what worked, what didn't, friction in the DX, missing features, surprising gaps. The retrospective feeds back into new plan items. This is how we find what we can't see from the framework side.
- **Arcanum Wizard** — interactive project scaffolding tool (`composer create-project` or standalone script). Guides a developer through setting up a new Arcanum app: project name, database driver, cache driver, auth (yes/no), Tailwind + HTMX (or bring your own), session config, etc. Generates `config/` files, `composer.json`, directory structure, and a working entry point based on answers. **Must wait until after the Todo App dogfood and retrospective** — we need to know what the real setup experience is before we try to automate it.

---

## Performance Notes

<details>
<summary>Reflection caching — explored and rejected (click to expand)</summary>

Benchmarked three reflection caching approaches (in-memory, flyweight facade, APCu persistence) under production conditions (nginx + PHP-FPM + opcache + JIT). PHP 8.4 reflection is already fast enough — caching produced no measurable throughput improvement (~300 req/s ceiling dominated by FPM/FastCGI overhead, not reflection).

Open question: 3→10 DTO fields drops throughput 77% — worth profiling.

</details>

<details>
<summary>Benchmark harness (click to expand)</summary>

```bash
BD=$(mktemp -d /tmp/arcanum_bench.XXXXXX)

cat > "$BD/nginx.conf" << CONF
worker_processes 4;
error_log /dev/null;
pid $BD/nginx.pid;
events { worker_connections 1024; }
http {
    access_log off;
    upstream php-fpm { server 127.0.0.1:9199; }
    server {
        listen 8299;
        root /path/to/arcanum/public;
        index index.php;
        location / { try_files \$uri /index.php\$is_args\$args; }
        location ~ \.php\$ {
            fastcgi_pass php-fpm;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            include /opt/homebrew/etc/nginx/fastcgi_params;
        }
    }
}
CONF

cat > "$BD/php-fpm.conf" << CONF
[global]
error_log = $BD/fpm_error.log
pid = $BD/php-fpm.pid
daemonize = yes
[www]
listen = 127.0.0.1:9199
pm = static
pm.max_children = 8
php_admin_value[opcache.enable] = 1
php_admin_value[opcache.validate_timestamps] = 0
php_admin_value[opcache.jit] = tracing
php_admin_value[opcache.jit_buffer_size] = 64M
CONF

php-fpm --fpm-config "$BD/php-fpm.conf" && nginx -c "$BD/nginx.conf"
for i in {1..200}; do curl -s http://127.0.0.1:8299/health.json > /dev/null; done
ab -n 10000 -c 20 -q http://127.0.0.1:8299/health.json
nginx -s stop; kill $(cat "$BD/php-fpm.pid"); rm -rf "$BD"
```

</details>

---

## Closed Questions

<details>
<summary>Decided — preserved for context (click to expand)</summary>

- ~~Bootstrap lifecycle hooks~~ — Won't do. App controls the Kernel subclass.
- ~~Handler auto-discovery~~ — Won't do for runtime. `validate:handlers` CLI command covers build-time.
- ~~Command response bodies~~ — Won't do. Location header is the answer.
- ~~SQL query builder~~ — Won't do. SQL is a first-class citizen.
- ~~Full ORM / Active Record~~ — Won't do. Fights CQRS.
- ~~WebSocket / real-time~~ — Won't do in core. Optional add-on.
- ~~Asset compilation~~ — Won't do. JS tools handle this.
- ~~Full template engine~~ — Won't do. Shodo covers lightweight pages.
- ~~Reflection caching~~ — Won't do. Benchmarked, no measurable improvement.

</details>
