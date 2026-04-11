# Arcanum

**A CQRS PHP framework that stays readable at scale.**

---

## Why Arcanum

### MVC Doesn't Scale

MVC groups by what things _are_: all models in one folder, all views in another, all controllers in a third. At 20 endpoints that's manageable. At 200, your `Models/` directory is a junk drawer and your controllers are 400-line god classes doing six different things.

CQRS groups by what things _do_. The DTO, handler, template, SQL, validation, and middleware for "place an order" all live together in `app/Domain/Shop/Command/PlaceOrder/`. The directory _is_ the architecture diagram. Things that change together live together.

### SQL Is Code

No ORM. No query builder. SQL files live in your codebase and the framework calls them directly.

```
app/Domain/Shop/Model/
├── AllProducts.sql       ← $db->model->allProducts()
├── FindProduct.sql       ← $db->model->findProduct(id: 42)
├── InsertProduct.sql     ← $db->model->insertProduct(name: 'Widget', price: 9.99)
└── Model.php             ← generated, type-safe
```

The database is the source of truth, not a PHP abstraction layer. You write real SQL — you can read it, you can `EXPLAIN` it, and you never fight a query builder when the abstraction leaks. Because it never leaks. It's just SQL.

### Stop Building Two Applications at the Same Time

Most modern web apps are really two apps: a backend API and a JavaScript frontend, maintained in parallel, deployed separately, constantly drifting apart.

