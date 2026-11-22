<?php
declare(strict_types=1);

require_once __DIR__ . '/db_helpers.php';
require_once __DIR__ . '/queue_manager.php';
require_once __DIR__ . '/private_storage.php';
require_once __DIR__ . '/ticket_mail.php';

/**
 * ขั้นตอนของใบงาน (feedback-todo-v3 หัวข้อ 3–4)
 *
 *   pending                 รอรับเรื่อง/รอคิว
 *   accepted                สตาฟรับงานแล้ว ต้องบันทึกเช็คลิสต์อุปกรณ์ตอนรับ
 *   awaiting_intake_sign    รอผู้แจ้งเซ็นรับทราบเช็คลิสต์ (admin ข้ามได้เมื่อเกิน intake_sign_skip_hours)
 *   in_progress             กำลังซ่อม
 *   inspected_ready         ซ่อมเสร็จ ตรวจซ้ำเทียบเช็คลิสต์แล้ว รอสตาฟเซ็นส่งมอบ
 *   awaiting_requester_sign รอผู้แจ้งเซ็นรับคืน (ไม่ยอมรับได้ -> disputed; admin ข้ามได้เมื่อเกิน requester_sign_skip_days)
 *   awaiting_admin_sign     รอครู (admin) รับรอง -> completed หรือส่งกลับให้สตาฟแก้ (รอบเซ็นใหม่)
 *   disputed                ผู้แจ้งไม่ยอมรับ รอ admin พิจารณา (ส่งกลับแก้ หรือรับรองพร้อมเหตุผล)
 *   completed               ลายเซ็นส่งคืนครบ 3 ฝ่าย (สตาฟ ผู้แจ้งหรือการข้ามขั้นที่บันทึกไว้ admin)
 *   cancelled               ยกเลิก
 *
 * ลายเซ็นและหลักฐานเช็คลิสต์แก้ย้อนหลังไม่ได้ การส่งกลับเพิ่ม sign_round และลายเซ็นรอบก่อนยังอยู่ครบ
 * ทุกฟังก์ชันตรวจสถานะด้วย SELECT ... FOR UPDATE ในทรานแซกชัน และตรวจว่าผู้กระทำเป็นบุคคล/บทบาทที่ถูกต้องของใบงาน
 * อีเมลส่งหลัง commit เสมอ (ส่งไม่สำเร็จไม่กระทบขั้นตอน)
 */

const TICKET_STATUS_LABELS = [
    'pending'                 => 'รอรับเรื่อง',
    'accepted'                => 'รับแล้ว รอบันทึกเช็คลิสต์',
    'awaiting_intake_sign'    => 'รอผู้แจ้งเซ็นรับทราบ',
    'in_progress'             => 'กำลังซ่อม',
    'inspected_ready'         => 'ตรวจซ้ำแล้ว รอส่งมอบ',
    'awaiting_requester_sign' => 'รอผู้แจ้งเซ็นรับคืน',
    'awaiting_admin_sign'     => 'รอครูรับรอง',
    'disputed'                => 'ผู้แจ้งไม่ยอมรับ',
    'completed'               => 'เสร็จสมบูรณ์',
    'cancelled'               => 'ยกเลิก',
];

/** สถานะที่ทีมช่างยังถืองานอยู่ (นับเป็น "งานที่กำลังทำ" ของสตาฟ) */
const TICKET_STAFF_ACTIVE_STATUSES = ['accepted', 'awaiting_intake_sign', 'in_progress', 'inspected_ready'];
/** สถานะระหว่างรอเซ็นตอนส่งคืน/ปิดงาน */
const TICKET_SIGNING_STATUSES = ['awaiting_requester_sign', 'awaiting_admin_sign', 'disputed'];
/** สถานะที่ยังไม่ปิด (ไม่ใช่ completed/cancelled) */
const TICKET_OPEN_STATUSES = ['pending', 'accepted', 'awaiting_intake_sign', 'in_progress', 'inspected_ready',
    'awaiting_requester_sign', 'awaiting_admin_sign', 'disputed'];

/** กลุ่มสถานะสำหรับสรุป/กรอง (ใช้ร่วมกันทุกหน้า) */
const TICKET_STATUS_GROUPS = [
    'pending'   => ['pending'],
    'working'   => TICKET_STAFF_ACTIVE_STATUSES,
    'signing'   => TICKET_SIGNING_STATUSES,
    'completed' => ['completed'],
    'cancelled' => ['cancelled'],
];
const TICKET_GROUP_LABELS = [
    'pending'   => 'รอรับเรื่อง',
    'working'   => 'กำลังดำเนินการ',
    'signing'   => 'รอเซ็น/รับรอง',
    'completed' => 'เสร็จสมบูรณ์',
    'cancelled' => 'ยกเลิก',
];

function ticket_status_group(string $status): string
{
    foreach (TICKET_STATUS_GROUPS as $group => $statuses) {
        if (in_array($status, $statuses, true)) {
            return $group;
        }
    }
    return $status;
}

