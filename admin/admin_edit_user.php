<?php
$page_title = 'แก้ไขข้อมูลผู้ใช้';
$current_page = 'users';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
   session_start();
}

if (empty($_SESSION['admin_csrf'])) {
   $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$message = is_array($_SESSION['admin_flash'] ?? null) ? (string)($_SESSION['admin_flash']['message'] ?? '') : '';
$messageType = is_array($_SESSION['admin_flash'] ?? null) ? (string)($_SESSION['admin_flash']['type'] ?? 'success') : 'success';
unset($_SESSION['admin_flash']);

// เทียบรหัสยืนยันแบบไม่สนตัวพิมพ์เล็ก/ใหญ่และช่องว่าง เพื่อให้ผู้ใช้พิมพ์ตรงแล้วทำงานได้แน่นอน
function app_confirm_normalize(string $value): string
{
   return strtoupper(preg_replace('/\s+/u', '', $value) ?? '');
}

// จัดการแก้ไขข้อมูลผู้ใช้และลบบัญชี (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['admin_csrf'], is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '')) {
   http_response_code(403);
   exit('คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $lockResult = $conn->query("SELECT GET_LOCK('reps_active_admin_guard', 5) AS acquired");
   if (!$lockResult || (int)$lockResult->fetch_assoc()['acquired'] !== 1) {
      http_response_code(503);
      exit('ระบบกำลังจัดการบัญชีผู้ดูแล กรุณาลองใหม่');
   }
   $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
   $id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
   $redirectToManage = true;

   if ($id && $action === 'update_user') {
      $redirectToManage = false;
      $firstName = trim(is_string($_POST['first_name'] ?? null) ? $_POST['first_name'] : '');
      $lastName = trim(is_string($_POST['last_name'] ?? null) ? $_POST['last_name'] : '');
      $idNumber = trim(is_string($_POST['id_number'] ?? null) ? $_POST['id_number'] : '');
      $userType = is_string($_POST['user_type'] ?? null) ? $_POST['user_type'] : '';
      $email = trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');
      $phone = trim(is_string($_POST['phone_number'] ?? null) ? $_POST['phone_number'] : '');
      $departmentId = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);
      $newRole = trim(is_string($_POST['role'] ?? null) ? $_POST['role'] : '');
      $isSelf = $id === (int)$_SESSION['user_id'];

      $validTypes = ['student', 'teacher', 'staff'];
      $departmentIds = [];
      $deptQuery = $conn->query('SELECT d_id FROM department');
      if ($deptQuery) {
         $departmentIds = array_map('intval', array_column($deptQuery->fetch_all(MYSQLI_ASSOC), 'd_id'));
         $deptQuery->free();
      }

      // ตรวจสอบความถูกต้องของข้อมูล (Validation)
      if (!$idNumber || mb_strlen($idNumber) > 20) {
         $message = 'กรุณากรอกรหัสนักศึกษา/บุคลากรไม่เกิน 20 ตัวอักษร';
         $messageType = 'error';
      } elseif ($firstName === '' || $lastName === '' || mb_strlen($firstName) > 100 || mb_strlen($lastName) > 100) {
         $message = 'กรุณากรอกชื่อและนามสกุลให้ถูกต้อง';
         $messageType = 'error';
      } elseif (!in_array($userType, $validTypes, true)) {
         $message = 'ประเภทผู้ใช้ที่เลือกไม่ถูกต้อง';
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
      } else {
         // ตรวจสอบรหัสประจำตัวและอีเมลใช้กับบัญชีอื่น
         $dupStmt = $conn->prepare('SELECT id_number, email, user_id FROM users WHERE (id_number = ? OR email = ?) AND user_id <> ? LIMIT 2');
         if (!$dupStmt) {
            $message = 'ตรวจสอบข้อมูลไม่สำเร็จ กรุณาลองใหม่';
            $messageType = 'error';
         } else {
            $dupStmt->bind_param('ssi', $idNumber, $email, $id);
            $dupStmt->execute();
            $duplicates = $dupStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $dupStmt->close();

            if ($duplicates) {
               $message = '';
               foreach ($duplicates as $dup) {
                  if (strcasecmp((string)$dup['id_number'], $idNumber) === 0) $message = 'รหัสนักศึกษา/บุคลากรนี้ถูกใช้กับบัญชีอื่นแล้ว';
                  elseif ($dup['email'] === $email) $message = 'อีเมลนี้ถูกใช้กับบัญชีอื่นแล้ว';
               }
               $messageType = 'error';
            } else {
               $updateError = '';

               // ตรวจสอบการเปลี่ยน role (ถ้ามีการส่ง role มา)
               $currentStmt = $conn->prepare('SELECT role, status FROM users WHERE user_id = ? LIMIT 1');
               if (!$currentStmt) {
                  $updateError = 'ตรวจสอบบัญชีผู้ใช้ไม่สำเร็จ';
               } else {
                  $currentStmt->bind_param('i', $id);
                  $currentStmt->execute();
                  $current = $currentStmt->get_result()->fetch_assoc();
                  $currentStmt->close();

                  if (!$current) {
                     $updateError = 'ไม่พบผู้ใช้ที่เลือก';
                  } elseif ($newRole === '' || $newRole === $current['role']) {
                     // ไม่มีการเปลี่ยน role
                  } elseif (!in_array($newRole, ['requester', 'admin'], true)) {
                     $updateError = 'role ที่เลือกไม่ถูกต้อง';
                  } elseif ($isSelf) {
                     $updateError = 'ไม่สามารถเปลี่ยน role ของบัญชีที่กำลังใช้งานอยู่ได้';
                  } elseif ($current['role'] === 'admin' && $current['status'] === 'active') {
                     $activeAdmins = $conn->query('SELECT COUNT(*) AS total FROM users WHERE role = "admin" AND status = "active"');
                     $activeAdminCount = $activeAdmins ? (int)$activeAdmins->fetch_assoc()['total'] : 0;
                     if ($activeAdminCount <= 1) {
                        $updateError = 'ไม่สามารถลด role ของผู้ดูแลที่ใช้งานอยู่คนสุดท้ายได้';
                     }
                  }
               }

               if ($updateError !== '') {
                  $message = $updateError;
                  $messageType = 'error';
               } else {
                  $updateStmt = $conn->prepare('UPDATE users SET id_number = ?, user_type = ?, first_name = ?, last_name = ?, email = ?, phone_number = ?, department_id = ?, role = ? WHERE user_id = ?');
                  $roleValue = ($newRole === '' || !in_array($newRole, ['requester', 'admin'], true)) ? ($current['role'] ?? 'requester') : $newRole;
                  if (!$updateStmt) {
                     $message = 'บันทึกข้อมูลผู้ใช้ไม่สำเร็จ';
                     $messageType = 'error';
                  } else {
                     $updateStmt->bind_param('ssssssisi', $idNumber, $userType, $firstName, $lastName, $email, $phone, $departmentId, $roleValue, $id);
                     if ($updateStmt->execute()) {
                        $message = $updateStmt->affected_rows > 0 ? 'บันทึกข้อมูลผู้ใช้เรียบร้อยแล้ว' : 'ข้อมูลไม่มีการเปลี่ยนแปลง';
                        if ($updateStmt->affected_rows > 0 && $newRole !== '' && $newRole !== ($current['role'] ?? '')) {
                           $message = 'บันทึกข้อมูลผู้ใช้และเปลี่ยน role เรียบร้อยแล้ว';
                        }
                     } else {
                        $message = 'บันทึกข้อมูลผู้ใช้ไม่สำเร็จ';
                        $messageType = 'error';
                        error_log('Admin user update failed for user ' . $id . ': ' . $updateStmt->error);
                     }
                     $updateStmt->close();
                  }
               }
            }
         }
      }
   } elseif ($id && $action === 'delete_user') {
      if ($id === (int)$_SESSION['user_id']) {
         $message = 'ไม่สามารถลบบัญชีที่กำลังใช้งานอยู่ได้';
         $messageType = 'error';
      } else {
         $targetStmt = $conn->prepare('SELECT role, status, id_number FROM users WHERE user_id = ? LIMIT 1');
         if (!$targetStmt) {
            $message = 'ตรวจสอบบัญชีผู้ใช้ไม่สำเร็จ';
            $messageType = 'error';
         } else {
            $targetStmt->bind_param('i', $id);
            $targetStmt->execute();
            $target = $targetStmt->get_result()->fetch_assoc();
            $targetStmt->close();

            if (!$target) {
               $message = 'ไม่พบผู้ใช้ที่เลือก';
               $messageType = 'error';
            } else {
               // ต้องพิมพ์รหัสประจำตัวให้ตรงก่อนยืนยันการลบ
               $confirmIdNumber = trim(is_string($_POST['confirm_id_number'] ?? null) ? $_POST['confirm_id_number'] : '');
               if (!hash_equals(app_confirm_normalize((string)$target['id_number']), app_confirm_normalize($confirmIdNumber))) {
                  $message = 'รหัสประจำตัวที่พิมพ์ไม่ตรงกับบัญชีนี้ การลบไม่ถูกดำเนินการ';
                  $messageType = 'error';
                  error_log('User deletion confirm mismatch for user ' . $id . ' by admin ' . (int)$_SESSION['user_id']);
               } else {
                  $referenceStmt = $conn->prepare(
                     'SELECT
                        (SELECT COUNT(*) FROM tickets WHERE requester_id = ?) AS requester_tickets,
                        (SELECT COUNT(*) FROM tickets WHERE assigned_to = ?) AS assigned_tickets,
                        (SELECT COUNT(*) FROM ticket_logs WHERE action_by = ?) AS action_logs'
                  );
                  if (!$referenceStmt) {
                     $message = 'ตรวจสอบข้อมูลที่เกี่ยวข้องไม่สำเร็จ';
                     $messageType = 'error';
                  } else {
                     $referenceStmt->bind_param('iii', $id, $id, $id);
                     $referenceStmt->execute();
                     $references = $referenceStmt->get_result()->fetch_assoc();
                     $referenceStmt->close();

                     if (!$references || (int)$references['requester_tickets'] + (int)$references['assigned_tickets'] + (int)$references['action_logs'] > 0) {
                        $message = 'ลบไม่ได้ เนื่องจากบัญชีนี้มีใบงานหรือประวัติที่ระบบต้องเก็บไว้';
                        $messageType = 'error';
                     } elseif ($target['role'] === 'admin' && $target['status'] === 'active') {
                        $activeAdmins = $conn->query('SELECT COUNT(*) AS total FROM users WHERE role = "admin" AND status = "active"');
                        $activeAdminCount = $activeAdmins ? (int)$activeAdmins->fetch_assoc()['total'] : 0;
                        if ($activeAdminCount <= 1) {
                           $message = 'ไม่สามารถลบผู้ดูแลที่ใช้งานอยู่คนสุดท้ายได้';
                           $messageType = 'error';
                        }
                     }

                     if ($message === '') {
                        $deleteStmt = $conn->prepare('DELETE FROM users WHERE user_id = ?');
                        if (!$deleteStmt) {
                           $message = 'ลบบัญชีผู้ใช้ไม่สำเร็จ';
                           $messageType = 'error';
                        } else {
                           $deleteStmt->bind_param('i', $id);
                           if ($deleteStmt->execute() && $deleteStmt->affected_rows === 1) {
                              $message = 'ลบบัญชีผู้ใช้เรียบร้อยแล้ว';
                           } else {
                              $message = 'ลบบัญชีผู้ใช้ไม่สำเร็จ';
                              $messageType = 'error';
                              error_log('User deletion failed: ' . $deleteStmt->error);
                           }
                           $deleteStmt->close();
                        }
                     }
                  }
               }
            }
         }
      }
   } else {
      $message = 'คำขอไม่ถูกต้อง';
      $messageType = 'error';
   }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $conn->query("SELECT RELEASE_LOCK('reps_active_admin_guard')");
   $_SESSION['admin_flash'] = ['message' => $message, 'type' => $messageType];
   if ($redirectToManage) {
      header('Location: admin_manage_user.php', true, 303);
   } else {
      header('Location: admin_edit_user.php?user_id=' . (int)$id, true, 303);
   }
   exit;
}

