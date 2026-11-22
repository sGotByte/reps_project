<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/user_auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/private_storage.php';
require_once __DIR__ . '/includes/user_menu.php';

$userId = app_require_active_user($conn, 'login.php', null);
$csrfToken = app_csrf_token();
$message = '';
$messageType = 'info';

// ดึงข้อมูลแผนกทั้งหมด
$departmentResult = $conn->query('SELECT d_id, name FROM department ORDER BY name');
$departments = $departmentResult ? $departmentResult->fetch_all(MYSQLI_ASSOC) : [];
$departmentIds = array_map('intval', array_column($departments, 'd_id'));

// ดึงข้อมูลผู้ใช้ปัจจุบัน
$userStmt = $conn->prepare('SELECT u.user_id, u.id_number, u.first_name, u.last_name, u.user_type, u.department_id, d.name AS department_name, u.phone_number, u.email, u.password, u.role, u.status, u.created_at, u.avatar_path, u.signature_path, u.promptpay_qr_path FROM users u LEFT JOIN department d ON d.d_id = u.department_id WHERE u.user_id = ? LIMIT 1');
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

if (!$user) {
   $_SESSION = [];
   session_destroy();
   header('Location: login.php');
   exit;
}

if (isset($_SESSION['profile_flash']) && is_array($_SESSION['profile_flash'])) {
   [$messageType, $message] = $_SESSION['profile_flash'] + ['info', ''];
   unset($_SESSION['profile_flash']);
}

