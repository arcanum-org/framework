<?php

declare(strict_types=1);

namespace Arcanum\Htmx;

/**
 * Value object for the JSON-envelope form of HX-Location.
 *
 * The simple form of HX-Location is just a path string. The JSON form
 * allows specifying target, swap method, and other options that control
 * how htmx performs the client-side navigation.
 *
 * Fields match the htmx spec: path is required, everything else is optional.
 */
final readonly class HtmxLocation implements \JsonSerializable
{
    public function __construct(
        public string $path,
        public ?string $target = null,
        public ?string $swap = null,
        public ?string $source = null,
        public ?string $event = null,
        public ?string $handler = null,
        /** @var array<string, mixed>|null */
        public ?array $values = null,
        /** @var array<string, string>|null */
        public ?array $headers = null,
        public ?string $select = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = ['path' => $this->path];

        if ($this->target !== null) {
            $data['target'] = $this->target;
        }
        if ($this->swap !== null) {
            $data['swap'] = $this->swap;
        }
        if ($this->source !== null) {
            $data['source'] = $this->source;
        }
        if ($this->event !== null) {
            $data['event'] = $this->event;
        }
        if ($this->handler !== null) {
            $data['handler'] = $this->handler;
        }
        if ($this->values !== null) {
            $data['values'] = $this->values;
        }
        if ($this->headers !== null) {
            $data['headers'] = $this->headers;
        }
        if ($this->select !== null) {
            $data['select'] = $this->select;
        }

        return $data;
    }

    /**
     * Encode to JSON string for the HX-Location header value.
     */
    public function toJson(): string
    {
        $encoded = json_encode($this, JSON_UNESCAPED_SLASHES);
        assert($encoded !== false);

        return $encoded;
    }
}
