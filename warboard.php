<?php
declare(strict_types=1);

/**
 * REPS · warboard.php — จอสรุปงานซ่อมสำหรับขึ้นจอ LED / ทีวีรวมในห้อง (ไม่ต้อง login)
 *
 *   warboard.php               หน้าเต็มจอ (ออกแบบสำหรับ 16:9 ดูจากระยะไกล)
 *   warboard.php?partial=1     เฉพาะเนื้อหากระดาน — หน้าเต็มเรียกซ้ำทุก 20 วินาทีเพื่ออัปเดตโดยไม่ต้องโหลดใหม่
 *   warboard.php?k=<รหัส>      ใช้เมื่อผู้ดูแลตั้ง "รหัสเปิดจอ" ไว้ในหน้าตั้งค่า
 *
 * เป็นหน้าสาธารณะ จึงไม่แสดงข้อมูลส่วนบุคคลของผู้แจ้ง (ชื่อ เบอร์โทร อีเมล):
 * แสดงเฉพาะเลขใบงาน สถานที่ อาการ สถานะ และชื่อต้นของสตาฟที่รับผิดชอบ
 * ปิดหน้าได้จาก ตั้งค่าระบบ → จอสรุปงาน (wallboard_enabled) · ผลคำนวณถูก cache 10 วินาที
 */

$GLOBALS['APP_SKIP_SESSION'] = true;   // หน้าสาธารณะ ไม่ต้องสร้าง session
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/site_config.php';
require_once __DIR__ . '/includes/terms.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/i18n.php';

const WALL_REFRESH_SECONDS = 20;
const WALL_CACHE_SECONDS = 10;
const WALL_ROWS_PER_PAGE = 6;

$settings = app_get_settings($conn);
$wallKey = trim((string)($settings['wallboard_key'] ?? ''));
$givenKey = is_string($_GET['k'] ?? null) ? $_GET['k'] : '';
if (($settings['wallboard_enabled'] ?? '1') !== '1' || ($wallKey !== '' && !hash_equals($wallKey, $givenKey))) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('ไม่พบหน้านี้');
}

