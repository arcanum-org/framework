# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

Always use composer scripts to run tests and static analysis:

- `composer phpunit` — run full test suite
- `composer phpunit -- --filter ClassName` — run a single test class
- `composer phpstan` — run static analysis
- `composer cs-fix` — auto-fix code style
- `composer check` — run all checks (cs-fix, cs-check, phpstan, phpunit)

## Architecture

Arcanum is a CQRS PHP framework (not MVC). It's a monorepo with packages under `src/`, each with a matching test directory under `tests/`. PHPUnit config is at `contrib/phpunit.xml`.

### Key packages and how they connect

- **Cabinet** — DI container (PSR-11). Uses Codex for auto-wiring. Supports services, factories, singletons, prototypes, decorators, and middleware on services.
- **Codex** — Reflection-based class resolver. Resolves constructor dependencies recursively. Supports specifications (`when X needs Y give Z`).
- **Flow** — Data flow, four subpackages:
  - **Pipeline** — linear stage chain (`object → object → object`)
  - **Continuum** — middleware pattern (each stage calls `$next`)
  - **Conveyor** — command bus (MiddlewareBus). Dispatches objects to handlers by naming convention (`PlaceOrder` → `PlaceOrderHandler`). Combines Pipeline + Continuum for before/after middleware.
  - **River** — PSR-7 stream implementations
- **Echo** — PSR-14 event dispatcher. Uses Flow Pipeline internally.
- **Gather** — Typed key-value registries. `Configuration` (dot-notation), `Environment` (no serialize/clone), `IgnoreCaseRegistry` (HTTP headers).
- **Ignition** — Bootstrap kernel. `HyperKernel` runs bootstrappers: Environment → Configuration → Logger → Exceptions.
- **Hyper** — PSR-7 HTTP messages and PSR-15 server handler.
- **Glitch** — Error/exception/shutdown handling with reporter system.
- **Quill** — Multi-channel PSR-3 logger over Monolog.
- **Atlas** — Convention-based CQRS router. Maps inputs (HTTP, CLI in future) to Query/Command namespaces. Core mapping is transport-agnostic; HTTP adapter extracts response format from file extensions.
- **Shodo** — Output rendering. `JsonRenderer`, `JsonExceptionRenderer`, format registry (WIP).
- **Parchment** — File utilities.
- **Toolkit** — String utilities.

### Testing patterns

- PHPUnit 13 with attributes: `#[CoversClass(...)]`, `#[UsesClass(...)]`
- Strict coverage enabled — every test class must declare what it covers
- Tests mirror src structure: `src/Hyper/Headers.php` → `tests/Hyper/HeadersTest.php`
- Arrange-Act-Assert pattern throughout
- Fixtures live in `tests/Fixture/` or in subpackage test directories

### Starter project

A WIP starter app lives at `../arcanum/` — it demonstrates how apps consume the framework via Cabinet container, HyperKernel, and config files.
