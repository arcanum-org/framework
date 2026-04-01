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
| 5 | Session management | 7 | 5 | 8 | Security, Caching |
| 6 | Auth & Authorization | 9 | 7 | 4 | Security, Validation, Sessions |
| 7 | HTTP client | 5 | 2 | 1 | — |
| 8 | Custom app scripts | 5 | 2 | 1 | — |
| 9 | Persistence layer | 10 | 9 | 6 | — (but Auth benefits from it) |
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

PSR-16 defines `CacheInterface` with: `get($key, $default)`, `set($key, $value, $ttl)`, `delete($key)`, `clear()`, `getMultiple()`, `setMultiple()`, `deleteMultiple()`, `has($key)`. Arcanum implements this exactly — no extensions, no extra methods on the interface. App code depends on `CacheInterface`, never a driver class.

- [ ] Add `psr/simple-cache` as a Composer dependency (provides `Psr\SimpleCache\CacheInterface` and the `InvalidArgumentException` interface).
- [ ] `FileDriver` — file-based cache, one file per key in a configurable directory. Value + expiry serialized together (e.g., `serialize(['expiry' => $ts, 'value' => $data])`). On `get()`, check expiry before returning — expired entries return `$default` and are lazily deleted. `clear()` deletes the entire cache directory. Key sanitization: hash the key to produce a safe filename (`md5($key).cache`). This is the default driver — zero config, works on any PHP installation.
- [ ] `ArrayDriver` — in-memory cache backed by a plain PHP array. Data lives only for the current process. Respects TTL by storing expiry timestamps alongside values. Intended for testing — inject `ArrayDriver` in tests to avoid filesystem or network calls. Also useful as a per-request cache layer.
- [ ] `NullDriver` — implements `CacheInterface` but stores nothing. `get()` always returns `$default`, `set()` is a no-op, `has()` returns false. Useful for disabling caching entirely without conditionals (e.g., development mode).
- [ ] `ApcuDriver` — wraps `apcu_fetch`/`apcu_store`/`apcu_delete`/`apcu_clear_cache`. Extremely fast, single-server only. Guard with `extension_loaded('apcu')` check at construction — throw if APCu isn't available. TTL is native to APCu.
- [ ] `RedisDriver` — wraps a `\Redis` connection instance. Uses `get`/`setex`/`del`/`flushDb`. TTL is native to Redis. Constructor takes a `\Redis` instance (the app manages the connection). Requires the `phpredis` extension.
- [ ] Tests for `FileDriver`: set/get round-trip, get returns default for missing key, TTL expiry returns default, delete removes key, clear removes all keys, has() returns true/false correctly, getMultiple/setMultiple/deleteMultiple, invalid key handling, concurrent-safe (file locking or atomic writes).
- [ ] Tests for `ArrayDriver`: same test matrix as FileDriver (PSR-16 contract is identical), plus verify data doesn't survive between test runs (new instance = empty cache).
- [ ] Tests for `NullDriver`: get always returns default, set doesn't throw, has always returns false, clear doesn't throw.
- [ ] Tests for `ApcuDriver`: same PSR-16 test matrix. Skip entire test class if APCu not available (`#[RequiresPhpExtension('apcu')]` or `markTestSkipped`).
- [ ] Tests for `RedisDriver`: same PSR-16 test matrix. Skip if Redis not available. Consider a mock `\Redis` for unit tests vs. integration tests with a real Redis server.

#### Key Validation

PSR-16 defines key constraints: keys must be strings, at least one character, and must not contain `{}()/\@:` characters. Drivers must throw `Psr\SimpleCache\InvalidArgumentException` for invalid keys.

- [ ] `KeyValidator` — shared static utility or base class method. Validates key against PSR-16 rules. Throws `InvalidArgument` (Arcanum class implementing `Psr\SimpleCache\InvalidArgumentException`) on violation. Called by every driver before any operation.
- [ ] `InvalidArgument` exception — implements `Psr\SimpleCache\InvalidArgumentException`. This is required by PSR-16 — the spec mandates that implementing libraries throw their own exception class that implements the PSR interface.
- [ ] Tests: valid keys pass, empty string rejected, keys with reserved characters rejected, non-string keys rejected (in PHP 8 this is a type error, but the PSR-16 spec predates strict types).

