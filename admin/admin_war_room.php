<?php
declare(strict_types=1);
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * REPS · admin/admin_war_room.php
 * หน้าภาพรวม War Room: ใครทำอะไรบ้าง / งานค้างกึ่งงาน / กรองเดือน (อิงจากวันที่รับงาน accepted_at)
 */

require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/user_auth.php';
require_once dirname(__DIR__) . '/includes/site_config.php';
require_once dirname(__DIR__) . '/includes/queue_manager.php';

// อนุญาตเฉพาะ teacher และ admin
$actorId = app_require_active_user($conn, '../login.php?next=admin', ['teacher', 'admin']);
$siteName = app_site_name($conn);

queue_process_expired_tickets($conn);

// ตัวกรองเดือน/ปี (ค่าเริ่มต้น = เดือนปัจจุบัน รูปแบบ YYYY-MM, หรือ 'all' เพื่อดูทั้งหมด)
$selectedMonth = trim((string)($_GET['month'] ?? date('Y-m')));
if ($selectedMonth !== 'all' && !preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}
$selectedStaff = filter_input(INPUT_GET, 'staff_id', FILTER_VALIDATE_INT) ?: 0;
$selectedStatus = trim((string)($_GET['status'] ?? ''));

// สร้างรายการตัวเลือกเดือนย้อนหลัง 12 เดือน
$monthOptions = ['all' => 'ทุกเดือน (ทั้งหมด)'];
$thaiMonths = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
];
for ($i = 0; $i < 12; $i++) {
    $ts = strtotime("-{$i} months");
    $ym = date('Y-m', $ts);
    $mNum = (int)date('n', $ts);
    $yThai = (int)date('Y', $ts) + 543;
    $monthOptions[$ym] = "{$thaiMonths[$mNum]} {$yThai}";
}

// ดึงรายชื่อสตาฟทั้งหมด + สรุปผลงานตามเดือนที่เลือก (อิงจาก accepted_at)
$staffListRes = $conn->query("SELECT user_id, first_name, last_name, score, is_active_queue FROM users WHERE role = 'staff' AND status = 'active' ORDER BY queue_order ASC, user_id ASC");
$staffMembers = $staffListRes ? $staffListRes->fetch_all(MYSQLI_ASSOC) : [];

// Query งานค้างกึ่งงานทั้งหมด (รับแล้ว in_progress แต่ยังไม่เสร็จ — แสดงเด่นชัดเสมอไม่ว่าจะข้ามเดือนหรือไม่)
$stuckSql = "SELECT t.*, c.name AS category_name,
                    s.first_name AS staff_first, s.last_name AS staff_last,
                    u.first_name AS req_first, u.last_name AS req_last
             FROM tickets t
             LEFT JOIN categories c ON c.id = t.category_id
             LEFT JOIN users s ON s.user_id = t.assigned_to
             JOIN users u ON u.user_id = t.requester_id
             WHERE t.status = 'in_progress'
             ORDER BY t.accepted_at ASC";
$stuckRes = $conn->query($stuckSql);
$stuckTickets = $stuckRes ? $stuckRes->fetch_all(MYSQLI_ASSOC) : [];

// Query รายการงานตามตัวกรองเดือน (อิงจากวันที่รับงาน accepted_at)
$where = ["t.assigned_to IS NOT NULL"];
$types = '';
$params = [];

if ($selectedMonth !== 'all') {
    $where[] = "DATE_FORMAT(t.accepted_at, '%Y-%m') = ?";
    $types .= 's';
    $params[] = $selectedMonth;
}
if ($selectedStaff > 0) {
    $where[] = "t.assigned_to = ?";
    $types .= 'i';
    $params[] = $selectedStaff;
}
if (in_array($selectedStatus, ['in_progress', 'completed', 'cancelled'], true)) {
    $where[] = "t.status = ?";
    $types .= 's';
    $params[] = $selectedStatus;
}

