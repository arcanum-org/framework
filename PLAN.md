# Framework Completion Plan

This checklist tracks remaining work to complete all packages in the Arcanum framework. Each item is a small unit of work that includes 100% test coverage.

**Progress: 213 done, 10 remaining + Rune (43 items).**

---

## Completed Work

### Bug Fixes

- [x] Fix `Headers::cleanValues()` rejecting `'0'` as a header value — PHP's `empty('0')` returns true, so `Content-Length: 0` is rejected. Replace `empty()` with a strict check.
- [x] Fix `Response::withoutHeader()` mutating the original object — it should return an immutable copy per PSR-7. This also breaks `Server::sendSetCookieHeaders()`.
- [x] Fix `EmptyStream::getMetadata($key)` returning `null` for any key — now looks up the key in the metadata array, returns `null` only for absent keys
- [x] Fix `PrimitiveResolver` calling `implode(",", $type->getTypes())` on `ReflectionType` objects — now maps to `getName()` via `ReflectionNamedType` check
- [x] Fix `StandardProcessor` using loose `!$payload` check — changed to `$payload === null`
- [x] Fix `RegistryTest::assertGetSetViaArrayAccess()` method name — renamed to `testGetSetViaArrayAccess()` so PHPUnit runs it
- [x] Fix `ProviderRegistry` interface accepting only `Provider` while `Container::provider()` accepts `string|Provider` — updated interface to `string|Provider`
- [x] Fix typo `testSimnpleProvider` → `testSimpleProvider` in `SimpleProviderTest`
- [x] Fix typo `testSimnpleProvider` → `testPrototypeProvider` in `PrototypeProviderTest`

### Hyper

- [x] Add CRLF injection prevention in header values (reject or strip `\r\n` sequences)
- [x] Add request target validation per RFC 7230 (origin-form, absolute-form, authority-form, asterisk-form)
- [x] Investigate PHPServerAdapter testability — not unit-testable: every method is a 1:1 wrapper around SAPI-dependent PHP built-ins. Class is correctly marked `@codeCoverageIgnore`.
- [x] Add tests for `Server::request()` cookie filtering — not unit-testable: directly accesses superglobals
- [x] Add tests for `Server::sendSetCookieHeaders()`
- [x] Fix `Port` constructor not storing type-converted value
- [x] Fix `Port` validation error message
- [x] Fix `Request::getRequestTarget()` returning stale cached value after `withUri()`
- [x] Add `ResponseTest` — 18 tests covering status code, reason phrase, charset, headers, body, protocol version, and immutability

### Glitch

- [x] Add `HttpException` class — exception that carries an HTTP status code
- [x] Define `ExceptionRenderer` interface — takes `Throwable`, returns `ResponseInterface`
- [x] Implement `JsonExceptionRenderer` — converts exceptions to JSON error responses
- [x] Integrate `ExceptionRenderer` into `HyperKernel`
- [x] Add tests for `Handler`, `LogReporter`, `Level` enum
- [x] Remove `JsonReporter` — replaced by `JsonExceptionRenderer`

### Ignition

- [x] Add configuration caching
- [x] Add environment validation
- [x] Add `Bootstrap\Routing` — registers Atlas routing and Shodo format registry in the container
- [x] Add tests for `HyperKernel`, `Bootstrap\Environment`, `Bootstrap\Configuration`, `Bootstrap\Logger`, `Bootstrap\Exceptions`

### Codex

- [x] Fix nullable parameter resolution — `resolveClass()` returns `object|array|null`
- [x] Add tests for nullable parameter fallback

### Quill

- [x] Review PSR-3 compliance — complete
- [x] Add tests for `Logger` and `Channel`

### Parchment

- [x] Add `Reader`, `Writer`, `FileSystem`, `TempFile`, `Searcher`
- [x] Migrate `ConfigurationCache` to use Parchment

### Atlas (Routing)

