<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/user_auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/private_storage.php';
require_once __DIR__ . '/includes/site_config.php';
require_once __DIR__ . '/includes/queue_manager.php';

$csrfToken = app_csrf_token();
require_once __DIR__ . '/admin/line_notify.php';

$user_id = app_require_active_user($conn, 'login.php?next=request_form');
$siteName = app_site_name($conn);
$settings = app_get_settings($conn);

// อัปเดตคิวที่หมดเวลาก่อนดึงข้อมูล
$queueBoard = queue_get_board($conn);
$nextQueueStaff = null;
foreach ($queueBoard as $st) {
   if ((int)$st['is_active_queue'] === 1) {
      $nextQueueStaff = $st;
      break;
   }
}

$u_stmt = $conn->prepare('SELECT u.user_id, u.first_name, u.last_name, u.user_type, d.name AS department_name, u.phone_number FROM users u LEFT JOIN department d ON d.d_id = u.department_id WHERE u.user_id = ? LIMIT 1');
if (!$u_stmt) {
   http_response_code(500);
   exit('โหลดข้อมูลผู้ใช้ไม่ได้ กรุณาลองใหม่ภายหลัง');
}
$u_stmt->bind_param('i', $user_id);
$u_stmt->execute();
$user = $u_stmt->get_result()->fetch_assoc();
$u_stmt->close();

if (!$user) {
   session_destroy();
   header('Location: login.php');
   exit;
}

$categories = [];
$cat_res = $conn->query('SELECT id, name FROM categories ORDER BY id ASC');
if ($cat_res) {
   $categories = $cat_res->fetch_all(MYSQLI_ASSOC);
   $cat_res->free();
}

