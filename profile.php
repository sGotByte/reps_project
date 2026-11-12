<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/user_auth.php';
require_once __DIR__ . '/includes/csrf.php';

$userId = app_require_active_user($conn, 'login.php');
$csrfToken = app_csrf_token();
$message = '';
$messageType = 'info';

// ดึงข้อมูลแผนกทั้งหมด
$departmentResult = $conn->query('SELECT d_id, name FROM department ORDER BY name');
$departments = $departmentResult ? $departmentResult->fetch_all(MYSQLI_ASSOC) : [];
$departmentIds = array_map('intval', array_column($departments, 'd_id'));

// ดึงข้อมูลผู้ใช้ปัจจุบัน
$userStmt = $conn->prepare('SELECT u.user_id, u.id_number, u.first_name, u.last_name, u.user_type, u.department_id, d.name AS department_name, u.phone_number, u.email, u.password, u.role, u.status, u.created_at FROM users u LEFT JOIN department d ON d.d_id = u.department_id WHERE u.user_id = ? LIMIT 1');
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

// เก็บ hash ปัจจุบันจากฐานข้อมูลแยกไว้ตรวจสอบรหัสผ่าน
$currentPasswordHash = $user['password'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   if (!app_csrf_is_valid($_POST['csrf'] ?? null)) {
      http_response_code(403);
      exit('คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
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
   <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
   <link rel="stylesheet" href="assets/css/app.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/app.css') ?>">
   <link rel="stylesheet" href="assets/css/portal-shell.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/portal-shell.css') ?>">
   <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
   <script src="https://cdn.tailwindcss.com"></script>
   <script>tailwind.config = { theme: { extend: { fontFamily: { kanit: ['Kanit', 'sans-serif'] } } } };</script>
   <style>
      details > summary { list-style: none; }
      details > summary::-webkit-details-marker { display: none; }
      .admin-shell { background: #FAFAF8; color: #161616; font-family: 'Kanit', sans-serif; }
      .admin-topbar { background: #FFFFFF !important; border-color: #ECEAE6 !important; box-shadow: 0 1px 2px rgba(22, 22, 22, .035); }
      .admin-brand-label { font-family: 'JetBrains Mono', monospace; font-size: 10.5px; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; }
      .admin-brand-sub { display: block; margin-top: 2px; color: #8E8B85; font-family: 'JetBrains Mono', monospace; font-size: 9px; letter-spacing: .12em; }
      .admin-user-avatar { background: #F7F6F4; border: 1px solid #ECEAE6; color: #55534F; font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 600; }
      .admin-btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 38px; padding: 0 14px; border: 1px solid #161616; border-radius: 999px; background: #161616; color: #FFFFFF; font-size: 13px; font-weight: 500; text-decoration: none; transition: background-color .15s ease; cursor: pointer; }
      .admin-btn:hover { background: #2A2927; }
      .admin-btn-secondary { background: #FFFFFF; border-color: #D9D6D0; color: #55534F; }
      .admin-btn-secondary:hover { background: #F7F6F4; color: #161616; border-color: #C9C6C0; }
      input:focus-visible, select:focus-visible { outline: 2px solid #161616; outline-offset: 2px; }
   </style>
</head>

<body class="admin-shell min-h-screen flex flex-col antialiased">
    <!-- Topbar Header ขยายเต็มจอแต่เนื้อหาจัดกึ่งกลางสอดคล้องกับ Dashboard -->
    <header class="admin-topbar sticky top-0 z-30 h-header border-b w-full bg-white">
        <div class="max-w-6xl w-full mx-auto px-4 sm:px-7 flex items-center justify-between h-16">
            <a href="dashboard.php" class="flex items-center gap-3 no-underline">
                <img src="assets/images/logo/reps-logo.svg" alt="REPS" class="h-6 w-auto">
            </a>
            <div class="flex items-center gap-3">
               <a href="request_form.php" class="admin-btn">
                  <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i>
                  <span>แจ้งซ่อมใหม่</span>
               </a>
               <details class="relative" id="userMenu">
                  <summary class="flex items-center gap-2.5 cursor-pointer select-none p-1 pr-2.5 rounded-full border border-neutral-200 hover:border-neutral-300 transition">
                     <div class="admin-user-avatar w-8 h-8 rounded-full flex items-center justify-center text-xs flex-none">
                        <?= profile_h($profileInitial) ?>
                     </div>
                     <span class="max-w-[120px] truncate text-xs text-neutral-800 hidden sm:inline font-medium"><?= profile_h($user['first_name']) ?></span>
                     <i class="fa-solid fa-chevron-down text-[10px] text-neutral-400" aria-hidden="true"></i>
                  </summary>
                  <div class="absolute right-0 mt-2 w-56 rounded-xl border border-neutral-200 bg-white p-1.5 shadow-lg z-50">
                     <div class="border-b border-neutral-100 px-3 py-2.5">
                        <p class="truncate text-xs font-semibold text-neutral-900"><?= profile_h(trim($user['first_name'] . ' ' . $user['last_name'])) ?></p>
                        <p class="truncate text-[10px] text-neutral-400 font-mono mt-0.5"><?= profile_h($profileMeta) ?></p>
                     </div>
                     <div class="py-1">
                        <a href="dashboard.php" class="flex items-center justify-between rounded-lg px-3 py-2 text-xs text-neutral-700 hover:bg-neutral-50 transition no-underline">ใบงานของฉัน <i class="fa-solid fa-clipboard-list text-[10px] text-neutral-400" aria-hidden="true"></i></a>
                        <a href="history.php" class="flex items-center justify-between rounded-lg px-3 py-2 text-xs text-neutral-700 hover:bg-neutral-50 transition no-underline">ประวัติใบแจ้งซ่อม <i class="fa-solid fa-clock-rotate-left text-[10px] text-neutral-400" aria-hidden="true"></i></a>
                        <a href="request_form.php" class="flex items-center justify-between rounded-lg px-3 py-2 text-xs text-neutral-700 hover:bg-neutral-50 transition no-underline">แจ้งซ่อมใหม่ <i class="fa-solid fa-plus text-[10px] text-neutral-400" aria-hidden="true"></i></a>
                        <div class="border-t border-neutral-100 my-1"></div>
                        <a href="logout.php" class="flex items-center justify-between rounded-lg px-3 py-2 text-xs text-rose-600 hover:bg-rose-50 transition no-underline">ออกจากระบบ <i class="fa-solid fa-arrow-right-from-bracket text-[10px]" aria-hidden="true"></i></a>
                     </div>
                  </div>
               </details>
            </div>
        </div>
    </header>

   <main class="flex-1 max-w-6xl w-full mx-auto p-4 sm:p-7">
      <header class="mb-6">
         <nav class="flex items-center gap-2 mb-2 text-[10.5px] font-mono uppercase tracking-wider text-[#8E8B85]" aria-label="Breadcrumb">
             <a href="dashboard.php" class="font-kanit hover:text-[#161616] no-underline">หน้าหลัก</a>
             <i class="fa-solid fa-chevron-right text-[8px] opacity-50" aria-hidden="true"></i>
             <span class="font-kanit text-[#161616]">ตั้งค่าโปรไฟล์</span>
         </nav>
         <h1 class="text-2xl sm:text-3xl font-normal tracking-tight text-[#161616]">แก้ไขโปรไฟล์</h1>
         <p class="mt-1 text-xs text-[#8E8B85]">จัดการข้อมูลส่วนตัว ข้อมูลติดต่อ และความปลอดภัยของบัญชีผู้ใช้งาน</p>
      </header>

      <?php if ($message !== ''): ?>
         <div class="mb-6 flex items-center gap-3 p-4 rounded-xl border text-xs <?= $messageType === 'error' ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900' ?>" role="status">
            <i class="fa-solid <?= $messageType === 'error' ? 'fa-circle-exclamation text-rose-600' : 'fa-circle-check text-emerald-600' ?>" aria-hidden="true"></i>
            <span><?= profile_h($message) ?></span>
         </div>
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
                     <input type="text" value="<?= profile_h($user['id_number']) ?>" readonly class="w-full rounded-xl border border-[#D9D6D0] bg-[#FAFAF8] px-3.5 py-2.5 text-xs text-[#8E8B85] cursor-not-allowed">
                  </div>
                  <div>
                     <label class="block text-xs font-medium text-[#55534F] mb-1.5">ประเภทผู้ใช้</label>
                     <select disabled class="w-full rounded-xl border border-[#D9D6D0] bg-[#FAFAF8] px-3.5 py-2.5 text-xs text-[#8E8B85] cursor-not-allowed">
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
               <p class="text-[11px] text-[#8E8B85] mb-4">ปล่อยว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่านในครั้งนี้</p>
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
               <a href="dashboard.php" class="w-full sm:w-auto admin-btn admin-btn-secondary text-xs rounded-lg">ยกเลิก</a>
               <button type="submit" class="w-full sm:w-auto admin-btn text-xs rounded-lg">
                  <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> บันทึกโปรไฟล์
               </button>
            </div>
         </div>
      </form>
   </main>

   <footer class="mt-auto border-t border-[#ECEAE6] bg-white py-6">
      <div class="max-w-6xl mx-auto px-4 sm:px-7 flex items-center justify-between text-sm text-[#8E8B85] font-kanit font-normal">
         <span>REPS · แผนกเทคโนโลยีธุรกิจดิจิทัล</span>
         <span>User Workspace</span>
      </div>
   </footer>

   <script>
      const userMenu = document.getElementById('userMenu');
      document.addEventListener('click', (event) => {
         if (userMenu?.open && !userMenu.contains(event.target)) userMenu.open = false;
      });
   </script>
</body>
</html>
