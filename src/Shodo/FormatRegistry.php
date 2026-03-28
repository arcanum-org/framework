<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

use Psr\Container\ContainerInterface;

/**
 * Maps file extensions to Format definitions and resolves renderers
 * from the container.
 */
final class FormatRegistry
{
    /**
     * @var array<string, Format>
     */
    private array $formats = [];

    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * Register a format.
     */
    public function register(Format $format): void
    {
        $this->formats[$format->extension] = $format;
    }

    /**
     * Get a format by extension.
     *
     * @throws UnsupportedFormat If the extension is not registered.
     */
    public function get(string $extension): Format
    {
        if (!isset($this->formats[$extension])) {
            throw new UnsupportedFormat($extension);
        }

        return $this->formats[$extension];
    }

    /**
     * Check if an extension is registered.
     */
    public function has(string $extension): bool
    {
        return isset($this->formats[$extension]);
    }

    /**
     * Remove a registered format.
     */
    public function remove(string $extension): void
    {
        unset($this->formats[$extension]);
    }

    /**
     * Resolve the renderer for a given extension from the container.
     *
     * @throws UnsupportedFormat If the extension is not registered.
     */
    public function renderer(string $extension): Renderer
    {
        $format = $this->get($extension);

        /** @var Renderer */
        return $this->container->get($format->rendererClass);
    }
}
