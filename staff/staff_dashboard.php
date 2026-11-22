<?php
declare(strict_types=1);

/**
 * REPS · staff/staff_dashboard.php — แดชบอร์ดงานของสตาฟ (หัวข้อ 3.2)
 * - เปิด/ปิดรับคิว, รับงาน (เฉพาะคิวกลางหรือคิวของตัวเอง), ปฏิเสธงานตามคิว (ตามกติกาใน system_settings)
 * - งานที่ทีมถืออยู่: ขั้นตอนต่อไปของแต่ละใบ (เช็คลิสต์ตอนรับ / ซ่อมและตรวจซ้ำ / เซ็นส่งมอบ) ทำในหน้า job.php
 * - ดูงานที่รับอยู่ งานรอรับรอง งานที่เสร็จ คะแนนและประวัติคะแนน
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/user_auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/site_config.php';
require_once __DIR__ . '/../includes/ticket_service.php';

/** @var mysqli $conn */
$userId = require_role($conn, ['staff', 'admin'], '../login.php?next=staff');
$isAdmin = current_role() === 'admin';

queue_process_expired_tickets($conn);
ticket_send_sign_reminders($conn); // เตือนผู้แจ้งที่ยังไม่เซ็น ทำงานไม่เกินทุก 10 นาที
$q = queue_settings($conn);

$siteName = app_site_name($conn);
$csrfToken = app_csrf_token();

