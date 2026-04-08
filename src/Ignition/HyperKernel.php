<?php

declare(strict_types=1);

namespace Arcanum\Ignition;

use Arcanum\Cabinet\Application;
use Arcanum\Glitch\ExceptionHandler;
use Arcanum\Glitch\ExceptionRenderer;
use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\CallableHandler;
use Arcanum\Hyper\Event\RequestFailed;
use Arcanum\Hyper\Event\RequestHandled;
use Arcanum\Hyper\Event\RequestReceived;
use Arcanum\Hyper\Event\ResponseSent;
use Arcanum\Hourglass\Stopwatch;
use Arcanum\Hyper\HttpMiddleware;
use Arcanum\Hyper\StatusCode;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A HyperKernel is the initial entry point for an HTTP application.
 */
class HyperKernel implements Kernel, RequestHandlerInterface
{
    /**
     * Whether the application has been bootstrapped yet.
     */
    private bool $isBootstrapped = false;

    /**
     * Global HTTP middleware class names, resolved from the container at dispatch time.
     *
     * @var list<class-string<MiddlewareInterface>>
     */
    private array $middleware = [];

    /**
     * Stored for terminate() to dispatch ResponseSent.
     */
    private ?ServerRequestInterface $lastRequest = null;
    private ?ResponseInterface $lastResponse = null;

    /**
     * The application container, set during bootstrap.
     */
    protected Application $container;

    /**
     * Environment variables that must be set for the application to run.
     * Override this in your app's Kernel to enforce required env vars.
     *
     * @var string[]
     */
    protected array $requiredEnvironmentVariables = [];

    /**
     * The bootstrappers to run before handling a request.
     *
     * @var class-string<Bootstrapper>[]
     */
    protected array $bootstrappers = [
        Bootstrap\Hourglass::class,
        Bootstrap\Environment::class,
        Bootstrap\Configuration::class,
        Bootstrap\Security::class,
        Bootstrap\Cache::class,
        Bootstrap\Database::class,
        Bootstrap\Sessions::class,
        Bootstrap\Auth::class,
        Bootstrap\Routing::class,
        Bootstrap\Helpers::class,
        Bootstrap\Formats::class,
        Bootstrap\RouteMiddleware::class,
        Bootstrap\Logger::class,
        Bootstrap\Exceptions::class,
        Bootstrap\Middleware::class,
    ];

    public function __construct(
        private string $rootDirectory,
        private string $configDirectory = '',
        private string $filesDirectory = '',
    ) {
        // Trim trailing slashes from the root directory.
        $this->rootDirectory = $rootDirectory = rtrim($rootDirectory, DIRECTORY_SEPARATOR);

        // Set the config and files directories if they are not set.
        if ($configDirectory === '') {
            $this->configDirectory = $rootDirectory . DIRECTORY_SEPARATOR . 'config';
        }
        if ($filesDirectory === '') {
            $this->filesDirectory = $rootDirectory . DIRECTORY_SEPARATOR . 'files';
        }
    }

    /**
     * Get the list of required environment variables.
     *
     * @return string[]
     */
    public function requiredEnvironmentVariables(): array
    {
        return $this->requiredEnvironmentVariables;
    }

    /**
     * Get the root directory of the application.
     */
    public function rootDirectory(): string
    {
        return $this->rootDirectory;
    }

    /**
     * Get the configuration directory of the application.
     */
    public function configDirectory(): string
    {
        return $this->configDirectory;
    }

    /**
     * Get the files directory of the application.
     */
    public function filesDirectory(): string
    {
        return $this->filesDirectory;
    }

    /**
     * Bootstrap the application.
     */
    public function bootstrap(Application $container): void
    {
        if ($this->isBootstrapped) {
            return;
        }

        $this->container = $container;
        $container->instance(Transport::class, Transport::Http);

        foreach ($this->bootstrappers as $name) {
            try {
                /** @var Bootstrapper $bootstrapper */
                $bootstrapper = $container->get($name);
                $bootstrapper->bootstrap($container);
            } catch (\Throwable $e) {
                throw new \RuntimeException(sprintf(
                    'Failed during %s bootstrap: %s. Bootstrappers may be in the wrong order.',
                    $name,
                    $e->getMessage(),
                ), 0, $e);
            }
        }

        Stopwatch::tap('boot.complete');

        $this->isBootstrapped = true;
    }

