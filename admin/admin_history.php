<?php
<<<<<<< HEAD
$page_title = 'ประวัติ';
=======
$page_title = 'History';
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
$current_page = 'history';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../db_connect.php';

function h($v)
{
   return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$from = is_string($_GET['from'] ?? null) ? $_GET['from'] : '';
$to = is_string($_GET['to'] ?? null) ? $_GET['to'] : '';
$isValidDate = static function (string $date): bool {
   $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
   return $parsed !== false && $parsed->format('Y-m-d') === $date;
};
$where = [];
$params = [];
$types = '';

if ($search !== '') {
   $where[] = '(t.ticket_no LIKE ? OR actor.first_name LIKE ? OR actor.last_name LIKE ? OR target.first_name LIKE ? OR target.last_name LIKE ?)';
   $term = '%' . $search . '%';
   array_push($params, $term, $term, $term, $term, $term);
   $types .= 'sssss';
}

if ($isValidDate($from)) {
   $where[] = 'tl.created_at >= ?';
   $params[] = $from . ' 00:00:00';
   $types .= 's';
}

if ($isValidDate($to)) {
   $where[] = 'tl.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
   $params[] = $to;
   $types .= 's';
}

$logs = [];

try {
   $sql = 'SELECT 
                    tl.created_at, tl.old_status, tl.new_status, 
                    t.ticket_no, 
                    actor.first_name AS actor_first, actor.last_name AS actor_last, 
                    target.first_name AS target_first, target.last_name AS target_last 
                FROM ticket_logs tl 
                JOIN tickets t ON t.id = tl.ticket_id 
                JOIN users actor ON actor.user_id = tl.action_by 
                JOIN users target ON target.user_id = t.requester_id'
      . ($where ? ' WHERE ' . implode(' AND ', $where) : '') .
      ' ORDER BY tl.created_at DESC LIMIT 250';

   $stmt = $conn->prepare($sql);
   if ($stmt) {
      if (!empty($params)) {
         $stmt->bind_param($types, ...$params);
      }
      $stmt->execute();
      $result = $stmt->get_result();
      $logs = $result->fetch_all(MYSQLI_ASSOC);
      $stmt->close();
   } else {
      throw new Exception($conn->error);
   }
} catch (Throwable $e) {
   $logs = [];
   error_log('Admin history query failed: ' . $e->getMessage());
   $loadError = 'โหลดประวัติไม่ได้ กรุณาลองใหม่ภายหลัง';
}

$labels = [
   'pending'     => 'รอรับเรื่อง',
<<<<<<< HEAD
   'pending' => 'รอรับเรื่อง', 'accepted' => 'รับแล้ว รอบันทึกเช็คลิสต์', 'awaiting_intake_sign' => 'รอผู้แจ้งเซ็นรับทราบ',
   'in_progress' => 'กำลังซ่อม', 'inspected_ready' => 'ตรวจซ้ำแล้ว รอส่งมอบ', 'awaiting_requester_sign' => 'รอผู้แจ้งเซ็นรับคืน',
   'awaiting_admin_sign' => 'รอครูรับรอง', 'disputed' => 'ผู้แจ้งไม่ยอมรับ',
=======
   'in_progress' => 'กำลังดำเนินการ',
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
   'completed'   => 'เสร็จสิ้น',
   'cancelled'   => 'ยกเลิก'
];
require_once __DIR__ . '/admin_header.php';
?>

         <div class="admin-page-head">
            <nav class="admin-crumb">
<<<<<<< HEAD
               <a href="admin_dashboard.php"><?= tr('แดชบอร์ด', 'Dashboard') ?></a>
               <i class="fa-solid fa-chevron-right"></i>
               <span><?= tr('ประวัติ', 'History') ?></span>
            </nav>
            <h1 class="admin-page-title"><?= tr('ประวัติการดำเนินงาน', 'Activity history') ?></h1>
            <p class="admin-page-sub"><?= tr('ตรวจสอบบันทึกการเปลี่ยนสถานะคำขอย้อนหลัง', 'Review past status changes on repair requests') ?></p>
=======
               <a href="admin_dashboard.php">Dashboard</a>
               <i class="fa-solid fa-chevron-right"></i>
               <span>ประวัติ</span>
            </nav>
            <h1 class="admin-page-title">ประวัติการดำเนินงาน</h1>
            <p class="admin-page-sub">ตรวจสอบบันทึกการเปลี่ยนสถานะคำขอย้อนหลัง</p>
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
         </div>

         <?php if (isset($loadError)): ?>
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
               <?= h($loadError) ?>
            </div>
         <?php endif; ?>

         <div class="admin-tray">
            <div class="admin-tray-label">
               <span>Activity Log</span>
               <span><?= count($logs) ?> รายการ</span>
            </div>
            <section class="admin-card">
               <form method="get" class="admin-toolbar">
                  <label class="min-w-[210px] flex-1 text-xs text-gray-600">
                     ค้นหาคำขอหรือผู้ดำเนินการ
                     <input name="q" value="<?= h($search) ?>" placeholder="รหัสคำขอ / ชื่อ" class="font-normal mt-1 block w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:border-gray-900">
                  </label>
                  <label class="text-xs text-gray-600">
                     ตั้งแต่
                     <input type="date" name="from" value="<?= h($from) ?>" class="mt-1 block rounded-lg border px-3 py-2 text-sm focus:outline-none focus:border-gray-900">
                  </label>
                  <label class="text-xs text-gray-600">
                     ถึงวันที่
                     <input type="date" name="to" value="<?= h($to) ?>" class="mt-1 block rounded-lg border px-3 py-2 text-sm focus:outline-none focus:border-gray-900">
                  </label>
                  <div class="admin-toolbar-actions">
                     <button class="admin-btn mt-5">ค้นหา</button>
                     <?php if ($search || $from || $to): ?>
                        <a href="admin_history.php" class="admin-reset-link">ล้าง</a>
                     <?php endif; ?>
                  </div>
               </form>

               <div class="overflow-x-auto admin-table-wrap">
<<<<<<< HEAD
                  <table class="w-full min-w-[750px] text-left text-sm" data-pager id="historyLogs">
=======
                  <table class="w-full min-w-[750px] text-left text-sm">
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
                     <thead class="bg-gray-50 text-xs text-gray-500">
                        <tr>
                           <th class="px-5 py-3 font-medium">วันและเวลา</th>
                           <th class="px-5 py-3 font-medium">คำขอ</th>
                           <th class="px-5 py-3 font-medium">ผู้แจ้ง</th>
                           <th class="px-5 py-3 font-medium">ผู้ดำเนินการ</th>
                           <th class="px-5 py-3 font-medium">เปลี่ยนสถานะ</th>
                        </tr>
                     </thead>
                     <tbody class="divide-y divide-gray-100">
                        <?php if (!$logs): ?>
                           <tr>
                              <td colspan="5" class="px-5 py-14 text-center text-gray-500">ไม่พบประวัติในช่วงที่เลือก</td>
                           </tr>
                        <?php endif; ?>
                        <?php foreach ($logs as $log): ?>
                           <tr class="hover:bg-gray-50/70 transition-colors">
                              <td class="px-5 py-4 text-gray-600"><?= h(date('d/m/Y H:i', strtotime($log['created_at']))) ?></td>
                              <td class="px-5 py-4 font-mono font-medium text-gray-900">#<?= h($log['ticket_no']) ?></td>
                              <td class="px-5 py-4 text-gray-800"><?= h($log['target_first'] . ' ' . $log['target_last']) ?></td>
                              <td class="px-5 py-4 text-gray-800"><?= h($log['actor_first'] . ' ' . $log['actor_last']) ?></td>
                              <td class="px-5 py-4">
                                 <span class="admin-chip st-<?= h($log['old_status']) ?>">
                                    <?= h($labels[$log['old_status']] ?? $log['old_status'] ?? 'เริ่มต้น') ?>
                                 </span>
<<<<<<< HEAD
                                 <i class="fa-solid fa-arrow-right mx-2 text-[10px] text-gray-500"></i>
=======
                                 <i class="fa-solid fa-arrow-right mx-2 text-[10px] text-gray-400"></i>
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
                                 <span class="admin-chip st-<?= h($log['new_status']) ?>">
                                    <?= h($labels[$log['new_status']] ?? $log['new_status']) ?>
                                 </span>
                              </td>
                           </tr>
                        <?php endforeach; ?>
                     </tbody>
                  </table>
               </div>
               <div class="border-t border-[#ECEAE6] px-5 py-3 text-xs text-gray-500">แสดง <?= count($logs) ?> รายการล่าสุด (สูงสุด 250)</div>
            </section>
         </div>
      </div>
<<<<<<< HEAD
<?php $footerBase = '../'; $footerVariant = 'app'; require __DIR__ . '/../includes/site_footer.php'; ?>
=======
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
   </div>
</body>
</html>
