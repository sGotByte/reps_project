<<<<<<< HEAD
<?php
$page_title = 'แดชบอร์ด';
$current_page = 'dashboard';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/terms.php';

if (session_status() === PHP_SESSION_NONE) {
   session_start();
}

// 1. ดึงสถิติภาพรวมจากตาราง tickets
$stats = [
   'total' => 0,
   'completed' => 0,
   'pending' => 0,
   'urgent_pending' => 0,
   'in_progress' => 0,
   'closed_today' => 0,
   'avg_minutes' => 15
];

$q_stat = "SELECT 
       COUNT(*) AS total,
       SUM(status = 'completed') AS completed,
       SUM(status = 'pending') AS pending,
       SUM(status = 'pending' AND urgency = 'urgent') AS urgent_pending,
       SUM(status IN ('accepted', 'awaiting_intake_sign', 'in_progress', 'inspected_ready')) AS in_progress,
       SUM(status = 'completed' AND DATE(completed_at) = CURDATE()) AS closed_today,
       AVG(CASE WHEN accepted_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, accepted_at) END) AS avg_minutes
   FROM tickets";
$res_stat = $conn->query($q_stat);
if ($res_stat && $r = $res_stat->fetch_assoc()) {
   $stats['total'] = (int)($r['total'] ?? 0);
   $stats['completed'] = (int)($r['completed'] ?? 0);
   $stats['pending'] = (int)($r['pending'] ?? 0);
   $stats['urgent_pending'] = (int)($r['urgent_pending'] ?? 0);
   $stats['in_progress'] = (int)($r['in_progress'] ?? 0);
   $stats['closed_today'] = (int)($r['closed_today'] ?? 0);
   $stats['avg_minutes'] = $r['avg_minutes'] !== null ? round((float)$r['avg_minutes']) : 0;
}
$completed_percent = $stats['total'] > 0 ? round(($stats['completed'] / $stats['total']) * 100, 1) : 0;

// ===== แนวโน้มคำขอ: 7 วัน / 12 เดือน / 5 ปี =====
$monthsList = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
$thaiDays = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'];
$thaiMonthsFull = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
$thaiDaysFull = ['วันอาทิตย์', 'วันจันทร์', 'วันอังคาร', 'วันพุธ', 'วันพฤหัสบดี', 'วันศุกร์', 'วันเสาร์'];

// วันที่ปัจจุบัน & เวลาล็อกอินเข้าระบบล่าสุด
$todayThaiText = $thaiDaysFull[(int)date('w')] . 'ที่ ' . (int)date('j') . ' ' . $thaiMonthsFull[(int)date('n') - 1] . ' ' . ((int)date('Y') + 543);
$lastLoginRaw = $_SESSION['last_login'] ?? $_SESSION['login_time'] ?? null;
if (!$lastLoginRaw && !empty($_SESSION['user_id'])) {
   $uid = (int)$_SESSION['user_id'];
   $chkCol = $conn->query("SHOW COLUMNS FROM users LIKE 'last_login'");
   if ($chkCol && $chkCol->num_rows > 0) {
      $resLogin = $conn->query("SELECT last_login FROM users WHERE user_id = {$uid} LIMIT 1");
      if ($resLogin && $rowLogin = $resLogin->fetch_assoc()) {
         $lastLoginRaw = $rowLogin['last_login'] ?? null;
      }
   }
}
$lastLoginTs = $lastLoginRaw ? strtotime((string)$lastLoginRaw) : time();
$lastLoginThaiText = (int)date('j', $lastLoginTs) . ' ' . $monthsList[(int)date('n', $lastLoginTs) - 1] . ' ' . ((int)date('Y', $lastLoginTs) + 543) . ' เวลา ' . date('H:i', $lastLoginTs) . ' น.';

$pixelRows = 20;
$subFactors = [0.3, 0.55, 1, 0.6, 0.35];

function trend_fetch_map(mysqli $conn, string $sql): array
{
   $map = [];
   $res = $conn->query($sql);
   if ($res) {
      while ($row = $res->fetch_assoc()) {
         $map[(string)$row['b']] = [
            'total'  => (int)$row['total'],
            'urgent' => (int)$row['urgent'],
            'normal' => (int)$row['normal'],
            'low'    => (int)$row['low'],
         ];
      }
      $res->free();
   }
   return $map;
}

function trend_prepare_range(array $buckets, int $pixelRows, array $subFactors): array
{
   $total = 0;
   $max = 1;
   foreach ($buckets as $b) {
      $total += (int)$b['count'];
      $max = max($max, (int)$b['count']);
   }
   $scaleMax = max(4, (int)ceil($max / 4) * 4);

   $pixels = [];
   foreach ($buckets as $b) {
      $count = (int)$b['count'];
      $urgent = min((int)$b['urgent'], $count);
      $normal = min((int)$b['normal'], max(0, $count - $urgent));
      $low = min((int)$b['low'], max(0, $count - $urgent - $normal));

      $cols = [];
      foreach ($subFactors as $f) {
         $uH = $urgent > 0 ? max(1, (int)round(($urgent / $scaleMax) * $pixelRows * $f)) : 0;
         $nH = $normal > 0 ? max(1, (int)round(($normal / $scaleMax) * $pixelRows * $f)) : 0;
         $lH = $low > 0 ? max(1, (int)round(($low / $scaleMax) * $pixelRows * $f)) : 0;
         $cols[] = ['u' => $uH, 'n' => $nH, 'l' => $lH];
      }

      $pixels[] = array_merge($b, [
         'count'  => $count,
         'urgent' => $urgent,
         'normal' => $normal,
         'low'    => $low,
         'p'      => round($count / $scaleMax, 3),
         'cols'   => $cols,
      ]);
   }

   return [
      'buckets' => $pixels,
      'total'   => $total,
      'max'     => $max,
      'scale'   => $scaleMax,
      'ticks'   => [$scaleMax, (int)($scaleMax * 0.75), (int)($scaleMax * 0.5), (int)($scaleMax * 0.25), 0],
   ];
}

// 12 เดือนล่าสุด
$requestTrend = [];
$firstOfMonth = strtotime(date('Y-m-01'));
for ($i = 11; $i >= 0; $i--) {
   $ts = strtotime("-$i months", $firstOfMonth);
   $mKey = date('Y-m', $ts);
   $requestTrend[$mKey] = [
      'label' => $monthsList[(int)date('n', $ts) - 1],
      'full'  => $thaiMonthsFull[(int)date('n', $ts) - 1] . ' ' . ((int)date('Y', $ts) + 543),
      'count' => 0,
      'urgent_users' => 0,
      'normal_users' => 0,
      'low_users'    => 0,
   ];
}

$trendQuery = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS m_key,
                                   COUNT(*) AS request_count,
                                   SUM(urgency = 'urgent') AS urgent_count,
                                   SUM(urgency = 'normal') AS normal_count,
                                   SUM(urgency = 'low') AS low_count
                            FROM tickets
                            WHERE created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)
                            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                            ORDER BY m_key");
if ($trendQuery) {
   while ($trendRow = $trendQuery->fetch_assoc()) {
      $mKey = (string)$trendRow['m_key'];
      if (array_key_exists($mKey, $requestTrend)) {
         $requestTrend[$mKey]['count'] = (int)$trendRow['request_count'];
         $requestTrend[$mKey]['urgent_users'] = (int)$trendRow['urgent_count'];
         $requestTrend[$mKey]['normal_users'] = (int)$trendRow['normal_count'];
         $requestTrend[$mKey]['low_users'] = (int)$trendRow['low_count'];
      }
   }
   $trendQuery->free();
}

$monthlyBuckets = [];
foreach ($requestTrend as $mKey => $tData) {
   $monthlyBuckets[] = [
      'key'    => $mKey,
      'label'  => $tData['label'],
      'full'   => $tData['full'],
      'count'  => (int)$tData['count'],
      'urgent' => (int)$tData['urgent_users'],
      'normal' => (int)$tData['normal_users'],
      'low'    => (int)$tData['low_users'],
   ];
}

