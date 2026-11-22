<?php
declare(strict_types=1);

/**
 * REPS · includes/staff_score.php — คะแนนสตาฟ (Lab boy) ต่อภาคเรียน
 *
 * กติกา: ทุกคนเริ่มภาคเรียนที่ 100 คะแนน (เต็ม) · หักเมื่อทำผิดข้อกำหนด · ครูผู้ควบคุมบวกคืนได้ แต่ไม่เกิน 100
 * - หักอัตโนมัติ: ไม่กดรับคิวภายในเวลา (includes/queue_manager.php)
 * - ครูให้/หักคะแนนเอง: เลือกจากรายการ score_rules หรือกำหนดคะแนนและเหตุผลเอง
 * - ไม่ลบประวัติ: ยกเลิกรายการที่ให้ผิด = บันทึกรายการกลับค่า (ประวัติครบเสมอ)
 * คะแนนแสดงผลคำนวณจาก score_logs ในช่วงภาคเรียน (term_score_sql ใน includes/terms.php)
 */

require_once __DIR__ . '/db_helpers.php';
require_once __DIR__ . '/terms.php';
require_once __DIR__ . '/private_storage.php';

const SCORE_MAX = 100;

/** คะแนนภาคเรียนปัจจุบันของสตาฟหนึ่งคน */
function score_current(mysqli $conn, int $userId, ?array $term = null): int
{
    $term ??= term_current($conn);
    $row = db_one($conn, 'SELECT ' . term_score_sql($term) . ' AS score FROM users u WHERE u.user_id = ?', 'i', [$userId]);
    return (int)($row['score'] ?? SCORE_MAX);
}

function score_rules(mysqli $conn, bool $activeOnly = true): array
{
    try {
        return db_all($conn, 'SELECT * FROM score_rules' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY points < 0 DESC, sort_order, id');
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * ครูปรับคะแนน: points ติดลบ = หัก, บวก = เพิ่ม (ตัดให้ไม่เกิน 100)
 * @return int คะแนนที่บันทึกจริง (หลังตัดเพดาน)
 */
function score_adjust(mysqli $conn, int $staffId, int $points, string $reason, int $adminId, ?int $ticketId = null): int
{
    $reason = trim($reason);
    if ($points === 0 || abs($points) > 100) {
        throw new AppUserException('คะแนนต้องไม่เป็น 0 และไม่เกิน 100');
    }
    if (mb_strlen($reason) < 3) {
        throw new AppUserException('กรุณาระบุเหตุผล');
    }
    $term = term_current($conn);
    if ($term && !in_array($staffId, term_roster_ids($conn, (int)$term['id']), true)) {
        throw new AppUserException('ปรับคะแนนได้เฉพาะสตาฟในทีม' . term_title($term));
    }
    $staff = db_one($conn, "SELECT user_id FROM users WHERE user_id = ? AND role = 'staff'", 'i', [$staffId]);
    if (!$staff) {
        throw new AppUserException('ไม่พบสตาฟคนนี้');
    }
    return db_transaction($conn, function () use ($conn, $staffId, $points, $reason, $adminId, $ticketId, $term) {
        // ล็อกแถวผู้ใช้ กันครูสองคนบวกพร้อมกันจนเกินเพดาน
        db_one($conn, 'SELECT user_id FROM users WHERE user_id = ? FOR UPDATE', 'i', [$staffId]);
        $current = score_current($conn, $staffId, $term);
        if ($points > 0) {
            $points = min($points, SCORE_MAX - $current);
            if ($points <= 0) {
                throw new AppUserException('สตาฟคนนี้มีคะแนนเต็ม ' . SCORE_MAX . ' แล้ว บวกเพิ่มไม่ได้');
            }
        }
        $label = ($points < 0 ? 'ครูหักคะแนน: ' : 'ครูให้คะแนน: ') . mb_substr($reason, 0, 200);
        db_exec($conn, 'INSERT INTO score_logs (user_id, ticket_id, points_change, reason, action_by) VALUES (?, ?, ?, ?, ?)', 'iiisi', [$staffId, $ticketId, $points, $label, $adminId]);
        db_exec($conn, 'UPDATE users SET score = LEAST(?, GREATEST(0, score + ?)) WHERE user_id = ?', 'iii', [SCORE_MAX, $points, $staffId]);
        return $points;
    });
}

/** ยกเลิกรายการคะแนน = บันทึกรายการกลับค่า (ไม่ลบประวัติ) */
function score_reverse(mysqli $conn, int $logId, int $adminId): void
{
    db_transaction($conn, function () use ($conn, $logId, $adminId) {
        $log = db_one($conn, 'SELECT * FROM score_logs WHERE id = ? FOR UPDATE', 'i', [$logId]);
        if (!$log || (int)$log['points_change'] === 0) {
            throw new AppUserException('ไม่พบรายการคะแนนนี้');
        }
        if (str_starts_with((string)$log['reason'], 'ยกเลิกรายการ #')) {
            throw new AppUserException('รายการนี้เป็นการยกเลิกอยู่แล้ว');
        }
        if (db_one($conn, 'SELECT id FROM score_logs WHERE reason LIKE ? LIMIT 1', 's', ['ยกเลิกรายการ #' . $logId . ' %'])) {
            throw new AppUserException('รายการนี้ถูกยกเลิกไปแล้ว');
        }
        $points = -(int)$log['points_change'];
        db_exec(
            $conn,
            'INSERT INTO score_logs (user_id, ticket_id, points_change, reason, action_by) VALUES (?, ?, ?, ?, ?)',
            'iiisi',
            [(int)$log['user_id'], $log['ticket_id'] !== null ? (int)$log['ticket_id'] : null, $points, 'ยกเลิกรายการ #' . $logId . ' (' . mb_substr((string)$log['reason'], 0, 150) . ')', $adminId]
        );
        db_exec($conn, 'UPDATE users SET score = LEAST(?, GREATEST(0, score + ?)) WHERE user_id = ?', 'iii', [SCORE_MAX, $points, (int)$log['user_id']]);
    });
}
