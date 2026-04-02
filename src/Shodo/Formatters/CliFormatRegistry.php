<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Formatters;

use Arcanum\Shodo\Formatter;
use Psr\Container\ContainerInterface;

/**
 * Maps CLI --format values to formatters.
 *
 * Simpler than FormatRegistry — no content types or Format objects,
 * just format name → formatter class. Resolves formatters from the container.
 */
final class CliFormatRegistry
{
    /**
     * @var array<string, class-string<Formatter>>
     */
    private array $formats = [];

    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * Register a format.
     *
     * @param class-string<Formatter> $formatterClass
     */
    public function register(string $name, string $formatterClass): void
    {
        $this->formats[$name] = $formatterClass;
    }

    /**
     * Check if a format is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->formats[$name]);
    }

    /**
     * Resolve the formatter for a given format name.
     *
     * @throws UnsupportedFormat If the format is not registered.
     */
    public function formatter(string $name): Formatter
    {
        if (!isset($this->formats[$name])) {
            throw new UnsupportedFormat($name);
        }

        /** @var Formatter */
        return $this->container->get($this->formats[$name]);
    }
}
