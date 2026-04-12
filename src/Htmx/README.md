# Arcanum Htmx

First-class htmx support for the Arcanum framework, targeting **htmx 4**.

If you're new to htmx, the short version is: htmx lets you make parts of a page interactive without writing JavaScript. You add attributes like `hx-get` and `hx-swap` to your HTML elements, and htmx handles fetching new content from the server and swapping it into the page. Arcanum's Htmx package makes the server side of that story seamless — your handlers don't need to know whether they're serving a full page or a small fragment.

This README covers everything the package does, how to set it up, and how the pieces fit together. If you're looking for the htmx client-side attribute reference (`hx-get`, `hx-swap`, `hx-trigger`, etc.), that lives at [four.htmx.org](https://four.htmx.org).

---

## How it works: the three rendering modes

When a browser makes a request to your Arcanum app, the Htmx package looks at the incoming HTTP headers to figure out what kind of request it is. Based on that, it picks one of three rendering modes automatically. Your handler code doesn't change between modes — it always returns the same data, and the framework decides how much of the page to render.

**Mode 1: Full page.** This is a normal browser request (no htmx involved). The framework renders the complete HTML page, including the layout — `<html>`, `<head>`, navigation, footer, everything. This is what happens when someone types a URL into their address bar or follows a regular link.

**Mode 2: Content section only.** This happens when htmx makes a "boosted" navigation request (a link with `hx-boost`). The browser already has the layout (nav, footer, etc.) from the initial page load, so the framework skips the layout wrapper and returns just the content section. htmx swaps it into the page, and it feels like an instant page transition.

**Mode 3: Element extraction.** This is the most interesting mode. When htmx sends a partial request targeting a specific element (via `HX-Target`), the framework finds that element in the template by its `id` attribute, extracts just that piece, and returns it. The rest of the page is never rendered at all. This is what makes htmx + Arcanum efficient — a button click that refreshes a sidebar only renders the sidebar, not the entire page.

Here's the key insight: **your handler always returns the same data.** A handler that powers a dashboard page returns `['stats' => ..., 'sidebar' => ..., 'title' => ...]` regardless of whether the browser wants the full page or just the sidebar. The framework handles the rest.

---

## Getting started

### 1. Set up your layout

Your layout template needs three things in the `<head>`:

```html
<head>
    {{! Htmx::script() !}}
    <meta name="csrf-token" content="{{ Csrf::token() }}">
    {{! Htmx::csrf() !}}
</head>
```

`Htmx::script()` outputs the `<script>` tag that loads htmx from a CDN. `Htmx::csrf()` loads a small JS helper that automatically attaches your CSRF token to every htmx request (more on that below). The `<meta>` tag is where the CSRF token lives so the JS helper can find it.

### 2. Register the middleware

In your `config/middleware.php`, add the three Htmx middleware to the global stack:

```php
'global' => [
    // ... your other middleware ...
    \Arcanum\Htmx\HtmxRequestMiddleware::class,
    \Arcanum\Htmx\HtmxEventTriggerMiddleware::class,
    \Arcanum\Htmx\HtmxAuthRedirectMiddleware::class,
],
```

Order matters here. `HtmxRequestMiddleware` must come first because it sets up the request context that the other two middleware read from.

Here's what each one does:

- **HtmxRequestMiddleware** reads the htmx headers from the incoming request and tells the rendering pipeline what kind of request it is (full page, content section, or element extraction). It also adds a `Vary: HX-Request` header to the response and serves the CSRF JS helper at `/_htmx/csrf.js`.
- **HtmxEventTriggerMiddleware** watches for domain events that should notify the browser (more on this in the event projection section below) and adds them as `HX-Trigger` response headers.
- **HtmxAuthRedirectMiddleware** handles the case where an htmx request hits a login wall (401/403). Instead of returning the login page HTML — which htmx would swap into a random element — it sends an `HX-Location` header that tells htmx to navigate the whole browser to the login page.

