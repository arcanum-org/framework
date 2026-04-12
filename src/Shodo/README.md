# Arcanum Shodo

Shodo (書道, "the way of writing") is the output formatting package. It converts handler results into strings — JSON, CSV, HTML, plain text, key-value pairs, tables — without any knowledge of HTTP, CLI, or any other transport. Formatters produce content. Kernels deliver it.

## Architecture

Shodo has a three-layer architecture:

1. **TemplateResolver** — maps a DTO class name to a filesystem path via PSR-4 convention. `resolveForStatus()` resolves status-specific templates (`Dto.422.html` → `errors/422.html` → null).
2. **TemplateEngine** — the mechanical render pipeline: compile, cache, execute. Four methods: `render()`, `renderFragment()` (content section only), `renderElement()` (element-by-id extraction), `renderSource()` (arbitrary source string).
3. **Formatters** — own data → variable conversion, escape function setup, and helper scoping. Receive a pre-resolved template path and delegate to the engine.

Transport layers wrap the result:

- **HTTP** (Hyper) — response renderers compose a `TemplateResolver` to resolve the template path, then call the formatter, then wrap in a `ResponseInterface`
- **CLI** (Rune) — writes formatter output to `Output` directly

```
TemplateResolver (path resolution)
        ↓
ResponseRenderer (HTTP adapter — resolves path, calls formatter)
        ↓
Formatter (data → variables, escape function, helpers)
        ↓
TemplateEngine (compile → cache → execute)
```

This separation means the same rendering pipeline serves success responses, error responses, and fallbacks. The same `TemplateEngine` renders user templates, status-specific templates (`AddEntry.422.html`), and bundled fallback templates.

## Formatters

All formatters implement the `Formatter` interface:

```php
interface Formatter
{
    public function format(mixed $data, string $templatePath = '', string $dtoClass = ''): string;
}
```

### JsonFormatter

Pure data serializer. Encodes any data as JSON:

```php
$formatter = new JsonFormatter();
$json = $formatter->format(['name' => 'Arcanum', 'version' => 1]);
// → {"name":"Arcanum","version":1}
```

### HtmlFormatter

Template-based. Receives a pre-resolved template path, builds variables from the handler's data (with `htmlspecialchars` as the escape function), and delegates to the `TemplateEngine`:

```php
// Handler returns:
['title' => 'Products', 'items' => ['Widget', 'Gadget']]

// Template at app/Domain/Shop/Query/Products.html:
// <h1>{{ $title }}</h1>
// {{ foreach($items as $item) }}<p>{{ $item }}</p>{{ endforeach }}

// → rendered HTML string
```

When no template path is provided, renders the bundled fallback template (`src/Shodo/Templates/fallback.html`) — a generic definition-list representation of the data, through the same engine.

### PlainTextFormatter

Template-based, like HTML. Receives a pre-resolved `.txt` template path. Uses an identity escape function — `{{ $var }}` passes through as-is since there's no HTML to escape.

```
// Template at app/Domain/Query/Health.txt:
Status: {{ $status }}
PHP: {{ $php_version }}

// → plain text string
```

Falls back to the bundled `fallback.txt` template when no path is provided.

### MarkdownFormatter

Template-based, like HTML and plain text. Receives a pre-resolved `.md` template path. Uses an identity escape function — `{{ $var }}` passes through as-is since Markdown doesn't require HTML escaping.

```
// Template at app/Domain/Query/Health.md:
# {{ $name }}

**Status:** {{ $status }}

{{ foreach($checks as $check) }}
- {{ $check }}
{{ endforeach }}

// → Markdown string
```

Falls back to the bundled `fallback.md` template when no path is provided.

### CsvFormatter

Pure data serializer. Renders tabular data (list of associative arrays) as CSV with proper escaping:

```php
$formatter = new CsvFormatter();
$csv = $formatter->format([
    ['name' => 'Alice', 'age' => 30],
    ['name' => 'Bob', 'age' => 25],
]);
// → name,age
//   Alice,30
//   Bob,25
```

