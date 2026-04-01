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

- [ ] `Encryptor` interface — `encrypt(string $plaintext): string`, `decrypt(string $ciphertext): string`. The contract that downstream consumers depend on.
- [ ] `EncryptionKey` value object — wraps a raw 32-byte key. Constructor validates length, throws `InvalidArgumentException` on wrong size. `fromBase64(string): self` factory for parsing `APP_KEY` from environment (the key is stored base64-encoded in `.env`).
- [ ] `AesGcmEncryptor` — AES-256-GCM implementation via `openssl_encrypt`/`openssl_decrypt`. Generates a fresh random 12-byte nonce per encryption (nonce reuse with GCM is catastrophic). Produces a self-contained envelope: `base64(nonce || ciphertext || tag)`. On decrypt, splits the envelope, verifies the GCM authentication tag, and returns plaintext. Throws `DecryptionFailed` on any error (tampered data, wrong key, malformed envelope).
- [ ] `DecryptionFailed` exception — extends `RuntimeException`. Carries no detail about *why* decryption failed (that would leak information to attackers).
- [ ] Tests: encrypt/decrypt round-trip, tampered ciphertext throws `DecryptionFailed`, wrong key throws `DecryptionFailed`, malformed envelope (truncated, empty, not base64) throws `DecryptionFailed`, empty string encrypts/decrypts correctly, binary data round-trips, two encryptions of the same plaintext produce different envelopes (nonce uniqueness).
- [ ] Tests for `EncryptionKey`: valid 32-byte key accepted, wrong length rejected, `fromBase64()` decodes correctly, `fromBase64()` rejects invalid base64.

#### Hashing

- [ ] `Hasher` interface — `hash(string $value): string`, `verify(string $value, string $hash): bool`, `needsRehash(string $hash): bool`. The `needsRehash()` method enables transparent algorithm migration: after a successful `verify()`, check if the hash should be upgraded, and if so, re-hash and update storage.
- [ ] `BcryptHasher` — wraps `password_hash(PASSWORD_BCRYPT)` with configurable cost (default 12). Note: bcrypt silently truncates input at 72 bytes — document this limitation.
- [ ] `Argon2Hasher` — wraps `password_hash(PASSWORD_ARGON2ID)` with configurable `memory_cost` (default 65536 = 64MB), `time_cost` (default 4), `threads` (default 1). Argon2id is the recommended algorithm when available.
- [ ] Tests for `BcryptHasher`: hash/verify round-trip, wrong value returns false, `needsRehash()` returns true when cost changes, `needsRehash()` returns false for current params, hash output is a valid bcrypt string.
- [ ] Tests for `Argon2Hasher`: hash/verify round-trip, wrong value returns false, `needsRehash()` detects stale params, hash output is a valid argon2id string. (Skip if argon2id not available in CI — guard with `extension_loaded` or `PASSWORD_ARGON2ID` constant check.)

#### Random

- [ ] `Random` static utility class — `bytes(int $length): string` (raw random bytes via `random_bytes`), `hex(int $bytes = 32): string` (hex-encoded, 64 chars for 32 bytes), `base64url(int $bytes = 32): string` (URL-safe base64, no padding). These are the building blocks for CSRF tokens, API keys, session IDs, nonces, and any other random value in the framework.
- [ ] Tests: `bytes()` returns correct length, `hex()` output length is `$bytes * 2`, `hex()` contains only `[0-9a-f]`, `base64url()` contains only `[A-Za-z0-9_-]`, two calls to each method produce different values (probabilistic but effectively guaranteed at 32 bytes).

#### HMAC Signing

- [ ] `Signer` interface — `sign(string $payload): string`, `verify(string $payload, string $signature): bool`. Used by signed URLs, signed cookies, and any tamper-detection scenario.
- [ ] `HmacSigner` — HMAC-SHA256 via `hash_hmac('sha256', ...)`. Verification uses `hash_equals()` for timing-safe comparison. Constructor takes a string key (the `APP_KEY` or a derived key).
- [ ] Tests: sign/verify round-trip, tampered payload fails verification, wrong key fails verification, empty payload signs/verifies correctly, signature is a 64-character hex string.

