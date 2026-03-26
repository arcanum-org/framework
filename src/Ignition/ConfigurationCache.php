<?php

declare(strict_types=1);

namespace Arcanum\Ignition;

use Arcanum\Gather\Configuration;

class ConfigurationCache
{
    public function __construct(
        private string $cachePath,
    ) {
    }

    /**
     * Check if a cached configuration file exists.
     */
    public function exists(): bool
    {
        return is_file($this->cachePath);
    }

    /**
     * Load the cached configuration array.
     *
     * @return array<string, mixed>
     */
    public function load(): array
    {
        /** @var array<string, mixed> */
        return require $this->cachePath;
    }

    /**
     * Write the configuration array to the cache file.
     */
    public function write(Configuration $config): void
    {
        $directory = dirname($this->cachePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $this->cachePath,
            '<?php return ' . var_export($config->toArray(), true) . ';' . \PHP_EOL,
            \LOCK_EX,
        );
    }

    /**
     * Delete the cached configuration file.
     */
    public function clear(): void
    {
        if ($this->exists()) {
            unlink($this->cachePath);
        }
    }

    /**
     * Get the cache file path.
     */
    public function path(): string
    {
        return $this->cachePath;
    }
}
