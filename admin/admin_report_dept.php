<?php
declare(strict_types=1);

/**
 * REPS · admin/admin_report_dept.php — รายงานตามแผนก/งาน (สังกัดของผู้แจ้ง)
 *   ?term=<id>                    ภาคเรียน (ค่าเริ่มต้น = ภาคเรียนปัจจุบัน)
 *   ?term=all&acad_year=2569      ทั้งปีการศึกษา
 *   ?term=range&from=Y-m-d&to=Y-m-d  กำหนดช่วงวันที่เอง (ไม่เกิน 3 ปี)
 *   &d=<department id|0>          ดูรายการใบงานของแผนกนั้น (0 = ไม่ระบุแผนก)
 *   &export=csv                   ดาวน์โหลดสรุปเป็น CSV (เปิดใน Excel ได้)
 * แผนกนับตามสังกัดปัจจุบันของผู้แจ้ง (users.department_id) · ช่วงเวลานับจากวันที่แจ้ง (tickets.created_at)
 */

$page_title = 'รายงานตามแผนก/งาน';
$current_page = 'report_dept';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/db_helpers.php';
require_once __DIR__ . '/../includes/site_config.php';
require_once __DIR__ . '/../includes/terms.php';
require_once __DIR__ . '/../includes/ticket_service.php';

function rd_h(mixed $v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

/** วันที่แบบไทย (พ.ศ.) */
function rd_date(string $dt): string
{
    $ts = strtotime($dt);
    return $ts ? date('d/m/', $ts) . ((int)date('Y', $ts) + 543) : '-';
}

/** ระยะเวลาจากนาที: "45 นาที", "3 ชม. 20 นาที", "2 วัน 4 ชม." */
function rd_duration(mixed $minutes): string
{
    if ($minutes === null || $minutes === '') {
        return '-';
    }
    $m = (int)round((float)$minutes);
    if ($m < 60) {
        return $m . ' นาที';
    }
    if ($m < 1440) {
        return intdiv($m, 60) . ' ชม.' . ($m % 60 ? ' ' . ($m % 60) . ' นาที' : '');
    }
    $hours = intdiv($m % 1440, 60);
    return intdiv($m, 1440) . ' วัน' . ($hours ? ' ' . $hours . ' ชม.' : '');
}

// ---------- ช่วงเวลา ----------
$settings = app_get_settings($conn);
$startMonth = max(1, min(12, (int)($settings['academic_year_start_month'] ?? 5)));
$currentAcadYearBE = ((int)date('n') >= $startMonth ? (int)date('Y') : (int)date('Y') - 1) + 543;
$allTerms = term_all($conn);
$termParam = is_string($_GET['term'] ?? null) ? $_GET['term'] : '';
$selTerm = null;
$mode = 'term';
if ($termParam === 'range') {
    $mode = 'range';
} elseif ($termParam === 'all' || !$allTerms) {
    $mode = 'year';
} else {
    $selTerm = ctype_digit($termParam) ? term_get($conn, (int)$termParam) : null;
    $selTerm ??= term_current($conn);
    if (!$selTerm) {
        $mode = 'year';
    }
}

$fromInput = is_string($_GET['from'] ?? null) ? $_GET['from'] : '';
$toInput = is_string($_GET['to'] ?? null) ? $_GET['to'] : '';
$selectedAcadYearBE = filter_input(INPUT_GET, 'acad_year', FILTER_VALIDATE_INT) ?: $currentAcadYearBE;
$rangeError = '';
if ($mode === 'term') {
    [$startDate, $endDate] = term_range($selTerm);
    $periodLabel = term_title($selTerm);
} elseif ($mode === 'range') {
    $validDate = static fn(string $d): bool => (bool)preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $d) && checkdate((int)substr($d, 5, 2), (int)substr($d, 8, 2), (int)substr($d, 0, 4));
    if (!$validDate($fromInput) || !$validDate($toInput)) {
        $fromInput = date('Y-m-01');
        $toInput = date('Y-m-d');
    }
    if ($fromInput > $toInput) {
        [$fromInput, $toInput] = [$toInput, $fromInput];
    }
    if (strtotime($toInput) - strtotime($fromInput) > 3 * 366 * 86400) {
        $fromInput = date('Y-m-d', strtotime($toInput . ' -3 years'));
        $rangeError = 'ช่วงวันที่ยาวเกิน 3 ปี ระบบปรับวันเริ่มต้นให้แล้ว';
    }
    $startDate = $fromInput . ' 00:00:00';
    $endDate = $toInput . ' 23:59:59';
    $periodLabel = rd_date($startDate) . ' – ' . rd_date($endDate);
} else {
    $yearTerms = array_values(array_filter($allTerms, static fn($t) => (int)$t['year_be'] === $selectedAcadYearBE));
    if ($yearTerms) {
        $startDate = min(array_column($yearTerms, 'start_date')) . ' 00:00:00';
        $endDate = max(array_column($yearTerms, 'end_date')) . ' 23:59:59';
    } else {
        $startDate = sprintf('%04d-%02d-01 00:00:00', $selectedAcadYearBE - 543, $startMonth);
        $endDate = date('Y-m-d H:i:s', strtotime('+1 year -1 second', strtotime($startDate)));
    }
    $periodLabel = 'ปีการศึกษา ' . $selectedAcadYearBE;
}