- [x] Convention-based URL-to-namespace routing (`ConventionResolver`)
- [x] `HttpRouter` with resolution priority: custom routes > pages > convention
- [x] `Route` value object with `isQuery()`, `isCommand()`, `isPage()`, `withFormat()`
- [x] `RouteMap` for explicit path → class mappings with `isPage` propagation
- [x] `PageDiscovery` — scans `app/Pages/` for `.html` templates, registers as GET-only routes
- [x] `PageResolver` for legacy page support
- [x] Context-aware format routing via file extensions
- [x] OPTIONS middleware with `Allow` header
- [x] Handler-only routes with dynamic Command/Query DTOs
- [x] 404/405 distinction for convention routes
- [x] Config-based custom route and page format override registration

### Shodo (Rendering)

- [x] `Renderer` interface — base contract for converting data to output
- [x] `JsonRenderer` — pure JSON serializer
- [x] `HtmlRenderer` — template-based with co-located `.html` discovery
- [x] `PlainTextRenderer` — template-based with co-located `.txt` discovery, identity escape
- [x] `CsvRenderer` — pure tabular serializer
- [x] `EmptyResponseRenderer` — status-code-only for commands
- [x] `JsonExceptionRenderer` — exceptions as JSON with debug/production modes
- [x] `TemplateCompiler` — regex-based `{{ }}` syntax → PHP, format-agnostic `$__escape`
- [x] `TemplateCache` — compiled template caching with mtime freshness
- [x] `TemplateResolver` — PSR-4 convention DTO class → template file
- [x] `HtmlFallback` / `PlainTextFallback` — generic data dumps when no template exists
- [x] `FormatRegistry` — maps extensions to renderers, resolves from container
- [x] Format configuration via `config/formats.php`

### CQRS Integration

- [x] Global HTTP middleware stack (`HttpMiddleware` in HyperKernel)
- [x] `Hydrator` — request data → DTO mapping with type coercion
- [x] Query response serialization via `FormatRegistry`
- [x] `EmptyResponseRenderer` for command responses (void→204, DTO→201)
- [x] JSON body parsing in `HyperKernel::prepareRequest()`
- [x] Error responses flow through full middleware stack

### Conveyor

- [x] Handler prefix dispatch (POST→`Post`, DELETE→`Delete`, etc.) with fallback
- [x] `HandlerProxy` interface for dynamic DTOs
- [x] `DynamicDTO` base class — shared Registry wrapper for Command, Query, Page
- [x] `Command` / `Query` dynamic DTOs for handler-only routes
- [x] `Page` dynamic DTO — fixed `handlerBaseName()` → `PageHandler`
- [x] `PageHandler` — framework-provided handler for all pages
- [x] `QueryResult` wrapper for non-object handler returns
- [x] DTO validation middleware filters

### Starter Project

- [x] Full CQRS pipeline: Router → Hydrator → Conveyor → Renderer
- [x] Example Query (`Health`), Command (`Contact/Submit`), Page (`Index`)
- [x] Pages are template-driven — `Index.html` + optional `Index.php` DTO with defaults
- [x] Config files: `app.php`, `routes.php`, `formats.php`, `middleware.php`, `cache.php`
- [x] CORS middleware example

### Documentation

- [x] Write `src/Parchment/README.md`
- [x] Write `src/Atlas/README.md`
- [x] Write `src/Shodo/README.md`
- [x] Update `src/Flow/Conveyor/README.md` — DynamicDTO, Page, PageHandler sections

---

## Remaining Work

### Coverage Gaps

Small, isolated test cases for edge cases and untested paths.

**Cabinet:**
- [x] Add test for `Container::specify()` with array `when` parameter — only the string form is tested
- [x] Add test for `Container` default constructor — no test creates a Container with `null` parameters

**Codex:**
- [x] Add test for `Resolver::resolve()` with callable that returns non-object
- [x] Add test for `Resolver::resolveWith()` with variadic constructor parameters — also fixed `resolveWith()` to handle variadic params

**Echo:**
- [x] Add test for listener that throws a non-`Interrupted` exception during dispatch
- [x] Add test for `Interrupted` exception during dispatch — verify event is returned and propagation stops
- [x] Add test for event mutation propagation through listener chain

