<?php

declare(strict_types=1);

namespace Arcanum\Flow\River;

class TemporaryStream extends Stream
{
    /**
     * Create a new Temporary Stream.
     */
    public function __construct(
        ResourceWrapper $source,
        int|null $size = null,
    ) {
        $meta = $source->streamGetMetaData();
        if ($meta['stream_type'] !== 'TEMP') {
            throw new InvalidSource('TemporaryStream can only be constructed from a temporary stream');
        }
        parent::__construct($source, $size);
    }

    /**
     * Create a new Temporary Stream.
     */
    public static function getNew(): Copyable
    {
        $pointer = fopen('php://temp', 'w+');
        // @codeCoverageIgnoreStart
        if ($pointer === false) {
            throw new \RuntimeException('Could not open temporary stream');
        }
        // @codeCoverageIgnoreEnd
        return new self(StreamResource::wrap($pointer));
    }
}
