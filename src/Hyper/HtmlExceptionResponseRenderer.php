<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

use Arcanum\Flow\River\LazyResource;
use Arcanum\Flow\River\Stream;
use Arcanum\Glitch\ArcanumException;
use Arcanum\Glitch\ExceptionRenderer;
use Arcanum\Glitch\HttpException;
use Arcanum\Shodo\HelperResolver;
use Arcanum\Shodo\TemplateEngine;
use Arcanum\Shodo\TemplateResolver;
use Psr\Http\Message\ResponseInterface;

/**
 * Renders exceptions as styled HTML error pages.
 *
 * Uses the same TemplateEngine as the success rendering path. When an
 * app-provided error template exists (co-located or app-wide), it's
 * rendered through the engine with error-specific variables. When no
 * template exists, falls back to a self-contained built-in error page.
 *
 * Production mode: status code, title, message, navigation links.
 * Debug mode adds: exception class, file:line, stack trace.
 * Verbose errors adds: suggestion hint from ArcanumException.
 */
class HtmlExceptionResponseRenderer implements ExceptionRenderer
{
    private string $dtoClass = '';
    private bool $isHtmxRequest = false;

    public function __construct(
        private readonly bool $debug = false,
        private readonly bool $verboseErrors = false,
        private readonly ?TemplateEngine $engine = null,
        private readonly ?TemplateResolver $templateResolver = null,
        private readonly ?HelperResolver $helpers = null,
    ) {
    }

    /**
     * Set the DTO class context for co-located error template resolution.
     *
     * Called by the kernel or middleware when the route is resolved.
     * When not set, only app-wide error templates are checked.
     */
    public function setDtoClass(string $dtoClass): void
    {
        $this->dtoClass = $dtoClass;
    }

    /**
     * Mark the current request as htmx-initiated.
     *
     * When true and no app-provided error template exists, renders a
     * minimal error fragment instead of the full styled error page.
     */
    public function setIsHtmxRequest(bool $isHtmx): void
    {
        $this->isHtmxRequest = $isHtmx;
    }

    public function render(\Throwable $e): ResponseInterface
    {
        $status = match (true) {
            $e instanceof HttpException => $e->getStatusCode(),
            $e instanceof \Arcanum\Validation\ValidationException => StatusCode::UnprocessableEntity,
            default => StatusCode::InternalServerError,
        };

        $title = $e instanceof ArcanumException
            ? $e->getTitle()
            : $status->reason()->value;

        $suggestion = $e instanceof ArcanumException
            ? $e->getSuggestion()
            : null;

        $html = $this->renderAppTemplate($status, $title, $e, $suggestion)
            ?? ($this->isHtmxRequest
                ? $this->buildHtmxFragment($status, $e)
                : $this->buildHtml($status, $title, $e, $suggestion));

        $body = new Stream(LazyResource::for('php://memory', 'w+'));
        $body->write($html);

        return new Response(
            new Message(
                new Headers([
                    'Content-Type' => ['text/html; charset=UTF-8'],
                    'Content-Length' => [(string) strlen($html)],
                ]),
                $body,
                Version::v11,
            ),
            $status,
        );
    }

    /**
     * Try to render an app-provided error template via the TemplateEngine.
     *
     * Resolution order (via TemplateResolver::resolveForStatus):
     *   1. Co-located: {DtoClass}.{status}.html (when dtoClass is set)
     *   2. App-wide:   {errorTemplatesDirectory}/{status}.html
     *   3. null (falls through to built-in error page)
     */
    private function renderAppTemplate(
        StatusCode $status,
        string $title,
        \Throwable $e,
        ?string $suggestion,
    ): ?string {
        if ($this->engine === null || $this->templateResolver === null) {
            return null;
        }

        $templatePath = $this->templateResolver->resolveForStatus(
            $this->dtoClass,
            $status->value,
            'html',
        );

        if ($templatePath === null) {
            return null;
        }

        $variables = $this->buildErrorVariables($status, $title, $e, $suggestion);
        return $this->engine->render($templatePath, $variables);
    }