/** SQL literal ของรายการสถานะ (ค่าคงที่ในโค้ดเท่านั้น ไม่ใช่ข้อมูลจากผู้ใช้) */
function ticket_status_sql(array $statuses): string
{
    return implode(', ', array_map(static fn($s) => "'" . preg_replace('/[^a-z_]/', '', (string)$s) . "'", $statuses));
}

const ITEM_CONDITION_LABELS = ['normal' => 'ปกติ', 'scratched' => 'มีรอย', 'damaged' => 'ชำรุดอยู่เดิม'];
const ITEM_RESULT_LABELS = ['returned' => 'คืนครบ', 'missing' => 'ไม่พบ (หาย)', 'changed' => 'สภาพเปลี่ยน'];
const SIGNER_ROLE_LABELS = ['staff' => 'สตาฟผู้รับงาน', 'requester' => 'ผู้แจ้ง', 'admin' => 'ครูผู้รับรอง'];

/** สร้าง placeholder ของ IN (...) สำหรับ prepared statement */
function sql_in(array $values): string
{
    return implode(',', array_fill(0, max(1, count($values)), '?'));
}

/** ทีมช่างของใบงาน: ช่างหลัก (ถ้ามี) + ผู้ช่วย ไม่ซ้ำกัน */
function ticket_team_ids(mysqli $conn, int $ticketId, int $leadId = 0): array
{
    $ids = $leadId > 0 ? [$leadId] : [];
    foreach (db_all($conn, 'SELECT user_id FROM ticket_assignees WHERE ticket_id = ?', 'i', [$ticketId]) as $row) {
        $ids[] = (int)$row['user_id'];
    }
    return array_values(array_unique($ids));
}

/**
 * admin กำหนดทีมช่าง
 * - รอรับเรื่อง: ส่งคิวให้ช่างหลัก (0 = คิวกลาง) และกำหนดผู้ช่วย
 * - ระหว่างทีมช่างถืองาน: ช่างหลักคงเดิม ปรับรายชื่อผู้ช่วยได้
 * @param int[] $helperIds
 */
function ticket_assign_team(mysqli $conn, int $ticketId, int $leadId, array $helperIds, int $adminId): string
{
    $q = queue_settings($conn);
    return db_transaction($conn, function () use ($conn, $ticketId, $leadId, $helperIds, $adminId, $q) {
        $ticket = db_one($conn, 'SELECT status, assigned_to FROM tickets WHERE id = ? FOR UPDATE', 'i', [$ticketId]);
        if (!$ticket || !in_array($ticket['status'], array_merge(['pending'], TICKET_STAFF_ACTIVE_STATUSES), true)) {
            throw new AppUserException('มอบหมายได้เฉพาะใบงานที่รอรับเรื่องหรือทีมช่างกำลังดำเนินการ');
        }
        $activeStaff = array_map(
            'intval',
            array_column(db_all($conn, "SELECT u.user_id FROM users u WHERE u.role = 'staff' AND u.status = 'active'" . term_staff_sql($conn)), 'user_id')
        );
        if ($ticket['status'] === 'pending') {
            if ($leadId > 0 && !in_array($leadId, $activeStaff, true)) {
                throw new AppUserException('ช่างหลักที่เลือกไม่ใช่สตาฟที่ใช้งานอยู่');
            }
            $expires = $leadId > 0 && $q['mode'] === 'auto' ? queue_expiry_from_now($q) : null;
            db_exec(
                $conn,
                'UPDATE tickets SET queue_assigned_to = ?, queue_expires_at = ? WHERE id = ?',
                'isi',
                [$leadId > 0 ? $leadId : null, $expires, $ticketId]
            );
            $lead = $leadId;
        } else {
            $lead = (int)$ticket['assigned_to'];
        }

        $helpers = array_values(array_unique(array_filter(
            array_map('intval', $helperIds),
            static fn($id) => $id > 0 && $id !== $lead && in_array($id, $activeStaff, true)
        )));
        db_exec($conn, 'DELETE FROM ticket_assignees WHERE ticket_id = ?', 'i', [$ticketId]);
        foreach ($helpers as $helperId) {
            db_exec($conn, 'INSERT INTO ticket_assignees (ticket_id, user_id, added_by) VALUES (?, ?, ?)', 'iii', [$ticketId, $helperId, $adminId]);
        }
        $leadText = $ticket['status'] === 'pending'
            ? ($lead > 0 ? 'ส่งคิวให้ช่างหลักแล้ว รอกดรับงาน' : 'ปล่อยเป็นคิวกลาง')
            : 'ปรับทีมช่างแล้ว';
        return $leadText . (count($helpers) ? ' · ผู้ช่วย ' . count($helpers) . ' คน' : '');
    });
}

function ticket_log(mysqli $conn, int $ticketId, ?string $old, string $new, ?int $actorId, string $source = 'web', ?string $note = null): void
{
    $note = $note !== null && $note !== '' ? mb_substr($note, 0, 500, 'UTF-8') : null;
    db_exec(
        $conn,
        'INSERT INTO ticket_logs (ticket_id, old_status, new_status, action_by, source, note) VALUES (?, ?, ?, ?, ?, ?)',
        'ississ',
        [$ticketId, $old, $new, $actorId, $source, $note]
    );
}

