<?php

declare(strict_types=1);

namespace Arcanum\Rune;

use Arcanum\Atlas\Route;
use Arcanum\Atlas\UnresolvableRoute;

/**
 * Stores explicit CLI command aliases that bypass convention-based resolution.
 *
 * Custom CLI routes take priority over convention routing, just as HTTP
 * custom routes do in Atlas\RouteMap. Each entry maps a CLI command name
 * to a DTO class with a CQRS type (command or query).
 */
final class CliRouteMap
{
    private const TYPE_MAP = [
        'command' => 'Command',
        'query' => 'Query',
    ];

    /**
     * @var array<string, array{dtoClass: string, type: string}>
     */
    private array $routes = [];

    /**
     * Register a custom CLI route.
     *
     * @param string $name The CLI command name (e.g., 'stripe:webhook').
     * @param string $dtoClass The fully-qualified DTO class name.
     * @param string $type The CQRS type: 'command' or 'query'.
     */
    public function register(string $name, string $dtoClass, string $type = 'command'): void
    {
        $type = strtolower($type);

        if (!isset(self::TYPE_MAP[$type])) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid CLI route type "%s". Use "command" or "query".',
                $type,
            ));
        }

        $this->routes[$name] = [
            'dtoClass' => $dtoClass,
            'type' => $type,
        ];
    }

    /**
     * Check if a command name has a custom route registered.
     */
    public function has(string $name): bool
    {
        return isset($this->routes[$name]);
    }

    /**
     * Resolve a custom CLI route to a Route.
     *
     * @param string $name The CLI command name.
     * @param string $format The output format.
     */
    public function resolve(string $name, string $format = 'cli'): Route
    {
        if (!isset($this->routes[$name])) {
            throw new UnresolvableRoute(sprintf(
                'No custom CLI route registered for "%s".',
                $name,
            ));
        }

        $entry = $this->routes[$name];

        return new Route(
            dtoClass: $entry['dtoClass'],
            handlerPrefix: '',
            format: $format,
        );
    }

    /**
     * Get all registered route names.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->routes);
    }
}
