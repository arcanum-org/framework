<?php

declare(strict_types=1);

namespace Arcanum\Flow\River;

use Psr\Http\Message\StreamInterface;

class CachingStream implements Copyable, \Stringable
{
    /**
     * Number of bytes to skip reading due to a previous write.
     */
    protected int $bytesToSkip = 0;

    /**
     * @var StreamInterface The stream to read from when caching.
     */
    protected StreamInterface $remote;

    /**
     * @var StreamInterface The stream to write to when caching.
     */
    protected StreamInterface $local;

    /**
     * CachingStream will initially read from the remote stream and write to
     * the local stream.
     *
     * If the local stream is not provided, it will be created as a temporary
     * stream.
     *
     */
    protected function __construct(
        StreamInterface $remote,
        ?StreamInterface $local = null,
    ) {
        $this->remote = $remote;
        $this->local = $local ?? TemporaryStream::getNew();
    }

    /**
     * Create a new CachingStream around a remote stream.
     */
    public static function fromStream(StreamInterface $remote): StreamInterface
    {
        return new self($remote);
    }

    /**
     * Create a new CachingStream around a remote stream with a local cache.
     */
    public static function fromStreamWithCache(StreamInterface $remote, StreamInterface $local): StreamInterface
    {
        return new self($remote, $local);
    }

    /**
     * Get the size of the stream if known.
     */
    public function getSize(): ?int
    {
        $remoteSize = $this->remote->getSize();

        if ($remoteSize === null) {
            return null;
        }

        $localSize = $this->local->getSize();

        if ($localSize === null) {
            return null;
        }

        return max($remoteSize, $localSize);
    }

    /**
     * Rewind the stream to the beginning.
     */
    public function rewind(): void
    {
        $this->seek(0);
    }

    /**
     * Seek to a position in the stream.
     */
    public function seek($offset, $whence = SEEK_SET): void
    {
        $byte = match ($whence) {
            SEEK_SET => $offset,
            SEEK_CUR => $this->tell() + $offset,
            SEEK_END => ($this->remote->getSize() ?? $this->consumeEverything()) + $offset,
            default => throw new \InvalidArgumentException('Invalid whence'),
        };

        $diff = $byte - $this->local->getSize();

        if ($diff <= 0) {
            // since there is no difference, we can just seek the local stream.
            $this->local->seek($byte);
            return;
        }

        // otherwise, we need to read until we get to at least the number of
        // bytes requested, or we reach the end of the stream.
        //
        // this allows us to cache even when seeking.
        while ($diff > 0 && !$this->remote->eof()) {
            $this->read($diff);
            $diff = $byte - $this->local->getSize();
        }
    }

    /**
     * Read data from the stream.
     */
    public function read(int $length): string
    {
        // first read local stream
        $data = $this->local->read($length);
        $remaining = $length - strlen($data);

        // if we have read all the data we need, return it.
        if ($remaining <= 0) {
            return $data;
        }

        // otherwise, read the remaining data from the remote stream.
        // if data was written to the local stream in a position that would
        // have been filled by the remote stream, we skip that data.
        //
        // this emulates overwriting the data, which is what other PHP stream
        // wrappers do.
        $remoteData = $this->remote->read($remaining + $this->bytesToSkip);

        if ($this->bytesToSkip) {
            $remoteLength = strlen($remoteData);
            $remoteData = substr($remoteData, $this->bytesToSkip);
            $this->bytesToSkip = max(0, $this->bytesToSkip - $remoteLength);
        }

        $data .= $remoteData;
        $this->local->write($remoteData);

        return $data;
    }

    /**
     * Write data to the stream.
     *
     * This will only write to the cache, not the remote stream.
     */
    public function write(string $string): int
    {
        $overflow = strlen($string) + $this->tell() - $this->remote->tell();
        if ($overflow > 0) {
            $this->bytesToSkip += $overflow;
        }

        return $this->local->write($string);
    }

    /**
     * Check if the stream is at the end.
     */
    public function eof(): bool
    {
        return $this->local->eof() && $this->remote->eof();
    }

    /**
     * Close the stream and the cache.
     */
    public function close(): void
    {
        $this->local->close();
        $this->remote->close();
    }

    /**
     * Detach the stream and the cache.
     */
    public function detach()
    {
        $this->remote->detach();
        return $this->local->detach();
    }

    /**
     * Get the current position of the cache.
     */
    public function tell(): int
    {
        return $this->local->tell();
    }

    /**
     * Get whether or not the stream is seekable.
     */
    public function isSeekable(): bool
    {
        return $this->local->isSeekable();
    }

    /**
     * Get whether or not the stream is writable.
     */
    public function isWritable(): bool
    {
        return $this->local->isWritable();
    }

    /**
     * Get whether or not the stream is readable.
     */
    public function isReadable(): bool
    {
        return $this->local->isReadable();
    }

    /**
     * Get the stream contents as a string.
     */
    public function getContents(): string
    {
        return $this->local->getContents();
    }

    /**
     * Get stream metadata as an associative array or retrieve a specific key.
     *
     * @param string|null $key
     * @return mixed
     */
    public function getMetadata($key = null): mixed
    {
        return $this->local->getMetadata($key);
    }

    public function __toString(): string
    {
        return $this->getContents();
    }

    /**
     * Cache all data from the remote stream by copying it to /dev/null.
     *
     * @param resource|null|false $pointer
     */
    public function consumeEverything($pointer = null): int
    {
        $pointer ??= fopen('/dev/null', 'w');
        if ($pointer === false) {
            throw new \RuntimeException('Could not open pointer to resource for target stream');
        }
        $devNull = StreamResource::wrap($pointer);
        $this->copyTo(new Stream($devNull));
        return $this->tell();
    }

    /**
     * Copy the contents of the stream to another stream.
     */
    public function copyTo(StreamInterface $output): void
    {
        $bytes = 8192;
        while (!$this->remote->eof()) {
            if (!$output->write($this->read($bytes))) {
                break;
            }
        }
    }
}