/** เปลี่ยนสถานะแบบมีเงื่อนไข (ต้องอยู่ในทรานแซกชันที่ล็อกแถวไว้แล้ว) */
function ticket_move(mysqli $conn, int $ticketId, string $from, string $to, ?int $actorId, string $source, ?string $note = null): void
{
    $n = db_exec($conn, 'UPDATE tickets SET status = ?, stage_since = NOW() WHERE id = ? AND status = ?', 'sis', [$to, $ticketId, $from]);
    if ($n !== 1) {
        throw new AppUserException('สถานะใบงานเปลี่ยนไปแล้ว กรุณาโหลดหน้าใหม่');
    }
    ticket_log($conn, $ticketId, $from, $to, $actorId, $source, $note);
}

/** ล็อกแถวใบงานและตรวจสถานะที่อนุญาต */
function ticket_lock(mysqli $conn, int $ticketId, array $allowedStatuses): array
{
    $t = db_one($conn, 'SELECT * FROM tickets WHERE id = ? FOR UPDATE', 'i', [$ticketId]);
    if (!$t) {
        throw new AppUserException('ไม่พบใบงาน');
    }
    if (!in_array($t['status'], $allowedStatuses, true)) {
        throw new AppUserException('ใบงานอยู่ในสถานะ "' . (TICKET_STATUS_LABELS[$t['status']] ?? $t['status']) . '" ทำรายการนี้ไม่ได้');
    }
    return $t;
}

function ticket_is_team_member(mysqli $conn, array $ticket, int $userId): bool
{
    if ((int)$ticket['assigned_to'] === $userId) {
        return true;
    }
    return (bool)db_one($conn, 'SELECT 1 FROM ticket_assignees WHERE ticket_id = ? AND user_id = ?', 'ii', [(int)$ticket['id'], $userId]);
}

function ticket_require_team(mysqli $conn, array $ticket, int $userId): void
{
    if (!ticket_is_team_member($conn, $ticket, $userId)) {
        throw new AppUserException('ใบงานนี้ไม่ได้อยู่ในความรับผิดชอบของคุณ');
    }
}

function ticket_require_admin(mysqli $conn, int $userId): void
{
    if (!db_one($conn, "SELECT 1 FROM users WHERE user_id = ? AND role = 'admin' AND status = 'active'", 'i', [$userId])) {
        throw new AppUserException('เฉพาะครูผู้ดูแลระบบเท่านั้นที่ทำรายการนี้ได้');
    }
}

function ticket_setting_int(mysqli $conn, string $key, int $default): int
{
    $row = db_one($conn, 'SELECT setting_value AS v FROM system_settings WHERE setting_key = ?', 's', [$key]);
    return $row !== null && is_numeric($row['v']) ? (int)$row['v'] : $default;
}

function ticket_signoff_insert(mysqli $conn, int $ticketId, string $role, string $stage, int $round, int $signerId, ?string $sigPath, string $method, ?string $note): void
{
    $note = $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 255, 'UTF-8') : null;
    try {
        db_exec(
            $conn,
            'INSERT INTO ticket_signoffs (ticket_id, signer_role, stage, round_no, signer_id, signature_image, method, ip, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'ississsss',
            [$ticketId, $role, $stage, $round, $signerId, $sigPath, $method, client_ip(), $note]
        );
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() === 1062) {
            throw new AppUserException('ขั้นตอนนี้มีการเซ็นไปแล้ว');
        }
        throw $e;
    }
}

/** ใบงานถึงกำหนดที่ admin ข้ามขั้นผู้แจ้งได้หรือยัง — คืน [ได้/ไม่ได้, ข้อความอธิบาย] */
function ticket_skip_window(mysqli $conn, array $ticket): array
{
    $isIntake = $ticket['status'] === 'awaiting_intake_sign';
    $limitHours = $isIntake
        ? ticket_setting_int($conn, 'intake_sign_skip_hours', 24)
        : ticket_setting_int($conn, 'requester_sign_skip_days', 3) * 24;
    if ($limitHours <= 0) {
        return [false, 'ระบบตั้งค่าไม่ให้ข้ามขั้นผู้แจ้ง'];
    }
    $since = strtotime((string)($ticket['stage_since'] ?? '')) ?: time();
    $due = $since + $limitHours * 3600;
    if (time() < $due) {
        return [false, 'ข้ามขั้นผู้แจ้งได้ตั้งแต่ ' . date('d/m/Y H:i', $due) . ' น.'];
    }
    return [true, ''];
}

/**
 * สตาฟ/admin กดรับงาน: รับได้เฉพาะงานคิวกลาง หรืองานที่ถึงคิวของตัวเอง -> accepted (ต้องบันทึกเช็คลิสต์ต่อ)
 */
