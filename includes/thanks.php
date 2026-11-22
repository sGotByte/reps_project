<?php
declare(strict_types=1);

/**
 * REPS · includes/thanks.php — ระบบ "ขอบคุณ Lab boy" (ไม่บังคับ)
 *
 * - ผู้แจ้งขอบคุณช่างหลักหรือผู้ช่วยของใบงานตัวเองได้หลังเซ็นรับคืน (ภายใน thanks_days วัน)
 * - โอนเงินผ่าน QR พร้อมเพย์ของ Lab boy คนนั้น (users.promptpay_qr_path) หรือบันทึกของที่ให้ (ขนม เครื่องดื่ม อื่น ๆ)
 * - ผู้แจ้งเป็นคนบันทึก (แนบสลิปได้) → Lab boy กดยืนยันว่าได้รับ / ไม่ได้รับ
 * - ไม่เกี่ยวกับคะแนน ไม่จัดอันดับ · Lab boy เห็นของตัวเอง ครูเห็นทั้งหมด
 * ระบบไม่รับเงินและไม่ตรวจสลิปอัตโนมัติ
 */

require_once __DIR__ . '/db_helpers.php';
require_once __DIR__ . '/private_storage.php';
require_once __DIR__ . '/site_config.php';
require_once __DIR__ . '/terms.php';

const THANKS_KINDS = ['money' => 'โอนเงิน', 'snack' => 'ขนม', 'drink' => 'เครื่องดื่ม', 'other' => 'อื่น ๆ'];
const THANKS_STATUS = ['pending' => 'รอยืนยัน', 'confirmed' => 'ได้รับแล้ว', 'not_received' => 'ไม่ได้รับ', 'cancelled' => 'ยกเลิก'];
const THANKS_PER_TICKET = 3;
const THANKS_GIVABLE_STATUSES = ['awaiting_admin_sign', 'completed'];

function thanks_settings(mysqli $conn): array
{
    $s = app_get_settings($conn);
    return [
        'enabled' => ($s['thanks_enabled'] ?? '0') === '1',
        'max'     => max(1, (int)($s['thanks_max_amount'] ?? 5000)),
        'days'    => max(1, (int)($s['thanks_days'] ?? 30)),
    ];
}

function thanks_enabled(mysqli $conn): bool
{
    return thanks_settings($conn)['enabled'];
}

/** ทีมช่างของใบงาน: ช่างหลักก่อน แล้วผู้ช่วย */
function thanks_team(mysqli $conn, int $ticketId): array
{
    return db_all(
        $conn,
        "SELECT u.user_id, u.first_name, u.last_name, u.promptpay_qr_path, (t.assigned_to = u.user_id) AS is_lead
           FROM tickets t
           JOIN users u ON u.user_id = t.assigned_to OR u.user_id IN (SELECT a.user_id FROM ticket_assignees a WHERE a.ticket_id = t.id)
          WHERE t.id = ? AND u.status = 'active'
          ORDER BY is_lead DESC, u.first_name",
        'i',
        [$ticketId]
    );
}

/** วันที่ผู้แจ้งเซ็นรับคืน (เริ่มนับระยะเวลาขอบคุณ) */
function thanks_window_start(mysqli $conn, array $ticket): ?string
{
    $row = db_one(
        $conn,
        "SELECT MAX(signed_at) AS at FROM ticket_signoffs WHERE ticket_id = ? AND signer_role = 'requester' AND stage = 'return'",
        'i',
        [(int)$ticket['id']]
    );
    return $row['at'] ?? ($ticket['completed_at'] ?? null);
}

/** เหตุผลที่ขอบคุณไม่ได้ (null = ขอบคุณได้) */
function thanks_block_reason(mysqli $conn, array $ticket, int $userId): ?string
{
    $cfg = thanks_settings($conn);
    if (!$cfg['enabled']) return 'ระบบขอบคุณ Lab boy ปิดอยู่';
    if ((int)$ticket['requester_id'] !== $userId) return 'ขอบคุณได้เฉพาะใบงานที่คุณเป็นผู้แจ้ง';
    if (!in_array($ticket['status'], THANKS_GIVABLE_STATUSES, true)) return 'ขอบคุณได้หลังเซ็นรับอุปกรณ์คืนแล้ว';
    $start = thanks_window_start($conn, $ticket);
    if ($start && strtotime($start) < time() - $cfg['days'] * 86400) return 'เลยกำหนดขอบคุณของใบงานนี้แล้ว (' . $cfg['days'] . ' วันหลังรับคืน)';
    return null;
}

