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
            $message = sprintf('Unknown type prefix "%s". Use "command:" or "query:".', $prefix);
            $similar = $this->findSimilarCustomRoutes($command);

            if ($similar !== []) {
                $message .= sprintf(' Did you mean: %s?', implode(', ', $similar));
            }

            throw new UnresolvableRoute($message);
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

        throw $this->buildNotFoundError($command, $typeNamespace, $path, $format);
    }

    /**
     * Build a descriptive error for a failed resolution, with suggestions.
     *
     * Checks two sources of suggestions:
     * 1. Wrong prefix — if the user typed command: but a query exists (or vice versa).
     * 2. Similar names — Levenshtein distance against registered custom routes.
     */
    private function buildNotFoundError(
        string $command,
        string $typeNamespace,
        string $path,
        string $format,
    ): UnresolvableRoute {
        $suggestions = [];

        // Check if the opposite type exists (CLI equivalent of HTTP 405).
        $alternateType = $typeNamespace === 'Command' ? 'Query' : 'Command';
        $alternatePrefix = strtolower($alternateType);
        $alternateRoute = $this->resolver->resolveByType(
            path: $path,
            typeNamespace: $alternateType,
            format: $format,
        );

        if (class_exists($alternateRoute->dtoClass) || class_exists($alternateRoute->dtoClass . 'Handler')) {
            $colonPos = strpos($command, ':');
            $rest = $colonPos !== false ? substr($command, $colonPos + 1) : $command;
            $suggestions[] = sprintf('%s:%s', $alternatePrefix, $rest);
        }

        $suggestions = array_merge($suggestions, $this->findSimilarCustomRoutes($command));

        $message = sprintf('No %s found for "%s".', strtolower($typeNamespace), $command);

        if ($suggestions !== []) {
            $message .= sprintf(' Did you mean: %s?', implode(', ', $suggestions));
        }

        return new UnresolvableRoute($message);
    }

    /**
     * Find custom route names within 3 edits of the given command.
     *
     * @return list<string>
     */
    private function findSimilarCustomRoutes(string $command): array
    {
        if ($this->routeMap === null) {
            return [];
        }

        $similar = [];

        foreach ($this->routeMap->names() as $name) {
            if (levenshtein($command, $name) <= 3) {
                $similar[] = $name;
            }
        }

        return $similar;
    }
}