// เก็บ hash ปัจจุบันจากฐานข้อมูลแยกไว้ตรวจสอบรหัสผ่าน
$currentPasswordHash = $user['password'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   if (!app_csrf_is_valid($_POST['csrf'] ?? null)) {
      http_response_code(403);
      exit('คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
   }

   // ---------- รูปโปรไฟล์ (ฟอร์มแยก) → บันทึกแล้ว redirect กลับ (PRG)
   $avatarAction = is_string($_POST['avatar_action'] ?? null) ? $_POST['avatar_action'] : '';
   if ($avatarAction === 'upload' || $avatarAction === 'remove') {
      $oldAvatar = $user['avatar_path'] ?? null;
      try {
         $newAvatar = null;
         if ($avatarAction === 'upload') {
            $newAvatar = app_store_uploaded_image($_FILES['avatar'] ?? [], 'av', 3 * 1048576, 512);
         }
         $stmt = $conn->prepare('UPDATE users SET avatar_path = ? WHERE user_id = ?');
         $stmt->bind_param('si', $newAvatar, $userId);
         if (!$stmt->execute()) {
            app_delete_private_file($newAvatar);
            throw new RuntimeException('avatar update failed');
         }
         $stmt->close();
         if ($oldAvatar && $oldAvatar !== $newAvatar) {
            app_delete_private_file($oldAvatar);
         }
         $_SESSION['profile_flash'] = ['success', $avatarAction === 'upload' ? 'เปลี่ยนรูปโปรไฟล์เรียบร้อยแล้ว' : 'ลบรูปโปรไฟล์แล้ว'];
      } catch (AppUserException $e) {
         $_SESSION['profile_flash'] = ['error', $e->getMessage()];
      } catch (Throwable $e) {
         error_log('profile avatar: ' . $e->getMessage());
         $_SESSION['profile_flash'] = ['error', 'บันทึกรูปโปรไฟล์ไม่สำเร็จ กรุณาลองใหม่'];
      }
      header('Location: profile.php#avatar', true, 303);
      exit;
   }

   // ---------- ลายเซ็นอิเล็กทรอนิกส์ที่เก็บไว้ใช้เซ็นใบงาน (v3 4.2)
   $sigAction = is_string($_POST['signature_action'] ?? null) ? $_POST['signature_action'] : '';
   if ($sigAction === 'upload' || $sigAction === 'remove') {
      $oldSig = $user['signature_path'] ?? null;
      try {
         $newSig = null;
         if ($sigAction === 'upload') {
            $newSig = app_store_uploaded_image($_FILES['signature'] ?? [], 'usig', 1048576, 900);
         }
         $stmt = $conn->prepare('UPDATE users SET signature_path = ? WHERE user_id = ?');
         $stmt->bind_param('si', $newSig, $userId);
         if (!$stmt->execute()) {
            app_delete_private_file($newSig);
            throw new RuntimeException('signature update failed');
         }
         $stmt->close();
         // ลายเซ็นที่ใช้ในใบงานแล้วเป็นไฟล์สำเนาแยก ลบไฟล์ในโปรไฟล์ได้โดยไม่กระทบหลักฐานเดิม
         if ($oldSig && $oldSig !== $newSig) {
            app_delete_private_file($oldSig);
         }
         $_SESSION['profile_flash'] = ['success', $sigAction === 'upload' ? 'บันทึกลายเซ็นอิเล็กทรอนิกส์แล้ว เลือกใช้ได้ตอนเซ็นใบงาน' : 'ลบลายเซ็นที่บันทึกไว้แล้ว'];
      } catch (AppUserException $e) {
         $_SESSION['profile_flash'] = ['error', $e->getMessage()];
      } catch (Throwable $e) {
         error_log('profile signature: ' . $e->getMessage());
         $_SESSION['profile_flash'] = ['error', 'บันทึกลายเซ็นไม่สำเร็จ กรุณาลองใหม่'];
      }
      header('Location: profile.php#signature', true, 303);
      exit;
   }

   // ---------- QR พร้อมเพย์สำหรับรับคำขอบคุณจากผู้แจ้ง (เฉพาะสตาฟ)
   $qrAction = is_string($_POST['tipqr_action'] ?? null) ? $_POST['tipqr_action'] : '';
   if (($qrAction === 'upload' || $qrAction === 'remove') && ($user['role'] ?? '') === 'staff') {
      $oldQr = $user['promptpay_qr_path'] ?? null;
      try {
         $newQr = $qrAction === 'upload' ? app_store_uploaded_image($_FILES['tipqr'] ?? [], 'tipqr', 2097152, 900) : null;
         $stmt = $conn->prepare('UPDATE users SET promptpay_qr_path = ? WHERE user_id = ?');
         $stmt->bind_param('si', $newQr, $userId);
         if (!$stmt->execute()) {
            app_delete_private_file($newQr);
            throw new RuntimeException('qr update failed');
         }
         $stmt->close();
         if ($oldQr && $oldQr !== $newQr) {
            app_delete_private_file($oldQr);
         }
         $_SESSION['profile_flash'] = ['success', $qrAction === 'upload' ? 'บันทึก QR พร้อมเพย์แล้ว ผู้แจ้งที่อยากขอบคุณจะเห็น QR นี้' : 'ลบ QR พร้อมเพย์แล้ว'];
      } catch (AppUserException $e) {
         $_SESSION['profile_flash'] = ['error', $e->getMessage()];
      } catch (Throwable $e) {
         error_log('profile tipqr: ' . $e->getMessage());
         $_SESSION['profile_flash'] = ['error', 'บันทึก QR ไม่สำเร็จ กรุณาลองใหม่'];
      }
      header('Location: profile.php#tipqr', true, 303);
      exit;
   }

   $firstName = trim(is_string($_POST['first_name'] ?? null) ? $_POST['first_name'] : '');
   $lastName = trim(is_string($_POST['last_name'] ?? null) ? $_POST['last_name'] : '');
   $email = trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');
   $phone = trim(is_string($_POST['phone_number'] ?? null) ? $_POST['phone_number'] : '');
   $departmentId = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);
   $currentPassword = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
   $newPassword = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
   $confirmPassword = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

   // เก็บค่าที่เพิ่งกรอกเพื่อแสดงผลคงไว้ในฟอร์ม
   $user['first_name'] = $firstName;
   $user['last_name'] = $lastName;
   $user['email'] = $email;
   $user['phone_number'] = $phone;
   $user['department_id'] = $departmentId ?: 0;

   // การตรวจสอบความถูกต้อง (Validation)
   if ($firstName === '' || $lastName === '' || mb_strlen($firstName) > 100 || mb_strlen($lastName) > 100) {
      $message = 'กรุณากรอกชื่อและนามสกุลให้ถูกต้อง';
      $messageType = 'error';
   } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
      $message = 'รูปแบบอีเมลไม่ถูกต้อง';
      $messageType = 'error';
   } elseif (!preg_match('/^0\d{8,9}$/', $phone)) {
      $message = 'เบอร์โทรศัพท์ไม่ถูกต้อง (เช่น 0812345678)';
      $messageType = 'error';
   } elseif (!$departmentId || !in_array($departmentId, $departmentIds, true)) {
      $message = 'กรุณาเลือกแผนกจากรายการ';
      $messageType = 'error';
   } elseif ($newPassword !== '' && ($currentPassword === '' || !password_verify($currentPassword, $currentPasswordHash))) {
      $message = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
      $messageType = 'error';
   } elseif ($newPassword !== '' && strlen($newPassword) < 8) {
      $message = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร';
      $messageType = 'error';
   } elseif ($newPassword !== '' && $newPassword !== $confirmPassword) {
      $message = 'รหัสผ่านใหม่และการยืนยันไม่ตรงกัน';
      $messageType = 'error';
   } elseif ($newPassword === '' && ($currentPassword !== '' || $confirmPassword !== '')) {
      $message = 'กรอกรหัสผ่านใหม่และยืนยันรหัสผ่านให้ครบ หรือเว้นว่างทั้งหมดหากไม่ต้องการเปลี่ยน';
      $messageType = 'error';
   } else {
      // ตรวจสอบอีเมลซ้ำกับผู้ใช้อื่น
      $duplicateCheck = $conn->prepare('SELECT user_id FROM users WHERE email = ? AND user_id <> ? LIMIT 1');
      if (!$duplicateCheck) {
         $message = 'ตรวจสอบข้อมูลไม่สำเร็จ กรุณาลองใหม่';
         $messageType = 'error';
      } else {
         $duplicateCheck->bind_param('si', $email, $userId);
         $duplicateCheck->execute();
         $duplicate = $duplicateCheck->get_result()->fetch_assoc();
         $duplicateCheck->close();

         if ($duplicate) {
            $message = 'อีเมลนี้ถูกใช้กับบัญชีอื่นแล้ว';
            $messageType = 'error';
         } else {
            if ($newPassword !== '') {
               $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
               $update = $conn->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, phone_number = ?, department_id = ?, password = ? WHERE user_id = ?');
               if ($update) {
                  $update->bind_param('ssssisi', $firstName, $lastName, $email, $phone, $departmentId, $passwordHash, $userId);
               }
            } else {
               $update = $conn->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, phone_number = ?, department_id = ? WHERE user_id = ?');
               if ($update) {
                  $update->bind_param('ssssii', $firstName, $lastName, $email, $phone, $departmentId, $userId);
               }
            }

            if (!$update) {
               $message = 'บันทึกโปรไฟล์ไม่สำเร็จ กรุณาลองใหม่';
               $messageType = 'error';
            } elseif ($update->execute()) {
               $_SESSION['user_name'] = $firstName . ' ' . $lastName;
               if ($newPassword !== '') {
                  $currentPasswordHash = $passwordHash;
                  $_SESSION['auth_password_hash'] = $passwordHash;
                  $user['password'] = $passwordHash;
               }
               $message = 'บันทึกข้อมูลโปรไฟล์เรียบร้อยแล้ว';
               $messageType = 'success';
            } else {
               $message = 'บันทึกโปรไฟล์ไม่สำเร็จ กรุณาลองใหม่';
               $messageType = 'error';
            }

            if ($update) {
               $update->close();
            }
         }
      }
   }
}

