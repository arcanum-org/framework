<?php

declare(strict_types=1);

namespace Arcanum\Forge;

use Arcanum\Glitch\ArcanumException;

class UnsupportedDriver extends \RuntimeException implements ArcanumException
{
    public function __construct(
        private readonly string $driver,
        \Throwable|null $previous = null,
    ) {
        parent::__construct(
            "Unsupported database driver \"{$driver}\"",
            0,
            $previous,
        );
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function getTitle(): string
    {
        return 'Unsupported Database Driver';
    }

    public function getSuggestion(): ?string
    {
        return "Supported drivers: mysql, pgsql, sqlite"
            . " — check the 'driver' key in config/database.php";
    }
}
