<?php
declare(strict_types=1);

/**
 * REPS · admin/admin_signoff.php — ครู (admin) รับรองงานขั้นสุดท้าย (v3 หัวข้อ 4)
 *
 * รายการด้านซ้าย:
 *   รอครูรับรอง (awaiting_admin_sign) · ผู้แจ้งไม่ยอมรับ (disputed) · ผู้แจ้งยังไม่เซ็นเกินกำหนด (ข้ามขั้นได้)
 * การทำงานกับใบงานที่เลือก:
 *   sign       เซ็นรับรอง -> completed (จาก disputed ต้องมีเหตุผล)
 *   return     ส่งกลับให้ทีมช่างแก้ -> in_progress เริ่มรอบเซ็นใหม่ (ลายเซ็นรอบก่อนเก็บเป็นประวัติ)
 *   skip       ข้ามขั้นผู้แจ้งเมื่อเกินกำหนด (requester_sign_skip_days / intake_sign_skip_hours) พร้อมเหตุผล
 * ทุกคำสั่งตรวจสถานะและสิทธิ์ซ้ำใน ticket_service ภายในทรานแซกชัน
 */

$page_title = 'รับรองงาน';
$current_page = 'signoff';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/ticket_service.php';
require_once __DIR__ . '/../includes/ticket_view.php';
require_once __DIR__ . '/../includes/signature.php';

