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

- [x] **Land explanation comments at every skipped time-using site** so future maintainers (or future Claude) don't "fix" what isn't broken. Covers `Vault\ApcuDriver::resolveTtl`, `Vault\RedisDriver::resolveTtl` (interval-to-int converters that never read "now"), `Shodo\Helpers\FormatHelper::date` (caller-provided value formatter), and `Hourglass\Stopwatch` (elapsed-time telemetry — Clock and Stopwatch model different things, conflating them would mean a test that froze Clock would also freeze elapsed-time measurement). Each comment names Hourglass\Clock explicitly and explains the why so the decision is locally legible without needing to read PLAN.md.

#### Bootstrap

- [x] **Register `Clock` in the container.** Renamed `Bootstrap\Stopwatch` → `Bootstrap\Hourglass`, added `Clock::class → SystemClock` singleton binding alongside the existing Stopwatch wiring. `HyperKernel` and `RuneKernel` bootstrap lists updated; Ignition README built-in marks table updated.
- [x] **Test the registration.** Added `tests/Ignition/Bootstrap/HourglassTest.php` covering both bindings — no test existed for the old `Bootstrap\Stopwatch`, so this is a fresh slate. Five test methods: Stopwatch singleton, Stopwatch process-global install, `arcanum.start` instant recorded, Clock bound to SystemClock, Clock resolves as singleton.

#### Vault

- [x] **`ArrayDriver` — inject Clock, migrate two `time()` sites.** Constructor now takes `Clock $clock = new SystemClock()` so existing call sites stay green and Codex auto-wires the container-bound clock in production. Three new deterministic tests using `FrozenClock::advance()`: still-valid before expiry, expired after, and a multi-step `DateInterval` walk.
- [x] **`FileDriver` — inject Clock, migrate two `time()` sites.** Same pattern as `ArrayDriver`: appended `Clock $clock = new SystemClock()` to the existing constructor signature, replaced both `time()` reads with `$this->clock->now()->getTimestamp()`. Three new `FrozenClock::advance()` tests. Hoisted the expiry-check `now` to a local to keep the line under 120 chars (cs-check caught it, fixed before committing).
- [x] **Vault README — document the Clock dependency.** Driver subsections note the optional `Clock` parameter; new `## Deterministic expiry tests` section walks through `FrozenClock::advance()` with a worked example. Notes that `ApcuDriver`/`RedisDriver` are intentionally Clock-free.

#### Throttle

- [x] **`TokenBucket` — inject Clock, migrate one `time()` site.** New constructor takes `Clock $clock = new SystemClock()`; the lone `time()` read is now `$this->clock->now()->getTimestamp()`. Throttler also computes `retryAfter` and passes it to `Quota`. Two new `FrozenClock`-based tests: deterministic refill across `advance()`, and an explicit retryAfter assertion under a frozen clock.
- [x] **`SlidingWindow` — inject Clock, migrate one `time()` site.** Same pattern as `TokenBucket`: constructor takes `Clock $clock = new SystemClock()`, `time()` becomes `$this->clock->now()->getTimestamp()`, denied Quotas get an explicit `retryAfter`. Two new `FrozenClock`-based tests cover deterministic window rotation and the explicit retryAfter assertion.
- [x] **`Quota` — accept retryAfter at construction (Option B from the plan).** Quota now takes `int $retryAfter = 0` and `headers()` uses it directly. Both Throttlers pass the explicit value, and the temporary wall-clock fallback that bridged the staged commits has been removed (`a461fbd`). Quota is now Clock-free entirely; QuotaTest covers explicit-retryAfter and default-zero paths.
- [x] **Throttle README — document the Clock dependency.** Quota table now lists `$retryAfter` and a sentence noting Quota stays Clock-free. New `### Deterministic tests with FrozenClock` subsection with a worked TokenBucket example.

#### Auth

