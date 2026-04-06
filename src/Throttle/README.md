# Arcanum Throttle

Throttle is a rate-limiting package backed by PSR-16 (`CacheInterface`). It ships two strategies — token bucket (burst-friendly) and sliding window (strict) — and returns a `Quota` value object with rate-limit headers ready to attach to the response.

## Quick start

```php
use Arcanum\Throttle\RateLimiter;
use Psr\SimpleCache\CacheInterface;

// Inject via the container
function handle(RateLimiter $limiter): void
{
    $quota = $limiter->attempt(
        key: $clientIp,
        limit: 60,            // 60 requests
        windowSeconds: 60,    // per 60 seconds
    );

    if (! $quota->isAllowed()) {
        // 429 Too Many Requests
    }

    // Add rate-limit headers to response
    foreach ($quota->headers() as $name => $value) {
        $response = $response->withHeader($name, $value);
    }
}
```

## Strategies

### Token bucket (default)

Tokens refill at a steady rate up to the configured limit. Each request costs one token. A client that has been idle accumulates tokens, allowing controlled bursts.

Cache entry: `{tokens: float, lastRefill: int}` with TTL equal to the window.

```php
use Arcanum\Throttle\RateLimiter;
use Arcanum\Throttle\TokenBucket;

$limiter = new RateLimiter($cache, new TokenBucket());
```

### Sliding window

Tracks request counts in fixed windows and weights the previous window's count by its overlap with the current sliding window. Provides a smooth rate limit with no burst allowance.

Cache entries: `{key}_cur` and `{key}_prev`, each storing `{count: int, windowStart: int}`.

```php
use Arcanum\Throttle\RateLimiter;
use Arcanum\Throttle\SlidingWindow;

$limiter = new RateLimiter($cache, new SlidingWindow());
```

### Custom strategies

Implement the `Throttler` interface:

```php
use Arcanum\Throttle\Quota;
use Arcanum\Throttle\Throttler;
use Psr\SimpleCache\CacheInterface;

final class FixedWindow implements Throttler
{
    public function attempt(CacheInterface $cache, string $key, int $limit, int $windowSeconds): Quota
    {
        // Your logic here
    }
}
```

## Quota

`Quota` is an immutable value object returned by every attempt:

| Property | Type | Description |
|---|---|---|
| `$allowed` | `bool` | Whether the request was allowed |
| `$remaining` | `int` | Requests left in the current window |
| `$limit` | `int` | Maximum requests per window |
| `$resetAt` | `int` | Unix timestamp when the window resets |

### Headers

`$quota->headers()` returns an array ready for HTTP response headers:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 42
X-RateLimit-Reset: 1700000060
Retry-After: 30              ← only on denied requests
```

## HTTP middleware example

```php
use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\StatusCode;
use Arcanum\Throttle\RateLimiter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RateLimit implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly int $limit = 60,
        private readonly int $windowSeconds = 60,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $key = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        $quota = $this->limiter->attempt($key, $this->limit, $this->windowSeconds);

        if (! $quota->isAllowed()) {
            throw new HttpException(StatusCode::TooManyRequests);
        }

        $response = $handler->handle($request);

        foreach ($quota->headers() as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
```

## At a glance

```
Throttle/
├── Throttler         — strategy interface
├── TokenBucket       — burst-friendly token bucket algorithm
├── SlidingWindow     — strict sliding window algorithm
├── RateLimiter       — main entry point (cache + strategy)
└── Quota             — immutable attempt result with headers
```
