<?php
declare(strict_types=1);

/**
 * REPS · admin/admin_settings.php — ตั้งค่าระบบ
 * - ปรับแต่งหน้าเว็บ (3.12 เฟส 1): ชื่อเว็บ ธีม สีหลัก โลโก้ ข้อความหน้าแรก ประกาศ footer เปิด/ปิดส่วนต่าง ๆ
 * - กติกาคิว (3.3): โหมดจ่ายงาน เวลารับงาน คะแนนหัก/โบนัส ช่วงเวลาทำงาน token cron
 * - Donate (3.14): เปิด/ปิด ข้อความ QR พร้อมเพย์
 * ทุกค่าเป็นข้อความล้วน (escape ตอนแสดงผล) ไม่รับ HTML/JavaScript
 */

$page_title = 'ตั้งค่าระบบ';
$current_page = 'settings';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/site_config.php';
require_once __DIR__ . '/../includes/private_storage.php';
require_once __DIR__ . '/../includes/db_helpers.php';

if (empty($_SESSION['admin_csrf'])) {
   $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

function h($v)
{
   return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}


/** key => [ชนิด, ความยาว/ช่วง] */
// ชื่อเว็บ ธีม โลโก้ เมนู ข้อความ และ footer ย้ายไปหน้า ปรับแต่งเว็บ (admin_site.php)
$fields = [
   'homepage_intro'             => ['text', 500],
   'homepage_announcement'      => ['text', 500],
   'homepage_show_announcement' => ['bool', 0],
   'homepage_show_steps'        => ['bool', 0],
   'homepage_show_faq'          => ['bool', 0],
   'queue_dispatch_mode'        => ['enum', ['auto', 'manual']],
   'queue_timeout_minutes'      => ['int', [1, 1440]],
   'queue_penalty_points'       => ['int', [0, 100]],

   'queue_work_start'           => ['time', 0],
   'queue_work_end'             => ['time', 0],
   'academic_year_start_month'  => ['int', [1, 12]],
   'thanks_enabled'             => ['bool', 0],
   'thanks_max_amount'          => ['int', [1, 100000]],
   'thanks_days'                => ['int', [1, 365]],

   'wallboard_enabled'          => ['bool', 0],
   'requester_sign_skip_days'   => ['int', [0, 60]],
   'intake_sign_skip_hours'     => ['int', [0, 720]],
   'sign_reminder_days'         => ['int', [0, 30]],
   'warroom_refresh_seconds'    => ['int', [10, 600]],
   'stale_warn_hours'           => ['int', [1, 720]],
   'stale_critical_hours'       => ['int', [1, 2160]],
   'wallboard_key'              => ['text', 40],
];

function settings_save(mysqli $conn, string $key, string $value): void
{
   db_exec(
      $conn,
      'INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
      'ss',
      [$key, $value]
   );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   if (!hash_equals($_SESSION['admin_csrf'], is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '')) {
      http_response_code(403);
      exit('คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
   }
   $errors = [];
   $values = [];
   foreach ($fields as $key => [$type, $rule]) {
      $raw = trim(is_string($_POST[$key] ?? null) ? $_POST[$key] : '');
      switch ($type) {
         case 'text':
            if (mb_strlen($raw) > $rule) $errors[] = "ข้อความ {$key} ยาวเกิน {$rule} ตัวอักษร";
            $values[$key] = str_replace(["\r\n", "\r"], "\n", $raw);
            break;
         case 'enum':
            if (!in_array($raw, $rule, true)) $errors[] = "ค่า {$key} ไม่ถูกต้อง";
            $values[$key] = $raw;
            break;
         case 'bool':
            $values[$key] = ($_POST[$key] ?? '') === '1' ? '1' : '0';
            break;
         case 'int':
            $int = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => $rule[0], 'max_range' => $rule[1]]]);
            if ($int === false) $errors[] = "ค่า {$key} ต้องเป็นตัวเลข {$rule[0]}–{$rule[1]}";
            $values[$key] = (string)$int;
            break;
         case 'color':
            if ($raw !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $raw)) $errors[] = 'สีหลักต้องอยู่ในรูปแบบ #RRGGBB';
            $values[$key] = $raw;
            break;
         case 'time':
            if ($raw !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $raw)) $errors[] = 'เวลาทำงานต้องอยู่ในรูปแบบ HH:MM';
            $values[$key] = $raw;
            break;
      }
   }
   if ($values['wallboard_key'] !== '' && !preg_match('/^[A-Za-z0-9_-]{4,40}$/', $values['wallboard_key'])) {
      $errors[] = 'รหัสเปิดจอสรุปงานต้องเป็นตัวอักษรอังกฤษ ตัวเลข - หรือ _ ยาว 4–40 ตัว';
   }
   if ((int)$values['stale_critical_hours'] <= (int)$values['stale_warn_hours']) {
      $errors[] = 'เกณฑ์งานค้างระดับวิกฤตต้องมากกว่าระดับเตือน';
   }
   if (($values['queue_work_start'] === '') !== ($values['queue_work_end'] === '')
      || ($values['queue_work_start'] !== '' && $values['queue_work_start'] >= $values['queue_work_end'])) {
      $errors[] = 'ช่วงเวลาทำงานต้องกรอกทั้งเวลาเริ่มและสิ้นสุด และเวลาเริ่มต้องน้อยกว่าเวลาสิ้นสุด (หรือเว้นว่างทั้งคู่)';
   }

   $newFiles = [];
   try {
      if (!$errors) {
         $current = app_get_settings($conn);
         $fileFields = []; // QR กลาง (donate) เลิกใช้แล้ว — Lab boy แต่ละคนตั้ง QR ของตัวเองที่หน้าโปรไฟล์
         foreach ($fileFields as $input => $key) {
            if (($_POST['remove_' . $input] ?? '') === '1') {
               $values[$key] = '';
            } elseif (isset($_FILES[$input]) && (int)$_FILES[$input]['error'] !== UPLOAD_ERR_NO_FILE) {
               $newFiles[$key] = app_store_uploaded_image($_FILES[$input], 'qr', 2097152, 800);
               $values[$key] = $newFiles[$key];
            }
         }
         if (($_POST['regenerate_cron_token'] ?? '') === '1') {
            $values['queue_cron_token'] = bin2hex(random_bytes(20));
         } elseif (($_POST['disable_cron_token'] ?? '') === '1') {
            $values['queue_cron_token'] = '';
         }
         db_transaction($conn, function () use ($conn, $values) {
            foreach ($values as $key => $value) {
               settings_save($conn, $key, $value);
            }
         });
         // ลบไฟล์เก่าที่ถูกแทนที่
         foreach ([] as $key) {
            if (array_key_exists($key, $values) && ($current[$key] ?? '') !== '' && $current[$key] !== $values[$key]) {
               app_delete_private_file($current[$key]);
            }
         }
         $_SESSION['admin_flash'] = ['message' => 'บันทึกการตั้งค่าแล้ว', 'type' => 'success'];
      } else {
         $_SESSION['admin_flash'] = ['message' => implode(' · ', $errors), 'type' => 'error'];
      }
   } catch (AppUserException $e) {
      foreach ($newFiles as $path) app_delete_private_file($path);
      $_SESSION['admin_flash'] = ['message' => $e->getMessage(), 'type' => 'error'];
   } catch (Throwable $e) {
      foreach ($newFiles as $path) app_delete_private_file($path);
      error_log('Settings save failed: ' . $e->getMessage());
      $_SESSION['admin_flash'] = ['message' => 'บันทึกการตั้งค่าไม่สำเร็จ', 'type' => 'error'];
   }
   header('Location: admin_settings.php', true, 303);
   exit;
}

