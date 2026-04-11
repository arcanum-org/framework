<?php

declare(strict_types=1);

namespace Arcanum\Flow\Conveyor\Middleware;

use Arcanum\Flow\Continuum\Progression;
use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\Attribute\HttpOnly;
use Arcanum\Hyper\StatusCode;
use Arcanum\Ignition\Transport;
use Arcanum\Rune\Attribute\CliOnly;

/**
 * Conveyor before-middleware that enforces transport restrictions on DTOs.
 *
 * Checks for #[CliOnly] and #[HttpOnly] attributes on the DTO class.
 * Rejects cross-transport dispatch with an appropriate error:
 *   - HTTP request to #[CliOnly] DTO → 405 Method Not Allowed
 *   - CLI call to #[HttpOnly] DTO → RuntimeException with clear message
 */
final class TransportGuard implements Progression
{
    public function __construct(
        private readonly Transport $transport,
    ) {
    }

    public function __invoke(object $payload, callable $next): void
    {
        $class = $this->resolveDtoClass($payload);

        if ($class !== null) {
            $this->check($class);
        }

        $next();
    }

    /**
     * @param class-string $class
     */
    private function check(string $class): void
    {
        $ref = new \ReflectionClass($class);

        if ($this->transport === Transport::Http) {
            if ($ref->getAttributes(CliOnly::class) !== []) {
                throw new HttpException(
                    StatusCode::MethodNotAllowed,
                    sprintf('"%s" is CLI-only and cannot be accessed via HTTP.', $class),
                );
            }
        }

        if ($this->transport === Transport::Cli) {
            if ($ref->getAttributes(HttpOnly::class) !== []) {
                throw new \RuntimeException(sprintf(
                    '"%s" is HTTP-only and cannot be executed from the CLI.',
                    $class,
                ));
            }
        }
    }

    /**
     * Resolve the actual DTO class name from the payload.
     *
     * HandlerProxy objects (Command, Query, Page) carry the class name
     * rather than being the class themselves.
     *
     * @return class-string|null
     */
    private function resolveDtoClass(object $payload): string|null
    {
        $class = $payload instanceof \Arcanum\Flow\Conveyor\HandlerProxy
            ? $payload->handlerBaseName()
            : get_class($payload);

        // HandlerProxy base names may reference non-existent DTO classes
        // (handler-only routes). Can't reflect on those.
        if (!class_exists($class)) {
            return null;
        }

        return $class;
    }
}
