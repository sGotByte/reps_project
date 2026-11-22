<?php
declare(strict_types=1);

/**
 * REPS · includes/ticket_mail.php — อีเมลแจ้งเตือนตามขั้นตอนของใบงาน (v3 หัวข้อ 5)
 *
 *   ticket_email($conn, $ticketId, 'intake_recorded');   // หลัง commit ของขั้นตอนนั้นแล้วเท่านั้น
 *   ticket_send_sign_reminders($conn);                   // เตือนผู้แจ้งที่ยังไม่เซ็น (เรียกจากหน้าเว็บ/cron)
 *
 * - ลิงก์ในอีเมลพาไปหน้าเว็บที่ต้องล็อกอิน ไม่มี token ที่ใช้เซ็นแทนได้
 * - ทุกฉบับบันทึกใน email_logs (template_key) และไม่โยน exception — ส่งไม่สำเร็จต้องไม่กระทบขั้นตอนในระบบ
 * - ข้อความอยู่ในไฟล์นี้ (แก้ผ่านหน้าเว็บได้ในระบบปรับแต่งเว็บเฟส 3)
 */

require_once __DIR__ . '/db_helpers.php';
require_once __DIR__ . '/mailer.php';

const TICKET_EMAIL_KEYS = [
    'intake_recorded', 'recheck_done', 'requester_turn', 'requester_signed', 'disputed',
    'returned_to_staff', 'completed', 'sign_reminder', 'staff_nudge',
];

function ticket_mail_base_url(): string
{
    $base = rtrim(trim((string)(getenv('APP_BASE_URL') ?: '')), '/');
    return filter_var($base, FILTER_VALIDATE_URL) ? $base : '';
}

/** ข้อมูลใบงานที่ใช้ประกอบอีเมล */
function ticket_mail_context(mysqli $conn, int $ticketId): ?array
{
    $t = db_one(
        $conn,
        "SELECT t.id, t.ticket_no, t.status, t.location_note, t.symptom, t.repair_method, t.resolution_summary,
                t.has_discrepancy, t.discrepancy_note, t.return_reason, t.sign_round, t.requester_id, t.assigned_to,
                COALESCE(NULLIF(t.primary_contact_email, ''), r.email) AS req_email,
                r.first_name AS req_first, r.last_name AS req_last,
                s.first_name AS staff_first, s.last_name AS staff_last
           FROM tickets t
           JOIN users r ON r.user_id = t.requester_id
           LEFT JOIN users s ON s.user_id = t.assigned_to
          WHERE t.id = ?",
        'i',
        [$ticketId]
    );
    if (!$t) {
        return null;
    }
    $t['items'] = db_all(
        $conn,
        'SELECT item_name, item_type, serial_no, qty_in, condition_in, note_in, qty_out, result_out, note_out
           FROM ticket_item_checks WHERE ticket_id = ? ORDER BY sort_order, id',
        'i',
        [$ticketId]
    );
    return $t;
}

function ticket_mail_items_text(array $items, bool $withReturn): string
{
    if (!$items) {
        return "(ไม่มีรายการเช็คลิสต์)\n";
    }
    $cond = ['normal' => 'ปกติ', 'scratched' => 'มีรอย', 'damaged' => 'ชำรุดอยู่เดิม'];
    $res = ['returned' => 'คืนครบ', 'missing' => 'ไม่พบ (หาย)', 'changed' => 'สภาพเปลี่ยน'];
    $out = '';
    foreach ($items as $i => $it) {
        $out .= ($i + 1) . '. ' . $it['item_name'] . ($it['item_type'] === 'main' ? ' [อุปกรณ์หลัก]' : '')
            . ' · จำนวน ' . (int)$it['qty_in'] . ' · ' . ($cond[$it['condition_in']] ?? $it['condition_in'])
            . ($it['serial_no'] ? ' · เลขเครื่อง ' . $it['serial_no'] : '')
            . ($it['note_in'] ? ' · ' . $it['note_in'] : '');
        if ($withReturn && $it['result_out'] !== null) {
            $out .= "\n   ตอนส่งคืน: " . ($res[$it['result_out']] ?? $it['result_out']) . ' (จำนวน ' . (int)$it['qty_out'] . ')'
                . ($it['note_out'] ? ' · ' . $it['note_out'] : '');
        }
        $out .= "\n";
    }
    return $out;
}

