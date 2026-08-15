<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Tests\Double;

use Psr\SimpleCache\CacheInterface;

/**
 * A PSR-16 cache in an array, with counters.
 *
 * The counters are the point: several tests assert that the request path did NOT
 * reach past the cache, and "how often was get() called" is how you prove that.
 */
final class ArrayCache implements CacheInterface
{
    /** @var array<string, mixed> */
    private array $items = [];

    public int $getCalls = 0;
    public int $setCalls = 0;

    /** @var list<string> */
    public array $keysWritten = [];

    public function get(string $key, mixed $default = null): mixed
    {
        ++$this->getCalls;

        return $this->items[$key] ?? $default;
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        ++$this->setCalls;
        $this->keysWritten[] = $key;
        $this->items[$key] = $value;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->items = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
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

    public function has(string $key): bool
    {
        return isset($this->items[$key]);
    }

    public function poison(string $key, mixed $garbage): void
    {
        $this->items[$key] = $garbage;
    }
}
