<?php
declare(strict_types=1);

/**
 * REPS · wallboard.php — ชื่อเดิมของจอสรุปงาน (เปลี่ยนเป็น warboard.php)
 * เก็บไว้เพื่อให้จอทีวีที่ตั้งลิงก์เดิมไว้ยังเปิดได้: ส่งต่อไป warboard.php พร้อม query เดิม (เช่น ?k=รหัส)
 */

$query = (string)($_SERVER['QUERY_STRING'] ?? '');
// รับเฉพาะอักขระของ query ปกติ กันการแทรก header
if ($query !== '' && preg_match('/^[A-Za-z0-9_\-=&%.~+]*$/', $query) !== 1) {
    $query = '';
}
header('Location: warboard.php' . ($query !== '' ? '?' . $query : ''), true, 301);
exit;