- [x] **`CliSession` — inject Clock, migrate two `time()` sites.** Constructor now takes `Clock $clock = new SystemClock()`; both `time()` reads in `store()` and `load()` migrated. The existing `testExpiredReturnsNullAndDeletesFile` test was using `sleep(1)` to wait for wall-clock advance — refactored to use `FrozenClock::advance()` with no sleep. Added a complementary "still valid before expiry" test. Test suite is ~1 second faster as a side benefit.
- [x] **Auth README — document the Clock dependency.** One paragraph in the CLI Sessions section: optional Clock parameter, container auto-wires SystemClock, FrozenClock for deterministic expiry tests.

#### Starter app

- [x] **`app/Domain/Query/HealthHandler` — migrate the verbose-mode `time()` call.** Constructor takes `Clock $clock = new SystemClock()`; verbose timestamp now reads through the clock. New `testVerboseTimestampComesFromInjectedClock` test pins the value with a `FrozenClock`. composer.lock bumped to pull in `psr/clock` transitively. Starter app commit: `009c7d7`.
- [x] **Verify starter app boots and serves requests after the framework migration.** `cache:clear` + `validate:handlers` clean. Smoke-tested via `php -S`: `/` → 200 (24KB welcome page renders), `/health.json` → `{"status":"ok"}`, `/health.json?verbose=true` → `{"status":"ok","timestamp":1775696981,"php":"8.4.3"}` — the timestamp confirms the framework's `Bootstrap\Hourglass` binding successfully auto-wires `SystemClock` into `HealthHandler` via Codex. Full chain works end-to-end.

#### Cross-cutting

- [x] **Run `composer check` after each commit.** Pre-commit hook enforces this — every commit on the branch has a green `composer check` line in its output. Suite is at 2503 tests / 5037 assertions and stayed green throughout the migration.
- [x] **Update COMPENDIUM.md.** Hourglass entry now names the consumers (Vault `ArrayDriver`/`FileDriver`, Throttle `TokenBucket`/`SlidingWindow`, Auth `CliSession`), notes the bootstrap binding, and explicitly calls out that Stopwatch deliberately bypasses Clock — they model different things, and conflating them would freeze elapsed-time measurement when a test froze the wall-clock. Testing-section paragraph updated to reflect that the Clock half of the testing-utilities arc is in progress.
- [x] **Final sweep.** Re-ran the discovery grep across `src/`. Down from 15 files to 13: the four migrated files (`TokenBucket`, `SlidingWindow`, `Quota`, `CliSession`) no longer match. Remaining 13 hits are all accounted for: `Hourglass\SystemClock` (the Clock implementation itself), `Hourglass\Stopwatch`/`Instant` (deliberately skipped, documented), `Vault\ApcuDriver`/`RedisDriver`/`FormatHelper` (deliberately skipped, documented), and the interval-converter lines inside `ArrayDriver::resolveExpiry`/`FileDriver::resolveExpiry` (which sit two lines below the migrated `$now` read — same pattern as the documented ApcuDriver/RedisDriver case). Zero stragglers.

### Hourglass Interval helper — active

The four Vault drivers (`ApcuDriver`, `RedisDriver`, `ArrayDriver`, `FileDriver`) all duplicate the same `(new \DateTime())->setTimestamp(0)->add($ttl)->getTimestamp()` incantation to convert a `DateInterval` into a count of seconds. Hourglass should own this primitive — it's a time conversion, it's stateless, and any code dealing with PSR-16 TTLs has the same need. Eliminating the duplication also resolves the inline-comment question for `ArrayDriver`/`FileDriver` `resolveExpiry()`: `Interval::secondsIn($ttl)` reads cleanly next to `$now = $this->clock->now()->getTimestamp()` and removes the "is this a now-read?" confusion at the source.

**Design:** helper-only, no subclassing. `Hourglass\Interval` is a final utility class with two static methods. Keeps Hourglass free of inheritance from PHP built-ins and avoids inheriting `DateInterval`'s mutability. If a discoverable instance method is wanted later, adding `final class Interval extends \DateInterval { public function toSeconds(): int { return self::secondsIn($this); } }` is a one-line follow-up — easier to add than to remove.