function ticket_accept(mysqli $conn, int $ticketId, int $staffId): bool
{
    // สตาฟที่ไม่ได้อยู่ในทีมภาคเรียนปัจจุบันรับงานไม่ได้ (admin รับงานแทนได้เสมอ)
    $role = db_one($conn, 'SELECT role FROM users WHERE user_id = ?', 'i', [$staffId])['role'] ?? '';
    if ($role === 'staff' && !term_staff_allowed($conn, $staffId)) {
        throw new AppUserException('คุณไม่ได้อยู่ในทีมสตาฟของ' . term_title(term_current($conn)) . ' จึงรับงานไม่ได้ กรุณาติดต่อครูผู้ดูแล');
    }
    return db_transaction($conn, function () use ($conn, $ticketId, $staffId) {
        $affected = db_exec(
            $conn,
            "UPDATE tickets
                SET status = 'accepted', assigned_to = ?, queue_assigned_to = NULL, queue_expires_at = NULL,
                    accepted_at = NOW(), stage_since = NOW()
              WHERE id = ? AND status = 'pending' AND (queue_assigned_to IS NULL OR queue_assigned_to = ?)",
            'iii',
            [$staffId, $ticketId, $staffId]
        );
        if ($affected !== 1) {
            return false;
        }
        db_exec($conn, 'DELETE FROM ticket_assignees WHERE ticket_id = ? AND user_id = ?', 'ii', [$ticketId, $staffId]);
        ticket_log($conn, $ticketId, 'pending', 'accepted', $staffId, 'staff');
        return true;
    });
}

/**
 * บันทึกเช็คลิสต์ตอนรับ (v3 3.1) -> awaiting_intake_sign แล้วอีเมลให้ผู้แจ้งเซ็นรับทราบ
 * @param list<array{name:string,type:string,serial:string,qty:int,condition:string,note:string,photo:?string}> $items
 */
function ticket_save_intake(mysqli $conn, int $ticketId, int $staffId, array $items, string $note, ?string $photo): void
{
    if (!$items) {
        throw new AppUserException('ต้องมีอุปกรณ์อย่างน้อย 1 รายการในเช็คลิสต์');
    }
    if (!array_filter($items, static fn($i) => $i['type'] === 'main' && $i['qty'] > 0)) {
        throw new AppUserException('ต้องระบุอุปกรณ์หลักอย่างน้อย 1 รายการที่รับมา');
    }
    db_transaction($conn, function () use ($conn, $ticketId, $staffId, $items, $note, $photo) {
        $t = ticket_lock($conn, $ticketId, ['accepted']);
        ticket_require_team($conn, $t, $staffId);
        if (db_one($conn, 'SELECT 1 FROM ticket_item_checks WHERE ticket_id = ? LIMIT 1', 'i', [$ticketId])) {
            throw new AppUserException('ใบงานนี้บันทึกเช็คลิสต์ตอนรับไปแล้ว');
        }
        foreach ($items as $i => $it) {
            db_exec(
                $conn,
                'INSERT INTO ticket_item_checks (ticket_id, item_name, item_type, serial_no, sort_order, qty_in, condition_in, note_in, photo_in, received_by, received_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                'isssiisssi',
                [$ticketId, $it['name'], $it['type'], $it['serial'] !== '' ? $it['serial'] : null, $i, $it['qty'], $it['condition'],
                    $it['note'] !== '' ? $it['note'] : null, $it['photo'], $staffId]
            );
        }
        db_exec($conn, 'UPDATE tickets SET intake_note = ?, intake_photo = ? WHERE id = ?', 'ssi', [$note !== '' ? $note : null, $photo, $ticketId]);
        $received = count(array_filter($items, static fn($i) => $i['qty'] > 0));
        ticket_move($conn, $ticketId, 'accepted', 'awaiting_intake_sign', $staffId, 'staff', "บันทึกเช็คลิสต์ตอนรับ {$received} รายการ");
    });
    ticket_email($conn, $ticketId, 'intake_recorded');
}

/** ผู้แจ้งเซ็นรับทราบเช็คลิสต์ตอนรับ -> in_progress */
function ticket_requester_sign_intake(mysqli $conn, int $ticketId, int $userId, string $sigPath, string $method, string $note): void
{
    db_transaction($conn, function () use ($conn, $ticketId, $userId, $sigPath, $method, $note) {
        $t = ticket_lock($conn, $ticketId, ['awaiting_intake_sign']);
        if ((int)$t['requester_id'] !== $userId) {
            throw new AppUserException('เฉพาะผู้แจ้งของใบงานนี้เท่านั้นที่เซ็นรับทราบได้');
        }
        ticket_signoff_insert($conn, $ticketId, 'requester', 'intake', 1, $userId, $sigPath, $method, $note);
        ticket_move($conn, $ticketId, 'awaiting_intake_sign', 'in_progress', $userId, 'requester', 'ผู้แจ้งเซ็นรับทราบเช็คลิสต์ตอนรับ');
    });
}