Associative arrays render as key-value pairs. Scalar values render as a single-column table. Nested values are JSON-encoded within cells.

### KeyValueFormatter

Auto-detects output format from data shape — the default CLI formatter:

- **Object/associative array** → aligned key-value pairs
- **List of objects/arrays** → ASCII table (delegates to `TableFormatter`)
- **Scalar** → plain text
- **Null/empty** → empty string

```php
$formatter = new KeyValueFormatter();
$formatter->format(['status' => 'ok', 'version' => '1.0']);
// →   status   ok
//     version  1.0
```

### TableFormatter

Renders list data as an ASCII table with auto-detected columns:

```php
$formatter = new TableFormatter();
$formatter->format([
    ['id' => 1, 'name' => 'Jo'],
    ['id' => 2, 'name' => 'Sam'],
]);
// → ┌────┬──────┐
//   │ id │ name │
//   ├────┼──────┤
//   │ 1  │ Jo   │
//   │ 2  │ Sam  │
//   └────┴──────┘
```

## Template syntax

Templates use `{{ }}` delimiters for everything. The compiler is format-agnostic — each formatter injects its own escape function.

### One rule to read them all

What's inside `{{ }}` is one of three things, distinguished by the first character:

| Starts with | Meaning | Example |
|---|---|---|
| `$` | Variable expression | `{{ $name }}`, `{{ $user->email }}` |
| Uppercase letter | Helper call | `{{ Route::url('home') }}`, `{{ Format::number($price, 2) }}` |
| Lowercase keyword | Directive | `{{ extends 'layout' }}`, `{{ if $foo }}`, `{{ csrf }}` |

Mirrors PHP's own conventions — `$variables`, `ClassNames`, `keywords`. No special prefix is required because the first character already tells you what you're looking at.

### Output

```
{{ $name }}              Escaped output (htmlspecialchars for HTML, identity for plain text)
{{! $rawHtml !}}         Raw output (no escaping — see security note below)
```

> **Security:** `{{! !}}` raw output bypasses all escaping. Only use it with trusted content — never with user input. If user-supplied data reaches a `{{! !}}` block, it's an XSS vector. The default `{{ }}` syntax is always safe.

### Control flow

The preferred form is paren-free, but the compiler also accepts parens and the PHP alt-syntax trailing colon. All three forms compile to identical output:

```
{{ if $foo > 0 }}        preferred — clean, paren-free
{{ if ($foo > 0) }}      also accepted
{{ if ($foo > 0): }}     also accepted (PHP alt syntax)
```

```
{{ if $user->isLoggedIn() }}
    <p>Welcome, {{ $user->name }}!</p>
{{ elseif $isGuest }}
    <p>Hello, guest.</p>
{{ else }}
    <p>Please log in.</p>
{{ endif }}

{{ foreach $items as $item }}
    <li>{{ $item->name }}</li>
{{ endforeach }}

{{ for $i = 0; $i < 3; $i++ }}
    <span>{{ $i }}</span>
{{ endfor }}

{{ while $running }}
    ...
{{ endwhile }}
```

### Match — switch-style branching

For selecting one branch out of N based on a single subject value:

```
{{ match $status }}
    {{ case 'pending', 'active' }}
        <span class="text-success">Active</span>
    {{ case 'closed' }}
        <span class="text-error">Closed</span>
    {{ default }}
        <span class="text-stone">Unknown</span>
{{ endmatch }}
```

Compiles to a PHP `switch` with implicit `break` after every case body. Comma-separated values in `case` map to PHP fall-through case lists. The match subject is evaluated exactly once.

`match` is for equality matching against a single subject. For free-form conditions, use `if`/`elseif`/`else`.

### Variable binding

The handler's return value becomes the template's variables:

- **Array** — keys become variables: `['name' => 'Alice']` → `{{ $name }}`
- **Object** — public properties become variables
- **Scalar** — available as `{{ $data }}`