function wall_h($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function wall_rows(mysqli $conn, string $sql): array
{
    try {
        $res = $conn->query($sql);
        return $res instanceof mysqli_result ? $res->fetch_all(MYSQLI_ASSOC) : [];
    } catch (Throwable $e) {
        error_log('wallboard: ' . $e->getMessage());
        return [];
    }
}

/** ระยะเวลาแบบอ่านง่ายจากระยะไกล: "12 นาที", "3 ชม. 20 นาที", "2 วัน" */
function wall_duration(?string $since): string
{
    $ts = $since ? strtotime($since) : false;
    if ($ts === false) {
        return '-';
    }
    $min = max(0, (int)floor((time() - $ts) / 60));
    if ($min < 60) {
        return $min . tr(' นาที', ' min');
    }
    if ($min < 1440) {
        return intdiv($min, 60) . tr(' ชม. ', ' h ') . ($min % 60) . tr(' นาที', ' min');
    }
    return intdiv($min, 1440) . tr(' วัน', ' d');
}

function wall_cut(string $text, int $len): string
{
    $text = trim((string)preg_replace('/\s+/u', ' ', $text));
    return mb_strlen($text) > $len ? mb_substr($text, 0, $len - 1) . '…' : $text;
}

/** ข้อมูลทั้งกระดาน (cache สั้น ๆ ในไฟล์ชั่วคราว กันหน้าสาธารณะยิงฐานข้อมูลถี่) */
function wall_data(mysqli $conn): array
{
    $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'reps_wallboard_' . substr(md5(__DIR__), 0, 12) . '.json';
    if (is_file($cacheFile) && filemtime($cacheFile) >= time() - WALL_CACHE_SECONDS) {
        $cached = json_decode((string)@file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            return $cached;
        }
    }

    $counts = wall_rows($conn,
        "SELECT SUM(status = 'pending') AS pending,
                SUM(status = 'pending' AND urgency = 'urgent') AS urgent,
                SUM(status IN ('accepted', 'awaiting_intake_sign', 'in_progress', 'inspected_ready')) AS working,
                SUM(status IN ('awaiting_requester_sign', 'awaiting_admin_sign', 'disputed')) AS signoff,
                SUM(status = 'completed' AND DATE(completed_at) = CURDATE()) AS done_today,
                SUM(DATE(created_at) = CURDATE()) AS new_today,
                SUM(status = 'completed' AND completed_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS done_month
           FROM tickets")[0] ?? [];

    $data = [
        'counts' => array_map('intval', $counts),
        'pending' => wall_rows($conn,
            "SELECT t.ticket_no, t.location_note, t.symptom, t.urgency, t.created_at, t.queue_expires_at, s.first_name AS queue_name
               FROM tickets t LEFT JOIN users s ON s.user_id = t.queue_assigned_to
              WHERE t.status = 'pending'
              ORDER BY t.urgency = 'urgent' DESC, t.created_at ASC LIMIT 18"),
        'working' => wall_rows($conn,
            "SELECT t.ticket_no, t.location_note, t.symptom, t.urgency, t.accepted_at, s.first_name AS staff_name,
                    (SELECT COUNT(*) FROM ticket_assignees a WHERE a.ticket_id = t.id) AS helpers
               FROM tickets t LEFT JOIN users s ON s.user_id = t.assigned_to
              WHERE t.status IN ('accepted', 'awaiting_intake_sign', 'in_progress', 'inspected_ready')
              ORDER BY t.urgency = 'urgent' DESC, t.accepted_at ASC LIMIT 18"),
        'signoff' => wall_rows($conn,
            "SELECT t.ticket_no, t.location_note, s.first_name AS staff_name
               FROM tickets t LEFT JOIN users s ON s.user_id = t.assigned_to
              WHERE t.status IN ('awaiting_requester_sign', 'awaiting_admin_sign', 'disputed') ORDER BY t.stage_since ASC LIMIT 6"),
        'done' => wall_rows($conn,
            "SELECT t.ticket_no, t.location_note, t.completed_at, s.first_name AS staff_name
               FROM tickets t LEFT JOIN users s ON s.user_id = t.assigned_to
              WHERE t.status = 'completed' ORDER BY t.completed_at DESC LIMIT 5"),
        'staff' => wall_rows($conn,
            "SELECT u.first_name, u.is_active_queue, " . term_score_sql(term_current($conn)) . " AS score,
                    (SELECT COUNT(*) FROM tickets t WHERE t.assigned_to = u.user_id AND t.status IN ('accepted', 'awaiting_intake_sign', 'in_progress', 'inspected_ready')) AS active_jobs,
                    (SELECT COUNT(*) FROM tickets t WHERE t.assigned_to = u.user_id AND t.status = 'completed'
                        AND t.completed_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS done_month
               FROM users u WHERE u.role = 'staff' AND u.status = 'active'" . term_staff_sql($conn) . "
              ORDER BY u.is_active_queue DESC, active_jobs DESC, u.first_name ASC LIMIT 10"),
        'trend' => [],
        'generated' => time(),
    ];

    // แนวโน้ม 7 วัน: แจ้งเข้า vs เสร็จ
    $in = array_column(wall_rows($conn, "SELECT DATE(created_at) AS d, COUNT(*) AS n FROM tickets WHERE created_at >= CURDATE() - INTERVAL 6 DAY GROUP BY d"), 'n', 'd');
    $out = array_column(wall_rows($conn, "SELECT DATE(completed_at) AS d, COUNT(*) AS n FROM tickets WHERE status = 'completed' AND completed_at >= CURDATE() - INTERVAL 6 DAY GROUP BY d"), 'n', 'd');
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} day"));
        $data['trend'][] = ['d' => $d, 'in' => (int)($in[$d] ?? 0), 'out' => (int)($out[$d] ?? 0)];
    }

    @file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $data;
}

/** รายการแบ่งเป็นหน้า ๆ (สลับหน้าอัตโนมัติฝั่งเบราว์เซอร์เมื่อมีมากกว่าหนึ่งหน้า) */
function wall_list(array $rows, callable $renderRow, string $emptyText): string
{
    if (!$rows) {
        return '<div class="wb-empty">' . app_icon('check', 'wb-empty-icon') . '<span>' . wall_h($emptyText) . '</span></div>';
    }
    $html = '<div class="wb-pages" data-pages>';
    foreach (array_chunk($rows, WALL_ROWS_PER_PAGE) as $p => $chunk) {
        $html .= '<ul class="wb-list' . ($p === 0 ? ' is-active' : '') . '" data-page>';
        foreach ($chunk as $i => $row) {
            $html .= '<li class="wb-row' . (($row['urgency'] ?? '') === 'urgent' ? ' is-urgent' : '') . '" style="--i:' . $i . '">' . $renderRow($row) . '</li>';
        }
        $html .= '</ul>';
    }
    return $html . '</div>';
}

function wall_board(array $d): string
{
    $c = $d['counts'] + ['pending' => 0, 'urgent' => 0, 'working' => 0, 'signoff' => 0, 'done_today' => 0, 'new_today' => 0, 'done_month' => 0];
    $thDays = ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'];
    $enDays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
    $trendMax = max(1, ...array_map(static fn($t) => max($t['in'], $t['out']), $d['trend'] ?: [['in' => 1, 'out' => 1]]));

    ob_start(); ?>
<section class="wb-kpis" aria-label="<?= tr('สรุปสถานะงาน', 'Job status summary') ?>">
    <?php foreach ([
        ['pending', $c['pending'], tr('รอรับงาน', 'Waiting'), $c['urgent'] > 0 ? tr('ด่วน ', 'Urgent ') . $c['urgent'] : tr('ไม่มีงานด่วน', 'No urgent jobs'), $c['urgent'] > 0],
        ['working', $c['working'], tr('กำลังซ่อม', 'In progress'), tr('สตาฟกำลังดำเนินการ', 'Staff working'), false],
        ['signoff', $c['signoff'], tr('รอเซ็น/รับรอง', 'Signing'), tr('ซ่อมเสร็จ รอเซ็นส่งคืน', 'Repaired, awaiting signatures'), false],
        ['done', $c['done_today'], tr('เสร็จวันนี้', 'Completed today'), tr('เดือนนี้ ', 'This month ') . number_format($c['done_month']), false],
        ['new', $c['new_today'], tr('แจ้งเข้าวันนี้', 'Reported today'), tr('ใบงานใหม่', 'New requests'), false],
    ] as $i => [$key, $value, $label, $note, $alert]): ?>
        <div class="wb-kpi wb-kpi--<?= $key ?>" style="--i:<?= $i ?>">
            <div class="wb-kpi-label"><?= wall_h($label) ?></div>
            <div class="wb-kpi-value" data-count="<?= (int)$value ?>"><?= number_format((int)$value) ?></div>
            <div class="wb-kpi-note<?= $alert ? ' is-alert' : '' ?>"><?= wall_h($note) ?></div>
        </div>
    <?php endforeach; ?>
</section>

<section class="wb-grid">
    <article class="wb-panel wb-panel--pending">
        <header class="wb-panel-head">
            <h2><?= tr('คิวงานรอรับ', 'Waiting queue') ?></h2>
            <span class="wb-panel-count"><?= (int)$c['pending'] ?></span>
        </header>
        <?= wall_list($d['pending'], static function (array $r): string {
            $who = $r['queue_name'] ? tr('คิว ', 'Queue: ') . wall_h($r['queue_name']) : tr('คิวกลาง', 'Open queue');
            return '<div class="wb-row-main"><span class="wb-no">' . wall_h($r['ticket_no']) . '</span>'
                . (($r['urgency'] ?? '') === 'urgent' ? '<span class="wb-flag">' . tr('ด่วน', 'Urgent') . '</span>' : '')
                . '<span class="wb-time">' . tr('รอ ', 'Waiting ') . wall_h(wall_duration($r['created_at'])) . '</span></div>'
                . '<div class="wb-row-text">' . wall_h(wall_cut((string)$r['location_note'], 46)) . '</div>'
                . '<div class="wb-row-sub"><span>' . wall_h(wall_cut((string)$r['symptom'], 54)) . '</span><span class="wb-who">' . $who . '</span></div>';
        }, tr('ไม่มีงานรอรับ', 'No jobs waiting')) ?>
    </article>

    <article class="wb-panel wb-panel--working">
        <header class="wb-panel-head">
            <h2><?= tr('กำลังซ่อม', 'In progress') ?></h2>
            <span class="wb-panel-count"><?= (int)$c['working'] ?></span>
        </header>
        <?= wall_list($d['working'], static function (array $r): string {
            $team = wall_h($r['staff_name'] ?: tr('ยังไม่ระบุ', 'Unassigned')) . ((int)$r['helpers'] > 0 ? ' +' . (int)$r['helpers'] : '');
            return '<div class="wb-row-main"><span class="wb-no">' . wall_h($r['ticket_no']) . '</span>'
                . (($r['urgency'] ?? '') === 'urgent' ? '<span class="wb-flag">' . tr('ด่วน', 'Urgent') . '</span>' : '')
                . '<span class="wb-time">' . wall_h(wall_duration($r['accepted_at'])) . '</span></div>'
                . '<div class="wb-row-text">' . wall_h(wall_cut((string)$r['location_note'], 46)) . '</div>'
                . '<div class="wb-row-sub"><span>' . wall_h(wall_cut((string)$r['symptom'], 54)) . '</span><span class="wb-who">' . tr('ช่าง ', 'Tech: ') . $team . '</span></div>';
        }, tr('ไม่มีงานที่กำลังซ่อม', 'No jobs in progress')) ?>
    </article>

    <div class="wb-side">
        <article class="wb-panel wb-panel--staff">
            <header class="wb-panel-head">
                <h2><?= tr('ทีมช่าง', 'Repair team') ?></h2>
                <span class="wb-panel-count"><?= count(array_filter($d['staff'], static fn($s) => (int)$s['is_active_queue'] === 1)) ?>/<?= count($d['staff']) ?></span>
            </header>
            <?php if (!$d['staff']): ?>
                <div class="wb-empty"><span><?= tr('ยังไม่มีสตาฟในระบบ', 'No staff yet') ?></span></div>
            <?php else: ?>
                <ul class="wb-staff">
                    <?php foreach ($d['staff'] as $s):
                        $jobs = (int)$s['active_jobs'];
                        $open = (int)$s['is_active_queue'] === 1; ?>
                        <li class="<?= $open ? 'is-open' : 'is-paused' ?>">
                            <span class="wb-staff-dot" aria-hidden="true"></span>
                            <span class="wb-staff-name"><?= wall_h($s['first_name']) ?></span>
                            <span class="wb-staff-state"><?= $jobs > 0 ? tr('ทำอยู่ ', 'Working ') . $jobs : ($open ? tr('ว่าง', 'Free') : tr('พักคิว', 'Paused')) ?></span>
                            <span class="wb-staff-done" title="<?= tr('เสร็จเดือนนี้', 'Completed this month') ?>"><?= (int)$s['done_month'] ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>

        <article class="wb-panel wb-panel--trend">
            <header class="wb-panel-head">
                <h2><?= tr('7 วันล่าสุด', 'Last 7 days') ?></h2>
                <span class="wb-legend"><i class="in"></i><?= tr('แจ้งเข้า', 'Reported') ?><i class="out"></i><?= tr('เสร็จ', 'Completed') ?></span>
            </header>
            <div class="wb-trend" role="img" aria-label="<?= tr('จำนวนงานแจ้งเข้าและเสร็จ 7 วันล่าสุด', 'Requests reported and completed over the last 7 days') ?>">
                <?php foreach ($d['trend'] as $j => $t):
                    $w = (int)date('w', strtotime($t['d'])); ?>
                    <div class="wb-trend-day<?= $j === 6 ? ' is-today' : '' ?>">
                        <div class="wb-trend-bars">
                            <span class="in" style="height: <?= round($t['in'] / $trendMax * 100) ?>%; --j:<?= $j ?>"><b><?= $t['in'] ?></b></span>
                            <span class="out" style="height: <?= round($t['out'] / $trendMax * 100) ?>%; --j:<?= $j ?>"><b><?= $t['out'] ?></b></span>
                        </div>
                        <div class="wb-trend-label"><?= app_lang() === 'en' ? $enDays[$w] : $thDays[$w] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
    </div>
</section>

<section class="wb-ticker" aria-label="<?= tr('งานที่เสร็จล่าสุด', 'Latest completed jobs') ?>">
    <span class="wb-ticker-label"><?= app_icon('check') ?> <?= tr('เสร็จล่าสุด', 'Just completed') ?></span>
    <div class="wb-ticker-items">
        <?php if (!$d['done']): ?>
            <span class="wb-ticker-item"><?= tr('ยังไม่มีงานที่เสร็จสมบูรณ์', 'No completed jobs yet') ?></span>
        <?php endif; ?>
        <?php foreach ($d['done'] as $r): ?>
            <span class="wb-ticker-item"><b><?= wall_h($r['ticket_no']) ?></b> <?= wall_h(wall_cut((string)$r['location_note'], 34)) ?><?= $r['staff_name'] ? ' · ' . wall_h($r['staff_name']) : '' ?> · <?= wall_h(date('d/m H:i', (int)strtotime((string)$r['completed_at']))) ?></span>
        <?php endforeach; ?>
        <?php if ($d['signoff']): ?>
            <span class="wb-ticker-item is-signoff"><?= tr('รอเซ็น/รับรอง: ', 'Awaiting signatures: ') ?><?= wall_h(implode(', ', array_column($d['signoff'], 'ticket_no'))) ?></span>
        <?php endif; ?>
    </div>
</section>
<?php
    return (string)ob_get_clean();
}

$board = wall_board(wall_data($conn));

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
if (isset($_GET['partial'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo $board;
    exit;
}

$siteName = app_site_name($conn);
$orgLine = trim((string)($settings['org_name'] ?? '')) ?: trim((string)($settings['org_unit'] ?? ''));
$partialUrl = 'warboard.php?partial=1' . ($wallKey !== '' ? '&k=' . rawurlencode($wallKey) : '');
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= tr('จอสรุปงานซ่อม', 'Repair wallboard') ?> | <?= wall_h($siteName) ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/images/logo/reps-icon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <?= app_theme_css($conn) ?>
    <link rel="stylesheet" href="assets/css/wallboard.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/wallboard.css') ?>">
</head>

<body>
    <header class="wb-top">
        <div class="wb-brand">
            <img src="assets/images/logo/reps-icon.svg" alt="" class="wb-logo">
            <div>
                <div class="wb-title"><?= tr('ศูนย์ติดตามงานซ่อม', 'Repair operations board') ?></div>
                <div class="wb-sub"><?= wall_h($orgLine !== '' ? $orgLine : $siteName) ?></div>
            </div>
        </div>
        <div class="wb-live" id="wbLive" role="status" aria-live="off">
            <span class="wb-live-dot" aria-hidden="true"></span>
            <span id="wbLiveText"><?= tr('อัปเดตอัตโนมัติ', 'Live') ?></span>
        </div>
        <div class="wb-clock" aria-hidden="true">
            <div class="wb-clock-time" id="wbTime">--:--</div>
            <div class="wb-clock-date" id="wbDate"></div>
        </div>
    </header>

    <main id="wbBoard" class="wb-board"><?= $board ?></main>

    <script>
    (function () {
        var REFRESH = <?= WALL_REFRESH_SECONDS ?> * 1000;
        var PAGE_MS = 8000;
        var URL_PARTIAL = <?= json_encode($partialUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        var LANG = <?= json_encode(app_lang()) ?>;
        var TXT = {
            live: <?= json_encode(tr('อัปเดตอัตโนมัติ', 'Live'), JSON_UNESCAPED_UNICODE) ?>,
            lost: <?= json_encode(tr('ขาดการเชื่อมต่อ กำลังลองใหม่', 'Connection lost, retrying'), JSON_UNESCAPED_UNICODE) ?>
        };
        var board = document.getElementById('wbBoard');
        var live = document.getElementById('wbLive');
        var liveText = document.getElementById('wbLiveText');

        // ---------- นาฬิกา (เวลาไทย)
        var days = LANG === 'en' ? ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
            : ['วันอาทิตย์', 'วันจันทร์', 'วันอังคาร', 'วันพุธ', 'วันพฤหัสบดี', 'วันศุกร์', 'วันเสาร์'];
        var months = LANG === 'en' ? ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
            : ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
        function tick() {
            var now = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Bangkok' }));
            var pad = function (n) { return (n < 10 ? '0' : '') + n; };
            document.getElementById('wbTime').textContent = pad(now.getHours()) + ':' + pad(now.getMinutes());
            document.getElementById('wbDate').textContent = days[now.getDay()] + ' ' + now.getDate() + ' ' + months[now.getMonth()] + ' '
                + (LANG === 'en' ? now.getFullYear() : now.getFullYear() + 543);
        }
        tick();
        setInterval(tick, 10000);

        // ---------- สลับหน้ารายการเมื่อมีมากกว่าหนึ่งหน้า
        var pageIndex = 0;
        function rotatePages() {
            pageIndex++;
            board.querySelectorAll('[data-pages]').forEach(function (wrap) {
                var pages = wrap.querySelectorAll('[data-page]');
                if (pages.length < 2) return;
                pages.forEach(function (p, i) { p.classList.toggle('is-active', i === pageIndex % pages.length); });
            });
        }
        setInterval(rotatePages, PAGE_MS);

        // ---------- อัปเดตเนื้อหาโดยไม่โหลดหน้าใหม่
        function counts() {
            var out = {};
            board.querySelectorAll('.wb-kpi').forEach(function (k) {
                out[k.className] = k.querySelector('[data-count]').getAttribute('data-count');
            });
            return out;
        }
        function refresh() {
            var before = counts();
            fetch(URL_PARTIAL, { cache: 'no-store', credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
                .then(function (html) {
                    board.classList.add('is-refresh');   // ไม่เล่น animation เข้าใหม่ทุกครั้ง
                    board.innerHTML = html;
                    pageIndex = 0;
                    var after = counts();
                    Object.keys(after).forEach(function (cls) {
                        if (before[cls] !== undefined && before[cls] !== after[cls]) {
                            var el = board.querySelector('.' + cls.split(' ').join('.') + ' .wb-kpi-value');
                            if (el) el.classList.add('is-changed');
                        }
                    });
                    live.classList.remove('is-lost');
                    liveText.textContent = TXT.live;
                })
                .catch(function () {
                    live.classList.add('is-lost');
                    liveText.textContent = TXT.lost;
                });
        }
        setInterval(refresh, REFRESH);

        // โหลดหน้าใหม่ทั้งหมดวันละครั้ง (ตอนตี 4) กันจอที่เปิดค้างหลายวันใช้โค้ดเก่า
        setInterval(function () {
            var now = new Date();
            if (now.getHours() === 4 && now.getMinutes() < 2) location.reload();
        }, 90000);
    })();
    </script>
</body>

</html>