/** admin ข้ามขั้นผู้แจ้ง (ตอนรับหรือตอนส่งคืน) เมื่อเกินกำหนด พร้อมเหตุผล */
function ticket_skip_requester(mysqli $conn, int $ticketId, int $adminId, string $reason): void
{
    $reason = trim($reason);
    if (mb_strlen($reason) < 5) {
        throw new AppUserException('กรุณาระบุเหตุผลที่ข้ามขั้นผู้แจ้ง (อย่างน้อย 5 ตัวอักษร)');
    }
    $next = db_transaction($conn, function () use ($conn, $ticketId, $adminId, $reason) {
        ticket_require_admin($conn, $adminId);
        $t = ticket_lock($conn, $ticketId, ['awaiting_intake_sign', 'awaiting_requester_sign']);
        [$ok, $why] = ticket_skip_window($conn, $t);
        if (!$ok) {
            throw new AppUserException($why);
        }
        $intake = $t['status'] === 'awaiting_intake_sign';
        $note = 'ข้ามขั้นผู้แจ้ง: ' . $reason;
        ticket_signoff_insert($conn, $ticketId, 'requester', $intake ? 'intake' : 'return', $intake ? 1 : (int)$t['sign_round'], $adminId, null, 'skip', $note);
        $to = $intake ? 'in_progress' : 'awaiting_admin_sign';
        ticket_move($conn, $ticketId, (string)$t['status'], $to, $adminId, 'admin', $note);
        return $to;
    });
    if ($next === 'awaiting_admin_sign') {
        ticket_email($conn, $ticketId, 'requester_signed', ['skipped' => $reason]);
    }
}

/**
 * สตาฟซ่อมเสร็จ: บันทึกผลซ่อม เบิกวัสดุ และตรวจซ้ำเทียบเช็คลิสต์ (v3 3.2) -> inspected_ready
 * @param array<int,array{item_id:int,qty:int,note:string}> $materials
 * @param array<int,array{result:string,qty:int,note:string,photo:?string}> $results  key = ticket_item_checks.id
 */
function ticket_submit_recheck(
    mysqli $conn,
    int $ticketId,
    int $staffId,
    string $repairMethod,
    string $resolutionSummary,
    string $completionImagePath,
    array $materials,
    array $results,
    string $discrepancyNote,
    string $returnNote,
    array $extraPhotos = []
): void {
    db_transaction($conn, function () use ($conn, $ticketId, $staffId, $repairMethod, $resolutionSummary, $completionImagePath, $materials, $results, $discrepancyNote, $returnNote, $extraPhotos) {
        $t = ticket_lock($conn, $ticketId, ['in_progress']);
        ticket_require_team($conn, $t, $staffId);

        // ---------- ตรวจซ้ำ: ทุกรายการตอนรับต้องมีผล
        $items = db_all($conn, 'SELECT id, item_name, qty_in FROM ticket_item_checks WHERE ticket_id = ? ORDER BY sort_order, id', 'i', [$ticketId]);
        $diff = [];
        foreach ($items as $it) {
            $r = $results[(int)$it['id']] ?? null;
            if ($r === null || !isset(ITEM_RESULT_LABELS[$r['result']])) {
                throw new AppUserException('กรุณาระบุผลตรวจของ "' . $it['item_name'] . '"');
            }
            $qtyOut = max(0, min(999, (int)$r['qty']));
            $result = $r['result'];
            if ($result === 'returned' && $qtyOut !== (int)$it['qty_in']) {
                $result = 'changed'; // จำนวนไม่ตรงกับตอนรับนับเป็นข้อแตกต่าง
            }
            if ($result !== 'returned') {
                $diff[] = $it['item_name'] . ' (' . ITEM_RESULT_LABELS[$result] . ')';
            }
            db_exec(
                $conn,
                'UPDATE ticket_item_checks SET qty_out = ?, result_out = ?, note_out = ?, photo_out = COALESCE(?, photo_out),
                        checked_out_by = ?, checked_out_at = NOW() WHERE id = ? AND ticket_id = ?',
                'isssiii',
                [$qtyOut, $result, $r['note'] !== '' ? mb_substr($r['note'], 0, 255, 'UTF-8') : null, $r['photo'], $staffId, (int)$it['id'], $ticketId]
            );
        }
        if ($diff && mb_strlen(trim($discrepancyNote)) < 5) {
            throw new AppUserException('พบข้อแตกต่างจากตอนรับ กรุณาอธิบายข้อแตกต่างก่อนส่งต่อ');
        }

        // ---------- เบิกวัสดุ (ตัดสต็อกไม่ให้ติดลบ)
        foreach ($materials as $m) {
            $item = db_one($conn, 'SELECT name, stock_qty FROM inventory_items WHERE id = ? FOR UPDATE', 'i', [$m['item_id']]);
            if (!$item) {
                throw new AppUserException('ไม่พบวัสดุที่เลือกในสต็อก');
            }
            $before = (int)$item['stock_qty'];
            if ($m['qty'] > $before) {
                throw new AppUserException('สต็อก "' . $item['name'] . '" เหลือ ' . $before . ' ไม่พอสำหรับเบิก ' . $m['qty']);
            }
            $after = $before - $m['qty'];
            db_exec($conn, 'UPDATE inventory_items SET stock_qty = ? WHERE id = ?', 'ii', [$after, $m['item_id']]);
            $mnote = $m['note'] !== '' ? mb_substr($m['note'], 0, 255, 'UTF-8') : null;
            db_exec(
                $conn,
                'INSERT INTO ticket_materials (ticket_id, item_id, qty_used, withdrawn_by, note) VALUES (?, ?, ?, ?, ?)',
                'iiiis',
                [$ticketId, $m['item_id'], $m['qty'], $staffId, $mnote]
            );
            db_exec(
                $conn,
                "INSERT INTO inventory_movements (item_id, movement_type, qty_change, stock_before, stock_after, ticket_id, action_by, note)
                 VALUES (?, 'withdraw', ?, ?, ?, ?, ?, ?)",
                'iiiiiis',
                [$m['item_id'], -$m['qty'], $before, $after, $ticketId, $staffId, 'เบิกใช้กับใบงาน #' . $t['ticket_no']]
            );
        }

        $oldImage = (string)($t['completion_image'] ?? '');
        db_exec(
            $conn,
            'UPDATE tickets SET repair_method = ?, resolution_summary = ?, completion_image = ?, has_discrepancy = ?,
                    discrepancy_note = ?, return_note = ? WHERE id = ?',
            'sssissi',
            [$repairMethod, $resolutionSummary, $completionImagePath, $diff ? 1 : 0,
                $diff ? mb_substr(trim($discrepancyNote), 0, 2000, 'UTF-8') : null, $returnNote !== '' ? mb_substr($returnNote, 0, 500, 'UTF-8') : null, $ticketId]
        );
        // รูปหลังซ่อมเพิ่มเติม (รูปที่ 2–3 ของรอบนี้)
        foreach (array_slice(array_values($extraPhotos), 0, 2) as $i => $path) {
            db_exec(
                $conn,
                'INSERT INTO ticket_completion_photos (ticket_id, round_no, file_path, sort_order, created_by) VALUES (?, ?, ?, ?, ?)',
                'iisii',
                [$ticketId, (int)$t['sign_round'], $path, $i + 2, $staffId]
            );
        }
        $summary = !$items ? 'ตรวจซ้ำ: ไม่มีเช็คลิสต์ตอนรับ (ใบงานก่อนระบบเช็คลิสต์)'
            : ($diff ? 'ตรวจซ้ำพบข้อแตกต่าง: ' . implode(', ', $diff) : 'ตรวจซ้ำครบถ้วน ' . count($items) . ' รายการ');
        ticket_move($conn, $ticketId, 'in_progress', 'inspected_ready', $staffId, 'staff', $summary);
        // รูปหลังซ่อมของรอบก่อน (กรณีถูกส่งกลับแก้) เก็บไว้ไม่ลบ เพราะเป็นหลักฐานของรอบนั้น
        unset($oldImage);
    });
    ticket_email($conn, $ticketId, 'recheck_done');
}

