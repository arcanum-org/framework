<?php

declare(strict_types=1);

namespace Arcanum\Ignition;

/**
 * A Kernel is the initial entry point for an application.
 *
 * Different implementations of this interface can be used to
 * either serve a web application or a console application.
 */
interface Kernel extends Terminable, Bootstrapper
{
    /**
     * Get the root directory of the application.
     */
    public function rootDirectory(): string;

    /**
     * Get the configuration directory of the application.
     */
    public function configDirectory(): string;

    /**
     * Get the files directory of the application.
     */
    public function filesDirectory(): string;
}