// ---------- ข้อมูล ----------
$openSql = ticket_status_sql(TICKET_OPEN_STATUSES);
$rows = db_all(
    $conn,
    "SELECT COALESCE(d.d_id, 0) AS did, COALESCE(d.name, '') AS dname,
            COUNT(*) AS total,
            SUM(t.status = 'completed') AS done,
            SUM(t.status IN ({$openSql})) AS open_cnt,
            SUM(t.status = 'cancelled') AS cancelled,
            SUM(t.urgency = 'urgent') AS urgent,
            AVG(CASE WHEN t.accepted_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.accepted_at) END) AS avg_accept,
            AVG(CASE WHEN t.status = 'completed' AND t.completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.completed_at) END) AS avg_done
       FROM tickets t
       JOIN users u ON u.user_id = t.requester_id
       LEFT JOIN department d ON d.d_id = u.department_id
      WHERE t.created_at BETWEEN ? AND ?
      GROUP BY COALESCE(d.d_id, 0), COALESCE(d.name, '')",
    'ss',
    [$startDate, $endDate]
);
$byDept = [];
foreach ($rows as $r) {
    $byDept[(int)$r['did']] = $r;
}
// หมวดหมู่ที่แจ้งบ่อยของแต่ละแผนก (3 อันดับ)
$topCats = [];
foreach (db_all(
    $conn,
    "SELECT COALESCE(u.department_id, 0) AS did, COALESCE(c.name, 'ไม่ระบุหมวดหมู่') AS cname, COUNT(*) AS n
       FROM tickets t
       JOIN users u ON u.user_id = t.requester_id
       LEFT JOIN categories c ON c.id = t.category_id
      WHERE t.created_at BETWEEN ? AND ?
      GROUP BY COALESCE(u.department_id, 0), COALESCE(c.name, 'ไม่ระบุหมวดหมู่')
      ORDER BY n DESC, cname",
    'ss',
    [$startDate, $endDate]
) as $c) {
    $did = (int)$c['did'];
    if (count($topCats[$did] ?? []) < 3) {
        $topCats[$did][] = $c['cname'] . ' (' . (int)$c['n'] . ')';
    }
}
// ทุกแผนกในระบบ (แผนกที่ไม่มีใบงานแสดงเป็น 0) + "ไม่ระบุแผนก" ถ้ามีใบงาน
$departments = db_all($conn, 'SELECT d_id, name FROM department ORDER BY name');
$report = [];
foreach ($departments as $d) {
    $did = (int)$d['d_id'];
    $r = $byDept[$did] ?? null;
    $report[] = [
        'did' => $did, 'name' => (string)$d['name'],
        'total' => (int)($r['total'] ?? 0), 'done' => (int)($r['done'] ?? 0), 'open' => (int)($r['open_cnt'] ?? 0),
        'cancelled' => (int)($r['cancelled'] ?? 0), 'urgent' => (int)($r['urgent'] ?? 0),
        'avg_accept' => $r['avg_accept'] ?? null, 'avg_done' => $r['avg_done'] ?? null,
        'top' => $topCats[$did] ?? [],
    ];
}
if (isset($byDept[0])) {
    $r = $byDept[0];
    $report[] = [
        'did' => 0, 'name' => 'ไม่ระบุแผนก',
        'total' => (int)$r['total'], 'done' => (int)$r['done'], 'open' => (int)$r['open_cnt'],
        'cancelled' => (int)$r['cancelled'], 'urgent' => (int)$r['urgent'],
        'avg_accept' => $r['avg_accept'], 'avg_done' => $r['avg_done'], 'top' => $topCats[0] ?? [],
    ];
}
usort($report, static fn($a, $b) => [$b['total'], $a['name']] <=> [$a['total'], $b['name']]);
$grandTotal = array_sum(array_column($report, 'total'));
$grandDone = array_sum(array_column($report, 'done'));
$deptWithTickets = count(array_filter($report, static fn($r) => $r['total'] > 0 && $r['did'] !== 0));
$maxTotal = max(1, ...array_column($report, 'total'));
$overallDone = db_one(
    $conn,
    "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, completed_at)) AS m FROM tickets
      WHERE status = 'completed' AND completed_at IS NOT NULL AND created_at BETWEEN ? AND ?",
    'ss',
    [$startDate, $endDate]
);

