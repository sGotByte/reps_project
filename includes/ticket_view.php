<?php
declare(strict_types=1);

/**
 * REPS · includes/ticket_view.php — ส่วนแสดงผลใบงานที่ใช้ร่วมกัน (สตาฟ ผู้แจ้ง ครู ใบรับรอง)
 *   tv_assets()                                  CSS ของส่วนประกอบ (เรียกครั้งเดียวต่อหน้า)
 *   tv_steps($status)                            แถบขั้นตอนของใบงาน
 *   tv_checklist($items, $ticketId, $base)       ตารางเช็คลิสต์ตอนรับเทียบตอนส่งคืน
 *   tv_signatures($signoffs, $ticketId, $base)   ลายเซ็นแยกตามรอบและขั้น
 *   tv_timeline($logs)                           ประวัติการเปลี่ยนสถานะพร้อมเหตุผล
 * ไม่พึ่ง Tailwind จึงใช้ได้ทั้งหน้าที่ใช้ app.css และหน้าที่ใช้ Tailwind CDN
 */

require_once __DIR__ . '/ticket_service.php';

function tv_h($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function tv_dt(?string $v): string
{
    return $v ? date('d/m/Y H:i', (int)strtotime($v)) : '-';
}

/** ข้อมูลใบงานพร้อมชื่อผู้เกี่ยวข้อง */
function tv_ticket(mysqli $conn, int $ticketId): ?array
{
    $t = db_one(
        $conn,
        "SELECT t.*, c.name AS category_name,
                r.first_name AS req_first, r.last_name AS req_last, r.phone_number AS req_phone, r.email AS req_email, r.id_number AS req_idno,
                s.first_name AS staff_first, s.last_name AS staff_last
           FROM tickets t
           LEFT JOIN categories c ON c.id = t.category_id
           JOIN users r ON r.user_id = t.requester_id
           LEFT JOIN users s ON s.user_id = t.assigned_to
          WHERE t.id = ?",
        'i',
        [$ticketId]
    );
    if ($t && function_exists('ticket_completion_photos')) {
        $t['completion_photos'] = ticket_completion_photos($conn, $t); // รูปหลังซ่อม 1–3 รูปของรอบปัจจุบัน
    }
    return $t;
}

function tv_logs(mysqli $conn, int $ticketId): array
{
    return db_all(
        $conn,
        'SELECT l.*, u.first_name, u.last_name FROM ticket_logs l LEFT JOIN users u ON u.user_id = l.action_by
          WHERE l.ticket_id = ? ORDER BY l.created_at, l.id',
        'i',
        [$ticketId]
    );
}

function tv_assets(): string
{
    static $done = false;
    if ($done) {
        return '';
    }
    $done = true;
    return <<<'HTML'
<style>
.tv-card { min-width: 0; border: 1px solid #ECEAE6; border-radius: 16px; background: #fff; }
.tv-card + .tv-card { margin-top: 16px; }
.tv-card-head { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 6px 16px; padding: 14px 18px; border-bottom: 1px solid #EFEDE9; }
.tv-card-title { margin: 0; font-size: 15px; font-weight: 600; color: #161616; }
.tv-card-sub { font-size: 13px; color: #6B6862; }
.tv-card-body { padding: 16px 18px; }
.tv-steps { display: flex; gap: 4px; margin: 0; padding: 0 0 4px; list-style: none; overflow-x: auto; }
.tv-step { flex: 1 1 0; min-width: 92px; }
.tv-step-bar { display: block; height: 5px; border-radius: 999px; background: #ECEAE6; }
.tv-step.is-done .tv-step-bar { background: var(--theme-primary, #161616); }
.tv-step.is-current .tv-step-bar { background: #0E7490; }
.tv-step.is-alert .tv-step-bar { background: #B45309; }
.tv-step-label { display: block; margin-top: 6px; font-size: 12px; line-height: 1.35; color: #8A867F; }
.tv-step.is-done .tv-step-label { color: #3F3D39; }
.tv-step.is-current .tv-step-label, .tv-step.is-alert .tv-step-label { color: #161616; font-weight: 600; }
.tv-table-wrap { overflow-x: auto; }
.tv-table { width: 100%; min-width: 640px; border-collapse: collapse; font-size: 13.5px; }
.tv-table th { padding: 8px 10px; background: #FAFAF8; border-bottom: 1px solid #EFEDE9; color: #57544F; font-size: 12.5px; font-weight: 500; text-align: left; white-space: nowrap; }
.tv-table td { padding: 10px; border-bottom: 1px solid #F1EFEC; vertical-align: top; color: #161616; }
.tv-table tr:last-child td { border-bottom: 0; }
.tv-table tr.is-diff td { background: #FFFBF5; }
.tv-muted { color: #6B6862; font-size: 12.5px; }
.tv-tag { display: inline-flex; align-items: center; gap: 5px; padding: 1px 8px; border: 1px solid #E7E4DF; border-radius: 999px; background: #FAFAF8; font-size: 12px; white-space: nowrap; }
.tv-tag::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--c, #BDB8B0); }
.tv-tag--ok { --c: #12A150; }
.tv-tag--warn { --c: #B45309; color: #92400E; border-color: #F1D9BF; }
.tv-tag--main { --c: var(--theme-primary, #161616); }
.tv-photo { display: inline-block; margin-top: 4px; font-size: 12.5px; color: #3F3D39; text-decoration: underline; }
.tv-sigs { display: grid; gap: 12px; grid-template-columns: repeat(auto-fill, minmax(min(200px, 100%), 1fr)); }
.tv-sig { padding: 12px; border: 1px solid #ECEAE6; border-radius: 12px; }
.tv-sig.is-missing { border-style: dashed; background: #FAFAF8; }
.tv-sig-role { font-size: 12.5px; color: #6B6862; }
.tv-sig img { display: block; width: 100%; height: 72px; margin: 6px 0; object-fit: contain; }
.tv-sig-skip { display: grid; place-items: center; height: 72px; margin: 6px 0; border-radius: 8px; background: #FFFBF5; color: #92400E; font-size: 12.5px; text-align: center; }
.tv-sig-wait { display: grid; place-items: center; height: 72px; margin: 6px 0; color: #8A867F; font-size: 12.5px; }
.tv-sig-name { font-size: 13.5px; font-weight: 500; }
.tv-round { margin: 0 0 8px; font-size: 13px; font-weight: 600; color: #3F3D39; }
.tv-round + .tv-sigs { margin-bottom: 16px; }
.tv-timeline { display: grid; gap: 0; margin: 0; padding: 0; list-style: none; }
.tv-timeline li { position: relative; padding: 0 0 14px 20px; font-size: 13.5px; }
.tv-timeline li::before { content: ''; position: absolute; top: 6px; left: 0; width: 8px; height: 8px; border-radius: 50%; background: #BDB8B0; }
.tv-timeline li::after { content: ''; position: absolute; top: 16px; bottom: 0; left: 3.5px; width: 1px; background: #E7E4DF; }
.tv-timeline li:last-child::after { display: none; }
.tv-timeline li:last-child::before { background: var(--theme-primary, #161616); }
.tv-timeline-note { margin-top: 2px; color: #57544F; }
.tv-alert { padding: 12px 14px; border: 1px solid #F1D9BF; border-radius: 12px; background: #FFFBF5; color: #7C2D12; font-size: 13.5px; }
.tv-alert b { color: #161616; }
.tv-kv { display: grid; gap: 8px 20px; grid-template-columns: repeat(auto-fill, minmax(min(220px, 100%), 1fr)); margin: 0; }
.tv-kv > div { min-width: 0; overflow-wrap: anywhere; }
.tv-kv dt { font-size: 12.5px; color: #6B6862; }
.tv-kv dd { margin: 2px 0 0; font-size: 14px; color: #161616; white-space: pre-line; }
</style>
HTML;
}

/** แถบขั้นตอนของใบงานตามสถานะปัจจุบัน */
function tv_steps(string $status): string
{
    $steps = [
        ['pending', 'รอรับเรื่อง'],
        ['accepted', 'รับงาน / เช็คลิสต์'],
        ['awaiting_intake_sign', 'ผู้แจ้งรับทราบ'],
        ['in_progress', 'กำลังซ่อม'],
        ['inspected_ready', 'ตรวจซ้ำ / สตาฟเซ็น'],
        ['awaiting_requester_sign', 'ผู้แจ้งเซ็นรับคืน'],
        ['awaiting_admin_sign', 'ครูรับรอง'],
        ['completed', 'เสร็จสมบูรณ์'],
    ];
    $order = array_column($steps, 0);
    $current = $status === 'disputed' ? array_search('awaiting_requester_sign', $order, true) : array_search($status, $order, true);
    if ($status === 'cancelled') {
        return '<div class="tv-alert">ใบงานนี้ถูกยกเลิกแล้ว</div>';
    }
    $html = '<ol class="tv-steps" aria-label="ขั้นตอนของใบงาน">';
    foreach ($steps as $i => [$key, $label]) {
        $cls = $current === false ? '' : ($i < $current || $status === 'completed' ? 'is-done' : ($i === $current ? 'is-current' : ''));
        if ($status === 'disputed' && $i === $current) {
            $cls = 'is-alert';
            $label = 'ผู้แจ้งไม่ยอมรับ';
        }
        $html .= '<li class="tv-step ' . $cls . '"' . ($cls === 'is-current' || $cls === 'is-alert' ? ' aria-current="step"' : '') . '><span class="tv-step-bar"></span><span class="tv-step-label">' . tv_h($label) . '</span></li>';
    }
    return $html . '</ol>';
}

/** ตารางเช็คลิสต์ (ตอนรับ และผลตอนส่งคืนถ้ามี) */
function tv_checklist(array $items, int $ticketId, string $base = ''): string
{
    if (!$items) {
        return '<p class="tv-muted">ยังไม่มีเช็คลิสต์อุปกรณ์</p>';
    }
    $hasOut = (bool)array_filter($items, static fn($i) => $i['result_out'] !== null);
    $html = '<div class="tv-table-wrap"><table class="tv-table"><thead><tr><th>อุปกรณ์</th><th>เลขเครื่อง/ครุภัณฑ์</th><th>ตอนรับ</th>'
        . ($hasOut ? '<th>ตอนส่งคืน</th>' : '') . '</tr></thead><tbody>';
    foreach ($items as $it) {
        $diff = $it['result_out'] !== null && $it['result_out'] !== 'returned';
        $in = (int)$it['qty_in'] === 0
            ? '<span class="tv-tag">ไม่มีมา</span>'
            : '<span class="tv-tag ' . ($it['condition_in'] === 'normal' ? 'tv-tag--ok' : 'tv-tag--warn') . '">' . tv_h(ITEM_CONDITION_LABELS[$it['condition_in']] ?? $it['condition_in']) . '</span> × ' . (int)$it['qty_in'];
        if ($it['note_in']) {
            $in .= '<div class="tv-muted">' . tv_h($it['note_in']) . '</div>';
        }
        if ($it['photo_in']) {
            $in .= '<a class="tv-photo" target="_blank" rel="noopener" href="' . tv_h($base) . 'file.php?t=' . $ticketId . '&amp;k=item_in&amp;i=' . (int)$it['id'] . '">รูปตอนรับ</a>';
        }
        $out = '';
        if ($hasOut) {
            if ($it['result_out'] === null) {
                $out = '<span class="tv-muted">-</span>';
            } else {
                $out = '<span class="tv-tag ' . ($diff ? 'tv-tag--warn' : 'tv-tag--ok') . '">' . tv_h(ITEM_RESULT_LABELS[$it['result_out']] ?? $it['result_out']) . '</span> × ' . (int)$it['qty_out'];
                if ($it['note_out']) {
                    $out .= '<div class="tv-muted">' . tv_h($it['note_out']) . '</div>';
                }
                if ($it['photo_out']) {
                    $out .= '<a class="tv-photo" target="_blank" rel="noopener" href="' . tv_h($base) . 'file.php?t=' . $ticketId . '&amp;k=item_out&amp;i=' . (int)$it['id'] . '">รูปตอนส่งคืน</a>';
                }
            }
        }
        $html .= '<tr class="' . ($diff ? 'is-diff' : '') . '"><td><div>' . tv_h($it['item_name']) . '</div>'
            . ($it['item_type'] === 'main' ? '<span class="tv-tag tv-tag--main">อุปกรณ์หลัก</span>' : '<span class="tv-muted">อุปกรณ์พ่วง</span>') . '</td>'
            . '<td data-label="เลขเครื่อง/ครุภัณฑ์">' . ($it['serial_no'] ? tv_h($it['serial_no']) : '<span class="tv-muted">-</span>') . '</td><td data-label="ตอนรับ">' . $in . '</td>'
            . ($hasOut ? '<td data-label="ตอนส่งคืน">' . $out . '</td>' : '') . '</tr>';
    }
    return $html . '</tbody></table></div>';
}

/** ลายเซ็นทั้งหมด แยกตามขั้นตอนรับ และแต่ละรอบของการส่งคืน ($ticket['has_intake'] = ใบงานมีเช็คลิสต์ตอนรับ) */
function tv_signatures(array $signoffs, int $ticketId, string $base, array $ticket): string
{
    $sigCard = static function (?array $s, string $roleLabel) use ($ticketId, $base): string {
        if ($s === null) {
            return '<div class="tv-sig is-missing"><div class="tv-sig-role">' . tv_h($roleLabel) . '</div><div class="tv-sig-wait">ยังไม่ได้เซ็น</div></div>';
        }
        $name = trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? '')) ?: '-';
        $img = $s['method'] === 'skip'
            ? '<div class="tv-sig-skip">ข้ามขั้นโดยครูผู้ดูแล</div>'
            : ($s['signature_image'] ? '<img src="' . tv_h($base) . 'file.php?t=' . $ticketId . '&amp;g=' . (int)$s['id'] . '" alt="ลายเซ็น ' . tv_h($name) . '" loading="lazy">' : '<div class="tv-sig-wait">-</div>');
        return '<div class="tv-sig"><div class="tv-sig-role">' . tv_h($roleLabel) . ($s['method'] === 'saved' ? ' · ลายเซ็นที่บันทึกไว้' : '') . '</div>' . $img
            . '<div class="tv-sig-name">' . tv_h($name) . '</div><div class="tv-muted">' . tv_h(tv_dt($s['signed_at'])) . '</div>'
            . ($s['note'] ? '<div class="tv-muted">' . tv_h($s['note']) . '</div>' : '') . '</div>';
    };
    $byKey = [];
    $rounds = [];
    foreach ($signoffs as $s) {
        $byKey[$s['stage'] . ':' . $s['round_no'] . ':' . $s['signer_role']] = $s;
        if ($s['stage'] === 'return') {
            $rounds[(int)$s['round_no']] = true;
        }
    }
    $currentRound = (int)($ticket['sign_round'] ?? 1);
    $rounds[$currentRound] = true;
    ksort($rounds);

    $html = '';
    $intake = $byKey['intake:1:requester'] ?? null;
    if ($intake !== null || ($ticket['has_intake'] ?? false)) {
        $html .= '<p class="tv-round">ตอนรับอุปกรณ์</p><div class="tv-sigs">' . $sigCard($intake, 'ผู้แจ้งรับทราบเช็คลิสต์') . '</div>';
    }
    foreach (array_keys($rounds) as $r) {
        $html .= '<p class="tv-round">ส่งคืนและปิดงาน' . (count($rounds) > 1 ? ' · รอบที่ ' . $r . ($r < $currentRound ? ' (ถูกส่งกลับแก้)' : '') : '') . '</p><div class="tv-sigs">';
        foreach (['staff', 'requester', 'admin'] as $role) {
            $html .= $sigCard($byKey['return:' . $r . ':' . $role] ?? null, SIGNER_ROLE_LABELS[$role]);
        }
        $html .= '</div>';
    }
    return $html;
}

function tv_timeline(array $logs): string
{
    if (!$logs) {
        return '<p class="tv-muted">ยังไม่มีประวัติ</p>';
    }
    $html = '<ol class="tv-timeline">';
    foreach ($logs as $l) {
        $who = trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? '')) ?: 'ระบบ';
        $label = $l['old_status'] === $l['new_status'] ? 'บันทึกเพิ่มเติม' : (TICKET_STATUS_LABELS[$l['new_status']] ?? $l['new_status']);
        $html .= '<li><div><b>' . tv_h($label) . '</b> <span class="tv-muted">· ' . tv_h($who) . ' · ' . tv_h(tv_dt($l['created_at'])) . '</span></div>'
            . (!empty($l['note']) ? '<div class="tv-timeline-note">' . tv_h($l['note']) . '</div>' : '') . '</li>';
    }
    return $html . '</ol>';
}

/** สรุปข้อมูลใบงาน (ไม่แสดงข้อมูลติดต่อเมื่อ $withContact = false) */
function tv_summary(array $t, string $base, bool $withContact = true): string
{
    $cat = !empty($t['custom_category']) ? $t['custom_category'] : ($t['category_name'] ?? '-');
    $primaryName = trim((string)($t['primary_contact_name'] ?? '')) ?: trim((string)$t['req_first'] . ' ' . (string)$t['req_last']);
    $primaryPhone = (string)($t['primary_contact_phone'] ?? $t['req_phone'] ?? '');
    $primaryEmail = (string)($t['primary_contact_email'] ?? $t['req_email'] ?? '');
    $secondaryName = (string)($t['secondary_contact_name'] ?? '');
    $secondaryPhone = (string)($t['secondary_contact_phone'] ?? '');
    $secondaryEmail = (string)($t['secondary_contact_email'] ?? '');
    $html = '<dl class="tv-kv">'
        . '<div><dt>สถานที่</dt><dd>' . tv_h($t['location_note']) . '</dd></div>'
        . '<div><dt>หมวดหมู่</dt><dd>' . tv_h($cat) . ' (จำนวน ' . (int)$t['quantity'] . ')</dd></div>'
        . '<div><dt>ผู้ติดต่อหลัก (ผู้แจ้ง)</dt><dd>' . tv_h($primaryName)
        . ($withContact && $primaryPhone !== '' ? ' · ' . tv_h($primaryPhone) : '')
        . ($withContact && $primaryEmail !== '' ? ' · ' . tv_h($primaryEmail) : '') . '</dd></div>'
        . ($withContact && ($secondaryName !== '' || $secondaryPhone !== '' || $secondaryEmail !== '')
            ? '<div><dt>ผู้ติดต่อสำรอง</dt><dd>' . tv_h($secondaryName)
                . ($secondaryPhone !== '' ? ' · ' . tv_h($secondaryPhone) : '')
                . ($secondaryEmail !== '' ? ' · ' . tv_h($secondaryEmail) : '') . '</dd></div>'
            : '')
        . '<div><dt>ช่างหลัก</dt><dd>' . tv_h(trim(($t['staff_first'] ?? '') . ' ' . ($t['staff_last'] ?? '')) ?: '-') . '</dd></div>'
        . (!empty($t['device_detail']) ? '<div><dt>อุปกรณ์ที่แจ้ง</dt><dd>' . tv_h($t['device_detail']) . '</dd></div>' : '')
        . '<div style="grid-column: 1 / -1"><dt>อาการที่แจ้ง</dt><dd>' . tv_h($t['symptom']) . '</dd></div>';
    if (!empty($t['repair_method'])) {
        $html .= '<div style="grid-column: 1 / -1"><dt>สิ่งที่ซ่อม</dt><dd>' . tv_h($t['repair_method']) . '</dd></div>'
            . '<div style="grid-column: 1 / -1"><dt>สรุปผล</dt><dd>' . tv_h($t['resolution_summary']) . '</dd></div>';
    }
    $html .= '</dl>';
    $photos = [];
    if (!empty($t['intake_photo'])) {
        $photos[] = '<a class="tv-photo" target="_blank" rel="noopener" href="' . tv_h($base) . 'file.php?t=' . (int)$t['id'] . '&amp;k=intake">รูปรวมตอนรับอุปกรณ์</a>';
    }
    $done = $t['completion_photos'] ?? (!empty($t['completion_image']) ? [['query' => 't=' . (int)$t['id'] . '&k=completion']] : []);
    foreach ($done as $n => $p) {
        $photos[] = '<a class="tv-photo" target="_blank" rel="noopener" href="' . tv_h($base . 'file.php?' . $p['query']) . '">รูปหลังซ่อม' . (count($done) > 1 ? ' ' . ($n + 1) : '') . '</a>';
    }
    if ($photos) {
        $html .= '<div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:10px">' . implode('', $photos) . '</div>';
    }
    return $html;
}
