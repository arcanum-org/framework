<?php

declare(strict_types=1);

namespace Arcanum\Ignition;

use Arcanum\Flow\River\LazyResource;
use Arcanum\Flow\River\Stream;
use Arcanum\Cabinet\Application;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
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
        Bootstrap\Logger::class,
        Bootstrap\Exceptions::class,
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

        foreach ($this->bootstrappers as $name) {
            /** @var Bootstrapper $bootstrapper */
            $bootstrapper = $container->get($name);
            $bootstrapper->bootstrap($container);
        }
    }

    /**
     * Handle an incoming HTTP request.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {

        // TEMPORARY
        return new \Arcanum\Hyper\Response(
            new \Arcanum\Hyper\Message(
                new \Arcanum\Hyper\Headers([
                    'Content-Type' => 'text/plain'
                ]),
                new Stream(LazyResource::for('php://memory', 'w+')),
                \Arcanum\Hyper\Version::v11,
            ),
            \Arcanum\Hyper\StatusCode::OK,
            \Arcanum\Hyper\Phrase::OK,
        );
    }

    /**
     * Terminate the application.
     */
    public function terminate(): void
    {
    }
}