/** สตาฟเซ็นส่งมอบ (ลำดับ 1) -> awaiting_requester_sign */
function ticket_staff_sign(mysqli $conn, int $ticketId, int $staffId, string $sigPath, string $method, string $note): void
{
    db_transaction($conn, function () use ($conn, $ticketId, $staffId, $sigPath, $method, $note) {
        $t = ticket_lock($conn, $ticketId, ['inspected_ready']);
        ticket_require_team($conn, $t, $staffId);
        ticket_signoff_insert($conn, $ticketId, 'staff', 'return', (int)$t['sign_round'], $staffId, $sigPath, $method, $note);
        ticket_move($conn, $ticketId, 'inspected_ready', 'awaiting_requester_sign', $staffId, 'staff', 'สตาฟเซ็นส่งมอบงาน');
    });
    ticket_email($conn, $ticketId, 'requester_turn');
}

/** ผู้แจ้งเซ็นรับคืน (ลำดับ 2) -> awaiting_admin_sign */
function ticket_requester_sign_return(mysqli $conn, int $ticketId, int $userId, string $sigPath, string $method, bool $ackDiscrepancy, string $note): void
{
    db_transaction($conn, function () use ($conn, $ticketId, $userId, $sigPath, $method, $ackDiscrepancy, $note) {
        $t = ticket_lock($conn, $ticketId, ['awaiting_requester_sign']);
        if ((int)$t['requester_id'] !== $userId) {
            throw new AppUserException('เฉพาะผู้แจ้งของใบงานนี้เท่านั้นที่เซ็นรับคืนได้');
        }
        if ((int)$t['has_discrepancy'] === 1 && !$ackDiscrepancy) {
            throw new AppUserException('ใบงานนี้มีข้อแตกต่างจากตอนรับ กรุณาติ๊กรับทราบข้อแตกต่างก่อนเซ็น');
        }
        ticket_signoff_insert($conn, $ticketId, 'requester', 'return', (int)$t['sign_round'], $userId, $sigPath, $method, $note);
        ticket_move($conn, $ticketId, 'awaiting_requester_sign', 'awaiting_admin_sign', $userId, 'requester',
            'ผู้แจ้งเซ็นรับคืน' . ((int)$t['has_discrepancy'] === 1 ? ' (รับทราบข้อแตกต่างแล้ว)' : ''));
    });
    ticket_email($conn, $ticketId, 'requester_signed');
}

