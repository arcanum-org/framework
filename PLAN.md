# Framework Completion Plan

---

## Completed Work

1997 tests, PHPStan level 9 clean. All checklist items through item 9 are done.

<details>
<summary>Core packages (click to expand)</summary>

Cabinet, Codex, Echo, Flow (Pipeline, Continuum, Conveyor, River), Gather, Glitch, Hyper, Ignition, Atlas, Shodo, Rune, Parchment, Quill, Toolkit. Full test coverage, all READMEs written.

</details>

<details>
<summary>1. Security Primitives — Toolkit (click to expand)</summary>

Encryption (SodiumEncryptor, XSalsa20-Poly1305), hashing (BcryptHasher, Argon2Hasher), random (Random utility), HMAC signing (SodiumSigner). Bootstrap\Security wires from APP_KEY. `make:key` CLI command.

</details>

<details>
<summary>2. Validation — new package (click to expand)</summary>

Attribute-based validation on DTO constructor params. 10 built-in rules (NotEmpty, MinLength, MaxLength, Min, Max, Email, Pattern, In, Url, Uuid, Callback). ValidationGuard Conveyor middleware fires before handlers. 422 on HTTP, field-level errors on CLI. Custom rules via Rule interface.

</details>

<details>
<summary>3. Caching — Vault (click to expand)</summary>

PSR-16 caching with 5 drivers (File, Array, Null, APCu, Redis). CacheManager for named stores. PrefixedCache decorator. Framework caches (config, templates, pages, middleware) migrated onto Vault. `cache:clear` and `cache:status` CLI commands.

</details>

<details>
<summary>4. Scaffolding Generators (click to expand)</summary>

Generator base class with stub templates (app-level overrides supported). `make:command`, `make:query`, `make:page`, `make:middleware`. Stubs use Shodo TemplateCompiler.

</details>

<details>
<summary>5. Sessions (click to expand)</summary>

HTTP session management with configurable drivers (file, cookie, cache). ActiveSession request-scoped holder. SessionMiddleware handles start/save/cookie. CSRF protection via CsrfMiddleware + `@csrf` template directive. Bootstrap\Sessions wires from config/session.php.

</details>

<details>
<summary>6. Auth & Authorization (click to expand)</summary>

