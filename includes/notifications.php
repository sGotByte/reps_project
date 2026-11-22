<?php
declare(strict_types=1);

/**
 * REPS · includes/notifications.php — กระดิ่งแจ้งเตือนบน navbar แยกตามบทบาท
 *
 *   echo notif_bell($conn, '../');   // $base = '' (หน้าในราก) หรือ '../' (admin/, staff/)
 *
 * admin  : งานรอรับรอง · ผู้ใช้รออนุมัติ · งานด่วนยังไม่มีคนรับ · งานรอรับเกิน 24 ชม. · วัสดุใกล้หมด
 * staff  : งานถึงคิวคุณ/งานใหม่ในคิวรวม · งานที่กำลังซ่อม (ยังไม่ส่งปิด) · วัสดุใกล้หมด
 * user   : ความคืบหน้าใบงานของตัวเอง (รับงาน / ซ่อมเสร็จรอรับรอง / เสร็จสมบูรณ์ / ยกเลิก)
 *
 * ฝั่ง admin/staff เป็น "สิ่งที่ต้องทำ" คำนวณจากสถานะปัจจุบัน — หายไปเองเมื่อจัดการแล้ว
 * ฝั่งผู้แจ้งเป็น "ข่าวสาร" จาก ticket_logs (log ก่อน users.notif_seen_log_id ถือว่าอ่านแล้ว)
 *
 * อ่านทีละรายการ: ทุกรายการมี key (เรื่อง + สถานะ ณ ตอนนั้น) กดรายการไหน บันทึกลง notification_reads
 * ผ่าน notifications_seen.php แล้วรายการนั้นหายจากกระดิ่งและตัวเลขลดลง · ถ้าเรื่องนั้นเปลี่ยนขั้นตอน key เปลี่ยน
 * จึงแจ้งเตือนใหม่ได้ · ปุ่ม "อ่านทั้งหมด" บันทึกทุกรายการที่แสดงอยู่
 */

require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/stock_alert.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/i18n.php';

const NOTIF_ITEMS_PER_GROUP = 4;

/** เวลาแบบสั้น: "5 นาทีที่แล้ว", "เมื่อวาน 14:20", "12/11 09:00" */
function notif_time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '';
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return tr('เมื่อสักครู่', 'just now');
    }
    if ($diff < 3600) {
        return (int)floor($diff / 60) . tr(' นาทีที่แล้ว', ' min ago');
    }
    if (date('Y-m-d', $ts) === date('Y-m-d')) {
        return (int)floor($diff / 3600) . tr(' ชม.ที่แล้ว', ' h ago');
    }
    if (date('Y-m-d', $ts) === date('Y-m-d', strtotime('-1 day'))) {
        return tr('เมื่อวาน ', 'yesterday ') . date('H:i', $ts);
    }
    return date('d/m H:i', $ts);
}

function notif_query(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    try {
        $stmt = $conn->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    } catch (Throwable $e) {
        error_log('notif_query: ' . $e->getMessage());
        return [];
    }
}

/** key ของการแจ้งเตือนที่ผู้ใช้กดอ่านแล้ว (ยังไม่ได้รัน migration 20261122 = ว่าง ทุกรายการยังไม่อ่าน) */
function notif_read_set(mysqli $conn, int $userId): array
{
    $set = [];
    foreach (notif_query($conn, 'SELECT notif_key FROM notification_reads WHERE user_id = ?', 'i', [$userId]) as $r) {
        $set[(string)$r['notif_key']] = true;
    }
    return $set;
}

/** ตัดรายการที่อ่านแล้วออก และแนบ key ไว้ที่ $row['_key'] */
function notif_unread(array $rows, callable $keyFn, array $read): array
{
    $out = [];
    foreach ($rows as $r) {
        $k = (string)$keyFn($r);
        if (!isset($read[$k])) {
            $r['_key'] = $k;
            $out[] = $r;
            notif_all_keys($k);
        }
    }
    return $out;
}

/** ทะเบียน key ที่ยังไม่อ่านทั้งหมดของการโหลดครั้งนี้ (รวมที่ไม่ได้แสดงในกระดิ่ง) — ใช้กับ "อ่านทั้งหมด" */
function notif_all_keys(?string $add = null, bool $reset = false): array
{
    static $keys = [];
    if ($reset) {
        $keys = [];
    }
    if ($add !== null) {
        $keys[$add] = true;
    }
    return array_keys($keys);
}

/** รูปแบบ key ที่ยอมรับ (ใช้ตรวจค่าจาก client ใน notifications_seen.php ด้วย) */
function notif_key_valid(string $key): bool
{
    return (bool)preg_match('/\A[a-z_]{2,20}:[A-Za-z0-9:_.\- ]{1,96}\z/', $key);
}