if (empty($_SESSION['admin_csrf'])) {
   $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

function h($v)
{
   return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

$adminId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   if (!hash_equals($_SESSION['admin_csrf'], is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '')) {
      http_response_code(403);
      exit('คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
   }
   $ticketId = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT) ?: 0;
   $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : 'sign';
   $signaturePath = null;
   try {
      if ($action === 'sign') {
         if (($_POST['confirm'] ?? '') !== '1') {
            throw new AppUserException('กรุณาติ๊กยืนยันว่าได้ตรวจสอบงานแล้ว');
         }
         [$signaturePath, $method] = signature_capture($conn, $adminId);
         ticket_signoff($conn, $ticketId, $adminId, $signaturePath, trim((string)($_POST['note'] ?? '')), $method);
         $_SESSION['admin_flash'] = ['message' => 'รับรองงานเรียบร้อยแล้ว สถานะเป็นเสร็จสมบูรณ์', 'type' => 'success'];
         // ไปใบงานที่รอรับรองถัดไปทันที
         $next = db_one($conn, "SELECT id FROM tickets WHERE status = 'awaiting_admin_sign' ORDER BY (urgency = 'urgent') DESC, stage_since ASC LIMIT 1");
         header('Location: admin_signoff.php?done=' . $ticketId . ($next ? '&id=' . (int)$next['id'] : ''), true, 303);
         exit;
      }
      if ($action === 'return') {
         ticket_return_to_staff($conn, $ticketId, $adminId, (string)($_POST['reason'] ?? ''));
         $_SESSION['admin_flash'] = ['message' => 'ส่งกลับให้ทีมช่างแก้ไขแล้ว (เริ่มรอบเซ็นใหม่)', 'type' => 'success'];
      } elseif ($action === 'skip') {
         ticket_skip_requester($conn, $ticketId, $adminId, (string)($_POST['reason'] ?? ''));
         $_SESSION['admin_flash'] = ['message' => 'ข้ามขั้นผู้แจ้งแล้ว บันทึกเหตุผลไว้ในประวัติใบงาน', 'type' => 'success'];
      } else {
         throw new AppUserException('คำสั่งไม่ถูกต้อง');
      }
      header('Location: admin_signoff.php?id=' . $ticketId, true, 303);
      exit;
   } catch (AppUserException $e) {
      $error = $e->getMessage();
   } catch (Throwable $e) {
      error_log('Signoff action "' . $action . '" failed for ticket ' . $ticketId . ': ' . $e->getMessage());
      $error = 'บันทึกไม่สำเร็จ กรุณาลองใหม่';
   }
   app_delete_private_file($signaturePath);
   $_SESSION['admin_flash'] = ['message' => $error, 'type' => 'error'];
   header('Location: admin_signoff.php?id=' . $ticketId, true, 303);
   exit;
}

$message = is_array($_SESSION['admin_flash'] ?? null) ? (string)($_SESSION['admin_flash']['message'] ?? '') : '';
$messageType = is_array($_SESSION['admin_flash'] ?? null) ? (string)($_SESSION['admin_flash']['type'] ?? 'success') : 'success';
unset($_SESSION['admin_flash']);

$selectedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$doneId = filter_input(INPUT_GET, 'done', FILTER_VALIDATE_INT) ?: 0;

$skipHours = ticket_setting_int($conn, 'intake_sign_skip_hours', 24);
$skipDays = ticket_setting_int($conn, 'requester_sign_skip_days', 3);
$listSelect = "SELECT t.id, t.ticket_no, t.location_note, t.urgency, t.status, t.stage_since, t.has_discrepancy,
                      s.first_name AS staff_first, s.last_name AS staff_last
                 FROM tickets t LEFT JOIN users s ON s.user_id = t.assigned_to";
$queues = [
   'admin'    => ['รอครูรับรอง', db_all($conn, "$listSelect WHERE t.status = 'awaiting_admin_sign' ORDER BY (t.urgency = 'urgent') DESC, t.stage_since ASC")],
   'disputed' => ['ผู้แจ้งไม่ยอมรับ', db_all($conn, "$listSelect WHERE t.status = 'disputed' ORDER BY t.stage_since ASC")],
   'overdue'  => ['ผู้แจ้งยังไม่เซ็น เกินกำหนด', db_all(
      $conn,
      "$listSelect WHERE (t.status = 'awaiting_intake_sign' AND ? > 0 AND t.stage_since <= NOW() - INTERVAL ? HOUR)
                      OR (t.status = 'awaiting_requester_sign' AND ? > 0 AND t.stage_since <= NOW() - INTERVAL ? DAY)
        ORDER BY t.stage_since ASC",
      'iiii',
      [$skipHours, $skipHours, $skipDays, $skipDays]
   )],
];
// เปิดหน้านี้โดยไม่เลือกใบงาน: แสดงใบแรกที่ต้องทำ (ด่วนก่อน รอนานก่อน)
if ($selectedId === 0) {
   foreach ($queues as [, $rows]) {
      if ($rows) {
         $selectedId = (int)$rows[0]['id'];
         break;
      }
   }
}

$ticket = $selectedId > 0 ? tv_ticket($conn, $selectedId) : null;
$items = $signoffs = $logs = $materials = $attachments = [];
if ($ticket) {
   $items = ticket_item_checks($conn, $selectedId);
   $ticket['has_intake'] = (bool)$items;
   $signoffs = ticket_signoffs($conn, $selectedId);
   $logs = tv_logs($conn, $selectedId);
   $materials = db_all($conn, 'SELECT m.qty_used, m.note, i.name, i.unit FROM ticket_materials m JOIN inventory_items i ON i.id = m.item_id WHERE m.ticket_id = ? ORDER BY m.id', 'i', [$selectedId]);
   $attachments = array_map('intval', array_column(db_all($conn, 'SELECT id FROM ticket_attachments WHERE ticket_id = ? ORDER BY id', 'i', [$selectedId]), 'id'));
}
$status = $ticket ? (string)$ticket['status'] : '';
[$canSkip, $skipWhy] = $ticket && in_array($status, ['awaiting_intake_sign', 'awaiting_requester_sign'], true) ? ticket_skip_window($conn, $ticket) : [false, ''];
$canSign = in_array($status, ['awaiting_admin_sign', 'disputed'], true);
$canReturn = in_array($status, ['inspected_ready', 'awaiting_requester_sign', 'awaiting_admin_sign', 'disputed'], true);

$admin_extra_head = tv_assets();
require_once __DIR__ . '/admin_header.php';
?>

<div class="admin-page-head">
   <nav class="admin-crumb">
      <a href="admin_dashboard.php"><?= tr('แดชบอร์ด', 'Dashboard') ?></a>
      <i class="fa-solid fa-chevron-right"></i>
      <span><?= tr('รับรองงาน', 'Sign-off') ?></span>
   </nav>
   <h1 class="admin-page-title"><?= tr('รับรองงานซ่อม', 'Sign off repairs') ?></h1>
   <p class="admin-page-sub">ขั้นสุดท้ายของการเซ็น 3 ฝ่าย (สตาฟ → ผู้แจ้ง → ครู) ตรวจเช็คลิสต์ ข้อแตกต่าง และลายเซ็นก่อนรับรอง</p>
</div>

<?php if ($message): ?>
   <div class="mb-4 rounded-lg border p-3 text-sm <?= $messageType === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' ?>" role="status">
      <?= h($message) ?>
      <?php if ($doneId): ?> · <a class="underline" href="../certificate.php?id=<?= (int)$doneId ?>" target="_blank" rel="noopener">เปิดใบรับรองงาน</a><?php endif; ?>
   </div>
<?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
   <div class="space-y-4 <?= $ticket ? 'order-2 lg:order-none' : '' ?>">
      <?php foreach ($queues as $qKey => [$qLabel, $rows]): ?>
         <?php if ($qKey !== 'admin' && !$rows) continue; ?>
         <div class="admin-tray">
            <div class="admin-tray-label"><span><?= h($qLabel) ?></span><span><?= count($rows) ?> งาน</span></div>
            <section class="admin-card divide-y divide-gray-100">
               <?php if (!$rows): ?>
                  <p class="p-6 text-center text-sm text-gray-500">ไม่มีงานรอรับรอง</p>
               <?php endif; ?>
               <?php foreach ($rows as $a): ?>
                  <a href="?id=<?= (int)$a['id'] ?>" class="block px-4 py-3 text-sm hover:bg-gray-50 <?= (int)$a['id'] === $selectedId ? 'bg-gray-50' : '' ?>">
                     <div class="flex items-center justify-between gap-2">
                        <span class="font-medium">#<?= h($a['ticket_no']) ?></span>
                        <span class="flex gap-1">
                           <?php if ((int)$a['has_discrepancy'] === 1): ?><span class="admin-chip st-disputed">ของไม่ครบ</span><?php endif; ?>
                           <?php if ($a['urgency'] === 'urgent'): ?><span class="admin-chip is-urgent">ด่วน</span><?php endif; ?>
                        </span>
                     </div>
                     <div class="text-xs text-gray-500"><?= h($a['location_note']) ?> · <?= h(trim(($a['staff_first'] ?? '') . ' ' . ($a['staff_last'] ?? ''))) ?></div>
                     <div class="text-[11px] text-gray-500"><?= h(TICKET_STATUS_LABELS[$a['status']] ?? $a['status']) ?> · ตั้งแต่ <?= h(tv_dt($a['stage_since'])) ?></div>
                  </a>
               <?php endforeach; ?>
            </section>
         </div>
      <?php endforeach; ?>
   </div>

   <div class="lg:col-span-2 space-y-4 <?= $ticket ? 'order-1 lg:order-none' : '' ?>">
      <?php if ($selectedId && !$ticket): ?>
         <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">ไม่พบใบงานนี้</div>
      <?php elseif ($ticket): ?>
         <section class="tv-card">
            <div class="tv-card-head">
               <h2 class="tv-card-title">ใบงาน #<?= h($ticket['ticket_no']) ?></h2>
               <span class="tv-card-sub"><?= h(TICKET_STATUS_LABELS[$status] ?? $status) ?><?= (int)$ticket['sign_round'] > 1 ? ' · รอบเซ็นที่ ' . (int)$ticket['sign_round'] : '' ?><?= $ticket['urgency'] === 'urgent' ? ' · ด่วน' : '' ?></span>
            </div>
            <div class="tv-card-body space-y-4"><?= tv_steps($status) ?>
               <?php if ($status === 'disputed'): ?>
                  <div class="tv-alert"><b>ผู้แจ้งไม่ยอมรับ:</b> <?= h($ticket['return_reason']) ?><br>เลือกได้ว่าจะส่งกลับให้ทีมช่างแก้ หรือรับรองปิดงานพร้อมระบุเหตุผล</div>
               <?php endif; ?>
               <?php if ((int)$ticket['has_discrepancy'] === 1): ?>
                  <div class="tv-alert"><b>ข้อแตกต่างตอนตรวจซ้ำ:</b> <?= h($ticket['discrepancy_note']) ?></div>
               <?php endif; ?>
               <?= tv_summary($ticket, '../', true) ?>
               <?php if ($materials): ?>
                  <div class="text-sm"><span class="tv-muted">วัสดุที่เบิกใช้:</span>
                     <?= h(implode(', ', array_map(static fn($m) => $m['name'] . ' ' . (int)$m['qty_used'] . ' ' . $m['unit'], $materials))) ?></div>
               <?php endif; ?>
               <?php if ($attachments): ?>
                  <div class="flex flex-wrap gap-3">
                     <?php foreach ($attachments as $n => $aid): ?>
                        <a class="tv-photo" href="../file.php?t=<?= (int)$ticket['id'] ?>&amp;a=<?= $aid ?>" target="_blank" rel="noopener">รูปตอนแจ้ง <?= $n + 1 ?></a>
                     <?php endforeach; ?>
                  </div>
               <?php endif; ?>
            </div>
         </section>

         <?php if ($items): ?>
            <section class="tv-card">
               <div class="tv-card-head"><h2 class="tv-card-title">เช็คลิสต์อุปกรณ์ ตอนรับ / ตอนส่งคืน</h2></div>
               <div class="tv-card-body"><?= tv_checklist($items, (int)$ticket['id'], '../') ?></div>
            </section>
         <?php endif; ?>

         <section class="tv-card">
            <div class="tv-card-head"><h2 class="tv-card-title">ลายเซ็น</h2></div>
            <div class="tv-card-body"><?= tv_signatures($signoffs, (int)$ticket['id'], '../', $ticket) ?></div>
         </section>

         <?php if ($canSign): ?>
            <section class="tv-card">
               <div class="tv-card-head"><h2 class="tv-card-title">เซ็นรับรองการจบงาน (ลำดับ 3 จาก 3)</h2></div>
               <form method="post" class="tv-card-body space-y-3" data-busy>
                  <input type="hidden" name="csrf" value="<?= h($_SESSION['admin_csrf']) ?>">
                  <input type="hidden" name="action" value="sign">
                  <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
                  <?= signature_field($conn, $adminId, '../', 'ลายเซ็นครูผู้รับรอง (' . ($_SESSION['user_name'] ?? '') . ')') ?>
                  <label class="block text-sm">หมายเหตุการรับรอง<?= $status === 'disputed' ? ' <span class="text-red-600">* ต้องระบุเหตุผลที่รับรองทั้งที่ผู้แจ้งโต้แย้ง</span>' : ' (ถ้ามี)' ?>
                     <input type="text" name="note" maxlength="255" <?= $status === 'disputed' ? 'required minlength="5"' : '' ?> class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                  </label>
                  <label class="flex items-start gap-2 text-sm">
                     <input type="checkbox" name="confirm" value="1" required class="mt-1">
                     <span>ข้าพเจ้าได้ตรวจผลการซ่อม เช็คลิสต์ และข้อแตกต่างทั้งหมดแล้ว และรับรองว่างานนี้เสร็จสมบูรณ์</span>
                  </label>
                  <div class="flex justify-end"><button type="submit" class="admin-btn inline-flex items-center gap-2"><i class="fa-solid fa-file-signature" aria-hidden="true"></i> เซ็นรับรองงาน</button></div>
               </form>
            </section>
         <?php endif; ?>

         <?php if ($canReturn): ?>
            <section class="tv-card">
               <div class="tv-card-head"><h2 class="tv-card-title">ไม่เห็นด้วย: ส่งกลับให้ทีมช่างแก้ไข</h2>
                  <span class="tv-card-sub">ลายเซ็นรอบนี้เก็บเป็นประวัติ และเริ่มเซ็นรอบใหม่</span></div>
               <form method="post" class="tv-card-body space-y-3" onsubmit="return confirm('ส่งใบงานกลับให้ทีมช่างแก้ไข?');">
                  <input type="hidden" name="csrf" value="<?= h($_SESSION['admin_csrf']) ?>">
                  <input type="hidden" name="action" value="return">
                  <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
                  <label class="block text-sm">เหตุผลที่ส่งกลับ <span class="text-red-600">*</span>
                     <textarea name="reason" rows="2" required minlength="5" maxlength="500" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></textarea>
                  </label>
                  <div class="flex justify-end"><button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm">ส่งกลับให้แก้</button></div>
               </form>
            </section>
         <?php endif; ?>

         <?php if (in_array($status, ['awaiting_intake_sign', 'awaiting_requester_sign'], true)): ?>
            <section class="tv-card">
               <div class="tv-card-head"><h2 class="tv-card-title">ข้ามขั้นผู้แจ้ง</h2>
                  <span class="tv-card-sub"><?= $status === 'awaiting_intake_sign' ? 'รอเซ็นรับทราบตอนรับ' : 'รอเซ็นรับคืน' ?> ตั้งแต่ <?= h(tv_dt($ticket['stage_since'])) ?></span></div>
               <?php if ($canSkip): ?>
                  <form method="post" class="tv-card-body space-y-3" onsubmit="return confirm('ยืนยันข้ามขั้นผู้แจ้ง? ระบบจะบันทึกว่าครูเป็นผู้ข้ามขั้นพร้อมเหตุผล');">
                     <input type="hidden" name="csrf" value="<?= h($_SESSION['admin_csrf']) ?>">
                     <input type="hidden" name="action" value="skip">
                     <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
                     <label class="block text-sm">เหตุผล <span class="text-red-600">*</span>
                        <textarea name="reason" rows="2" required minlength="5" maxlength="500" placeholder="เช่น ติดต่อผู้แจ้งไม่ได้ 3 วัน ได้รับอุปกรณ์คืนแล้วที่ห้องพักครู" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></textarea>
                     </label>
                     <div class="flex justify-end"><button type="submit" class="admin-btn">ข้ามขั้นผู้แจ้ง</button></div>
                  </form>
               <?php else: ?>
                  <p class="tv-card-body text-sm text-gray-600"><?= h($skipWhy) ?> (ตั้งค่าได้ที่ ตั้งค่าระบบ)</p>
               <?php endif; ?>
            </section>
         <?php endif; ?>

         <section class="tv-card">
            <div class="tv-card-head"><h2 class="tv-card-title">ประวัติ</h2></div>
            <div class="tv-card-body"><?= tv_timeline($logs) ?></div>
         </section>
      <?php else: ?>
         <div class="rounded-lg border border-gray-200 bg-white p-6 text-sm text-gray-500">ไม่มีใบงานที่ต้องรับรองตอนนี้</div>
      <?php endif; ?>
   </div>
</div>
<script>
   document.querySelectorAll('form[data-busy]').forEach(function (f) {
      f.addEventListener('submit', function (e) {
         if (e.defaultPrevented) return;
         var b = f.querySelector('button[type=submit]');
         if (b) setTimeout(function () { b.disabled = true; }, 0);
      });
   });
</script>

      </div>
<?php $footerBase = '../'; $footerVariant = 'app'; require __DIR__ . '/../includes/site_footer.php'; ?>
   </div>
</body>
</html>