### 3. Add htmx attributes to your templates

Now you can start adding htmx attributes to your HTML. Here's a simple example — a refresh button that reloads a section of the page:

```html
<div id="stats">
    <h2>Statistics</h2>
    <p>Active users: {{ $activeUsers }}</p>
    <button hx-get="/dashboard.html" hx-target="#stats" hx-swap="outerHTML">
        Refresh
    </button>
</div>
```

When the button is clicked, htmx sends a GET request to `/dashboard.html` with an `HX-Target: stats` header. Arcanum sees that header, finds the `<div id="stats">` in the template, and returns just that element. htmx swaps it into the page, and the stats are refreshed without a full page reload.

Notice that the handler doesn't need to do anything special. It returns the same data it always does — `['activeUsers' => 42, ...]` — and the framework handles the extraction.

---

## Element extraction in detail

When htmx targets a specific element, Arcanum extracts it from your template by searching for the matching `id` attribute. This extraction works on the compiled template output, so it sees your HTML structure with all the template expressions already compiled.

By default, the extraction returns **outerHTML** — that means the response includes the element itself, along with everything inside it. For example, if your template has:

```html
<h1>Dashboard</h1>
<div id="sidebar"><p>{{ $stats }}</p></div>
<div id="main"><p>{{ $content }}</p></div>
```

...and htmx targets `sidebar`, the response is:

```html
<div id="sidebar"><p>42 active users</p></div>
```

The `<h1>` and `#main` are never rendered. This is efficient — expensive data for other sections of the page is never computed (especially when combined with lazy closures, covered below).

**What happens when the id doesn't exist?** If the template doesn't have an element with the requested id, the framework logs a warning and falls back to returning the entire content section (without the layout). This is a soft failure — the user sees a working page, and you see a log entry telling you which id was missing from which template.

**Important: htmx only sends `HX-Target` when the target element has an `id`.** If you write `hx-target="#sidebar"`, htmx sends `HX-Target: sidebar`. But if you write `hx-target="closest div"` or target an element without an id, htmx doesn't send the header at all, and Arcanum falls back to content-section rendering. The rule is simple: if you want server-side element extraction, give your target element an `id`.

### Fragment markers for innerHTML swap modes

The default outerHTML extraction works great with `hx-swap="outerHTML"` — the response replaces the entire element, including its wrapper tag. But three swap modes need just the inner content without the wrapper: `innerHTML`, `afterbegin`, and `beforeend`.

For those cases, you can add explicit fragment markers to your template:

```html
<ul id="notifications">
    {{ fragment 'notifications' }}
    {{ foreach $items as $item }}
    <li>{{ $item }}</li>
    {{ endforeach }}
    {{ endfragment }}
</ul>
```

When htmx targets `notifications` and the framework finds a `{{ fragment 'notifications' }}` marker, it returns only the content between the markers — the `<li>` items without the `<ul>` wrapper. This is exactly what `hx-swap="innerHTML"` or `hx-swap="beforeend"` expects.

When there's no fragment marker for the target id, the framework falls back to outerHTML extraction as usual. The markers are completely transparent during normal full-page rendering — they're stripped out and have no effect on the output.

You don't need fragment markers most of the time. The default outerHTML extraction with `hx-swap="outerHTML"` is the recommended approach. Fragment markers are the escape hatch for the specific swap modes that need inner content.

---

## Lazy data with closures

This feature becomes really valuable as your pages grow. Consider a dashboard handler that returns data for several sections:

```php
public function __invoke(GetDashboard $query): array
{
    return [
        'title'   => 'Dashboard',
        'stats'   => $this->db->model->computeStats(),     // expensive query
        'sidebar' => $this->db->model->recentActivity(),   // another expensive query
        'chart'   => $this->db->model->chartData(),        // yet another
    ];
}
```

When htmx refreshes just the sidebar, all three expensive queries still run, even though only `$sidebar` is used in the rendered fragment. That's wasteful.

