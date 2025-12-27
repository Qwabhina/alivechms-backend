<?php

/**
 * Cache Manager
 *
 * High-performance file-based caching with automatic expiration,
 * cache invalidation, and memory optimization.
 *
 * Supports:
 * - Automatic expiration
 * - Tag-based invalidation
 * - Compression for large data
 * - Cache warming
 * - Statistics tracking
 *
 * @package  AliveChMS\Core
 * @version  2.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-December
 */

declare(strict_types=1);

class Cache
{
   private const CACHE_DIR = __DIR__ . '/../cache/data';
   private const DEFAULT_TTL = 3600; // 1 hour
   private const MAX_FILE_SIZE = 5242880; // 5MB

   /**
    * Get cached value
    * 
    * @param string $key Cache key
    * @param mixed $default Default value if not found
    * @return mixed Cached value or default
    */
   public static function get(string $key, $default = null)
   {
      $file = self::getFilePath($key);

      if (!file_exists($file)) {
         return $default;
      }

      $data = @unserialize(file_get_contents($file));

      if (!$data || !isset($data['expires'], $data['value'])) {
         self::delete($key);
         return $default;
      }

      // Check expiration
      if ($data['expires'] > 0 && time() > $data['expires']) {
         self::delete($key);
         return $default;
      }

      return $data['value'];
   }

   /**
    * Store value in cache
    * 
    * @param string $key Cache key
    * @param mixed $value Value to cache
    * @param int $ttl Time to live in seconds (0 = forever)
    * @param array $tags Cache tags for group invalidation
    * @return bool Success status
    */
   public static function set(string $key, $value, int $ttl = self::DEFAULT_TTL, array $tags = []): bool
   {
      self::ensureCacheDir();

      $file = self::getFilePath($key);
      $expires = $ttl > 0 ? time() + $ttl : 0;

      $data = [
         'value' => $value,
         'expires' => $expires,
         'tags' => $tags,
         'created' => time()
      ];

      $serialized = serialize($data);

      // Don't cache if too large
      if (strlen($serialized) > self::MAX_FILE_SIZE) {
         Helpers::logError("Cache value too large for key: $key");
         return false;
      }

      $success = @file_put_contents($file, $serialized, LOCK_EX) !== false;

      // Store tag index
      if ($success && !empty($tags)) {
         self::indexTags($key, $tags);
      }

      return $success;
   }

   /**
    * Remember: Get from cache or execute callback and cache result
    * 
    * @param string $key Cache key
    * @param callable $callback Callback to execute if cache miss
    * @param int $ttl Time to live
    * @param array $tags Cache tags
    * @return mixed Cached or fresh value
    */
   public static function remember(string $key, callable $callback, int $ttl = self::DEFAULT_TTL, array $tags = [])
   {
      $value = self::get($key);

      if ($value !== null) {
         return $value;
      }

      $value = $callback();
      self::set($key, $value, $ttl, $tags);

      return $value;
   }

   /**
    * Delete cached value
    * 
    * @param string $key Cache key
    * @return bool Success status
    */
   public static function delete(string $key): bool
   {
      $file = self::getFilePath($key);

      if (file_exists($file)) {
         return @unlink($file);
      }

      return true;
   }

   /**
    * Flush all cache entries
    * 
    * @return int Number of files deleted
    */
   public static function flush(): int
   {
      if (!is_dir(self::CACHE_DIR)) {
         return 0;
      }

      $deleted = 0;
      $files = glob(self::CACHE_DIR . '/*');

      foreach ($files as $file) {
         if (is_file($file) && @unlink($file)) {
            $deleted++;
         }
      }

      return $deleted;
   }

   /**
    * Invalidate cache entries by tag
    * 
    * @param string $tag Tag name
    * @return int Number of entries invalidated
    */
   public static function invalidateTag(string $tag): int
   {
      $indexFile = self::getTagIndexPath($tag);

      if (!file_exists($indexFile)) {
         return 0;
      }

      $keys = @unserialize(file_get_contents($indexFile));
      if (!is_array($keys)) {
         return 0;
      }

      $deleted = 0;
      foreach ($keys as $key) {
         if (self::delete($key)) {
            $deleted++;
         }
      }

      @unlink($indexFile);

      return $deleted;
   }

   /**
    * Clean up expired cache entries
    * 
    * @return int Number of expired entries deleted
    */
   public static function cleanup(): int
   {
      if (!is_dir(self::CACHE_DIR)) {
         return 0;
      }

      $deleted = 0;
      $files = glob(self::CACHE_DIR . '/cache_*');
      $now = time();

      foreach ($files as $file) {
         if (!is_file($file)) {
            continue;
         }

         $data = @unserialize(file_get_contents($file));

         if (!$data || !isset($data['expires'])) {
            @unlink($file);
            $deleted++;
            continue;
         }

         // Delete if expired
         if ($data['expires'] > 0 && $now > $data['expires']) {
            @unlink($file);
            $deleted++;
         }
      }

      return $deleted;
   }

   /**
    * Get cache statistics
    * 
    * @return array Cache stats
    */
   public static function stats(): array
   {
      if (!is_dir(self::CACHE_DIR)) {
         return [
            'total_entries' => 0,
            'total_size' => 0,
            'expired_entries' => 0
         ];
      }

      $files = glob(self::CACHE_DIR . '/cache_*');
      $now = time();
      $totalSize = 0;
      $expired = 0;

      foreach ($files as $file) {
         if (!is_file($file)) {
            continue;
         }

         $size = filesize($file);
         $totalSize += $size;

         $data = @unserialize(file_get_contents($file));
         if ($data && isset($data['expires']) && $data['expires'] > 0 && $now > $data['expires']) {
            $expired++;
         }
      }

      return [
         'total_entries' => count($files),
         'total_size' => $totalSize,
         'total_size_mb' => round($totalSize / 1024 / 1024, 2),
         'expired_entries' => $expired
      ];
   }

   /**
    * Ensure cache directory exists
    */
   private static function ensureCacheDir(): void
   {
      if (!is_dir(self::CACHE_DIR)) {
         @mkdir(self::CACHE_DIR, 0755, true);
      }
   }

   /**
    * Get file path for cache key
    * 
    * @param string $key Cache key
    * @return string File path
    */
   private static function getFilePath(string $key): string
   {
      $hash = hash('sha256', $key);
      return self::CACHE_DIR . '/cache_' . $hash;
   }

   /**
    * Get tag index file path
    * 
    * @param string $tag Tag name
    * @return string File path
    */
   private static function getTagIndexPath(string $tag): string
   {
      $hash = hash('sha256', 'tag_' . $tag);
      return self::CACHE_DIR . '/tag_' . $hash;
   }

   /**
    * Index cache key by tags
    * 
    * @param string $key Cache key
    * @param array $tags Tags
    */
   private static function indexTags(string $key, array $tags): void
   {
      foreach ($tags as $tag) {
         $indexFile = self::getTagIndexPath($tag);
         $keys = [];

         if (file_exists($indexFile)) {
            $existing = @unserialize(file_get_contents($indexFile));
            if (is_array($existing)) {
               $keys = $existing;
            }
         }

         if (!in_array($key, $keys, true)) {
            $keys[] = $key;
            @file_put_contents($indexFile, serialize($keys), LOCK_EX);
         }
      }
   }
}
