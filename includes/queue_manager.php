<?php
declare(strict_types=1);

require_once __DIR__ . '/site_config.php';

/**
 * ตรวจสอบใบงานที่ถึงคิวแล้วแต่สตาฟไม่กดรับภายในเวลาที่กำหนด -> หักคะแนนและส่งต่อคิวถัดไป
 */
function queue_process_expired_tickets(mysqli $conn): void
{
    $settings = app_get_settings($conn);
    $timeoutMinutes = max(1, (int)($settings['queue_timeout_minutes'] ?? 30));
    $penaltyPoints  = max(1, (int)($settings['queue_penalty_points'] ?? 5));

    // ค้นหาใบงานที่ยัง pending และมีคิวหมดอายุแล้ว
    $sql = "SELECT id, ticket_no, queue_assigned_to
            FROM tickets
            WHERE status = 'pending'
              AND queue_assigned_to IS NOT NULL
              AND queue_expires_at IS NOT NULL
              AND queue_expires_at < NOW()";
    $res = $conn->query($sql);
    if (!$res || $res->num_rows === 0) {
        return;
    }

    $expiredTickets = $res->fetch_all(MYSQLI_ASSOC);
    $res->free();

    foreach ($expiredTickets as $t) {
        $ticketId = (int)$t['id'];
        $staffId  = (int)$t['queue_assigned_to'];

        $conn->begin_transaction();
        try {
            // ล็อกแถวเพื่อป้องกันการหักคะแนนซ้ำซ้อนหากมีคนเปิดหน้าพร้อมกัน
            $lock = $conn->prepare("SELECT id, queue_assigned_to FROM tickets WHERE id = ? AND status = 'pending' AND queue_expires_at < NOW() FOR UPDATE");
            $lock->bind_param('i', $ticketId);
            $lock->execute();
            $lockedRow = $lock->get_result()->fetch_assoc();
            $lock->close();

            if (!$lockedRow) {
                $conn->rollback();
                continue;
            }

            // 1. หักคะแนนสตาฟ (ไม่ให้ต่ำกว่า 0)
            $deduct = $conn->prepare("UPDATE users SET score = GREATEST(0, score - ?) WHERE user_id = ?");
            $deduct->bind_param('ii', $penaltyPoints, $staffId);
            $deduct->execute();
            $deduct->close();

            // 2. บันทึกประวัติการหักคะแนนลง score_logs
            $negPoints = -$penaltyPoints;
            $reason = "ไม่กดรับงาน #{$t['ticket_no']} ตามคิวภายใน {$timeoutMinutes} นาที";
            $log = $conn->prepare("INSERT INTO score_logs (user_id, ticket_id, points_change, reason, action_by) VALUES (?, ?, ?, ?, NULL)");
            $log->bind_param('iiis', $staffId, $ticketId, $negPoints, $reason);
            $log->execute();
            $log->close();

            // 3. หาสตาฟคนถัดไปในคิว (ยกเว้นคนที่เพิ่งโดนข้าม)
            $nextStaffId = queue_pick_next_staff($conn, $staffId);
            if ($nextStaffId !== null) {
                $upd = $conn->prepare("UPDATE tickets SET queue_assigned_to = ?, queue_expires_at = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?");
                $upd->bind_param('iii', $nextStaffId, $timeoutMinutes, $ticketId);
            } else {
                // หากไม่มีสตาฟคนอื่นในคิว ให้ปลดคิวออกเพื่อให้สตาฟคนใดก็ได้กดรับ
                $upd = $conn->prepare("UPDATE tickets SET queue_assigned_to = NULL, queue_expires_at = NULL WHERE id = ?");
                $upd->bind_param('i', $ticketId);
            }
            $upd->execute();
            $upd->close();

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('Queue expiration error: ' . $e->getMessage());
        }
    }
}

/**
 * หาสตาฟคนถัดไปที่เปิดรับคิว (เรียงตาม queue_order และจำนวนงานที่กำลังทำน้อยที่สุด)
 */
function queue_pick_next_staff(mysqli $conn, ?int $excludeUserId = null): ?int
{
    $sql = "SELECT u.user_id,
                   (SELECT COUNT(*) FROM tickets t WHERE t.queue_assigned_to = u.user_id AND t.status = 'pending') AS pending_q
            FROM users u
            WHERE u.role = 'staff'
              AND u.status = 'active'
              AND u.is_active_queue = 1";
    if ($excludeUserId !== null) {
        $sql .= " AND u.user_id <> " . (int)$excludeUserId;
    }
    $sql .= " ORDER BY pending_q ASC, COALESCE(u.queue_order, 9999) ASC, u.user_id ASC LIMIT 1";

    $res = $conn->query($sql);
    if ($res && $row = $res->fetch_assoc()) {
        $res->free();
        return (int)$row['user_id'];
    }
    return null;
}

/**
 * ดึงรายชื่อสตาฟที่อยู่ในคิวทั้งหมด พร้อมคะแนนและสถานะคิวปัจจุบัน
 */
function queue_get_board(mysqli $conn): array
{
    queue_process_expired_tickets($conn);

    $sql = "SELECT u.user_id, u.first_name, u.last_name, u.score, u.queue_order, u.is_active_queue,
                   (SELECT COUNT(*) FROM tickets t WHERE t.assigned_to = u.user_id AND t.status = 'in_progress') AS active_jobs,
                   (SELECT COUNT(*) FROM tickets t WHERE t.queue_assigned_to = u.user_id AND t.status = 'pending') AS waiting_accept
            FROM users u
            WHERE u.role = 'staff' AND u.status = 'active'
            ORDER BY u.is_active_queue DESC, COALESCE(u.queue_order, 9999) ASC, u.user_id ASC";
    $res = $conn->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}