Arcanum takes a different path. The server renders the HTML. [htmx](https://htmx.org) makes it feel like a single-page app — partial updates, no page reloads, cross-component refresh — without client-side state management or a JavaScript build pipeline.

The framework's [Htmx package](src/Htmx/README.md) handles the backend ceremony so you don't have to:

- **You write normal handlers.** The framework inspects htmx headers, decides how much of the page to render, and returns the right fragment. Your handler code doesn't change.
- **Domain events propagate to the DOM.** A command handler dispatches an event. The framework projects it as an `HX-Trigger` response header. Listening elements refresh themselves. Zero JavaScript coordination.
- **CSRF, auth redirects, cache safety** — all wired up by three middleware. You register them once and move on.

One codebase. One language. One rendering pipeline. One source of truth.

### Humans Don't Read Documentation, but AI Agents Do

The AI genie is out of the bottle. Most developers use coding assistants, and AI agents are quickly becoming both the authors _and_ the consumers of your code. Arcanum is designed for this reality.

**AI writes better Arcanum code** because there's one idiomatic way to do things. When an AI agent scaffolds a new feature, the conventions guide it toward the right structure, the right naming, the right location — automatically. Tailwind is the default for styling because the visual intent lives right in the markup, not in a separate stylesheet an agent might miss.

**AI consumes Arcanum apps natively.** The same handler serves `.html`, `.json`, `.md`, `.csv`, and `.txt` — just change the URL extension. An agent doesn't need to parse HTML or reverse-engineer an API; it asks for `/products.md` and gets Markdown. HTTP status codes carry precise, machine-readable semantics: 422 means "bad input, here are the field errors," 405 means "this endpoint exists but you used the wrong method," 429 means "back off and retry." Your app speaks a language that agents already understand — structured errors, content negotiation, and semantic links — without building a separate "bot API."

### Your Handler Is Five Lines

The framework absorbs the ceremony. Validation, authorization, CSRF, content negotiation, error rendering, response formatting — all handled by attributes, middleware, and conventions. What's left is the thing that matters:

```php
final class PlaceOrderHandler
{
    public function __construct(private readonly Database $db) {}

    public function __invoke(PlaceOrder $command): void
    {
        $this->db->model->insertOrder(product: $command->product, quantity: $command->quantity);
    }
}
```

Validation? `#[NotEmpty]` and `#[Min(1)]` on the DTO constructor params — `ValidationGuard` rejects bad input with a 422 before the handler runs. Auth? `#[RequiresAuth]` on the DTO class. CSRF? Automatic. Response format? Automatic. Error rendering? Automatic.

---

## First Principles

These are the foundations that everything in Arcanum is built upon.

- **Don't abstract away the thing you're actually doing.** SQL, HTTP status codes, URL structure — let the real thing be the thing. Abstractions that hide the underlying reality eventually leak, and then you have two mental models instead of one.

- **Colocation by concern, not by layer.** The DTO, handler, template, SQL, validation, and middleware for an operation all live in the same directory. When you need to change how something works, you open one folder, not five.

- **Strong defaults, not walls.** Conventions make the common case effortless — one class per operation, handler discovered by name, template co-located with the DTO. But when you need to break the pattern, the framework gets out of your way. Opinionated doesn't mean inflexible.

- **Build for two audiences.** Every design decision should work for developers writing code _and_ agents consuming the deployed app. Opinionated conventions make AI write better code. Multi-format responses, structured errors, and semantic HTTP make your app machine-readable without a separate integration layer.

---

## What It Looks Like

A query that serves `GET /shop/products.html` (and `.json`, `.csv`, `.md`, `.txt`):

```php
// app/Domain/Shop/Query/Products.php — the DTO
final class Products
{
    public function __construct(
        public readonly string $category = '',
    ) {}
}

// app/Domain/Shop/Query/ProductsHandler.php — the handler
final class ProductsHandler
{
    public function __construct(private readonly Database $db) {}

    public function __invoke(Products $query): array
    {
        return ['products' => $this->db->model->productsByCategory(category: $query->category)];
    }
}
```

```sql
-- app/Domain/Shop/Model/ProductsByCategory.sql
-- @param category string
-- @cast price float
SELECT id, name, price FROM products
WHERE (:category = '' OR category = :category)
ORDER BY name
```

No route registration. No controller. No model class. No ORM. The namespace _is_ the URL. The SQL _is_ the method.

---

## Quick Start

```bash
git clone https://github.com/arcanum-org/arcanum.git myapp
cd myapp
composer install
php bin/arcanum migrate
php -S localhost:8000 -t public
```

Visit [localhost:8000](http://localhost:8000) — you'll see the welcome page with a live guestbook demo, diagnostics, and a CQRS walkthrough.

See the [starter app README](https://github.com/arcanum-org/arcanum) for the full setup guide.

---

## What's in the Box

23 packages, each with its own README.

### Foundation

| Package | What it does |
|---|---|
| [Cabinet](src/Cabinet/README.md) | PSR-11 dependency injection container |
| [Codex](src/Codex/README.md) | Reflection-based auto-wiring and class resolution |
| [Toolkit](src/Toolkit/README.md) | String utilities, cryptography primitives, random generation |
| [Parchment](src/Parchment/README.md) | Filesystem reader utilities |
| [Gather](src/Gather/README.md) | Typed registries: Configuration, Environment, IgnoreCaseRegistry |

### Data Flow

| Package | What it does |
|---|---|
| [Pipeline](src/Flow/Pipeline/) | Linear stage chain — output of one becomes input of the next |
| [Continuum](src/Flow/Continuum/) | Middleware pattern — each stage calls `$next` to continue |
| [Conveyor](src/Flow/Conveyor/) | Command bus — dispatches DTOs to handlers with before/after middleware |
| [River](src/Flow/River/) | PSR-7 stream implementations |
| [Sequence](src/Flow/Sequence/) | Lazy and eager ordered iterables — `Cursor` (single-pass) and `Series` (multi-pass) |

### HTTP and CLI

| Package | What it does |
|---|---|
| [Hyper](src/Hyper/) | PSR-7 messages, PSR-15 handling, response renderers, StatusCode enum |
| [Rune](src/Rune/README.md) | CLI transport: input parsing, output formatting, built-in commands |
| [Atlas](src/Atlas/README.md) | Convention-based CQRS router for HTTP and CLI |
| [Ignition](src/Ignition/README.md) | Bootstrap kernels: HyperKernel, RuneKernel, bootstrapper chain |
| [Shodo](src/Shodo/README.md) | Template engine, formatters (HTML, JSON, CSV, Markdown, plain text), compiler directives |
| [Htmx](src/Htmx/README.md) | First-class htmx 4 support: rendering modes, event projection, CSRF, auth redirects |

### Persistence and State

| Package | What it does |
|---|---|
| [Forge](src/Forge/README.md) | SQL-as-methods, connection management, model generation, migrations |
| [Vault](src/Vault/README.md) | PSR-16 caching: File, Array, Null, APCu, Redis drivers |
| [Session](src/Session/README.md) | HTTP sessions, CSRF middleware, configurable drivers |

### Identity and Access

| Package | What it does |
|---|---|
| [Auth](src/Auth/README.md) | Authentication guards, authorization attributes, CLI auth |

### Validation, Observability, Throttling

| Package | What it does |
|---|---|
| [Validation](src/Validation/README.md) | Attribute-based DTO validation with 11 built-in rules |
| [Glitch](src/Glitch/README.md) | Error handling, HttpException, StatusCode enum, ArcanumException |
| [Quill](src/Quill/README.md) | Multi-channel PSR-3 logging over Monolog |
| [Echo](src/Echo/README.md) | PSR-14 event dispatcher |
| [Throttle](src/Throttle/README.md) | Rate limiting: token bucket and sliding window strategies |
| [Hourglass](src/Hourglass/README.md) | Clock interface (PSR-20), FrozenClock, Stopwatch, Interval |
| [Testing](src/Testing/README.md) | Test harness: TestKernel, Factory, HTTP and CLI test surfaces |

---

## Documentation

| Resource | What it covers |
|---|---|
| [COMPENDIUM.md](COMPENDIUM.md) | The guided tour — what Arcanum is, how the pieces fit, conventions, CLI surface |
| [Starter App](https://github.com/arcanum-org/arcanum) | Getting started, directory structure, examples |
| [PLAN.md](PLAN.md) | What's been built, what's coming, design decisions |
| Package READMEs | Deep dives — linked in the table above |

---

## Status

Arcanum is under active development, approaching a 1.0-alpha release.

- **PHP 8.4+** required
- **PHPStan level 9** — strict static analysis across the entire codebase
- **2700+ tests** with strict coverage — every test class declares exactly what it covers
- **AI-assisted, not vibe-coded.** AI agents have contributed significantly to this codebase, but every line passes the same static analysis, test coverage, and code review standards. Structure and rigor first.

```bash
composer check    # cs-fix, cs-check, phpstan, phpunit — all at once
```
