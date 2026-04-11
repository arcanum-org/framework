# Arcanum

**A CQRS PHP framework that stays readable and manageable at scale.**

---

## Why Arcanum

### MVC Doesn't Scale

MVC groups by what things _are_: all models go in one folder, all views in another, and all controllers in a third. At 20 endpoints that's manageable. At 200, your `Models/` directory is a junk drawer and your controllers are 400-line god classes doing six different things.

CQRS groups by what things _do_. The handler, template, SQL, validation, and specialized middleware for "place an order" all live together in `app/Domain/Shop/Command/PlaceOrder/`. The directory _is_ the architecture diagram. Things that change together live together.

### SQL Is Code

Every framework I've ever worked with does two things: 1. Abstracts away SQL with an ORM or query builder. 2. Forces you to write SQL in strings.

In Arcanum, SQL files are first-class citizens. There is no ORM. There is no query builder. That's because SQL is already a perfectly good language. SQL is code. You can read it. You can `EXPLAIN` it. The framework generates type-safe methods from your SQL files, so you get autocompletion and static analysis without giving up the power and readability of raw SQL.

It's hard to explain this important point, because we've been trained to think of SQL as a string, and we must write model methods to build those strings, send them off to the database, and parse the results. But when SQL is a first-class citizen, you write it once, in its own file, and the framework generates a method that _is_ that SQL. The method signature is generated from the SQL parameters and result columns. The mental model is one-to-one with reality.

### Stop Building Two Applications at the Same Time

Most modern web apps are really two apps: a backend API and a JavaScript frontend, maintained in parallel, sometimes deployed separately, and constantly drifting apart.

