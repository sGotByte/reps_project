<?php
$page_title = 'Reports';
$current_page = 'reports';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/admin_report_data.php';

function h($v)
{
   return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

$from = is_string($_GET['from'] ?? null) ? $_GET['from'] : date('Y-m-01');
$to = is_string($_GET['to'] ?? null) ? $_GET['to'] : date('Y-m-d');
$isValidDate = static function (string $date): bool {
   $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
   return $parsed !== false && $parsed->format('Y-m-d') === $date;
};
if (!$isValidDate($from)) $from = date('Y-m-01');
if (!$isValidDate($to)) $to = date('Y-m-d');

try {
   ['summary' => $summary, 'statusRows' => $statusRows, 'categories' => $categories, 'daily' => $daily, 'tickets' => $tickets] = load_admin_report_data($conn, $from, $to);
} catch (Throwable $e) {
   $summary = [];
   $statusRows = $categories = $daily = $tickets = [];
   error_log('Admin report query failed: ' . $e->getMessage());
   $loadError = 'สร้างรายงานไม่ได้ กรุณาลองใหม่ภายหลัง';
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
               <span>รายงาน</span>
            </nav>
            <h1 class="admin-page-title">รายงานคำขอซ่อม</h1>
            <p class="admin-page-sub">สรุปปริมาณงานและผลการดำเนินงานตามช่วงวันที่</p>
         </div>

         <div class="admin-tray">
            <div class="admin-tray-label">Report Range</div>
            <form method="get" class="admin-card admin-toolbar">
               <label class="text-xs text-gray-600">
                  ตั้งแต่
                  <input type="date" name="from" value="<?= h($from) ?>" class="mt-1 block rounded-lg border px-3 py-2 text-sm focus:outline-none focus:border-gray-900">
               </label>
               <label class="text-xs text-gray-600">
                  ถึงวันที่
                  <input type="date" name="to" value="<?= h($to) ?>" class="mt-1 block rounded-lg border px-3 py-2 text-sm focus:outline-none focus:border-gray-900">
               </label>
               <div class="admin-toolbar-actions">
                  <button type="submit" class="admin-btn">แสดงรายงาน</button>
                  <button type="submit" formaction="admin_report_pdf.php" formmethod="get" class="admin-btn admin-btn-secondary">
                     <i class="fa-solid fa-file-pdf" aria-hidden="true"></i> ดาวน์โหลด PDF
                  </button>
               </div>
            </form>
         </div>

         <?php if (isset($loadError)): ?>
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800"><?= h($loadError) ?></div>
         <?php endif; ?>

         <!-- การ์ดตัวเลขสรุป -->
         <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            <?php foreach (
               [
                  ['คำขอทั้งหมด', (int)($summary['total'] ?? 0), 'fa-clipboard-list'],
                  ['ปิดงานแล้ว', (int)($summary['completed'] ?? 0), 'fa-circle-check'],
                  ['กำลังดำเนินการ', (int)($summary['open_count'] ?? 0), 'fa-screwdriver-wrench'],
                  ['งานด่วน', (int)($summary['urgent_count'] ?? 0), 'fa-triangle-exclamation']
               ] as [$label, $value, $icon]
            ): ?>
               <div class="admin-stat">
                  <div class="admin-stat-label">
                     <span><?= h($label) ?></span>
                     <!-- <i class="fa-solid <?= h($icon) ?> admin-stat-icon" aria-hidden="true"></i> -->
                  </div>
                  <div class="admin-stat-value"><?= number_format($value) ?></div>
               </div>
            <?php endforeach; ?>
         </div>

         <!-- สัดส่วนสถานะ และ หมวดหมู่ -->
         <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
            <div class="admin-tray">
               <div class="admin-tray-label">Status Split</div>
               <section class="admin-card p-5">
                  <h2 class="font-medium text-gray-900">แยกตามสถานะ</h2>
                  <div class="mt-4 space-y-4">
                     <?php
                     $total = (int)($summary['total'] ?? 0);
                     foreach ($labels as $key => $label):
                        $count = (int)($statusRows[$key] ?? 0);
                        $pct = $total ? round($count / $total * 100) : 0;
                     ?>
                        <div>
                           <div class="mb-1 flex justify-between text-sm">
                              <span><?= h($label) ?></span>
                              <span class="text-gray-500"><?= $count ?> · <?= $pct ?>%</span>
                           </div>
                           <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                              <div class="h-full rounded-full bg-gray-800" style="width:<?= $pct ?>%"></div>
                           </div>
                        </div>
                     <?php endforeach; ?>
                  </div>
                  <?php if (!empty($summary['avg_hours'])): ?>
                     <p class="mt-5 border-t border-[#F1EFEC] pt-4 text-sm text-gray-500">
                        เวลาเฉลี่ยจนปิดงาน <strong class="text-gray-900"><?= number_format((float)$summary['avg_hours'], 1) ?> ชั่วโมง</strong>
                     </p>
                  <?php endif; ?>
               </section>
            </div>

            <div class="admin-tray">
               <div class="admin-tray-label">Categories</div>
               <section class="admin-card">
                  <div class="border-b border-[#ECEAE6] p-5">
                     <h2 class="font-medium text-gray-900">หมวดหมู่งานซ่อม</h2>
                  </div>
                  <div class="divide-y divide-[#F1EFEC]">
                     <?php if (!$categories): ?>
                        <p class="p-8 text-center text-sm text-gray-500">ไม่มีข้อมูลในช่วงวันที่นี้</p>
                     <?php endif; ?>
                     <?php foreach ($categories as $c): ?>
                        <div class="flex justify-between p-4 text-sm">
                           <span><?= h($c['name']) ?></span>
                           <span class="font-medium"><?= number_format((int)$c['total']) ?> รายการ</span>
                        </div>
                     <?php endforeach; ?>
                  </div>
               </section>
            </div>
         </div>

         <!-- คำขอรายวัน -->
         <div class="admin-tray">
            <div class="admin-tray-label">Daily Volume</div>
            <section class="admin-card">
               <div class="border-b border-[#ECEAE6] p-5">
                  <h2 class="font-medium text-gray-900">จำนวนคำขอรายวัน</h2>
               </div>
               <div class="overflow-x-auto admin-table-wrap">
                  <table class="w-full text-sm">
                     <thead class="bg-gray-50 text-xs text-gray-500">
                        <tr>
                           <th class="px-5 py-3 text-left font-medium">วันที่</th>
                           <th class="px-5 py-3 text-right font-medium">จำนวน</th>
                        </tr>
                     </thead>
                     <tbody class="divide-y">
                        <?php if (!$daily): ?>
                           <tr>
                              <td colspan="2" class="p-6 text-center text-gray-500">ไม่มีข้อมูล</td>
                           </tr>
                        <?php endif; ?>
                        <?php foreach ($daily as $d): ?>
                           <tr>
                              <td class="px-5 py-3"><?= h(date('d/m/Y', strtotime($d['day']))) ?></td>
                              <td class="px-5 py-3 text-right"><?= number_format((int)$d['total']) ?></td>
                           </tr>
                        <?php endforeach; ?>
                     </tbody>
                  </table>
               </div>
            </section>
         </div>

         <!-- ตารางรายการคำขอล่าสุด 100 รายการ -->
         <div class="admin-tray">
            <div class="admin-tray-label">Recent Requests</div>
            <section class="admin-card">
               <div class="border-b border-[#ECEAE6] p-5">
                  <h2 class="font-medium text-gray-900">รายการคำขอ</h2>
                  <p class="mt-1 text-xs text-gray-500">แสดงสูงสุด 100 รายการ</p>
               </div>
               <div class="overflow-x-auto admin-table-wrap">
                  <table class="w-full min-w-[700px] text-sm">
                     <thead class="bg-gray-50 text-xs text-gray-500">
                        <tr>
                           <th class="px-4 py-3 text-left font-medium">รหัส</th>
                           <th class="px-4 py-3 text-left font-medium">ผู้แจ้ง</th>
                           <th class="px-4 py-3 text-left font-medium">หมวดหมู่</th>
                           <th class="px-4 py-3 text-left font-medium">สถานะ</th>
                           <th class="px-4 py-3 text-left font-medium">วันที่แจ้ง</th>
                        </tr>
                     </thead>
                     <tbody class="divide-y divide-gray-100">
                        <?php if (!$tickets): ?>
                           <tr>
                              <td colspan="5" class="p-8 text-center text-gray-500">ไม่มีข้อมูล</td>
                           </tr>
                        <?php endif; ?>
                        <?php foreach ($tickets as $t): ?>
                           <tr class="hover:bg-gray-50/70 transition-colors">
                              <td class="px-4 py-3 font-mono font-medium text-gray-900">#<?= h($t['ticket_no']) ?></td>
                              <td class="px-4 py-3 text-gray-900">
                                 <div><?= h($t['first_name'] . ' ' . $t['last_name']) ?></div>
                                 <div class="text-xs text-gray-500">รหัส: <?= h($t['id_number'] ?? '—') ?></div>
                              </td>
                              <td class="px-4 py-3 text-gray-700"><?= h($t['category_name']) ?></td>
                              <td class="px-4 py-3">
                                 <span class="admin-chip st-<?= h($t['status']) ?>">
                                    <?= h($labels[$t['status']] ?? $t['status']) ?>
                                 </span>
                              </td>
                              <td class="px-4 py-3 text-gray-500"><?= h(date('d/m/Y H:i', strtotime($t['created_at']))) ?></td>
                           </tr>
                        <?php endforeach; ?>
                     </tbody>
                  </table>
               </div>
            </section>
         </div>

         <style>
            @media print {

               header,
               aside,
               #sidebarOverlay,
               form,
               .mb-6 nav {
                  display: none !important;
               }

               .lg\:ml-sidebar {
                  margin: 0 !important;
                  padding: 0 !important;
               }

               .p-6 {
                  padding: 0 !important;
               }

               body {
                  background: #fff !important;
               }
            }
         </style>
      </div>
   </div>
</body>
</html>
