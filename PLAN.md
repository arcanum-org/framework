# Framework Completion Plan

This checklist tracks remaining work to complete all packages in the Arcanum framework. Each item is a small unit of work that includes 100% test coverage.

---

## Completed Work

All core packages, the Rune CLI transport, and the Shodo/Hyper rendering refactor are complete. 1402 tests, PHPStan clean.

<details>
<summary>Packages — bug fixes, features, tests, documentation (click to expand)</summary>

- **Cabinet** — DI container. Full test coverage including `specify()` array form, default constructor.
- **Codex** — Reflection resolver. Nullable params, variadic params, non-object callables.
- **Echo** — Event dispatcher. Interrupted propagation, mutation propagation, non-Interrupted exceptions.
- **Flow/River** — Streams. `read(0)` edge case, `CachingStream::seek(SEEK_END)`.
- **Flow/Pipeline** — Stage chains. Complete.
- **Flow/Continuum** — Middleware pattern. Complete.
- **Flow/Conveyor** — Command bus. Handler prefix dispatch, DynamicDTO, Page/PageHandler, QueryResult, DTO validation, handler-not-found, TransportGuard.
- **Gather** — Registries. Configuration dot-notation coercion, IgnoreCaseRegistry coercion, Environment methods, scalar-in-path edge case.
- **Glitch** — Error handling. HttpException, ExceptionRenderer, JsonExceptionRenderer, Handler, LogReporter, Level.
- **Hyper** — HTTP layer. CRLF injection, request target validation, Port fixes, Response immutability, JSON body parsing, global middleware stack, ResponseRenderer base class, FormatRegistry, all response renderers (Json, Csv, Html, PlainText, Empty, JsonException).
- **Ignition** — Bootstrap. HyperKernel, RuneKernel, all bootstrappers (Environment, Configuration, Routing, CliRouting, RouteMiddleware, Logger, Exceptions, Middleware), ConfigurationCache, RouteDispatcher, Transport enum.
- **Atlas** — Router. ConventionResolver (shared HTTP+CLI), HttpRouter, CliRouter, Route, RouteMap, CliRouteMap, PageDiscovery, PageResolver, OPTIONS middleware, 404/405 distinction, per-route middleware (attributes + Middleware.php).
- **Shodo** — Formatting. Formatter interface, JsonFormatter, CsvFormatter, HtmlFormatter, PlainTextFormatter, KeyValueFormatter, TableFormatter, TemplateCompiler, TemplateCache, TemplateResolver, HtmlFallback, PlainTextFallback, CliFormatRegistry, UnsupportedFormat.
- **Rune** — CLI transport. Input, Output/ConsoleOutput, ExitCode, CliExceptionWriter, HelpWriter, BuiltInRegistry, Description attribute, CliOnly attribute, ListCommand, HelpCommand, ValidateHandlersCommand.
- **Parchment** — File utilities. Reader, Writer, FileSystem, TempFile, Searcher.
- **Quill** — Logging. PSR-3 compliance, Logger, Channel.
- **Toolkit** — String utilities. Complete.

</details>

<details>
<summary>Starter project (click to expand)</summary>

- Full CQRS pipeline: Router → Hydrator → Conveyor → Renderer
- Example Query (Health), Command (Contact/Submit), Page (Index)
- HTTP + CLI entry points with shared bootstrapper architecture
- Config: app.php, routes.php, formats.php, middleware.php, cache.php, cors.php, log.php
- CORS middleware example

</details>

<details>
<summary>Shodo/Hyper rendering refactor (click to expand)</summary>

Shodo was decoupled from Hyper — formatters produce strings, response renderers build HTTP responses:

- **Phase 1** — Formatter interface, extracted JsonFormatter/CsvFormatter/HtmlFormatter/PlainTextFormatter, renamed CliRenderer→KeyValueFormatter, TableRenderer→TableFormatter, deleted CliJsonRenderer, updated CliFormatRegistry+RuneKernel.
- **Phase 2** — ResponseRenderer abstract class, JsonResponseRenderer/CsvResponseRenderer/HtmlResponseRenderer/PlainTextResponseRenderer, EmptyResponseRenderer, JsonExceptionResponseRenderer in Hyper.
- **Phase 3+4** — Deleted old Shodo renderers+Renderer interface, moved FormatRegistry to Hyper, decoupled UnsupportedFormat from HttpException, rewired Bootstrap\Routing, updated starter app and integration tests.
- **Phase 5** — Verified all pipelines (HTTP+CLI), zero dead code, updated Shodo README.

</details>

<details>
<summary>Documentation (click to expand)</summary>

All package READMEs written/updated: Cabinet, Codex, Echo, Gather, Parchment, Flow (Pipeline, Continuum, Conveyor, River), Shodo, Atlas, Hyper, Glitch, Quill, Ignition, Toolkit, Rune. Root README updated with all packages.

</details>

---

## Upcoming Work

Items are ordered by priority. The dependency chain drives the order: **Security → Validation → Caching → Scaffolding → Sessions → Auth**, then standalone items, then the heavyweights.

| Priority | Item | Impact | Difficulty | Blocking | Depends on |
|---|---|---|---|---|---|
| 1 | Security primitives | 6 | 3 | 9 | — |
| 2 | Validation | 10 | 4 | 8 | — |
| 3 | Caching (Vault) | 7 | 4 | 7 | — |
| 4 | Scaffolding generators | 8 | 3 | 1 | — |
| 5 | Sessions (HTTP-only) | 5 | 4 | 8 | Security, Caching |
| 6 | Auth & Authorization | 9 | 6 | 4 | Security, Validation, Sessions |
| 7 | HTTP client | 5 | 2 | 1 | — |
| 8 | Custom app scripts | 5 | 2 | 1 | — |
| 9 | Persistence — Forge | 10 | 7 | 6 | — (Auth benefits from it) |
| 10 | Template helpers | 6 | 4 | 2 | Persistence (for reverse routing) |
| 11 | Starter app polish | 4 | 2 | 1 | Auth, Persistence, Caching |

Scores: Impact (1=niche, 10=affects everyone), Difficulty (1=simple wrapper, 10=bespoke behemoth), Blocking (1=standalone, 10=everything else needs it first).

---

### 1. Security Primitives — in Toolkit

Foundational security utilities that other packages build on. Smallest effort, highest blocking score — sessions, auth, CSRF, and signed URLs all depend on these existing. Lives in Toolkit since these are general-purpose tools, not a standalone package.

Downstream consumers:
- **Sessions** need encryption (cookie encryption) and random tokens (session IDs)
- **Auth** needs hashing (passwords), random tokens (API keys, remember-me), HMAC (signed tokens)
- **CSRF middleware** needs random tokens (generation) and timing-safe comparison (verification)
- **Signed URLs** need HMAC signing and verification

#### Encryption

- [x] `Encryptor` interface — `encrypt(string $plaintext): string`, `decrypt(string $ciphertext): string`. The contract that downstream consumers depend on.
- [x] `EncryptionKey` value object — wraps a raw 32-byte key (SODIUM_CRYPTO_SECRETBOX_KEYBYTES). Constructor validates length, throws `InvalidArgumentException` on wrong size. `fromBase64(string): self` factory for parsing `APP_KEY` from environment.
- [x] `SodiumEncryptor` — XSalsa20-Poly1305 via libsodium (`sodium_crypto_secretbox`). Fresh 24-byte nonce per encryption. Produces `base64(nonce || ciphertext)`. Throws `DecryptionFailed` on any error. Chosen over AES-256-GCM: 24-byte nonce eliminates nonce collision risk, simpler API, built into PHP 8.x.
- [x] `DecryptionFailed` exception — extends `RuntimeException`. Carries no detail about *why* decryption failed (that would leak information to attackers).
- [x] Tests: encrypt/decrypt round-trip, tampered ciphertext throws `DecryptionFailed`, wrong key throws `DecryptionFailed`, malformed envelope (truncated, empty, not base64) throws `DecryptionFailed`, empty string encrypts/decrypts correctly, binary data round-trips, two encryptions of the same plaintext produce different envelopes (nonce uniqueness).
- [x] Tests for `EncryptionKey`: valid 32-byte key accepted, wrong length rejected, `fromBase64()` decodes correctly, `fromBase64()` rejects invalid base64.

