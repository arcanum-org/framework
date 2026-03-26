<?php

declare(strict_types=1);

namespace Arcanum\Glitch;

enum Level : int
{
    case ERROR = \E_ERROR;
    case WARNING = \E_WARNING;
    case PARSE = \E_PARSE;
    case NOTICE = \E_NOTICE;
    case CORE_ERROR = \E_CORE_ERROR;
    case CORE_WARNING = \E_CORE_WARNING;
    case COMPILE_ERROR = \E_COMPILE_ERROR;
    case COMPILE_WARNING = \E_COMPILE_WARNING;
    case USER_ERROR = \E_USER_ERROR;
    case USER_WARNING = \E_USER_WARNING;
    case USER_NOTICE = \E_USER_NOTICE;
    case STRICT = \E_STRICT;
    case RECOVERABLE_ERROR = \E_RECOVERABLE_ERROR;
    case DEPRECATED = \E_DEPRECATED;
    case USER_DEPRECATED = \E_USER_DEPRECATED;
    case ALL = \E_ALL;

    /**
     * Check if the given level is a deprecation.
     */
    public static function isDeprecation(self|int $level): bool
    {
        if (is_int($level)) {
            $level = self::tryFrom($level) ?? self::ERROR;
        }
        return $level === self::DEPRECATED || $level === self::USER_DEPRECATED;
    }

    /**
     * Check if the given level is fatal.
     */
    public static function isFatal(self|int $level): bool
    {
        if (is_int($level)) {
            $level = self::tryFrom($level) ?? self::ERROR;
        }
        return $level === self::ERROR
            || $level === self::CORE_ERROR
            || $level === self::COMPILE_ERROR
            || $level === self::USER_ERROR
            || $level === self::PARSE;
    }
}
