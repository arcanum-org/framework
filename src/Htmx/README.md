# Arcanum Htmx

First-class htmx support for the Arcanum framework, targeting **htmx 4** directly.

htmx composes naturally with CQRS: every action is already its own URL with its own handler, which is exactly what htmx wants on the wire. The same handler that returns data for a full page load returns the same data for an htmx partial — the framework decides what slice to render. Handlers stay transport-agnostic.

---

## The three rendering modes

When an HTTP request arrives, the `HtmxAwareResponseRenderer` picks the rendering shape based on the htmx headers:

| Mode | Condition | Output |
|---|---|---|
| **Full** | Non-htmx request | Complete HTML page with layout |
| **Content section** | htmx Full type (boosted nav) or Partial without target | Content section only, no layout wrapper |
| **Element extraction** | htmx Partial with `HX-Target` | Just the target element (or fragment) |

Handlers never choose the mode. They return data; the framework picks the shape.

### Element extraction: the zero-ceremony default

Developers write normal HTML with `id` attributes:

```html
<h1>Dashboard</h1>
<div id="sidebar"><p>{{ $stats }}</p></div>
<div id="main"><p>{{ $content }}</p></div>
```

When htmx sends `HX-Target: sidebar`, the framework finds `<div id="sidebar">` in the compiled template, extracts just that element, and returns it. The `<h1>` and `#main` are never rendered.

This is outerHTML extraction — the response includes the element's own tags. It pairs naturally with `hx-swap="outerHTML"` (recommended) and the positional swap modes (`beforebegin`, `afterend`).

### Explicit fragment markers for innerHTML

Three swap modes need just the inner content without the wrapper element: `innerHTML`, `afterbegin`, `beforeend`. The `{{ fragment }}` directive is the opt-in:

```html
<div id="main">
    {{ fragment 'main' }}
    <p>This content is returned without the wrapper div.</p>
    {{ endfragment }}
</div>
```

When `HX-Target: main` arrives and a `{{ fragment 'main' }}` marker exists, the framework returns the inner content only. When no marker exists, it falls back to outerHTML. The markers are stripped during normal (non-htmx) rendering — they're transparent.

`{{ fragment }}` is a custom directive registered by the Htmx package via the Shodo `CompilerDirective` system. Without the Htmx package, the directive doesn't exist.

### Lazy data via closures

Handlers can return closures for expensive data. On partial renders, only closures whose variables appear in the rendered slice are invoked:

```php
return [
    'sidebar' => fn() => $this->db->model->recentPosts(),   // invoked only if $sidebar is in the fragment
    'stats'   => fn() => $this->db->model->expensiveStats(), // skipped if not referenced
    'title'   => 'Dashboard',                                 // plain values always available
];
```

On full renders, all closures are invoked. Closures must be pure data suppliers — no side effects, no event dispatching. Events belong in the handler.

---

## Inspecting htmx requests

`HtmxRequest` is a read-side decorator over `ServerRequestInterface`. Handlers that need to inspect htmx headers receive it via dependency injection:

```php
public function __invoke(GetDashboard $query, HtmxRequest $htmx): array
{
    if ($htmx->isHtmx()) {
        // htmx request — target() gives the element id
        $target = $htmx->target();  // 'sidebar', or null
    }

    return ['stats' => $this->loadStats()];
}
```

Most handlers don't need this — the rendering pipeline reads the headers automatically. `HtmxRequest` is for the rare handler that wants to vary its data based on request type.

### Accessors

| Method | Returns | Header |
|---|---|---|
| `isHtmx()` | `bool` | `HX-Request` |
| `isBoosted()` | `bool` | `HX-Boosted` |
| `isHistoryRestore()` | `bool` | `HX-History-Restore-Request` |
| `type()` | `?HtmxRequestType` | `HX-Request-Type` (`Full` or `Partial`) |
| `target()` | `?string` | `HX-Target` (normalized to bare id) |
| `targetRaw()` | `?string` | `HX-Target` (raw, v4 sends `tagName#id`) |
| `swapMode()` | `?string` | `HX-Swap` |
| `triggerId()` | `?string` | `HX-Trigger` |
| `triggerName()` | `?string` | `HX-Trigger-Name` |
| `currentUrl()` | `?string` | `HX-Current-URL` |
| `prompt()` | `?string` | `HX-Prompt` |

