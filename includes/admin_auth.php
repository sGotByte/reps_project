<?php
require_once __DIR__ . '/../db_connect.php';
<<<<<<< HEAD
require_once __DIR__ . '/user_auth.php';

// หน้าในโฟลเดอร์ admin: ต้องเป็นบัญชี admin ที่ active
// - ยังไม่ล็อกอิน / บัญชีถูกระงับ / เปลี่ยนรหัสผ่าน -> ล้าง session และไปหน้า login
// - ล็อกอินแล้วแต่ไม่ใช่ admin -> ส่งกลับหน้าหลักของ role ตัวเอง (ไม่ทำให้หลุดจากระบบ)
$adminId = app_require_active_user($conn, '../login.php?next=admin', 'admin');
=======
if (session_status() === PHP_SESSION_NONE) session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
if (empty($_SESSION['user_id'])) { header('Location: ../login.php?next=admin'); exit; }
$adminId = (int)$_SESSION['user_id'];
$adminStmt = $conn->prepare('SELECT role, status, password FROM users WHERE user_id = ? LIMIT 1');
if (!$adminStmt) { http_response_code(500); exit('ตรวจสอบสิทธิ์ผู้ดูแลระบบไม่ได้'); }
$adminStmt->bind_param('i', $adminId);
if (!$adminStmt->execute()) { $adminStmt->close(); http_response_code(500); exit('ตรวจสอบสิทธิ์ผู้ดูแลระบบไม่ได้'); }
$adminAccount = $adminStmt->get_result()->fetch_assoc();
$adminStmt->close();
$validSession = $adminAccount && $adminAccount['role'] === 'admin' && $adminAccount['status'] === 'active'
   && isset($_SESSION['auth_password_hash'])
   && hash_equals((string)$adminAccount['password'], (string)$_SESSION['auth_password_hash']);
if (!$validSession) {
   $_SESSION = [];
   if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
   }
   session_destroy();
   header('Location: ../login.php?next=admin');
   exit;
}
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
