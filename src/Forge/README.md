# Arcanum Forge

Forge is Arcanum's persistence layer. There's no ORM, no query builder, and no SQL strings floating around your PHP code. Instead, you write `.sql` files and call them as methods. Forge handles connections, parameter binding, execution, and result shaping.

## Quick start

Write a SQL file:

```sql
-- app/Domain/Shop/Model/Products.sql
-- @cast price float
-- @cast in_stock bool
SELECT id, name, price, in_stock
FROM products
WHERE category = :category
```

Call it from your handler:

```php
final class ProductsHandler
{
    public function __construct(private readonly Database $db) {}

    public function __invoke(Products $dto): array
    {
        return $this->db->model->products(category: $dto->category)->rows();
    }
}
```

That's it. The SQL file name becomes the method name. Parameters are type-safe. Results come back with the types you specified. No boilerplate, no mapping classes, no configuration.

## How it works

Your SQL files live in `Model/` directories inside each domain:

```
app/Domain/Shop/
    Model/
        Products.sql           ← $db->model->products(...)
        ProductById.sql        ← $db->model->productById(...)
        InsertOrder.sql        ← $db->model->insertOrder(...)
    Query/
        Products.php           ← route DTO
        ProductsHandler.php    ← handler
    Command/
        PlaceOrder.php         ← route DTO
        PlaceOrderHandler.php  ← handler
```

The directory is the object, the file is the method. `Products.sql` becomes `$db->model->products(...)`. PascalCase file names become camelCase methods.

## Calling methods

Forge supports PHP named arguments, positional arguments, and mixed — just like calling a regular PHP function.

```php
// Named arguments (recommended for clarity)
$db->model->products(category: 'shoes');

// Positional arguments (filled in SQL binding order)
$db->model->products('shoes');

// Mixed — named args claim their bindings, positional fills the rest
$db->model->search('shoes', active: true);
```

Named arguments are converted from camelCase to snake_case to match SQL bindings: `minPrice` maps to `:min_price` in the SQL.

## Results

Every query returns a `Result`. You pick the shape you need:

```php
$result = $db->model->products(category: 'shoes');

$result->rows();          // all rows as associative arrays
$result->first();         // first row, or null
$result->scalar();        // first column of first row (throws if empty)
$result->count();         // number of rows returned
$result->isEmpty();       // true if zero rows returned/affected
$result->affectedRows();  // rows affected by INSERT/UPDATE/DELETE
$result->lastInsertId();  // auto-increment ID from an INSERT
```

## Type casting with @cast

PDO returns most values as strings. That's usually not what you want. Add `@cast` annotations to your SQL files and Forge converts the values for you:

```sql
-- @cast price float
-- @cast in_stock bool
-- @cast quantity int
-- @cast metadata json
SELECT id, name, price, in_stock, quantity, metadata
FROM products
WHERE category = :category
```

Supported types:

| Type | What it does |
|---|---|
| `int` | `(int)` cast |
| `float` | `(float)` cast |
| `bool` | Normalizes `'1'`/`'0'`, `'t'`/`'f'`, `'true'`/`'false'`, `'yes'`/`'no'` |
| `json` | `json_decode($value, true)` |

Annotations are optional — skip them and you get whatever PDO gives you. Casts only apply to read queries (`SELECT`), not writes.

## Parameter annotations with @param

You can declare parameter types in your SQL files too. These are used by the model generator (see below) to produce typed method signatures:

```sql
-- @param category string
-- @param min_price float
-- @param active bool
SELECT * FROM products
WHERE category = :category AND price >= :min_price AND active = :active
```

If you don't add `@param` annotations, the generator defaults all parameters to `string`.

## Transactions

Wrap multiple operations in a transaction. If anything throws, the transaction rolls back automatically:

```php
$db->transaction(function (Database $db) use ($dto) {
    $db->model->insertOrder(
        userId: $dto->userId,
        total: $dto->total,
    );

    $orderId = $db->model->insertOrder(/* ... */)->lastInsertId();

    $db->model->insertOrderItem(
        orderId: $orderId,
        productId: $dto->productId,
        quantity: $dto->quantity,
    );

    $db->model->deductInventory(
        productId: $dto->productId,
        quantity: $dto->quantity,
    );
});
```

## Connections

### Configuration

Define your connections in `config/database.php`:

```php
return [
    'default' => 'mysql',

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'arcanum'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
        ],
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => 'database.sqlite',
        ],
    ],

    // Optional read/write split
    'read' => null,
    'write' => null,

    // Optional domain-to-connection mapping
    'domains' => [
        'Analytics' => 'analytics',
    ],
];
```

### Read/write routing

Forge automatically routes queries to the right connection by inspecting the SQL. `SELECT`, `WITH` (CTEs), `EXPLAIN`, `SHOW`, `DESCRIBE`, and `PRAGMA` go to the read connection. Everything else goes to write. You don't think about it.

