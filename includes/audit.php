<?php
declare(strict_types=1);

require_once __DIR__ . '/db_helpers.php';

/** บันทึกการเปลี่ยนแปลงบัญชีผู้ใช้ลง user_audit_logs (ไม่ทำให้งานหลักล้มถ้าบันทึกไม่ได้) */
function audit_user(mysqli $conn, ?int $targetUserId, ?int $actorId, string $detail): void
{
    try {
        $detail = mb_substr($detail, 0, 500, 'UTF-8');
        db_exec(
            $conn,
            'INSERT INTO user_audit_logs (target_user_id, changed_by, action_detail) VALUES (?, ?, ?)',
            'iis',
            [$targetUserId, $actorId, $detail]
        );
    } catch (Throwable $e) {
        error_log('Audit log failed: ' . $e->getMessage());
    }
}
