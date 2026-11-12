<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/gmail_smtp.php';

$csrfToken = app_csrf_token();
$message = '';
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   if (!app_csrf_is_valid($_POST['csrf'] ?? null)) {
      http_response_code(403);
      exit('คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
   }

   $emailValue = trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');
   if (!filter_var($emailValue, FILTER_VALIDATE_EMAIL) || mb_strlen($emailValue) > 255) {
      $message = 'รูปแบบอีเมลไม่ถูกต้อง กรุณาตรวจสอบแล้วลองใหม่';
   } else {
      $stmt = $conn->prepare("SELECT user_id, email, first_name FROM users WHERE email = ? AND status = 'active' LIMIT 1");
      if (!$stmt) {
         error_log('Password reset account query could not be prepared: ' . $conn->error);
         $message = 'ตรวจสอบอีเมลไม่ได้ กรุณาลองใหม่ภายหลัง';
      } else {
         $stmt->bind_param('s', $emailValue);
         if (!$stmt->execute()) {
            error_log('Password reset account query failed: ' . $stmt->error);
            $stmt->close();
            $message = 'ตรวจสอบอีเมลไม่ได้ กรุณาลองใหม่ภายหลัง';
         } else {
            $account = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$account) {
               $message = 'ไม่พบอีเมลนี้ในบัญชีที่เปิดใช้งาน กรุณาตรวจสอบอีเมลอีกครั้ง';
            } else {
               $userId = (int)$account['user_id'];
               $rateStmt = $conn->prepare('SELECT COUNT(*) AS total FROM password_reset_tokens WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)');
               if (!$rateStmt) {
                  error_log('Password reset rate query could not be prepared: ' . $conn->error);
                  $message = 'ตรวจสอบคำขอไม่ได้ กรุณาลองใหม่ภายหลัง';
               } else {
                  $rateStmt->bind_param('i', $userId);
                  if (!$rateStmt->execute()) {
                     error_log('Password reset rate query failed: ' . $rateStmt->error);
                     $rateStmt->close();
                     $message = 'ตรวจสอบคำขอไม่ได้ กรุณาลองใหม่ภายหลัง';
                  } else {
                     $requestCount = (int)$rateStmt->get_result()->fetch_assoc()['total'];
                     $rateStmt->close();

                     if ($requestCount >= 3) {
                        $message = 'ส่งคำขอครบ 3 ครั้งในช่วง 1 ชั่วโมงแล้ว กรุณารอสักครู่แล้วลองใหม่';
                     } else {
                        $baseUrl = rtrim((string)(getenv('APP_BASE_URL') ?: ''), '/');
                        $baseParts = $baseUrl !== '' ? parse_url($baseUrl) : false;
                        $validBaseUrl = filter_var($baseUrl, FILTER_VALIDATE_URL)
                           && is_array($baseParts)
                           && in_array(strtolower((string)($baseParts['scheme'] ?? '')), ['https', 'http'], true)
                           && !isset($baseParts['user'])
                           && !isset($baseParts['pass'])
                           && !isset($baseParts['query'])
                           && !isset($baseParts['fragment']);

                        if (!$validBaseUrl || !gmailSmtpIsConfigured()) {
                           error_log('Password reset email is not configured (check APP_BASE_URL and Gmail SMTP credentials)');
                           $message = 'พบอีเมลในระบบ แต่บริการส่งเมลยังไม่พร้อม กรุณาติดต่อผู้ดูแลระบบ';
                        } else {
                           $conn->query('DELETE FROM password_reset_tokens WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY) OR (used_at IS NOT NULL AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY))');
                           $invalidateOld = $conn->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');

                           if (!$invalidateOld) {
                              error_log('Could not invalidate old password reset tokens: ' . $conn->error);
                              $message = 'สร้างลิงก์รีเซ็ตรหัสผ่านไม่สำเร็จ กรุณาลองใหม่ภายหลัง';
                           } else {
                              $invalidateOld->bind_param('i', $userId);
                              if (!$invalidateOld->execute()) {
                                 error_log('Could not invalidate old password reset tokens: ' . $invalidateOld->error);
                                 $message = 'สร้างลิงก์รีเซ็ตรหัสผ่านไม่สำเร็จ กรุณาลองใหม่ภายหลัง';
                              }
                              $invalidateOld->close();

                              if ($message === '') {
                                 $token = bin2hex(random_bytes(32));
                                 $tokenHash = hash('sha256', $token);
                                 $insert = $conn->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))');
                                 if (!$insert) {
                                    error_log('Could not prepare password reset token insert: ' . $conn->error);
                                    $message = 'สร้างลิงก์รีเซ็ตรหัสผ่านไม่สำเร็จ กรุณาลองใหม่ภายหลัง';
                                 } else {
                                    $insert->bind_param('is', $userId, $tokenHash);
                                    if (!$insert->execute()) {
                                       error_log('Could not create password reset token: ' . $insert->error);
                                       $message = 'สร้างลิงก์รีเซ็ตรหัสผ่านไม่สำเร็จ กรุณาลองใหม่ภายหลัง';
                                    } else {
                                       $resetUrl = $baseUrl . '/reset_password.php?token=' . rawurlencode($token);
                                       $body = "สวัสดี " . $account['first_name'] . ",\n\n"
                                          . "มีคำขอตั้งรหัสผ่านใหม่สำหรับบัญชี REPS ของคุณ\n"
                                          . "เปิดลิงก์นี้เพื่อตั้งรหัสผ่านใหม่ (ลิงก์ใช้ได้ 1 ชั่วโมงและใช้ได้ครั้งเดียว):\n"
                                          . $resetUrl . "\n\n"
                                          . "หากคุณไม่ได้เป็นผู้ขอ สามารถเพิกเฉยต่ออีเมลนี้ได้\n";
                                       try {
                                          sendGmailSmtpMail($account['email'], 'ตั้งรหัสผ่าน REPS ใหม่', $body);
                                          $message = 'พบอีเมลในระบบและส่งลิงก์ตั้งรหัสผ่านแล้ว กรุณาตรวจ Inbox และ Spam';
                                       } catch (Throwable $mailError) {
                                          $consume = $conn->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE token_hash = ?');
                                          if ($consume) {
                                             $consume->bind_param('s', $tokenHash);
                                             $consume->execute();
                                             $consume->close();
                                          }
                                          error_log('Password reset email could not be sent for user ' . $userId . ': ' . $mailError->getMessage());
                                          $message = 'พบอีเมลในระบบ แต่ส่งเมลไม่สำเร็จ กรุณาลองใหม่ภายหลัง';
                                       }
                                    }
                                    $insert->close();
                                 }
                              }
                           }
                        }
                     }
                  }
               }
            }
         }
      }
   }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && filter_var($emailValue, FILTER_VALIDATE_EMAIL) && mb_strlen($emailValue) <= 255) {
   // Keep the specific result from the account lookup/SMTP send above.
   // The generic message is only a fallback for valid requests that did not
   // reach one of the explicit success/error branches.
   if ($message === '') {
      $message = 'หากอีเมลนี้มีบัญชีที่ใช้งานได้ ระบบจะส่งลิงก์ให้ กรุณาตรวจ Inbox และ Spam';
   }
}