$whereSql = implode(' AND ', $where);
$jobsSql = "SELECT t.*, c.name AS category_name,
                   s.first_name AS staff_first, s.last_name AS staff_last
            FROM tickets t
            LEFT JOIN categories c ON c.id = t.category_id
            LEFT JOIN users s ON s.user_id = t.assigned_to
            WHERE {$whereSql}
            ORDER BY t.accepted_at DESC";
$stmt = $conn->prepare($jobsSql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$filteredJobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// สรุปตัวเลขรายบุคคลจาก $filteredJobs
$summaryByStaff = [];
foreach ($staffMembers as $sm) {
    $sid = (int)$sm['user_id'];
    $summaryByStaff[$sid] = [
        'info' => $sm,
        'total' => 0,
        'in_progress' => 0,
        'completed' => 0,
    ];
}
foreach ($filteredJobs as $fj) {
    $sid = (int)$fj['assigned_to'];
    if (isset($summaryByStaff[$sid])) {
        $summaryByStaff[$sid]['total']++;
        if ($fj['status'] === 'in_progress') $summaryByStaff[$sid]['in_progress']++;
        if ($fj['status'] === 'completed') $summaryByStaff[$sid]['completed']++;
    }
}


?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>War Room ภาพรวมงานสตาฟ | <?= h($siteName) ?></title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo/reps-icon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500;600&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { kanit: ['Kanit', 'sans-serif'], mono: ['JetBrains Mono', 'monospace'] } } } };</script>
    <?= app_theme_css($conn) ?>
    <style>
        body { background: #FAFAF8; color: #161616; font-family: 'Kanit', 'Inter', sans-serif; }
        .tray { padding: 6px; border: 1px solid #EAE8E4; border-radius: 20px; background: #F7F6F4; }
        .tray-card { border: 1px solid #ECEAE6; border-radius: 14px; background: #FFFFFF; }
    </style>
</head>
<body class="min-h-screen flex flex-col antialiased">

    <header class="sticky top-0 z-30 h-16 border-b border-[#ECEAE6] bg-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-7 h-full flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="admin_dashboard.php"><img src="../assets/images/logo/reps-logo.svg" alt="REPS" class="h-6 w-auto"></a>
                <span class="text-neutral-300">|</span>
                <span class="font-mono text-xs font-semibold uppercase tracking-wider text-neutral-800">WAR ROOM · MONITOR</span>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <a href="admin_dashboard.php" class="px-3 py-1.5 rounded-lg border border-neutral-200 bg-white hover:bg-neutral-50">แดชบอร์ดหลัก</a>
                <a href="admin_teacher_report.php" class="px-3 py-1.5 rounded-lg border border-neutral-200 bg-white hover:bg-neutral-50">รายงานประจำปี & กราฟ</a>
                <a href="admin_inventory.php" class="px-3 py-1.5 rounded-lg border border-neutral-200 bg-white hover:bg-neutral-50">คลังสต็อก</a>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-6xl w-full mx-auto px-4 sm:px-7 py-7 space-y-6">

        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-normal tracking-tight">War Room ติดตามงานฝ่ายซ่อม</h1>
                <p class="text-xs sm:text-sm text-[#8E8B85] mt-1">ตรวจสอบว่าใครกำลังทำอะไรอยู่ งานค้างสะสม และคัดกรองตามเดือนที่รับงาน</p>
            </div>

            <!-- ตัวกรองเดือน / สตาฟ / สถานะ -->
            <form method="GET" class="flex flex-wrap items-center gap-2 bg-white p-2.5 rounded-2xl border border-[#EAE8E4] shadow-xs text-xs">
                <select name="month" class="rounded-lg border border-neutral-300 px-3 py-2 bg-white">
                    <?php foreach ($monthOptions as $val => $lbl): ?>
                        <option value="<?= h($val) ?>" <?= $selectedMonth === $val ? 'selected' : '' ?>>เดือนรับงาน: <?= h($lbl) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="staff_id" class="rounded-lg border border-neutral-300 px-3 py-2 bg-white">
                    <option value="0">สตาฟ: ทุกคน</option>
                    <?php foreach ($staffMembers as $sm): ?>
                        <option value="<?= (int)$sm['user_id'] ?>" <?= $selectedStaff === (int)$sm['user_id'] ? 'selected' : '' ?>>
                            <?= h($sm['first_name'] . ' ' . $sm['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" class="rounded-lg border border-neutral-300 px-3 py-2 bg-white">
                    <option value="">สถานะ: ทั้งหมด</option>
                    <option value="in_progress" <?= $selectedStatus === 'in_progress' ? 'selected' : '' ?>>กำลังดำเนินการ (ค้าง)</option>
                    <option value="completed" <?= $selectedStatus === 'completed' ? 'selected' : '' ?>>เสร็จสิ้นแล้ว</option>
                </select>

                <button type="submit" class="admin-btn px-4 py-2 rounded-lg text-white font-medium cursor-pointer">กรองข้อมูล</button>
            </form>
        </div>

        <!-- Section 1: งานค้างกึ่งงาน (รับแล้วแต่ยังไม่เสร็จ — เด่นชัดเสมอ) -->
        <section class="tray border-amber-300 bg-amber-50/40">
            <div class="px-4 py-2.5 flex items-center justify-between text-xs">
                <span class="font-semibold text-amber-950 flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-amber-600"></i> งานค้างกึ่งงานทั้งหมดในระบบ (รับเรื่องแล้ว แต่ยังไม่ปิดงาน: <?= count($stuckTickets) ?> เคส)
                </span>
                <span class="text-amber-800 text-[11px]">แสดงงานค้างทั้งหมดรวมงานข้ามเดือน</span>
            </div>
            <div class="tray-card p-4">
                <?php if (empty($stuckTickets)): ?>
                    <p class="text-xs text-emerald-700 text-center py-4"><i class="fa-solid fa-circle-check mr-1"></i> เยี่ยมมาก! ขณะนี้ไม่มีงานค้างกึ่งงานในระบบ</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="border-b border-neutral-200 text-neutral-500">
                                    <th class="py-2.5 px-3">เลขใบงาน</th>
                                    <th class="py-2.5 px-3">ผู้รับผิดชอบ (สตาฟ)</th>
                                    <th class="py-2.5 px-3">สถานที่ / อาการ</th>
                                    <th class="py-2.5 px-3">วันที่รับงาน</th>
                                    <th class="py-2.5 px-3">ระยะเวลาค้าง</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100">
                                <?php foreach ($stuckTickets as $st):
                                    $daysStuck = $st['accepted_at'] ? (int)floor((time() - strtotime($st['accepted_at'])) / 86400) : 0;
                                ?>
                                    <tr class="hover:bg-amber-50/30">
                                        <td class="py-2.5 px-3 font-mono font-semibold">#<?= h($st['ticket_no']) ?></td>
                                        <td class="py-2.5 px-3 font-medium text-neutral-900"><?= h($st['staff_first'] . ' ' . $st['staff_last']) ?></td>
                                        <td class="py-2.5 px-3">
                                            <div class="font-medium"><?= h($st['location_note']) ?></div>
                                            <div class="text-neutral-500 line-clamp-1"><?= h($st['symptom']) ?></div>
                                        </td>
                                        <td class="py-2.5 px-3 font-mono text-neutral-600"><?= $st['accepted_at'] ? date('d/m/Y H:i', strtotime($st['accepted_at'])) : '—' ?></td>
                                        <td class="py-2.5 px-3">
                                            <span class="px-2 py-0.5 rounded-full font-mono text-[11px] <?= $daysStuck >= 3 ? 'bg-rose-100 text-rose-800 font-semibold' : 'bg-amber-100 text-amber-800' ?>">
                                                <?= $daysStuck === 0 ? 'วันนี้' : "ค้าง {$daysStuck} วัน" ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Section 2: สรุปผลงานรายบุคคลตามเดือนที่เลือก -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($summaryByStaff as $sid => $data):
                $info = $data['info'];
            ?>
                <div class="tray">
                    <div class="tray-card p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <div class="text-sm font-semibold text-neutral-900"><?= h($info['first_name'] . ' ' . $info['last_name']) ?></div>
                                <div class="text-[11px] text-neutral-500">
                                    <?= (int)$info['is_active_queue'] === 1 ? '🟢 เปิดรับคิวอยู่' : '⚪ ปิดรับคิว' ?>
                                </div>
                            </div>
                            <span class="font-mono text-xs font-semibold px-2.5 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200">
                                <?= (int)$info['score'] ?> pt
                            </span>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-center pt-2 border-t border-neutral-100">
                            <div>
                                <div class="text-[10px] text-neutral-400">รับงานเดือนนี้</div>
                                <div class="font-mono text-lg font-semibold"><?= $data['total'] ?></div>
                            </div>
                            <div>
                                <div class="text-[10px] text-amber-600">กำลังทำ (ค้าง)</div>
                                <div class="font-mono text-lg font-semibold text-amber-600"><?= $data['in_progress'] ?></div>
                            </div>
                            <div>
                                <div class="text-[10px] text-emerald-600">ปิดงานแล้ว</div>
                                <div class="font-mono text-lg font-semibold text-emerald-600"><?= $data['completed'] ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <!-- Section 3: รายการงานทั้งหมดตามตัวกรองเดือน -->
        <section class="tray">
            <div class="px-4 py-2.5 flex items-center justify-between text-xs">
                <span class="font-semibold text-neutral-800">รายละเอียดใบงานตามเดือนที่รับงาน (<?= count($filteredJobs) ?> รายการ)</span>
                <span class="font-mono text-[11px] text-neutral-500"><?= h($monthOptions[$selectedMonth] ?? $selectedMonth) ?></span>
            </div>
            <div class="tray-card overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-neutral-200 bg-[#FAFAF8] text-neutral-500">
                            <th class="py-3 px-4">เลขใบงาน</th>
                            <th class="py-3 px-4">สตาฟผู้รับผิดชอบ</th>
                            <th class="py-3 px-4">รายละเอียดงาน</th>
                            <th class="py-3 px-4">วันที่รับงาน</th>
                            <th class="py-3 px-4">วันที่เสร็จสิ้น</th>
                            <th class="py-3 px-4">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <?php if (empty($filteredJobs)): ?>
                            <tr><td colspan="6" class="py-8 text-center text-neutral-400">ไม่พบรายการงานในเงื่อนไขที่เลือก</td></tr>
                        <?php else: ?>
                            <?php foreach ($filteredJobs as $job): ?>
                                <tr class="hover:bg-neutral-50/70">
                                    <td class="py-3 px-4 font-mono font-semibold">#<?= h($job['ticket_no']) ?></td>
                                    <td class="py-3 px-4 font-medium"><?= h($job['staff_first'] . ' ' . $job['staff_last']) ?></td>
                                    <td class="py-3 px-4">
                                        <div class="font-medium text-neutral-900"><?= h($job['category_name']) ?> (<?= h($job['location_note']) ?>)</div>
                                        <div class="text-neutral-500 line-clamp-1"><?= h($job['symptom']) ?></div>
                                    </td>
                                    <td class="py-3 px-4 font-mono text-neutral-600"><?= $job['accepted_at'] ? date('d/m/Y H:i', strtotime($job['accepted_at'])) : '—' ?></td>
                                    <td class="py-3 px-4 font-mono text-neutral-600"><?= $job['completed_at'] ? date('d/m/Y H:i', strtotime($job['completed_at'])) : '—' ?></td>
                                    <td class="py-3 px-4">
                                        <?php if ($job['status'] === 'completed'): ?>
                                            <span class="px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px]">เสร็จสิ้น</span>
                                        <?php elseif ($job['status'] === 'in_progress'): ?>
                                            <span class="px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200 text-[11px]">กำลังดำเนินการ</span>
                                        <?php else: ?>
                                            <span class="px-2.5 py-0.5 rounded-full bg-neutral-100 text-neutral-600 text-[11px]"><?= h($job['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>
</body>
</html>