#### Hashing

- [x] `Hasher` interface — `hash(string $value): string`, `verify(string $value, string $hash): bool`, `needsRehash(string $hash): bool`. The `needsRehash()` method enables transparent algorithm migration.
- [x] `BcryptHasher` — wraps `password_hash(PASSWORD_BCRYPT)` with configurable cost (default 12). Note: bcrypt silently truncates input at 72 bytes — documented in class docblock.
- [x] `Argon2Hasher` — wraps `password_hash(PASSWORD_ARGON2ID)` with configurable `memory_cost` (default 65536 = 64MB), `time_cost` (default 4), `threads` (default 1).
- [x] Tests for `BcryptHasher`: hash/verify round-trip, wrong value returns false, `needsRehash()` returns true when cost changes, `needsRehash()` returns false for current params, hash output is a valid bcrypt string.
- [x] Tests for `Argon2Hasher`: hash/verify round-trip, wrong value returns false, `needsRehash()` detects stale params, hash output is a valid argon2id string. Skipped if argon2id not available (`PASSWORD_ARGON2ID` constant check).

#### Random

- [x] `Random` static utility class — `bytes(int $length): string`, `hex(int $bytes = 32): string`, `base64url(int $bytes = 32): string`. All backed by `random_bytes()`.
- [x] Tests: `bytes()` returns correct length, `hex()` output length is `$bytes * 2`, `hex()` contains only `[0-9a-f]`, `base64url()` contains only `[A-Za-z0-9_-]`, two calls produce different values.

#### HMAC Signing

- [x] `Signer` interface — `sign(string $payload): string`, `verify(string $payload, string $signature): bool`. Used by signed URLs, signed cookies, and any tamper-detection scenario.
- [x] `SodiumSigner` — HMAC-SHA512/256 via `sodium_crypto_auth`/`sodium_crypto_auth_verify`. Constant-time verification. Constructor validates key length (SODIUM_CRYPTO_AUTH_KEYBYTES). Signatures are hex-encoded. Chosen over `hash_hmac` for consistency with libsodium across the security stack.
- [x] Tests: sign/verify round-trip, tampered payload fails verification, wrong key fails verification, empty payload signs/verifies correctly, signature is correct hex length, malformed signatures return false, wrong key length rejected.

#### Bootstrap & Wiring

- [x] `Bootstrap\Security` bootstrapper in Ignition — reads `APP_KEY` from the `Environment` registry. Decodes the key via `EncryptionKey::fromBase64()`. Registers `Encryptor` (bound to `SodiumEncryptor`) and `Signer` (bound to `SodiumSigner`) in the container. Registers a default `Hasher` (bound to `BcryptHasher`). Throws `RuntimeException` if `APP_KEY` is missing or invalid.
- [x] Add `Bootstrap\Security` to both `HyperKernel::$bootstrappers` and `RuneKernel::$bootstrappers` — slot after `Configuration` and before `Routing`.
- [x] Starter app: add `APP_KEY=base64:<generated>` to `.env` and `.env.example`.
- [x] Tests for `Bootstrap\Security`: registers `Encryptor`, `Signer`, and `Hasher` in container; throws on missing `APP_KEY`; throws on invalid (wrong-length) key; accepts key with and without `base64:` prefix; encryptor and signer are functional.

#### CLI

- [x] `make:key` built-in Rune command — generates a random 32-byte key, base64-encodes it, prints `APP_KEY=base64:<key>`. Writes directly to `.env` if `--write` flag is passed. Registered in `Bootstrap\CliRouting`.
- [x] Tests: output matches `APP_KEY=base64:...` format, `--write` flag creates/updates `.env` file, generated key decodes to 32 bytes, replaces existing APP_KEY, appends when no existing key.

#### Documentation

- [x] Update `src/Toolkit/README.md` — document Encryption, Hashing, Random, and HMAC Signing sections with examples.
- [x] Update `src/Ignition/README.md` — add `Bootstrap\Security` to the bootstrapper tables for both HTTP and CLI.

### 2. Validation — new package

DTO-attribute-based validation, executed as Conveyor middleware before the handler runs. Affects every DTO in the framework. Auth will need it. Arcanum's CQRS architecture makes this exceptionally clean: the DTO already declares its shape via constructor params, so validation rules live as attributes right on the parameters.

```php
#[Description('Submit a contact form')]
final class Submit
{
    public function __construct(
        #[NotEmpty] #[MaxLength(100)]
        public readonly string $name,
        #[NotEmpty] #[Email]
        public readonly string $email,
        #[MaxLength(5000)]
        public readonly string $message = '',
    ) {}
}
```

Validation runs automatically via a Conveyor middleware — if validation fails, an HTTP request gets **422 Unprocessable Entity** with structured error details, and a CLI invocation gets a clear error message with exit code 2. No manual validation calls in handlers.

Key design decisions:
- Validation runs on the **hydrated DTO**, not raw input — the object already has typed values by the time validation runs.
- Rules are **PHP attributes on constructor params** — inspected via reflection, same pattern as `#[Description]` and `#[CliOnly]`.
- Validation is a **Conveyor before-middleware** (`Progression`) — same pattern as `TransportGuard`. Fires before the handler, aborts dispatch on failure.
- `HandlerProxy` DTOs (dynamic `Command`/`Query`/`Page`) are **skipped** — they don't have constructor params to validate.
- The `Validator` is a **standalone class** — testable independently of the bus, so apps can also validate manually when needed.

#### Rule Interface and Built-in Rules

- [x] `Rule` interface — `validate(mixed $value, string $field): ValidationError|null`. Attribute target (`Attribute::TARGET_PARAMETER`) so rules double as PHP attributes.
- [x] `NotEmpty` rule — rejects `null`, `''`, `[]`. Message: `"The {field} field is required."`
- [x] `MinLength(int $min)` / `MaxLength(int $max)` — string length bounds via `mb_strlen`. Non-string values skip the check.
- [x] `Min(int|float $min)` / `Max(int|float $max)` — numeric bounds. Non-numeric values skip the check.
- [x] `Email` — validates email format via `filter_var(FILTER_VALIDATE_EMAIL)`.
- [x] `Pattern(string $regex)` — validates against a regex via `preg_match`.
- [x] `In(mixed ...$values)` — value must be one of the listed values (strict comparison).
- [x] `Url` — validates URL format via `filter_var(FILTER_VALIDATE_URL)`.
- [x] `Uuid` — validates UUID format (any version) via regex.
- [x] Tests for each rule: valid value returns null, invalid value returns `ValidationError` with correct field name and message, edge cases (empty string, null, wrong type, boundary values, multibyte strings). 57 rule tests.

#### Validation Engine

- [x] `ValidationError` — value object: `field` (string), `message` (string).
- [x] `ValidationException` — extends `RuntimeException`. Carries `list<ValidationError>`. Provides `errors()` and `errorsByField()`.
- [x] `Validator` — `validate(object $dto): void` (throws) and `check(object $dto): list<ValidationError>` (returns). Reflects on constructor params, finds `Rule` attributes, collects all errors before throwing.
- [x] `Validator` skips `HandlerProxy` instances.
- [x] `Validator` skips params with no `Rule` attributes.
- [x] `Validator` handles nullable params — null values on nullable types skip all rules.
- [x] Tests: multiple rules on multiple params, no rules, all valid, nullable null, HandlerProxy skipped, check vs validate, field names in messages. 14 engine tests.

#### Conveyor Middleware

- [x] `ValidationGuard` — Conveyor before-middleware (`Progression`). Validates DTOs before handler dispatch. HandlerProxy payloads are skipped.
- [x] Tests: valid DTO passes through, invalid throws before `$next()`, HandlerProxy skipped, no-rule DTOs pass. 4 middleware tests.

#### Error Rendering

