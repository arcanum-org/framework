<?php

declare(strict_types=1);

namespace Arcanum\Vault;

use Arcanum\Hourglass\Clock;
use Arcanum\Hourglass\SystemClock;
use Psr\SimpleCache\CacheInterface;

/**
 * In-memory cache backed by a plain PHP array.
 *
 * Data lives only for the current process. Intended for testing
 * and as a per-request cache layer.
 */
final class ArrayDriver implements CacheInterface
{
    /** @var array<string, array{value: mixed, expiry: int|null}> */
    private array $store = [];

    public function __construct(
        private readonly Clock $clock = new SystemClock(),
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        KeyValidator::validate($key);

        if (!isset($this->store[$key])) {
            return $default;
        }

        $entry = $this->store[$key];

        if ($entry['expiry'] !== null && $entry['expiry'] <= $this->clock->now()->getTimestamp()) {
            unset($this->store[$key]);
            return $default;
        }

        return $entry['value'];
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        KeyValidator::validate($key);

        $this->store[$key] = [
            'value' => $value,
            'expiry' => $this->resolveExpiry($ttl),
        ];

        return true;
    }

    public function delete(string $key): bool
    {
        KeyValidator::validate($key);
        unset($this->store[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->store = [];
        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    private function resolveExpiry(\DateInterval|int|null $ttl): int|null
    {
        if ($ttl === null) {
            return null;
        }

        $now = $this->clock->now()->getTimestamp();

        if ($ttl instanceof \DateInterval) {
            return $now + (int) (new \DateTime())->setTimestamp(0)->add($ttl)->getTimestamp();
        }

        if ($ttl <= 0) {
            return 0; // already expired
        }

        return $now + $ttl;
    }
}