- [x] **Add `Hourglass\Interval` with `secondsIn` and `ofSeconds`.** Final class, two static methods. `secondsIn` uses the epoch-anchor trick; `ofSeconds` clamps negatives to zero and constructs `new \DateInterval("PT{$n}S")`. 13 IntervalTest methods covering h/m/s/d intervals, mixed intervals, the documented `P1M`→31d / `P1Y`→365d epoch-anchor behavior, the negative-clamp, and a round-trip.
- [x] **Migrate all four Vault drivers to call `Interval::secondsIn()`.** Single commit covering ApcuDriver, RedisDriver, ArrayDriver, FileDriver. Each driver gains `use Arcanum\Hourglass\Interval;` and replaces the epoch-anchor incantation with `Interval::secondsIn($ttl)`. ApcuDriver/RedisDriver `resolveTtl` docblocks trimmed — the verbose explanation is no longer needed because the call site speaks for itself.
- [x] **Hourglass README + COMPENDIUM.** Hourglass README intro now lists three primitives (Clock / Stopwatch / Interval), with a new `## Interval` section covering the API table, the documented epoch-anchor behavior for months and years, and a "why no value object" subsection capturing the helper-vs-subclass-vs-wrap reasoning. COMPENDIUM Hourglass entry mentions Interval as a sibling primitive used by all four Vault drivers.

### Testing utilities — `Arcanum\Testing` — active

The single highest-leverage item left. App developers writing handler tests today have to hand-roll Cabinet containers, fake PSR-7 requests, manual DTO construction, and stubbed services. Even the framework's own tests show the strain — `RuneKernelTest` has private `containerWith()`/`bootstrapKernel()` helpers that absorb the boilerplate but aren't reusable, and there are two ad-hoc test kernels in the codebase already (`tests/Fixture/CapturingKernel` in the framework, `tests/Helpers/FixtureKernel` in the starter app) that solve narrow slices of the same problem in incompatible ways. The Hourglass Clock work that just landed was the prerequisite — Sessions, Auth, Throttle, and Vault are now fakeable via `FrozenClock`, which is what `TestKernel` needs to bind during construction.

**Design decisions made during discovery:**

