<?php
declare(strict_types=1);

/** REPS · ประวัติใบแจ้งซ่อมทั้งหมดของผู้แจ้ง (Stack & Detail Modal) */
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/user_auth.php';
require_once __DIR__ . '/includes/thanks.php';
require_once __DIR__ . '/includes/icons.php';

require_once __DIR__ . '/includes/pager.php';
const HISTORY_PER_PAGE_OPTIONS = [10, 25, 50];
define('HISTORY_PER_PAGE', pager_per_page(HISTORY_PER_PAGE_OPTIONS, 10));
const HISTORY_SEARCH_MAX = 100;

// ตัวกรอง = ช่วงหลัก (in_progress / awaiting_signoff รวมสถานะละเอียดของ v3)
$statusLabels = [
    'pending' => 'รอรับเรื่อง',
    'in_progress' => 'กำลังดำเนินการ',
    'awaiting_signoff' => 'รอเซ็นรับรอง',
    'completed' => 'เสร็จสมบูรณ์',
    'cancelled' => 'ยกเลิก',
];
$statusPhases = [
    'in_progress' => ['accepted', 'awaiting_intake_sign', 'in_progress', 'inspected_ready'],
    'awaiting_signoff' => ['awaiting_requester_sign', 'awaiting_admin_sign', 'disputed'],
];
// ป้ายของสถานะจริงแต่ละค่า (แสดงบนการ์ด)
$ticketStatusLabels = $statusLabels + [
    'accepted' => 'ช่างรับงานแล้ว',
    'awaiting_intake_sign' => 'รอคุณเซ็นรับทราบ',
    'inspected_ready' => 'ซ่อมเสร็จ รอส่งคืน',
    'awaiting_requester_sign' => 'รอคุณเซ็นรับคืน',
    'awaiting_admin_sign' => 'รอครูรับรอง',
    'disputed' => 'คุณไม่ยอมรับ รอครูพิจารณา',
];
$ticketStatusLabels['in_progress'] = 'กำลังซ่อม';