// โหลดข้อมูลผู้ใช้ที่ต้องการแก้ไข (GET)
$userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
$user = null;
$loadError = null;
$isSelf = $userId === (int)$_SESSION['user_id'];

if (!$userId) {
   $loadError = 'ไม่พบผู้ใช้ที่ต้องการแก้ไข';
} else {
   $userStmt = $conn->prepare(
      'SELECT u.user_id, u.id_number, u.user_type, u.first_name, u.last_name, u.department_id, u.phone_number, u.email, u.status, u.role, u.created_at, d.name AS department_name
         FROM users u
         LEFT JOIN department d ON d.d_id = u.department_id
        WHERE u.user_id = ? LIMIT 1'
   );
   if (!$userStmt) {
      $loadError = 'โหลดข้อมูลผู้ใช้ไม่ได้ กรุณาตรวจสอบฐานข้อมูล';
   } else {
      $userStmt->bind_param('i', $userId);
      $userStmt->execute();
      $user = $userStmt->get_result()->fetch_assoc();
      $userStmt->close();
      if (!$user) {
         $loadError = 'ไม่พบผู้ใช้ที่ต้องการแก้ไข';
      }
   }
}

// ดึงรายชื่อแผนก
$departments = [];
$deptResult = $conn->query('SELECT d_id, name FROM department ORDER BY name');
if ($deptResult) {
   $departments = $deptResult->fetch_all(MYSQLI_ASSOC);
   $deptResult->free();
}

