# Arcanum Session

Session management for Arcanum's HTTP transport layer.

Sessions are a **transport concern**, not a domain concern. They exist to support three specific purposes: identity persistence, CSRF protection, and flash messages. There is no generic key-value session API — domain state belongs in the domain layer, not the session.

## Design Philosophy

Arcanum takes an opinionated stance on sessions:

- **Sessions are structured, not junk drawers.** The `Session` object exposes purpose-built methods (`csrfToken()`, `flash()`, `identityId()`), not `get('arbitrary_key')`. Shopping carts, wizard state, and draft data belong in your persistence layer.
- **Sessions are HTTP-only.** CLI commands authenticate via tokens (`--token` flag or environment variable). There is no CLI session.
- **Handlers never touch sessions.** The session lives in PSR-15 middleware. By the time a command or query handler runs, authentication is already resolved. Handlers receive a typed `Identity` object — they don't know or care whether it came from a session cookie or a JWT.
- **Auth is a transport concern; identity is a domain concern.** A session cookie and a Bearer token are two different transport mechanisms that both resolve to the same identity. The session stores the identity ID; the auth layer (a separate package) resolves it.

## How It Works

```
HTTP Request
  → SessionMiddleware — reads session cookie, hydrates Session
  → CsrfMiddleware — validates CSRF token on POST/PUT/PATCH/DELETE
  → App middleware (CORS, etc.)
  → Router → DTO hydration → Handler
  ← SessionMiddleware — persists session, sets cookie on response
```

The `SessionMiddleware` is the outermost framework middleware. It reads the session ID from the cookie, loads session data from the configured driver, and makes the `Session` available via the `SessionRegistry`. On the way out, it persists any changes back to the driver and sets the `Set-Cookie` header.

## Session Drivers

| Driver | Class | Storage | Use case |
|---|---|---|---|
| `file` | `FileSessionDriver` | One file per session | Default, zero config |
| `cache` | `CacheSessionDriver` | Any PSR-16 cache (Vault) | Redis, APCu via existing cache drivers |
| `cookie` | `CookieSessionDriver` | Encrypted client cookie | Zero server storage, ~4KB limit |

### File Driver

```php
// config/session.php
return [
    'driver' => 'file',
    'lifetime' => 7200,
];
```

Sessions are stored as serialized files in `files/sessions/`. Expired sessions are lazily deleted on read and garbage-collected probabilistically (1% chance per request).

### Cache Driver

```php
return [
    'driver' => 'cache',
    'store' => 'redis',   // references a store in config/cache.php
    'lifetime' => 7200,
];
```

Delegates to any Vault `CacheInterface`. If your cache config has a Redis store, sessions use Redis with zero extra setup. TTL-based expiry is handled natively by the cache driver.

### Cookie Driver

```php
return [
    'driver' => 'cookie',
    'lifetime' => 7200,
];
```

All session data is encrypted via the framework's `Encryptor` (SodiumEncryptor by default) and stored in the cookie itself. No server-side storage. Limited to ~4KB, but sufficient for the structured session data (CSRF token, identity ID, flash messages).

## Configuration

```php
// config/session.php
return [
    'driver' => 'file',             // file, cache, cookie
    'store' => '',                   // cache store name (cache driver only)
    'lifetime' => 7200,              // seconds
    'cookie' => 'arcanum_session',   // cookie name
    'path' => '/',                   // cookie path
    'domain' => '',                  // cookie domain
    'secure' => true,                // HTTPS only
    'http_only' => true,             // no JavaScript access
    'same_site' => 'Lax',           // Strict, Lax, or None
];
```

## CSRF Protection

`CsrfMiddleware` generates a CSRF token stored in the session and validates it on state-changing requests (POST, PUT, PATCH, DELETE). The token is read from:

1. The `_token` field in the request body (form submissions)
2. The `X-CSRF-TOKEN` header (AJAX requests)

Requests with a `Bearer` token in the `Authorization` header bypass CSRF — API clients authenticate via tokens and don't use cookies, so CSRF doesn't apply.

Failed validation throws `HttpException` with **403 Forbidden**.

### In templates

```html
<form method="POST" action="/contact/submit">
    <input type="hidden" name="_token" value="{{ $csrfToken }}">
    <!-- fields -->
</form>
```

### In AJAX

```javascript
fetch('/contact/submit', {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({ name: 'Jane', email: 'jane@example.com' }),
});
```

## Flash Messages

Flash messages are write-once, read-once — set during one request, available during the next, then discarded.

```php
// In middleware or a redirect handler:
$session->flash()->set('success', 'Contact form submitted.');

// In the next request's template:
$session->flash()->get('success'); // "Contact form submitted."
$session->flash()->has('success'); // true
$session->flash()->all();          // ['success' => 'Contact form submitted.']
```

## Session Lifecycle

### Regeneration

`Session::regenerate()` creates a new session ID while preserving data. Called automatically when `setIdentity()` is invoked (login). Prevents session fixation attacks.

### Invalidation

`Session::invalidate()` destroys all data and creates a new session ID. Called automatically when `clearIdentity()` is invoked (logout). The old session is destroyed in the driver.

## Bootstrap

The `Bootstrap\Sessions` bootstrapper registers `SessionDriver`, `SessionConfig`, and `SessionRegistry` in the container. It runs after `Bootstrap\Security` and `Bootstrap\Cache` (sessions may need encryption and cache drivers).

Session middleware (`SessionMiddleware` and `CsrfMiddleware`) is registered in `Bootstrap\Middleware` as the outermost framework middleware — app middleware runs inside the session layer.

CLI kernels skip session bootstrapping entirely.
