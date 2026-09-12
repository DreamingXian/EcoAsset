<?php

namespace App\Utils;

class CacheUtil
{
    private static ?string $cacheDir = null;

    private static function getCacheDir(): string
    {
        if (self::$cacheDir === null) {
            self::$cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ecoasset_cache';
            if (!is_dir(self::$cacheDir)) {
                @mkdir(self::$cacheDir, 0777, true);
            }
        }
        return self::$cacheDir;
    }

    private static function getFilePath(string $key): string
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        return self::getCacheDir() . DIRECTORY_SEPARATOR . $safeKey . '.cache';
    }

    /**
     * Retrieve an item from cache if not expired.
     */
    public static function get(string $key)
    {
        $filePath = self::getFilePath($key);
        if (!file_exists($filePath)) {
            return null;
        }

        $content = @file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        $data = @json_decode($content, true);
        if (!is_array($data) || !isset($data['expires_at'])) {
            return null;
        }

        if (time() > $data['expires_at']) {
            @unlink($filePath);
            return null;
        }

        return $data['payload'];
    }

    /**
     * Store an item in the cache for a given number of seconds.
     */
    public static function set(string $key, $payload, int $ttlSeconds = 15): bool
    {
        $filePath = self::getFilePath($key);
        $data = [
            'expires_at' => time() + $ttlSeconds,
            'payload'    => $payload
        ];

        return @file_put_contents($filePath, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
    }

    /**
     * Fetch from cache or execute callback and cache result.
     */
    public static function remember(string $key, int $ttlSeconds, callable $callback)
    {
        $cached = self::get($key);
        if ($cached !== null) {
            return $cached;
        }

        $fresh = $callback();
        if ($fresh !== null) {
            self::set($key, $fresh, $ttlSeconds);
        }
        return $fresh;
    }

    /**
     * Remove a specific cache key.
     */
    public static function delete(string $key): void
    {
        $filePath = self::getFilePath($key);
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    /**
     * Invalidate all cache keys or keys matching a prefix (e.g. 'tasks', 'analytics').
     */
    public static function flush(string $prefix = ''): void
    {
        $dir = self::getCacheDir();
        if (!is_dir($dir)) return;

        $files = scandir($dir);
        if (!$files) return;

        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            if (empty($prefix) || str_starts_with($f, $prefix)) {
                @unlink($dir . DIRECTORY_SEPARATOR . $f);
            }
        }
    }
}