// 7 วันล่าสุด (รวมวันนี้)
$sevenMap = trend_fetch_map($conn, "SELECT DATE(created_at) AS b,
                                          COUNT(*) AS total,
                                          SUM(urgency = 'urgent') AS urgent,
                                          SUM(urgency = 'normal') AS normal,
                                          SUM(urgency = 'low') AS low
                                   FROM tickets
                                   WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                                   GROUP BY DATE(created_at)");
$sevenBuckets = [];
for ($i = 6; $i >= 0; $i--) {
   $ts = strtotime("-$i days");
   $key = date('Y-m-d', $ts);
   $c = $sevenMap[$key] ?? ['total' => 0, 'urgent' => 0, 'normal' => 0, 'low' => 0];
   $sevenBuckets[] = [
      'key'    => $key,
      'label'  => $thaiDays[(int)date('w', $ts)] . ' ' . date('j', $ts),
      'full'   => $thaiDays[(int)date('w', $ts)] . ' ' . date('j', $ts) . ' ' . $monthsList[(int)date('n', $ts) - 1] . ' ' . ((int)date('Y', $ts) + 543),
      'count'  => (int)$c['total'],
      'urgent' => (int)$c['urgent'],
      'normal' => (int)$c['normal'],
      'low'    => (int)$c['low'],
   ];
}

// 5 ปีล่าสุด
$yearMap = trend_fetch_map($conn, "SELECT YEAR(created_at) AS b,
                                         COUNT(*) AS total,
                                         SUM(urgency = 'urgent') AS urgent,
                                         SUM(urgency = 'normal') AS normal,
                                         SUM(urgency = 'low') AS low
                                  FROM tickets
                                  WHERE YEAR(created_at) >= YEAR(CURDATE()) - 4
                                  GROUP BY YEAR(created_at)");
$yearlyBuckets = [];
$currentYear = (int)date('Y');
for ($y = $currentYear - 4; $y <= $currentYear; $y++) {
   $c = $yearMap[(string)$y] ?? ['total' => 0, 'urgent' => 0, 'normal' => 0, 'low' => 0];
   $yearlyBuckets[] = [
      'key'    => (string)$y,
      'label'  => (string)($y + 543),
      'full'   => 'ปี ' . ($y + 543),
      'count'  => (int)$c['total'],
      'urgent' => (int)$c['urgent'],
      'normal' => (int)$c['normal'],
      'low'    => (int)$c['low'],
   ];
}

$trendRanges = [
   '7d' => array_merge(trend_prepare_range($sevenBuckets, $pixelRows, $subFactors), [
      'short' => '7D',
      'foot'  => 'จำนวนคำขอแจ้งซ่อมรายวัน (เลื่อนเมาส์เพื่อดูรายละเอียด)',
      'unit'  => 'ต่อวัน',
   ]),
   'monthly' => array_merge(trend_prepare_range($monthlyBuckets, $pixelRows, $subFactors), [
      'short' => 'Monthly',
      'foot'  => 'จำนวนคำขอแจ้งซ่อมรายเดือน (เลื่อนเมาส์เพื่อดูรายละเอียด)',
      'unit'  => 'ต่อเดือน',
   ]),
   'yearly' => array_merge(trend_prepare_range($yearlyBuckets, $pixelRows, $subFactors), [
      'short' => 'Yearly',
      'foot'  => 'จำนวนคำขอแจ้งซ่อมรายปี (เลื่อนเมาส์เพื่อดูรายละเอียด)',
      'unit'  => 'ต่อปี',
   ]),
];

$trendTotal = $trendRanges['monthly']['total'];
$trendMax = $trendRanges['monthly']['max'];

// sparkline 12 เดือน แยกตามสถานะ (ใช้กับการ์ด KPI)
$spark = ['total' => [], 'pending' => [], 'in_progress' => [], 'closed' => []];
foreach (array_keys($requestTrend) as $k) {
   foreach ($spark as $n => $_) {
      $spark[$n][$k] = 0;
   }
}
$q_spark = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS m_key,
                                COUNT(*) AS total,
                                SUM(status = 'pending') AS pending,
                                SUM(status IN ('accepted', 'awaiting_intake_sign', 'in_progress', 'inspected_ready')) AS in_progress,
                                SUM(status = 'completed') AS closed
                         FROM tickets
                         WHERE created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)
                         GROUP BY DATE_FORMAT(created_at, '%Y-%m')");
if ($q_spark) {
   while ($s = $q_spark->fetch_assoc()) {
      $k = (string)$s['m_key'];
      if (!isset($spark['total'][$k])) continue;
      $spark['total'][$k]       = (int)$s['total'];
      $spark['pending'][$k]     = (int)$s['pending'];
      $spark['in_progress'][$k] = (int)$s['in_progress'];
      $spark['closed'][$k]      = (int)$s['closed'];
   }
   $q_spark->free();
}

function spark_bars(array $series)
{
   $max = max(1, max($series));
   $n = count($series);
   $i = 0;
   $html = '';
   foreach ($series as $k => $v) {
      $i++;
      $hgt = $v > 0 ? max(16, round($v / $max * 100)) : 8;
      $html .= '<i class="' . ($i === $n ? 'on' : '') . '" style="height:' . $hgt . '%" title="' . htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') . ' · ' . $v . '"></i>';
   }
   return $html;
}

// ข้อมูลการ์ด KPI
$kpis = [
   [
      'label' => 'คำขอทั้งหมด',
      'value' => $stats['total'],
      'spark' => 'total',
      'href'  => null,
      'note'  => '<b class="up">' . $completed_percent . '%</b> ปิดงานสำเร็จ',
   ],
   [
      'label' => 'รอรับเรื่อง',
      'value' => $stats['pending'],
      'spark' => 'pending',
      'href'  => 'admin_manage_request.php?status=pending',
      'note'  => $stats['urgent_pending'] > 0
         ? '<b class="down">เคสด่วน ' . $stats['urgent_pending'] . '</b>'
         : 'ไม่มีเคสด่วน',
   ],
   [
      'label' => 'กำลังดำเนินการ',
      'value' => $stats['in_progress'],
      'spark' => 'in_progress',
      'href'  => 'admin_manage_request.php?status=working',
      'note'  => 'สตาฟกำลังซ่อม',
   ],
   [
      'label' => 'เสร็จสิ้นวันนี้',
      'value' => $stats['closed_today'],
      'spark' => 'closed',
      'href'  => null,
      'note'  => 'เข้าช่วยเฉลี่ย <b class="ink">' . $stats['avg_minutes'] . '</b> นาที',
   ],
];

// 2. ดึงสัดส่วนหมวดหมู่งานซ่อม
$cat_counts = [];
$q_cats = "SELECT c.id, c.name, COUNT(t.id) AS count
              FROM categories c
              LEFT JOIN tickets t ON t.category_id = c.id
              GROUP BY c.id, c.name
              ORDER BY count DESC";
$res_cats = $conn->query($q_cats);
if ($res_cats) {
   $cat_counts = $res_cats->fetch_all(MYSQLI_ASSOC);
}

// 4. Donut: แสดง 4 หมวดแรก + รวมที่เหลือเป็น "อื่น ๆ"
$donut_src = array_slice($cat_counts, 0, 4);
$rest = array_slice($cat_counts, 4);
if ($rest) {
   $donut_src[] = ['id' => 0, 'name' => 'อื่น ๆ', 'count' => array_sum(array_column($rest, 'count'))];
}

$circumference = 238.8;           // 2πr, r = 38
$seg_gap = count($donut_src) > 1 ? 2.5 : 0;
$donut_colors = ['#161616', '#6B6B68', '#A8A6A1', '#CFCDC8', '#E6E4E0'];
$donut_segments = [];
$current_offset = 0;

foreach ($donut_src as $i => $c) {
   $c_count = (int)$c['count'];
   $ratio = $stats['total'] > 0 ? $c_count / $stats['total'] : 0;
   $len = $ratio * $circumference;
   $donut_segments[] = [
      'name'   => $c['name'],
      'count'  => $c_count,
      'pct'    => round($ratio * 100),
      'dash'   => $c_count > 0 ? round(max(0.5, $len - $seg_gap), 1) : 0,
      'offset' => -round($current_offset, 1),
      'color'  => $donut_colors[$i % count($donut_colors)],
   ];
   $current_offset += $len;
}

// 5. ดึงคำขอล่าสุด 5 รายการ
$recent_tickets = [];
$q_recent = "SELECT 
       t.id AS ticket_id, t.ticket_no, t.location_note AS location_name, NULL AS sub_category, t.symptom AS symptoms, t.urgency, t.status, t.created_at,
       u.first_name, u.last_name, d.name AS department_name,
       c.name AS category_name,
       CONCAT(tech.first_name, ' ', tech.last_name) AS tech_name
   FROM tickets t
   JOIN users u ON u.user_id = t.requester_id
   LEFT JOIN department d ON d.d_id = u.department_id
   JOIN categories c ON c.id = t.category_id
   LEFT JOIN users tech ON tech.user_id = t.assigned_to
   ORDER BY t.created_at DESC
   LIMIT 5";
$res_recent = $conn->query($q_recent);
if ($res_recent) {
   $recent_tickets = $res_recent->fetch_all(MYSQLI_ASSOC);
}

// 6. ภาพรวม Staff แต่ละคน + คิวรับงาน
$staff_list = [];
$dashTerm = term_current($conn); // เฉพาะทีมและงานของภาคเรียนปัจจุบัน
$q_staff = "SELECT u.user_id, u.first_name, u.last_name, u.is_active_queue, u.queue_order, " . term_score_sql($dashTerm) . " AS score,
       COUNT(t.id) AS total_assigned,
       COALESCE(SUM(t.status IN ('accepted', 'awaiting_intake_sign', 'in_progress', 'inspected_ready')), 0) AS in_progress,
       COALESCE(SUM(t.status = 'completed' AND DATE(t.completed_at) = CURDATE()), 0) AS done_today,
       COALESCE(SUM(t.status = 'completed' AND DATE_FORMAT(t.completed_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')), 0) AS done_month,
       COALESCE(SUM(t.status = 'completed'), 0) AS done_total,
       AVG(CASE WHEN t.accepted_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.accepted_at) END) AS avg_minutes,
       MAX(t.accepted_at) AS last_accepted
   FROM users u
   LEFT JOIN tickets t ON t.assigned_to = u.user_id" . term_date_sql($dashTerm, 't.created_at') . "
   WHERE u.role = 'staff' AND u.status = 'active'" . term_staff_sql($conn, 'u.user_id', $dashTerm) . "
   GROUP BY u.user_id, u.first_name, u.last_name, u.is_active_queue, u.queue_order";
$res_staff = $conn->query($q_staff);
if ($res_staff) {
   $staff_list = $res_staff->fetch_all(MYSQLI_ASSOC);
}

// งานที่กำลังเรียกให้ Staff รับอยู่ตอนนี้ (ยังไม่หมดเวลา)
$offers = [];
$res_offer = $conn->query("SELECT queue_assigned_to AS uid,
                                  COUNT(*) AS cnt,
                                  MIN(TIMESTAMPDIFF(SECOND, NOW(), queue_expires_at)) AS secs_left
                           FROM tickets
                           WHERE status = 'pending'
                             AND queue_assigned_to IS NOT NULL
                             AND queue_expires_at > NOW()
                           GROUP BY queue_assigned_to");
if ($res_offer) {
   while ($o = $res_offer->fetch_assoc()) {
      $offers[(int)$o['uid']] = ['cnt' => (int)$o['cnt'], 'secs' => max(0, (int)$o['secs_left'])];
   }
   $res_offer->free();
}

// เรียงตามคิว: คนเปิดรับคิวก่อน → queue_order น้อยไปมาก (NULL ท้ายสุด) → user_id
usort($staff_list, function ($a, $b) {
   $qa = (int)$a['is_active_queue'];
   $qb = (int)$b['is_active_queue'];
   if ($qa !== $qb) return $qb <=> $qa;
   $oa = $a['queue_order'] !== null ? (int)$a['queue_order'] : PHP_INT_MAX;
   $ob = $b['queue_order'] !== null ? (int)$b['queue_order'] : PHP_INT_MAX;
   if ($oa !== $ob) return $oa <=> $ob;
   return (int)$a['user_id'] <=> (int)$b['user_id'];
});

$queue_pos = 0;
$current_offer = null; // คนที่กำลังถูกเรียกรับงานอยู่
foreach ($staff_list as $i => $s) {
   $staff_list[$i]['queue_pos'] = (int)$s['is_active_queue'] === 1 ? ++$queue_pos : 0;
   $staff_list[$i]['offer'] = $offers[(int)$s['user_id']] ?? null;
   if ($staff_list[$i]['offer'] && ($current_offer === null || $staff_list[$i]['offer']['secs'] < $current_offer['offer']['secs'])) {
      $current_offer = $staff_list[$i];
   }
}
$next_staff = ($staff_list && $staff_list[0]['queue_pos'] === 1) ? $staff_list[0] : null;
$staff_in_queue = $queue_pos;

function h($v)
{
   return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function time_ago($datetime)
{
   $time = strtotime($datetime);
   $diff = time() - $time;
   if ($diff < 60) return 'เมื่อสักครู่';
   if ($diff < 3600) return round($diff / 60) . ' นาทีที่แล้ว';
   if ($diff < 86400) return round($diff / 3600) . ' ชม. ที่แล้ว';
   return date('d/m H:i', $time);
}

$statusLabels = [
   'pending'     => 'รอรับเรื่อง',
   'pending' => 'รอรับเรื่อง', 'accepted' => 'รับแล้ว รอบันทึกเช็คลิสต์', 'awaiting_intake_sign' => 'รอผู้แจ้งเซ็นรับทราบ',
   'in_progress' => 'กำลังซ่อม', 'inspected_ready' => 'ตรวจซ้ำแล้ว รอส่งมอบ', 'awaiting_requester_sign' => 'รอผู้แจ้งเซ็นรับคืน',
   'awaiting_admin_sign' => 'รอครูรับรอง', 'disputed' => 'ผู้แจ้งไม่ยอมรับ',
   'completed'   => 'เสร็จสิ้น',
   'cancelled'   => 'ยกเลิก'
];

require_once __DIR__ . '/admin_header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<style>
   .dash {
      --tray: var(--admin-tray);
      --tray-line: var(--admin-tray-line);
      --card-bg: var(--admin-card);
      --card-line: var(--admin-line);
      --ink: var(--admin-ink);
      --muted: var(--admin-muted);
      --pix-off: #F1EFEC;
      --c-urgent: #161616;
      --c-normal: #6B6862;
      --c-low: #C9C6C0;
      font-family: 'Kanit', sans-serif;
   }

   .dash .mono {
      font-family: 'Kanit', sans-serif;
   }

   .dash .welcome-title {
      font-size: 26px;
      font-weight: 400;
      color: var(--ink);
      letter-spacing: -0.02em;
      line-height: 1.25;
   }

   .dash .login-meta {
      display: inline-flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 6px;
      margin-top: 4px;
      font-size: 13px;
      color: var(--muted);
   }

   .dash .btn-ink {
      background: var(--ink);
      color: #fff;
      border-radius: 10px;
   }

   /* ===== KPI card: tray เทา + การ์ดขาวด้านใน + footer บน tray ===== */
   .dash .kcard {
      min-width: 0;
      background: var(--tray);
      border: 1px solid var(--tray-line);
      border-radius: 16px;
      padding: 4px;
      display: flex;
      flex-direction: column;
      text-decoration: none;
      transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
   }

   .dash a.kcard:hover {
      border-color: #D9D6D0;
      box-shadow: 0 8px 22px rgba(0, 0, 0, .07);
      transform: translateY(-1px);
   }

   .dash .kcard-body {
      flex: 1;
      display: flex;
      align-items: stretch;
      justify-content: space-between;
      gap: 10px;
      min-height: 104px;
      padding: 16px 18px;
      background: var(--card-bg);
      border: 1px solid var(--card-line);
      border-radius: 12px;
      box-shadow: 0 1px 2px rgba(0, 0, 0, .03);
   }

   .dash .kcard-left {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      gap: 14px;
   }

   .dash .kcard-label {
      font-family: 'Kanit', sans-serif;
      font-size: 12px;
      font-weight: 400;
      letter-spacing: .06em;
      text-transform: uppercase;
      color: #55534F;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
   }

   .dash .kcard-numrow {
      display: flex;
      align-items: baseline;
      gap: 8px;
      min-width: 0;
   }

   .dash .kcard-num {
      font-family: 'Kanit', sans-serif;
      font-size: 32px;
      font-weight: 500;
      letter-spacing: -.02em;
      line-height: 1;
      color: var(--ink);
   }

   .dash .kcard-unit {
      font-size: 12.5px;
      color: var(--muted);
      white-space: nowrap;
   }

   .dash .kcard-bars {
      display: flex;
      align-items: flex-end;
      gap: 3px;
      height: 44px;
      flex: none;
      align-self: center;
   }

   .dash .kcard-bars i {
      display: block;
      width: 2px;
      min-height: 6px;
      border-radius: 2px;
      background: #E4E2DE;
   }

   .dash .kcard-bars i.on {
      width: 3px;
      background: var(--ink);
   }

   .dash .kcard-foot {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      padding: 10px 12px 7px;
   }

   .dash .kcard-dot {
      width: 18px;
      height: 18px;
      border-radius: 50%;
      background: #C9C6C0;
      color: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex: none;
   }

   .dash .kcard-note {
      min-width: 0;
      font-size: 12px;
      color: var(--muted);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
   }

   .dash .kcard-note b {
      font-family: 'Kanit', sans-serif;
      font-weight: 600;
   }

   .dash .kcard-note b.up { color: #12A150; }
   .dash .kcard-note b.down { color: #DC2626; }
   .dash .kcard-note b.ink { color: var(--ink); }

   /* การ์ดขาวทั่วไป (ตาราง) */
   .dash .trend-card {
      background: var(--card-bg);
      border: 1px solid var(--card-line);
      border-radius: 16px;
      padding: 24px;
   }

   /* ===== Tray: กรอบเทาด้านนอก + การ์ดขาวด้านใน ===== */
   .dash .tray {
      background: var(--tray);
      border: 1px solid var(--tray-line);
      border-radius: 20px;
      padding: 6px;
      display: flex;
      flex-direction: column;
   }

   .dash .tray-label {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 10px 14px 12px;
      font-family: 'Kanit', sans-serif;
      font-size: 12px;
      letter-spacing: .06em;
      text-transform: uppercase;
      color: var(--muted);
   }

   .dash .tray-card {
      background: var(--card-bg);
      border: 1px solid var(--card-line);
      border-radius: 14px;
      padding: 20px 22px 12px;
      flex: 1;
   }

   .dash .tray-foot {
      display: flex;
      justify-content: space-between;
      padding: 10px 14px 6px;
      font-size: 12px;
      color: var(--muted);
   }

   .dash .legend-dot {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-family: 'Kanit', sans-serif;
      font-size: 11.5px;
      letter-spacing: .06em;
      text-transform: uppercase;
      color: #55534F;
   }

   .dash .legend-dot::before {
      content: '';
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: var(--c);
   }

   .dash .seg-toggle {
      display: inline-flex;
      background: var(--tray);
      border-radius: 999px;
      padding: 3px;
      font-size: 12px;
   }

   .dash .seg-toggle button {
      padding: 5px 14px;
      border: 0;
      border-radius: 999px;
      background: transparent;
      color: var(--muted);
      font: inherit;
      font-size: 12px;
      font-weight: 500;
      line-height: 1.5;
      cursor: pointer;
      transition: color .15s ease, background-color .15s ease, box-shadow .15s ease;
   }

   .dash .seg-toggle button:hover {
      color: var(--ink);
   }

   .dash .seg-toggle button.act {
      background: #fff;
      color: var(--ink);
      box-shadow: 0 1px 3px rgba(0, 0, 0, .08);
   }

   .dash .seg-toggle button:focus-visible {
      outline: 2px solid var(--ink);
      outline-offset: 2px;
   }

   /* ===== สลับช่วงเวลา ===== */
   .dash .trend-pane {
      display: none;
   }

   .dash .trend-pane.is-active {
      display: block;
      animation: trendFade .22s ease-out;
   }

   @keyframes trendFade {
      from {
         opacity: 0;
         transform: translateY(3px);
      }

      to {
         opacity: 1;
         transform: translateY(0);
      }
   }

   @media (prefers-reduced-motion: reduce) {
      .dash .trend-pane.is-active {
         animation: none;
      }
   }

   /* ===== Dense pixel chart ===== */
   .dash .pix-wrap {
      display: grid;
      grid-template-columns: 44px 1fr;
      gap: 8px;
   }

   .dash .pix-y {
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding-bottom: 32px;
      font-family: 'Kanit', sans-serif;
      font-size: 12px;
      color: var(--muted);
   }

   .dash .pix-y span {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 8px;
      line-height: 1;
   }

   .dash .pix-y span::after {
      content: '';
      width: 3px;
      height: 3px;
      border-radius: 50%;
      background: #BDBAB4;
   }

   .dash .pix-cols {
      display: flex;
      gap: 2px;
   }

   .dash .pix-month {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      cursor: default;
   }

   .dash .pix-grid {
      position: relative;
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 2px;
   }

   .dash .pix-c {
      display: flex;
      flex-direction: column-reverse;
      gap: 2px;
   }

   .dash .pix-c b {
      display: block;
      aspect-ratio: 1;
      border-radius: 2px;
      background: var(--pix-off);
   }

   .dash .pix-c b.n { background: var(--c-normal); }
   .dash .pix-c b.l { background: var(--c-low); }
   .dash .pix-c b.u { background: var(--c-urgent); }

   .dash .pix-x {
      position: relative;
      height: 32px;
      padding-top: 12px;
      text-align: center;
      font-family: 'Kanit', sans-serif;
      font-size: 12px;
      letter-spacing: .03em;
      color: var(--muted);
   }

   .dash .pix-month:not(:last-child) .pix-x::after {
      content: '';
      position: absolute;
      right: -2px;
      top: 17px;
      width: 3px;
      height: 3px;
      border-radius: 50%;
      background: #C9C6C0;
   }

   .dash .pix-x.active,
   .dash .pix-month:hover .pix-x {
      color: var(--ink);
      font-weight: 700;
   }

   /* เส้นประ + จุด + tooltip ตอน hover */
   .dash .pix-line {
      display: none;
      position: absolute;
      left: 50%;
      top: 0;
      bottom: 0;
      border-left: 1px dotted #7A7873;
      pointer-events: none;
      z-index: 2;
   }

   .dash .pix-dot {
      position: absolute;
      left: -5px;
      bottom: calc(var(--p) * 100% - 4px);
      width: 9px;
      height: 9px;
      border-radius: 50%;
      background: var(--ink);
      border: 2px solid #fff;
      box-shadow: 0 0 0 1px #D8D5CF;
   }

   .dash .pix-tip {
      display: none;
      position: absolute;
      left: calc(50% + 14px);
      top: clamp(0px, calc((1 - var(--p)) * 100% - 10px), 55%);
      z-index: 10;
      min-width: 150px;
      background: #fff;
      border: 1px solid var(--card-line);
      border-radius: 12px;
      padding: 6px;
      box-shadow: 0 10px 28px rgba(0, 0, 0, .09);
      pointer-events: none;
   }

   .dash .pix-month.flip .pix-tip {
      left: auto;
      right: calc(50% + 14px);
   }

   .dash .pix-month:hover .pix-line,
   .dash .pix-month:hover .pix-tip {
      display: block;
   }

   .dash .pix-tip .t-head {
      background: var(--tray);
      border-radius: 8px;
      padding: 7px 10px;
      font-size: 14px;
      color: var(--muted);
   }

   .dash .pix-tip .t-row {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px 10px 3px;
      font-size: 13px;
      color: #6B6964;
   }

   .dash .pix-tip .t-row::before {
      content: '';
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--c);
      flex: none;
   }

   .dash .pix-tip .t-row strong {
      margin-left: 2px;
      color: var(--ink);
      font-size: 15px;
      font-weight: 600;
   }

   /* ===== Donut ===== */
   .dash .donut-wrap {
      position: relative;
      width: 150px;
      height: 150px;
      margin: 4px auto 18px;
   }

   .dash .donut-wrap svg {
      width: 100%;
      height: 100%;
      transform: rotate(-90deg);
   }

   .dash .donut-wrap circle.seg {
      transition: opacity .2s ease;
   }

   .dash .donut-wrap:hover circle.seg { opacity: .55; }
   .dash .donut-wrap circle.seg:hover { opacity: 1; }

   .dash .donut-center {
      position: absolute;
      inset: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      pointer-events: none;
   }

   .dash .donut-center .num {
      font-family: 'Kanit', sans-serif;
      font-size: 24px;
      font-weight: 700;
      color: var(--ink);
      line-height: 1;
   }

   .dash .donut-center .cap {
      margin-top: 4px;
      font-family: 'Kanit', sans-serif;
      font-size: 11px;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: var(--muted);
   }

   .dash .donut-legend {
      display: flex;
      flex-direction: column;
      gap: 9px;
      padding-top: 14px;
      border-top: 1px solid var(--card-line);
   }

   .dash .donut-legend .row {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12.5px;
      color: #55534F;
   }

   .dash .donut-legend .row::before {
      content: '';
      width: 8px;
      height: 8px;
      border-radius: 2px;
      background: var(--c);
      box-shadow: inset 0 0 0 1px rgba(0, 0, 0, .06);
      flex: none;
   }

   .dash .donut-legend .name {
      flex: 1;
      min-width: 0;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
   }

   .dash .donut-legend .cnt {
      font-family: 'Kanit', sans-serif;
      font-size: 11.5px;
      color: var(--muted);
   }

   .dash .donut-legend .pct {
      min-width: 38px;
      text-align: right;
      font-family: 'Kanit', sans-serif;
      font-weight: 600;
      color: var(--ink);
   }

   /* ===== Recent Service Requests ===== */
   .dash .recent-card {
      padding: 20px 22px 8px;
   }

   .dash .recent-head {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      gap: 16px;
      padding-bottom: 16px;
   }

   .dash .recent-title {
      margin: 0;
      font-size: 16px;
      font-weight: 400;
      letter-spacing: -.01em;
      color: var(--ink);
   }

   .dash .recent-sub {
      margin-top: 3px;
      font-size: 12px;
      color: var(--muted);
   }

   .dash .recent-link {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 6px 11px;
      border: 1px solid var(--card-line);
      border-radius: 999px;
      background: #fff;
      font-size: 12px;
      font-weight: 500;
      color: #55534F;
      text-decoration: none;
      white-space: nowrap;
      transition: color .15s ease, border-color .15s ease;
   }

   .dash .recent-link:hover {
      color: var(--ink);
      border-color: var(--tray-line);
   }

   .dash .recent-link i {
      font-size: 11px;
      transition: transform .15s ease;
   }

   .dash .recent-link:hover i {
      transform: translateX(2px);
   }

   .dash .recent-table {
      width: 100%;
      min-width: 900px;
      border-collapse: separate;
      border-spacing: 0;
   }

   .dash .recent-table thead {
      background: transparent;
   }

   .dash .recent-table thead th {
      padding: 9px 12px;
      background: var(--admin-soft, #F7F6F4);
      font-family: 'Kanit', sans-serif;
      font-size: 11px;
      font-weight: 500;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: var(--muted);
      text-align: left;
      white-space: nowrap;
   }

   .dash .recent-table thead th:first-child {
      padding-left: 14px;
      border-top-left-radius: 999px;
      border-bottom-left-radius: 999px;
   }

   .dash .recent-table thead th:last-child {
      padding-right: 14px;
      text-align: right;
      border-top-right-radius: 999px;
      border-bottom-right-radius: 999px;
   }

   .dash .recent-table thead th.text-center {
      text-align: center;
   }

   .dash .recent-table tbody td {
      padding: 13px 12px;
      border-bottom: 1px solid #F1EFEC;
      vertical-align: middle;
   }

   .dash .recent-table tbody td:first-child {
      padding-left: 14px;
   }

   .dash .recent-table tbody td:last-child {
      padding-right: 14px;
   }

   .dash .recent-table tbody tr {
      transition: background-color .15s ease;
   }

   .dash .recent-table tbody tr:hover {
      background: #FBFAF8;
   }

   .dash .recent-table tbody tr:last-child td {
      border-bottom: 0;
   }

   .dash .recent-ticket {
      display: block;
      font-family: 'Kanit', sans-serif;
      font-size: 12px;
      font-weight: 500;
      color: var(--ink);
   }

   .dash .recent-when {
      display: flex;
      align-items: center;
      gap: 5px;
      margin-top: 3px;
      font-size: 11px;
      color: var(--muted);
   }

   .dash .recent-when i {
      font-size: 11px;
   }

   .dash .recent-person {
      display: flex;
      align-items: center;
      gap: 9px;
      min-width: 0;
   }

   .dash .recent-avatar {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: var(--tray);
      border: 1px solid var(--card-line);
      font-family: 'Kanit', sans-serif;
      font-size: 11px;
      font-weight: 600;
      color: #55534F;
      flex: none;
   }

   .dash .recent-name {
      font-size: 13px;
      font-weight: 500;
      color: var(--ink);
   }

   .dash .recent-dept {
      margin-top: 2px;
      font-size: 11.5px;
      color: var(--muted);
   }

   .dash .recent-loc {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 13px;
      font-weight: 500;
      color: var(--ink);
   }

   .dash .recent-loc i {
      font-size: 11px;
      color: #B9B5AE;
   }

   .dash .recent-sym {
      max-width: 260px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      font-size: 13px;
      font-weight: 500;
      color: var(--ink);
   }

   .dash .recent-cat {
      margin-top: 3px;
      font-family: 'Kanit', sans-serif;
      font-size: 11px;
      letter-spacing: .05em;
      text-transform: uppercase;
      color: var(--muted);
   }

   .dash .recent-chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 3px 9px;
      border: 1px solid var(--card-line);
      border-radius: 999px;
      background: #F7F6F4;
      font-size: 11.5px;
      font-weight: 500;
      line-height: 1.5;
      color: #55534F;
      white-space: nowrap;
   }

   .dash .recent-chip::before {
      content: '';
      width: 5px;
      height: 5px;
      border-radius: 50%;
      background: var(--c, #B9B5AE);
      flex: none;
   }

   .dash .recent-chip.is-urgent {
      background: var(--ink);
      border-color: var(--ink);
      color: #fff;
   }

   .dash .recent-chip.is-urgent::before {
      background: #fff;
   }

   .dash .recent-chip.is-low {
      background: transparent;
      color: var(--muted);
   }

   .dash .recent-chip.st-pending { --c: #D97706; }
   .dash .recent-chip.st-in_progress,
   .dash .recent-chip.st-accepted,
   .dash .recent-chip.st-awaiting_intake_sign,
   .dash .recent-chip.st-inspected_ready { --c: #2563EB; }
   .dash .recent-chip.st-awaiting_requester_sign,
   .dash .recent-chip.st-awaiting_admin_sign { --c: #0E7490; }
   .dash .recent-chip.st-disputed { --c: #B45309; }
   .dash .recent-chip.st-completed { --c: #12A150; }
   .dash .recent-chip.st-cancelled { --c: #6B6862; }

   .dash .recent-tech {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 12.5px;
      color: #55534F;
   }

   .dash .recent-tech i {
      font-size: 11px;
      color: #B9B5AE;
   }

   .dash .recent-waiting {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      color: var(--muted);
   }

   .dash .recent-waiting::before {
      content: '';
      width: 5px;
      height: 5px;
      border-radius: 50%;
      background: #D9D6D0;
   }

   .dash .recent-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 11px;
      border-radius: 8px;
      background: var(--ink);
      font-size: 12px;
      font-weight: 400;
      color: #fff;
      text-decoration: none;
      white-space: nowrap;
      transition: background-color .15s ease;
   }

   .dash .recent-btn:hover {
      background: #2A2927;
   }

   .dash .recent-btn i {
      font-size: 11px;
      transition: transform .15s ease;
   }

   .dash .recent-btn:hover i {
      transform: translateX(2px);
   }

   .dash .recent-btn:focus-visible {
      outline: 2px solid var(--ink);
      outline-offset: 2px;
   }

   .dash .recent-empty {
      padding: 44px 16px;
      text-align: center;
      color: var(--muted);
   }

   .dash .recent-empty i {
      display: block;
      margin-bottom: 10px;
      font-size: 20px;
      color: #D9D6D0;
   }

   .dash .recent-empty span {
      font-size: 12.5px;
   }

   /* ===== Staff Overview / Queue ===== */
   .dash .staff-next {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 14px 16px;
      margin-bottom: 14px;
      background: var(--ink);
      border-radius: 12px;
      color: #fff;
   }

   .dash .staff-next-avatar {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: #fff;
      color: var(--ink);
      font-family: 'Kanit', sans-serif;
      font-size: 15px;
      font-weight: 700;
      flex: none;
   }

   .dash .staff-next-cap {
      font-family: 'Kanit', sans-serif;
      font-size: 11px;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: #A8A6A1;
   }

   .dash .staff-next-name {
      margin-top: 2px;
      font-size: 16px;
      font-weight: 500;
   }

   .dash .staff-next-meta {
      margin-left: auto;
      text-align: right;
      font-size: 12px;
      color: #A8A6A1;
   }

   .dash .staff-next.is-empty {
      background: var(--tray);
      color: var(--muted);
   }

   .dash .queue-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 28px;
      height: 24px;
      padding: 0 8px;
      border-radius: 999px;
      border: 1px solid var(--card-line);
      background: #F7F6F4;
      font-family: 'Kanit', sans-serif;
      font-size: 11.5px;
      font-weight: 600;
      color: #55534F;
      white-space: nowrap;
   }

   .dash .queue-badge.is-next {
      background: var(--ink);
      border-color: var(--ink);
      color: #fff;
   }

   .dash .queue-badge.is-off {
      background: transparent;
      color: var(--muted);
      font-weight: 400;
   }

   .dash .queue-badge.is-offer {
      background: #FEF3C7;
      border-color: #FCD34D;
      color: #92400E;
   }

   .dash .staff-num {
      font-family: 'Kanit', sans-serif;
      font-size: 13px;
      font-weight: 600;
      color: var(--ink);
   }

   .dash .staff-num.zero {
      color: #C9C6C0;
      font-weight: 400;
   }

   .dash .staff-num.low-score {
      color: #DC2626;
   }

   .dash .staff-row-next td {
      background: #FBFAF8;
   }

   @media (max-width: 768px) {
      .dash .pix-wrap { grid-template-columns: 34px 1fr; }
      .dash .pix-x { font-size: 8px; letter-spacing: 0; }
      .dash .pix-month:not(:last-child) .pix-x::after { display: none; }

      .dash .recent-card { padding: 16px 14px 6px; }
      .dash .recent-head { align-items: flex-start; gap: 10px; }
      .dash .recent-sub { display: none; }
      .dash .recent-title { font-size: 15px; }
      .dash .recent-link { padding: 5px 10px; font-size: 11.5px; }
   }
</style>

<div class="dash">

   <!-- Breadcrumb + Page Header -->
   <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
      <div>
         <nav class="flex items-center gap-2 text-xs text-gray-500 mb-2 font-medium">
            <a href="admin_dashboard.php" class="hover:text-ink transition-colors"><?= tr('แดชบอร์ด', 'Dashboard') ?></a>
            <i class="fa-solid fa-chevron-right text-[8px]"></i>
            <span class="text-gray-900 font-normal"><?= tr('แดชบอร์ด', 'Overview') ?></span>
         </nav>
         <h1 class="welcome-title"><?= h($todayThaiText) ?></h1>
         <div class="login-meta">
            <i class="fa-regular fa-clock text-xs"></i>
            <span>ล็อกอินเข้าระบบล่าสุด: <?= h($lastLoginThaiText) ?></span>
         </div>
      </div>
      <a href="admin_manage_request.php" class="btn-ink inline-flex items-center gap-2 px-4 py-2 text-sm font-normal hover:bg-neutral-800 transition-colors">
         <i class="fa-solid fa-list-check text-xs"></i>
         <span><?= tr('จัดการคำขอทั้งหมด', 'Manage all requests') ?></span>
      </a>
   </div>

   <!-- แถวที่ 1: KPI 4 ใบ -->
   <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4 font-kanit">
      <?php foreach ($kpis as $k): ?>
         <<?= $k['href'] ? 'a href="' . h($k['href']) . '"' : 'div' ?> class="kcard">
            <div class="kcard-body">
               <div class="kcard-left">
                  <div class="kcard-label"><?= h($k['label']) ?></div>
                  <div class="kcard-numrow">
                     <span class="kcard-num"><?= number_format($k['value']) ?></span>
                     <span class="kcard-unit">เคส</span>
                  </div>
               </div>
               <span class="kcard-bars" role="img" aria-label="แนวโน้ม 12 เดือนล่าสุด">
                  <?= spark_bars($spark[$k['spark']]) ?>
               </span>
            </div>
            <div class="kcard-foot">
               <span class="kcard-dot" aria-hidden="true">
                  <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5" /><path d="M5 12l7-7 7 7" /></svg>
               </span>
               <span class="kcard-note"><?= $k['note'] ?></span>
            </div>
         </<?= $k['href'] ? 'a' : 'div' ?>>
      <?php endforeach; ?>
   </div>

   <!-- Trend chart + สัดส่วนหมวดหมู่ -->
   <div class="grid grid-cols-1 xl:grid-cols-4 gap-5 mb-6">

      <!-- REQUESTS TREND (tray + dense pixel chart) -->
      <div class="tray xl:col-span-3">
         <div class="tray-label">
            <span>แนวโน้มจำนวนคำขอ</span>
            <i class="fa-solid fa-circle-info text-gray-300 text-xs" title="จำนวนคำขอแจ้งซ่อม แยกตามช่วงเวลาและความเร่งด่วน"></i>
         </div>

         <div class="tray-card">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
               <div class="flex items-center gap-4 flex-wrap w-full">
                  <div class="flex items-baseline gap-3">
                     <span class="text-sm text-gray-500">รวมทั้งหมด</span>
                     <span class="mono text-2xl font-medium text-gray-900" id="trendTotal"><?= number_format($trendTotal) ?></span>
                  </div>
                  <div class="flex items-center gap-4 lg:ml-6">
                     <span class="legend-dot" style="--c:var(--c-normal)">ปกติ</span>
                     <span class="legend-dot" style="--c:var(--c-urgent)">ด่วน</span>
                  </div>
                  <div class="seg-toggle ml-auto self-start lg:self-auto" id="trendToggle" role="tablist" aria-label="ช่วงเวลาของกราฟ">
                     <button type="button" data-range="7d" role="tab" aria-selected="false" aria-controls="trendPane7d" tabindex="-1">7 วัน</button>
                     <button type="button" data-range="monthly" role="tab" aria-selected="true" aria-controls="trendPaneMonthly" class="act">รายเดือน</button>
                     <button type="button" data-range="yearly" role="tab" aria-selected="false" aria-controls="trendPaneYearly" tabindex="-1">รายปี</button>
                  </div>
               </div>
            </div>

            <?php foreach ($trendRanges as $rangeKey => $range): ?>
               <?php $paneId = 'trendPane' . ($rangeKey === '7d' ? '7d' : ucfirst($rangeKey)); ?>
               <div class="trend-pane <?= $rangeKey === 'monthly' ? 'is-active' : '' ?>"
                  id="<?= $paneId ?>"
                  role="tabpanel"
                  data-trend-pane="<?= h($rangeKey) ?>"
                  data-total="<?= number_format($range['total']) ?>"
                  data-max="<?= number_format($range['max']) ?>"
                  data-foot="<?= h($range['foot']) ?>"
                  data-unit="<?= h($range['unit']) ?>">

                  <div class="pix-wrap">
                     <div class="pix-y">
                        <?php foreach ($range['ticks'] as $tick): ?><span><?= number_format($tick) ?></span><?php endforeach; ?>
                     </div>

                     <div class="pix-cols">
                        <?php foreach ($range['buckets'] as $mi => $px): ?>
                           <?php
                           $flipFrom = max(0, count($range['buckets']) - 4);
                           $isCurrent = ($rangeKey === '7d' && $px['key'] === date('Y-m-d'))
                              || ($rangeKey === 'monthly' && $px['key'] === date('Y-m'))
                              || ($rangeKey === 'yearly' && $px['key'] === (string)date('Y'));
                           ?>
                           <div class="pix-month <?= $mi >= $flipFrom ? 'flip' : '' ?>">
                              <div class="pix-grid" style="--p: <?= $px['p'] ?>">
                                 <div class="pix-line"><span class="pix-dot"></span></div>
                                 <div class="pix-tip mono">
                                    <div class="t-head"><?= h($px['full']) ?></div>
                                    <div class="t-row" style="--c:var(--c-normal)">ปกติ <strong><?= number_format($px['normal']) ?></strong></div>
                                    <div class="t-row" style="--c:var(--c-urgent)">ด่วน <strong><?= number_format($px['urgent']) ?></strong></div>
                                 </div>
                                 <?php foreach ($px['cols'] as $col): ?>
                                    <div class="pix-c">
                                       <?php for ($r = 0; $r < $pixelRows; $r++): ?>
                                          <?php
                                          if ($r < $col['u']) $cls = 'u';
                                          elseif ($r < $col['u'] + $col['n']) $cls = 'n';
                                          elseif ($r < $col['u'] + $col['n'] + $col['l']) $cls = 'l';
                                          else $cls = '';
                                          ?>
                                          <b<?= $cls ? ' class="' . $cls . '"' : '' ?>></b>
                                       <?php endfor; ?>
                                    </div>
                                 <?php endforeach; ?>
                              </div>
                              <div class="pix-x <?= $isCurrent ? 'active' : '' ?>"><?= h($px['label']) ?></div>
                           </div>
                        <?php endforeach; ?>
                     </div>
                  </div>
               </div>
            <?php endforeach; ?>
         </div>

         <div class="tray-foot">
            <span id="trendFootLabel"><?= h($trendRanges['monthly']['foot']) ?></span>
            <span>สูงสุด <span id="trendMax"><?= number_format($trendMax) ?></span> <span id="trendMaxUnit">ต่อเดือน</span></span>
         </div>
      </div>

      <!-- Donut (tray style เหมือน Requests Trend) -->
      <div class="tray xl:col-span-1">
         <div class="tray-label">
            <span>สัดส่วนตามหมวดหมู่</span>
            <i class="fa-solid fa-circle-info text-gray-300 text-xs" title="สัดส่วนคำขอแยกตามหมวดหมู่งานซ่อม"></i>
         </div>

         <div class="tray-card flex flex-col justify-center">
            <div class="donut-wrap">
               <svg viewBox="0 0 100 100" role="img" aria-label="สัดส่วนหมวดหมู่งานซ่อม">
                  <circle cx="50" cy="50" r="38" fill="none" stroke="#F1EFEC" stroke-width="10" />
                  <?php foreach ($donut_segments as $seg): ?>
                     <?php if ($seg['dash'] > 0): ?>
                        <circle class="seg" cx="50" cy="50" r="38" fill="none"
                           stroke="<?= $seg['color'] ?>"
                           stroke-width="10"
                           stroke-dasharray="<?= $seg['dash'] ?> <?= $circumference ?>"
                           stroke-dashoffset="<?= $seg['offset'] ?>">
                           <title><?= h($seg['name']) ?> · <?= $seg['count'] ?> เคส (<?= $seg['pct'] ?>%)</title>
                        </circle>
                     <?php endif; ?>
                  <?php endforeach; ?>
               </svg>
               <div class="donut-center">
                  <span class="num"><?= number_format($stats['total']) ?></span>
                  <span class="cap">เคส</span>
               </div>
            </div>

            <div class="donut-legend">
               <?php if (empty($donut_segments)): ?>
                  <p class="text-gray-500 text-center text-xs py-2">ยังไม่มีข้อมูล</p>
               <?php endif; ?>
               <?php foreach ($donut_segments as $seg): ?>
                  <div class="row" style="--c: <?= $seg['color'] ?>" title="<?= h($seg['name']) ?>">
                     <span class="name"><?= h($seg['name']) ?></span>
                     <span class="cnt"><?= number_format($seg['count']) ?></span>
                     <span class="pct"><?= $seg['pct'] ?>%</span>
                  </div>
               <?php endforeach; ?>
            </div>
         </div>

         <div class="tray-foot">
            <span>หมวดหมู่</span>
            <span class="mono"><?= count($cat_counts) ?> หมวด</span>
         </div>
      </div>

   </div>

   <!-- ภาพรวม Staff + คิวรับงาน -->
   <div class="tray mb-6">
      <div class="tray-label">
         <span>คิวงาน</span>
         <i class="fa-solid fa-circle-info text-gray-300 text-xs" title="สรุปผลงานของ Staff แต่ละคน คะแนน และลำดับคิวรับงาน"></i>
         <span class="ml-auto mono text-[10px] tracking-wider text-gray-500"><?= count($staff_list) ?> คน · เข้าคิว <?= $staff_in_queue ?></span>
      </div>

      <div class="tray-card">
         <?php
         $banner = $current_offer ?? $next_staff;
         $bannerIsOffer = $current_offer !== null;
         ?>
         <?php if ($banner): ?>
            <?php $nInit = mb_strtoupper(mb_substr(trim((string)$banner['first_name']), 0, 1, 'UTF-8'), 'UTF-8'); ?>
            <div class="staff-next">
               <span class="staff-next-avatar" aria-hidden="true"><?= h($nInit !== '' ? $nInit : '?') ?></span>
               <div class="min-w-0">
                  <div class="staff-next-cap"><?= $bannerIsOffer ? 'กำลังรอรับงาน' : 'คิวรับงานถัดไป' ?></div>
                  <div class="staff-next-name truncate"><?= h($banner['first_name'] . ' ' . $banner['last_name']) ?></div>
               </div>
               <div class="staff-next-meta hidden sm:block">
                  <?php if ($bannerIsOffer): ?>
                     <div>มีงานรอรับ <?= (int)$banner['offer']['cnt'] ?> เคส</div>
                     <div>เหลือเวลา <?= floor($banner['offer']['secs'] / 60) ?> นาที <?= $banner['offer']['secs'] % 60 ?> วิ</div>
                  <?php else: ?>
                     <div>งานค้าง <?= (int)$banner['in_progress'] ?> เคส · คะแนน <?= (int)$banner['score'] ?></div>
                     <div>รับงานล่าสุด: <?= $banner['last_accepted'] ? h(time_ago($banner['last_accepted'])) : 'ยังไม่เคยรับงาน' ?></div>
                  <?php endif; ?>
               </div>
            </div>
         <?php else: ?>
            <div class="staff-next is-empty">
               <i class="fa-regular fa-circle-pause" aria-hidden="true"></i>
               <span class="text-sm">ยังไม่มี Staff ที่เปิดรับคิว</span>
            </div>
         <?php endif; ?>

         <div class="overflow-x-auto">
            <table class="recent-table" style="min-width:820px">
               <thead>
                  <tr>
                     <th>คิว</th>
                     <th>สตาฟ</th>
                     <th class="text-center">คะแนน</th>
                     <th class="text-center">กำลังทำ</th>
                     <th class="text-center">เสร็จวันนี้</th>
                     <th class="text-center">เสร็จเดือนนี้</th>
                     <th class="text-center">เสร็จทั้งหมด</th>
                     <th class="text-center">รับงานเฉลี่ย</th>
                     <th>รับงานล่าสุด</th>
                  </tr>
               </thead>
               <tbody>
                  <?php if (empty($staff_list)): ?>
                     <tr>
                        <td colspan="9" class="recent-empty">
                           <i class="fa-regular fa-user"></i>
                           <span>ยังไม่มีบัญชี Staff</span>
                        </td>
                     </tr>
                  <?php endif; ?>
                  <?php foreach ($staff_list as $s): ?>
                     <?php
                     $sInit = mb_strtoupper(mb_substr(trim((string)$s['first_name']), 0, 1, 'UTF-8'), 'UTF-8');
                     $isNext = !$current_offer && $s['queue_pos'] === 1;
                     $isOffered = $s['offer'] !== null;
                     ?>
                     <tr class="<?= ($isNext || $isOffered) ? 'staff-row-next' : '' ?>">
                        <td>
                           <?php if ($isOffered): ?>
                              <span class="queue-badge is-offer">รอรับงาน</span>
                           <?php elseif ($isNext): ?>
                              <span class="queue-badge is-next">ถัดไป</span>
                           <?php elseif ($s['queue_pos'] > 0): ?>
                              <span class="queue-badge">#<?= (int)$s['queue_pos'] ?></span>
                           <?php else: ?>
                              <span class="queue-badge is-off">ปิดรับคิว</span>
                           <?php endif; ?>
                        </td>
                        <td>
                           <div class="recent-person">
                              <span class="recent-avatar" aria-hidden="true"><?= h($sInit !== '' ? $sInit : '?') ?></span>
                              <span class="recent-name truncate max-w-[200px]"><?= h($s['first_name'] . ' ' . $s['last_name']) ?></span>
                           </div>
                        </td>
                        <td class="text-center"><span class="staff-num <?= (int)$s['score'] < 100 ? 'low-score' : '' ?>"><?= (int)$s['score'] ?></span></td>
                        <td class="text-center"><span class="staff-num <?= (int)$s['in_progress'] === 0 ? 'zero' : '' ?>"><?= (int)$s['in_progress'] ?></span></td>
                        <td class="text-center"><span class="staff-num <?= (int)$s['done_today'] === 0 ? 'zero' : '' ?>"><?= (int)$s['done_today'] ?></span></td>
                        <td class="text-center"><span class="staff-num <?= (int)$s['done_month'] === 0 ? 'zero' : '' ?>"><?= (int)$s['done_month'] ?></span></td>
                        <td class="text-center"><span class="staff-num <?= (int)$s['done_total'] === 0 ? 'zero' : '' ?>"><?= (int)$s['done_total'] ?></span></td>
                        <td class="text-center">
                           <span class="staff-num <?= $s['avg_minutes'] === null ? 'zero' : '' ?>"><?= $s['avg_minutes'] !== null ? round((float)$s['avg_minutes']) . ' นาที' : '-' ?></span>
                        </td>
                        <td>
                           <span class="text-xs text-gray-500"><?= $s['last_accepted'] ? h(time_ago($s['last_accepted'])) : 'ยังไม่เคยรับงาน' ?></span>
                        </td>
                     </tr>
                  <?php endforeach; ?>
               </tbody>
            </table>
         </div>
      </div>

      <div class="tray-foot">
         <span>เรียงตามลำดับคิว · คะแนนต่ำกว่า 100 แสดงเป็นสีแดง</span>
         <span class="mono">อัปเดต <?= date('d/m H:i') ?></span>
      </div>
   </div>

   <!-- ตารางคำขอล่าสุด -->
   <div class="tray recent-tray">
      <div class="tray-label">
         <span>คำขอซ่อมล่าสุด</span>
         <i class="fa-solid fa-circle-info text-gray-300 text-xs" title="รายการแจ้งซ่อมล่าสุด 5 รายการ พร้อมสถานะและผู้รับผิดชอบ"></i>
         <span class="ml-auto mono text-[10px] tracking-wider text-gray-500"><?= count($recent_tickets) ?> รายการ</span>
      </div>

      <div class="tray-card recent-card">
         <div class="recent-head">
            <div class="min-w-0">
               <h2 class="recent-title">คำขอแจ้งซ่อมล่าสุด</h2>
               <p class="recent-sub">รายการที่เข้ามาใหม่ที่สุด พร้อมสถานะและผู้รับผิดชอบ</p>
            </div>
            <a href="admin_manage_request.php" class="recent-link">
               <span>ดูทั้งหมด</span>
               <i class="fa-solid fa-arrow-right"></i>
            </a>
         </div>

         <div class="overflow-x-auto">
            <table class="recent-table">
               <thead>
                  <tr>
                     <th>รหัส / เวลา</th>
                     <th>ผู้แจ้ง</th>
                     <th>สถานที่</th>
                     <th>ปัญหา / อาการเสีย</th>
                     <th class="text-center">ความเร่งด่วน</th>
                     <th class="text-center">สถานะ</th>
                     <th>ผู้รับผิดชอบ</th>
                     <th class="text-right">การจัดการ</th>
                  </tr>
               </thead>
               <tbody>
                  <?php if (empty($recent_tickets)): ?>
                     <tr>
                        <td colspan="8" class="recent-empty">
                           <i class="fa-regular fa-folder-open"></i>
                           <span>ยังไม่มีรายการแจ้งซ่อม</span>
                        </td>
                     </tr>
                  <?php endif; ?>
                  <?php foreach ($recent_tickets as $t): ?>
                     <?php
                     $urg = $t['urgency'] ?? 'normal';
                     $urgClass = $urg === 'urgent' ? ' is-urgent' : '';
                     $urgLabel = $urg === 'urgent' ? 'ด่วน' : 'ปกติ';
                     $initial = mb_strtoupper(mb_substr(trim((string)$t['first_name']), 0, 1, 'UTF-8'), 'UTF-8');
                     ?>
                     <tr>
                        <td>
                           <span class="recent-ticket">#<?= h($t['ticket_no']) ?></span>
                           <span class="recent-when">
                              <i class="fa-regular fa-clock" aria-hidden="true"></i>
                              <?= h(time_ago($t['created_at'])) ?>
                           </span>
                        </td>
                        <td>
                           <div class="recent-person">
                              <span class="recent-avatar" aria-hidden="true"><?= h($initial !== '' ? $initial : '?') ?></span>
                              <span class="min-w-0">
                                 <span class="recent-name block truncate max-w-[170px]"><?= h($t['first_name'] . ' ' . $t['last_name']) ?></span>
                                 <span class="recent-dept block truncate max-w-[170px]"><?= h($t['department_name'] ?? 'ไม่ระบุแผนก') ?></span>
                              </span>
                           </div>
                        </td>
                        <td>
                           <div class="recent-loc">
                              <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                              <span class="truncate max-w-[170px]" title="<?= h($t['location_name']) ?>"><?= h($t['location_name']) ?></span>
                           </div>
                        </td>
                        <td>
                           <div class="recent-sym" title="<?= h($t['symptoms']) ?>">
                              <?= h(!empty($t['sub_category']) ? $t['sub_category'] : $t['symptoms']) ?>
                           </div>
                           <div class="recent-cat"><?= h($t['category_name']) ?></div>
                        </td>
                        <td class="text-center">
                           <span class="recent-chip<?= $urgClass ?>"><?= h($urgLabel) ?></span>
                        </td>
                        <td class="text-center">
                           <span class="recent-chip st-<?= h($t['status']) ?>"><?= h($statusLabels[$t['status']] ?? $t['status']) ?></span>
                        </td>
                        <td>
                           <?php if (!empty($t['tech_name'])): ?>
                              <span class="recent-tech">
                                 <i class="fa-solid fa-user" aria-hidden="true"></i>
                                 <?= h($t['tech_name']) ?>
                              </span>
                           <?php else: ?>
                              <span class="recent-waiting">รอรับงาน</span>
                           <?php endif; ?>
                        </td>
                        <td class="text-right">
                           <a href="admin_manage_request.php" class="recent-btn" aria-label="ดูเคส #<?= h($t['ticket_no']) ?>">
                              <span>ดูเคส</span>
                              <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                           </a>
                        </td>
                     </tr>
                  <?php endforeach; ?>
               </tbody>
            </table>
         </div>
      </div>

      <div class="tray-foot">
         <span><?= count($recent_tickets) ?> รายการล่าสุดจากทั้งหมด <?= number_format($stats['total']) ?> รายการ</span>
         <span class="mono">อัปเดต <?= date('d/m H:i') ?></span>
      </div>
   </div>

</div><!-- /.dash -->

</div>
<?php $footerBase = '../'; $footerVariant = 'app'; require __DIR__ . '/../includes/site_footer.php'; ?>
</div>
<script>
   (() => {
      const toggle = document.getElementById('trendToggle');
      if (!toggle) return;

      const buttons = Array.from(toggle.querySelectorAll('button[data-range]'));
      const panes = Array.from(document.querySelectorAll('[data-trend-pane]'));
      const totalEl = document.getElementById('trendTotal');
      const maxEl = document.getElementById('trendMax');
      const unitEl = document.getElementById('trendMaxUnit');
      const footEl = document.getElementById('trendFootLabel');

      const activate = (range, moveFocus = false) => {
         const button = buttons.find((b) => b.dataset.range === range);
         if (!button) return;

         buttons.forEach((b) => {
            const on = b === button;
            b.classList.toggle('act', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
            b.tabIndex = on ? 0 : -1;
         });

         if (moveFocus) button.focus();

         panes.forEach((pane) => {
            const on = pane.dataset.trendPane === range;
            pane.classList.toggle('is-active', on);
            pane.hidden = !on;

            if (on) {
               totalEl.textContent = pane.dataset.total;
               maxEl.textContent = pane.dataset.max;
               unitEl.textContent = pane.dataset.unit;
               footEl.textContent = pane.dataset.foot;
            }
         });
      };

      buttons.forEach((btn) => {
         btn.addEventListener('click', () => activate(btn.dataset.range));
      });

      toggle.addEventListener('keydown', (e) => {
         const current = buttons.indexOf(document.activeElement);
         if (current === -1) return;

         if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
            e.preventDefault();
            const dir = e.key === 'ArrowRight' ? 1 : -1;
            activate(buttons[(current + dir + buttons.length) % buttons.length].dataset.range, true);
         } else if (e.key === 'Home') {
            e.preventDefault();
            activate(buttons[0].dataset.range, true);
         } else if (e.key === 'End') {
            e.preventDefault();
            activate(buttons[buttons.length - 1].dataset.range, true);
         }
      });
   })();
</script>
</body>

=======
<?php
$page_title = 'Dashboard';
$current_page = 'dashboard';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
   session_start();
}

// 1. ดึงสถิติภาพรวมจากตาราง tickets
$stats = [
   'total' => 0,
   'completed' => 0,
   'pending' => 0,
   'urgent_pending' => 0,
   'in_progress' => 0,
   'closed_today' => 0,
   'avg_minutes' => 15
];

$q_stat = "SELECT 
       COUNT(*) AS total,
       SUM(status = 'completed') AS completed,
       SUM(status = 'pending') AS pending,
       SUM(status = 'pending' AND urgency = 'urgent') AS urgent_pending,
       SUM(status = 'in_progress') AS in_progress,
       SUM(status = 'completed' AND DATE(completed_at) = CURDATE()) AS closed_today,
       AVG(CASE WHEN accepted_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, accepted_at) END) AS avg_minutes
   FROM tickets";
$res_stat = $conn->query($q_stat);
if ($res_stat && $r = $res_stat->fetch_assoc()) {
   $stats['total'] = (int)($r['total'] ?? 0);
   $stats['completed'] = (int)($r['completed'] ?? 0);
   $stats['pending'] = (int)($r['pending'] ?? 0);
   $stats['urgent_pending'] = (int)($r['urgent_pending'] ?? 0);
   $stats['in_progress'] = (int)($r['in_progress'] ?? 0);
   $stats['closed_today'] = (int)($r['closed_today'] ?? 0);
   $stats['avg_minutes'] = $r['avg_minutes'] !== null ? round((float)$r['avg_minutes']) : 0;
}
$completed_percent = $stats['total'] > 0 ? round(($stats['completed'] / $stats['total']) * 100, 1) : 0;

// ===== แนวโน้มคำขอ: 7 วัน / 12 เดือน / 5 ปี =====
$monthsList = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
$pixelRows = 20;
$subFactors = [0.3, 0.55, 1, 0.6, 0.35];

function trend_fetch_map(mysqli $conn, string $sql): array
{
   $map = [];
   $res = $conn->query($sql);
   if ($res) {
      while ($row = $res->fetch_assoc()) {
         $map[(string)$row['b']] = [
            'total'  => (int)$row['total'],
            'urgent' => (int)$row['urgent'],
            'normal' => (int)$row['normal'],
            'low'    => (int)$row['low'],
         ];
      }
      $res->free();
   }
   return $map;
}

function trend_prepare_range(array $buckets, int $pixelRows, array $subFactors): array
{
   $total = 0;
   $max = 1;
   foreach ($buckets as $b) {
      $total += (int)$b['count'];
      $max = max($max, (int)$b['count']);
   }
   $scaleMax = max(4, (int)ceil($max / 4) * 4);

   $pixels = [];
   foreach ($buckets as $b) {
      $count = (int)$b['count'];
      $urgent = min((int)$b['urgent'], $count);
      $normal = min((int)$b['normal'], max(0, $count - $urgent));
      $low = min((int)$b['low'], max(0, $count - $urgent - $normal));

      $cols = [];
      foreach ($subFactors as $f) {
         $uH = $urgent > 0 ? max(1, (int)round(($urgent / $scaleMax) * $pixelRows * $f)) : 0;
         $nH = $normal > 0 ? max(1, (int)round(($normal / $scaleMax) * $pixelRows * $f)) : 0;
         $lH = $low > 0 ? max(1, (int)round(($low / $scaleMax) * $pixelRows * $f)) : 0;
         $cols[] = ['u' => $uH, 'n' => $nH, 'l' => $lH];
      }

      $pixels[] = array_merge($b, [
         'count'  => $count,
         'urgent' => $urgent,
         'normal' => $normal,
         'low'    => $low,
         'p'      => round($count / $scaleMax, 3),
         'cols'   => $cols,
      ]);
   }

   return [
      'buckets' => $pixels,
      'total'   => $total,
      'max'     => $max,
      'scale'   => $scaleMax,
      'ticks'   => [$scaleMax, (int)($scaleMax * 0.75), (int)($scaleMax * 0.5), (int)($scaleMax * 0.25), 0],
   ];
}

// 12 เดือนล่าสุด
$requestTrend = [];
$firstOfMonth = strtotime(date('Y-m-01'));
for ($i = 11; $i >= 0; $i--) {
   $ts = strtotime("-$i months", $firstOfMonth);
   $mKey = date('Y-m', $ts);
   $requestTrend[$mKey] = [
      'label' => $monthsList[(int)date('n', $ts) - 1],
      'full'  => date('M Y', $ts),
      'count' => 0,
      'urgent_users' => 0,
      'normal_users' => 0,
      'low_users'    => 0,
   ];
}

$trendQuery = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS m_key,
                                   COUNT(*) AS request_count,
                                   SUM(urgency = 'urgent') AS urgent_count,
                                   SUM(urgency = 'normal') AS normal_count,
                                   SUM(urgency = 'low') AS low_count
                            FROM tickets
                            WHERE created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)
                            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                            ORDER BY m_key");
if ($trendQuery) {
   while ($trendRow = $trendQuery->fetch_assoc()) {
      $mKey = (string)$trendRow['m_key'];
      if (array_key_exists($mKey, $requestTrend)) {
         $requestTrend[$mKey]['count'] = (int)$trendRow['request_count'];
         $requestTrend[$mKey]['urgent_users'] = (int)$trendRow['urgent_count'];
         $requestTrend[$mKey]['normal_users'] = (int)$trendRow['normal_count'];
         $requestTrend[$mKey]['low_users'] = (int)$trendRow['low_count'];
      }
   }
   $trendQuery->free();
}

$monthlyBuckets = [];
foreach ($requestTrend as $mKey => $tData) {
   $monthlyBuckets[] = [
      'key'    => $mKey,
      'label'  => $tData['label'],
      'full'   => $tData['full'],
      'count'  => (int)$tData['count'],
      'urgent' => (int)$tData['urgent_users'],
      'normal' => (int)$tData['normal_users'],
      'low'    => (int)$tData['low_users'],
   ];
}

// 7 วันล่าสุด (รวมวันนี้)
$sevenMap = trend_fetch_map($conn, "SELECT DATE(created_at) AS b,
                                          COUNT(*) AS total,
                                          SUM(urgency = 'urgent') AS urgent,
                                          SUM(urgency = 'normal') AS normal,
                                          SUM(urgency = 'low') AS low
                                   FROM tickets
                                   WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                                   GROUP BY DATE(created_at)");
$sevenBuckets = [];
for ($i = 6; $i >= 0; $i--) {
   $ts = strtotime("-$i days");
   $key = date('Y-m-d', $ts);
   $c = $sevenMap[$key] ?? ['total' => 0, 'urgent' => 0, 'normal' => 0, 'low' => 0];
   $sevenBuckets[] = [
      'key'    => $key,
      'label'  => strtoupper(date('D j', $ts)),
      'full'   => date('D j M Y', $ts),
      'count'  => (int)$c['total'],
      'urgent' => (int)$c['urgent'],
      'normal' => (int)$c['normal'],
      'low'    => (int)$c['low'],
   ];
}

// 5 ปีล่าสุด
$yearMap = trend_fetch_map($conn, "SELECT YEAR(created_at) AS b,
                                         COUNT(*) AS total,
                                         SUM(urgency = 'urgent') AS urgent,
                                         SUM(urgency = 'normal') AS normal,
                                         SUM(urgency = 'low') AS low
                                  FROM tickets
                                  WHERE YEAR(created_at) >= YEAR(CURDATE()) - 4
                                  GROUP BY YEAR(created_at)");
$yearlyBuckets = [];
$currentYear = (int)date('Y');
for ($y = $currentYear - 4; $y <= $currentYear; $y++) {
   $c = $yearMap[(string)$y] ?? ['total' => 0, 'urgent' => 0, 'normal' => 0, 'low' => 0];
   $yearlyBuckets[] = [
      'key'    => (string)$y,
      'label'  => (string)$y,
      'full'   => 'ปี ' . $y,
      'count'  => (int)$c['total'],
      'urgent' => (int)$c['urgent'],
      'normal' => (int)$c['normal'],
      'low'    => (int)$c['low'],
   ];
}

$trendRanges = [
   '7d' => array_merge(trend_prepare_range($sevenBuckets, $pixelRows, $subFactors), [
      'short' => '7D',
      'foot'  => 'จำนวนคำขอแจ้งซ่อมรายวัน (เลื่อนเมาส์เพื่อดูรายละเอียด)',
      'unit'  => '/ day',
   ]),
   'monthly' => array_merge(trend_prepare_range($monthlyBuckets, $pixelRows, $subFactors), [
      'short' => 'Monthly',
      'foot'  => 'จำนวนคำขอแจ้งซ่อมรายเดือน (เลื่อนเมาส์เพื่อดูรายละเอียด)',
      'unit'  => '/ mo',
   ]),
   'yearly' => array_merge(trend_prepare_range($yearlyBuckets, $pixelRows, $subFactors), [
      'short' => 'Yearly',
      'foot'  => 'จำนวนคำขอแจ้งซ่อมรายปี (เลื่อนเมาส์เพื่อดูรายละเอียด)',
      'unit'  => '/ yr',
   ]),
];

$trendTotal = $trendRanges['monthly']['total'];
$trendMax = $trendRanges['monthly']['max'];

// sparkline 12 เดือน แยกตามสถานะ (ใช้กับการ์ด KPI)
$spark = ['total' => [], 'pending' => [], 'in_progress' => [], 'closed' => []];
foreach (array_keys($requestTrend) as $k) {
   foreach ($spark as $n => $_) {
      $spark[$n][$k] = 0;
   }
}
$q_spark = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS m_key,
                                COUNT(*) AS total,
                                SUM(status = 'pending') AS pending,
                                SUM(status = 'in_progress') AS in_progress,
                                SUM(status = 'completed') AS closed
                         FROM tickets
                         WHERE created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)
                         GROUP BY DATE_FORMAT(created_at, '%Y-%m')");
if ($q_spark) {
   while ($s = $q_spark->fetch_assoc()) {
      $k = (string)$s['m_key'];
      if (!isset($spark['total'][$k])) continue;
      $spark['total'][$k]       = (int)$s['total'];
      $spark['pending'][$k]     = (int)$s['pending'];
      $spark['in_progress'][$k] = (int)$s['in_progress'];
      $spark['closed'][$k]      = (int)$s['closed'];
   }
   $q_spark->free();
}

function spark_bars(array $series)
{
   $max = max(1, max($series));
   $n = count($series);
   $i = 0;
   $html = '';
   foreach ($series as $k => $v) {
      $i++;
      $hgt = $v > 0 ? max(16, round($v / $max * 100)) : 8;
      $html .= '<i class="' . ($i === $n ? 'on' : '') . '" style="height:' . $hgt . '%" title="' . htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') . ' · ' . $v . '"></i>';
   }
   return $html;
}

// ข้อมูลการ์ด KPI
$kpis = [
   [
      'label' => 'คำขอทั้งหมด',
      'value' => $stats['total'],
      'spark' => 'total',
      'href'  => null,
      'note'  => '<b class="up">' . $completed_percent . '%</b> ปิดงานสำเร็จ',
   ],
   [
      'label' => 'รอรับเรื่อง',
      'value' => $stats['pending'],
      'spark' => 'pending',
      'href'  => 'admin_manage_request.php?status=pending',
      'note'  => $stats['urgent_pending'] > 0
         ? '<b class="down">เคสด่วน ' . $stats['urgent_pending'] . '</b>'
         : 'ไม่มีเคสด่วน',
   ],
   [
      'label' => 'กำลังดำเนินการ',
      'value' => $stats['in_progress'],
      'spark' => 'in_progress',
      'href'  => 'admin_manage_request.php?status=in_progress',
      'note'  => 'Lab Boy กำลังดูแล',
   ],
   [
      'label' => 'เสร็จสิ้นวันนี้',
      'value' => $stats['closed_today'],
      'spark' => 'closed',
      'href'  => null,
      'note'  => 'เข้าช่วยเฉลี่ย <b class="ink">' . $stats['avg_minutes'] . '</b> นาที',
   ],
];

// 2. ดึงสัดส่วนหมวดหมู่งานซ่อม
$cat_counts = [];
$q_cats = "SELECT c.id, c.name, COUNT(t.id) AS count
              FROM categories c
              LEFT JOIN tickets t ON t.category_id = c.id
              GROUP BY c.id, c.name
              ORDER BY count DESC";
$res_cats = $conn->query($q_cats);
if ($res_cats) {
   $cat_counts = $res_cats->fetch_all(MYSQLI_ASSOC);
}

// 4. Donut: แสดง 4 หมวดแรก + รวมที่เหลือเป็น "อื่น ๆ"
$donut_src = array_slice($cat_counts, 0, 4);
$rest = array_slice($cat_counts, 4);
if ($rest) {
   $donut_src[] = ['id' => 0, 'name' => 'อื่น ๆ', 'count' => array_sum(array_column($rest, 'count'))];
}

$circumference = 238.8;           // 2πr, r = 38
$seg_gap = count($donut_src) > 1 ? 2.5 : 0;
$donut_colors = ['#161616', '#6B6B68', '#A8A6A1', '#CFCDC8', '#E6E4E0'];
$donut_segments = [];
$current_offset = 0;

foreach ($donut_src as $i => $c) {
   $c_count = (int)$c['count'];
   $ratio = $stats['total'] > 0 ? $c_count / $stats['total'] : 0;
   $len = $ratio * $circumference;
   $donut_segments[] = [
      'name'   => $c['name'],
      'count'  => $c_count,
      'pct'    => round($ratio * 100),
      'dash'   => $c_count > 0 ? round(max(0.5, $len - $seg_gap), 1) : 0,
      'offset' => -round($current_offset, 1),
      'color'  => $donut_colors[$i % count($donut_colors)],
   ];
   $current_offset += $len;
}

// 5. ดึงคำขอล่าสุด 5 รายการ
$recent_tickets = [];
$q_recent = "SELECT 
       t.id AS ticket_id, t.ticket_no, t.location_note AS location_name, NULL AS sub_category, t.symptom AS symptoms, t.urgency, t.status, t.created_at,
       u.first_name, u.last_name, d.name AS department_name,
       c.name AS category_name,
       CONCAT(tech.first_name, ' ', tech.last_name) AS tech_name
   FROM tickets t
   JOIN users u ON u.user_id = t.requester_id
   LEFT JOIN department d ON d.d_id = u.department_id
   JOIN categories c ON c.id = t.category_id
   LEFT JOIN users tech ON tech.user_id = t.assigned_to
   ORDER BY t.created_at DESC
   LIMIT 5";
$res_recent = $conn->query($q_recent);
if ($res_recent) {
   $recent_tickets = $res_recent->fetch_all(MYSQLI_ASSOC);
}

// 6. ภาพรวม Staff แต่ละคน + คิวรับงาน
$staff_list = [];
$q_staff = "SELECT u.user_id, u.first_name, u.last_name, u.is_active_queue, u.queue_order, u.score,
       COUNT(t.id) AS total_assigned,
       COALESCE(SUM(t.status = 'in_progress'), 0) AS in_progress,
       COALESCE(SUM(t.status = 'completed' AND DATE(t.completed_at) = CURDATE()), 0) AS done_today,
       COALESCE(SUM(t.status = 'completed' AND DATE_FORMAT(t.completed_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')), 0) AS done_month,
       COALESCE(SUM(t.status = 'completed'), 0) AS done_total,
       AVG(CASE WHEN t.accepted_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.accepted_at) END) AS avg_minutes,
       MAX(t.accepted_at) AS last_accepted
   FROM users u
   LEFT JOIN tickets t ON t.assigned_to = u.user_id
   WHERE u.role = 'staff' AND u.status = 'active'
   GROUP BY u.user_id, u.first_name, u.last_name, u.is_active_queue, u.queue_order, u.score";
$res_staff = $conn->query($q_staff);
if ($res_staff) {
   $staff_list = $res_staff->fetch_all(MYSQLI_ASSOC);
}

// งานที่กำลังเรียกให้ Staff รับอยู่ตอนนี้ (ยังไม่หมดเวลา)
$offers = [];
$res_offer = $conn->query("SELECT queue_assigned_to AS uid,
                                  COUNT(*) AS cnt,
                                  MIN(TIMESTAMPDIFF(SECOND, NOW(), queue_expires_at)) AS secs_left
                           FROM tickets
                           WHERE status = 'pending'
                             AND queue_assigned_to IS NOT NULL
                             AND queue_expires_at > NOW()
                           GROUP BY queue_assigned_to");
if ($res_offer) {
   while ($o = $res_offer->fetch_assoc()) {
      $offers[(int)$o['uid']] = ['cnt' => (int)$o['cnt'], 'secs' => max(0, (int)$o['secs_left'])];
   }
   $res_offer->free();
}

// เรียงตามคิว: คนเปิดรับคิวก่อน → queue_order น้อยไปมาก (NULL ท้ายสุด) → user_id
usort($staff_list, function ($a, $b) {
   $qa = (int)$a['is_active_queue'];
   $qb = (int)$b['is_active_queue'];
   if ($qa !== $qb) return $qb <=> $qa;
   $oa = $a['queue_order'] !== null ? (int)$a['queue_order'] : PHP_INT_MAX;
   $ob = $b['queue_order'] !== null ? (int)$b['queue_order'] : PHP_INT_MAX;
   if ($oa !== $ob) return $oa <=> $ob;
   return (int)$a['user_id'] <=> (int)$b['user_id'];
});

$queue_pos = 0;
$current_offer = null; // คนที่กำลังถูกเรียกรับงานอยู่
foreach ($staff_list as $i => $s) {
   $staff_list[$i]['queue_pos'] = (int)$s['is_active_queue'] === 1 ? ++$queue_pos : 0;
   $staff_list[$i]['offer'] = $offers[(int)$s['user_id']] ?? null;
   if ($staff_list[$i]['offer'] && ($current_offer === null || $staff_list[$i]['offer']['secs'] < $current_offer['offer']['secs'])) {
      $current_offer = $staff_list[$i];
   }
}
$next_staff = ($staff_list && $staff_list[0]['queue_pos'] === 1) ? $staff_list[0] : null;
$staff_in_queue = $queue_pos;

function h($v)
{
   return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function time_ago($datetime)
{
   $time = strtotime($datetime);
   $diff = time() - $time;
   if ($diff < 60) return 'เมื่อสักครู่';
   if ($diff < 3600) return round($diff / 60) . ' นาทีที่แล้ว';
   if ($diff < 86400) return round($diff / 3600) . ' ชม. ที่แล้ว';
   return date('d/m H:i', $time);
}

$statusLabels = [
   'pending'     => 'รอรับเรื่อง',
   'in_progress' => 'กำลังดำเนินการ',
   'completed'   => 'เสร็จสิ้น',
   'cancelled'   => 'ยกเลิก'
];

require_once __DIR__ . '/admin_header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
   .dash {
      --tray: #F5F4F1;
      --tray-line: #EAE8E4;
      --card-bg: #FFFFFF;
      --card-line: #ECEAE6;
      --ink: #161616;
      --muted: #8E8B85;
      --pix-off: #F1EFEC;
      --c-urgent: #161616;
      --c-normal: #8E8B85;
      --c-low: #C9C6C0;
      font-family: 'Inter', 'Kanit', sans-serif;
   }

   .dash .mono {
      font-family: 'JetBrains Mono', 'Kanit', monospace;
   }

   .dash .welcome-title {
      font-size: 28px;
      font-weight: 400;
      color: var(--ink);
      letter-spacing: -0.02em;
   }

   .dash .btn-ink {
      background: var(--ink);
      color: #fff;
      border-radius: 10px;
   }

   /* ===== KPI card: tray เทา + การ์ดขาวด้านใน + footer บน tray ===== */
   .dash .kcard {
      min-width: 0;
      background: var(--tray);
      border: 1px solid var(--tray-line);
      border-radius: 16px;
      padding: 4px;
      display: flex;
      flex-direction: column;
      text-decoration: none;
      transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
   }

   .dash a.kcard:hover {
      border-color: #D9D6D0;
      box-shadow: 0 8px 22px rgba(0, 0, 0, .07);
      transform: translateY(-1px);
   }

   .dash .kcard-body {
      flex: 1;
      display: flex;
      align-items: stretch;
      justify-content: space-between;
      gap: 10px;
      min-height: 104px;
      padding: 16px 18px;
      background: var(--card-bg);
      border: 1px solid var(--card-line);
      border-radius: 12px;
      box-shadow: 0 1px 2px rgba(0, 0, 0, .03);
   }

   .dash .kcard-left {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      gap: 14px;
   }

   .dash .kcard-label {
      font-family: 'JetBrains Mono', 'Kanit', monospace;
      font-size: 12px;
      font-weight: 400;
      letter-spacing: .06em;
      text-transform: uppercase;
      color: #55534F;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
   }

   .dash .kcard-numrow {
      display: flex;
      align-items: baseline;
      gap: 8px;
      min-width: 0;
   }

   .dash .kcard-num {
      font-family: 'JetBrains Mono', 'Kanit', monospace;
      font-size: 28px;
      font-weight: 700;
      letter-spacing: -.02em;
      line-height: 1;
      color: var(--ink);
   }

   .dash .kcard-unit {
      font-size: 12.5px;
      color: var(--muted);
      white-space: nowrap;
   }

   .dash .kcard-bars {
      display: flex;
      align-items: flex-end;
      gap: 3px;
      height: 44px;
      flex: none;
      align-self: center;
   }

   .dash .kcard-bars i {
      display: block;
      width: 2px;
      min-height: 6px;
      border-radius: 2px;
      background: #E4E2DE;
   }

   .dash .kcard-bars i.on {
      width: 3px;
      background: var(--ink);
   }

   .dash .kcard-foot {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      padding: 10px 12px 7px;
   }

   .dash .kcard-dot {
      width: 18px;
      height: 18px;
      border-radius: 50%;
      background: #C9C6C0;
      color: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex: none;
   }

   .dash .kcard-note {
      min-width: 0;
      font-size: 12px;
      color: var(--muted);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
   }

   .dash .kcard-note b {
      font-family: 'JetBrains Mono', 'Kanit', monospace;
      font-weight: 600;
   }

   .dash .kcard-note b.up { color: #12A150; }
   .dash .kcard-note b.down { color: #DC2626; }
   .dash .kcard-note b.ink { color: var(--ink); }

   /* การ์ดขาวทั่วไป (ตาราง) */
   .dash .trend-card {
      background: var(--card-bg);
      border: 1px solid var(--card-line);
      border-radius: 16px;
      padding: 24px;
   }

   /* ===== Tray: กรอบเทาด้านนอก + การ์ดขาวด้านใน ===== */
   .dash .tray {
      background: var(--tray);
      border: 1px solid var(--tray-line);
      border-radius: 20px;
      padding: 6px;
      display: flex;
      flex-direction: column;
   }

   .dash .tray-label {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 10px 14px 12px;
      font-family: 'JetBrains Mono', 'Kanit', monospace;
      font-size: 12px;
      letter-spacing: .06em;
      text-transform: uppercase;
      color: var(--muted);
   }

   .dash .tray-card {
      background: var(--card-bg);
      border: 1px solid var(--card-line);
      border-radius: 14px;
      padding: 20px 22px 12px;
      flex: 1;
   }

   .dash .tray-foot {
      display: flex;
      justify-content: space-between;
      padding: 10px 14px 6px;
      font-size: 12px;
      color: var(--muted);
   }

   .dash .legend-dot {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-family: 'JetBrains Mono', monospace;
      font-size: 11.5px;
      letter-spacing: .06em;
      text-transform: uppercase;
      color: #55534F;
   }

   .dash .legend-dot::before {
      content: '';
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: var(--c);
   }

   .dash .seg-toggle {
      display: inline-flex;
      background: var(--tray);
      border-radius: 999px;
      padding: 3px;
      font-size: 12px;
   }

   .dash .seg-toggle button {
      padding: 5px 14px;
      border: 0;
      border-radius: 999px;
      background: transparent;
      color: var(--muted);
      font: inherit;
      font-size: 12px;
      font-weight: 500;
      line-height: 1.5;
      cursor: pointer;
      transition: color .15s ease, background-color .15s ease, box-shadow .15s ease;
   }

   .dash .seg-toggle button:hover {
      color: var(--ink);
   }

   .dash .seg-toggle button.act {
      background: #fff;
      color: var(--ink);
      box-shadow: 0 1px 3px rgba(0, 0, 0, .08);
   }

   .dash .seg-toggle button:focus-visible {
      outline: 2px solid var(--ink);
      outline-offset: 2px;
   }

   /* ===== สลับช่วงเวลา ===== */
   .dash .trend-pane {
      display: none;
   }

   .dash .trend-pane.is-active {
      display: block;
      animation: trendFade .22s ease-out;
   }

   @keyframes trendFade {
      from {
         opacity: 0;
         transform: translateY(3px);
      }

      to {
         opacity: 1;
         transform: translateY(0);
      }
   }

   @media (prefers-reduced-motion: reduce) {
      .dash .trend-pane.is-active {
         animation: none;
      }
   }

   /* ===== Dense pixel chart ===== */
   .dash .pix-wrap {
      display: grid;
      grid-template-columns: 44px 1fr;
      gap: 8px;
   }

   .dash .pix-y {
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding-bottom: 32px;
      font-family: 'JetBrains Mono', monospace;
      font-size: 12px;
      color: var(--muted);
   }

   .dash .pix-y span {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 8px;
      line-height: 1;
   }

   .dash .pix-y span::after {
      content: '';
      width: 3px;
      height: 3px;
      border-radius: 50%;
      background: #BDBAB4;
   }

   .dash .pix-cols {
      display: flex;
      gap: 2px;
   }

   .dash .pix-month {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      cursor: default;
   }

   .dash .pix-grid {
      position: relative;
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 2px;
   }

   .dash .pix-c {
      display: flex;
      flex-direction: column-reverse;
      gap: 2px;
   }

   .dash .pix-c b {
      display: block;
      aspect-ratio: 1;
      border-radius: 2px;
      background: var(--pix-off);
   }

   .dash .pix-c b.n { background: var(--c-normal); }
   .dash .pix-c b.l { background: var(--c-low); }
   .dash .pix-c b.u { background: var(--c-urgent); }

   .dash .pix-x {
      position: relative;
      height: 32px;
      padding-top: 12px;
      text-align: center;
      font-family: 'JetBrains Mono', monospace;
      font-size: 12px;
      letter-spacing: .03em;
      color: var(--muted);
   }

   .dash .pix-month:not(:last-child) .pix-x::after {
      content: '';
      position: absolute;
      right: -2px;
      top: 17px;
      width: 3px;
      height: 3px;
      border-radius: 50%;
      background: #C9C6C0;
   }

   .dash .pix-x.active,
   .dash .pix-month:hover .pix-x {
      color: var(--ink);
      font-weight: 700;
   }

   /* เส้นประ + จุด + tooltip ตอน hover */
   .dash .pix-line {
      display: none;
      position: absolute;
      left: 50%;
      top: 0;
      bottom: 0;
      border-left: 1px dotted #7A7873;
      pointer-events: none;
      z-index: 2;
   }

   .dash .pix-dot {
      position: absolute;
      left: -5px;
      bottom: calc(var(--p) * 100% - 4px);
      width: 9px;
      height: 9px;
      border-radius: 50%;
      background: var(--ink);
      border: 2px solid #fff;
      box-shadow: 0 0 0 1px #D8D5CF;
   }

   .dash .pix-tip {
      display: none;
      position: absolute;
      left: calc(50% + 14px);
      top: clamp(0px, calc((1 - var(--p)) * 100% - 10px), 55%);
      z-index: 10;
      min-width: 150px;
      background: #fff;
      border: 1px solid var(--card-line);
      border-radius: 12px;
      padding: 6px;
      box-shadow: 0 10px 28px rgba(0, 0, 0, .09);
      pointer-events: none;
   }

   .dash .pix-month.flip .pix-tip {
      left: auto;
      right: calc(50% + 14px);
   }

   .dash .pix-month:hover .pix-line,
   .dash .pix-month:hover .pix-tip {
      display: block;
   }

   .dash .pix-tip .t-head {
      background: var(--tray);
      border-radius: 8px;
      padding: 7px 10px;
      font-size: 14px;
      color: var(--muted);
   }

   .dash .pix-tip .t-row {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px 10px 3px;
      font-size: 13px;
      color: #6B6964;
   }

   .dash .pix-tip .t-row::before {
      content: '';
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--c);
      flex: none;
   }

   .dash .pix-tip .t-row strong {
      margin-left: 2px;
      color: var(--ink);
      font-size: 15px;
      font-weight: 600;
   }

   /* ===== Donut ===== */
   .dash .donut-wrap {
      position: relative;
      width: 150px;
      height: 150px;
      margin: 4px auto 18px;
   }

   .dash .donut-wrap svg {
      width: 100%;
      height: 100%;
      transform: rotate(-90deg);
   }

   .dash .donut-wrap circle.seg {
      transition: opacity .2s ease;
   }

   .dash .donut-wrap:hover circle.seg { opacity: .55; }
   .dash .donut-wrap circle.seg:hover { opacity: 1; }

   .dash .donut-center {
      position: absolute;
      inset: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      pointer-events: none;
   }

   .dash .donut-center .num {
      font-family: 'JetBrains Mono', monospace;
      font-size: 24px;
      font-weight: 700;
      color: var(--ink);
      line-height: 1;
   }

   .dash .donut-center .cap {
      margin-top: 4px;
      font-family: 'JetBrains Mono', monospace;
      font-size: 10px;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: var(--muted);
   }

   .dash .donut-legend {
      display: flex;
      flex-direction: column;
      gap: 9px;
      padding-top: 14px;
      border-top: 1px solid var(--card-line);
   }

   .dash .donut-legend .row {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12.5px;
      color: #55534F;
   }

   .dash .donut-legend .row::before {
      content: '';
      width: 8px;
      height: 8px;
      border-radius: 2px;
      background: var(--c);
      box-shadow: inset 0 0 0 1px rgba(0, 0, 0, .06);
      flex: none;
   }

   .dash .donut-legend .name {
      flex: 1;
      min-width: 0;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
   }

   .dash .donut-legend .cnt {
      font-family: 'JetBrains Mono', monospace;
      font-size: 11.5px;
      color: var(--muted);
   }

   .dash .donut-legend .pct {
      min-width: 38px;
      text-align: right;
      font-family: 'JetBrains Mono', monospace;
      font-weight: 600;
      color: var(--ink);
   }

   /* ===== Recent Service Requests ===== */
   .dash .recent-card {
      padding: 20px 22px 8px;
   }

   .dash .recent-head {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      gap: 16px;
      padding-bottom: 16px;
   }

   .dash .recent-title {
      margin: 0;
      font-size: 16px;
      font-weight: 400;
      letter-spacing: -.01em;
      color: var(--ink);
   }

   .dash .recent-sub {
      margin-top: 3px;
      font-size: 12px;
      color: var(--muted);
   }

   .dash .recent-link {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 6px 11px;
      border: 1px solid var(--card-line);
      border-radius: 999px;
      background: #fff;
      font-size: 12px;
      font-weight: 500;
      color: #55534F;
      text-decoration: none;
      white-space: nowrap;
      transition: color .15s ease, border-color .15s ease;
   }

   .dash .recent-link:hover {
      color: var(--ink);
      border-color: var(--tray-line);
   }

   .dash .recent-link i {
      font-size: 9px;
      transition: transform .15s ease;
   }

   .dash .recent-link:hover i {
      transform: translateX(2px);
   }

   .dash .recent-table {
      width: 100%;
      min-width: 900px;
      border-collapse: separate;
      border-spacing: 0;
   }

   .dash .recent-table thead {
      background: transparent;
   }

   .dash .recent-table thead th {
      padding: 9px 12px;
      background: var(--admin-soft, #F7F6F4);
      font-family: 'JetBrains Mono', 'Kanit', monospace;
      font-size: 11px;
      font-weight: 500;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: var(--muted);
      text-align: left;
      white-space: nowrap;
   }

   .dash .recent-table thead th:first-child {
      padding-left: 14px;
      border-top-left-radius: 999px;
      border-bottom-left-radius: 999px;
   }

   .dash .recent-table thead th:last-child {
      padding-right: 14px;
      text-align: right;
      border-top-right-radius: 999px;
      border-bottom-right-radius: 999px;
   }

   .dash .recent-table thead th.text-center {
      text-align: center;
   }

   .dash .recent-table tbody td {
      padding: 13px 12px;
      border-bottom: 1px solid #F1EFEC;
      vertical-align: middle;
   }

   .dash .recent-table tbody td:first-child {
      padding-left: 14px;
   }

   .dash .recent-table tbody td:last-child {
      padding-right: 14px;
   }

   .dash .recent-table tbody tr {
      transition: background-color .15s ease;
   }

   .dash .recent-table tbody tr:hover {
      background: #FBFAF8;
   }

   .dash .recent-table tbody tr:last-child td {
      border-bottom: 0;
   }

   .dash .recent-ticket {
      display: block;
      font-family: 'JetBrains Mono', monospace;
      font-size: 13px;
      font-weight: 600;
      letter-spacing: -.01em;
      color: var(--ink);
   }

   .dash .recent-when {
      display: flex;
      align-items: center;
      gap: 5px;
      margin-top: 3px;
      font-size: 11px;
      color: var(--muted);
   }

   .dash .recent-when i {
      font-size: 9px;
   }

   .dash .recent-person {
      display: flex;
      align-items: center;
      gap: 9px;
      min-width: 0;
   }

   .dash .recent-avatar {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: var(--tray);
      border: 1px solid var(--card-line);
      font-family: 'JetBrains Mono', monospace;
      font-size: 11px;
      font-weight: 600;
      color: #55534F;
      flex: none;
   }

   .dash .recent-name {
      font-size: 13px;
      font-weight: 500;
      color: var(--ink);
   }

   .dash .recent-dept {
      margin-top: 2px;
      font-size: 11.5px;
      color: var(--muted);
   }

   .dash .recent-loc {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 13px;
      font-weight: 500;
      color: var(--ink);
   }

   .dash .recent-loc i {
      font-size: 10px;
      color: #B9B5AE;
   }

   .dash .recent-sym {
      max-width: 260px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      font-size: 13px;
      font-weight: 500;
      color: var(--ink);
   }

   .dash .recent-cat {
      margin-top: 3px;
      font-family: 'JetBrains Mono', 'Kanit', monospace;
      font-size: 11px;
      letter-spacing: .05em;
      text-transform: uppercase;
      color: var(--muted);
   }

   .dash .recent-chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 3px 9px;
      border: 1px solid var(--card-line);
      border-radius: 999px;
      background: #F7F6F4;
      font-size: 11.5px;
      font-weight: 500;
      line-height: 1.5;
      color: #55534F;
      white-space: nowrap;
   }

   .dash .recent-chip::before {
      content: '';
      width: 5px;
      height: 5px;
      border-radius: 50%;
      background: var(--c, #B9B5AE);
      flex: none;
   }

   .dash .recent-chip.is-urgent {
      background: var(--ink);
      border-color: var(--ink);
      color: #fff;
   }

   .dash .recent-chip.is-urgent::before {
      background: #fff;
   }

   .dash .recent-chip.is-low {
      background: transparent;
      color: var(--muted);
   }

   .dash .recent-chip.st-pending { --c: #D97706; }
   .dash .recent-chip.st-in_progress { --c: #2563EB; }
   .dash .recent-chip.st-completed { --c: #12A150; }
   .dash .recent-chip.st-cancelled { --c: #8E8B85; }

   .dash .recent-tech {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 12.5px;
      color: #55534F;
   }

   .dash .recent-tech i {
      font-size: 9px;
      color: #B9B5AE;
   }

   .dash .recent-waiting {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      color: var(--muted);
   }

   .dash .recent-waiting::before {
      content: '';
      width: 5px;
      height: 5px;
      border-radius: 50%;
      background: #D9D6D0;
   }

   .dash .recent-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 11px;
      border-radius: 8px;
      background: var(--ink);
      font-size: 12px;
      font-weight: 400;
      color: #fff;
      text-decoration: none;
      white-space: nowrap;
      transition: background-color .15s ease;
   }

   .dash .recent-btn:hover {
      background: #2A2927;
   }

   .dash .recent-btn i {
      font-size: 9px;
      transition: transform .15s ease;
   }

   .dash .recent-btn:hover i {
      transform: translateX(2px);
   }

   .dash .recent-btn:focus-visible {
      outline: 2px solid var(--ink);
      outline-offset: 2px;
   }

   .dash .recent-empty {
      padding: 44px 16px;
      text-align: center;
      color: var(--muted);
   }

   .dash .recent-empty i {
      display: block;
      margin-bottom: 10px;
      font-size: 20px;
      color: #D9D6D0;
   }

   .dash .recent-empty span {
      font-size: 12.5px;
   }

   /* ===== Staff Overview / Queue ===== */
   .dash .staff-next {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 14px 16px;
      margin-bottom: 14px;
      background: var(--ink);
      border-radius: 12px;
      color: #fff;
   }

   .dash .staff-next-avatar {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: #fff;
      color: var(--ink);
      font-family: 'JetBrains Mono', monospace;
      font-size: 15px;
      font-weight: 700;
      flex: none;
   }

   .dash .staff-next-cap {
      font-family: 'JetBrains Mono', monospace;
      font-size: 11px;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: #A8A6A1;
   }

   .dash .staff-next-name {
      margin-top: 2px;
      font-size: 16px;
      font-weight: 500;
   }

   .dash .staff-next-meta {
      margin-left: auto;
      text-align: right;
      font-size: 12px;
      color: #A8A6A1;
   }

   .dash .staff-next.is-empty {
      background: var(--tray);
      color: var(--muted);
   }

   .dash .queue-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 28px;
      height: 24px;
      padding: 0 8px;
      border-radius: 999px;
      border: 1px solid var(--card-line);
      background: #F7F6F4;
      font-family: 'JetBrains Mono', monospace;
      font-size: 11.5px;
      font-weight: 600;
      color: #55534F;
      white-space: nowrap;
   }

   .dash .queue-badge.is-next {
      background: var(--ink);
      border-color: var(--ink);
      color: #fff;
   }

   .dash .queue-badge.is-off {
      background: transparent;
      color: var(--muted);
      font-weight: 400;
   }

   .dash .queue-badge.is-offer {
      background: #FEF3C7;
      border-color: #FCD34D;
      color: #92400E;
   }

   .dash .staff-num {
      font-family: 'JetBrains Mono', monospace;
      font-size: 13px;
      font-weight: 600;
      color: var(--ink);
   }

   .dash .staff-num.zero {
      color: #C9C6C0;
      font-weight: 400;
   }

   .dash .staff-num.low-score {
      color: #DC2626;
   }

   .dash .staff-row-next td {
      background: #FBFAF8;
   }

   @media (max-width: 768px) {
      .dash .pix-wrap { grid-template-columns: 34px 1fr; }
      .dash .pix-x { font-size: 8px; letter-spacing: 0; }
      .dash .pix-month:not(:last-child) .pix-x::after { display: none; }

      .dash .recent-card { padding: 16px 14px 6px; }
      .dash .recent-head { align-items: flex-start; gap: 10px; }
      .dash .recent-sub { display: none; }
      .dash .recent-title { font-size: 15px; }
      .dash .recent-link { padding: 5px 10px; font-size: 11.5px; }
   }
</style>

<div class="dash">

   <!-- Breadcrumb + Page Header -->
   <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
      <div>
         <nav class="flex items-center gap-2 text-xs text-gray-400 mb-2 font-medium">
            <a href="admin_dashboard.php" class="hover:text-ink transition-colors">Dashboard</a>
            <i class="fa-solid fa-chevron-right text-[8px]"></i>
            <span class="text-gray-900 font-normal">แดชบอร์ด</span>
         </nav>
         <h1 class="welcome-title">Welcome back, <?= h($_SESSION['admin_name'] ?? 'Admin') ?></h1>
      </div>
      <a href="admin_manage_request.php" class="btn-ink inline-flex items-center gap-2 px-4 py-2 text-sm font-normal hover:bg-neutral-800 transition-colors">
         <i class="fa-solid fa-list-check text-xs"></i>
         <span>จัดการคำขอทั้งหมด</span>
      </a>
   </div>

   <!-- แถวที่ 1: KPI 4 ใบ -->
   <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4 font-kanit">
      <?php foreach ($kpis as $k): ?>
         <<?= $k['href'] ? 'a href="' . h($k['href']) . '"' : 'div' ?> class="kcard">
            <div class="kcard-body">
               <div class="kcard-left">
                  <div class="kcard-label"><?= h($k['label']) ?></div>
                  <div class="kcard-numrow">
                     <span class="kcard-num"><?= number_format($k['value']) ?></span>
                     <span class="kcard-unit">เคส</span>
                  </div>
               </div>
               <span class="kcard-bars" role="img" aria-label="แนวโน้ม 12 เดือนล่าสุด">
                  <?= spark_bars($spark[$k['spark']]) ?>
               </span>
            </div>
            <div class="kcard-foot">
               <span class="kcard-dot" aria-hidden="true">
                  <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5" /><path d="M5 12l7-7 7 7" /></svg>
               </span>
               <span class="kcard-note"><?= $k['note'] ?></span>
            </div>
         </<?= $k['href'] ? 'a' : 'div' ?>>
      <?php endforeach; ?>
   </div>

   <!-- Trend chart + สัดส่วนหมวดหมู่ -->
   <div class="grid grid-cols-1 xl:grid-cols-4 gap-5 mb-6">

      <!-- REQUESTS TREND (tray + dense pixel chart) -->
      <div class="tray xl:col-span-3">
         <div class="tray-label">
            <span>Requests Trend</span>
            <i class="fa-solid fa-circle-info text-gray-300 text-xs" title="จำนวนคำขอแจ้งซ่อม แยกตามช่วงเวลาและความเร่งด่วน"></i>
         </div>

         <div class="tray-card">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
               <div class="flex items-center gap-4 flex-wrap w-full">
                  <div class="flex items-baseline gap-3">
                     <span class="text-sm text-gray-500">Total Requests :</span>
                     <span class="mono text-2xl font-bold text-gray-900" id="trendTotal"><?= number_format($trendTotal) ?></span>
                  </div>
                  <div class="flex items-center gap-4 lg:ml-6">
                     <span class="legend-dot" style="--c:var(--c-low)">ไม่เร่ง</span>
                     <span class="legend-dot" style="--c:var(--c-normal)">ปกติ</span>
                     <span class="legend-dot" style="--c:var(--c-urgent)">ด่วน</span>
                  </div>
                  <div class="seg-toggle ml-auto self-start lg:self-auto" id="trendToggle" role="tablist" aria-label="ช่วงเวลาของกราฟ">
                     <button type="button" data-range="7d" role="tab" aria-selected="false" aria-controls="trendPane7d" tabindex="-1">7 Days</button>
                     <button type="button" data-range="monthly" role="tab" aria-selected="true" aria-controls="trendPaneMonthly" class="act">Monthly</button>
                     <button type="button" data-range="yearly" role="tab" aria-selected="false" aria-controls="trendPaneYearly" tabindex="-1">Yearly</button>
                  </div>
               </div>
            </div>

            <?php foreach ($trendRanges as $rangeKey => $range): ?>
               <?php $paneId = 'trendPane' . ($rangeKey === '7d' ? '7d' : ucfirst($rangeKey)); ?>
               <div class="trend-pane <?= $rangeKey === 'monthly' ? 'is-active' : '' ?>"
                  id="<?= $paneId ?>"
                  role="tabpanel"
                  data-trend-pane="<?= h($rangeKey) ?>"
                  data-total="<?= number_format($range['total']) ?>"
                  data-max="<?= number_format($range['max']) ?>"
                  data-foot="<?= h($range['foot']) ?>"
                  data-unit="<?= h($range['unit']) ?>">

                  <div class="pix-wrap">
                     <div class="pix-y">
                        <?php foreach ($range['ticks'] as $tick): ?><span><?= number_format($tick) ?></span><?php endforeach; ?>
                     </div>

                     <div class="pix-cols">
                        <?php foreach ($range['buckets'] as $mi => $px): ?>
                           <?php
                           $flipFrom = max(0, count($range['buckets']) - 4);
                           $isCurrent = ($rangeKey === '7d' && $px['key'] === date('Y-m-d'))
                              || ($rangeKey === 'monthly' && $px['key'] === date('Y-m'))
                              || ($rangeKey === 'yearly' && $px['key'] === (string)date('Y'));
                           ?>
                           <div class="pix-month <?= $mi >= $flipFrom ? 'flip' : '' ?>">
                              <div class="pix-grid" style="--p: <?= $px['p'] ?>">
                                 <div class="pix-line"><span class="pix-dot"></span></div>
                                 <div class="pix-tip mono">
                                    <div class="t-head"><?= h($px['full']) ?></div>
                                    <div class="t-row" style="--c:var(--c-low)">ไม่เร่ง <strong><?= number_format($px['low']) ?></strong></div>
                                    <div class="t-row" style="--c:var(--c-normal)">ปกติ <strong><?= number_format($px['normal']) ?></strong></div>
                                    <div class="t-row" style="--c:var(--c-urgent)">ด่วน <strong><?= number_format($px['urgent']) ?></strong></div>
                                 </div>
                                 <?php foreach ($px['cols'] as $col): ?>
                                    <div class="pix-c">
                                       <?php for ($r = 0; $r < $pixelRows; $r++): ?>
                                          <?php
                                          if ($r < $col['u']) $cls = 'u';
                                          elseif ($r < $col['u'] + $col['n']) $cls = 'n';
                                          elseif ($r < $col['u'] + $col['n'] + $col['l']) $cls = 'l';
                                          else $cls = '';
                                          ?>
                                          <b<?= $cls ? ' class="' . $cls . '"' : '' ?>></b>
                                       <?php endfor; ?>
                                    </div>
                                 <?php endforeach; ?>
                              </div>
                              <div class="pix-x <?= $isCurrent ? 'active' : '' ?>"><?= h($px['label']) ?></div>
                           </div>
                        <?php endforeach; ?>
                     </div>
                  </div>
               </div>
            <?php endforeach; ?>
         </div>

         <div class="tray-foot">
            <span id="trendFootLabel"><?= h($trendRanges['monthly']['foot']) ?></span>
            <span class="mono">Max: <span id="trendMax"><?= number_format($trendMax) ?></span> <span id="trendMaxUnit">/ mo</span></span>
         </div>
      </div>

      <!-- Donut (tray style เหมือน Requests Trend) -->
      <div class="tray xl:col-span-1">
         <div class="tray-label">
            <span>Category Share</span>
            <i class="fa-solid fa-circle-info text-gray-300 text-xs" title="สัดส่วนคำขอแยกตามหมวดหมู่งานซ่อม"></i>
         </div>

         <div class="tray-card flex flex-col justify-center">
            <div class="donut-wrap">
               <svg viewBox="0 0 100 100" role="img" aria-label="สัดส่วนหมวดหมู่งานซ่อม">
                  <circle cx="50" cy="50" r="38" fill="none" stroke="#F1EFEC" stroke-width="10" />
                  <?php foreach ($donut_segments as $seg): ?>
                     <?php if ($seg['dash'] > 0): ?>
                        <circle class="seg" cx="50" cy="50" r="38" fill="none"
                           stroke="<?= $seg['color'] ?>"
                           stroke-width="10"
                           stroke-dasharray="<?= $seg['dash'] ?> <?= $circumference ?>"
                           stroke-dashoffset="<?= $seg['offset'] ?>">
                           <title><?= h($seg['name']) ?> · <?= $seg['count'] ?> เคส (<?= $seg['pct'] ?>%)</title>
                        </circle>
                     <?php endif; ?>
                  <?php endforeach; ?>
               </svg>
               <div class="donut-center">
                  <span class="num"><?= number_format($stats['total']) ?></span>
                  <span class="cap">Cases</span>
               </div>
            </div>

            <div class="donut-legend">
               <?php if (empty($donut_segments)): ?>
                  <p class="text-gray-400 text-center text-xs py-2">ยังไม่มีข้อมูล</p>
               <?php endif; ?>
               <?php foreach ($donut_segments as $seg): ?>
                  <div class="row" style="--c: <?= $seg['color'] ?>" title="<?= h($seg['name']) ?>">
                     <span class="name"><?= h($seg['name']) ?></span>
                     <span class="cnt"><?= number_format($seg['count']) ?></span>
                     <span class="pct"><?= $seg['pct'] ?>%</span>
                  </div>
               <?php endforeach; ?>
            </div>
         </div>

         <div class="tray-foot">
            <span>หมวดหมู่</span>
            <span class="mono"><?= count($cat_counts) ?> หมวด</span>
         </div>
      </div>

   </div>

   <!-- ภาพรวม Staff + คิวรับงาน -->
   <div class="tray mb-6">
      <div class="tray-label">
         <span>Staff Overview</span>
         <i class="fa-solid fa-circle-info text-gray-300 text-xs" title="สรุปผลงานของ Staff แต่ละคน คะแนน และลำดับคิวรับงาน"></i>
         <span class="ml-auto mono text-[10px] tracking-wider text-gray-400"><?= count($staff_list) ?> คน · เข้าคิว <?= $staff_in_queue ?></span>
      </div>

      <div class="tray-card">
         <?php
         $banner = $current_offer ?? $next_staff;
         $bannerIsOffer = $current_offer !== null;
         ?>
         <?php if ($banner): ?>
            <?php $nInit = mb_strtoupper(mb_substr(trim((string)$banner['first_name']), 0, 1, 'UTF-8'), 'UTF-8'); ?>
            <div class="staff-next">
               <span class="staff-next-avatar" aria-hidden="true"><?= h($nInit !== '' ? $nInit : '?') ?></span>
               <div class="min-w-0">
                  <div class="staff-next-cap"><?= $bannerIsOffer ? 'กำลังรอรับงาน' : 'คิวรับงานถัดไป' ?></div>
                  <div class="staff-next-name truncate"><?= h($banner['first_name'] . ' ' . $banner['last_name']) ?></div>
               </div>
               <div class="staff-next-meta hidden sm:block">
                  <?php if ($bannerIsOffer): ?>
                     <div>มีงานรอรับ <?= (int)$banner['offer']['cnt'] ?> เคส</div>
                     <div>เหลือเวลา <?= floor($banner['offer']['secs'] / 60) ?> นาที <?= $banner['offer']['secs'] % 60 ?> วิ</div>
                  <?php else: ?>
                     <div>งานค้าง <?= (int)$banner['in_progress'] ?> เคส · คะแนน <?= (int)$banner['score'] ?></div>
                     <div>รับงานล่าสุด: <?= $banner['last_accepted'] ? h(time_ago($banner['last_accepted'])) : 'ยังไม่เคยรับงาน' ?></div>
                  <?php endif; ?>
               </div>
            </div>
         <?php else: ?>
            <div class="staff-next is-empty">
               <i class="fa-regular fa-circle-pause" aria-hidden="true"></i>
               <span class="text-sm">ยังไม่มี Staff ที่เปิดรับคิว</span>
            </div>
         <?php endif; ?>

         <div class="overflow-x-auto">
            <table class="recent-table" style="min-width:820px">
               <thead>
                  <tr>
                     <th>คิว</th>
                     <th>Staff</th>
                     <th class="text-center">คะแนน</th>
                     <th class="text-center">กำลังทำ</th>
                     <th class="text-center">เสร็จวันนี้</th>
                     <th class="text-center">เสร็จเดือนนี้</th>
                     <th class="text-center">เสร็จทั้งหมด</th>
                     <th class="text-center">รับงานเฉลี่ย</th>
                     <th>รับงานล่าสุด</th>
                  </tr>
               </thead>
               <tbody>
                  <?php if (empty($staff_list)): ?>
                     <tr>
                        <td colspan="9" class="recent-empty">
                           <i class="fa-regular fa-user"></i>
                           <span>ยังไม่มีบัญชี Staff</span>
                        </td>
                     </tr>
                  <?php endif; ?>
                  <?php foreach ($staff_list as $s): ?>
                     <?php
                     $sInit = mb_strtoupper(mb_substr(trim((string)$s['first_name']), 0, 1, 'UTF-8'), 'UTF-8');
                     $isNext = !$current_offer && $s['queue_pos'] === 1;
                     $isOffered = $s['offer'] !== null;
                     ?>
                     <tr class="<?= ($isNext || $isOffered) ? 'staff-row-next' : '' ?>">
                        <td>
                           <?php if ($isOffered): ?>
                              <span class="queue-badge is-offer">รอรับงาน</span>
                           <?php elseif ($isNext): ?>
                              <span class="queue-badge is-next">ถัดไป</span>
                           <?php elseif ($s['queue_pos'] > 0): ?>
                              <span class="queue-badge">#<?= (int)$s['queue_pos'] ?></span>
                           <?php else: ?>
                              <span class="queue-badge is-off">ปิดรับคิว</span>
                           <?php endif; ?>
                        </td>
                        <td>
                           <div class="recent-person">
                              <span class="recent-avatar" aria-hidden="true"><?= h($sInit !== '' ? $sInit : '?') ?></span>
                              <span class="recent-name truncate max-w-[200px]"><?= h($s['first_name'] . ' ' . $s['last_name']) ?></span>
                           </div>
                        </td>
                        <td class="text-center"><span class="staff-num <?= (int)$s['score'] < 100 ? 'low-score' : '' ?>"><?= (int)$s['score'] ?></span></td>
                        <td class="text-center"><span class="staff-num <?= (int)$s['in_progress'] === 0 ? 'zero' : '' ?>"><?= (int)$s['in_progress'] ?></span></td>
                        <td class="text-center"><span class="staff-num <?= (int)$s['done_today'] === 0 ? 'zero' : '' ?>"><?= (int)$s['done_today'] ?></span></td>
                        <td class="text-center"><span class="staff-num <?= (int)$s['done_month'] === 0 ? 'zero' : '' ?>"><?= (int)$s['done_month'] ?></span></td>
                        <td class="text-center"><span class="staff-num <?= (int)$s['done_total'] === 0 ? 'zero' : '' ?>"><?= (int)$s['done_total'] ?></span></td>
                        <td class="text-center">
                           <span class="staff-num <?= $s['avg_minutes'] === null ? 'zero' : '' ?>"><?= $s['avg_minutes'] !== null ? round((float)$s['avg_minutes']) . ' นาที' : '-' ?></span>
                        </td>
                        <td>
                           <span class="text-xs text-gray-500"><?= $s['last_accepted'] ? h(time_ago($s['last_accepted'])) : 'ยังไม่เคยรับงาน' ?></span>
                        </td>
                     </tr>
                  <?php endforeach; ?>
               </tbody>
            </table>
         </div>
      </div>

      <div class="tray-foot">
         <span>เรียงตาม queue_order · คะแนนต่ำกว่า 100 แสดงเป็นสีแดง</span>
         <span class="mono">อัปเดต <?= date('d/m H:i') ?></span>
      </div>
   </div>

   <!-- ตารางคำขอล่าสุด -->
   <div class="tray recent-tray">
      <div class="tray-label">
         <span>Recent Service Requests</span>
         <i class="fa-solid fa-circle-info text-gray-300 text-xs" title="รายการแจ้งซ่อมล่าสุด 5 รายการ พร้อมสถานะและผู้รับผิดชอบ"></i>
         <span class="ml-auto mono text-[10px] tracking-wider text-gray-400"><?= count($recent_tickets) ?> รายการ</span>
      </div>

      <div class="tray-card recent-card">
         <div class="recent-head">
            <div class="min-w-0">
               <h2 class="recent-title">คำขอแจ้งซ่อมล่าสุด</h2>
               <p class="recent-sub">รายการที่เข้ามาใหม่ที่สุด พร้อมสถานะและผู้รับผิดชอบ</p>
            </div>
            <a href="admin_manage_request.php" class="recent-link">
               <span>ดูทั้งหมด</span>
               <i class="fa-solid fa-arrow-right"></i>
            </a>
         </div>

         <div class="overflow-x-auto">
            <table class="recent-table">
               <thead>
                  <tr>
                     <th>รหัส / เวลา</th>
                     <th>ผู้แจ้ง</th>
                     <th>สถานที่</th>
                     <th>ปัญหา / อาการเสีย</th>
                     <th class="text-center">ความเร่งด่วน</th>
                     <th class="text-center">สถานะ</th>
                     <th>ผู้รับผิดชอบ</th>
                     <th class="text-right">การจัดการ</th>
                  </tr>
               </thead>
               <tbody>
                  <?php if (empty($recent_tickets)): ?>
                     <tr>
                        <td colspan="8" class="recent-empty">
                           <i class="fa-regular fa-folder-open"></i>
                           <span>ยังไม่มีรายการแจ้งซ่อม</span>
                        </td>
                     </tr>
                  <?php endif; ?>
                  <?php foreach ($recent_tickets as $t): ?>
                     <?php
                     $urg = $t['urgency'] ?? 'normal';
                     $urgClass = $urg === 'urgent' ? ' is-urgent' : ($urg === 'low' ? ' is-low' : '');
                     $urgLabel = $urg === 'urgent' ? 'ด่วน' : ($urg === 'low' ? 'ไม่เร่ง' : 'ปกติ');
                     $initial = mb_strtoupper(mb_substr(trim((string)$t['first_name']), 0, 1, 'UTF-8'), 'UTF-8');
                     ?>
                     <tr>
                        <td>
                           <span class="recent-ticket">#<?= h($t['ticket_no']) ?></span>
                           <span class="recent-when">
                              <i class="fa-regular fa-clock" aria-hidden="true"></i>
                              <?= h(time_ago($t['created_at'])) ?>
                           </span>
                        </td>
                        <td>
                           <div class="recent-person">
                              <span class="recent-avatar" aria-hidden="true"><?= h($initial !== '' ? $initial : '?') ?></span>
                              <span class="min-w-0">
                                 <span class="recent-name block truncate max-w-[170px]"><?= h($t['first_name'] . ' ' . $t['last_name']) ?></span>
                                 <span class="recent-dept block truncate max-w-[170px]"><?= h($t['department_name'] ?? 'ไม่ระบุแผนก') ?></span>
                              </span>
                           </div>
                        </td>
                        <td>
                           <div class="recent-loc">
                              <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                              <span class="truncate max-w-[170px]" title="<?= h($t['location_name']) ?>"><?= h($t['location_name']) ?></span>
                           </div>
                        </td>
                        <td>
                           <div class="recent-sym" title="<?= h($t['symptoms']) ?>">
                              <?= h(!empty($t['sub_category']) ? $t['sub_category'] : $t['symptoms']) ?>
                           </div>
                           <div class="recent-cat"><?= h($t['category_name']) ?></div>
                        </td>
                        <td class="text-center">
                           <span class="recent-chip<?= $urgClass ?>"><?= h($urgLabel) ?></span>
                        </td>
                        <td class="text-center">
                           <span class="recent-chip st-<?= h($t['status']) ?>"><?= h($statusLabels[$t['status']] ?? $t['status']) ?></span>
                        </td>
                        <td>
                           <?php if (!empty($t['tech_name'])): ?>
                              <span class="recent-tech">
                                 <i class="fa-solid fa-user" aria-hidden="true"></i>
                                 <?= h($t['tech_name']) ?>
                              </span>
                           <?php else: ?>
                              <span class="recent-waiting">รอรับงาน</span>
                           <?php endif; ?>
                        </td>
                        <td class="text-right">
                           <a href="admin_manage_request.php" class="recent-btn" aria-label="ดูเคส #<?= h($t['ticket_no']) ?>">
                              <span>ดูเคส</span>
                              <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                           </a>
                        </td>
                     </tr>
                  <?php endforeach; ?>
               </tbody>
            </table>
         </div>
      </div>

      <div class="tray-foot">
         <span><?= count($recent_tickets) ?> รายการล่าสุดจากทั้งหมด <?= number_format($stats['total']) ?> รายการ</span>
         <span class="mono">อัปเดต <?= date('d/m H:i') ?></span>
      </div>
   </div>

</div><!-- /.dash -->

</div>
</div>
<script>
   (() => {
      const toggle = document.getElementById('trendToggle');
      if (!toggle) return;

      const buttons = Array.from(toggle.querySelectorAll('button[data-range]'));
      const panes = Array.from(document.querySelectorAll('[data-trend-pane]'));
      const totalEl = document.getElementById('trendTotal');
      const maxEl = document.getElementById('trendMax');
      const unitEl = document.getElementById('trendMaxUnit');
      const footEl = document.getElementById('trendFootLabel');

      const activate = (range, moveFocus = false) => {
         const button = buttons.find((b) => b.dataset.range === range);
         if (!button) return;

         buttons.forEach((b) => {
            const on = b === button;
            b.classList.toggle('act', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
            b.tabIndex = on ? 0 : -1;
         });

         if (moveFocus) button.focus();

         panes.forEach((pane) => {
            const on = pane.dataset.trendPane === range;
            pane.classList.toggle('is-active', on);
            pane.hidden = !on;

            if (on) {
               totalEl.textContent = pane.dataset.total;
               maxEl.textContent = pane.dataset.max;
               unitEl.textContent = pane.dataset.unit;
               footEl.textContent = pane.dataset.foot;
            }
         });
      };

      buttons.forEach((btn) => {
         btn.addEventListener('click', () => activate(btn.dataset.range));
      });

      toggle.addEventListener('keydown', (e) => {
         const current = buttons.indexOf(document.activeElement);
         if (current === -1) return;

         if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
            e.preventDefault();
            const dir = e.key === 'ArrowRight' ? 1 : -1;
            activate(buttons[(current + dir + buttons.length) % buttons.length].dataset.range, true);
         } else if (e.key === 'Home') {
            e.preventDefault();
            activate(buttons[0].dataset.range, true);
         } else if (e.key === 'End') {
            e.preventDefault();
            activate(buttons[buttons.length - 1].dataset.range, true);
         }
      });
   })();
</script>
</body>

>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
</html>