The fix is to wrap expensive values in closures:

```php
public function __invoke(GetDashboard $query): array
{
    return [
        'title'   => 'Dashboard',
        'stats'   => fn() => $this->db->model->computeStats(),
        'sidebar' => fn() => $this->db->model->recentActivity(),
        'chart'   => fn() => $this->db->model->chartData(),
    ];
}
```

Now the framework is smart about it. Before rendering, it scans the compiled template fragment for `$variable` references. Only closures whose variable names actually appear in the fragment are invoked. The rest are skipped entirely — no query, no computation, no wasted time.

On a full page render, all closures are invoked (the full page needs all the data). The optimization only kicks in for partial renders where element extraction or fragment rendering narrows the scope.

A few things to keep in mind:

- **Closures must be pure data suppliers.** They should fetch and return data, nothing else. Don't dispatch events or trigger side effects from inside a closure — those belong in the handler itself, where the framework's event capture is active.
- **The scan is text-based.** If a variable name appears anywhere in the fragment source — even inside a `{{ if false }}` block that never executes — the closure will be invoked. This is a deliberate tradeoff. The big win is skipping closures for data that belongs to entirely different sections of the page, and that works perfectly.
- **Plain values work exactly as before.** You can mix closures and regular values freely. `'title' => 'Dashboard'` is always available; `'stats' => fn() => ...` is resolved on demand.

---

## Event projection: making components refresh each other

This is one of the most powerful patterns in Arcanum + htmx. When a command handler does something (like adding a guestbook entry), you often want other parts of the page to update in response (like the entry list). With traditional approaches, you'd need JavaScript to coordinate this. With Arcanum's event projection, it happens automatically.

The idea is straightforward: your command handler dispatches a domain event (something it probably does already). The Htmx package intercepts that event and sends it to the browser as an `HX-Trigger` response header. Any htmx element that's listening for that event will automatically refresh itself.

Here's a complete example using a guestbook.

### Step 1: Create an event that implements ClientBroadcast

```php
use Arcanum\Htmx\ClientBroadcast;

final readonly class EntryAdded implements ClientBroadcast
{
    public function __construct(
        public string $name,
    ) {
    }

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

The `ClientBroadcast` interface requires two methods: `eventName()` returns the name of the event that htmx will fire on the browser side, and `payload()` returns any data you want to send along with it (or an empty array for a signal-only event).

### Step 2: Dispatch the event from your command handler

```php
public function __invoke(AddEntry $command): void
{
    // ... insert the entry into the database ...

    $this->dispatcher->dispatch(new EntryAdded($command->name));
}
```

This is a normal Echo event dispatch — the same thing you'd do even without htmx. The `HtmxEventTriggerMiddleware` captures any `ClientBroadcast` events that were dispatched during the request and adds them to the response headers automatically.

### Step 3: Listen for the event in your template

```html
<div id="guestbook-list"
     hx-get="/guestbook/get-entries.html"
     hx-trigger="guestbook:entry:added from:body"
     hx-swap="outerHTML">
    {{ foreach $entries as $entry }}
    <li>{{ $entry['name'] }}: {{ $entry['message'] }}</li>
    {{ endforeach }}
