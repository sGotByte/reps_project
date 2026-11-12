<?php
declare(strict_types=1);

/**
 * REPS · admin/admin_teacher_report.php
 * รายงานประจำปีการศึกษา (สำหรับครู/แอดมิน) + กราฟรายงานผลงานของสตาฟแต่ละคน
 */

require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/user_auth.php';
require_once dirname(__DIR__) . '/includes/site_config.php';

$actorId = app_require_active_user($conn, '../login.php?next=admin', ['teacher', 'admin']);
$siteName = app_site_name($conn);
$settings = app_get_settings($conn);
$startMonth = max(1, min(12, (int)($settings['academic_year_start_month'] ?? 5))); // ค่าเริ่มต้นเดือน 5 (พฤษภาคม)

// คำนวณปีการศึกษาไทยปัจจุบัน (พ.ศ.)
$currentYearAD = (int)date('Y');
$currentMonth  = (int)date('n');
$currentAcadYearBE = ($currentMonth >= $startMonth ? $currentYearAD : $currentYearAD - 1) + 543;

$selectedAcadYearBE = filter_input(INPUT_GET, 'acad_year', FILTER_VALIDATE_INT) ?: $currentAcadYearBE;
$startYearAD = $selectedAcadYearBE - 543;
$endYearAD   = $startYearAD + 1;

// ช่วงวันที่ของปีการศึกษาที่เลือก (เช่น 2026-05-01 00:00:00 ถึง 2027-04-30 23:59:59)
$startDate = sprintf('%04d-%02d-01 00:00:00', $startYearAD, $startMonth);
$endTs = strtotime("+1 year -1 second", strtotime($startDate));
$endDate = date('Y-m-d H:i:s', $endTs);

// 1. สรุปภาพรวมทั้งปีการศึกษา
$kpiStmt = $conn->prepare(
    "SELECT
        COUNT(*) AS total_tickets,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_tickets,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_tickets,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_tickets
     FROM tickets
     WHERE created_at BETWEEN ? AND ?"
);
$kpiStmt->bind_param('ss', $startDate, $endDate);
$kpiStmt->execute();
$kpi = $kpiStmt->get_result()->fetch_assoc();
$kpiStmt->close();

// 2. ข้อมูลเปรียบเทียบสตาฟแต่ละคนในปีการศึกษานี้
$staffPerfStmt = $conn->prepare(
    "SELECT u.user_id, u.first_name, u.last_name, u.score,
            COUNT(t.id) AS total_assigned,
            SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) AS completed_cnt,
            SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) AS active_cnt,
            ROUND(AVG(CASE WHEN t.status = 'completed' AND t.accepted_at IS NOT NULL AND t.completed_at IS NOT NULL
                THEN TIMESTAMPDIFF(MINUTE, t.accepted_at, t.completed_at) END), 0) AS avg_minutes
     FROM users u
     LEFT JOIN tickets t ON t.assigned_to = u.user_id AND t.created_at BETWEEN ? AND ?
     WHERE u.role = 'staff' AND u.status = 'active'
     GROUP BY u.user_id
     ORDER BY completed_cnt DESC, u.score DESC"
);
$staffPerfStmt->bind_param('ss', $startDate, $endDate);
$staffPerfStmt->execute();
$staffStats = $staffPerfStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$staffPerfStmt->close();

// 3. แนวโน้มรายเดือนตลอดปีการศึกษา (12 เดือน)
$monthlyLabels = [];
$monthlyCreated = [];
$monthlyCompleted = [];
$thaiShortMonths = [1=>'ม.ค.',2=>'ก.พ.',3=>'มี.ค.',4=>'เม.ย.',5=>'พ.ค.',6=>'มิ.ย.',7=>'ก.ค.',8=>'ส.ค.',9=>'ก.ย.',10=>'ต.ค.',11=>'พ.ย.',12=>'ธ.ค.'];

for ($mOffset = 0; $mOffset < 12; $mOffset++) {
    $mTs = strtotime("+{$mOffset} months", strtotime($startDate));
    $ym = date('Y-m', $mTs);
    $mIdx = (int)date('n', $mTs);
    $yShort = substr((string)((int)date('Y', $mTs) + 543), -2);
    $monthlyLabels[] = $thaiShortMonths[$mIdx] . ' ' . $yShort;

    $mStmt = $conn->prepare(
        "SELECT
            COUNT(*) AS created_cnt,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS done_cnt
         FROM tickets
         WHERE DATE_FORMAT(created_at, '%Y-%m') = ?"
    );
    $mStmt->bind_param('s', $ym);
    $mStmt->execute();
    $mRow = $mStmt->get_result()->fetch_assoc();
    $mStmt->close();

    $monthlyCreated[] = (int)($mRow['created_cnt'] ?? 0);
    $monthlyCompleted[] = (int)($mRow['done_cnt'] ?? 0);
}

