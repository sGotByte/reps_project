<?php
declare(strict_types=1);

/**
 * REPS · admin/admin_war_room.php — ภาพรวมงานทั้งหมดและสตาฟทุกคนในหน้าเดียว (v3 หัวข้อ 2) [admin, staff]
 *
 *   สรุปตัวเลข · งานเข้าใหม่ (ด่วนก่อน) · คิวถัดไป · ใครทำอะไรอยู่ · งานค้างเกินเกณฑ์ · งานรอเซ็นรับรอง
 *   รีเฟรชอัตโนมัติด้วย polling (?partial=1) ตามค่า warroom_refresh_seconds · กรองตามสตาฟ กลุ่มสถานะ หมวดหมู่ ช่วงเวลา
 *   admin: เห็นเบอร์ติดต่อสตาฟ, มอบหมาย/ย้ายงาน (ไปหน้าจัดการคำขอ), ส่งอีเมลเร่งงานค้าง
 *   staff: รับงานใหม่ตามกติกาคิว (งานคิวกลาง หรือคิวของตัวเอง)
 * เกณฑ์งานค้าง (stale_warn_hours / stale_critical_hours) ตั้งได้ที่หน้าตั้งค่าระบบ
 */

require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/user_auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/site_config.php';
require_once dirname(__DIR__) . '/includes/ticket_service.php';
require_once dirname(__DIR__) . '/includes/icons.php';

$actorId = (int)app_require_active_user($conn, '../login.php?next=war_room', ['admin', 'staff']);
$isAdmin = current_role() === 'admin';
$csrfToken = app_csrf_token();
$siteName = app_site_name($conn);

