<?php

declare(strict_types=1);

namespace Arcanum\Rune;

use Arcanum\Atlas\ConventionResolver;
use Arcanum\Atlas\Route;
use Arcanum\Atlas\Router;
use Arcanum\Atlas\UnresolvableRoute;

/**
 * Maps CLI input to Routes using the convention system.
 *
 * Commands use the `command:` prefix, queries use the `query:` prefix.
 * Colon-separated segments after the prefix map to namespace segments:
 *
 *   command:contact:submit  → App\Contact\Command\Submit
 *   query:users:find        → App\Users\Query\Find
 */
final class CliRouter implements Router
{
    private const TYPE_MAP = [
        'command' => 'Command',
        'query' => 'Query',
    ];

    /**
     * @param ConventionResolver $resolver The convention-based path-to-namespace resolver.
     * @param CliRouteMap|null $routeMap Custom CLI route overrides, checked before convention routing.
     * @param string $defaultFormat Default output format when no --format flag is present.
     */
    public function __construct(
        private readonly ConventionResolver $resolver,
        private readonly CliRouteMap|null $routeMap = null,
        private readonly string $defaultFormat = 'cli',
    ) {
    }

    public function resolve(object $input): Route
    {
        if (!$input instanceof Input) {
            throw new UnresolvableRoute(sprintf(
                'CliRouter expects an Input, got %s.',
                get_class($input),
            ));
        }

        $command = $input->command();

        if ($command === '') {
            throw new UnresolvableRoute('No command specified.');
        }

        $format = $input->option('format') ?? $this->defaultFormat;

        // Custom routes take priority — check full command name first.
        if ($this->routeMap !== null && $this->routeMap->has($command)) {
            return $this->routeMap->resolve($command, $format);
        }

        $colonPos = strpos($command, ':');

        if ($colonPos === false) {
            throw new UnresolvableRoute(sprintf(
                'Command "%s" has no type prefix. Use "command:%s" or "query:%s".',
                $command,
                $command,
                $command,
            ));
        }

        $prefix = substr($command, 0, $colonPos);
        $rest = substr($command, $colonPos + 1);

        $typeNamespace = self::TYPE_MAP[strtolower($prefix)] ?? null;

        if ($typeNamespace === null) {
            throw new UnresolvableRoute(sprintf(
                'Unknown type prefix "%s". Use "command:" or "query:".',
                $prefix,
            ));
        }

        if ($rest === '') {
            throw new UnresolvableRoute(sprintf(
                'No command name after "%s:" prefix.',
                $prefix,
            ));
        }

        $path = str_replace(':', '/', $rest);

        $route = $this->resolver->resolveByType(
            path: $path,
            typeNamespace: $typeNamespace,
            format: $format,
        );

        if (class_exists($route->dtoClass)) {
            return $route;
        }

        if (class_exists($route->dtoClass . 'Handler')) {
            return $route;
        }

        throw new UnresolvableRoute(sprintf(
            'No %s found for "%s" (expected class %s).',
            strtolower($typeNamespace),
            $command,
            $route->dtoClass,
        ));
    }
}
