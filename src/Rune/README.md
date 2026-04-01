# Arcanum Rune

Rune is Arcanum's CLI transport layer. It lets you run the same Commands and Queries from the terminal that you run over HTTP — same DTOs, same handlers, same Conveyor bus. Where Atlas maps HTTP requests to routes, Rune maps CLI arguments. Where Hyper renders JSON/HTML for browsers, Rune renders tables and key-value output for terminals.

## Quick start

```bash
# Run a query
php arcanum query:health

# Run a query with parameters
php arcanum query:users:find --name="Alice"

# Run a command
php arcanum command:contact:submit --name="Jo" --email="jo@test.com"

# Choose an output format
php arcanum query:health --format=json
php arcanum query:health --format=csv
php arcanum query:health --format=table

# Get help for a command
php arcanum query:health --help

# List all available commands and queries
php arcanum list
```

## CLI syntax

```
php arcanum <prefix>:<domain>:<action> [--key=value ...] [--flag] [--format=FORMAT]
```

The prefix tells Rune whether you're running a **query** (read) or a **command** (write):

```
query:health                → App\Domain\Query\Health
query:users:find            → App\Domain\Users\Query\Find
command:contact:submit      → App\Domain\Contact\Command\Submit
command:users:deactivate    → App\Domain\Users\Command\Deactivate
```

This mirrors how HTTP methods determine intent — GET maps to queries, POST/PUT/DELETE map to commands. The CLI prefix is the equivalent.

Commands without a prefix are **built-in framework commands**:

```
php arcanum list                 → show all available commands
php arcanum help query:health    → show help for a specific command
php arcanum validate:handlers    → check all DTOs have handlers
```

## How routing works

Rune uses the same convention-based routing as HTTP. The `CliRouter` parses the `command:` or `query:` prefix, converts the remaining segments to PascalCase, and resolves the DTO class:

```
command:contact:submit
  ↓ prefix = "command"
  ↓ path = "contact:submit" → "Contact/Submit"
  ↓ namespace = App\Domain\Contact\Command\Submit
```

If you have a DTO at that namespace with a matching handler (`SubmitHandler`), you're done. No configuration, no annotations — just follow the naming convention.

### Custom CLI routes

For DTOs that don't follow the convention, add aliases in `config/routes.php`:

```php
return [
    'cli' => [
        'deploy' => [
            'class' => App\Infrastructure\Command\Deploy::class,
            'type' => 'command',
        ],
        'status' => [
            'class' => App\Monitoring\Query\SystemStatus::class,
            'type' => 'query',
        ],
    ],
];
```

Now `php arcanum command:deploy` and `php arcanum query:status` route to those classes.

## Input parsing

Rune's `Input` class parses `$argv` into a structured representation:

```bash
php arcanum command:contact:submit --name="Jo" --email="jo@test.com" --verbose
```

This produces:

| Part | Value |
|---|---|
| Command | `command:contact:submit` |
| Options | `['name' => 'Jo', 'email' => 'jo@test.com']` |
| Flags | `['verbose' => true]` |

Options (`--key=value`) and flags (`--verbose`) are merged together and passed to the Hydrator, which maps them to DTO constructor parameters — the same mechanism HTTP uses for query params and request bodies.

### Special flags

- **`--help`** — Show help for the command instead of executing it
- **`--format=FORMAT`** — Choose output format (`cli`, `json`, `csv`, `table`)

## Output formats

Results are formatted by Shodo formatters via the `CliFormatRegistry`. The default format is `cli` (key-value pairs), but you can switch with `--format`:

| Format | Formatter | Best for |
|---|---|---|
| `cli` (default) | `KeyValueFormatter` | Human reading — auto-detects data shape |
| `json` | `JsonFormatter` | Piping to `jq` or other tools |
| `csv` | `CsvFormatter` | Spreadsheets, data processing |
| `table` | `TableFormatter` | Lists of records |

```bash
# Default: key-value pairs
$ php arcanum query:health
  status  ok

# JSON
$ php arcanum query:health --format=json
{"status":"ok"}

# CSV
$ php arcanum query:health --format=csv
key,value
status,ok

# Table
$ php arcanum query:health --format=table
┌────────┬───────┐
│ key    │ value │
├────────┼───────┤
│ status │ ok    │
└────────┴───────┘
```