- **Package location:** `src/Testing/`, namespace `Arcanum\Testing\`. Full README parallel to other framework packages. Loaded via `autoload` (not `autoload-dev`) so it ships as a public API surface.
- **Kernel composition, not re-implementation.** `TestKernel` composes a real `HyperKernel` and a real `RuneKernel` internally rather than re-implementing the bootstrap loop. Wrapping means `TestKernel` automatically picks up future bootstrapper additions and lifecycle changes — no parallel maintenance burden.
- **Lazy transport construction.** `TestKernel::http()` and `TestKernel::cli()` build their respective kernels on first call and memoize. Most tests touch one transport; a CLI-only test shouldn't pay for constructing a HyperKernel it never uses. Cross-transport tests still work because the shared state (container, FrozenClock, ArrayDriver, ActiveIdentity) lives on the parent `TestKernel`, and both lazy kernels bootstrap against the same container — so a `cli()->run('login')` followed by `http()->get('/api/me')` sees the same identity.
- **`Factory` composes `Codex\Hydrator` rather than reimplementing the reflection walk.** `Hydrator` already owns ctor reflection, default-value handling, scalar coercion, missing-required errors, and (critically) passes object-valued data through unchanged — so a Factory that pre-builds nested DTO instances and hands them off via `$data` works without any Hydrator changes. Factory's only job is the pre-pass: walk params, and for each one without an override and without a default, synthesize a value (from validation attributes, recursive Factory call for nested DTOs, or `null` for nullable types). Then `array_merge($synthesized, $overrides)` and call `Hydrator::hydrate()`. Constructor takes `Hydrator $hydrator = new Hydrator()` so `new Factory()` stays ergonomic in tests. Free upgrade path: any future Hydrator improvement (richer coercion, union types) flows through Factory automatically. `Codex\Resolver` is the wrong tool — it fills params from container bindings, not from synthesized payload values.
- **`Factory` ships with the easy validation rules supported.** `NotEmpty`, `Email`, `Url`, `Uuid`, `MinLength`, `MaxLength`, `Min`, `Max`, `In`, `Pattern` (literal-string patterns), nullable types. Throws `FactoryException` with a "provide an override" message for `#[Pattern]` against arbitrary regexes and `#[Callback]` rules — those are user-payload-dependent and can't be auto-generated.
- **`Fake\` namespace deferred.** No fakeable external boundaries exist in the framework yet — the mailer, HTTP client, and queue are all on the long-distance list and don't have interfaces to fake against. Defer the entire `Fake\` namespace until the first such interface lands (likely the mailer).
- **Inception caveat:** `TestKernel`'s own tests can't use `TestKernel` — they have to construct it directly and verify its behavior. `tests/Testing/*Test.php` will look more like traditional unit tests than the kind of tests app developers will write *using* `TestKernel`. That's expected.

#### Foundation

- [x] **Create `Arcanum\Testing` package skeleton.** New `src/Testing/` with placeholder `TestKernel.php`, `Factory.php`, and a `README.md` stub. No composer.json change needed — `Arcanum\` already maps to `src/` under production `autoload`, so `Arcanum\Testing\` ships as public API automatically. `tests/Testing/PackageSkeletonTest.php` smoke-tests both classes load and instantiate (`#[CoversClass]` on each). Suite green at 2518 tests.
- [x] **Build the minimal `TestKernel` core.** Constructor takes optional `Clock`, `CacheInterface`, and `string $rootDirectory` overrides. Defaults to a `FrozenClock` pinned at `2026-01-01T00:00:00+00:00`, an `ArrayDriver` sharing that clock, and a fresh `ActiveIdentity`. Builds a real Cabinet `Container` up front and binds all three by interface (`Clock::class`, `CacheInterface::class`, `ActiveIdentity::class`). Exposes `container()`, `clock()`, `cache()`, `rootDirectory()`, and `actingAs(Identity): self` (chained — name follows a widely-used convention across PHP test harnesses). `tests/Testing/TestKernelTest.php` covers defaults, container bindings, override path, `actingAs` chaining + identity propagation, and `FrozenClock::advance()` reflected through `clock()`. Suite green at 2522 tests.
- [x] **Add the `http()` surface.** `Arcanum\Testing\HttpTestSurface` with `get/put/post/patch/delete` returning `ResponseInterface`. `TestKernel::http()` lazily constructs and memoizes an internal `Arcanum\Testing\Internal\TestHyperKernel` (a HyperKernel subclass with an empty bootstrappers list so it doesn't stomp the FrozenClock or other shared bindings) and bootstraps it against the shared container on first dispatch. Surface translates fluent calls into real `ServerRequest`s built via `Hyper\Headers/Message/Request/URI/Version/Flow\River` so the wrapped kernel's full pipeline runs (including JSON `prepareRequest()` parsing). `withHeader()` is a chained setter; default headers persist across requests on the same surface. `setCoreHandler()` installs a PSR-15 fixture handler the kernel delegates to via the override; without one, requests render through the kernel's standard 404 path. `tests/Testing/HttpTestSurfaceTest.php` covers memoization, all five verbs, query/header/body propagation, JSON body parsing, and the unrouted-404 path. Test fixtures live in `tests/Fixture/Testing/` (PSR-12 one-class-per-file). Suite green at 2528 tests.
- [x] **Add the `cli()` surface.** `Arcanum\Testing\CliTestSurface::run(array $argv): CliResult`. `CliResult` carries `exitCode`, `stdout`, `stderr`. Captures via `Arcanum\Testing\BufferedOutput` (an in-memory `Rune\Output` impl) bound fresh into the shared container per `run()` so captures never bleed across invocations. `TestKernel::cli()` lazily constructs and memoizes an internal `Internal\TestRuneKernel` (RuneKernel subclass with empty bootstrappers, mirroring TestHyperKernel) and bootstraps against the shared container on first dispatch. `setRunner()` installs a `(callable(Input, Output): int)` the kernel delegates to for non-empty argv (parallel to `HttpTestSurface::setCoreHandler()`); without a runner the empty-argv splash path falls through to the real `RuneKernel::handle()`. `tests/Testing/CliTestSurfaceTest.php` covers memoization, runner dispatch with stdout+stderr+exit-code capture, splash fall-through, fresh-buffer-per-run, runner clearing, and the cross-transport `actingAs()` invariant proving both surfaces share the same container. Suite green at 2534 tests.
- [x] **Build `Factory`.** Composes `Codex\Hydrator` per the design decision above. Pre-pass walks ctor params, skips any param with an override or a default, and synthesizes the rest based on the declared type and validation attributes: strings respect `#[Email]`, `#[Url]`, `#[Uuid]`, `#[In]`, `#[MinLength]`, `#[MaxLength]` (combined), `#[NotEmpty]`; ints/floats respect `#[Min]`/`#[Max]`/`#[In]`; bools respect `#[In]`; arrays respect `#[In]` and default to `['x']`; nullable types with no rules synthesize `null`; nested user-class types recurse via `Factory::make`. `#[Pattern]` and `#[Callback]` throw `FactoryException` with a "provide an override" hint — both are user-payload-dependent. Public API: `Factory::make(class-string<T>, array $overrides = []): T`. Constructor takes `Hydrator $hydrator = new Hydrator()` so `new Factory()` stays ergonomic. `tests/Testing/FactoryTest.php` covers happy path (synthesized values verified by running the real `Validator::check()` against the produced DTO — this catches drift between Factory and the rule semantics), override path, default fallback, recursive nested-DTO path, nullable scalar path, both `FactoryException` cases, and the explicit-override escape hatch for `#[Pattern]`. Test fixtures (`SimpleDto`, `NestedDto`, `PatternDto`, `CallbackDto`, `NullableDto`) live under `tests/Fixture/Testing/`. PackageSkeletonTest deleted now that Factory has real coverage. Suite green at 2543 tests.
- [x] **Write `src/Testing/README.md` and update `COMPENDIUM.md`.** README walks through TestKernel construction + accessors + overrides, the HTTP and CLI surfaces (verb table, `withHeader`/`setCoreHandler`/`setRunner`, JSON body parsing, fresh-buffer-per-run), cross-transport state, and Factory's synthesis table + the explicit-override escape hatch for `#[Pattern]`/`#[Callback]`. COMPENDIUM gains a Testing entry (package count bumped 21 → 22) and the "Testing today" section now reflects that apps have a real harness instead of "nothing yet". The `withDatabase`/`withConfiguration` opt-in builder shape is a future call (flagged in the package internal-kernel comments and the COMPENDIUM entry), not part of this commit.

#### Migration of existing ad-hoc patterns

- [ ] **Migrate `tests/Integration/CqrsLifecycleTest.php` to `TestKernel`.** This is the longest existing integration test and the closest match for what app handler tests will look like. Replaces hand-rolled `stubRequest()` / `container()` / `router()` helpers and the per-test `MiddlewareBus` + `Hydrator` + `Router` construction with `$kernel = new TestKernel(rootDirectory: ...); $response = $kernel->http()->get('/integration/status.json');`. Proves the test kernel handles the most complex existing pattern.
- [ ] **Migrate `tests/Integration/HelperResolutionTest.php` to `TestKernel`.** Same migration pattern.
- [ ] **Evaluate `tests/Hyper/Event/LifecycleEventTest.php` for migration.** Currently uses `CapturingKernel`. The lifecycle events (`RequestReceived`, `RequestHandled`, `RequestFailed`, `ResponseSent`) are dispatched by `HyperKernel`, so a TestKernel wrapping a real HyperKernel should observe them naturally. Migrate if practical, document why not if the test specifically needs `CapturingKernel`'s captured-request behavior.
- [ ] **Migrate starter app `tests/Helpers/WiredUpHelperTest.php` to `TestKernel`.** Replaces `FixtureKernel` instantiation with `new TestKernel(rootDirectory: $this->tmpRoot)`. The helper takes a real `Kernel` instance — `TestKernel` is one.
- [ ] **Migrate starter app `tests/Helpers/EnvCheckHelperTest.php` to `TestKernel`.** Same migration.
- [ ] **Delete starter app `tests/Helpers/FixtureKernel.php`.** Once both callers are migrated, the fake kernel has no remaining users. Removing it forces future helper tests to use `TestKernel` instead of inventing a new ad-hoc fake.

#### Cross-cutting

- [ ] **Run `composer check` after each commit.** Pre-commit hook enforces this; same practice as the Hourglass arc.
- [ ] **Final sweep.** After all migrations, grep `tests/` for any remaining ad-hoc patterns: classes implementing `Kernel` directly, anonymous subclasses of `HyperKernel`/`RuneKernel`, manual `MiddlewareBus + Hydrator + Router` construction. Update this checklist with anything found.

### Welcome page — nice-to-haves (deferred)

The Index redesign landed (nine-section structure, real diagnostics, CSS-only tabs, copy buttons, ASCII rune). The leftovers are explicitly optional:

- [ ] **Diagnostic rows link to configuration docs** — every non-green row in the welcome page Application column (and any Environment row that's red) should link out to the relevant Arcanum configuration doc when clicked. A yellow "Session driver — not configured (optional — required for CSRF)" line should link to the session config guide; a red "Cache driver — config broken" line should link to the cache config guide; "Database — not configured" links to database setup; etc. Cheap UX win once the docs site exists. Blocked on the documentation site itself — defer until real docs URLs exist (same blocker as the placeholder URL cleanup below).
- [ ] **Syntax highlighting in code blocks** — the welcome page's incantation card and CQRS demo tabs render plain monochrome `<pre><code>` blocks. Adding color hinting (PHP keywords, strings, attributes) would meaningfully improve the first impression. Low priority. Look for a small client-side library — Prism.js, highlight.js, or shiki — that loads from CDN with a single script tag and a stylesheet, no build step required. Constraints: must respect dark mode (the page already toggles `.dark` on `<html>`), must not be a heavy dependency (the welcome page is the only consumer for now), and must not require running a Node toolchain to use. This isn't welcome-page-only — Shodo's documentation, README code blocks, and any future docs page would all benefit. When picking a library, prefer one that handles PHP, HTML, SQL, and shell well since those are the four languages the framework's docs use most.
- [ ] **`?debug=1` bootstrap visualization** — replaces welcome banner with bootstrap order list when the query param is set. Easter egg.
- [ ] **Placeholder URL cleanup** — replace `https://example.com/{docs,tutorial,api,discussions}` references in the Index page with real URLs once real docs / tutorial / community channels exist. GitHub repo links are already real.

---

## Upcoming Work

### Testing utilities — promoted to active checklist

See "Testing utilities — `Arcanum\Testing`" under Active Checklists. The Clock half of the original "Testing utilities + PSR-20 Clock adoption" arc is fully done; the testing-utilities half is now in progress.

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
- **`TestKernel` transactional database wrapping** — Symfony's DAMA DoctrineTestBundle and similar tools across the PHP ecosystem solve the same problem: keep test database state isolated by wrapping each test in a transaction and rolling back at teardown. When Forge grows real-database integration tests, `TestKernel` should ship a transactional opt-in (`->withTransactionalDatabase()` or similar) that does the same. Out of scope for the current testing-utilities arc — Arcanum doesn't yet have enough Forge usage to justify the design — but worth flagging now so the precedent isn't forgotten when the need arises. The pattern is well-trodden across the PHP ecosystem.
- **Refactor `HyperKernel` and `RuneKernel` onto a shared `AbstractKernel` base** — Discovery for the testing-utilities arc surfaced that the two production kernels share ~80% of their structure: identical constructor signature, identical `isBootstrapped` flag, identical four directory accessors, identical bootstrap loop (only differing in which `Transport` enum gets bound at line 1), identical `Stopwatch::tap('boot.complete')`, identical `Stopwatch::tap('arcanum.complete')` in `terminate()`. The bootstrapper lists overlap entirely except for the HTTP-specific entries (`Sessions`, `Routing`, `Helpers`, `Formats`, `RouteMiddleware`, `Middleware`). Only `handle()` genuinely diverges (PSR-7 in / PSR-7 out vs argv in / int out). An `AbstractKernel` base class could collapse the duplication. **Wait until after the testing-utilities arc lands** — TestKernel will exercise both production kernels through composition, so the test surface will be in place to verify the refactor doesn't break behavior. Treat this as a pure refactor; no API changes.
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

