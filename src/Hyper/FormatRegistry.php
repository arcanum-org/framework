<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

use Arcanum\Glitch\HttpException;
use Arcanum\Shodo\Formatters\Format;
use Psr\Container\ContainerInterface;

/**
 * Maps file extensions to Format definitions and resolves HTTP response
 * renderers from the container.
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
     * @throws HttpException If the extension is not registered (406 Not Acceptable).
     */
    public function get(string $extension): Format
    {
        if (!isset($this->formats[$extension])) {
            throw new HttpException(
                StatusCode::NotAcceptable,
                sprintf('Format "%s" is not supported.', $extension),
            );
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
     * Resolve the response renderer for a given extension from the container.
     *
     * @throws HttpException If the extension is not registered (406 Not Acceptable).
     */
    public function renderer(string $extension): ResponseRenderer
    {
        $format = $this->get($extension);

        /** @var ResponseRenderer */
        return $this->container->get($format->rendererClass);
    }
}
