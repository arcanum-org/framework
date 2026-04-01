# Arcanum Validation

Attribute-based DTO validation for Arcanum's CQRS architecture. Validation rules live as PHP attributes on DTO constructor parameters — the same place the data is declared. Validation runs automatically via Conveyor middleware before the handler, so handlers never see invalid data.

## Quick start

```php
use Arcanum\Validation\Rule\NotEmpty;
use Arcanum\Validation\Rule\Email;
use Arcanum\Validation\Rule\MaxLength;

final class Submit
{
    public function __construct(
        #[NotEmpty] #[MaxLength(100)]
        public readonly string $name,
        #[NotEmpty] #[Email]
        public readonly string $email,
        #[MaxLength(5000)]
        public readonly string $message = '',
    ) {}
}
```

If validation fails on HTTP, the client gets **422 Unprocessable Entity** with structured JSON errors:

```json
{
  "errors": {
    "name": ["The name field is required."],
    "email": ["The email field must be a valid email address."]
  }
}
```

On CLI, errors are printed to stderr with field names and messages.

## Built-in rules

| Rule | Parameters | Validates | Message |
|---|---|---|---|
| `NotEmpty` | — | Rejects `null`, `''`, `[]` | "The {field} field is required." |
| `MinLength` | `int $min` | String length ≥ min (via `mb_strlen`) | "...must be at least {min} characters." |
| `MaxLength` | `int $max` | String length ≤ max (via `mb_strlen`) | "...must not exceed {max} characters." |
| `Min` | `int\|float $min` | Numeric value ≥ min | "...must be at least {min}." |
| `Max` | `int\|float $max` | Numeric value ≤ max | "...must not exceed {max}." |
| `Email` | — | `filter_var(FILTER_VALIDATE_EMAIL)` | "...must be a valid email address." |
| `Pattern` | `string $regex` | Matches regex via `preg_match` | "...format is invalid." |
| `In` | `mixed ...$values` | Value is in the allowed set (strict) | "...must be one of: {values}." |
| `Url` | — | `filter_var(FILTER_VALIDATE_URL)` | "...must be a valid URL." |
| `Uuid` | — | UUID format (any version) | "...must be a valid UUID." |
| `Callback` | `callable $fn` | `$fn($value)` returns `true` or error string | Custom message from callable |

All rules skip non-applicable types silently — `MinLength` on an int is a no-op, `Email` on a non-string is a no-op. Type enforcement is PHP's job, not validation's.

## How it works

1. The DTO is hydrated from HTTP/CLI input (typed values ready)
2. `ValidationGuard` middleware runs before the handler
3. The `Validator` reflects on the DTO's constructor parameters
4. Each parameter's `Rule` attributes are instantiated and executed
5. **All** errors are collected before throwing (not just the first)
6. `ValidationException` propagates up — the kernel renders it

### Nullable parameters

If a parameter is nullable (`?string`) and the value is `null`, all rules are skipped. Null is a valid value for nullable types.

### HandlerProxy DTOs

Dynamic DTOs (`Command`, `Query`, `Page` handler proxies) are skipped automatically — they carry data in a Registry, not constructor parameters.

## Manual validation

The `Validator` is standalone — usable outside the middleware:

```php
use Arcanum\Validation\Validator;
use Arcanum\Validation\ValidationException;

$validator = new Validator();

// Throws ValidationException on failure:
$validator->validate($dto);

// Or check without throwing:
$errors = $validator->check($dto);
foreach ($errors as $error) {
    echo "{$error->field}: {$error->message}\n";
}
```

## Custom rules

Implement the `Rule` interface and add `#[Attribute(Attribute::TARGET_PARAMETER)]`:

```php
use Arcanum\Validation\Rule;
use Arcanum\Validation\ValidationError;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class Unique implements Rule
{
    public function __construct(
        private readonly string $table,
        private readonly string $column,
    ) {}

    public function validate(mixed $value, string $field): ValidationError|null
    {
        // Check the database...
        if ($exists) {
            return new ValidationError($field, "The {$field} has already been taken.");
        }
        return null;
    }
}
```

Then use it:

```php
final class Register
{
    public function __construct(
        #[NotEmpty] #[Email] #[Unique('users', 'email')]
        public readonly string $email,
    ) {}
}
```

### Callback rule

For one-off validation that doesn't warrant a class:

```php
final class Redeem
{
    public function __construct(
        #[Callback([self::class, 'validateCode'])]
        public readonly string $code,
    ) {}

    public static function validateCode(mixed $value): true|string
    {
        return strlen($value) === 8 ? true : 'Code must be exactly 8 characters.';
    }
}
```

Note: PHP attributes require compile-time constant expressions, so closures can't be used directly. Use `[ClassName::class, 'method']` or an invokable class.

## Conveyor integration

`ValidationGuard` is a `Progression` (Conveyor before-middleware) registered automatically by `Bootstrap\Routing` and `Bootstrap\CliRouting`. It runs on every dispatch, after `TransportGuard`.

To disable validation for a specific DTO, simply don't add any rule attributes — DTOs without rules pass through untouched.

## Error rendering

| Transport | Exception | Response |
|---|---|---|
| HTTP | `ValidationException` | **422 Unprocessable Entity**, JSON `{"errors": {...}}` |
| HTTP | Any other exception | Delegated to `JsonExceptionResponseRenderer` |
| CLI | `ValidationException` | Formatted field→message list on stderr |
| CLI | Any other exception | Standard `CliExceptionWriter` output |

## At a glance

```
Rule (interface)              — validate(value, field): ValidationError|null
ValidationError               — field + message value object
ValidationException           — carries list<ValidationError>, groups by field
Validator                      — reflects on DTO, runs rules, collects all errors

Rule/
├── NotEmpty                   — rejects null, '', []
├── MinLength(min)             — string length ≥ min
├── MaxLength(max)             — string length ≤ max
├── Min(min)                   — numeric ≥ min
├── Max(max)                   — numeric ≤ max
├── Email                      — FILTER_VALIDATE_EMAIL
├── Pattern(regex)             — preg_match
├── In(...values)              — strict in_array
├── Url                        — FILTER_VALIDATE_URL
├── Uuid                       — UUID format regex
└── Callback(callable)         — escape hatch

ValidationGuard                — Conveyor before-middleware (Progression)

Hyper/ValidationExceptionRenderer — 422 JSON decorator for ExceptionRenderer
```
