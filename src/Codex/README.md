# Arcanum Codex

Codex solves one problem: **given a class name, create an instance of it with all its dependencies automatically resolved**.

## The basic idea

Say you have a class like this:

```php
class OrderService {
    public function __construct(
        private Database $db,
        private Logger $logger,
    ) {}
}
```

Normally you'd have to wire this up yourself:

```php
$logger = new Logger();
$db = new Database($connectionString);
$service = new OrderService($db, $logger);
```

With Codex, you just say:

```php
$resolver = Resolver::forContainer($container);
$service = $resolver->resolve(OrderService::class);
```

Codex reads the constructor using PHP's Reflection API, sees it needs a `Database` and a `Logger`, resolves those too (recursively), and hands you a fully built `OrderService`. It's like a factory that can build anything.

## How resolution works

When you call `resolve(OrderService::class)`:

1. **Event** — fires a `ClassRequested` event so listeners know something's being built.
2. **Container check** — if this is a dependency (not the top-level call), it checks the PSR-11 container first. Maybe someone already registered this class.
3. **Reflection** — uses `ReflectionClass` to inspect the constructor.
4. **No constructor / no params?** Just `new $className()` and you're done.
5. **Has parameters?** Loops through each one and figures out what to pass:
   - **Variable specification** — did someone tell the resolver "when building X, use this value for `$paramName`"? Use that.
   - **Class parameter** — is the type hint a class? Resolve it recursively. This is where the magic happens — dependencies of dependencies get resolved too.
   - **Primitive parameter** — is it a string, int, etc.? Use the default value if one exists, otherwise throw an error.
6. **Instantiate** — creates the object with all resolved dependencies.
7. **Finalize** — fires a `ClassResolved` event and returns the instance.

## Specifications — manual overrides

Sometimes auto-resolution isn't enough. Specifications let you tell the resolver exactly what to use:

```php
// When building OrderService, use this specific connection string
$resolver->specify(OrderService::class, '$connectionString', 'mysql://...');

// When building OrderService, use RedisCache instead of the default Cache
$resolver->specify(OrderService::class, CacheInterface::class, RedisCache::class);

// Apply the same spec to multiple classes at once
$resolver->specify(
    [OrderService::class, UserService::class],
    LoggerInterface::class,
    FileLogger::class,
);
```

Specifications can be:
- A **raw value** (for primitives like strings and ints)
- A **class name** (Codex will resolve it recursively)
- A **callable** (Codex will call it with the container)
- An **array** of any of the above

## resolveWith — explicit arguments

When you know exactly which classes to pass for each constructor parameter by position:

```php
$resolver->resolveWith(OrderService::class, [
    Database::class,     // param 0
    FileLogger::class,   // param 1
]);
```

Each argument is resolved through Codex, so you get full recursive resolution. If a parameter isn't provided and has a default value, the default is used.

## Helper classes

**ClassNameResolver** — a static utility that extracts the class name from a parameter's type hint using reflection. Handles `ReflectionNamedType`, the `parent` keyword, and returns `null` for built-in types (int, string, etc.).

**PrimitiveResolver** — handles non-class parameters. If the parameter has a default value, use it. If it's variadic, return an empty array. Otherwise, throw an error — Codex can't guess what a primitive should be.

## Events

Codex integrates with the Echo event system. Two events fire during resolution:

- **ClassRequested** — fired before resolution starts. Contains the class name being requested.
- **ClassResolved** — fired after resolution completes. Contains the fully built instance.

Any resolved class that implements `Codex\EventDispatcher` gets automatically registered as a listener. This means the event system itself can be resolved by Codex — it bootstraps itself.

## Error hierarchy

All errors extend `Unresolvable` (which implements PSR-11's `ContainerExceptionInterface`):

- **UnknownClass** — the class doesn't exist.
- **UnresolvableClass** — the class exists but can't be instantiated (abstract, interface, or missing required arguments).
- **UnresolvablePrimitive** — a primitive parameter with no default value and no specification.
- **UnresolvableUnionType** — a union-typed parameter (`Foo|Bar`) that Codex can't automatically resolve.

## The hierarchy at a glance

```
Resolver (ClassResolver + Specifier)
|-- Uses: ClassNameResolver (extracts class from type hints)
|-- Uses: PrimitiveResolver (handles non-class params)
|-- Manages: specifications (manual overrides)
|-- Dispatches: CodexEvent
|   |-- ClassRequested (before resolution)
|   \-- ClassResolved (after resolution)
\-- Errors: Unresolvable
    |-- UnknownClass
    |-- UnresolvableClass
    |-- UnresolvablePrimitive
    \-- UnresolvableUnionType
```