function h($v)
{
   return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

require_once __DIR__ . '/admin_header.php';
?>

         <div class="admin-page-head">
            <nav class="admin-crumb">
               <a href="admin_dashboard.php">Dashboard</a>
               <i class="fa-solid fa-chevron-right"></i>
               <a href="admin_manage_user.php">จัดการผู้ใช้</a>
               <i class="fa-solid fa-chevron-right"></i>
               <span>แก้ไขข้อมูลผู้ใช้</span>
            </nav>
            <h1 class="admin-page-title">แก้ไขข้อมูลผู้ใช้</h1>
            <p class="admin-page-sub">แก้ไขข้อมูลส่วนตัว การติดต่อ และ role ของบัญชีผู้ใช้</p>
         </div>

         <?php if ($message): ?>
            <div class="mb-4 rounded-lg border p-3 text-sm <?= $messageType === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' ?>" role="status">
               <?= h($message) ?>
            </div>
         <?php endif; ?>

         <?php if ($loadError): ?>
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
               <?= h($loadError) ?>
            </div>
            <a href="admin_manage_user.php" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"><i class="fa-solid fa-arrow-left"></i> กลับหน้าจัดการผู้ใช้</a>
         <?php else: ?>
            <?php $statusLabels = ['active' => 'ใช้งานได้', 'disabled' => 'ระงับใช้งาน']; ?>
            <?php $roleLabels = ['requester' => 'ผู้ใช้', 'admin' => 'ผู้ดูแลระบบ']; ?>
            <?php $typeLabels = ['student' => 'นักศึกษา', 'teacher' => 'ครู/อาจารย์', 'staff' => 'บุคลากร']; ?>
            <?php $initial = trim(mb_substr((string)$user['first_name'], 0, 1)) !== '' ? mb_substr((string)$user['first_name'], 0, 1) : '?'; ?>

            <div class="grid items-start gap-6 xl:grid-cols-5">
               <!-- คอลัมน์ซ้าย: สรุปตัวตน + โซนลบบัญชี (นอกฟอร์ม กันคลิกพัง submit) -->
               <div class="xl:col-span-2">
                  <div class="admin-tray">
                     <div class="admin-tray-label">Profile</div>
                     <section class="admin-card p-5">
                        <div class="flex items-center gap-4 sm:flex-col sm:items-center sm:text-center">
                           <span class="admin-profile-avatar" aria-hidden="true"><?= h($initial) ?></span>
                           <div class="min-w-0">
                              <p class="truncate font-semibold text-gray-900"><?= h($user['first_name'] . ' ' . $user['last_name']) ?></p>
                              <p class="mt-0.5 font-mono text-xs text-gray-500"><?= h($user['id_number']) ?></p>
                           </div>
                        </div>
                        <div class="mt-4 flex flex-wrap items-center justify-center gap-2 text-xs">
                           <span class="admin-chip no-dot st-<?= h($user['status']) ?>">
                              <i class="fa-<?= $user['status'] === 'active' ? 'solid fa-circle-check' : 'solid fa-circle-xmark' ?>" aria-hidden="true"></i>
                              <?= h($statusLabels[$user['status']] ?? h($user['status'])) ?>
                           </span>
                           <span class="admin-chip no-dot <?= $user['role'] === 'admin' ? 'role-admin' : '' ?>">
                              <i class="fa-<?= $user['role'] === 'admin' ? 'solid fa-user-shield' : 'solid fa-user' ?>" aria-hidden="true"></i>
                              <?= h($roleLabels[$user['role']] ?? h($user['role'])) ?>
                           </span>
                           <span class="admin-chip no-dot">
                              <i class="fa-solid fa-building" aria-hidden="true"></i>
                              <?= h($user['department_name'] ?? 'ไม่ระบุแผนก') ?>
                           </span>
                        </div>
                        <p class="mt-4 flex items-center justify-center gap-1.5 border-t border-[#F1EFEC] pt-3 text-xs text-gray-500">
                           <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                           สมัครเมื่อ <?= h(date('d/m/Y', strtotime($user['created_at']))) ?>
                        </p>
                     </section>
                  </div>

                  <!-- โซนยืนยันก่อนลบบัญชี -->
                  <div class="admin-tray">
                     <div class="admin-tray-label">Danger Zone</div>
                     <section class="admin-card admin-danger-card p-5">
                        <h2 class="flex items-center gap-2 font-medium text-red-900"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> ลบบัญชีผู้ใช้</h2>
                        <p class="mt-1.5 text-sm text-gray-600">การลบไม่สามารถย้อนกลับได้ บัญชีที่มีใบงานหรือประวัติการทำงานจะลบไม่ได้ หากต้องการลบต้องพิมพ์รหัสประจำตัวของบัญชีนี้เพื่อยืนยัน</p>
                        <button type="button" data-open-user-delete
                           data-user-id="<?= (int)$user['user_id'] ?>"
                           data-id-number="<?= h($user['id_number']) ?>"
                           data-user-name="<?= h($user['first_name'] . ' ' . $user['last_name']) ?>"
                           class="admin-btn admin-btn-danger-outline mt-4 w-full <?= $isSelf ? 'opacity-40 pointer-events-none' : '' ?>">
                           <i class="fa-regular fa-trash-can" aria-hidden="true"></i> ลบบัญชี
                        </button>
                     </section>
                  </div>
               </div>

               <!-- คอลัมน์ขวา: ฟอร์มแก้ไขข้อมูล แบ่งเป็นหมวด -->
               <div class="admin-tray xl:col-span-3">
                  <div class="admin-tray-label">Account Details</div>
                  <form method="post" class="admin-card">
                     <input type="hidden" name="csrf" value="<?= h($_SESSION['admin_csrf']) ?>">
                     <input type="hidden" name="action" value="update_user">
                     <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">

                     <div class="flex items-center justify-between gap-3 border-b border-[#ECEAE6] p-5">
                        <h2 class="font-medium text-gray-900">ข้อมูลผู้ใช้</h2>
                        <p class="text-xs text-gray-500">สถานะบัญชี (ใช้งาน/ระงับ) ปรับได้จากหน้า<a class="text-gray-900 underline underline-offset-2 hover:text-black" href="admin_manage_user.php">จัดการผู้ใช้</a></p>
                     </div>

                     <div class="space-y-6 p-5">
                        <!-- หมวด: ระบุตัวตน -->
                        <section>
                           <h3 class="admin-form-label"><i class="fa-regular fa-id-card" aria-hidden="true"></i> การระบุตัวตน</h3>
                           <div class="mt-3 grid gap-5 sm:grid-cols-2">
                              <div class="space-y-1.5">
                                 <label class="block text-sm font-medium text-gray-700" for="id_number">รหัสนักศึกษา / บุคลากร</label>
                                 <input type="text" id="id_number" name="id_number" required maxlength="20" value="<?= h($user['id_number']) ?>" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 font-mono text-sm focus:border-gray-900 focus:outline-none">
                              </div>
                              <div class="space-y-1.5">
                                 <label class="block text-sm font-medium text-gray-700" for="user_type">ประเภทผู้ใช้</label>
                                 <select id="user_type" name="user_type" required class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-gray-900 focus:outline-none">
                                    <?php foreach ($typeLabels as $typeValue => $typeLabel): ?>
                                       <option value="<?= h($typeValue) ?>" <?= $user['user_type'] === $typeValue ? 'selected' : '' ?>><?= h($typeLabel) ?></option>
                                    <?php endforeach; ?>
                                 </select>
                              </div>
                              <div class="space-y-1.5">
                                 <label class="block text-sm font-medium text-gray-700" for="first_name">ชื่อจริง</label>
                                 <input type="text" id="first_name" name="first_name" required maxlength="100" value="<?= h($user['first_name']) ?>" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-gray-900 focus:outline-none">
                              </div>
                              <div class="space-y-1.5">
                                 <label class="block text-sm font-medium text-gray-700" for="last_name">นามสกุล</label>
                                 <input type="text" id="last_name" name="last_name" required maxlength="100" value="<?= h($user['last_name']) ?>" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-gray-900 focus:outline-none">
                              </div>
                              <div class="space-y-1.5 sm:col-span-2">
                                 <label class="block text-sm font-medium text-gray-700" for="department_id">แผนกวิชา / สังกัด</label>
                                 <select id="department_id" name="department_id" required class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-gray-900 focus:outline-none">
                                    <option value="">-- เลือกแผนก --</option>
                                    <?php foreach ($departments as $department): ?>
                                       <option value="<?= (int)$department['d_id'] ?>" <?= (int)$user['department_id'] === (int)$department['d_id'] ? 'selected' : '' ?>><?= h($department['name']) ?></option>
                                    <?php endforeach; ?>
                                 </select>
                              </div>
                           </div>
                        </section>

                        <!-- หมวด: ข้อมูลติดต่อ -->
                        <section class="border-t border-[#ECEAE6] pt-6">
                           <h3 class="admin-form-label"><i class="fa-regular fa-address-card" aria-hidden="true"></i> ข้อมูลติดต่อ</h3>
                           <div class="mt-3 grid gap-5 sm:grid-cols-2">
                              <div class="space-y-1.5 sm:col-span-2">
                                 <label class="block text-sm font-medium text-gray-700" for="email">อีเมล</label>
                                 <input type="email" id="email" name="email" required maxlength="255" value="<?= h($user['email']) ?>" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-gray-900 focus:outline-none">
                              </div>
                              <div class="space-y-1.5">
                                 <label class="block text-sm font-medium text-gray-700" for="phone_number">เบอร์โทรศัพท์</label>
                                 <input type="tel" id="phone_number" name="phone_number" required inputmode="numeric" pattern="0[0-9]{8,9}" maxlength="10" value="<?= h($user['phone_number']) ?>" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-gray-900 focus:outline-none">
                              </div>
                           </div>
                        </section>

                        <!-- หมวด: สิทธิ์การใช้งาน -->
                        <section class="border-t border-[#ECEAE6] pt-6">
                           <h3 class="admin-form-label"><i class="fa-solid fa-user-shield" aria-hidden="true"></i> สิทธิ์การใช้งาน (Role)</h3>
                           <div class="mt-3 grid gap-5 sm:grid-cols-2">
                              <div class="space-y-1.5">
                                 <label class="block text-sm font-medium text-gray-700" for="role">Role</label>
                                 <?php if ($isSelf): ?>
                                    <input type="hidden" name="role" value="<?= h($user['role']) ?>">
                                    <select id="role" disabled class="block w-full cursor-not-allowed rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-500 focus:outline-none">
                                       <option selected><?= h($roleLabels[$user['role']] ?? h($user['role'])) ?></option>
                                    </select>
                                 <?php else: ?>
                                    <select id="role" name="role" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-gray-900 focus:outline-none">
                                       <option value="requester" <?= $user['role'] === 'requester' ? 'selected' : '' ?>>ผู้ใช้</option>
                                       <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>ผู้ดูแลระบบ</option>
                                    </select>
                                 <?php endif; ?>
                              </div>
                              <div class="self-end space-y-1.5">
                                 <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-800">
                                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                    "ผู้ดูแลระบบ" จะเห็นเมนู Admin Panel ทั้งหมด "ผู้ใช้" จะเห็นเฉพาะหน้าของตัวเอง
                                 </p>
                              </div>
                           </div>
                        </section>
                     </div>

                     <div class="flex justify-end gap-2 border-t border-[#ECEAE6] bg-[#FAFAF8] px-5 py-3.5">
                        <a href="admin_manage_user.php" class="admin-btn admin-btn-secondary">ยกเลิก</a>
                        <button type="submit" class="admin-btn"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> บันทึกข้อมูล</button>
                     </div>
                  </form>
               </div>
            </div>
         <?php endif; ?>

         <!-- Modal ยืนยันการลบบัญชี (ต้องพิมพ์รหัสประจำตัว) -->
         <dialog id="userDeleteModal" aria-labelledby="userDeleteTitle" class="m-auto w-[min(460px,calc(100%-2rem))] overflow-hidden rounded-2xl border border-red-100 bg-white p-0 shadow-2xl backdrop:bg-gray-950/40 backdrop:backdrop-blur-sm">
            <form method="post" id="userDeleteForm" novalidate>
               <input type="hidden" name="csrf" value="<?= h($_SESSION['admin_csrf']) ?>">
               <input type="hidden" name="action" value="delete_user">
               <input type="hidden" name="user_id" id="userDeleteUserId">
               <div class="flex items-start justify-between gap-4 border-b border-red-100 bg-gradient-to-br from-red-50 to-white px-5 py-4 sm:px-6">
                  <div class="flex min-w-0 items-start gap-3">
                     <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-600 text-white shadow-sm"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
                     <div class="min-w-0">
                        <p class="text-xs font-medium text-red-600">การดำเนินการที่ย้อนกลับไม่ได้</p>
                        <h2 id="userDeleteTitle" class="mt-0.5 font-semibold text-gray-950">ยืนยันการลบบัญชี</h2>
                     </div>
                  </div>
                  <button type="button" data-close-user-delete aria-label="ปิด" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-900"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
               </div>
               <div class="space-y-4 px-5 py-5 sm:px-6">
                  <p class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                     กำลังจะลบบัญชี <strong id="userDeleteName" class="break-all"></strong>
                     <br>รหัสประจำตัว <span class="font-mono font-semibold" id="userDeleteIdNumber"></span>
                  </p>
                  <div>
                     <label for="userDeleteConfirmInput" class="mb-1.5 block text-sm font-medium text-gray-800">พิมพ์รหัสประจำตัวเพื่อยืนยันการลบ</label>
                     <input type="text" id="userDeleteConfirmInput" name="confirm_id_number" autocomplete="off" spellcheck="false" autocapitalize="off" autocorrect="off" placeholder="รหัสประจำตัวของบัญชีนี้"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 font-mono text-sm outline-none transition placeholder:font-kanit placeholder:text-gray-400 focus:border-red-600 focus:ring-2 focus:ring-red-600/10">
                     <p id="userDeleteHint" class="mt-1.5 text-xs text-gray-500">รหัสประจำตัวของบัญชีที่เลือก ยังไม่ต้องสนใจตัวพิมพ์เล็ก-ใหญ่และช่องว่าง</p>
                     <p class="mt-1 text-xs text-gray-500">บัญชีที่มีใบงานหรือประวัติจะลบไม่ได้</p>
                  </div>
               </div>
               <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50/70 px-5 py-3.5 sm:px-6">
                  <button type="button" data-close-user-delete class="rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50">ปิด</button>
                  <button type="submit" id="userDeleteSubmit" disabled class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition enabled:hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-40 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">
                     <i class="fa-regular fa-trash-can" aria-hidden="true"></i> ยืนยันลบบัญชี
                  </button>
               </div>
            </form>
         </dialog>

         <script>
            (function () {
               const modal = document.getElementById('userDeleteModal');
               const form = document.getElementById('userDeleteForm');
               const confirmInput = document.getElementById('userDeleteConfirmInput');
               const submitButton = document.getElementById('userDeleteSubmit');
               const hint = document.getElementById('userDeleteHint');
               const hintDefault = hint ? hint.textContent : '';
               const idSpan = document.getElementById('userDeleteIdNumber');
               let expected = '';

               // เทียบแบบไม่สนตัวพิมพ์เล็ก-ใหญ่และช่องว่าง เพื่อกันพิมพ์ตรงแล้วจับไม่ได้
               function normalize(value) {
                  return String(value || '').replace(/\s+/g, '').toUpperCase();
               }

               function refreshConfirmState() {
                  const typed = normalize(confirmInput.value);
                  const match = expected !== '' && typed !== '' && typed === normalize(expected);
                  submitButton.disabled = !match;
                  if (hint) {
                     if (confirmInput.value.trim() === '') {
                        hint.textContent = hintDefault;
                        hint.className = 'mt-1.5 text-xs text-gray-500';
                     } else if (match) {
                        hint.textContent = 'รหัสตรงกันแล้ว กด "ยืนยันลบบัญชี" เพื่อทำรายการ';
                        hint.className = 'mt-1.5 text-xs font-medium text-emerald-600';
                     } else {
                        hint.textContent = 'รหัสที่พิมพ์ยังไม่ตรงกัน กรุณาตรวจสอบอีกครั้ง';
                        hint.className = 'mt-1.5 text-xs font-medium text-amber-600';
                     }
                  }
               }

               document.querySelectorAll('[data-open-user-delete]').forEach(function (button) {
                  button.addEventListener('click', function () {
                     document.getElementById('userDeleteUserId').value = button.dataset.userId;
                     document.getElementById('userDeleteName').textContent = button.dataset.userName;
                     expected = String(button.dataset.idNumber || '');
                     idSpan.textContent = expected;
                     confirmInput.value = '';
                     refreshConfirmState();
                     modal.showModal();
                     confirmInput.focus();
                  });
               });

               // ผูกหลายเหตุการณ์เพื่อให้ตรวจทันทีที่ป้อน ไม่ว่าเบราว์เซอร์หรือวิธีพิมพ์ใด
               ['input', 'change', 'keyup', 'paste', 'cut'].forEach(function (type) {
                  confirmInput.addEventListener(type, function () { refreshConfirmState(); });
               });

               // และถ้าปุ่มยืนยันถูกกดโดยที่รหัสไม่ตรง ให้ยกเลิกการส่งก่อนเซิร์ฟเวอร์
               form.addEventListener('submit', function (event) {
                  if (normalize(confirmInput.value) !== normalize(expected)) {
                     event.preventDefault();
                     refreshConfirmState();
                     hint && (hint.textContent = '✗ รหัสประจำตัวไม่ตรง จึงยังลบบัญชีไม่ได้');
                     return;
                  }
                  submitButton.disabled = true;
               });

               modal.querySelectorAll('[data-close-user-delete]').forEach(function (button) {
                  button.addEventListener('click', function () { modal.close(); });
               });
               modal.addEventListener('click', function (event) {
                  if (event.target === modal) modal.close();
               });
            })();
         </script>
      </div>
   </div>
</body>
</html>
