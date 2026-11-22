<?php
declare(strict_types=1);

/**
 * REPS · change_password.php — ตั้งรหัสผ่านใหม่หลังครูรีเซ็ตเป็นรหัสชั่วคราว
 * user_auth ส่งผู้ใช้ที่ must_change_password = 1 มาหน้านี้ก่อนเข้าหน้าอื่นทุกหน้า
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/user_auth.php';
require_once __DIR__ . '/includes/password_reset.php';

header('Referrer-Policy: no-referrer');

$userId = app_require_active_user($conn, 'login.php', null);
$home = ltrim(role_home_url(current_role()), '/');

$row = db_one($conn, 'SELECT first_name, must_change_password FROM users WHERE user_id = ?', 'i', [$userId]);
if (!$row || (int)$row['must_change_password'] !== 1) {
   header('Location: ' . $home, true, 303);
   exit;
}

$csrfToken = app_csrf_token();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   if (!app_csrf_is_valid($_POST['csrf'] ?? null)) {
      http_response_code(403);
      exit('คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
   }
   $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
   $confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
   try {
      $hash = pw_set_new($conn, $userId, $password, $confirm);
      session_regenerate_id(true);
      $_SESSION['auth_password_hash'] = $hash;
      audit_user($conn, $userId, $userId, 'ตั้งรหัสผ่านใหม่หลังได้รับรหัสชั่วคราว');
      header('Location: ' . $home, true, 303);
      exit;
   } catch (AppUserException $e) {
      $message = $e->getMessage();
   } catch (Throwable $e) {
      error_log('change_password failed: ' . $e->getMessage());
      $message = 'บันทึกรหัสผ่านไม่สำเร็จ กรุณาลองใหม่';
   }
}

function cp_h($value): string
{
   return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <meta name="referrer" content="no-referrer">
   <title>ตั้งรหัสผ่านใหม่ | REPS</title>
   <link rel="icon" type="image/svg+xml" href="assets/images/logo/reps-icon.svg">
   <link rel="alternate icon" type="image/png" sizes="32x32" href="assets/images/logo/favicon-32.png">
   <link rel="apple-touch-icon" href="assets/images/logo/apple-touch-icon.png">
   <link rel="preconnect" href="https://fonts.googleapis.com">
   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
   <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
   <link rel="stylesheet" href="assets/css/app.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/app.css') ?>">
   <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
   <link rel="stylesheet" href="assets/css/base.css?v=18">
</head>
<body class="flex min-h-screen flex-col bg-[#f6f6f4] font-kanit text-gray-900">
   <div class="flex flex-1 items-center justify-center px-4 py-10">
   <main class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
      <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-neutral-900 text-white"><i class="fa-solid fa-key"></i></div>
      <h1 class="mt-5 text-center text-2xl font-medium">ตั้งรหัสผ่านใหม่</h1>
      <p class="mt-2 text-center text-sm leading-6 text-gray-500">ครูผู้ดูแลรีเซ็ตรหัสผ่านของคุณเป็นรหัสชั่วคราว กรุณาตั้งรหัสผ่านใหม่ที่จำได้ก่อนใช้งานต่อ</p>

      <?php if ($message !== ''): ?>
         <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-3 text-center text-sm leading-6 text-amber-900" role="alert"><?= cp_h($message) ?></div>
      <?php endif; ?>

      <form method="post" class="mt-6 space-y-4">
         <input type="hidden" name="csrf" value="<?= cp_h($csrfToken) ?>">
         <label class="block text-sm font-medium text-gray-700" for="password">รหัสผ่านใหม่</label>
         <input id="password" name="password" type="password" minlength="8" maxlength="4096" autocomplete="new-password" required autofocus class="-mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-neutral-900 focus:outline-none">
         <p class="-mt-2 text-xs text-gray-500">อย่างน้อย 8 ตัวอักษร และไม่ซ้ำกับรหัสชั่วคราว</p>
         <label class="block text-sm font-medium text-gray-700" for="confirm_password">ยืนยันรหัสผ่านใหม่</label>
         <input id="confirm_password" name="confirm_password" type="password" minlength="8" maxlength="4096" autocomplete="new-password" required class="-mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-neutral-900 focus:outline-none">
         <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-neutral-700"><i class="fa-solid fa-floppy-disk"></i> บันทึกรหัสผ่านใหม่</button>
         <a href="logout.php" class="mx-auto flex w-full items-center justify-center gap-2 rounded-lg border border-gray-500 px-4 py-2.5 text-sm text-gray-500 hover:border-black hover:text-gray-900">ออกจากระบบ</a>
      </form>
   </main>
   </div>
<?php $footerBase = ''; $footerVariant = 'light'; require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