The `KeyValueFormatter` (default) auto-detects the best layout from data shape:
- **Single object or associative array** → aligned key-value pairs
- **List of objects/arrays** → ASCII table (delegates to `TableFormatter`)
- **Scalar** → plain text
- **Null/empty** → silent (just the exit code)

## Help system

Add `--help` to any command to see its parameters:

```bash
$ php arcanum query:users:find --help

Usage: query:users:find [options]
Type:  query

Options:
  --name=<string>          The user's name (required)
  --email=<string>         The user's email (required)
  [--active=<bool>]        Only show active users (default: true)
```

The help is generated automatically from your DTO's constructor via reflection. Add `#[Description]` attributes for human-readable descriptions:

```php
use Arcanum\Rune\Attribute\Description;

#[Description('Find users by name and/or email')]
final class Find
{
    public function __construct(
        #[Description('The user\'s name')]
        public readonly string $name,
        #[Description('The user\'s email')]
        public readonly string $email,
        #[Description('Only show active users')]
        public readonly bool $active = true,
    ) {}
}
```

The `#[Description]` attribute is purely for CLI help — it's ignored by HTTP.

## Built-in commands

Rune ships with framework commands that don't use a `command:` or `query:` prefix:

### `list`

Discovers and displays all available commands and queries:

```bash
$ php arcanum list

Built-in commands:
  list
  help
  validate:handlers
  make:key
  cache:clear
  cache:status
  make:command
  make:query
  make:page
  make:middleware

Queries:
  query:health        Check application status

Commands:
  command:contact:submit    Submit a contact form
```

It scans your app's namespace for Command and Query DTOs, reads `#[Description]` attributes, and includes any custom CLI routes.

### `help`

An alias for `<command> --help`:

```bash
php arcanum help query:health
# Same as:
php arcanum query:health --help
```

### `validate:handlers`

Scans all DTO classes and verifies each one has a corresponding handler:

```bash
$ php arcanum validate:handlers
✓ Checked 12 DTOs — all have handlers.
```

If any handlers are missing, it reports which DTOs are missing them and exits with code 1. This is useful in CI pipelines to catch missing handlers before deployment.

### Adding your own built-in commands

Register custom operational commands (like `cache:clear` or `migrate`) in your CLI kernel or via config:

```php
// In your app's CLI Kernel or a bootstrapper:
$registry = $container->get(BuiltInRegistry::class);
$registry->register('cache:clear', CacheClearCommand::class);
```

Your command implements `BuiltInCommand`:

```php
use Arcanum\Rune\BuiltInCommand;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;

final class CacheClearCommand implements BuiltInCommand
{
    public function execute(Input $input, Output $output): int
    {
        // Clear caches...
        $output->writeLine('Cache cleared.');
        return 0;  // ExitCode::Success
    }
}
```

## Scaffolding generators

Rune ships with four code generators that create DTO, handler, and template stubs from the command line:

```bash
php arcanum make:command Contact/Submit
php arcanum make:query Users/Find
php arcanum make:page About
php arcanum make:middleware RateLimit
```

### make:command

Creates a Command DTO and void handler:

```bash
$ php arcanum make:command Contact/Submit
Created: app/Domain/Contact/Command/Submit.php
Created: app/Domain/Contact/Command/SubmitHandler.php
```

The handler has a `void` return type — commands don't return data.

### make:query

Creates a Query DTO and handler that returns `array`:

```bash
$ php arcanum make:query Users/Find
Created: app/Domain/Users/Query/Find.php
Created: app/Domain/Users/Query/FindHandler.php
```

### make:page

Creates a Page DTO and HTML template:

```bash
$ php arcanum make:page Docs/GettingStarted
Created: app/Pages/Docs/GettingStarted.php
Created: app/Pages/Docs/GettingStarted.html
```

The DTO gets a `$title` property with a default derived from the class name ("Getting Started"). The template is a minimal HTML5 boilerplate using Shodo's `{{ $title }}` syntax.

### make:middleware

Creates a PSR-15 middleware class:

```bash
$ php arcanum make:middleware RateLimit
Created: app/Http/Middleware/RateLimit.php
```

### Naming conventions

Slash-separated paths map to namespace segments. The last segment becomes the class name:

```
Contact/Submit       → App\Domain\Contact\Command\Submit
Admin/Users/BanUser  → App\Domain\Admin\Users\Command\BanUser
Submit               → App\Domain\Command\Submit (single segment)
```