## Layouts

Templates can extend a layout using `{{ extends 'layout' }}`. The layout defines `{{ yield 'name' }}` slots, and child templates fill them with `{{ section 'name' }}...{{ endsection }}`:

**Layout** (`app/Pages/layout.html`):
```html
<!DOCTYPE html>
<html>
<head><title>{{ yield 'title' }}</title></head>
<body>
{{ include 'partials/nav' }}
<main>{{ yield 'content' }}</main>
{{ include 'partials/footer' }}
</body>
</html>
```

**Child template** (`app/Pages/Index.html`):
```html
{{ extends 'layout' }}

{{ section 'title' }}Home{{ endsection }}

{{ section 'content' }}
<h1>{{ $name }}</h1>
<p>{{ $message }}</p>
{{ endsection }}
```

Layout resolution walks up directories from the child template's location. A `Pages/layout.html` is found before a top-level `layout.html`, so subdirectories can have their own layouts.

Unfilled `{{ yield }}` slots produce an empty string.

## Includes

The `{{ include 'path' }}` directive inlines another template file's contents before compilation:

```html
{{ include 'partials/nav' }}
{{ include 'partials/footer.html' }}
```

Paths resolve relative to the current template's directory. The `.html` extension is optional — it's tried automatically when no extension is given.

Includes nest up to 10 levels deep. Included files can themselves use `{{ include }}`.

## Fragment rendering (htmx)

When a template uses `extends`, the same file can serve both full-page loads and htmx partial swaps. The `TemplateEngine` provides two methods:

- `render($path, $variables)` — full render with layout
- `renderFragment($path, $variables)` — content section only, layout skipped

The `HtmxAwareResponseRenderer` calls `renderFragment()` for htmx boosted navigation requests and `renderElement()` for targeted partial swaps. Full page loads go through `render()`.

The `TemplateCompiler` mirrors this split with `compile()` and `compileFragment()` — no boolean flags.

## Template discovery

Templates are co-located with their handler DTOs. The `TemplateResolver` maps a DTO class name to a template file using PSR-4 convention:

```
App\Domain\Shop\Query\Products  →  app/Domain/Shop/Query/Products.html
App\Pages\Index                  →  app/Pages/Index.html
App\Pages\About                  →  app/Pages/About.txt
```

### Status-specific templates

`resolveForStatus($dtoClass, $statusCode, $format)` resolves templates for a specific HTTP status code. Resolution order:

1. **Co-located:** `{DtoClass}.{status}.{format}` — e.g., `AddEntry.422.html` next to the command
2. **App-wide:** `{errorTemplatesDirectory}/{status}.{format}` — e.g., `app/Templates/errors/422.html`
3. **null** — no status-specific template, fall through to default

Works for any status code — error templates (422, 500), success templates (200, 201), or anything in between.

### Resolution in the rendering pipeline

Template-based response renderers (`Html`, `PlainText`, `Markdown`) compose a `TemplateResolver` and resolve the path before calling the formatter. Resolution order for each request:

1. `resolveForStatus($dtoClass, $statusCode)` — status-specific (skipped for 200)
2. `resolve($dtoClass)` — default co-located template
3. Bundled fallback template — generic data representation

## Template compilation and caching

The `TemplateCompiler` translates template syntax into PHP via regex replacements. Compiled templates are cached by `TemplateCache` — the cache checks source file modification times and recompiles when the template changes.

Caching is controlled via `config/cache.php`:

```php
'templates' => [
    'enabled' => ($_ENV['APP_DEBUG'] ?? 'false') !== 'true',
],
```

## Template helpers

Template helpers provide reusable functions callable from templates using static-method syntax:

```html
{{ Format::number($price, 2) }}
{{ Route::url('App\\Domain\\Query\\Health') }}
{{ Str::truncate($description, 100) }}
{{! Csrf::field() !}}
{{ csrf }}
```