/** ผู้แจ้งไม่ยอมรับ -> disputed (รอ admin พิจารณา ไม่ปิดงาน) */
function ticket_requester_dispute(mysqli $conn, int $ticketId, int $userId, string $reason): void
{
    $reason = trim($reason);
    if (mb_strlen($reason) < 5) {
        throw new AppUserException('กรุณาระบุเหตุผลที่ไม่ยอมรับ (อย่างน้อย 5 ตัวอักษร)');
    }
    db_transaction($conn, function () use ($conn, $ticketId, $userId, $reason) {
        $t = ticket_lock($conn, $ticketId, ['awaiting_requester_sign']);
        if ((int)$t['requester_id'] !== $userId) {
            throw new AppUserException('เฉพาะผู้แจ้งของใบงานนี้เท่านั้นที่ทำรายการนี้ได้');
        }
        db_exec($conn, 'UPDATE tickets SET return_reason = ? WHERE id = ?', 'si', [mb_substr($reason, 0, 500, 'UTF-8'), $ticketId]);
        ticket_move($conn, $ticketId, 'awaiting_requester_sign', 'disputed', $userId, 'requester', 'ผู้แจ้งไม่ยอมรับ: ' . $reason);
    });
    ticket_email($conn, $ticketId, 'disputed');
}

/**
 * ครู (admin) รับรอง (ลำดับ 3) -> completed, ให้คะแนนทีมช่าง แล้วอีเมลแจ้งปิดงาน
 * จากสถานะ disputed ต้องระบุเหตุผลประกอบการรับรอง
 */
function ticket_signoff(mysqli $conn, int $ticketId, int $adminId, string $signaturePath, string $note, string $method = 'draw'): void
{
    $q = queue_settings($conn);
    db_transaction($conn, function () use ($conn, $ticketId, $adminId, $signaturePath, $note, $method, $q) {
        ticket_require_admin($conn, $adminId);
        $t = ticket_lock($conn, $ticketId, ['awaiting_admin_sign', 'disputed']);
        $round = (int)$t['sign_round'];
        if ($t['status'] === 'disputed' && mb_strlen(trim($note)) < 5) {
            throw new AppUserException('ผู้แจ้งไม่ยอมรับใบงานนี้ การรับรองต้องระบุเหตุผลในหมายเหตุ');
        }
        // ต้องมีลายเซ็นสตาฟของรอบนี้ (ใบงานก่อนระบบ v3 ที่ย้ายมาจาก "รอครูรับรอง" ยกเว้น)
        $hasStaff = (bool)db_one($conn, "SELECT 1 FROM ticket_signoffs WHERE ticket_id = ? AND stage = 'return' AND signer_role = 'staff' AND round_no = ?", 'ii', [$ticketId, $round]);
        $isLegacy = !db_one($conn, "SELECT 1 FROM ticket_logs WHERE ticket_id = ? AND new_status = 'inspected_ready' LIMIT 1", 'i', [$ticketId]);
        if (!$hasStaff && !$isLegacy) {
            throw new AppUserException('ยังไม่มีลายเซ็นส่งมอบของสตาฟในรอบนี้');
        }
        if ($t['status'] === 'awaiting_admin_sign' && !$isLegacy
            && !db_one($conn, "SELECT 1 FROM ticket_signoffs WHERE ticket_id = ? AND stage = 'return' AND signer_role = 'requester' AND round_no = ?", 'ii', [$ticketId, $round])) {
            throw new AppUserException('ยังไม่มีลายเซ็นรับคืนของผู้แจ้งในรอบนี้');
        }
        ticket_signoff_insert($conn, $ticketId, 'admin', 'return', $round, $adminId, $signaturePath, $method, $note);
        db_exec($conn, "UPDATE tickets SET status = 'completed', completed_at = NOW(), stage_since = NOW() WHERE id = ?", 'i', [$ticketId]);
        ticket_log($conn, $ticketId, (string)$t['status'], 'completed', $adminId, 'signoff',
            $t['status'] === 'disputed' ? 'รับรองทั้งที่ผู้แจ้งโต้แย้ง: ' . trim($note) : null);

        // โบนัสให้ทั้งทีม: ช่างหลัก + ผู้ช่วยทุกคน
        $team = ticket_team_ids($conn, $ticketId, (int)($t['assigned_to'] ?? 0));
        if ($q['bonus'] > 0) {
            foreach ($team as $memberId) {
                db_exec($conn, 'UPDATE users SET score = score + ? WHERE user_id = ?', 'ii', [$q['bonus'], $memberId]);
                db_exec(
                    $conn,
                    'INSERT INTO score_logs (user_id, ticket_id, points_change, reason, action_by) VALUES (?, ?, ?, ?, ?)',
                    'iiisi',
                    [$memberId, $ticketId, $q['bonus'], 'งาน #' . $t['ticket_no'] . ' ได้รับการรับรองจากครู', $adminId]
                );
            }
        }
    });
    ticket_email($conn, $ticketId, 'completed');
}