$message = is_array($_SESSION['admin_flash'] ?? null) ? (string)($_SESSION['admin_flash']['message'] ?? '') : '';
$messageType = is_array($_SESSION['admin_flash'] ?? null) ? (string)($_SESSION['admin_flash']['type'] ?? 'success') : 'success';
unset($_SESSION['admin_flash']);

$s = app_get_settings($conn);
$cronToken = (string)($s['queue_cron_token'] ?? '');
$baseUrl = rtrim((string)(getenv('APP_BASE_URL') ?: ''), '/');

require_once __DIR__ . '/admin_header.php';
?>

<div class="admin-page-head">
   <nav class="admin-crumb">
      <a href="admin_dashboard.php"><?= tr('แดชบอร์ด', 'Dashboard') ?></a>
      <i class="fa-solid fa-chevron-right"></i>
      <span><?= tr('ตั้งค่าระบบ', 'Settings') ?></span>
   </nav>
   <h1 class="admin-page-title"><?= tr('ตั้งค่าระบบ', 'Settings') ?></h1>
   <p class="admin-page-sub"><?= tr('ปรับแต่งหน้าเว็บ กติกาคิวงาน และปุ่มสนับสนุนค่ากาแฟ (ข้อความทั้งหมดเป็นข้อความล้วน ไม่รองรับ HTML)', 'Customize the site, queue rules and the coffee-support button (plain text only, no HTML)') ?></p>
