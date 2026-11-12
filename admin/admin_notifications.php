<?php
   $page_title = 'Notifications';
   $current_page = 'notifications';
   require_once __DIR__ . '/../includes/admin_auth.php';
   require_once __DIR__ . '/../db_connect.php';

   function h($v)
   {
      return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
   }

   $urgent = [];
   $pending = [];
   $recent = [];

   try {
      // 1. งานเร่งด่วนที่ยังไม่ปิด
      $sql_urgent = "SELECT t.id AS ticket_id, t.ticket_no, t.location_note AS location_name, t.created_at, 
                            u.first_name, u.last_name
                     FROM tickets t 
                     JOIN users u ON u.user_id = t.requester_id 
                     WHERE t.urgency = 'urgent' AND t.status IN ('pending', 'in_progress') 
                     ORDER BY t.created_at DESC 
                     LIMIT 50";
      $res_urgent = $conn->query($sql_urgent);
      if (!$res_urgent) {
         throw new RuntimeException($conn->error);
      }
      $urgent = $res_urgent->fetch_all(MYSQLI_ASSOC);
      $res_urgent->free();

      // 2. คำขอรอรับเรื่อง (ที่ไม่ใช่งานด่วน)
      $sql_pending = "SELECT t.id AS ticket_id, t.ticket_no, t.location_note AS location_name, t.created_at, 
                             u.first_name, u.last_name
                      FROM tickets t 
                      JOIN users u ON u.user_id = t.requester_id 
                      WHERE t.status = 'pending' AND t.urgency <> 'urgent' 
                      ORDER BY t.created_at DESC 
                      LIMIT 50";
      $res_pending = $conn->query($sql_pending);
      if (!$res_pending) {
         throw new RuntimeException($conn->error);
      }
      $pending = $res_pending->fetch_all(MYSQLI_ASSOC);
      $res_pending->free();

      // 3. ความเคลื่อนไหวล่าสุด (ticket_logs)
      $sql_recent = "SELECT tl.created_at, tl.old_status, tl.new_status, tl.source, 
                            t.ticket_no, u.first_name, u.last_name 
                     FROM ticket_logs tl 
                     JOIN tickets t ON t.id = tl.ticket_id 
                     JOIN users u ON u.user_id = tl.action_by 
                     ORDER BY tl.created_at DESC 
                     LIMIT 20";
      $res_recent = $conn->query($sql_recent);
      if (!$res_recent) {
         throw new RuntimeException($conn->error);
      }
      $recent = $res_recent->fetch_all(MYSQLI_ASSOC);
      $res_recent->free();

   } catch (Throwable $e) {
      $urgent = $pending = $recent = [];
      error_log('Admin notifications query failed: ' . $e->getMessage());
      $loadError = 'โหลดการแจ้งเตือนไม่ได้ กรุณาลองใหม่ภายหลัง';
   }

   $labels = [
      'pending'     => 'รอรับเรื่อง', 
      'in_progress' => 'กำลังดำเนินการ', 
      'completed'   => 'เสร็จสิ้น', 
      'cancelled'   => 'ยกเลิก'
   ];

   require_once __DIR__ . '/admin_header.php';
