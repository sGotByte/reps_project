<?php
declare(strict_types=1);

/**
 * REPS · includes/password_reset.php — ผู้ดูแลรีเซ็ตรหัสผ่านให้ผู้ใช้/สตาฟที่ลืมรหัส
 *
 * 2 วิธี:
 *  1) รหัสชั่วคราว: ระบบสุ่มให้ (แสดงครูครั้งเดียว ไม่เก็บเป็นข้อความ) และตั้ง must_change_password = 1
 *     ผู้ใช้ต้องตั้งรหัสใหม่ทันทีที่ล็อกอิน (change_password.php)
 *  2) ส่งลิงก์ทางอีเมล: ใช้ token แบบเดียวกับ "ลืมรหัสผ่าน" (เก็บเฉพาะ SHA-256, อายุ 1 ชั่วโมง, ใช้ได้ครั้งเดียว)
 * ทุกครั้ง: session เดิมของผู้ใช้หลุดทุกเครื่อง (user_auth เทียบ hash รหัสผ่าน), ลิงก์รีเซ็ตเก่าใช้ไม่ได้, บันทึก audit
 * ห้ามรีเซ็ตบัญชีครู/ผู้ดูแลคนอื่น และห้ามรีเซ็ตบัญชีตัวเอง (ใช้หน้าโปรไฟล์ หรือ "ลืมรหัสผ่าน")
 */

require_once __DIR__ . '/db_helpers.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/private_storage.php'; // AppUserException

const PW_MIN_LENGTH = 8;

