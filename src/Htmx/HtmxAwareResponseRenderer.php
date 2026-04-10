<?php

declare(strict_types=1);

namespace Arcanum\Htmx;

use Arcanum\Hourglass\Stopwatch;
use Arcanum\Hyper\ResponseRenderer;
use Arcanum\Hyper\StatusCode;
use Arcanum\Parchment\Reader;
use Arcanum\Shodo\Formatters\HtmlFormatter;
use Arcanum\Shodo\TemplateEngine;
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
 * Mode 3 checks for explicit {{ fragment 'id' }} markers first. When
 * present, renders the inner content only (no wrapper element) — the
 * opt-in for innerHTML/afterbegin/beforeend swap modes. When absent,
 * falls back to element-by-id extraction (outerHTML).
 *
 * Falls back to content section when HX-Target is absent or the id
 * isn't found in the template.
 */
class HtmxAwareResponseRenderer extends ResponseRenderer
{
    private ?HtmxRequest $htmxRequest = null;

    public function __construct(
        private readonly HtmlFormatter $formatter,
        private readonly TemplateEngine $engine,
        private readonly Reader $reader = new Reader(),
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

    public function render(
        mixed $data,
        string $dtoClass = '',
        StatusCode $status = StatusCode::OK,
    ): ResponseInterface {
        Stopwatch::tap('render.start');

        try {
            $html = $this->renderHtml($data, $dtoClass, $status->value);
            return $this->buildResponse($html, 'text/html; charset=UTF-8', $status);
        } finally {
            Stopwatch::tap('render.complete');
        }
    }

    private function renderHtml(mixed $data, string $dtoClass, int $statusCode): string
    {
        // Mode 1: Non-htmx — full render with layout.
        if ($this->htmxRequest === null || !$this->htmxRequest->isHtmx()) {
            return $this->formatter->format($data, $dtoClass, $statusCode);
        }

        $type = $this->htmxRequest->type();
        $target = $this->htmxRequest->target();

        // Mode 3: Partial with a target id — check for explicit fragment
        // markers first, then fall back to element-by-id extraction.
        if ($type === HtmxRequestType::Partial && $target !== null) {
            return $this->renderPartial($target, $data, $dtoClass);
        }

        // Mode 2: Full htmx (boosted nav) or partial without target —
        // content section only, no layout.
        $templatePath = $this->formatter->resolveTemplate($dtoClass);
        if ($templatePath === null) {
            return $this->formatter->format($data, $dtoClass, $statusCode);
        }

        $variables = $this->formatter->buildVariables($data, $dtoClass);
        return $this->engine->renderFragment($templatePath, $variables);
    }

    /**
     * Render a partial response for an htmx request with HX-Target.
     *
     * Checks for explicit {{ fragment 'id' }} markers in the raw template
     * source. When found, renders the inner content only (innerHTML).
     * When not found, delegates to element-by-id extraction (outerHTML).
     */
    private function renderPartial(string $target, mixed $data, string $dtoClass): string
    {
        $templatePath = $this->formatter->resolveTemplate($dtoClass);

        if ($templatePath === null) {
            return $this->formatter->format($data, $dtoClass);
        }

        $variables = $this->formatter->buildVariables($data, $dtoClass);

        $source = $this->reader->read($templatePath);
        $fragment = FragmentDirective::extractFragment($source, $target);

        if ($fragment !== null) {
            return $this->engine->renderSource(
                $fragment,
                dirname($templatePath),
                $variables,
            );
        }

        return $this->engine->renderElement($templatePath, $target, $variables);
    }
}
