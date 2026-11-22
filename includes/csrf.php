<?php

function app_csrf_token(): string
{
   if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
   }

   return $_SESSION['csrf_token'];
}

function app_csrf_is_valid($token): bool
{
   return is_string($token)
      && isset($_SESSION['csrf_token'])
      && is_string($_SESSION['csrf_token'])
      && hash_equals($_SESSION['csrf_token'], $token);
}
