<?php

/** Return a persistent attachment directory outside the public document root. */
function app_ticket_upload_dir(): string
{
   $configured = trim((string)(getenv('TICKET_UPLOAD_DIR') ?: ''));
   $base = $configured !== '' ? $configured : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'reps_private_uploads';

   // Relative paths are resolved against the project root so shared hosts without shell access do not need absolute paths.
   if (!str_starts_with($base, DIRECTORY_SEPARATOR)) {
      $base = dirname(__DIR__) . DIRECTORY_SEPARATOR . $base;
   }
   $base = rtrim($base, DIRECTORY_SEPARATOR);
   if ($base === '') {
      throw new RuntimeException('TICKET_UPLOAD_DIR is not a valid path');
   }

   $resolvedBase = realpath($base);
   if ($resolvedBase === false) {
      $parent = realpath(dirname($base));
      $resolvedBase = ($parent !== false ? $parent : dirname($base)) . DIRECTORY_SEPARATOR . basename($base);
   }
   $candidate = rtrim($resolvedBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'tickets';
   $documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
   if ($documentRoot !== false) {
      $normalizedRoot = rtrim($documentRoot, DIRECTORY_SEPARATOR);
      if ($resolvedBase === $normalizedRoot || str_starts_with($resolvedBase, $normalizedRoot . DIRECTORY_SEPARATOR)) {
         // Shared hosting such as InfinityFree cannot write outside htdocs on the main domain.
         // In that case the directory must be explicitly allowed and protected by its own .htaccess.
         $allowInDocroot = filter_var((string)(getenv('TICKET_UPLOAD_ALLOW_IN_DOCROOT') ?: 'false'), FILTER_VALIDATE_BOOLEAN);
         if (!$allowInDocroot) {
            throw new RuntimeException('Ticket upload storage must be outside the document root');
         }
         $protector = $candidate . DIRECTORY_SEPARATOR . '.htaccess';
         $protection = is_file($protector) ? strtolower((string)file_get_contents($protector)) : '';
         if (preg_match('/require\s+all\s+denied/', $protection) !== 1) {
            throw new RuntimeException('TICKET_UPLOAD_DIR inside the document root must contain tickets/.htaccess with Require all denied');
         }
      }
   }
   return $candidate;
}