#### Bootstrap & Wiring

- [ ] `Bootstrap\Security` bootstrapper in Ignition — reads `APP_KEY` from the `Environment` registry (set by `Bootstrap\Environment` from `.env`). Decodes the key via `EncryptionKey::fromBase64()`. Registers `Encryptor` (bound to `AesGcmEncryptor`) and `Signer` (bound to `HmacSigner`) in the container. Registers a default `Hasher` (bound to `BcryptHasher` — apps can override to `Argon2Hasher`). Throws `RuntimeException` if `APP_KEY` is missing or invalid.
- [ ] Add `Bootstrap\Security` to both `HyperKernel::$bootstrappers` and `RuneKernel::$bootstrappers` — slot after `Configuration` (needs config to be loaded) and before `Routing` (so security services are available to middleware).
- [ ] Starter app: add `APP_KEY=base64:<generated>` to `.env` and `.env.example`.
- [ ] Tests for `Bootstrap\Security`: registers `Encryptor`, `Signer`, and `Hasher` in container; throws on missing `APP_KEY`; throws on invalid (wrong-length) key.

#### CLI

- [ ] `make:key` built-in Rune command — generates a random 32-byte key, base64-encodes it, prints `APP_KEY=base64:<key>`. Optionally writes directly to `.env` if `--write` flag is passed. Registered in `Bootstrap\CliRouting`.
- [ ] Tests: output matches `APP_KEY=base64:...` format, `--write` flag modifies `.env` file, generated key decodes to 32 bytes.

#### Documentation

- [ ] Update `src/Toolkit/README.md` — document Encryption, Hashing, Random, and HMAC Signing sections with examples.
- [ ] Update `src/Ignition/README.md` — add `Bootstrap\Security` to the bootstrapper tables for both HTTP and CLI.

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

- [ ] `Rule` interface — `validate(mixed $value, string $field): ValidationError|null`. Takes a value and the field name. Returns `null` if valid, or a `ValidationError` with a message if invalid. Every built-in rule and every custom rule implements this. The interface is an attribute target (`Attribute::TARGET_PARAMETER`) so rules double as PHP attributes.
- [ ] `NotEmpty` rule — rejects `null`, `''`, `[]`. Message: `"The {field} field is required."` Handles the most common validation need.
- [ ] `MinLength(int $min)` / `MaxLength(int $max)` — string length bounds via `mb_strlen`. Messages: `"The {field} field must be at least {min} characters."` / `"...must not exceed {max} characters."` Works on strings only — non-string values skip the check (type enforcement is PHP's job, not validation's).
- [ ] `Min(int|float $min)` / `Max(int|float $max)` — numeric bounds. Messages: `"The {field} field must be at least {min}."` / `"...must not exceed {max}."` Works on int and float values.
- [ ] `Email` — validates email format via `filter_var(FILTER_VALIDATE_EMAIL)`. Message: `"The {field} field must be a valid email address."` Does not check deliverability — that's the handler's concern.
- [ ] `Pattern(string $regex)` — validates against a regex via `preg_match`. Message: `"The {field} field format is invalid."` For phone numbers, slugs, codes, etc.
- [ ] `In(array $values)` — value must be one of the listed values. Message: `"The {field} field must be one of: {values}."` For enums and fixed sets.
- [ ] `Url` — validates URL format via `filter_var(FILTER_VALIDATE_URL)`. Message: `"The {field} field must be a valid URL."`
- [ ] `Uuid` — validates UUID v4 format via regex. Message: `"The {field} field must be a valid UUID."`
- [ ] Tests for each rule: valid value returns null, invalid value returns `ValidationError` with correct field name and message, edge cases (empty string, null, wrong type, boundary values).

#### Validation Engine

