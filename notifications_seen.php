<?php
declare(strict_types=1);

/**
 * REPS · notifications_seen.php — บันทึกว่าอ่านการแจ้งเตือนแล้ว (POST + CSRF)
 *   key=<notif key>  อ่านรายการเดียว (กดรายการในกระดิ่ง)
 *   all=1            อ่านทั้งหมด: คำนวณรายการที่ยังไม่อ่านจากฝั่งเซิร์ฟเวอร์ (ไม่เชื่อรายการจาก client)
 *                    และเลื่อน users.notif_seen_log_id ไปที่ log ล่าสุดของใบงานตัวเอง
 * บันทึกได้เฉพาะของบัญชีที่ล็อกอินอยู่ · key ต้องตรงรูปแบบ notif_key_valid()
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/user_auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/notifications.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('{"ok":false}');
}

$userId = (int)app_require_active_user($conn, 'login.php', null);

if (!app_csrf_is_valid($_POST['csrf'] ?? null)) {
    http_response_code(403);
    exit('{"ok":false}');
}

$keys = [];
if (($_POST['all'] ?? '') === '1') {
    notif_load($conn, $userId, current_role(), '');
    $keys = notif_all_keys();
} else {
    $key = is_string($_POST['key'] ?? null) ? trim($_POST['key']) : '';
    if (!notif_key_valid($key)) {
        http_response_code(400);
        exit('{"ok":false}');
    }
    $keys = [$key];
}

try {
    if ($keys) {
        $stmt = $conn->prepare('INSERT IGNORE INTO notification_reads (user_id, notif_key) VALUES (?, ?)');
        foreach (array_slice($keys, 0, 500) as $k) {
            $stmt->bind_param('is', $userId, $k);
            $stmt->execute();
        }
        $stmt->close();
    }
    if (($_POST['all'] ?? '') === '1') {
        $stmt = $conn->prepare(
            'UPDATE users SET notif_seen_log_id = GREATEST(COALESCE(notif_seen_log_id, 0), (
                 SELECT COALESCE(MAX(l.id), 0) FROM ticket_logs l JOIN tickets t ON t.id = l.ticket_id WHERE t.requester_id = ?
             )) WHERE user_id = ?'
        );
        $stmt->bind_param('ii', $userId, $userId);
        $stmt->execute();
        $stmt->close();
    }
    // เก็บกวาด: รายการที่อ่านเกิน 60 วัน (เรื่องเก่าที่ไม่แจ้งซ้ำแล้ว)
    if (random_int(1, 50) === 1) {
        $conn->query('DELETE FROM notification_reads WHERE read_at < NOW() - INTERVAL 60 DAY');
    }
} catch (Throwable $e) {
    error_log('notifications_seen: ' . $e->getMessage());
    http_response_code(500);
    exit('{"ok":false}');
}

echo '{"ok":true,"count":' . count($keys) . '}';
