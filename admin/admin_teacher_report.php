<?php
declare(strict_types=1);

/**
 * REPS · admin/admin_teacher_report.php
 * รายงานตามภาคเรียน (ค่าเริ่มต้น = ภาคเรียนปัจจุบัน) หรือทั้งปีการศึกษา + กราฟผลงานของสตาฟแต่ละคน
 *   ?term=<id>        ภาคเรียนที่เลือก: สตาฟ = ทีมของภาคเรียนนั้น · คะแนน = คะแนนในภาคเรียนนั้น
 *   ?term=all&acad_year=2569  ทั้งปีการศึกษา: สตาฟที่มีงานในปีนั้น · คะแนนรวมในช่วงปี
 */

require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/user_auth.php';
require_once dirname(__DIR__) . '/includes/site_config.php';
require_once dirname(__DIR__) . '/includes/terms.php';

$actorId = app_require_active_user($conn, '../login.php?next=teacher_report', ['admin']);
$siteName = app_site_name($conn);
$settings = app_get_settings($conn);
$startMonth = max(1, min(12, (int)($settings['academic_year_start_month'] ?? 5))); // ค่าเริ่มต้นเดือน 5 (พฤษภาคม)

// คำนวณปีการศึกษาไทยปัจจุบัน (พ.ศ.)
$currentYearAD = (int)date('Y');
$currentMonth  = (int)date('n');
$currentAcadYearBE = ($currentMonth >= $startMonth ? $currentYearAD : $currentYearAD - 1) + 543;

$allTerms = term_all($conn);
$termParam = is_string($_GET['term'] ?? null) ? $_GET['term'] : '';
$selTerm = null;
if ($termParam !== 'all' && $allTerms) {
    $selTerm = ctype_digit($termParam) ? term_get($conn, (int)$termParam) : null;
    $selTerm ??= term_current($conn);
}

if ($selTerm) {
    $selectedAcadYearBE = (int)$selTerm['year_be'];
    [$startDate, $endDate] = term_range($selTerm);
    $periodLabel = term_title($selTerm);
} else {
    $selectedAcadYearBE = filter_input(INPUT_GET, 'acad_year', FILTER_VALIDATE_INT) ?: $currentAcadYearBE;
    $startYearAD = $selectedAcadYearBE - 543;
    // ช่วงวันที่ของปีการศึกษาที่เลือก: ใช้ภาคเรียนที่ตั้งไว้ของปีนั้นถ้ามี ไม่งั้นนับจากเดือนเริ่มปีการศึกษา 12 เดือน
    $yearTerms = array_values(array_filter($allTerms, static fn($t) => (int)$t['year_be'] === $selectedAcadYearBE));
    if ($yearTerms) {
        $startDate = min(array_column($yearTerms, 'start_date')) . ' 00:00:00';
        $endDate = max(array_column($yearTerms, 'end_date')) . ' 23:59:59';
    } else {
        $startDate = sprintf('%04d-%02d-01 00:00:00', $startYearAD, $startMonth);
        $endDate = date('Y-m-d H:i:s', strtotime('+1 year -1 second', strtotime($startDate)));
    }
    $periodLabel = 'ปีการศึกษา ' . $selectedAcadYearBE;
}
$rangeTerm = ['start_date' => substr($startDate, 0, 10), 'end_date' => substr($endDate, 0, 10)];

// 1. สรุปภาพรวมของช่วงที่เลือก
$kpiStmt = $conn->prepare(
    "SELECT
        COUNT(*) AS total_tickets,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_tickets,
        SUM(CASE WHEN status IN ('accepted', 'awaiting_intake_sign', 'in_progress', 'inspected_ready') THEN 1 ELSE 0 END) AS in_progress_tickets,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_tickets
     FROM tickets
     WHERE created_at BETWEEN ? AND ?"
);
$kpiStmt->bind_param('ss', $startDate, $endDate);
$kpiStmt->execute();
$kpi = $kpiStmt->get_result()->fetch_assoc();
$kpiStmt->close();

// 2. ผลงานสตาฟ: ภาคเรียน = ทีมของภาคเรียนนั้น (รวมคนที่ถูกปิดบัญชีภายหลัง) · ทั้งปี = สตาฟที่มีงานในช่วงนั้น
$staffWhere = $selTerm
    ? "u.user_id IN (SELECT ts.user_id FROM term_staff ts WHERE ts.term_id = " . (int)$selTerm['id'] . ")"
    : "u.role = 'staff'";
