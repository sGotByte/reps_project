<?php
declare(strict_types=1);

require_once __DIR__ . '/site_config.php';
require_once __DIR__ . '/db_helpers.php';
require_once __DIR__ . '/terms.php';

/**
 * ระบบคิวจ่ายงาน (หัวข้อ 3.3)
 * - auto  : จ่ายงานให้สตาฟที่เปิดรับคิวตาม queue_order ถ้าไม่กดรับภายในเวลาที่กำหนด หักคะแนนและเลื่อนไปคนถัดไป
 * - manual: admin เลือกสตาฟเอง ไม่หักคะแนนอัตโนมัติ
 * ค่าทั้งหมดอ่านจาก system_settings (ไม่ฝังค่าในโค้ด)
 * InfinityFree ไม่มี cron: ตรวจคิวหมดเวลาตอนเปิดหน้า หรือให้ cron ภายนอกเรียก cron_queue.php
 */

function queue_settings(mysqli $conn): array
{
    $s = app_get_settings($conn);
    $time = static fn(string $v): string => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', trim($v)) ? trim($v) : '';
    return [
        'mode'       => ($s['queue_dispatch_mode'] ?? 'auto') === 'manual' ? 'manual' : 'auto',
        'timeout'    => max(1, (int)($s['queue_timeout_minutes'] ?? 30)),
        'penalty'    => max(0, (int)($s['queue_penalty_points'] ?? 5)),
        'bonus'      => max(0, (int)($s['completion_bonus_points'] ?? 2)),
        'work_start' => $time((string)($s['queue_work_start'] ?? '')),
        'work_end'   => $time((string)($s['queue_work_end'] ?? '')),
    ];
}

/** ช่วงเวลาทำงานของวันที่ระบุ หรือ null ถ้านับคิวตลอด 24 ชม. */
function queue_work_window(array $q, DateTimeImmutable $day): ?array
{
    if ($q['work_start'] === '' || $q['work_end'] === '' || $q['work_start'] >= $q['work_end']) {
        return null;
    }
    $date = $day->format('Y-m-d');
    return [
        new DateTimeImmutable($date . ' ' . $q['work_start'] . ':00'),
        new DateTimeImmutable($date . ' ' . $q['work_end'] . ':00'),
    ];
}

function queue_is_work_time(array $q, ?DateTimeImmutable $now = null): bool
{
    $now ??= new DateTimeImmutable('now');
    $window = queue_work_window($q, $now);
    return $window === null || ($now >= $window[0] && $now < $window[1]);
}

/** เวลาหมดเขตรับคิว: นับเฉพาะในเวลาทำงาน (นอกเวลาจะเริ่มนับตอนเปิดทำการถัดไป) */
function queue_expiry_from_now(array $q, ?DateTimeImmutable $now = null): string
{
    $now ??= new DateTimeImmutable('now');
    $start = $now;
    $window = queue_work_window($q, $now);
    if ($window !== null) {
        if ($now < $window[0]) {
            $start = $window[0];
        } elseif ($now >= $window[1]) {
            $start = queue_work_window($q, $now->modify('+1 day'))[0];
        }
    }
    return $start->modify('+' . $q['timeout'] . ' minutes')->format('Y-m-d H:i:s');
}

/**
 * หาสตาฟคนถัดไปที่เปิดรับคิว: งานรอรับน้อยสุดก่อน แล้วเรียงตาม queue_order (round-robin)
 * @param int[] $excludeUserIds สตาฟที่ข้ามไปแล้วสำหรับใบงานนี้
 */