    /**
     * Register a global HTTP middleware class.
     *
     * Middleware is executed in the order it is registered — the first
     * middleware registered is the outermost layer of the onion.
     *
     * @param class-string<MiddlewareInterface> $middlewareClass
     */
    public function middleware(string $middlewareClass): void
    {
        $this->middleware[] = $middlewareClass;
    }

    /**
     * Handle an incoming HTTP request.
     *
     * Exception handling has two layers:
     *
     * 1. Inside the core handler — handler exceptions are caught here so
     *    that the rendered error response flows back through the middleware
     *    stack (so middleware like CORS still runs on error responses).
     *
     * 2. Around sendThroughMiddleware — middleware-thrown exceptions are
     *    caught here. The middleware that threw has already half-executed,
     *    so the error response cannot be fed back through it; instead we
     *    render the exception directly. Without this catch, an exception
     *    from middleware would escape the kernel entirely, leaving the
     *    client with whatever default response PHP emits (usually nothing).
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $request = $this->prepareRequest($request);
            // Dispatch RequestReceived — listeners can mutate the request.
            $request = $this->dispatchRequestReceived($request);
        } catch (\Throwable $e) {
            $this->lastRequest = $request;
            $response = $this->sendThroughMiddleware(
                $request,
                new CallableHandler(fn() => $this->handleException($e)),
            );
            $this->lastResponse = $response;
            return $response;
        }

        $this->lastRequest = $request;

        $core = new CallableHandler(function (ServerRequestInterface $r): ResponseInterface {
            try {
                return $this->handleRequest($r);
            } catch (\Throwable $e) {
                $this->dispatchRequestFailed($r, $e);
                return $this->handleException($e, $r);
            }
        });

        try {
            $response = $this->sendThroughMiddleware($request, $core);
        } catch (\Throwable $e) {
            // A middleware threw before reaching the core handler. The
            // partially-executed stack can't be replayed, so render the
            // exception directly into a response.
            $this->dispatchRequestFailed($request, $e);
            $response = $this->handleException($e, $request);
        }

        // Dispatch RequestHandled — read-only observation.
        // Listener failures must not destroy a successful response.
        try {
            $this->dispatchRequestHandled($request, $response);
        } catch (\Throwable $e) {
            $this->reportException($e);
        }

        $this->lastResponse = $response;

        return $response;
    }

    /**
     * Send a request through the middleware stack to a core handler.
     *
     * If no middleware is registered, delegates directly to the handler.
     */
    private function sendThroughMiddleware(
        ServerRequestInterface $request,
        CallableHandler $core,
    ): ResponseInterface {
        if ($this->middleware === []) {
            return $core->handle($request);
        }

        $stack = new HttpMiddleware($core, $this->container);

        foreach ($this->middleware as $middlewareClass) {
            $stack->add($middlewareClass);
        }

        return $stack->handle($request);
    }

    /**
     * Prepare the request before it reaches the application handler.
     *
     * Parses JSON request bodies into parsedBody so that application
     * handlers receive structured data regardless of content type.
     */
    protected function prepareRequest(ServerRequestInterface $request): ServerRequestInterface
    {
        $contentType = $request->getHeaderLine('Content-Type');

        if (str_contains($contentType, 'application/json')) {
            $body = (string) $request->getBody();
            if ($body !== '') {
                $decoded = json_decode($body, true);
                if (!is_array($decoded)) {
                    throw new HttpException(StatusCode::BadRequest, 'Malformed JSON body.');
                }
                $request = $request->withParsedBody($decoded);
            }
        }

        return $request;
    }

