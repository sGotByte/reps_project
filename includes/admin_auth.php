<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/user_auth.php';

// หน้าในโฟลเดอร์ admin: ต้องเป็นบัญชี admin ที่ active
// - ยังไม่ล็อกอิน / บัญชีถูกระงับ / เปลี่ยนรหัสผ่าน -> ล้าง session และไปหน้า login
// - ล็อกอินแล้วแต่ไม่ใช่ admin -> ส่งกลับหน้าหลักของ role ตัวเอง (ไม่ทำให้หลุดจากระบบ)
$adminId = app_require_active_user($conn, '../login.php?next=admin', 'admin');
