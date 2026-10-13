<?php

declare(strict_types=1);

namespace WilyCode\TranslationDatabase\Tests;

use DateInterval;
use Psr\SimpleCache\CacheInterface;

final class ArrayCache implements CacheInterface
{
    /** @var array<string, mixed> */
    private array $store = [];

    public int $getCalls = 0;
    public int $setCalls = 0;
    public null|int|DateInterval $lastTtl = null;
    public ?string $lastKey = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $this->getCalls++;
        return $this->store[$key] ?? $default;
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->setCalls++;
        $this->lastTtl = $ttl;
        $this->lastKey = $key;
        $this->store[$key] = $value;
        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->store[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->store = [];
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->get((string) $key, $default);
        }
        return $out;
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        foreach ($values as $k => $v) {
            $this->set((string) $k, $v, $ttl);
        }
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete((string) $key);
        }
        return true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->store);
    }
}