/**
 * @return list<array{0:string,1:string}> [อีเมลผู้รับ, ชื่อ]
 */
function ticket_mail_recipients(mysqli $conn, array $t, string $key): array
{
    $requester = [[(string)$t['req_email'], (string)$t['req_first']]];
    $admins = static fn(): array => array_map(
        static fn($r) => [(string)$r['email'], (string)$r['first_name']],
        db_all($conn, "SELECT email, first_name FROM users WHERE role = 'admin' AND status = 'active'")
    );
    $team = static function () use ($conn, $t): array {
        $rows = db_all(
            $conn,
            'SELECT u.email, u.first_name FROM users u
              WHERE u.user_id = ? OR u.user_id IN (SELECT a.user_id FROM ticket_assignees a WHERE a.ticket_id = ?)',
            'ii',
            [(int)$t['assigned_to'], (int)$t['id']]
        );
        return array_map(static fn($r) => [(string)$r['email'], (string)$r['first_name']], $rows);
    };
    return match ($key) {
        'intake_recorded', 'recheck_done', 'requester_turn', 'sign_reminder' => $requester,
        'requester_signed', 'disputed' => $admins(),
        'returned_to_staff', 'staff_nudge' => $team(),
        'completed' => array_merge($requester, $team()),
        default => [],
    };
}