function queue_pick_next_staff(mysqli $conn, array $excludeUserIds = []): ?int
{
    $excludeUserIds = array_values(array_unique(array_map('intval', $excludeUserIds)));
    $sql = "SELECT u.user_id,
                   (SELECT COUNT(*) FROM tickets t WHERE t.queue_assigned_to = u.user_id AND t.status = 'pending') AS pending_q
              FROM users u
             WHERE u.role = 'staff' AND u.status = 'active' AND u.is_active_queue = 1" . term_staff_sql($conn); // เฉพาะทีมภาคเรียนปัจจุบัน
    if ($excludeUserIds) {
        $sql .= ' AND u.user_id NOT IN (' . implode(',', array_fill(0, count($excludeUserIds), '?')) . ')';
    }
    $sql .= ' ORDER BY pending_q ASC, COALESCE(u.queue_order, 9999) ASC, u.user_id ASC LIMIT 1';
    $row = db_one($conn, $sql, str_repeat('i', count($excludeUserIds)), $excludeUserIds);
    return $row ? (int)$row['user_id'] : null;
}

/** สตาฟที่เคยถูกข้าม (หมดเวลา/ปฏิเสธ) สำหรับใบงานนี้ เพื่อไม่ให้คิววนกลับไปหักคะแนนซ้ำ */
function queue_skipped_staff(mysqli $conn, int $ticketId): array
{
    $rows = db_all(
        $conn,
        "SELECT DISTINCT user_id FROM score_logs WHERE ticket_id = ? AND reason LIKE 'คิว:%'",
        'i',
        [$ticketId]
    );
    return array_map(static fn($r) => (int)$r['user_id'], $rows);
}

/** ส่งใบงานไปคนถัดไป หรือปล่อยเป็นคิวกลางถ้าไม่เหลือคน (ต้องเรียกภายใน transaction) */
function queue_move_to_next(mysqli $conn, int $ticketId, array $q, array $exclude): ?int
{
    $next = queue_pick_next_staff($conn, $exclude);
    if ($next !== null) {
        db_exec(
            $conn,
            'UPDATE tickets SET queue_assigned_to = ?, queue_expires_at = ? WHERE id = ?',
            'isi',
            [$next, queue_expiry_from_now($q), $ticketId]
        );
    } else {
        db_exec($conn, 'UPDATE tickets SET queue_assigned_to = NULL, queue_expires_at = NULL WHERE id = ?', 'i', [$ticketId]);
    }
    return $next;
}

function queue_penalize(mysqli $conn, int $staffId, int $ticketId, int $points, string $reason, ?int $actionBy): void
{
    if ($points > 0) {
        db_exec($conn, 'UPDATE users SET score = GREATEST(0, score - ?) WHERE user_id = ?', 'ii', [$points, $staffId]);
    }
    // บันทึกเสมอ (แม้ 0 คะแนน) เพื่อใช้เป็นประวัติว่าข้ามสตาฟคนนี้แล้ว
    db_exec(
        $conn,
        'INSERT INTO score_logs (user_id, ticket_id, points_change, reason, action_by) VALUES (?, ?, ?, ?, ?)',
        'iiisi',
        [$staffId, $ticketId, -$points, $reason, $actionBy]
    );
}

