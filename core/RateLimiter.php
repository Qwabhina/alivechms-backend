<?php

/**
 * Rate Limiter
 * 
 * Prevents brute force attacks and API abuse through request rate limiting.
 * Uses file-based caching for simplicity and performance.
 *
 * @package AliveChMS\Core
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-November
 */

declare(strict_types=1);

class RateLimiter
{
   private const CACHE_DIR = __DIR__ . '/../cache/rate_limits';

   /**
    * Check if rate limit exceeded
    * 
    * @param string $identifier Unique identifier (IP, user ID, etc.)
    * @param int $maxAttempts Maximum number of attempts
    * @param int $windowSeconds Time window in seconds
    * @return bool True if allowed, false if rate limited
    */
   public static function check(string $identifier, int $maxAttempts = 5, int $windowSeconds = 300): bool
   {
      // Ensure cache directory exists
      if (!is_dir(self::CACHE_DIR)) {
         mkdir(self::CACHE_DIR, 0755, true);
      }

      $cacheKey = hash('sha256', $identifier);
      $cacheFile = self::CACHE_DIR . '/' . $cacheKey;

      $now = time();
      $attempts = [];

      // Load existing attempts
      if (file_exists($cacheFile)) {
         $content = file_get_contents($cacheFile);
         $data = json_decode($content, true);

         if ($data && isset($data['attempts'])) {
            $attempts = $data['attempts'];

            // Remove old attempts outside window
            $attempts = array_filter($attempts, function ($timestamp) use ($now, $windowSeconds) {
               return ($now - $timestamp) < $windowSeconds;
            });
         }
      }

      // Check if rate limit exceeded
      if (count($attempts) >= $maxAttempts) {
         // Update file to maintain accurate count
         file_put_contents($cacheFile, json_encode(['attempts' => $attempts]));
         return false;
      }

      // Add current attempt
      $attempts[] = $now;
      file_put_contents($cacheFile, json_encode(['attempts' => $attempts]));

      return true;
   }

   /**
    * Clear rate limit for identifier
    * Useful after successful authentication
    * 
    * @param string $identifier Unique identifier
    */
   public static function clear(string $identifier): void
   {
      $cacheKey = hash('sha256', $identifier);
      $cacheFile = self::CACHE_DIR . '/' . $cacheKey;

      if (file_exists($cacheFile)) {
         unlink($cacheFile);
      }
   }

   /**
    * Get remaining attempts
    * 
    * @param string $identifier Unique identifier
    * @param int $maxAttempts Maximum attempts allowed
    * @param int $windowSeconds Time window
    * @return int Number of attempts remaining
    */
   public static function getRemaining(string $identifier, int $maxAttempts = 5, int $windowSeconds = 300): int
   {
      $cacheKey = hash('sha256', $identifier);
      $cacheFile = self::CACHE_DIR . '/' . $cacheKey;

      if (!file_exists($cacheFile)) {
         return $maxAttempts;
      }

      $content = file_get_contents($cacheFile);
      $data = json_decode($content, true);

      if (!$data || !isset($data['attempts'])) {
         return $maxAttempts;
      }

      $now = time();
      $attempts = array_filter($data['attempts'], function ($timestamp) use ($now, $windowSeconds) {
         return ($now - $timestamp) < $windowSeconds;
      });

      return max(0, $maxAttempts - count($attempts));
   }

   /**
    * Get time until rate limit reset
    * 
    * @param string $identifier Unique identifier
    * @param int $windowSeconds Time window
    * @return int Seconds until reset (0 if not rate limited)
    */
   public static function getResetTime(string $identifier, int $windowSeconds = 300): int
   {
      $cacheKey = hash('sha256', $identifier);
      $cacheFile = self::CACHE_DIR . '/' . $cacheKey;

      if (!file_exists($cacheFile)) {
         return 0;
      }

      $content = file_get_contents($cacheFile);
      $data = json_decode($content, true);

      if (!$data || !isset($data['attempts']) || empty($data['attempts'])) {
         return 0;
      }

      // Get oldest attempt in current window
      $now = time();
      $oldestAttempt = min($data['attempts']);

      // If oldest attempt is outside window, no rate limit
      if (($now - $oldestAttempt) >= $windowSeconds) {
         return 0;
      }

      // Calculate reset time
      return $windowSeconds - ($now - $oldestAttempt);
   }

   /**
    * Clean up old rate limit files
    * Should be run periodically via cron
    * 
    * @param int $maxAge Maximum age in seconds (default 24 hours)
    * @return int Number of files deleted
    */
   public static function cleanup(int $maxAge = 86400): int
   {
      if (!is_dir(self::CACHE_DIR)) {
         return 0;
      }

      $deleted = 0;
      $now = time();
      $files = glob(self::CACHE_DIR . '/*');

      foreach ($files as $file) {
         if (is_file($file) && ($now - filemtime($file)) > $maxAge) {
            unlink($file);
            $deleted++;
         }
      }

      return $deleted;
   }

   /**
    * Check and handle rate limit with automatic response
    * This is a convenience method that checks rate limit and sends response if exceeded
    * 
    * @param string $identifier Unique identifier
    * @param int $maxAttempts Maximum attempts
    * @param int $windowSeconds Time window
    * @return void Exits with 429 response if rate limited
    */
   public static function enforce(string $identifier, int $maxAttempts = 5, int $windowSeconds = 300): void
   {
      if (!self::check($identifier, $maxAttempts, $windowSeconds)) {
         $resetTime = self::getResetTime($identifier, $windowSeconds);
         $resetMinutes = ceil($resetTime / 60);

         http_response_code(429);
         echo json_encode([
            'status' => 'error',
            'message' => "Too many requests. Please try again in $resetMinutes minute(s).",
            'code' => 429,
            'retry_after' => $resetTime,
            'timestamp' => date('c')
         ]);
         exit;
      }
   }
}