$staffPerfStmt = $conn->prepare(
    "SELECT u.user_id, u.first_name, u.last_name, " . term_score_sql($rangeTerm) . " AS score,
            COUNT(t.id) AS total_assigned,
            SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) AS completed_cnt,
            SUM(CASE WHEN t.status IN ('accepted', 'awaiting_intake_sign', 'in_progress', 'inspected_ready') THEN 1 ELSE 0 END) AS active_cnt,
            ROUND(AVG(CASE WHEN t.status = 'completed' AND t.accepted_at IS NOT NULL AND t.completed_at IS NOT NULL
                THEN TIMESTAMPDIFF(MINUTE, t.accepted_at, t.completed_at) END), 0) AS avg_minutes
     FROM users u
     LEFT JOIN tickets t ON t.assigned_to = u.user_id AND t.created_at BETWEEN ? AND ?
     WHERE {$staffWhere}
     GROUP BY u.user_id
     " . ($selTerm ? '' : 'HAVING total_assigned > 0') . "
     ORDER BY completed_cnt DESC, score DESC"
);
$staffPerfStmt->bind_param('ss', $startDate, $endDate);
$staffPerfStmt->execute();
$staffStats = $staffPerfStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$staffPerfStmt->close();

// 3. แนวโน้มรายเดือนในช่วงที่เลือก
$monthlyLabels = [];
$monthlyCreated = [];
$monthlyCompleted = [];
$thaiShortMonths = [1=>'ม.ค.',2=>'ก.พ.',3=>'มี.ค.',4=>'เม.ย.',5=>'พ.ค.',6=>'มิ.ย.',7=>'ก.ค.',8=>'ส.ค.',9=>'ก.ย.',10=>'ต.ค.',11=>'พ.ย.',12=>'ธ.ค.'];
$monthCursor = strtotime(date('Y-m-01', strtotime($startDate)));
$monthLast = strtotime(date('Y-m-01', strtotime($endDate)));
$mStmt = $conn->prepare(
    "SELECT COUNT(*) AS created_cnt, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS done_cnt
       FROM tickets WHERE created_at BETWEEN ? AND ?"
);
for ($guard = 0; $monthCursor <= $monthLast && $guard < 24; $guard++, $monthCursor = strtotime('+1 month', $monthCursor)) {
    $mIdx = (int)date('n', $monthCursor);
    $monthlyLabels[] = $thaiShortMonths[$mIdx] . ' ' . substr((string)((int)date('Y', $monthCursor) + 543), -2);
    $from = max($startDate, date('Y-m-01 00:00:00', $monthCursor));
    $to = min($endDate, date('Y-m-t 23:59:59', $monthCursor));
    $mStmt->bind_param('ss', $from, $to);
    $mStmt->execute();
    $mRow = $mStmt->get_result()->fetch_assoc();
    $monthlyCreated[] = (int)($mRow['created_cnt'] ?? 0);
    $monthlyCompleted[] = (int)($mRow['done_cnt'] ?? 0);
}
$mStmt->close();

// เตรียมข้อมูล JSON สำหรับ Chart.js
$chartStaffNames = array_map(fn($s) => $s['first_name'], $staffStats);
$chartStaffDone  = array_map(fn($s) => (int)$s['completed_cnt'], $staffStats);
$chartStaffActive = array_map(fn($s) => (int)$s['active_cnt'], $staffStats);
$chartStaffScore = array_map(fn($s) => (int)$s['score'], $staffStats);