// เตรียมข้อมูล JSON สำหรับ Chart.js
$chartStaffNames = array_map(fn($s) => $s['first_name'], $staffStats);
$chartStaffDone  = array_map(fn($s) => (int)$s['completed_cnt'], $staffStats);
$chartStaffActive = array_map(fn($s) => (int)$s['active_cnt'], $staffStats);
$chartStaffScore = array_map(fn($s) => (int)$s['score'], $staffStats);

function h(mixed $v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานประจำปีการศึกษา <?= $selectedAcadYearBE ?> | <?= h($siteName) ?></title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo/reps-icon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500;600&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { kanit: ['Kanit', 'sans-serif'], mono: ['JetBrains Mono', 'monospace'] } } } };</script>
    <?= app_theme_css($conn) ?>
    <style>
        body { background: #FAFAF8; color: #161616; font-family: 'Kanit', 'Inter', sans-serif; }
        .tray { padding: 6px; border: 1px solid #EAE8E4; border-radius: 20px; background: #F7F6F4; }
        .tray-card { border: 1px solid #ECEAE6; border-radius: 14px; background: #FFFFFF; }
        @media print { .no-print { display: none !important; } body { background: #FFF; } }
    </style>
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

    <main class="flex-1 max-w-6xl w-full mx-auto px-4 sm:px-7 py-7 space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-normal tracking-tight">รายงานสรุปประจำปีการศึกษา <?= $selectedAcadYearBE ?></h1>
                <p class="text-xs text-[#8E8B85] mt-1">
                    ช่วงเวลา: <?= date('d/m/Y', strtotime($startDate)) ?> ถึง <?= date('d/m/Y', $endTs) ?> · ประเมินผลปฏิบัติงานฝ่ายซ่อมรายบุคคล
                </p>
            </div>

            <form method="GET" class="no-print flex items-center gap-2 bg-white p-2 rounded-xl border border-[#EAE8E4] text-xs">
                <label class="text-neutral-500 pl-2">ปีการศึกษา:</label>
                <select name="acad_year" onchange="this.form.submit()" class="rounded-lg border border-neutral-300 px-3 py-1.5 font-mono font-semibold bg-white">
                    <?php for ($y = $currentAcadYearBE; $y >= $currentAcadYearBE - 4; $y--): ?>
                        <option value="<?= $y ?>" <?= $selectedAcadYearBE === $y ? 'selected' : '' ?>>ปีการศึกษา <?= $y ?></option>
                    <?php endfor; ?>
                </select>
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
                <div class="px-4 py-2 text-xs font-semibold text-neutral-800">กราฟเปรียบเทียบผลงานสตาฟแต่ละคน (ปีการศึกษา <?= $selectedAcadYearBE ?>)</div>
                <div class="tray-card p-4">
                    <canvas id="staffBarChart" height="220"></canvas>
                </div>
            </div>

            <div class="tray">
                <div class="px-4 py-2 text-xs font-semibold text-neutral-800">สถิติการแจ้งซ่อมและปิดงานรายเดือนตลอดปีการศึกษา</div>
                <div class="tray-card p-4">
                    <canvas id="monthlyLineChart" height="220"></canvas>
                </div>
            </div>
        </section>

        <!-- ตารางประเมินผลงานสตาฟแต่ละคน -->
        <section class="tray">
            <div class="px-4 py-2.5 text-xs font-semibold text-neutral-800">ตารางสรุปผลการปฏิบัติงานและคะแนนสะสมของสตาฟรายบุคคล</div>
            <div class="tray-card overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
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
                    { label: 'แจ้งซ่อมทั้งหมด', data: <?= json_encode($monthlyCreated) ?>, borderColor: '#8E8B85', backgroundColor: 'rgba(142,139,133,0.1)', tension: 0.3, fill: true },
                    { label: 'ซ่อมเสร็จสิ้น', data: <?= json_encode($monthlyCompleted) ?>, borderColor: '#12A150', backgroundColor: 'rgba(18,161,80,0.1)', tension: 0.3, fill: true }
                ]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
    </script>
</body>
</html>