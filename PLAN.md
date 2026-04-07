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

**Expanded scope — late-night brainstorm, agreed direction:**

The wireframe is the skeleton. On top of it we layer personality and signal density. The page should tell a new dev: (1) the framework is alive and healthy, (2) what's wired up right now, (3) what to do in the next 60 seconds, and (4) why CQRS instead of MVC — without being preachy.

**Sections, top to bottom:**

1. **Heartbeat badge** (#9) — single dense monospace line at the very top: `Arcanum v0.x.y · PHP 8.4.3 · env: local · db: sqlite · debug: ON`. Replaces the version-only banner. Symfony-style; the most useful single line on the page.

2. **Welcome banner** — `Welcome to Arcanum`, tagline, and the "this page lives at app/Pages/Index.html — replace it" hint. Keep short.

3. **Today's incantation** (#1) — rotating tip-of-the-day card. Hardcoded array of ~15 real Arcanum tricks (the `match` directive, `#[RequiresAuth]`, `make:query`, `cache:clear`, `Env::extensions()`, etc). Picked deterministically by `date('z') % count` so it changes daily but is stable within a day. Format: short title, one-line explanation, optional code snippet. Free onboarding without a docs site.

4. **Diagnostics — two columns** — Environment checks (PHP version, extensions, writable dirs) and Application checks (cache driver, logs, sessions, database, CSS bundle, debug mode). Plain professional language, no theme cuteness — green check / yellow warning / red cross bullets. Static checks per request, no caching.

5. **What's wired up** (#4) — small introspection panel showing live counts: `12 services · 3 commands · 4 queries · 2 pages · 5 middleware · 6 helpers`. Pulled from Cabinet and the discovery caches. Doubles as a smoke test — `0 commands` instantly tells a new dev discovery didn't run.

6. **Why CQRS (not MVC)** — replaces the generic three-card "How It Works" grid. Two short paragraphs framing the choice as deliberate, not contrarian. Tone: confident, not marketing-y. They already installed it; we're just affirming the wise choice. Headline angle: *MVC controllers grow into junk drawers. CQRS keeps each operation small, named, and testable.* Beneath the prose, **inline mini demo** (#8): tabbed code block (pure CSS `:target` tabs) showing a 4-line Query, a 4-line Command, and a 4-line Page side-by-side. Devs learn by reading code.

7. **Your next 60 seconds / 10 minutes / 1 hour** (#7) — three progressive-commitment cards replacing the LEARN/COMMUNITY/BUILD grid:
   - **60 seconds** — copy a `make:page Home` command (with copy button #5). Instant action.
   - **10 minutes** — inline 3-paragraph CQRS primer + link to a deeper write-up.
   - **1 hour** — getting-started guide, source link, GitHub repo.
   Each command/snippet on the page has a tiny **copy-to-clipboard button** (#5) — vanilla JS one-liner, no dependency.

8. **Footer crumb** (#3) — single understated line: `This page rendered in 3.2ms. You are request #47 since boot.` Pulled from the lifecycle events shipped earlier (`RequestReceived` timestamp + an APCu/file counter incremented in a listener). Demonstrates the events package without ever mentioning it.

9. **ASCII rune in the corner** (#10) — small SVG or pre-formatted ASCII glyph in the page footer. Subtle. Because we're called Arcanum and it'd be a crime not to.

10. **Nice-to-have: `?debug=1` easter egg** (#6) — toggling the query param replaces the welcome banner with a visualization of the resolved bootstrap order (Environment → Configuration → Logger → Exceptions → ...). Completely optional, ship only if the rest lands cleanly.

**Plan items — helpers and data:**

- [ ] **`App\Helpers\EnvCheckHelper`** — registered as the `Env` alias. Methods: `phpVersion()`, `phpVersionOk()`, `extensions(): array<string,bool>`, `filesWritable()`, `cacheWritable()`, `logsWritable()`, `sessionsWritable()`, `cacheDriver()`, `sessionDriver()`, `databaseConnection(): ?string`, `cssBuilt()`, `debugMode()`, `frameworkVersion()`, `appEnvironment()`.
- [ ] **`App\Helpers\WiredUpHelper`** — registered as the `Wired` alias. Introspects Cabinet + discovery caches and returns counts: `services()`, `commands()`, `queries()`, `pages()`, `middleware()`, `helpers()`. Read from the existing discovery caches where available; fall back to filesystem scan.
- [ ] **`App\Helpers\IncantationHelper`** — registered as the `Incantation` alias. Hardcoded array of ~15 tip records (`['title' => ..., 'body' => ..., 'code' => ?]`). `today()` returns `$tips[date('z') % count($tips)]`. Pure, no I/O.
- [ ] **`App\Http\Listener\RequestCounter`** — listens to `RequestHandled`, increments a counter in Vault (`framework.requests` key). Exposed via a new `Env::requestCount()` reader. Also stamps render duration onto the request attribute from the `RequestReceived` listener already present.
- [ ] **`Env::renderDurationMs()`** — reads the start timestamp set by the existing `RequestLogger` listener and returns elapsed ms at render time.

**Plan items — page and templates:**

- [ ] **Index page redesign** — rewrite `app/Pages/Index.html` to the nine-section structure above. One file, no partials (this is the welcome page, it should be readable as a single document).
- [ ] **`Index.php` DTO** — keep `name` and `message` only. All other data flows through helpers.
- [ ] **CSS — status bullets** — green check / yellow warning / red cross via Tailwind utility classes. No new CSS file.
- [ ] **CSS — `:target` tabs** — pure CSS tabbed code block for the inline CQRS mini demo. No JS.
- [ ] **Copy-to-clipboard buttons** — one tiny inline `<script>` block at the bottom of the page wiring `[data-copy]` buttons to `navigator.clipboard.writeText`. Visual feedback on click (text swap to "Copied!" for 1.5s).
- [ ] **ASCII/SVG rune mark** — small decorative glyph in the footer area.
- [ ] **Placeholder example.com URLs** for docs/tutorial/api links. Tracked in the cleanup section below.

**Plan items — content:**

- [ ] **Write the "Why CQRS" prose** — two short paragraphs. Confident, not preachy. Frame MVC controllers as junk drawers; frame CQRS handlers as small, named, testable. No marketing fluff.
- [ ] **Write the 15 incantations** — short, real, useful. Lean toward things a new user wouldn't discover from skimming the README.
- [ ] **Write the three progressive-commitment card bodies** — 60s / 10min / 1hr.

**Plan items — verification:**

- [ ] **Smoke test happy path** — fresh starter app, all checks green, all counts non-zero, render duration shows, request counter increments across reloads, incantation rotates with `date('z')`.
- [ ] **Smoke test failure path** — `chmod -w files/cache/` flips the cache bullet red without crashing the page; dropping the database file flips the database bullet without crashing.
- [ ] **Tab demo works without JS** — disable JS in browser, confirm `:target` tabs still switch.
- [ ] **Copy buttons work** — click each, confirm clipboard contents and visual feedback.

**Nice-to-have (defer if time runs short):**

- [ ] **`?debug=1` bootstrap visualization** — replaces welcome banner with bootstrap order list when query param is set.

**Plan items — remove the Contact feature:**

The Contact form/page/command was useful early on as a smoke test for the full CQRS write-path (DTO → validation → handler → Forge persistence → redirect → re-read query). It's served its purpose. The upcoming todo app will cover the same ground in a more meaningful way, and keeping Contact around now just clutters the starter and competes with the new index for attention. Yank it cleanly so the starter is welcome page + health check only until the todo app lands.

- [x] **Delete the Contact domain** — `app/Domain/Contact/` (Command/Submit DTO + handler, Query/Messages DTO + handler, Model/ subdirectory with `Save.sql`, `FindAll.sql`, `CreateTable.sql`, any generated model classes).
- [x] **Delete the Contact page** — `app/Pages/Contact.php`, `app/Pages/Contact.html`.
- [x] **Remove Contact links from navigation** — `app/Templates/partials/nav.html`.
- [x] **Remove Contact references from the index page** — "Get in Touch" CTA dropped from current Index; README examples swapped to a generic Orders feature.
- [x] **Drop the contacts table migration / SQL** — gone with the Contact domain delete; nothing else wired it.
- [x] **Keep `config/database.php` and the SQLite connection** — left intact for the todo app and `Env::databaseConnection()`.
- [x] **Smoke test** — `/`, `/health.html`, `/health.json` all 200; `/contact.html` now 404; `composer check` clean (3 tests, PHPStan green).

**Plan items — placeholder URL cleanup (deferred until real docs exist):**

- [ ] **Replace `https://example.com/docs`** in starter app Index page with real documentation URL.
- [ ] **Replace `https://example.com/tutorial`** with real tutorial URL.
- [ ] **Replace `https://example.com/api`** with real API reference URL.
- [ ] **Replace `https://example.com/discussions`** with real community URL (Discord/Slack/GitHub Discussions).

GitHub source and issues URLs will use the real `arcanum-org/framework` repo links — those exist already.

### Stopwatch — framework-wide lifecycle timing

Surfaced while writing the starter app's `RenderMetrics` holder for the welcome page footer crumb. That class wanted to be one specific thing (request-scoped start time) but already aspirationally named itself for a broader concept (render metrics). The honest design is a generic, framework-level **stopwatch** that records named checkpoints across the request lifecycle and exposes them to anyone who asks. The welcome page's "rendered in X ms" is then just one of many consumers — a debug toolbar, log lines with phase breakdowns, slow-test detection in CI, and per-middleware profiling all sit on top of the same primitive.

**Decisions, settled:**

- **Lives in `Arcanum\Toolkit\Stopwatch`** — single class, fits Toolkit's "small reusable utilities" character. (Echo gets its own subpackage because it's many related classes; Stopwatch is one class.)
- **Always on, no debug gate.** `microtime(true)` + array push is single-digit nanoseconds; five core marks per request is below noise floor. The welcome page's footer crumb is a fact, not a debug feature, and should work in dev *and* prod. Heavier instrumentation (per-middleware, per-bootstrapper marks) opts in via the debug flag later if it lands.
- **Construction marks `request.start`.** Constructor captures `microtime(true)` AND adds a `request.start` entry to the marks list. Keeps the chronological timeline complete and self-explanatory — no special-casing "the start time isn't in marks but is implied".
- **Single source of truth.** Replaces the dual-write currently in `RequestLogger` (which writes start time to both a PSR-7 request attribute and the starter-app `RenderMetrics` holder). Stopwatch is the only place that knows.

**Shape:**

```php
namespace Arcanum\Toolkit\Stopwatch;

final class Stopwatch
{
    /** @var array<string, Mark> */
    private array $marks = [];

    public function __construct()
    {
        $this->mark('request.start');
    }

    public function mark(string $label): void;        // record current monotonic time
    public function has(string $label): bool;
    public function elapsedMs(): float;               // since request.start
    public function elapsedSinceMs(string $label): ?float;
    public function elapsedBetweenMs(string $from, string $to): ?float;
    public function startTime(): float;               // microtime(true) of request.start

    /** @return list<Mark> in insertion order */
    public function marks(): array;
}

final class Mark
{
    public function __construct(
        public readonly string $label,
        public readonly float $time, // microtime(true)
    ) {}
}
```

**Built-in lifecycle marks (always on):**

| Mark | When |
|---|---|
| `request.start` | Stopwatch constructor (called as the first thing in `public/index.php` / `bin/arcanum`) |
| `boot.complete` | After `HyperKernel::boot()` finishes running bootstrappers |
| `request.received` | `RequestReceived` listener |
| `handler.start` | Conveyor before-middleware boundary |
| `handler.complete` | Conveyor after-middleware boundary |
| `render.start` | Formatter `format()` entry |
| `render.complete` | Formatter `format()` exit |
| `request.handled` | `RequestHandled` listener |
| `response.sent` | `ResponseSent` listener |

**Plan items — framework:**

- [ ] **`Arcanum\Toolkit\Stopwatch\Stopwatch`** — single class as sketched above. `mark()`, `has()`, `elapsedMs()`, `elapsedSinceMs()`, `elapsedBetweenMs()`, `startTime()`, `marks()`. Constructor records `request.start`.
- [ ] **`Arcanum\Toolkit\Stopwatch\Mark`** — immutable value object, label + time.
- [ ] **`tests/Toolkit/Stopwatch/StopwatchTest.php`** — construction marks request.start, mark records insertion-ordered marks, has() returns true/false correctly, elapsedMs returns positive duration, elapsedSinceMs returns null for missing labels, elapsedBetweenMs returns null when either label is missing, marks() returns insertion order (use a frozen-time helper or accept small tolerances).
- [ ] **`tests/Toolkit/Stopwatch/MarkTest.php`** — value object basics.
- [ ] **`Bootstrap\HyperKernel`** — accept an optional pre-built Stopwatch in the constructor (so `public/index.php` can mark `request.start` at the absolute earliest moment); fall back to constructing one if none provided. Bind it to the container as a singleton. Mark `boot.complete` after bootstrappers run.
- [ ] **`Bootstrap\Hyper`** — register Echo listeners that mark `request.received`, `request.handled`, and `response.sent` from the corresponding lifecycle events.
- [ ] **`Flow\Conveyor\MiddlewareBus`** — mark `handler.start` before before-middleware fires, `handler.complete` after after-middleware fires. Inject Stopwatch as an optional dependency (so the bus stays usable in CLI / non-HTTP contexts where no Stopwatch is bound).
- [ ] **`Shodo\Formatter` boundary** — mark `render.start` / `render.complete` around `format()` execution. Inject Stopwatch optionally.
- [ ] **`src/Toolkit/Stopwatch/README.md`** — small dedicated doc covering the class, the built-in marks, and example consumers (welcome page footer, profiling middleware, slow-test assertions).

**Plan items — starter app migration:**

- [ ] **`public/index.php`** — `$stopwatch = new Stopwatch()` as the first thing after `vendor/autoload.php`. Pass into kernel construction.
- [ ] **`bin/arcanum`** — same first-line construction for CLI requests (so CLI scripts can also benefit from elapsed-time reporting).
- [ ] **`RequestLogger`** — read elapsed from `Stopwatch::elapsedMs()` instead of the PSR-7 request attribute. Drop the `arcanum.start_time` attribute write entirely.
- [ ] **Delete `App\Http\RenderMetrics`** + its test + its bootstrap registration. The starter app no longer carries this concept; Stopwatch covers it.
- [ ] **`EnvCheckHelper`** — when written, takes `Stopwatch` instead of `RenderMetrics` for `renderDurationMs()`.

**Open follow-ups (defer):**

- **Per-middleware marks** — `App\Http\Middleware\StopwatchMiddleware` that marks `middleware.{name}.start` / `.complete` around each PSR-15 middleware. Opt-in via the debug flag because the mark count grows with the middleware stack.
- **Per-bootstrapper marks** — same idea for `Ignition\HyperKernel::boot()`. Useful for tracking down a slow bootstrapper. Debug-mode only.
- **Debug toolbar** — render `Stopwatch::marks()` as an inline timeline strip at the bottom of HTML responses in debug mode.
- **Phase context in log lines** — `RequestLogger` includes a `phases` dict with all marks in its log context.

### Shodo conditionals (and directive syntax cleanup)

Initial plan was based on a wrong premise. Shodo *does* already have `if`/`elseif`/`else`/`endif`/`foreach`/`for`/`while` — they live under bare-keyword syntax with required parens (`{{ if ($foo > 0) }}`). I missed them when researching because I grepped for `@if`. So the actual gap isn't "no conditionals," it's an **inconsistency**: layout/structure directives are `@`-prefixed (`@extends`, `@section`, `@yield`, `@include`, `@csrf`), control structures are bare keywords. Two conventions in the same templates.

**The fix: drop `@` prefixes from everything.** Single rule for the inside of `{{ }}`:

| Starts with | Meaning | Example |
|---|---|---|
| `$` | variable expression | `{{ $user->name }}` |
| Uppercase letter | helper call | `{{ Route::url('home') }}` |
| Lowercase keyword | directive | `{{ extends 'layout' }}`, `{{ if $foo }}`, `{{ csrf }}` |

Mirrors how PHP itself distinguishes things — variables, classes, keywords. No `@` prefix needed because the first character already tells you what you're looking at.

**Conditional syntax — preferred form is paren-free, but tolerant of all three:**

```
{{ if $foo > 0 }}        // preferred — clean, no ceremony
{{ if ($foo > 0) }}      // also accepted — PHP-ish, some devs prefer
{{ if ($foo > 0): }}     // also accepted — full PHP alt syntax
```

The compiler normalises to canonical `<?php if ($expr): ?>` regardless of input form.

**`match` / `case` / `default`** for switch-style branching:

```
{{ match $status }}
    {{ case 'pending', 'active' }}
        <span class="text-success">Active</span>
    {{ case 'closed' }}
        <span class="text-error">Closed</span>
    {{ default }}
        <span class="text-stone">Unknown</span>
{{ endmatch }}
```

Compiles to a PHP `switch` statement with implicit `break` after each case. Strictly an equality-match-against-subject construct. Comma-separated values in `case` map to fall-through case lists. Named `match` (not `switch`) because it's the closer mental model and avoids the C-style fall-through trap.

PHP's native `match` expression can't be used directly because match arms must be expressions, not statement bodies. The compiler emits `switch` under the hood but the developer-facing keyword is `match`.

**Plan items:**

- [x] **Drop `@` prefix from existing directives** — `extends`, `section`, `endsection`, `yield`, `include`, `csrf` are now bare-keyword. Updated all six regex sites in `TemplateCompiler` (directive compilation, include resolution, layout resolution, fragment mode, yield collection, section extraction).
- [x] **Tolerant `if` / `elseif` / `for` / `while` / `foreach` regex** — accepts paren-free, paren-wrapped, and PHP-alt-syntax forms. The compiler strips outer parens (only when balanced as a true wrapping pair) and trailing colon, then normalises to canonical `<?php KEYWORD (EXPR): ?>`. Also accepts the `if($foo)` no-space-before-paren form via a lookahead in the regex separator.
- [x] **`match` / `case` / `default` / `endmatch`** — implemented as a pre-pass that compiles to PHP `switch` alt-syntax with implicit `break` after every case body. Comma-separated values in `case` map to fall-through case lists. Case values are split with a string-and-bracket-aware tokenizer so commas inside `'a, b'` or `[1, 2]` don't break things.
- [x] **Migrate test fixtures** — 5 files in `tests/Fixture/Templates/`.
- [x] **Migrate starter app templates** — 4 files: `app/Templates/layout.html`, `app/Pages/Index.html`, `app/Pages/Contact.html`, `app/Domain/Query/Health.html`.
- [x] **Update existing Shodo tests** — `TemplateCompilerTest.php` and `HtmlFormatterTest.php` migrated from `@`-prefixed forms. Assertions updated to expect the new spaced canonical output (`<?php if ($foo): ?>`).
- [x] **Add tests for the new behaviours** — 12 new tests covering: paren-free `if`, all-three-forms identity, internal-paren preservation in `($a) || ($b)`, paren-free `foreach`, basic `match`, comma-separated `case` values, `default` case, empty match, case body with template syntax, comma-in-string preservation, single-evaluation of match subject.
- [x] **Update Shodo README** — rewrote the directive section. Added a "one rule to read them all" table covering the three first-character conventions ($variable / Helper::call / directive). Added the three accepted control flow forms with explanation. Added a `match` example. Migrated every code sample in the file from `@`-prefixed to bare-keyword.
- [x] **Smoke test on live starter app** — `/`, `/health.html`, `/health.json`, `/contact.html` all return 200 with full HTML bodies after the migration.

### Cache management gaps

Surfaced while wiring up the Tailwind production build. The template helper `App::cssTags()` reads `file_exists()` at render time, but compiled templates inline the layout content at build time, so the helper call only takes effect after the cache is invalidated. `cache:clear` doesn't currently clean the templates cache, which made the helper appear broken until manually nuked.

**Design — framework cache bypass switch:**

Some developers want a completely fresh pull on every refresh while iterating, regardless of debug mode. Tying this to `app.debug` is wrong: debug is a runtime concern (verbose errors, stack traces), and a developer might legitimately want caching enabled in dev to test cache behavior, or disabled in production for diagnosis. Cache bypass is its own orthogonal switch.

A new `cache.framework.enabled` config key (defaults to `true`). When set to `false`, every framework-internal cache surface — templates, helpers, page discovery, middleware discovery, configuration cache — becomes a no-op. Application caches that the dev wired up themselves through `Vault` are unaffected; this is purely a framework escape hatch.

Implementation: `CacheManager::frameworkStore()` checks the flag and returns a `NullDriver` instead of resolving the configured store. One choke point, every framework subsystem already routes through it.

**Plan items:**

- [x] **`cache.framework.enabled` config flag** — new key in `config/cache.php`, defaults to `true`. Read by `CacheManager::frameworkStore()`. When `false`, returns `NullDriver` for every named store regardless of configured driver. Application stores via `CacheManager::store()` remain unaffected. `Bootstrap\Formats` also consults the flag so the `TemplateCache` (which writes directly to disk, not through Vault) honors the master switch. `cache.framework` was restructured from a flat `[purpose => store]` map to `['enabled' => bool, 'stores' => [purpose => store]]`.
- [x] **Document the bypass switch** in the Vault README — explain when and why to use it, and what it does and does not affect.
- [x] **`TemplateCache::store()` no-op when disabled** — fixed a latent bug where calling `store()` with `cacheDirectory=''` (the disabled sentinel) wrote a file to the filesystem root (`/<md5>.php`) and crashed on read-only filesystems. Surfaced when wiring the framework cache bypass through `Bootstrap\Formats`.
- [x] **`cache:clear` should clear the templates cache** — fixed in `Bootstrap\CliRouting`'s `CacheClearCommand` factory, which now constructs a `TemplateCache` directly from `Kernel::filesDirectory()` when one isn't already registered (i.e. in CLI bootstrap, where `Bootstrap\Formats` doesn't run). The command's `Cleared template cache` line is now visible in CLI output.
- [x] **`cache:clear` should clear the helper discovery cache** — covered by the new "stray framework cache subdirectory" pass in `CacheClearCommand`. After clearing Vault stores and the structured framework caches, the command walks `files/cache/` and empties any subdirectory that wasn't already handled (helpers, pages, middleware, and any future surface). Directory roots are preserved so file drivers don't lose their footing.
- [x] **Audit framework cache surfaces** — inventory documented in the Vault README under "Framework cache inventory" with a table mapping every cache surface to its path/driver and the `cache:clear` pass that reaches it. The new stray-subdirectory walk acts as a safety net for any future framework cache that lands without being formally wired into the command.
- [x] **Template cache invalidation on layout change** — `TemplateCompiler` now tracks every layout and include it touches during compilation, exposed via `lastDependencies()`. `TemplateCache::store()` writes the deps as a JSON header comment in the cache file (`<?php /* arcanum-deps: ["..."] */ ?>`), and `isFresh()` checks every dep's mtime. Editing a layout or partial — at any nesting depth — automatically invalidates every cached template that depends on it. Deleted dependencies are also treated as stale so the recompile surfaces missing-include errors instead of serving stale output.

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

## Flow\Sequence + Forge Read/Write Split

**Problem:** `Forge\Result` has two unrelated jobs glued together. For reads it holds a fully materialized `array<int, array<string,mixed>>` (via `PDOStatement::fetchAll()`) — unbounded memory, no way to process a large query row-by-row. For writes it carries `affectedRows` and `lastInsertId`, which have nothing to do with rows at all. The read side blocks exports, batch jobs, reports, and any unbounded query. The write side is structural noise that pollutes the read type.

**Approach:** Split reads and writes into separate types, make streaming the default for reads, and lift the generic sequence primitives into their own Flow subpackage so Forge consumes an abstract interface rather than a concrete class.

```
Flow\Sequence\Sequence<T>   (interface)       — abstract ordered iterable
Flow\Sequence\Cursor<T>     implements Sequence — lazy, single-pass
Flow\Sequence\Series<T>     implements Sequence — eager, multi-pass

Forge\Connection::query()   : Sequence<array<string,mixed>>   (reads, always streams)
Forge\Connection::execute() : WriteResult                      (writes)
Forge\WriteResult                                              (affectedRows, lastInsertId)
Forge\Result                                                   DELETED
```

Forge programs against the `Sequence` interface. `PdoConnection::query()` returns a `Cursor` in practice (always streams), but the contract is `Sequence`, so test fakes and alternative implementations can return a `Series` directly with no consumer changes. `Cursor::toSeries()` is the explicit escape hatch when a handler needs `count()`, multi-pass iteration, or random access — the cost of materialization is named at the call site.

### Design rationale — what goes on the interface

The `Sequence<T>` interface contains **only operations that are honest on both shapes.** Anything that would force Cursor to silently materialize stays off the interface and lives on `Series` only:

| Operation | On `Sequence`? | Reason |
|---|---|---|
| `IteratorAggregate::getIterator()` | ✅ | Foreach-compatible on both |
| `first(): ?T` | ✅ | O(1) on both — Cursor peeks one row and closes |
| `each(callable): void` | ✅ | Terminal iteration |
| `map(callable): Sequence<U>` | ✅ | Lazy on Cursor, eager on Series |
| `filter(callable): Sequence<T>` | ✅ | Lazy on Cursor, eager on Series |
| `chunk(int): Sequence<list<T>>` | ✅ | Lazy on Cursor, eager on Series |
| `take(int): Sequence<T>` | ✅ | Lazy on Cursor, eager on Series |
| `toSeries(): Series<T>` | ✅ | Walks generator on Cursor; returns `$this` on Series (idempotent) |
| `count(): int` | ❌ | Forbidden on Cursor — would materialize silently. Devs write `$seq->toSeries()->count()` or `SELECT COUNT(*)`. |
| `all(): list<T>` | ❌ | Eager-only dump. Lives on Series. |
| `isEmpty(): bool` | ❌ | Can't be answered without consuming at least one row. Lives on Series. |
| `scalar()` | ❌ | Database-specific ("first column of first row"). Generic `T` has no columns. Lives in the Forge layer if at all, not on `Sequence`. |

### Cursor contract

- Single-pass. Throws `CursorAlreadyConsumed` on second iteration or on `toSeries()` after any prior iteration (partial or full).
- Self-closing. `getIterator()` wraps the source generator in `try/finally` that calls `close()`. `__destruct` calls `close()`. `close()` is idempotent and runs exactly once across all paths.
- `first()` peeks one row via iteration, closes the cursor, returns the row (or `null` if empty). Marks the cursor consumed.
- `map`/`filter`/`chunk`/`take` are lazy: return a new `Cursor` sharing the same `onClose`. None of them execute until the result is iterated.
- `toSeries()` walks the generator into a `list<T>` and returns a new `Series<T>`. Legal only on a fresh, unconsumed cursor.

### Series contract

- Eager, multi-pass. Backed by a `list<T>`.
- All `Sequence` methods plus `count()`, `all(): list<T>`, `isEmpty()`.
- `map`/`filter`/`chunk`/`take` return new `Series` instances (eagerly applied).
- `toSeries()` returns `$this`.
- No consumption tracking — multi-pass iteration is fine.

### Benchmark summary (justification, preserved for context)

Measured with subprocess isolation per cell, 20-column rows, sqlite in-memory, mean of 5–500 iterations depending on size. See conversation history for the full benchmark harness.

| Rows | Eager (current) time | Generator time | Eager peak memory | Generator peak memory |
|---|---|---|---|---|
| 1 | 0.0119 ms | 0.0124 ms | 5.7 KB | 4.6 KB |
| 10 | 0.0344 ms | 0.0338 ms | 33.7 KB | 6.2 KB |
| 100 | 0.254 ms | 0.239 ms | 329 KB | 6.3 KB |
| 1,000 | 2.39 ms | 2.26 ms | 3.20 MB | 6.3 KB |
| 10,000 | 26.3 ms | 22.7 ms | 32.1 MB | 6.3 KB |
| 100,000 | 288 ms | 237 ms | 320 MB | 6.3 KB |
| 500,000 | 1629 ms | 1208 ms | 1598 MB | 6.3 KB |

Generator is equal or faster at every row count tested from 10 up (the 1-row −5% sits inside normal noise). Memory scales linearly for eager and **stays flat at 6.3 KB for the generator regardless of result size.** Streaming is never meaningfully slower and always uses constant memory.

### Open questions to resolve before coding

- [x] **Internal `Model::execute()` shape.** Resolved as **(b)** + a runtime bridge: `Model` now has `protected read()` (returns `Sequencer<array<string,mixed>>`) and `protected write()` (returns `WriteResult`), with `protected dispatch()` sniffing isRead at runtime so the single generated stub keeps working until Phase 3 narrows it. Today it's a single protected method returning `Result` regardless of read/write. New design needs the dispatch split so generated methods can declare tight return types. Options: (a) keep one internal dispatcher returning `Sequence|WriteResult` and let generated methods declare narrower types statically via ModelGenerator's read/write detection; (b) split into two internal methods (`read`/`write` or similar). Decide during Phase 2 implementation — preference is (b) for type cleanliness, but worth confirming that PHPStan is happy with either.
- [x] **`Forge\Cast::apply()` placement.** Landed in `src/Forge/Cast.php` as planned. Empty cast maps short-circuit to an identity closure so callers can compose unconditionally. New helper returning a row-mapping closure from a cast map. Lives in `src/Forge/Cast.php`. Reuses `Sql::castValue`. Confirm during Phase 2 that this is the right namespace (vs. `Forge\Sql::caster()` or similar).
- [x] **`scalar()` shape in Forge.** Resolved as **(a)** — dropped. No production callers existed; the only references were in the deleted `ResultTest` and one `ModelTest` case that now uses `$row['cnt']`. Today `Result::scalar()` returns the first column of the first row. In the new design there's no such method on `Sequence`. Options: (a) drop it — let callers write `(int) $seq->first()['count']`; (b) small Forge helper `Forge\Scalar::from($sequence, $column = null)`; (c) a `scalar()` method on Forge's query path that returns `mixed` directly (separate from `query()`, returns the first column of the first row without going through Sequence). Recommend (a) for simplicity unless there are many existing callers that would churn. Check before committing.
- [x] **`DbStatusCommand`** — uses `Result` directly (per grep). Identify the exact call sites and confirm the migration path during Phase 2.

### Phase 1 — `Flow\Sequence` subpackage (no Forge changes)

The generic primitives land first with zero Forge coupling. This phase can be reviewed and merged independently.

- [x] **Create `src/Flow/Sequence/` directory and `tests/Flow/Sequence/`.**
- [x] **`Flow\Sequence\Sequencer<T>`** — interface (renamed from `Sequence` to avoid the namespace/type collision; `@template-covariant T` since every use of `T` is a producer position) extending `\IteratorAggregate<int, T>`. Method set: `first(): ?T`, `each(callable): void`, `map(callable): Sequence`, `filter(callable): Sequence`, `chunk(int): Sequence`, `take(int): Sequence`, `toSeries(): Series`. Full generic annotations for PHPStan.
- [x] **`Flow\Sequence\Cursor<T>`** — `final class` implementing `Sequence<T>`. Constructor takes `\Closure(): \Generator<int, T> $source` and `\Closure(): void $onClose`. Tracks `$consumed` flag. `getIterator()` sets `$consumed = true`, yields from source inside `try/finally` that calls `close()`. `close()` is idempotent and invokes `$onClose` exactly once. `__destruct` calls `close()`. `first()` iterates once, returns first value or null, consumes cursor. `map`/`filter`/`chunk`/`take` wrap the source generator into a new `Cursor` sharing `$onClose`. `each()` is foreach + apply (terminal). `toSeries()` walks the full generator into a `list<T>` and returns a `new Series($items)`; throws `CursorAlreadyConsumed` if already consumed.
- [x] **`Flow\Sequence\Series<T>`** — `final class` implementing `Sequence<T>`. Constructor takes `list<T> $items`. `first()`, `each()`, `map`/`filter`/`chunk`/`take` operate on `$items` directly. `toSeries()` returns `$this`. Plus eager-only methods: `count(): int`, `all(): list<T>`, `isEmpty(): bool`.
- [x] **`Flow\Sequence\CursorAlreadyConsumed`** — exception class extending `\LogicException` with a clear message pointing at `toSeries()` semantics.
- [x] **`tests/Flow/Sequence/SequencerContractTest.php`** — parameterized test that exercises both `Cursor` and `Series` against the interface surface (first, each, map, filter, chunk, take, toSeries). Shared assertions, two data providers.
- [x] **`tests/Flow/Sequence/CursorTest.php`** — lazy iteration assertion (source generator not invoked until iteration begins), single-pass guard (second iteration throws), close-on-completion, close-on-break, close-on-exception, close-on-destruct, `onClose` invoked exactly once across all paths, `toSeries()` on fresh cursor walks everything, `toSeries()` on consumed cursor throws, `first()` on empty cursor returns null and closes, map/filter/chunk/take chain correctly and don't trigger iteration until terminal.
- [x] **`tests/Flow/Sequence/SeriesTest.php`** — eager iteration, multi-pass iteration works, `count()`/`all()`/`isEmpty()`, `toSeries()` returns same instance (`assertSame`), map/filter/chunk/take return new `Series` instances.
- [x] **Run `composer check`** and confirm PHPStan is happy with the generic annotations.

### Phase 2 — Forge refactor

With `Flow\Sequence` available, refactor Forge to use it. This phase deletes `Forge\Result` and introduces `Forge\WriteResult` and the `Connection::query`/`Connection::execute` split.

- [x] **`Forge\WriteResult`** — new `final class`. Immutable. Holds `affectedRows: int`, `lastInsertId: string`. Constructor + accessors. No rows field.
- [x] **`Forge\Connection` interface rewrite:**
  - Remove `run(string $sql, array $params = []): Result`.
  - Add `query(string $sql, array $params = []): Sequence` with `@return Sequence<array<string, mixed>>` annotation.
  - Add `execute(string $sql, array $params = []): WriteResult`.
  - Keep `beginTransaction()`, `commit()`, `rollBack()` unchanged.
- [x] **`Forge\PdoConnection::query()`** — prepare + execute; wrap `$statement->fetch()` loop in a generator; construct a `Cursor` with `onClose` calling `$statement->closeCursor()`. No `Sql::isRead()` branch — caller named their intent by choosing `query()`.
- [x] **`Forge\PdoConnection::execute()`** — prepare + execute; read `$statement->rowCount()` and `$pdo->lastInsertId() ?: ''`; return `new WriteResult(...)`. No row fetching.
- [x] **`Forge\Cast`** — new `final class` with `public static function apply(array<string, string> $casts): \Closure`. The returned closure takes `array<string, mixed> $row` and returns the casted row. Reuses `Sql::castValue`. Pure function, no state.
- [x] **Delete `Forge\Result`** — `src/Forge/Result.php` removed. All references to `Arcanum\Forge\Result` deleted across `src/` and `tests/`.
- [x] **`Forge\Model` rewrite:**
  - `__call` return type becomes `Sequence|WriteResult` (union; the dynamic path has to be honest about dispatch).
  - `__call` dispatches via `Sql::isRead()` to either an internal read path (`query`) or write path (`write`) — exact shape decided per open question above.
  - Internal read path: loads SQL, loads casts, calls `$connection->query($sql, $params)`, composes `->map(Cast::apply($casts))` if casts declared, returns the `Sequence`.
  - Internal write path: calls `$connection->execute($sql, $params)`, returns the `WriteResult`.
  - Delete `loadCasts` cache? No — still useful; casts are still parsed from SQL comments via `Sql::parseCasts()`.
  - Delete the old `execute()` method (protected dispatcher returning `Result`).
- [x] **`Forge\Database`** — scan for Result references, update accordingly.
- [x] **`Rune\Command\DbStatusCommand`** — migrated; uses `$conn->query('SELECT 1')->first()` for the ping.
- [x] **Update all Forge test fakes** — `tests/Forge/ConnectionTest.php` fake connection, any stubs in `ConnectionFactoryTest`, `ConnectionManagerTest`, `DatabaseTest`. All must satisfy the new interface (implement `query` and `execute`).
- [x] **Delete `tests/Forge/ResultTest.php`** — the class is gone.
- [x] **`tests/Forge/WriteResultTest.php`** — new, covers `WriteResult` getters and immutability.
- [x] **`tests/Forge/PdoConnectionQueryTest.php`** — sqlite in-memory, seed ~10k rows, iterate via `query()`, assert peak memory delta is bounded relative to what fetching everything would cost; assert `closeCursor` is called on early `break`; assert `closeCursor` is called when iteration throws.
- [x] **`tests/Forge/PdoConnectionExecuteTest.php`** — sqlite in-memory, cover INSERT/UPDATE/DELETE returning correct `WriteResult.affectedRows` and (for INSERT) `lastInsertId`.
- [x] **`tests/Forge/CastTest.php`** — new, covers `Cast::apply()` behavior: returned closure casts each column per the map, leaves unmapped columns alone, handles empty cast maps.
- [x] **Update `ModelTest`** — adjust existing tests for the new return types. Read methods now return `Sequence` (asserting via `iterator_to_array` or `->toSeries()->all()` where tests previously called `->rows()`). Write methods return `WriteResult`.
- [x] **Update `ConnectionTest`, `ConnectionFactoryTest`, `ConnectionManagerTest`, `DatabaseTest`** — any test touching `Result` or `->run()` migrates to `Sequence`/`WriteResult` and `query`/`execute`.
- [x] **Run `composer check`.** 2442 tests / 4924 assertions / PHPStan level 9 clean.

### Phase 3 — ModelGenerator + starter app

Generated models and the starter app get updated to the new shapes. This lands after Phase 2 is green.

- [x] **`src/Rune/Command/stubs/model.stub`** — drop `use Arcanum\Forge\Result`; no explicit import needed (generated methods will import `Sequence` / `WriteResult` at method level or via top-level use).
- [x] **`src/Rune/Command/stubs/model_method.stub`** — split into `model_method_read.stub` and `model_method_write.stub`. — rewrite. The method body should no longer call `$this->execute()` (old dispatcher is gone). Instead, at generation time ModelGenerator parses the SQL once and picks a path:
  - Read query → emit a body calling the internal read path with declared return type `Sequence<array<string, mixed>>` (or narrower if a row DTO is declared in the SQL), composing `->map(Cast::apply($casts))` when the SQL declares casts.
  - Write query → emit a body calling the internal write path with declared return type `WriteResult`.
  - The stub may need to split into `model_method_read.stub` and `model_method_write.stub`, or use a directive inside a single stub. Decide during implementation.
- [x] **`Forge\ModelGenerator::renderMethod()`** — parse SQL via `Sql::isRead()` at generation time, pick the right stub and variables, emit the correct return type annotation in the method signature.
- [x] **`tests/Forge/ModelGeneratorTest.php`** — fixture SQL files for read (with and without casts) and write, assert generated method signatures, return types, and body shapes. Include one SQL file with casts declared to verify `Cast::apply` composition.
- [x] **Regenerate starter app models** — no-op for now; the starter app has no models since Contact was yanked. Revisit when the todo app lands. — from `../arcanum/`, run `composer run-script rune -- forge:models` (or the equivalent) and commit the regenerated classes. Per memory, generated models are committed, not gitignored.
- [x] **Starter app migration** — no call sites to migrate; the starter has no handlers touching Forge after the Contact yank. — sweep the starter app for any call sites that used `->rows()`, `->count()`, `->scalar()`, `->withCasts()`, or relied on `affectedRows`/`lastInsertId`. Migrate: `->rows()` → `->toSeries()->all()` or direct iteration; `->count()` → `->toSeries()->count()` or a `SELECT COUNT(*)` query; reads of write metadata → use methods now returning `WriteResult`.
- [x] **Starter app smoke test** — `/`, `/health.html`, `/health.json` all 200 after the Phase 2 migration. — run the app locally, hit every page that touches a model (per CLAUDE.md: always smoke test framework changes affecting HTTP). Confirm reads work, confirm writes return the expected `WriteResult`, confirm casts still apply per column.
- [x] **Document the new surface in `src/Forge/README.md`** — remove `Result` references, add `Sequence`/`Cursor`/`Series` explanation with a short example showing iteration + `toSeries()` escape hatch.
- [x] **Document `Flow\Sequence` in `src/Flow/Sequence/README.md`** — new doc with interface, implementations, toSeries escape hatch, and the streaming benchmark. Also added a pointer in `src/Flow/README.md`. — new doc covering the interface, the two implementations, the `toSeries()` contract, and when to use which. Include the short benchmark table above as justification for why streaming is the default.

### Commit ordering

Each checkbox group above corresponds roughly to one commit. Sensible order:

1. Flow\Sequence interface + Cursor + Series + tests (Phase 1, one commit)
2. Forge\WriteResult + Cast helper (standalone, no Connection changes yet)
3. Connection interface split + PdoConnection::query + PdoConnection::execute (breaks the world temporarily)
4. Model rewrite to use new Connection methods + delete Forge\Result
5. All Forge test updates (may fold into #4 if the blast radius is small enough)
6. ModelGenerator + stub rewrite + tests (Phase 3, one commit)
7. Starter app regeneration + migration + smoke test
8. Forge README + Flow\Sequence README (docs)

### Open follow-ups (explicitly not in this change)

- **Generic `Flow\Sequence\Sequence` consumers outside Forge** — the subpackage is generic; other framework parts (Echo event replay, Gather iteration, HTTP paginated responses) could adopt it over time. Don't build speculative adapters; wait for a second consumer to appear organically.
- **Other SQL directive ideas** (`@returns`, `@one`) — explicitly excluded. The current design has zero directives and no naming conventions; adding any is a separate decision that should wait for a real need.
- **`Cursor::reduce(callable, $initial)`** — useful but not required for Phase 1. Add when the first real caller wants it.

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