    /**
     * Handle the request through the application.
     *
     * Override this method in your application kernel to dispatch
     * the request to your router or command bus.
     */
    protected function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        throw new HttpException(StatusCode::NotFound);
    }

    /**
     * Handle an exception that occurred during request handling.
     *
     * Reports the exception via the ExceptionHandler, then renders
     * it into a ResponseInterface via the ExceptionRenderer.
     *
     * Format selection mirrors the success path: the URL extension
     * picks the format, and when there is no extension we fall back
     * to the configured `formats.default`. HTML requests get the
     * HtmlExceptionResponseRenderer if registered; all others use
     * the default ExceptionRenderer.
     */
    protected function handleException(
        \Throwable $e,
        ?ServerRequestInterface $request = null,
    ): ResponseInterface {
        if ($this->container->has(ExceptionHandler::class)) {
            /** @var ExceptionHandler $handler */
            $handler = $this->container->get(ExceptionHandler::class);
            $handler->handleException($e);
        }

        // Use format-specific renderer when the request indicates HTML.
        if ($request !== null) {
            $extension = $this->resolveResponseFormat($request);

            if (
                $extension === 'html'
                && $this->container->has(\Arcanum\Hyper\HtmlExceptionResponseRenderer::class)
            ) {
                /** @var ExceptionRenderer $htmlRenderer */
                $htmlRenderer = $this->container->get(
                    \Arcanum\Hyper\HtmlExceptionResponseRenderer::class,
                );
                return $htmlRenderer->render($e);
            }
        }

        if ($this->container->has(ExceptionRenderer::class)) {
            /** @var ExceptionRenderer $renderer */
            $renderer = $this->container->get(ExceptionRenderer::class);
            return $renderer->render($e);
        }

        throw $e;
    }

    /**
     * Resolve the response format for a request.
     *
     * Uses the URL extension when present, otherwise falls back to the
     * configured `formats.default`. Returns an empty string if neither
     * is available. This method must never throw — it is called from
     * the exception handling path, and a failure here would mask the
     * original exception.
     */
    private function resolveResponseFormat(ServerRequestInterface $request): string
    {
        try {
            $path = $request->getUri()->getPath();
            $extension = pathinfo($path, PATHINFO_EXTENSION);

            if ($extension !== '') {
                return $extension;
            }

            if ($this->container->has(\Arcanum\Gather\Configuration::class)) {
                /** @var \Arcanum\Gather\Configuration $config */
                $config = $this->container->get(\Arcanum\Gather\Configuration::class);
                $default = $config->get('formats.default');
                if (is_string($default)) {
                    return $default;
                }
            }
        } catch (\Throwable) {
            // Format resolution is best-effort; fall through to default.
        }

        return '';
    }

    /**
     * Report an exception without rendering a response.
     *
     * Used for non-fatal errors (e.g., listener failures) where the
     * request should still complete successfully.
     */
    private function reportException(\Throwable $e): void
    {
        if ($this->container->has(ExceptionHandler::class)) {
            /** @var ExceptionHandler $handler */
            $handler = $this->container->get(ExceptionHandler::class);
            $handler->handleException($e);
        }
    }

    // ------------------------------------------------------------------
    // Lifecycle event dispatching
    // ------------------------------------------------------------------

    /**
     * Dispatch RequestReceived and return the (possibly mutated) request.
     */
    private function dispatchRequestReceived(
        ServerRequestInterface $request,
    ): ServerRequestInterface {
        Stopwatch::tap('request.received');
        $event = new RequestReceived($request);
        $this->dispatchEvent($event);

        return $event->getRequest();
    }

    /**
     * Dispatch RequestHandled for read-only observation.
     */
    private function dispatchRequestHandled(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): void {
        Stopwatch::tap('request.handled');
        $this->dispatchEvent(new RequestHandled($request, $response));
    }

    /**
     * Dispatch RequestFailed for observational reporting.
     */
    private function dispatchRequestFailed(
        ServerRequestInterface $request,
        \Throwable $exception,
    ): void {
        $this->dispatchEvent(new RequestFailed($request, $exception));
    }

    /**
     * Dispatch an event if an EventDispatcher is available.
     */
    private function dispatchEvent(object $event): void
    {
        if ($this->container->has(EventDispatcherInterface::class)) {
            /** @var EventDispatcherInterface $dispatcher */
            $dispatcher = $this->container->get(EventDispatcherInterface::class);
            $dispatcher->dispatch($event);
        }
    }

    /**
     * Terminate the application after the response has been sent.
     *
     * Calls fastcgi_finish_request() if available to release the client
     * connection, then dispatches ResponseSent for post-response work
     * (deferred logging, metrics, cleanup).
     *
     * Call this from public/index.php after sending the response.
     */
    public function terminate(): void
    {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        if ($this->lastRequest !== null && $this->lastResponse !== null) {
            Stopwatch::tap('response.sent');
            $this->dispatchEvent(
                new ResponseSent($this->lastRequest, $this->lastResponse),
            );
        }

        Stopwatch::tap('arcanum.complete');
    }
}
