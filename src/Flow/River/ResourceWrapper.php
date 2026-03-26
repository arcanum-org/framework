<?php

declare(strict_types=1);

namespace Arcanum\Flow\River;

interface ResourceWrapper
{
    /**
     * Return the resource.
     *
     * @return resource
     */
    public function export();

    /**
     * Proxy for is_resource().
     */
    public function isResource(): bool;

    /**
     * Proxy for feof().
     */
    public function feof(): bool;

    /**
     * Proxy for ftell().
     */
    public function ftell(): int|false;

    /**
     * Proxy for fseek().
     */
    public function fseek(int $offset, int $whence = SEEK_SET): int;

    /**
     * Proxy for rewind().
     */
    public function fclose(): bool;

    /**
     * Proxy for stream_get_meta_data().
     *
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
    public function streamGetMetaData(): array;

    /**
     * Proxy for clearstatcache().
     */
    public function clearstatcache(bool $clearRealPathCache = false, string $filename = ''): void;

    /**
     * Proxy for fstat().
     *
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
    public function fstat(): array|false;

    /**
     * Proxy for fread().
     *
     * @param int<1, max> $length
     */
    public function fread(int $length): string|false;

    /**
     * Proxy for fwrite().
     */
    public function fwrite(string $data): int|false;

    /**
     * Proxy for stream_get_contents().
     */
    public function streamGetContents(): string|false;
}