    /**
     * Build the template variable array for error templates.
     *
     * @return array<string, mixed>
     */
    private function buildErrorVariables(
        StatusCode $status,
        string $title,
        \Throwable $e,
        ?string $suggestion,
    ): array {
        $rawMessage = $e->getMessage();
        if ($rawMessage === $status->reason()->value) {
            $rawMessage = $this->defaultDescription($status);
        }

        $variables = [
            'code' => $status->value,
            'title' => $title,
            'message' => $rawMessage,
            'suggestion' => $this->verboseErrors ? $suggestion : null,
            'errors' => $e instanceof \Arcanum\Validation\ValidationException
                ? $e->errorsByField() : [],
            '__escape' => static fn(string $v): string =>
                htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'),
            '__helpers' => $this->helpers !== null
                ? $this->helpers->for($this->dtoClass) : [],
        ];

        if ($this->debug) {
            $variables['exception'] = get_class($e);
            $variables['file'] = $e->getFile();
            $variables['line'] = $e->getLine();
            $variables['trace'] = $e->getTraceAsString();
        }

        return $variables;
    }

    /**
     * Build a minimal error fragment for htmx requests.
     *
     * For 422 (validation): an unstyled <ul> of field error messages.
     * For other errors: a single <p> with the error message.
     * No layout, no styles — htmx will swap this into the existing page.
     */
    private function buildHtmxFragment(StatusCode $status, \Throwable $e): string
    {
        if (
            $status === StatusCode::UnprocessableEntity
            && $e instanceof \Arcanum\Validation\ValidationException
        ) {
            $items = '';
            foreach ($e->errorsByField() as $field => $messages) {
                foreach ($messages as $message) {
                    $items .= '<li>' . $this->escape($field) . ': '
                        . $this->escape($message) . '</li>';
                }
            }
            return '<ul>' . $items . '</ul>';
        }

        $message = $e->getMessage();
        if ($message === $status->reason()->value) {
            $message = $this->defaultDescription($status);
        }

        return '<p>' . $this->escape($message) . '</p>';
    }

    /**
     * Render the built-in fallback error page.
     *
     * Uses a pre-compiled PHP template via extract() + require, deliberately
     * bypassing the Shodo TemplateEngine. The error page must render even
     * when the template engine, cache, or configuration is broken.
     */
    private function buildHtml(
        StatusCode $status,
        string $title,
        \Throwable $e,
        ?string $suggestion,
    ): string {
        $rawMessage = $e->getMessage();
        if ($rawMessage === $status->reason()->value) {
            $rawMessage = $this->defaultDescription($status);
        }

        $variables = [
            'code' => $status->value,
            'title' => $this->escape($title),
            'message' => $this->escape($rawMessage),
            'suggestion' => ($this->verboseErrors && $suggestion !== null)
                ? $this->escape($suggestion)
                : null,
            'exception' => $this->debug ? $this->escape(get_class($e)) : null,
            'file' => $this->debug ? $this->escape($e->getFile()) : null,
            'line' => $this->debug ? $e->getLine() : null,
            'trace' => $this->debug ? $this->escape($e->getTraceAsString()) : null,
        ];

        return (static function (string $__template, array $__variables): string {
            extract($__variables);
            ob_start();
            require $__template;
            return (string) ob_get_clean();
        })(__DIR__ . '/Templates/compiled-error.php', $variables);
    }

    /**
     * Human-friendly default descriptions for common HTTP error codes.
     *
     * Used when no custom exception message was provided. Each description
     * explains what happened in plain language and hints at what to do.
     */
    private function defaultDescription(StatusCode $status): string
    {
        return match ($status) {
            StatusCode::BadRequest
                => "The request couldn't be understood — check the"
                    . " data you sent and try again",
            StatusCode::Unauthorized
                => 'You need to sign in to access this page',
            StatusCode::Forbidden
                => "You don't have permission to access this page",
            StatusCode::NotFound
                => "The page you're looking for doesn't exist —"
                    . " it may have been moved or removed",
            StatusCode::MethodNotAllowed
                => 'This URL exists, but not for that HTTP method',
            StatusCode::NotAcceptable
                => "The requested format isn't available for this"
                    . ' resource',
            StatusCode::RequestTimeout
                => 'The server waited too long for the request'
                    . ' — try again',
            StatusCode::Conflict
                => 'The request conflicts with the current state'
                    . ' of the resource',
            StatusCode::Gone
                => 'This resource has been permanently removed',
            StatusCode::UnprocessableEntity
                => "The data you submitted couldn't be processed"
                    . ' — check for errors and try again',
            StatusCode::TooManyRequests
                => "You've made too many requests — slow down and"
                    . ' try again shortly',
            StatusCode::InternalServerError
                => 'Something went wrong on our end — this has'
                    . ' been noted and will be looked into',
            StatusCode::ServiceUnavailable
                => "The service is temporarily unavailable —"
                    . ' try again in a moment',
            default => $status->reason()->value,
        };
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