The compiler rewrites `Name::method(...)` to `$__helpers['Name']->method(...)`. Escaped output (`{{ }}`) wraps the result in `$__escape`; raw output (`{{! !}}`) does not. The `{{ csrf }}` directive is shorthand for `{{! Csrf::field() !}}`.

### Helper calls inside expressions

The body of `{{ }}` (and `{{! !}}`) is treated as an arbitrary PHP expression. Every helper-call occurrence inside it is rewritten in a single pass — anything PHP allows after a method call composes naturally:

```html
{{ Tip::pick()['title'] }}                     array access
{{ User::current()->name }}                    method chain
{{ Math::pi() + 1 }}                           arithmetic
{{ Env::debugMode() ? 'on' : 'off' }}          ternary
{{ User::current() ?? 'guest' }}               null coalesce
{{ Format::number(Math::pi(), 2) }}            nested helper calls
{{ Str::upper($name) . '!' }}                  concatenation
```

Helper calls also work inside control-structure conditions:

```html
{{ if Env::debugMode() }}...{{ endif }}
{{ foreach Wired::list() as $item }}...{{ endforeach }}
```

**Escape hatch — fully-qualified static calls.** A real PHP static call inside a template would normally collide with a helper alias. Lead with a backslash to opt out of the rewrite:

```html
{{ \App\Foo::bar() }}
```

The lookbehind also leaves `$Format::method()` (variable static call) and partially-qualified `Namespace\Foo::bar()` alone. String literals containing helper-shaped text are out of scope — the rewriter operates on the raw expression body and would mangle `'A::b()'`. Templates don't quote helper-shaped strings in practice; revisit if a real fixture needs it.

### Built-in helpers

| Alias | Class | Methods |
|-------|-------|---------|
| **Format** | `FormatHelper` | `number($value, $decimals, $decSep, $thousandsSep)`, `date($timestamp, $format)` |
| **Str** | `StrHelper` | `truncate($text, $length, $suffix)`, `lower($str)`, `upper($str)`, `title($str)`, `kebab($str)` |
| **Arr** | `ArrHelper` | `count($items)`, `join($items, $sep)`, `first($items)`, `last($items)` |
| **Route** | `RouteHelper` | `url($dtoClass)`, `asset($path)` — requires HTTP bootstrap |
| **Html** | `HtmlHelper` | `url($href)`, `js($value)`, `attr($value)`, `css($value)`, `nonce()`, `classIf($cond, $class)` |
| **Csrf** | `CsrfHelper` | `field()`, `token()` — requires HTTP bootstrap (ActiveSession) |

Format, Str, Arr, and Html are always available. Route and Csrf are registered by the HTTP bootstrap when their dependencies exist (UrlResolver, ActiveSession).

### Context-specific output encoding

Shodo's `{{ }}` applies `htmlspecialchars()` — correct for HTML body text and quoted attributes, but not for other contexts. The `Html` helper provides the right encoding for each OWASP context:

```html
<a href="{{ Html::url($userLink) }}">Profile</a>        URL scheme validation
<div title={{ Html::attr($title) }}>                     strict attribute encoding
<script>var name = '{{ Html::js($name) }}';</script>     JS string encoding
<div style="color: {{ Html::css($color) }}">              CSS value encoding
```

- **`Html::url()`** rejects `javascript:`, `data:`, and other dangerous schemes. Safe schemes: `http`, `https`, `mailto`, `tel`, plus relative paths.
- **`Html::js()`** encodes everything non-alphanumeric as `\uHHHH`. Prefer `data-` attributes over inline JS when possible.
- **`Html::attr()`** encodes everything non-alphanumeric as `&#xHH;`. Use for unquoted or event-handler attributes.
- **`Html::css()`** encodes everything non-alphanumeric as `\HEX `. Avoid user data in style attributes when possible.

### App-wide helpers (`app/Helpers/Helpers.php`)

