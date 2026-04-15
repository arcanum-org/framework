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

## IdentityProvider

The `IdentityProvider` interface is the bridge between the auth system and your user storage. Implement it once and the framework uses it everywhere — session guard, token guard, CLI auth, and the login command all go through the same provider.

```php
interface IdentityProvider
{
    public function findById(string $id): Identity|null;
    public function findByToken(string $token): Identity|null;
    public function findByCredentials(string ...$credentials): Identity|null;
}
```

Every method returns `null` for "not found" or "invalid." Normal lookup failures (unknown ID, expired token, wrong password) are not exceptional — return `null` and let the guard handle it. Only throw for infrastructure failures (database down, etc.).

### Example implementation

```php
namespace App\Auth;

use App\Domain\User\Model\User;
use Arcanum\Auth\Identity;
use Arcanum\Auth\IdentityProvider;
use Arcanum\Auth\SimpleIdentity;

final class UserProvider implements IdentityProvider
{
    public function __construct(private readonly User $users)
    {
    }

    public function findById(string $id): Identity|null
    {
        $row = $this->users->findById(id: (int) $id);

        return $row ? new SimpleIdentity($row->id, $row->roles) : null;
    }

    public function findByToken(string $token): Identity|null
    {
        $row = $this->users->findByToken(token: $token);

        return $row ? new SimpleIdentity($row->id, $row->roles) : null;
    }

    public function findByCredentials(string ...$credentials): Identity|null
    {
        [$email, $password] = $credentials;

        $row = $this->users->findByEmail(email: $email);

        if ($row === null || !password_verify($password, $row->password_hash)) {
            return null;
        }

        return new SimpleIdentity($row->id, $row->roles);
    }
}
```

Register the provider in `config/auth.php`:

```php
return [
    'provider' => \App\Auth\UserProvider::class,
    // ...
];
```

The provider is resolved from the container, so it can inject Forge models, database connections, or any other service.

## Guards

Guards resolve an `Identity` from an HTTP request. They never reject — that's authorization's job.

```php
interface Guard
{
    public function resolve(ServerRequestInterface $request): Identity|null;
}
```

### SessionGuard

Reads the identity ID from the session, calls `IdentityProvider::findById()` to look up the full identity:

```php
return [
    'guard' => 'session',
    'provider' => \App\Auth\UserProvider::class,
];
```

### TokenGuard

Reads a Bearer token from the `Authorization` header, calls `IdentityProvider::findByToken()`:

```php
return [
    'guard' => 'token',
    'provider' => \App\Auth\UserProvider::class,
];
```

### CompositeGuard

Tries multiple guards in order. First non-null identity wins. For apps serving both HTML and API:

```php
return [
    'guard' => ['session', 'token'],
    'provider' => \App\Auth\UserProvider::class,
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

CLI uses a three-level priority chain for identity resolution:

1. `--token` option (highest priority — for scripts and CI)
2. **CLI session** (from `login` command — for interactive development)
3. `ARCANUM_TOKEN` environment variable (fallback for CI)

```bash
# One-off token
php arcanum command:admin:reset-cache --token=my-secret-token

# Environment variable (CI pipelines)
ARCANUM_TOKEN=my-secret-token php arcanum command:admin:reset-cache

# Interactive login (stays authenticated for 24 hours by default)
php arcanum login
php arcanum command:admin:reset-cache   # uses stored session
php arcanum logout
```

The same `#[RequiresAuth]` and `#[RequiresRole]` attributes work on CLI — `AuthorizationGuard` runs in the Conveyor pipeline regardless of transport.

### CLI Sessions

The `login` command prompts for credentials (configurable fields), validates them through your app's `IdentityProvider::findByCredentials()`, and stores the identity in an encrypted file (`files/.cli-session`). Subsequent commands automatically pick up the stored identity without needing `--token`.

Sessions are encrypted at rest using the framework's `Encryptor` (your `APP_KEY`). They contain only the identity ID and an expiry timestamp — never the raw credentials. Sessions expire after the configured TTL (default: 24 hours).

`CliSession` takes an optional `Hourglass\Clock` constructor parameter (defaults to `SystemClock`) for the expiry math. Production code lets the container auto-wire it; tests pass a `FrozenClock` to assert expiry behavior deterministically without `sleep()`.

```
php arcanum login        # prompts for email + password
php arcanum logout       # clears the stored session
```

### Prompter

The `Prompter` class provides minimal interactive input for CLI commands:

```php
$prompter->ask('Email:');      // visible input
$prompter->secret('Password:'); // hidden input (disables terminal echo)
```

Fields named `password`, `secret`, or `token` automatically use hidden input in the `login` command.

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
CLI → CliAuthResolver (--token / session / env) → Router → DTO hydrated
  → Conveyor dispatches → AuthorizationGuard → ValidationGuard → Handler
```

Same `AuthorizationGuard`, same attributes, different identity resolution.

## Configuration

```php
// config/auth.php
return [
    // 'session', 'token', or ['session', 'token'] for composite
    'guard' => 'session',

    // Class implementing IdentityProvider — resolved from the container
    'provider' => \App\Auth\UserProvider::class,

    // CLI login settings
    'login' => [
        'fields' => ['email', 'password'], // prompt labels, in order
        'ttl' => 86400,                    // session lifetime in seconds
    ],
];
```

The `IdentityProvider::findByCredentials()` receives positional arguments matching the `fields` order. Fields named `password`, `secret`, or `token` use hidden input.

## Bootstrap

`Bootstrap\Auth` runs after `Bootstrap\Sessions` and before `Bootstrap\Routing`. It registers:

- `ActiveIdentity` as a singleton
- `Identity` interface factory (resolves from `ActiveIdentity`)
- `IdentityProvider` (resolved from the `provider` config key, cached in the container)
- `Guard` (configured from `config/auth.php`) — HTTP only
- `AuthMiddleware` — HTTP only
- `CliSession` (encrypted file store) — CLI only
- `CliAuthResolver` (with session support) — CLI only

`AuthorizationGuard` is registered as Conveyor before-middleware in both `Bootstrap\Routing` and `Bootstrap\CliRouting` — after `TransportGuard`, before `ValidationGuard`.

## CLI commands

```
php arcanum login    # prompt for credentials, store encrypted session
php arcanum logout   # clear stored session
```
