<?php

declare(strict_types=1);

namespace Arcanum\Htmx;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Read-side decorator over ServerRequestInterface for htmx headers.
 *
 * Provides typed accessors for every htmx request header. Handlers
 * that need to inspect htmx state receive this instead of the raw
 * PSR-7 request — but most handlers should never need it, since the
 * framework's rendering pipeline reads htmx headers automatically.
 */
final readonly class HtmxRequest
{
    public function __construct(
        private ServerRequestInterface $request,
    ) {
    }

    /**
     * Whether this is an htmx-initiated request (HX-Request header present).
     */
    public function isHtmx(): bool
    {
        return $this->request->hasHeader('HX-Request');
    }

    /**
     * Whether this request was triggered by an element with hx-boost.
     */
    public function isBoosted(): bool
    {
        return $this->header('HX-Boosted') === 'true';
    }

    /**
     * Whether this request is a history-restoration request.
     */
    public function isHistoryRestore(): bool
    {
        return $this->header('HX-History-Restore-Request') === 'true';
    }

    /**
     * The request type: Full or Partial.
     *
     * htmx v4 sends HX-Request-Type with values 'full' or 'partial'.
     * Falls back to Partial when the header is missing but HX-Request
     * is present (htmx v2 compat), or null for non-htmx requests.
     */
    public function type(): ?HtmxRequestType
    {
        if (!$this->isHtmx()) {
            return null;
        }

        $value = $this->header('HX-Request-Type');

        if ($value !== null) {
            return HtmxRequestType::tryFrom($value);
        }

        // htmx v2 doesn't send HX-Request-Type. Boosted requests are
        // full-page navigations; everything else is a partial swap.
        return $this->isBoosted() ? HtmxRequestType::Full : HtmxRequestType::Partial;
    }

    /**
     * The id of the target element, from HX-Target.
     *
     * htmx only sends this header when the resolved target element has
     * an id attribute. Returns null when the header is absent or empty.
     */
    public function target(): ?string
    {
        return $this->header('HX-Target');
    }

    /**
     * The swap mode requested by the client, from HX-Swap.
     *
     * Common values: 'innerHTML', 'outerHTML', 'beforeend', etc.
     * Returns null when the header is absent (client uses default).
     */
    public function swapMode(): ?string
    {
        return $this->header('HX-Swap');
    }

    /**
     * The id of the element that triggered the request, from HX-Trigger.
     */
    public function triggerId(): ?string
    {
        return $this->header('HX-Trigger');
    }

    /**
     * The name of the element that triggered the request, from HX-Trigger-Name.
     */
    public function triggerName(): ?string
    {
        return $this->header('HX-Trigger-Name');
    }

    /**
     * The URL the browser was on when the request was issued, from HX-Current-URL.
     */
    public function currentUrl(): ?string
    {
        return $this->header('HX-Current-URL');
    }

    /**
     * The user response to an hx-prompt, from HX-Prompt.
     */
    public function prompt(): ?string
    {
        return $this->header('HX-Prompt');
    }

    /**
     * The underlying PSR-7 request.
     */
    public function request(): ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * Read a single header value, or null when absent/empty.
     */
    private function header(string $name): ?string
    {
        $line = $this->request->getHeaderLine($name);

        return $line !== '' ? $line : null;
    }
}