// พารามิเตอร์ช่วงเวลาปัจจุบัน (ใช้กับลิงก์ดูแผนก และ CSV)
$periodParams = ['term' => $mode === 'term' ? (string)$selTerm['id'] : ($mode === 'range' ? 'range' : 'all')];
if ($mode === 'year') {
    $periodParams['acad_year'] = (string)$selectedAcadYearBE;
}
if ($mode === 'range') {
    $periodParams['from'] = $fromInput;
    $periodParams['to'] = $toInput;
}
$linkWith = static fn(array $extra): string => 'admin_report_dept.php?' . http_build_query($periodParams + $extra);

// ---------- CSV ----------
if (($_GET['export'] ?? '') === 'csv') {
    $filename = 'reps-department-report-' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF"); // BOM ให้ Excel อ่านภาษาไทยถูก
    // กัน CSV injection: ข้อความที่ขึ้นต้นด้วย = + - @ ใส่ ' นำหน้า
    $cell = static fn($v): string => preg_match('/\A[=+\-@\t\r]/', (string)$v) ? "'" . $v : (string)$v;
    fputcsv($out, ['รายงานตามแผนก/งาน', $periodLabel]);
    fputcsv($out, ['แผนก/งาน', 'ใบงานทั้งหมด', 'เสร็จสมบูรณ์', 'ยังไม่ปิด', 'ยกเลิก', 'ด่วน', 'รับงานเฉลี่ย (นาที)', 'เสร็จเฉลี่ย (นาที)', 'หมวดที่แจ้งบ่อย']);
    foreach ($report as $r) {
        fputcsv($out, array_map($cell, [
            $r['name'], $r['total'], $r['done'], $r['open'], $r['cancelled'], $r['urgent'],
            $r['avg_accept'] !== null ? (int)round((float)$r['avg_accept']) : '',
            $r['avg_done'] !== null ? (int)round((float)$r['avg_done']) : '',
            implode(', ', $r['top']),
        ]));
    }
    fputcsv($out, ['รวม', $grandTotal, $grandDone]);
    fclose($out);
    exit;
}

// ---------- รายการใบงานของแผนกที่เลือก ----------
$selDept = isset($_GET['d']) && ctype_digit((string)$_GET['d']) ? (int)$_GET['d'] : null;
$selDeptRow = null;
$deptTickets = [];
if ($selDept !== null) {
    foreach ($report as $r) {
        if ($r['did'] === $selDept) {
            $selDeptRow = $r;
        }
    }
    if ($selDeptRow) {
        $deptTickets = db_all(
            $conn,
            'SELECT t.ticket_no, t.created_at, t.status, t.urgency, t.location_note, c.name AS cname,
                    CONCAT(u.first_name, " ", u.last_name) AS requester
               FROM tickets t
               JOIN users u ON u.user_id = t.requester_id
               LEFT JOIN categories c ON c.id = t.category_id
              WHERE t.created_at BETWEEN ? AND ? AND ' . ($selDept === 0 ? 'u.department_id IS NULL' : 'u.department_id = ?') . '
              ORDER BY t.created_at DESC LIMIT 100',
            $selDept === 0 ? 'ss' : 'ssi',
            $selDept === 0 ? [$startDate, $endDate] : [$startDate, $endDate, $selDept]
        );
    }
}

