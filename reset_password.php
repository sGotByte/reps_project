<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';

header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, max-age=0');

$csrfToken = app_csrf_token();
$token = is_string($_POST['token'] ?? $_GET['token'] ?? null) ? (string)($_POST['token'] ?? $_GET['token']) : '';
$message = '';
$messageType = 'error';
$resetComplete = false;
$validToken = (bool)preg_match('/\A[a-f0-9]{64}\z/', $token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   if (!app_csrf_is_valid($_POST['csrf'] ?? null)) {
      http_response_code(403);
      exit('คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
   }

   $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
   $confirmPassword = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

   if (strlen($password) < 8 || strlen($password) > 4096) {
      $message = 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร';
   } elseif ($password !== $confirmPassword) {
      $message = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน';
   } elseif (!$validToken) {
      $message = 'ลิงก์นี้ไม่ถูกต้องหรือหมดอายุแล้ว กรุณาขอลิงก์ใหม่';
   } else {
      $tokenHash = hash('sha256', $token);
      $passwordHash = password_hash($password, PASSWORD_DEFAULT);

      if ($conn->begin_transaction()) {
         try {
            $lookup = $conn->prepare("SELECT prt.id, prt.user_id FROM password_reset_tokens prt JOIN users u ON u.user_id = prt.user_id WHERE prt.token_hash = ? AND prt.used_at IS NULL AND prt.expires_at > NOW() AND u.status = 'active' LIMIT 1 FOR UPDATE");
            if (!$lookup) {
               throw new RuntimeException($conn->error);
            }
            $lookup->bind_param('s', $tokenHash);
            if (!$lookup->execute()) {
               throw new RuntimeException($lookup->error);
            }
            $tokenRow = $lookup->get_result()->fetch_assoc();
            $lookup->close();

            if (!$tokenRow) {
               $conn->rollback();
               $message = 'ลิงก์นี้ไม่ถูกต้องหรือหมดอายุแล้ว กรุณาขอลิงก์ใหม่';
            } else {
               $userId = (int)$tokenRow['user_id'];
               // ตั้งรหัสใหม่ด้วยลิงก์ = ไม่ต้องเปลี่ยนรหัสชั่วคราวอีก (fallback ถ้ายังไม่ได้รัน migration 20261120)
               $update = $conn->prepare('UPDATE users SET password = ?, must_change_password = 0 WHERE user_id = ?')
                  ?: $conn->prepare('UPDATE users SET password = ? WHERE user_id = ?');
               if (!$update) {
                  throw new RuntimeException($conn->error);
               }
               $update->bind_param('si', $passwordHash, $userId);
               if (!$update->execute()) {
                  throw new RuntimeException($update->error);
               }
               $update->close();

               $consume = $conn->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
               if (!$consume) {
                  throw new RuntimeException($conn->error);
               }
               $consume->bind_param('i', $userId);
               if (!$consume->execute() || $consume->affected_rows < 1) {
                  throw new RuntimeException($consume->error ?: 'Reset token was already used');
               }
               $consume->close();

               if (!$conn->commit()) {
                  throw new RuntimeException($conn->error);
               }
               $resetComplete = true;
               $messageType = 'success';
               $message = 'รหัสผ่านใหม่ถูกบันทึกแล้ว เข้าสู่ระบบด้วยรหัสผ่านใหม่ได้เลย';
            }
         } catch (Throwable $e) {
            $conn->rollback();
            error_log('Password reset failed: ' . $e->getMessage());
            $message = 'ตั้งรหัสผ่านใหม่ไม่สำเร็จ กรุณาลองใหม่';
         }
      } else {
         $message = 'ตั้งรหัสผ่านใหม่ไม่สำเร็จ กรุณาลองใหม่';
      }
   }
}

if (!$resetComplete && $validToken) {
   $tokenHash = hash('sha256', $token);
   $check = $conn->prepare("SELECT id FROM password_reset_tokens WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1");
   if ($check) {
      $check->bind_param('s', $tokenHash);
      $check->execute();
      $tokenExists = $check->get_result()->num_rows > 0;
      $check->close();
      if (!$tokenExists && $message === '') {
         $message = 'ลิงก์นี้ไม่ถูกต้องหรือหมดอายุแล้ว กรุณาขอลิงก์ใหม่';
      }
   } elseif ($message === '') {
      $message = 'ตรวจสอบลิงก์ไม่ได้ กรุณาลองใหม่ภายหลัง';
   }
} elseif (!$validToken && $message === '') {
   $message = 'ลิงก์นี้ไม่ถูกต้องหรือหมดอายุแล้ว กรุณาขอลิงก์ใหม่';
}

function reset_h($value): string
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
<<<<<<< HEAD
   <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
   <link rel="stylesheet" href="assets/css/app.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/app.css') ?>">
   <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
   <link rel="stylesheet" href="assets/css/base.css?v=18">
</head>
<body class="flex min-h-screen flex-col bg-[#f6f6f4] font-kanit text-gray-900">
   <div class="flex flex-1 items-center justify-center px-4 py-10">
=======
   <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
   <link rel="stylesheet" href="assets/css/app.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/app.css') ?>">
   <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
</head>
<body class="flex min-h-screen items-center justify-center bg-[#f6f6f4] px-4 py-10 font-kanit text-gray-900">
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
   <main class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
      <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-neutral-900 text-white"><i class="fa-solid fa-lock"></i></div>
      <h1 class="mt-5 text-center text-2xl font-medium">ตั้งรหัสผ่านใหม่</h1>
      <p class="mt-2 text-center text-sm leading-6 text-gray-500">ตั้งรหัสผ่านใหม่สำหรับบัญชี REPS ของคุณ</p>

      <?php if ($message !== ''): ?>
         <div class="mt-5 rounded-lg border p-3 text-center text-sm leading-6 <?= $messageType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-900' ?>" role="status"><?= reset_h($message) ?></div>
      <?php endif; ?>

      <?php if (!$resetComplete && $validToken): ?>
         <form method="post" class="mt-6 space-y-4">
            <input type="hidden" name="csrf" value="<?= reset_h($csrfToken) ?>">
            <input type="hidden" name="token" value="<?= reset_h($token) ?>">
            <label class="block text-sm font-medium text-gray-700" for="password">รหัสผ่านใหม่</label>
            <input id="password" name="password" type="password" minlength="8" maxlength="4096" autocomplete="new-password" required class="-mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-neutral-900 focus:outline-none">
            <label class="block text-sm font-medium text-gray-700" for="confirm_password">ยืนยันรหัสผ่านใหม่</label>
            <input id="confirm_password" name="confirm_password" type="password" minlength="8" maxlength="4096" autocomplete="new-password" required class="-mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-neutral-900 focus:outline-none">
            <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-neutral-700"><i class="fa-solid fa-floppy-disk"></i> บันทึกรหัสผ่านใหม่</button>
            <a href="login.php" class="mx-auto flex w-full items-center justify-center gap-2 rounded-lg border border-gray-500 px-4 py-2.5 text-sm text-gray-500 hover:border-black hover:text-gray-900">กลับหน้าเข้าสู่ระบบ</a>
         </form>
      <?php else: ?>
         <a href="login.php" class="mt-6 flex w-full items-center justify-center gap-2 rounded-lg border border-gray-500 px-4 py-2.5 text-sm text-gray-500 hover:border-black hover:text-gray-900">กลับหน้าเข้าสู่ระบบ</a>
      <?php endif; ?>
   </main>
<<<<<<< HEAD
   </div>
<?php $footerBase = ''; $footerVariant = 'light'; require __DIR__ . '/includes/site_footer.php'; ?>
=======
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
</body>
</html>