/** รหัสชั่วคราวอ่านง่าย ไม่มีตัวที่สับสน (0/O, 1/l/I) เช่น "Kp7m-x4Qz" */
function pw_temp_generate(): string
{
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $part = static function (int $n) use ($alphabet): string {
        $s = '';
        for ($i = 0; $i < $n; $i++) {
            $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $s;
    };
    // มีตัวพิมพ์ใหญ่ เล็ก และตัวเลขแน่นอน
    do {
        $pw = $part(4) . '-' . $part(4);
    } while (!preg_match('/[A-Z]/', $pw) || !preg_match('/[a-z]/', $pw) || !preg_match('/\d/', $pw));
    return $pw;
}

/** ตรวจว่าผู้ดูแลคนนี้รีเซ็ตรหัสผ่านของบัญชีเป้าหมายได้หรือไม่ */
function pw_reset_target(mysqli $conn, int $targetId, int $adminId): array
{
    $target = db_one($conn, 'SELECT user_id, id_number, first_name, last_name, email, role, status FROM users WHERE user_id = ?', 'i', [$targetId]);
    if (!$target) {
        throw new AppUserException('ไม่พบบัญชีนี้');
    }
    if ($targetId === $adminId) {
        throw new AppUserException('เปลี่ยนรหัสผ่านของตัวเองได้ที่หน้าโปรไฟล์');
    }
    if ($target['role'] === 'admin') {
        throw new AppUserException('รีเซ็ตรหัสผ่านของครู/ผู้ดูแลคนอื่นไม่ได้ ให้เจ้าของบัญชีใช้ "ลืมรหัสผ่าน" ที่หน้าเข้าสู่ระบบ');
    }
    if (in_array($target['status'], ['pending', 'rejected'], true)) {
        throw new AppUserException('บัญชีนี้ยังไม่ได้รับการอนุมัติ อนุมัติบัญชีก่อนจึงรีเซ็ตรหัสผ่านได้');
    }
    return $target;
}

/** ยกเลิกลิงก์รีเซ็ตที่ยังไม่ได้ใช้ทั้งหมดของผู้ใช้ */
function pw_invalidate_tokens(mysqli $conn, int $userId): void
{
    db_exec($conn, 'UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', 'i', [$userId]);
}

/**
 * วิธีที่ 1: ตั้งรหัสชั่วคราว + บังคับเปลี่ยนตอนล็อกอิน
 * @return string รหัสชั่วคราว (แสดงให้ครูครั้งเดียว)
 */
function pw_admin_reset_temp(mysqli $conn, int $targetId, int $adminId): string
{
    $target = pw_reset_target($conn, $targetId, $adminId);
    $temp = pw_temp_generate();
    db_transaction($conn, function () use ($conn, $targetId, $temp) {
        db_exec($conn, 'UPDATE users SET password = ?, must_change_password = 1 WHERE user_id = ?', 'si', [password_hash($temp, PASSWORD_DEFAULT), $targetId]);
        pw_invalidate_tokens($conn, $targetId);
    });
    audit_user($conn, $targetId, $adminId, 'รีเซ็ตรหัสผ่าน (รหัสชั่วคราว ต้องเปลี่ยนเมื่อเข้าสู่ระบบ) บัญชี ' . $target['id_number']);
    return $temp;
}

/**
 * วิธีที่ 2: ส่งลิงก์ตั้งรหัสใหม่ไปที่อีเมลของผู้ใช้ (รหัสเดิมยังใช้ได้จนกว่าจะตั้งใหม่)
 * @return string อีเมลที่ส่งไป
 */
function pw_admin_send_link(mysqli $conn, int $targetId, int $adminId): string
{
    require_once __DIR__ . '/mailer.php';
    $target = pw_reset_target($conn, $targetId, $adminId);
    if (!filter_var((string)$target['email'], FILTER_VALIDATE_EMAIL)) {
        throw new AppUserException('บัญชีนี้ไม่มีอีเมลที่ถูกต้อง ใช้วิธีรหัสชั่วคราวแทน');
    }
    $baseUrl = rtrim((string)(getenv('APP_BASE_URL') ?: ''), '/');
    if (!preg_match('#^https?://#i', $baseUrl)) {
        throw new AppUserException('ยังไม่ได้ตั้ง APP_BASE_URL จึงสร้างลิงก์ในอีเมลไม่ได้ ใช้วิธีรหัสชั่วคราวแทน');
    }
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    db_transaction($conn, function () use ($conn, $targetId, $tokenHash) {
        pw_invalidate_tokens($conn, $targetId);
        db_exec($conn, 'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))', 'is', [$targetId, $tokenHash]);
    });
    $body = 'สวัสดี ' . $target['first_name'] . ",\n\n"
        . "ครูผู้ดูแลระบบส่งลิงก์ตั้งรหัสผ่านใหม่สำหรับบัญชี REPS ของคุณ\n"
        . "เปิดลิงก์นี้เพื่อตั้งรหัสผ่านใหม่ (ใช้ได้ 1 ชั่วโมงและใช้ได้ครั้งเดียว):\n"
        . $baseUrl . '/reset_password.php?token=' . rawurlencode($token) . "\n\n"
        . "หากคุณไม่ได้แจ้งลืมรหัสผ่าน กรุณาติดต่อครูผู้ดูแลระบบ\n";
    try {
        app_mail_send((string)$target['email'], 'ตั้งรหัสผ่าน REPS ใหม่', $body);
    } catch (Throwable $e) {
        db_exec($conn, 'UPDATE password_reset_tokens SET used_at = NOW() WHERE token_hash = ?', 's', [$tokenHash]);
        error_log('pw_admin_send_link mail failed: ' . $e->getMessage());
        throw new AppUserException('ส่งอีเมลไม่สำเร็จ (ระบบอีเมลยังใช้ไม่ได้) ใช้วิธีรหัสชั่วคราวแทน');
    }
    audit_user($conn, $targetId, $adminId, 'ส่งลิงก์ตั้งรหัสผ่านใหม่ทางอีเมล บัญชี ' . $target['id_number']);
    return (string)$target['email'];
}

/** ผู้ใช้ตั้งรหัสใหม่หลังได้รหัสชั่วคราว — คืน hash ใหม่ (ใช้อัปเดต session) */
function pw_set_new(mysqli $conn, int $userId, string $newPassword, string $confirm): string
{
    if (mb_strlen($newPassword) < PW_MIN_LENGTH) {
        throw new AppUserException('รหัสผ่านใหม่ต้องมีอย่างน้อย ' . PW_MIN_LENGTH . ' ตัวอักษร');
    }
    if (strlen($newPassword) > 4096) {
        throw new AppUserException('รหัสผ่านยาวเกินไป');
    }
    if (!hash_equals($newPassword, $confirm)) {
        throw new AppUserException('รหัสผ่านใหม่และการยืนยันไม่ตรงกัน');
    }
    $row = db_one($conn, 'SELECT password FROM users WHERE user_id = ?', 'i', [$userId]);
    if ($row && password_verify($newPassword, (string)$row['password'])) {
        throw new AppUserException('กรุณาตั้งรหัสผ่านใหม่ที่ไม่ซ้ำกับรหัสชั่วคราว');
    }
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    db_exec($conn, 'UPDATE users SET password = ?, must_change_password = 0 WHERE user_id = ?', 'si', [$hash, $userId]);
    pw_invalidate_tokens($conn, $userId);
    return $hash;
}
