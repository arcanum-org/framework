# Arcanum Auth

Authentication and authorization for Arcanum.

## Design Philosophy

Auth splits into two distinct concerns:

- **Authentication** (who are you?) — transport-layer. Resolves an `Identity` from the request. Lives in PSR-15 middleware (HTTP) or `CliAuthResolver` (CLI).
- **Authorization** (can you do this?) — domain-layer. Checks permissions on the DTO before the handler runs. Lives in the Conveyor pipeline as a `Progression` middleware.

Handlers never know how identity was resolved. They receive a typed `Identity` via the container — the same interface whether it came from a session cookie, Bearer token, or CLI `--token` flag.

## Identity

The `Identity` interface is the domain's representation of "who is making this request":

```php
interface Identity
{
    public function id(): string;
    public function roles(): array;
}
```

`SimpleIdentity` is the built-in implementation for guards that resolve from tokens or sessions. Apps with richer user models should implement `Identity` directly on their User class.

`ActiveIdentity` is the request-scoped holder (same pattern as `ActiveSession`). Auth middleware writes, authorization guard and handlers read.

## Guards

Guards resolve an `Identity` from an HTTP request. They never reject — that's authorization's job.

```php
interface Guard
{
    public function resolve(ServerRequestInterface $request): Identity|null;
}
```

### SessionGuard

Reads the identity ID from the session, calls your resolver to look up the full identity:

```php
// config/auth.php
return [
    'guard' => 'session',
    'resolvers' => [
        'identity' => fn(string $id) => User::find($id),
    ],
];
```

### TokenGuard

Reads a Bearer token from the `Authorization` header:

```php
return [
    'guard' => 'token',
    'resolvers' => [
        'token' => fn(string $token) => ApiToken::validate($token)?->user(),
    ],
];
```

### CompositeGuard

Tries session first, then token. For apps serving both HTML and API:

```php
return [
    'guard' => 'composite',
    'resolvers' => [
        'identity' => fn(string $id) => User::find($id),
        'token' => fn(string $token) => ApiToken::validate($token)?->user(),
    ],
];
```

## Authorization Attributes

Authorization is declared on DTOs via attributes and enforced by `AuthorizationGuard` in the Conveyor pipeline.

### #[RequiresAuth]

The DTO requires an authenticated identity. No identity → **401 Unauthorized**.

```php
#[RequiresAuth]
final class ViewDashboard
{
    public function __construct(public readonly string $section = 'overview') {}
}
```

### #[RequiresRole]

The identity must have at least one of the listed roles. Missing role → **403 Forbidden**. Implies `RequiresAuth`.

```php
#[RequiresRole('admin', 'moderator')]
final class BanUser
{
    public function __construct(public readonly string $userId) {}
}
```

### #[RequiresPolicy]

For authorization logic that depends on the DTO's data. The policy is resolved from the container.

```php
#[RequiresPolicy(OwnsPostPolicy::class)]
final class EditPost
{
    public function __construct(public readonly string $postId, public readonly string $title) {}
}

final class OwnsPostPolicy implements Policy
{
    public function __construct(private PostRepository $posts) {}

    public function authorize(Identity $identity, object $dto): bool
    {
        $post = $this->posts->find($dto->postId);
        return $post->authorId === $identity->id();
    }
}
```

Multiple policies on one DTO are all checked — all must pass.

## CLI Authentication

CLI doesn't use sessions or HTTP middleware. `CliAuthResolver` reads a token from:

1. `--token` option (takes precedence)
2. `ARCANUM_TOKEN` environment variable

```bash
php arcanum command:admin:reset-cache --token=my-secret-token
ARCANUM_TOKEN=my-secret-token php arcanum command:admin:reset-cache
```

The same `#[RequiresAuth]` and `#[RequiresRole]` attributes work on CLI — `AuthorizationGuard` runs in the Conveyor pipeline regardless of transport.

## How It Works

### HTTP Flow

```
Request → SessionMiddleware → AuthMiddleware → CsrfMiddleware → App middleware
  → Router → DTO hydrated → Conveyor dispatches
    → AuthorizationGuard (checks #[RequiresAuth], #[RequiresRole], #[RequiresPolicy])
    → ValidationGuard → Handler
```

`AuthMiddleware` resolves identity and stores it in `ActiveIdentity`. It never rejects. `AuthorizationGuard` reads DTO attributes and enforces requirements.

### CLI Flow

```
CLI → CliAuthResolver → Router → DTO hydrated → Conveyor dispatches
  → AuthorizationGuard → ValidationGuard → Handler
```

Same `AuthorizationGuard`, same attributes, different identity resolution.

## Configuration

```php
// config/auth.php
return [
    // 'session', 'token', or 'composite'
    'guard' => 'session',

    'resolvers' => [
        // Session guard: maps identity ID → Identity
        'identity' => fn(string $id) => null, // Replace with your user lookup

        // Token guard: maps bearer token → Identity
        'token' => fn(string $token) => null, // Replace with your token validation
    ],
];
```

## Bootstrap

`Bootstrap\Auth` runs after `Bootstrap\Sessions` and before `Bootstrap\Routing`. It registers:

- `ActiveIdentity` as a singleton
- `Identity` interface factory (resolves from `ActiveIdentity`)
- `Guard` (configured from `config/auth.php`)
- `AuthMiddleware` (HTTP only)

`AuthorizationGuard` is registered as Conveyor before-middleware in both `Bootstrap\Routing` and `Bootstrap\CliRouting` — after `TransportGuard`, before `ValidationGuard`.
