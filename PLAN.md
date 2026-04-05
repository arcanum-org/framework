# Framework Completion Plan

---

## Completed Work

2209 tests, PHPStan level 9 clean.

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

Identity interface, Guards (Session, Token, Composite), AuthMiddleware (HTTP), CliAuthResolver (CLI). Authorization via DTO attributes (#[RequiresAuth], #[RequiresRole], #[RequiresPolicy]). AuthorizationGuard Conveyor middleware. CLI sessions: Prompter, CliSession (encrypted file store), LoginCommand, LogoutCommand. Priority chain: --token → session → ARCANUM_TOKEN env. CSRF/Auth coordination: AuthMiddleware sets PSR-7 request attribute for token-authenticated requests; CsrfMiddleware skips CSRF for those. Guard config supports array syntax: `'guard' => ['session', 'token']`.

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

SQL files as first-class methods. Connection interface with PdoConnection (MySQL, PostgreSQL, SQLite). ConnectionManager with read/write split and domain mapping. Model maps `__call` to .sql files with PHP named/positional/mixed arg support. `@cast` (int, float, bool, json) and `@param` annotations. Result with lazy withCasts(). Sql utility with SqlScanner for comment/string-aware parsing. Database service with domain-scoped model access and transactions. DomainContext + DomainContextMiddleware for automatic domain scoping. ModelGenerator with stub templates and app-level overrides. forge:models, validate:models, db:status CLI commands. Dev-mode auto-regeneration with configurable auto_forge. Bootstrap\Database wires from config/database.php.

**Performance TODO:** `Result::rows()` with casts uses `array_map`, copying the row array. Investigate lazy iteration for large result sets.

</details>

<details>
<summary>10. Template Helpers — Shodo extension (click to expand)</summary>

Static-method-call syntax in templates: `{{ Route::url('...') }}`, `{{ Format::number($price, 2) }}`. HelperRegistry, HelperResolver (domain-scoped via co-located `Helpers.php` files), HelperDiscovery. Five built-in helper groups: Route, Format, Str, Html, Arr. `{{ @csrf }}` directive. Compiler rewrites `Name::method(...)` to `$__helpers['Name']->method(...)`.

</details>

<details>
<summary>Shodo/Hyper rendering refactor (click to expand)</summary>

Shodo decoupled from Hyper — formatters produce strings, response renderers build HTTP responses. Five phases: interface extraction, ResponseRenderer classes, old code deletion, Bootstrap rewiring, pipeline verification.

</details>

<details>
<summary>Security fixes (click to expand)</summary>

All complete: Bearer token CSRF bypass removed, CSRF/Auth coordination via request attributes, `#[Url]` restricted to http/https, Model path traversal fixed, JsonFormatter JSON_HEX_TAG added, template eval() security documented, Pattern regex ReDoS documented.

</details>

<details>
<summary>DX guardrails (click to expand)</summary>

All complete: ValidationGuard missing detection, `#[AllowedFormats]` attribute (406 Not Acceptable), unused template variable warning (TemplateAnalyzer), handler error messages, `validate:handlers` promotion, circular dependency detection, template undefined variable errors, `factory()` caching documented, bootstrapper ordering enforcement, page discovery warning.

</details>

<details>
<summary>Additional features (click to expand)</summary>

- **Markdown formatter** — template-based with `.md` files, identity escape, fallback renderer, MarkdownResponseRenderer.
- **Command response Location headers** — LocationResolver builds URLs from returned Query DTO instances (class → path, properties → query params). 201 Created + Location header.
- **Bootstrap\Routing split** — split into Bootstrap\Formats, Bootstrap\Routing (slimmed), Bootstrap\Helpers.
- **SqlScanner** — extracted character-level SQL lexer from Forge\Sql::extractBindings() into reusable SqlScanner class.

</details>

<details>
<summary>Starter project (click to expand)</summary>

Full CQRS pipeline: Router → Hydrator → Conveyor → Renderer. Example Query (Health), Command (Contact/Submit), Page (Index, Contact). HTTP + CLI entry points. Config files with comments. Getting-started README covering quick start, CQRS concepts, directory structure, validation, auth, response formats, testing, and development workflow. Example test (HealthHandlerTest).

</details>

---

## Upcoming Work

### Starter app

- [x] **Add database example** — Contact domain persists to SQLite via Forge. Model/ directory with Save.sql, FindAll.sql, CreateTable.sql. New Messages query reads submissions back. config/database.php with SQLite connection.

### Forge Sub-Model Redesign

Current Forge generates one flat `Model.php` per domain with a method for every SQL file in `Model/`. For large domains this becomes a god object. Redesign: subdirectories become independent, autowireable model classes. Handlers inject specific sub-models by class name instead of `Database`.

**Directory convention:**
```
app/Domain/Shop/Model/
    Products/                   ← subdirectory = sub-model
        FindAll.sql
        FindById.sql
        Products.php            ← generated, class named after directory
    Orders/
        Create.sql
        FindByCustomer.sql
        Orders.php              ← generated
    GetCart.sql                 ← root-level SQL files
    ListSpecials.sql
    Model.php                   ← generated for root-level SQL only
```

**Generated class pattern:**
```php
// app/Domain/Shop/Model/Products/Products.php
namespace App\Domain\Shop\Model\Products;

final class Products extends BaseModel
{
    public function __construct(ConnectionManager $connections)
    {
        parent::__construct(__DIR__, $connections);
    }

    public function findAll(): Result { ... }
    public function findById(int $id): Result { ... }
}
```

- `__DIR__` for self-location — generated class lives next to its SQL files, no path config needed.
- Only dependency is `ConnectionManager` — fully autowireable by Codex without container registration.
- No root Model.php delegating to sub-models — each sub-model is independent.
- Root-level SQL files still generate a `Model` class for small domains (backwards compatible).
- Transactions: handler injects `Database` alongside specific models when needed.

**Handler injection (new pattern):**
```php
use App\Domain\Shop\Model\Products\Products;

final class ProductsHandler
{
    public function __construct(private readonly Products $products) {}

    public function __invoke(ProductsQuery $query): array
    {
        return $this->products->findAll()->rows();
    }
}
```

**Changes required:**

Framework:

- [ ] **Refactor `Model` base class constructor** — accept `ConnectionManager` instead of separate read/write `Connection` objects. Model uses `ConnectionManager` to resolve read/write connections internally (respects domain mapping and read/write split). The `directory` parameter becomes optional — if omitted, defaults to `__DIR__` in generated subclasses.
- [ ] **Update `ModelGenerator::generate()`** — scan for subdirectories in `Model/`. For each subdirectory with `.sql` files, generate a class named after the directory (e.g., `Products/Products.php`). Root-level `.sql` files generate `Model.php` as today. The `discoverSqlFiles()` method needs to distinguish root-level vs subdirectory files.
- [ ] **Update model stub** — constructor takes `ConnectionManager` instead of `Connection $readConnection, Connection $writeConnection`. Uses `__DIR__` for directory. Import `ConnectionManager` instead of `Model as BaseModel`.
- [ ] **Update `ModelGenerator::renderClass()`** — pass the class name (directory name for sub-models, `Model` for root-level) to the stub. Handle namespacing: sub-model at `Products/Products.php` has namespace `App\Domain\Shop\Model\Products`.
- [ ] **Update `forge:models` CLI command** — iterate subdirectories and generate each sub-model. Report each generated file. Handle mixed structures (some root-level SQL + some subdirectories).
- [ ] **Update `validate:models` CLI command** — validate sub-models alongside root models.
- [ ] **Update `Database` class** — `$db->model` still works for backwards compatibility (resolves root-level Model). Consider deprecation path.
- [ ] **Update `DomainContext`** — may need adjustment for sub-model path resolution.
- [ ] **Tests** — `ModelGeneratorTest` for subdirectory generation, `ModelTest` for new constructor, existing tests must pass.
- [ ] **Update Forge README** — document sub-model convention, handler injection pattern, directory structure.

Starter app:

- [ ] **Restructure Contact Model/** — reorganize into the new subdirectory pattern if appropriate, or keep flat (small domain). Update SubmitHandler and MessagesHandler to inject models directly.

### 11. Starter App Polish

**Needs design and checklist.** Planned:
- **Rate limiting middleware** — example in starter app, uses Vault cache, demonstrates 429 Too Many Requests
- **Request logging middleware** — example demonstrating the middleware onion model
- **Default CSS and styling** — minimal CSS and base HTML layout for presentable default pages and error screens

### Error message personality pass

Full pass across every package to make error messages helpful, friendly, and fun. Every message should: (1) say what went wrong clearly, (2) suggest what to do about it, (3) have personality without sacrificing precision. Scan all `throw new`, `RuntimeException`, `InvalidArgumentException`, `HttpException`, `LogicException`, and custom exception classes across `src/`. Rewrite dry messages, add "did you mean?" hints where possible, and ensure every error points the developer toward a fix.

"Did you mean?" hints and solution suggestions should be configurable — on by default in development, off in production, and toggleable for developers who prefer terse output. A config key like `app.verbose_errors` (defaulting to the value of `app.debug`) controls this. The error message core (what went wrong) is always present; the hint/suggestion suffix is conditional.

---

## Long-Distance Future

- **RFC 9457 Problem Details for HTTP APIs** — standardized JSON error response format (`application/problem+json`). Needs design discussion: how it integrates with HttpException, Glitch, and Shodo's exception renderers. See https://www.rfc-editor.org/rfc/rfc9457.html
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
- ~~Command response bodies~~ — Won't do. Location header is the answer.
- ~~SQL query builder~~ — Won't do. SQL is a first-class citizen.
- ~~Full ORM / Active Record~~ — Won't do. Fights CQRS.
- ~~WebSocket / real-time~~ — Won't do in core. Optional add-on.
- ~~Asset compilation~~ — Won't do. JS tools handle this.
- ~~Full template engine~~ — Won't do. Shodo covers lightweight pages.
- ~~Reflection caching~~ — Won't do. Benchmarked, no measurable improvement.

</details>