- [ ] `ValidationError` — value object: `field` (string), `message` (string). Represents a single validation failure on a single field.
- [ ] `ValidationException` — extends `RuntimeException`. Carries a `list<ValidationError>` of all failures. Provides `errors(): array` to retrieve them and `errorsByField(): array<string, list<string>>` to get a field-keyed map of messages. This is what the middleware throws on failure.
- [ ] `Validator` — the core engine. `validate(object $dto): void` (throws `ValidationException` on failure) and `check(object $dto): list<ValidationError>` (returns errors without throwing). Uses reflection to read constructor params, finds `Rule` attributes on each param, reads the corresponding property value from the DTO, runs each rule. Collects **all** errors before throwing (doesn't stop at the first failure — the developer sees every problem at once).
- [ ] `Validator` skips `HandlerProxy` instances — dynamic DTOs (Command, Query, Page) carry data in a Registry, not constructor params. They are validated by their underlying DTO class (if one exists) at a higher level.
- [ ] `Validator` skips params with no `Rule` attributes — no attributes means no validation for that param, not an error.
- [ ] `Validator` handles nullable params — if a param is nullable and the value is `null`, skip rule checks for that param (null is a valid value for nullable types). Rules only run on non-null values.
- [ ] Tests: DTO with multiple rules on multiple params collects all errors, DTO with no rules passes, DTO with all valid values passes, nullable null value skips rules, `HandlerProxy` instances are skipped, `check()` returns errors without throwing, `validate()` throws `ValidationException`, error messages contain correct field names.

#### Conveyor Middleware

- [ ] `ValidationGuard` — Conveyor before-middleware (`Progression`). Resolves the DTO class from the payload (same `HandlerProxy` resolution as `TransportGuard`), instantiates `Validator`, calls `validate()`. On failure, the `ValidationException` propagates up — each kernel renders it appropriately. Follows the exact same pattern as `TransportGuard`: constructor takes no container dependency, works purely on the payload object.
- [ ] Tests: valid DTO passes through and calls `$next()`, invalid DTO throws `ValidationException` before `$next()` is called, `HandlerProxy` payloads are skipped (passed through without validation).

#### Error Rendering

The validation middleware throws `ValidationException`. Each kernel's exception handling renders it for the transport:

- [ ] `ValidationExceptionRenderer` in Hyper — catches `ValidationException`, renders a **422 Unprocessable Entity** JSON response with structured error body: `{"errors": {"name": ["The name field is required."], "email": ["The email field must be a valid email address."]}}`. Registered alongside `JsonExceptionResponseRenderer` in the HTTP exception handling chain.
- [ ] CLI rendering — `CliExceptionWriter` already handles any `RuntimeException`. For `ValidationException` specifically, format the errors readably: one line per field with its messages. No new class needed if the default `CliExceptionWriter` output is acceptable, but consider a dedicated branch in the writer that checks `instanceof ValidationException` and formats the field→message list cleanly rather than dumping a stack trace.
- [ ] Tests for `ValidationExceptionRenderer`: returns 422 status code, body contains field-keyed error messages, handles single and multiple errors, handles multiple errors on the same field.
- [ ] Tests for CLI rendering: validation errors are readable on stderr, includes field names and messages.

#### Bootstrap & Wiring

- [ ] Register `ValidationGuard` as a Conveyor before-middleware — either in `Bootstrap\Routing` / `Bootstrap\CliRouting` (so it's automatic) or document that apps register it in their bootstrap files. Decision: automatic registration is better (validation should be opt-out, not opt-in). Slot after `TransportGuard` (check transport first, then validate).
- [ ] Register `ValidationExceptionRenderer` in `Bootstrap\Exceptions` or the starter app's `bootstrap/http.php` — it needs to catch `ValidationException` before the generic `JsonExceptionResponseRenderer` turns it into a 500.
- [ ] Starter app: update the `Contact\Submit` DTO to demonstrate validation attributes.
- [ ] Tests for bootstrap integration: `ValidationGuard` is registered on the bus, `ValidationExceptionRenderer` catches validation errors correctly.

#### Custom Rules

- [ ] Document how to create custom rules: implement `Rule`, add `#[Attribute(Attribute::TARGET_PARAMETER)]`, use on DTO constructor params. Example: a `#[Unique('users', 'email')]` rule that checks a database (apps implement this, not the framework — it depends on persistence).
- [ ] `Callback(callable)` rule — escape hatch for one-off validation logic that doesn't warrant a dedicated rule class. The callable receives the value and returns `true` (valid) or a string (error message). Useful during prototyping.

#### Documentation

- [ ] Write validation package `README.md` — overview, built-in rules reference, custom rules, manual validation, Conveyor integration, error rendering on HTTP and CLI. Include examples for each built-in rule.
- [ ] Update `src/Flow/Conveyor/README.md` — mention `ValidationGuard` alongside `TransportGuard` in the middleware section.

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

- [ ] `PrefixedCache` — decorator that prepends a namespace prefix to all keys. Wraps any `CacheInterface` instance. Useful for isolating framework caches from app caches on the same driver (e.g., `framework:templates:` prefix vs. `app:` prefix). `clear()` only clears keys with the prefix (for file/array drivers) or delegates to the inner driver's `clear()` (for Redis/APCu where prefix-scoped clearing isn't practical without `SCAN` — document this limitation).
- [ ] Tests: get/set/delete prepend prefix, clear behavior, inner driver receives prefixed keys.

#### Bootstrap & Wiring

- [ ] `Bootstrap\Cache` bootstrapper in Ignition — reads `config/cache.php`, builds `CacheManager`, registers it in the container. Also registers the default store as `CacheInterface` so apps can typehint the PSR-16 interface directly. Slot after `Configuration` in both kernel bootstrapper lists.
- [ ] Add `Bootstrap\Cache` to `HyperKernel::$bootstrappers` and `RuneKernel::$bootstrappers`.
- [ ] Starter app: update `config/cache.php` to include the new `default`/`stores` structure alongside existing page/template cache toggles.
- [ ] Tests: bootstrapper registers `CacheManager` and `CacheInterface`, default store is resolvable.

#### Migrate Framework Ad-hoc Caches

Four existing caches currently use bespoke file-based caching. Once Vault exists, they can optionally use it for unified driver selection and cache clearing.

- [ ] Evaluate `ConfigurationCache` — currently serialized PHP array via Parchment. This cache is special: it must work *before* the container is built (it's read during `Bootstrap\Configuration`). It cannot depend on the CacheManager (which is registered *after* Configuration). **Decision: leave as-is.** ConfigurationCache is a bootstrap primitive, not an app cache. Document why it stays independent.
- [ ] Evaluate `TemplateCache` — compiled PHP per template, mtime-based freshness. This is content-addressed (key = hash of template path) with file-specific freshness (mtime comparison, not TTL). A generic PSR-16 cache doesn't support mtime freshness natively. **Decision: leave as-is but make the cache directory configurable via CacheManager config.** The template cache is a specialized compilation cache, not a generic key-value store. Unify the directory configuration so `cache:clear` knows about it.
- [ ] Migrate `PageDiscovery` cache — currently a single `var_export`ed file with TTL. This fits PSR-16 well: `$cache->set('page_discovery', $pages, $ttl)`. Refactor to accept `CacheInterface` instead of managing its own file. Use `PrefixedCache` to namespace it.
- [ ] Migrate `MiddlewareDiscovery` cache — same pattern as PageDiscovery. Refactor to accept `CacheInterface`.
- [ ] Update `Bootstrap\Routing` and `Bootstrap\RouteMiddleware` to pass the cache instance to PageDiscovery and MiddlewareDiscovery.
- [ ] Tests: PageDiscovery and MiddlewareDiscovery work with ArrayDriver in tests, produce same results as before migration.

#### CLI

- [ ] `cache:clear` built-in Rune command — clears the default cache store via `CacheInterface::clear()`. Also clears the framework's independent caches (ConfigurationCache, TemplateCache) so that one command resets everything. Prints what was cleared. Registered in `Bootstrap\CliRouting`.
- [ ] `cache:status` built-in Rune command (optional/low priority) — shows configured stores, default store, and whether each store's backing service is reachable (Redis ping, APCu enabled, file directory writable). Useful for debugging.
- [ ] Tests for `cache:clear`: clears cache, prints confirmation. Tests for `cache:status`: shows store info.

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

Each generator creates the files in the correct directory following Arcanum's conventions, with proper namespaces, constructor params, and handler stubs. `make:page` also creates the `.html` template. Generators are registered as Rune built-in commands.

This is the single biggest DX improvement for onboarding — new developers go from zero to a working endpoint in seconds without memorizing namespace conventions.

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
