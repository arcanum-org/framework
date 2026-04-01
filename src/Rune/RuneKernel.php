<?php

declare(strict_types=1);

namespace Arcanum\Rune;

use Arcanum\Cabinet\Application;
use Arcanum\Ignition\Bootstrap;
use Arcanum\Ignition\Bootstrapper;
use Arcanum\Ignition\Kernel;

/**
 * The CLI entry point — parallel to HyperKernel for HTTP.
 *
 * RuneKernel shares the same Kernel interface and transport-agnostic
 * bootstrappers (Environment, Configuration, Logger, Exceptions) but
 * skips HTTP-specific bootstrappers (Middleware, RouteMiddleware).
 *
 * Subclass this in your application to customize bootstrappers or
 * register built-in framework commands.
 */
class RuneKernel implements Kernel
{
    private bool $isBootstrapped = false;

    protected Application $container;

    /**
     * Environment variables that must be set for the application to run.
     * Override this in your app's CLI Kernel to enforce required env vars.
     *
     * @var string[]
     */
    protected array $requiredEnvironmentVariables = [];

    /**
     * The bootstrappers to run before handling a command.
     *
     * Reuses transport-agnostic bootstrappers from Ignition. Skips
     * Middleware and RouteMiddleware (PSR-15 specific).
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
        private readonly string $rootDirectory,
        private string $configDirectory = '',
        private string $filesDirectory = '',
    ) {
        $root = rtrim($rootDirectory, DIRECTORY_SEPARATOR);

        if ($configDirectory === '') {
            $this->configDirectory = $root . DIRECTORY_SEPARATOR . 'config';
        }
        if ($filesDirectory === '') {
            $this->filesDirectory = $root . DIRECTORY_SEPARATOR . 'files';
        }
    }

    /**
     * @return string[]
     */
    public function requiredEnvironmentVariables(): array
    {
        return $this->requiredEnvironmentVariables;
    }

    public function rootDirectory(): string
    {
        return $this->rootDirectory;
    }

    public function configDirectory(): string
    {
        return $this->configDirectory;
    }

    public function filesDirectory(): string
    {
        return $this->filesDirectory;
    }

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
     * Handle CLI input and return an exit code.
     *
     * Override this in your application's CLI Kernel to customize
     * the dispatch pipeline.
     *
     * @param list<string> $argv Raw CLI arguments from $argv.
     */
    public function handle(array $argv): int
    {
        return ExitCode::Success->value;
    }

    public function terminate(): void
    {
    }
}