function thanks_can_give(mysqli $conn, array $ticket, int $userId): bool
{
    return thanks_block_reason($conn, $ticket, $userId) === null;
}

/**
 * บันทึกคำขอบคุณ — ตรวจทุกเงื่อนไขฝั่งเซิร์ฟเวอร์
 * @param array|null $slipFile $_FILES['slip'] หรือ null
 */
function thanks_create(mysqli $conn, int $ticketId, int $fromUserId, int $toUserId, string $kind, string $amountRaw, string $itemNote, string $message, ?array $slipFile): int
{
    $ticket = db_one($conn, 'SELECT * FROM tickets WHERE id = ?', 'i', [$ticketId]);
    if (!$ticket) {
        throw new AppUserException('ไม่พบใบงานนี้');
    }
    $block = thanks_block_reason($conn, $ticket, $fromUserId);
    if ($block !== null) {
        throw new AppUserException($block);
    }
    if (!isset(THANKS_KINDS[$kind])) {
        throw new AppUserException('กรุณาเลือกสิ่งที่ให้');
    }
    $teamIds = array_map(static fn($m) => (int)$m['user_id'], thanks_team($conn, $ticketId));
    if (!in_array($toUserId, $teamIds, true)) {
        throw new AppUserException('ผู้รับต้องเป็นช่างที่ซ่อมใบงานนี้');
    }
    $cfg = thanks_settings($conn);
    $itemNote = mb_substr(trim($itemNote), 0, 200);
    $message = mb_substr(trim($message), 0, 300);
    $amountRaw = trim(str_replace(',', '', $amountRaw));
    $amount = null;
    if ($amountRaw !== '') {
        if (preg_match('/^\d{1,6}(\.\d{1,2})?$/', $amountRaw) !== 1) {
            throw new AppUserException('จำนวนเงินไม่ถูกต้อง');
        }
        $amount = (float)$amountRaw;
    }
    if ($kind === 'money') {
        if ($amount === null || $amount <= 0) {
            throw new AppUserException('กรุณาระบุจำนวนเงินที่โอน');
        }
    } elseif ($itemNote === '') {
        throw new AppUserException('กรุณาระบุว่าให้อะไร เช่น กาแฟเย็น 2 แก้ว');
    }
    if ($amount !== null && $amount > $cfg['max']) {
        throw new AppUserException('จำนวนเงินต้องไม่เกิน ' . number_format($cfg['max']) . ' บาทต่อครั้ง');
    }
    $count = (int)(db_one($conn, "SELECT COUNT(*) AS n FROM ticket_thanks WHERE ticket_id = ? AND from_user_id = ? AND status <> 'cancelled'", 'ii', [$ticketId, $fromUserId])['n'] ?? 0);
    if ($count >= THANKS_PER_TICKET) {
        throw new AppUserException('บันทึกคำขอบคุณของใบงานนี้ได้สูงสุด ' . THANKS_PER_TICKET . ' รายการ');
    }
    $slip = null;
    if ($slipFile && (int)($slipFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $slip = app_store_uploaded_image($slipFile, 'slip', 3145728, 1400);
    }
    try {
        return db_insert(
            $conn,
            'INSERT INTO ticket_thanks (ticket_id, from_user_id, to_user_id, kind, amount, item_note, message, slip_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            'iiisdsss',
            [$ticketId, $fromUserId, $toUserId, $kind, $amount, $itemNote, $message, $slip]
        );
    } catch (Throwable $e) {
        if ($slip) app_delete_private_file($slip);
        throw $e;
    }
}

/** Lab boy ยืนยันว่าได้รับ / ไม่ได้รับ (เฉพาะของตัวเอง และเฉพาะตอนรอยืนยัน) */
function thanks_respond(mysqli $conn, int $thanksId, int $staffId, bool $received, string $note): void
{
    db_transaction($conn, function () use ($conn, $thanksId, $staffId, $received, $note) {
        $row = db_one($conn, 'SELECT * FROM ticket_thanks WHERE id = ? FOR UPDATE', 'i', [$thanksId]);
        if (!$row || (int)$row['to_user_id'] !== $staffId) {
            throw new AppUserException('ไม่พบคำขอบคุณนี้');
        }
        if ($row['status'] !== 'pending') {
            throw new AppUserException('รายการนี้ยืนยันไปแล้ว');
        }
        db_exec(
            $conn,
            'UPDATE ticket_thanks SET status = ?, staff_note = ?, confirmed_at = NOW() WHERE id = ?',
            'ssi',
            [$received ? 'confirmed' : 'not_received', mb_substr(trim($note), 0, 200), $thanksId]
        );
    });
}

/** ผู้แจ้งยกเลิกรายการที่ยังรอยืนยัน */
function thanks_cancel(mysqli $conn, int $thanksId, int $userId): void
{
    db_transaction($conn, function () use ($conn, $thanksId, $userId) {
        $row = db_one($conn, 'SELECT * FROM ticket_thanks WHERE id = ? FOR UPDATE', 'i', [$thanksId]);
        if (!$row || (int)$row['from_user_id'] !== $userId) {
            throw new AppUserException('ไม่พบคำขอบคุณนี้');
        }
        if ($row['status'] !== 'pending') {
            throw new AppUserException('ยกเลิกได้เฉพาะรายการที่ยังรอยืนยัน');
        }
        db_exec($conn, "UPDATE ticket_thanks SET status = 'cancelled' WHERE id = ?", 'i', [$thanksId]);
    });
}

/** ข้อความสรุปสิ่งที่ให้ เช่น "โอนเงิน 50 บาท" หรือ "ขนม · ขนมปัง 2 ชิ้น (~40 บาท)" */
function thanks_describe(array $t): string
{
    $kind = THANKS_KINDS[$t['kind']] ?? $t['kind'];
    $amount = $t['amount'] !== null ? number_format((float)$t['amount'], (float)$t['amount'] == floor((float)$t['amount']) ? 0 : 2) . ' บาท' : '';
    if ($t['kind'] === 'money') {
        return $kind . ' ' . $amount;
    }
    return $kind . ($t['item_note'] !== '' ? ' · ' . $t['item_note'] : '') . ($amount !== '' ? ' (มูลค่าประมาณ ' . $amount . ')' : '');
}

/**
 * ยอดรวมตามภาคเรียน: ต่อ Lab boy (หรือคนเดียว)
 * @return array<int, array{money:float, gifts:int, pending:int, count:int}>
 */
function thanks_summary(mysqli $conn, ?int $userId, ?array $term): array
{
    $sql = "SELECT to_user_id,
                   SUM(CASE WHEN status = 'confirmed' AND kind = 'money' THEN amount ELSE 0 END) AS money,
                   SUM(status = 'confirmed' AND kind <> 'money') AS gifts,
                   SUM(status = 'pending') AS pending,
                   SUM(status = 'confirmed') AS confirmed_count
              FROM ticket_thanks
             WHERE status <> 'cancelled'" . term_date_sql($term, 'created_at');
    $types = '';
    $params = [];
    if ($userId !== null) {
        $sql .= ' AND to_user_id = ?';
        $types = 'i';
        $params[] = $userId;
    }
    $sql .= ' GROUP BY to_user_id';
    $out = [];
    foreach (db_all($conn, $sql, $types, $params) as $r) {
        $out[(int)$r['to_user_id']] = ['money' => (float)$r['money'], 'gifts' => (int)$r['gifts'], 'pending' => (int)$r['pending'], 'count' => (int)$r['confirmed_count']];
    }
    return $out;
}

/** จำนวนคำขอบคุณที่รอ Lab boy ยืนยัน (ใช้ในกระดิ่งและแท็บ) */
function thanks_pending_count(mysqli $conn, int $staffId): int
{
    try {
        return (int)(db_one($conn, "SELECT COUNT(*) AS n FROM ticket_thanks WHERE to_user_id = ? AND status = 'pending'", 'i', [$staffId])['n'] ?? 0);
    } catch (Throwable $e) {
        return 0; // ยังไม่ได้รัน migration
    }
}