</div>

<?php if ($message): ?>
   <div class="mb-4 rounded-lg border p-3 text-sm <?= $messageType === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' ?>" role="status"><?= h($message) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="space-y-6">
   <input type="hidden" name="csrf" value="<?= h($_SESSION['admin_csrf']) ?>">

   <div class="admin-tray">
      <div class="admin-tray-label"><span>หน้าแรก</span><span>3.12</span></div>
      <section class="admin-card p-5 grid gap-4 sm:grid-cols-2 text-sm">
         <p class="sm:col-span-2 rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs text-gray-700">ชื่อเว็บ โลโก้ ไอคอนแท็บ ธีม ฟอนต์ เมนู ข้อความบนหน้าเว็บ และข้อมูลใน footer ย้ายไปที่ <a class="underline" href="admin_site.php">ปรับแต่งเว็บ</a></p>
         <label class="block sm:col-span-2">ข้อความแนะนำหน้าแรก (ว่าง = ข้อความเดิม)
            <textarea name="homepage_intro" maxlength="500" rows="2" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"><?= h($s['homepage_intro'] ?? '') ?></textarea>
         </label>
         <label class="block sm:col-span-2">ประกาศหน้าแรก
            <textarea name="homepage_announcement" maxlength="500" rows="2" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"><?= h($s['homepage_announcement'] ?? '') ?></textarea>
         </label>
         <fieldset class="sm:col-span-2 flex flex-wrap gap-x-6 gap-y-2">
            <legend class="mb-1 text-gray-600">ส่วนที่แสดงบนหน้าแรก</legend>
            <?php foreach (['homepage_show_announcement' => 'ประกาศ', 'homepage_show_steps' => 'ขั้นตอนการแจ้งซ่อม', 'homepage_show_faq' => 'คำถามที่พบบ่อย'] as $key => $label): ?>
               <label class="inline-flex items-center gap-2"><input type="checkbox" name="<?= h($key) ?>" value="1" <?= ($s[$key] ?? '0') === '1' ? 'checked' : '' ?>> <?= h($label) ?></label>
            <?php endforeach; ?>
         </fieldset>
      </section>
   </div>

   <div class="admin-tray">
      <div class="admin-tray-label"><span>คิวงานและคะแนน</span><span>3.3</span></div>
      <section class="admin-card p-5 grid gap-4 sm:grid-cols-3 text-sm">
         <label class="block">โหมดจ่ายงาน
            <select name="queue_dispatch_mode" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
               <option value="auto" <?= ($s['queue_dispatch_mode'] ?? 'auto') === 'auto' ? 'selected' : '' ?>>อัตโนมัติ (ตามลำดับคิว)</option>
               <option value="manual" <?= ($s['queue_dispatch_mode'] ?? '') === 'manual' ? 'selected' : '' ?>>admin เลือกสตาฟเอง</option>
            </select>
         </label>
         <label class="block">เวลากดรับงาน (นาที)
            <input type="number" name="queue_timeout_minutes" min="1" max="1440" value="<?= h($s['queue_timeout_minutes'] ?? '30') ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
         </label>
         <label class="block">คะแนนที่หักเมื่อไม่รับงาน
            <input type="number" name="queue_penalty_points" min="0" max="100" value="<?= h($s['queue_penalty_points'] ?? '5') ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
         </label>
         <label class="block">เริ่มนับคิว (HH:MM)
            <input type="time" name="queue_work_start" value="<?= h($s['queue_work_start'] ?? '') ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
         </label>
         <label class="block">หยุดนับคิว (HH:MM)
            <input type="time" name="queue_work_end" value="<?= h($s['queue_work_end'] ?? '') ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
         </label>
         <p class="sm:col-span-3 text-xs text-gray-500">นอกช่วงเวลาทำงาน ระบบจะไม่หักคะแนน และเริ่มนับเวลารับงานใหม่ตอนเปิดทำการ · เว้นว่างทั้งสองช่อง = นับตลอด 24 ชม. · โหมด manual ไม่หักคะแนนอัตโนมัติ</p>
         <label class="block">เดือนเริ่มปีการศึกษา (1–12)
            <input type="number" name="academic_year_start_month" min="1" max="12" value="<?= h($s['academic_year_start_month'] ?? '5') ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
         </label>
         <div class="sm:col-span-2 rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs">
            <div class="font-medium text-gray-800">cron ภายนอก (InfinityFree ไม่มี cron)</div>
            <?php if ($cronToken !== ''): ?>
               <p class="mt-1 break-all">ตั้ง cron-job.org ให้เรียกทุก 5 นาที: <code><?= h(($baseUrl ?: 'https://โดเมนของคุณ') . '/cron_queue.php?token=' . $cronToken) ?></code></p>
               <label class="mt-2 inline-flex items-center gap-2"><input type="checkbox" name="regenerate_cron_token" value="1"> สร้าง token ใหม่</label>
               <label class="mt-2 ml-4 inline-flex items-center gap-2 text-red-700"><input type="checkbox" name="disable_cron_token" value="1"> ปิด cron</label>
            <?php else: ?>
               <p class="mt-1 text-gray-600">ยังไม่เปิดใช้ ระบบจะตรวจคิวหมดเวลาเมื่อมีคนเปิดหน้าเว็บ</p>
               <label class="mt-2 inline-flex items-center gap-2"><input type="checkbox" name="regenerate_cron_token" value="1"> เปิดใช้และสร้าง token</label>
            <?php endif; ?>
         </div>
      </section>
   </div>

   <div class="admin-tray">
      <div class="admin-tray-label"><span>ขั้นตอนเซ็นและงานค้าง</span><span>v3</span></div>
      <section class="admin-card p-5 grid gap-4 sm:grid-cols-3 text-sm">
         <label class="block">ผู้แจ้งไม่เซ็นรับคืนเกินกี่วัน ครูจึงข้ามขั้นได้
            <input type="number" name="requester_sign_skip_days" min="0" max="60" value="<?= h($s['requester_sign_skip_days'] ?? '3') ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
            <span class="mt-1 block text-xs text-gray-500">0 = ไม่อนุญาตให้ข้าม ต้องรอผู้แจ้งเสมอ</span>
         </label>
         <label class="block">ผู้แจ้งไม่เซ็นรับทราบเช็คลิสต์ตอนรับเกินกี่ชั่วโมง ครูจึงข้ามขั้นได้
            <input type="number" name="intake_sign_skip_hours" min="0" max="720" value="<?= h($s['intake_sign_skip_hours'] ?? '24') ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
            <span class="mt-1 block text-xs text-gray-500">0 = ไม่อนุญาตให้ข้าม</span>
         </label>
         <label class="block">อีเมลเตือนผู้แจ้งที่ยังไม่เซ็น ทุกกี่วัน
            <input type="number" name="sign_reminder_days" min="0" max="30" value="<?= h($s['sign_reminder_days'] ?? '1') ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
            <span class="mt-1 block text-xs text-gray-500">0 = ไม่ส่งอีเมลเตือน</span>
         </label>
         <label class="block">รีเฟรชหน้าภาพรวมงานทุกกี่วินาที
            <input type="number" name="warroom_refresh_seconds" min="10" max="600" value="<?= h($s['warroom_refresh_seconds'] ?? '30') ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
         </label>
         <label class="block">งานค้าง ระดับเตือน (ชั่วโมงหลังรับงาน)
            <input type="number" name="stale_warn_hours" min="1" max="720" value="<?= h($s['stale_warn_hours'] ?? '24') ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
         </label>
         <label class="block">งานค้าง ระดับวิกฤต (ชั่วโมงหลังรับงาน)
            <input type="number" name="stale_critical_hours" min="1" max="2160" value="<?= h($s['stale_critical_hours'] ?? '72') ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
         </label>
         <p class="sm:col-span-3 text-xs text-gray-500">รายการอุปกรณ์ตั้งต้นของเช็คลิสต์ตอนรับแต่ละหมวดหมู่ แก้ได้ที่ <a class="underline" href="admin_checklists.php">จัดการเช็คลิสต์ตั้งต้น</a></p>
      </section>
   </div>

   <div class="admin-tray">
      <div class="admin-tray-label"><span>จอสรุปงาน (Wallboard)</span></div>
      <section class="admin-card p-5 grid gap-4 sm:grid-cols-2 text-sm">
         <?php
         $wallOn = ($s['wallboard_enabled'] ?? '1') === '1';
         $wallUrl = '../warboard.php' . (($s['wallboard_key'] ?? '') !== '' ? '?k=' . rawurlencode((string)$s['wallboard_key']) : '');
         ?>
         <p class="sm:col-span-2 text-gray-600">หน้าเต็มจอสำหรับขึ้นจอ LED/ทีวีรวมในห้อง เปิดดูได้โดยไม่ต้องเข้าสู่ระบบ และอัปเดตเองทุก 20 วินาที แสดงเฉพาะเลขใบงาน สถานที่ อาการ สถานะ และชื่อต้นของสตาฟ ไม่แสดงชื่อหรือเบอร์โทรของผู้แจ้ง</p>
         <label class="inline-flex items-center gap-2 sm:col-span-2"><input type="checkbox" name="wallboard_enabled" value="1" <?= $wallOn ? 'checked' : '' ?>> เปิดใช้จอสรุปงาน</label>
         <label class="block">รหัสเปิดจอ (ไม่บังคับ)
            <input type="text" name="wallboard_key" maxlength="40" pattern="[A-Za-z0-9_-]{4,40}" value="<?= h($s['wallboard_key'] ?? '') ?>" placeholder="เว้นว่าง = ใครมีลิงก์ก็เปิดได้" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
            <span class="mt-1 block text-xs text-gray-500">ถ้าตั้งรหัส ลิงก์จะต้องมี ?k=รหัส ต่อท้าย เหมาะเมื่อเว็บเปิดจากอินเทอร์เน็ตภายนอกได้</span>
         </label>
         <div class="flex items-end">
            <?php if ($wallOn): ?>
               <a href="<?= h($wallUrl) ?>" target="_blank" rel="noopener" class="admin-btn admin-btn-secondary inline-flex items-center gap-2"><i class="fa-solid fa-tv" aria-hidden="true"></i> เปิดจอสรุปงาน</a>
            <?php else: ?>
               <span class="text-xs text-gray-500">จอสรุปงานปิดอยู่</span>
            <?php endif; ?>
         </div>
      </section>
   </div>
   <div class="admin-tray">
      <div class="admin-tray-label"><span>ขอบคุณ Lab boy</span><span>ไม่บังคับ</span></div>
      <section class="admin-card p-5 grid gap-4 sm:grid-cols-2 text-sm">
         <label class="inline-flex items-center gap-2 sm:col-span-2"><input type="checkbox" name="thanks_enabled" value="1" <?= ($s['thanks_enabled'] ?? '0') === '1' ? 'checked' : '' ?>> เปิดให้ผู้แจ้งขอบคุณ Lab boy ที่ซ่อมใบงานของตัวเอง</label>
         <label class="block">ยอดเงินสูงสุดต่อครั้ง (บาท)
            <input type="number" name="thanks_max_amount" min="1" max="100000" value="<?= h($s['thanks_max_amount'] ?? '5000') ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
         </label>
         <label class="block">ขอบคุณได้ภายในกี่วันหลังผู้แจ้งเซ็นรับคืน
            <input type="number" name="thanks_days" min="1" max="365" value="<?= h($s['thanks_days'] ?? '30') ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
         </label>
         <p class="sm:col-span-2 text-xs text-gray-500">ผู้แจ้งสแกน QR พร้อมเพย์ของ Lab boy คนนั้นโดยตรง (Lab boy ตั้ง QR เองที่หน้าโปรไฟล์) หรือบันทึกว่าให้ขนม/เครื่องดื่ม · ผู้แจ้งบันทึก Lab boy กดยืนยันว่าได้รับ · ไม่เกี่ยวกับคะแนน · ดูรายการทั้งหมดที่ <a class="underline" href="admin_thanks.php">คำขอบคุณ Lab boy</a> · ระบบไม่รับเงินและไม่ตรวจสลิป</p>
      </section>
   </div>

   <div class="flex justify-end">
      <button class="admin-btn inline-flex items-center gap-2"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> บันทึกการตั้งค่า</button>
   </div>
</form>

      </div>
<?php $footerBase = '../'; $footerVariant = 'app'; require __DIR__ . '/../includes/site_footer.php'; ?>
   </div>
</body>
</html>