/** @return array{0:string,1:string} [หัวเรื่อง, เนื้อหา] */
function ticket_mail_compose(array $t, string $key, string $name, array $extra = []): array
{
    $no = (string)$t['ticket_no'];
    $base = ticket_mail_base_url();
    $signLink = $base !== '' ? "\nเข้าสู่ระบบแล้วเปิดใบงานได้ที่: {$base}/sign.php?id={$t['id']}\n" : '';
    $staffLink = $base !== '' ? "\nเปิดใบงาน: {$base}/staff/job.php?id={$t['id']}\n" : '';
    $adminLink = $base !== '' ? "\nเปิดหน้ารับรองงาน: {$base}/admin/admin_signoff.php?id={$t['id']}\n" : '';
    $head = "สวัสดี {$name},\n\n";
    $info = "ใบงาน: {$no}\nสถานที่: {$t['location_note']}\n";
    $staff = trim(($t['staff_first'] ?? '') . ' ' . ($t['staff_last'] ?? '')) ?: '-';
    $foot = "\n-- \nREPS ระบบแจ้งซ่อม (อีเมลอัตโนมัติ โปรดอย่าตอบกลับ)\n";

    return match ($key) {
        'intake_recorded' => [
            "ใบงาน {$no}: ช่างรับอุปกรณ์แล้ว กรุณาเซ็นรับทราบรายการ",
            $head . "ช่าง {$staff} รับอุปกรณ์ของคุณเข้าซ่อมแล้ว และบันทึกรายการอุปกรณ์ที่รับไว้ดังนี้\n\n" . $info . "\n"
                . ticket_mail_items_text($t['items'], false)
                . "\nกรุณาตรวจสอบและเซ็นรับทราบรายการในระบบ ช่างจะเริ่มซ่อมหลังคุณเซ็นแล้ว\n" . $signLink . $foot,
        ],
        'recheck_done' => [
            "ใบงาน {$no}: ซ่อมเสร็จแล้ว ตรวจอุปกรณ์ก่อนส่งคืน" . ((int)$t['has_discrepancy'] ? ' (พบข้อแตกต่าง)' : ''),
            $head . "ช่างซ่อมเสร็จและตรวจอุปกรณ์ซ้ำก่อนส่งคืนแล้ว\n\n" . $info
                . 'สิ่งที่ซ่อม: ' . ($t['repair_method'] ?: '-') . "\nสรุปผล: " . ($t['resolution_summary'] ?: '-') . "\n\n"
                . "ผลตรวจอุปกรณ์เทียบกับตอนรับ:\n" . ticket_mail_items_text($t['items'], true)
                . ((int)$t['has_discrepancy'] ? "\nพบข้อแตกต่าง: " . ($t['discrepancy_note'] ?: '-') . "\n" : "\nตรวจครบถ้วน\n")
                . "\nเมื่อช่างส่งมอบและเซ็นแล้ว ระบบจะแจ้งให้คุณเซ็นรับคืน\n" . $signLink . $foot,
        ],
        'requester_turn' => [
            "ใบงาน {$no}: ถึงคิวคุณเซ็นรับอุปกรณ์คืน",
            $head . "ช่าง {$staff} ส่งมอบงานและเซ็นแล้ว ถึงคิวคุณตรวจรับอุปกรณ์และเซ็นรับคืน\n\n" . $info
                . ((int)$t['has_discrepancy'] ? "\nหมายเหตุ: ใบงานนี้มีข้อแตกต่างจากตอนรับ กรุณาตรวจรายการก่อนเซ็น หากไม่ยอมรับ กดปุ่ม \"ไม่ยอมรับ\" พร้อมเหตุผลได้\n" : '')
                . $signLink . $foot,
        ],
        'sign_reminder' => [
            "เตือน: ใบงาน {$no} ยังรอคุณเซ็น",
            $head . ($t['status'] === 'awaiting_intake_sign'
                ? "ใบงานนี้ยังรอคุณเซ็นรับทราบรายการอุปกรณ์ตอนรับ ช่างจะเริ่มซ่อมหลังคุณเซ็น\n\n"
                : "ใบงานนี้ซ่อมเสร็จแล้วและยังรอคุณเซ็นรับอุปกรณ์คืน\n\n") . $info . $signLink . $foot,
        ],
        'requester_signed' => [
            "ใบงาน {$no}: ผู้แจ้งเซ็นรับคืนแล้ว รอครูรับรอง",
            $head . "ผู้แจ้ง {$t['req_first']} {$t['req_last']} เซ็นรับอุปกรณ์คืนแล้ว ใบงานรอการรับรองจากครู\n\n" . $info
                . 'ช่าง: ' . $staff . "\n" . ((int)$t['has_discrepancy'] ? 'มีข้อแตกต่างตอนตรวจซ้ำ: ' . ($t['discrepancy_note'] ?: '-') . "\n" : '')
                . (!empty($extra['skipped']) ? "\nหมายเหตุ: ขั้นผู้แจ้งถูกข้ามโดยผู้ดูแล เหตุผล: {$extra['skipped']}\n" : '')
                . $adminLink . $foot,
        ],
        'disputed' => [
            "ใบงาน {$no}: ผู้แจ้งไม่ยอมรับ รอครูพิจารณา",
            $head . "ผู้แจ้ง {$t['req_first']} {$t['req_last']} ไม่ยอมรับการส่งคืน\n\n" . $info
                . 'เหตุผล: ' . ($t['return_reason'] ?: '-') . "\n" . $adminLink . $foot,
        ],
        'returned_to_staff' => [
            "ใบงาน {$no}: ครูส่งกลับให้แก้ไข",
            $head . "ใบงานนี้ถูกส่งกลับให้ทีมช่างแก้ไขก่อนปิดงาน (รอบเซ็นที่ " . (int)$t['sign_round'] . ")\n\n" . $info
                . 'เหตุผล: ' . ($t['return_reason'] ?: '-') . "\n" . $staffLink . $foot,
        ],
        'staff_nudge' => [
            "เร่งงาน: ใบงาน {$no}",
            $head . "ผู้ดูแลขอให้ติดตามใบงานนี้ซึ่งค้างอยู่\n\n" . $info
                . (!empty($extra['message']) ? 'ข้อความจากผู้ดูแล: ' . $extra['message'] . "\n" : '') . $staffLink . $foot,
        ],
        'completed' => [
            "งานซ่อม {$no} เสร็จสมบูรณ์",
            $head . "ใบงานนี้ได้รับการรับรองจากครูและปิดงานเรียบร้อยแล้ว\n\n" . $info
                . 'ช่าง: ' . $staff . "\nสิ่งที่ซ่อม: " . ($t['repair_method'] ?: '-') . "\nสรุปผล: " . ($t['resolution_summary'] ?: '-') . "\n"
                . ($base !== '' ? "\nดูใบรับรองงาน: {$base}/certificate.php?id={$t['id']}\n" : '') . "\nขอบคุณที่ใช้บริการ REPS\n" . $foot,
        ],
        default => ['', ''],
    };
}