/** admin ไม่เห็นด้วย: ส่งกลับให้ทีมช่างแก้ -> in_progress, เริ่มรอบเซ็นใหม่ (ลายเซ็นรอบก่อนเก็บไว้) */
function ticket_return_to_staff(mysqli $conn, int $ticketId, int $adminId, string $reason): void
{
    $reason = trim($reason);
    if (mb_strlen($reason) < 5) {
        throw new AppUserException('กรุณาระบุเหตุผลที่ส่งกลับ (อย่างน้อย 5 ตัวอักษร)');
    }
    db_transaction($conn, function () use ($conn, $ticketId, $adminId, $reason) {
        ticket_require_admin($conn, $adminId);
        $t = ticket_lock($conn, $ticketId, ['inspected_ready', 'awaiting_requester_sign', 'awaiting_admin_sign', 'disputed']);
        db_exec($conn, 'UPDATE tickets SET sign_round = sign_round + 1, return_reason = ? WHERE id = ?', 'si', [mb_substr($reason, 0, 500, 'UTF-8'), $ticketId]);
        ticket_move($conn, $ticketId, (string)$t['status'], 'in_progress', $adminId, 'admin',
            'ส่งกลับให้ทีมช่างแก้ (เริ่มรอบเซ็นที่ ' . ((int)$t['sign_round'] + 1) . '): ' . $reason);
    });
    ticket_email($conn, $ticketId, 'returned_to_staff');
}

/** admin เร่งงานค้าง: ส่งอีเมลถึงทีมช่าง และบันทึกประวัติ (ไม่เปลี่ยนสถานะ) */
function ticket_nudge_staff(mysqli $conn, int $ticketId, int $adminId, string $message): int
{
    ticket_require_admin($conn, $adminId);
    $t = db_one($conn, 'SELECT status FROM tickets WHERE id = ?', 'i', [$ticketId]);
    if (!$t || !in_array($t['status'], TICKET_STAFF_ACTIVE_STATUSES, true)) {
        throw new AppUserException('เร่งงานได้เฉพาะใบงานที่ทีมช่างกำลังดำเนินการ');
    }
    ticket_log($conn, $ticketId, (string)$t['status'], (string)$t['status'], $adminId, 'nudge', 'ผู้ดูแลเร่งงาน' . ($message !== '' ? ': ' . $message : ''));
    return ticket_email($conn, $ticketId, 'staff_nudge', ['message' => mb_substr($message, 0, 300, 'UTF-8')]);
}

function app_base_url(): string
{
    return ticket_mail_base_url();
}

/** ลายเซ็นทั้งหมดของใบงาน เรียงตามรอบและลำดับขั้น */
function ticket_signoffs(mysqli $conn, int $ticketId): array
{
    return db_all(
        $conn,
        "SELECT s.*, u.first_name, u.last_name
           FROM ticket_signoffs s LEFT JOIN users u ON u.user_id = s.signer_id
          WHERE s.ticket_id = ?
          ORDER BY s.stage = 'return', s.round_no, FIELD(s.signer_role, 'staff', 'requester', 'admin'), s.id",
        'i',
        [$ticketId]
    );
}

/** รายการเช็คลิสต์ของใบงาน */
/**
 * รูปหลักฐานหลังซ่อมของรอบปัจจุบัน (1–3 รูป) เรียงตามลำดับ
 * @return list<array{query:string}> query สำหรับต่อท้าย file.php? (ตรวจสิทธิ์ที่ file.php)
 */
function ticket_completion_photos(mysqli $conn, array $ticket): array
{
    $out = [];
    if (!empty($ticket['completion_image'])) {
        $out[] = ['query' => 't=' . (int)$ticket['id'] . '&k=completion'];
    }
    try {
        foreach (db_all($conn, 'SELECT id FROM ticket_completion_photos WHERE ticket_id = ? AND round_no = ? ORDER BY sort_order, id LIMIT 2', 'ii', [(int)$ticket['id'], (int)($ticket['sign_round'] ?? 1)]) as $r) {
            $out[] = ['query' => 't=' . (int)$ticket['id'] . '&k=done&i=' . (int)$r['id']];
        }
    } catch (Throwable $e) {
        // ยังไม่ได้รัน migration รูปหลังซ่อมหลายรูป
    }
    return $out;
}

function ticket_item_checks(mysqli $conn, int $ticketId): array
{
    return db_all($conn, 'SELECT * FROM ticket_item_checks WHERE ticket_id = ? ORDER BY sort_order, id', 'i', [$ticketId]);
}

/** รายการตั้งต้นตามหมวดหมู่ (รวมรายการที่ใช้ได้ทุกหมวด) */
function checklist_presets_for(mysqli $conn, ?int $categoryId): array
{
    return db_all(
        $conn,
        'SELECT item_name, item_type FROM checklist_presets
          WHERE is_active = 1 AND (category_id IS NULL OR category_id = ?)
          ORDER BY category_id IS NULL, sort_order, id',
        'i',
        [(int)$categoryId]
    );
}
