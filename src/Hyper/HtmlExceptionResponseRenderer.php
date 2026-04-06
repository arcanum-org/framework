<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

use Arcanum\Flow\River\LazyResource;
use Arcanum\Flow\River\Stream;
use Arcanum\Glitch\ArcanumException;
use Arcanum\Glitch\ExceptionRenderer;
use Arcanum\Glitch\HttpException;
use Arcanum\Parchment\Reader;
use Arcanum\Shodo\TemplateCompiler;
use Psr\Http\Message\ResponseInterface;

/**
 * Renders exceptions as styled HTML error pages.
 *
 * Self-contained inline styles following DESIGN.md — no external CSS
 * dependency, so error pages work even when the app's assets are broken.
 *
 * Production mode: status code, title, message, navigation links.
 * Debug mode adds: exception class, file:line, stack trace.
 * Verbose errors adds: suggestion hint from ArcanumException.
 *
 * App override: when errorTemplatesDirectory is configured, checks for
 * a {code}.html template (e.g., errors/404.html) before rendering the
 * built-in page. Templates receive $code, $title, $message, $suggestion.
 */
class HtmlExceptionResponseRenderer implements ExceptionRenderer
{
    public function __construct(
        private readonly bool $debug = false,
        private readonly bool $verboseErrors = false,
        private readonly string $errorTemplatesDirectory = '',
        private readonly ?TemplateCompiler $compiler = null,
        private readonly Reader $reader = new Reader(),
    ) {
    }

