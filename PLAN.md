# Framework Completion Plan

> **COMPENDIUM.md must stay in sync.** Any time framework functionality changes, is added, or is removed — new packages, renamed classes, dropped features, new CLI commands, new attributes, new built-in marks, anything a user-facing tour would mention — update `COMPENDIUM.md` in the same change. It's the source of truth for the eventual documentation site, and an out-of-date entry there is worse than no entry. Treat it like committing: not a follow-up, part of done.

---

## On Deck

Ordered by dependency — foundations first, then the features that build on them.

1. **Dogfood: critical bug fixes** — details in checklist below.
2. **Dogfood: routing & handler resolution** — details in checklist below.
3. **Dogfood: auth config reform** — details in checklist below.
4. **Dogfood: bootstrap self-wiring** — details in checklist below.
5. **Dogfood: investigation fixes** — details in checklist below. (Handler naming table blocked on #2.)
6. **Dogfood: CommandResult & flash messages** — details in checklist below. (Reverse routing blocked on #2.)
7. **Dogfood: auth exceptions & redirect** — details in checklist below.
8. **Dogfood: unify CLI dispatch & colon routing** — details in checklist below.
9. **PSR-18 HTTP Client** — details in checklist below.
10. **Dogfood: starter app update** — details in checklist below. (Needs 1–8.)
11. **Dogfood: documentation** — details in checklist below. (Needs code changes from 1–8.)
12. **Integration test coverage** — details in checklist below. (Better after 1–8.)

---

### Dogfood: critical bug fixes

Bugs surfaced by the Todo App dogfood that need fixes before anyone else uses the framework. Each item was hit by one or both builds and is documented in `RETROSPECTIVE.md`.

#### `make:key` chicken-and-egg (retro 1.1)

Bootstrap requires `APP_KEY` to run (`Bootstrap\Security` throws if missing), but `make:key` is a CLI command that runs through the full bootstrap chain. New users can't generate their key with the tool designed to generate it.

**Fix:** Configurable per-command bootstrap lists. `RuneKernel` always runs `Environment` + `Configuration` first (the minimum needed to read config and parse argv). It then checks `config/bootstrap.php` for a custom bootstrapper list for the current CLI command. If found, run those bootstrappers (skipping Environment + Configuration since they already ran). If absent, run the full default bootstrap list. The framework ships built-in defaults for `make:key`, `list`, and `help` — app developers can override or add entries for their own commands.

The config key is `cli` (not `commands`) to avoid confusion with CQRS Commands in the Conveyor sense.

##### Checklist

- [x] **`config/bootstrap.php` support in RuneKernel** — After running `Environment` + `Configuration`, parse argv to resolve the command name. Check `config/bootstrap.php` under the `cli` key for a matching entry. If found, run only those bootstrappers (deduplicating Environment + Configuration). If absent, run the full default `$bootstrappers` list. Framework ships internal defaults: `make:key`, `list`, and `help` map to empty arrays (Environment + Configuration only). App config merges over framework defaults.
- [x] **Update `MakeKeyCommand` and built-in commands** — Verify `make:key`, `list`, and `help` work with the minimal bootstrap (no Security, no Database, no Auth). Adjust if any have hidden dependencies.
- [x] **Test: `make:key` with no APP_KEY** — Integration test: empty `.env` (no APP_KEY), run `make:key` via RuneKernel, assert it produces a valid key without throwing. Test `--write` flag writes to `.env`. Test that a normal app command still gets the full bootstrap.
- [ ] **Starter app: add `config/bootstrap.php`** — Ship the config file in the starter with the framework defaults commented out as documentation, paralleling how other config files show available options. *(Starter app repo — deferred to starter app update stream.)*

#### Parsed body for non-POST methods (retro 1.3)

`Server::request()` populates `getParsedBody()` from `$_POST`, which PHP only fills for POST requests. PUT, PATCH, and DELETE requests with `application/x-www-form-urlencoded` bodies silently lose their data. The framework's routing table documents these methods and their handler naming conventions, but they don't work with form data. Both builds had to use `hx-post` for everything.

**Fix:** In `Server::request()`, for non-GET/non-POST methods, check the `Content-Type` header. If it contains `application/x-www-form-urlencoded`, read the body stream, parse with `parse_str()`, and rewind the stream. For non-POST methods with no recognized content type, pass `null` (PSR-7: "A null value indicates the absence of body content"). POST continues using `$_POST` as required by PSR-7.

PSR-7 compliance verified: the spec says for POST + form content types the method "MUST return the contents of `$_POST`" (unchanged). For all other cases, it "may return any results of deserializing the request body content" (our fix). Parsed results "MUST be arrays or objects only" (`parse_str()` returns an array).

##### Checklist

- [x] **`Server::request()` — parse body for non-POST methods** — After building the headers and body stream, check: if method is not GET and not POST, and `Content-Type` contains `application/x-www-form-urlencoded`, call `parse_str((string) $body, $parsedBody)` and `$body->rewind()`. Use `$parsedBody` in `withParsedBody()`. For non-POST methods with no recognized content type, pass `null`. POST continues using `$_POST`. Verify `CachingStream` supports `rewind()` after `__toString()`.
- [x] **Tests: parsed body for PUT/PATCH/DELETE** — Unit tests on `Server::request()` covering: PUT with url-encoded body parses correctly, PATCH with url-encoded body parses correctly, DELETE with url-encoded body parses correctly, POST still uses `$_POST`, GET returns null parsed body, non-POST with `application/json` content type returns null (not parsed — stream is available for manual reading), non-POST with no content type returns null.

#### Missing `symfony/filesystem` dependency (retro 1.4)

Parchment uses `Symfony\Component\Filesystem\Filesystem` in `Reader`, `Writer`, `TempFile`, and `FileSystem`, but `composer.json` only requires `symfony/finder` (a different package). Works in dev via transitive dependencies; fails on a clean `composer require arcanum-org/framework`.

##### Checklist

- [x] **Add `symfony/filesystem` to `composer.json` `require`** — Add `"symfony/filesystem": "^6.3 || ^7.0"` (matching the existing `symfony/finder` version constraint). Run `composer update` to verify resolution. No code changes needed.

#### `ExceptionHandler` not registered (retro 1.8)

Every CLI command prints `Service 'Arcanum\Glitch\ExceptionHandler' is not registered` to stderr on shutdown. The starter app registers `App\Error\Handler`, but from-scratch users get noise. Worse: `Lifecycle::report()` silently swallows non-fatal exceptions (listener failures, post-processing errors) when no handler is registered — no logging, no reporting.

Two problems in `Bootstrap\Exceptions`: (1) the three `handle*()` methods use `$container->get()` and check for `null`, but `get()` throws on missing services, never returns `null` — should use `has()` before `get()`. (2) `handleShutdown()` has no try-catch, unlike the other two handlers.

**Fix:** Two changes. First, fix the `has()` guards in all three `handle*()` methods and wrap `handleShutdown()` in try-catch (defensive). Second, register `Glitch\Handler` as the default `ExceptionHandler`, `ErrorHandler`, and `ShutdownHandler` in `Bootstrap\Exceptions` with `has()` guards — same pattern `Bootstrap\Logger` uses for `QuillLogger`. From-scratch users get real error handling out of the box. Apps override by registering their own implementation before or after bootstrap — Cabinet silently overrides on re-registration, and the `has()` guard respects pre-existing registrations.

##### Checklist

- [x] **Fix `has()` guards in `Bootstrap\Exceptions`** — Replace bare `$container->get()` calls with `$container->has()` + `$container->get()` in `handleError()`, `handleException()`, and `handleShutdown()`. Wrap `handleShutdown()` body in try-catch — shutdown handlers must never throw. Match the pattern `Lifecycle::report()` already uses correctly.
- [x] **Register `Glitch\Handler` as default** — In `Bootstrap\Exceptions::bootstrap()`, register `Glitch\Handler` as the default implementation for `ExceptionHandler`, `ErrorHandler`, and `ShutdownHandler` with `has()` guards. Apps can override before bootstrap (guard skips) or after (Cabinet re-registration overwrites). Tests: from-scratch container with no app handler → `Glitch\Handler` is resolved; container with pre-registered app handler → app handler wins; container with post-registered app handler → app handler wins.

#### `migrate:create` timestamp collision (retro 1.2)

Migrations use second-resolution timestamps (`YmdHis`, 14 digits) as version identifiers. Two migrations created in the same second get the same version, causing a unique constraint violation on `arcanum_migrations.version`. Build A hit this creating `task_lists` and `tasks` back-to-back.

**Fix:** Switch to millisecond precision. `DateTime::format('YmdHisv')` produces 17-digit timestamps (e.g., `20260413030511123`). Framework is unreleased, so no migration path needed — change the format before anyone depends on it.

##### Checklist

- [x] **Millisecond timestamps in `MigrateCreateCommand`** — Replace `date('YmdHis')` with `(new \DateTimeImmutable())->format('YmdHisv')` for 17-digit millisecond-resolution versions. Update the output message.
- [x] **Update `MigrationParser` regex** — Change `FILENAME_PATTERN` from `\d{14}` to `\d{17}`.
- [x] **Update `MigrationRepository` schema** — Change `VARCHAR(14)` to `VARCHAR(17)` in MySQL and PostgreSQL table creation SQL. SQLite uses `TEXT`, no change needed.
- [x] **Update tests** — Adjust all migration tests that assert on filename format or version length.

#### Move migrations to `database/migrations/` (retro 8.3, orchestrator observation)

Migrations currently live at `migrations/` in the project root. Both builds expected `database/migrations/` — a more natural home alongside the future `database/seeders/` directory. The path is hardcoded in four CLI commands: `MigrateCommand`, `MigrateCreateCommand`, `MigrateRollbackCommand`, `MigrateStatusCommand`.

**Fix:** Default to `database/migrations/` but make it configurable via `config/database.php` (e.g., `'migrations_path' => 'database/migrations'`). The four CLI commands read the config instead of using a hardcoded path.

##### Checklist

- [x] **Configurable migrations path** — Add a `migrations_path` key to `config/database.php`, defaulting to `database/migrations`. Update `MigrateCommand`, `MigrateCreateCommand`, `MigrateRollbackCommand`, and `MigrateStatusCommand` to read the path from config instead of hardcoding it. `MigrateCreateCommand` should create the directory if it doesn't exist. Update output messages to reflect the configured path.
- [x] **Update tests** — No tests referenced the hardcoded `migrations/` path directly; migration commands use constructor injection.
- [ ] **Update starter app** — Move `migrations/` to `database/migrations/`. Add the `migrations_path` key to `config/database.php` (commented out, showing the default). *(Starter app repo — deferred to starter app update stream.)*
- [ ] **Update COMPENDIUM.md and Forge README** — Reflect the new default `database/migrations/` path and the config option in all documentation.

### Dogfood: routing & handler resolution (retro 1.7, 1.5)

Two bugs in the routing and handler resolution pipeline, both surfaced by the dogfood.

#### Convention routing path doubling (retro 1.7) — resolved as working-as-designed

When a domain name matches the DTO class name, convention routing produces "doubled" paths: `App\Domain\TaskLists\Query\TaskLists` → `/task-lists/task-lists`. Both builds found this surprising and fell back to custom routes.

**Decision:** This is correct behavior, not a bug. The forward resolver maps `/task-lists` to root-level `App\Domain\Query\TaskLists` and `/task-lists/task-lists` to domain-level `App\Domain\TaskLists\Query\TaskLists`. Collapsing the reverse direction breaks the round-trip (the generated URL would route to a different class). Devs wanting clean URLs like `/task-lists` use a custom route in `config/routes.php` — a one-liner. Document this clearly rather than adding magic path collapsing.

#### Handler resolution misreports dependency failures (retro 1.5, 1.6)

`MiddlewareBus::handlerFor()` has two problems:

1. **Line 95: `has()` for prefixed handlers.** Uses `Container::has()` to check for prefixed handlers (e.g., `PostTaskListsHandler`). `has()` only checks explicit providers — returns false for classes that exist and would auto-wire fine via Codex. This breaks the prefixed handler pattern for custom routes. Changing `has()` globally is wrong — most call sites intentionally mean "explicitly registered." Fix: use `class_exists()` instead of `has()` at this specific call site.

2. **Line 110: `catch (\Throwable)` is too broad.** The unprefixed handler fallback catches `\Throwable` and wraps it as `HandlerNotFound`. When the handler class exists but a dependency fails to resolve (e.g., Codex throws `UnresolvableClass` because `Identity` can't be satisfied), the catch misreports it as "Handler Not Found" — a misleading 500 instead of the real error. Cabinet throws `ServiceNotFound` when a class truly doesn't exist; Codex throws `Unresolvable` when a dependency can't be satisfied. The catch should only catch `ServiceNotFound`.

##### Checklist

- [x] ~~**Fix `UrlResolver::resolveConvention()` path doubling**~~ — Resolved as working-as-designed. The doubled path is the correct convention URL; collapsing breaks the forward/reverse round-trip. Added a test confirming the behavior. Documentation items below cover communicating this to devs.
- [x] **Fix `handlerFor()` prefixed lookup** — Replace `$this->container->has($prefixedName)` with `class_exists($prefixedName)` at the prefixed handler check. This correctly discovers handlers that exist and autoload, regardless of explicit container registration. Test: prefixed handler class exists but is not registered → found and auto-wired. Prefixed handler class does not exist → falls through to unprefixed.
- [x] **Narrow `handlerFor()` catch to `ServiceNotFound`** — Replace `catch (\Throwable $e)` with `catch (ServiceNotFound $e)` at the unprefixed handler fallback. Dependency resolution failures (`Unresolvable` from Codex, `AuthenticationException` from the auth work) propagate to the kernel as-is, producing accurate error responses instead of misleading "Handler Not Found" errors.
- [x] **Tests** — UrlResolver: added test asserting non-collapsed behavior (working-as-designed). MiddlewareBus: added tests for prefixed handler auto-wiring and dependency failure propagation.
- [x] **Document root path (`/`) convention limitation** — Atlas convention routing can't produce an empty path — `GET /` always needs a custom route. Both dogfood builds hit this. Document in the Atlas README and the from-scratch guide that `'/' => DtoClass::class` in `config/routes.php` is required.
- [x] **Update COMPENDIUM.md** — Document the convention routing behavior (domain = class name produces doubled path, use custom routes for clean URLs) and the root path limitation in the routing conventions section.

### Dogfood: auth config reform (retro 4.1, orchestrator observation)

`config/auth.php` contains closures with actual application logic — database queries, password verification, identity construction. Configuration should be declarative (strings, scalars, arrays); the system that consumes the config should do the work. The closures also can't access the container, which forced both dogfood builds to drop to raw PDO instead of using Forge models for identity resolution.

**Fix:** Replace the three resolver closures (`identity`, `token`, `credentials`) with a single `IdentityProvider` interface that the framework defines and the app implements as a container-resolved class. Config becomes a class-string reference. `Bootstrap\Auth` resolves it from the container, so the provider can inject Forge models, database connections, or any other service.

```
// config/auth.php (after)
'provider' => App\Auth\UserProvider::class,

// Framework interface
interface IdentityProvider {
    findById(string $id): ?Identity
    findByToken(string $token): ?Identity
    findByCredentials(string ...$credentials): ?Identity
}
```

Guards receive the `IdentityProvider` instead of raw `Closure` arguments. `SessionGuard` calls `findById()`, `TokenGuard` calls `findByToken()`, `CliAuthResolver` calls `findByCredentials()`.

##### Checklist

- [x] **`IdentityProvider` interface** — Define in `src/Auth/`. Three methods: `findById(string $id): ?Identity`, `findByToken(string $token): ?Identity`, `findByCredentials(string ...$credentials): ?Identity`. Document the contract: each method returns `null` for "not found / invalid," never throws for normal lookup failures.
- [x] **Update guards to accept `IdentityProvider`** — `SessionGuard` constructor takes `IdentityProvider` instead of `Closure`, calls `findById()`. `TokenGuard` takes `IdentityProvider`, calls `findByToken()`. `CliAuthResolver` takes `IdentityProvider`, calls `findByCredentials()`. Update all guard tests.
- [x] **Update `Bootstrap\Auth`** — Read `provider` key from `config/auth.php` as a class-string. Resolve from the container (Codex auto-wires dependencies). Pass the resolved `IdentityProvider` to guard constructors. Throw a clear error if the provider class doesn't exist or doesn't implement the interface.
- [ ] **Update `config/auth.php` format** — Remove the `resolvers` key and its closures. Add `provider` key (class-string). Keep `guard`, `login.fields`, and `login.ttl` as-is (already scalars). Update the starter app's config file.
- ~~**`make:provider` CLI command**~~ — Skipped. Apps only need one provider; the Auth README shows a complete example.
- [x] **Update Auth README** — Document the new `IdentityProvider` interface, the config format change, and show a complete example provider using Forge models. Remove any references to resolver closures.

### Dogfood: bootstrap self-wiring (retro 3.1, 3.2, 3.4)

Build B hit 12 errors before a single page rendered. The gap between "clone the starter" and "start from scratch" is a cliff because the starter's `bootstrap/http.php` and `bootstrap/cli.php` files contain ~170 lines of infrastructure wiring that every app needs — container self-registration, kernel binding, bus registration, server adapter, event dispatcher, exception renderers. Most of this is framework infrastructure with no app-specific variation.

**Goal:** A minimal from-scratch bootstrap file should be:

```
$container = new Container();  // self-registers under Application + ContainerInterface
$container->service(Kernel::class, MyKernel::class);
$container->specify(when: MyKernel::class, needs: '$rootDirectory', give: __DIR__ . '/..');
$container->specify(when: MyKernel::class, needs: '$configDirectory', give: __DIR__ . '/../config');
$container->specify(when: MyKernel::class, needs: '$filesDirectory', give: __DIR__ . '/../files');
return $container;
```

Everything else is framework defaults with `has()` guards — apps override what they need. The starter's bootstrap files shrink to kernel binding, primitive specs, and genuinely app-specific wiring (listeners, helpers, exception renderer preferences).

**Construction order:** The container is created first, then the kernel binding is registered, then `public/index.php` resolves the kernel via `$container->get(Kernel::class)`, then `$kernel->bootstrap($container)` runs the bootstrapper chain. Container self-registration must happen at construction time. Kernel binding must happen in the bootstrap file (only the app knows its concrete kernel class). Everything else can happen in `bootstrap()` or in bootstrappers.

#### Checklist

- [x] **Container self-registration** — In `Container::__construct()`, register the container instance under both `Arcanum\Cabinet\Application` and `Psr\Container\ContainerInterface`. Every app needs this, no variation, no reason for it to be manual. Tests: `$container->get(Application::class)` and `$container->get(ContainerInterface::class)` both return the container instance immediately after construction.
- [ ] **Kernel pre-bootstrap wiring in `bootstrap()`** — At the top of `Kernel::bootstrap()` (the shared base, before the bootstrapper loop), register framework infrastructure with `has()` guards so apps can override before or after. Registrations:
  - `Bus::class` → `MiddlewareBus::class` (both kernels). Read `$debug` from the `app.debug` config key (available after `Configuration` bootstrapper) or specify it as a deferred factory.
  - `EventDispatcherInterface` → Echo `Dispatcher` with a new `Provider` (both kernels). Currently only wired in the starter's HTTP bootstrap; CLI needs it too for lifecycle events and the planned `FlashMessage` events.
- [ ] **HyperKernel-specific wiring in `bootstrap()`** — After the shared kernel wiring, `HyperKernel::bootstrap()` registers HTTP-specific services with `has()` guards:
  - `ServerAdapter::class` → `PHPServerAdapter::class`
  - `EmptyResponseRenderer::class` (used by the kernel for command 204 responses)
  - `Server::class` with `PHPServerAdapter` via `serviceWith()` or equivalent
- [ ] **Slim down starter `bootstrap/http.php`** — Remove container self-registration (now in constructor), Bus registration, ServerAdapter, EmptyResponseRenderer, EventDispatcher (all now framework defaults). What remains: kernel binding + primitive specs, exception renderer choice + debug flags, app-specific event listeners, app-specific helpers. Should shrink from ~170 lines to ~60.
- [ ] **Slim down starter `bootstrap/cli.php`** — Remove container self-registration, Bus registration (all now framework defaults). What remains: kernel binding + primitive specs, exception renderer. Should shrink from ~88 lines to ~30.
- [ ] **Test: from-scratch bootstrap** — Integration test: create a minimal bootstrap file (container + kernel binding + specs only), verify the kernel bootstraps without errors, verify `Bus`, `ServerAdapter`, `EventDispatcherInterface`, `ContainerInterface` are all resolvable from the container. This is the regression test for "12 errors before a page renders."
- [ ] **Update COMPENDIUM.md** — Document what the framework auto-registers (container, bus, server adapter, event dispatcher, exception handlers) vs. what the app must provide (kernel binding, directory specs, exception renderer preferences). Update the "shape of an Arcanum app" section.

### Dogfood: investigation fixes (retro 2.1, 2.2, 2.3, 2.5)

Items from the retrospective's "possible bugs / inconclusive" section, now investigated and resolved.

#### CSRF JS helper broken on htmx v4 (retro 2.1)

Confirmed bug — two breaking changes between htmx v2 and v4:
1. Event name renamed: `htmx:configRequest` → `htmx:config:request`. The listener never fires.
2. Property paths moved: `event.detail.verb` → `event.detail.ctx.request.method`, `event.detail.headers` → `event.detail.ctx.request.headers`.

The framework targets htmx v4 directly. No v1/v2 backward compatibility needed.

##### Checklist

- [ ] **Update `HtmxCsrfController` JS** — Change event listener from `htmx:configRequest` to `htmx:config:request`. Change method access from `event.detail.verb || event.detail.method` to `event.detail.ctx.request.method`. Change header assignment from `event.detail.headers[...]` to `event.detail.ctx.request.headers[...]`. Verify the `meta[name="csrf-token"]` selector still works with the htmx v4 shim.
- [ ] **Test CSRF JS in browser** — Manual verification: standalone `hx-post` button (not inside a `<form>`) with the CSRF JS helper should attach `X-CSRF-TOKEN` header automatically. No 403 CSRF mismatch.
- [ ] **Update Htmx README** — Document the htmx v4 event names if any references to v2 names remain.

#### `{{ @csrf }}` vs `{{ csrf }}` (retro 2.2)

Non-issue. No `@csrf` references exist in the starter app or framework. The directive is `{{ csrf }}`. Build A's developer brought Blade syntax muscle memory. If any stray `@csrf` references exist in docs, fix them.

##### Checklist

- [ ] **Grep for `@csrf` in all docs and READMEs** — Fix any stray references to `{{ @csrf }}` → `{{ csrf }}`. Includes starter README, framework READMEs, and COMPENDIUM.

#### `env()` helper (retro 2.3, 5.3)

The Forge README uses `env('DB_HOST', '127.0.0.1')` in config examples, but the function doesn't exist. Both builds hit this. Config files genuinely need environment variable access.

##### Checklist

- [ ] **Implement `env()` in Toolkit** — Global function `env(string $key, mixed $default = null): mixed`. Reads from `$_ENV`, falls back to `$default`. Lives in a file autoloaded via Composer's `files` autoload (e.g., `src/Toolkit/helpers.php`). Keep it simple — no casting, no type coercion. If casting is needed later, add named variants (`env_bool`, `env_int`) rather than magic.
- [ ] **Tests for `env()`** — Test: key exists → returns value. Key missing → returns default. Key missing + no default → returns null.
- [ ] **Update Toolkit README** — Document `env()` alongside the existing utility functions.

#### Handler naming table (retro 2.5)

The starter README's command routing table shows `DELETE → DeleteSubmitHandler`, but in practice the unprefixed `DeleteHandler` works because `handlerFor()` falls through to it (the prefixed lookup fails due to the `has()` bug fixed in the routing stream). Once the `handlerFor()` fix lands, the prefixed convention will work. The table is confusing — it shows handler suffixes without explaining they're prefixed onto the DTO name.

##### Checklist

- [ ] **Clarify handler naming table in starter README** — After the `handlerFor()` `class_exists()` fix lands, rewrite the table to show full class names relative to a concrete DTO example. Make clear that the unprefixed `{DtoName}Handler` is the default and the prefixed variants (`Post{DtoName}Handler`, `Delete{DtoName}Handler`, etc.) are optional overrides for per-method dispatch on the same DTO.

### Dogfood: CommandResult & flash messages (retro 5.1, 5.2, 4.3, 4.4, 9.3)

The single biggest DX friction from the dogfood. Command handlers today return `void` (204), a DTO (201), or `null` (202) — no redirects, no custom status codes, no way to say "success, now go to `/dashboard`." Both builds fought this: Build A used htmx client-side redirects (requires JS, no fallback), Build B returned raw `ResponseInterface` objects with 5+ imports and kernel-level special-case logic. Neither felt like the intended pattern because there wasn't one.

**The deeper problem:** Arcanum has no controllers — the handler IS the endpoint. Unlike MediatR/.NET where a controller interprets command results into HTTP, Arcanum's rendering pipeline must bridge handler returns to transport-specific responses. The framework was already doing this implicitly (void→204, DTO→201); it just didn't go far enough.

**Design principles (informed by Wolverine, Ecotone, and the CQRS consensus):**

- **Handlers express transport-agnostic intent; kernels interpret it.** A handler says "redirect here" or "resource created at this URL." The HTTP kernel produces a 303 or 201+Location. The CLI kernel prints a message or ignores it. The handler never touches PSR-7.
- **Metadata only, no payloads.** `CommandResult` carries location URLs and status intent, never business data. That's the query side's job. Commands tell you *where* to find the result, not *what* the result is.
- **Flash messages are side effects, not outcomes.** They flow through Echo events, not return values. A `FlashMessageListener` stores them in the session (HTTP) or prints them (CLI). This composes with command chaining — inner commands dispatch flashes too, all collected by the same listener.
- **One canonical return type.** Middleware normalizes all command returns into `CommandResult`. Existing `void`/`null`/DTO conventions are upscaled, not replaced — no existing handlers break.

**Normalization table:**

| Handler returns | Normalized to | HTTP | CLI |
|---|---|---|---|
| `void` | `CommandResult::empty()` | 204 No Content | exit 0, no output |
| `null` | `CommandResult::accepted()` | 202 Accepted | exit 0, "Accepted" |
| Query DTO | `CommandResult::created(reverse_route($dto))` | 201 + Location header | exit 0, path printed |
| `CommandResult` | pass through | per factory method | per factory method |

**Reverse routing for DTO returns:** When a command handler returns a Query DTO, the middleware reverse-routes the DTO class through Atlas to produce the Location URL. Parameterized routes (e.g., `GetOrder` with `$id`) extract constructor values from the DTO instance to fill URL segments. Atlas's `UrlResolver` gains a `reverseRoute(object $dto): string` method for this.

**Command chaining:** When a handler dispatches an inner command through the bus, the inner `CommandResult` is returned to the calling handler — not to the transport. Only the outermost handler's return reaches the kernel. Flash messages from inner commands fire as events regardless of nesting depth.

#### Checklist

- [ ] **`CommandResult` value object** — Define in `Flow\Conveyor`. Immutable, four static factory methods: `empty()`, `accepted()`, `created(string $location)`, `redirect(string $location, StatusCode $status = StatusCode::SeeOther)`. No payload, no business data. `location()` accessor returns the URL or null. `status()` accessor returns the intended status code. Mirrors `QueryResult` in naming.
- [ ] **`MiddlewareBus` normalization** — After the handler returns, normalize the result into `CommandResult`. `void` → `empty()`, `null` → `accepted()`, DTO instance → `created(reverse_route($dto))`, `CommandResult` → pass through. Remove the existing ad-hoc return type interpretation (EmptyDTO 204, AcceptedDTO 202, etc.) and consolidate into `CommandResult`. Update `EmptyDTO` and `AcceptedDTO` references if they exist as special types.
- [ ] **Atlas reverse routing** — Add `UrlResolver::reverseRoute(object $dto): string` (or similar). Given a DTO instance, resolve its class to a URL path via the convention router, extracting constructor parameter values to fill parameterized segments. Throw a clear error if the DTO class can't be reverse-routed (no convention match and no custom route). Tests for simple paths, parameterized paths, and custom routes.
- [ ] **HyperKernel interpretation** — When the command pipeline returns a `CommandResult`, render the appropriate HTTP response: `empty()` → 204 empty body, `accepted()` → 202 empty body, `created($url)` → 201 + `Location` header + empty body, `redirect($url)` → 303 (or specified status) + `Location` header + empty body. No template rendering — these are metadata responses.
- [ ] **RuneKernel interpretation** — When the command pipeline returns a `CommandResult`, produce CLI-appropriate output: `empty()` → exit 0 + no output, `accepted()` → exit 0 + "Accepted" message, `created($url)` → exit 0 + "Created: $url" message, `redirect($url)` → exit 0 + no output (redirect is meaningless in CLI). The URL is informational, not actionable.
- [ ] **`FlashMessage` event** — Define in `Session` package. Constructor: `string $level` (`success`, `error`, `warning`, `info`), `string $message`. Immutable value object implementing a marker interface or extending a base event class compatible with Echo.
- [ ] **`FlashMessageListener` (HTTP)** — Listens for `FlashMessage` events and stores them in the session under a known key. On the next request, the messages are available and cleared after reading. Lives in `Session` package.
- [ ] **`FlashHelper` template helper** — `Flash::messages()` returns pending flash messages for the current request (reads and clears from session). `Flash::has()` checks if any exist. Register in `Bootstrap\Helpers` or via `HelperDiscovery`. Templates render flashes in the layout.
- [ ] **`CliFlashListener`** — Listens for `FlashMessage` events and writes them to stdout immediately. Lives in `Rune` package.
- [ ] **`StringStream` convenience** — Add `Stream::fromString(string $content): Stream` factory method on `Flow\River\Stream`. Eliminates the `LazyResource::for('php://temp')` + write + rewind ceremony. Used internally by response building and available to app developers. Addresses retro 5.2.
- [ ] **Tests** — Unit tests for `CommandResult` (factory methods, accessors). Integration tests for the full normalization pipeline: void handler → 204, null handler → 202, DTO-returning handler → 201 + Location, `CommandResult::redirect()` → 303. Flash message round-trip: dispatch event → session storage → template helper → cleared. CLI equivalents.
- [ ] **Update COMPENDIUM.md** — Document `CommandResult` as the canonical command return type, the normalization table, flash messages via Echo events, and the transport interpretation. Update the "shape of an Arcanum app" section to show the `CommandResult` flow.

### Dogfood: auth exceptions & redirect (retro 1.6, 5.4, 3.3)

`#[RequiresAuth]` on a DTO produces a 500 instead of a 401 when no user is authenticated. The root cause: `ActiveIdentity::get()` throws a bare `RuntimeException`, and there may be code paths where handler construction (which resolves `Identity` from the container) precedes `AuthorizationGuard`. Even when the guard does run first, it throws `HttpException(401)` — an HTTP concept that doesn't make sense when the same guard runs in a CLI context through Rune.

Additionally, auth redirects only work for htmx requests (`HtmxAuthRedirectMiddleware`). Regular browser GETs to protected endpoints produce an error page instead of a redirect to login. Both builds had to implement their own redirect logic at the kernel level.

**Fix:** Transport-agnostic auth exceptions in the `Auth` package, interpreted per-transport by each kernel. A framework-level `AuthRedirectMiddleware` for HTTP browser requests, with `HtmxAuthRedirectMiddleware` layering htmx-specific behavior on top.

#### Checklist

- [ ] **`AuthenticationException` and `AuthorizationException`** — Define in `Auth` package. Both extend `ArcanumException` (carry `getTitle()` and `getSuggestion()`). `AuthenticationException`: "Authentication required" / "Log in to continue." `AuthorizationException`: "Insufficient permissions" / suggestion varies by context (role name, policy class). These are transport-agnostic — no HTTP status codes, no CLI exit codes.
- [ ] **`ActiveIdentity::get()` throws `AuthenticationException`** — Replace the bare `RuntimeException` with `AuthenticationException`. This is the safety net: if any code path resolves `Identity` from the container without an authenticated user, it produces a meaningful auth error instead of a generic 500.
- [ ] **`AuthorizationGuard` throws auth exceptions** — Replace `HttpException(401)` with `AuthenticationException` for `#[RequiresAuth]` failures. Replace `HttpException(403)` with `AuthorizationException` for `#[RequiresRole]` and `#[RequiresPolicy]` failures. The guard is a Conveyor middleware shared by both transports — it must not depend on HTTP concepts.
- [ ] **HyperKernel exception mapping** — Map `AuthenticationException` → 401 and `AuthorizationException` → 403 in the HTTP exception rendering pipeline. Ensure error templates (`errors/401.html`, `errors/403.html`) work through the standard Shodo resolution chain.
- [ ] **RuneKernel exception mapping** — Map `AuthenticationException` → "You must be logged in. Run `login` to authenticate." + exit 1. Map `AuthorizationException` → "Insufficient permissions." + exit 1. The CLI exception writer produces a helpful message, not an HTTP status code.
- [ ] **`AuthRedirectMiddleware` (HTTP, framework-level)** — PSR-15 middleware that catches `AuthenticationException` and `AuthorizationException` during the request lifecycle. For browser requests (non-API, non-htmx): redirect to the login URL with a 302. Login URL read from `config/auth.php` under an `auth.redirect` key (defaulting to `/login`). For API requests (Accept: application/json): let the exception propagate to produce a JSON 401/403 response. Register in `Bootstrap\Auth` for HTTP kernels.
- [ ] **Refactor `HtmxAuthRedirectMiddleware`** — Layer htmx-specific behavior on top of `AuthRedirectMiddleware`. For htmx requests: respond with `HX-Location` header instead of a 302 redirect. Can be a decorator or subclass of the general middleware.
- [ ] **Generalize DTO-specific exception templates** — The status-specific template resolution chain (`{Dto}.{status}.{format}`) currently only triggers for `ValidationException` (422). Generalize so that any exception thrown during handler dispatch checks for DTO-specific templates first. The renderer knows the DTO class from the dispatch context — if `Login.401.html` exists, use it; if not, fall back to `errors/401.html`, then the framework default. Same resolution chain, broader trigger. This lets `AuthenticationException` render `Login.401.html` with form re-render and error feedback instead of going straight to the generic error page. Solves the dogfood pattern of abusing `ValidationException` for non-validation errors.
- [ ] **Tests** — Unit tests for both exception classes. AuthorizationGuard tests updated to assert `AuthenticationException`/`AuthorizationException` instead of `HttpException`. Integration test: unauthenticated browser GET to `#[RequiresAuth]` endpoint → 302 redirect to login. Integration test: unauthenticated htmx request → HX-Location header. Integration test: unauthenticated API request → 401 JSON. CLI test: unauthenticated command → helpful error message + exit 1. Integration test: `AuthenticationException` during login → `Login.401.html` rendered with error.
- [ ] **Update COMPENDIUM.md and Auth README** — Document the transport-agnostic exception model, the redirect middleware, the `auth.redirect` config key, and the generalized DTO-specific exception template resolution. Remove references to `HttpException` for auth failures.

### Dogfood: unify CLI dispatch & colon routing (retro 8.5, orchestrator observation)

Two related problems uncovered during the dogfood:

1. **`help` doesn't work for built-in commands** (`help migrate:create` → exception). Root cause: built-in commands implement `BuiltInCommand` directly — a separate system from the DTO + Handler pattern that app commands use. Their constructor params are container dependencies, not CLI arguments. `HelpWriter` can't reflect on them.

2. **CLI naming is inconsistent.** Built-in commands use colon syntax (`migrate:create`, `cache:clear`). App CQRS commands use URL-like kebab syntax (`place-order`). The word "command" collides between CQRS Commands (write operations) and CLI "commands" (anything you type). Two dispatch paths, two naming conventions, two mental models.

**Principle:** The framework should use the same pattern it asks app developers to use. There should be one way to build CLI actions, and the CLI should have one consistent naming convention.

**Architecture:**

**One dispatch path.** Every CLI action — framework and app — is a DTO + Handler pair dispatched through the same Conveyor pipeline. DTOs carry CLI arguments as constructor params with `#[Description]` and `#[CliOnly]` attributes. Handlers carry container dependencies. `help`, validation, hydration, middleware — all free. The `BuiltInCommand` interface is retired.

**One naming convention: colon syntax.** `CliRouter` convention resolution produces colon-separated names from the namespace structure, not URL-like paths:

| Namespace | HTTP route | CLI route |
|---|---|---|
| `App\Domain\Shop\Command\PlaceOrder` | `POST /shop/place-order` | `shop:place-order` |
| `App\Domain\Shop\Query\Products` | `GET /shop/products.json` | `shop:products` |
| `App\Domain\Auth\Command\Login` | `POST /auth/login` | `auth:login` |

Domain → colon prefix. DTO name → kebab-case suffix. Same namespace, same handler, two transports, two routing conventions. Framework commands (`migrate:create`, `cache:clear`) are registered explicitly since they live in the framework namespace.

**Registry stays, role changes.** `BuiltInRegistry` maps colon-separated names to DTO class-strings (not `BuiltInCommand` classes). It exists because framework commands can't be convention-routed — they live in `Arcanum\Rune\`, not the app namespace. The registry lookup happens before convention routing, but dispatch goes through the standard Conveyor pipeline either way.

**Interactive commands.** `login` — interactive prompting (username/password) happens in the handler via `Prompter`/`Output`, not via constructor params. The DTO can be empty or carry optional `--username`/`--password` flags.

**19 framework commands to convert, grouped by category:**

| Category | Commands |
|---|---|
| Scaffolding | `make:key`, `make:command`, `make:query`, `make:page`, `make:middleware` |
| Database | `db:status`, `migrate`, `migrate:rollback`, `migrate:status`, `migrate:create`, `forge:models`, `validate:models` |
| Cache | `cache:clear`, `cache:status` |
| Validation | `validate:handlers` |
| Auth | `login`, `logout` |
| Meta | `list`, `help` |

#### Checklist

- [ ] **`CliRouter` colon convention** — Update `CliRouter`'s convention resolution to produce colon-separated names from namespaces. `App\Domain\Shop\Command\PlaceOrder` → `shop:place-order`. Domain segments become the colon prefix, DTO name becomes the kebab-case suffix. Query/Command type segment is stripped (same as HTTP). Path-doubling fix from the routing stream applies here too (domain = DTO name → single segment). Update `CliRouter` reverse resolution to match.
- [ ] **Convert scaffolding commands** — `make:key`, `make:command`, `make:query`, `make:page`, `make:middleware`. Each becomes a DTO with the command's arguments (e.g., `MakeCommand` DTO with `string $name`) and a Handler with container dependencies (`$rootDirectory`, `Writer`, `FileSystem`). Update `BuiltInRegistry` entries to point to DTO classes. Verify `help make:command` works after conversion.
- [ ] **Convert database commands** — `db:status`, `migrate`, `migrate:rollback`, `migrate:status`, `migrate:create`, `forge:models`, `validate:models`. `MigrateRollback` DTO gets `int $step = 1` as an optional param. `MigrateCreate` DTO gets `string $name`. Handlers inject `ConnectionManager`, `MigrationRepository`, `Migrator` as needed. Verify `help migrate:create` shows the `name` parameter.
- [ ] **Convert cache commands** — `cache:clear` (optional `string $store` param), `cache:status`. Straightforward.
- [ ] **Convert validation commands** — `validate:handlers`. No CLI arguments, handler injects the container and router.
- [ ] **Convert auth commands** — `login`, `logout`. `Login` DTO can be empty or carry optional `--username`/`--password` flags. Handler injects `Prompter`, `Output`, `CliAuthResolver` for interactive prompting. `Logout` DTO is empty.
- [ ] **Convert meta commands** — `list`, `help`. These introspect the command system itself. `Help` DTO takes `string $command`. Handler injects `CliRouter` and `BuiltInRegistry`. `List` DTO is empty; handler injects the registry and router to enumerate all commands with descriptions.
- [ ] **Retire `BuiltInCommand` interface** — Remove the interface. Update `BuiltInRegistry` to map names to DTO class-strings. Update `RuneKernel::handleInput()` to dispatch built-in commands through the same Conveyor pipeline as app commands (hydration → middleware → handler). Registry lookup happens before convention routing, but dispatch is identical.
- [ ] **Update `config/bootstrap.php` defaults** — The `make:key`, `list`, and `help` entries still need minimal bootstrap. Now they reference DTO classes instead of `BuiltInCommand` classes. Verify the handler dependencies are satisfiable under minimal bootstrap.
- [ ] **Update `make:command` / `make:query` scaffolders** — Generated DTOs should include `#[CliOnly]` if scaffolded as CLI-only. Generated handler stubs should follow the standard pattern. The scaffolder output should reference the colon-separated CLI name (e.g., "Run with: `php arcanum shop:place-order`").
- [ ] **Tests** — `help <name>` works for every built-in command. `list` shows all commands (built-in and app) with colon-separated names and descriptions. Built-in commands dispatch through the standard Conveyor pipeline. App commands accessible via colon syntax. Existing command behavior is unchanged.
- [ ] **Update COMPENDIUM.md and Rune README** — Document the unified CLI dispatch model: one DTO + Handler pattern for all CLI actions (framework and app), colon-separated naming convention derived from namespaces, `BuiltInRegistry` for framework commands. Remove references to `BuiltInCommand`. Update the CLI surface table with colon names. Update the app directory structure example to show how domains map to CLI names.

### PSR-18 HTTP Client

Any real app needs outgoing HTTP requests (APIs, OAuth, webhooks). Wrap an established library behind PSR-18 `ClientInterface` and provide PSR-17 factories over Hyper's existing PSR-7 classes. Ship a test mock so apps can assert outgoing calls without hitting the network.

**Architecture decisions:**

- **PSR-17 factories over Hyper classes.** `RequestFactory` composes `Message` + `Request` + `URI`. `ResponseFactory` composes `Message` + `Response`. `UriFactory` delegates to `new URI($string)`. `StreamFactory` needs a PSR-7 `StreamInterface` implementation — River's `Stream` has every required method but doesn't declare `implements StreamInterface`. **Decision: extend River\Stream to implement the interface.** One `implements` addition makes all of River PSR-7 compatible with no adapter overhead.
- **PSR-18 client wraps Guzzle, but Guzzle is optional.** Guzzle is currently a dev/transitive dependency, not a production one. The framework must not force every app to install Guzzle. Instead: `composer.json` adds Guzzle as a `suggest`, `Bootstrap\HttpClient` checks `class_exists(GuzzleHttp\Client::class)` before registering. Apps that need outgoing HTTP do `composer require guzzlehttp/guzzle`. Apps that don't pay nothing. The wrapper maps Guzzle exceptions to PSR-18 `NetworkException`/`RequestException`.
- **Convenience methods alongside PSR-18.** `sendRequest(RequestInterface)` is the PSR-18 contract. The wrapper also provides `get($url, $headers)`, `post($url, $body, $headers)`, etc. that build the request internally — less verbose for common cases.
- **TestClient for app testing.** Records outgoing requests, returns pre-configured responses. Registered in the container during tests. Same pattern as `FrozenClock` and `ArrayDriver` — deterministic, no side effects.
- **Lives at `src/Hyper/Client/`** as a sub-namespace of Hyper, since it builds on the same PSR-7 classes.

**Security:**

- **TLS verification must default to on.** The Guzzle wrapper must never disable SSL verification by default. If `config/http.php` exposes a `verify` option, it defaults to `true`. The README warns against disabling in production.
- **Sensitive header scrubbing in logs.** When logging integration lands, outgoing request logging must strip `Authorization`, `Cookie`, `X-Api-Key`, and other sensitive headers from log context by default. Configurable blacklist.

#### Checklist

- [ ] **River\Stream — implement `StreamInterface`** — Add `implements StreamInterface` to `Flow\River\Stream`. It already has every method; this is a declaration, not a rewrite. Adjust any signature mismatches. Update River tests. This unblocks PSR-17 StreamFactory.
- [ ] **PSR-17 factories** — `RequestFactory`, `ResponseFactory`, `UriFactory`, `StreamFactory` over Hyper's existing PSR-7 classes. Add `psr/http-factory` as a direct dependency. Tests for each factory.
- [ ] **PSR-18 client** — `HttpClient implements ClientInterface` wrapping Guzzle. Constructor takes Guzzle `ClientInterface` (or builds a default if Guzzle is installed). Convenience methods: `get()`, `post()`, `put()`, `patch()`, `delete()`. Maps Guzzle exceptions to PSR-18 `NetworkException`/`RequestException`. Add `psr/http-client` as a direct dependency; add `guzzlehttp/guzzle` as a `suggest`. Config via `config/http.php` (base_uri, timeout, headers, verify defaults to `true`). Tests with Guzzle mock handler.
- [ ] **TestClient** — `TestClient implements ClientInterface` for app tests. `addResponse(ResponseInterface)` queues responses. `assertSent(callable)` checks recorded requests. `assertNothingSent()`. Register in container when `TestKernel` is used. Tests.
- [ ] **Bootstrap registration** — `Bootstrap\HttpClient` registers factories and client in the container. PSR-17 interfaces bound to Hyper factories. PSR-18 `ClientInterface` bound to the Guzzle wrapper only if `class_exists(GuzzleHttp\Client::class)` — skip gracefully otherwise. `has()` guards so apps can bring their own. Sensitive header scrubbing list for future logging integration (`Authorization`, `Cookie`, `X-Api-Key`).
- [ ] **README** — What it does, when to use it, sending requests (PSR-18 and convenience methods), handling responses, testing with TestClient, configuration, TLS verification warning, swapping implementations.

### Dogfood: starter app update

After the dogfood framework changes land, the starter app needs a comprehensive update pass to absorb them. This is not a feature-by-feature migration — it's a single coordinated update that brings the starter in line with the new framework APIs.

#### Checklist

- [ ] **Slim down bootstrap files** — Remove container self-registration, Bus registration, ServerAdapter, EmptyResponseRenderer, EventDispatcher, ExceptionHandler/ErrorHandler/ShutdownHandler from both `bootstrap/http.php` and `bootstrap/cli.php`. These are now framework defaults. What remains: kernel binding, primitive specs, exception renderer preferences, app-specific listeners, app-specific helpers.
- [ ] **Auth config reform** — Replace `config/auth.php` closures with an `IdentityProvider` class reference. Create `app/Auth/UserProvider.php` implementing `IdentityProvider` with the existing user lookup logic (using Forge models now that the provider is container-resolved). Add `auth.redirect` key pointing to `/login`.
- [ ] **Add `config/bootstrap.php`** — Ship with framework defaults commented out as documentation.
- [ ] **Move migrations** — Move `migrations/` to `database/migrations/`. Add `migrations_path` key to `config/database.php` (commented out, showing the default).
- [ ] **Use `env()` in config files** — Replace any hardcoded values or manual `$_ENV` reads in config files with `env()` calls.
- [ ] **Update login flow** — Refactor login handler to return `CommandResult::redirect('/dashboard')` (or wherever). Remove any htmx `hx-on::after-request` redirect workarounds. Add `FlashMessage` event dispatch for login success/failure feedback.
- [ ] **CSRF JS update** — Verify the htmx v4 CSRF JS fix works with the starter's forms and standalone htmx buttons. Remove any `<form>` wrapper workarounds for CSRF.
- [ ] **CLI colon syntax** — Update any documentation, scripts, or references to CLI commands to use the new colon-separated naming convention for app commands.
- [ ] **Update starter README** — Reflect all changes: slimmed bootstrap, new auth config, `database/migrations/`, login flow with `CommandResult`, flash messages, CLI colon syntax. Rewrite the handler naming table to show full class names with a concrete example.
- [ ] **Fix `.env.example` and Quick Start** — The starter README Quick Start says `composer install` then `php bin/arcanum migrate` — but doesn't mention copying `.env.example` to `.env` first. Add the copy step. Change `.env.example` from `APP_KEY=base64:` (confusing empty value) to `APP_KEY=` with a comment saying "Run `php bin/arcanum make:key --write` to generate."
- [ ] **Smoke test** — Start the app from a fresh clone, follow the README Quick Start, verify: `.env` copy + `make:key --write` works from a blank slate, migrations run from `database/migrations/`, login/logout flow works with redirects, CSRF works on standalone htmx buttons, `list` shows all commands with colon names, `help migrate:create` shows parameters.

### Dogfood: documentation (retro 6.1–6.8)

Documentation gaps surfaced by the dogfood. Many are blocked on or significantly easier after the framework changes above land. These should be written after the code changes are complete, not before — documenting the "before" state wastes effort.

#### From-scratch guide (retro 6.1)

Build B hit 12 errors before a page rendered. After the bootstrap self-wiring work, the from-scratch path is dramatically shorter, but it still needs a guide.

##### Checklist

- [ ] **"Getting started from scratch" guide** — Step-by-step: `composer require arcanum-org/framework`, create directory structure, write `bootstrap/http.php` (5 lines after self-wiring), write `public/index.php`, write `app/Http/Kernel.php`, create `.env` + `make:key --write`, create first page, run with PHP built-in server. Each step shows the expected result and the error you'd see if you skip it. Lives in the framework README or a dedicated `docs/from-scratch.md`.

#### Config schema reference (retro 6.2, 6.8)

The only way to learn config file formats today is to read bootstrapper source code. Every config file needs a documented schema.

##### Checklist

- [ ] **Config schema reference** — Document every config file with all keys, types, defaults, and a complete example. Files to cover: `app.php`, `auth.php` (new `IdentityProvider` format), `bootstrap.php` (new), `cache.php`, `database.php` (including `migrations_path`), `formats.php`, `htmx.php`, `log.php` (the `Handler::STREAM` / `handlers` / `channels` format that tripped Build B), `middleware.php`, `routes.php`, `session.php`. Lives in the framework README, a dedicated `docs/configuration.md`, or as a section in each relevant package README. One canonical location, not scattered.

#### HTTP login flow recipe (retro 6.3)

The most common thing web developers build, and neither dogfood build found a documented path. This becomes straightforward after `CommandResult`, `AuthenticationException`, `AuthRedirectMiddleware`, and `IdentityProvider` land.

##### Checklist

- [ ] **HTTP login flow recipe** — End-to-end guide: `IdentityProvider` implementation, `config/auth.php` with session guard, Login DTO + handler returning `CommandResult::redirect('/dashboard')`, login template with `{{ csrf }}`, error handling via `AuthenticationException` + status-specific template, `AuthRedirectMiddleware` configuration, logout flow. Show the full working code, not pseudocode. Lives in the Auth README or a dedicated recipe doc.

#### Testing guide (retro 6.4, 5.7)

The Testing package README covers `TestKernel`, `Factory`, and `HttpTestSurface`. It doesn't cover Forge models, database setup, or the `final`-class workarounds.

##### Checklist

- [ ] **Testing guide for database-backed handlers** — How to set up a `ConnectionManager` for tests (constructor signature, config format). Temp-file SQLite pattern for Forge model tests. Seeding test data. Testing handlers that inject `Identity` (use real `ActiveIdentity` + `SimpleIdentity`). The `final`-class philosophy: why mocking is blocked, what to do instead (real objects, `TestKernel`, `Factory`). Lives in the Testing README.

#### Additional documentation (retro 6.5, 6.6, 6.7)

##### Checklist

- [ ] **`handleRequest()` contract** — Document what `HyperKernel::handleRequest()` should do: resolve route, hydrate DTO, dispatch through Conveyor, render response. Show the typical implementation with the key method calls. Lives in the Ignition README or Hyper README.
- [ ] **Pages routing and handling** — Document how `app/Pages/*.html` works: `PageDiscovery` scans for templates, virtual DTO class names, `isPage()` check, `Page` DTO construction. The convention is discoverable via the starter but invisible from scratch. Lives in the Atlas README.
- [ ] **Domain boundary guidance** — When to create separate domains vs. group things together. Rules of thumb: entities that share a lifecycle belong together, cross-domain reads are a smell that domains are too granular. Document the current cross-domain options (import directly, duplicate SQL, dispatch query) with tradeoffs. Lives in the COMPENDIUM or a dedicated guide.

### Integration test coverage

Only 2 integration tests exist today (`CqrsLifecycleTest`, `HelperResolutionTest`). Unit tests miss interaction bugs: discovery ordering, bootstrap sequencing, round-trip rendering, lifecycle event flow. `TestKernel` already provides `HttpTestSurface` and `CliTestSurface` — expanding coverage is cheap. This is also a long-tail principle: every new feature should land with at least one integration test.

**What exists today:**
- `CqrsLifecycleTest` — HTTP request → routing → hydration → dispatch → render (JSON/CSV/HTML), command 204/201, per-route middleware.
- `HelperResolutionTest` — global + domain-scoped helpers through real bootstrappers.
- `TestKernel` — shared container with FrozenClock, ArrayDriver cache, ActiveIdentity, lazy HTTP/CLI surfaces, `actingAs()`.

**What's missing:**

| Gap | Why it matters |
|-----|----------------|
| CLI lifecycle | Zero CLI integration tests. Routing, dispatch, output formatting, exit codes untested end-to-end. |
| htmx rendering | HX-Request header → fragment extraction is a key user-facing path with no integration test. |
| Error responses | 404, 405, 406, 422, 500 across JSON and HTML formats — only unit-tested in isolation. |
| Session + auth | Session middleware → auth middleware → AuthorizationGuard flow never tested as a chain. |
| Lifecycle events | Events fire in unit tests, but never verified in a full middleware → handler → render pass. |
| Template rendering | HTTP request → TemplateResolver → TemplateCompiler → TemplateEngine → response never tested end-to-end. |

**Fixture convention:** Integration test fixtures (DTOs, handlers, templates, middleware) live in `tests/Integration/Fixture/`, mirroring the pattern established by `CqrsLifecycleTest`. Some tests (SessionAuth, Template) require real middleware chains — use `TestKernel` with selective bootstrapper setup or manual wiring as needed.

#### Checklist

- [ ] **CliIntegrationTest** — CLI routing → hydration → dispatch → output rendering → exit codes. Cover: convention-based command, query with formatted output, built-in command (`list`), unknown command (error + suggestion), `--help` flag.
- [ ] **HtmxIntegrationTest** — Request with `HX-Request` header → fragment rendering (content section only, no layout). Cover: `HX-Target` → element-by-id extraction, `ClientBroadcast` → `HX-Trigger` header on response, `Vary: HX-Request` header.
- [ ] **ErrorResponseIntegrationTest** — Full error lifecycle across formats. Cover: 404 (no route), 405 (wrong method → correct Allow header), 406 (unsupported format), 422 (validation failure → field errors), 500 (unhandled exception). Verify both JSON and HTML renderers produce correct status codes and content.
- [ ] **SessionAuthIntegrationTest** — Session start → identity set → AuthorizationGuard enforcement → CSRF validation. Cover: unauthenticated request to `#[RequiresAuth]` → 401, authenticated request → passes, wrong role → 403, policy check with DTO data, CSRF token round-trip (session → form → validation). This test requires SessionMiddleware + AuthMiddleware + CsrfMiddleware + the Conveyor pipeline wired together — document the setup pattern for future complex integration tests.
- [ ] **MiddlewareOrderIntegrationTest** — Verify middleware executes in registered order. Register middleware A, B, C; verify execution order is A → B → C → handler → C → B → A (onion model). Catches ordering bugs that unit tests miss.
- [ ] **LifecycleEventIntegrationTest** — Verify all events fire in correct order during a full HTTP request. Cover: `RequestReceived` (mutable — verify listener can enrich request), `RequestHandled` (verify request + response pair), `RequestFailed` (verify exception captured), `ResponseSent` (verify fires in terminate). Parallel test for CLI events (`CommandReceived`, `CommandHandled`, `CommandFailed`, `CommandCompleted`).
- [ ] **TemplateRenderIntegrationTest** — HTTP request → template resolution → compilation → rendering with helpers. Cover: co-located template found, layout extends, helper calls resolve, status-specific template (422 error template).

---

## Completed Work

One-line summaries. Details are in git history and the COMPENDIUM.

- **Logging instrumentation** — `?LoggerInterface` across the stack: Bootstrap\Logger binding + `CorrelationProcessor`, HyperKernel/RuneKernel lifecycle, HttpRouter/CliRouter route resolution, RouteDispatcher, AuthMiddleware, SessionMiddleware, RateLimiter, Migrator. Harmonized all log sites to null-safe `?->`. One INFO line per request; everything else DEBUG/NOTICE.
- **Hyper README** — Full package reference: request journey, PSR-7 messages, URI, status codes, file uploads, response renderers, exception renderers, format registry, middleware chain, lifecycle events, server + adapter, `#[HttpOnly]`.
- **Todo App dogfood** — Two parallel builds (clone-starter, from-scratch) of the same Todo app. Journals and `RETROSPECTIVE.md` delivered. 10 work streams extracted into this plan.
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

- **HTTP method enforcement on convention routes** — A PUT to a Command endpoint dispatches to the default handler today (smoke-tested during the parsed body fix). Should convention routes restrict which HTTP methods reach a handler? Options: (1) Commands only accept POST by default, Queries only accept GET. (2) The unprefixed handler accepts all methods unless a prefixed handler exists (`PutAddEntryHandler`). (3) Explicit `#[AllowedMethods('POST')]` attribute. Surfaced during parsed body smoke test — a PUT to the guestbook's AddEntry created a real entry.
- **Database seeding** — `database/seeders/` directory with a `Seeder` interface and `db:seed` CLI command. Seeders are PHP classes (not SQL) so they can use `password_hash()`, Forge models, and other runtime logic. `make:seeder` scaffolding command. Surfaced in dogfood: both builds needed a test user but had no framework-supported way to seed one — Build A pre-computed a bcrypt hash in raw SQL, Build B hardcoded credentials.
- **Forge dynamic WHERE composition** — Forge's one-SQL-file-per-method model can't parameterize or conditionally compose WHERE clauses. Filtering (all/active/completed) requires three nearly-identical SQL files. Surfaced in dogfood: Build A needed `AllForList.sql`, `ActiveForList.sql`, `CompletedForList.sql` with identical JOINs and differing only in the WHERE clause. Possible solutions: `@where` pragma, SQL template includes, or a lightweight query-composition layer that preserves Forge's SQL-first philosophy.
- **`final` class testing patterns** — `final` on Forge Models, `ActiveSession`, `Session` blocks PHPUnit mocking. Deliberate design choice — document the intended testing patterns (real objects, temp SQLite, `TestKernel`) rather than removing `final`. Surfaced in dogfood: both builds struggled with handler unit tests.
- **Cross-domain model access** — `DomainContext` scopes `$db->model` to one domain per handler. When a handler needs read-only data from another domain, current options are: import the other domain's Model directly (breaks boundaries), duplicate the SQL file (violates DRY), or dispatch through the bus (heavy for a simple lookup). Real architectural question with scope implications — document the current options and defer the design decision. Consider cross-domain relationship markers.
- **`PdoConnection` accepts PDO instance** — `PdoConnection` takes a DSN string, not a `\PDO` instance. Can't use in-memory SQLite for testing. Fix: accept both DSN string and PDO instance. Surfaced in dogfood: both builds needed temp-file SQLite workarounds, adding ~1s overhead and setup boilerplate per test class.
- **Template cache bypass in dev mode** — Template changes require `cache:clear` during development. Add an mtime check when `APP_DEBUG=true` so templates recompile automatically. Surfaced in dogfood: Build B flagged this as an extra step that breaks flow.
- **`{{ end }}` universal closer** — `{{ end }}` is not recognized — only `{{ endif }}`, `{{ endforeach }}`, etc. Deliberate design choice (explicit closers are clearer). Document this. Surfaced in dogfood: Build B noted this as a DX gap for users coming from Go/Hugo templates.
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