function h(mixed $v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<?php $adminShell = current_role() === 'admin'; ?>
<?php if ($adminShell):
    $page_title = 'รายงานปีการศึกษา';
    $current_page = 'teacher_report';
    ob_start(); ?>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Kanit', 'sans-serif'], kanit: ['Kanit', 'sans-serif'], mono: ['Kanit', 'sans-serif'] } } } };</script>
<?= app_theme_css($conn) ?>
<style>
        body { background: #FAFAF8; color: #161616; font-family: 'Kanit', sans-serif; }
        .tray { padding: 6px; border: 1px solid #EAE8E4; border-radius: 20px; background: #F7F6F4; }
        .tray-card { border: 1px solid #ECEAE6; border-radius: 14px; background: #FFFFFF; }
        @media print { .no-print { display: none !important; } body { background: #FFF; } }
    </style>
<?php $admin_extra_head = ob_get_clean();
    require __DIR__ . '/admin_header.php';
else: ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานสรุป<?= h($periodLabel) ?> | <?= h($siteName) ?></title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo/reps-icon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Kanit', 'sans-serif'], kanit: ['Kanit', 'sans-serif'], mono: ['Kanit', 'sans-serif'] } } } };</script>
    <?= app_theme_css($conn) ?>
    <style>
        body { background: #FAFAF8; color: #161616; font-family: 'Kanit', sans-serif; }
        .tray { padding: 6px; border: 1px solid #EAE8E4; border-radius: 20px; background: #F7F6F4; }
        .tray-card { border: 1px solid #ECEAE6; border-radius: 14px; background: #FFFFFF; }
        @media print { .no-print { display: none !important; } body { background: #FFF; } }
    </style>
   <link rel="stylesheet" href="../assets/css/base.css?v=18">
</head>
<body class="min-h-screen flex flex-col antialiased">

    <header class="no-print sticky top-0 z-30 h-16 border-b border-[#ECEAE6] bg-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-7 h-full flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="admin_dashboard.php"><img src="../assets/images/logo/reps-logo.svg" alt="REPS" class="h-6 w-auto"></a>
                <span class="text-neutral-300">|</span>
                <span class="font-mono text-xs font-semibold uppercase tracking-wider text-neutral-800">ACADEMIC YEAR REPORT</span>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <a href="admin_war_room.php" class="px-3 py-1.5 rounded-lg border border-neutral-200 bg-white hover:bg-neutral-50">War Room</a>
                <a href="admin_dashboard.php" class="px-3 py-1.5 rounded-lg border border-neutral-200 bg-white hover:bg-neutral-50">แดชบอร์ดหลัก</a>
                <button onclick="window.print()" class="admin-btn px-3 py-1.5 rounded-lg text-white cursor-pointer"><i class="fa-solid fa-print mr-1"></i> พิมพ์รายงาน</button>
            </div>
        </div>
    </header>
<?php endif; ?>

    <main class="<?= $adminShell ? 'w-full space-y-6' : 'flex-1 max-w-6xl w-full mx-auto px-4 sm:px-7 py-7 space-y-6' ?>">

        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-normal tracking-tight">รายงานสรุป<?= h($periodLabel) ?></h1>
                <p class="text-xs text-[#6B6862] mt-1">
                    ช่วงเวลา: <?= date('d/m/', strtotime($startDate)) . ((int)date('Y', strtotime($startDate)) + 543) ?> ถึง <?= date('d/m/', strtotime($endDate)) . ((int)date('Y', strtotime($endDate)) + 543) ?> · ประเมินผลปฏิบัติงานฝ่ายซ่อมรายบุคคล<?= $selTerm ? ' · คะแนนนับเฉพาะภาคเรียนนี้' : '' ?>
                </p>
            </div>

            <form method="GET" class="no-print flex flex-wrap items-center gap-2 bg-white p-2 rounded-xl border border-[#EAE8E4] text-xs">
                <?php if ($allTerms): ?>
                    <label class="text-neutral-500 pl-2" for="termSel">ช่วงรายงาน:</label>
                    <select id="termSel" name="term" onchange="this.form.submit()" class="rounded-lg border border-neutral-300 px-3 py-1.5 font-semibold bg-white">
                        <?php foreach ($allTerms as $t): ?>
                            <option value="<?= (int)$t['id'] ?>" <?= $selTerm && (int)$selTerm['id'] === (int)$t['id'] ? 'selected' : '' ?>><?= h(term_title($t)) ?></option>
                        <?php endforeach; ?>
                        <option value="all" <?= !$selTerm ? 'selected' : '' ?>>ทั้งปีการศึกษา</option>
                    </select>
                <?php endif; ?>
                <?php if (!$selTerm): ?>
                    <label class="text-neutral-500 pl-2" for="yearSel">ปีการศึกษา:</label>
                    <select id="yearSel" name="acad_year" onchange="this.form.submit()" class="rounded-lg border border-neutral-300 px-3 py-1.5 font-mono font-semibold bg-white">
                        <?php for ($y = $currentAcadYearBE; $y >= $currentAcadYearBE - 4; $y--): ?>
                            <option value="<?= $y ?>" <?= $selectedAcadYearBE === $y ? 'selected' : '' ?>>ปีการศึกษา <?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                <?php endif; ?>
            </form>
        </div>

        <!-- KPI Summary Cards -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="tray"><div class="tray-card p-4">
                <span class="text-xs text-neutral-500">ใบแจ้งซ่อมทั้งหมด</span>
                <div class="font-mono text-3xl font-semibold mt-2"><?= number_format((int)$kpi['total_tickets']) ?></div>
            </div></div>
            <div class="tray"><div class="tray-card p-4">
                <span class="text-xs text-emerald-700">ซ่อมเสร็จสิ้น</span>
                <div class="font-mono text-3xl font-semibold text-emerald-700 mt-2"><?= number_format((int)$kpi['completed_tickets']) ?></div>
            </div></div>
            <div class="tray"><div class="tray-card p-4">
                <span class="text-xs text-blue-700">กำลังดำเนินการ</span>
                <div class="font-mono text-3xl font-semibold text-blue-700 mt-2"><?= number_format((int)$kpi['in_progress_tickets']) ?></div>
            </div></div>
            <div class="tray"><div class="tray-card p-4">
                <span class="text-xs text-amber-700">รอรับเรื่อง</span>
                <div class="font-mono text-3xl font-semibold text-amber-700 mt-2"><?= number_format((int)$kpi['pending_tickets']) ?></div>
            </div></div>
        </section>

        <!-- กราฟเปรียบเทียบผลงานสตาฟ & แนวโน้มรายเดือน -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="tray">
                <div class="px-4 py-2 text-xs font-semibold text-neutral-800">กราฟเปรียบเทียบผลงานสตาฟแต่ละคน (<?= h($periodLabel) ?>)</div>
                <div class="tray-card p-4">
                    <canvas id="staffBarChart" height="220"></canvas>
                </div>
            </div>

            <div class="tray">
                <div class="px-4 py-2 text-xs font-semibold text-neutral-800">สถิติการแจ้งซ่อมและปิดงานรายเดือน (<?= h($periodLabel) ?>)</div>
                <div class="tray-card p-4">
                    <canvas id="monthlyLineChart" height="220"></canvas>
                </div>
            </div>
        </section>

        <!-- ตารางประเมินผลงานสตาฟแต่ละคน -->
        <section class="tray">
            <div class="px-4 py-2.5 text-xs font-semibold text-neutral-800">ตารางสรุปผลการปฏิบัติงานและคะแนนสะสมของสตาฟรายบุคคล</div>
            <div class="tray-card overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse" data-pager id="staffStats">
                    <thead>
                        <tr class="border-b border-neutral-200 bg-[#FAFAF8] text-neutral-500">
                            <th class="py-3 px-4">ชื่อ-นามสกุล สตาฟ</th>
                            <th class="py-3 px-4 text-center">รับงานทั้งหมด</th>
                            <th class="py-3 px-4 text-center">เสร็จสิ้น</th>
                            <th class="py-3 px-4 text-center">ค้างดำเนินการ</th>
                            <th class="py-3 px-4 text-center">เวลาซ่อมเฉลี่ย</th>
                            <th class="py-3 px-4 text-right">คะแนนวินัยคิว</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <?php foreach ($staffStats as $st): ?>
                            <tr class="hover:bg-neutral-50">
                                <td class="py-3 px-4 font-medium text-neutral-900"><?= h($st['first_name'] . ' ' . $st['last_name']) ?></td>
                                <td class="py-3 px-4 text-center font-mono"><?= (int)$st['total_assigned'] ?></td>
                                <td class="py-3 px-4 text-center font-mono text-emerald-700 font-semibold"><?= (int)$st['completed_cnt'] ?></td>
                                <td class="py-3 px-4 text-center font-mono text-amber-600"><?= (int)$st['active_cnt'] ?></td>
                                <td class="py-3 px-4 text-center font-mono text-neutral-600"><?= $st['avg_minutes'] ? (int)$st['avg_minutes'] . ' นาที' : '—' ?></td>
                                <td class="py-3 px-4 text-right font-mono font-semibold"><?= (int)$st['score'] ?> pt</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>

    <script>
    // กราฟวาดบน canvas จึงไม่ใช้ฟอนต์จาก CSS: ตั้ง Kanit ให้ Chart.js และรอโหลดฟอนต์ก่อนวาด
    Chart.defaults.font.family = "'Kanit', sans-serif";
    Chart.defaults.font.size = 13;
    Chart.defaults.color = '#3F3D39';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.tooltip.titleFont = { family: "'Kanit', sans-serif", size: 13, weight: '500' };
    Chart.defaults.plugins.tooltip.bodyFont = { family: "'Kanit', sans-serif", size: 13 };
    function drawReportCharts() {
        new Chart(document.getElementById('staffBarChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartStaffNames, JSON_UNESCAPED_UNICODE) ?>,
                datasets: [
                    { label: 'ปิดงานเสร็จสิ้น', data: <?= json_encode($chartStaffDone) ?>, backgroundColor: '#161616', borderRadius: 6 },
                    { label: 'กำลังทำ (ค้าง)', data: <?= json_encode($chartStaffActive) ?>, backgroundColor: '#D97706', borderRadius: 6 }
                ]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });

        new Chart(document.getElementById('monthlyLineChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($monthlyLabels, JSON_UNESCAPED_UNICODE) ?>,
                datasets: [
                    { label: 'แจ้งซ่อมทั้งหมด', data: <?= json_encode($monthlyCreated) ?>, borderColor: '#6B6862', backgroundColor: 'rgba(142,139,133,0.1)', tension: 0.3, fill: true },
                    { label: 'ซ่อมเสร็จสิ้น', data: <?= json_encode($monthlyCompleted) ?>, borderColor: '#12A150', backgroundColor: 'rgba(18,161,80,0.1)', tension: 0.3, fill: true }
                ]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
    }
    Promise.all([document.fonts.load("400 13px Kanit"), document.fonts.load("500 13px Kanit")])
        .catch(function () {})
        .then(drawReportCharts);
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