/** ตรวจใบงานที่หมดเวลารับคิว -> หักคะแนนและส่งต่อ (เฉพาะโหมด auto และในเวลาทำงาน) */
function queue_process_expired_tickets(mysqli $conn): void
{
    try {
        $q = queue_settings($conn);
        if ($q['mode'] !== 'auto' || !queue_is_work_time($q)) {
            return;
        }
        $expired = db_all(
            $conn,
            "SELECT id FROM tickets
              WHERE status = 'pending' AND queue_assigned_to IS NOT NULL
                AND queue_expires_at IS NOT NULL AND queue_expires_at < NOW()"
        );
    } catch (Throwable $e) {
        error_log('Queue scan failed: ' . $e->getMessage());
        return;
    }

    $now = new DateTimeImmutable('now');
    $window = queue_work_window($q, $now);

    foreach ($expired as $row) {
        $ticketId = (int)$row['id'];
        try {
            db_transaction($conn, function () use ($conn, $ticketId, $q, $now, $window) {
                $locked = db_one(
                    $conn,
                    "SELECT ticket_no, queue_assigned_to, queue_expires_at FROM tickets
                      WHERE id = ? AND status = 'pending' AND queue_assigned_to IS NOT NULL
                        AND queue_expires_at < NOW() FOR UPDATE",
                    'i',
                    [$ticketId]
                );
                if (!$locked) {
                    return;
                }
                // หมดเวลานอกเวลาทำงาน (เช่น ข้ามคืน) -> เริ่มนับใหม่โดยไม่หักคะแนน
                if ($window !== null && new DateTimeImmutable($locked['queue_expires_at']) < $window[0]) {
                    db_exec($conn, 'UPDATE tickets SET queue_expires_at = ? WHERE id = ?', 'si', [queue_expiry_from_now($q, $now), $ticketId]);
                    return;
                }
                $staffId = (int)$locked['queue_assigned_to'];
                queue_penalize(
                    $conn,
                    $staffId,
                    $ticketId,
                    $q['penalty'],
                    "คิว: ไม่กดรับงาน #{$locked['ticket_no']} ภายใน {$q['timeout']} นาที",
                    null
                );
                $exclude = queue_skipped_staff($conn, $ticketId);
                $exclude[] = $staffId;
                queue_move_to_next($conn, $ticketId, $q, $exclude);
            });
        } catch (Throwable $e) {
            error_log('Queue expiration error for ticket ' . $ticketId . ': ' . $e->getMessage());
        }
    }
}

/**
 * สตาฟปฏิเสธงานที่ถึงคิวตัวเอง
 * @return array{ok:bool, penalty:int, message:string}
 */
function queue_decline_ticket(mysqli $conn, int $ticketId, int $staffId): array
{
    $q = queue_settings($conn);
    return db_transaction($conn, function () use ($conn, $ticketId, $staffId, $q) {
        $ticket = db_one(
            $conn,
            "SELECT ticket_no FROM tickets WHERE id = ? AND status = 'pending' AND queue_assigned_to = ? FOR UPDATE",
            'ii',
            [$ticketId, $staffId]
        );
        if (!$ticket) {
            return ['ok' => false, 'penalty' => 0, 'message' => 'ใบงานนี้ไม่ได้อยู่ในคิวของคุณแล้ว'];
        }
        if ($q['mode'] === 'manual') {
            // โหมด manual: ไม่หักคะแนนอัตโนมัติ คืนงานให้ admin มอบหมายใหม่
            db_exec($conn, 'UPDATE tickets SET queue_assigned_to = NULL, queue_expires_at = NULL WHERE id = ?', 'i', [$ticketId]);
            return ['ok' => true, 'penalty' => 0, 'message' => 'คืนงานให้ผู้ดูแลมอบหมายใหม่แล้ว'];
        }
        queue_penalize($conn, $staffId, $ticketId, $q['penalty'], "คิว: ปฏิเสธงาน #{$ticket['ticket_no']} เมื่อถึงคิว", $staffId);
        $exclude = queue_skipped_staff($conn, $ticketId);
        $exclude[] = $staffId;
        queue_move_to_next($conn, $ticketId, $q, $exclude);
        return [
            'ok' => true,
            'penalty' => $q['penalty'],
            'message' => 'ปฏิเสธงานแล้ว' . ($q['penalty'] > 0 ? ' ระบบหัก ' . $q['penalty'] . ' คะแนน' : '') . ' และส่งคิวต่อให้คนถัดไป',
        ];
    });
}

/**
 * คิวสำหรับใบงานใหม่
 * @return array{0:?int,1:?string} [staff_id, queue_expires_at]
 */
function queue_assignment_for_new_ticket(mysqli $conn): array
{
    $q = queue_settings($conn);
    if ($q['mode'] === 'manual') {
        return [null, null];
    }
    $staffId = queue_pick_next_staff($conn);
    return [$staffId, $staffId !== null ? queue_expiry_from_now($q) : null];
}