Arcanum takes a different path. Hypermedia as the Engine of Application State, HATEOAS. The server renders the HTML. [htmx](https://htmx.org) handles the dynamic bits. Arcanum ships with first-class support for htmx 4, so you can build a dynamic, interactive app without writing a line of JavaScript. The server is the single source of truth for your app's behavior and state. The DOM is just a view layer that reflects that state.

The framework's [Htmx package](src/Htmx/README.md) handles the backend ceremony so you don't have to:

- **You write normal query handlers.** The framework inspects htmx headers, decides how much of the page to render, and returns the right fragment. Your handler code doesn't change.
- **Domain events propagate to the DOM.** A command handler dispatches an event. The framework projects it as an `HX-Trigger` response header. Listening elements refresh themselves. Zero JavaScript coordination.
- **CSRF, auth redirects, cache safety** — all wired up by middleware.

Don't want to use htmx? Sure, but you're missing out on the most seamless way to build a dynamic app. The framework doesn't force you to use it, but it's the path of least resistance for a reason.

### Humans Don't Read Documentation, but AI Agents Do

The AI genie is out of the bottle. Most developers use coding assistants, and AI agents are quickly becoming both the authors _and_ the consumers of your code. Arcanum is designed for this reality.

**AI writes better Arcanum code** because there's one idiomatic way to do it. When an AI agent scaffolds a new feature, the conventions guide it toward the right structure, the right naming, the right location — automatically.

**AI consumes Arcanum apps natively.** Humans want to see HTML. Agents want to see markdown, JSON, CSV, or plain text. When you build an endpoint in Arcanum, it can server all of those formats without extra work on your part. The same handler serves `.html`, `.json`, `.md`, `.csv`, and `.txt` — just change the URL extension. An agent doesn't need to parse HTML or reverse-engineer an API; it asks for `/products.md` instead of `/products.html` and the framework renders Markdown with zero effort on your part.

### Your Handler Is Five Lines

Arcanum absorbs the ceremony. Validation, authorization, CSRF, content negotiation, error rendering, response formatting — all handled by attributes, middleware, and conventions. What's left is the thing that matters:

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

A dev doesn't need to write any route definitions, controller methods, or model classes. An AI agent doesn't need to be trained on how to structure the code or where to put things. The namespace _is_ the URL. The SQL _is_ the method.

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

| Package                              | What it does                                                 |
| ------------------------------------ | ------------------------------------------------------------ |
| [Cabinet](src/Cabinet/README.md)     | PSR-11 dependency injection container                        |
| [Codex](src/Codex/README.md)         | Reflection-based auto-wiring and class resolution            |
| [Toolkit](src/Toolkit/README.md)     | String utilities, cryptography primitives, random generation |
| [Parchment](src/Parchment/README.md) | Filesystem layer                                             |
| [Gather](src/Gather/README.md)       | Collections for storing and retrieving key-value data        |

### Data Flow

| Package                          | What it does                                                                         |
| -------------------------------- | ------------------------------------------------------------------------------------ |
| [Pipeline](src/Flow/Pipeline/)   | Send data through a pipeline where the output of one stage becomes input of the next |
| [Continuum](src/Flow/Continuum/) | Middleware pattern — each stage calls `$next` to continue                            |
| [Conveyor](src/Flow/Conveyor/)   | Command bus — dispatches DTOs to handlers with before/after middleware               |
| [River](src/Flow/River/)         | PSR-7 stream implementations                                                         |
| [Sequence](src/Flow/Sequence/)   | Lazy and eager ordered iterable objects                                              |

### HTTP and CLI

| Package                            | What it does                                                                             |
| ---------------------------------- | ---------------------------------------------------------------------------------------- |
| [Hyper](src/Hyper/)                | PSR-7 messages, PSR-15 handling, response renderers, etc.                                |
| [Rune](src/Rune/README.md)         | CLI tool with built-in commands and support for custom commands.                         |
| [Atlas](src/Atlas/README.md)       | Convention-based CQRS router for HTTP and CLI                                            |
| [Ignition](src/Ignition/README.md) | Bootstrap kernels: HyperKernel, RuneKernel. This is what makes the framework work.       |
| [Shodo](src/Shodo/README.md)       | Template engine, formatters (HTML, JSON, CSV, Markdown, plain text), compiler directives |
| [Htmx](src/Htmx/README.md)         | First-class htmx 4 support: rendering modes, event projection, CSRF, auth redirects      |

### Persistence and State

| Package                          | What it does                                                        |
| -------------------------------- | ------------------------------------------------------------------- |
| [Forge](src/Forge/README.md)     | SQL-as-methods, connection management, model generation, migrations |
| [Vault](src/Vault/README.md)     | PSR-16 caching: File, Array, Null, APCu, Redis drivers              |
| [Session](src/Session/README.md) | HTTP sessions, CSRF middleware, configurable drivers                |

### Identity and Access

| Package                    | What it does                                              |
| -------------------------- | --------------------------------------------------------- |
| [Auth](src/Auth/README.md) | Authentication guards, authorization attributes, CLI auth |

### Validation, Observability, Throttling

| Package                                | What it does                                                     |
| -------------------------------------- | ---------------------------------------------------------------- |
| [Validation](src/Validation/README.md) | Attribute-based DTO validation with 11 built-in rules            |
| [Glitch](src/Glitch/README.md)         | Error handling, HttpException, StatusCode enum, ArcanumException |
| [Quill](src/Quill/README.md)           | Multi-channel PSR-3 logging over Monolog                         |
| [Echo](src/Echo/README.md)             | PSR-14 event dispatcher                                          |
| [Throttle](src/Throttle/README.md)     | Rate limiting: token bucket and sliding window strategies        |
| [Hourglass](src/Hourglass/README.md)   | Clock interface (PSR-20), FrozenClock, Stopwatch, Interval       |
| [Testing](src/Testing/README.md)       | Test harness: TestKernel, Factory, HTTP and CLI test surfaces    |

---

## Documentation

| Resource                                              | What it covers                                                                  |
| ----------------------------------------------------- | ------------------------------------------------------------------------------- |
| [COMPENDIUM.md](COMPENDIUM.md)                        | The guided tour — what Arcanum is, how the pieces fit, conventions, CLI surface |
| [Starter App](https://github.com/arcanum-org/arcanum) | Getting started, directory structure, examples                                  |
| [PLAN.md](PLAN.md)                                    | What's been built, what's coming, design decisions                              |
| Package READMEs                                       | Deep dives — linked in the table above                                          |

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