- [x] `ValidationExceptionRenderer` in Hyper — decorator that catches `ValidationException`, renders **422 Unprocessable Entity** with `{"errors": {...}}`. Delegates other exceptions to inner renderer. Registered as decorator on `ExceptionRenderer` in `Bootstrap\Routing`.
- [x] CLI rendering — `CliExceptionWriter` detects `ValidationException` and formats field→message list cleanly instead of stack trace. Works in both debug and production mode.
- [x] Tests for `ValidationExceptionRenderer`: 422 status, field-keyed JSON, multiple errors per field, delegation of non-validation exceptions. 5 tests.
- [x] Tests for CLI rendering: validation errors readable on stderr with field names, no stack trace. 2 tests.

#### Bootstrap & Wiring

- [x] `ValidationGuard` registered as Conveyor before-middleware automatically in both `Bootstrap\Routing` and `Bootstrap\CliRouting`.
- [x] `ValidationExceptionRenderer` registered as decorator on `ExceptionRenderer` in `Bootstrap\Routing`.
- [x] Starter app: `Contact\Submit` DTO updated with `#[NotEmpty]`, `#[Email]`, `#[MaxLength]` validation attributes.
- [x] Bootstrap wiring tested via full suite — ValidationGuard fires, ValidationExceptionRenderer catches.

#### Custom Rules

- [x] Custom rules documented in README: implement `Rule`, add `#[Attribute]`, example `#[Unique('users', 'email')]`.
- [x] `Callback(callable)` rule — escape hatch. Callable returns `true` or error string. 4 tests.

#### Documentation

- [x] Validation package `README.md` — overview, built-in rules reference, custom rules, manual validation, Conveyor integration, error rendering.
- [x] Updated `src/Flow/Conveyor/README.md` — `ValidationGuard` alongside `TransportGuard` in framework middleware section.

### 3. Caching — new package (Vault)

A general-purpose PSR-16 (`CacheInterface`) caching package with swappable drivers. Sessions need cache drivers. The framework's own caches migrate onto it. PSR-16 is a clean, well-defined spec.

Downstream consumers:
- **Sessions** need a cache driver for session storage (file or Redis)
- **Rate limiting** needs fast increment/check (APCu or Redis)
- **Auth** may cache user lookups, token blacklists
- **Framework internals** — four existing ad-hoc caches (config, templates, page discovery, middleware discovery) migrate onto this

#### PSR-16 Interface and Core

- [x] Added `psr/simple-cache` ^3.0 as Composer dependency.
- [x] `FileDriver` — file-based, one file per key, atomic writes (temp+rename), lazy expiry deletion, md5 key hashing.
- [x] `ArrayDriver` — in-memory, per-process, TTL via expiry timestamps.
- [x] `NullDriver` — no-op driver for disabling caching.
- [x] `ApcuDriver` — wraps APCu, guards with `extension_loaded('apcu')` check.
- [x] `RedisDriver` — wraps `\Redis` instance, serializes values, native TTL via SETEX.
- [x] Full PSR-16 test coverage for FileDriver (14 tests), ArrayDriver (16 tests), NullDriver (7 tests). APCu (8 tests, skipped without extension), Redis (10 mock-based tests, skipped without extension).

#### Key Validation

- [x] `KeyValidator` — static utility validating PSR-16 key constraints (`{}()/\@:` rejection, empty rejection).
- [x] `InvalidArgument` — implements `Psr\SimpleCache\InvalidArgumentException`.
- [x] Tests: valid keys pass, empty rejected, each reserved character rejected, validateMultiple. 12 tests.

#### Cache Manager

- [x] `CacheManager` — factory/registry for named stores, lazy instantiation, framework store mapping, relative path resolution.
- [x] Config structure documented and implemented in starter app `config/cache.php`.
- [x] Tests: default store, named stores, unknown store, lazy instantiation, framework mapping, fallback, store names, relative paths, unknown driver. 11 tests.

#### Prefix Scoping

- [x] `PrefixedCache` — key-prefix decorator for any `CacheInterface`. `clear()` delegates to inner (documented limitation).
- [x] Tests: prefix prepending on all operations, isolation between prefixes. 8 tests.

#### Store Assignment — framework and app caches

- [x] Framework store mapping via `config/cache.php` `framework` key.
- [x] `CacheManager::frameworkStore($purpose)` resolves mapped or default store.
- [x] Tests: framework mapping resolves correct driver, falls back to default.

#### Bootstrap & Wiring

- [x] `Bootstrap\Cache` — reads `config/cache.php`, registers `CacheManager` and `CacheInterface`. Falls back to file driver if no config.
- [x] Added to `HyperKernel::$bootstrappers` and `RuneKernel::$bootstrappers` after `Security`, before `Routing`.
- [x] Starter app `config/cache.php` updated with `default`, `stores`, `framework` structure.
- [x] Tests: registers CacheManager, registers CacheInterface, default store usable, works with no config. 4 tests.

#### Migrate Framework Ad-hoc Caches

- [x] `ConfigurationCache` — **left as-is.** Bootstrap primitive that runs before the container is built; cannot depend on CacheManager.
- [x] `TemplateCache` — **left as-is.** Specialized mtime-based compilation cache; PSR-16 doesn't support mtime freshness natively. `cache:clear` knows about it.
- [x] Migrate `PageDiscovery` cache — refactored to accept `CacheInterface`. Replaced `$cachePath`/`$cacheMaxAge`/`$reader`/`$writer` with `$cache`/`$cacheTtl`. Uses PSR-16 get/set/delete. Tests updated to use `ArrayDriver`.
- [x] Migrate `MiddlewareDiscovery` cache — same pattern. Replaced bespoke file cache with `CacheInterface`. Serializes `RouteMiddleware` to/from arrays for cache storage. Tests updated.
- [x] Updated `Bootstrap\Routing` — resolves framework `pages` store via `CacheManager`, wraps in `PrefixedCache('fw.pages.')`, passes to `PageDiscovery`.
- [x] Updated `Bootstrap\RouteMiddleware` — resolves framework `middleware` store, wraps in `PrefixedCache('fw.middleware.')`, passes to `MiddlewareDiscovery`.

#### CLI

- [x] `cache:clear` built-in Rune command — clears all stores + ConfigurationCache + TemplateCache. `--store=NAME` for single store. Registered in `Bootstrap\CliRouting`. 4 tests.
- [x] `cache:status` built-in Rune command — shows configured stores, default store, driver types, and framework store assignments. 3 tests.

#### Documentation

- [x] `src/Vault/README.md` — PSR-16 compliance, all drivers, configuration, PrefixedCache, CacheManager, CLI.
- [x] Updated `src/Ignition/README.md` — `Bootstrap\Cache` in bootstrapper tables for both HTTP and CLI.

### 4. Scaffolding Generators — Rune built-in commands

Zero dependencies on other upcoming work. Massive DX payoff. Slotted after validation so the generated stubs can include validation attributes.

Code generators that create DTO + handler + template stubs from the command line:

```
php arcanum make:query Users/Find
php arcanum make:command Contact/Submit
php arcanum make:page About
php arcanum make:middleware RateLimit
```

This is the single biggest DX improvement for onboarding — new developers go from zero to a working endpoint in seconds without memorizing namespace conventions.

#### Stub Rendering via Shodo