/** admin มอบหมายใบงานที่รอรับให้สตาฟ (ใช้ได้ทั้งสองโหมด; โหมด manual ไม่มีเวลาหมดเขต) */
function queue_admin_assign(mysqli $conn, int $ticketId, int $staffId): bool
{
    $q = queue_settings($conn);
    $staff = db_one($conn, "SELECT u.user_id FROM users u WHERE u.user_id = ? AND u.role = 'staff' AND u.status = 'active'" . term_staff_sql($conn), 'i', [$staffId]);
    if (!$staff) {
        return false;
    }
    $expires = $q['mode'] === 'auto' ? queue_expiry_from_now($q) : null;
    return db_exec(
        $conn,
        "UPDATE tickets SET queue_assigned_to = ?, queue_expires_at = ? WHERE id = ? AND status = 'pending'",
        'isi',
        [$staffId, $expires, $ticketId]
    ) > 0;
}

/** รายชื่อสตาฟพร้อมคะแนนและสถานะคิว */
function queue_get_board(mysqli $conn): array
{
    queue_process_expired_tickets($conn);

    $term = term_current($conn);
    $sql = "SELECT u.user_id, u.first_name, u.last_name, " . term_score_sql($term) . " AS score, u.queue_order, u.is_active_queue,
                   (SELECT COUNT(*) FROM tickets t WHERE t.assigned_to = u.user_id AND t.status IN ('accepted', 'awaiting_intake_sign', 'in_progress', 'inspected_ready')) AS active_jobs,
                   (SELECT COUNT(*) FROM tickets t WHERE t.queue_assigned_to = u.user_id AND t.status = 'pending') AS waiting_accept
            FROM users u
            WHERE u.role = 'staff' AND u.status = 'active'" . term_staff_sql($conn, 'u.user_id', $term) . "
            ORDER BY u.is_active_queue DESC, COALESCE(u.queue_order, 9999) ASC, u.user_id ASC";
    $res = $conn->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * สถานะคิวสำหรับผู้แจ้ง (หัวข้อ 3.10) ไม่เปิดเผยข้อมูลส่วนตัวสตาฟ
 * position = ลำดับของใบงานในคิวรอรับ (งานด่วนมาก่อน แล้วตามเวลาแจ้ง)
 */
function queue_public_status(mysqli $conn, ?int $ticketId = null): array
{
    $summary = db_one(
        $conn,
        "SELECT SUM(status = 'pending') AS waiting, SUM(status IN ('accepted', 'awaiting_intake_sign', 'in_progress', 'inspected_ready')) AS working,
                (SELECT COUNT(*) FROM users u WHERE u.role = 'staff' AND u.status = 'active' AND u.is_active_queue = 1" . term_staff_sql($conn) . ") AS available
           FROM tickets WHERE status IN ('pending', 'accepted', 'awaiting_intake_sign', 'in_progress', 'inspected_ready')"
    ) ?? [];
    $result = [
        'waiting'   => (int)($summary['waiting'] ?? 0),
        'working'   => (int)($summary['working'] ?? 0),
        'available' => (int)($summary['available'] ?? 0),
        'position'  => null,
    ];
    if ($ticketId !== null) {
        $mine = db_one($conn, "SELECT id, urgency, created_at FROM tickets WHERE id = ? AND status = 'pending'", 'i', [$ticketId]);
        if ($mine) {
            $ahead = db_one(
                $conn,
                "SELECT COUNT(*) AS n FROM tickets
                  WHERE status = 'pending' AND id <> ?
                    AND ((urgency = 'urgent' AND ? = 'normal')
                      OR (urgency = ? AND (created_at < ? OR (created_at = ? AND id < ?))))",
                'issssi',
                [$ticketId, $mine['urgency'], $mine['urgency'], $mine['created_at'], $mine['created_at'], $ticketId]
            );
            $result['position'] = (int)($ahead['n'] ?? 0) + 1;
        }
    }
    return $result;
}