function h(mixed $v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function staff_redirect(string $msg, string $type = 'ok'): never
{
    $_SESSION['staff_flash'] = ['msg' => $msg, 'type' => $type];
    header('Location: staff_dashboard.php', true, 303);
    exit;
}

$flash = is_array($_SESSION['staff_flash'] ?? null) ? $_SESSION['staff_flash'] : null;
unset($_SESSION['staff_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_csrf_is_valid($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('คำขอหมดอายุ กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $ticketId = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT) ?: 0;

    try {
        if ($action === 'toggle_queue') {
            $newState = (int)($_POST['is_active_queue'] ?? 0) === 1 ? 1 : 0;
            db_exec($conn, "UPDATE users SET is_active_queue = ? WHERE user_id = ? AND role = 'staff'", 'ii', [$newState, $userId]);
            staff_redirect($newState === 1 ? 'เปิดสถานะรับคิวงานแล้ว' : 'พักการรับคิวงานชั่วคราวแล้ว');
        }

        if ($action === 'accept_ticket' && $ticketId > 0) {
            if (ticket_accept($conn, $ticketId, $userId)) {
                // ขั้นต่อไปคือบันทึกเช็คลิสต์ตอนรับ (v3 3.1)
                $_SESSION['job_flash'] = ['msg' => 'รับงานแล้ว ขั้นต่อไป: บันทึกเช็คลิสต์อุปกรณ์ตอนรับร่วมกับผู้แจ้ง', 'type' => 'ok'];
                header('Location: job.php?id=' . $ticketId, true, 303);
                exit;
            }
            staff_redirect('รับงานนี้ไม่ได้ (มีคนรับไปแล้ว หรือเป็นคิวของสตาฟคนอื่น)', 'err');
        }

        if ($action === 'decline_ticket' && $ticketId > 0) {
            $result = queue_decline_ticket($conn, $ticketId, $userId);
            staff_redirect($result['message'], $result['ok'] ? 'warn' : 'err');
        }

    } catch (AppUserException $e) {
        staff_redirect($e->getMessage(), 'err');
    } catch (Throwable $e) {
        error_log('Staff action "' . $action . '" failed: ' . $e->getMessage());
        staff_redirect('ทำรายการไม่สำเร็จ กรุณาลองใหม่อีกครั้ง', 'err');
    }
    staff_redirect('คำสั่งไม่ถูกต้อง', 'err');
}

// ---------- ข้อมูลสำหรับแสดงผล ----------
$sdTerm = term_current($conn);
// คะแนน งานเสร็จ และประวัติคะแนน นับเฉพาะภาคเรียนปัจจุบัน (สตาฟเปลี่ยนทุกภาคเรียน)
$me = db_one($conn, 'SELECT u.first_name, u.last_name, u.role, ' . term_score_sql($sdTerm) . ' AS score, u.queue_order, u.is_active_queue FROM users u WHERE u.user_id = ?', 'i', [$userId]) ?? [];
$isStaffRole = ($me['role'] ?? '') === 'staff';
$inTermTeam = !$isStaffRole || term_staff_allowed($conn, $userId);

$ticketSelect = "SELECT t.*, c.name AS category_name, u.first_name AS req_first, u.last_name AS req_last, u.phone_number AS req_phone
                   FROM tickets t
                   LEFT JOIN categories c ON c.id = t.category_id
                   JOIN users u ON u.user_id = t.requester_id";

$pendingTickets = !$inTermTeam ? [] : db_all(
    $conn,
    "$ticketSelect WHERE t.status = 'pending' AND (t.queue_assigned_to = ? OR t.queue_assigned_to IS NULL)
     ORDER BY (t.queue_assigned_to = ?) DESC, (t.urgency = 'urgent') DESC, t.created_at ASC",
    'ii',
    [$userId, $userId]
);
// งานของฉัน = เป็นช่างหลัก หรือเป็นผู้ช่วยในทีม
$myActive = db_all($conn, "$ticketSelect WHERE t.status IN ('accepted', 'awaiting_intake_sign', 'in_progress', 'inspected_ready') AND (t.assigned_to = ? OR EXISTS (SELECT 1 FROM ticket_assignees ta WHERE ta.ticket_id = t.id AND ta.user_id = ?)) ORDER BY t.status IN ('accepted', 'inspected_ready') DESC, (t.urgency = 'urgent') DESC, t.accepted_at ASC", 'ii', [$userId, $userId]);
$myAwaiting = db_all($conn, "$ticketSelect WHERE t.status IN ('awaiting_requester_sign', 'awaiting_admin_sign', 'disputed') AND (t.assigned_to = ? OR EXISTS (SELECT 1 FROM ticket_assignees ta WHERE ta.ticket_id = t.id AND ta.user_id = ?)) ORDER BY t.id DESC", 'ii', [$userId, $userId]);
$myDone = db_all($conn, "$ticketSelect WHERE t.status = 'completed'" . term_date_sql($sdTerm, 't.completed_at') . " AND (t.assigned_to = ? OR EXISTS (SELECT 1 FROM ticket_assignees ta WHERE ta.ticket_id = t.id AND ta.user_id = ?)) ORDER BY t.completed_at DESC LIMIT 50", 'ii', [$userId, $userId]);
$doneCount = (int)(db_one($conn, "SELECT COUNT(*) AS n FROM tickets t WHERE t.status = 'completed'" . term_date_sql($sdTerm, 't.completed_at') . " AND (t.assigned_to = ? OR EXISTS (SELECT 1 FROM ticket_assignees ta WHERE ta.ticket_id = t.id AND ta.user_id = ?))", 'ii', [$userId, $userId])['n'] ?? 0);

$attachments = [];
$listedIds = array_map(static fn($t) => (int)$t['id'], array_merge($pendingTickets, $myActive));
if ($listedIds) {
    $rows = db_all(
        $conn,
        'SELECT id, ticket_id FROM ticket_attachments WHERE ticket_id IN (' . implode(',', array_fill(0, count($listedIds), '?')) . ') ORDER BY id',
        str_repeat('i', count($listedIds)),
        $listedIds
    );
    foreach ($rows as $r) {
        $attachments[(int)$r['ticket_id']][] = (int)$r['id'];
    }
}

$teamByTicket = [];
$teamIds = array_map(static fn($t) => (int)$t['id'], $myActive);
if ($teamIds) {
    $rows = db_all(
        $conn,
        'SELECT a.ticket_id, u.first_name, u.last_name FROM ticket_assignees a JOIN users u ON u.user_id = a.user_id WHERE a.ticket_id IN (' . implode(',', array_fill(0, count($teamIds), '?')) . ')',
        str_repeat('i', count($teamIds)),
        $teamIds
    );
    foreach ($rows as $r) {
        $teamByTicket[(int)$r['ticket_id']][] = $r['first_name'] . ' ' . $r['last_name'];
    }
}
$leadNames = [];
foreach ($myActive as $t) {
    if ((int)$t['assigned_to'] !== $userId) {
        $lead = db_one($conn, 'SELECT first_name, last_name FROM users WHERE user_id = ?', 'i', [(int)$t['assigned_to']]);
        $leadNames[(int)$t['id']] = $lead ? $lead['first_name'] . ' ' . $lead['last_name'] : '';
    }
}

$lowStockCount = (int)(db_one($conn, 'SELECT COUNT(*) AS n FROM inventory_items WHERE stock_qty <= min_stock')['n'] ?? 0);
$scoreHistory = db_all($conn, 'SELECT points_change, reason, created_at FROM score_logs WHERE user_id = ?' . term_date_sql($sdTerm, 'created_at') . ' ORDER BY created_at DESC, id DESC LIMIT 50', 'i', [$userId]);

$penaltyLabel = $q['mode'] === 'auto' && $q['penalty'] > 0 ? ' (-' . $q['penalty'] . ' คะแนน)' : '';

function staff_ticket_meta(array $t): void
{
    $cat = !empty($t['custom_category']) ? $t['custom_category'] : ($t['category_name'] ?? 'ทั่วไป');
    $primaryName = trim((string)($t['primary_contact_name'] ?? '')) ?: trim((string)$t['req_first'] . ' ' . (string)$t['req_last']);
    $primaryPhone = (string)($t['primary_contact_phone'] ?? $t['req_phone'] ?? '');
    $secondaryName = (string)($t['secondary_contact_name'] ?? '');
    $secondaryPhone = (string)($t['secondary_contact_phone'] ?? '');
    $secondaryEmail = (string)($t['secondary_contact_email'] ?? '');
    ?>
    <div class="font-medium text-neutral-900 text-sm"><?= h($t['location_note']) ?> · <?= h($cat) ?> (จำนวน <?= (int)$t['quantity'] ?>)</div>
    <?php if (!empty($t['device_detail'])): ?>
        <div class="text-neutral-600">อุปกรณ์: <?= h($t['device_detail']) ?></div>
    <?php endif; ?>
    <p class="text-neutral-600 whitespace-pre-line"><?= h($t['symptom']) ?></p>
    <div class="text-neutral-500 pt-1 flex flex-wrap gap-x-4 gap-y-1">
        <span>ผู้ติดต่อหลัก (ผู้แจ้ง): <?= h($primaryName) ?><?= $primaryPhone !== '' ? ' (' . h($primaryPhone) . ')' : '' ?></span>
        <?php if ($secondaryName !== '' || $secondaryPhone !== '' || $secondaryEmail !== ''): ?>
            <span>ผู้ติดต่อสำรอง: <?= h($secondaryName) ?><?= $secondaryPhone !== '' ? ' (' . h($secondaryPhone) . ')' : '' ?><?= $secondaryEmail !== '' ? ' · ' . h($secondaryEmail) : '' ?></span>
        <?php endif; ?>
        <?php if (!empty($t['contact_person']) && $t['contact_person'] !== $primaryName . ($primaryPhone !== '' ? ' (' . $primaryPhone . ')' : '')): ?><span>ผู้ติดต่อหน้างาน: <?= h($t['contact_person']) ?></span><?php endif; ?>
        <?php if (!empty($t['coordinator_teacher'])): ?>
            <span>ครูผู้ประสานงาน: <?= h($t['coordinator_teacher']) ?><?= !empty($t['coordinator_phone']) ? ' (' . h($t['coordinator_phone']) . ')' : '' ?></span>
        <?php endif; ?>
        <?php if (!empty($t['room_caretaker'])): ?>
            <span>ครูผู้ดูแลห้อง: <?= h($t['room_caretaker']) ?><?= !empty($t['room_caretaker_phone']) ? ' (' . h($t['room_caretaker_phone']) . ')' : '' ?></span>
        <?php endif; ?>
    </div>
    <?php
}

function staff_attachment_links(array $ids, int $ticketId): void
{
    if (!$ids) return;
    echo '<div class="flex flex-wrap gap-2 pt-1">';
    foreach ($ids as $n => $aid) {
        echo '<a class="text-[11px] underline text-neutral-700" target="_blank" rel="noopener" href="../file.php?t=' . $ticketId . '&amp;a=' . $aid . '">รูปแนบ ' . ($n + 1) . '</a>';
    }
    echo '</div>';
}
?>
<?php $adminShell = current_role() === 'admin'; ?>
<?php if ($adminShell):
    $page_title = 'มุมมองสตาฟ';
    $current_page = 'staffview';
    ob_start(); ?>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Kanit', 'sans-serif'], kanit: ['Kanit', 'sans-serif'], mono: ['Kanit', 'sans-serif'] } } } };</script>
<?= app_theme_css($conn) ?>
<style>
        body { background: #FAFAF8; color: #161616; font-family: 'Kanit', sans-serif; }
        .tray { padding: 6px; border: 1px solid #EAE8E4; border-radius: 20px; background: #F7F6F4; }
        .tray-card { border: 1px solid #ECEAE6; border-radius: 14px; background: #FFFFFF; }
        .sd-tabs { display: flex; gap: 4px; padding: 2px 2px 8px; overflow-x: auto; }
        .sd-tab { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; padding: 0 14px; border: 1px solid transparent; border-radius: 12px; background: transparent; color: #6B6862; font: inherit; font-size: 14px; white-space: nowrap; cursor: pointer; transition: background-color .15s ease, color .15s ease, border-color .15s ease; }
        .sd-tab:hover { color: #161616; background: #EFEDEA; }
        .sd-tab[aria-selected="true"] { border-color: #ECEAE6; background: #FFFFFF; color: #161616; font-weight: 500; box-shadow: 0 1px 2px rgba(22,22,22,.05); }
        .sd-tab i { font-size: 12px; }
        .sd-tab__count { min-width: 22px; padding: 0 7px; border-radius: 999px; background: #EAE8E4; color: #3F3D39; font-size: 12px; line-height: 20px; text-align: center; font-variant-numeric: tabular-nums; }
        .sd-tab[aria-selected="true"] .sd-tab__count { background: var(--theme-primary, #161616); color: #FFFFFF; }
        .sd-tab__alert { padding: 0 8px; border-radius: 999px; background: #D97706; color: #FFFFFF; font-size: 11.5px; line-height: 20px; }
        @media (max-width: 480px) { .sd-tab { flex: 1 1 0; justify-content: center; padding: 0 8px; gap: 6px; } .sd-tab > i { display: none; } }
    </style>
<?php $admin_extra_head = ob_get_clean();
    require __DIR__ . '/../admin/admin_header.php';
else: ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>แดชบอร์ดสตาฟ | <?= h($siteName) ?></title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo/reps-icon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Kanit', 'sans-serif'], kanit: ['Kanit', 'sans-serif'], mono: ['Kanit', 'sans-serif'] } } } };</script>
    <?= app_theme_css($conn) ?>
    <style>
        body { background: #FAFAF8; color: #161616; font-family: 'Kanit', sans-serif; }
        .tray { padding: 6px; border: 1px solid #EAE8E4; border-radius: 20px; background: #F7F6F4; }
        .tray-card { border: 1px solid #ECEAE6; border-radius: 14px; background: #FFFFFF; }
        .sd-tabs { display: flex; gap: 4px; padding: 2px 2px 8px; overflow-x: auto; }
        .sd-tab { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; padding: 0 14px; border: 1px solid transparent; border-radius: 12px; background: transparent; color: #6B6862; font: inherit; font-size: 14px; white-space: nowrap; cursor: pointer; transition: background-color .15s ease, color .15s ease, border-color .15s ease; }
        .sd-tab:hover { color: #161616; background: #EFEDEA; }
        .sd-tab[aria-selected="true"] { border-color: #ECEAE6; background: #FFFFFF; color: #161616; font-weight: 500; box-shadow: 0 1px 2px rgba(22,22,22,.05); }
        .sd-tab i { font-size: 12px; }
        .sd-tab__count { min-width: 22px; padding: 0 7px; border-radius: 999px; background: #EAE8E4; color: #3F3D39; font-size: 12px; line-height: 20px; text-align: center; font-variant-numeric: tabular-nums; }
        .sd-tab[aria-selected="true"] .sd-tab__count { background: var(--theme-primary, #161616); color: #FFFFFF; }
        .sd-tab__alert { padding: 0 8px; border-radius: 999px; background: #D97706; color: #FFFFFF; font-size: 11.5px; line-height: 20px; }
        @media (max-width: 480px) { .sd-tab { flex: 1 1 0; justify-content: center; padding: 0 8px; gap: 6px; } .sd-tab > i { display: none; } }
    </style>
   <link rel="stylesheet" href="../assets/css/base.css?v=18">
</head>
<body class="min-h-screen flex flex-col antialiased">

    <?php $staffNavActive = 'jobs'; $staffNavBadge = $lowStockCount; require __DIR__ . '/../includes/staff_nav.php'; ?>
<?php endif; ?>

    <main class="<?= $adminShell ? 'w-full space-y-6' : 'flex-1 max-w-5xl w-full mx-auto px-4 sm:px-7 py-7 space-y-6' ?>">

        <?php if ($flash): ?>
            <?php $ft = (string)($flash['type'] ?? 'ok'); ?>
            <div role="status" class="rounded-xl border p-3.5 text-xs sm:text-sm <?= $ft === 'err' ? 'border-red-200 bg-red-50 text-red-800' : ($ft === 'warn' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800') ?>">
                <?= h($flash['msg'] ?? '') ?>
            </div>
        <?php endif; ?>

        <?php if (!$inTermTeam): ?>
            <div role="status" class="rounded-xl border border-amber-200 bg-amber-50 p-3.5 text-sm text-amber-900">
                คุณไม่ได้อยู่ในทีมสตาฟของ<?= h(term_title($sdTerm)) ?> จึงไม่ได้รับคิวและรับงานใหม่ไม่ได้ งานที่ค้างอยู่ยังทำต่อจนจบได้ตามปกติ หากควรอยู่ในทีม กรุณาติดต่อครูผู้ดูแล
            </div>
        <?php endif; ?>

        <!-- สถานะสตาฟ -->
        <section class="tray">
            <div class="tray-card p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="text-xs text-neutral-500"><?= $isAdmin ? 'ผู้ดูแลระบบ (ใช้งานในมุมมองสตาฟ)' : 'เจ้าหน้าที่ฝ่ายซ่อม (Staff)' ?><?= $sdTerm ? ' · ' . h(term_title($sdTerm)) : '' ?></div>
                    <h1 class="text-xl sm:text-2xl font-medium text-neutral-900 mt-0.5"><?= h(($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? '')) ?></h1>
                    <div class="flex flex-wrap items-center gap-2.5 mt-2 text-xs">
                        <?php if ($isStaffRole): ?>
                            <span class="font-mono font-semibold px-2.5 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200">
                                <i class="fa-solid fa-star text-amber-500 mr-1"></i> คะแนนภาคเรียนนี้ <?= (int)($me['score'] ?? 0) ?> คะแนน
                            </span>
                            <span class="font-mono px-2.5 py-1 rounded-full bg-neutral-100 text-neutral-700">
                                ลำดับคิว: <?= ($me['queue_order'] ?? null) !== null ? '#' . (int)$me['queue_order'] : 'ยังไม่กำหนด' ?>
                            </span>
                        <?php endif; ?>
                        <span class="px-2.5 py-1 rounded-full bg-neutral-100 text-neutral-700">โหมดจ่ายงาน: <?= $q['mode'] === 'auto' ? 'อัตโนมัติ (รับภายใน ' . $q['timeout'] . ' นาที)' : 'admin มอบหมายเอง' ?></span>
                        <span class="px-2.5 py-1 rounded-full bg-neutral-100 text-neutral-700">เสร็จสมบูรณ์ในภาคเรียนนี้ <?= $doneCount ?> งาน</span>
                    </div>
                </div>

                <?php if ($isStaffRole): ?>
                    <form method="POST" class="flex items-center gap-2 w-full sm:w-auto">
                        <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">
                        <input type="hidden" name="action" value="toggle_queue">
                        <?php if ((int)($me['is_active_queue'] ?? 0) === 1): ?>
                            <input type="hidden" name="is_active_queue" value="0">
                            <button type="submit" class="inline-flex items-center justify-center gap-2 w-full sm:w-auto min-h-[44px] px-4 rounded-xl bg-emerald-700 text-white text-sm font-medium hover:bg-emerald-800 transition cursor-pointer">
                                <i class="fa-solid fa-circle-play" aria-hidden="true"></i> เปิดรับคิวอยู่ · กดเพื่อพักคิว
                            </button>
                        <?php else: ?>
                            <input type="hidden" name="is_active_queue" value="1">
                            <button type="submit" class="inline-flex items-center justify-center gap-2 w-full sm:w-auto min-h-[44px] px-4 rounded-xl bg-neutral-800 text-white text-sm font-medium hover:bg-neutral-900 transition cursor-pointer">
                                <i class="fa-solid fa-circle-pause" aria-hidden="true"></i> พักคิวอยู่ · กดเพื่อเปิดรับงาน
                            </button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>
        </section>

        <!-- คิวงานรอรับ + งานที่กำลังทำ: การ์ดเดียว สลับด้วยแท็บ (เริ่มที่คิวงาน · ลิงก์ #mine เปิดแท็บงานของฉัน) -->
        <?php $myTurnCount = count(array_filter($pendingTickets, static fn(array $pt): bool => (int)($pt['queue_assigned_to'] ?? 0) === $userId)); ?>
        <section class="tray" data-job-tabs>
            <div class="sd-tabs" role="tablist" aria-label="รายการงาน">
                <button type="button" class="sd-tab" role="tab" id="tab-queue" aria-controls="panel-queue" aria-selected="true" data-tab="queue">
                    <i class="fa-solid fa-bell" aria-hidden="true"></i><span>คิวงานที่รอรับ</span><span class="sd-tab__count"><?= count($pendingTickets) ?></span>
                    <?php if ($myTurnCount > 0): ?><span class="sd-tab__alert">ถึงคิวคุณ <?= $myTurnCount ?></span><?php endif; ?>
                </button>
                <button type="button" class="sd-tab" role="tab" id="tab-mine" aria-controls="panel-mine" aria-selected="false" tabindex="-1" data-tab="mine">
                    <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i><span>งานที่กำลังทำ</span><span class="sd-tab__count"><?= count($myActive) ?></span>
                </button>
            </div>

            <div role="tabpanel" id="panel-mine" aria-labelledby="tab-mine" hidden>
            <p class="px-4 pb-2.5 text-neutral-500 text-[11px]">รับงาน → เช็คลิสต์ → ผู้แจ้งเซ็น → ซ่อม → ตรวจซ้ำ → เซ็นส่งมอบ</p>
            <div class="tray-card p-4 space-y-3" data-pager data-pager-size="10" id="myActiveJobs">
                <?php if (!$myActive): ?>
                    <p class="text-xs text-neutral-500 text-center py-6">ไม่มีงานที่กำลังดำเนินการ</p>
                <?php endif; ?>
                <?php foreach ($myActive as $job): ?>
                    <div class="p-4 rounded-xl border border-neutral-200 bg-[#FAFAF8] flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1 text-xs">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono font-semibold text-sm text-neutral-900">#<?= h($job['ticket_no']) ?></span>
                                <span class="px-2 py-0.5 rounded bg-neutral-100 text-neutral-700 text-xs"><?= h(TICKET_STATUS_LABELS[$job['status']] ?? $job['status']) ?></span>
                                <?php if ((int)$job['assigned_to'] === $userId): ?>
                                    <span class="px-2 py-0.5 rounded bg-neutral-900 text-white text-xs">ช่างหลัก</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded bg-sky-100 text-sky-900 text-xs">ผู้ช่วย · ช่างหลัก <?= h($leadNames[(int)$job['id']] ?? '') ?></span>
                                <?php endif; ?>
                                <?php if (!empty($teamByTicket[(int)$job['id']])): ?>
                                    <span class="text-xs text-neutral-600">ทีม: <?= h(implode(', ', $teamByTicket[(int)$job['id']])) ?></span>
                                <?php endif; ?>
                                <?php if ($job['urgency'] === 'urgent'): ?>
                                    <span class="px-2 py-0.5 rounded bg-red-100 text-red-700 font-medium">ด่วน: <?= h($job['urgency_reason'] ?? '-') ?></span>
                                <?php endif; ?>
                                <?php if (!empty($job['accepted_at'])): ?>
                                    <span class="text-neutral-500 font-mono">รับงาน <?= h(date('d/m/Y H:i', strtotime($job['accepted_at']))) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php staff_ticket_meta($job); staff_attachment_links($attachments[(int)$job['id']] ?? [], (int)$job['id']); ?>
                        </div>
                        <?php
                        $stepLabel = [
                            'accepted'             => ['บันทึกเช็คลิสต์ตอนรับ', true],
                            'awaiting_intake_sign' => ['รอผู้แจ้งเซ็นรับทราบ', false],
                            'in_progress'          => [(int)$job['sign_round'] > 1 ? 'ครูส่งกลับ · แก้และตรวจซ้ำ' : 'ซ่อมเสร็จ · ตรวจซ้ำ', true],
                            'inspected_ready'      => ['เซ็นส่งมอบ', true],
                        ][$job['status']] ?? ['เปิดใบงาน', false];
                        ?>
                        <a href="job.php?id=<?= (int)$job['id'] ?>"
                            class="shrink-0 min-h-[44px] px-5 rounded-xl <?= $stepLabel[1] ? 'bg-neutral-900 text-white hover:bg-black' : 'border border-neutral-300 bg-white text-neutral-800 hover:bg-neutral-50' ?> text-sm font-medium transition inline-flex items-center justify-center gap-2">
                            <?= h($stepLabel[0]) ?> <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            </div>

            <div role="tabpanel" id="panel-queue" aria-labelledby="tab-queue">
            <?php if ($q['mode'] === 'auto' && $q['penalty'] > 0): ?>
                <div class="px-2 pb-2.5">
                    <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"><i class="fa-solid fa-circle-info mr-1" aria-hidden="true"></i>เมื่อถึงคิวของคุณ กดรับภายใน <?= $q['timeout'] ?> นาที ถ้าไม่ทันหรือกดไม่รับ จะถูกหัก <?= $q['penalty'] ?> คะแนน แล้วงานจะส่งต่อให้คนถัดไป<?= $q['work_start'] !== '' ? ' (นับเฉพาะ ' . h($q['work_start']) . '–' . h($q['work_end']) . ' น.)' : '' ?></p>
                </div>
            <?php endif; ?>
            <div class="tray-card p-4 space-y-3" data-pager id="queueJobs">
                <?php if (!$pendingTickets): ?>
                    <p class="text-xs text-neutral-500 text-center py-6">ยังไม่มีใบแจ้งซ่อมในคิวขณะนี้</p>
                <?php endif; ?>
                <?php foreach ($pendingTickets as $pt):
                    $isMine = (int)($pt['queue_assigned_to'] ?? 0) === $userId; ?>
                    <div class="p-4 rounded-xl border <?= $isMine ? 'border-amber-300 bg-amber-50/40' : 'border-neutral-200 bg-white' ?> flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1 text-xs">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono font-semibold text-sm">#<?= h($pt['ticket_no']) ?></span>
                                <?php if ($isMine): ?>
                                    <span class="px-2 py-0.5 rounded-full bg-amber-600 text-white text-[11px] font-medium">ถึงคิวของคุณ</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full bg-neutral-100 text-neutral-600 text-[11px]">คิวกลาง</span>
                                <?php endif; ?>
                                <?php if ($pt['urgency'] === 'urgent'): ?>
                                    <span class="px-2 py-0.5 rounded-full bg-red-600 text-white text-[11px]">ด่วน</span>
                                <?php endif; ?>
                                <span class="text-neutral-500 font-mono"><?= h(date('d/m/Y H:i', strtotime($pt['created_at']))) ?></span>
                                <?php if ($isMine && !empty($pt['queue_expires_at'])): ?>
                                    <span class="text-amber-800 font-medium" data-countdown="<?= h((string)strtotime($pt['queue_expires_at'])) ?>">หมดเวลารับ <?= h(date('d/m H:i', strtotime($pt['queue_expires_at']))) ?> น.</span>
                                <?php endif; ?>
                            </div>
                            <?php staff_ticket_meta($pt); staff_attachment_links($attachments[(int)$pt['id']] ?? [], (int)$pt['id']); ?>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <form method="POST">
                                <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="action" value="accept_ticket">
                                <input type="hidden" name="ticket_id" value="<?= (int)$pt['id'] ?>">
                                <button type="submit" class="min-h-[44px] px-5 rounded-xl bg-neutral-900 text-white text-sm font-medium hover:bg-black transition cursor-pointer">รับงานนี้</button>
                            </form>
                            <?php if ($isMine): ?>
                                <form method="POST" data-confirm="ยืนยันไม่รับงานนี้?<?= $penaltyLabel !== '' ? ' ระบบจะหัก ' . $q['penalty'] . ' คะแนน' : '' ?>">
                                    <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">
                                    <input type="hidden" name="action" value="decline_ticket">
                                    <input type="hidden" name="ticket_id" value="<?= (int)$pt['id'] ?>">
                                    <button type="submit" class="min-h-[44px] px-4 rounded-xl border border-red-200 bg-white text-red-700 text-sm font-medium hover:bg-red-50 transition cursor-pointer">ไม่รับงาน<?= h($penaltyLabel) ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            </div>
        </section>

        <!-- รอครูรับรอง / เสร็จแล้ว -->
        <div class="grid gap-6 md:grid-cols-2">
            <section class="tray">
                <div class="px-4 py-2 text-xs font-semibold text-neutral-800">รอเซ็น / รอครูรับรอง (<?= count($myAwaiting) ?>)</div>
                <div class="tray-card divide-y divide-neutral-100 text-xs" data-pager id="awaitingJobs">
                    <?php if (!$myAwaiting): ?><p class="p-4 text-neutral-500 text-center">ไม่มีงานรอรับรอง</p><?php endif; ?>
                    <?php foreach ($myAwaiting as $t): ?>
                        <a class="block px-4 py-2.5 hover:bg-neutral-50" href="job.php?id=<?= (int)$t['id'] ?>">
                            <span class="font-mono font-semibold">#<?= h($t['ticket_no']) ?></span> · <?= h($t['location_note']) ?>
                            <div class="text-neutral-500"><?= h(TICKET_STATUS_LABELS[$t['status']] ?? $t['status']) ?> · <?= h($t['resolution_summary']) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <section class="tray">
                <div class="px-4 py-2 text-xs font-semibold text-neutral-800">งานที่เสร็จสมบูรณ์ล่าสุด</div>
                <div class="tray-card divide-y divide-neutral-100 text-xs" data-pager id="doneJobs">
                    <?php if (!$myDone): ?><p class="p-4 text-neutral-500 text-center">ยังไม่มีงานที่เสร็จสมบูรณ์</p><?php endif; ?>
                    <?php foreach ($myDone as $t): ?>
                        <div class="px-4 py-2.5 flex items-center justify-between gap-2">
                            <span><span class="font-mono font-semibold">#<?= h($t['ticket_no']) ?></span> · <?= h($t['location_note']) ?></span>
                            <a class="underline text-neutral-600 shrink-0" href="../certificate.php?id=<?= (int)$t['id'] ?>" target="_blank" rel="noopener">ใบรับรอง</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <?php if ($scoreHistory): ?>
            <section class="tray">
                <div class="px-4 py-2 text-xs font-medium text-neutral-600">ประวัติคะแนนล่าสุด</div>
                <div class="tray-card divide-y divide-neutral-100 text-xs" data-pager id="scoreHistory">
                    <?php foreach ($scoreHistory as $sh): $pts = (int)$sh['points_change']; ?>
                        <div class="px-4 py-2.5 flex items-center justify-between gap-2">
                            <div>
                                <span class="text-neutral-800"><?= h($sh['reason']) ?></span>
                                <span class="text-neutral-500 font-mono ml-2"><?= h(date('d/m/Y H:i', strtotime($sh['created_at']))) ?></span>
                            </div>
                            <span class="font-mono font-semibold <?= $pts >= 0 ? 'text-emerald-600' : 'text-red-600' ?>"><?= $pts >= 0 ? '+' . $pts : $pts ?> คะแนน</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>


    <script>
        (function () {
            // แท็บ คิวงานที่รอรับ / งานที่กำลังทำ — เริ่มที่คิวงาน, #mine เปิดแท็บงานของฉัน, ลูกศรซ้าย/ขวาสลับแท็บ
            var box = document.querySelector('[data-job-tabs]');
            if (box) {
                var tabs = Array.prototype.slice.call(box.querySelectorAll('[role="tab"]'));
                var select = function (tab, focus) {
                    tabs.forEach(function (t) {
                        var on = t === tab;
                        t.setAttribute('aria-selected', on ? 'true' : 'false');
                        t.tabIndex = on ? 0 : -1;
                        document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
                    });
                    if (focus) tab.focus();
                    if (history.replaceState) history.replaceState(null, '', tab.dataset.tab === 'mine' ? '#mine' : location.pathname + location.search);
                };
                tabs.forEach(function (t, i) {
                    t.addEventListener('click', function () { select(t, false); });
                    t.addEventListener('keydown', function (e) {
                        if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                            e.preventDefault();
                            select(tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length], true);
                        }
                    });
                });
                if (location.hash === '#mine') select(tabs[1], false);
            }

            // นับถอยหลังเวลารับคิว (อัปเดตทุก 30 วินาที)
            function updateCountdowns() {
                const now = Date.now() / 1000;
                document.querySelectorAll('[data-countdown]').forEach(function (el) {
                    const left = Math.round((Number(el.dataset.countdown) - now) / 60);
                    el.textContent = left > 0 ? 'เหลือเวลารับอีก ' + left + ' นาที' : 'หมดเวลารับแล้ว รอระบบส่งต่อคิว';
                });
            }
            updateCountdowns();
            setInterval(updateCountdowns, 30000);
            document.querySelectorAll('form[data-confirm]').forEach(function (form) {
                form.addEventListener('submit', function (e) {
                    if (!confirm(form.dataset.confirm)) e.preventDefault();
                });
            });
        })();
    </script>
<?php if ($adminShell): ?>
      </div>
<?php $footerBase = '../'; $footerVariant = 'app'; require __DIR__ . '/../includes/site_footer.php'; ?>
   </div>
<?php else: ?>
<?php $footerBase = '../'; $footerVariant = 'light'; require __DIR__ . '/../includes/site_footer.php'; ?>
<?php endif; ?>
</body>
</html>