`target()` normalizes htmx v4's `tagName#id` format to a bare id. `targetRaw()` returns the raw header value.

---

## Event projection: domain events as HX-Trigger

The CQRS-native cross-component refresh story. A command handler dispatches a domain event through Echo; htmx elements listening for that event refresh automatically.

### 1. Define a broadcast event

Implement `ClientBroadcast` on any Echo event:

```php
final readonly class EntryAdded implements ClientBroadcast
{
    public function __construct(public string $name) {}

    public function eventName(): string
    {
        return 'guestbook:entry:added';
    }

    public function payload(): array
    {
        return ['name' => $this->name];
    }
}
```

### 2. Dispatch it from the handler

```php
public function __invoke(AddEntry $command): void
{
    // ... insert the entry ...
    $this->dispatcher->dispatch(new EntryAdded($command->name));
}
```

### 3. Listen in the template

```html
<div id="guestbook-list"
     hx-get="/guestbook/get-entries.html"
     hx-trigger="guestbook:entry:added from:body"
     hx-swap="outerHTML">
    <!-- entries rendered here -->
</div>
```

When the command completes, `HtmxEventTriggerMiddleware` reads the captured `EntryAdded` event and adds `HX-Trigger: {"guestbook:entry:added": {"name": "Alice"}}` to the response. htmx fires the event on the document body; the `<div>` hears it and re-fetches its content.

### Timing control

By default, `HX-Trigger` fires immediately (before the swap). For events that should fire after the swap or after the settle step:

| Implement | Header | Fires |
|---|---|---|
| `ClientBroadcast` | `HX-Trigger` | Before swap |
| `BroadcastAfterSwap` | `HX-Trigger-After-Swap` | After swap completes |
| `BroadcastAfterSettle` | `HX-Trigger-After-Settle` | After settle completes |

The sub-interfaces extend `ClientBroadcast` — same `eventName()` and `payload()` contract, different timing.

### Command redirects

`HtmxEventTriggerMiddleware` also copies `Location` headers to `HX-Location` for htmx requests. A command that returns a 201 Created with a `Location` header works without special-casing — htmx navigates to the new URL.

---

## CSRF protection

htmx sends requests via `XMLHttpRequest`, which doesn't carry CSRF tokens automatically. The package provides a lightweight JS shim:

### Setup in the layout

```html
<head>
    {{! Htmx::script() !}}
    <meta name="csrf-token" content="{{ Html::csrfToken() }}">
    {{! Htmx::csrf() !}}
</head>
```

`Htmx::script()` renders the htmx `<script>` tag from CDN with the pinned version. `Htmx::csrf()` renders `<script src="/_htmx/csrf.js"></script>`.

### How it works

The `/_htmx/csrf.js` endpoint (served by `HtmxCsrfController` via `HtmxRequestMiddleware`) returns a JS shim that:

1. Listens to `htmx:configRequest`
2. On every non-GET request, reads the CSRF token from `<meta name="csrf-token">`
3. Adds it as `X-CSRF-TOKEN` to the request headers

The shim handles boosted-navigation token rotation by reading the updated meta tag after each full page swap. The response is cacheable (`Cache-Control: public, max-age=86400`).

---

## Auth redirect handling

When an htmx request receives a 401 or 403, the normal redirect-to-login pattern breaks — htmx would swap the login page HTML into whatever element triggered the request. `HtmxAuthRedirectMiddleware` intercepts the error and returns an empty body with `HX-Location: /login` (or `HX-Refresh: true`), telling htmx to perform a full client-side navigation instead.

Non-htmx requests pass through unchanged.

Configure via `config/htmx.php`:

```php
'auth_redirect' => '/login',  // URL to navigate to
'auth_refresh'  => false,     // true = HX-Refresh instead of HX-Location
```

---

## Vary header

`HtmxRequestMiddleware` auto-adds `Vary: HX-Request` to every response (via `withAddedHeader`, preserving existing Vary values). This tells HTTP caches to distinguish between htmx and full-page responses — without it, a CDN could serve a partial HTML fragment to a full-page request, or vice versa.

Disable in config if you manage Vary headers yourself:

```php
'vary' => false,
```

---

## Configuration

All config lives in `config/htmx.php`:

| Key | Default | Purpose |
|---|---|---|
| `version` | `'4.0.0-beta1'` | htmx version for CDN URL |
| `cdn_url` | unpkg template | CDN URL with `{version}` placeholder |
| `integrity` | `''` | SRI hash (empty skips integrity check) |
| `vary` | `true` | Auto-add `Vary: HX-Request` |
| `auth_redirect` | `'/login'` | Auth redirect URL for 401/403 |
| `auth_refresh` | `false` | Use `HX-Refresh` instead of `HX-Location` |

---

## Middleware registration

Register the three middleware in `config/middleware.php`:

```php
'global' => [
    \Arcanum\Htmx\HtmxRequestMiddleware::class,
    \Arcanum\Htmx\HtmxEventTriggerMiddleware::class,
    \Arcanum\Htmx\HtmxAuthRedirectMiddleware::class,
],
```

Order matters: `HtmxRequestMiddleware` must run first (it sets up the request context that the other two read).

---

## HtmxResponse builder

For middleware that needs to compose htmx response headers, `HtmxResponse` is an immutable builder:

```php
$response = (new HtmxResponse($originalResponse))
    ->withLocation('/dashboard')
    ->withTrigger('toast', ['message' => 'Saved!'])
    ->withTriggerAfterSwap('highlight', ['id' => 42])
    ->toResponse();
```

Trigger methods merge — multiple `withTrigger()` calls accumulate into a single `HX-Trigger` JSON header. All builder methods return new instances (immutable).

`HtmxLocation` is a value object for the JSON-envelope form of `HX-Location` when you need to specify a target, swap mode, or other navigation options beyond a simple path.

App code rarely uses `HtmxResponse` directly — the middleware handles the common cases. It's available for custom middleware that needs fine-grained control over htmx response headers.

---

## HX-Target is id-only

htmx only sends `HX-Target` when the target element has an `id` attribute (confirmed in the htmx source: `getAttributeValue(target, 'id')`). When the target has no id, the header is omitted entirely. This means auto-extraction is the only viable server-side partial rendering — the server never receives CSS selectors, only ids (or nothing).

The convention is self-enforcing: if you want server-side partial rendering, put an `id` on the target element. If you don't, htmx swaps the full response and the server renders normally.

---

## Dynamic ids and the CQRS decomposition

`<div id="item-{{ $id }}">` inside a `{{ foreach }}` can't be extracted at the template level — the id is only known after rendering, and the loop variable `$item` would be undefined outside its scope.

The CQRS answer: if something is independently addressable via htmx, it should be its own query with its own handler and template. Per-item updates route to a single-item query (`GET /items/42`), not a fragment of the list query. This aligns with CQRS — each independently updatable region is its own query, with its own URL, handler, and template.

---

## Package contents

| Class | Purpose |
|---|---|
| `HtmxRequest` | Read-side decorator with typed accessors for htmx headers |
| `HtmxRequestType` | Enum: `Full` or `Partial` |
| `HtmxResponse` | Immutable builder for htmx response headers |
| `HtmxLocation` | Value object for JSON-form `HX-Location` |
| `ClientBroadcast` | Interface for events projected as `HX-Trigger` |
| `BroadcastAfterSwap` | Timing sub-interface: fires after swap |
| `BroadcastAfterSettle` | Timing sub-interface: fires after settle |
| `EventCapture` | Decorator that records `ClientBroadcast` events |
| `FragmentDirective` | Custom `CompilerDirective` for `{{ fragment }}` markers |
| `HtmxAwareResponseRenderer` | Picks rendering mode from htmx headers |
| `HtmxRequestMiddleware` | Sets up request context, serves CSRF endpoint, adds Vary |
| `HtmxEventTriggerMiddleware` | Projects captured events into HX-Trigger headers |
| `HtmxAuthRedirectMiddleware` | Converts 401/403 to HX-Location for htmx requests |
| `HtmxCsrfController` | Serves the CSRF JS shim at `/_htmx/csrf.js` |
| `HtmxHelper` | Template helper: `Htmx::script()`, `Htmx::csrf()` |

---

## htmx reference

This package handles the server side. For the `hx-*` attribute reference, swap modes, event lifecycle, and everything else on the client side, see [four.htmx.org](https://four.htmx.org).