#### Cache Manager

- [ ] `CacheManager` — factory/registry for named cache stores. Reads `config/cache.php` to build driver instances. The app interacts with named stores: `$cache->store('redis')` returns the Redis driver, `$cache->store()` (no argument) returns the default store. Stores are lazily instantiated from config.
- [ ] Config structure in `config/cache.php`:
  ```php
  return [
      'default' => 'file',
      'stores' => [
          'file'  => ['driver' => 'file', 'path' => 'files/cache/app'],
          'array' => ['driver' => 'array'],
          'redis' => ['driver' => 'redis', 'host' => '127.0.0.1', 'port' => 6379],
          'apcu'  => ['driver' => 'apcu'],
      ],
  ];
  ```
- [ ] Tests: resolves default store, resolves named stores, throws on unknown store name, lazily instantiates (doesn't create Redis connection until requested).

#### Prefix Scoping

- [ ] `PrefixedCache` — decorator that prepends a namespace prefix to all keys. Wraps any `CacheInterface` instance. Useful for isolating framework caches from app caches on the same driver (e.g., `framework:pages:` prefix vs. `app:sessions:` prefix). `clear()` only clears keys with the prefix (for file/array drivers) or delegates to the inner driver's `clear()` (for Redis/APCu where prefix-scoped clearing isn't practical without `SCAN` — document this limitation).
- [ ] Tests: get/set/delete prepend prefix, clear behavior, inner driver receives prefixed keys.

#### Store Assignment — framework and app caches

Different caches have different performance and durability requirements. The config lets both the framework and the app assign specific stores to specific purposes.

- [ ] Framework store mapping in `config/cache.php` — maps framework cache names to store names:
  ```php
  'framework' => [
      'pages'      => 'file',    // page discovery — durable, survives restarts
      'middleware' => 'file',    // middleware discovery — same
      'templates'  => 'file',   // template compilation directory (path only, not PSR-16)
  ],
  ```
  Bootstrappers read this mapping and resolve the named store for each framework cache. An app can move discovery caches to APCu for speed (`'pages' => 'apcu'`) or Redis for shared deployments without touching framework code.
- [ ] App developers use `CacheManager::store($name)` to get different drivers for different concerns:
  ```php
  // In a handler or service
  $userCache = $cache->store('redis');      // fast, distributed
  $reportCache = $cache->store('file');     // large payloads, durable
  $requestCache = $cache->store('array');   // per-request only
  ```
  The default store (`$cache->store()` or typehinting `CacheInterface`) covers the common case. Named stores cover everything else. No new classes needed — this is just `CacheManager` doing its job.
- [ ] Tests: framework store mapping resolves correct driver per cache name, falls back to default store when mapping key is absent.

#### Bootstrap & Wiring

- [ ] `Bootstrap\Cache` bootstrapper in Ignition — reads `config/cache.php`, builds `CacheManager`, registers it in the container. Also registers the default store as `CacheInterface` so apps can typehint the PSR-16 interface directly. Slot after `Configuration` in both kernel bootstrapper lists.
- [ ] Add `Bootstrap\Cache` to `HyperKernel::$bootstrappers` and `RuneKernel::$bootstrappers`.
- [ ] Starter app: update `config/cache.php` with `default`, `stores`, and `framework` mapping structure.
- [ ] Tests: bootstrapper registers `CacheManager` and `CacheInterface`, default store is resolvable, framework store mapping is respected.

#### Migrate Framework Ad-hoc Caches

Four existing caches currently use bespoke file-based caching. Once Vault exists, they can optionally use it for unified driver selection and cache clearing.

