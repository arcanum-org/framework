<?php

declare(strict_types=1);

namespace Arcanum\Validation;

use Arcanum\Flow\Continuum\Progression;
use Arcanum\Flow\Conveyor\HandlerProxy;

/**
 * Conveyor before-middleware that validates DTOs before handler dispatch.
 *
 * Runs validation rules declared as attributes on DTO constructor parameters.
 * On failure, throws ValidationException which each kernel renders appropriately
 * (422 on HTTP, error message on CLI).
 *
 * HandlerProxy payloads are skipped — dynamic DTOs don't have constructor
 * params to validate.
 */
final class ValidationGuard implements Progression, ValidatesInput
{
    /** @var array<class-string, bool> */
    private static array $ruleCache = [];

    public function __construct(
        private readonly Validator $validator = new Validator(),
    ) {
    }

    /**
     * Check if a DTO class has any Rule attributes on its constructor parameters.
     *
     * Results are cached per class. Used by MiddlewareBus to detect when
     * validation rules exist but no guard is registered.
     *
     * @param class-string $class
     */
    public static function dtoHasRules(string $class): bool
    {
        if (isset(self::$ruleCache[$class])) {
            return self::$ruleCache[$class];
        }

        try {
            $constructor = (new \ReflectionClass($class))->getConstructor();
        } catch (\ReflectionException) {
            return self::$ruleCache[$class] = false;
        }

        if ($constructor === null) {
            return self::$ruleCache[$class] = false;
        }

        foreach ($constructor->getParameters() as $param) {
            if ($param->getAttributes(Rule::class, \ReflectionAttribute::IS_INSTANCEOF) !== []) {
                return self::$ruleCache[$class] = true;
            }
        }

        return self::$ruleCache[$class] = false;
    }

    public function __invoke(object $payload, callable $next): void
    {
        $dto = $this->resolveDto($payload);

        if ($dto !== null) {
            $this->validator->validate($dto);
        }

        $next();
    }

    private function resolveDto(object $payload): object|null
    {
        // HandlerProxy instances carry data in a Registry, not constructor params.
        if ($payload instanceof HandlerProxy) {
            return null;
        }

        return $payload;
    }
}
