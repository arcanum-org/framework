# Framework Completion Plan

> **COMPENDIUM.md must stay in sync.** Any time framework functionality changes, is added, or is removed — new packages, renamed classes, dropped features, new CLI commands, new attributes, new built-in marks, anything a user-facing tour would mention — update `COMPENDIUM.md` in the same change. It's the source of truth for the eventual documentation site, and an out-of-date entry there is worse than no entry. Treat it like committing: not a follow-up, part of done.

---

## Active Checklists

Concrete, walkable lists. Everything else in this file is informational — context, history, decisions, and future work that hasn't been broken into steps yet. When something becomes a checklist, it lives here.

### Shodo fragment support — superseded by auto-detection

The `{{ fragment 'name' }}` directive was built, tested, and documented, then removed in favor of auto-fragment extraction from HTML `id` attributes (see the htmx package section below). The directive added ceremony that auto-detection eliminates — developers just write `<div id="sidebar">` and the framework extracts it on htmx requests.

**What survived from this arc:**
- `TemplateCache` fragment-keyed entries (`isFresh`, `load`, `store`, `cachePath` all accept an optional `fragmentName` parameter). Auto-detection reuses this for caching by element id.
- `HtmlFormatter` gained a `LoggerInterface` constructor parameter for warning on fall-through. Auto-detection will use this.
- The content-section-only mode (`compile(fragment: true)` / `setFragment(true)`) is unchanged and still serves as the fall-through when auto-detection finds no matching id.

#### Cross-cutting

- [x] **Run `composer check` after each commit.**

### htmx package — `Arcanum\Htmx` — active

First-class htmx support baked into the framework, targeting **htmx 4** directly. Replaces the starter app's local `App\Http\Middleware\Htmx` (30-line minimal middleware) with a full `src/Htmx/` package that exposes:

- `HtmxRequest` — read-side decorator over `ServerRequestInterface` for handlers that want to inspect htmx headers.
- **Auto-fragment extraction** — the framework reads `HX-Target` from the request, finds the matching `id="..."` element in the raw template source, extracts just that region, and compiles/renders only the extracted slice. Developers write normal HTML with ids; the framework handles partial rendering automatically.
- `ClientBroadcast` — marker interface on domain events that should project as `HX-Trigger` server-to-client signals.
- Automatic swap-mode awareness — the framework reads the swap mode (outerHTML vs innerHTML) and includes or strips the wrapper element accordingly.
- An `HtmxEventTriggerMiddleware` that auto-projects domain events through Echo into `HX-Trigger` headers — the CQRS-native cross-component refresh story.
- A CSRF JS shim served from a framework endpoint (`/_htmx/csrf.js`) that auto-injects tokens via `htmx:configRequest`.
- An htmx-aware auth-redirect handler that returns `HX-Location: /login` on 401/403 instead of swapping login HTML into a random target.

**Design decisions made during discovery:**

