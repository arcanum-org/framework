# Framework Completion Plan

> **COMPENDIUM.md must stay in sync.** Any time framework functionality changes, is added, or is removed — new packages, renamed classes, dropped features, new CLI commands, new attributes, new built-in marks, anything a user-facing tour would mention — update `COMPENDIUM.md` in the same change. It's the source of truth for the eventual documentation site, and an out-of-date entry there is worse than no entry. Treat it like committing: not a follow-up, part of done.

---

## Active Checklists

Concrete, walkable lists. Everything else in this file is informational — context, history, decisions, and future work that hasn't been broken into steps yet. When something becomes a checklist, it lives here.

### Hourglass Clock integration — active

Integrate `Hourglass\Clock` throughout the framework and starter app so every wall-clock read crosses an injectable boundary. Built once, then `FrozenClock` makes Sessions/Auth/Throttle/Vault tests deterministic without `sleep()` and without any test-only time mocking. This is the foundation for the broader testing-utilities arc (`TestKernel`, `Factory`, `Fake\`) — those build on a fakeable Clock and become much cheaper once it's in place.

**Scope decisions made during discovery:**
- **Migrate** every site that reads "now" and crosses a testability boundary (TTL math, expiry checks, persisted timestamps).
- **Skip** deterministic `DateInterval`-to-int converters in `ApcuDriver`/`RedisDriver` (they don't read now), the `FormatHelper::date()` formatter (it formats caller-provided values), and `Stopwatch`'s `microtime(true)` calls (Stopwatch is high-resolution monotonic-ish elapsed time, Clock is wall-clock; intentionally separate concerns).
- **Test-only sites** in `tests/` and starter app entry points (`public/index.php`, `bin/arcanum` defining `ARCANUM_START`) are not migration targets — they're either test fixtures or the legitimate source of truth.

**Order:** bootstrap → Vault → Throttle → Auth → starter app → docs. Vault first because it's a dependency of Throttle (Throttle stores its state through Vault), and starting with the dependency means downstream packages compile against an already-Clock-aware Vault. Each item is its own commit.

#### Documentation of deliberately-skipped sites

- [x] **Land explanation comments at every skipped time-using site** so future maintainers (or future Claude) don't "fix" what isn't broken. Covers `Vault\ApcuDriver::resolveTtl`, `Vault\RedisDriver::resolveTtl` (interval-to-int converters that never read "now"), `Shodo\Helpers\FormatHelper::date` (caller-provided value formatter), and `Hourglass\Stopwatch` (high-resolution elapsed-time telemetry that intentionally bypasses Clock — different concern, different precision requirement). Each comment names Hourglass\Clock explicitly and explains the why so the decision is locally legible without needing to read PLAN.md.

#### Bootstrap

- [ ] **Register `Clock` in the container.** Either extend `Bootstrap\Stopwatch` to also bind `Clock::class → SystemClock::class` (and rename it to `Bootstrap\Hourglass`), or add a new `Bootstrap\Clock` bootstrapper. Prefer extending+renaming — Hourglass is one cohesive package, two bootstrappers for it would split the bootstrap order awkwardly. Both `HyperKernel` and `RuneKernel` bootstrap lists need updating to reference the renamed bootstrapper.
- [ ] **Test the registration.** Add a bootstrapper test that resolves `Clock::class` from the container and asserts it's an instance of `SystemClock`. Mirror existing `Bootstrap\StopwatchTest` patterns.

#### Vault

- [ ] **`ArrayDriver` — inject Clock, migrate two `time()` sites.** Add `Clock` constructor parameter; replace `time()` at line 30 (expiry check in `get()`) and line 110 (TTL math in expiry calc) with `$this->clock->now()->getTimestamp()`. Update `ArrayDriverTest` to construct with `FrozenClock` and add at least one deterministic-expiry test that walks the clock past the TTL via `FrozenClock::advance()`.
- [ ] **`FileDriver` — inject Clock, migrate two `time()` sites.** Same migration pattern at line 58 (expiry check) and line 160 (TTL math). Update `FileDriverTest` similarly.
- [ ] **Vault README — document the Clock dependency.** Note that `ArrayDriver` and `FileDriver` now take a `Clock` constructor argument (auto-wired via container), and that `FrozenClock` enables deterministic TTL tests.

#### Throttle

- [ ] **`TokenBucket` — inject Clock, migrate one `time()` site.** Throttler classes currently have no constructors; add one that takes `Clock`. Replace `time()` at line 22. Update `TokenBucketTest` to construct with `FrozenClock` and test window math via `advance()`.
- [ ] **`SlidingWindow` — inject Clock, migrate one `time()` site.** Same pattern at line 24. Update `SlidingWindowTest`.
- [ ] **`Quota` — inject Clock, migrate `headers()` `time()` site.** Quota is currently a pure value object constructed by Throttler results. The `time()` call is inside `headers()` (only used when `!allowed`, for `Retry-After`). Two options: (a) inject Clock into `Quota` (changes the constructor signature for every Throttler), or (b) compute `Retry-After` at construction time and store it. **Option (b) is cleaner** — `Quota` stays a pure value object, the Throttler computes `retryAfter` once when building the result. Migrate accordingly. Update `QuotaTest`.
- [ ] **Throttle README — document the Clock dependency.** Note that `TokenBucket` and `SlidingWindow` now take Clock, and `Quota` is unchanged (still a value object).

#### Auth

- [ ] **`CliSession` — inject Clock, migrate two `time()` sites.** Add Clock to constructor; replace `time() + $ttl` at line 38 (`save()`) and `$expires <= time()` at line 76 (`load()`). Update `CliSessionTest` to use `FrozenClock`, including a test that asserts a session expires correctly when the clock advances past TTL.
- [ ] **Auth README — document the Clock dependency.** Add a one-liner about `CliSession` taking Clock.

#### Starter app

- [ ] **`app/Domain/Query/HealthHandler` — migrate the verbose-mode `time()` call.** Inject Clock via constructor; replace `time()` with `$this->clock->now()->getTimestamp()`. The handler becomes deterministic for tests once a `FrozenClock` is bound. Smoke test: `curl /health.json?verbose=true` still returns a valid timestamp.
- [ ] **Verify starter app boots and serves requests after the framework migration.** Run `php bin/arcanum cache:clear`, `php bin/arcanum validate:handlers`, then hit the starter app with a few requests (`/`, `/health.json`, `/health.json?verbose=true`) using the bench harness or `php -S` to confirm nothing regressed.

#### Cross-cutting

- [ ] **Run `composer check` after each commit.** PHPStan will catch any missed call sites or constructor mismatches; PHPUnit will catch any test that broke without an obvious symptom.
- [ ] **Update COMPENDIUM.md.** Add a note in the Hourglass entry that `Clock` is now used by Sessions, Auth, Throttle, and Vault. Per the maintenance rule at the top of PLAN.md and COMPENDIUM.md, this is part of done, not a follow-up.
- [ ] **Final sweep.** After all migrations, re-run the discovery grep (`time(`, `new DateTime`, `new DateTimeImmutable`) across `src/` to confirm only the explicitly-skipped sites remain. Update this checklist with any stragglers found.

### Welcome page — nice-to-haves (deferred)

The Index redesign landed (nine-section structure, real diagnostics, CSS-only tabs, copy buttons, ASCII rune). The leftovers are explicitly optional:

- [ ] **Diagnostic rows link to configuration docs** — every non-green row in the welcome page Application column (and any Environment row that's red) should link out to the relevant Arcanum configuration doc when clicked. A yellow "Session driver — not configured (optional — required for CSRF)" line should link to the session config guide; a red "Cache driver — config broken" line should link to the cache config guide; "Database — not configured" links to database setup; etc. Cheap UX win once the docs site exists. Blocked on the documentation site itself — defer until real docs URLs exist (same blocker as the placeholder URL cleanup below).
- [ ] **Syntax highlighting in code blocks** — the welcome page's incantation card and CQRS demo tabs render plain monochrome `<pre><code>` blocks. Adding color hinting (PHP keywords, strings, attributes) would meaningfully improve the first impression. Low priority. Look for a small client-side library — Prism.js, highlight.js, or shiki — that loads from CDN with a single script tag and a stylesheet, no build step required. Constraints: must respect dark mode (the page already toggles `.dark` on `<html>`), must not be a heavy dependency (the welcome page is the only consumer for now), and must not require running a Node toolchain to use. This isn't welcome-page-only — Shodo's documentation, README code blocks, and any future docs page would all benefit. When picking a library, prefer one that handles PHP, HTML, SQL, and shell well since those are the four languages the framework's docs use most.
- [ ] **`?debug=1` bootstrap visualization** — replaces welcome banner with bootstrap order list when the query param is set. Easter egg.
- [ ] **Placeholder URL cleanup** — replace `https://example.com/{docs,tutorial,api,discussions}` references in the Index page with real URLs once real docs / tutorial / community channels exist. GitHub repo links are already real.

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
- **PHP `//` comments terminate at `?>`.** A docblock that mentions `<?= ?>` literally will break the file's parse — the lexer needs to switch out of PHP mode. Use `/* */` blocks for any prose containing template markers.

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

