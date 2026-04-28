<?php

declare(strict_types=1);

namespace Nikanzo\Core\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * PSR-16 file-based cache.
 *
 * Each entry is stored as a PHP file that returns a serialised array:
 *   ['expires' => int|null, 'value' => mixed]
 *
 * Controlled by env:
 *   NIKANZO_CACHE_PATH  – directory for cache files (default: var/cache/data)
 *   NIKANZO_CACHE_TTL   – default TTL in seconds (default: 3600)
 */
final class FileCache implements CacheInterface
{
    private string $directory;
    private int $defaultTtl;

    public function __construct(string $directory = '', int $defaultTtl = 0)
    {
        $this->directory  = rtrim($directory ?: ((string) getenv('NIKANZO_CACHE_PATH') ?: dirname(__DIR__, 3) . '/var/cache/data'), '/\\');
        $this->defaultTtl = $defaultTtl ?: (int) (getenv('NIKANZO_CACHE_TTL') ?: 3600);

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);
        $file = $this->path($key);

        if (!is_file($file)) {
            return $default;
        }

        $entry = include $file;

        if (!is_array($entry)) {
            return $default;
        }

        if ($entry['expires'] !== null && time() > $entry['expires']) {
            @unlink($file);

            return $default;
        }

        return $entry['value'];
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $this->validateKey($key);
        $seconds = $this->ttlToSeconds($ttl);
        $expires = $seconds === null ? null : time() + $seconds;
        $entry   = ['expires' => $expires, 'value' => $value];
        $content = '<?php return ' . var_export($entry, true) . ';';

        return (bool) file_put_contents($this->path($key), $content, LOCK_EX);
    }

    public function delete(string $key): bool
    {
        $this->validateKey($key);
        $file = $this->path($key);

        return !is_file($file) || unlink($file);
    }

    public function clear(): bool
    {
        $ok = true;
        foreach (glob($this->directory . '/*.php') ?: [] as $file) {
            $ok = unlink($file) && $ok;
        }

        return $ok;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        $ok = true;
        foreach ($values as $key => $value) {
            $ok = $this->set((string) $key, $value, $ttl) && $ok;
        }

        return $ok;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $ok = true;
        foreach ($keys as $key) {
            $ok = $this->delete($key) && $ok;
        }

        return $ok;
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    private function path(string $key): string
    {
        return $this->directory . '/' . hash('sha256', $key) . '.php';
    }

    private function validateKey(string $key): void
    {
        if ($key === '' || strpbrk($key, '{}()/\\@:') !== false) {
            throw new InvalidArgumentException(sprintf('Invalid cache key: "%s"', $key));
        }
    }

    private function ttlToSeconds(null|int|\DateInterval $ttl): ?int
    {
        if ($ttl === null) {
            return $this->defaultTtl ?: null;
        }

        if ($ttl instanceof \DateInterval) {
            return (int) (new \DateTime())->add($ttl)->getTimestamp() - time();
        }

        return $ttl;
    }
}
