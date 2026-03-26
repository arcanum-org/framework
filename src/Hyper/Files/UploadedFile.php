<?php

declare(strict_types=1);

namespace Arcanum\Hyper\Files;

use Arcanum\Flow\River\LazyResource;
use Arcanum\Flow\River\ResourceWrapper;
use Arcanum\Flow\River\Stream;
use Arcanum\Flow\River\StreamResource;
use Arcanum\Flow\River\Bank;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

class UploadedFile implements UploadedFileInterface
{
    /**
     * @var string The file path, if set.
     */
    private string $file;

    /**
     * @var StreamInterface The stream representation of the uploaded file, if set.
     */
    private StreamInterface $stream;

    /**
     * @var bool Whether the uploaded file has already been moved.
     */
    private bool $moved = false;

    /**
     * @param StreamInterface|string|resource|ResourceWrapper|null $file
     */
    public function __construct(
        $file,
        private string $mode,
        private Error $error = Error::UPLOAD_ERR_OK,
        private int|null $size = null,
        private string|null $clientFilename = null,
        private string|null $clientMediaType = null
    ) {
        if ($this->error->isOK() && $file !== null) {
            $this->marshallFile($file);
        }
    }

    /**
     * @param StreamInterface|string|resource|ResourceWrapper $file
     * @throws InvalidFile
     */
    protected function marshallFile($file): void
    {
        if (is_string($file)) {
            $this->file = $file;
            return;
        }

        $this->stream = match (true) {
            $file instanceof StreamInterface => $file,
            is_resource($file) => new Stream(StreamResource::wrap($file)),
            $file instanceof ResourceWrapper => new Stream($file),
            default => throw new InvalidFile('Invalid file type'),
        };
    }

    /**
     * Get a stream representation of the uploaded file.
     */
    public function getStream(): StreamInterface
    {
        if (!$this->error->isOK()) {
            throw new InvalidFile('Cannot retrieve stream due to upload error');
        }
        if (isset($this->stream)) {
            return $this->stream;
        }
        if (isset($this->file)) {
            return new Stream(LazyResource::for($this->file, $this->mode));
        }
        throw new InvalidFile('No file or stream available');
    }

    /**
     * Move the uploaded file to a new location.
     *
     * @param non-empty-string $targetPath
     */
    public function moveTo(string $targetPath): void
    {
        if (empty($targetPath)) {
            throw new InvalidFile('Target path cannot be empty');
        }
        if ($this->moved) {
            throw new InvalidFile('File has already been moved');
        }
        if (!$this->error->isOK()) {
            throw new InvalidFile('Cannot move file due to upload error');
        }
        $this->moved = isset($this->file)
            ? $this->nativeMove($targetPath)
            : $this->streamMove($targetPath);

        if (!$this->moved) {
            throw new \RuntimeException('Unable to move file');
        }
    }

    /**
     * Use a native PHP move operation (rename or move_uploaded_file) to move the file.
     */
    protected function nativeMove(string $targetPath): bool
    {
        return \PHP_SAPI === 'cli'
            ? rename($this->file ?? '', $targetPath)
            : move_uploaded_file($this->file ?? '', $targetPath);
    }

    /**
     * Use a stream-based operation to move the file.
     */
    protected function streamMove(string $targetPath): bool
    {
        $stream = $this->getStream();
        $target = new Stream(LazyResource::for($targetPath, 'w'));
        Bank::copyTo($stream, $target);
        Bank::deleteIfMoved($stream, $targetPath);
        return $stream->eof();
    }

    /**
     * Retrieve the file size.
     */
    public function getSize(): ?int
    {
        return $this->size;
    }

    /**
     * Retrieve the error associated with the uploaded file.
     */
    public function getError(): int
    {
        return $this->error->value;
    }

    /**
     * Retrieve the filename sent by the client.
     */
    public function getClientFilename(): string|null
    {
        return $this->clientFilename;
    }

    /**
     * Retrieve the media type sent by the client.
     */
    public function getClientMediaType(): string|null
    {
        return $this->clientMediaType;
    }
}
