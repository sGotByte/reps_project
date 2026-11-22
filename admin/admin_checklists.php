<?php
declare(strict_types=1);

/**
 * REPS · admin/admin_checklists.php — รายการตั้งต้นของเช็คลิสต์อุปกรณ์ตอนรับ แยกตามหมวดหมู่ (v3 3.1)
 * สตาฟเห็นรายการเหล่านี้เป็นค่าเริ่มต้นตอนบันทึกเช็คลิสต์ และเพิ่ม/ลบรายการเองได้ในใบงาน
 * การแก้รายการตั้งต้นไม่กระทบเช็คลิสต์ของใบงานที่บันทึกไปแล้ว
 */

$page_title = 'เช็คลิสต์ตั้งต้น';
$current_page = 'settings';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/db_helpers.php';

if (empty($_SESSION['admin_csrf'])) {
   $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

function h($v)
{
   return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

$categories = db_all($conn, 'SELECT id, name FROM categories ORDER BY name');
$categoryIds = array_map('intval', array_column($categories, 'id'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   if (!hash_equals($_SESSION['admin_csrf'], is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '')) {
      http_response_code(403);
      exit('คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
   }
   $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
   $categoryId = (int)($_POST['category_id'] ?? 0);
   $msg = ['message' => 'บันทึกแล้ว', 'type' => 'success'];
   try {
      if ($categoryId !== 0 && !in_array($categoryId, $categoryIds, true)) {
         throw new RuntimeException('หมวดหมู่ไม่ถูกต้อง');
      }
      if ($action === 'save_category') {
         // แทนที่รายการของหมวดนี้ทั้งชุดตามลำดับที่ส่งมา
         $names = (array)($_POST['name'] ?? []);
         $rows = [];
         foreach ($names as $i => $raw) {
            $name = trim(is_string($raw) ? $raw : '');
            if ($name === '') {
               continue;
            }
            $rows[] = [
               mb_substr($name, 0, 150, 'UTF-8'),
               ($_POST['type'][$i] ?? '') === 'main' ? 'main' : 'accessory',
               ($_POST['active'][$i] ?? '1') === '1' ? 1 : 0,
            ];
         }
         if (count($rows) > 30) {
            throw new RuntimeException('รายการต่อหมวดมีได้ไม่เกิน 30 รายการ');
         }
         db_transaction($conn, function () use ($conn, $categoryId, $rows) {
            if ($categoryId === 0) {
               db_exec($conn, 'DELETE FROM checklist_presets WHERE category_id IS NULL');
            } else {
               db_exec($conn, 'DELETE FROM checklist_presets WHERE category_id = ?', 'i', [$categoryId]);
            }
            foreach ($rows as $order => [$name, $type, $active]) {
               db_exec(
                  $conn,
                  'INSERT INTO checklist_presets (category_id, item_name, item_type, sort_order, is_active) VALUES (?, ?, ?, ?, ?)',
                  'issii',
                  [$categoryId === 0 ? null : $categoryId, $name, $type, $order + 1, $active]
               );
            }
         });
         $msg['message'] = 'บันทึกรายการตั้งต้นแล้ว (' . count($rows) . ' รายการ)';
      }
   } catch (Throwable $e) {
      error_log('Checklist preset save failed: ' . $e->getMessage());
      $msg = ['message' => $e instanceof RuntimeException ? $e->getMessage() : 'บันทึกไม่สำเร็จ กรุณาลองใหม่', 'type' => 'error'];
   }
   $_SESSION['admin_flash'] = $msg;
   header('Location: admin_checklists.php#cat-' . $categoryId, true, 303);
   exit;
}

$message = is_array($_SESSION['admin_flash'] ?? null) ? (string)($_SESSION['admin_flash']['message'] ?? '') : '';
$messageType = is_array($_SESSION['admin_flash'] ?? null) ? (string)($_SESSION['admin_flash']['type'] ?? 'success') : 'success';
unset($_SESSION['admin_flash']);

$presets = [];
foreach (db_all($conn, 'SELECT category_id, item_name, item_type, is_active FROM checklist_presets ORDER BY sort_order, id') as $p) {
   $presets[(int)($p['category_id'] ?? 0)][] = $p;
}
$groups = array_merge([['id' => 0, 'name' => 'ใช้กับทุกหมวดหมู่']], $categories);

require_once __DIR__ . '/admin_header.php';
?>
<div class="admin-page-head">
   <nav class="admin-crumb">
      <a href="admin_dashboard.php"><?= tr('แดชบอร์ด', 'Dashboard') ?></a>
      <i class="fa-solid fa-chevron-right"></i>
      <a href="admin_settings.php"><?= tr('ตั้งค่าระบบ', 'Settings') ?></a>
      <i class="fa-solid fa-chevron-right"></i>
      <span>เช็คลิสต์ตั้งต้น</span>
   </nav>
   <h1 class="admin-page-title">เช็คลิสต์อุปกรณ์ตั้งต้น</h1>
   <p class="admin-page-sub">รายการที่สตาฟเห็นเป็นค่าเริ่มต้นตอนรับอุปกรณ์เข้าซ่อม แยกตามหมวดหมู่ สตาฟเพิ่มหรือลบรายการเองได้ในแต่ละใบงาน</p>
</div>

<?php if ($message): ?>
   <div class="mb-4 rounded-lg border p-3 text-sm <?= $messageType === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' ?>" role="status"><?= h($message) ?></div>
<?php endif; ?>

<div class="grid gap-5 lg:grid-cols-2">
   <?php foreach ($groups as $g): $gid = (int)$g['id']; $rows = $presets[$gid] ?? []; ?>
      <div class="admin-tray" id="cat-<?= $gid ?>">
         <div class="admin-tray-label"><span><?= h($g['name']) ?></span><span><?= count($rows) ?> รายการ</span></div>
         <form method="post" class="admin-card p-4 space-y-2 text-sm" data-preset-form>
            <input type="hidden" name="csrf" value="<?= h($_SESSION['admin_csrf']) ?>">
            <input type="hidden" name="action" value="save_category">
            <input type="hidden" name="category_id" value="<?= $gid ?>">
            <div class="space-y-2" data-rows>
               <?php foreach (array_merge($rows, [['item_name' => '', 'item_type' => 'accessory', 'is_active' => 1]]) as $r): ?>
                  <div class="grid grid-cols-12 gap-2 items-center" data-row>
                     <input name="name[]" value="<?= h($r['item_name']) ?>" maxlength="150" placeholder="ชื่ออุปกรณ์" aria-label="ชื่ออุปกรณ์" class="col-span-6 rounded-lg border border-gray-300 px-2.5 py-2">
                     <select name="type[]" aria-label="ประเภท" class="col-span-3 rounded-lg border border-gray-300 px-2 py-2">
                        <option value="main" <?= $r['item_type'] === 'main' ? 'selected' : '' ?>>หลัก</option>
                        <option value="accessory" <?= $r['item_type'] !== 'main' ? 'selected' : '' ?>>พ่วง</option>
                     </select>
                     <select name="active[]" aria-label="ใช้งาน" class="col-span-3 rounded-lg border border-gray-300 px-2 py-2">
                        <option value="1" <?= (int)$r['is_active'] === 1 ? 'selected' : '' ?>>ใช้งาน</option>
                        <option value="0" <?= (int)$r['is_active'] !== 1 ? 'selected' : '' ?>>ปิด</option>
                     </select>
                  </div>
               <?php endforeach; ?>
            </div>
            <div class="flex items-center justify-between pt-1">
               <button type="button" class="text-sm underline" data-add>+ เพิ่มแถว</button>
               <button type="submit" class="admin-btn">บันทึก</button>
            </div>
            <p class="text-xs text-gray-500">เว้นชื่อว่าง = ลบรายการนั้น · ลำดับตามที่เรียงในหน้านี้</p>
         </form>
      </div>
   <?php endforeach; ?>
</div>
<script>
   document.querySelectorAll('[data-preset-form]').forEach(function (form) {
      form.querySelector('[data-add]').addEventListener('click', function () {
         var rows = form.querySelector('[data-rows]');
         var row = rows.querySelector('[data-row]:last-child').cloneNode(true);
         row.querySelector('input').value = '';
         row.querySelector('select[name="type[]"]').value = 'accessory';
         row.querySelector('select[name="active[]"]').value = '1';
         rows.appendChild(row);
         row.querySelector('input').focus();
      });
   });
</script>

      </div>
<?php $footerBase = '../'; $footerVariant = 'app'; require __DIR__ . '/../includes/site_footer.php'; ?>
   </div>
</body>
</html>