- [ ] Evaluate `ConfigurationCache` — currently serialized PHP array via Parchment. This cache is special: it must work *before* the container is built (it's read during `Bootstrap\Configuration`). It cannot depend on the CacheManager (which is registered *after* Configuration). **Decision: leave as-is.** ConfigurationCache is a bootstrap primitive, not an app cache. Document why it stays independent.
- [ ] Evaluate `TemplateCache` — compiled PHP per template, mtime-based freshness. This is content-addressed (key = hash of template path) with file-specific freshness (mtime comparison, not TTL). A generic PSR-16 cache doesn't support mtime freshness natively. **Decision: leave as-is but make the cache directory configurable via the `framework.templates` store mapping in config.** The template cache is a specialized compilation cache, not a generic key-value store. Unify the directory configuration so `cache:clear` knows about it.
- [ ] Migrate `PageDiscovery` cache — currently a single `var_export`ed file with TTL. This fits PSR-16 well: `$cache->set('page_discovery', $pages, $ttl)`. Refactor to accept `CacheInterface` instead of managing its own file. Use `PrefixedCache` to namespace it. The bootstrapper reads `config.cache.framework.pages` to resolve which store to use.
- [ ] Migrate `MiddlewareDiscovery` cache — same pattern as PageDiscovery. Reads `config.cache.framework.middleware` for store selection.
- [ ] Update `Bootstrap\Routing` and `Bootstrap\RouteMiddleware` to resolve the framework store for each cache and pass the `CacheInterface` instance to PageDiscovery and MiddlewareDiscovery.
- [ ] Tests: PageDiscovery and MiddlewareDiscovery work with ArrayDriver in tests, produce same results as before migration, respect store mapping.

#### CLI

- [ ] `cache:clear` built-in Rune command — clears all configured stores via `CacheManager`, plus the framework's independent caches (ConfigurationCache, TemplateCache). Accepts an optional `--store=NAME` flag to clear only a specific store. Prints what was cleared. Registered in `Bootstrap\CliRouting`.
- [ ] `cache:status` built-in Rune command (optional/low priority) — shows configured stores, default store, framework store assignments, and whether each store's backing service is reachable (Redis ping, APCu enabled, file directory writable). Useful for debugging.
- [ ] Tests for `cache:clear`: clears all stores, clears single store with `--store` flag, prints confirmation. Tests for `cache:status`: shows store info.

#### Documentation

- [ ] Write `src/Vault/README.md` — PSR-16 compliance, drivers, configuration, PrefixedCache, CacheManager, migration notes for framework caches, CLI commands.
- [ ] Update `src/Ignition/README.md` — add `Bootstrap\Cache` to bootstrapper tables.

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

Generators use Shodo's existing template engine to render stubs — no parallel template system needed. Stubs are just templates with variables like `{{ $namespace }}`, `{{ $className }}`, `{{ $dtoClass }}`.

- [ ] Stub templates stored as co-located `.stub` files alongside each generator command class (e.g., `Rune/Command/stubs/command.stub`, `handler.stub`). Shodo's `TemplateCompiler` compiles them, identity escape (no HTML escaping in PHP source).
- [ ] Each generator uses `TemplateCompiler::compile()` directly on the stub source string, then executes with variables — same pipeline as `PlainTextFormatter` but without resolver or caching (stubs are one-shot, no caching needed).
- [ ] Tests: stub templates render with correct variable substitution, control flow works if needed (e.g., optional sections).

#### make:command

```
php arcanum make:command Contact/Submit
```

Creates two files:

```
app/Domain/Contact/Command/Submit.php         ← DTO
app/Domain/Contact/Command/SubmitHandler.php  ← Handler
```

- [ ] `MakeCommand` — Rune built-in command implementing `BuiltInCommand`. Accepts a single argument: the slash-separated path (`Contact/Submit`). Reads `app.namespace` from config to determine the root namespace (e.g., `App\Domain`). Splits the path: all segments except the last become namespace segments, the last becomes the class name. Converts to PascalCase if needed.
- [ ] DTO stub — produces a `final class` with `declare(strict_types=1)`, correct namespace, empty constructor with `// TODO: add constructor parameters` comment, `readonly` properties pattern matching existing DTOs.
  ```php
  <?php

  declare(strict_types=1);

  namespace App\Domain\Contact\Command;

  final class Submit
  {
      public function __construct(
          // TODO: add constructor parameters
      ) {
      }
  }
  ```
- [ ] Handler stub — produces a `final class` with `__invoke` method accepting the DTO, `void` return type (commands default to void), `// TODO: implement` comment.
  ```php
  <?php

  declare(strict_types=1);

  namespace App\Domain\Contact\Command;

  final class SubmitHandler
  {
      public function __invoke(Submit $command): void
      {
          // TODO: implement
      }
  }
  ```
- [ ] Creates intermediate directories if they don't exist (`app/Domain/Contact/Command/`).
- [ ] Refuses to overwrite existing files — prints a clear message and exits with code 1. The developer must delete manually to regenerate. No `--force` flag (too dangerous for a code generator).
- [ ] Prints the created file paths on success.
- [ ] Tests: generates correct files with correct namespaces, refuses to overwrite, creates directories, handles nested paths (`Users/Admin/BanUser`), reads namespace from config.

#### make:query

```
php arcanum make:query Users/Find
```

Creates two files:

```
app/Domain/Users/Query/Find.php         ← DTO
app/Domain/Users/Query/FindHandler.php  ← Handler
```

- [ ] `MakeQuery` — same structure as `MakeCommand`, but inserts `Query` into the namespace instead of `Command`. Handler return type is `array` instead of `void` (queries return data).
- [ ] Handler stub uses `array` return type with a placeholder return:
  ```php
  public function __invoke(Find $query): array
  {
      // TODO: implement
      return [];
  }
  ```
- [ ] Same overwrite protection, directory creation, and output as `MakeCommand`.
- [ ] Tests: same matrix as `MakeCommand` but verifying `Query` namespace and `array` return type.

#### make:page

```
php arcanum make:page About
php arcanum make:page Docs/GettingStarted
```

Creates two files:

```
app/Pages/About.php    ← DTO (optional data defaults)
app/Pages/About.html   ← Template
```

- [ ] `MakePage` — reads `app.pages_namespace` and `app.pages_directory` from config. Slash-separated path maps to subdirectories under the pages directory. PascalCase class name becomes the filename.
- [ ] DTO stub — `final class` with a default `$title` property matching the page name (converted to title case):
  ```php
  <?php

  declare(strict_types=1);

  namespace App\Pages;

  final class About
  {
      public function __construct(
          public readonly string $title = 'About',
      ) {
      }
  }
  ```
- [ ] Template stub — minimal HTML5 boilerplate referencing `{{ $title }}`:
  ```html
  <!DOCTYPE html>
  <html lang="en">
  <head>
      <meta charset="UTF-8">
      <title>{{ $title }}</title>
  </head>
  <body>
      <h1>{{ $title }}</h1>
  </body>
  </html>
  ```
- [ ] Same overwrite protection, directory creation, and output.
- [ ] Tests: generates both files, correct namespace for nested pages (`Docs/GettingStarted` → `App\Pages\Docs\GettingStarted`), template references `{{ $title }}`.

#### make:middleware

```
php arcanum make:middleware RateLimit
```

Creates one file:

```
app/Http/Middleware/RateLimit.php
```

- [ ] `MakeMiddleware` — simpler than the others, always creates in `app/Http/Middleware/` (no configurable path — middleware is always HTTP). Produces a PSR-15 `MiddlewareInterface` stub.
- [ ] Stub implements `MiddlewareInterface` with `process()` method that delegates to `$handler->handle($request)`:
  ```php
  <?php

  declare(strict_types=1);

  namespace App\Http\Middleware;

  use Psr\Http\Message\ResponseInterface;
  use Psr\Http\Message\ServerRequestInterface;
  use Psr\Http\Server\MiddlewareInterface;
  use Psr\Http\Server\RequestHandlerInterface;

  final class RateLimit implements MiddlewareInterface
  {
      public function process(
          ServerRequestInterface $request,
          RequestHandlerInterface $handler,
      ): ResponseInterface {
          // TODO: implement
          return $handler->handle($request);
      }
  }
  ```
- [ ] Same overwrite protection, directory creation, and output.
- [ ] Tests: generates correct file, correct namespace, implements MiddlewareInterface.

#### Registration & Config

- [ ] Register all four generators as Rune built-in commands in `Bootstrap\CliRouting`: `make:command`, `make:query`, `make:page`, `make:middleware`.
- [ ] Each generator reads the app namespace and root directory from the container (via `Kernel` and `Configuration`) so it knows where to create files. Generators receive these via constructor injection, resolved by the built-in registry.
- [ ] `php arcanum list` shows the generators under "Built-in commands" with `#[Description]` text.
- [ ] Tests: generators are registered, `list` shows them.

#### Edge Cases & Error Handling

- [ ] Invalid name (empty, contains invalid characters) — print a clear error and exit with code 2.
- [ ] Name that already matches a framework class — no special handling. The developer's namespace is separate from `Arcanum\`, so collisions are impossible via convention routing.
- [ ] Single-segment names (`make:command Submit`) — valid. Creates at the root of the domain namespace: `App\Domain\Command\Submit`.
- [ ] Deeply nested paths (`make:query Admin/Users/Permissions/Check`) — valid. Creates all intermediate directories.
- [ ] Tests: each edge case.

#### Documentation

- [ ] Update `src/Rune/README.md` — add scaffolding generators section with examples for each generator.
- [ ] Add `#[Description]` attributes to each generator command class so `php arcanum help make:command` shows useful info.

### 5. Session Management — needs design discussion

⚠️ **Needs careful design before implementation.** Sessions have CQRS ramifications.

Depends on: security primitives (encryption for cookie sessions), caching (driver reuse).

Sessions are required for HTML-serving apps (login state, flash messages, CSRF tokens). Drivers: file, database (once persistence exists), Redis (once caching exists).

**Key constraint:** Commands must be session-independent. Sessions carry user identity context for authentication (read-only access to "who is making this request"), but command behavior must never vary based on arbitrary session state. The session is a read-side concern — it informs *authorization* (can this user do this?) but never *logic* (what does this command do?). The design must enforce this boundary, possibly by making the session available to HTTP middleware and query handlers but not to command handlers.

This also affects authentication and authorization (below) since both depend on session infrastructure. Needs discussion before building.

### 6. Authentication & Authorization

The capstone that ties security + sessions + validation together. Highest complexity of the dependency chain, but correctly positioned after its prerequisites.

Depends on: security primitives (hashing, encryption), sessions, validation. Persistence layer enhances it (user storage) but isn't strictly required (token-based auth can work without a database).

**Authentication** (who are you?) — Guards that check credentials via different strategies: session-based (HTML apps), token-based (API apps, including optional JWT support), API key. A `Guard` interface with swappable implementations. The guard resolves a `User` (or similar identity object) from the request and makes it available to the rest of the pipeline.

**Authorization** (can you do this?) — CQRS-native approach: authorization as Conveyor middleware, similar to `TransportGuard`. Attributes on DTOs declare permission requirements:

```php
#[RequiresAuth]
#[RequiresRole('admin')]
final class BanUser
{
    public function __construct(
        public readonly string $userId,
    ) {}
}
```

The middleware checks the authenticated user against the DTO's requirements before the handler runs. Works on both HTTP and CLI (CLI could authenticate via a `--token` flag or environment variable).

Policies for complex authorization logic (e.g., "users can only edit their own posts") as invocable classes resolved from the container.

The starter app should ship with an auth example (login flow or API token guard) and example middleware (rate limiting, request logging) once this is complete.

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

### 9. Persistence Layer

Highest effort by far. Correctly deferred until the auth/session/validation stack is solid. Apps need to authenticate users before they need to persist data.

A database persistence layer to make Arcanum a viable full-stack framework. CLI commands like migrations, schema management, and seed scripts use Rune.

**Design philosophy:** SQL is a powerful language — Arcanum treats `.sql` files as first-class citizens rather than hiding SQL behind a query builder abstraction. Migrations are `.sql` files, not PHP classes that generate SQL. Complex queries are written in SQL, not chained method calls. The framework provides connection management, parameter binding, result hydration, and migration tooling — but the SQL itself is yours.

CQRS shapes the design: repositories for the write side (aggregates), direct SQL queries for the read side (projections). Not a full ORM — lightweight entity mapping, not Doctrine-level magic. No active record pattern (models don't save themselves — commands handle writes).

Key areas: connection management (PDO, multi-driver), repository pattern, migration runner (`.sql` files, up/down, CLI commands), schema introspection, result hydration into DTOs. The `Location` header for 201 responses (deferred below) depends on this + reverse routing.

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
