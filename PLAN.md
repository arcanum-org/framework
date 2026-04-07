# Framework Completion Plan

---

## Completed Work

2455 tests, PHPStan level 9 clean.

### Core packages

Cabinet, Codex, Echo, Flow (Pipeline, Continuum, Conveyor, River, Sequence), Gather, Glitch, Hyper, Ignition, Atlas, Shodo, Rune, Parchment, Quill, Toolkit. Full test coverage, all READMEs written.

### 1. Security Primitives — Toolkit

Encryption (SodiumEncryptor, XSalsa20-Poly1305), hashing (BcryptHasher, Argon2Hasher), random (Random utility), HMAC signing (SodiumSigner). Bootstrap\Security wires from APP_KEY. `make:key` CLI command.

### 2. Validation — new package

Attribute-based validation on DTO constructor params. 10 built-in rules (NotEmpty, MinLength, MaxLength, Min, Max, Email, Pattern, In, Url, Uuid, Callback). ValidationGuard Conveyor middleware fires before handlers. 422 on HTTP, field-level errors on CLI. Custom rules via Rule interface.

### 3. Caching — Vault

PSR-16 caching with 5 drivers (File, Array, Null, APCu, Redis). CacheManager for named stores. PrefixedCache decorator. Framework caches (config, templates, pages, middleware) migrated onto Vault. `cache:clear` and `cache:status` CLI commands.

### 4. Scaffolding Generators

Generator base class with stub templates (app-level overrides supported). `make:command`, `make:query`, `make:page`, `make:middleware`. Stubs use Shodo TemplateCompiler.

### 5. Sessions

HTTP session management with configurable drivers (file, cookie, cache). ActiveSession request-scoped holder. SessionMiddleware handles start/save/cookie. CSRF protection via CsrfMiddleware + `csrf` template directive. Bootstrap\Sessions wires from config/session.php.

### 6. Auth & Authorization