/** แปลงรายการวัสดุใกล้หมดเป็นกลุ่มแจ้งเตือน */
function notif_stock_group(mysqli $conn, string $base, array $read = []): ?array
{
    $alert = stock_alert_load($conn);
    if ((int)$alert['count'] === 0) {
        return null;
    }
    // key รวมยอดคงเหลือ: อ่านแล้วแต่ยอดลดลงอีก -> แจ้งใหม่
    $all = notif_query($conn, 'SELECT id, name, unit, stock_qty, min_stock FROM inventory_items WHERE stock_qty <= min_stock ORDER BY (stock_qty = 0) DESC, stock_qty / GREATEST(min_stock, 1) ASC, name ASC');
    $rows = notif_unread($all, static fn($it) => 'stock:' . (int)$it['id'] . ':' . (int)$it['stock_qty'], $read);
    if (!$rows) {
        return null;
    }
    $items = [];
    foreach (array_slice($rows, 0, NOTIF_ITEMS_PER_GROUP) as $it) {
        $qty = (int)$it['stock_qty'];
        $items[] = [
            'key'   => $it['_key'],
            'title' => (string)$it['name'],
            'meta'  => $qty === 0 ? tr('หมดสต็อก', 'Out of stock') : tr("เหลือ {$qty} / จุดแจ้งเตือน ", "{$qty} left / alert at ") . (int)$it['min_stock'] . ' ' . $it['unit'],
            'flag'  => $qty === 0 ? tr('หมด', 'Out') : '',
            'href'  => $base . 'staff/inventory.php',
        ];
    }
    return [
        'key' => 'stock', 'icon' => 'fa-boxes-stacked', 'title' => tr('วัสดุใกล้หมด', 'Low stock'),
        'count' => count($rows), 'badge' => true,
        'href' => $base . 'staff/inventory.php', 'more_label' => tr('ดูสต็อก', 'View inventory'),
        'items' => $items,
    ];
}