### Connection overrides

If a handler needs a specific connection:

```php
$db->connection('legacy')->model->activeUsers();
```

Same domain, different database server.

### Domain-to-connection mapping

The `domains` config maps domain names to connections. If your `Analytics` domain lives in a separate PostgreSQL database, just add the mapping — handlers in that domain automatically use the right connection.

### Bring your own DBAL

Forge ships with `PdoConnection`, a lightweight PDO wrapper that handles MySQL, PostgreSQL, and SQLite out of the box. For most apps, this is all you need.

If you need advanced driver support — Oracle, SQL Server, connection pooling, driver-specific options — you can provide your own `Connection` implementation. `Connection` is an interface with four methods: `run()`, `beginTransaction()`, `commit()`, `rollBack()`. Wrap Doctrine DBAL, wrap your company's internal library, wrap whatever you want. Forge doesn't care what's behind the interface.

```php
use Arcanum\Forge\Connection;
use Arcanum\Forge\Result;
use Doctrine\DBAL\Connection as DbalConnection;

final class DoctrineDbalConnection implements Connection
{
    public function __construct(private readonly DbalConnection $dbal) {}

    public function run(string $sql, array $params = []): Result
    {
        $stmt = $this->dbal->executeQuery($sql, $params);
        // ... build and return a Result
    }

    public function beginTransaction(): void { $this->dbal->beginTransaction(); }
    public function commit(): void { $this->dbal->commit(); }
    public function rollBack(): void { $this->dbal->rollBack(); }
}
```

## Model generation

The magic `Model` class uses `__call` to map methods to SQL files at runtime. This works great, but your IDE and PHPStan can't see the methods. For full static analysis coverage and typed parameters, generate your models:

```
php arcanum forge:models
```

This scans every domain's `Model/` directory and generates a typed PHP class:

```php
// Auto-generated — app/Domain/Shop/Model.php
final class Model extends BaseModel
{
    public function products(string $category): Result
    {
        return $this->execute('products', ['category' => $category]);
    }

    public function insertOrder(string $userId, string $total): Result
    {
        return $this->execute('insertOrder', ['user_id' => $userId, 'total' => $total]);
    }
}
```

Generated classes extend the base `Model`, so `__call` still catches any SQL files that haven't been generated yet. The calling convention is identical — `$db->model->products(category: 'shoes')` works the same whether you've generated or not.

### Customizing generation

Drop a `stubs/model.stub` or `stubs/model_method.stub` in your project root to customize what gets generated. Same override pattern as `make:command` and other generators.

### Dev-mode auto-regeneration

In debug mode, Forge automatically regenerates model classes when it detects that a SQL file has changed. You edit a SQL file, refresh the page, and the model is up to date. Zero friction.

Control this via `config/database.php`:

```php
'auto_forge' => true,   // auto-regenerate (default in debug mode)
'auto_forge' => false,  // throw an exception instead
// omit the key entirely to skip staleness checks (production default)
```

### Validating models

Run the validator in CI to catch drift between SQL files and generated classes:

```
php arcanum validate:models
```

Reports missing methods, stale methods (deleted SQL files), parameter count mismatches, and type mismatches.

## CLI commands

```
php arcanum db:status         # show connections, test connectivity, list models
php arcanum forge:models      # generate typed model classes from SQL files
php arcanum validate:models   # check generated models match SQL files
```

## Domain scoping

Each handler's `$db->model` is automatically scoped to the handler's domain. A handler in `App\Domain\Shop\Command\` gets SQL files from `app/Domain/Shop/Model/`. A handler in `App\Domain\Users\Query\` gets `app/Domain/Users/Model/`. This is set automatically by the Conveyor middleware — you never configure it.

If a handler needs data from another domain, it dispatches a query through the Conveyor bus. That's the domain's public API. No reaching across domain boundaries to grab SQL files from another context.

## Bootstrap

`Bootstrap\Database` runs after `Cache` and registers:
- `ConnectionManager` — named connection factory
- `DomainContext` — request-scoped domain holder
- `Database` — the service your handlers inject

Skips gracefully if `config/database.php` doesn't exist — apps without a database are unaffected.

## At a glance

```
Forge/
    Connection            — interface for database connections
    PdoConnection         — built-in PDO implementation
    ConnectionFactory     — builds connections from config arrays
    ConnectionManager     — named connections, read/write split, domain mapping
    Database              — developer-facing service ($db->model, transactions)
    DomainContext         — request-scoped domain holder
    DomainContextMiddleware — Conveyor middleware, sets domain from DTO namespace
    Model                 — maps method calls to SQL files (magic + generated)
    ModelGenerator        — generates typed model classes from SQL files
    Result                — query result with typed accessors
    Sql                   — SQL string introspection (read detection, @cast, @param, bindings)
```