$errors = [];
$location_note = '';
$selected_category_id = '';
$custom_category = '';
$quantity = 1;
$contact_person = trim($user['first_name'] . ' ' . $user['last_name']) . ' (' . $user['phone_number'] . ')';
$coordinator_teacher = '';
$room_caretaker = '';
$symptoms = '';
$urgency = 'normal';
$urgency_reason = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   if (!app_csrf_is_valid($_POST['csrf'] ?? null)) {
      http_response_code(403);
      exit('คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
   }

   $location_note       = trim((string)($_POST['location_note'] ?? ''));
   $selected_category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT) ?: '';
   $custom_category     = trim((string)($_POST['custom_category'] ?? ''));
   $quantity            = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT) ?: 1;
   $contact_person      = trim((string)($_POST['contact_person'] ?? ''));
   $coordinator_teacher = trim((string)($_POST['coordinator_teacher'] ?? ''));
   $room_caretaker      = trim((string)($_POST['room_caretaker'] ?? ''));
   $symptoms            = trim((string)($_POST['symptoms'] ?? ''));
   $urgency             = (string)($_POST['urgency'] ?? 'normal');
   $urgency_reason      = trim((string)($_POST['urgency_reason'] ?? ''));

   $categoryMap = [];
   foreach ($categories as $c) {
      $categoryMap[(int)$c['id']] = $c['name'];
   }

   if ($location_note === '' || mb_strlen($location_note) > 255) {
      $errors[] = 'กรุณากรอกสถานที่ / ห้อง (ไม่เกิน 255 ตัวอักษร)';
   }
   if (!$selected_category_id || !isset($categoryMap[(int)$selected_category_id])) {
      $errors[] = 'กรุณาเลือกหมวดหมู่งานซ่อม';
   } else {
      $catName = $categoryMap[(int)$selected_category_id];
      if (str_contains($catName, 'อื่นๆ') && $custom_category === '') {
         $errors[] = 'กรุณาระบุหมวดหมู่เพิ่มเติมในช่อง "ระบุหมวดหมู่เอง"';
      }
   }
   if ($quantity < 1 || $quantity > 500) {
      $errors[] = 'จำนวนอุปกรณ์ต้องอยู่ระหว่าง 1 ถึง 500';
   }
   if ($contact_person === '' || mb_strlen($contact_person) > 255) {
      $errors[] = 'กรุณาระบุผู้ที่ติดต่อได้หน้างาน';
   }
   if ($symptoms === '' || mb_strlen($symptoms) > 5000) {
      $errors[] = 'กรุณาระบุรายละเอียดอาการเสีย (ไม่เกิน 5,000 ตัวอักษร)';
   }
   if (!in_array($urgency, ['urgent', 'normal', 'low'], true)) {
      $errors[] = 'ระดับความเร่งด่วนไม่ถูกต้อง';
   }
   if ($urgency === 'urgent' && $urgency_reason === '') {
      $errors[] = 'กรุณาระบุเหตุผลว่าทำไมจึงต้องดำเนินการด่วน';
   }

   // ตรวจสอบไฟล์แนบ (สูงสุด 3 รูป)
   $uploadFiles = [];
   if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
      $fileCount = count($_FILES['attachments']['name']);
      if ($fileCount > 3) {
         $errors[] = 'แนบรูปได้ไม่เกิน 3 รูป';
      } else {
         $mimeExtensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
         $finfo = finfo_open(FILEINFO_MIME_TYPE);
         if ($finfo === false) {
            $errors[] = 'ตรวจสอบชนิดไฟล์แนบไม่ได้';
         } else {
            for ($i = 0; $i < $fileCount; $i++) {
               $uploadError = $_FILES['attachments']['error'][$i];
               if ($uploadError === UPLOAD_ERR_NO_FILE) continue;
               if ($uploadError !== UPLOAD_ERR_OK) {
                  $errors[] = 'อัปโหลดรูปไม่สำเร็จ กรุณาลองใหม่';
                  break;
               }
               $tmpName = $_FILES['attachments']['tmp_name'][$i];
               $fileSize = (int)$_FILES['attachments']['size'][$i];
               $mimeType = finfo_file($finfo, $tmpName);
               if (!isset($mimeExtensions[$mimeType])) {
                  $errors[] = 'ไฟล์แนบต้องเป็น JPG, PNG หรือ WEBP เท่านั้น';
                  break;
               }
               if ($fileSize > 5 * 1024 * 1024) {
                  $errors[] = 'ขนาดไฟล์ต้องไม่เกิน 5 MB ต่อรูป';
                  break;
               }
               $filename = 'tk_' . bin2hex(random_bytes(16)) . '.' . $mimeExtensions[$mimeType];
               $uploadFiles[] = [
                  'tmp_name' => $tmpName,
                  'filename' => $filename,
                  'path' => 'ticket-private/' . $filename,
               ];
            }
            finfo_close($finfo);
         }
      }
   }

   if (!$errors) {
      $ticketNo = 'LB-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
      $uploadDir = app_ticket_upload_dir();
      $movedFiles = [];

      // หาสตาฟที่จะได้รับคิวงานนี้
      $assignedQueueStaffId = queue_pick_next_staff($conn);
      $timeoutMinutes = max(1, (int)($settings['queue_timeout_minutes'] ?? 30));

      try {
         if ($uploadFiles && !is_dir($uploadDir) && !mkdir($uploadDir, 0700, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('สร้างโฟลเดอร์จัดเก็บรูปไม่สำเร็จ');
         }

         if (!$conn->begin_transaction()) {
            throw new RuntimeException($conn->error);
         }

         $customCatVal   = $custom_category !== '' ? $custom_category : null;
         $urgencyReasonVal = ($urgency === 'urgent' && $urgency_reason !== '') ? $urgency_reason : null;
         $coordVal       = $coordinator_teacher !== '' ? $coordinator_teacher : null;
         $caretakerVal   = $room_caretaker !== '' ? $room_caretaker : null;

         if ($assignedQueueStaffId !== null) {
            $insert = $conn->prepare(
               'INSERT INTO tickets (
                   ticket_no, requester_id, category_id, custom_category, quantity,
                   location_note, contact_person, coordinator_teacher, room_caretaker,
                   symptom, urgency, urgency_reason, status, queue_assigned_to, queue_expires_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))'
            );
            $insert->bind_param(
               'siisisssssssii',
               $ticketNo, $user_id, $selected_category_id, $customCatVal, $quantity,
               $location_note, $contact_person, $coordVal, $caretakerVal,
               $symptoms, $urgency, $urgencyReasonVal, $assignedQueueStaffId, $timeoutMinutes
            );
         } else {
            $insert = $conn->prepare(
               'INSERT INTO tickets (
                   ticket_no, requester_id, category_id, custom_category, quantity,
                   location_note, contact_person, coordinator_teacher, room_caretaker,
                   symptom, urgency, urgency_reason, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")'
            );
            $insert->bind_param(
               'siisisssssss',
               $ticketNo, $user_id, $selected_category_id, $customCatVal, $quantity,
               $location_note, $contact_person, $coordVal, $caretakerVal,
               $symptoms, $urgency, $urgencyReasonVal
            );
         }

         if (!$insert->execute()) {
            throw new RuntimeException($insert->error);
         }
         $ticketId = $insert->insert_id;
         $insert->close();

         if ($uploadFiles) {
            $attachStmt = $conn->prepare('INSERT INTO ticket_attachments (ticket_id, file_path) VALUES (?, ?)');
            foreach ($uploadFiles as $file) {
               $destination = $uploadDir . DIRECTORY_SEPARATOR . $file['filename'];
               if (!move_uploaded_file($file['tmp_name'], $destination)) {
                  throw new RuntimeException('ย้ายไฟล์แนบไม่สำเร็จ');
               }
               @chmod($destination, 0600);
               $movedFiles[] = $destination;
               $attachStmt->bind_param('is', $ticketId, $file['path']);
               $attachStmt->execute();
            }
            $attachStmt->close();
         }

         $logStmt = $conn->prepare('INSERT INTO ticket_logs (ticket_id, old_status, new_status, action_by) VALUES (?, NULL, "pending", ?)');
         $logStmt->bind_param('ii', $ticketId, $user_id);
         $logStmt->execute();
         $logStmt->close();

         $conn->commit();

         $flashMessage = 'บันทึกใบแจ้งซ่อมเรียบร้อยแล้ว เลขที่ใบงาน ' . $ticketNo;
         try {
            sendRepairNotification($conn, $ticketId);
         } catch (Throwable $notificationError) {
            error_log('LINE notification failed: ' . $notificationError->getMessage());
         }

         $_SESSION['dashboard_flash'] = ['type' => 'success', 'message' => $flashMessage];
         header('Location: dashboard.php?created=1', true, 303);
         exit;
      } catch (RuntimeException $e) {
         $conn->rollback();
         foreach ($movedFiles as $filePath) {
            if (is_file($filePath)) unlink($filePath);
         }
         error_log('Request form failed: ' . $e->getMessage());
         $errors[] = 'บันทึกใบงานไม่สำเร็จ กรุณาลองใหม่ภายหลัง';
      }
   }
}

