# Arcanum Echo

Echo is an event system. It lets parts of your application talk to each other without knowing about each other directly. One part says "this happened" (dispatches an event), and other parts that care about it (listeners) get notified.

It follows the [PSR-14](https://www.php-fig.org/psr/psr-14/) standard, so it's interoperable with any PSR-14 compatible code.

## The basics

There are three things: **events**, **listeners**, and the **dispatcher**.

```php
// 1. Define an event
class UserRegistered extends \Arcanum\Echo\Event {
    public function __construct(public readonly string $email) {}
}

// 2. Set up a provider and register a listener
$provider = new Provider();
$provider->listen(UserRegistered::class, function (object $event): object {
    sendWelcomeEmail($event->email);
    return $event;
});

// 3. Dispatch
$dispatcher = new Dispatcher($provider);
$dispatcher->dispatch(new UserRegistered('alice@example.com'));
```

That's the whole flow. When `dispatch()` is called, Echo finds all listeners registered for `UserRegistered` and calls them in order.

## Event — the base class

All events extend the abstract `Event` class, which implements PSR-14's `StoppableEventInterface`:

```php
$event->stopPropagation();       // stop further listeners from running
$event->isPropagationStopped();  // check if someone stopped it
```

If a listener calls `stopPropagation()`, Echo stops the pipeline — no further listeners are called.

## Provider — where listeners live

The `Provider` registers listeners and retrieves them when an event is dispatched:

```php
$provider->listen(UserRegistered::class, $myListener);
```

When retrieving listeners, Provider walks the class hierarchy using reflection. So if you register a listener for a base `Event` class, it fires for all events that extend it. A listener registered for `UserRegistered` fires only for that specific event.

The `listenerPipeline()` method builds a Flow Pipeline that chains all matching listeners together, with propagation checks between each one. It also deduplicates — if the same listener is registered for both a parent and child class, it only runs once.

## Dispatcher — the orchestrator

The Dispatcher ties it together. When you call `dispatch($event)`:

1. **Wrap** — if the event isn't already an `Event` subclass, it gets wrapped in an `UnknownEvent`. This means you can dispatch *any* object, not just Echo events.
2. **Execute** — builds a listener pipeline from the Provider and sends the event through it. If propagation is stopped (which causes `Flow\Interrupted`), it catches the exception gracefully.
3. **Unwrap** — if the event was wrapped, extract the original object and return it. PSR-14 requires returning the original event.

## UnknownEvent — dispatching anything

You don't have to extend `Event` to dispatch something:

```php
$dispatcher->dispatch(new \stdClass());
```

Echo wraps it in an `UnknownEvent` internally, so your listeners can work with it. The original object is accessible via `$event->payload`, and `$event->name` holds its class name. After dispatch, the original object is unwrapped and returned.

## How it connects to Flow

Echo uses Flow's Pipeline under the hood. Each listener becomes a stage in the pipeline, with a propagation check after each one. When `stopPropagation()` is called, the check stage returns null, which causes Pipeline to throw `Interrupted` — and the Dispatcher catches that to end the chain cleanly.

## The hierarchy at a glance

```
Dispatcher (EventDispatcherInterface)
\-- Provider (ListenerProviderInterface)
    |-- Stores listeners by event class name
    |-- Walks class hierarchy via reflection
    \-- Builds Flow Pipeline for execution

Event (abstract, StoppableEventInterface)
\-- UnknownEvent (wraps arbitrary objects)
```
