<?php
declare(strict_types=1);

require_once __DIR__ . '/db_helpers.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/private_storage.php';

/**
 * ไฟล์ส่วนตัวของบัญชี (รูปโปรไฟล์ + ลายเซ็นที่บันทึกไว้) — อ่านก่อน DELETE แล้วลบไฟล์หลังลบบัญชีสำเร็จ
 * คืนค่าเป็นสตริงคั่นด้วย | เพื่อใช้กับโค้ดเดิมที่ส่งเข้า user_delete_private_files()
 */
function user_avatar_path(mysqli $conn, int $userId): ?string
{
    try {
        $row = db_one($conn, 'SELECT avatar_path, signature_path, promptpay_qr_path FROM users WHERE user_id = ?', 'i', [$userId]);
    } catch (Throwable) {
        try {
            $row = db_one($conn, 'SELECT avatar_path, signature_path FROM users WHERE user_id = ?', 'i', [$userId]); // ยังไม่ได้รัน migration ขอบคุณ
        } catch (Throwable) {
            return null;
        }
    }
    $paths = array_filter([$row['avatar_path'] ?? null, $row['signature_path'] ?? null, $row['promptpay_qr_path'] ?? null]);
    return $paths ? implode('|', $paths) : null;
}

function user_delete_private_files(?string $paths): void
{
    foreach (array_filter(explode('|', (string)$paths)) as $p) {
        app_delete_private_file($p);
    }
}

/**
 * นับข้อมูลที่อ้างถึงบัญชีนี้และระบบต้องเก็บไว้ (ถ้า > 0 ห้ามลบ ให้ระงับบัญชีแทน)
 * ครอบคลุมทุกตารางที่มี FK แบบ RESTRICT หรือเป็นประวัติการทำงาน
 */
function user_reference_count(mysqli $conn, int $userId): int
{
    $row = db_one(
        $conn,
        'SELECT
            (SELECT COUNT(*) FROM tickets WHERE requester_id = ? OR assigned_to = ? OR queue_assigned_to = ?)
          + (SELECT COUNT(*) FROM ticket_logs WHERE action_by = ?)
          + (SELECT COUNT(*) FROM ticket_materials WHERE withdrawn_by = ?)
          + (SELECT COUNT(*) FROM ticket_signoffs WHERE signer_id = ?)
          + (SELECT COUNT(*) FROM score_logs WHERE user_id = ?)
          + (SELECT COUNT(*) FROM ticket_assignees WHERE user_id = ?) AS total',
        'iiiiiiii',
        [$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]
    );
    return (int)($row['total'] ?? 0);
}

function user_active_admin_count(mysqli $conn): int
{
    return (int)(db_one($conn, "SELECT COUNT(*) AS n FROM users WHERE role = 'admin' AND status = 'active'")['n'] ?? 0);
}

/** อธิบายบัญชีสำหรับ audit log (เก็บไว้เผื่อบัญชีถูกลบ) */
function user_audit_label(array $user): string
{
    return '#' . (int)($user['user_id'] ?? 0) . ' ' . ($user['id_number'] ?? '') . ' ' . trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
}