$typeLabels = ['student' => 'นักศึกษา', 'teacher' => 'ครู/อาจารย์', 'staff' => 'บุคลากร'];
$requesterInitial = mb_strtoupper(mb_substr(trim((string)$user['first_name']), 0, 1, 'UTF-8'), 'UTF-8');
$requesterMeta = trim(($typeLabels[$user['user_type']] ?? (string)$user['user_type']) . (!empty($user['department_name']) ? ' · ' . $user['department_name'] : ''));
?>
<!DOCTYPE html>
<html lang="th" class="scroll-smooth">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>แจ้งซ่อมใหม่ | <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></title>
   <link rel="icon" type="image/svg+xml" href="assets/images/logo/reps-icon.svg">
   <link rel="preconnect" href="https://fonts.googleapis.com">
   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
   <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500;600&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
   <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
   <script src="https://cdn.tailwindcss.com"></script>
   <script>tailwind.config = { theme: { extend: { fontFamily: { kanit: ['Kanit', 'sans-serif'], mono: ['JetBrains Mono', 'monospace'] } } } };</script>
   <?= app_theme_css($conn) ?>
   <style>
      body { background: #FAFAF8; color: #161616; font-family: 'Kanit', 'Inter', sans-serif; }
      .tray { padding: 6px; border: 1px solid #EAE8E4; border-radius: 20px; background: #F7F6F4; }
      .tray-card { border: 1px solid #ECEAE6; border-radius: 14px; background: #FFFFFF; }
      .form-input { width: 100%; border: 1px solid #D9D6D0; border-radius: 10px; padding: 9px 12px; font-size: 13.5px; background: #FFFFFF; transition: .15s; }
      .form-input:focus { outline: none; border-color: #161616; box-shadow: 0 0 0 3px rgba(22,22,22,.07); }
      .urgency-card input:checked + div { border-color: #161616; background: #F7F6F4; box-shadow: inset 0 0 0 1px #161616; }
   </style>
</head>
<body class="min-h-screen flex flex-col antialiased">

   <!-- Header -->
   <header class="sticky top-0 z-30 h-16 border-b border-[#ECEAE6] bg-white/95 backdrop-blur">
      <div class="max-w-6xl mx-auto px-4 sm:px-7 h-full flex items-center justify-between">
         <a href="dashboard.php" class="flex items-center gap-3">
            <img src="assets/images/logo/reps-logo.svg" alt="REPS" class="h-6 w-auto">
         </a>
         <div class="flex items-center gap-3">
            <a href="dashboard.php" class="text-xs text-neutral-600 hover:text-neutral-900 px-3 py-2 rounded-lg border border-neutral-200 bg-white">
               <i class="fa-solid fa-arrow-left mr-1"></i> กลับหน้าใบงาน
            </a>
            <div class="flex items-center gap-2 px-2.5 py-1 rounded-full border border-neutral-200 bg-[#FAFAF8]">
               <span class="w-7 h-7 rounded-full bg-neutral-900 text-white text-xs font-mono flex items-center justify-center"><?= htmlspecialchars($requesterInitial, ENT_QUOTES, 'UTF-8') ?></span>
               <span class="text-xs font-medium hidden sm:inline"><?= htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
         </div>
      </div>
   </header>

   <main class="flex-1 max-w-6xl w-full mx-auto px-4 sm:px-7 py-7">
      <div class="mb-6">
         <h1 class="text-2xl sm:text-3xl font-normal tracking-tight">แจ้งซ่อมอุปกรณ์ / ระบบไอที</h1>
         <p class="text-xs sm:text-sm text-[#8E8B85] mt-1">กรอกรายละเอียดอาการ จำนวนอุปกรณ์ และผู้ประสานงาน เพื่อให้ทีมสตาฟเข้าดำเนินการได้รวดเร็ว</p>
      </div>

      <?php if (!empty($errors)): ?>
         <div class="mb-6 p-4 rounded-xl border border-rose-200 bg-rose-50 text-rose-900 text-xs space-y-1">
            <div class="font-semibold flex items-center gap-2"><i class="fa-solid fa-circle-exclamation text-rose-600"></i> กรุณาตรวจสอบข้อมูลอีกครั้ง:</div>
            <ul class="list-disc pl-5 space-y-0.5">
               <?php foreach ($errors as $err): ?>
                  <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
               <?php endforeach; ?>
            </ul>
         </div>
      <?php endif; ?>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
         <!-- ฟอร์มหลัก (2 คอลัมน์) -->
         <div class="lg:col-span-2 tray">
            <div class="px-4 py-2 text-xs font-medium text-[#8E8B85] flex items-center justify-between">
               <span>แบบฟอร์มแจ้งซ่อม (Repair Request Form)</span>
               <span class="font-mono text-[11px]">* จำเป็นต้องกรอก</span>
            </div>

            <form action="request_form.php" method="POST" enctype="multipart/form-data" id="requestForm" class="tray-card divide-y divide-[#ECEAE6]">
               <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

               <!-- 01 ข้อมูลผู้แจ้ง -->
               <section class="p-5 sm:p-6">
                  <h2 class="text-sm font-semibold mb-3 flex items-center gap-2">
                     <span class="w-6 h-6 rounded-md bg-[#F7F6F4] border border-[#ECEAE6] font-mono text-xs flex items-center justify-center">01</span>
                     ข้อมูลผู้แจ้งซ่อม
                  </h2>
                  <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                     <div class="p-3 rounded-xl bg-[#FAFAF8] border border-[#ECEAE6]">
                        <span class="text-[#8E8B85] block">ชื่อ-นามสกุล</span>
                        <strong class="font-medium text-neutral-900 mt-0.5 block"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                     </div>
                     <div class="p-3 rounded-xl bg-[#FAFAF8] border border-[#ECEAE6]">
                        <span class="text-[#8E8B85] block">สังกัด / แผนก</span>
                        <strong class="font-medium text-neutral-900 mt-0.5 block"><?= htmlspecialchars($requesterMeta, ENT_QUOTES, 'UTF-8') ?></strong>
                     </div>
                     <div class="p-3 rounded-xl bg-[#FAFAF8] border border-[#ECEAE6]">
                        <span class="text-[#8E8B85] block">เบอร์โทรศัพท์</span>
                        <strong class="font-mono font-medium text-neutral-900 mt-0.5 block"><?= htmlspecialchars($user['phone_number'], ENT_QUOTES, 'UTF-8') ?></strong>
                     </div>
                  </div>
               </section>

               <!-- 02 หมวดหมู่ สถานที่ และผู้ประสานงาน -->
               <section class="p-5 sm:p-6 space-y-4">
                  <h2 class="text-sm font-semibold flex items-center gap-2">
                     <span class="w-6 h-6 rounded-md bg-[#F7F6F4] border border-[#ECEAE6] font-mono text-xs flex items-center justify-center">02</span>
                     หมวดหมู่ สถานที่ และผู้เกี่ยวข้อง
                  </h2>

                  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                     <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-neutral-700 mb-1" for="category_id">หมวดหมู่งานซ่อม <span class="text-rose-600">*</span></label>
                        <select id="category_id" name="category_id" required class="form-input">
                           <option value="">-- เลือกหมวดหมู่ --</option>
                           <?php foreach ($categories as $cat): ?>
                              <option value="<?= (int)$cat['id'] ?>" data-name="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>" <?= (int)$selected_category_id === (int)$cat['id'] ? 'selected' : '' ?>>
                                 <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                              </option>
                           <?php endforeach; ?>
                        </select>
                     </div>

                     <div>
                        <label class="block text-xs font-medium text-neutral-700 mb-1" for="quantity">จำนวน (เครื่อง/ชิ้น) <span class="text-rose-600">*</span></label>
                        <input type="number" id="quantity" name="quantity" min="1" max="500" required value="<?= (int)$quantity ?>" class="form-input font-mono">
                     </div>
                  </div>

                  <!-- ช่องพิมพ์หมวดหมู่เองเมื่อเลือก "อื่นๆ" -->
                  <div id="customCategoryBox" class="hidden p-3.5 rounded-xl bg-amber-50/60 border border-amber-200">
                     <label class="block text-xs font-medium text-amber-900 mb-1" for="custom_category">ระบุหมวดหมู่เพิ่มเติม (พิมพ์เอง) <span class="text-rose-600">*</span></label>
                     <input type="text" id="custom_category" name="custom_category" maxlength="255" value="<?= htmlspecialchars($custom_category, ENT_QUOTES, 'UTF-8') ?>" placeholder="เช่น ระบบเสียงห้องประชุม, กล้องวงจรปิด, ปลั๊กไฟ..." class="form-input">
                  </div>

                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                     <div>
                        <label class="block text-xs font-medium text-neutral-700 mb-1" for="location_note">สถานที่ / ห้อง / เลขเครื่อง <span class="text-rose-600">*</span></label>
                        <input type="text" id="location_note" name="location_note" required maxlength="255" value="<?= htmlspecialchars($location_note, ENT_QUOTES, 'UTF-8') ?>" placeholder="เช่น ห้องปฏิบัติการคอม 2 เครื่อง PC-14" class="form-input">
                     </div>
                     <div>
                        <label class="block text-xs font-medium text-neutral-700 mb-1" for="contact_person">ผู้ที่ติดต่อได้หน้างาน <span class="text-rose-600">*</span></label>
                        <input type="text" id="contact_person" name="contact_person" required maxlength="255" value="<?= htmlspecialchars($contact_person, ENT_QUOTES, 'UTF-8') ?>" placeholder="ชื่อและเบอร์โทรผู้ที่อยู่หน้างาน" class="form-input">
                     </div>
                  </div>

                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                     <div>
                        <label class="block text-xs font-medium text-neutral-700 mb-1" for="coordinator_teacher">ครูผู้ประสานงาน</label>
                        <input type="text" id="coordinator_teacher" name="coordinator_teacher" maxlength="255" value="<?= htmlspecialchars($coordinator_teacher, ENT_QUOTES, 'UTF-8') ?>" placeholder="ระบุชื่อครูผู้ประสานงาน (ถ้ามี)" class="form-input">
                     </div>
                     <div>
                        <label class="block text-xs font-medium text-neutral-700 mb-1" for="room_caretaker">ครูผู้ดูแลห้อง</label>
                        <input type="text" id="room_caretaker" name="room_caretaker" maxlength="255" value="<?= htmlspecialchars($room_caretaker, ENT_QUOTES, 'UTF-8') ?>" placeholder="ระบุชื่อครูผู้ดูแลห้อง (ถ้ามี)" class="form-input">
                     </div>
                  </div>
               </section>

               <!-- 03 อาการเสียและความเร่งด่วน -->
               <section class="p-5 sm:p-6 space-y-4">
                  <h2 class="text-sm font-semibold flex items-center gap-2">
                     <span class="w-6 h-6 rounded-md bg-[#F7F6F4] border border-[#ECEAE6] font-mono text-xs flex items-center justify-center">03</span>
                     รายละเอียดอาการและความเร่งด่วน
                  </h2>

                  <div>
                     <div class="flex justify-between items-center mb-1">
                        <label class="text-xs font-medium text-neutral-700" for="symptoms">อาการเสียที่พบ และสิ่งที่ลองแก้ไขเบื้องต้น <span class="text-rose-600">*</span></label>
                        <span class="font-mono text-[11px] text-neutral-400"><span id="symCount">0</span>/5000</span>
                     </div>
                     <textarea id="symptoms" name="symptoms" rows="4" required maxlength="5000" placeholder="อธิบายอาการอย่างละเอียด เช่น เปิดเครื่องแล้วจอฟ้า, อินเทอร์เน็ตหลุดบ่อย, ลองรีสตาร์ทแล้วไม่หาย..." class="form-input"><?= htmlspecialchars($symptoms, ENT_QUOTES, 'UTF-8') ?></textarea>
                  </div>

                  <div>
                     <span class="block text-xs font-medium text-neutral-700 mb-2">ระดับความเร่งด่วน <span class="text-rose-600">*</span></span>
                     <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                        <label class="urgency-card cursor-pointer">
                           <input type="radio" name="urgency" value="urgent" class="sr-only" <?= $urgency === 'urgent' ? 'checked' : '' ?>>
                           <div class="p-3 rounded-xl border border-[#D9D6D0] bg-white transition">
                              <div class="text-xs font-semibold text-rose-700 flex items-center gap-1.5"><i class="fa-solid fa-bolt"></i> ด่วน</div>
                              <div class="text-[11px] text-neutral-500 mt-0.5">กระทบการเรียนการสอนทันที</div>
                           </div>
                        </label>
                        <label class="urgency-card cursor-pointer">
                           <input type="radio" name="urgency" value="normal" class="sr-only" <?= $urgency === 'normal' ? 'checked' : '' ?>>
                           <div class="p-3 rounded-xl border border-[#D9D6D0] bg-white transition">
                              <div class="text-xs font-semibold text-neutral-900">ปกติ</div>
                              <div class="text-[11px] text-neutral-500 mt-0.5">ดำเนินการตามลำดับคิว</div>
                           </div>
                        </label>
                        <label class="urgency-card cursor-pointer">
                           <input type="radio" name="urgency" value="low" class="sr-only" <?= $urgency === 'low' ? 'checked' : '' ?>>
                           <div class="p-3 rounded-xl border border-[#D9D6D0] bg-white transition">
                              <div class="text-xs font-semibold text-neutral-600">ไม่เร่งด่วน</div>
                              <div class="text-[11px] text-neutral-500 mt-0.5">แจ้งตรวจสอบ/บำรุงรักษา</div>
                           </div>
                        </label>
                     </div>
                  </div>

                  <!-- ช่องกรอกเหตุผลที่ด่วน (แสดงเมื่อเลือก ด่วน) -->
                  <div id="urgencyReasonBox" class="<?= $urgency === 'urgent' ? '' : 'hidden' ?> p-3.5 rounded-xl bg-rose-50/70 border border-rose-200">
                     <label class="block text-xs font-medium text-rose-900 mb-1" for="urgency_reason">เหตุผลที่ต้องดำเนินการด่วน <span class="text-rose-600">*</span></label>
                     <input type="text" id="urgency_reason" name="urgency_reason" maxlength="500" value="<?= htmlspecialchars($urgency_reason, ENT_QUOTES, 'UTF-8') ?>" placeholder="เช่น กำลังใช้สอบปฏิบัติคาบถัดไป, เครื่องครูผู้สอนใช้งานไม่ได้..." class="form-input">
                  </div>

                  <div>
                     <label class="block text-xs font-medium text-neutral-700 mb-1" for="attachments">แนบรูปภาพประกอบ (สูงสุด 3 รูป · JPG, PNG, WEBP ไม่เกิน 5MB)</label>
                     <input type="file" id="attachments" name="attachments[]" multiple accept="image/jpeg,image/png,image/webp" class="form-input text-xs file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:bg-neutral-900 file:text-white file:text-xs cursor-pointer">
                  </div>
               </section>

               <!-- ปุ่มส่งฟอร์ม -->
               <div class="p-5 sm:p-6 bg-[#FAFAF8] rounded-b-2xl flex flex-col sm:flex-row items-center justify-between gap-3">
                  <span class="text-xs text-[#8E8B85]">ระบบจะจ่ายงานเข้าคิวสตาฟอัตโนมัติหลังกดส่ง</span>
                  <div class="flex items-center gap-2.5 w-full sm:w-auto">
                     <a href="dashboard.php" class="w-1/2 sm:w-auto text-center px-4 py-2.5 rounded-xl border border-neutral-300 bg-white text-xs font-medium text-neutral-700 hover:bg-neutral-50">ยกเลิก</a>
                     <button type="submit" class="admin-btn w-1/2 sm:w-auto px-5 py-2.5 rounded-xl text-white text-xs font-medium flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-paper-plane"></i> ส่งใบแจ้งซ่อม
                     </button>
                  </div>
               </div>
            </form>
         </div>

         <!-- ด้านขวา: แสดงสถานะคิวสตาฟปัจจุบัน (ข้อ 12) -->
         <aside class="space-y-4">
            <div class="bg-white rounded-2xl border border-[#EAE8E4] p-5 shadow-sm">
               <div class="flex items-center justify-between pb-3 border-b border-[#ECEAE6]">
                  <h3 class="text-sm font-semibold flex items-center gap-2">
                     <i class="fa-solid fa-list-ol text-amber-600"></i> คิวสตาฟฝ่ายซ่อมปัจจุบัน
                  </h3>
                  <span class="font-mono text-[10px] px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">LIVE QUEUE</span>
               </div>

               <?php if ($nextQueueStaff): ?>
                  <div class="mt-3 p-3 rounded-xl bg-amber-50/70 border border-amber-200 text-xs">
                     <span class="text-amber-800 block text-[11px]">คิวรับงานถัดไป:</span>
                     <strong class="text-sm font-semibold text-neutral-900 mt-0.5 block">
                        <?= htmlspecialchars($nextQueueStaff['first_name'] . ' ' . $nextQueueStaff['last_name'], ENT_QUOTES, 'UTF-8') ?>
                     </strong>
                     <span class="text-[11px] text-neutral-600 mt-1 block">
                        ต้องกดรับงานภายใน <strong><?= (int)($settings['queue_timeout_minutes'] ?? 30) ?> นาที</strong> (ไม่รับหัก <?= (int)($settings['queue_penalty_points'] ?? 5) ?> คะแนน)
                     </span>
                  </div>
               <?php else: ?>
                  <div class="mt-3 p-3 rounded-xl bg-neutral-50 border border-neutral-200 text-xs text-neutral-600">
                     ขณะนี้ยังไม่มีสตาฟเปิดรับคิว ใบงานจะเข้าสู่กองกลางให้ทีมช่างกดรับทันที
                  </div>
               <?php endif; ?>

               <div class="mt-4 space-y-2">
                  <div class="text-[11px] font-medium text-neutral-500">ลำดับคิวทั้งหมด:</div>
                  <?php if (empty($queueBoard)): ?>
                     <p class="text-xs text-neutral-400">ยังไม่มีรายชื่อสตาฟในระบบ</p>
                  <?php else: ?>
                     <?php foreach ($queueBoard as $idx => $st): ?>
                        <div class="flex items-center justify-between p-2.5 rounded-xl border <?= (int)$st['is_active_queue'] === 1 ? 'border-neutral-200 bg-[#FAFAF8]' : 'border-neutral-100 bg-neutral-50/50 opacity-60' ?> text-xs">
                           <div class="flex items-center gap-2.5 min-w-0">
                              <span class="w-5 h-5 rounded-full bg-neutral-900 text-white font-mono text-[10px] flex items-center justify-center flex-none">
                                 <?= $idx + 1 ?>
                              </span>
                              <div class="truncate">
                                 <div class="font-medium text-neutral-900 truncate"><?= htmlspecialchars($st['first_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                 <div class="text-[10px] text-neutral-500">
                                    <?= (int)$st['is_active_queue'] === 1 ? 'พร้อมรับคิว · กำลังซ่อม ' . (int)$st['active_jobs'] . ' งาน' : 'ปิดรับคิวชั่วคราว' ?>
                                 </div>
                              </div>
                           </div>
                           <span class="font-mono text-[11px] font-semibold px-2 py-0.5 rounded bg-white border border-neutral-200">
                              <?= (int)$st['score'] ?> pt
                           </span>
                        </div>
                     <?php endforeach; ?>
                  <?php endif; ?>
               </div>
            </div>
         </aside>
      </div>
   </main>

   <script>
      const catSelect = document.getElementById('category_id');
      const customCatBox = document.getElementById('customCategoryBox');
      const customCatInput = document.getElementById('custom_category');
      const urgencyRadios = document.querySelectorAll('input[name="urgency"]');
      const reasonBox = document.getElementById('urgencyReasonBox');
      const reasonInput = document.getElementById('urgency_reason');
      const symInput = document.getElementById('symptoms');
      const symCount = document.getElementById('symCount');

      function toggleCustomCategory() {
         const opt = catSelect.options[catSelect.selectedIndex];
         const name = opt ? (opt.dataset.name || '') : '';
         const isOther = name.includes('อื่นๆ');
         customCatBox.classList.toggle('hidden', !isOther);
         customCatInput.required = isOther;
      }

      function toggleUrgencyReason() {
         const checked = document.querySelector('input[name="urgency"]:checked');
         const isUrgent = checked && checked.value === 'urgent';
         reasonBox.classList.toggle('hidden', !isUrgent);
         reasonInput.required = isUrgent;
      }

      catSelect.addEventListener('change', toggleCustomCategory);
      urgencyRadios.forEach(r => r.addEventListener('change', toggleUrgencyReason));
      symInput.addEventListener('input', () => symCount.textContent = symInput.value.length);

      toggleCustomCategory();
      toggleUrgencyReason();
      symCount.textContent = symInput.value.length;
   </script>
</body>
</html>