</div>
```

The `hx-trigger="guestbook:entry:added from:body"` attribute tells htmx to watch for a `guestbook:entry:added` event on the document body. When the `AddEntry` command completes, the response includes an `HX-Trigger` header with the event name. htmx fires that event on the body, the `<div>` hears it, and it re-fetches its content from `/guestbook/get-entries.html`. The list updates without any JavaScript.

The `from:body` part is important — `HX-Trigger` events are fired on the body element, so your listening element needs to listen there.

### Command redirects work automatically

When a command handler returns a 201 Created response with a `Location` header (which is the standard Arcanum pattern for commands that create resources), the `HtmxEventTriggerMiddleware` automatically copies it to an `HX-Location` header for htmx requests. This means htmx navigates to the new URL after the command completes, without any special handling in the handler.

---

## Validation errors and form re-rendering

When a command fails validation, Arcanum returns a 422 response with the form re-rendered including error messages. The recommended pattern uses **Idiomorph** (built into htmx v4) to preserve the user's typed values while inserting the error messages.

### The pattern

1. **Extract the form into a shared partial** in `app/Templates/` so it can be included from both the page template and the error template:

```html
<!-- app/Templates/forms/_my-form.html -->
<form id="my-form"
      hx-post="/my-command"
      hx-swap="outerMorph"
      hx-on:my:success="this.reset()"
      class="...">
    {{ csrf }}
    {{ if isset($errors) && $errors }}
    <div class="...error styles...">
        <ul>
            {{ foreach $errors as $field => $messages }}
                {{ foreach $messages as $msg }}
                <li>{{ $msg }}</li>
                {{ endforeach }}
            {{ endforeach }}
        </ul>
    </div>
    {{ endif }}
    <input type="text" name="title" placeholder="Title" required>
    <button type="submit">Submit</button>
</form>
```

2. **Include the partial from both templates.** The page template includes it for the initial render. The command's co-located `.422.html` error template includes the same partial, so the re-rendered form has identical structure:

```html
<!-- app/Pages/MyPage.html (or wherever the form lives) -->
{{ include 'forms/_my-form' }}