function forgot_h($value): string
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
   <title>ลืมรหัสผ่าน | REPS</title>
   <link rel="icon" type="image/svg+xml" href="assets/images/logo/reps-icon.svg">
   <link rel="alternate icon" type="image/png" sizes="32x32" href="assets/images/logo/favicon-32.png">
   <link rel="apple-touch-icon" href="assets/images/logo/apple-touch-icon.png">
   <link rel="preconnect" href="https://fonts.googleapis.com">
   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
   <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
   <link rel="stylesheet" href="assets/css/app.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/app.css') ?>">
   <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
</head>
<body class="flex min-h-screen items-center justify-center bg-[#f6f6f4] px-4 py-10 font-kanit text-gray-900">
   <main class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
      <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-neutral-900 text-white"><i class="fa-solid fa-key"></i></div>
      <h1 class="mt-5 text-center text-2xl font-medium">ลืมรหัสผ่าน?</h1>
      <p class="mt-2 text-center text-sm leading-6 text-gray-500">กรอกอีเมลที่ใช้กับบัญชี ระบบจะส่งลิงก์ตั้งรหัสผ่านใหม่ให้</p>

      <?php if ($message !== ''): ?>
         <div class="mt-5 rounded-lg border border-blue-200 bg-blue-50 p-3 text-center text-sm leading-6 text-blue-900" role="status"><?= forgot_h($message) ?></div>
      <?php endif; ?>

      <form method="post" class="mt-6 space-y-4">
         <input type="hidden" name="csrf" value="<?= forgot_h($csrfToken) ?>">
         <label for="email" class="block text-sm font-medium text-gray-700">อีเมล</label>
         <input id="email" name="email" type="email" autocomplete="email" maxlength="255" required value="<?= forgot_h($emailValue) ?>" class="-mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-neutral-900 focus:outline-none">
         <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-neutral-700"><i class="fa-regular fa-paper-plane"></i> ส่งลิงก์ตั้งรหัสผ่าน</button>
         <a href="login.php" class="mx-auto flex w-full items-center justify-center gap-2 text-sm text-gray-500 rounded-lg px-4 py-2.5 border border-gray-500 hover:text-gray-900 hover:border-black">กลับหน้าเข้าสู่ระบบ</a>

      </form>
   </main>
</body>
</html>
