# Arcanum Vault

Vault is a PSR-16 (`CacheInterface`) caching package with swappable drivers. It provides a `CacheManager` for named stores, a `PrefixedCache` decorator for key isolation, and five drivers covering development through production.

## Quick start

```php
use Psr\SimpleCache\CacheInterface;

// Inject via the container — resolves to the default store
function handle(CacheInterface $cache): void
{
    $cache->set('user.42', $user, 3600);    // TTL in seconds
    $user = $cache->get('user.42');
    $cache->delete('user.42');
}
```

## Drivers

| Driver | Use case | Backing store |
|---|---|---|
| `FileDriver` | Default, zero config | One file per key in a directory |
| `ArrayDriver` | Testing, per-request cache | PHP array (current process only) |
| `NullDriver` | Disable caching | Stores nothing |
| `ApcuDriver` | Fast single-server cache | APCu shared memory |
| `RedisDriver` | Distributed cache | phpredis extension |

All drivers implement `Psr\SimpleCache\CacheInterface` exactly — no extensions, no extra methods.

### FileDriver

```php
$cache = new FileDriver('/path/to/cache/directory');
$cache->set('key', ['data' => true], 3600);
```

Keys are hashed to safe filenames (`md5($key).cache`). Writes are atomic (temp file + rename). Expired entries are lazily deleted on access.

### ArrayDriver

```php
$cache = new ArrayDriver();
$cache->set('key', 'value', 60);
```

Data lives only for the current process. Respects TTL via expiry timestamps. New instance = empty cache.

### NullDriver

```php
$cache = new NullDriver();
$cache->set('key', 'value');  // no-op
$cache->get('key');            // always returns default
```

### ApcuDriver / RedisDriver

```php
// APCu — requires ext-apcu with apc.enable_cli
$cache = new ApcuDriver();

// Redis — pass a connected \Redis instance
$redis = new \Redis();
$redis->connect('127.0.0.1', 6379);
$cache = new RedisDriver($redis);
```

## CacheManager

The manager resolves named stores lazily from configuration:

```php
use Arcanum\Vault\CacheManager;

$manager->store();           // default store
$manager->store('redis');    // named store
$manager->store('array');    // per-request cache
```

### Configuration

`config/cache.php`:

```php
return [
    'default' => 'file',

    'stores' => [
        'file'  => ['driver' => 'file', 'path' => 'cache/app'],
        'array' => ['driver' => 'array'],
        'redis' => ['driver' => 'redis', 'host' => '127.0.0.1', 'port' => 6379],
        'apcu'  => ['driver' => 'apcu'],
    ],

    'framework' => [
        'pages'      => 'file',
        'middleware'  => 'file',
    ],
];
```

File driver paths are relative to the `files/` directory by default. Absolute paths are used as-is.

### Framework store mapping

The `framework` key has two siblings: `enabled` (master bypass switch) and `stores` (purpose → store-name mapping). Override `stores` to move framework caches to faster drivers:

```php
'framework' => [
    'enabled' => true,
    'stores' => [
        'pages'      => 'apcu',    // move page discovery to APCu
        'middleware'  => 'redis',   // move middleware discovery to Redis
    ],
],
```

Access via `$manager->frameworkStore('pages')`.

### Framework cache bypass

Set `cache.framework.enabled` to `false` to disable every framework-internal cache (templates, helpers, page discovery, middleware discovery, configuration). Every call to `frameworkStore()` returns a `NullDriver` regardless of which store was configured, so the framework rebuilds its compiled artefacts on every request.

```php
'framework' => [
    'enabled' => false,
    'stores' => [ /* ... */ ],
],
```

This is an orthogonal switch from `app.debug` — a developer can run with caches off while debug is on (or vice versa). Use it when you want a completely fresh pull on every refresh while iterating, without the cache invalidation rules tripping you up.

**What it affects:** every cache the framework writes to via `CacheManager::frameworkStore()`.

**What it does NOT affect:** application caches that the developer wires up via `CacheManager::store()` or by injecting `CacheInterface` directly. Those continue to use whatever driver is configured.

### Backwards-compatible legacy shape

If `cache.framework` is itself a flat `[purpose => store]` map (the older shape, no `enabled`/`stores` wrapper), it is still honoured and treated as the store mapping with the bypass disabled.

## PrefixedCache

Decorator that prepends a namespace prefix to all keys:

```php
use Arcanum\Vault\PrefixedCache;

$appCache = new PrefixedCache($driver, 'app.');
$fwCache = new PrefixedCache($driver, 'fw.');

$appCache->set('user', $data);   // stored as "app.user"
$fwCache->set('user', $data);    // stored as "fw.user"
```

Note: `clear()` delegates to the inner driver and clears ALL keys, not just prefixed ones. For true isolation, use separate driver instances.

## Key validation

PSR-16 forbids keys that are empty or contain `{}()/\@:`. All drivers validate keys before every operation and throw `InvalidArgument` (implementing `Psr\SimpleCache\InvalidArgumentException`) on violation.

## Bootstrap

`Bootstrap\Cache` runs after `Configuration` and registers:
- `CacheManager` — the factory for named stores
- `CacheInterface` — the default store (PSR-16 typehint)

## CLI commands

### cache:clear

```
php arcanum cache:clear              # clear all stores + framework caches
php arcanum cache:clear --store=file # clear only the file store
```

Clears all configured Vault stores plus framework caches (ConfigurationCache, TemplateCache).

## At a glance

```
Vault/
├── FileDriver            — file-based, one file per key
├── ArrayDriver           — in-memory, per-process
├── NullDriver            — no-op, disables caching
├── ApcuDriver            — APCu shared memory
├── RedisDriver           — phpredis wrapper
├── CacheManager          — factory/registry for named stores
├── PrefixedCache         — key-prefix decorator
├── KeyValidator          — PSR-16 key constraint enforcement
└── InvalidArgument       — PSR-16 exception implementation
```
