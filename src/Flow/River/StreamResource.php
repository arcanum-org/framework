<?php

declare(strict_types=1);

namespace Arcanum\Flow\River;

class StreamResource implements ResourceWrapper
{
    /**
     * @param resource $resource
     */
    private function __construct(private $resource)
    {
    }

    /**
     * Wrap a resource.
     *
     * @param resource $resource
     */
    public static function wrap($resource): self
    {
        $wrapper = new self($resource);
        if (!$wrapper->isResource()) {
            throw new InvalidSource('Stream source must be a live resource');
        }
        return $wrapper;
    }

    /**
     * Return the resource.
     *
     * @return resource
     */
    public function export()
    {
        return $this->resource;
    }

    public function isResource(): bool
    {
        return is_resource($this->resource);
    }

    public function feof(): bool
    {
        return feof($this->resource);
    }

    public function ftell(): int|false
    {
        return ftell($this->resource);
    }

    public function fseek(int $offset, int $whence = SEEK_SET): int
    {
        return fseek($this->resource, $offset, $whence);
    }

    public function fclose(): bool
    {
        return fclose($this->resource);
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
        return stream_get_meta_data($this->resource);
    }

    public function clearstatcache(bool $clearRealPathCache = false, string $filename = ''): void
    {
        clearstatcache($clearRealPathCache, $filename);
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
        return fstat($this->resource);
    }

    /**
     * @param int<1, max> $length
     */
    public function fread(int $length): string|false
    {
        return fread($this->resource, $length);
    }

    public function fwrite(string $data): int|false
    {
        return fwrite($this->resource, $data);
    }

    public function streamGetContents(): string|false
    {
        return stream_get_contents($this->resource);
    }
}
