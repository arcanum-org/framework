<?php

declare(strict_types=1);

namespace Arcanum\Htmx;

use Psr\Http\Message\ResponseInterface;

/**
 * Immutable builder for htmx response headers.
 *
 * Framework-internal — app code never constructs this directly. Middleware
 * uses it to compose the htmx-specific response headers that the browser's
 * htmx runtime reads after a swap.
 *
 * Each with* method returns a new instance (immutable). Call toResponse()
 * at the end to apply all accumulated headers to the underlying PSR-7
 * response.
 *
 * Trigger methods merge into a single JSON header — multiple triggers
 * accumulate rather than overwrite.
 */
final class HtmxResponse
{
    /** @var array<string, mixed> */
    private array $triggers = [];

    /** @var array<string, string> */
    private array $headers = [];

    public function __construct(
        private readonly ResponseInterface $response,
    ) {
    }

    /**
     * Set HX-Location for client-side navigation without a full page reload.
     */
    public function withLocation(string|HtmxLocation $location): self
    {
        $value = $location instanceof HtmxLocation
            ? $location->toJson()
            : $location;

        return $this->with('HX-Location', $value);
    }

    /**
     * Push a URL into the browser history stack.
     */
    public function withPushUrl(string $url): self
    {
        return $this->with('HX-Push-Url', $url);
    }

    /**
     * Replace the current URL in the browser history.
     */
    public function withReplaceUrl(string $url): self
    {
        return $this->with('HX-Replace-Url', $url);
    }

    /**
     * Set HX-Redirect for a client-side redirect.
     */
    public function withRedirect(string $url): self
    {
        return $this->with('HX-Redirect', $url);
    }

    /**
     * Trigger a full page refresh on the client.
     */
    public function withRefresh(): self
    {
        return $this->with('HX-Refresh', 'true');
    }

    /**
     * Override the target element for the swap (CSS selector).
     */
    public function withRetarget(string $selector): self
    {
        return $this->with('HX-Retarget', $selector);
    }

    /**
     * Override the swap method (e.g. 'innerHTML', 'outerHTML').
     */
    public function withReswap(string $method): self
    {
        return $this->with('HX-Reswap', $method);
    }

    /**
     * Override the content selection from the response (CSS selector).
     */
    public function withReselect(string $selector): self
    {
        return $this->with('HX-Reselect', $selector);
    }

    /**
     * Add a Vary header value (appends, does not overwrite).
     */
    public function withVary(string $value): self
    {
        $clone = clone $this;
        // Vary is accumulated on the response directly to preserve
        // existing values — we don't want to overwrite framework-set Vary.
        $clone->headers['Vary'] = $value;
        return $clone;
    }

    /**
     * Add a trigger event (immediate timing — fires before the swap).
     *
     * @param array<string, mixed> $payload
     */
    public function withTrigger(string $eventName, array $payload = []): self
    {
        $clone = clone $this;
        $clone->triggers[$eventName] = $payload !== [] ? $payload : $eventName;
        return $clone;
    }

    /**
     * Apply all accumulated htmx headers to the underlying response.
     */
    public function toResponse(): ResponseInterface
    {
        $response = $this->response;

        foreach ($this->headers as $name => $value) {
            $response = $name === 'Vary'
                ? $response->withAddedHeader($name, $value)
                : $response->withHeader($name, $value);
        }

        if ($this->triggers !== []) {
            $response = $response->withHeader(
                'HX-Trigger',
                $this->encodeTriggers($this->triggers),
            );
        }

        return $response;
    }

    /**
     * Set a simple header value.
     */
    private function with(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    /**
     * Encode trigger events for an HX-Trigger header.
     *
     * When all triggers are signal-only (no payload), htmx accepts a
     * simple comma-separated list. When any trigger has a payload, the
     * header must be a JSON object mapping event names to their data.
     *
     * @param array<string, mixed> $triggers
     */
    private function encodeTriggers(array $triggers): string
    {
        // Check if all triggers are signal-only (value === event name string).
        $allSimple = true;
        foreach ($triggers as $name => $value) {
            if ($value !== $name) {
                $allSimple = false;
                break;
            }
        }

        if ($allSimple) {
            return implode(', ', array_keys($triggers));
        }

        $encoded = json_encode($triggers, JSON_UNESCAPED_SLASHES);
        assert($encoded !== false);

        return $encoded;
    }
}
