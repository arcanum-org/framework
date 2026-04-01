<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

use Psr\Container\ContainerInterface;

/**
 * Maps CLI --format values to renderers.
 *
 * Simpler than FormatRegistry — no content types or Format objects,
 * just format name → renderer class. Resolves renderers from the container.
 */
final class CliFormatRegistry
{
    /**
     * @var array<string, class-string<Renderer>>
     */
    private array $formats = [];

    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * Register a format.
     *
     * @param class-string<Renderer> $rendererClass
     */
    public function register(string $name, string $rendererClass): void
    {
        $this->formats[$name] = $rendererClass;
    }

    /**
     * Check if a format is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->formats[$name]);
    }

    /**
     * Resolve the renderer for a given format name.
     *
     * @throws UnsupportedFormat If the format is not registered.
     */
    public function renderer(string $name): Renderer
    {
        if (!isset($this->formats[$name])) {
            throw new UnsupportedFormat($name);
        }

        /** @var Renderer */
        return $this->container->get($this->formats[$name]);
    }
}
