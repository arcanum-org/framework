<?php

declare(strict_types=1);

namespace Arcanum\Forge\Migration;

/**
 * The outcome of a migrate or rollback operation.
 */
final readonly class MigrationResult
{
    /**
     * @param list<string> $ran    Filenames that were executed successfully.
     * @param list<string> $errors Error messages (empty on full success).
     */
    public function __construct(
        public array $ran,
        public array $errors,
    ) {
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