/** @return array{groups: list<array>, badge: int, unread_mode: bool, sig: string} */
function notif_load(mysqli $conn, int $userId, string $role, string $base): array
{
    $groups = [];
    $unreadMode = false;
    $read = notif_read_set($conn, $userId);
    notif_all_keys(null, true);
    $slice = static fn(array $rows): array => array_slice($rows, 0, NOTIF_ITEMS_PER_GROUP);

    // ทุกบทบาทอาจเป็นผู้แจ้งเองได้: ใบงานที่รอให้ผู้ใช้คนนี้เซ็น
    $toSign = notif_unread(notif_query($conn,
        "SELECT id, ticket_no, location_note, status, stage_since, has_discrepancy, sign_round FROM tickets
          WHERE requester_id = ? AND status IN ('awaiting_intake_sign', 'awaiting_requester_sign')
          ORDER BY stage_since ASC",
        'i', [$userId]), static fn($r) => 'sign:' . $r['id'] . ':' . $r['status'] . ':' . (int)$r['sign_round'], $read);
    if ($toSign) {
        $groups[] = [
            'key' => 'to_sign', 'icon' => 'fa-signature', 'title' => tr('รอคุณเซ็น', 'Waiting for your signature'),
            'count' => count($toSign), 'badge' => true,
            'href' => $base . 'sign.php?id=' . (int)$toSign[0]['id'], 'more_label' => tr('ไปเซ็น', 'Sign now'),
            'items' => array_map(static fn($r) => [
                'key'   => $r['_key'],
                'title' => $r['status'] === 'awaiting_intake_sign'
                    ? tr('เซ็นรับทราบรายการอุปกรณ์ที่ช่างรับไป', 'Acknowledge the equipment the technician took')
                    : tr('ซ่อมเสร็จแล้ว เซ็นรับอุปกรณ์คืน', 'Repaired · sign to receive it back'),
                'meta'  => $r['ticket_no'] . ' · ' . $r['location_note'] . ' · ' . notif_time_ago($r['stage_since']),
                'flag'  => (int)$r['has_discrepancy'] === 1 && $r['status'] === 'awaiting_requester_sign' ? tr('มีข้อแตกต่าง', 'Discrepancy') : '',
                'href'  => $base . 'sign.php?id=' . (int)$r['id'],
            ], $slice($toSign)),
        ];
    }

    if ($role === 'admin') {
        $rows = notif_unread(notif_query($conn,
            "SELECT t.id, t.ticket_no, t.location_note, t.urgency, t.status, t.has_discrepancy, t.stage_since, t.sign_round,
                    CONCAT(u.first_name, ' ', u.last_name) AS staff_name
               FROM tickets t LEFT JOIN users u ON u.user_id = t.assigned_to
              WHERE t.status IN ('awaiting_admin_sign', 'disputed')
              ORDER BY t.status = 'disputed' DESC, t.stage_since ASC, t.id ASC"),
            static fn($r) => 'signoff:' . $r['id'] . ':' . $r['status'] . ':' . (int)$r['sign_round'], $read);
        if ($rows) {
            $groups[] = [
                'key' => 'signoff', 'icon' => 'fa-file-signature', 'title' => tr('รอครูรับรอง / ผู้แจ้งโต้แย้ง', 'Awaiting sign-off / disputed'),
                'count' => count($rows), 'badge' => true,
                'href' => $base . 'admin/admin_signoff.php', 'more_label' => tr('ไปหน้ารับรองงาน', 'Go to sign-off'),
                'items' => array_map(static fn($r) => [
                    'key'   => $r['_key'],
                    'title' => $r['ticket_no'] . ' · ' . $r['location_note'],
                    'meta'  => ($r['status'] === 'disputed' ? tr('ผู้แจ้งไม่ยอมรับ', 'Requester disputed') : tr('ช่าง ', 'Tech ') . trim((string)$r['staff_name']))
                        . ' · ' . notif_time_ago($r['stage_since']),
                    'flag'  => $r['status'] === 'disputed' ? tr('โต้แย้ง', 'Disputed') : ((int)$r['has_discrepancy'] === 1 ? tr('ของไม่ครบ', 'Discrepancy') : ($r['urgency'] === 'urgent' ? tr('ด่วน', 'Urgent') : '')),
                    'href'  => $base . 'admin/admin_signoff.php?id=' . (int)$r['id'],
                ], $slice($rows)),
            ];
        }

        // ผู้แจ้งยังไม่เซ็นจนเกินกำหนด -> admin ข้ามขั้นได้ (v3 4.3)
        $skipHoursIntake = (int)(notif_query($conn, "SELECT setting_value AS v FROM system_settings WHERE setting_key = 'intake_sign_skip_hours'")[0]['v'] ?? 24);
        $skipDaysReturn = (int)(notif_query($conn, "SELECT setting_value AS v FROM system_settings WHERE setting_key = 'requester_sign_skip_days'")[0]['v'] ?? 3);
        $rows = notif_unread(notif_query($conn,
            "SELECT t.id, t.ticket_no, t.location_note, t.status, t.stage_since, t.sign_round
               FROM tickets t
              WHERE (t.status = 'awaiting_intake_sign' AND ? > 0 AND t.stage_since <= NOW() - INTERVAL ? HOUR)
                 OR (t.status = 'awaiting_requester_sign' AND ? > 0 AND t.stage_since <= NOW() - INTERVAL ? DAY)
              ORDER BY t.stage_since ASC",
            'iiii', [$skipHoursIntake, $skipHoursIntake, $skipDaysReturn, $skipDaysReturn]),
            static fn($r) => 'overdue:' . $r['id'] . ':' . $r['status'] . ':' . (int)$r['sign_round'], $read);
        if ($rows) {
            $groups[] = [
                'key' => 'overdue_sign', 'icon' => 'fa-user-clock', 'title' => tr('ผู้แจ้งยังไม่เซ็น เกินกำหนด', 'Requester signature overdue'),
                'count' => count($rows), 'badge' => true,
                'href' => $base . 'admin/admin_signoff.php?view=overdue', 'more_label' => tr('ดูทั้งหมด', 'View all'),
                'items' => array_map(static fn($r) => [
                    'key'   => $r['_key'],
                    'title' => $r['ticket_no'] . ' · ' . $r['location_note'],
                    'meta'  => ($r['status'] === 'awaiting_intake_sign' ? tr('รอเซ็นรับทราบตอนรับ', 'Intake signature') : tr('รอเซ็นรับคืน', 'Return signature'))
                        . ' · ' . tr('ค้างตั้งแต่ ', 'since ') . notif_time_ago($r['stage_since']),
                    'flag'  => tr('ข้ามได้', 'Can skip'),
                    'href'  => $base . 'admin/admin_signoff.php?id=' . (int)$r['id'],
                ], $slice($rows)),
            ];
        }

        $rows = notif_unread(notif_query($conn,
            "SELECT t.id, t.ticket_no, t.location_note, t.created_at, t.queue_assigned_to
               FROM tickets t WHERE t.status = 'pending' AND t.urgency = 'urgent' ORDER BY t.created_at ASC"),
            static fn($r) => 'urgent:' . $r['id'], $read);
        if ($rows) {
            $groups[] = [
                'key' => 'urgent', 'icon' => 'fa-bolt', 'title' => tr('งานด่วน ยังไม่มีคนรับ', 'Urgent · not accepted yet'),
                'count' => count($rows), 'badge' => true,
                'href' => $base . 'admin/admin_manage_request.php?status=pending', 'more_label' => tr('ดูงานรอรับ', 'View pending'),
                'items' => array_map(static fn($r) => [
                    'key'   => $r['_key'],
                    'title' => $r['ticket_no'] . ' · ' . $r['location_note'],
                    'meta'  => tr('แจ้ง ', 'Reported ') . notif_time_ago($r['created_at']) . ($r['queue_assigned_to'] ? tr(' · อยู่ในคิวช่าง', ' · in a tech queue') : tr(' · ยังไม่ถึงคิวใคร', ' · unassigned')),
                    'flag'  => tr('ด่วน', 'Urgent'),
                    'href'  => $base . 'admin/admin_manage_request.php?status=pending&q=' . rawurlencode((string)$r['ticket_no']),
                ], $slice($rows)),
            ];
        }

        // รายการสรุปรวม: key มีจำนวนและเวลางานเก่าสุด -> มีงานค้างเพิ่ม/เปลี่ยน แจ้งใหม่
        $stale = notif_query($conn,
            "SELECT COUNT(*) AS n, MIN(created_at) AS oldest FROM tickets
              WHERE status = 'pending' AND urgency <> 'urgent' AND created_at < NOW() - INTERVAL 1 DAY");
        $staleN = (int)($stale[0]['n'] ?? 0);
        $staleRows = $staleN > 0
            ? notif_unread([$stale[0]], static fn($r) => 'stale:' . (int)$r['n'] . ':' . strtotime((string)$r['oldest']), $read)
            : [];
        if ($staleRows) {
            $groups[] = [
                'key' => 'stale', 'icon' => 'fa-hourglass-half', 'title' => tr('งานรอรับเกิน 24 ชั่วโมง', 'Waiting over 24 hours'),
                'count' => $staleN, 'badge' => true,
                'href' => $base . 'admin/admin_manage_request.php?status=pending', 'more_label' => tr('ดูงานรอรับ', 'View pending'),
                'items' => [[
                    'key'   => $staleRows[0]['_key'],
                    'title' => tr("งานปกติ {$staleN} รายการยังไม่มีช่างรับ", "{$staleN} normal request(s) not accepted yet"),
                    'meta'  => tr('เก่าสุดแจ้งเมื่อ ', 'Oldest reported ') . notif_time_ago($stale[0]['oldest'] ?? null),
                    'flag'  => '',
                    'href'  => $base . 'admin/admin_manage_request.php?status=pending',
                ]],
            ];
        }

        $rows = notif_unread(notif_query($conn,
            "SELECT user_id, first_name, last_name, user_type, created_at FROM users WHERE status = 'pending' ORDER BY created_at ASC"),
            static fn($r) => 'user:' . $r['user_id'], $read);
        if ($rows) {
            $types = ['student' => tr('นักเรียน', 'Student'), 'teacher' => tr('ครู', 'Teacher'), 'staff' => tr('บุคลากร', 'Personnel')];
            $groups[] = [
                'key' => 'users', 'icon' => 'fa-user-clock', 'title' => tr('ผู้สมัครรออนุมัติ', 'Sign-ups awaiting approval'),
                'count' => count($rows), 'badge' => true,
                'href' => $base . 'admin/admin_manage_user.php', 'more_label' => tr('ไปหน้าจัดการผู้ใช้', 'Go to users'),
                'items' => array_map(static fn($r) => [
                    'key'   => $r['_key'],
                    'title' => trim($r['first_name'] . ' ' . $r['last_name']),
                    'meta'  => ($types[$r['user_type']] ?? tr('ผู้ใช้', 'User')) . tr(' · สมัคร ', ' · registered ') . notif_time_ago($r['created_at']),
                    'flag'  => '',
                    'href'  => $base . 'admin/admin_manage_user.php',
                ], $slice($rows)),
            ];
        }

        if ($g = notif_stock_group($conn, $base, $read)) {
            $groups[] = $g;
        }
    } elseif ($role === 'staff') {
        // key รวมคนที่ถึงคิว: จากคิวรวมกลายเป็นถึงคิวคุณ -> แจ้งใหม่
        $rows = notif_unread(notif_query($conn,
            "SELECT t.id, t.ticket_no, t.location_note, t.urgency, t.created_at, t.queue_assigned_to, t.queue_expires_at
               FROM tickets t
              WHERE t.status = 'pending' AND (t.queue_assigned_to = ? OR t.queue_assigned_to IS NULL)
              ORDER BY (t.queue_assigned_to = ?) DESC, t.urgency = 'urgent' DESC, t.created_at ASC",
            'ii', [$userId, $userId]),
            static fn($r) => 'job:' . $r['id'] . ':' . (int)$r['queue_assigned_to'], $read);
        if ($rows) {
            $mine = count(array_filter($rows, static fn($r) => (int)$r['queue_assigned_to'] === $userId));
            $groups[] = [
                'key' => 'jobs', 'icon' => 'fa-inbox', 'title' => $mine > 0 ? tr("งานแจ้งเข้า · ถึงคิวคุณ {$mine}", "New jobs · {$mine} in your queue") : tr('งานแจ้งเข้า รอรับ', 'New jobs to accept'),
                'count' => count($rows), 'badge' => true,
                'href' => $base . 'staff/staff_dashboard.php', 'more_label' => tr('ไปหน้างานของฉัน', 'Go to my jobs'),
                'items' => array_map(static function ($r) use ($userId, $base) {
                    $isMine = (int)$r['queue_assigned_to'] === $userId;
                    $meta = tr('แจ้ง ', 'Reported ') . notif_time_ago($r['created_at']);
                    if ($isMine && $r['queue_expires_at']) {
                        $meta = tr('ถึงคิวคุณ · รับภายใน ', 'Your turn · accept by ') . date('H:i', (int)strtotime($r['queue_expires_at'])) . tr(' น.', '');
                    } elseif (!$isMine) {
                        $meta .= tr(' · คิวรวม', ' · open queue');
                    }
                    return [
                        'key'   => $r['_key'],
                        'title' => $r['ticket_no'] . ' · ' . $r['location_note'],
                        'meta'  => $meta,
                        'flag'  => $r['urgency'] === 'urgent' ? tr('ด่วน', 'Urgent') : '',
                        'href'  => $base . 'staff/staff_dashboard.php',
                    ];
                }, $slice($rows)),
            ];
        }

        $rows = notif_unread(notif_query($conn,
            "SELECT t.id, t.ticket_no, t.location_note, t.urgency, t.status, t.stage_since, t.return_reason, t.sign_round
               FROM tickets t
              WHERE t.status IN ('accepted', 'awaiting_intake_sign', 'in_progress', 'inspected_ready')
                AND (t.assigned_to = ? OR EXISTS (SELECT 1 FROM ticket_assignees a WHERE a.ticket_id = t.id AND a.user_id = ?))
              ORDER BY t.status IN ('accepted', 'inspected_ready') DESC, t.urgency = 'urgent' DESC, t.stage_since ASC",
            'ii', [$userId, $userId]),
            static fn($r) => 'work:' . $r['id'] . ':' . $r['status'] . ':' . (int)$r['sign_round'], $read);
        if ($rows) {
            $next = [
                'accepted'             => tr('ต้องบันทึกเช็คลิสต์ตอนรับ', 'Record the intake checklist'),
                'awaiting_intake_sign' => tr('รอผู้แจ้งเซ็นรับทราบเช็คลิสต์', 'Waiting for requester to sign intake'),
                'in_progress'          => tr('กำลังซ่อม', 'Repairing'),
                'inspected_ready'      => tr('ตรวจซ้ำแล้ว ต้องเซ็นส่งมอบ', 'Re-checked · sign the handover'),
            ];
            $todo = count(array_filter($rows, static fn($r) => in_array($r['status'], ['accepted', 'inspected_ready'], true) || ($r['status'] === 'in_progress' && (int)$r['sign_round'] > 1)));
            $groups[] = [
                'key' => 'working', 'icon' => 'fa-screwdriver-wrench',
                'title' => $todo > 0 ? tr("งานของฉัน · ต้องทำต่อ {$todo}", "My jobs · {$todo} need action") : tr('งานของฉันที่ยังไม่ปิด', 'My open jobs'),
                'count' => $todo > 0 ? $todo : count($rows), 'badge' => $todo > 0,
                'href' => $base . 'staff/staff_dashboard.php', 'more_label' => tr('ไปหน้างานของฉัน', 'Go to my jobs'),
                'items' => array_map(static fn($r) => [
                    'key'   => $r['_key'],
                    'title' => $r['ticket_no'] . ' · ' . $r['location_note'],
                    'meta'  => ((int)$r['sign_round'] > 1 && $r['status'] === 'in_progress' ? tr('ครูส่งกลับให้แก้', 'Returned by teacher') : ($next[$r['status']] ?? $r['status']))
                        . ' · ' . notif_time_ago($r['stage_since']),
                    'flag'  => $r['urgency'] === 'urgent' ? tr('ด่วน', 'Urgent') : '',
                    'href'  => $base . 'staff/job.php?id=' . (int)$r['id'],
                ], $slice($rows)),
            ];
        }

        // คำขอบคุณจากผู้แจ้งที่รอ Lab boy ยืนยันว่าได้รับ
        $rows = notif_unread(notif_query($conn,
            "SELECT th.id, th.kind, th.amount, th.item_note, th.created_at, t.ticket_no
               FROM ticket_thanks th JOIN tickets t ON t.id = th.ticket_id
              WHERE th.to_user_id = ? AND th.status = 'pending'
              ORDER BY th.id DESC",
            'i', [$userId]), static fn($r) => 'thanks:' . $r['id'], $read);
        if ($rows) {
            require_once __DIR__ . '/thanks.php';
            $groups[] = [
                'key' => 'thanks', 'icon' => 'fa-heart', 'title' => tr('คำขอบคุณรอยืนยัน', 'Thanks to confirm'),
                'count' => count($rows), 'badge' => true,
                'href' => $base . 'staff/thanks.php', 'more_label' => tr('ไปหน้าคำขอบคุณ', 'Go to thanks'),
                'items' => array_map(static fn($r) => [
                    'key'   => $r['_key'],
                    'title' => thanks_describe($r + ['item_note' => '']),
                    'meta'  => $r['ticket_no'] . ' · ' . notif_time_ago($r['created_at']),
                    'flag'  => '',
                    'href'  => $base . 'staff/thanks.php',
                ], $slice($rows)),
            ];
        }

        if ($g = notif_stock_group($conn, $base, $read)) {
            $groups[] = $g;
        }
    } else {
        $unreadMode = true;
        // log ก่อน notif_seen_log_id ถือว่าอ่านแล้ว (กด "อ่านทั้งหมด" หรือข้อมูลก่อนมีระบบอ่านทีละรายการ)
        $seen = notif_query($conn, 'SELECT notif_seen_log_id FROM users WHERE user_id = ?', 'i', [$userId]);
        $seenId = (int)($seen[0]['notif_seen_log_id'] ?? 0);
        $rows = notif_unread(notif_query($conn,
            "SELECT l.id AS log_id, l.new_status, l.created_at, t.ticket_no, t.location_note, t.cancel_reason
               FROM ticket_logs l JOIN tickets t ON t.id = l.ticket_id
              WHERE t.requester_id = ? AND (l.action_by IS NULL OR l.action_by <> ?) AND l.id > ?
                AND l.new_status IN ('accepted', 'in_progress', 'inspected_ready', 'completed', 'cancelled')
                AND l.created_at >= NOW() - INTERVAL 30 DAY
              ORDER BY l.id DESC LIMIT 50",
            'iii', [$userId, $userId, $seenId]), static fn($r) => 'log:' . $r['log_id'], $read);
        if ($rows) {
            $text = [
                'accepted'        => tr('ช่างรับงานแล้ว กำลังตรวจรับอุปกรณ์', 'A technician accepted your request'),
                'in_progress'     => tr('ช่างเริ่มซ่อมแล้ว', 'Repair started'),
                'inspected_ready' => tr('ซ่อมเสร็จ ช่างตรวจอุปกรณ์ก่อนส่งคืนแล้ว', 'Repaired and re-checked'),
                'completed'       => tr('งานเสร็จสมบูรณ์ ครูรับรองแล้ว', 'Completed and signed off'),
                'cancelled'       => tr('ใบงานถูกยกเลิก', 'Request cancelled'),
            ];
            $groups[] = [
                'key' => 'updates', 'icon' => 'fa-clipboard-check', 'title' => tr('ความคืบหน้าใบงานของคุณ', 'Updates on your requests'),
                'count' => count($rows), 'badge' => true,
                'href' => $base . 'history.php', 'more_label' => tr('ดูประวัติทั้งหมด', 'View all history'),
                'items' => array_map(static fn($r) => [
                    'key'   => $r['_key'],
                    'title' => ($text[$r['new_status']] ?? $r['new_status']),
                    'meta'  => $r['ticket_no'] . ' · ' . $r['location_note'] . ' · ' . notif_time_ago($r['created_at'])
                        . ($r['new_status'] === 'cancelled' && $r['cancel_reason'] ? ' · ' . $r['cancel_reason'] : ''),
                    'flag'  => '',
                    'unread' => true,
                    'href'  => $base . 'history.php?q=' . rawurlencode((string)$r['ticket_no']),
                ], array_slice($rows, 0, 8)),
            ];
        }
    }

    $badge = 0;
    foreach ($groups as $g) {
        if ($g['badge']) {
            $badge += (int)$g['count'];
        }
    }
    $sig = substr(hash('sha256', json_encode(array_map(
        static fn($g) => [$g['key'], $g['count'], array_column($g['items'], 'title')],
        $groups
    ))), 0, 16);

    return ['groups' => $groups, 'badge' => $badge, 'unread_mode' => $unreadMode, 'sig' => $sig];
}

/** กระดิ่งพร้อม popover — เรียกครั้งเดียวต่อหน้า */
function notif_bell(mysqli $conn, string $base = ''): string
{
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        return '';
    }
    $role = normalize_role((string)($_SESSION['role'] ?? 'user'));
    $data = notif_load($conn, $userId, $role, $base);
    $h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $badge = (int)$data['badge'];
    $label = $badge > 0
        ? ($data['unread_mode'] ? tr("การแจ้งเตือน: ยังไม่อ่าน {$badge} รายการ", "Notifications: {$badge} unread") : tr("การแจ้งเตือน: ต้องดำเนินการ {$badge} รายการ", "Notifications: {$badge} need action"))
        : tr('การแจ้งเตือน: ไม่มีรายการใหม่', 'Notifications: nothing new');

    ob_start(); ?>
<style>
.notif__readall { display: inline-flex; align-items: center; gap: 5px; min-height: 32px; padding: 0 8px; border: 0; border-radius: 8px; background: transparent; color: #57544F; font: inherit; font-size: 12.5px; font-weight: 500; cursor: pointer; }
.notif__readall:hover { background: #F4F3F0; color: #161616; }
.notif__readall:focus-visible { outline: 2px solid #161616; outline-offset: 1px; }
.stock-alert__empty[hidden] { display: none; }
.stock-alert__head { align-items: center; }
</style>
<details class="stock-alert notif" data-notif data-sig="<?= $h($data['sig']) ?>" data-seen-url="<?= $h($base . 'notifications_seen.php') ?>" data-csrf="<?= $h(app_csrf_token()) ?>" data-badge="<?= $badge ?>">
    <summary class="stock-alert__btn" aria-label="<?= $h($label) ?>" title="<?= $h($label) ?>">
        <?= app_icon('bell', 'stock-alert__icon') ?>
        <?php if ($badge > 0): ?>
            <span class="stock-alert__count" aria-hidden="true"><?= $badge > 99 ? '99+' : $badge ?></span>
            <span class="stock-alert__ping" aria-hidden="true"></span>
        <?php endif; ?>
    </summary>
    <div class="stock-alert__panel notif__panel" role="region" aria-label="<?= tr('การแจ้งเตือน', 'Notifications') ?>">
        <div class="stock-alert__head">
            <span class="stock-alert__title"><?= tr('การแจ้งเตือน', 'Notifications') ?></span>
            <?php if ($data['groups']): ?>
                <button type="button" class="notif__readall" data-notif-readall><?= app_icon('check') ?> <?= tr('อ่านทั้งหมด', 'Mark all read') ?></button>
            <?php endif; ?>
        </div>
        <div class="stock-alert__empty" data-notif-empty<?= $data['groups'] ? ' hidden' : '' ?>>
            <?= app_icon('check', 'stock-alert__empty-icon') ?>
            <span><?= tr('ไม่มีการแจ้งเตือนใหม่', 'No new notifications') ?></span>
        </div>
        <?php if ($data['groups']): ?>
            <div class="notif__body">
                <?php $k = 0; foreach ($data['groups'] as $g): ?>
                    <section class="notif__group" data-notif-group data-counted="<?= $g['badge'] ? '1' : '0' ?>">
                        <div class="notif__group-head">
                            <span class="notif__group-icon<?= $g['badge'] ? ' is-action' : '' ?>" aria-hidden="true"><i class="fa-solid <?= $h($g['icon']) ?>"></i></span>
                            <span class="notif__group-title"><?= $h($g['title']) ?></span>
                            <?php if ((int)$g['count'] > 0): ?><span class="notif__group-count<?= $g['badge'] ? ' is-action' : '' ?>" data-notif-gcount><?= (int)$g['count'] ?></span><?php endif; ?>
                        </div>
                        <ul class="notif__list">
                            <?php foreach ($g['items'] as $it): ?>
                                <li style="--k:<?= $k++ ?>">
                                    <a class="notif__item<?= !empty($it['unread']) ? ' is-unread' : '' ?>" href="<?= $h($it['href']) ?>" data-notif-key="<?= $h($it['key'] ?? '') ?>">
                                        <span class="notif__item-title"><?= $h($it['title']) ?><?php if ($it['flag'] !== ''): ?> <span class="notif__flag"><?= $h($it['flag']) ?></span><?php endif; ?></span>
                                        <span class="notif__item-meta"><?= $h($it['meta']) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ((int)$g['count'] > count($g['items']) || $g['key'] === 'stale'): ?>
                            <a class="notif__more" href="<?= $h($g['href']) ?>"><?= $h($g['more_label']) ?><?= (int)$g['count'] > count($g['items']) && $g['key'] !== 'stale' ? ' (' . tr('อีก ', '+') . ((int)$g['count'] - count($g['items'])) . ')' : '' ?> <?= app_icon('arrow-right') ?></a>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</details>
<script>
(function () {
    var el = document.currentScript && document.currentScript.previousElementSibling;
    if (!el || !el.matches('[data-notif]')) return;
    var KEY = 'reps.notifSeen';
    var sig = el.getAttribute('data-sig') || '';
    var url = el.getAttribute('data-seen-url');
    var csrf = el.getAttribute('data-csrf') || '';
    var badge = parseInt(el.getAttribute('data-badge') || '0', 10);
    var LABEL_NONE = <?= json_encode(tr('การแจ้งเตือน: ไม่มีรายการใหม่', 'Notifications: nothing new'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    var LABEL_N = <?= json_encode(tr('การแจ้งเตือน: {n} รายการ', 'Notifications: {n}'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    var seen = '';
    try { seen = localStorage.getItem(KEY) || ''; } catch (e) {}
    if (el.querySelector('.stock-alert__count') && seen !== sig) el.classList.add('is-new');
    el.addEventListener('toggle', function () {
        if (!el.open) return;
        el.classList.remove('is-new');
        try { localStorage.setItem(KEY, sig); } catch (e) {}
    });

    // บันทึกว่าอ่านแล้ว: sendBeacon ส่งได้แม้หน้ากำลังเปลี่ยนไปหน้าใหม่
    function send(params) {
        var fd = new FormData();
        fd.append('csrf', csrf);
        Object.keys(params).forEach(function (k) {
            [].concat(params[k]).forEach(function (v) { fd.append(k, v); });
        });
        if (navigator.sendBeacon && navigator.sendBeacon(url, fd)) return Promise.resolve();
        return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', keepalive: true });
    }
    function setBadge(n) {
        badge = Math.max(0, n);
        var c = el.querySelector('.stock-alert__count');
        var s = el.querySelector('summary');
        if (badge === 0) {
            if (c) c.remove();
            var p = el.querySelector('.stock-alert__ping');
            if (p) p.remove();
        } else if (c) {
            c.textContent = badge > 99 ? '99+' : String(badge);
        }
        var label = badge === 0 ? LABEL_NONE : LABEL_N.replace('{n}', badge);
        s.setAttribute('aria-label', label);
        s.setAttribute('title', label);
    }
    function removeItem(a) {
        var li = a.closest('li');
        var group = a.closest('[data-notif-group]');
        if (li) li.remove();
        if (!group) return;
        var gc = group.querySelector('[data-notif-gcount]');
        var left = gc ? parseInt(gc.textContent, 10) - 1 : 0;
        if (gc) {
            if (left > 0) gc.textContent = String(left); else gc.remove();
        }
        if (group.getAttribute('data-counted') === '1') setBadge(badge - 1);
        if (!group.querySelector('[data-notif-key]') && !group.querySelector('.notif__more')) group.remove();
        if (!el.querySelector('[data-notif-group]')) showEmpty();
    }
    function showEmpty() {
        var body = el.querySelector('.notif__body');
        if (body) body.remove();
        var btn = el.querySelector('[data-notif-readall]');
        if (btn) btn.remove();
        var empty = el.querySelector('[data-notif-empty]');
        if (empty) empty.hidden = false;
    }

    el.addEventListener('click', function (ev) {
        var a = ev.target.closest && ev.target.closest('a[data-notif-key]');
        if (a && a.getAttribute('data-notif-key')) {
            send({ key: a.getAttribute('data-notif-key') });
            removeItem(a);
            return;
        }
        if (ev.target.closest && ev.target.closest('[data-notif-readall]')) {
            ev.preventDefault();
            send({ all: '1' }).then(function () {
                el.querySelectorAll('[data-notif-group]').forEach(function (g) { g.remove(); });
                setBadge(0);
                showEmpty();
            }).catch(function () {});
        }
    });
    document.addEventListener('click', function (ev) {
        if (el.open && !el.contains(ev.target)) el.open = false;
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && el.open) {
            el.open = false;
            el.querySelector('summary').focus();
        }
    });
})();
</script>
<?php
    return (string)ob_get_clean();
}