if (!function_exists('h')) {
    function h(mixed $v): string
    {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

function wr_age(?string $since): string
{
    $ts = $since ? strtotime($since) : false;
    if ($ts === false) {
        return '-';
    }
    $min = max(0, (int)floor((time() - $ts) / 60));
    if ($min < 60) {
        return $min . ' นาที';
    }
    if ($min < 1440) {
        return intdiv($min, 60) . ' ชม. ' . ($min % 60) . ' นาที';
    }
    return intdiv($min, 1440) . ' วัน ' . intdiv($min % 1440, 60) . ' ชม.';
}

/** ลิงก์เปิดใบงานตามบทบาทและสถานะ */
function wr_ticket_href(array $t, bool $isAdmin): string
{
    if ($isAdmin && in_array($t['status'], ['awaiting_requester_sign', 'awaiting_admin_sign', 'disputed', 'awaiting_intake_sign'], true)) {
        return 'admin_signoff.php?id=' . (int)$t['id'];
    }
    if ($t['status'] === 'pending') {
        return $isAdmin ? 'admin_manage_request.php?q=' . rawurlencode((string)$t['ticket_no']) : '../staff/staff_dashboard.php';
    }
    return '../staff/job.php?id=' . (int)$t['id'];
}

// ---------------------------------------------------------------- คำสั่ง (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_csrf_is_valid($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('คำขอหมดอายุ กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $tid = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT) ?: 0;
    $back = 'admin_war_room.php' . (is_string($_POST['qs'] ?? null) && preg_match('/^[A-Za-z0-9_=&%.-]*$/', $_POST['qs']) && $_POST['qs'] !== '' ? '?' . $_POST['qs'] : '');
    try {
        if ($action === 'accept') {
            if (ticket_accept($conn, $tid, $actorId)) {
                $_SESSION['job_flash'] = ['msg' => 'รับงานแล้ว ขั้นต่อไป: บันทึกเช็คลิสต์อุปกรณ์ตอนรับร่วมกับผู้แจ้ง', 'type' => 'ok'];
                header('Location: ../staff/job.php?id=' . $tid, true, 303);
                exit;
            }
            $_SESSION['wr_flash'] = ['รับงานนี้ไม่ได้ (มีคนรับไปแล้ว หรือเป็นคิวของสตาฟคนอื่น)', 'err'];
        } elseif ($action === 'nudge' && $isAdmin) {
            $sent = ticket_nudge_staff($conn, $tid, $actorId, trim((string)($_POST['message'] ?? '')));
            $_SESSION['wr_flash'] = [$sent > 0 ? "ส่งอีเมลเร่งงานถึงทีมช่างแล้ว ({$sent} ฉบับ)" : 'บันทึกการเร่งงานแล้ว แต่ส่งอีเมลไม่สำเร็จ (ดูบันทึกอีเมล)', $sent > 0 ? 'ok' : 'warn'];
        } else {
            $_SESSION['wr_flash'] = ['คำสั่งไม่ถูกต้อง', 'err'];
        }
    } catch (AppUserException $e) {
        $_SESSION['wr_flash'] = [$e->getMessage(), 'err'];
    } catch (Throwable $e) {
        error_log('war room action failed: ' . $e->getMessage());
        $_SESSION['wr_flash'] = ['ทำรายการไม่สำเร็จ กรุณาลองใหม่', 'err'];
    }
    header('Location: ' . $back, true, 303);
    exit;
}

queue_process_expired_tickets($conn);
ticket_send_sign_reminders($conn); // ทำงานไม่เกินทุก 10 นาที (ล็อกไฟล์)

// ---------------------------------------------------------------- ตัวกรอง
$refreshSeconds = max(10, ticket_setting_int($conn, 'warroom_refresh_seconds', 30));
$staleWarn = max(1, ticket_setting_int($conn, 'stale_warn_hours', 24));
$staleCrit = max($staleWarn + 1, ticket_setting_int($conn, 'stale_critical_hours', 72));

$wrTerm = term_current($conn);
// สตาฟเฉพาะทีมภาคเรียนปัจจุบัน · คะแนนนับเฉพาะภาคเรียนนี้
$staffMembers = db_all($conn, "SELECT u.user_id, u.first_name, u.last_name, u.phone_number, " . term_score_sql($wrTerm) . " AS score, u.is_active_queue, u.queue_order FROM users u WHERE u.role = 'staff' AND u.status = 'active'" . term_staff_sql($conn, 'u.user_id', $wrTerm) . " ORDER BY COALESCE(u.queue_order, 9999), u.first_name");
$categories = db_all($conn, 'SELECT id, name FROM categories ORDER BY name');
$fStaff = filter_input(INPUT_GET, 'staff', FILTER_VALIDATE_INT) ?: 0;
$fGroup = in_array($_GET['group'] ?? '', ['pending', 'working', 'signing'], true) ? $_GET['group'] : '';
$fCat = filter_input(INPUT_GET, 'cat', FILTER_VALIDATE_INT) ?: 0;
$periods = ['all' => 'ทั้งหมด', 'today' => 'แจ้งวันนี้', '7d' => '7 วันล่าสุด', '30d' => '30 วันล่าสุด'];
$fPeriod = isset($periods[$_GET['period'] ?? '']) ? $_GET['period'] : 'all';
$qs = http_build_query(array_filter(['staff' => $fStaff ?: '', 'group' => $fGroup, 'cat' => $fCat ?: '', 'period' => $fPeriod === 'all' ? '' : $fPeriod]));

$where = ["t.status IN (" . ticket_status_sql(TICKET_OPEN_STATUSES) . ")"];
$types = '';
$params = [];
if ($fGroup !== '') {
    $where[] = 't.status IN (' . ticket_status_sql(TICKET_STATUS_GROUPS[$fGroup]) . ')';
}
if ($fCat > 0) {
    $where[] = 't.category_id = ?';
    $types .= 'i';
    $params[] = $fCat;
}
if ($fStaff > 0) {
    $where[] = '(t.assigned_to = ? OR t.queue_assigned_to = ? OR EXISTS (SELECT 1 FROM ticket_assignees a WHERE a.ticket_id = t.id AND a.user_id = ?))';
    $types .= 'iii';
    array_push($params, $fStaff, $fStaff, $fStaff);
}
if ($fPeriod !== 'all') {
    $where[] = ['today' => 't.created_at >= CURDATE()', '7d' => 't.created_at >= NOW() - INTERVAL 7 DAY', '30d' => 't.created_at >= NOW() - INTERVAL 30 DAY'][$fPeriod];
}
$open = db_all(
    $conn,
    "SELECT t.id, t.ticket_no, t.status, t.urgency, t.location_note, t.symptom, t.created_at, t.accepted_at, t.stage_since,
            t.has_discrepancy, t.sign_round, t.assigned_to, t.queue_assigned_to, t.queue_expires_at, t.custom_category,
            c.name AS category_name, s.first_name AS staff_first, q.first_name AS queue_first
       FROM tickets t
       LEFT JOIN categories c ON c.id = t.category_id
       LEFT JOIN users s ON s.user_id = t.assigned_to
       LEFT JOIN users q ON q.user_id = t.queue_assigned_to
      WHERE " . implode(' AND ', $where) . "
      ORDER BY t.urgency = 'urgent' DESC, t.created_at ASC",
    $types,
    $params
);

$newJobs = array_values(array_filter($open, static fn($t) => $t['status'] === 'pending'));
$working = array_values(array_filter($open, static fn($t) => in_array($t['status'], TICKET_STAFF_ACTIVE_STATUSES, true)));
$signing = array_values(array_filter($open, static fn($t) => in_array($t['status'], array_merge(TICKET_SIGNING_STATUSES, ['awaiting_intake_sign']), true)));
$ageHours = static fn(array $t): float => (time() - (int)strtotime((string)($t['accepted_at'] ?: $t['created_at']))) / 3600;
$stale = array_values(array_filter($open, static fn($t) => $t['status'] !== 'pending' && $ageHours($t) >= $staleWarn));
usort($stale, static fn($a, $b) => $ageHours($b) <=> $ageHours($a));
usort($signing, static fn($a, $b) => strcmp((string)$a['stage_since'], (string)$b['stage_since']));
$queued = array_values(array_filter($newJobs, static fn($t) => (int)$t['queue_assigned_to'] > 0 && $t['queue_expires_at']));
$nextStaffId = queue_pick_next_staff($conn);
$nextStaffName = '';
foreach ($staffMembers as $m) {
    if ((int)$m['user_id'] === (int)$nextStaffId) {
        $nextStaffName = $m['first_name'] . ' ' . $m['last_name'];
    }
}
$doneToday = (int)(db_one($conn, "SELECT COUNT(*) AS n FROM tickets WHERE status = 'completed' AND completed_at >= CURDATE()")['n'] ?? 0);

// งานที่สตาฟแต่ละคนถืออยู่ (ช่างหลักหรือผู้ช่วย) — ไม่ขึ้นกับตัวกรอง เพื่อให้เห็นภาระงานจริง
$held = db_all(
    $conn,
    "SELECT t.id, t.ticket_no, t.status, t.urgency, t.location_note, t.accepted_at, t.created_at, t.stage_since, t.has_discrepancy,
            t.assigned_to AS member_id, 1 AS is_lead
       FROM tickets t WHERE t.status IN (" . ticket_status_sql(array_merge(TICKET_STAFF_ACTIVE_STATUSES, TICKET_SIGNING_STATUSES)) . ") AND t.assigned_to IS NOT NULL
     UNION ALL
     SELECT t.id, t.ticket_no, t.status, t.urgency, t.location_note, t.accepted_at, t.created_at, t.stage_since, t.has_discrepancy,
            a.user_id AS member_id, 0 AS is_lead
       FROM tickets t JOIN ticket_assignees a ON a.ticket_id = t.id
      WHERE t.status IN (" . ticket_status_sql(array_merge(TICKET_STAFF_ACTIVE_STATUSES, TICKET_SIGNING_STATUSES)) . ")
      ORDER BY accepted_at ASC"
);
$heldBy = [];
foreach ($held as $row) {
    $heldBy[(int)$row['member_id']][] = $row;
}
$wrFlash = $_SESSION['wr_flash'] ?? null;
unset($_SESSION['wr_flash']);

$chip = static function (array $t): string {
    $label = TICKET_STATUS_LABELS[$t['status']] ?? $t['status'];
    return '<span class="wr-chip st-' . h($t['status']) . '">' . h($label) . '</span>'
        . ($t['urgency'] === 'urgent' ? ' <span class="wr-flag wr-flag--urgent">ด่วน</span>' : '')
        . ((int)($t['has_discrepancy'] ?? 0) === 1 ? ' <span class="wr-flag wr-flag--warn">ของไม่ครบ</span>' : '');
};
$staleLevel = static fn(array $t): string => $ageHours($t) >= $staleCrit ? 'crit' : 'warn';

// ---------------------------------------------------------------- เนื้อหาบอร์ด (ใช้ทั้งหน้าเต็มและ ?partial=1)
ob_start(); ?>
<section class="wr-kpis" aria-label="สรุปตัวเลข">
    <?php foreach ([
        ['งานเข้าใหม่', count($newJobs), count(array_filter($newJobs, static fn($t) => $t['urgency'] === 'urgent')) . ' งานด่วน', 'pending'],
        ['กำลังดำเนินการ', count($working), count(array_filter($heldBy)) . ' สตาฟถืองานอยู่', 'working'],
        ['งานค้างเกินเกณฑ์', count($stale), 'เกิน ' . $staleWarn . ' ชม. หลังรับงาน', 'stale'],
        ['รอเซ็นรับรอง', count($signing), count(array_filter($signing, static fn($t) => $t['status'] === 'disputed')) . ' งานโต้แย้ง', 'signing'],
        ['เสร็จวันนี้', $doneToday, 'ทั้งระบบ', 'done'],
    ] as [$label, $value, $note, $key]): ?>
        <div class="wr-kpi wr-kpi--<?= $key ?>">
            <div class="wr-kpi-label"><?= h($label) ?></div>
            <div class="wr-kpi-value"><?= number_format($value) ?></div>
            <div class="wr-kpi-note"><?= h($note) ?></div>
        </div>
    <?php endforeach; ?>
</section>

<div class="wr-grid">
    <section class="wr-panel" aria-labelledby="wr-new">
        <header class="wr-panel-head"><h2 id="wr-new">งานเข้าใหม่</h2><span><?= count($newJobs) ?></span></header>
        <?php if (!$newJobs): ?><p class="wr-empty">ไม่มีงานรอรับ</p><?php endif; ?>
        <ul class="wr-list">
            <?php foreach ($newJobs as $t):
                $canAccept = !$t['queue_assigned_to'] || (int)$t['queue_assigned_to'] === $actorId; ?>
                <li class="wr-item <?= $t['urgency'] === 'urgent' ? 'is-urgent' : '' ?>">
                    <div class="wr-item-main">
                        <a class="wr-no" href="<?= h(wr_ticket_href($t, $isAdmin)) ?>"><?= h($t['ticket_no']) ?></a>
                        <?= $t['urgency'] === 'urgent' ? '<span class="wr-flag wr-flag--urgent">ด่วน</span>' : '' ?>
                        <span class="wr-time">รอ <?= h(wr_age($t['created_at'])) ?></span>
                    </div>
                    <div class="wr-item-text"><?= h($t['location_note']) ?> · <?= h($t['custom_category'] ?: ($t['category_name'] ?? '-')) ?></div>
                    <div class="wr-item-sub">
                        <span><?= $t['queue_first'] ? 'ถึงคิว ' . h($t['queue_first']) : 'คิวกลาง' ?></span>
                        <span class="wr-actions">
                            <?php if ($canAccept): ?>
                                <form method="post"><input type="hidden" name="csrf" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="accept"><input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="qs" value="<?= h($qs) ?>">
                                    <button class="wr-btn">รับงาน</button></form>
                            <?php endif; ?>
                            <?php if ($isAdmin): ?><a class="wr-btn wr-btn--ghost" href="admin_manage_request.php?q=<?= rawurlencode((string)$t['ticket_no']) ?>">มอบหมาย</a><?php endif; ?>
                        </span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="wr-panel" aria-labelledby="wr-queue">
        <header class="wr-panel-head"><h2 id="wr-queue">คิวถัดไป</h2><span><?= count($queued) ?></span></header>
        <p class="wr-next">สตาฟที่จะได้รับงานถัดไป: <b><?= $nextStaffName !== '' ? h($nextStaffName) : 'ยังไม่มีสตาฟเปิดรับคิว' ?></b></p>
        <?php if (!$queued): ?><p class="wr-empty">ไม่มีงานที่รอสตาฟกดรับ</p><?php endif; ?>
        <ul class="wr-list">
            <?php foreach ($queued as $t): $left = (int)strtotime((string)$t['queue_expires_at']) - time(); ?>
                <li class="wr-item">
                    <div class="wr-item-main"><a class="wr-no" href="<?= h(wr_ticket_href($t, $isAdmin)) ?>"><?= h($t['ticket_no']) ?></a><span class="wr-time <?= $left < 600 ? 'is-alert' : '' ?>" data-expires="<?= (int)strtotime((string)$t['queue_expires_at']) ?>"><?= $left > 0 ? 'เหลือ ' . (int)ceil($left / 60) . ' นาที' : 'หมดเวลา' ?></span></div>
                    <div class="wr-item-sub"><span>ถึงคิว <b><?= h($t['queue_first']) ?></b></span></div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>

<section class="wr-panel" aria-labelledby="wr-staff">
    <header class="wr-panel-head"><h2 id="wr-staff">ใครทำอะไรอยู่</h2><span><?= count($staffMembers) ?> คน</span></header>
    <div class="wr-staff">
        <?php foreach ($staffMembers as $m):
            $jobs = $heldBy[(int)$m['user_id']] ?? [];
            if ($fStaff > 0 && (int)$m['user_id'] !== $fStaff) continue;
            $state = $jobs ? 'working' : ((int)$m['is_active_queue'] === 1 ? 'free' : 'paused'); ?>
            <article class="wr-card wr-card--<?= $state ?>">
                <div class="wr-card-head">
                    <span class="wr-dot" aria-hidden="true"></span>
                    <b><?= h($m['first_name'] . ' ' . $m['last_name']) ?></b>
                    <span class="wr-card-state"><?= ['working' => 'กำลังทำงาน', 'free' => 'ว่าง', 'paused' => 'ปิดรับคิว'][$state] ?> · <?= count($jobs) ?> งาน</span>
                </div>
                <?php if ($isAdmin && !empty($m['phone_number'])): ?><div class="wr-muted">โทร <?= h($m['phone_number']) ?> · คะแนน <?= (int)$m['score'] ?></div><?php endif; ?>
                <?php if ($jobs): ?>
                    <ul class="wr-card-jobs">
                        <?php foreach ($jobs as $j): ?>
                            <li><a href="<?= h(wr_ticket_href($j, $isAdmin)) ?>"><?= h($j['ticket_no']) ?></a> <?= $chip($j) ?><?= (int)$j['is_lead'] === 0 ? ' <span class="wr-muted">(ผู้ช่วย)</span>' : '' ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<div class="wr-grid">
    <section class="wr-panel" aria-labelledby="wr-stale">
        <header class="wr-panel-head"><h2 id="wr-stale">งานค้างเกินเกณฑ์</h2><span><?= count($stale) ?></span></header>
        <p class="wr-legend"><span class="wr-lv wr-lv--warn"></span>เกิน <?= $staleWarn ?> ชม. <span class="wr-lv wr-lv--crit"></span>เกิน <?= $staleCrit ?> ชม. (นับจากรับงาน)</p>
        <?php if (!$stale): ?><p class="wr-empty">ไม่มีงานค้างเกินเกณฑ์</p><?php endif; ?>
        <ul class="wr-list">
            <?php foreach ($stale as $t): $lv = $staleLevel($t); ?>
                <li class="wr-item wr-stale--<?= $lv ?>">
                    <div class="wr-item-main"><a class="wr-no" href="<?= h(wr_ticket_href($t, $isAdmin)) ?>"><?= h($t['ticket_no']) ?></a> <?= $chip($t) ?><span class="wr-time">ค้าง <?= h(wr_age($t['accepted_at'] ?: $t['created_at'])) ?></span></div>
                    <div class="wr-item-text"><?= h($t['location_note']) ?> · ช่าง <?= h($t['staff_first'] ?? '-') ?></div>
                    <?php if ($isAdmin && in_array($t['status'], TICKET_STAFF_ACTIVE_STATUSES, true)): ?>
                        <form method="post" class="wr-nudge" onsubmit="return confirm('ส่งอีเมลเร่งงานถึงทีมช่างของใบงานนี้?');">
                            <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="nudge"><input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="qs" value="<?= h($qs) ?>">
                            <input name="message" maxlength="300" placeholder="ข้อความถึงช่าง (ไม่บังคับ)" aria-label="ข้อความเร่งงาน">
                            <button class="wr-btn wr-btn--ghost">เร่งงาน</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="wr-panel" aria-labelledby="wr-sign">
        <header class="wr-panel-head"><h2 id="wr-sign">งานรอเซ็นรับรอง</h2><span><?= count($signing) ?></span></header>
        <?php if (!$signing): ?><p class="wr-empty">ไม่มีงานรอเซ็น</p><?php endif; ?>
        <ul class="wr-list">
            <?php foreach ($signing as $t): ?>
                <li class="wr-item <?= $t['status'] === 'disputed' ? 'wr-stale--crit' : '' ?>">
                    <div class="wr-item-main"><a class="wr-no" href="<?= h(wr_ticket_href($t, $isAdmin)) ?>"><?= h($t['ticket_no']) ?></a> <?= $chip($t) ?><span class="wr-time">รอ <?= h(wr_age($t['stage_since'])) ?></span></div>
                    <div class="wr-item-text"><?= h($t['location_note']) ?> · ช่าง <?= h($t['staff_first'] ?? '-') ?><?= (int)$t['sign_round'] > 1 ? ' · รอบเซ็นที่ ' . (int)$t['sign_round'] : '' ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>
<p class="wr-updated">อัปเดตล่าสุด <?= date('H:i:s') ?> · รีเฟรชอัตโนมัติทุก <?= $refreshSeconds ?> วินาที</p>
<?php
$board = (string)ob_get_clean();
if (isset($_GET['partial'])) {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo $board;
    exit;
}

$extraHead = '<script src="https://cdn.tailwindcss.com"></script>'
    . "<script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Kanit', 'sans-serif'] } } } };</script>"
    . app_theme_css($conn) . <<<'HTML'
<style>
body { background: #FAFAF8; color: #161616; font-family: 'Kanit', sans-serif; }
.wr-filters { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; padding: 10px; border: 1px solid #EAE8E4; border-radius: 16px; background: #fff; }
.wr-filters label { display: grid; gap: 4px; font-size: 12.5px; color: #57544F; }
.wr-filters select { min-height: 40px; padding: 0 10px; border: 1px solid #D4D0C9; border-radius: 10px; background: #fff; font: inherit; font-size: 14px; color: #161616; }
.wr-kpis { display: grid; gap: 12px; grid-template-columns: repeat(5, minmax(0, 1fr)); }
.wr-kpi { padding: 14px 16px; border: 1px solid #ECEAE6; border-left: 4px solid var(--c, #BDB8B0); border-radius: 14px; background: #fff; }
.wr-kpi--pending { --c: #D97706; } .wr-kpi--working { --c: #2563EB; } .wr-kpi--stale { --c: #B91C1C; } .wr-kpi--signing { --c: #0E7490; } .wr-kpi--done { --c: #12A150; }
.wr-kpi-label { font-size: 13px; color: #57544F; }
.wr-kpi-value { font-size: 32px; font-weight: 600; line-height: 1.1; font-variant-numeric: tabular-nums; }
.wr-kpi-note { font-size: 12.5px; color: #6B6862; }
.wr-grid { display: grid; gap: 16px; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); }
.wr-panel { border: 1px solid #ECEAE6; border-radius: 16px; background: #fff; overflow: hidden; }
.wr-panel-head { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid #EFEDE9; }
.wr-panel-head h2 { margin: 0; font-size: 15px; font-weight: 600; }
.wr-panel-head span { min-width: 28px; padding: 0 8px; border-radius: 999px; background: #F4F3F0; font-size: 13px; font-weight: 600; line-height: 24px; text-align: center; }
.wr-list { margin: 0; padding: 0; list-style: none; max-height: 420px; overflow-y: auto; }
.wr-item { display: grid; gap: 3px; padding: 10px 16px; border-bottom: 1px solid #F1EFEC; font-size: 13.5px; }
.wr-item:last-child { border-bottom: 0; }
.wr-item.is-urgent { background: linear-gradient(90deg, #FFF7ED, transparent 60%); }
.wr-item-main { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
.wr-no { font-weight: 600; color: #161616; text-decoration: none; }
.wr-no:hover { text-decoration: underline; }
.wr-time { margin-left: auto; color: #57544F; font-size: 12.5px; white-space: nowrap; }
.wr-time.is-alert { color: #B45309; font-weight: 600; }
.wr-item-text { color: #3F3D39; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wr-item-sub { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 6px; color: #6B6862; font-size: 12.5px; }
.wr-actions { display: inline-flex; gap: 6px; }
.wr-btn { display: inline-flex; align-items: center; min-height: 34px; padding: 0 12px; border: 1px solid var(--theme-primary, #161616); border-radius: 9px; background: var(--theme-primary, #161616); color: #fff; font: inherit; font-size: 13px; cursor: pointer; text-decoration: none; }
.wr-btn--ghost { border-color: #D4D0C9; background: #fff; color: #161616; }
@media (pointer: coarse) { .wr-btn { min-height: 44px; } }
.wr-chip { display: inline-flex; align-items: center; gap: 5px; padding: 0 8px; border: 1px solid #E7E4DF; border-radius: 999px; background: #FAFAF8; font-size: 12px; line-height: 20px; }
.wr-chip::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--c, #BDB8B0); }
.wr-flag { padding: 0 7px; border-radius: 999px; font-size: 11.5px; line-height: 18px; }
.wr-flag--urgent { border: 1px solid #F1D9BF; color: #B45309; }
.wr-flag--warn { border: 1px solid #F5C2C0; color: #B91C1C; }
.wr-empty { margin: 0; padding: 16px; color: #6B6862; font-size: 13.5px; text-align: center; }
.wr-next { margin: 0; padding: 10px 16px; border-bottom: 1px solid #F1EFEC; font-size: 13.5px; color: #3F3D39; }
.wr-staff { display: grid; gap: 10px; padding: 12px; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); }
.wr-card { padding: 12px; border: 1px solid #ECEAE6; border-radius: 12px; background: #FCFBFA; font-size: 13.5px; }
.wr-card-head { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
.wr-card-state { margin-left: auto; color: #6B6862; font-size: 12.5px; }
.wr-dot { width: 9px; height: 9px; border-radius: 50%; background: #BDB8B0; }
.wr-card--working .wr-dot { background: #2563EB; } .wr-card--free .wr-dot { background: #12A150; }
.wr-card-jobs { margin: 8px 0 0; padding: 0; list-style: none; display: grid; gap: 4px; }
.wr-card-jobs a { color: #161616; font-weight: 500; }
.wr-muted { color: #6B6862; font-size: 12.5px; }
.wr-legend { display: flex; align-items: center; gap: 6px; margin: 0; padding: 8px 16px; border-bottom: 1px solid #F1EFEC; color: #6B6862; font-size: 12.5px; }
.wr-lv { width: 10px; height: 10px; border-radius: 3px; margin-left: 6px; }
.wr-lv--warn, .wr-stale--warn { --lv: #D97706; } .wr-lv--crit, .wr-stale--crit { --lv: #B91C1C; }
.wr-lv { background: var(--lv); }
.wr-stale--warn, .wr-stale--crit { box-shadow: inset 4px 0 0 var(--lv); }
.wr-nudge { display: flex; gap: 6px; margin-top: 4px; }
.wr-nudge input { flex: 1 1 auto; min-width: 0; min-height: 34px; padding: 0 10px; border: 1px solid #D4D0C9; border-radius: 9px; font: inherit; font-size: 13px; }
.wr-updated { margin: 0; color: #8A867F; font-size: 12px; text-align: right; }
.wr-flash { padding: 10px 14px; border-radius: 12px; font-size: 14px; }
.wr-flash--ok { border: 1px solid #BBE5CC; background: #F0FAF4; color: #14532D; }
.wr-flash--warn { border: 1px solid #F1D9BF; background: #FFFBF5; color: #7C2D12; }
.wr-flash--err { border: 1px solid #F5C2C0; background: #FEF2F2; color: #7F1D1D; }
.st-pending { --c: #D97706; } .st-accepted, .st-awaiting_intake_sign, .st-in_progress, .st-inspected_ready { --c: #2563EB; }
.st-awaiting_requester_sign, .st-awaiting_admin_sign { --c: #0E7490; } .st-disputed { --c: #B45309; }
@media (max-width: 1100px) { .wr-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); } .wr-grid { grid-template-columns: minmax(0, 1fr); } }
@media (max-width: 560px) { .wr-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .wr-kpi-value { font-size: 26px; } }
</style>
HTML;

$adminShell = $isAdmin;
if ($adminShell) {
    $page_title = 'ภาพรวมงาน';
    $current_page = 'warroom';
    $admin_extra_head = $extraHead;
    require __DIR__ . '/admin_header.php';
} else { ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ภาพรวมงานฝ่ายซ่อม | <?= h($siteName) ?></title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo/reps-icon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <?= $extraHead ?>
    <link rel="stylesheet" href="../assets/css/base.css?v=18">
</head>
<body class="min-h-screen flex flex-col antialiased">
    <?php $staffNavActive = 'warroom'; require __DIR__ . '/../includes/staff_nav.php'; ?>
<?php } ?>

<main class="<?= $adminShell ? 'w-full space-y-5' : 'flex-1 max-w-6xl w-full mx-auto px-4 sm:px-7 py-7 space-y-5' ?>">
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-normal tracking-tight"><?= tr('ภาพรวมงานฝ่ายซ่อม', 'Repair team overview') ?></h1>
            <p class="text-sm text-[#6B6862] mt-1">งานเข้าใหม่ ใครทำอะไรอยู่ งานค้าง และงานรอเซ็น อัปเดตเองทุก <?= $refreshSeconds ?> วินาที</p>
            <?php if ($adminShell):
                // จอสรุปงาน (ขึ้นจอทีวี) ย้ายจากแถบเมนูมาเป็นปุ่มในหน้านี้
                require_once __DIR__ . '/../includes/site_config.php';
                $wrSettings = app_get_settings($conn);
                $wrWallUrl = '../warboard.php' . (($wrSettings['wallboard_key'] ?? '') !== '' ? '?k=' . rawurlencode((string)$wrSettings['wallboard_key']) : '');
                $wrWallOn = ($wrSettings['wallboard_enabled'] ?? '1') === '1'; ?>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                    <?php if ($wrWallOn): ?>
                        <a href="<?= h($wrWallUrl) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 min-h-[40px] px-3.5 rounded-lg border border-[#D4D0C9] bg-white text-neutral-800 hover:bg-neutral-50"><i class="fa-solid fa-tv text-xs" aria-hidden="true"></i> เปิดจอสรุปงาน (ขึ้นจอ)</a>
                    <?php else: ?>
                        <span class="text-[#6B6862]">จอสรุปงานปิดอยู่ · <a class="underline" href="admin_settings.php">เปิดที่ตั้งค่าระบบ</a></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <form method="get" class="wr-filters" id="wrFilters">
            <label>สตาฟ<select name="staff"><option value="">ทุกคน</option>
                <?php foreach ($staffMembers as $m): ?><option value="<?= (int)$m['user_id'] ?>" <?= $fStaff === (int)$m['user_id'] ? 'selected' : '' ?>><?= h($m['first_name'] . ' ' . $m['last_name']) ?></option><?php endforeach; ?>
            </select></label>
            <label>สถานะ<select name="group"><option value="">ทุกสถานะที่ยังเปิด</option>
                <?php foreach (['pending' => 'รอรับเรื่อง', 'working' => 'กำลังดำเนินการ', 'signing' => 'รอเซ็น/รับรอง'] as $k => $v): ?><option value="<?= $k ?>" <?= $fGroup === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?>
            </select></label>
            <label>หมวดหมู่<select name="cat"><option value="">ทุกหมวด</option>
                <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $fCat === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?>
            </select></label>
            <label>ช่วงเวลาที่แจ้ง<select name="period">
                <?php foreach ($periods as $k => $v): ?><option value="<?= $k ?>" <?= $fPeriod === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?>
            </select></label>
        </form>
    </div>

    <?php if ($wrFlash): ?><div role="status" class="wr-flash wr-flash--<?= h($wrFlash[1] ?? 'ok') ?>"><?= h($wrFlash[0] ?? '') ?></div><?php endif; ?>

    <div id="wrBoard" class="space-y-5" aria-live="polite"><?= $board ?></div>
</main>

<script>
(function () {
    var REFRESH = <?= $refreshSeconds ?> * 1000;
    var board = document.getElementById('wrBoard');
    var filters = document.getElementById('wrFilters');
    filters.querySelectorAll('select').forEach(function (s) {
        s.addEventListener('change', function () { filters.requestSubmit ? filters.requestSubmit() : filters.submit(); });
    });
    function busy() {
        // ไม่รีเฟรชขณะผู้ใช้กำลังพิมพ์ข้อความเร่งงาน หรือโฟกัสอยู่ในบอร์ด
        var a = document.activeElement;
        return a && board.contains(a) && (a.tagName === 'INPUT' || a.tagName === 'BUTTON');
    }
    function refresh() {
        if (document.hidden || busy()) return;
        var q = new URLSearchParams(location.search);
        q.set('partial', '1');
        fetch('admin_war_room.php?' + q.toString(), { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
            .then(function (html) { board.innerHTML = html; })
            .catch(function () {});
    }
    setInterval(refresh, REFRESH);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
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
