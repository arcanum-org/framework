<?php

declare(strict_types=1);

namespace Arcanum\Ignition\Bootstrap;

use Arcanum\Ignition\Bootstrapper;
use Arcanum\Ignition\Kernel;
use Arcanum\Cabinet\Application;
use Dotenv\Dotenv;

/**
 * The environment bootstrapper.
 */
class Environment implements Bootstrapper
{
    /**
     * Bootstrap the application.
     */
    public function bootstrap(Application $container): void
    {
        /** @var Kernel $kernel */
        $kernel = $container->get(Kernel::class);
        $rootDirectory = $kernel->rootDirectory();

        // Load the environment variables from the .env file, if it exists.
        $dotenv = Dotenv::createImmutable($rootDirectory);
        $dotenv->safeLoad();

        // Validate required environment variables.
        $required = $kernel->requiredEnvironmentVariables();
        if ($required !== []) {
            $dotenv->required($required);
        }

        // Register the environment in the container.
        $container->factory(
            \Arcanum\Gather\Environment::class,
            fn() => new \Arcanum\Gather\Environment($_ENV)
        );
    }
}