For helpers available to *every* template — including Pages — register them in a special file at `app/Helpers/Helpers.php`. `Bootstrap\Helpers` reads this file explicitly at boot time and merges its entries into the global `HelperRegistry`:

```php
<?php
// app/Helpers/Helpers.php

return [
    'App' => \App\Helpers\AppHelper::class,
];
```

This is the file the starter app ships to register its `App::cssTags()` helper. Anything registered here is reachable from any template by its alias.

> **Note.** `app/Helpers/Helpers.php` is a hardcoded special path that `Bootstrap\Helpers` reads directly. It is *not* discovered by `HelperDiscovery`, which only walks `app/Domain/` (see below). The asymmetry exists for historical reasons and is tracked under `PLAN.md` for future cleanup — global helpers will eventually move to `config/helpers.php` to parallel `config/middleware.php`.

### Domain-scoped helpers

Custom helpers can also be registered via co-located `Helpers.php` files inside `app/Domain/`. `HelperDiscovery` walks `app/Domain/` only, so files outside that subtree are not picked up:

```
app/Domain/Helpers.php              → available to all DTOs under App\Domain\*
app/Domain/Shop/Helpers.php         → only Shop queries/commands
app/Domain/Shop/Checkout/Helpers.php → only Checkout subdomain
```

Each file returns an array mapping aliases to class names:

```php
<?php

return [
    'Cart' => \App\Domain\Shop\CartHelper::class,
];
```

At format-time, the `HelperResolver` walks from the root to the DTO's namespace, accumulating helpers. Deeper directories override shallower ones — a `Cart` alias in `Shop/Checkout/Helpers.php` overrides the same alias in `Shop/Helpers.php`.

Discovery results are cached via PSR-16 (controlled by `cache.helpers.enabled` in `config/cache.php`).

> **Pages cannot have a discovered `Helpers.php`.** `HelperDiscovery` only walks `app/Domain/`, so a file at `app/Pages/Helpers.php` is not picked up. Pages get the global helpers from `app/Helpers/Helpers.php`, plus whatever they declare via `#[WithHelper]` on the Page DTO class itself. This is asymmetric with `Middleware.php` (which is discovered across the whole `app/` tree); both halves of the asymmetry are tracked in `PLAN.md` for future cleanup.

### Per-DTO helpers via `#[WithHelper]`

Some helpers only make sense for one specific DTO — a welcome page's diagnostic helpers, a one-off admin screen, a debug-only inspector. Registering these globally would pollute every render with code only one page uses; putting them in a domain `Helpers.php` would still leak to every DTO under that namespace.

The `#[WithHelper]` attribute lets a DTO declare its own helpers inline:

```php
use Arcanum\Shodo\Attribute\WithHelper;

#[WithHelper(\App\Helpers\EnvCheckHelper::class, 'Env')]
#[WithHelper(\App\Helpers\IncantationHelper::class, 'Tip')]
final class Index
{
    public function __construct(
        public readonly string $name = 'Arcanum',
    ) {}
}
```

The attribute is repeatable — declare one per helper. Both the helper class and the template alias are explicit; there's no auto-derivation. The class is resolved from the container at render time, so the helper can ask for any service the container can provide.

**Precedence, from least to most specific:**

```
global registry  ←  domain Helpers.php  ←  #[WithHelper] on the DTO
   (built-ins         (discovered under          (declared on the
    + app/Helpers/      app/Domain/, walked        DTO class itself,
    Helpers.php)        from root to leaf)         highest priority)
```

A `#[WithHelper]` declaration always wins over both global and domain-discovered helpers — the DTO has explicitly named what it needs. Use this for narrow, page-specific helpers; keep `Helpers.php` files for genuinely shared functionality.

### How it works

