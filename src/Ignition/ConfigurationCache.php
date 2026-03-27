<?php

declare(strict_types=1);

namespace Arcanum\Ignition;

use Arcanum\Gather\Configuration;
use Arcanum\Parchment\Reader;
use Arcanum\Parchment\Writer;
use Arcanum\Parchment\FileSystem;

class ConfigurationCache
{
    public function __construct(
        private string $cachePath,
        private Reader $reader = new Reader(),
        private Writer $writer = new Writer(),
        private FileSystem $fileSystem = new FileSystem(),
    ) {
    }

    /**
     * Check if a cached configuration file exists.
     */
    public function exists(): bool
    {
        return $this->reader->exists($this->cachePath);
    }

    /**
     * Load the cached configuration array.
     *
     * @return array<string, mixed>
     */
    public function load(): array
    {
        /** @var array<string, mixed> */
        return $this->reader->require($this->cachePath);
    }

    /**
     * Write the configuration array to the cache file.
     */
    public function write(Configuration $config): void
    {
        $this->writer->write(
            $this->cachePath,
            '<?php return ' . var_export($config->toArray(), true) . ';' . \PHP_EOL,
        );
    }

    /**
     * Delete the cached configuration file.
     */
    public function clear(): void
    {
        if ($this->exists()) {
            $this->fileSystem->delete($this->cachePath);
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