function profile_h($value): string
{
   return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
$profileHome = role_home_url(current_role());
$profileInitial = mb_strtoupper(mb_substr(trim((string)$user['first_name']), 0, 1, 'UTF-8'), 'UTF-8');
$profileTypeLabels = ['student' => 'นักศึกษา', 'teacher' => 'ครู/อาจารย์', 'staff' => 'บุคลากร'];
$profileMeta = trim(($profileTypeLabels[$user['user_type']] ?? (string)$user['user_type'])
   . (!empty($user['department_name']) ? ' · ' . $user['department_name'] : ''));
?>
<!DOCTYPE html>
<html lang="th">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>แก้ไขโปรไฟล์ | REPS</title>
   <link rel="icon" type="image/svg+xml" href="assets/images/logo/reps-icon.svg">
   <link rel="alternate icon" type="image/png" sizes="32x32" href="assets/images/logo/favicon-32.png">
   <link rel="apple-touch-icon" href="assets/images/logo/apple-touch-icon.png">
   <link rel="preconnect" href="https://fonts.googleapis.com">
   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
   <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
   <link rel="stylesheet" href="assets/css/app.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/app.css') ?>">
   <link rel="stylesheet" href="assets/css/portal-shell.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/portal-shell.css') ?>">
   <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
   <script src="https://cdn.tailwindcss.com"></script>
   <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Kanit', 'sans-serif'], kanit: ['Kanit', 'sans-serif'] } } } };</script>
   <style>
      details > summary { list-style: none; }
      details > summary::-webkit-details-marker { display: none; }
      .admin-shell { background: #FAFAF8; color: #161616; font-family: 'Kanit', sans-serif; }
      .admin-topbar { background: #FFFFFF !important; border-color: #ECEAE6 !important; box-shadow: 0 1px 2px rgba(22, 22, 22, .035); }
      .admin-brand-label { font-family: 'Kanit', sans-serif; font-size: 11px; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; }
      .admin-brand-sub { display: block; margin-top: 2px; color: #6B6862; font-family: 'Kanit', sans-serif; font-size: 11px; letter-spacing: .12em; }
      .admin-user-avatar { background: #F7F6F4; border: 1px solid #ECEAE6; color: #55534F; font-family: 'Kanit', sans-serif; font-size: 13px; font-weight: 600; }
      .admin-btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 38px; padding: 0 14px; border: 1px solid #161616; border-radius: 999px; background: #161616; color: #FFFFFF; font-size: 13px; font-weight: 500; text-decoration: none; transition: background-color .15s ease; cursor: pointer; }
      .admin-btn:hover { background: #2A2927; }
      .admin-btn-secondary { background: #FFFFFF; border-color: #D9D6D0; color: #55534F; }
      .admin-btn-secondary:hover { background: #F7F6F4; color: #161616; border-color: #C9C6C0; }
      input:focus-visible, select:focus-visible { outline: 2px solid #161616; outline-offset: 2px; }
   </style>
   <link rel="stylesheet" href="assets/css/base.css?v=18">
   <link rel="stylesheet" href="assets/css/requester-theme.css?v=2">
</head>

<body class="admin-shell min-h-screen flex flex-col antialiased<?= current_role() === 'user' ? ' rq-on' : '' ?>">
    <!-- Topbar Header ขยายเต็มจอแต่เนื้อหาจัดกึ่งกลางสอดคล้องกับ Dashboard -->
    <?php if (current_role() === 'user'): ?>
    <?php $rqActive = 'profile'; $rqBottom = false; require __DIR__ . '/includes/requester_nav.php'; ?>
<?php else: ?>
<header class="admin-topbar sticky top-0 z-30 h-header border-b w-full bg-white">
        <div class="max-w-6xl w-full mx-auto px-4 sm:px-7 flex items-center justify-between h-16">
            <a href="<?= profile_h($profileHome) ?>" class="flex items-center gap-3 no-underline">
                <img src="assets/images/logo/reps-logo.svg" alt="REPS" class="h-6 w-auto">
            </a>
            <div class="flex items-center gap-3">
               <?php require_once __DIR__ . '/includes/notifications.php'; echo notif_bell($conn, ''); ?>
               <?= user_menu_render($conn, '') ?>
            </div>
        </div>
    </header>
<?php endif; ?>

   <main class="flex-1 max-w-6xl w-full mx-auto p-4 sm:p-7">
      <header class="mb-6">
         <nav class="flex items-center gap-2 mb-2 text-[10.5px] font-mono uppercase tracking-wider text-[#6B6862]" aria-label="Breadcrumb">
             <a href="<?= profile_h($profileHome) ?>" class="font-kanit hover:text-[#161616] no-underline"><?= tr('หน้าหลัก', 'Home') ?></a>
             <i class="fa-solid fa-chevron-right text-[8px] opacity-50" aria-hidden="true"></i>
             <span class="font-kanit text-[#161616]"><?= tr('ตั้งค่าโปรไฟล์', 'Profile settings') ?></span>
         </nav>
         <h1 class="text-2xl sm:text-3xl font-normal tracking-tight text-[#161616]"><?= tr('แก้ไขโปรไฟล์', 'Edit profile') ?></h1>
         <p class="mt-1 text-xs text-[#6B6862]"><?= tr('จัดการข้อมูลส่วนตัว ข้อมูลติดต่อ และความปลอดภัยของบัญชีผู้ใช้งาน', 'Manage your personal details, contact info and account security') ?></p>
      </header>

      <?php if ($message !== ''): ?>
         <div class="mb-6 flex items-center gap-3 p-4 rounded-xl border text-xs <?= $messageType === 'error' ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900' ?>" role="status">
            <i class="fa-solid <?= $messageType === 'error' ? 'fa-circle-exclamation text-rose-600' : 'fa-circle-check text-emerald-600' ?>" aria-hidden="true"></i>
            <span><?= profile_h($message) ?></span>
         </div>
      <?php endif; ?>

      <section id="avatar" class="pf-avatar bg-white rounded-2xl border border-[#EAE8E4] shadow-sm mb-6" aria-labelledby="avatar-heading">
         <div class="pf-avatar__photo">
            <?= user_avatar_html($user, '', 'user-avatar--xl') ?>
            <img id="avatarPreview" alt="" hidden>
         </div>
         <div class="pf-avatar__body">
            <h2 id="avatar-heading" class="text-sm font-semibold text-[#161616]">รูปโปรไฟล์</h2>
            <p class="text-xs text-[#6B6862] mt-0.5">แสดงบนแถบเมนูและรายชื่อในระบบ · JPG, PNG หรือ WEBP ไม่เกิน 3 MB · ระบบย่อรูปและครอปเป็นวงกลมให้อัตโนมัติ</p>
            <div class="flex flex-wrap items-center gap-2 mt-3">
               <form method="post" enctype="multipart/form-data" id="avatarForm">
                  <input type="hidden" name="csrf" value="<?= profile_h($csrfToken) ?>">
                  <input type="hidden" name="avatar_action" value="upload">
                  <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/webp" class="sr-only">
                  <label for="avatarInput" class="admin-btn text-xs rounded-lg cursor-pointer" id="avatarPick"><i class="fa-solid fa-camera" aria-hidden="true"></i> <?= empty($user['avatar_path']) ? 'อัปโหลดรูป' : 'เปลี่ยนรูป' ?></label>
               </form>
               <?php if (!empty($user['avatar_path'])): ?>
                  <form method="post" onsubmit="return confirm('ลบรูปโปรไฟล์?');">
                     <input type="hidden" name="csrf" value="<?= profile_h($csrfToken) ?>">
                     <input type="hidden" name="avatar_action" value="remove">
                     <button type="submit" class="admin-btn admin-btn-secondary text-xs rounded-lg">ลบรูป</button>
                  </form>
               <?php endif; ?>
               <span id="avatarStatus" class="text-xs text-[#6B6862]" role="status" aria-live="polite"></span>
            </div>
         </div>
      </section>

      <section id="signature" class="pf-avatar bg-white rounded-2xl border border-[#EAE8E4] shadow-sm mb-6" aria-labelledby="signature-heading">
         <div class="pf-avatar__photo">
            <?php if (!empty($user['signature_path'])): ?>
               <img src="file.php?k=mysig&amp;v=<?= profile_h(substr(basename((string)$user['signature_path']), 0, 20)) ?>" alt="ลายเซ็นที่บันทึกไว้" style="width:160px;height:72px;object-fit:contain;border:1px solid #ECEAE6;border-radius:12px;background:#fff;padding:6px">
            <?php else: ?>
               <div style="width:160px;height:72px;display:grid;place-items:center;border:1px dashed #D4D0C9;border-radius:12px;color:#8A867F;font-size:12px">ยังไม่มีลายเซ็น</div>
            <?php endif; ?>
         </div>
         <div class="pf-avatar__body">
            <h2 id="signature-heading" class="text-sm font-semibold text-[#161616]">ลายเซ็นอิเล็กทรอนิกส์</h2>
            <p class="text-xs text-[#6B6862] mt-0.5">ใช้เลือกแทนการวาดเมื่อเซ็นใบงาน · แนะนำ PNG พื้นโปร่งใส ไม่เกิน 1 MB · ทุกครั้งที่เซ็น ระบบเก็บสำเนาแยกไว้กับใบงาน เปลี่ยนหรือลบภายหลังได้โดยไม่กระทบใบงานเดิม</p>
            <div class="flex flex-wrap items-center gap-2 mt-3">
               <form method="post" enctype="multipart/form-data" id="sigForm">
                  <input type="hidden" name="csrf" value="<?= profile_h($csrfToken) ?>">
                  <input type="hidden" name="signature_action" value="upload">
                  <input type="file" name="signature" id="sigInput" accept="image/png,image/jpeg,image/webp" class="sr-only" onchange="if (this.files.length) { if (this.files[0].size > 1048576) { alert('ไฟล์ใหญ่เกิน 1 MB'); this.value = ''; return; } this.form.submit(); }">
                  <label for="sigInput" class="admin-btn text-xs rounded-lg cursor-pointer"><i class="fa-solid fa-signature" aria-hidden="true"></i> <?= empty($user['signature_path']) ? 'อัปโหลดลายเซ็น' : 'เปลี่ยนลายเซ็น' ?></label>
               </form>
               <?php if (!empty($user['signature_path'])): ?>
                  <form method="post" onsubmit="return confirm('ลบลายเซ็นที่บันทึกไว้?');">
                     <input type="hidden" name="csrf" value="<?= profile_h($csrfToken) ?>">
                     <input type="hidden" name="signature_action" value="remove">
                     <button type="submit" class="admin-btn admin-btn-secondary text-xs rounded-lg">ลบลายเซ็น</button>
                  </form>
               <?php endif; ?>
            </div>
         </div>
      </section>

      <?php if (($user['role'] ?? '') === 'staff'): ?>
      <section id="tipqr" class="pf-avatar bg-white rounded-2xl border border-[#EAE8E4] shadow-sm mb-6" aria-labelledby="tipqr-heading">
         <div class="pf-avatar__photo">
            <?php if (!empty($user['promptpay_qr_path'])): ?>
               <img src="file.php?k=tipqr&amp;u=<?= (int)$userId ?>&amp;v=<?= profile_h(substr(basename((string)$user['promptpay_qr_path']), 0, 20)) ?>" alt="QR พร้อมเพย์ที่บันทึกไว้" style="width:96px;height:96px;object-fit:contain;border:1px solid #ECEAE6;border-radius:12px;background:#fff;padding:4px">
            <?php else: ?>
               <div style="width:96px;height:96px;display:grid;place-items:center;border:1px dashed #D4D0C9;border-radius:12px;color:#8A867F;font-size:12px;text-align:center">ยังไม่มี QR</div>
            <?php endif; ?>
         </div>
         <div class="pf-avatar__body">
            <h2 id="tipqr-heading" class="text-sm font-semibold text-[#161616]">QR พร้อมเพย์สำหรับรับคำขอบคุณ</h2>
            <p class="text-xs text-[#6B6862] mt-0.5">ผู้แจ้งที่ประทับใจงานของคุณสแกนโอนให้ได้โดยตรง (ไม่บังคับ) · แสดงเฉพาะผู้แจ้งของใบงานที่คุณซ่อม · ใช้รูป QR จากแอปธนาคารของคุณ JPG/PNG ไม่เกิน 2 MB</p>
            <div class="flex flex-wrap items-center gap-2 mt-3">
               <form method="post" enctype="multipart/form-data">
                  <input type="hidden" name="csrf" value="<?= profile_h($csrfToken) ?>">
                  <input type="hidden" name="tipqr_action" value="upload">
                  <input type="file" name="tipqr" id="tipqrInput" accept="image/png,image/jpeg,image/webp" class="sr-only" onchange="if (this.files.length) { if (this.files[0].size > 2097152) { alert('ไฟล์ใหญ่เกิน 2 MB'); this.value = ''; return; } this.form.submit(); }">
                  <label for="tipqrInput" class="admin-btn text-xs rounded-lg cursor-pointer"><i class="fa-solid fa-qrcode" aria-hidden="true"></i> <?= empty($user['promptpay_qr_path']) ? 'อัปโหลด QR' : 'เปลี่ยน QR' ?></label>
               </form>
               <?php if (!empty($user['promptpay_qr_path'])): ?>
                  <form method="post" onsubmit="return confirm('ลบ QR พร้อมเพย์?');">
                     <input type="hidden" name="csrf" value="<?= profile_h($csrfToken) ?>">
                     <input type="hidden" name="tipqr_action" value="remove">
                     <button type="submit" class="admin-btn admin-btn-secondary text-xs rounded-lg">ลบ QR</button>
                  </form>
               <?php endif; ?>
            </div>
         </div>
      </section>
      <?php endif; ?>

      <form method="post" class="bg-white rounded-2xl border border-[#EAE8E4] shadow-sm overflow-hidden">
         <input type="hidden" name="csrf" value="<?= profile_h($csrfToken) ?>">

         <div class="p-6 sm:p-8 space-y-6">
            <!-- ข้อมูลบัญชี -->
            <div>
               <div class="flex items-center gap-2.5 pb-3 border-b border-[#ECEAE6] mb-4">
                  <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#F7F6F4] text-[#55534F] text-xs"><i class="fa-solid fa-id-card" aria-hidden="true"></i></span>
                  <h2 class="text-xs font-semibold uppercase tracking-wider text-[#161616]">ข้อมูลบัญชีระบบ</h2>
               </div>
               <div class="grid gap-4 sm:grid-cols-2">
                  <div>
                     <label class="block text-xs font-medium text-[#55534F] mb-1.5">รหัสนักศึกษา / รหัสบุคลากร</label>
                     <input type="text" value="<?= profile_h($user['id_number']) ?>" readonly class="w-full rounded-xl border border-[#D9D6D0] bg-[#FAFAF8] px-3.5 py-2.5 text-xs text-[#6B6862] cursor-not-allowed">
                  </div>
                  <div>
                     <label class="block text-xs font-medium text-[#55534F] mb-1.5">ประเภทผู้ใช้</label>
                     <select disabled class="w-full rounded-xl border border-[#D9D6D0] bg-[#FAFAF8] px-3.5 py-2.5 text-xs text-[#6B6862] cursor-not-allowed">
                        <?php foreach (['student' => 'นักศึกษา', 'teacher' => 'ครู/อาจารย์', 'staff' => 'บุคลากร'] as $typeValue => $typeLabel): ?>
                           <option value="<?= profile_h($typeValue) ?>" <?= $user['user_type'] === $typeValue ? 'selected' : '' ?>><?= profile_h($typeLabel) ?></option>
                        <?php endforeach; ?>
                     </select>
                  </div>
               </div>
            </div>

            <!-- ข้อมูลส่วนตัวและการติดต่อ -->
            <div>
               <div class="flex items-center gap-2.5 pb-3 border-b border-[#ECEAE6] mb-4">
                  <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#F7F6F4] text-[#55534F] text-xs"><i class="fa-solid fa-user-pen" aria-hidden="true"></i></span>
                  <h2 class="text-xs font-semibold uppercase tracking-wider text-[#161616]">ข้อมูลส่วนตัวและการติดต่อ</h2>
               </div>
               <div class="grid gap-4 sm:grid-cols-2">
                  <div>
                     <label class="block text-xs font-medium text-[#55534F] mb-1.5">ชื่อจริง <span class="text-rose-600">*</span></label>
                     <input type="text" name="first_name" maxlength="100" required value="<?= profile_h($user['first_name']) ?>" class="w-full rounded-xl border border-[#D9D6D0] bg-white px-3.5 py-2.5 text-xs text-[#161616] focus:border-[#161616] focus:outline-none">
                  </div>
                  <div>
                     <label class="block text-xs font-medium text-[#55534F] mb-1.5">นามสกุล <span class="text-rose-600">*</span></label>
                     <input type="text" name="last_name" maxlength="100" required value="<?= profile_h($user['last_name']) ?>" class="w-full rounded-xl border border-[#D9D6D0] bg-white px-3.5 py-2.5 text-xs text-[#161616] focus:border-[#161616] focus:outline-none">
                  </div>
                  <div>
                     <label class="block text-xs font-medium text-[#55534F] mb-1.5">อีเมล <span class="text-rose-600">*</span></label>
                     <input type="email" name="email" maxlength="255" required value="<?= profile_h($user['email']) ?>" class="w-full rounded-xl border border-[#D9D6D0] bg-white px-3.5 py-2.5 text-xs text-[#161616] focus:border-[#161616] focus:outline-none">
                  </div>
                  <div>
                     <label class="block text-xs font-medium text-[#55534F] mb-1.5">เบอร์โทรศัพท์ติดต่อ <span class="text-rose-600">*</span></label>
                     <input type="tel" name="phone_number" pattern="0[0-9]{8,9}" maxlength="10" required value="<?= profile_h($user['phone_number']) ?>" class="w-full rounded-xl border border-[#D9D6D0] bg-white px-3.5 py-2.5 text-xs text-[#161616] focus:border-[#161616] focus:outline-none">
                  </div>
                  <div class="sm:col-span-2">
                     <label class="block text-xs font-medium text-[#55534F] mb-1.5">แผนก / สังกัด <span class="text-rose-600">*</span></label>
                     <select name="department_id" required class="w-full rounded-xl border border-[#D9D6D0] bg-white px-3.5 py-2.5 text-xs text-[#161616] focus:border-[#161616] focus:outline-none">
                        <option value="">-- เลือกแผนก --</option>
                        <?php foreach ($departments as $department): ?>
                           <option value="<?= (int)$department['d_id'] ?>" <?= (int)$department['d_id'] === (int)$user['department_id'] ? 'selected' : '' ?>>
                              <?= profile_h($department['name']) ?>
                           </option>
                        <?php endforeach; ?>
                     </select>
                  </div>
               </div>
            </div>

            <!-- ส่วนเปลี่ยนรหัสผ่าน -->
            <div class="rounded-2xl border border-[#EAE8E4] bg-[#FAFAF8] p-5">
               <h2 class="text-xs font-semibold uppercase tracking-wider text-[#161616] mb-1">เปลี่ยนรหัสผ่าน</h2>
               <p class="text-[11px] text-[#6B6862] mb-4">ปล่อยว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่านในครั้งนี้</p>
               <div class="grid gap-4 sm:grid-cols-2">
                  <div class="sm:col-span-2">
                     <label class="block text-xs font-medium text-[#55534F] mb-1.5">รหัสผ่านปัจจุบัน</label>
                     <input type="password" name="current_password" autocomplete="current-password" class="w-full rounded-xl border border-[#D9D6D0] bg-white px-3.5 py-2.5 text-xs text-[#161616] focus:border-[#161616] focus:outline-none">
                  </div>
                  <div>
                     <label class="block text-xs font-medium text-[#55534F] mb-1.5">รหัสผ่านใหม่ (อย่างน้อย 8 ตัวอักษร)</label>
                     <input type="password" name="new_password" minlength="8" autocomplete="new-password" class="w-full rounded-xl border border-[#D9D6D0] bg-white px-3.5 py-2.5 text-xs text-[#161616] focus:border-[#161616] focus:outline-none">
                  </div>
                  <div>
                     <label class="block text-xs font-medium text-[#55534F] mb-1.5">ยืนยันรหัสผ่านใหม่</label>
                     <input type="password" name="confirm_password" minlength="8" autocomplete="new-password" class="w-full rounded-xl border border-[#D9D6D0] bg-white px-3.5 py-2.5 text-xs text-[#161616] focus:border-[#161616] focus:outline-none">
                  </div>
               </div>
            </div>

            <!-- ปุ่มบันทึก -->
            <div class="flex flex-col sm:flex-row items-center justify-end gap-3 pt-4 border-t border-[#ECEAE6]">
               <a href="<?= profile_h($profileHome) ?>" class="w-full sm:w-auto admin-btn admin-btn-secondary text-xs rounded-lg">ยกเลิก</a>
               <button type="submit" class="w-full sm:w-auto admin-btn text-xs rounded-lg">
                  <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> บันทึกโปรไฟล์
               </button>
            </div>
         </div>
      </form>
   </main>

    <?php $footerBase = ''; $footerVariant = 'light'; require __DIR__ . '/includes/site_footer.php'; ?>

   <script>
      (function () {
         var input = document.getElementById('avatarInput');
         if (!input) return;
         input.addEventListener('change', function () {
            var f = input.files && input.files[0];
            var status = document.getElementById('avatarStatus');
            if (!f) return;
            if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { status.textContent = 'รองรับเฉพาะ JPG, PNG หรือ WEBP'; input.value = ''; return; }
            if (f.size > 3 * 1048576) { status.textContent = 'ไฟล์ใหญ่เกิน 3 MB'; input.value = ''; return; }
            var prev = document.getElementById('avatarPreview');
            var cur = prev.previousElementSibling;
            prev.src = URL.createObjectURL(f);
            prev.hidden = false;
            if (cur) cur.hidden = true;
            status.textContent = 'กำลังอัปโหลด…';
            document.getElementById('avatarPick').setAttribute('aria-disabled', 'true');
            document.getElementById('avatarForm').submit();
         });
      })();
   </script>
</body>
</html>
