# Framework Completion Plan

---

## Upcoming Work

### Starter app — index page redesign (Groups 2–5)

**Group 1 (data layer)** is done — `EnvCheckHelper`, `WiredUpHelper`, `IncantationHelper`, `RequestCounter`, and the `#[WithHelper]` wiring on `Index.php` all live in the starter app. `EnvCheckHelper::renderDurationMs()` now reads from the framework Stopwatch (the request-scoped `RenderMetrics` holder is gone). What remains is the actual page redesign that consumes those helpers, plus content writing and verification.

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

---

## Long-Distance Future

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
- **Todo App dogfood** — build a fully-featured Todo app twice: once from scratch (no starter app), once using the starter app as a base. Both versions: SQLite via Forge, Vault caching, auth with sessions, Tailwind + HTMX front-end. Full CRUD, task lists, completion toggling, filtering. Step-by-step, experiencing the framework as an app developer would. Then write a retrospective: pain points, what worked, what didn't, friction in the DX, missing features, surprising gaps. The retrospective feeds back into new plan items. This is how we find what we can't see from the framework side.
- **Arcanum Wizard** — interactive project scaffolding tool (`composer create-project` or standalone script). Guides a developer through setting up a new Arcanum app: project name, database driver, cache driver, auth, Tailwind + HTMX, session config, etc. Generates `config/` files, `composer.json`, directory structure, and a working entry point. **Must wait until after the Todo App dogfood and retrospective** — we need to know what the real setup experience is before we try to automate it.

---

## Lessons & Tenets

The framework's load-bearing decisions, distilled. Not an inventory — git history is the inventory. These are the things to remember when making future calls.

### What Arcanum is

- **CQRS strictness pays off.** Commands return `EmptyDTO` (204), `AcceptedDTO` (202), or a `Query` DTO that becomes a `201 Created` with a `Location` header — never response bodies. Queries return data. The boundary stays clean and handlers stay tiny because the framework absorbs the ceremony (ValidationGuard, AuthorizationGuard, DomainContext, the Conveyor middleware stack).
- **SQL is a first-class citizen.** Forge maps `__call` to `.sql` files with `@cast` annotations. No query builder, no ORM — both fight CQRS. Generated sub-models give handlers fully type-safe injection without losing the "SQL is the source of truth" property.
- **Streaming is the default.** `Flow\Sequence\Cursor` streams row-by-row at constant memory (6.3 KB at 500k rows vs 1.6 GB eager). `toSeries()` is the explicit opt-in to materialize.
- **HTTP status codes are part of the API.** 204/201/202/405/406/422/429 — never collapse to 200/404/500. The exception infrastructure carries titles + suggestions and is RFC 9457 forward-compatible.
- **Build-time validation beats runtime discovery.** `validate:handlers`, `validate:models`, page discovery cache, bootstrapper-order enforcement. Cheaper than reflection-walking on every request, and the failure modes name the actual problem.

### What Arcanum is *not*

- **Not a full template engine.** Shodo is intentionally lightweight. The body of `{{ }}` is a PHP expression after helper rewriting, not a custom DSL. No filters, no inheritance gymnastics beyond `extends`/`section`/`yield`/`include`.
- **Not an asset pipeline.** JS/CSS tooling handles its own world. Arcanum ships Tailwind via CDN in dev and a built bundle in prod, with a guardrail warning when the bundle is missing — but it doesn't compile anything itself.
- **Not a runtime auto-discovery framework.** Discovery happens at build time via CLI commands. The container is PSR-11; services don't enumerate.

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

**Open question:** 3→10 DTO fields drops throughput 77% — worth profiling. Reflection is exonerated; the bottleneck is somewhere else.

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