All generators convert names to PascalCase automatically, refuse to overwrite existing files, and create intermediate directories as needed.

### Custom stubs

To customize the generated code, copy any stub from the framework's `src/Rune/Command/stubs/` directory to your app's `stubs/` directory:

```
your-app/
└── stubs/
    ├── command.stub           ← overrides the Command DTO template
    ├── command_handler.stub   ← overrides the Command handler template
    ├── query.stub
    ├── query_handler.stub
    ├── page.stub
    ├── page_template.stub
    └── middleware.stub
```

Stubs use Shodo's `{{! $variable !}}` syntax for placeholder substitution. Available variables vary by generator: `$namespace`, `$className`, and `$title` (pages only).

## Cache commands

```bash
php arcanum cache:clear              # clear all stores + framework caches
php arcanum cache:clear --store=file # clear only a specific store
php arcanum cache:status             # show configured stores and assignments
```

## Transport restriction

Sometimes a command or query should only be available on one transport. Mark DTOs with `#[CliOnly]` or `#[HttpOnly]`:

```php
use Arcanum\Rune\Attribute\CliOnly;

#[CliOnly]
final class Migrate
{
    // This command can only run from the terminal.
    // HTTP requests will get 405 Method Not Allowed.
}
```

```php
use Arcanum\Hyper\Attribute\HttpOnly;

#[HttpOnly]
final class WebhookCallback
{
    // This handler only accepts HTTP requests.
    // CLI invocations will show a clear error message.
}
```

The `TransportGuard` middleware in the Conveyor bus enforces these restrictions automatically.

## Exit codes

Rune follows standard POSIX conventions:

| Code | Enum | Meaning |
|---|---|---|
| 0 | `ExitCode::Success` | Command completed successfully |
| 1 | `ExitCode::Failure` | Runtime error (exception during dispatch) |
| 2 | `ExitCode::Invalid` | Invalid input (unknown command, bad arguments) |

Commands that return `void` exit with 0 silently. Commands that return `AcceptedDTO` (async/deferred work) print "Accepted." and exit with 0.

## Error handling

Exceptions during dispatch are caught and rendered to stderr:

**Production mode** (`APP_DEBUG=false`):
```
Error: No query found for "query:nonexistent:thing".
```

**Debug mode** (`APP_DEBUG=true`):
```
[Arcanum\Atlas\UnresolvableRoute] No query found for "query:nonexistent:thing".
  in src/Atlas/CliRouter.php:147
  #0 src/Atlas/CliRouter.php:106 Arcanum\Atlas\CliRouter->buildNotFoundError()
  #1 src/Ignition/RuneKernel.php:167 Arcanum\Atlas\CliRouter->resolve()
  ...
```

The `CliExceptionWriter` handles this — it reads the `app.debug` config value to decide how much detail to show.

## Console output

The `Output` interface provides four methods for writing to the terminal:

```php
$output->write('no newline');        // stdout, no trailing newline
$output->writeLine('with newline');  // stdout + newline
$output->error('error text');        // stderr, no trailing newline
$output->errorLine('error line');    // stderr + newline
```

`ConsoleOutput` is the concrete implementation. It auto-detects TTY support and strips ANSI color codes when output is piped to a file or another program:

```bash
# Colors enabled (interactive terminal)
php arcanum list

# Colors stripped (piped)
php arcanum list | cat
```

## At a glance

```
Input parsing:
  Input::fromArgv($argv) → command, options (--key=value), flags (--verbose)

Routing:
  CliRouter → ConventionResolver (shared with HTTP)
  command:contact:submit → App\Domain\Contact\Command\Submit
  Built-in commands checked first

Dispatch:
  Same Conveyor bus as HTTP → same handlers, same middleware

Output:
  CliFormatRegistry → KeyValueFormatter | TableFormatter | JsonFormatter | CsvFormatter

Built-in commands:
  list, help, validate:handlers        — discovery & debugging
  make:command, make:query             — generate CQRS stubs
  make:page, make:middleware           — generate page & middleware stubs
  make:key                             — generate APP_KEY
  cache:clear, cache:status            — cache management

Help:
  HelpWriter → reflection on DTO constructor + #[Description] attributes

Transport restriction:
  #[CliOnly] / #[HttpOnly] → TransportGuard middleware

Exit codes:
  0 = Success, 1 = Failure, 2 = Invalid
```
