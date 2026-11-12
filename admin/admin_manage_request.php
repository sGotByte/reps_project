<?php
$page_title = 'Requests';
$current_page = 'request';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
   session_start();
}

if (empty($_SESSION['admin_csrf'])) {
   $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$message = is_array($_SESSION['admin_flash'] ?? null) ? (string)($_SESSION['admin_flash']['message'] ?? '') : '';
$message_type = is_array($_SESSION['admin_flash'] ?? null) ? (string)($_SESSION['admin_flash']['type'] ?? 'success') : 'success';
unset($_SESSION['admin_flash']);
$allowed = ['pending', 'in_progress', 'completed', 'cancelled'];
$statusTransitions = [
   'pending' => ['in_progress', 'cancelled'],
   'in_progress' => ['completed', 'cancelled'],
   'completed' => [],
   'cancelled' => []
];

// จัดการหมวดหมู่และอัปเดตสถานะคำขอ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['admin_csrf'], is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '')) {
   http_response_code(403);
   exit('คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

   if ($action === 'add_category') {
      $categoryName = trim(is_string($_POST['category_name'] ?? null) ? $_POST['category_name'] : '');
      if ($categoryName === '') {
         $message = 'กรุณาระบุชื่อหมวดหมู่';
         $message_type = 'error';
      } else {
         try {
            $check = $conn->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
            if (!$check) {
               throw new RuntimeException($conn->error);
            }
            $check->bind_param('s', $categoryName);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();

            if ($exists) {
               $message = 'หมวดหมู่นี้มีอยู่ในระบบแล้ว';
               $message_type = 'error';
            } else {
               $insert = $conn->prepare('INSERT INTO categories (name) VALUES (?)');
               if (!$insert) {
                  throw new RuntimeException($conn->error);
               }
               $insert->bind_param('s', $categoryName);
               if (!$insert->execute()) {
                  throw new RuntimeException($insert->error);
               }
               $insert->close();
               $message = 'เพิ่มหมวดหมู่ใหม่เรียบร้อยแล้ว';
            }
         } catch (Throwable $e) {
            $message = 'เพิ่มหมวดหมู่ไม่สำเร็จ กรุณาตรวจสอบฐานข้อมูล';
            $message_type = 'error';
         }
      }
   } elseif ($action === 'edit_category') {
      $categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
      $categoryName = trim(is_string($_POST['category_name'] ?? null) ? $_POST['category_name'] : '');

      if (!$categoryId) {
         $message = 'หมวดหมู่ที่เลือกไม่ถูกต้อง';
         $message_type = 'error';
      } elseif ($categoryName === '') {
         $message = 'กรุณาระบุชื่อหมวดหมู่';
         $message_type = 'error';
      } else {
         try {
            $check = $conn->prepare('SELECT id FROM categories WHERE name = ? AND id <> ? LIMIT 1');
            if (!$check) {
               throw new RuntimeException($conn->error);
            }
            $check->bind_param('si', $categoryName, $categoryId);
            $check->execute();
            $duplicate = $check->get_result()->num_rows > 0;
            $check->close();

            if ($duplicate) {
               $message = 'มีหมวดหมู่นี้อยู่ในระบบแล้ว';
               $message_type = 'error';
            } else {
               $update = $conn->prepare('UPDATE categories SET name = ? WHERE id = ?');
               if (!$update) {
                  throw new RuntimeException($conn->error);
               }
               $update->bind_param('si', $categoryName, $categoryId);
               if (!$update->execute()) {
                  throw new RuntimeException($update->error);
               }
               $updated = $update->affected_rows > 0;
               $update->close();
               $message = $updated ? 'แก้ไขหมวดหมู่เรียบร้อยแล้ว' : 'ชื่อหมวดหมู่ไม่เปลี่ยนแปลง';
            }
         } catch (Throwable $e) {
            $message = 'แก้ไขหมวดหมู่ไม่สำเร็จ กรุณาตรวจสอบฐานข้อมูล';
            $message_type = 'error';
         }
      }
   } elseif ($action === 'delete_category') {
      $categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
      if (!$categoryId) {
         $message = 'หมวดหมู่ที่เลือกไม่ถูกต้อง';
         $message_type = 'error';
      } else {
         try {
            $ticketCheck = $conn->prepare('SELECT id FROM tickets WHERE category_id = ? LIMIT 1');
            if (!$ticketCheck) {
               throw new RuntimeException($conn->error);
            }
            $ticketCheck->bind_param('i', $categoryId);
            $ticketCheck->execute();
            $hasTickets = $ticketCheck->get_result()->num_rows > 0;
            $ticketCheck->close();

            if ($hasTickets) {
               $message = 'ไม่สามารถลบได้เนื่องจากมีคำขอแจ้งซ่อมที่ผูกกับหมวดหมู่นี้อยู่';
               $message_type = 'error';
            } else {
               $delete = $conn->prepare('DELETE FROM categories WHERE id = ?');
               if (!$delete) {
                  throw new RuntimeException($conn->error);
               }
               $delete->bind_param('i', $categoryId);
               if (!$delete->execute()) {
                  throw new RuntimeException($delete->error);
               }
               $deleted = $delete->affected_rows > 0;
               $delete->close();
               $message = $deleted ? 'ลบหมวดหมู่เรียบร้อยแล้ว' : 'ไม่พบหมวดหมู่ที่ต้องการลบ';
               $message_type = $deleted ? 'success' : 'error';
            }
         } catch (Throwable $e) {
            $message = 'ลบหมวดหมู่ไม่สำเร็จ กรุณาตรวจสอบฐานข้อมูล';
            $message_type = 'error';
         }
      }
   } elseif ($action === 'delete_ticket') {
      $id = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
      $confirmNo = trim(is_string($_POST['confirm_ticket_no'] ?? null) ? $_POST['confirm_ticket_no'] : '');
      $attachmentFiles = [];
      if (!$id) {
         $message = 'ไม่พบใบแจ้งซ่อมที่เลือก';
         $message_type = 'error';
      } else {
         try {
            if (!$conn->begin_transaction()) {
               throw new RuntimeException($conn->error);
            }
            // ล็อกแถวใบงานเพื่อยืนยันรหัสก่อนลบ
            $chk = $conn->prepare('SELECT ticket_no FROM tickets WHERE id = ? FOR UPDATE');
            if (!$chk) {
               throw new RuntimeException($conn->error);
            }
            $chk->bind_param('i', $id);
            $chk->execute();
            $ticket = $chk->get_result()->fetch_assoc();
            $chk->close();

            if (!$ticket) {
               throw new RuntimeException('Ticket not found: ' . $id);
            }
            if (!hash_equals(app_confirm_normalize((string)$ticket['ticket_no']), app_confirm_normalize($confirmNo))) {
               $conn->rollback();
               $message = 'รหัสใบแจ้งซ่อมที่พิมพ์ไม่ตรงกัน การลบไม่ถูกดำเนินการ';
               $message_type = 'error';
               error_log('Ticket deletion confirm mismatch for ticket ' . $id . ' by admin ' . (int)($_SESSION['user_id'] ?? 0));
            } else {
               // เก็บตำแหน่งไฟล์แนบไว้ลบทิ้งหลังบันทึกสำเร็จ
               $fileStmt = $conn->prepare('SELECT file_path FROM ticket_attachments WHERE ticket_id = ?');
               if (!$fileStmt) {
                  throw new RuntimeException($conn->error);
               }
               $fileStmt->bind_param('i', $id);
               $fileStmt->execute();
               $fileRows = $fileStmt->get_result()->fetch_all(MYSQLI_ASSOC);
               $fileStmt->close();

               $delAttachments = $conn->prepare('DELETE FROM ticket_attachments WHERE ticket_id = ?');
               $delLogs = $conn->prepare('DELETE FROM ticket_logs WHERE ticket_id = ?');
               $delTicket = $conn->prepare('DELETE FROM tickets WHERE id = ?');
               if (!$delAttachments || !$delLogs || !$delTicket) {
                  throw new RuntimeException($conn->error);
               }
               $delAttachments->bind_param('i', $id);
               if (!$delAttachments->execute()) {
                  throw new RuntimeException($delAttachments->error);
               }
               $delAttachments->close();
               $delLogs->bind_param('i', $id);
               if (!$delLogs->execute()) {
                  throw new RuntimeException($delLogs->error);
               }
               $delLogs->close();
               $delTicket->bind_param('i', $id);
               if (!$delTicket->execute() || $delTicket->affected_rows !== 1) {
                  throw new RuntimeException($delTicket->error);
               }
               $delTicket->close();

               if (!$conn->commit()) {
                  throw new RuntimeException($conn->error);
               }
               $message = 'ลบใบแจ้งซ่อม #' . $ticket['ticket_no'] . ' เรียบร้อยแล้ว';

               foreach ($fileRows as $fileRow) {
                  if (preg_match('#^(?:ticket-private/)?([A-Za-z0-9_-]+\.(?:jpg|png))$#i', (string)$fileRow['file_path'], $m) === 1) {
                     $attachmentFiles[] = $m[1];
                  }
               }
            }
         } catch (Throwable $e) {
            $conn->rollback();
            error_log('Ticket deletion failed for ticket ' . $id . ': ' . $e->getMessage());
            $message = 'ลบใบแจ้งซ่อมไม่สำเร็จ กรุณาตรวจสอบฐานข้อมูล';
            $message_type = 'error';
         }

         // ลบไฟล์แนบจริงหลังลบข้อมูลในฐานข้อมูลสำเร็จ
         if ($attachmentFiles) {
            require_once __DIR__ . '/../includes/private_storage.php';
            $uploadDir = app_ticket_upload_dir();
            $legacyDir = __DIR__ . '/../uploads/tickets';
            foreach ($attachmentFiles as $filename) {
               foreach ([$uploadDir . DIRECTORY_SEPARATOR . $filename, $legacyDir . DIRECTORY_SEPARATOR . $filename] as $fileLocation) {
                  if (is_file($fileLocation)) {
                     @unlink($fileLocation);
                  }
               }
            }
         }
      }
   } else {
      $id = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
      $status = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
      $statusReason = trim(is_string($_POST['status_reason'] ?? null) ? $_POST['status_reason'] : '');

      if ($id && in_array($status, $allowed, true)) {
         if (in_array($status, ['completed', 'cancelled'], true) && $statusReason === '') {
            $message = $status === 'completed'
               ? 'กรุณาระบุสรุปการดำเนินงานก่อนปิดงาน'
               : 'กรุณาระบุเหตุผลก่อนยกเลิกคำขอ';
            $message_type = 'error';
         } elseif ($statusReason !== '' && mb_strlen($statusReason, 'UTF-8') > 255) {
            $message = 'สรุปหรือเหตุผลต้องมีความยาวไม่เกิน 255 ตัวอักษร';
            $message_type = 'error';
         } else {
         try {
            if (!$conn->begin_transaction()) {
               throw new RuntimeException($conn->error);
            }
            // ดึงสถานะปัจจุบันของใบงาน
            $chk = $conn->prepare('SELECT status FROM tickets WHERE id = ? FOR UPDATE');
            if (!$chk) {
               throw new RuntimeException($conn->error);
            }
            $chk->bind_param('i', $id);
            $chk->execute();
            $res = $chk->get_result();
            $row = $res->fetch_assoc();
            $chk->close();

            if (!$row) {
               throw new RuntimeException('Ticket not found: ' . $id);
            }
            if (!in_array($status, $statusTransitions[$row['status']] ?? [], true)) {
               $conn->rollback();
               if (in_array($row['status'], ['completed', 'cancelled'], true)) {
                  $message = 'ใบงานนี้ปิดสถานะแล้ว ไม่สามารถเปลี่ยนสถานะได้';
               } elseif ($row['status'] === 'in_progress' && $status === 'pending') {
                  $message = 'ใบงานที่กำลังดำเนินการไม่สามารถเปลี่ยนกลับเป็นรอรับเรื่องได้';
               } else {
                  $message = 'ไม่สามารถเปลี่ยนสถานะใบงานตามลำดับนี้ได้';
               }
               $message_type = 'error';
            } else {
               $old_status = $row['status'];
               $actor = (int)($_SESSION['user_id'] ?? 0);

               // อัปเดตสถานะและเวลา
               $u_sql = "UPDATE tickets SET 
                            status = ?, 
                            assigned_to = CASE WHEN ? = 'in_progress' AND assigned_to IS NULL THEN ? ELSE assigned_to END,
                            accepted_at = CASE WHEN ? = 'in_progress' AND accepted_at IS NULL THEN NOW() ELSE accepted_at END, 
                            completed_at = CASE WHEN ? = 'completed' THEN NOW() ELSE NULL END,
                            resolution_summary = CASE WHEN ? = 'completed' THEN ? ELSE NULL END,
                            cancel_reason = CASE WHEN ? = 'cancelled' THEN ? ELSE NULL END
                          WHERE id = ?";
               $u_stmt = $conn->prepare($u_sql);
               if (!$u_stmt) {
                  throw new RuntimeException($conn->error);
               }
               $u_stmt->bind_param('ssissssssi', $status, $status, $actor, $status, $status, $status, $statusReason, $status, $statusReason, $id);
               if (!$u_stmt->execute()) {
                  throw new RuntimeException($u_stmt->error);
               }
               $u_stmt->close();

               // บันทึกประวัติลง ticket_logs
               $log_stmt = $conn->prepare('INSERT INTO ticket_logs (ticket_id, old_status, new_status, action_by, created_at) VALUES (?, ?, ?, ?, NOW())');
               if (!$log_stmt) {
                  throw new RuntimeException($conn->error);
               }
               $log_stmt->bind_param('issi', $id, $old_status, $status, $actor);
               if (!$log_stmt->execute()) {
                  throw new RuntimeException($log_stmt->error);
               }
               $log_stmt->close();

               if (!$conn->commit()) {
                  throw new RuntimeException($conn->error);
               }
               $message = 'อัปเดตสถานะคำขอแล้ว';
            }
         } catch (Throwable $e) {
            $conn->rollback();
            error_log('Ticket status update failed for ticket ' . $id . ': ' . $e->getMessage());
            $message = 'อัปเดตไม่สำเร็จ กรุณาตรวจสอบฐานข้อมูล';
            $message_type = 'error';
         }
         }
      }
   }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $_SESSION['admin_flash'] = ['message' => $message, 'type' => $message_type];
   $redirect = 'admin_manage_request.php';
   $returnStatus = is_string($_POST['return_status'] ?? null) ? $_POST['return_status'] : '';
   if (in_array($returnStatus, $allowed, true)) $redirect .= '?status=' . rawurlencode($returnStatus);
   header('Location: ' . $redirect, true, 303);
   exit;
}

// เทียบรหัสยืนยันแบบไม่สนตัวพิมพ์เล็ก/ใหญ่และช่องว่าง เพื่อให้พิมพ์ตรงแล้วทำงานได้แน่นอน
function app_confirm_normalize(string $value): string
{
   return strtoupper(preg_replace('/\s+/u', '', $value) ?? '');
}

// กรองสถานะและค้นหา (GET)
$filter = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
$where = in_array($filter, $allowed, true) ? 'WHERE t.status = ?' : '';

$tickets = [];
$counts = ['pending' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0];
$categoryStats = [];

try {
   $sql = 'SELECT 
                t.id AS ticket_id, t.ticket_no, NULL AS sub_category, t.symptom AS symptoms, t.urgency, t.status, t.created_at,
                t.accepted_at, t.completed_at, t.resolution_summary, t.cancel_reason,
                u.user_id AS requester_id, u.first_name, u.last_name, u.id_number, u.user_type, u.phone_number, u.email, d.name AS department_name,
                t.location_note AS location_name,
                c.name AS category_name,
                assignee.first_name AS tech_first, assignee.last_name AS tech_last,
                (SELECT tl.action_by
                   FROM ticket_logs tl
                  WHERE tl.ticket_id = t.id AND tl.new_status = \'cancelled\'
                  ORDER BY tl.created_at DESC, tl.id DESC
                  LIMIT 1) AS cancelled_by
            FROM tickets t
            JOIN users u ON u.user_id = t.requester_id
            LEFT JOIN department d ON d.d_id = u.department_id
            JOIN categories c ON c.id = t.category_id
            LEFT JOIN users assignee ON assignee.user_id = t.assigned_to
            ' . $where . '
            ORDER BY t.created_at DESC, t.id DESC';

   $stmt = $conn->prepare($sql);
   if ($where) {
      $stmt->bind_param('s', $filter);
   }
   $stmt->execute();
   $tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
   $stmt->close();

   $attachmentsByTicket = [];
   if ($tickets) {
      $ticketIds = array_map('intval', array_column($tickets, 'ticket_id'));
      $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
      $attachmentStmt = $conn->prepare(
         "SELECT id, ticket_id, file_path FROM ticket_attachments WHERE ticket_id IN ({$placeholders}) ORDER BY id"
      );
      if (!$attachmentStmt) {
         throw new RuntimeException($conn->error);
      }
      $attachmentTypes = str_repeat('i', count($ticketIds));
      $attachmentStmt->bind_param($attachmentTypes, ...$ticketIds);
      if (!$attachmentStmt->execute()) {
         throw new RuntimeException($attachmentStmt->error);
      }
      $attachmentResult = $attachmentStmt->get_result();
      while ($attachment = $attachmentResult->fetch_assoc()) {
         $path = $attachment['file_path'];
         if (preg_match('#^(?:ticket-private/)?[A-Za-z0-9_-]+\.(?:jpg|png)$#i', str_replace('ticket-private/', '', $path))) {
            $attachmentsByTicket[(int)$attachment['ticket_id']][] = (int)$attachment['id'];
         }
      }
      $attachmentStmt->close();
   }

   // ดึงจำนวนสถิติแยกตามสถานะ
   $count_res = $conn->query('SELECT status, COUNT(*) AS total FROM tickets GROUP BY status');
   if ($count_res) {
      while ($c_row = $count_res->fetch_assoc()) {
         if (isset($counts[$c_row['status']])) {
            $counts[$c_row['status']] = (int)$c_row['total'];
         }
      }
      $count_res->free();
   }
} catch (Throwable $e) {
   $tickets = [];
   $counts = [];
   error_log('Admin request query failed: ' . $e->getMessage());
   $loadError = 'โหลดคำขอไม่ได้ กรุณาลองใหม่ภายหลัง';
}

try {
   $categoryResult = $conn->query(
      'SELECT c.id, c.name, COUNT(t.id) AS ticket_count
         FROM categories c
         LEFT JOIN tickets t ON t.category_id = c.id
         GROUP BY c.id, c.name
         ORDER BY c.name'
   );
   if (!$categoryResult) {
      throw new RuntimeException($conn->error);
   }
   $categoryStats = $categoryResult->fetch_all(MYSQLI_ASSOC);
   $categoryResult->free();
} catch (Throwable $e) {
   $categoryLoadError = 'โหลดหมวดหมู่งานซ่อมไม่ได้ กรุณาลองใหม่ภายหลัง';
}

function h($v)
{
   return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

$statusLabel = [
   'pending' => 'รอรับเรื่อง',
   'in_progress' => 'กำลังดำเนินการ',
   'completed' => 'เสร็จสิ้น',
   'cancelled' => 'ยกเลิก'
];
$userTypeLabels = ['student' => 'นักศึกษา', 'teacher' => 'ครู/อาจารย์', 'staff' => 'บุคลากร'];

require_once __DIR__ . '/admin_header.php';
?>

         <div class="admin-page-head">
            <nav class="admin-crumb">
               <a href="admin_dashboard.php">Dashboard</a>
               <i class="fa-solid fa-chevron-right"></i>
               <span>จัดการคำขอซ่อม</span>
            </nav>
            <h1 class="admin-page-title">จัดการคำขอซ่อม</h1>
            <p class="admin-page-sub">ค้นหา ติดตาม และปรับสถานะงานซ่อม</p>
         </div>

         <?php if ($message): ?>
            <div class="mb-4 rounded-lg border p-3 text-sm <?= $message_type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800' ?>" role="status">
               <?= h($message) ?>
            </div>
         <?php endif; ?>

         <?php if (!empty($categoryLoadError)): ?>
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800" role="alert">
               <?= h($categoryLoadError) ?>
            </div>
         <?php endif; ?>

         <?php if (!empty($loadError)): ?>
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
               <?= h($loadError) ?>
            </div>
         <?php endif; ?>

         <!-- สถิติจำนวนใบงานแยกตามสถานะ -->
         <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 mb-6">
            <?php foreach ($statusLabel as $key => $label): ?>
               <a href="?status=<?= h($key) ?>" class="admin-stat st-<?= h($key) ?> <?= $filter === $key ? 'is-active' : '' ?>">
                  <div class="admin-stat-label"><?= h($label) ?></div>
                  <div class="admin-stat-value"><?= number_format((int)($counts[$key] ?? 0)) ?></div>
               </a>
            <?php endforeach; ?>
         </div>

         <!-- ตารางรายการคำขอแจ้งซ่อม -->
         <div class="admin-tray">
            <div class="admin-tray-label">
               <span>Requests</span>
               <span><?= count($tickets) ?> รายการ</span>
            </div>
            <section class="admin-card">
               <div class="admin-toolbar justify-between">
                  <div>
                     <h2 class="font-normal text-gray-900">รายการคำขอ</h2>
                     <p class="mt-1 text-xs text-gray-500">
                        ทั้งหมด <?= count($tickets) ?> รายการ<?= $filter ? ' · กรอง: ' . h($statusLabel[$filter] ?? $filter) : '' ?>
                     </p>
                  </div>
                  <form method="get" class="flex flex-wrap items-center gap-2">
                     <label class="sr-only" for="status-filter">กรองสถานะ</label>
                     <select id="status-filter" name="status" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:border-gray-900">
                        <option value="">ทุกสถานะ</option>
                        <?php foreach ($statusLabel as $key => $label): ?>
                           <option value="<?= h($key) ?>" <?= $filter === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                     </select>
                     <button class="admin-btn">กรอง</button>
                     <?php if ($filter): ?>
                        <a href="admin_manage_request.php" class="admin-reset-link">ล้าง</a>
                     <?php endif; ?>
                  </form>
               </div>

               <div class="overflow-x-auto admin-table-wrap">
                  <table class="w-full min-w-[1000px] text-left text-sm">
                     <thead class="bg-gray-50 text-xs text-gray-500">
                        <tr>
                           <th class="px-4 py-3 font-medium">รหัส / วันที่</th>
                           <th class="px-4 py-3 font-medium">ผู้แจ้ง</th>
                           <th class="px-4 py-3 font-medium">สถานที่</th>
                           <th class="px-4 py-3 font-medium">รายละเอียด</th>
                           <th class="px-4 py-3 font-medium">ความเร่งด่วน</th>
                           <th class="px-4 py-3 font-medium">สถานะ</th>
                           <th class="px-4 py-3 font-medium">ผู้รับผิดชอบ</th>
                           <th class="px-4 py-3 font-medium">บันทึก</th>
                        </tr>
                     </thead>
                     <tbody class="divide-y divide-gray-100">
                        <?php if (!$tickets): ?>
                           <tr>
                              <td colspan="8" class="px-4 py-12 text-center text-gray-500">ยังไม่มีคำขอในรายการนี้</td>
                           </tr>
                        <?php endif; ?>

                        <?php foreach ($tickets as $t): ?>
                           <tr class="align-top hover:bg-gray-50/70 transition-colors">
                              <td class="px-4 py-4">
                                 <div class="font-mono font-medium text-gray-900">#<?= h($t['ticket_no']) ?></div>
                                 <div class="mt-1 text-xs text-gray-500"><?= h(date('d/m/Y H:i', strtotime($t['created_at']))) ?></div>
                              </td>
                              <td class="px-4 py-4">
                                 <div class="font-medium text-gray-900"><?= h($t['first_name'] . ' ' . $t['last_name']) ?></div>
                                 <div class="text-xs text-gray-500">รหัส: <?= h($t['id_number'] ?? '—') ?></div>
                                 <div class="text-xs text-gray-500"><?= h($t['department_name'] ?? 'ไม่ระบุแผนก') ?></div>
                              </td>
                              <td class="px-4 py-4">
                                 <div class="text-gray-900"><?= h($t['location_name']) ?></div>
                              </td>
                              <td class="max-w-xs px-4 py-4">
                                 <div class="font-medium text-gray-900"><?= h($t['category_name']) ?><?= !empty($t['sub_category']) ? ' · ' . h($t['sub_category']) : '' ?></div>
                                 <div class="mt-1 whitespace-normal text-xs text-gray-500"><?= h($t['symptoms']) ?></div>
                                 <?php
                                 $ticketDetails = [
                                    'ticketNo' => $t['ticket_no'],
                                    'createdAt' => date('d/m/Y H:i', strtotime($t['created_at'])),
                                    'status' => $statusLabel[$t['status']] ?? $t['status'],
                                    'urgency' => $t['urgency'] === 'urgent' ? 'ด่วน' : ($t['urgency'] === 'low' ? 'ไม่เร่งด่วน' : 'ปกติ'),
                                    'category' => $t['category_name'],
                                    'location' => $t['location_name'],
                                    'requester' => trim($t['first_name'] . ' ' . $t['last_name']),
                                    'idNumber' => $t['id_number'],
                                    'userTypeDepartment' => (($userTypeLabels[$t['user_type']] ?? $t['user_type']) . ' · ' . ($t['department_name'] ?? 'ไม่ระบุแผนก')),
                                    'phone' => $t['phone_number'],
                                    'email' => $t['email'],
                                    'assignee' => trim(($t['tech_first'] ?? '') . ' ' . ($t['tech_last'] ?? '')),
                                    'symptoms' => $t['symptoms'],
                                    'acceptedAt' => $t['accepted_at'] ? date('d/m/Y H:i', strtotime($t['accepted_at'])) : '',
                                    'completedAt' => $t['completed_at'] ? date('d/m/Y H:i', strtotime($t['completed_at'])) : '',
                                    'resolutionSummary' => $t['resolution_summary'],
                                    'cancelReason' => $t['cancel_reason'],
                                    'cancelledByRequester' => (int)($t['cancelled_by'] ?? 0) === (int)$t['requester_id'],
                                    'cancelledByLabel' => $t['cancelled_by'] === null
                                       ? 'ไม่ทราบผู้ยกเลิก'
                                       : ((int)$t['cancelled_by'] === (int)$t['requester_id'] ? 'ผู้แจ้งยกเลิก' : 'ผู้ดูแลยกเลิก'),
                                    'ticket_id' => (int)$t['ticket_id'],
                                    'attachments' => $attachmentsByTicket[(int)$t['ticket_id']] ?? [],
                                 ];
                                 ?>
                                 <button type="button" data-ticket-details="<?= h(json_encode($ticketDetails, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE)) ?>"
                                    class="mt-2 inline-flex items-center gap-1.5 rounded-md border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:border-gray-500 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-400">
                                    <i class="fa-regular fa-eye" aria-hidden="true"></i> ดูรายละเอียด
                                 </button>
                              </td>
                              <td class="px-4 py-4">
                                 <span class="admin-chip <?= $t['urgency'] === 'urgent' ? 'is-urgent' : ($t['urgency'] === 'low' ? 'is-low' : '') ?>">
                                    <?= $t['urgency'] === 'urgent' ? 'ด่วน' : ($t['urgency'] === 'low' ? 'ไม่เร่งด่วน' : 'ปกติ') ?>
                                 </span>
                              </td>
                              <td class="px-4 py-4">
                                 <span class="admin-chip st-<?= h($t['status']) ?>">
                                    <?= h($statusLabel[$t['status']] ?? $t['status']) ?>
                                 </span>
                                 <?php if ($t['status'] === 'cancelled'): ?>
                                    <?php
                                    $cancelledByRequester = (int)($t['cancelled_by'] ?? 0) === (int)$t['requester_id'];
                                    $cancellationLabel = $t['cancelled_by'] === null
                                       ? 'ไม่ทราบผู้ยกเลิก'
                                       : ($cancelledByRequester ? 'ผู้แจ้งยกเลิก' : 'ผู้ดูแลยกเลิก');
                                    ?>
                                    <span class="admin-chip is-warning no-dot mt-3 flex w-fit">
                                       <i class="fa-solid fa-user-xmark" aria-hidden="true"></i>
                                       <?= h($cancellationLabel) ?>
                                    </span>
                                 <?php endif; ?>
                              </td>
                              <td class="px-4 py-4 text-xs">
                                 <?= !empty($t['tech_first']) ? h($t['tech_first'] . ' ' . $t['tech_last']) : '<span class="text-gray-400">ยังไม่มอบหมาย</span>' ?>
                              </td>
                              <td class="px-4 py-4">
                                 <?php if (!empty($statusTransitions[$t['status']])): ?>
                                    <button type="button" data-open-status-modal
                                       data-ticket-id="<?= (int)$t['ticket_id'] ?>"
                                       data-ticket-no="<?= h($t['ticket_no']) ?>"
                                       data-current-status="<?= h($t['status']) ?>"
                                       data-current-label="<?= h($statusLabel[$t['status']] ?? $t['status']) ?>"
                                       data-allowed-statuses="<?= h(json_encode($statusTransitions[$t['status']])) ?>"
                                       class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:border-gray-500 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-400">
                                       <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> อัปเดตสถานะ
                                    </button>
                                 <?php else: ?>
                                    <button type="button" disabled
                                       class="inline-flex cursor-not-allowed items-center gap-1.5 rounded-md border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-xs font-medium text-gray-500"
                                       title="ไม่สามารถเปลี่ยนสถานะหลังปิดงานได้">
                                       <i class="fa-solid fa-lock" aria-hidden="true"></i> ล็อกสถานะ
                                    </button>
                                 <?php endif; ?>
                                 <br>
                                 <button type="button" data-open-ticket-delete
                                    data-ticket-id="<?= (int)$t['ticket_id'] ?>"
                                    data-ticket-no="<?= h($t['ticket_no']) ?>"
                                    class="mt-2 inline-flex items-center gap-1.5 rounded-md border border-red-200 px-2.5 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-400">
                                    <i class="fa-regular fa-trash-can" aria-hidden="true"></i> ลบใบแจ้งซ่อม
                                 </button>
                              </td>
                           </tr>
                        <?php endforeach; ?>
                     </tbody>
                  </table>
               </div>
            </section>
         </div>

         <dialog id="statusUpdateModal" aria-labelledby="statusUpdateTitle" class="m-auto max-h-[90vh] w-[min(460px,calc(100%-2rem))] overflow-hidden rounded-2xl border border-gray-200 bg-white p-0 shadow-2xl backdrop:bg-gray-950/40 backdrop:backdrop-blur-sm">
            <form method="post" id="statusUpdateForm" class="flex max-h-[90vh] flex-col">
               <input type="hidden" name="csrf" value="<?= h($_SESSION['admin_csrf']) ?>">
               <input type="hidden" name="return_status" value="<?= h(is_string($_GET['status'] ?? null) ? $_GET['status'] : '') ?>">
               <input type="hidden" name="ticket_id" id="statusUpdateTicketId">
               <div class="flex items-start justify-between gap-4 border-b border-gray-100 bg-gradient-to-br from-gray-50 to-white px-5 py-4 sm:px-6">
                  <div class="flex min-w-0 items-start gap-3">
                     <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-900 text-white shadow-sm"><i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i></span>
                     <div class="min-w-0">
                        <p class="text-xs font-medium text-gray-500">จัดการใบงาน</p>
                        <h2 id="statusUpdateTitle" class="mt-0.5 truncate font-semibold text-gray-950"></h2>
                        <div class="mt-1.5 flex items-center gap-1.5 text-xs text-gray-500">
                           <span>สถานะปัจจุบัน</span>
                           <span id="statusUpdateCurrent" class="rounded-full border border-gray-200 bg-white px-2 py-0.5 font-medium text-gray-700"></span>
                        </div>
                     </div>
                  </div>
                  <button type="button" data-close-status-modal aria-label="ปิด" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-900"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
               </div>
               <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-5 pb-7 sm:px-6">
                  <div>
                     <label for="statusUpdateSelect" class="mb-1.5 block text-sm font-medium text-gray-800">สถานะใหม่</label>
                     <select name="status" id="statusUpdateSelect" required class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 shadow-sm outline-none transition focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10">
                        <option value="" selected disabled>เลือกสถานะใหม่</option>
                        <?php foreach ($statusLabel as $key => $label): ?>
                           <option value="<?= h($key) ?>"><?= h($label) ?></option>
                        <?php endforeach; ?>
                     </select>
                     <p class="mt-1.5 text-xs text-gray-500">เลือกสถานะที่ต้องการบันทึกให้กับใบงานนี้</p>
                  </div>
                  <div class="hidden rounded-xl border border-gray-200 bg-gray-50 p-3.5" data-status-reason-wrap>
                     <label for="statusUpdateReason" class="mb-1.5 block text-sm font-medium text-gray-800" data-status-reason-label></label>
                     <textarea name="status_reason" id="statusUpdateReason" rows="3" maxlength="255" class="w-full resize-y rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10" data-status-reason></textarea>
                     <p class="mt-1.5 text-xs text-gray-500">ข้อมูลนี้จะแสดงให้ผู้แจ้งเห็นในหน้าติดตามใบงาน</p>
                  </div>
               </div>
               <div class="sticky bottom-0 flex shrink-0 justify-end gap-2 border-t border-gray-100 bg-gray-50/95 px-5 py-3.5 shadow-[0_-4px_12px_rgba(15,23,42,0.04)] sm:px-6">
                  <button type="button" data-close-status-modal class="rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50">ปิด</button>
                  <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2"><i class="fa-solid fa-check" aria-hidden="true"></i> บันทึกสถานะ</button>
               </div>
            </form>
         </dialog>

         <dialog id="ticketDetailModal" aria-labelledby="ticketDetailTitle" class="m-auto max-h-[calc(100dvh-2rem)] w-[min(760px,calc(100%-2rem))] overflow-y-auto rounded-xl p-0 shadow-2xl backdrop:bg-black/60">
            <div class="sticky top-0 z-10 flex items-center justify-between border-b bg-white px-5 py-4">
               <div>
                  <p class="text-xs text-gray-500">รายละเอียดคำขอซ่อม</p>
                  <h2 id="ticketDetailTitle" class="font-mono text-lg font-semibold text-gray-900"></h2>
               </div>
               <button type="button" data-close-ticket-modal aria-label="ปิดรายละเอียด" class="flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-900">
                  <i class="fa-solid fa-xmark" aria-hidden="true"></i>
               </button>
            </div>
            <div class="space-y-5 p-5">
               <section>
                  <div class="mb-4 flex items-center gap-2 border-b border-gray-100 pb-3">
                     <span class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-900 text-xs text-white">1</span>
                     <h3 class="font-medium text-gray-900">ข้อมูลผู้แจ้ง</h3>
                  </div>
                  <div id="ticketDetailRequester" class="grid grid-cols-1 gap-4 sm:grid-cols-2"></div>
               </section>
               <section>
                  <div class="mb-4 flex items-center gap-2 border-b border-gray-100 pb-3">
                     <span class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-900 text-xs text-white">2</span>
                     <h3 class="font-medium text-gray-900">สถานที่และอุปกรณ์</h3>
                  </div>
                  <div id="ticketDetailLocation" class="grid grid-cols-1 gap-4 sm:grid-cols-2"></div>
               </section>
               <section>
                  <div class="mb-4 flex items-center gap-2 border-b border-gray-100 pb-3">
                     <span class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-900 text-xs text-white">3</span>
                     <h3 class="font-medium text-gray-900">อาการและความเร่งด่วน</h3>
                  </div>
                  <div id="ticketDetailStatus" class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2"></div>
                  <div>
                     <h4 class="mb-1 block text-sm font-medium text-gray-900">รายละเอียดอาการ</h4>
                     <p id="ticketDetailSymptoms" class="min-h-12 whitespace-pre-wrap break-words rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-800"></p>
                  </div>
                  <div id="ticketDetailResolution" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2"></div>
               </section>
               <section id="ticketDetailAttachmentsSection" class="hidden">
                  <div class="mb-4 flex items-center gap-2 border-b border-gray-100 pb-3">
                     <span class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-900 text-xs text-white">4</span>
                     <h3 class="font-medium text-gray-900">รูปประกอบ</h3>
                  </div>
                  <div id="ticketDetailAttachments" class="grid grid-cols-2 gap-3 sm:grid-cols-3"></div>
               </section>
            </div>
         </dialog>

         <!-- Modal ยืนยันการลบใบแจ้งซ่อม (ต้องพิมพ์รหัสใบแจ้งซ่อม) -->
         <dialog id="ticketDeleteModal" aria-labelledby="ticketDeleteTitle" class="m-auto w-[min(460px,calc(100%-2rem))] overflow-hidden rounded-2xl border border-red-100 bg-white p-0 shadow-2xl backdrop:bg-gray-950/40 backdrop:backdrop-blur-sm">
            <form method="post" id="ticketDeleteForm">
               <input type="hidden" name="csrf" value="<?= h($_SESSION['admin_csrf']) ?>">
               <input type="hidden" name="action" value="delete_ticket">
               <input type="hidden" name="return_status" value="<?= h(is_string($_GET['status'] ?? null) ? $_GET['status'] : '') ?>">
               <input type="hidden" name="ticket_id" id="ticketDeleteTicketId">
               <div class="flex items-start justify-between gap-4 border-b border-red-100 bg-gradient-to-br from-red-50 to-white px-5 py-4 sm:px-6">
                  <div class="flex min-w-0 items-start gap-3">
                     <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-600 text-white shadow-sm"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
                     <div class="min-w-0">
                        <p class="text-xs font-medium text-red-600">การดำเนินการที่ย้อนกลับไม่ได้</p>
                        <h2 id="ticketDeleteTitle" class="mt-0.5 font-semibold text-gray-950">ยืนยันการลบใบแจ้งซ่อม</h2>
                     </div>
                  </div>
                  <button type="button" data-close-ticket-delete aria-label="ปิด" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-900"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
               </div>
               <div class="space-y-4 px-5 py-5 sm:px-6">
                  <p class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                     กำลังจะลบใบแจ้งซ่อม <span class="font-mono font-semibold" id="ticketDeleteTicketNo"></span> พร้อมรูปแนบและประวัติการดำเนินงานทั้งหมด
                  </p>
                  <div>
                     <label for="ticketDeleteConfirmInput" class="mb-1.5 block text-sm font-medium text-gray-800">พิมพ์รหัสใบแจ้งซ่อมเพื่อยืนยันการลบ</label>
                     <input type="text" id="ticketDeleteConfirmInput" name="confirm_ticket_no" autocomplete="off" spellcheck="false" autocapitalize="off" autocorrect="off" placeholder="รหัสใบแจ้งซ่อมของใบงานนี้"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 font-mono text-sm outline-none transition placeholder:font-kanit placeholder:text-gray-400 focus:border-red-600 focus:ring-2 focus:ring-red-600/10">
                     <p id="ticketDeleteHint" class="mt-1.5 text-xs text-gray-500">ไม่ต้องพิมพ์เครื่องหมาย # และยังไม่ต้องสนใจตัวพิมพ์เล็ก-ใหญ่หรือช่องว่าง</p>
                  </div>
               </div>
               <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50/70 px-5 py-3.5 sm:px-6">
                  <button type="button" data-close-ticket-delete class="rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50">ปิด</button>
                  <button type="submit" id="ticketDeleteSubmit" disabled class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition enabled:hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-40 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">
                     <i class="fa-regular fa-trash-can" aria-hidden="true"></i> ยืนยันลบใบแจ้งซ่อม
                  </button>
               </div>
            </form>
         </dialog>

         <script>
            (function () {
               const deleteModal = document.getElementById('ticketDeleteModal');
               const deleteForm = document.getElementById('ticketDeleteForm');
               const deleteConfirmInput = document.getElementById('ticketDeleteConfirmInput');
               const deleteSubmit = document.getElementById('ticketDeleteSubmit');
               const deleteHint = document.getElementById('ticketDeleteHint');
               const deleteHintDefault = deleteHint ? deleteHint.textContent : '';
               const expectedTicketNo = document.getElementById('ticketDeleteTicketNo');
               let expected = '';

               // เทียบแบบไม่สนตัวพิมพ์เล็ก-ใหญ่และช่องว่าง เพื่อกันพิมพ์ตรงแล้วจับไม่ได้
               function normalize(value) {
                  return String(value || '').replace(/\s+/g, '').replace(/^#+/, '').toUpperCase();
               }

               function refreshDeleteState() {
                  const typed = normalize(deleteConfirmInput.value);
                  const match = expected !== '' && typed !== '' && typed === normalize(expected);
                  deleteSubmit.disabled = !match;
                  if (deleteHint) {
                     if (deleteConfirmInput.value.trim() === '') {
                        deleteHint.textContent = deleteHintDefault;
                        deleteHint.className = 'mt-1.5 text-xs text-gray-500';
                     } else if (match) {
                        deleteHint.textContent = 'รหัสตรงกันแล้ว กด "ยืนยันลบใบแจ้งซ่อม" เพื่อทำรายการ';
                        deleteHint.className = 'mt-1.5 text-xs font-medium text-emerald-600';
                     } else {
                        deleteHint.textContent = 'รหัสที่พิมพ์ยังไม่ตรงกัน กรุณาตรวจสอบอีกครั้ง';
                        deleteHint.className = 'mt-1.5 text-xs font-medium text-amber-600';
                     }
                  }
               }

               document.querySelectorAll('[data-open-ticket-delete]').forEach(function (button) {
                  button.addEventListener('click', function () {
                     document.getElementById('ticketDeleteTicketId').value = button.dataset.ticketId;
                     expected = String(button.dataset.ticketNo || '');
                     expectedTicketNo.textContent = expected;
                     deleteConfirmInput.value = '';
                     refreshDeleteState();
                     deleteModal.showModal();
                     deleteConfirmInput.focus();
                  });
               });

               // ผูกหลายเหตุการณ์เพื่อให้ตรวจทันทีที่ป้อน ไม่ว่าเบราว์เซอร์หรือวิธีพิมพ์ใด
               ['input', 'change', 'keyup', 'paste', 'cut'].forEach(function (type) {
                  deleteConfirmInput.addEventListener(type, function () { refreshDeleteState(); });
               });

               // และถ้าปุ่มยืนยันถูกกดโดยที่รหัสไม่ตรง ให้ยกเลิกการส่งก่อนเซิร์ฟเวอร์
               if (deleteForm) {
                  deleteForm.addEventListener('submit', function (event) {
                     if (normalize(deleteConfirmInput.value) !== normalize(expected)) {
                        event.preventDefault();
                        refreshDeleteState();
                        deleteHint && (deleteHint.textContent = '✗ รหัสใบแจ้งซ่อมไม่ตรง จึงยังลบไม่ได้');
                        return;
                     }
                     deleteSubmit.disabled = true;
                  });
               }

               deleteModal.querySelectorAll('[data-close-ticket-delete]').forEach(function (button) {
                  button.addEventListener('click', function () { deleteModal.close(); });
               });
               deleteModal.addEventListener('click', function (event) {
                  if (event.target === deleteModal) deleteModal.close();
               });
            })();
            (function () {
               const statusModal = document.getElementById('statusUpdateModal');
               const statusSelect = document.getElementById('statusUpdateSelect');
               const statusReasonWrap = statusModal.querySelector('[data-status-reason-wrap]');
               const statusReasonLabel = statusModal.querySelector('[data-status-reason-label]');
               const statusReason = statusModal.querySelector('[data-status-reason]');
               function updateStatusFields() {
                  const selectedStatus = statusSelect.value;
                  const needsReason = selectedStatus === 'completed' || selectedStatus === 'cancelled';
                  const isCompleted = selectedStatus === 'completed';
                  statusReasonWrap.classList.toggle('hidden', !needsReason);
                  statusReason.required = needsReason;
                  statusReasonLabel.textContent = isCompleted ? 'สรุปการดำเนินงาน (จำเป็น)' : 'เหตุผลการยกเลิก (จำเป็น)';
                  statusReason.placeholder = isCompleted ? 'ระบุสิ่งที่ดำเนินการหรือผลการแก้ไข' : 'ระบุเหตุผลที่ยกเลิกคำขอ';
               }
               document.querySelectorAll('[data-open-status-modal]').forEach(function (button) {
                  button.addEventListener('click', function () {
                     document.getElementById('statusUpdateTicketId').value = button.dataset.ticketId;
                     document.getElementById('statusUpdateTitle').textContent = '#' + button.dataset.ticketNo;
                     document.getElementById('statusUpdateCurrent').textContent = button.dataset.currentLabel;
                     let allowedStatuses = [];
                     try {
                        allowedStatuses = JSON.parse(button.dataset.allowedStatuses);
                     } catch (error) {
                        return;
                     }
                     Array.from(statusSelect.options).forEach(function (option) {
                        const available = option.value === '' || allowedStatuses.includes(option.value);
                        option.hidden = !available;
                        option.disabled = !available;
                     });
                     statusSelect.value = '';
                     statusReason.value = '';
                     updateStatusFields();
                     statusModal.showModal();
                  });
               });
               statusSelect.addEventListener('change', updateStatusFields);
               statusModal.querySelectorAll('[data-close-status-modal]').forEach(function (button) {
                  button.addEventListener('click', function () { statusModal.close(); });
               });
               statusModal.addEventListener('click', function (event) {
                  if (event.target === statusModal) statusModal.close();
               });

               const modal = document.getElementById('ticketDetailModal');
               const title = document.getElementById('ticketDetailTitle');
               const requesterFields = document.getElementById('ticketDetailRequester');
               const locationFields = document.getElementById('ticketDetailLocation');
               const statusFields = document.getElementById('ticketDetailStatus');
               const resolutionFields = document.getElementById('ticketDetailResolution');
               const symptoms = document.getElementById('ticketDetailSymptoms');
               const attachmentSection = document.getElementById('ticketDetailAttachmentsSection');
               const attachments = document.getElementById('ticketDetailAttachments');
               function addField(container, label, value, fullWidth, highlight) {
                  if (!value) return;
                  const wrapper = document.createElement('div');
                  wrapper.className = 'min-w-0' + (fullWidth ? ' sm:col-span-2' : '');
                  const labelElement = document.createElement('div');
                  labelElement.className = 'mb-1 block text-xs ' + (highlight ? 'font-medium text-amber-800' : 'text-gray-500');
                  labelElement.textContent = label;
                  const valueElement = document.createElement('div');
                  valueElement.className = highlight
                     ? 'break-words whitespace-pre-wrap rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-950'
                     : 'break-words whitespace-pre-wrap rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-800';
                  valueElement.textContent = value;
                  wrapper.append(labelElement, valueElement);
                  container.appendChild(wrapper);
               }

               document.querySelectorAll('[data-ticket-details]').forEach(function (button) {
                  button.addEventListener('click', function () {
                     let details;
                     try {
                        details = JSON.parse(button.dataset.ticketDetails);
                     } catch (error) {
                        return;
                     }

                     title.textContent = '#' + (details.ticketNo || '');
                     [requesterFields, locationFields, statusFields, resolutionFields].forEach(function (container) {
                        container.replaceChildren();
                     });
                     addField(requesterFields, 'ชื่อผู้แจ้ง', details.requester);
                     addField(requesterFields, 'ประเภท / แผนกวิชา', details.userTypeDepartment);
                     addField(requesterFields, 'รหัสผู้แจ้ง', details.idNumber);
                     addField(requesterFields, 'เบอร์ติดต่อ', details.phone);
                     addField(requesterFields, 'อีเมล', details.email, true);
                     addField(locationFields, 'สถานที่ / ห้อง', details.location);
                     addField(locationFields, 'หมวดหมู่', details.category);
                     addField(statusFields, 'วันที่แจ้ง', details.createdAt);
                     addField(statusFields, 'สถานะ', details.status);
                     addField(statusFields, 'ความเร่งด่วน', details.urgency);
                     addField(statusFields, 'ผู้รับผิดชอบ', details.assignee);
                     addField(statusFields, 'เริ่มดำเนินการ', details.acceptedAt);
                     addField(statusFields, 'วันที่เสร็จสิ้น', details.completedAt);
                     symptoms.textContent = details.symptoms || 'ไม่มีรายละเอียด';
                     addField(resolutionFields, 'สรุปการแก้ไข', details.resolutionSummary, true);
                     addField(resolutionFields, (details.cancelledByLabel || 'เหตุผลการยกเลิก') + ' · เหตุผล', details.cancelReason, true, true);
                     attachments.replaceChildren();
                     const imagePaths = Array.isArray(details.attachments) ? details.attachments : [];
                     attachmentSection.classList.toggle('hidden', imagePaths.length === 0);
                     imagePaths.forEach(function (path) {
                        const link = document.createElement('a');
                        link.href = 'ticket_attachment.php?id=' + encodeURIComponent(details.ticket_id) + '&attachment=' + encodeURIComponent(path);
                        link.target = '_blank';
                        link.rel = 'noopener noreferrer';
                        link.className = 'block overflow-hidden rounded-lg border border-gray-200 bg-gray-50';
                        link.setAttribute('aria-label', 'เปิดรูปภาพขนาดเต็ม');
                        const image = document.createElement('img');
                        image.src = 'ticket_attachment.php?id=' + encodeURIComponent(details.ticket_id) + '&attachment=' + encodeURIComponent(path);
                        image.alt = 'รูปประกอบคำขอซ่อม';
                        image.loading = 'eager';
                        image.decoding = 'async';
                        image.className = 'h-40 w-full object-contain';
                        link.appendChild(image);
                        attachments.appendChild(link);
                     });
                     modal.showModal();
                  });
               });

               modal.querySelector('[data-close-ticket-modal]').addEventListener('click', function () {
                  modal.close();
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