```
TemplateCompiler
  compiles {{ Route::url(...) }} → $__helpers['Route']->url(...)

HelperResolver
  for('App\Domain\Shop\Query\Products')
    → merges global HelperRegistry
    → overlays matching domain Helpers.php files
    → overlays #[WithHelper] attributes from the DTO class
    → returns ['Format' => FormatHelper, 'Route' => RouteHelper, 'Cart' => CartHelper, ...]

Formatter.buildVariables()
  injects $__helpers into the variable array

TemplateEngine.render()
  extracts variables into scope, executes compiled template
```

## CLI format registry

The `CliFormatRegistry` maps `--format` values to formatters for CLI output:

```
--format=cli   → KeyValueFormatter (default)
--format=table → TableFormatter
--format=json  → JsonFormatter
--format=csv   → CsvFormatter
```

## HTTP format registry

For HTTP, Hyper's `FormatRegistry` maps URL file extensions to response renderers that compose Shodo formatters. Configure in `config/formats.php`:

```php
return [
    'default' => 'json',
    'formats' => [
        'json' => [
            'content_type' => 'application/json',
            'renderer' => \Arcanum\Hyper\JsonResponseRenderer::class,
        ],
        'html' => [
            'content_type' => 'text/html',
            'renderer' => \Arcanum\Hyper\HtmlResponseRenderer::class,
        ],
        'csv' => [
            'content_type' => 'text/csv',
            'renderer' => \Arcanum\Hyper\CsvResponseRenderer::class,
        ],
        'txt' => [
            'content_type' => 'text/plain',
            'renderer' => \Arcanum\Hyper\PlainTextResponseRenderer::class,
        ],
        'md' => [
            'content_type' => 'text/markdown',
            'renderer' => \Arcanum\Hyper\MarkdownResponseRenderer::class,
        ],
    ],
];
```

To add a custom format, add an entry and implement `Formatter`. To disable a format, remove it from the array. Requesting a disabled or unknown format returns **406 Not Acceptable**.

## At a glance

```
Shodo (pure formatting — no transport dependency)
├── Formatter interface
├── Formatters/
│   ├── JsonFormatter (pure serializer)
│   ├── CsvFormatter (pure tabular serializer)
│   ├── HtmlFormatter (template-based, htmlspecialchars escape)
│   ├── PlainTextFormatter (template-based, identity escape)
│   ├── MarkdownFormatter (template-based, identity escape)
│   ├── KeyValueFormatter (auto-detect: key-value pairs or table)
│   └── TableFormatter (ASCII tables)
├── TemplateResolver (DTO class → filesystem path, status-specific resolution)
├── TemplateEngine (compile → cache → execute, fragment/element/source modes)
├── TemplateCompiler (compile / compileFragment → CompilerDirective pipeline)
├── TemplateCache (compiled PHP cache with mtime + dependency freshness)
├── Templates/
│   ├── fallback.html (bundled generic HTML representation)
│   ├── fallback.txt (bundled generic plain text representation)
│   └── fallback.md (bundled generic Markdown representation)
├── HelperRegistry → HelperResolver → HelperDiscovery
├── Helpers/
│   ├── FormatHelper, StrHelper, ArrHelper (always available)
│   └── RouteHelper, HtmlHelper (HTTP bootstrap)
├── CliFormatRegistry (--format → Formatter)
├── Format (extension + content type value object)
└── UnsupportedFormat (exception)

Hyper (HTTP response adapters — compose Shodo formatters)
├── ResponseRenderer (abstract base)
├── HtmlResponseRenderer → TemplateResolver + HtmlFormatter
├── PlainTextResponseRenderer → TemplateResolver + PlainTextFormatter
├── MarkdownResponseRenderer → TemplateResolver + MarkdownFormatter
├── JsonResponseRenderer → JsonFormatter
├── CsvResponseRenderer → CsvFormatter
├── EmptyResponseRenderer (status-code-only for commands)
├── HtmlExceptionResponseRenderer → TemplateEngine (unified error pipeline)
├── JsonExceptionResponseRenderer (exceptions as JSON)
└── FormatRegistry (URL extension → ResponseRenderer)
```
