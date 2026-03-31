# Arcanum Shodo

Shodo (書道, "the way of writing") is the output rendering package. It converts handler results into HTTP responses through a format-aware registry — the same handler can produce JSON, HTML, CSV, or plain text based on the file extension in the URL. Request an unsupported format? **406 Not Acceptable**.

## How it works

The URL extension determines the output format. `/products.json` renders JSON. `/products.html` renders HTML. `/products.csv` renders CSV. The `FormatRegistry` maps extensions to renderers, and the kernel calls the right one:

```php
$renderer = $formats->renderer($route->format);
$response = $renderer->render($result, $route->dtoClass);
```

Every renderer returns a `ResponseInterface` with the correct Content-Type and status code.

## Renderers

### JsonRenderer

Pure data serializer. Encodes any data as JSON with proper headers:

```php
$renderer = new JsonRenderer();
$response = $renderer->render(['name' => 'Arcanum', 'version' => 1]);
// → 200 OK, Content-Type: application/json
// → {"name":"Arcanum","version":1}
```

### HtmlRenderer

Template-based. Discovers a co-located `.html` template file by convention, compiles it, and renders with the handler's data as template variables:

```php
// Handler returns:
['title' => 'Products', 'items' => ['Widget', 'Gadget']]

// Template at app/Domain/Shop/Query/Products.html:
// <h1>{{ $title }}</h1>
// {{ foreach($items as $item) }}<p>{{ $item }}</p>{{ endforeach }}

// GET /shop/products.html → rendered HTML
```

When no template exists, falls back to a generic HTML dump of the data (definition lists for key-value pairs, unordered lists for arrays).

### PlainTextRenderer

Template-based, like HTML. Discovers a co-located `.txt` template file. Uses an identity escape function — `{{ $var }}` passes through as-is since there's no HTML to escape.

```
// Template at app/Domain/Query/Health.txt:
Status: {{ $status }}
PHP: {{ $php_version }}

// GET /health.txt → plain text response
```

Falls back to a YAML-like structured dump when no template exists.

### CsvRenderer

Pure data serializer. Renders tabular data (list of associative arrays) as CSV with proper escaping:

```php
$renderer = new CsvRenderer();
$response = $renderer->render([
    ['name' => 'Alice', 'age' => 30],
    ['name' => 'Bob', 'age' => 25],
]);
// → 200 OK, Content-Type: text/csv
// → name,age
//   Alice,30
//   Bob,25
```

Associative arrays render as key-value pairs. Scalar values render as a single-column table. Nested values are JSON-encoded within cells.

### EmptyResponseRenderer

For commands. Returns a status-code-only response with no body:

```php
$renderer = new EmptyResponseRenderer();
$response = $renderer->render(StatusCode::NoContent); // 204, empty body
```

### JsonExceptionRenderer

Renders exceptions as JSON error responses. Maps `HttpException` to its status code, defaults to 500 for generic exceptions. Includes stack trace in debug mode only.

## Template syntax

Templates use `{{ }}` delimiters for everything. The compiler is format-agnostic — each renderer injects its own escape function.

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

Each renderer has its own resolver configured for its file extension.

## Template compilation and caching

The `TemplateCompiler` translates template syntax into PHP via regex replacements. Compiled templates are cached by `TemplateCache` — the cache checks source file modification times and recompiles when the template changes.

Caching is controlled via `config/cache.php`:

```php
'templates' => [
    'enabled' => ($_ENV['APP_DEBUG'] ?? 'false') !== 'true',
],
```

## Format registry

The `FormatRegistry` maps file extensions to `Format` value objects (extension, content type, renderer class). Renderers are resolved from the container on demand.

Configure formats in `config/formats.php`:

```php
return [
    'default' => 'json',
    'formats' => [
        'json' => [
            'content_type' => 'application/json',
            'renderer' => \Arcanum\Shodo\JsonRenderer::class,
        ],
        'html' => [
            'content_type' => 'text/html',
            'renderer' => \Arcanum\Shodo\HtmlRenderer::class,
        ],
        'csv' => [
            'content_type' => 'text/csv',
            'renderer' => \Arcanum\Shodo\CsvRenderer::class,
        ],
        'txt' => [
            'content_type' => 'text/plain',
            'renderer' => \Arcanum\Shodo\PlainTextRenderer::class,
        ],
    ],
];
```

To add a custom format, add an entry and implement the `Renderer` interface. To disable a format, remove it from the array. Requesting a disabled or unknown format throws `UnsupportedFormat` (406 Not Acceptable).

## At a glance

```
FormatRegistry
|-- json → JsonRenderer (pure serializer)
|-- html → HtmlRenderer (template-based, htmlspecialchars escape)
|-- csv  → CsvRenderer (pure tabular serializer)
\-- txt  → PlainTextRenderer (template-based, identity escape)

Template pipeline:
  TemplateResolver (DTO class → file path)
  → TemplateCompiler (source → PHP)
  → TemplateCache (compiled PHP cached to disk)
  → execute with $__escape + extracted variables
  → ResponseInterface

Exception rendering:
  JsonExceptionRenderer → HttpException status codes, debug traces

Empty responses:
  EmptyResponseRenderer → status-code-only for commands
```
