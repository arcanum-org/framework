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

### Output

```
{{ $name }}              Escaped output (htmlspecialchars for HTML, identity for plain text)
{{! $rawHtml !}}         Raw output (no escaping, use for trusted content)
```

### Control flow

```
{{ foreach($items as $item) }}
    <p>{{ $item }}</p>
{{ endforeach }}

{{ if($show) }}
    <p>Visible</p>
{{ elseif($other) }}
    <p>Other</p>
{{ else }}
    <p>Hidden</p>
{{ endif }}

{{ for($i = 0; $i < 3; $i++) }}
    <span>{{ $i }}</span>
{{ endfor }}

{{ while($condition) }}
    ...
{{ endwhile }}
```

The colon after opening statements is optional: `{{ foreach($items as $item): }}` and `{{ foreach($items as $item) }}` are identical.

### Variable binding

The handler's return value becomes the template's variables:

- **Array** — keys become variables: `['name' => 'Alice']` → `{{ $name }}`
- **Object** — public properties become variables
- **Scalar** — available as `{{ $data }}`

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
    ],
];
```

To add a custom format, add an entry and implement `Formatter`. To disable a format, remove it from the array. Requesting a disabled or unknown format returns **406 Not Acceptable**.

## At a glance

```
Shodo (pure formatting — no transport dependency)
├── Formatter interface
├── JsonFormatter (pure serializer)
├── CsvFormatter (pure tabular serializer)
├── HtmlFormatter (template-based, htmlspecialchars escape)
├── PlainTextFormatter (template-based, identity escape)
├── KeyValueFormatter (auto-detect: key-value pairs or table)
├── TableFormatter (ASCII tables)
├── TemplateCompiler → TemplateCache → TemplateResolver
├── HtmlFallback / PlainTextFallback
└── CliFormatRegistry (--format → Formatter)

Hyper (HTTP response adapters — compose Shodo formatters)
├── ResponseRenderer (abstract base)
├── JsonResponseRenderer → JsonFormatter
├── CsvResponseRenderer → CsvFormatter
├── HtmlResponseRenderer → HtmlFormatter
├── PlainTextResponseRenderer → PlainTextFormatter
├── EmptyResponseRenderer (status-code-only for commands)
├── JsonExceptionResponseRenderer (exceptions as JSON)
└── FormatRegistry (URL extension → ResponseRenderer)
```