- [x] Stub templates stored as co-located `.stub` files in `Rune/Command/stubs/` (command.stub, command_handler.stub, query.stub, query_handler.stub, page.stub, page_template.stub, middleware.stub).
- [x] `TemplateCompiler::render()` — new method added to Shodo for direct `{{! $var !}}` substitution without eval. Designed for stubs where the output itself is PHP source code (which conflicts with compile()'s PHP tag emission). 4 new TemplateCompiler tests.
- [x] `Generator` abstract base class — shared stub rendering, directory creation, overwrite protection, name parsing, namespace building. All four generators extend it.

#### make:command

- [x] `MakeCommandCommand` — generates DTO + handler in `app/Domain/{segments}/Command/`. Handler has `void` return type. 7 tests (correct files/namespaces, overwrite protection, intermediate dirs, single-segment names, invalid names, prints paths).

#### make:query

- [x] `MakeQueryCommand` — generates DTO + handler in `app/Domain/{segments}/Query/`. Handler has `array` return type with `return []`. 2 tests.

#### make:page

- [x] `MakePageCommand` — generates DTO + HTML template in pages directory. DTO has `$title` default from PascalCase-to-title-case conversion. Template is raw Shodo template (not compiled). Supports configurable `pages_namespace` and `pages_directory`. 4 tests.

#### make:middleware

- [x] `MakeMiddlewareCommand` — generates PSR-15 `MiddlewareInterface` in `app/Http/Middleware/`. 2 tests.

#### Registration & Config

- [x] All four registered in `Bootstrap\CliRouting` with factories providing `rootDirectory` and `rootNamespace` from Kernel/Configuration.
- [x] `#[Description]` attributes on each generator for `php arcanum help` and `php arcanum list`.

#### Edge Cases & Error Handling

- [x] Empty name → exit code 2 with error message.
- [x] Invalid characters → exit code 2 with descriptive error.
- [x] Single-segment names → valid, creates at domain root.
- [x] Deeply nested paths → valid, all intermediate directories created.
- [x] Overwrite protection → refuses with error, exit code 1.

#### Documentation

- [x] Updated `src/Rune/README.md` — scaffolding generators, custom stubs, cache commands, updated built-in command list and at-a-glance.

### 5. Session Management — new package

Depends on: security primitives (encryption for cookie sessions), caching (driver reuse for server-side storage).

Sessions are an **HTTP-only transport concern**. They exist to support three specific purposes: identity persistence (remembering that a user already authenticated), CSRF protection, and flash messages. Sessions are **not** a general-purpose key-value store for application state.

**Design decisions (resolved):**

- **Auth is a transport concern; identity is a domain concern.** A session cookie and a Bearer token are two different transport mechanisms that both resolve to the same `Identity` value object. By the time a command or query handler runs, auth is already done — the handler receives a typed `Identity`, never a session.
- **Commands stay stateless.** The session lives in PSR-15 middleware, not in the Conveyor bus. Handlers never see it. A command handler receiving `BanUser($userId, $actor)` is stateless — given the same inputs, it does the same thing regardless of whether `$actor` was resolved from a session cookie or a JWT.
- **No generic key-value session API.** Sessions expose purpose-built methods (`flash()`, `csrf()`, identity storage) — not `get('arbitrary_key')`/`set('arbitrary_key', $value)`. Domain state (shopping carts, wizard progress, drafts) belongs in the domain layer (persistence), not in sessions. This is an opinionated stance: sessions are structured, not junk drawers.
- **Own package, not part of Hyper.** Sessions have their own storage drivers, middleware, and concerns (CSRF, flash) that are distinct from PSR-7 messages. Auth consumes sessions, so they must be independent of auth.
- **CLI does not use sessions.** CLI has no cookies. CLI auth uses tokens — `--token` flag, environment variable, or stored config file. Cross-transport browser auth (like `gh auth login` opening a browser) is a real pattern but niche — it results in a stored token, not a session. Deferred as a future CLI auth extension.

**The layering:**

```
HTTP Request
  → SessionMiddleware (PSR-15) — reads/writes encrypted session cookie, hydrates Session object
  → AuthMiddleware (PSR-15) — calls Guard to resolve Identity from session/token/API key
  → Identity registered in container
  → Atlas routes → DTO hydrated (Identity injected via container) → Conveyor dispatches
  → Handler receives typed Identity, never touches Session
```

```
CLI Command
  → TokenResolver — reads --token flag or environment variable
  → Identity registered in container
  → Rune routes → DTO hydrated → Conveyor dispatches
  → Handler receives same typed Identity
```

Same handler, same `Identity` type, different transport auth mechanisms. The DTO doesn't know or care how identity was resolved.

#### Session Storage

- [x] `Session` — structured object with `identityId()`, `flash()`, `csrfToken()`, `regenerate()`, `invalidate()`. Not a key-value bag. Includes `SessionId` (crypto-secure hex ID) and `CsrfToken` (constant-time comparison) value objects.
- [x] `SessionDriver` interface — `read(string $id): array`, `write(string $id, array $data, int $ttl): void`, `destroy(string $id): void`.
- [x] `CacheSessionDriver` — delegates to any `CacheInterface` (Vault). Prefixed keys. Enables Redis/APCu/file session storage via existing cache drivers. The `file` driver config wires Vault's `FileDriver` under the hood.
- [x] `CookieSessionDriver` — encrypted client-side sessions via `Encryptor`. In-memory buffer + encrypt/decrypt for cookie transport.
- [x] `SessionConfig` — value object holding cookie name, lifetime, path, domain, Secure, HttpOnly, SameSite. Builds Set-Cookie headers.
- [x] `ActiveSession` — request-scoped holder. Middleware writes, downstream reads.
- [x] Tests: SessionId (7), CsrfToken (6), Flash (8), Session (14), ActiveSession (3), SessionConfig (5), CacheSessionDriver (4), CookieSessionDriver (6). 53 core tests.

#### HTTP Middleware

- [x] `SessionMiddleware` (PSR-15) — reads session ID from cookie, hydrates `Session` via driver, writes back on response. Handles ID generation, regeneration, invalidation, cookie attributes. Probabilistic GC (1% per request). Cookie driver handled specially (encrypt/decrypt).
- [x] `CsrfMiddleware` (PSR-15) — validates CSRF token on POST/PUT/PATCH/DELETE. Reads from `_token` body field or `X-CSRF-TOKEN` header. Skips Bearer token requests. Rejects with **403 Forbidden**.
- [x] Tests: SessionMiddleware (7), CsrfMiddleware (12). 19 middleware tests.

#### Flash Messages

- [x] `Flash` — write-once read-once message bag. Constructor receives "next" data from previous request as "current". `set()` queues for next request. `pending()` returns data to persist.
- [x] Available to query/page handlers via `ActiveSession` container injection.

#### Bootstrap & Wiring

- [x] `Bootstrap\Sessions` — reads `config/session.php`, registers `SessionDriver`, `SessionConfig`, `ActiveSession` in container. Supports file/cache/cookie drivers. CLI kernels skip entirely.
- [x] `SessionMiddleware` and `CsrfMiddleware` registered in `Bootstrap\Middleware` as outermost framework middleware.
- [x] Added `Bootstrap\Sessions` to `HyperKernel::$bootstrappers` after Cache, before Routing.
- [x] Tests: Bootstrap\Sessions (6). Existing MiddlewareTest and HyperKernelTest updated.

#### Documentation

- [x] Package README — design philosophy (structured not generic, HTTP-only, no handler access), drivers, CSRF, flash messages, configuration, session lifecycle.

### 6. Authentication & Authorization — new package

Auth splits into two distinct concerns with different architectural homes:

- **Authentication** (who are you?) — transport-layer. Resolves `Identity` from the request before routing. Lives in PSR-15 middleware (HTTP) or a resolver (CLI).
- **Authorization** (can you do this?) — domain-layer. Checks permissions on the DTO before the handler runs. Lives in Conveyor before-middleware, same pattern as `TransportGuard` and `ValidationGuard`.

Depends on: security primitives (hashing, encryption), sessions (for `SessionGuard`), validation. Persistence layer enhances it (user storage) but isn't strictly required — token-based auth works without a database.

**Key design decisions:**

- **`Identity` is a domain interface, guards are transport.** Handlers receive `Identity` via the container. They never know whether it came from a session cookie, JWT, API key, or CLI `--token` flag.
- **Authentication does NOT reject requests.** The `AuthMiddleware` resolves identity and registers it (or leaves it absent). It never short-circuits with 401. That's authorization's job — some routes are public.
- **Authorization is a Conveyor `Progression`.** `AuthorizationGuard` reads `#[RequiresAuth]` / `#[RequiresRole]` attributes from the DTO class and checks against the resolved identity. Unauthenticated + `#[RequiresAuth]` → 401. Authenticated but wrong role → 403. No attribute → public, passes through.
- **`ActiveIdentity` follows the `ActiveSession` pattern.** A request-scoped holder registered as a singleton. Auth middleware writes, authorization guard and handlers read. Same late-binding pattern.
- **Guards are composable.** `CompositeGuard` tries multiple guards in order (session, then token). Apps configure which guards to use via `config/auth.php`.
- **CLI auth is token-based only.** No sessions. Reads `--token` flag or `ARCANUM_TOKEN` env var. Resolves via a configurable closure (app provides the lookup). Browser-based CLI auth (OAuth device flow) deferred.

**The layering:**

```
HTTP Request
  → SessionMiddleware (reads session)
  → AuthMiddleware (PSR-15) — calls Guard, writes ActiveIdentity
  → CsrfMiddleware (validates CSRF)
  → App middleware
  → Atlas routes → DTO hydrated → Conveyor dispatches
    → AuthorizationGuard (Progression) — reads #[RequiresAuth]/#[RequiresRole], checks ActiveIdentity
    → ValidationGuard → Handler
```

```
CLI Command
  → CliAuthResolver — reads --token/env var, writes ActiveIdentity
  → Rune routes → DTO hydrated → Conveyor dispatches
    → AuthorizationGuard — same check, same attributes, same Identity
    → ValidationGuard → Handler
```

#### Identity — domain value object

- [x] `Identity` interface — `id(): string`, `roles(): list<string>`. Transport-agnostic domain representation.
- [x] `SimpleIdentity` — concrete implementation with `string $id, list<string> $roles = []`.
- [x] `ActiveIdentity` — request-scoped holder. `set()`, `get()` (throws), `resolve()` (returns null), `has()`.
- [x] Tests: SimpleIdentity (3), ActiveIdentity (4). 7 tests.

#### Guard Interface and Built-in Guards

- [x] `Guard` interface — `resolve(ServerRequestInterface $request): Identity|null`.
- [x] `SessionGuard` — reads `ActiveSession`, calls resolver closure to look up identity.
- [x] `TokenGuard` — reads `Authorization: Bearer <token>`, calls resolver closure.
- [x] `CompositeGuard` — tries `Guard ...$guards` in order, returns first non-null.
- [x] Tests: SessionGuard (4), TokenGuard (5), CompositeGuard (3). 12 tests.

#### JWT Support — deferred

JWT (`JwtGuard`) is deferred. `TokenGuard` with a closure that decodes JWTs is the escape hatch — apps bring their own JWT library and wire it in the resolver closure.

#### AuthMiddleware — HTTP transport (PSR-15)

- [x] `AuthMiddleware` — calls `guard->resolve($request)`, writes to `ActiveIdentity`. Never short-circuits.
- [x] Middleware ordering: after `SessionMiddleware`, before `CsrfMiddleware` in `Bootstrap\Middleware`.
- [x] Tests: resolves and sets identity, leaves empty when null, always delegates. 3 tests.

#### CliAuthResolver — CLI transport

- [x] `CliAuthResolver` — reads `--token` option or `ARCANUM_TOKEN` env var, calls resolver closure, writes `ActiveIdentity`.
- [x] Tests: resolves from token, from env, token precedence, no token, null resolver. 5 tests.

#### Authorization Attributes

- [x] `#[RequiresAuth]` — class-level marker attribute.
- [x] `#[RequiresRole(string ...$roles)]` — class-level, implies auth, roles OR'd.
- [x] `#[RequiresPolicy(PolicyClass::class)]` — class-level, repeatable, implies auth.

#### AuthorizationGuard — Conveyor before-middleware

- [x] `AuthorizationGuard` — `Progression` implementation. Resolves DTO class (handles `HandlerProxy`), reflects for `#[RequiresAuth]`, `#[RequiresRole]`, `#[RequiresPolicy]`. No attributes → passes. Missing identity → 401. Wrong role → 403. Policy denied → 403.
- [x] Injected: `ActiveIdentity`, `Transport`, `ContainerInterface` (for policy resolution).
- [x] Tests: public passes, auth passes/fails, role passes/fails, multi-role, policy passes/denies, HandlerProxy, non-existent class. 14 tests.

#### Policies — complex authorization

- [x] `Policy` interface — `authorize(Identity $identity, object $dto): bool`.
- [x] `#[RequiresPolicy]` attribute — points to `Policy` implementation, repeatable, all must pass.
- [x] Integrated into `AuthorizationGuard` — resolved from container.

#### Error Rendering

- [x] HTTP: `HttpException(401)` and `HttpException(403)` render through existing exception renderers. No new code needed.
- [x] CLI: `RuntimeException` caught by `CliExceptionWriter` with clear messages.

#### Bootstrap & Wiring

- [x] `Bootstrap\Auth` — reads `config/auth.php`, registers `ActiveIdentity`, `Identity` factory, `Guard`, `AuthMiddleware`.
- [x] `AuthMiddleware` registered in `Bootstrap\Middleware` after `SessionMiddleware`, before `CsrfMiddleware`.
- [x] `AuthorizationGuard` registered as Conveyor before-middleware in both `Bootstrap\Routing` and `Bootstrap\CliRouting`.
- [x] Added to both `HyperKernel::$bootstrappers` and `RuneKernel::$bootstrappers` after `Cache`/`Sessions`, before `Routing`.
- [x] Existing kernel and middleware tests updated for new counts.

#### CLI Sessions — persistent login

CLI users shouldn't need to pass `--token` on every command. `php arcanum login` authenticates once and persists the credential, just like `gh auth login` or `gcloud auth login`.

**The flow:**

```
$ php arcanum login
Email: stephen@example.com
Password: ••••••••
✓ Authenticated as stephen. Session expires in 24 hours.

$ php arcanum query:auth:whoami          ← no --token needed
{"id":"user-1","roles":["admin"]}

$ php arcanum logout
✓ CLI session cleared.
```

**Priority chain for `CliAuthResolver`:**
1. `--token` flag (explicit override for scripts, always wins)
2. Stored CLI session file (persistent login)
3. `ARCANUM_TOKEN` env var (CI/automation)

**Design decisions:**

- **Credential file is project-local** (`files/.cli-session`), not user-home. Different projects have different users. Gitignored automatically (lives inside `files/`).
- **Encrypted at rest** via the framework's `Encryptor` (APP_KEY). Contains the identity ID + expiry timestamp, not the raw credentials.
- **`login` accepts credentials, not tokens.** The app provides a `credentials` resolver closure that takes input fields and returns `Identity|null`. The `login` command prompts for those fields, validates via the resolver, and stores the result. `--token` remains the escape hatch for scripts.
- **Interactive input is new to Rune.** The `Prompter` is a minimal stdin reader: `ask(string $label): string` and `secret(string $label): string` (disables echo). Not a full TUI — just enough for login.

##### Rune Interactive Input

- [ ] `Prompter` — reads from stdin. `ask(string $label): string` writes label to output, reads line from stdin. `secret(string $label): string` same but disables terminal echo (`stty -echo` / restore). Constructor takes `Output` and optional stdin stream for testing.
- [ ] Tests: ask returns trimmed input, secret disables echo (mock-based, since stty isn't testable in PHPUnit), empty input returns empty string. ~4 tests.

##### CLI Session Storage

- [ ] `CliSession` — encrypted file-based credential store. Constructor takes `Encryptor`, `string $path` (default `files/.cli-session`), plus Parchment `Reader`/`Writer`/`FileSystem` for I/O.
  - `store(string $identityId, int $ttl): void` — encrypts `{id, expiry}` as JSON, writes to file.
  - `load(): string|null` — reads file, decrypts, checks expiry. Returns identity ID or null. Deletes file if expired or corrupt.
  - `clear(): void` — deletes the file.
- [ ] Tests: store/load round-trip, expired returns null and deletes file, corrupt file returns null, clear deletes file, missing file returns null. ~6 tests.

##### Login Command

- [ ] `LoginCommand` — built-in Rune command. Reads `config/auth.php` key `login.fields` (default: `['email', 'password']`). For each field, prompts via `Prompter` (fields named `password`, `secret`, or `token` use `secret()`). Calls `resolvers.credentials` closure with the collected fields. On success: stores identity ID via `CliSession`, prints confirmation. On failure: prints error, exit code 1.
- [ ] Config structure for login:
  ```php
  // config/auth.php
  'login' => [
      'fields' => ['email', 'password'],  // prompt labels, in order
      'ttl' => 86400,                      // 24 hours
  ],
  'resolvers' => [
      'credentials' => fn(string $email, string $password) => /* validate, return Identity|null */,
  ],
  ```
- [ ] The credentials resolver receives positional args matching the field order. `fn(string $email, string $password)` maps to `fields: ['email', 'password']`.
- [ ] Tests: successful login stores session and prints confirmation, failed login prints error with exit code 1, fields are prompted in order, password fields use secret(). ~5 tests.

##### Logout Command

- [ ] `LogoutCommand` — built-in Rune command. Calls `CliSession::clear()`. Prints confirmation. Always exit code 0.
- [ ] Tests: clears session, prints message. ~2 tests.

##### CliAuthResolver Update

- [ ] Update `CliAuthResolver` to check stored CLI session between `--token` and env var:
  1. `--token` flag → call token resolver
  2. `CliSession::load()` → if non-null, call identity resolver (same as `SessionGuard` uses)
  3. `ARCANUM_TOKEN` env var → call token resolver
- [ ] `CliAuthResolver` constructor gains optional `CliSession` and identity resolver closure.
- [ ] Tests: stored session resolves, expired session falls through to env, --token overrides stored session, all three sources work in priority order. ~4 tests.

##### Bootstrap & Wiring

- [ ] `Bootstrap\Auth` registers `CliSession` for CLI kernels (with `Encryptor` and file path from `Kernel::filesDirectory()`).
- [ ] `Bootstrap\Auth` passes `CliSession` and identity resolver to `CliAuthResolver`.
- [ ] `Bootstrap\CliRouting` registers `LoginCommand` and `LogoutCommand` in `BuiltInRegistry`.
- [ ] `LoginCommand` factory provides `Prompter`, `CliSession`, credentials resolver, login config.
- [ ] Tests: CliSession registered, login/logout commands registered, resolver has session support. ~3 tests.

##### Documentation

- [ ] Update Auth README — CLI sessions section: login/logout flow, credential config, TTL, priority chain.
- [ ] Update Rune README — Prompter, login/logout in built-in commands list.

##### Deferred

- Browser-based login (`php arcanum login --browser` → opens OAuth flow, local callback server). Complex, niche. The credential-based flow covers the common case.
- Interactive credential types beyond string fields (MFA codes, key files). Can extend `login.fields` config later.

#### Starter App

- [ ] `config/auth.php` — default config with session guard, token guard, placeholder resolvers, login fields.
- [ ] Example `#[RequiresAuth]` on a DTO.

#### Documentation

- [x] Auth package README — design philosophy, Identity, guards, authorization attributes, policies, CLI auth, configuration.
- [ ] Updated `src/Ignition/README.md` — `Bootstrap\Auth` in bootstrapper tables.
- [ ] Updated `src/Flow/Conveyor/README.md` — `AuthorizationGuard` alongside `TransportGuard` and `ValidationGuard`.

### 7. HTTP Client — PSR-18 wrapper

Standalone, low effort. Useful for handlers that call external APIs. No other framework feature depends on it.

A thin PSR-18 (`ClientInterface`) wrapper around an established HTTP client (likely Guzzle or Symfony HttpClient). The wrapper provides:

- PSR-18 compliance so app code depends on the interface, not the underlying library
- A testable fake/mock client for testing (record requests, return canned responses)
- Convenient factory methods for common patterns (JSON APIs, bearer auth, retries)

The goal is not to build an HTTP client from scratch — it's to provide a consistent, testable interface that integrates with the container and can be swapped in tests.

### 8. Custom App Scripts — Rune extension

Small extension to Rune's built-in registry. Standalone. Nice to have once people are building real apps.

Allow app developers to define their own CLI scripts that have access to the bootstrapped framework container but live outside the `command:`/`query:` domain dispatch. Similar to Rune's built-in commands, but defined by the application.

Use case: operational scripts like `db:seed`, `cache:warm`, `deploy:check` — things that need the container (config, logging, database connections) but aren't domain commands. These would be registered via config or a kernel method, run without a `command:`/`query:` prefix, and show up in `php arcanum list` under an "App commands" section.

This is distinct from built-in framework commands (which ship with Rune) and domain commands (which go through the full CQRS dispatch). It fills the gap for application-level tooling.

### 9. Persistence Layer — Forge

SQL is a first-class citizen in Arcanum. There is no ORM, no query builder, no active record, and no SQL strings in PHP. `.sql` files are code — they live in domain `Model/` directories and become methods on a dynamic `Model` object. The developer writes SQL files and calls them as methods; the framework handles connections, parameter binding, execution, and result shaping.

**What the developer writes:**

```
app/Domain/Shop/Model/
    Products.sql              ← $db->model->products([...])
    ProductById.sql           ← $db->model->productById([...])
    InsertOrder.sql           ← $db->model->insertOrder([...])
    UpdateOrderStatus.sql     ← $db->model->updateOrderStatus([...])
    DeductInventory.sql       ← $db->model->deductInventory([...])

app/Domain/Shop/Query/
    Products.php              ← route DTO
    ProductsHandler.php       ← handler (uses $db->model)

app/Domain/Shop/Command/
    PlaceOrder.php            ← route DTO
    PlaceOrderHandler.php     ← handler (uses $db->model)
```

```php
final class ProductsHandler
{
    public function __construct(private readonly Database $db) {}

    public function __invoke(Products $dto): array
    {
        return $this->db->model->products([
            'category' => $dto->category,
        ])->rows();
    }
}
```

```php
final class PlaceOrderHandler
{
    public function __construct(private readonly Database $db) {}

    public function __invoke(PlaceOrder $dto): void
    {
        $this->db->transaction(function (Database $db) use ($dto) {
            $db->model->insertOrder([
                'userId' => $dto->userId,
                'total' => $dto->total,
            ]);

            $db->model->insertOrderItem([
                'orderId' => $db->lastInsertId(),
                'productId' => $dto->productId,
                'quantity' => $dto->quantity,
            ]);

            $db->model->deductInventory([
                'productId' => $dto->productId,
                'quantity' => $dto->quantity,
            ]);
        });
    }
}
```

**What the developer never writes:** connection strings, repository classes, model/entity classes, query builder chains, SQL strings in PHP, or migration PHP classes.

**Design decisions:**

- **SQL file names are method names.** `Products.sql` → `$db->model->products(...)`. `InsertOrder.sql` → `$db->model->insertOrder(...)`. PascalCase file names become camelCase methods. The directory is the object, the file is the method.
- **No raw SQL strings in the API.** The `Database` service never accepts SQL strings. Every query is a `.sql` file called as a method. If you need SQL, make a file.
- **Every call returns a `Result`.** The developer picks the shape: `->rows()`, `->first()`, `->scalar()`, `->isEmpty()`, `->affectedRows()`, `->lastInsertId()`. No return-type guessing from file name prefixes.
- **Read/write routing from SQL content.** The framework inspects the SQL to determine which connection to use. `SELECT` → read connection. `INSERT`/`UPDATE`/`DELETE` → write connection. The developer doesn't think about it.
- **Model directory is domain-scoped.** `app/Domain/Shop/Model/` contains all SQL for the Shop bounded context. Shared queries live in a shared domain directory (e.g., `app/Domain/Shared/Model/`). Not table-scoped — a Model directory can query across tables.
- **Parameters are named arrays.** `$db->model->products(['category' => 'shoes'])` binds `:category` in the SQL. Simple, no reflection on DTO constructors — just an associative array.
- **Transactions are explicit.** `$db->transaction(fn(Database $db) => ...)` wraps a closure.
- **Multiple connections out of the box.** `config/database.php` defines named connections with optional read/write split.

#### Connection Management

- [ ] `Connection` — thin PDO wrapper. Constructor takes DSN, username, password, options. Lazy-connects on first use. Methods: `run(string $sql, array $params): Result` — executes the SQL, returns a `Result` regardless of query type. `beginTransaction(): void`, `commit(): void`, `rollBack(): void`. Uses PDO named parameters and prepared statements. Sets `ERRMODE_EXCEPTION`, `FETCH_ASSOC` defaults. Tracks `lastInsertId()`.
- [ ] `ConnectionFactory` — creates `Connection` instances from config arrays. Supports drivers: `mysql` (charset, collation), `pgsql`, `sqlite` (file path or `:memory:`). Builds DSN string from config keys (`host`, `port`, `database`, `unix_socket`, etc.).
- [ ] `ConnectionManager` — manages named connections. Lazy instantiation (like `CacheManager`). `connection(string $name = ''): Connection` returns named or default. `readConnection(): Connection` returns the read replica if configured, otherwise the default. `writeConnection(): Connection` always returns the write primary.
- [ ] Tests: Connection run returns Result, prepared statement parameter binding, transaction commit/rollback, lazy connection, ConnectionFactory builds correct DSN for each driver, ConnectionManager named connections, read/write split resolution, default fallback. ~18 tests.

#### Configuration

- [ ] `config/database.php` structure:
  ```php
  return [
      'default' => 'mysql',
      'connections' => [
          'mysql' => [
              'driver' => 'mysql',
              'host' => env('DB_HOST', '127.0.0.1'),
              'port' => env('DB_PORT', 3306),
              'database' => env('DB_DATABASE', 'arcanum'),
              'username' => env('DB_USERNAME', 'root'),
              'password' => env('DB_PASSWORD', ''),
              'charset' => 'utf8mb4',
              'collation' => 'utf8mb4_unicode_ci',
          ],
          'sqlite' => [
              'driver' => 'sqlite',
              'database' => 'database.sqlite',  // relative to files/
          ],
      ],
      'read' => null,   // connection name for read replica, or null
      'write' => null,   // connection name for write primary, or null
  ];
  ```

#### Result

- [ ] `Result` — wraps the raw PDO result with convenience accessors. Constructed with the row data, affected row count, and last insert ID.
  - `rows(): array` — all rows as associative arrays.
  - `first(): array|null` — first row or null if empty.
  - `scalar(): mixed` — first column of first row. Throws if empty.
  - `isEmpty(): bool` — whether zero rows were returned/affected.
  - `affectedRows(): int` — number of rows affected (writes).
  - `lastInsertId(): string` — last auto-increment ID (inserts).
  - `count(): int` — number of rows returned.
- [ ] Tests: rows returns all, first returns first row, first returns null on empty, scalar returns single value, scalar throws on empty, isEmpty, affectedRows, lastInsertId, count. ~10 tests.

#### Model — SQL File Resolution and Execution

- [ ] `Model` — dynamic object that maps method calls to SQL files. Constructed with a directory path (the Model directory), a `Connection` reference (read and write), and a SQL file reader.
  - `__call(string $method, array $args): Result` — converts method name from camelCase to PascalCase file name (`insertOrder` → `InsertOrder.sql`). Reads the SQL file. Binds named parameters from `$args[0]` (the associative array). Inspects SQL content to determine read vs write connection. Executes. Returns `Result`.
  - Throws `RuntimeException` if the SQL file doesn't exist (clear message: "Model method 'insertOrder' not found — expected file: .../Model/InsertOrder.sql").
  - SQL file contents cached in memory per-request.
- [ ] SQL content inspection for connection routing: scans first non-comment, non-whitespace SQL token. `SELECT` → read connection. Everything else → write connection. Comment lines (`-- ...`) are skipped when determining the SQL operation type.
- [ ] **Type casting via SQL file annotations.** The framework parses `-- @cast` comment headers in SQL files and applies type coercion to each result row before returning.
  ```sql
  -- @cast price float
  -- @cast in_stock bool
  -- @cast quantity int
  -- @cast metadata json
  SELECT id, name, price, in_stock, quantity, metadata
  FROM products
  WHERE category = :category
  ```
  Supported casts:
  - `int` — `(int)` cast
  - `float` — `(float)` cast
  - `bool` — truthy/falsy normalization (`'t'`/`'f'`, `'1'`/`'0'`, `1`/`0` → `true`/`false`)
  - `json` — `json_decode($value, true)`
  
  Annotations are optional — unannotated columns return whatever PDO gives (strings for most drivers). Parsed once per SQL file and cached with the file content. Applied only to read results (`SELECT`), not to write operations.
- [ ] Tests: method call resolves to correct SQL file, camelCase→PascalCase conversion, parameters bound as `:named`, SELECT uses read connection, INSERT/UPDATE/DELETE use write connection, missing file throws with helpful message, file content cached, parameterless SQL works (empty array), @cast int, @cast float, @cast bool (all driver variants), @cast json, no @cast returns raw PDO values, multiple @cast annotations, @cast on write query ignored. ~16 tests.

#### Database Service

- [ ] `Database` — the developer-facing service. Constructor takes `ConnectionManager` and a domain root path (from Kernel config).
  - `model` property (via `__get`) — returns a `Model` object scoped to the current domain's `Model/` directory. The domain is determined from the DTO's namespace, set by the Conveyor dispatch pipeline before the handler runs.
  - `transaction(\Closure $callback): mixed` — begins transaction on write connection, calls `$callback($this)`, commits. Rolls back on exception and rethrows.
  - `lastInsertId(): string` — delegates to write connection.
- [ ] **Domain context: bounded and automatic.** The Conveyor dispatch extracts the domain from the DTO's namespace (`App\Domain\Shop\Command\PlaceOrder` → `Shop`) and sets it on the `Database` service before calling the handler. `$db->model` resolves to `app/Domain/Shop/Model/`. No cross-domain access — a handler in `Shop` cannot call SQL from `Users`. If a handler needs data from another domain, it dispatches a query through the Conveyor bus (the domain's public API).
- [ ] `DomainContext` — request-scoped value holder (same pattern as `ActiveIdentity`). Set by Conveyor dispatch, read by `Database`. Methods: `set(string $domain)`, `get(): string`, `modelPath(): string`.
- [ ] Conveyor integration: the `MiddlewareBus` (or a new Conveyor middleware) extracts the domain segment from the DTO's namespace and writes it to `DomainContext` before the handler runs. The domain is the namespace segment after the configured root (`App\Domain\`) and before `Command\`/`Query\` — e.g., `App\Domain\Shop\Command\PlaceOrder` → `Shop`, `App\Domain\Admin\Users\Query\List` → `Admin\Users`.
- [ ] Tests: model property returns Model scoped to domain, transaction commits, transaction rolls back, lastInsertId delegates, domain context set from DTO namespace, cross-domain access not possible. ~8 tests.

#### Bootstrap & Wiring

- [ ] `Bootstrap\Database` bootstrapper — reads `config/database.php`, creates `ConnectionManager`, `DomainContext`, `Database`. Registers all in container. Configures domain root path from Kernel.
- [ ] Added to `HyperKernel::$bootstrappers` and `RuneKernel::$bootstrappers` — after `Auth`, before `Routing`.
- [ ] Conveyor domain context middleware registered in both `Bootstrap\Routing` and `Bootstrap\CliRouting` — sets `DomainContext` from DTO namespace before handler dispatch.
- [ ] Tests: registers Database in container, ConnectionManager configured from config, works with no config (skips gracefully for apps without a database), domain context middleware sets domain from DTO. ~5 tests.

#### CLI Commands

- [ ] `db:status` built-in Rune command — shows configured connections, default connection, read/write split, tests connectivity (tries to connect, reports success or error), lists discovered Model directories and SQL file counts.
- [ ] Tests: shows connection info, reports connection error gracefully. ~2 tests.

#### Migrations — deferred (needs design discussion)

⚠️ **Not part of the MVP.** Migrations are about schema evolution, not runtime queries. The Forge MVP focuses on making SQL-file-driven reads and writes work. Migrations will be designed separately.

Key questions for the migration design conversation:
- **File naming:** timestamp-based (`2026_04_02_001_create_products.sql`) vs sequential?
- **Up/down:** single file with delimiter (`-- DOWN`) or paired files (`.up.sql` / `.down.sql`)?
- **Location:** `database/migrations/` or alongside domain code?
- **Runner:** CLI commands (`migrate`, `migrate:rollback`, `migrate:status`).
- **Tracking:** migration state table in the database itself (`_migrations`).
- **Seeding:** separate concern? `.sql` seed files?

#### Documentation

- [ ] `src/Forge/README.md` — philosophy (SQL as first-class citizen, no ORM, no query builder, no raw strings), Database/Model API, SQL file conventions, Result shaping, parameter binding, transactions, connections, configuration.
- [ ] Updated `src/Ignition/README.md` — `Bootstrap\Database` in bootstrapper tables.
- [ ] Updated root `README.md` — Forge package description.

### 10. Template Helpers — Shodo extension

Reverse routing is the valuable piece, but it depends on conventions that may shift as persistence lands. Formatting helpers are trivial and could ship earlier as a subset.

A library of helper functions exposed to the template engine. Templates currently have access to `{{ $variable }}` and control flow, but lack common utilities for formatting and URL generation.

Potential helpers:
- **URL/routing** — `{{ url('query:health') }}` reverse routing, `{{ asset('css/app.css') }}` asset paths
- **Formatting** — `{{ number($price, 2) }}`, `{{ date($timestamp, 'M j, Y') }}`, `{{ truncate($text, 100) }}`
- **Collections** — `{{ count($items) }}`, `{{ join($items, ', ') }}`, `{{ first($items) }}`
- **Security** — `{{ csrf() }}` token field, `{{ nonce() }}` for CSP
- **Conditionals** — `{{ class_if($active, 'selected') }}` for HTML class toggling

These could live as a Shodo subpackage or a dedicated helpers package. Some underlying utilities (number formatting, array projections) would live in Toolkit. The template engine would need a mechanism to register helper functions that are available in all templates — possibly a `HelperRegistry` that injects functions alongside `$__escape` during template execution.

Reverse routing (URL generation from a DTO class or route name) is particularly important — it's needed by both templates and the deferred `Location` header for 201 responses.

### 11. Starter App — shipped middleware and styling

Most valuable after auth and persistence exist — that's when the starter app becomes a real demo. Rate limiter is more useful with cache-backed counters.

**Rate limiting middleware** — ships as an example in the starter app's `app/Http/Middleware/`. Configurable limits per route or globally. Demonstrates how to write middleware that short-circuits with **429 Too Many Requests**.

**Request logging middleware** — ships as an example. Logs request method, path, status code, and duration. Demonstrates the middleware onion model (starts a timer on the way in, logs on the way out).

**Default CSS and styling** — a minimal CSS file and base HTML layout that ships with the starter app (not the framework). Makes the default pages and error screens look presentable out of the box. Lives in `public/css/` and is referenced by the page templates. This is purely a starter app concern — the framework has no opinion on styling.

### Deferred — Command Response Enhancements

Blocked on persistence layer + reverse routing:

- **`Location` header for 201 responses** — requires reverse routing (URL generation from a class/identifier) and a persistence layer. In CQRS, commands and queries live at different paths, so there's no canonical resource URL. Revisit after: persistence layer, reverse routing, and a convention linking Commands to their corresponding Queries.
- **Integration tests for 202/201 in Kernel** — straightforward once the Location header is settled.

---

## Long-Distance Future

Ideas worth exploring eventually, but not on the near-term roadmap:

- **Queue/Job system** — async processing with drivers (Redis, database, SQS), failed job tracking, retries, rate limiting. Could start with a synchronous dispatcher (useful for the interface) and add async drivers later.
- **Testing utilities** — DTO factories, service fakes (cache, mail, queue), a `TestKernel` for in-memory integration tests. Arcanum's strict test coverage culture means developers will want good test helpers.
- **Internationalization** — translation strings, locale detection, pluralization. Important for multi-language apps but not every app needs it.
- **Task scheduling** — `php arcanum schedule:run` invoked by a single cron entry, dispatches scheduled tasks defined in code.
- **Mail/Notifications** — thin wrappers or integration with established libraries (Symfony Mailer). Multi-channel notifications (Slack, SMS) are niche.

---

## Performance Notes

### Reflection caching — explored and rejected

We built a reflection caching layer ("Mirror") that memoized `ReflectionClass` instances, constructor parameters, attributes, and property/method metadata. Three variants were benchmarked under production-like conditions (nginx + PHP-FPM + opcache + JIT, 10k requests, concurrency 20):

1. **In-memory static cache** — same `ReflectionClass` instance reused within a request
2. **Flyweight facade** — `ClassReflector`/`ParameterReflector` wrappers with eager caching, no raw reflection leakage
3. **APCu persistence** — serialized reflection data cached across requests, zero reflection on warm cache

**Results:**

| Variant | req/s (3-field DTO) | req/s (10-field DTO) |
|---|---|---|
| No caching (raw reflection) | ~301 | ~298 |
| In-memory cache | ~304 | ~300 |
| Flyweight facade | ~304 | ~300 |
| APCu persistence | ~301 | not tested |

**Conclusion: PHP 8.4's reflection with opcache + JIT is already fast enough that caching it produces no measurable improvement.** The ~300 req/s ceiling is dominated by something other than reflection — likely FPM process overhead, FastCGI protocol, JSON parsing, container resolution, or the handler itself. Mirror was reverted.

**Open question:** Going from 3 fields to 10 fields drops throughput from ~1,300 req/s (early tests) to ~300 req/s — a 77% drop for 3.3x more fields. This is far worse than linear and worth investigating with a real profiler (Xdebug, SPX, or Blackfire).

### Benchmark harness

Quick nginx + PHP-FPM benchmark setup for future performance testing:

```bash
# Temp config directory
BD=$(mktemp -d /tmp/arcanum_bench.XXXXXX)

# nginx.conf
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

# php-fpm.conf
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

# Start, warm, bench, tear down
php-fpm --fpm-config "$BD/php-fpm.conf" && nginx -c "$BD/nginx.conf"
for i in {1..200}; do curl -s http://127.0.0.1:8299/health.json > /dev/null; done
ab -n 10000 -c 20 -q -p body.json -T "application/json" http://127.0.0.1:8299/contact/submit
nginx -s stop; kill $(cat "$BD/php-fpm.pid"); rm -rf "$BD"
```

Requires: `brew install nginx`, PHP-FPM (ships with Homebrew PHP), Apache Bench (`ab`, ships with macOS).

---

## Closed Questions

Decided — preserved for context:

- ~~**Bootstrap lifecycle hooks**~~ — **Won't do.** The app controls the Kernel subclass and can add, remove, or reorder bootstrappers directly.
- ~~**Handler auto-discovery**~~ — **Won't do for runtime.** Codex resolves handlers on demand via reflection. Boot-time validation is the `validate:handlers` CLI command.
- ~~**Opt-in command response bodies**~~ — **Won't do.** Commands shouldn't respond with data. The Location header (once persistence + reverse routing exist) gives clients a way to fetch the created resource via a proper Query.
- ~~**CLI command prefixes**~~ — `command:` or `query:` prefix for domain dispatch. No prefix = built-in framework command.
- ~~**CLI custom routes vs HTTP custom routes**~~ — Independent configs under `config/routes.php` (`http` and `cli` keys).
- ~~**Renderer return type**~~ — Resolved by Shodo/Hyper refactor. Formatters return strings, ResponseRenderers return ResponseInterface.
- ~~**Shodo renderers are HTTP-specific**~~ — Resolved by Shodo/Hyper refactor.
- ~~**CLI debug mode not respecting config**~~ — Not a bug. Starter app's `.env` sets `APP_DEBUG=true`.
- ~~**SQL query builder**~~ — **Won't do.** SQL is a first-class citizen in Arcanum. The framework provides connection management, parameter binding, and result hydration — but queries are written in SQL (or `.sql` files), not chained method calls.
- ~~**Full ORM / Active Record**~~ — **Won't do.** Active Record fights CQRS (models shouldn't save themselves — commands handle writes). Lightweight entity mapping via repositories instead.
- ~~**WebSocket / real-time**~~ — **Won't do in core.** Too specialized and infrastructure-dependent. Better as an optional add-on package.
- ~~**Asset compilation**~~ — **Won't do.** Vite/esbuild/Webpack are JS tools. The framework has no opinion on frontend build tooling.
- ~~**Full template engine (Blade/Twig competitor)**~~ — **Won't do.** Shodo's `{{ }}` compiler handles lightweight pages. Apps with complex frontend needs will use a JS framework.
- ~~**Reflection caching (Mirror)**~~ — **Won't do.** Benchmarked three approaches (in-memory, flyweight facade, APCu persistence). PHP 8.4 reflection with opcache + JIT is already fast enough — caching produced no measurable throughput improvement. See Performance Notes above.
