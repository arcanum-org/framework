# Arcanum Shodo

Shodo (書道, "the way of writing") is the output formatting package. It converts handler results into strings — JSON, CSV, HTML, plain text, key-value pairs, tables — without any knowledge of HTTP, CLI, or any other transport. Formatters produce content. Kernels deliver it.

## Architecture

Shodo owns **formatting**. Each formatter takes data in and returns a string. Transport layers wrap the result:

- **HTTP** (Hyper) — wraps formatter output in a `ResponseInterface` with Content-Type, Content-Length, and status code via response renderers
- **CLI** (Rune) — writes formatter output to `Output` directly

```
Shodo (pure formatting)
  ↑              ↑
Hyper            Rune
(HTTP adapters)  (uses formatters directly)
```

This separation means the same `JsonFormatter` serves both `GET /health.json` and `php arcanum query:health --format=json`. No duplicate formatting logic.

## Formatters

All formatters implement the `Formatter` interface:

```php
interface Formatter
{
    public function format(mixed $data, string $dtoClass = ''): string;
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

Template-based. Discovers a co-located `.html` template file by convention, compiles it, and renders with the handler's data as template variables:

```php
// Handler returns:
['title' => 'Products', 'items' => ['Widget', 'Gadget']]

// Template at app/Domain/Shop/Query/Products.html:
// <h1>{{ $title }}</h1>
// {{ foreach($items as $item) }}<p>{{ $item }}</p>{{ endforeach }}

// → rendered HTML string
```

When no template exists, falls back to a generic HTML dump of the data (definition lists for key-value pairs, unordered lists for arrays).

### PlainTextFormatter

Template-based, like HTML. Discovers a co-located `.txt` template file. Uses an identity escape function — `{{ $var }}` passes through as-is since there's no HTML to escape.

```
// Template at app/Domain/Query/Health.txt:
Status: {{ $status }}
PHP: {{ $php_version }}

// → plain text string
```

Falls back to a YAML-like structured dump when no template exists.

### MarkdownFormatter

Template-based, like HTML and plain text. Discovers a co-located `.md` template file. Uses an identity escape function — `{{ $var }}` passes through as-is since Markdown doesn't require HTML escaping.

```
// Template at app/Domain/Query/Health.md:
# {{ $name }}

**Status:** {{ $status }}

{{ foreach($checks as $check) }}
- {{ $check }}
{{ endforeach }}

// → Markdown string
```

Falls back to a structured Markdown representation when no template exists — bold keys for associative arrays (`**key:** value`), bulleted lists for sequential arrays, and `##` headings for nested structures.

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

When a template uses `extends`, the same file can serve both full-page loads and htmx partial swaps. In **fragment mode**, only the `content` section is rendered — the layout wrapper (head, nav, footer) is skipped.

The `HtmlFormatter` exposes `setFragment(bool)`. Call it from middleware when the `HX-Request` header is present:

```php
if ($request->hasHeader('HX-Request')) {
    $formatter->setFragment(true);
}
```

Full page load returns the complete layout. htmx request returns just the content — ready for `hx-swap`.

### Named fragments

For finer-grained partial rendering, named fragments let you address specific regions inside a template. A page and its addressable sub-regions live in one file:

```html
{{ extends 'layout' }}

{{ section 'content' }}
<h1>Products</h1>

{{ fragment 'product-list' }}
<div id="product-list">
    <ul>
    {{ foreach $products as $product }}
        <li>{{ $product }}</li>
    {{ endforeach }}
    </ul>
</div>
{{ endfragment }}

<hr>

{{ fragment 'sidebar' }}
<aside id="sidebar">
    <h3>{{ $sidebarTitle }}</h3>
</aside>
{{ endfragment }}
{{ endsection }}
```

**Convention: fragment names match DOM element ids.** A `{{ fragment 'product-list' }}` block wraps a `<div id="product-list">`. htmx sends `HX-Target: div#product-list`, the framework extracts the id, looks up the named fragment, renders it. Self-consistent loop.

#### Basic usage

Render a specific fragment via `HtmlFormatter::renderFragment()`:

```php
// Renders only the 'product-list' fragment — no layout, no surrounding section
$html = $formatter->renderFragment('product-list', $data, $dtoClass);
```

