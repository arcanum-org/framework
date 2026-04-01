<?php

declare(strict_types=1);

namespace Arcanum\Validation;

/**
 * Thrown when DTO validation fails.
 *
 * Carries all validation errors — the developer sees every problem at once
 * rather than fixing them one at a time.
 */
final class ValidationException extends \RuntimeException
{
    /** @var list<ValidationError> */
    private readonly array $errors;

    /**
     * @param list<ValidationError> $errors
     */
    public function __construct(array $errors)
    {
        $this->errors = $errors;

        $count = count($errors);
        parent::__construct("Validation failed with {$count} error(s).");
    }

    /**
     * All validation errors.
     *
     * @return list<ValidationError>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Errors grouped by field name.
     *
     * @return array<string, list<string>>
     */
    public function errorsByField(): array
    {
        $grouped = [];

        foreach ($this->errors as $error) {
            $grouped[$error->field][] = $error->message;
        }

        return $grouped;
    }
}
