# Framework Completion Plan

> **COMPENDIUM.md must stay in sync.** Any time framework functionality changes, is added, or is removed — new packages, renamed classes, dropped features, new CLI commands, new attributes, new built-in marks, anything a user-facing tour would mention — update `COMPENDIUM.md` in the same change. It's the source of truth for the eventual documentation site, and an out-of-date entry there is worse than no entry. Treat it like committing: not a follow-up, part of done.

---

## Active Checklists

Concrete, walkable lists. Everything else in this file is informational — context, history, decisions, and future work that hasn't been broken into steps yet. When something becomes a checklist, it lives here.

### Welcome page — nice-to-haves (deferred)

The Index redesign landed (nine-section structure, real diagnostics, CSS-only tabs, copy buttons, ASCII rune). The leftovers are explicitly optional:

- [ ] **Diagnostic rows link to configuration docs** — every non-green row in the welcome page Application column (and any Environment row that's red) should link out to the relevant Arcanum configuration doc when clicked. A yellow "Session driver — not configured (optional — required for CSRF)" line should link to the session config guide; a red "Cache driver — config broken" line should link to the cache config guide; "Database — not configured" links to database setup; etc. Cheap UX win once the docs site exists. Blocked on the documentation site itself — defer until real docs URLs exist (same blocker as the placeholder URL cleanup below).
- [ ] **Syntax highlighting in code blocks** — the welcome page's incantation card and CQRS demo tabs render plain monochrome `<pre><code>` blocks. Adding color hinting (PHP keywords, strings, attributes) would meaningfully improve the first impression. Low priority. Look for a small client-side library — Prism.js, highlight.js, or shiki — that loads from CDN with a single script tag and a stylesheet, no build step required. Constraints: must respect dark mode (the page already toggles `.dark` on `<html>`), must not be a heavy dependency (the welcome page is the only consumer for now), and must not require running a Node toolchain to use. This isn't welcome-page-only — Shodo's documentation, README code blocks, and any future docs page would all benefit. When picking a library, prefer one that handles PHP, HTML, SQL, and shell well since those are the four languages the framework's docs use most.
- [ ] **`?debug=1` bootstrap visualization** — replaces welcome banner with bootstrap order list when the query param is set. Easter egg.
- [ ] **Placeholder URL cleanup** — replace `https://example.com/{docs,tutorial,api,discussions}` references in the Index page with real URLs once real docs / tutorial / community channels exist. GitHub repo links are already real.

### DTO field-count throughput investigation

The Performance Notes section below records the open question: 3→10 DTO fields drops throughput 77%, and reflection caching has already been ruled out as the cause. Walk this list top to bottom. Each suspect has an isolation strategy — bypass the suspect, re-run the benchmark, see if the gap closes.

**Status (2026-04-08): the 77% drop does not reproduce.** Steps 1 and 2 (effectively) and Suspect 2 are done; the remaining suspects are blocked pending a real signal to chase. See "Findings" subsection below — the headline is that the original measurement is now suspected to be a slow/fast-band measurement artifact rather than a real per-field cost.

- [x] **Reproduce the baseline.** Ran `hey` (not `ab` — macOS `ab` is broken with `-k` and flaky without it; `hey` has real connection pooling) against four synthetic DTOs with full query-string hydration: 1, 3, 10, and 30 string fields, no validation attributes. Hydrator's per-parameter loop *and* `coerce()` exercised on every param. Result: throughput is essentially flat across DTO sizes, dwarfed by run-to-run variance. Numbers in the Findings subsection below.
- [ ] **Capture wall-clock + CPU profiles.** Skipped — superseded by direct A/B tests of the suspects. Reinstate if a future signal warrants it.
- [partial] **Suspect 1 — Hydrator.** Indirectly tested: 1→30 fields with full hydration is roughly flat (1-field median 392 rps, 30-field median 366 rps, ~7% delta which is within noise). Hydration scales acceptably with parameter count up to 30. Not formally bypassed, but the flat scaling means the fix-the-Hydrator hypothesis isn't load-bearing.
- [x] **Suspect 2 — ValidationGuard.** Tested directly, twice. **Light test:** built `BenchTenValidated` (10 fields, each with `#[NotEmpty]`) and benched against plain `BenchTen`. Slow-band medians 389 vs 346 rps — well below the run-to-run noise (which routinely swings 100+ rps). **Heavy test:** built `BenchThreeHeavy` and `BenchThirtyHeavy` with 4 rules per param (`#[NotEmpty]` + `#[MinLength(1)]` + `#[MaxLength(255)]` + `#[Pattern('/^[A-Za-z0-9]+$/')]`), giving 12 vs 120 rule executions per request — a 10× difference in validation work. Confirmed via `Validator.php:67-75` that there is **no short-circuiting**: every rule on every param runs in the success path. Result over 5 interleaved iterations: 3-heavy median 331 rps, 30-heavy median 385 rps — the 10×-validation-work endpoint is *not slower*, the bimodal noise dominates everything. ValidationGuard reflection scaling is not measurable on this stack at any reasonable rule density. **Untested:** truly expensive rules like `Email` (filter_var), `Url`, `Uuid`, `Callback` invoking user code, and `#[Pattern]` against catastrophically backtracking regexes — those are user-payload-dependent in ways our synthetic params don't capture.
- [ ] **Suspect 3 — AuthorizationGuard / TransportGuard.** Untested. No reason to chase until a real signal appears.
- [ ] **Suspect 4 — Codex constructor parameter resolution.** Untested. Same.
- [ ] **Suspect 5 — Echo lifecycle event dispatch.** Untested. Same.
- [ ] **Suspect 6 — Container resolution.** Untested. Same.
- [x] **Decide the fix.** No fix to apply. The 77% drop is no longer reproducible on current `main` (commit `1b0141d`) under the harness in Performance Notes. Either the framework has changed since the original measurement (history doesn't show an obvious smoking-gun commit), or the original measurement was a slow/fast-band artifact (see Findings). Closing the investigation as "won't do" pending a fresh, reproducible report.
- [x] **Capture the result.** Written up in the Findings subsection below and folded into Performance Notes.

#### Findings

**Headline:** the 77% drop is not reproducible. Synthetic 1, 3, 10, 30-field DTOs all land in the same throughput band (~340-440 req/s slow band) on the macOS + nginx + php-fpm + opcache + JIT harness. Adding 10 `#[NotEmpty]` attributes to a 10-field DTO does not measurably move the number.

**Hey medians, full query-string hydration, n=10000 c=20, 3 iterations each, slow-band runs only:**

| DTO | Fields | Median req/s | Δ vs 1-field |
|---|---|---|---|
| `Health` | 1 | 392 | — |
| `BenchThree` | 3 | 395 | ≈ 0% |
| `BenchTen` | 10 | 345 | −12% |
| `BenchThirty` | 30 | 366 | −7% |
| `BenchTenValidated` | 10 (+10 `#[NotEmpty]`) | 346 | −12% |

**The bimodal slow/fast-band signal — the most interesting find.** Throughput on every endpoint occasionally jumps from a "slow band" (~330-440 rps) to a "fast band" (~900-915 rps), roughly 2.5x faster, and stays there for a run or two before reverting. The jump happens for both plain and validated DTOs, and is uncorrelated with field count. Likely candidates: php-fpm worker JIT trace cache reaching steady state after enough hits, opcache settling, macOS thermal management, or TCP TIME_WAIT pool draining. Confirming would require running the same harness on Linux and pinning CPU frequency.

**Why this matters for the original 77% measurement.** A single ad-hoc `ab` run that caught the 3-field endpoint in the fast band (~900 rps) and the 10-field endpoint in the slow band (~390 rps) would *exactly* produce a "77% drop" reading: (900 − 390) / 900 ≈ 56%, or with slightly different numbers, 77%. The original measurement is now suspected to be a slow/fast-band artifact, not a per-field cost.

**What this exonerates:** Hydrator scaling with parameter count (up to 30), `ValidationGuard` reflection over `NotEmpty` attributes (up to 10).

**What this does NOT exonerate, in case a real signal reappears:** heavier validation rules (`Pattern`, `Email`, `Callback`), `AuthorizationGuard` walking `#[RequiresAuth]`/`#[RequiresRole]`/`#[RequiresPolicy]`, the `TransportGuard` attribute walk, lifecycle event dispatch under heavy listener loads, and the underlying bimodal signal itself. If somebody reports a real throughput cliff in the future, the bimodal signal needs to be controlled for *first* before chasing per-field costs.

**Reproducing this investigation later.** Recreate four DTOs in the starter app under `app/Domain/Query/`, named `BenchThree`, `BenchTen`, `BenchThirty`, `BenchTenValidated` (plus matching `*Handler` classes that return `['ok' => 'true']`). Each `BenchN` has N `public readonly string` constructor params with single-letter names (`a`, `b`, ..., then `aa`, `bb` past `z`) and string defaults. `BenchTenValidated` adds `#[Arcanum\Validation\Rule\NotEmpty]` to each. Hit them with hey using full query strings (`?a=A&b=B&...`) so the hydrator's `coerce()` actually runs on every parameter. Use the harness in Performance Notes with `pm.max_children=16` and `fastcgi_keepalive on`. The bench DTOs themselves were not committed to the starter app — they're trivial scaffolding and would clutter the demo app for no ongoing benefit. This recipe is the source of truth.

---

## Upcoming Work

### Testing utilities + PSR-20 `Clock` adoption — next focus

The single highest-leverage item left. See the long-distance entry below for the concrete shape; promote to a checklist above when ready to start.

---

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
- **Testing utilities + PSR-20 `Clock` adoption** — The single highest-leverage item left. Hourglass landed `Clock` / `SystemClock` / `FrozenClock`, but nothing in the framework actually depends on the interface yet. Sessions, Auth, Throttle, Vault, and Forge timestamps each call `time()` / `new DateTime()` directly, so any test that touches them is non-deterministic. Adopting `Clock` everywhere makes the whole framework fakeable, which then makes everything *else* on the long-distance list (queues, scheduling, mail, the Todo App dogfood) cheap to test deterministically. Concrete shape:
    - **Audit and migrate** every `time()` / `new DateTime[Immutable]()` / `microtime()` call in `src/` whose value crosses a testability boundary. Inject `Clock` instead. Sessions expiry, Auth token TTL, Throttle window math, Vault TTL math, Forge timestamp casts are the obvious starts.
    - **`Arcanum\Test\TestKernel`** — extends `HyperKernel` with the boilerplate baked in: in-memory Vault store, `FrozenClock` bound, fake guards, fluent request builder, response assertion helpers. The thing every starter-app dev wishes they had on day one.
    - **`Arcanum\Test\Factory`** — DTO construction helper that respects validation rules, nested DTOs, and default values. Replaces hand-rolling `new SomeCommand(name: 'Alice', email: 'a@b.c', ...)` in every test.
    - **`Arcanum\Test\Fake\`** — fakes for things that genuinely need stubbing across the app boundary: mailer, HTTP client, queue (when it lands). Each is a simple in-memory implementation of the corresponding interface.
    - **`src/Testing/README.md` or `tests/README.md`** — document the testing story end-to-end: how to write a handler test, how to advance the clock, how to assert on a sent email, how to use the request builder. The first thing a new app dev should read after the getting-started guide.

  **Why before the Todo App dogfood?** The dogfood's whole point is to surface friction we *can't see* from inside. If we run it without testing utilities, the retrospective will be dominated by testability complaints we already know about, drowning out the genuinely new surprises. Doing this first means the dogfood retrospective surfaces gaps we couldn't predict.
- **Queue/Job system** — async processing with drivers (Redis, database, SQS).
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
- **Reflection caching didn't help.** Benchmarked under nginx + PHP-FPM + opcache + JIT — PHP 8.4 reflection is fast enough. The throughput ceiling is dominated by FPM/FastCGI overhead, not reflection. Don't add caches that don't measurably move the needle.
- **PHP `//` comments terminate at `?>`.** A docblock that mentions `<?= ?>` literally will break the file's parse — the lexer needs to switch out of PHP mode. Use `/* */` blocks for any prose containing template markers.

---

## Performance Notes

### Reflection caching — explored and rejected

Benchmarked three reflection caching approaches (in-memory, flyweight facade, APCu persistence) under production conditions (nginx + PHP-FPM + opcache + JIT). PHP 8.4 reflection is already fast enough — caching produced no measurable throughput improvement (~300 req/s ceiling dominated by FPM/FastCGI overhead, not reflection).

**Open question (resolved 2026-04-08):** 3→10 DTO fields drops throughput 77% — investigated and **not reproduced**. See the "DTO field-count throughput investigation" checklist in Active Checklists for the full write-up. Headline: synthetic 1, 3, 10, 30-field DTOs all sit in the same throughput band, and adding 10 `#[NotEmpty]` attributes does not move the number. The original measurement is now suspected to be a slow/fast-band artifact — every endpoint occasionally jumps to ~2.5x throughput for a run or two before reverting, and an ad-hoc bench that caught one DTO in the fast band and another in the slow band would manufacture a ~77% delta out of thin air. Closed as "won't do" pending a fresh, reproducible report. **Lesson:** before chasing a per-feature throughput cliff on this stack, control for the bimodal slow/fast-band signal first — ideally run on Linux with CPU frequency pinned.

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