**Flow — River:**
- [x] Add test for `Stream::read(0)` edge case — already existed at `StreamTest::testReadReturnsEmptyStringIfLengthToReadIsZero`
- [x] Add test for `CachingStream::seek()` with `SEEK_END` on an unseekable remote stream

**Flow — Conveyor:**
- [x] Add test for `MiddlewareBus::dispatch()` when handler class is not found in container

**Gather:**
- [x] Add tests for `Configuration::asAlpha()`, `asAlnum()`, `asDigits()` with dot-notation keys
- [x] Add tests for `IgnoreCaseRegistry::asAlpha()`, `asAlnum()`, `asDigits()` — verify case-insensitive coercion
- [x] Add tests for `Environment` inherited methods (`get()`, `has()`, `set()`, `count()`)
- [x] Add test for `Configuration::set()` where an intermediate path value is a scalar, not an array

### Integration Tests

Now that all renderers are built, these integration tests are unblocked:

- [x] Add integration tests for Query response with HTML format
- [x] Add integration tests for Query response with CSV format

### Format Registry Tests

- [x] Add tests for app-defined custom format with custom renderer
- [x] Add tests for disabling a built-in format via config
- [x] Add tests for overriding a built-in format's renderer via config

### Command Response Enhancements

- [x] Add void/null distinction in Conveyor — reflect on handler return type: `void` → EmptyDTO (204), nullable returning null → AcceptedDTO (202), nullable returning value → 201
- [x] Add documentation guidance — explain CQRS command conventions and why response bodies are discouraged

**Deferred — blocked on upstream work:**

The remaining command response items depend on infrastructure that doesn't exist yet:

- **`Location` header for 201 responses** — requires reverse routing (URL generation from a class/identifier) and a persistence layer (handlers need to create things and get IDs back). In CQRS, commands and queries live at different paths, so there's no canonical resource URL to point to like in REST/CRUD. A `Locatable` interface would force devs to hardcode URLs, which is brittle. Revisit after: persistence layer, reverse routing, and a convention linking Commands to their corresponding Queries.
- **Integration tests for 202/201 in Kernel** — straightforward once the Location header is settled, but low value without it.

### Route Middleware

