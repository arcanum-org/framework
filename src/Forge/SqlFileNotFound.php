<?php

declare(strict_types=1);

namespace Arcanum\Forge;

use Arcanum\Glitch\ArcanumException;
use Arcanum\Toolkit\Strings;

class SqlFileNotFound extends \RuntimeException implements ArcanumException
{
    private ?string $suggestion = null;

    public function __construct(
        private readonly string $path,
        \Throwable|null $previous = null,
    ) {
        parent::__construct(
            "SQL file not found: {$path}",
            0,
            $previous,
        );
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getTitle(): string
    {
        return 'SQL File Not Found';
    }

    public function getSuggestion(): ?string
    {
        return $this->suggestion;
    }

    public function withSuggestion(string $suggestion): static
    {
        $this->suggestion = $suggestion;

        return $this;
    }

    /**
     * Build a suggestion by listing nearby SQL files in the same directory.
     */
    public function withNearbySuggestion(): static
    {
        $dir = dirname($this->path);
        $expected = basename($this->path);

        if (!is_dir($dir)) {
            return $this->withSuggestion(
                "Directory does not exist: {$dir}",
            );
        }

        $sqlFiles = glob($dir . DIRECTORY_SEPARATOR . '*.sql');

        if ($sqlFiles === false || $sqlFiles === []) {
            return $this->withSuggestion(
                "No .sql files found in {$dir}"
                    . " — create {$expected} to define this query",
            );
        }

        $names = array_map('basename', $sqlFiles);
        $closest = Strings::closestMatch($expected, $names);

        if ($closest !== null) {
            return $this->withSuggestion(
                "Did you mean {$closest}?",
            );
        }

        return $this->withSuggestion(
            "Expected {$expected} — available SQL files: "
                . implode(', ', $names),
        );
    }
}