Identity interface, Guards (Session, Token, Composite), AuthMiddleware (HTTP), CliAuthResolver (CLI). Authorization via DTO attributes (#[RequiresAuth], #[RequiresRole], #[RequiresPolicy]). AuthorizationGuard Conveyor middleware. CLI sessions: Prompter, CliSession (encrypted file store), LoginCommand, LogoutCommand. Priority chain: --token → session → ARCANUM_TOKEN env. All READMEs updated.

</details>

<details>
<summary>7. HTTP Client (click to expand)</summary>

PSR-18 HTTP client wrapper. Complete.

</details>

<details>
<summary>8. Custom App Scripts (click to expand)</summary>

Rune extension for app-defined CLI scripts. Complete.

</details>

<details>
<summary>9. Persistence — Forge (click to expand)</summary>

SQL files as first-class methods. Connection interface with PdoConnection (MySQL, PostgreSQL, SQLite). ConnectionManager with read/write split and domain mapping. Model maps `__call` to .sql files with PHP named/positional/mixed arg support. `@cast` (int, float, bool, json) and `@param` annotations. Result with lazy withCasts(). Sql utility (isRead, firstKeyword, parseCasts, extractBindings, resolveArgs). Database service with domain-scoped model access and transactions. DomainContext + DomainContextMiddleware for automatic domain scoping. ModelGenerator with stub templates and app-level overrides. forge:models, validate:models, db:status CLI commands. Dev-mode auto-regeneration with configurable auto_forge. Bootstrap\Database wires from config/database.php. Forge README, updated Ignition/Rune/Conveyor/root READMEs.

**Performance TODO:** `Result::rows()` with casts uses `array_map`, copying the row array. Investigate lazy iteration for large result sets.

</details>

<details>
<summary>Starter project (click to expand)</summary>

Full CQRS pipeline: Router → Hydrator → Conveyor → Renderer. Example Query (Health), Command (Contact/Submit), Page (Index). HTTP + CLI entry points. Config: app.php, routes.php, formats.php, middleware.php, cache.php, cors.php, log.php, auth.php. CORS middleware, auth examples (#[RequiresAuth], #[RequiresRole]), CLI login config.

</details>

<details>
<summary>Shodo/Hyper rendering refactor (click to expand)</summary>

Shodo decoupled from Hyper — formatters produce strings, response renderers build HTTP responses. Five phases: interface extraction, ResponseRenderer classes, old code deletion, Bootstrap rewiring, pipeline verification.

</details>

---

## Upcoming Work

### 10. Template Helpers — Shodo extension

Template helpers use static-method-call syntax in templates: `{{ Route::url('query:health') }}`, `{{ Format::number($price, 2) }}`. Each helper group is a plain class with public methods, registered under a short alias. The compiler recognizes `Name::method(...)` patterns and rewrites them to `$__helpers['Name']->method(...)`.

Helpers are domain-scoped via co-located `Helpers.php` files, following the same convention as `Middleware.php` discovery. Global helpers live at `app/Domain/Helpers.php`; domain-specific helpers live at `app/Domain/Shop/Helpers.php` etc. When formatting a DTO, the resolver walks from root to the DTO's domain, accumulating helpers. Domain-specific aliases override global ones.

**Framework helper groups:**
- **Route** (`RouteHelper`) — `url($dtoClass)`, `asset($path)`
- **Format** (`FormatHelper`) — `number($value, $decimals, $decSep, $thousandsSep)`, `date($timestamp, $format)`
- **Str** (`StrHelper`) — `truncate($text, $length, $suffix)`
- **Html** (`HtmlHelper`) — `csrf()`, `csrf_token()`, `nonce()`, `class_if($condition, $class)`
- **Arr** (`ArrHelper`) — `count($items)`, `join($items, $sep)`, `first($items)`, `last($items)`

**Architecture:**
- `HelperRegistry` — simple alias → object map (Shodo)
- `HelperResolver` — `for(string $dtoClass): array` returns alias → instance map for a given DTO (Shodo)
- `HelperDiscovery` — scans `Helpers.php` files, builds namespace-prefix → alias map, caches via PSR-16 (Shodo)
- Formatters accept `?HelperResolver`, call `$resolver->for($dtoClass)` at format-time
- Bootstrap populates global helpers (framework-provided) and wires discovery for app helpers

#### 10.1 HelperRegistry

- [x] **10.1a** Create `Shodo\HelperRegistry` class. Methods: `register(string $alias, object $helper): void`, `get(string $alias): object` (throws on missing), `has(string $alias): bool`, `all(): array<string, object>`. Stores alias → object instance.
- [x] **10.1b** Throw a dedicated `UnknownHelper` exception from `get()` when alias not found. Message includes the alias and lists registered aliases.
- [x] **10.1c** Unit tests: register and retrieve, `has()` true/false, `all()` returns map, `get()` throws `UnknownHelper` for unregistered alias, registering same alias overwrites previous.

#### 10.2 Compiler support for helper calls

The compiler currently has 5 regex passes. The helper-call pass must run before the escaped-output pass (pass 5), since `{{ Route::url(...) }}` would otherwise be emitted as `$__escape((string)(Route::url(...)))` — a real static call. The new pass rewrites `Name::method(...)` to `$__helpers['Name']->method(...)` before the escaped-output pass wraps it in `$__escape`.

- [x] **10.2a** Add a new regex pass to `TemplateCompiler::compile()` between pass 4 (end tags) and pass 5 (escaped output). Pattern matches `{{ UppercaseName::method(...) }}` and emits `<?= $__escape((string)($__helpers['Name']->method(...))) ?>`. The alias must start with an uppercase letter (to distinguish from PHP's real static calls on fully-qualified class names, which would use backslashes).
- [x] **10.2b** Handle raw-output variant: `{{! Route::url(...) !}}` should also rewrite to `$__helpers['Route']->url(...)` but without `$__escape`. Since pass 1 (raw output) runs first, the raw pass must also recognize helper syntax. Extend pass 1 to rewrite helper calls within `{{! !}}` delimiters.
- [x] **10.2c** Unit tests: helper call compiles to `$__helpers` lookup, helper call inside raw output compiles without escape, helper with multiple args, helper with no args (`{{ Html::csrf() }}`), helper with nested expressions as args (`{{ Format::number($item->price, 2) }}`), regular `{{ $variable }}` still compiles normally, real static calls with backslashes (`{{ \App\Foo::bar() }}`) are left alone.

#### 10.3 HelperResolver and formatter injection

Both `HtmlFormatter` and `PlainTextFormatter` execute templates via `extract($variables)` then `eval()`. The `$__helpers` variable must be present in that scope for compiled helper calls to resolve. Helpers are domain-scoped, so the formatter needs a resolver that returns the right helpers for each DTO class.

- [x] ~~**10.3a–e** Initial implementation with flat `HelperRegistry` (superseded by 10.3f–j below).~~
- [x] **10.3f** Create `Shodo\HelperResolver` class. Method: `for(string $dtoClass): array` returns `array<string, object>` (alias → helper instance). Constructor takes a global `HelperRegistry` (framework-provided helpers). `for()` returns `$this->global->all()` — domain scoping is layered on in 10.3i.
- [x] **10.3g** Refactor `HtmlFormatter`: change constructor param from `?HelperRegistry` to `?HelperResolver`. In `renderTemplate()`, call `$this->helpers->for($dtoClass)` to get the `$__helpers` array. Pass `$dtoClass` through from `format()` to `renderTemplate()`.
- [x] **10.3h** Same refactor for `PlainTextFormatter`.
- [x] **10.3i** Add `HelperDiscovery` support to `HelperResolver`. Constructor gains optional `HelperDiscovery $discovery` and `ContainerInterface $container`. `for()` merges global helpers with domain-scoped helpers discovered for the DTO's namespace prefix. Domain aliases override global. Helper classes from discovery are resolved from the container lazily.
- [x] **10.3j** Update existing formatter tests to use `HelperResolver` instead of `HelperRegistry`. Verify domain-scoped helpers override global ones.

#### 10.3A Helper discovery

Follows the `MiddlewareDiscovery` pattern: scans `Helpers.php` files co-located in domain directories.

- [x] **10.3Aa** Create `Shodo\HelperDiscovery`. Constructor takes `string $rootNamespace`, `string $rootDirectory`, optional `CacheInterface $cache`, `int $cacheTtl`. Method: `discover(): array` returns `array<string, array<string, string>>` (namespace prefix → alias → class name map). Scans for `Helpers.php` files via `Searcher::findAll()`, reads each via `Reader::require()`, maps file path to namespace prefix.
- [x] **10.3Ab** `Helpers.php` file convention: returns `array<string, string>` mapping alias → class name. Example: `return ['Cart' => CartHelper::class];`.
- [x] **10.3Ac** `clearCache(): void` method for cache invalidation.
- [x] **10.3Ad** Unit tests: discovers root-level Helpers.php, discovers domain-scoped Helpers.php, caches results, `clearCache()` works, missing directory returns empty, nested domains accumulate correctly.
- [x] **10.3Ae** `HelperResolver` merge logic unit tests: global helpers present for all DTOs, domain helpers added for matching DTOs, domain alias overrides global, unrelated domain helpers not included.

#### 10.4 Pure helpers

Transport-agnostic helper classes in Shodo. No dependencies on HTTP, session, or container.

- [ ] **10.4a** Create `Shodo\Helper\FormatHelper` with methods: `number(float|int $value, int $decimals = 0, string $decimalSeparator = '.', string $thousandsSeparator = ','): string` (wraps `number_format`), `date(int|string|\DateTimeInterface $timestamp, string $format = 'M j, Y'): string` (accepts unix timestamp, date string, or DateTimeInterface).
- [ ] **10.4b** Unit tests for `FormatHelper`: number with defaults, number with custom separators, number with negative values, date from int timestamp, date from string, date from DateTimeInterface, date with custom format.
- [ ] **10.4c** Create `Shodo\Helper\StrHelper` with method: `truncate(string $text, int $length, string $suffix = '...'): string`. Returns original if shorter than length. Truncates to `$length - strlen($suffix)` and appends suffix. Respects multibyte strings.
- [ ] **10.4d** Unit tests for `StrHelper`: text shorter than limit unchanged, exact limit unchanged, truncation with default suffix, truncation with custom suffix, multibyte text, empty string.
- [ ] **10.4e** Create `Shodo\Helper\ArrHelper` with methods: `count(array|\Countable $items): int`, `join(array $items, string $separator): string`, `first(array $items): mixed` (returns null for empty), `last(array $items): mixed` (returns null for empty).
- [ ] **10.4f** Unit tests for `ArrHelper`: count array, count Countable, join with separator, first/last of populated array, first/last of empty array returns null.

#### 10.5 Reverse URL resolver

- [ ] **10.5a** Create `Atlas\UrlResolver`. Constructor takes `string $rootNamespace` (e.g. `'App'`). Method: `resolve(string $dtoClass): string`. Strips `$rootNamespace\Domain\` prefix, identifies and strips `Query\` or `Command\` type namespace, converts remaining PascalCase segments to kebab-case via `Strings::kebab()`, joins with `/`, prepends `/`. Example: `App\Domain\Shop\Query\ProductsFeatured` → `/shop/products-featured`.
- [ ] **10.5b** Handle root-level DTOs (no domain segments): `App\Domain\Query\Health` → `/health`.
- [ ] **10.5c** Handle command handler prefixes: POST/PATCH/DELETE prefixes (`PostSubmit`, `PatchUpdate`, `DeleteRemove`) strip the prefix from the URL. The resolver doesn't need to handle these — it resolves the DTO class to the canonical path, not the HTTP method. Document this decision.
- [ ] **10.5d** Add optional `RouteMap` parameter to constructor. When present, `resolve()` first checks a reverse index (DTO class → path) built from the RouteMap. If found, returns the custom path. Falls back to convention.
- [ ] **10.5e** Build the reverse index: add `RouteMap::reverseLookup(string $dtoClass): ?string` method that iterates registered routes and returns the path for a matching DTO class. Cache the reverse map on first call.
- [ ] **10.5f** Handle Pages namespace: `App\Pages\Docs\GettingStarted` → `/docs/getting-started`. Detect by checking if class starts with pages namespace (needs `string $pagesNamespace` constructor param, nullable).
- [ ] **10.5g** Unit tests: convention query, convention command, root-level DTO, custom route override, pages namespace, unknown class outside root namespace throws exception.

#### 10.6 HTTP-aware helpers and Bootstrap wiring

- [ ] **10.6a** Create `Shodo\Helper\RouteHelper`. Constructor takes `Atlas\UrlResolver $resolver` and `string $baseUrl` (from `config/app.php → app.url`). Method: `url(string $dtoClass): string` — calls `$resolver->resolve($dtoClass)` and prepends `$baseUrl`. Method: `asset(string $path): string` — prepends `$baseUrl` and trims duplicate slashes.
- [ ] **10.6b** Unit tests for `RouteHelper`: url resolves and prepends base, asset prepends base, asset with leading slash, asset with trailing-slash base URL.
- [ ] **10.6c** Create `Shodo\Helper\HtmlHelper`. Constructor takes `Session\ActiveSession $session`. Methods: `csrf(): string` — returns `<input type="hidden" name="_token" value="...">` with token from `$session->get()->csrfToken()`. `csrf_token(): string` — returns raw token string. `nonce(): string` — generates random base64 string (16 bytes). `class_if(bool $condition, string $class): string` — returns `$class` if true, empty string if false.
- [ ] **10.6d** Unit tests for `HtmlHelper`: csrf returns hidden input with token value, csrf_token returns raw string, nonce returns base64 string of expected length, nonce is unique per call, class_if true returns class, class_if false returns empty string. Mock `ActiveSession` for csrf tests.
- [ ] **10.6e** Add `app.url` to `config/app.php` in starter app (default `''`). Document in config.
- [ ] **10.6f** Bootstrap `HelperResolver` wiring: create `HelperRegistry` with framework helpers (`FormatHelper`, `StrHelper`, `ArrHelper` always; `HtmlHelper` if `ActiveSession` exists; `RouteHelper` if `UrlResolver` exists). Create `HelperDiscovery` (scans `app/Domain/` for `Helpers.php` files, with PSR-16 cache). Create `HelperResolver` with global registry + discovery + container. Register as singleton.
- [ ] **10.6g** Update `HtmlFormatter` and `PlainTextFormatter` factory registrations in `Bootstrap\Routing` to resolve `HelperResolver` from container and pass to constructors.
- [ ] **10.6h** In `Bootstrap\Routing::registerRouter()`, after creating `ConventionResolver` and `RouteMap`, create `UrlResolver` and register in container. Pass it the root namespace, RouteMap, and pages namespace.
- [ ] **10.6i** Integration test: bootstrap a container with Routing bootstrapper, verify `HelperResolver` returns correct helpers, verify domain-scoped helpers from `Helpers.php` are included for matching DTOs.

#### 10.7 @csrf compiler directive

- [ ] **10.7a** Add compiler pass for `{{ @csrf }}` — emits `<?= $__helpers['Html']->csrf() ?>` (raw output, no escape — it's intentional HTML). Must run before both the raw-output and escaped-output passes.
- [ ] **10.7b** Unit test: `{{ @csrf }}` compiles to the raw helper call, not wrapped in `$__escape`.
- [ ] **10.7c** Integration test: template with `{{ @csrf }}` produces a hidden input when `HtmlHelper` is in the registry.

#### 10.8 README updates

- [ ] **10.8a** Update Shodo README: explain helper syntax (`{{ Name::method() }}`), document `HelperRegistry`/`HelperResolver`/`HelperDiscovery`, list built-in helpers with signatures and examples, show `Helpers.php` convention for domain-scoped custom helpers.
- [ ] **10.8b** Update Atlas README: document `UrlResolver`, explain the reverse convention (DTO class → URL path), show examples for queries, commands, custom routes, and pages.
- [ ] **10.8c** Update root README: add brief mention of template helpers in the Shodo package description.

#### 10.9 Starter app demo

- [ ] **10.9a** Update `../arcanum/app/Pages/Index.html` to use at least one helper — e.g., `{{ Format::date('now') }}` or `{{ Route::url('App\\Domain\\Query\\Health') }}`.
- [ ] **10.9b** Add a simple form page (`../arcanum/app/Pages/Contact.html`) demonstrating `{{ @csrf }}` inside a `<form>`.
- [ ] **10.9c** Add a domain-scoped `Helpers.php` example in the starter app (e.g., `app/Domain/Contact/Helpers.php` registering a helper only used by Contact templates).
- [ ] **10.9d** Verify the starter app boots and renders the updated pages without errors.

### 11. Starter App Polish

**Needs design and checklist.** Most valuable now that Auth, Persistence, and Caching are done.

Planned:
- **Rate limiting middleware** — example in starter app, uses Vault cache, demonstrates 429 Too Many Requests
- **Request logging middleware** — example demonstrating the middleware onion model
- **Default CSS and styling** — minimal CSS and base HTML layout for presentable default pages and error screens
- **Database example** — add a Model/ directory with SQL files, demonstrate Forge in a real domain

### Deferred — Command Response Enhancements

Blocked on reverse routing (Template Helpers item 10):

- **`Location` header for 201 responses** — requires URL generation from a class/identifier
- **Integration tests for 202/201 in Kernel** — straightforward once Location header is settled

---

## Long-Distance Future

- **Queue/Job system** — async processing with drivers (Redis, database, SQS)
- **Testing utilities** — DTO factories, service fakes, TestKernel
- **Internationalization** — translation strings, locale detection, pluralization
- **Task scheduling** — `schedule:run` cron dispatcher
- **Mail/Notifications** — thin wrappers or Symfony Mailer integration

---

## Performance Notes

<details>
<summary>Reflection caching — explored and rejected (click to expand)</summary>

Benchmarked three reflection caching approaches (in-memory, flyweight facade, APCu persistence) under production conditions (nginx + PHP-FPM + opcache + JIT). PHP 8.4 reflection is already fast enough — caching produced no measurable throughput improvement (~300 req/s ceiling dominated by FPM/FastCGI overhead, not reflection).

Open question: 3→10 DTO fields drops throughput 77% — worth profiling.

</details>

<details>
<summary>Benchmark harness (click to expand)</summary>

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

</details>

---

## Closed Questions

<details>
<summary>Decided — preserved for context (click to expand)</summary>

- ~~Bootstrap lifecycle hooks~~ — Won't do. App controls the Kernel subclass.
- ~~Handler auto-discovery~~ — Won't do for runtime. `validate:handlers` CLI command covers build-time.
- ~~Command response bodies~~ — Won't do. Location header (once reverse routing exists) is the answer.
- ~~SQL query builder~~ — Won't do. SQL is a first-class citizen.
- ~~Full ORM / Active Record~~ — Won't do. Fights CQRS.
- ~~WebSocket / real-time~~ — Won't do in core. Optional add-on.
- ~~Asset compilation~~ — Won't do. JS tools handle this.
- ~~Full template engine~~ — Won't do. Shodo covers lightweight pages.
- ~~Reflection caching~~ — Won't do. Benchmarked, no measurable improvement.

</details>