- [x] Add per-route middleware support — PHP attributes (#[HttpMiddleware], #[Before], #[After]) on DTOs + co-located Middleware.php files for directory-scoped middleware. RouteDispatcher composes per-route middleware with existing Bus at both HTTP and Conveyor layers.
- [x] Add tests for per-route middleware execution

### Documentation

- [x] Write `src/Toolkit/README.md`
- [x] Write `src/Glitch/README.md`
- [x] Write `src/Ignition/README.md`
- [x] Write `src/Quill/README.md`

---

## Rune — CLI Transport

Rune is Arcanum's CLI transport layer. It lets developers execute the same Commands and Queries from the terminal that they execute over HTTP — same DTOs, same handlers, same Conveyor bus. Where Atlas maps HTTP requests to Routes, Rune maps CLI arguments. Where Shodo renders JSON/HTML for browsers, Rune renders tables and key-value output for terminals.

### Design Principles

- **The DTO is the contract.** A developer writes a DTO and a handler. It works on HTTP and CLI without ceremony. The transport is just plumbing.
- **Explicit intent.** HTTP uses methods (GET/POST) to distinguish queries from commands. CLI uses prefixes: `query:` and `command:`. Every CLI invocation declares its CQRS intent.
- **Convention routing.** The same namespace conventions drive both transports. `command:contact:submit` maps to `App\Domain\Contact\Command\Submit` the same way `POST /contact/submit` does.
- **Unprefixed = framework.** Built-in operational commands (`list`, `help`, `validate:handlers`) have no prefix. The `command:`/`query:` prefix explicitly enters domain dispatch.

### CLI Syntax

```
php arcanum command:<domain>:<action> [--arg=value ...]
php arcanum query:<domain>:<action> [--arg=value ...] [--format=json|table|text]
php arcanum <built-in> [--flags]
```

Mapping examples:
```
query:health                → App\Domain\Query\Health
query:users:find            → App\Domain\Users\Query\Find
command:contact:submit      → App\Domain\Contact\Command\Submit
command:users:deactivate    → App\Domain\Users\Command\Deactivate
```

CLI flags map to DTO constructor parameters via the Hydrator — the same mechanism HTTP uses:
```
php arcanum command:contact:submit --name="Jo" --email="jo@example.com"
```

### Phase 1: Core I/O

The foundation — parsing CLI input and writing CLI output.

- [x] `Input` value object — parses `$argv` into command name, positional arguments, named options (`--key=value`), and boolean flags (`--verbose`)
- [x] `Output` interface — `write(string)`, `writeLine(string)`, `error(string)`, `errorLine(string)`
- [x] `ConsoleOutput` — concrete `Output` writing to `STDOUT`/`STDERR` with ANSI color support (detect TTY, allow `--no-ansi` flag)
- [x] `ExitCode` enum — `Success = 0`, `Failure = 1`, `Invalid = 2` (mirrors standard CLI conventions)

### Phase 2: Routing

Map CLI arguments to Route objects, reusing the convention system.

- [x] Refactor `ConventionResolver` — extract a lower-level `resolveByType(string $path, string $typeNamespace, string $handlerPrefix, string $format): Route` method that both HTTP method mapping and CLI type prefix can call. No breaking changes to existing `resolve()`.
- [x] `CliRouter` implementing `Router` — accepts `Input`, parses `command:`/`query:` prefix, delegates to `ConventionResolver::resolveByType()`. Throws `UnresolvableRoute` for unknown prefixes.
- [x] `CliRouteMap` — config-based CLI aliases for non-conventional classes (parallels `RouteMap` for HTTP). Loaded from `config/routes.php` under a `'cli'` key.
- [x] 404/405 equivalent for CLI — `UnresolvableRoute` for unknown commands, clear error messages with "did you mean?" suggestions based on registered commands
- [x] `--help` flag interception — when present, `CliRouter` returns a special help route instead of dispatching
- [x] `--format` flag extraction — passed through to `Route::$format`, defaults to CLI-appropriate format (not JSON)

### Phase 3: Kernel

The CLI entry point — parallel to `HyperKernel` but without PSR-7/PSR-15 coupling.

- [x] `RuneKernel` extending/implementing `Kernel` — shares `rootDirectory`, `configDirectory`, `filesDirectory`, `requiredEnvironmentVariables`. Has its own `handle(array $argv): int` method returning an exit code.
- [x] `RuneKernel` bootstrapper list — reuses `Environment`, `Configuration`, `Logger`, `Exceptions`. Skips `Middleware` and `RouteMiddleware` (PSR-15 specific). Adds CLI-specific bootstrappers as needed.
- [x] `RuneKernel::handle()` flow — parse `Input` → route → hydrate DTO → dispatch through Conveyor → render output → return exit code
- [x] `RuneKernel` exception handling — catches exceptions, renders to `STDERR` via a `CliExceptionWriter`, returns appropriate exit codes
- [x] `CliExceptionWriter` — renders exceptions as formatted error messages to `Output`. Debug mode shows stack traces, production mode shows clean messages with status context.
- [x] `Bootstrap\CliRouting` bootstrapper — registers `CliRouter`, CLI format registry, and CLI-specific renderers in the container. Parallels `Bootstrap\Routing` for HTTP.

### Phase 4: Rendering

CLI-specific output rendering — tables, key-value pairs, and reuse of existing renderers.

- [x] Resolve `Renderer` return type — the `mixed` return was deferred for this moment. Renderers stay `mixed` return (string for content renderers), and each Kernel is responsible for wrapping the result into its transport's response type. HTTP kernels wrap into `ResponseInterface`. CLI kernels write to `Output`. The Renderer contract stays transport-agnostic.
- [x] `CliRenderer` — default CLI renderer. Single objects render as key-value pairs. Arrays of objects render as tables. Scalars render as plain text. Null/void renders nothing (just exit code).
- [x] `TableRenderer` — ASCII table formatting for array/list data. Auto-detects columns from object properties or array keys. Supports column width calculation and truncation.
- [x] `CliFormatRegistry` — maps `--format` values to renderers. Built-in: `table` → `TableRenderer`, `json` → existing `JsonRenderer`, `text` → existing `PlainTextRenderer`, `csv` → existing `CsvRenderer`. Default (no `--format`) → `CliRenderer`.
- [x] `CliEmptyResponseRenderer` — not needed as a separate class. The void/accepted/DTO distinction is handled inline in `RuneKernel::renderResult()`. In HTTP, `EmptyResponseRenderer` exists because building a `ResponseInterface` with status codes is non-trivial. For CLI, it's exit code + optional message — already in the kernel.

### Phase 5: Help System

Auto-generated help from DTO reflection and attributes.

- [x] `Arcanum\Rune\Attribute\Description` — PHP attribute for DTOs and constructor params. Provides help text. Ignored by HTTP.
- [x] ~~`Arcanum\Rune\Attribute\Example`~~ — dropped. Usage line is auto-generated from constructor signature: required params as `--name=<type>`, optional as `[--name=<type>]`, bools as `[--verbose]`.
- [x] `HelpWriter` — reads DTO class via reflection: constructor params become documented flags (name, type, required/optional, default value, `#[Description]` text). Auto-generates usage line. Extracted from RuneKernel for independent testability.
- [x] `list` built-in command — discovers all available commands and queries by scanning the app namespace. Groups by domain. Shows `#[Description]` text if present.
- [x] `help` built-in command — alias for `<command> --help`. Example: `php arcanum help query:health`.

### Phase 6: Transport Restriction

Middleware that restricts DTOs to specific transports.

- [x] `Arcanum\Rune\Attribute\CliOnly` — PHP attribute on DTOs. Marks a command/query as CLI-only.
- [x] `Arcanum\Hyper\Attribute\HttpOnly` — PHP attribute on DTOs. Marks a command/query as HTTP-only.
- [x] `TransportGuard` Conveyor middleware — reads transport context from a container-registered value (e.g., `Transport::Http` or `Transport::Cli` enum), checks DTO attributes, throws appropriate error. For HTTP: `HttpException(405)` — the thing exists, wrong transport. For CLI: clear error message with suggestion to use the other transport.
- [x] Transport context registration — each Kernel registers its `Transport` enum value in the container during bootstrap so `TransportGuard` can check it.
- [x] Tests for cross-transport rejection — HTTP request to `#[CliOnly]` DTO returns 405, CLI call to `#[HttpOnly]` DTO shows error.

### Phase 7: Built-in Framework Commands

Operational commands that ship with Rune (no `command:`/`query:` prefix).

- [x] `list` — discover and display all registered commands and queries (convention + custom CLI routes)
- [x] `validate:handlers` — scan all DTO classes, verify each has a corresponding handler class. Report missing handlers. (The dev tool mentioned in Closed Questions — now has a home.)
- [x] Built-in command registry — framework commands registered separately from domain dispatch. `RuneKernel` checks built-ins before routing to `CliRouter`.
- [x] Extensible built-in commands — app developers can register custom operational commands (e.g., `cache:clear`, `migrate`) via config or Kernel method, without the `command:` prefix.

### Phase 8: Starter App Integration

Wire Rune into the starter project as a working example.

- [x] `bin/arcanum` entry point — `#!/usr/bin/env php` script. Loads autoloader, requires `bootstrap/cli.php`, bootstraps, handles `$argv`, exits with code.
- [x] `bootstrap/cli.php` — container setup for CLI. Parallels `bootstrap/http.php`. Registers `RuneKernel`, `CliRouter`, `ConsoleOutput`, CLI renderers.
- [x] `app/Cli/Kernel.php` — app-level CLI kernel extending `RuneKernel`. Developers customize bootstrappers and built-in commands here.
- [x] CLI route config — add `'cli'` key to `config/routes.php` for custom CLI aliases.
- [x] Verify existing `Health` query works from CLI — `php arcanum query:health` dispatches to `HealthHandler`, renders key-value output. `--format=json` and `--format=table` work. `--help` shows parameters and usage.
- [x] Verify existing `Contact\Submit` command works from CLI — `php arcanum command:contact:submit --name="Jo" --email="jo@test.com"` dispatches to `SubmitHandler`, silent success (exit 0). Built-in `list`, `help`, and `validate:handlers` all work.

### Phase 9: Documentation

- [x] Write `src/Rune/README.md` — package overview, CLI syntax, routing conventions, renderers, transport restriction, built-in commands
- [ ] Update `README.md` — add Rune to the package list
- [ ] Update `src/Atlas/README.md` — document `ConventionResolver` refactoring and shared convention system
- [x] Update `src/Ignition/README.md` — document `RuneKernel` and shared bootstrapper architecture

---

## Future Work

### Persistence Layer

A database/filesystem/SQLite persistence layer to make Arcanum a viable full-stack framework. Blocked on Rune completion — CLI commands like migrations, schema management, and seed scripts need the CLI transport. Design should embrace CQRS: repositories for the write side (aggregates), query builder for the read side (projections). Not a full ORM — lightweight entity mapping, not Doctrine-level magic.

Key areas: connection management (PDO, multi-driver), repository pattern, query builder, migrations, schema management, entity hydration. The `Location` header for 201 responses (deferred in Command Response Enhancements) depends on this + reverse routing.

### Deferred — Command Response Enhancements

Blocked on persistence layer:

- **`Location` header for 201 responses** — requires reverse routing (URL generation from a class/identifier) and a persistence layer (handlers need to create things and get IDs back). In CQRS, commands and queries live at different paths, so there's no canonical resource URL to point to like in REST/CRUD. A `Locatable` interface would force devs to hardcode URLs, which is brittle. Revisit after: persistence layer, reverse routing, and a convention linking Commands to their corresponding Queries.
- **Integration tests for 202/201 in Kernel** — straightforward once the Location header is settled, but low value without it.

### Design Considerations

Items flagged for future discussion. Not blocking.

- ~~Revisit `Renderer` interface return type~~ — resolved by Rune Phase 4. Renderers stay `mixed` return. Each Kernel wraps the result into its transport's response type.
- ~~**Shodo renderers are HTTP-specific**~~ — addressed by the Shodo/Hyper refactor below.
- [x] **CLI debug mode not respecting config** — investigated and resolved. Not a bug: the starter app's `.env` file sets `APP_DEBUG=true`, so debug mode is correctly enabled. With `APP_DEBUG=false`, the `CliExceptionWriter` correctly shows only the clean error message. The `Bootstrap\CliRouting` check (`$debug === true || $debug === 'true'`) handles both boolean and string values correctly.

---

## Shodo/Hyper Rendering Refactor

Shodo currently depends on Hyper — every content renderer builds a `ResponseInterface`. This couples the formatting package to HTTP, blocking clean CLI reuse. The fix: Shodo becomes a pure formatting package (string output, no HTTP dependency), and HTTP response adapters move to Hyper.

### Design Principles

- **Shodo owns formatting.** Pure data → string conversion. No transport dependency. JSON, CSV, HTML, plain text, CLI key-value, tables — all return strings.
- **Hyper owns HTTP responses.** Thin adapters wrap Shodo formatters into `ResponseInterface` with Content-Type, Content-Length, status codes. Each adapter composes a formatter instance.
- **Rune uses formatters directly.** The CLI writes formatter output to `Output`. No adapters needed.
- **No breaking changes to external API.** The starter app's Kernel and config files should work with minimal changes. Internal class locations move, but the bootstrappers absorb the wiring changes.

### Dependency flow after refactor

```
Shodo (pure formatting)
  ↑              ↑
Hyper            Rune
(HTTP adapters)  (uses formatters directly)
```

### Phase 1: Formatter Interface and Extractions

Extract pure formatting logic from each HTTP renderer into a `Formatter` class.

- [x] `Formatter` interface in Shodo — `format(mixed $data): string`. Replaces `Renderer` as Shodo's primary contract.
- [x] `JsonFormatter` — extract JSON encoding from `JsonRenderer`. Pretty-print, unescaped slashes, throw on error.
- [x] `CsvFormatter` — extract CSV encoding from `CsvRenderer`. All the tabular/associative/scalar detection logic stays here.
- [x] `HtmlFormatter` — extract template compilation and rendering from `HtmlRenderer`. Takes data + DTO class, returns HTML string. Depends on TemplateCompiler, TemplateCache, TemplateResolver (all stay in Shodo).
- [x] `PlainTextFormatter` — extract from `PlainTextRenderer`. Same template system, identity escape.
- [x] Delete `CliJsonRenderer` — replaced by `JsonFormatter` (same thing, now properly named).
- [x] Rename `CliRenderer` → `KeyValueFormatter` — it's not CLI-specific, it's a formatting strategy. Auto-detects data shape (object → key-value, list → table, scalar → plain text).
- [x] Rename `TableRenderer` → `TableFormatter` — pure string output, not a renderer.
- [x] Update `CliFormatRegistry` to resolve `Formatter` instances instead of `Renderer`.
- [x] Tests for each new formatter — mostly extracted from existing renderer tests, assertions change from checking ResponseInterface to checking strings.

### Phase 2: HTTP Response Adapters in Hyper

Thin wrappers that compose a Shodo formatter and build ResponseInterface.

- [x] `ResponseRenderer` abstract class in Hyper — shared logic for building a Response from a string body (Content-Type, Content-Length, Stream wrapping). Avoids duplicating the Response construction in every adapter.
- [x] `JsonResponseRenderer` in Hyper — wraps `JsonFormatter`, sets `application/json` content type.
- [x] `CsvResponseRenderer` in Hyper — wraps `CsvFormatter`, sets `text/csv` content type.
- [x] `HtmlResponseRenderer` in Hyper — wraps `HtmlFormatter`, sets `text/html` content type.
- [x] `PlainTextResponseRenderer` in Hyper — wraps `PlainTextFormatter`, sets `text/plain` content type.
- [x] Move `EmptyResponseRenderer` to Hyper — purely HTTP (status code + empty body, no formatting).
- [x] Move `JsonExceptionRenderer` to Hyper — depends on `JsonResponseRenderer` + Glitch. Rename to `JsonExceptionResponseRenderer` for clarity.
- [x] Move `UnsupportedFormat` exception — decoupled from HttpException in Phase 3+4. Now a plain RuntimeException. Hyper's FormatRegistry throws HttpException(406) directly.
- [x] Tests for each adapter — verify Response status code, Content-Type header, body content matches formatter output.

### Phase 3: Delete Old Shodo Renderers

Remove the HTTP-coupled classes from Shodo now that Hyper owns them.

- [x] Delete `Shodo\JsonRenderer` — replaced by `Hyper\JsonResponseRenderer` + `Shodo\JsonFormatter`.
- [x] Delete `Shodo\CsvRenderer` — replaced by `Hyper\CsvResponseRenderer` + `Shodo\CsvFormatter`.
- [x] Delete `Shodo\HtmlRenderer` — replaced by `Hyper\HtmlResponseRenderer` + `Shodo\HtmlFormatter`.
- [x] Delete `Shodo\PlainTextRenderer` — replaced by `Hyper\PlainTextResponseRenderer` + `Shodo\PlainTextFormatter`.
- [x] Delete `Shodo\EmptyResponseRenderer` — moved to Hyper.
- [x] Delete `Shodo\JsonExceptionRenderer` — moved to Hyper as `JsonExceptionResponseRenderer`.
- [x] Delete `Shodo\Renderer` interface — replaced by `Hyper\ResponseRenderer` abstract class. FormatRegistry returns `ResponseRenderer`.
- [x] Delete `Shodo\CliJsonRenderer` — replaced by `Shodo\JsonFormatter` (done in Phase 1).

### Phase 4: Update Wiring

Update bootstrappers, registries, and the starter app.

- [x] Move `FormatRegistry` to Hyper — resolves Hyper response renderers. Returns `ResponseRenderer`. Throws `HttpException(406)` directly instead of `UnsupportedFormat`.
- [x] Update `CliFormatRegistry` — resolves Shodo formatters (for CLI). Stays in Shodo. (Done in Phase 1.)
- [x] Update `Bootstrap\Routing` (HTTP) — register Hyper response renderers instead of Shodo renderers. Wire formatter → adapter composition.
- [x] Update `Bootstrap\CliRouting` (CLI) — register Shodo formatters directly. (Done in Phase 1.)
- [x] Update `RuneKernel` — call `Formatter::format()` instead of `Renderer::render()`. (Done in Phase 1.)
- [x] Decouple `UnsupportedFormat` from `HttpException` — now a plain `RuntimeException` in Shodo (for CliFormatRegistry).
- [x] Update starter app `Http\Kernel`, `bootstrap/http.php`, `config/formats.php` — import changes for moved classes.
- [x] Update integration tests — verify full HTTP pipeline still works with new adapter classes.
- [x] Update Shodo README — document the formatter-first architecture.

### Phase 5: Verify and Clean Up

- [x] Run full `composer check` — all tests pass, PHPStan clean, CS clean.
- [x] Verify starter app HTTP — FormatRegistry → JsonResponseRenderer → 200 OK, application/json.
- [x] Verify starter app CLI — `query:health` works with all format flags (cli, json, csv, table).
- [x] Remove any unused imports or dead code left over from the migration — clean scan, zero issues.

---

## Closed Questions

Decided — preserved for context:

- ~~**Bootstrap lifecycle hooks**~~ — **Won't do.** The app controls the Kernel subclass and can add, remove, or reorder bootstrappers directly. Events would add complexity to an already detailed middleware lifecycle without solving a problem that bootstrapper ordering doesn't already handle.
- ~~**Handler auto-discovery**~~ — **Won't do for runtime.** Codex resolves handlers on demand via reflection — no pre-registration needed. Boot-time validation is now the `validate:handlers` built-in CLI command in Rune Phase 7.
- ~~**Opt-in command response bodies**~~ — **Won't do.** Commands shouldn't respond with data. The Location header (once persistence + reverse routing exist) gives clients a way to fetch the created resource via a proper Query. Keeping commands body-less preserves clean CQRS separation.
- ~~**CLI command prefixes**~~ — All domain dispatch uses `command:` or `query:` prefix. No prefix = built-in framework command. This mirrors how HTTP method determines CQRS intent — the prefix is the CLI equivalent.
- ~~**CLI custom routes vs HTTP custom routes**~~ — Independent configs. HTTP custom routes exist for URL aesthetics (API versioning, pretty paths). CLI custom routes exist for aliasing non-conventional namespaces. Convention routing covers 90% on both sides. Config lives in `config/routes.php` under `'http'` and `'cli'` keys.

## Resolved Questions

- ~~**Manual route overrides**~~ — Custom routes are the general mechanism (path+methods → explicit class mapping). Pages are a convenience layer that auto-discovers templates and registers them as GET-only custom routes. Priority: custom routes > convention.
- ~~**HTML renderer and templates**~~ — Custom micro template compiler in Shodo. Templates co-located with DTOs, `{{ }}` syntax compiled to PHP and cached. Format-agnostic `$__escape` injected by each renderer.
- ~~**Static file pages**~~ — Pages are template-driven. A `.html` template in `app/Pages/` is all that's needed. Pages flow through Conveyor via `Page` DTO and `PageHandler`. Optional DTO provides default data. Query params hydrated into template variables.
- ~~**Renderer return type**~~ — Stays `mixed`. Renderers produce content (string), Kernels wrap it into transport-specific responses. Decided during Rune design — see Phase 4.