- **Target htmx 4 directly, no v2 fallback.** Pin to `4.0.0-beta1` exactly (configurable via `config/htmx.php`). v4 fixes the 422 validation gotcha by default (4xx/5xx swap), introduces `HX-Request-Type` for clean rendering-mode resolution, ships native morph swaps, and aligns mentally with Arcanum's CQRS conventions. The htmx team has perpetual v2 support, so existing v2 docs/tutorials will keep working in the wider ecosystem.
- **Auto-fragment extraction from HTML ids — the zero-ceremony default.** Instead of requiring developers to wrap regions in `{{ fragment 'name' }}…{{ endfragment }}` directives, the framework finds elements by `id` attribute directly in the raw template source. When `HX-Target: sidebar` arrives, the compiler searches the template's content section for `id="sidebar"`, determines the tag name, depth-counts open/close tags to find the matching close, extracts that slice, and compiles only the extracted region. The developer writes natural HTML with ids — no fragment directives, no htmx awareness in the template. The explicit `{{ fragment }}` directive (already landed in Shodo) remains as a fallback for unusual layouts, but the auto-detection path is the primary mechanism and what documentation leads with.
- **Swap-mode-aware extraction.** The framework reads the swap mode from the request (htmx v4 sends swap info, or the framework infers from config). For `outerHTML` swaps, the extracted region includes the target element itself. For `innerHTML` swaps, the framework strips the wrapper and returns only the element's children. Same template, same handler — the framework adapts the response shape automatically. This eliminates the innerHTML/outerHTML footgun entirely: the developer doesn't need to think about swap modes when writing templates.
- **`HX-Target` is only sent when the target element has an `id`.** Confirmed in htmx source (line 3605: `'HX-Target': getAttributeValue(target, 'id')`). `Element.getAttribute('id')` returns null for elements without an id, so the header is omitted. This means auto-detection is the only viable server-side fragment resolution — the server never receives CSS selectors, only ids (or nothing). The convention is self-enforcing: if the developer wants server-side partial rendering, they put an id on the target element. If they don't, htmx swaps the full response and the server renders normally.
- **App-facing API surface is intentionally tiny:** `HtmxRequest`, `ClientBroadcast`. Two types. Everything else is middleware, internal builders, and config the developer never imports. No `Fragment` value object — strict CQRS means commands don't return data, and queries always return the full dataset; the framework decides what slice to render from `HX-Target`.
- **Handlers stay transport-agnostic by default.** The framework's HTML renderer reads `HX-Request-Type` from the request. On `partial`, it reads `HX-Target` to get the element id, finds it in the template source, extracts and compiles just that region. On `full`, it renders the whole template (with layout for non-htmx, content section for htmx-with-full-type). Handlers return plain data; the renderer figures out the shape.
- **Dynamic ids are a routing question, not a fragment question.** `<div id="item-{{ $id }}">` inside a `{{ foreach }}` can't be extracted at the template level — the id is only known after rendering, and the loop variable `$item` would be undefined outside its scope. The existing debug-mode undefined-variable detection catches this naturally. The CQRS answer: if something is independently addressable via htmx, it should be its own query with its own handler and template. Per-item updates route to a single-item query (`GET /items/42`), not a fragment of the list query.
- **`ClientBroadcast` is a marker interface** with `eventName(): string` and `payload(): array`. Sub-interfaces `BroadcastAfterSwap` and `BroadcastAfterSettle` for the timing variants — most events default to immediate `HX-Trigger`.
- **`HtmxResponse` is framework-internal**, possibly under `Internal\`. App code never constructs it. It's the typed builder middleware uses to compose response headers.
- **`Vary: HX-Request` is auto-added** by `HtmxRequestMiddleware` via `withAddedHeader` (preserves existing `Vary` values). Config opt-out available; default on. Avoids the cache-key footgun.
- **No `#[HtmxOnly]` attribute** — pattern doesn't match transport-guard semantics, can be added later if a real case demands it.
- **Don't override htmx's default `hx-swap`.** Keep upstream docs accurate for Arcanum users. Recommend `outerHTML` in package README + starter examples, since it pairs most naturally with the id-based extraction (the target element survives the swap).
- **Top-level `src/Htmx/` package**, not nested under Hyper. Cross-cuts Echo, Session, Auth, Shodo, Validation, Hyper, Glitch, Ignition — the cross-cutting nature makes a sub-package wrong. Parallel to `Arcanum\Testing` in placement and dependency shape.
- **Soft fall-through on missing ids**: when `HX-Target` carries an id with no matching element in the template, the renderer falls back to the content section and logs a warning. Strict failure would 404 real users for typos.
- **Nested ids work naturally.** A `<div id="main">` containing `<div id="subsection-a">` and `<div id="subsection-b">` produces three addressable fragments. Each extraction is independent — search for the target id, depth-count from there. No nesting constraints to enforce because HTML element structure is unambiguous (unlike flat text markers where the parser can't tell which close tag matches which open tag).

#### Foundation

- [x] **Create `Arcanum\Htmx` package skeleton.** New `src/Htmx/` directory with placeholder files (`HtmxRequest.php`, `ClientBroadcast.php`, `README.md` stub). Smoke test verifies namespace loads. No composer.json change needed — `Arcanum\` already maps to `src/`.
- [x] **Add `HtmxRequestType` enum and `HtmxRequest` decorator.** Enum: `Full` | `Partial`. Decorator wraps `ServerRequestInterface` with `isHtmx()`, `isBoosted()`, `isHistoryRestore()`, `type(): HtmxRequestType`, `target(): ?string` (the `HX-Target` value — an element id or null), `swapMode(): ?string` (the `HX-Swap` value when available), `source(): ?string`, `triggerName()`, `currentUrl()`, `prompt()`. Tests cover every accessor + edge cases (missing headers, empty values).
- [x] **Add `ClientBroadcast` marker interface and timing sub-interfaces.** `ClientBroadcast::eventName(): string` and `payload(): array`. `BroadcastAfterSwap extends ClientBroadcast` and `BroadcastAfterSettle extends ClientBroadcast` are pure marker sub-interfaces — no method additions, just type-level signals. Tests cover that `instanceof` checks pick the right sub-interface.

#### Auto-fragment extraction (Shodo integration)

- [x] **Add `TemplateCompiler::extractElementById(string $source, string $id): ?ElementExtraction`.** Searches raw template source for an element with the given `id` attribute. Uses tag-name depth counting to find the matching close tag. Returns an `ElementExtraction` value object with `outerHtml` (the full element including its tags) and `innerHtml` (children only), or null when the id isn't found. Handles self-closing/void elements. Tests cover: basic extraction, nested same-tag elements, void elements, id not found, id inside control structures (still extracts — the undefined-variable detection catches scope issues at render time), id with template expressions in attributes (skipped — literal ids only).
- [x] **Add `HtmlFormatter::renderElementById(string $id, string $swapMode, mixed $data, string $dtoClass): string`.** Resolves the template, extracts the content section (no layout), calls `extractElementById`, picks `outerHtml` or `innerHtml` based on swap mode, compiles and renders just that slice. Falls back to content section + log warning when the id isn't found. Uses fragment-keyed cache (`template#id`). Tests cover outerHTML mode, innerHTML mode, cache hit, fall-through, and escaping.

#### Rendering pipeline

- [x] **Add `HtmxResponse` internal builder.** Immutable PSR-7-style decorator with `withLocation`, `withPushUrl`, `withReplaceUrl`, `withRedirect`, `withRefresh`, `withRetarget`, `withReswap`, `withReselect`, `withTrigger`, `withTriggerAfterSwap`, `withTriggerAfterSettle`, `withVary`, `toResponse()`. Trigger methods merge into a single header per timing slot. Tests cover all builder methods + trigger merging.
- [x] **Add `HtmxLocation` value object** for the JSON-envelope form of `HX-Location`. Fields per the htmx spec: `path`, `target`, `swap`, `source`, `event`, `handler`, `values`, `headers`, `select`. JSON-serializable. Tests cover the simple `path`-only case + the full envelope.
- [x] **Extend `HtmlResponseRenderer` (or add `HtmxAwareResponseRenderer`) to read `HX-Request-Type` and `HX-Target` from the request and pick the rendering shape.** Three modes: (1) full + layout (non-htmx), (2) full content section without layout (htmx full-type), (3) auto-extracted element by id from `HX-Target` (htmx partial-type). Falls back to content section + log warning when element id lookup fails. Tests cover all three modes + the fall-through.

#### Middleware

- [x] **Add `HtmxRequestMiddleware`** (inbound). Populates `HtmxRequest` resolution in the container, auto-adds `Vary: HX-Request` via `withAddedHeader`. Replaces the starter app's `App\Http\Middleware\Htmx`. Tests cover the request decoration + the Vary appending (including the "Vary already has Accept" case).
- [x] **Add `EventCapture` and `HtmxEventTriggerMiddleware`** (outbound). `EventCapture` is a thin decorator around Echo's dispatcher that records `ClientBroadcast` events fired during the request. The middleware reads them after the handler runs and merges them into `HX-Trigger` / `HX-Trigger-After-Swap` / `HX-Trigger-After-Settle` based on which sub-interface they implement. Also handles the `Location → HX-Location` copy for command redirects. Tests cover single event, multiple events, mixed timings, the location copy, and the no-events no-op case.
- [x] **Add `HtmxAuthRedirectMiddleware`** (or extend the existing exception renderer). On 401/403 with an htmx request, returns an empty body with `HX-Location: /login` (default) or `HX-Refresh: true` (config). Tests cover both modes plus the non-htmx pass-through.

#### CSRF and config

- [x] **Add `/_htmx/csrf.js` endpoint and `HtmxCsrfController`.** Returns the JS shim that listens to `htmx:configRequest` and attaches the CSRF token from the `<meta name="csrf-token">` tag to every non-GET request. Includes the boosted-navigation token-rotation handling. Cacheable response with `Cache-Control: public, max-age=...`. Tests cover the endpoint response + content.
- [x] **Add `config/htmx.php` and the `Htmx::script()` template helper.** Config exposes the pinned version (`'version' => '4.0.0-beta1'`), integrity hash, CDN URL template, CSRF strategy, auth-redirect mode, Vary opt-out. Helper renders the full `<script src="..." integrity="..." crossorigin="anonymous">` tag for inclusion in the layout. Tests cover the helper output.
- [x] **Add `Bootstrap\Htmx` bootstrapper.** Registers the three middleware classes, the request decorator factory, the event capture, and the config-loaded values. Added to HyperKernel's `$bootstrappers` list. Tests cover bootstrap + binding resolution.

#### Starter app

- [x] **Replace starter `App\Http\Middleware\Htmx`** with the framework package's `HtmxRequestMiddleware` + `HtmxEventTriggerMiddleware` registered globally. Update `config/middleware.php` accordingly. Verify smoke test still passes.
- [x] **Update starter layout** to use `{{ Htmx::script() }}` instead of the hardcoded `<script src="...htmx.org@2.0.4...">` line. Add `<meta name="csrf-token">` and `<script src="/_htmx/csrf.js">`.
- [ ] **Build the welcome-page guestbook fixture.** New `app/Domain/Guestbook/` module: `GetGuestbookEntries` query + handler + `.html` template with the entry list inside a `<div id="guestbook-list">`, `AddEntry` command + handler with validation attributes, `EntryAdded` event implementing `ClientBroadcast`. SQLite storage via Forge with two `.sql` files. Wire into `Index.html` as a new card. Manually test the round-trip: form submit → 422 with errors → fix → success → list refreshes via `EntryAdded` broadcast. Auto-fragment extraction serves the `<div id="guestbook-list">` region on htmx requests — no `{{ fragment }}` directives needed.
- [x] **Welcome-page upscales: incantation card refresh.** Add a refresh button on the existing incantation card that hits `GET /` with `hx-target="#incantation"`. Wrap the incantation content in `<div id="incantation">…</div>` in `Index.html`. Verify `GetIndexHandler` returns the same data and the framework auto-extracts the element from `HX-Target`.
- [x] **Welcome-page upscales: diagnostic row re-check.** Add per-row re-check buttons. Each row gets a unique id (e.g., `<div id="diag-database">`). Buttons target their own row's id. `GetIndexHandler` unchanged — auto-extraction serves the right element.
- [x] **Welcome-page upscales: CQRS demo lazy-load.** The existing CSS-only tabs become htmx-lazy-loaded. Each tab's content lives inside a `<div id="cqrs-tab-x">` block. Tab buttons get `hx-get="/" hx-target="#cqrs-tab-x" hx-trigger="click once"` so each tab loads on first click and stays cached. Auto-extraction serves each tab independently.

#### Lazy template data

- [ ] **Add lazy closure support to the template variable resolver.** When a handler returns a closure as a template variable value, the framework resolves it selectively *before* rendering — not during. The flow: (1) extract the fragment source for the target element, (2) scan the extracted source for `$variableName` references, (3) walk the handler's return data and invoke closures only for variables found in the scan, (4) leave unreferenced closures untouched, (5) `extract()` and render. PHP sees real values after extract — no proxy objects, no magic methods, no type transparency issues. Handler code: `'items' => fn() => $this->db->model->items(...)->toSeries()->all()`. No htmx awareness, no element-id coupling, fully transport-agnostic. **Tradeoff:** a variable behind an `{{ if }}` that evaluates to false still gets invoked if it appears anywhere in the fragment source text. The main win — skipping closures for data in *other* fragments — is preserved. **Known concerns to design around:** (1) Event timing — if a closure calls a model method that dispatches Echo events, those events fire during the resolve-before-render step, not the handler pass. The `HtmxEventTriggerMiddleware` capture window must still be open. (2) Database in the render path — a closure that throws a database exception surfaces through the rendering error path. Glitch needs to handle this gracefully. (3) Debug-mode unused-variable warnings — `TemplateAnalyzer` flags variables not referenced in the template source. Closures for unreferenced variables are intentionally uninvoked, not "unused data" — they should be exempt from the warning. (4) Full-page renders invoke all closures (no fragment scan to skip against), so the optimization only activates for partial renders.

#### Shodo compiler plugin system and `{{ fragment }}` directive

The auto-fragment extraction defaults to outerHTML (the full element). This works for `hx-swap="outerHTML"` and the positional swap modes (`beforebegin`, `afterend`). But three swap modes need just the inner content without the wrapper element: `innerHTML`, `afterbegin`, `beforeend`. The `{{ fragment 'id' }}` directive is the opt-in for those cases — the developer explicitly marks the inner content boundary.

The problem: `{{ fragment }}` is an htmx concern, not a core Shodo concern. Baking it into the compiler means non-htmx users see a directive in the template language that does nothing for them. The fix is a compiler plugin system.

- [ ] **Add `CompilerDirective` interface to Shodo and migrate all core directives onto it.** The compiler becomes a thin orchestrator: it holds a list of `CompilerDirective` plugins and runs them in priority order, then does the final variable/helper rewriting. Every existing directive — `{{ if }}`, `{{ foreach }}`, `{{ extends }}`, `{{ section }}`, `{{ yield }}`, `{{ include }}`, `{{ match }}`, `{{ csrf }}`, etc. — becomes a `CompilerDirective` class shipped by Shodo and registered by default. The htmx package adds `{{ fragment }}` via the same mechanism. Third-party packages can add their own. One code path for everything — no "built-in" vs "plugin" distinction. Minimal interface: `directives(): array` (keyword names claimed), `priority(): int` (execution order — includes before sections before layout, etc.), and `process(string $source, CompilerContext $ctx): string`. Unknown `{{ lowercase_keyword }}` directives not claimed by any plugin produce a clear error. Co-dependent directives (e.g. `{{ section }}` + `{{ yield }}` + `{{ extends }}`) live in a single `LayoutDirective` class.
- [ ] **Add `FragmentDirective` as a compiler plugin in the Htmx package.** In the full-render path, strips `{{ fragment 'name' }}` and `{{ endfragment }}` markers (transparent). In the element-extraction path (`renderElementById`), checks for a `{{ fragment 'id' }}` matching the target id *before* falling back to HTML id extraction — if found, returns the fragment content (no wrapper element). This gives developers an explicit innerHTML opt-in: `<div id="main">{{ fragment 'main' }}<p>content</p>{{ endfragment }}</div>`.
- [ ] **Register `FragmentDirective` in `Bootstrap\Htmx`.** Without the htmx package, the directive doesn't exist in the compiler. The compiler stays clean of htmx-specific knowledge.
- [ ] **Update `HtmlFormatter::renderElementById` to check fragments first.** When a `{{ fragment 'id' }}` exists for the target id, return the fragment content (inner only). When no fragment exists, fall back to the current HTML id extraction (outerHTML). This makes outerHTML the zero-ceremony default and `{{ fragment }}` the explicit escape hatch for innerHTML/afterbegin/beforeend swap modes.

#### Documentation

- [ ] **Write `src/Htmx/README.md`.** End-to-end package reference: the three request modes, `HtmxRequest` accessors, auto-fragment extraction (how ids become fragments, outerHTML vs innerHTML handling, the fall-through behavior), `ClientBroadcast` events, CSRF integration, auth-redirect handling, dynamic ids and the CQRS decomposition pattern, the quirks list (especially `from:body` and `Vary` notes), pointers to `four.htmx.org` for the `hx-*` attribute reference itself. Aim 400-600 lines, comprehensive enough that an app developer never needs another htmx tutorial.
- [ ] **Update COMPENDIUM.** Replace the existing "Front-end defaults" htmx paragraph with one that names the package, the request/response builders, the event projection bridge, and the v4 pin. Add a new package entry alongside Testing/etc. (count 22 → 23).
- [ ] **Update starter README.** Walk through the welcome-page guestbook feature and the upscales. Document the htmx pin config and how to bump it. Replace the existing "Front-End: Tailwind CSS + htmx" section.

#### Cross-cutting

- [ ] **Run `composer check` after each commit.** Pre-commit hook enforces.
- [ ] **Smoke-test the starter app end-to-end** after the package lands. Boot via `php -S`, click through the welcome page, exercise the guestbook (add entry, validation failure, success), refresh each upscaled card, verify all htmx interactions return the right fragments and the cross-component refresh fires.
- [ ] **Final sweep.** Grep for `HX-` references across `framework/` and `arcanum/` to confirm only the framework package handles htmx headers; no leftover ad-hoc header reads in handlers or templates. Confirm the starter app's `App\Http\Middleware\Htmx` is gone.

### Welcome page — nice-to-haves (deferred)

The Index redesign landed (nine-section structure, real diagnostics, CSS-only tabs, copy buttons, ASCII rune). The leftovers are explicitly optional:

- [ ] **Diagnostic rows link to configuration docs** — every non-green row in the welcome page Application column (and any Environment row that's red) should link out to the relevant Arcanum configuration doc when clicked. A yellow "Session driver — not configured (optional — required for CSRF)" line should link to the session config guide; a red "Cache driver — config broken" line should link to the cache config guide; "Database — not configured" links to database setup; etc. Cheap UX win once the docs site exists. Blocked on the documentation site itself — defer until real docs URLs exist (same blocker as the placeholder URL cleanup below).
- [ ] **Syntax highlighting in code blocks** — the welcome page's incantation card and CQRS demo tabs render plain monochrome `<pre><code>` blocks. Adding color hinting (PHP keywords, strings, attributes) would meaningfully improve the first impression. Low priority. Look for a small client-side library — Prism.js, highlight.js, or shiki — that loads from CDN with a single script tag and a stylesheet, no build step required. Constraints: must respect dark mode (the page already toggles `.dark` on `<html>`), must not be a heavy dependency (the welcome page is the only consumer for now), and must not require running a Node toolchain to use. This isn't welcome-page-only — Shodo's documentation, README code blocks, and any future docs page would all benefit. When picking a library, prefer one that handles PHP, HTML, SQL, and shell well since those are the four languages the framework's docs use most.
- [ ] **`?debug=1` bootstrap visualization** — replaces welcome banner with bootstrap order list when the query param is set. Easter egg.
- [ ] **Placeholder URL cleanup** — replace `https://example.com/{docs,tutorial,api,discussions}` references in the Index page with real URLs once real docs / tutorial / community channels exist. GitHub repo links are already real.

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

