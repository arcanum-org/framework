<?php

declare(strict_types=1);

namespace Arcanum\Ignition;

use Arcanum\Cabinet\Application;
use Arcanum\Glitch\ExceptionHandler;
use Arcanum\Glitch\ExceptionRenderer;
use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\CallableHandler;
use Arcanum\Hyper\HttpMiddleware;
use Arcanum\Hyper\StatusCode;
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
        Bootstrap\Environment::class,
        Bootstrap\Configuration::class,
        Bootstrap\Routing::class,
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

        foreach ($this->bootstrappers as $name) {
            /** @var Bootstrapper $bootstrapper */
            $bootstrapper = $container->get($name);
            $bootstrapper->bootstrap($container);
        }

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
     * Exception handling is inside the core handler so that error responses
     * flow back through the middleware stack — every response (success or
     * error) passes through all middleware layers.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $request = $this->prepareRequest($request);
        } catch (\Throwable $e) {
            return $this->sendThroughMiddleware(
                $request,
                new CallableHandler(fn() => $this->handleException($e)),
            );
        }

        $core = new CallableHandler(function (ServerRequestInterface $r): ResponseInterface {
            try {
                return $this->handleRequest($r);
            } catch (\Throwable $e) {
                return $this->handleException($e);
            }
        });

        return $this->sendThroughMiddleware($request, $core);
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
     */
    protected function handleException(\Throwable $e): ResponseInterface
    {
        if ($this->container->has(ExceptionHandler::class)) {
            /** @var ExceptionHandler $handler */
            $handler = $this->container->get(ExceptionHandler::class);
            $handler->handleException($e);
        }

        if ($this->container->has(ExceptionRenderer::class)) {
            /** @var ExceptionRenderer $renderer */
            $renderer = $this->container->get(ExceptionRenderer::class);
            return $renderer->render($e);
        }

        throw $e;
    }

    /**
     * Terminate the application.
     */
    public function terminate(): void
    {
    }
}
