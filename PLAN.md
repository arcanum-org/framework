# Framework Completion Plan

> **COMPENDIUM.md must stay in sync.** Any time framework functionality changes, is added, or is removed — new packages, renamed classes, dropped features, new CLI commands, new attributes, new built-in marks, anything a user-facing tour would mention — update `COMPENDIUM.md` in the same change. It's the source of truth for the eventual documentation site, and an out-of-date entry there is worse than no entry. Treat it like committing: not a follow-up, part of done.

---

## On Deck (needs planning and checklists)

These are the highest-impact items from the backlog. Each needs to be broken into a walkable checklist before work begins.

1. **Logging instrumentation** — The only pre-1.0 blocker. The framework is silent in production. Inject `?LoggerInterface` progressively across the HTTP lifecycle first (routing → dispatch → render → response), then expand to auth, cache, throttle, migrations. Log *decisions*, not *data*. Null means silent — no performance cost when logging is disabled.

2. **Todo App dogfood** — Build a full Todo app using the starter app. SQLite via Forge, Vault caching, auth with sessions, Tailwind + htmx front-end. Full CRUD, task lists, completion toggling, filtering. Then write a retrospective: pain points, what worked, friction in the DX, missing features. The retrospective feeds back into new plan items. This is how we find what we can't see from the framework side.

3. **Hyper README** — The only core package without a README. Document PSR-7 message classes, response renderers, exception renderers, format registry, file uploads, URI handling. Quick win, high credibility impact.

4. **PSR-18 HTTP Client** — Any real app needs outgoing HTTP requests (APIs, OAuth, webhooks). Wrap an established library, implement PSR-18 `ClientInterface` and PSR-17 factories over Hyper's existing PSR-7 classes. Bootstrap registration, testing mock, logging integration.

5. **Integration test coverage** — Only 2 integration tests exist today (`CqrsLifecycleTest`, `HelperResolutionTest`). Unit tests miss interaction bugs: discovery ordering, bootstrap sequencing, round-trip rendering, lifecycle event flow. TestKernel already exists — expanding coverage is cheap. Long-tail effort: every new feature should land with at least one integration test.

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