<!-- app/Domain/Shop/Command/MyCommand.422.html -->
{{ include 'forms/_my-form' }}
```

3. **Idiomorph preserves input values.** The `hx-swap="outerMorph"` attribute tells htmx v4 to use its built-in Idiomorph for the swap. Idiomorph compares the live DOM (with the user's typed values) against the server response and morphs the differences — inserting error messages while keeping input values intact. Note: the v2 extension syntax `morph:outerHTML` does not work in v4; use `outerMorph` (or `innerMorph` for children-only morphing).

### How it works end to end

1. User submits the form → htmx sends POST with form data
2. `ValidationGuard` rejects invalid input → framework resolves `MyCommand.422.html`
3. Error template includes the same form partial, now with `$errors` populated
4. htmx receives the 422 response → Idiomorph morphs the form in place
5. Error messages appear, typed values stay, CSRF token refreshes

### The status-specific template resolution chain

The `{DtoClass}.{status}.{format}` convention works for any status code and format, not just 422:

1. **Co-located**: `AddEntry.422.html` next to `AddEntry.php`
2. **App-wide fallback**: `app/Templates/errors/422.html`
3. **Framework default**: minimal error fragment for htmx requests, full error page otherwise

Error templates receive these variables: `$code`, `$title`, `$message`, `$errors` (validation field errors), `$suggestion` (from `ArcanumException`).

### Escape hatches

- **`hx-status:422`** on the form — route error responses to a different target. `hx-status:422="target:#errors swap:innerHTML"` sends the 422 response to an error container instead of morphing the form.
- **App-wide error template** — `app/Templates/errors/422.html` handles all commands that don't have a co-located `.422.html`.
- **No template at all** — the framework returns a minimal error fragment for htmx requests (a `<ul>` of field errors for 422) or a full error page for non-htmx requests.

---

## CSRF protection

htmx sends requests using `XMLHttpRequest` under the hood. Unlike regular form submissions, XHR requests don't automatically include CSRF tokens. The Htmx package solves this with a small JavaScript helper that runs automatically.

When you include `{{! Htmx::csrf() !}}` in your layout's `<head>`, it loads a tiny script from `/_htmx/csrf.js`. This script listens for htmx's `htmx:configRequest` event (which fires before every htmx request) and does the following:

1. Checks if the request method is something that modifies data (POST, PUT, PATCH, DELETE — anything except GET, HEAD, and OPTIONS).
2. Reads the CSRF token from the `<meta name="csrf-token">` tag in your page's `<head>`.
3. Adds it as an `X-CSRF-TOKEN` header on the outgoing request.

This happens automatically for every htmx request. You don't need to add hidden inputs to your forms or manually set headers. Just make sure the `<meta name="csrf-token">` tag is in your layout (the starter app includes it), and everything works.

The script also handles token rotation during boosted navigation — when htmx replaces the page content, the meta tag might contain a new token, and the script picks it up automatically.

The `/_htmx/csrf.js` endpoint is served by the framework (via `HtmxRequestMiddleware`) and is cached by the browser for 24 hours.

---

## Authentication redirects

There's a subtle problem with htmx and login redirects. Normally, when a user hits a protected page without being logged in, the server returns a redirect to `/login`. The browser follows the redirect and shows the login page. Simple.

But with htmx, the request might be targeting a small element — say, a sidebar. If the server returns the login page HTML, htmx will swap it into the sidebar element. Now your sidebar contains an entire login page. Not great.

`HtmxAuthRedirectMiddleware` catches this case. When an htmx request gets a 401 (Unauthorized) or 403 (Forbidden) response, the middleware replaces it with an empty response that includes an `HX-Location: /login` header. This tells htmx to perform a full browser navigation to the login page instead of swapping content.

You can configure the redirect URL and behavior in `config/htmx.php`:

```php
'auth_redirect' => '/login',   // where to send the user
'auth_refresh'  => false,       // set to true to use HX-Refresh (full page reload) instead
```

Non-htmx requests are not affected — they pass through the middleware unchanged.

---

## The Vary header

When you have a mix of htmx and non-htmx requests going to the same URL, HTTP caches (CDNs, browser caches, reverse proxies) need to know that the responses are different. A full-page response and an htmx partial response have the same URL but very different content.

`HtmxRequestMiddleware` handles this by adding `Vary: HX-Request` to every response. This tells caches to store separate versions based on whether the `HX-Request` header was present. Without this, a CDN could cache the partial response from an htmx request and serve it to a user who navigated to the URL directly — they'd see a fragment instead of a full page.

If you manage Vary headers yourself (or don't use a CDN), you can disable this in `config/htmx.php`:

```php
'vary' => false,
```

---

## Configuration reference

All configuration lives in `config/htmx.php`. Here's every option:

| Key | Default | What it does |
|---|---|---|
| `version` | `'4.0.0-beta1'` | The htmx version to load from CDN. Used by `Htmx::script()`. |
| `cdn_url` | unpkg URL template | CDN URL with a `{version}` placeholder that gets replaced. |
| `integrity` | `''` | Subresource Integrity hash for the CDN script. Leave empty to skip the check (useful during beta). |
| `vary` | `true` | Whether to auto-add `Vary: HX-Request` to every response. |
| `auth_redirect` | `'/login'` | Where to redirect htmx requests that get a 401 or 403. |
| `auth_refresh` | `false` | When `true`, uses `HX-Refresh` (full page reload) instead of `HX-Location` for auth redirects. |

---

## Inspecting htmx requests in handlers

Most of the time, you don't need to know whether a request came from htmx or not — your handler returns data, and the framework picks the rendering mode. But occasionally, a handler needs to vary its behavior based on the request type.

For those cases, you can type-hint `HtmxRequest` in your handler's constructor or `__invoke` method:

```php
public function __invoke(GetDashboard $query, HtmxRequest $htmx): array
{
    if ($htmx->isHtmx()) {
        $target = $htmx->target(); // 'sidebar', or null if no target
    }

    return ['stats' => $this->loadStats()];
}
```

`HtmxRequest` is a thin wrapper around the PSR-7 `ServerRequestInterface` that gives you typed access to all the htmx headers:

| Method | What it returns | Which header |
|---|---|---|
| `isHtmx()` | Whether this is an htmx request | `HX-Request` |
| `isBoosted()` | Whether the request was triggered by `hx-boost` | `HX-Boosted` |
| `isHistoryRestore()` | Whether this is a history restoration request | `HX-History-Restore-Request` |
| `type()` | `HtmxRequestType::Full`, `Partial`, or `null` | `HX-Request-Type` |
| `target()` | The target element's id (bare id, normalized) | `HX-Target` |
| `targetRaw()` | The raw `HX-Target` value (v4 sends `tagName#id`) | `HX-Target` |
| `swapMode()` | The swap mode (`innerHTML`, `outerHTML`, etc.) | `HX-Swap` |
| `triggerId()` | The id of the element that triggered the request | `HX-Trigger` |
| `triggerName()` | The name of the triggering element | `HX-Trigger-Name` |
| `currentUrl()` | The URL the browser was on when the request was made | `HX-Current-URL` |
| `prompt()` | The user's response to an `hx-prompt` dialog | `HX-Prompt` |

