<?php

declare(strict_types=1);

namespace Arcanum\Flow\River;

final class LazyResource implements ResourceWrapper
{
    private ?ResourceWrapper $resource = null;

    private function __construct(
        private \Closure $resourceFactory,
    ) {
    }

    public static function for(string $filename, string $mode): ResourceWrapper
    {
        return static::from(fn() => fopen($filename, $mode));
    }

    /**
     * @param callable(): (resource|false) $resourceFactory
     */
    public static function from(callable $resourceFactory): ResourceWrapper
    {
        if ($resourceFactory instanceof \Closure) {
            return new static($resourceFactory);
        }
        return new static($resourceFactory(...));
    }

    /**
     * Wrap a resource.
     */
    private function resource(): ResourceWrapper
    {
        if ($this->resource === null) {
            $pointer = ($this->resourceFactory)();
            if (!is_resource($pointer)) {
                throw new InvalidSource('Stream source must be a live resource');
            }
            $this->resource = StreamResource::wrap($pointer);
        }
        return $this->resource;
    }

    /**
     * Return the resource.
     *
     * @return resource
     */
    public function export()
    {
        return $this->resource()->export();
    }

    public function isResource(): bool
    {
        return $this->resource()->isResource();
    }

    public function feof(): bool
    {
        return $this->resource()->feof();
    }

    public function ftell(): int|false
    {
        return $this->resource()->ftell();
    }

    public function fseek(int $offset, int $whence = SEEK_SET): int
    {
        return $this->resource()->fseek($offset, $whence);
    }

    public function fclose(): bool
    {
        return $this->resource()->fclose();
    }

    /**
     * @return array{
     *   timed_out: bool,
     *   blocked: bool,
     *   eof: bool,
     *   unread_bytes: int,
     *   stream_type: string,
     *   wrapper_type: string,
     *   wrapper_data: mixed,
     *   mode: string,
     *   seekable: bool,
     *   uri?: string,
     *   crypto?: array{
     *     protocol: string,
     *     cipher_name: string,
     *     cipher_bits: int,
     *     cipher_version: string
     *   }
     * }
     */
    public function streamGetMetaData(): array
    {
        return $this->resource()->streamGetMetaData();
    }

    public function clearstatcache(bool $clearRealPathCache = false, string $filename = ''): void
    {
        $this->resource()->clearstatcache($clearRealPathCache, $filename);
    }

    /**
     * @return false|array{
     *   dev: int,
     *   ino: int,
     *   mode: int,
     *   nlink: int,
     *   uid: int,
     *   gid: int,
     *   rdev: int,
     *   size: int|false,
     *   atime: int,
     *   mtime: int,
     *   ctime: int,
     *   blksize: int,
     *   blocks: int
     * }
     */
    public function fstat(): array|false
    {
        return $this->resource()->fstat();
    }

    /**
     * @param int<1, max> $length
     */
    public function fread(int $length): string|false
    {
        return $this->resource()->fread($length);
    }

    public function fwrite(string $data): int|false
    {
        return $this->resource()->fwrite($data);
    }

    public function streamGetContents(): string|false
    {
        return $this->resource()->streamGetContents();
    }
}
