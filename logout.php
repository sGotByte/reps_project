<<<<<<< HEAD
<?php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
if (isset($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) === 'on') {
   ini_set('session.cookie_secure', '1');
}
if (session_status() === PHP_SESSION_NONE) session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$_SESSION = [];
if (ini_get('session.use_cookies')) {
   $params = session_get_cookie_params();
   setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: login.php', true, 303);
exit;
=======
<?php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
if (isset($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) === 'on') {
   ini_set('session.cookie_secure', '1');
}
if (session_status() === PHP_SESSION_NONE) session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$_SESSION = [];
if (ini_get('session.use_cookies')) {
   $params = session_get_cookie_params();
   setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: login.php', true, 303);
exit;
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