function history_h(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function history_fmt(mixed $value): string
{
    if (!is_string($value) || $value === '') {
        return '—';
    }
    $timestamp = strtotime($value);
    return $timestamp === false ? '—' : date('d/m/Y H:i', $timestamp);
}

function history_get(string $key): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function history_page_url(array $params): string
{
    if (HISTORY_PER_PAGE !== 10 && !array_key_exists('per', $params)) {
        $params['per'] = HISTORY_PER_PAGE;
    }
    $params = array_filter($params, static fn($value): bool => $value !== '' && $value !== null && $value !== 1);
    return 'history.php' . ($params === [] ? '' : '?' . http_build_query($params));
}

function history_tracker(array $ticket, array $logs): array
{
    $status = (string)($ticket['status'] ?? 'pending');
    if ($status === 'cancelled') {
        $cancelledAt = null;
        foreach ($logs as $log) {
            if (($log['new_status'] ?? '') === 'cancelled') {
                $cancelledAt = $log['created_at'] ?? null;
            }
        }
        return [
            ['label' => 'รอรับเรื่อง', 'when' => $ticket['created_at'] ?? null, 'on' => true, 'now' => false],
            ['label' => 'ยกเลิก', 'when' => $cancelledAt, 'on' => true, 'now' => true],
        ];
    }

    $phase = match ($status) {
        'accepted', 'awaiting_intake_sign', 'in_progress', 'inspected_ready' => 'in_progress',
        'awaiting_requester_sign', 'awaiting_admin_sign', 'disputed' => 'awaiting_signoff',
        default => $status,
    };
    $index = ['in_progress' => 1, 'awaiting_signoff' => 2, 'completed' => 3][$phase] ?? 0;
    $submittedAt = null;
    foreach ($logs as $log) {
        if (in_array($log['new_status'] ?? '', ['awaiting_requester_sign', 'awaiting_admin_sign'], true) && $submittedAt === null) {
            $submittedAt = $log['created_at'] ?? null;
        }
    }
    return [
        ['label' => 'รอรับเรื่อง', 'when' => $ticket['created_at'] ?? null, 'on' => true, 'now' => $index === 0],
        ['label' => 'กำลังดำเนินการ', 'when' => $ticket['accepted_at'] ?? null, 'on' => $index >= 1, 'now' => $index === 1],
        ['label' => 'รอเซ็นรับรอง', 'when' => $submittedAt, 'on' => $index >= 2, 'now' => $index === 2],
        ['label' => 'เสร็จสมบูรณ์', 'when' => $ticket['completed_at'] ?? null, 'on' => $index >= 3, 'now' => $index === 3],
    ];
}

$userId = (int)app_require_active_user($conn, 'login.php?next=history');
$profileStmt = $conn->prepare(
    'SELECT u.first_name, u.last_name, u.user_type, d.name AS department_name
     FROM users u LEFT JOIN department d ON d.d_id = u.department_id
     WHERE u.user_id = ? LIMIT 1'
);
if (!$profileStmt) {
    http_response_code(500);
    exit('โหลดข้อมูลผู้ใช้ไม่ได้ กรุณาลองใหม่ภายหลัง');
}
$profileStmt->bind_param('i', $userId);
$profileStmt->execute();
$user = $profileStmt->get_result()->fetch_assoc();
$profileStmt->close();
if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$statusFilter = history_get('status');
if (!array_key_exists($statusFilter, $statusLabels)) {
    $statusFilter = '';
}
$search = mb_substr(history_get('q'), 0, HISTORY_SEARCH_MAX, 'UTF-8');
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = is_int($page) ? max(1, $page) : 1;

$where = ['t.requester_id = ?'];
$types = 'i';
$params = [$userId];
if ($statusFilter !== '') {
    $filterStatuses = $statusPhases[$statusFilter] ?? [$statusFilter];
    $where[] = 't.status IN (' . implode(',', array_fill(0, count($filterStatuses), '?')) . ')';
    $types .= str_repeat('s', count($filterStatuses));
    array_push($params, ...$filterStatuses);
}
if ($search !== '') {
    $where[] = '(t.ticket_no LIKE ? OR t.symptom LIKE ? OR t.location_note LIKE ? OR c.name LIKE ?)';
    $term = '%' . addcslashes($search, '\\%_') . '%';
    $types .= 'ssss';
    array_push($params, $term, $term, $term, $term);
}
$whereSql = implode(' AND ', $where);

$countStmt = $conn->prepare("SELECT COUNT(*) FROM tickets t LEFT JOIN categories c ON c.id = t.category_id WHERE {$whereSql}");
if (!$countStmt) {
    http_response_code(500);
    exit('โหลดประวัติไม่ได้ กรุณาลองใหม่ภายหลัง');
}
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total = (int)$countStmt->get_result()->fetch_row()[0];
$countStmt->close();

$maxPages = max(1, (int)ceil($total / HISTORY_PER_PAGE));
$page = min($page, $maxPages);
$offset = ($page - 1) * HISTORY_PER_PAGE;
$ticketSql = "SELECT t.id AS ticket_id, t.ticket_no, t.category_id, t.symptom AS symptoms,
                     t.urgency, t.status, t.created_at, t.accepted_at, t.completed_at,
                     t.resolution_summary, t.cancel_reason, t.location_note AS location_name, t.completion_image,
                     c.name AS category_name,
                     CONCAT(tech.first_name, ' ', tech.last_name) AS tech_name
              FROM tickets t
              LEFT JOIN categories c ON c.id = t.category_id
              LEFT JOIN users tech ON tech.user_id = t.assigned_to
              WHERE {$whereSql}
              ORDER BY t.created_at DESC
              LIMIT ? OFFSET ?";
$ticketStmt = $conn->prepare($ticketSql);
if (!$ticketStmt) {
    http_response_code(500);
    exit('โหลดประวัติไม่ได้ กรุณาลองใหม่ภายหลัง');
}
$ticketTypes = $types . 'ii';
$ticketParams = $params;
$ticketParams[] = HISTORY_PER_PAGE;
$ticketParams[] = $offset;
$ticketStmt->bind_param($ticketTypes, ...$ticketParams);
$ticketStmt->execute();
$tickets = $ticketStmt->get_result()->fetch_all(MYSQLI_ASSOC);
// ใบงานที่ยังขอบคุณทีมช่างได้ (ระบบขอบคุณเปิด · เซ็นรับคืนแล้ว · ยังไม่เกินกำหนดวัน)
$thanksIds = [];
if (thanks_enabled($conn)) {
    $thanksDays = thanks_settings($conn)['days'];
    $thanksIds = array_flip(array_map('intval', array_column(db_all($conn, "SELECT t.id FROM tickets t JOIN ticket_signoffs s ON s.ticket_id = t.id AND s.signer_role = 'requester' AND s.stage = 'return' WHERE t.requester_id = ? AND t.status IN ('awaiting_admin_sign', 'completed') GROUP BY t.id HAVING MAX(s.signed_at) >= NOW() - INTERVAL ? DAY", 'ii', [$userId, $thanksDays]), 'id')));
}
$ticketStmt->close();

$logsByTicket = [];
$ticketIds = array_map('intval', array_column($tickets, 'ticket_id'));
if ($ticketIds !== []) {
    $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
    $logStmt = $conn->prepare(
        "SELECT l.ticket_id, l.old_status, l.new_status, l.created_at,
                CONCAT(u.first_name, ' ', u.last_name) AS by_name
         FROM ticket_logs l LEFT JOIN users u ON u.user_id = l.action_by
         WHERE l.ticket_id IN ({$placeholders}) ORDER BY l.created_at ASC"
    );
    if ($logStmt) {
        $logStmt->bind_param(str_repeat('i', count($ticketIds)), ...$ticketIds);
        $logStmt->execute();
        $logResult = $logStmt->get_result();
        while ($log = $logResult->fetch_assoc()) {
            $logsByTicket[(int)$log['ticket_id']][] = $log;
        }
        $logStmt->close();
    }
}

$fullName = trim($user['first_name'] . ' ' . $user['last_name']);
$initial = mb_strtoupper(mb_substr(trim((string)$user['first_name']), 0, 1, 'UTF-8'), 'UTF-8');
$profileMeta = trim((['student' => 'นักศึกษา', 'teacher' => 'ครู/อาจารย์', 'staff' => 'บุคลากร'][$user['user_type']] ?? $user['user_type'])
    . (!empty($user['department_name']) ? ' · ' . $user['department_name'] : ''));
$hasFilter = $statusFilter !== '' || $search !== '';
?>
<!DOCTYPE html>
<html lang="th" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติใบแจ้งซ่อม — REPS</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/logo/reps-icon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Kanit', 'sans-serif'], kanit: ['Kanit', 'sans-serif'] } } } };</script>
    <style>
        .tnum { font-variant-numeric: tabular-nums; }
        .admin-shell { background: #FAFAF8; color: #161616; font-family: 'Kanit', sans-serif; }
        .admin-topbar { background: #FFFFFF !important; border-color: #ECEAE6 !important; box-shadow: 0 1px 2px rgba(22, 22, 22, .035); }
        .admin-brand-label { font-family: 'Kanit', sans-serif; font-size: 11px; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; }
        .admin-brand-sub { display: block; margin-top: 2px; color: #6B6862; font-family: 'Kanit', sans-serif; font-size: 11px; letter-spacing: .12em; }
        .admin-user-avatar { background: #F7F6F4; border: 1px solid #ECEAE6; color: #55534F; font-family: 'Kanit', sans-serif; font-size: 13px; font-weight: 600; }
        .admin-btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 38px; padding: 0 14px; border: 1px solid #161616; border-radius: 999px; background: #161616; color: #FFFFFF; font-size: 13px; font-weight: 500; text-decoration: none; transition: background-color .15s ease; cursor: pointer; }
        .admin-btn:hover { background: #2A2927; }
        .admin-btn-secondary { background: #FFFFFF; border-color: #D9D6D0; color: #55534F; }
        .admin-btn-secondary:hover { background: #F7F6F4; color: #161616; border-color: #C9C6C0; }
        details > summary { list-style: none; }
        details > summary::-webkit-details-marker { display: none; }
        
        /* สไตล์ชิปสถานะ */
        .admin-chip { display: inline-flex; align-items: center; gap: 6px; padding: 3px 9px; border: 1px solid #ECEAE6; border-radius: 999px; background: #F7F6F4; color: #55534F; font-size: 11.5px; font-weight: 500; white-space: nowrap; }
        .admin-chip::before { content: ''; width: 5px; height: 5px; border-radius: 50%; background: var(--c, #B9B5AE); flex: none; }
        .st-pending { --c: #D97706; }
        .st-in_progress, .st-accepted, .st-awaiting_intake_sign, .st-inspected_ready { --c: #2563EB; }
        .st-awaiting_signoff, .st-awaiting_requester_sign, .st-awaiting_admin_sign { --c: #0E7490; }
        .st-disputed { --c: #B45309; }
        .st-completed { --c: #12A150; }
        .st-cancelled { --c: #6B6862; }

        /* Stacked History Cards Effect */
        .history-stack {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .history-stack-item {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .history-stack-item:hover {
            transform: translateY(-2px);
            border-color: #C9C6C0;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
        }

        /* Modal Styles */
        dialog {
            border: 1px solid #D9D6D0;
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.18);
            animation: modalSlideIn 0.25s ease-out;
        }
        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        dialog::backdrop {
            background-color: rgba(22, 22, 22, 0.5);
            backdrop-filter: blur(4px);
        }
    </style>
   <link rel="stylesheet" href="assets/css/base.css?v=18">
   <link rel="stylesheet" href="assets/css/requester-theme.css?v=2">
</head>
<body class="admin-shell min-h-screen flex flex-col antialiased rq-on">
    <!-- Topbar Header -->
    <?php $rqActive = 'history'; $rqBottom = true; require __DIR__ . '/includes/requester_nav.php'; ?>

    <main class="flex-1 max-w-6xl w-full mx-auto p-4 sm:p-7">
        <header class="mb-6">
            <nav class="flex items-center gap-2 mb-2 text-[10.5px] font-kanit uppercase tracking-wider text-[#6B6862]" aria-label="Breadcrumb">
                <a href="dashboard.php" class="hover:text-[#161616] no-underline"><?= tr('หน้าหลัก', 'Home') ?></a>
                <i class="fa-solid fa-chevron-right text-[8px] opacity-50" aria-hidden="true"></i>
                <span class="text-[#161616]"><?= tr('ประวัติการแจ้งซ่อม', 'Request history') ?></span>
            </nav>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-normal tracking-tight text-[#161616]"><?= tr('ประวัติใบแจ้งซ่อมทั้งหมด', 'All repair requests') ?></h1>
                    <p class="mt-1 text-xs text-[#6B6862]"><?= tr('คลิกที่รายการการ์ดเพื่อดูรายละเอียดข้อมูลเชิงลึกและประวัติสถานะ', 'Click a card to see full details and status history') ?></p>
                </div>
            </div>
        </header>

        <!-- Search & Filters Toolbar -->
        <div class="bg-white rounded-2xl border border-[#EAE8E4] p-4 mb-6 shadow-sm">
            <form method="get" class="flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="relative w-full sm:w-80">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-neutral-500" aria-hidden="true"></i>
                    <input type="search" name="q" aria-label="ค้นหาใบงานในประวัติ" value="<?= history_h($search) ?>" maxlength="<?= HISTORY_SEARCH_MAX ?>" placeholder="ค้นหาเลขใบงาน อาการ หรือสถานที่..." class="w-full border border-[#D9D6D0] rounded-xl bg-white py-2 pl-9 pr-3 text-xs text-neutral-900 placeholder:text-neutral-500 focus:border-neutral-950 focus:outline-none">
                </div>
                <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                    <label class="text-xs text-neutral-600 flex items-center gap-2">สถานะ:
                        <select name="status" class="rounded-xl border border-[#D9D6D0] bg-white px-3 py-2 text-xs text-neutral-800 focus:border-neutral-950 focus:outline-none">
                            <option value="">ทั้งหมด</option>
                            <?php foreach ($statusLabels as $value => $label): ?>
                                <option value="<?= history_h($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= history_h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="admin-btn text-xs !min-h-[34px] rounded-lg">กรองข้อมูล</button>
                    <?php if ($hasFilter): ?>
                        <a href="history.php" class="text-xs text-neutral-500 hover:text-neutral-900 underline whitespace-nowrap">ล้างตัวกรอง</a>
                    <?php endif; ?>
                </div>
            </form>
            <div class="mt-3 pt-3 border-t border-[#ECEAE6] flex items-center justify-between text-xs text-[#6B6862] font-mono">
                <span>แสดง <?= number_format(count($tickets)) ?> จากทั้งหมด <?= number_format($total) ?> รายการ</span>
                <span>เรียงจากรายการล่าสุด</span>
            </div>
        </div>

        <?php if ($tickets === []): ?>
            <div class="bg-white rounded-2xl border border-[#EAE8E4] p-12 text-center">
                <i class="fa-solid fa-folder-open text-3xl text-neutral-300 mb-3" aria-hidden="true"></i>
                <p class="text-sm font-medium text-neutral-800"><?= $hasFilter ? 'ไม่พบประวัติที่ตรงกับตัวกรอง' : 'ยังไม่มีประวัติใบแจ้งซ่อมในระบบ' ?></p>
                <p class="text-xs text-neutral-500 mt-1 mb-4">ลองปรับเปลี่ยนเงื่อนไขการค้นหา หรือสร้างใบแจ้งซ่อมใหม่</p>
                <a href="request_form.php" class="admin-btn text-xs inline-flex">
                    <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i>
                    <span>สร้างใบแจ้งซ่อมใหม่</span>
                </a>
            </div>
        <?php else: ?>
            <!-- Stacked History List -->
            <section class="history-stack" aria-label="รายการประวัติใบแจ้งซ่อม">
                <?php foreach ($tickets as $ticket):
                    $ticketStatus = (string)$ticket['status'];
                    $logs = $logsByTicket[(int)$ticket['ticket_id']] ?? [];
                    $tracker = history_tracker($ticket, $logs);
                    $hasTech = !empty($ticket['tech_name']);
                ?>
                    <article class="history-stack-item bg-white border border-[#EAE8E4] rounded-2xl p-5 shadow-sm sm:p-6" 
                             data-open-modal 
                             data-ticket-no="<?= history_h($ticket['ticket_no']) ?>"
                             data-category="<?= history_h($ticket['category_name'] ?? 'ไม่ระบุหมวดหมู่') ?>"
                             data-status-label="<?= history_h($ticketStatusLabels[$ticketStatus] ?? $ticketStatus) ?>"
                             data-status-class="st-<?= history_h($ticketStatus) ?>"
                             data-created="<?= history_h(history_fmt($ticket['created_at'])) ?>"
                             data-urgency="<?= history_h($ticket['urgency']) ?>"
                             data-location="<?= history_h($ticket['location_name']) ?>"
                             data-tech="<?= $hasTech ? history_h($ticket['tech_name']) : 'ยังไม่มีผู้รับงาน' ?>"
                             data-symptoms="<?= history_h($ticket['symptoms']) ?>"
                             data-resolution="<?= history_h($ticket['resolution_summary'] ?? '') ?>"
                             data-cancel-reason="<?= history_h($ticket['cancel_reason'] ?? '') ?>"
                             data-ticket-id="<?= (int)$ticket['ticket_id'] ?>"
                             data-has-completion="<?= !empty($ticket['completion_image']) ? '1' : '0' ?>"
                             data-is-completed="<?= $ticketStatus === 'completed' ? '1' : '0' ?>"
                             data-can-thank="<?= isset($thanksIds[(int)$ticket['ticket_id']]) ? '1' : '0' ?>"
                             data-logs='<?= json_encode($logs, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'
                             data-tracker='<?= json_encode($tracker, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>
                        
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs font-semibold text-neutral-900">
                                    #<?= history_h($ticket['ticket_no']) ?>
                                </span>
                                <span class="text-neutral-300">·</span>
                                <span class="font-mono text-[11px] text-neutral-500"><?= history_h(history_fmt($ticket['created_at'])) ?></span>
                                <?php if ($ticket['urgency'] === 'urgent'): ?>
                                    <span class="admin-chip urgency-urgent !text-[10px] !py-0.5 !px-2 bg-neutral-900 text-white border-neutral-900">ด่วน</span>
                                <?php endif; ?>
                            </div>
                            <span class="admin-chip st-<?= history_h($ticketStatus) ?>">
                                <?= history_h($ticketStatusLabels[$ticketStatus] ?? $ticketStatus) ?>
                            </span>
                        </div>

                        <h3 class="text-base font-semibold text-neutral-900 mb-1">
                            <?= history_h($ticket['category_name'] ?? 'ไม่ระบุหมวดหมู่') ?>
                        </h3>

                        <p class="text-xs text-neutral-600 line-clamp-2 mb-3 leading-relaxed">
                            <?= history_h($ticket['symptoms']) ?>
                        </p>

                        <div class="flex flex-wrap items-center justify-between gap-2 pt-3 border-t border-[#ECEAE6] text-xs text-neutral-500">
                            <span class="truncate max-w-[280px] sm:max-w-md"><i class="fa-solid fa-location-dot text-neutral-500 mr-1.5" aria-hidden="true"></i><?= history_h($ticket['location_name']) ?></span>
                            <span class="text-neutral-500 text-[11px]"><i class="fa-solid fa-circle-info mr-1"></i> คลิกเพื่อดูรายละเอียดทั้งหมด</span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if ($total > min(HISTORY_PER_PAGE_OPTIONS)): ?>
        <div class="mt-6 overflow-hidden rounded-2xl border border-[#EAE8E4] bg-white">
            <?php pager_render('history.php', $total, $page, HISTORY_PER_PAGE, HISTORY_PER_PAGE_OPTIONS, static fn(int $p): string => history_page_url(['status' => $statusFilter, 'q' => $search, 'page' => $p]), ['status' => $statusFilter, 'q' => $search], 'เปลี่ยนหน้าประวัติ'); ?>
        </div>
        <?php endif; ?>
    </main>

    <!-- Detailed Modal -->
    <dialog id="detailModal" class="w-full max-w-2xl rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-black/40">
        <div class="flex items-center justify-between border-b border-[#ECEAE6] px-6 py-4 bg-[#FAFAF8]">
            <div>
                <span class="text-[10px] font-mono font-semibold uppercase text-neutral-500 tracking-wider">Ticket Details</span>
                <h2 class="text-base font-semibold text-neutral-900">รายละเอียดใบแจ้งซ่อม <span id="modalTicketNo" class="font-mono font-normal"></span></h2>
            </div>
            <button type="button" class="text-neutral-500 hover:text-neutral-900 border-0 bg-transparent cursor-pointer p-1" data-close-modal aria-label="ปิด"><i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i></button>
        </div>
        
        <div class="p-6 space-y-6 max-h-[70vh] overflow-y-auto text-xs">
            <!-- Header Info -->
            <div class="flex flex-wrap items-center justify-between gap-3 bg-[#FAFAF8] p-4 rounded-xl border border-[#ECEAE6]">
                <div>
                    <span class="text-neutral-500 block mb-1">หมวดหมู่ปัญหา</span>
                    <strong id="modalCategory" class="text-sm font-semibold text-neutral-900">—</strong>
                </div>
                <div>
                    <span class="text-neutral-500 block mb-1">สถานะปัจจุบัน</span>
                    <span id="modalStatusBadge" class="admin-chip">—</span>
                </div>
            </div>

            <!-- Metadata Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="p-3 bg-white rounded-xl border border-[#ECEAE6]">
                    <span class="text-neutral-500 block mb-1">วันที่แจ้งซ่อม</span>
                    <strong id="modalCreated" class="font-mono text-neutral-800">—</strong>
                </div>
                <div class="p-3 bg-white rounded-xl border border-[#ECEAE6]">
                    <span class="text-neutral-500 block mb-1">ระดับความเร่งด่วน</span>
                    <strong id="modalUrgency" class="text-neutral-800">—</strong>
                </div>
                <div class="p-3 bg-white rounded-xl border border-[#ECEAE6]">
                    <span class="text-neutral-500 block mb-1">สถานที่ / อุปกรณ์</span>
                    <strong id="modalLocation" class="text-neutral-800">—</strong>
                </div>
                <div class="p-3 bg-white rounded-xl border border-[#ECEAE6]">
                    <span class="text-neutral-500 block mb-1">ผู้รับผิดชอบ (ช่าง/Lab Boy)</span>
                    <strong id="modalTech" class="text-neutral-800">—</strong>
                </div>
            </div>

            <!-- Symptoms -->
            <div>
                <span class="font-semibold text-neutral-900 block mb-1.5"><i class="fa-solid fa-message text-neutral-500 mr-1.5"></i> อาการที่พบและรายละเอียด</span>
                <div id="modalSymptoms" class="p-4 bg-neutral-50 rounded-xl border border-[#ECEAE6] text-neutral-700 whitespace-pre-line leading-relaxed">—</div>
            </div>

            <!-- Resolution / Cancel Reason (Conditional) -->
            <div id="modalResultContainer" class="hidden">
                <span id="modalResultTitle" class="font-semibold text-neutral-900 block mb-1.5">ผลการดำเนินงาน</span>
                <div id="modalResultText" class="p-4 rounded-xl border text-neutral-800 whitespace-pre-line leading-relaxed">—</div>
            </div>
            <div id="modalProofLinks" class="hidden flex flex-wrap gap-3 text-xs">
                <a id="modalCompletionLink" class="hidden underline text-neutral-700" target="_blank" rel="noopener"><i class="fa-regular fa-image mr-1"></i>รูปหลังซ่อม</a>
                <a id="modalCertificateLink" class="hidden underline text-neutral-700" target="_blank" rel="noopener"><i class="fa-solid fa-file-signature mr-1"></i>ใบรับรองงาน</a>
                <a id="modalThanksLink" class="hidden underline text-neutral-700"><i class="fa-solid fa-heart mr-1" aria-hidden="true"></i>ขอบคุณทีมช่าง (ไม่บังคับ)</a>
            </div>

            <!-- Tracker Stepper -->
            <div>
                <span class="font-semibold text-neutral-900 block mb-3"><i class="fa-solid fa-chart-line text-neutral-500 mr-1.5"></i> ความคืบหน้าภาพรวม</span>
                <div class="p-4 bg-[#FAFAF8] rounded-xl border border-[#ECEAE6]">
                    <ol id="modalTrackerList" class="grid grid-cols-3 gap-3 text-xs" aria-label="ความคืบหน้า">
                        <!-- JS Dynamic Render -->
                    </ol>
                </div>
            </div>

            <!-- Logs Timeline -->
            <div>
                <span class="font-semibold text-neutral-900 block mb-3"><i class="fa-solid fa-clock-rotate-left text-neutral-500 mr-1.5"></i> ประวัติลำดับสถานะย้อนหลัง</span>
                <ol id="modalLogList" class="relative space-y-3 border-l border-neutral-200 pl-4 text-xs text-neutral-600">
                    <!-- JS Dynamic Render -->
                </ol>
            </div>
        </div>

        <div class="flex items-center justify-between border-t border-[#ECEAE6] px-6 py-4 bg-[#FAFAF8] rounded-b-2xl">
            <button type="button" class="admin-btn admin-btn-secondary ml-auto text-xs !min-h-[34px]" data-close-modal>ปิดหน้าต่าง</button>
        </div>
    </dialog>

    <?php $footerBase = ''; $footerVariant = 'light'; require __DIR__ . '/includes/site_footer.php'; ?>

    <script>
        // Modal Handler Logic
        const detailModal = document.getElementById('detailModal');
        
        document.querySelectorAll('[data-open-modal]').forEach(card => {
            card.addEventListener('click', () => {
                const dataset = card.dataset;
                
                document.getElementById('modalTicketNo').textContent = '#' + dataset.ticketNo;
                document.getElementById('modalCategory').textContent = dataset.category;
                
                const badge = document.getElementById('modalStatusBadge');
                badge.className = 'admin-chip ' + dataset.statusClass;
                badge.textContent = dataset.statusLabel;

                document.getElementById('modalCreated').textContent = dataset.created;
                
                const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
                const proof = document.getElementById('modalProofLinks');
                const completionLink = document.getElementById('modalCompletionLink');
                const certificateLink = document.getElementById('modalCertificateLink');
                completionLink.href = 'file.php?t=' + encodeURIComponent(dataset.ticketId) + '&k=completion';
                certificateLink.href = 'certificate.php?id=' + encodeURIComponent(dataset.ticketId);
                completionLink.classList.toggle('hidden', dataset.hasCompletion !== '1');
                certificateLink.classList.toggle('hidden', dataset.isCompleted !== '1');
                const thanksLink = document.getElementById('modalThanksLink');
                thanksLink.href = 'thanks.php?id=' + encodeURIComponent(dataset.ticketId);
                thanksLink.classList.toggle('hidden', dataset.canThank !== '1');
                proof.classList.toggle('hidden', dataset.hasCompletion !== '1' && dataset.isCompleted !== '1' && dataset.canThank !== '1');

                const urgencyMap = { 'urgent': 'ด่วน', 'normal': 'ปกติ' };
                document.getElementById('modalUrgency').textContent = urgencyMap[dataset.urgency] || dataset.urgency;
                
                document.getElementById('modalLocation').textContent = dataset.location;
                document.getElementById('modalTech').textContent = dataset.tech;
                document.getElementById('modalSymptoms').textContent = dataset.symptoms;

                // ผลการซ่อม หรือ เหตุผลยกเลิก
                const resultContainer = document.getElementById('modalResultContainer');
                const resultTitle = document.getElementById('modalResultTitle');
                const resultText = document.getElementById('modalResultText');

                if (dataset.resolution) {
                    resultContainer.classList.remove('hidden');
                    resultContainer.className = 'p-4 rounded-xl border border-emerald-200 bg-emerald-50/50 text-emerald-900';
                    resultTitle.innerHTML = '<i class="fa-solid fa-circle-check text-emerald-600 mr-1.5"></i> ผลการซ่อมสำเร็จ';
                    resultText.textContent = dataset.resolution;
                } else if (dataset.cancelReason) {
                    resultContainer.classList.remove('hidden');
                    resultContainer.className = 'p-4 rounded-xl border border-neutral-200 bg-neutral-50 text-neutral-800';
                    resultTitle.innerHTML = '<i class="fa-solid fa-ban text-neutral-600 mr-1.5"></i> เหตุผลที่ยกเลิกใบงาน';
                    resultText.textContent = dataset.cancelReason;
                } else {
                    resultContainer.classList.add('hidden');
                }

                // Render Tracker Steps
                const trackerList = document.getElementById('modalTrackerList');
                const trackers = JSON.parse(dataset.tracker || '[]');
                trackerList.className = `grid grid-cols-${trackers.length} gap-3 text-xs`;
                trackerList.innerHTML = trackers.map(step => `
                    <li class="relative border-t-2 pt-2 ${step.now ? 'border-neutral-900' : (step.on ? 'border-neutral-500' : 'border-neutral-200')}">
                        <span class="absolute -top-[5px] left-0 h-2 w-2 rounded-full border-2 border-white ${step.now ? 'bg-neutral-900' : (step.on ? 'bg-neutral-500' : 'bg-neutral-300')}"></span>
                        <span class="block pl-1 text-[11px] font-medium ${step.now ? 'text-neutral-900 font-semibold' : (step.on ? 'text-neutral-700' : 'text-neutral-500')}">${esc(step.label)}</span>
                        <span class="mt-0.5 block pl-1 font-mono text-[10px] text-neutral-500">${step.on ? esc(step.when || '—') : 'รอ'}</span>
                    </li>
                `).join('');

                // Render Logs Timeline
                const logList = document.getElementById('modalLogList');
                const logs = JSON.parse(dataset.logs || '[]');
                const statusLabelsMap = <?= json_encode($ticketStatusLabels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
                
                let logsHtml = `
                    <li class="relative">
                        <span class="absolute -left-[21px] top-1 h-2 w-2 rounded-full border border-white bg-neutral-900"></span>
                        <p class="text-neutral-800">สร้างใบงาน · สถานะเริ่มต้น <strong class="font-medium">รอรับเรื่อง</strong></p>
                        <time class="mt-0.5 block font-mono text-[10px] text-neutral-500">${esc(dataset.created)}</time>
                    </li>
                `;
                
                logs.forEach(log => {
                    const newLbl = statusLabelsMap[log.new_status] || log.new_status;
                    const oldLbl = log.old_status ? (statusLabelsMap[log.old_status] || log.old_status) : '';
                    logsHtml += `
                        <li class="relative">
                            <span class="absolute -left-[21px] top-1 h-2 w-2 rounded-full border border-white bg-neutral-500"></span>
                            <p class="text-neutral-800">เปลี่ยนสถานะเป็น <strong class="font-medium">${esc(newLbl)}</strong>${oldLbl ? ' (จาก ' + esc(oldLbl) + ')' : ''}</p>
                            <p class="mt-0.5 text-[11px] text-neutral-500">ดำเนินการโดย: ${esc(log.by_name || 'ระบบ')}</p>
                            <time class="mt-0.5 block font-mono text-[10px] text-neutral-500">${esc(log.created_at || '—')}</time>
                        </li>
                    `;
                });
                logList.innerHTML = logsHtml;

                detailModal.showModal();
            });
        });

        document.querySelectorAll('[data-close-modal]').forEach(btn => {
            btn.addEventListener('click', () => detailModal.close());
        });

        detailModal.addEventListener('click', (e) => {
            if (e.target === detailModal) detailModal.close();
        });
    </script>
</body>
</html>
