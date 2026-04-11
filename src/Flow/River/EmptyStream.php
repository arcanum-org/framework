<?php

declare(strict_types=1);

namespace Arcanum\Flow\River;

use Psr\Http\Message\StreamInterface;

/**
 * @phpstan-type MetaDataKey 'timed_out'|'blocked'|'eof'|'unread_bytes'|'stream_type'|'wrapper_type'|'wrapper_data'|'mode'|'seekable'|'uri'|'crypto'
 * @phpstan-type MetaData array{
 *   timed_out: bool,
 *   blocked: bool,
 *   eof: bool,
 *   unread_bytes: int,
 *   stream_type: string,
 *   wrapper_type: string,
 *   wrapper_data?: mixed,
 *   mode: string,
 *   seekable: bool,
 *   uri: string,
 *   crypto?: array{
 *     protocol: string,
 *     cipher_name: string,
 *     cipher_bits: int,
 *     cipher_version: string
 *   }
 * }
 */
class EmptyStream implements Copyable, \Stringable
{
    /**
     * Return the stream as a string.
     */
    public function __toString(): string
    {
        return '';
    }

    /**
     * Returns whether or not the stream is readable.
     */
    public function isReadable(): bool
    {
        return true;
    }

    /**
     * Returns whether or not the stream is writable.
     */
    public function isWritable(): bool
    {
        return false;
    }

    /**
     * Returns whether or not the stream is seekable.
     */
    public function isSeekable(): bool
    {
        return false;
    }

    /**
     * Returns true if the stream is at the end of the stream.
     */
    public function eof(): bool
    {
        return true;
    }

    /**
     * Returns the current position of the file read/write pointer
     */
    public function tell(): int
    {
        return 0;
    }

    /**
     * Rewind the stream to the beginning.
     */
    public function rewind(): void
    {
        // noop
    }

    /**
     * Seek the stream to a new position.
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        // noop
    }

    /**
     * Close the stream and any underlying resources.
     */
    public function close(): void
    {
        // noop
    }

    /**
     * Detach the stream from any underlying resources, if any.
     *
     * This will render the stream unusable.
     *
     * @return resource|null Underlying PHP stream, if any
     */
    public function detach()
    {
        return null;
    }

    /**
     * Get stream metadata as an associative array or retrieve a specific key.
     *
     * The keys returned are identical to the keys returned from PHP's
     * `stream_get_meta_data()` function.
     *
     * @param null|MetaDataKey $key Specific metadata to retrieve.
     * @return mixed|MetaData
     */
    public function getMetadata(string|null $key = null): mixed
    {
        $meta = [
            'timed_out' => false,
            'blocked' => false,
            'eof' => true,
            'unread_bytes' => 0,
            'stream_type' => 'EMPTY',
            'wrapper_type' => 'EMPTY',
            'wrapper_data' =>  null,
            'mode' => 'rb',
            'seekable' => false,
            'uri' => ''
        ];

        if ($key === null) {
            return $meta;
        }

        return $meta[$key] ?? null;
    }

    /**
     * Get the stream contents as a string.
     */
    public function getContents(): string
    {
        return '';
    }

    /**
     * Get the size of the stream if known.
     */
    public function getSize(): ?int
    {
        return 0;
    }

    /**
     * Read data from the stream.
     */
    public function read(int $length): string
    {
        return '';
    }

    /**
     * Write data to the stream.
     */
    public function write(string $string): int
    {
        return 0;
    }

    /**
     * Copy the stream to another stream.
     */
    public function copyTo(StreamInterface $output): void
    {
        // noop
    }
}