Identity interface, Guards (Session, Token, Composite), AuthMiddleware (HTTP), CliAuthResolver (CLI). Authorization via DTO attributes (#[RequiresAuth], #[RequiresRole], #[RequiresPolicy]). AuthorizationGuard Conveyor middleware. CLI sessions: Prompter, CliSession (encrypted file store), LoginCommand, LogoutCommand. Priority chain: --token → session → ARCANUM_TOKEN env. CSRF/Auth coordination: AuthMiddleware sets PSR-7 request attribute for token-authenticated requests; CsrfMiddleware skips CSRF for those. Guard config supports array syntax: `'guard' => ['session', 'token']`.

### 7. HTTP Client

PSR-18 HTTP client wrapper. Complete.

### 8. Custom App Scripts

Rune extension for app-defined CLI scripts. Complete.

### 9. Persistence — Forge

SQL files as first-class methods. Connection interface with PdoConnection (MySQL, PostgreSQL, SQLite). ConnectionManager with read/write split and domain mapping. Model maps `__call` to .sql files with PHP named/positional/mixed arg support. `@cast` (int, float, bool, json) and `@param` annotations. Sql utility with SqlScanner for comment/string-aware parsing. Database service with domain-scoped model access and transactions. DomainContext + DomainContextMiddleware for automatic domain scoping. ModelGenerator with stub templates and app-level overrides. forge:models, validate:models, db:status CLI commands. Dev-mode auto-regeneration with configurable auto_forge. Bootstrap\Database wires from config/database.php.

### 10. Template Helpers — Shodo extension

Static-method-call syntax in templates: `{{ Route::url('...') }}`, `{{ Format::number($price, 2) }}`. HelperRegistry, HelperResolver (domain-scoped via co-located `Helpers.php` files), HelperDiscovery. Five built-in helper groups: Route, Format, Str, Html, Arr. `{{ csrf }}` directive. Compiler rewrites `Name::method(...)` to `$__helpers['Name']->method(...)`.

### 11. Rate Limiting — Throttle

New `Throttle` package under `src/Throttle/`. Token bucket and sliding window strategies behind a `Throttler` interface. `RateLimiter` entry point takes `CacheInterface`, returns a `Quota` value object with `isAllowed()`, `headers()` (X-RateLimit-* + Retry-After). Starter app gets `RateLimit` middleware extracting key from IP, throws `HttpException(StatusCode::TooManyRequests)` on reject, adds headers on allow. `config/throttle.php` for limit/window/strategy. Throttle README documents algorithms.

### 12. Kernel Lifecycle Events

Hyper\Event classes: `RequestReceived` (mutable, listeners can rewrite the request), `RequestHandled` / `RequestFailed` / `ResponseSent` (read-only — middleware is the last word on the response). HyperKernel dispatches them around the middleware stack; `terminate()` calls `fastcgi_finish_request()` then dispatches `ResponseSent`. Starter ships `App\Http\Listener\RequestLogger` listening to RequestReceived (records start time on a request attribute) and RequestHandled (logs method/path/status/duration via the `requests` channel). Ignition README documents the events table and the "use middleware to wrap, listeners to react" rule.

### 13. Default Styling & Front-End Integration

`DESIGN.md` warm editorial system: parchment canvas, Lora headings, Inter body, copper accent, dark mode. Framework ships `HtmlExceptionResponseRenderer` with self-contained inline styles, friendly default descriptions for common status codes (400/401/403/404/405/406/422/429/500/503), and an app-override mechanism via `{errorTemplatesDirectory}/{code}.html`. `HtmlFallbackFormatter` styled to match. Starter app uses Tailwind CSS (CDN play script in dev, built `app.min.css` in prod via `composer css:build` / `css:watch`, production guardrail logs a WARNING when CDN is detected without the bundle), `darkMode: 'class'` toggle persisted to localStorage with OS preference detection. Shodo gained layout support (`extends`, `section`, `yield`), `include` directive, and HTMX fragment rendering (HX-Request header skips the layout wrapper). Starter ships HTMX 2.0.4 pinned, `HtmxMiddleware` (Location → HX-Location), styled base layout with shared nav/footer partials, styled Index/Contact/Messages/Health pages, and a README front-end section.

### 14. Error Message Personality Pass

`ArcanumException` interface in Glitch with `getTitle()` + `getSuggestion()` (RFC 9457 forward-compatible). `app.verbose_errors` config gates suggestion display, independent from `app.debug`. Renderers updated: JsonExceptionResponseRenderer always includes title, includes suggestion when verbose; HtmlExceptionResponseRenderer renders suggestion as a styled aside. Named exceptions per package, each implementing ArcanumException over the right PHP built-in: Cabinet (ServiceNotFound, CircularDependency), Codex (UnresolvableParameter, ClassNotFound), Forge (SqlFileNotFound, InvalidModelMethod, ConnectionNotConfigured, UnsupportedDriver), Atlas (UnresolvableRoute, MethodNotAllowed), Shodo (UnknownHelper, UnsupportedFormat), Vault (StoreNotFound, InvalidArgument), Flow\Conveyor (HandlerNotFound), Session (SessionNotStarted), Glitch HttpException. Suggestions computed at the throw site (nearby files, registered services, allowed formats). `Strings::closestMatch()` Levenshtein helper wired into the four "did you mean?" exceptions. Consistent message format pass across `src/`. Starter app picks up `app.verbose_errors` config + README conventions.

### Shodo conditionals + directive cleanup

Settled the directive inconsistency: layout/structure directives used to be `@`-prefixed, control structures were bare keywords. Dropped `@` from everything and adopted a single rule for the inside of `{{ }}`: starts-with-`$` → variable, starts-with-uppercase → helper call, starts-with-lowercase → directive. Mirrors PHP itself. Tolerant `if`/`elseif`/`for`/`while`/`foreach` regex accepts paren-free, paren-wrapped, and PHP-alt-syntax forms — compiler normalises to canonical `<?php KEYWORD (EXPR): ?>`. New `match`/`case`/`default`/`endmatch` pre-pass compiles to PHP `switch` alt-syntax with implicit breaks; comma-separated case values fall through. Migrated test fixtures, starter templates, README, and added 12 new tests for the new behaviours.

### Cache management gaps

`cache.framework.enabled` master switch (independent of `app.debug`) makes every framework cache a no-op when off — `CacheManager::frameworkStore()` returns NullDriver, `Bootstrap\Formats` consults the flag for the disk-only TemplateCache. `cache:clear` now reaches every framework cache surface: Vault stores, structured framework caches, and a stray-subdirectory walk in `files/cache/` as a safety net for any future cache. CLI bootstrap got a TemplateCache factory so `Cleared template cache` actually fires. Latent `TemplateCache::store()` filesystem-root crash fixed. Template cache invalidation on layout/include change: TemplateCompiler tracks dependencies, TemplateCache stores them as a JSON header, `isFresh()` checks every dep's mtime. Vault README documents the bypass switch and a framework cache inventory table.

### Forge Sub-Model Redesign

Subdirectories in `Model/` become independent, autowireable model classes. Generated classes have zero constructors — they inherit `(ConnectionManager)` from the base class. Methods pass `__DIR__ . '/File.sql'` directly. Base class `__call` derives its directory via reflection. Single stub for all models. `forge:models` and `validate:models` handle root + sub-model generation/validation. Handlers inject generated model classes directly for full type safety. `$db->model` still works for backwards compatibility.

### Flow\Sequence + Forge Read/Write Split

Forge\Result is gone. Replaced with two separate types and a generic ordered-iterable subpackage:

- **`Flow\Sequence\Sequencer<T>`** — `@template-covariant` interface extending `IteratorAggregate<int, T>`. Method set: `first(): ?T`, `each`, `map`, `filter`, `chunk`, `take`, `toSeries`. Only operations honest on both lazy and eager shapes live on the interface.
- **`Flow\Sequence\Cursor<T>`** — lazy, single-pass, self-closing via `CloseLatch`. Throws `CursorAlreadyConsumed` on second iteration. `__destruct` runs the close callback if iteration never happened. Derived cursors share the latch.
- **`Flow\Sequence\Series<T>`** — eager, multi-pass, list-backed. Adds `count`, `all`, `isEmpty` (the operations that would force a Cursor to silently materialize stay here).
- **`Forge\Connection`** — `query(): Sequencer` for reads, `execute(): WriteResult` for writes. Caller declares intent at the call site, no read/write sniffing inside the connection.
- **`Forge\PdoConnection::query()`** — wraps `$statement->fetch()` in a generator and returns a `Cursor` whose close callback calls `closeCursor()`. Streams row-by-row regardless of result size.
- **`Forge\WriteResult`** — immutable affectedRows + lastInsertId.
- **`Forge\Cast`** — pure helper producing a row-mapping closure from a `@cast` annotation map. Self-contained; absorbed `Sql::castValue` and `Sql::castBool` so the dead `Sql::applyCasts` could be deleted.
- **`Forge\Model`** — `__call` returns `Sequencer|WriteResult` (union for the dynamic path). Internal `read()` / `write()` methods give generated subclasses tight return types. ModelGenerator parses each SQL file once at generation time and emits per-method narrow signatures via split `model_method_read.stub` / `model_method_write.stub`. The class stub computes its imports dynamically — read-only models don't import WriteResult, write-only don't import Sequencer.
- **Streaming benchmark.** Eager scaled linearly in memory (1.6 GB at 500k rows); generator stays flat at 6.3 KB regardless. Generator is equal or faster from 10 rows up. Streaming is the default; materialization is the explicit opt-in via `toSeries()`.

Phases 1, 2, 3 complete. Forge README rewritten around `Sequencer` + `WriteResult` with a Cursor-based "Bring your own DBAL" example. New `src/Flow/Sequence/README.md` covers the interface, both implementations, the `toSeries()` escape hatch, the benchmark table, and a when-to-use-which decision table. `src/Flow/README.md` updated to mention the new subpackage.

### Shodo #[WithHelper] attribute

Per-DTO template helpers via a class-level attribute. `Arcanum\Shodo\Attribute\WithHelper` is repeatable; takes class name + explicit alias as two required positional args. `HelperResolver::for($dtoClass)` reads the attributes via reflection and merges them onto the helper set with the highest precedence — global registry ← domain-discovered Helpers.php ← #[WithHelper] attributes. Use this for narrow, page-specific helpers (welcome page diagnostics, admin one-offs); keep `Helpers.php` files for genuinely shared functionality. Auto-stripping of `Helper` suffix was tried and dropped — the "obvious" auto-derived alias was almost never the one anyone wanted, and inconsistent with the explicit-alias convention everywhere else in the system. Now all three registration paths (HelperRegistry::register, Helpers.php map, #[WithHelper]) require explicit aliases.

### Welcome page helpers — Group 1

The data layer behind the upcoming Index page redesign:

- **`App\Helpers\EnvCheckHelper`** — diagnostic facts: phpVersion / phpVersionOk / extensions (sodium, pdo, json, mbstring, openssl) / filesWritable / cacheWritable / logsWritable / sessionsWritable / cacheDriver / sessionDriver / databaseConnection / cssBuilt / debugMode / appEnvironment / frameworkVersion (via `Composer\InstalledVersions`) / renderDurationMs / requestCount. Static checks per request, no caching.
- **`App\Helpers\WiredUpHelper`** — counts: commands / queries (filesystem walks excluding `*Handler.php`) / pages (PageDiscovery) / middleware (MiddlewareDiscovery) / helpers (HelperRegistry). `services()` deliberately omitted (Cabinet is PSR-11, no enumeration; the count would mislead).
- **`App\Helpers\IncantationHelper`** — rotating tip-of-the-day, 5 placeholder tips currently (will grow to ~15 in the content pass). `today()` picks deterministically by `date('z') % count`. Pure, no I/O.
- **`App\Http\Listener\RequestCounter`** — listens to RequestHandled, increments `framework.requests` in the default Vault store. Counter survives across requests, resets with `cache:clear`.
- **`App\Http\RenderMetrics`** — request-scoped holder for the request start time. RequestLogger writes it on RequestReceived. (Will be replaced by `Toolkit\Stopwatch` once that lands; tracked under the Stopwatch section below.)
- **`#[WithHelper]` wiring on `Index.php`** — declares Env / Wired / Tip aliases on the page DTO. No global registration; other pages stay clean.
- **Tests for each helper.** Smoke probe verified end-to-end resolution: php=8.4.3 | env=development | pages=1 | helpers=6 | requests=N.

Bonus framework fixes from the same arc: bound `HelperRegistry` to the container at bootstrap (was being auto-wired empty); bumped the starter app's dev toolchain to PHPStan 2.x and PHPUnit 13.x (matches the framework); narrowed the `$rootDirectory` resolution in `bootstrap/{cli,http}.php` to satisfy phpstan 2.x's stricter mixed checks; renamed `app/HTTP` → `app/Http` to fix a latent PSR-4 case bug.

### Hourglass — time package + framework Stopwatch

New `src/Hourglass/` package owns all time primitives. Originally planned as a single `Arcanum\Toolkit\Stopwatch` class, but once PSR-20 entered the picture the scope grew into a coherent time subsystem worth its own namespace and README.

- **`Clock`** interface extending `Psr\Clock\ClockInterface` so PSR-20 consumers work without ceremony.
- **`SystemClock`** — production wall-clock.
- **`FrozenClock`** — caller-controlled pinned clock. Documented as a general primitive (replay, batch jobs, simulations, deterministic tests), not a "test double".
- **`Stopwatch`** — process-lifetime timeline recorder. Records `arcanum.start` in the constructor; accepts an optional explicit start time so entry points can pass an early `ARCANUM_START` constant and the recorded start reflects the true earliest moment (not when the container resolved the Stopwatch). API: `mark`, `has`, `timeSince` (most-recent label match), `timeBetween` (first occurrence of each), `startTime`, `marks`. Storage is `list<Instant>` so duplicate labels are preserved and the timeline reads as truth.
- **`Instant`** — readonly value object (label + time). Carries its own label so a `list<Instant>` reads as a complete timeline without lookup ceremony.
- **Static accessor.** `Stopwatch::install($stopwatch)` stores the bootstrap-resolved instance as a process-global; `Stopwatch::current()` reads it (throws loudly when uninstalled — read sites should fail loud); `Stopwatch::tap($label)` is a write-only helper that delegates to the installed instance and is a no-op when none is installed (right ergonomic for middleware/formatter/listener call sites that just want to mark one line). Singleton-by-convention via `Bootstrap\Stopwatch`, not enforced by the class — keeps tests and library code free to construct private timelines.

**Framework wiring:**

- **`Bootstrap\Stopwatch`** — first bootstrapper for both kernels. Reads `ARCANUM_START` if defined, builds the Stopwatch, registers it as a singleton instance on the container, and calls `Stopwatch::install()`.
- **`HyperKernel` / `RuneKernel`** — mark `boot.complete` after the bootstrapper loop.
- **`HyperKernel` lifecycle** — marks `request.received`, `request.handled`, `response.sent` from the existing dispatch points (no separate Echo listeners needed; the kernel already owns the seam).
- **`Flow\Conveyor\MiddlewareBus`** — marks `handler.start` / `handler.complete` around dispatch (`finally` clause covers exception paths).
- **5 `*ResponseRenderer`** classes — mark `render.start` / `render.complete` around `format()`. JSON, HTML, CSV, PlainText, Markdown.
- **`composer require psr/clock`** added.
- **Hourglass README** covers Clock and Stopwatch separately, the `ARCANUM_START` rationale, the tap-vs-current contract, and the built-in marks table.

**Starter app migration:**

- `public/index.php` and `bin/arcanum` already defined `ARCANUM_START` — no entry-point changes needed.
- `App\Http\RenderMetrics` deleted (class + test + bootstrap registration).
- `RequestLogger` no longer subscribes to `RequestReceived`; reads `timeSince('request.received')` from the framework Stopwatch on `RequestHandled`.
- `EnvCheckHelper` depends on `Stopwatch` directly; `renderDurationMs()` reads `timeSince('request.received')`.

**Built-in marks (always on):**

| Mark | When |
|---|---|
| `arcanum.start` | Bootstrap\Stopwatch (or `ARCANUM_START` constant) |
| `boot.complete` | After all bootstrappers run |
| `request.received` | HyperKernel before middleware |
| `handler.start` / `handler.complete` | Conveyor dispatch boundaries |
| `render.start` / `render.complete` | ResponseRenderer boundaries |
| `request.handled` | HyperKernel after response built |
| `response.sent` | HyperKernel::terminate after fastcgi_finish_request |
| `arcanum.complete` | End of terminate() — last thing the framework does before process exit. Pairs symmetrically with `arcanum.start` for total process lifetime; captures any post-`response.sent` listener time. RuneKernel marks it from its own `terminate()`. |

**Open follow-ups (deferred):**

- **Per-middleware marks** — `App\Http\Middleware\StopwatchMiddleware` that marks `middleware.{name}.start` / `.complete` around each PSR-15 middleware. Opt-in via debug flag.
- **Per-bootstrapper marks** — same idea for the bootstrapper loop. Debug-mode only.
- **Debug toolbar** — render `Stopwatch::marks()` as an inline timeline strip at the bottom of HTML responses in debug mode.
- **Phase context in log lines** — `RequestLogger` includes a `phases` dict with all marks in its log context.
- **PSR-20 elsewhere in framework** — Sessions, Auth, Throttle, Vault, Forge `@cast` for timestamps. Each currently calls `time()` / `new DateTime()` directly. Adopting `Clock` everywhere is its own cleanup arc — probably groups with the testability/TestKernel work in the long-distance list.

### Shodo/Hyper rendering refactor

Shodo decoupled from Hyper — formatters produce strings, response renderers build HTTP responses. Five phases: interface extraction, ResponseRenderer classes, old code deletion, Bootstrap rewiring, pipeline verification.

### Security fixes

All complete: Bearer token CSRF bypass removed, CSRF/Auth coordination via request attributes, `#[Url]` restricted to http/https, Model path traversal fixed, JsonFormatter JSON_HEX_TAG added, template eval() security documented, Pattern regex ReDoS documented.

### DX guardrails

All complete: ValidationGuard missing detection, `#[AllowedFormats]` attribute (406 Not Acceptable), unused template variable warning (TemplateAnalyzer), handler error messages, `validate:handlers` promotion, circular dependency detection, template undefined variable errors, `factory()` caching documented, bootstrapper ordering enforcement, page discovery warning.

### Additional features

- **Markdown formatter** — template-based with `.md` files, identity escape, fallback renderer, MarkdownResponseRenderer.
- **Command response Location headers** — LocationResolver builds URLs from returned Query DTO instances (class → path, properties → query params). 201 Created + Location header.
- **Bootstrap\Routing split** — split into Bootstrap\Formats, Bootstrap\Routing (slimmed), Bootstrap\Helpers.
- **SqlScanner** — extracted character-level SQL lexer from Forge\Sql::extractBindings() into reusable SqlScanner class.

### Starter project

Full CQRS pipeline: Router → Hydrator → Conveyor → Renderer. Example Query (Health), Page (Index). HTTP + CLI entry points. Config files with comments. Getting-started README covering quick start, CQRS concepts, directory structure, validation, auth, response formats, testing, and development workflow. Contact domain was added as a database example, then yanked once it served its purpose — `config/database.php` and the SQLite connection stay around for the upcoming todo app. Example test (HealthHandlerTest).

---

## Upcoming Work

### Starter app — index page redesign (Groups 2–5)

**Group 1 (data layer)** is complete and lives in the collapsed "Welcome page helpers — Group 1" entry above. What remains is the actual page redesign that consumes those helpers, plus content writing and verification.

#### Design narrative + section spec

Researched the Symfony 8 and CakePHP 5 welcome pages. Symfony is polished and resource-focused (banner, "Next Step" CTA, three columns of links). CakePHP is diagnostic and reassuring (version banner, filesystem/database health checks with green/red bullets). Our current index is sparse — hero + two CTAs + a CQRS card grid, no version, no environment info. The new index combines all three: Symfony's polish, CakePHP's diagnostic checklist, and our CQRS explainer (uniquely valuable — neither competitor explains its own mental model on the first page).

The page should tell a new dev: (1) the framework is alive and healthy, (2) what's wired up right now, (3) what to do in the next 60 seconds, and (4) why CQRS instead of MVC — without being preachy.

**Sections, top to bottom:**

1. **Heartbeat badge** — single dense monospace line at the very top: `Arcanum v0.x.y · PHP 8.4.3 · env: local · db: sqlite · debug: ON`. Symfony-style; the most useful single line on the page.
2. **Welcome banner** — `Welcome to Arcanum`, tagline, and "this page lives at app/Pages/Index.html — replace it" hint.
3. **Today's incantation** — rotating tip-of-the-day card from `IncantationHelper::today()`. Format: short title, one-line explanation, optional code snippet.
4. **Diagnostics — two columns** — Environment checks (PHP version, extensions, writable dirs) and Application checks (cache driver, logs, sessions, database, CSS bundle, debug mode). Plain professional language, green check / yellow warning / red cross bullets via Tailwind.
5. **What's wired up** — small introspection panel showing live counts from `WiredUpHelper`: `commands · queries · pages · middleware · helpers`. Doubles as a smoke test.
6. **Why CQRS (not MVC)** — replaces the generic three-card "How It Works" grid. Two short paragraphs framing the choice as deliberate. Headline angle: *MVC controllers grow into junk drawers. CQRS keeps each operation small, named, and testable.* Beneath the prose, **inline mini demo**: tabbed code block (pure CSS `:target` tabs) showing a 4-line Query, a 4-line Command, and a 4-line Page side-by-side.
7. **Your next 60 seconds / 10 minutes / 1 hour** — three progressive-commitment cards replacing the LEARN/COMMUNITY/BUILD grid. 60s = copy a `make:page Home` command. 10min = inline 3-paragraph CQRS primer. 1hr = getting-started guide, source link, GitHub repo. Each command/snippet has a tiny copy-to-clipboard button (vanilla JS).
8. **Footer crumb** — single understated line: `This page rendered in 3.2ms. You are request #47 since boot.` Pulled from `EnvCheckHelper::renderDurationMs()` and `requestCount()`.
9. **ASCII rune in the corner** — small SVG or pre-formatted ASCII glyph in the page footer.
10. **Nice-to-have: `?debug=1` easter egg** — toggling the query param replaces the welcome banner with a visualization of the resolved bootstrap order. Optional, ship only if the rest lands cleanly.

**Design decisions, settled:** static checks per request (no caching — page renders rarely, accuracy matters); page is not auto-disabled in production (the user replaces `app/Pages/Index.html` themselves — replacing the file is the explicit signal that they're making the app their own); framework version via `Composer\InstalledVersions::getVersion('arcanum-org/framework')`.

**Plan items — page and templates:**

- [ ] **Index page redesign** — rewrite `app/Pages/Index.html` to the nine-section structure above. One file, no partials (this is the welcome page, it should be readable as a single document).
- [ ] **CSS — status bullets** — green check / yellow warning / red cross via Tailwind utility classes. No new CSS file.
- [ ] **CSS — `:target` tabs** — pure CSS tabbed code block for the inline CQRS mini demo. No JS.
- [ ] **Copy-to-clipboard buttons** — one tiny inline `<script>` block at the bottom of the page wiring `[data-copy]` buttons to `navigator.clipboard.writeText`. Visual feedback on click (text swap to "Copied!" for 1.5s).
- [ ] **ASCII/SVG rune mark** — small decorative glyph in the footer area.
- [ ] **Placeholder example.com URLs** for docs/tutorial/api links. Tracked in the cleanup section below.

**Plan items — content:**

- [ ] **Write the "Why CQRS" prose** — two short paragraphs. Confident, not preachy. Frame MVC controllers as junk drawers; frame CQRS handlers as small, named, testable. No marketing fluff.
- [ ] **Write the 15 incantations** — short, real, useful. Lean toward things a new user wouldn't discover from skimming the README. Replaces the placeholder set currently in `IncantationHelper`.
- [ ] **Write the three progressive-commitment card bodies** — 60s / 10min / 1hr.

**Plan items — verification:**

- [ ] **Smoke test happy path** — fresh starter app, all checks green, all counts non-zero, render duration shows, request counter increments across reloads, incantation rotates with `date('z')`.
- [ ] **Smoke test failure path** — `chmod -w files/cache/` flips the cache bullet red without crashing the page; dropping the database file flips the database bullet without crashing.
- [ ] **Tab demo works without JS** — disable JS in browser, confirm `:target` tabs still switch.
- [ ] **Copy buttons work** — click each, confirm clipboard contents and visual feedback.

**Nice-to-have (defer if time runs short):**

- [ ] **`?debug=1` bootstrap visualization** — replaces welcome banner with bootstrap order list when query param is set.

**Plan items — placeholder URL cleanup (deferred until real docs exist):**

- [ ] **Replace `https://example.com/docs`** in starter app Index page with real documentation URL.
- [ ] **Replace `https://example.com/tutorial`** with real tutorial URL.
- [ ] **Replace `https://example.com/api`** with real API reference URL.
- [ ] **Replace `https://example.com/discussions`** with real community URL (Discord/Slack/GitHub Discussions).

GitHub source and issues URLs will use the real `arcanum-org/framework` repo links — those exist already.

### Shodo — expression-aware helper call rewriting

Surfaced while wiring the welcome page index helpers. The `IncantationHelper::today()` method returns an array shape `{title, body, code}`, and the natural way to read the title from a template is `{{ Tip::today()['title'] }}`. Today, that throws `Class "Tip" not found` because Shodo's helper-rewrite regex matches only the exact form `{{ Name::method(args) }}` with no trailing PHP. The leftover `['title']` between the captured method call and `}}` defeats the regex, so the rewrite doesn't fire and PHP later tries to resolve a literal class named `Tip`.

The current shape of the compiler:

```php
private const HELPER_PATTERN = '([A-Z][a-zA-Z0-9]*)::(\w+)((?:\(...\)))';

// Helper calls in escaped output: {{ Route::url('x') }}
$compiled = $this->replaceCallback(
    '/\{\{\s*' . self::HELPER_PATTERN . '\s*\}\}/s',
    fn (array $m) => '<?= $__escape((string)($__helpers[\'' . $m[1] . '\']->' . $m[2] . $m[3] . ')) ?>',
    $compiled,
);
```

The regex captures `Name::method(args)` and demands `\}\}` immediately after. No room for `[...]`, `->next()`, `+ 1`, ternaries, `??`, or any other PHP expression continuation. Devs hit this the moment their helper returns anything other than a scalar.

**The fix: treat the body of `{{ ... }}` as a PHP expression.** Instead of matching the entire expression with one regex, the compile pass becomes:

1. Find `{{ ... }}` (escaped) or `{{! ... !}}` (raw) and capture the inside.
2. Rewrite *every* `Name::method(args)` occurrence inside the captured body via `preg_replace_callback`. Each match becomes `$__helpers['Name']->method(args)`.
3. Wrap the result in `<?= $__escape((string)(...)) ?>` (escaped) or `<?= ... ?>` (raw).

After step 2 the body is valid PHP. Anything PHP allows after a method call — `[...]`, `->`, `?->`, `+`, `*`, ternary, `??`, `instanceof` — Just Works because PHP itself parses the result. Multiple helper calls in one expression compose: `{{ Format::number(Math::pi(), 2) }}` rewrites both occurrences.

The dedicated "Helper calls in escaped output" and "Helper calls in raw output" passes go away — the rewriter lives inside the general output pass. Net code reduction in `TemplateCompiler::compile()`.

**Decisions, settled:**

- **The uppercase-first-letter rule is the contract.** A real PHP static call inside a template would be a name collision against an alias. Rare, but the escape hatch is the FQCN with leading `\` (e.g. `\App\Foo::bar()` — the leading backslash means the regex won't match). Document this in the README.
- **String literals are out of scope.** The current regex doesn't handle `'A::b()'` either, and it has never been a problem because templates don't quote helper-shaped strings. Leaving it unhandled keeps the regex tractable.
- **Single output pass per body.** The rewrite happens inside the body capture for both `{{ }}` and `{{! !}}` callbacks. No separate "helper-only" pass.

**Plan items:**

- [ ] **`TemplateCompiler::compile()` rewrite** — replace the dedicated helper-call passes with a body-aware rewrite inside the escaped/raw output passes. The escaped output callback runs the helper rewriter on the captured body, then wraps in `$__escape((string)(...))`. Same for raw output. Delete the four lines defining the standalone helper-call patterns.
- [ ] **`HELPER_PATTERN` simplification** — the constant becomes the inner pattern used by the body rewriter, no longer anchored to `\{\{` / `\}\}`. May rename to `HELPER_CALL_PATTERN` to reflect its new role.
- [ ] **`tests/Shodo/TemplateCompilerTest.php`** — add cases for: array access (`Tip::today()['title']`), arrow access (`User::current()->name`), arithmetic (`Math::pi() + 1`), ternary (`Env::debugMode() ? 'on' : 'off'`), null coalesce, nested helper calls (`Format::number(Math::pi(), 2)`), helper call followed by string concatenation. Plus: helper call inside an `if` condition (`{{ if Env::debugMode() }}`), inside a `foreach` (`{{ foreach Wired::list() as $item }}`).
- [ ] **`tests/Shodo/HelperResolverTest.php`** — no change needed; the resolver doesn't care about call shape.
- [ ] **README update** — note that the inside of `{{ }}` is "any PHP expression with helper calls auto-rewritten", document the FQCN escape hatch, mention the multi-helper-per-expression composition.
- [ ] **Test fixture migration** — if any existing template fixtures use the workaround (variable binding to a helper-call result), simplify them to the natural form to verify the new path. Most fixtures use scalar-returning helpers and won't need changes.

**Open follow-ups (defer):**

- **String-literal awareness.** If a template ever needs to print the literal text `Foo::bar()`, the rewrite will mangle it. Could be addressed with a string-literal scanner like the one in `Forge\SqlScanner`, but the current need is zero. Revisit when a real fixture surfaces it.
- **`{{ if Helper::method() }}` syntax.** The control-structure regex has its own helper-call awareness today; verify the new body rewriter doesn't double-process. May need to apply the helper rewriter to control-structure expression captures too — covered by the test items above.

---

## Long-Distance Future

- **FastCGI / post-response work patterns** — Surfaced while adding `arcanum.complete`. Arcanum currently calls `fastcgi_finish_request()` in `HyperKernel::terminate()` and dispatches `ResponseSent` afterwards, but there is no formal "deferred work" abstraction beyond the listener — no queueing semantics, no per-listener time budget, no documentation of what is and isn't safe to do post-response, no story for non-FCGI SAPIs (CLI workers, RoadRunner, FrankenPHP, Swoole) that lack `fastcgi_finish_request()` entirely. Worth a focused pass: document the contract, decide whether to formalize a `DeferredWork` hook above raw `ResponseSent` listeners, and consider how `arcanum.complete` should behave under long-running runtimes where there is no "process exit" per request. Until then, `arcanum.complete` measures what it measures (end of `terminate()`), and consumers should treat it as "framework work done" rather than "process exit".
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

### Reflection caching — explored and rejected

Benchmarked three reflection caching approaches (in-memory, flyweight facade, APCu persistence) under production conditions (nginx + PHP-FPM + opcache + JIT). PHP 8.4 reflection is already fast enough — caching produced no measurable throughput improvement (~300 req/s ceiling dominated by FPM/FastCGI overhead, not reflection).

Open question: 3→10 DTO fields drops throughput 77% — worth profiling.

### Benchmark harness

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

---

## Closed Questions

### Decided — preserved for context

- ~~Bootstrap lifecycle hooks~~ — Won't do. App controls the Kernel subclass.
- ~~Handler auto-discovery~~ — Won't do for runtime. `validate:handlers` CLI command covers build-time.
- ~~Command response bodies~~ — Won't do. Location header is the answer.
- ~~SQL query builder~~ — Won't do. SQL is a first-class citizen.
- ~~Full ORM / Active Record~~ — Won't do. Fights CQRS.
- ~~WebSocket / real-time~~ — Won't do in core. Optional add-on.
- ~~Asset compilation~~ — Won't do. JS tools handle this.
- ~~Full template engine~~ — Won't do. Shodo covers lightweight pages.
- ~~Reflection caching~~ — Won't do. Benchmarked, no measurable improvement.
- ~~`#[WithHelper]` auto-strip alias~~ — Tried `EnvCheckHelper` → `EnvCheck`. Confused even its own author. Now requires explicit alias, matching every other registration path.
