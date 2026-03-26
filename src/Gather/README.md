# Arcanum Gather

Gather gives you smart collections for storing and retrieving key-value data. Think of it as PHP arrays, but with type safety, PSR-11 container compliance, and specialized behaviors for different use cases.

## Registry — the foundation

A `Registry` is a key-value store that wraps an array. What makes it useful is that it implements a bunch of interfaces at once — you can use it as a PSR-11 container, iterate over it, count it, access it like an array, serialize it to JSON, and coerce values to specific types:

```php
$registry = new Registry([
    'name' => 'Alice',
    'age' => '30',
    'active' => '1',
]);

$registry->get('name');          // 'Alice'
$registry->has('name');          // true
$registry->asInt('age');         // 30 (coerced to int)
$registry->asBool('active');    // true (coerced to bool)
$registry->asString('missing', 'default');  // 'default' (fallback)
```

The coercion methods (`asString`, `asInt`, `asFloat`, `asBool`, `asAlpha`, `asAlnum`, `asDigits`) are the key feature. They safely convert values to the type you need, with optional fallbacks for missing keys. This is especially useful when working with user input, `$_SERVER`, or config files where everything comes in as strings.

## Configuration — dot-notation access

`Configuration` extends Registry and adds support for nested access using dot notation:

```php
$config = new Configuration([
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
    ],
]);

$config->get('database.host');   // 'localhost'
$config->get('database.port');   // 3306
$config->get('database');        // ['host' => 'localhost', 'port' => 3306]
```

When you store an array under a key, Configuration makes both the full array and its individual elements accessible via dot notation. This is how framework config files work — you can drill into nested settings without manually traversing arrays.

## Environment — secure environment variables

`Environment` extends Registry but locks everything down. Environment variables often contain secrets (API keys, database passwords), so this class intentionally prevents leaking them:

```php
$env = new Environment($_ENV);

$env->get('DB_PASSWORD');        // works fine
$env->asString('DB_PASSWORD');   // works fine
(string) $env;                   // 'ENVIRONMENT' (not the actual data)
json_encode($env);               // null
serialize($env);                 // empty array
clone $env;                      // throws LogicException
```

You can read values, but you can't serialize, clone, or stringify the whole thing. This prevents accidental logging or exposure of sensitive data.

## IgnoreCaseRegistry — case-insensitive keys

`IgnoreCaseRegistry` extends Registry and makes all key lookups case-insensitive while preserving the original key casing:

```php
$headers = new IgnoreCaseRegistry([
    'Content-Type' => ['text/html'],
]);

$headers->get('content-type');   // ['text/html']
$headers->get('CONTENT-TYPE');   // ['text/html']
$headers->get('Content-Type');   // ['text/html']
```

This is what powers Hyper's `Headers` class — HTTP headers are case-insensitive by spec, so `Content-Type` and `content-type` must be treated as the same key.

## The interfaces

Gather defines three interfaces that describe what a collection can do:

- **Collection** — combines PSR-11 `ContainerInterface`, `IteratorAggregate`, `Countable`, and `ArrayAccess`. Any Collection is a full-featured key-value store.
- **Coercible** — extends Collection with the type coercion methods (`asString`, `asInt`, etc.).
- **Serializable** — combines `JsonSerializable` and `Stringable` with PHP's native `__serialize`/`__unserialize`.

## The hierarchy at a glance

```
Collection (interface: PSR-11 + IteratorAggregate + Countable + ArrayAccess)
\-- Coercible (interface: adds type coercion)
    \-- Registry (class: the core implementation)
        |-- Configuration (dot-notation nested access)
        |-- Environment (security-hardened, no serialization)
        \-- IgnoreCaseRegistry (case-insensitive keys)

Serializable (interface: JSON + PHP serialization)
```