    public function render(\Throwable $e): ResponseInterface
    {
        $status = $e instanceof HttpException
            ? $e->getStatusCode()
            : StatusCode::InternalServerError;

        $title = $e instanceof ArcanumException
            ? $e->getTitle()
            : $status->reason()->value;

        $suggestion = $e instanceof ArcanumException
            ? $e->getSuggestion()
            : null;

        $html = $this->renderAppTemplate($status, $title, $e, $suggestion)
            ?? $this->buildHtml($status, $title, $e, $suggestion);

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
     * Try to render an app-provided error template.
     *
     * Looks for {errorTemplatesDirectory}/{code}.html. Returns null if
     * no template exists or the directory isn't configured.
     */
    private function renderAppTemplate(
        StatusCode $status,
        string $title,
        \Throwable $e,
        ?string $suggestion,
    ): ?string {
        if ($this->errorTemplatesDirectory === '' || $this->compiler === null) {
            return null;
        }

        $templatePath = $this->errorTemplatesDirectory
            . DIRECTORY_SEPARATOR . $status->value . '.html';

        if (!is_file($templatePath)) {
            return null;
        }

        $source = $this->reader->read($templatePath);
        $compiled = $this->compiler->compile(
            $source,
            $this->errorTemplatesDirectory,
        );

        $rawMessage = $e->getMessage();
        if ($rawMessage === $status->reason()->value) {
            $rawMessage = $this->defaultDescription($status);
        }

        $variables = [
            'code' => $status->value,
            'title' => $title,
            'message' => $rawMessage,
            'suggestion' => $this->verboseErrors ? $suggestion : null,
            '__escape' => static fn(string $v): string =>
                htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'),
            '__helpers' => [],
        ];

        if ($this->debug) {
            $variables['exception'] = get_class($e);
            $variables['file'] = $e->getFile();
            $variables['line'] = $e->getLine();
            $variables['trace'] = $e->getTraceAsString();
        }

        $executor = static function (string $php, array $vars): string {
            extract($vars);
            ob_start();
            eval('?>' . $php);
            return (string) ob_get_clean();
        };

        return $executor($compiled, $variables);
    }

    private function buildHtml(
        StatusCode $status,
        string $title,
        \Throwable $e,
        ?string $suggestion,
    ): string {
        $code = $status->value;
        $rawMessage = $e->getMessage();

        // When the exception message is just the reason phrase (no custom
        // message was provided), use a friendlier default description.
        if ($rawMessage === $status->reason()->value) {
            $rawMessage = $this->defaultDescription($status);
        }

        $message = $this->escape($rawMessage);
        $escapedTitle = $this->escape($title);

        $suggestionBlock = '';
        if ($this->verboseErrors && $suggestion !== null) {
            $escaped = $this->escape($suggestion);
            $suggestionBlock = "<p class=\"suggestion\">{$escaped}</p>";
        }

        $debugBlock = '';
        if ($this->debug) {
            $class = $this->escape(get_class($e));
            $file = $this->escape($e->getFile());
            $line = $e->getLine();
            $trace = $this->escape($e->getTraceAsString());

            $debugBlock = <<<HTML
            <div class="debug">
                <p class="debug-class">{$class}</p>
                <p class="debug-file">{$file}:{$line}</p>
                <details>
                    <summary>Stack trace</summary>
                    <pre>{$trace}</pre>
                </details>
            </div>
            HTML;
        }

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$code} {$escapedTitle}</title>
            {$this->fonts()}
            {$this->css()}
        </head>
        <body>
            <div class="container">
                <p class="status-code">{$code}</p>
                <h1>{$escapedTitle}</h1>
                <p class="message">{$message}</p>
                {$suggestionBlock}
                {$debugBlock}
                <div class="actions">
                    <a href="javascript:history.back()">Go back</a>
                    <a href="/">Go home</a>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    private function fonts(): string
    {
        // phpcs:disable Generic.Files.LineLength.TooLong
        $families = 'family=Inter:wght@400;500&family=JetBrains+Mono:wght@400&family=Lora:wght@600';
        // phpcs:enable Generic.Files.LineLength.TooLong

        return <<<HTML
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?{$families}&display=swap" rel="stylesheet">
        HTML;
    }

    private function css(): string
    {
        // phpcs:disable Generic.Files.LineLength.TooLong
        $heading = "Lora,Georgia,'Times New Roman',serif";
        $body = "Inter,system-ui,-apple-system,'Segoe UI',sans-serif";
        $mono = "'JetBrains Mono','Fira Code','Source Code Pro',Consolas,monospace";

        return <<<CSS
        <style>
            body {
                margin:0; padding:0; min-height:100vh;
                display:flex; align-items:center; justify-content:center;
                background:#faf8f1;
                font-family:{$body}; color:#2c2a25;
            }
            .container { max-width:480px; width:100%; padding:24px; }
            .status-code {
                margin:0 0 8px; font-family:{$heading};
                font-size:48px; font-weight:600;
                line-height:1.10; letter-spacing:-0.5px; color:#b5623f;
            }
            h1 {
                margin:0 0 8px; font-family:{$heading};
                font-size:28px; font-weight:600; line-height:1.20; color:#2c2a25;
            }
            .message { margin:0; font-size:16px; line-height:1.65; color:#6b675e; }
            .suggestion {
                margin:16px 0 0; padding:14px 18px;
                border-left:3px solid #4a6fa5; background:rgba(74,111,165,0.08);
                border-radius:6px; color:#4a6fa5; font-size:14px; line-height:1.55;
            }
            .debug {
                margin-top:32px; padding-top:24px; border-top:1px solid #ddd9ce;
            }
            .debug-class {
                margin:0 0 8px; font-family:{$mono};
                font-size:14px; color:#3d3a34;
            }
            .debug-file {
                margin:0 0 16px; font-family:{$mono};
                font-size:13px; color:#6b675e;
            }
            details summary {
                cursor:pointer; font-family:{$body};
                font-size:14px; font-weight:500; color:#6b675e; margin-bottom:8px;
            }
            details pre {
                margin:0; padding:16px 20px; background:#eae6da;
                border:1px solid #ddd9ce; border-radius:6px; overflow-x:auto;
                font-family:{$mono}; font-size:13px; line-height:1.55; color:#3d3a34;
            }
            .actions { margin-top:32px; display:flex; gap:12px; }
            .actions a {
                display:inline-block; padding:10px 20px; border-radius:6px;
                border:1px solid #c4bfb3; background:transparent; color:#b5623f;
                font-family:{$body}; font-size:15px; font-weight:500;
                text-decoration:none;
            }
            .actions a:hover { background:#ece9e0; }
            @media (prefers-color-scheme: dark) {
                body { background:#1a1915; color:#e8e4db; }
                .status-code { color:#c8795a; }
                h1 { color:#e8e4db; }
                .message { color:#9c9789; }
                .suggestion {
                    border-color:#6a8fc0; color:#6a8fc0;
                    background:rgba(106,143,192,0.1);
                }
                .debug { border-color:#3d3a34; }
                .debug-class { color:#c4bfb3; }
                .debug-file { color:#9c9789; }
                details summary { color:#9c9789; }
                details pre {
                    background:#2d2b24; border-color:#3d3a34; color:#c4bfb3;
                }
                .actions a {
                    border-color:#3d3a34; color:#c8795a;
                }
                .actions a:hover { background:#2d2b24; }
            }
        </style>
        CSS;
        // phpcs:enable Generic.Files.LineLength.TooLong
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