---

## Building htmx response headers

The package includes `HtmxResponse`, an immutable builder for composing htmx response headers. You probably won't need this in application code — the middleware handles the common cases automatically. But if you're writing custom middleware that needs fine-grained control over the htmx response, it's available:

```php
$response = (new HtmxResponse($originalResponse))
    ->withLocation('/dashboard')
    ->withTrigger('toast', ['message' => 'Saved!'])
    ->withTrigger('highlight', ['id' => 42])
    ->toResponse();
```

Every method returns a new instance (the builder is immutable). Trigger methods merge — calling `withTrigger()` multiple times accumulates events into a single `HX-Trigger` JSON header rather than overwriting.

For the `HX-Location` header's JSON envelope form (when you need to specify a target, swap mode, or other navigation options), there's an `HtmxLocation` value object:

```php
$location = new HtmxLocation(
    path: '/items/42',
    target: '#main',
    swap: 'innerHTML',
);

$response = (new HtmxResponse($originalResponse))
    ->withLocation($location)
    ->toResponse();
```

---

## A note on dynamic ids

You might be tempted to do something like this inside a `{{ foreach }}` loop:

```html
{{ foreach $items as $item }}
<div id="item-{{ $item['id'] }}">{{ $item['name'] }}</div>
{{ endforeach }}
```

This won't work with element extraction, because the id is dynamic — it contains a template expression that only resolves at render time. The framework can't find `id="item-42"` in the raw template source because the template source says `id="item-{{ $item['id'] }}"`.

The CQRS solution is cleaner anyway: if an individual item needs to be independently refreshable via htmx, it should be its own query with its own handler and template. Instead of extracting one item from a list template, create a `GET /items/42` route that returns just that item. Each independently updatable region of your page is its own query — its own URL, its own handler, its own template. This keeps everything simple and predictable.

---

## Package contents

Here's everything in the package, for reference:

| Class | What it does |
|---|---|
| `HtmxRequest` | Wraps the PSR-7 request with typed accessors for htmx headers |
| `HtmxRequestType` | Enum with two values: `Full` and `Partial` |
| `HtmxResponse` | Immutable builder for composing htmx response headers |
| `HtmxLocation` | Value object for the JSON form of `HX-Location` |
| `ClientBroadcast` | Interface for domain events that should notify the browser |
| `EventCapture` | Decorator around Echo's dispatcher that records broadcast events |
| `FragmentDirective` | Custom compiler directive for `{{ fragment }}` / `{{ endfragment }}` markers |
| `HtmxAwareResponseRenderer` | Picks the rendering mode based on htmx request headers |
| `HtmxRequestMiddleware` | Sets up request context, serves the CSRF endpoint, adds Vary |
| `HtmxEventTriggerMiddleware` | Turns captured broadcast events into `HX-Trigger` response headers |
| `HtmxAuthRedirectMiddleware` | Redirects htmx requests to the login page on 401/403 |
| `HtmxCsrfController` | Serves the CSRF JS helper at `/_htmx/csrf.js` |
| `HtmxHelper` | Template helper: `Htmx::script()` and `Htmx::csrf()` |