ob_start(); ?>
<style>
    .rd-filters { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; padding: 12px; border: 1px solid #EAE8E4; border-radius: 14px; background: #FFFFFF; }
    .rd-filters label { display: flex; flex-direction: column; gap: 4px; font-size: 12.5px; color: #57544F; }
    .rd-filters select, .rd-filters input { min-height: 40px; padding: 0 10px; border: 1px solid #C9C5BE; border-radius: 10px; background: #FFFFFF; font: inherit; font-size: 14px; color: #161616; }
    .rd-filters .rd-actions { display: flex; gap: 8px; margin-left: auto; }
    .rd-kpis { display: grid; gap: 12px; grid-template-columns: repeat(4, minmax(0, 1fr)); }
    @media (max-width: 1100px) { .rd-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .rd-kpi { padding: 16px; border: 1px solid #E7E4DF; border-radius: 14px; background: #FFFFFF; }
    .rd-kpi span { font-size: 12.5px; color: #6B6862; }
    .rd-kpi strong { display: block; margin-top: 6px; font-size: 26px; font-weight: 600; line-height: 1.2; font-variant-numeric: tabular-nums; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .rd-kpi small { display: block; margin-top: 2px; font-size: 12.5px; color: #6B6862; }
    .rd-table { width: 100%; min-width: 980px; border-collapse: collapse; font-size: 14px; }
    .rd-table th { padding: 10px 14px; background: #FAFAF8; color: #57544F; font-size: 12.5px; font-weight: 500; text-align: left; white-space: nowrap; }
    .rd-table td { padding: 11px 14px; border-top: 1px solid #EFEDE9; vertical-align: middle; }
    .rd-table .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .rd-table tr.is-zero td { color: #8A867F; }
    .rd-table tr.is-selected td { background: #FCFBFA; }
    .rd-bar { display: flex; align-items: center; gap: 10px; min-width: 160px; }
    .rd-bar__track { flex: 1; height: 8px; border-radius: 999px; background: #F1EFEC; overflow: hidden; }
    .rd-bar__fill { display: block; height: 100%; border-radius: inherit; background: var(--theme-primary, #161616); }
    .rd-bar strong { min-width: 2.5ch; font-weight: 600; text-align: right; font-variant-numeric: tabular-nums; }
    .rd-top { color: #57544F; font-size: 13px; }
    .rd-link { display: inline-flex; align-items: center; min-height: 32px; padding: 0 10px; border: 1px solid #D4D0C9; border-radius: 8px; background: #FFFFFF; color: #161616; font-size: 13px; text-decoration: none; white-space: nowrap; }
    .rd-link:hover { background: #F5F4F1; }
    .rd-note { font-size: 12.5px; color: #6B6862; }
    @media print { .no-print { display: none !important; } }
</style>
<?php $admin_extra_head = ob_get_clean();
require_once __DIR__ . '/admin_header.php';
?>

<div class="admin-page-head">
    <nav class="admin-crumb">
        <a href="admin_dashboard.php">แดชบอร์ด</a>
        <i class="fa-solid fa-chevron-right"></i>
        <span>รายงาน</span>
        <i class="fa-solid fa-chevron-right"></i>
        <span>ตามแผนก/งาน</span>
    </nav>
    <h1 class="admin-page-title">รายงานตามแผนก/งาน</h1>
    <p class="admin-page-sub"><?= rd_h($periodLabel) ?> · <?= rd_date($startDate) ?> ถึง <?= rd_date($endDate) ?> · นับตามแผนก/งานที่ผู้แจ้งสังกัด</p>
</div>

<form method="get" class="rd-filters no-print mb-5" id="rdFilters">
    <label>ช่วงรายงาน
        <select name="term" data-autosubmit>
            <?php foreach ($allTerms as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= $mode === 'term' && (int)$selTerm['id'] === (int)$t['id'] ? 'selected' : '' ?>><?= rd_h(term_title($t)) ?></option>
            <?php endforeach; ?>
            <option value="all" <?= $mode === 'year' ? 'selected' : '' ?>>ทั้งปีการศึกษา</option>
            <option value="range" <?= $mode === 'range' ? 'selected' : '' ?>>กำหนดช่วงวันที่</option>
        </select>
    </label>
    <?php if ($mode === 'year'): ?>
        <label>ปีการศึกษา
            <select name="acad_year" data-autosubmit>
                <?php for ($y = $currentAcadYearBE; $y >= $currentAcadYearBE - 4; $y--): ?>
                    <option value="<?= $y ?>" <?= $selectedAcadYearBE === $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </label>
    <?php elseif ($mode === 'range'): ?>
        <label>ตั้งแต่<input type="date" name="from" value="<?= rd_h($fromInput) ?>" required></label>
        <label>ถึง<input type="date" name="to" value="<?= rd_h($toInput) ?>" required></label>
        <button type="submit" class="admin-btn">แสดงรายงาน</button>
    <?php endif; ?>
    <div class="rd-actions">
        <a class="admin-btn admin-btn-secondary" href="<?= rd_h($linkWith(['export' => 'csv'])) ?>"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> ดาวน์โหลด CSV</a>
        <button type="button" class="admin-btn admin-btn-secondary" onclick="window.print()"><i class="fa-solid fa-print" aria-hidden="true"></i> พิมพ์</button>
    </div>
</form>
<?php if ($rangeError !== ''): ?>
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="status"><?= rd_h($rangeError) ?></div>
<?php endif; ?>

<?php $topDept = $report && $report[0]['total'] > 0 ? $report[0] : null; ?>
<section class="rd-kpis mb-5" aria-label="สรุป">
    <div class="rd-kpi"><span>ใบงานทั้งหมด</span><strong><?= number_format($grandTotal) ?></strong><small>เสร็จสมบูรณ์ <?= number_format($grandDone) ?> (<?= $grandTotal ? round($grandDone / $grandTotal * 100) : 0 ?>%)</small></div>
    <div class="rd-kpi"><span>แผนก/งานที่แจ้งซ่อม</span><strong><?= $deptWithTickets ?> <span style="font-size:15px;font-weight:400;color:#6B6862">จาก <?= count($departments) ?></span></strong><small><?= count($departments) - $deptWithTickets ?> แผนก/งานไม่มีใบงานในช่วงนี้</small></div>
    <div class="rd-kpi"><span>แจ้งมากที่สุด</span><strong title="<?= rd_h($topDept['name'] ?? '-') ?>"><?= rd_h($topDept['name'] ?? '-') ?></strong><small><?= $topDept ? number_format($topDept['total']) . ' ใบงาน · ' . round($topDept['total'] / max(1, $grandTotal) * 100) . '% ของทั้งหมด' : 'ยังไม่มีใบงาน' ?></small></div>
    <div class="rd-kpi"><span>ตั้งแต่แจ้งจนเสร็จ (เฉลี่ย)</span><strong><?= rd_h(rd_duration($overallDone['m'] ?? null)) ?></strong><small>นับเฉพาะงานที่เสร็จสมบูรณ์</small></div>
</section>

<section class="admin-card mb-5">
    <div class="admin-toolbar justify-between">
        <h2 class="font-medium text-gray-900">สรุปรายแผนก/งาน</h2>
        <span class="rd-note">เรียงจากแจ้งมากไปน้อย · กด "ดูใบงาน" เพื่อดูรายการ</span>
    </div>
    <div class="overflow-x-auto">
        <table class="rd-table">
            <thead>
                <tr>
                    <th scope="col">แผนก/งาน</th>
                    <th scope="col">ใบงาน</th>
                    <th scope="col" class="num">เสร็จ</th>
                    <th scope="col" class="num">ยังไม่ปิด</th>
                    <th scope="col" class="num">ยกเลิก</th>
                    <th scope="col" class="num">ด่วน</th>
                    <th scope="col" class="num">รับงานเฉลี่ย</th>
                    <th scope="col" class="num">เสร็จเฉลี่ย</th>
                    <th scope="col">หมวดที่แจ้งบ่อย</th>
                    <th scope="col" class="no-print"><span class="sr-only">ดูใบงาน</span></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$report): ?>
                    <tr><td colspan="10" class="rd-note">ยังไม่มีข้อมูลแผนกในระบบ</td></tr>
                <?php endif; ?>
                <?php foreach ($report as $r): ?>
                    <tr class="<?= $r['total'] === 0 ? 'is-zero' : '' ?><?= $selDept === $r['did'] ? ' is-selected' : '' ?>">
                        <td class="font-medium"><?= rd_h($r['name']) ?></td>
                        <td>
                            <div class="rd-bar" role="img" aria-label="<?= rd_h($r['name']) ?> <?= $r['total'] ?> ใบงาน">
                                <strong><?= number_format($r['total']) ?></strong>
                                <span class="rd-bar__track"><span class="rd-bar__fill" style="width: <?= round($r['total'] / $maxTotal * 100, 1) ?>%"></span></span>
                            </div>
                        </td>
                        <td class="num"><?= $r['total'] ? number_format($r['done']) : '-' ?></td>
                        <td class="num"><?= $r['total'] ? number_format($r['open']) : '-' ?></td>
                        <td class="num"><?= $r['total'] ? number_format($r['cancelled']) : '-' ?></td>
                        <td class="num"><?= $r['total'] ? number_format($r['urgent']) : '-' ?></td>
                        <td class="num"><?= rd_h(rd_duration($r['avg_accept'])) ?></td>
                        <td class="num"><?= rd_h(rd_duration($r['avg_done'])) ?></td>
                        <td class="rd-top"><?= $r['top'] ? rd_h(implode(' · ', $r['top'])) : '-' ?></td>
                        <td class="no-print"><?php if ($r['total'] > 0): ?><a class="rd-link" href="<?= rd_h($linkWith(['d' => $r['did']])) ?>#dept-tickets">ดูใบงาน</a><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($selDeptRow): ?>
    <section class="admin-card mb-5" id="dept-tickets">
        <div class="admin-toolbar justify-between">
            <div>
                <h2 class="font-medium text-gray-900">ใบงานของ <?= rd_h($selDeptRow['name']) ?></h2>
                <p class="rd-note mt-1"><?= number_format($selDeptRow['total']) ?> ใบงาน<?= $selDeptRow['total'] > 100 ? ' · แสดง 100 รายการล่าสุด' : '' ?></p>
            </div>
            <a class="rd-link no-print" href="<?= rd_h($linkWith([])) ?>">ปิดรายการ</a>
        </div>
        <div class="overflow-x-auto">
            <table class="rd-table" style="min-width: 760px">
                <thead>
                    <tr>
                        <th scope="col">เลขใบงาน</th>
                        <th scope="col">วันที่แจ้ง</th>
                        <th scope="col">ผู้แจ้ง</th>
                        <th scope="col">หมวดหมู่</th>
                        <th scope="col">สถานที่</th>
                        <th scope="col">สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deptTickets as $t): ?>
                        <tr>
                            <td class="font-mono"><a class="underline" href="admin_manage_request.php?q=<?= rawurlencode((string)$t['ticket_no']) ?>"><?= rd_h($t['ticket_no']) ?></a><?= $t['urgency'] === 'urgent' ? ' <span class="stock-alert__tab">ด่วน</span>' : '' ?></td>
                            <td class="whitespace-nowrap"><?= rd_h(rd_date((string)$t['created_at'])) ?></td>
                            <td><?= rd_h($t['requester']) ?></td>
                            <td><?= rd_h($t['cname'] ?? 'ไม่ระบุหมวดหมู่') ?></td>
                            <td><?= rd_h($t['location_note']) ?></td>
                            <td class="whitespace-nowrap"><?= rd_h(TICKET_STATUS_LABELS[$t['status']] ?? $t['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<p class="rd-note mb-6">แผนก/งานนับตามสังกัดปัจจุบันของผู้แจ้ง (ถ้าผู้ใช้ย้ายแผนก ใบงานเก่าจะนับไปที่แผนกใหม่) · ช่วงเวลานับจากวันที่แจ้ง · รับงานเฉลี่ย = ตั้งแต่แจ้งจนช่างรับงาน</p>

<script>
    document.querySelectorAll('#rdFilters [data-autosubmit]').forEach(function (el) {
        el.addEventListener('change', function () {
            var f = el.form;
            if (el.name === 'term') { ['acad_year', 'from', 'to'].forEach(function (n) { var x = f.querySelector('[name="' + n + '"]'); if (x) x.disabled = true; }); }
            f.submit();
        });
    });
</script>
      </div>
<?php $footerBase = '../'; $footerVariant = 'app'; require __DIR__ . '/../includes/site_footer.php'; ?>
   </div>
</body>
</html>
