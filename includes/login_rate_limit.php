<?php

/**
 * Lightweight single-host login throttling. Stores only hashed IP/account keys
 * and timestamps outside the document root.
 */
function appLoginRateLimitPath(): string
{
   return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
      . 'reps-login-limit-' . hash('sha256', __DIR__) . '.json';
}

function appLoginRateLimitBuckets(string $account): array
{
   $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
   return [
      'ip' => [hash('sha256', 'ip:' . $ip), 30],
      'account' => [hash('sha256', 'account-ip:' . strtolower(trim($account)) . ':' . $ip), 6],
   ];
}

function appLoginRateLimitUpdate(string $account, string $operation): bool
{
   $path = appLoginRateLimitPath();
   $file = @fopen($path, 'c+');
   if ($file === false) {
      error_log('Could not open login rate-limit storage');
      return $operation === 'check';
   }

   try {
      if (!flock($file, LOCK_EX)) {
         error_log('Could not lock login rate-limit storage');
         return $operation === 'check';
      }
      $raw = stream_get_contents($file);
      $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
      if (!is_array($data)) {
         $data = [];
      }

      $now = time();
      $window = 900;
      foreach ($data as $key => $timestamps) {
         if (!is_array($timestamps)) {
            unset($data[$key]);
            continue;
         }
         $data[$key] = array_values(array_filter($timestamps, static fn($stamp) => is_int($stamp) && $stamp > $now - $window));
         if (!$data[$key]) {
            unset($data[$key]);
         }
      }

      $buckets = appLoginRateLimitBuckets($account);
      $limited = false;
      foreach ($buckets as $type => [$key, $limit]) {
         $attempts = $data[$key] ?? [];
         if ($operation === 'check' && count($attempts) >= $limit) {
            $limited = true;
         } elseif ($operation === 'record') {
            $attempts[] = $now;
            $data[$key] = $attempts;
         } elseif ($operation === 'clear' && $type === 'account') {
            unset($data[$key]);
         }
      }

      if (count($data) > 20000) {
         uasort($data, static fn($a, $b) => max($a ?: [0]) <=> max($b ?: [0]));
         $data = array_slice($data, -20000, null, true);
      }

      rewind($file);
      ftruncate($file, 0);
      fwrite($file, json_encode($data, JSON_THROW_ON_ERROR));
      fflush($file);
      @chmod($path, 0600);
      flock($file, LOCK_UN);
      return $limited;
   } catch (Throwable $e) {
      error_log('Login rate-limit storage failed: ' . $e->getMessage());
      return $operation === 'check';
   } finally {
      fclose($file);
   }
}

function appLoginIsRateLimited(string $account): bool
{
   return appLoginRateLimitUpdate($account, 'check');
}

function appLoginRecordFailure(string $account): void
{
   appLoginRateLimitUpdate($account, 'record');
}

function appLoginClearAccountFailures(string $account): void
{
   appLoginRateLimitUpdate($account, 'clear');
}
