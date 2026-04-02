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

**Needs design and checklist.** A library of helper functions for the template engine. Templates currently have `{{ $variable }}` and control flow but lack formatting and URL generation utilities.

Potential helpers:
- **URL/routing** — `{{ url('query:health') }}` reverse routing, `{{ asset('css/app.css') }}` asset paths
- **Formatting** — `{{ number($price, 2) }}`, `{{ date($timestamp, 'M j, Y') }}`, `{{ truncate($text, 100) }}`
- **Collections** — `{{ count($items) }}`, `{{ join($items, ', ') }}`, `{{ first($items) }}`
- **Security** — `{{ csrf() }}` token field, `{{ nonce() }}` for CSP
- **Conditionals** — `{{ class_if($active, 'selected') }}` for HTML class toggling

Key design questions:
- Where do helpers live? Shodo subpackage or dedicated package?
- How are helpers registered? A `HelperRegistry` injected alongside `$__escape` during template execution?
- Reverse routing is the high-value piece — needs a convention linking DTOs to URLs. Depends on Atlas conventions stabilizing.

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