In a full page load, fragment markers are transparent — their content renders inline as part of the section. No special handling needed.

#### Multiple fragments per template

A single template can have multiple fragments. Each compiles and caches independently (the cache key is `template path + fragment name`), so rendering one fragment never compiles the others:

```html
{{ section 'content' }}
{{ fragment 'stats' }}<div id="stats">{{ $count }} items</div>{{ endfragment }}
{{ fragment 'chart' }}<div id="chart">{{ $chartHtml }}</div>{{ endfragment }}
{{ endsection }}
```

#### Fragments with conditionals

Fragments can contain any template syntax — control flow, helpers, includes:

```html
{{ fragment 'alert' }}
<div id="alert">
{{ if $hasError }}
    <p class="text-red-500">{{ $errorMessage }}</p>
{{ else }}
    <p class="text-green-500">All clear.</p>
{{ endif }}
</div>
{{ endfragment }}
```

#### Fall-through on unknown fragment name

When `renderFragment()` is called with a name that doesn't exist in the template, it logs a warning and falls back to rendering the content section (the same behavior as `setFragment(true)`). This is a soft failure — a typo in a fragment name shouldn't 404 a real user, but the framework log surfaces the bug for the developer.

#### Constraints

- **Fragments cannot be nested.** `{{ fragment 'outer' }}{{ fragment 'inner' }}…{{ endfragment }}{{ endfragment }}` throws a `RuntimeException`. Fragments are flat addressable regions, not a hierarchy.
- **Fragments live inside sections.** They're part of the template content, not a replacement for sections or layouts.
- **Unclosed `{{ fragment }}` or orphan `{{ endfragment }}`** both throw a `RuntimeException` with a clear message naming the offending directive.

## Template discovery

Templates are co-located with their handler DTOs. The `TemplateResolver` maps a DTO class name to a template file using PSR-4 convention:

```
App\Domain\Shop\Query\Products  →  app/Domain/Shop/Query/Products.html
App\Pages\Index                  →  app/Pages/Index.html
App\Pages\About                  →  app/Pages/About.txt
```

Each formatter has its own resolver configured for its file extension.

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
{{! Html::csrf() !}}
{{ csrf }}
```

The compiler rewrites `Name::method(...)` to `$__helpers['Name']->method(...)`. Escaped output (`{{ }}`) wraps the result in `$__escape`; raw output (`{{! !}}`) does not. The `{{ csrf }}` directive is shorthand for `{{! Html::csrf() !}}`.

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
| **Html** | `HtmlHelper` | `csrf()`, `csrfToken()`, `nonce()`, `classIf($cond, $class)` — requires HTTP bootstrap |

Format, Str, and Arr are always available. Route and Html are registered by the HTTP bootstrap when their dependencies exist (UrlResolver, ActiveSession).

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

HtmlFormatter / PlainTextFormatter / MarkdownFormatter
  injects $__helpers into template scope via extract()
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
│   ├── TableFormatter (ASCII tables)
│   ├── HtmlFallbackFormatter / PlainTextFallbackFormatter / MarkdownFallbackFormatter
├── TemplateCompiler → TemplateCache → TemplateResolver
├── HelperRegistry → HelperResolver → HelperDiscovery
├── Helpers/
│   ├── FormatHelper, StrHelper, ArrHelper (always available)
│   ├── RouteHelper, HtmlHelper (HTTP bootstrap)
├── CliFormatRegistry (--format → Formatter)
├── Format (extension + content type value object)
└── UnsupportedFormat (exception)

Hyper (HTTP response adapters — compose Shodo formatters)
├── ResponseRenderer (abstract base)
├── JsonResponseRenderer → JsonFormatter
├── CsvResponseRenderer → CsvFormatter
├── HtmlResponseRenderer → HtmlFormatter
├── PlainTextResponseRenderer → PlainTextFormatter
├── MarkdownResponseRenderer → MarkdownFormatter
├── EmptyResponseRenderer (status-code-only for commands)
├── JsonExceptionResponseRenderer (exceptions as JSON)
└── FormatRegistry (URL extension → ResponseRenderer)
```