/**
 * ส่งอีเมลของขั้นตอนหนึ่งให้ผู้รับทุกคนที่เกี่ยวข้อง — ไม่โยน exception
 * @return int จำนวนฉบับที่ส่งสำเร็จ
 */
function ticket_email(mysqli $conn, int $ticketId, string $key, array $extra = []): int
{
    if (!in_array($key, TICKET_EMAIL_KEYS, true)) {
        return 0;
    }
    try {
        $t = ticket_mail_context($conn, $ticketId);
        if (!$t) {
            return 0;
        }
        $recipients = ticket_mail_recipients($conn, $t, $key);
    } catch (Throwable $e) {
        error_log("ticket_email {$key} setup failed: " . $e->getMessage());
        return 0;
    }

    $sent = 0;
    $seen = [];
    foreach ($recipients as [$email, $name]) {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seen[strtolower($email)])) {
            continue;
        }
        $seen[strtolower($email)] = true;
        [$subject, $body] = ticket_mail_compose($t, $key, $name, $extra);
        $logId = 0;
        try {
            $logId = db_insert($conn, "INSERT INTO email_logs (ticket_id, to_email, template_key, status) VALUES (?, ?, ?, 'pending')", 'iss', [$ticketId, $email, $key]);
        } catch (Throwable $e) {
            error_log('email_logs insert failed: ' . $e->getMessage());
        }
        $status = 'failed';
        $error = null;
        try {
            app_mail_send($email, $subject, $body);
            $status = 'sent';
            $sent++;
        } catch (Throwable $e) {
            $error = mb_substr($e->getMessage(), 0, 1000, 'UTF-8');
            error_log("Email {$key} for ticket {$ticketId} failed: {$error}");
        }
        if ($logId > 0) {
            try {
                db_exec($conn, "UPDATE email_logs SET status = ?, error = ?, sent_at = IF(? = 'sent', NOW(), NULL) WHERE id = ?", 'sssi', [$status, $error, $status, $logId]);
            } catch (Throwable $e) {
                error_log('email_logs update failed: ' . $e->getMessage());
            }
        }
    }
    return $sent;
}

/**
 * เตือนผู้แจ้งที่ยังไม่เซ็น (ตอนรับ/ตอนส่งคืน) ทุก sign_reminder_days วัน
 * ทำงานไม่เกินครั้งละ 10 นาทีต่อเซิร์ฟเวอร์ (ล็อกด้วยไฟล์) จึงเรียกจากหน้าเว็บที่เปิดบ่อยได้
 */
function ticket_send_sign_reminders(mysqli $conn, bool $force = false): int
{
    $lock = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'reps_sign_reminder_' . substr(md5(__DIR__), 0, 12) . '.lock';
    if (!$force && is_file($lock) && filemtime($lock) > time() - 600) {
        return 0;
    }
    @touch($lock);
    try {
        $days = (int)(db_one($conn, "SELECT setting_value AS v FROM system_settings WHERE setting_key = 'sign_reminder_days'")['v'] ?? 0);
        if ($days <= 0) {
            return 0;
        }
        $rows = db_all(
            $conn,
            "SELECT t.id FROM tickets t
              WHERE t.status IN ('awaiting_intake_sign', 'awaiting_requester_sign')
                AND t.stage_since <= NOW() - INTERVAL ? DAY
                AND NOT EXISTS (
                    SELECT 1 FROM email_logs l
                     WHERE l.ticket_id = t.id AND l.template_key IN ('sign_reminder', 'intake_recorded', 'requester_turn')
                       AND l.created_at > NOW() - INTERVAL ? DAY)
              LIMIT 20",
            'ii',
            [$days, $days]
        );
    } catch (Throwable $e) {
        error_log('sign reminders failed: ' . $e->getMessage());
        return 0;
    }
    $n = 0;
    foreach ($rows as $r) {
        $n += ticket_email($conn, (int)$r['id'], 'sign_reminder');
    }
    return $n;
}
