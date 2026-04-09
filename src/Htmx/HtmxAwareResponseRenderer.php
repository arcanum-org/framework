<?php

declare(strict_types=1);

namespace Arcanum\Htmx;

use Arcanum\Hourglass\Stopwatch;
use Arcanum\Hyper\ResponseRenderer;
use Arcanum\Shodo\Formatters\HtmlFormatter;
use Psr\Http\Message\ResponseInterface;

/**
 * htmx-aware HTML response renderer.
 *
 * Replaces the plain HtmlResponseRenderer when the htmx package is
 * bootstrapped. Reads the per-request HtmxRequest context (set by
 * HtmxRequestMiddleware) and picks the rendering shape:
 *
 *   1. Non-htmx request → full render with layout (delegates to formatter)
 *   2. htmx Full type   → content section only, no layout (fragment mode)
 *   3. htmx Partial type → auto-extracted element by id from HX-Target
 *
 * Mode 3 always returns the full element (outerHTML). The developer
 * controls the swap mode on their element with hx-swap — the framework
 * does not override it with HX-Reswap. Recommend outerHTML in docs.
 *
 * Falls back to content section when HX-Target is absent or the id
 * isn't found in the template.
 */
class HtmxAwareResponseRenderer extends ResponseRenderer
{
    private ?HtmxRequest $htmxRequest = null;

    public function __construct(
        private readonly HtmlFormatter $formatter,
    ) {
    }

    /**
     * Set the htmx request context for the current request.
     *
     * Called by HtmxRequestMiddleware before the handler runs. When
     * not set (non-htmx requests), the renderer delegates to the
     * formatter's normal full-render path.
     */
    public function setHtmxRequest(HtmxRequest $htmxRequest): void
    {
        $this->htmxRequest = $htmxRequest;
    }

    public function render(mixed $data, string $dtoClass = ''): ResponseInterface
    {
        Stopwatch::tap('render.start');

        try {
            $html = $this->renderHtml($data, $dtoClass);
            return $this->buildResponse($html, 'text/html; charset=UTF-8');
        } finally {
            Stopwatch::tap('render.complete');
        }
    }

    private function renderHtml(mixed $data, string $dtoClass): string
    {
        // Mode 1: Non-htmx — full render with layout.
        if ($this->htmxRequest === null || !$this->htmxRequest->isHtmx()) {
            return $this->formatter->format($data, $dtoClass);
        }

        $type = $this->htmxRequest->type();
        $target = $this->htmxRequest->target();

        // Mode 3: Partial with a target id — auto-extract the element.
        if ($type === HtmxRequestType::Partial && $target !== null) {
            return $this->formatter->renderElementById($target, $data, $dtoClass);
        }

        // Mode 2: Full htmx (boosted nav) or partial without target —
        // content section only, no layout.
        $this->formatter->setFragment(true);

        try {
            return $this->formatter->format($data, $dtoClass);
        } finally {
            $this->formatter->setFragment(false);
        }
    }
}