?>

      <div class="admin-page-head">
         <nav class="admin-crumb">
            <a href="admin_dashboard.php">Dashboard</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>การแจ้งเตือน</span>
         </nav>
         <h1 class="admin-page-title">การแจ้งเตือน</h1>
         <p class="admin-page-sub">ติดตามงานด่วน คำขอใหม่ และความเคลื่อนไหวล่าสุด</p>
      </div>

      <?php if (isset($loadError)): ?>
         <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800"><?= h($loadError) ?></div>
      <?php endif; ?>

      <!-- กล่องงานด่วน และ งานรอรับเรื่อง -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
         <!-- งานเร่งด่วน -->
         <div class="admin-tray">
            <div class="admin-tray-label">
               <span>Urgent</span>
               <span><?= count($urgent) ?> รายการ</span>
            </div>
            <section class="admin-card">
               <div class="flex items-center justify-between border-b border-[#ECEAE6] p-5">
                  <div class="flex items-center gap-3">
                     <span class="admin-icon is-ink">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                     </span>
                     <div>
                        <h2 class="font-medium text-gray-900">งานเร่งด่วนที่ยังไม่ปิด</h2>
                        <p class="text-xs text-gray-500">ต้องติดตาม <?= count($urgent) ?> รายการ</p>
                     </div>
                  </div>
               </div>
               <div class="divide-y divide-[#F1EFEC]">
                  <?php if (!$urgent): ?>
                     <p class="p-8 text-center text-sm text-gray-500">ไม่มีงานด่วนค้างอยู่</p>
                  <?php endif; ?>
                  <?php foreach ($urgent as $t): ?>
                     <a href="admin_manage_request.php" class="flex items-center justify-between gap-3 p-4 hover:bg-[#FBFAF8] transition-colors">
                        <div>
                           <div class="font-mono text-sm font-medium text-gray-900">#<?= h($t['ticket_no']) ?></div>
                           <div class="mt-1 text-xs text-gray-500">
                              <?= h($t['first_name'] . ' ' . $t['last_name']) ?> · <?= h($t['location_name']) ?>
                           </div>
                        </div>
                        <span class="shrink-0 text-xs text-gray-500"><?= h(date('d/m H:i', strtotime($t['created_at']))) ?></span>
                     </a>
                  <?php endforeach; ?>
               </div>
            </section>
         </div>

         <!-- งานรอรับเรื่อง -->
         <div class="admin-tray">
            <div class="admin-tray-label">
               <span>Pending</span>
               <span><?= count($pending) ?> รายการ</span>
            </div>
            <section class="admin-card">
               <div class="flex items-center justify-between border-b border-[#ECEAE6] p-5">
                  <div class="flex items-center gap-3">
                     <span class="admin-icon">
                        <i class="fa-regular fa-clock"></i>
                     </span>
                     <div>
                        <h2 class="font-medium text-gray-900">คำขอรอรับเรื่อง</h2>
                        <p class="text-xs text-gray-500">รอดำเนินการ <?= count($pending) ?> รายการ</p>
                     </div>
                  </div>
               </div>
               <div class="divide-y divide-[#F1EFEC]">
                  <?php if (!$pending): ?>
                     <p class="p-8 text-center text-sm text-gray-500">ไม่มีคำขอรอรับเรื่อง</p>
                  <?php endif; ?>
                  <?php foreach ($pending as $t): ?>
                     <a href="admin_manage_request.php" class="flex items-center justify-between gap-3 p-4 hover:bg-[#FBFAF8] transition-colors">
                        <div>
                           <div class="font-mono text-sm font-medium text-gray-900">#<?= h($t['ticket_no']) ?></div>
                           <div class="mt-1 text-xs text-gray-500">
                              <?= h($t['first_name'] . ' ' . $t['last_name']) ?> · <?= h($t['location_name']) ?>
                           </div>
                        </div>
                        <span class="shrink-0 text-xs text-gray-500"><?= h(date('d/m H:i', strtotime($t['created_at']))) ?></span>
                     </a>
                  <?php endforeach; ?>
               </div>
            </section>
         </div>
      </div>

      <!-- รายการความเคลื่อนไหวล่าสุด -->
      <div class="admin-tray">
         <div class="admin-tray-label">Activity</div>
         <section class="admin-card">
            <div class="border-b border-[#ECEAE6] p-5">
               <h2 class="font-medium text-gray-900">ความเคลื่อนไหวล่าสุด</h2>
               <p class="mt-1 text-xs text-gray-500">การเปลี่ยนสถานะที่บันทึกในระบบ</p>
            </div>
            <div class="divide-y divide-[#F1EFEC]">
               <?php if (!$recent): ?>
                  <p class="p-8 text-center text-sm text-gray-500">ยังไม่มีความเคลื่อนไหว</p>
               <?php endif; ?>
               <?php foreach ($recent as $r): ?>
                  <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                     <div class="flex items-center gap-3">
                        <span class="admin-icon" style="width:32px;height:32px;border-radius:50%;">
                           <i class="fa-solid fa-arrow-right-arrow-left text-xs"></i>
                        </span>
                        <div class="text-sm">
                           <span class="font-medium text-gray-900">#<?= h($r['ticket_no']) ?></span>
                           <span class="text-gray-500">
                              · <?= h($r['first_name'] . ' ' . $r['last_name']) ?> 
                              เปลี่ยนจาก <?= h($labels[$r['old_status']] ?? $r['old_status'] ?? 'เริ่มต้น') ?> 
                              เป็น <?= h($labels[$r['new_status']] ?? $r['new_status']) ?>
                              <?php if (($r['source'] ?? 'web') === 'line'): ?><span class="admin-chip no-dot ml-1"><i class="fa-brands fa-line"></i> ผ่าน LINE</span><?php endif; ?>
                           </span>
                        </div>
                     </div>
                     <time class="text-xs text-gray-400"><?= h(date('d/m/Y H:i', strtotime($r['created_at']))) ?></time>
                  </div>
               <?php endforeach; ?>
            </div>
         </section>
      </div>
   </div>
</div>
</body>
</html>
