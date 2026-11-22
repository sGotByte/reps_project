<?php
<<<<<<< HEAD
$page_title = 'จัดการคำขอซ่อม';
$current_page = 'request';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/ticket_service.php';
=======
$page_title = 'Requests';
$current_page = 'request';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../db_connect.php';
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff

if (session_status() === PHP_SESSION_NONE) {
   session_start();
}

<<<<<<< HEAD
queue_process_expired_tickets($conn);
ticket_send_sign_reminders($conn); // เตือนผู้แจ้งที่ยังไม่เซ็น ทำงานไม่เกินทุก 10 นาที

=======
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
if (empty($_SESSION['admin_csrf'])) {
   $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$message = is_array($_SESSION['admin_flash'] ?? null) ? (string)($_SESSION['admin_flash']['message'] ?? '') : '';
$message_type = is_array($_SESSION['admin_flash'] ?? null) ? (string)($_SESSION['admin_flash']['type'] ?? 'success') : 'success';
unset($_SESSION['admin_flash']);
<<<<<<< HEAD
// ตัวกรองรับได้ทั้งกลุ่มสถานะ (pending/working/signing/completed/cancelled) และสถานะรายตัว
$allowed = array_values(array_unique(array_merge(array_keys(TICKET_STATUS_GROUPS), array_keys(TICKET_STATUS_LABELS))));
// v3: ปิดงานได้ทางเดียวคือเซ็นครบ 3 ฝ่าย (staff/job.php, sign.php, admin_signoff.php)
// หน้านี้เปลี่ยนสถานะเองได้แค่ "ยกเลิก" ก่อนเข้าขั้นเซ็นส่งคืน
$statusTransitions = array_fill_keys(array_keys(TICKET_STATUS_LABELS), []);
foreach (array_merge(['pending'], TICKET_STAFF_ACTIVE_STATUSES) as $st) {
   $statusTransitions[$st] = ['cancelled'];
}
=======
$allowed = ['pending', 'in_progress', 'completed', 'cancelled'];
$statusTransitions = [
   'pending' => ['in_progress', 'cancelled'],
   'in_progress' => ['completed', 'cancelled'],
   'completed' => [],
   'cancelled' => []
];
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff

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
<<<<<<< HEAD
               $fileStmt = $conn->prepare(
                  'SELECT file_path FROM ticket_attachments WHERE ticket_id = ?
                   UNION ALL SELECT completion_image FROM tickets WHERE id = ? AND completion_image IS NOT NULL
                   UNION ALL SELECT signature_image FROM ticket_signoffs WHERE ticket_id = ? AND signature_image IS NOT NULL'
               );
               if (!$fileStmt) {
                  throw new RuntimeException($conn->error);
               }
               $fileStmt->bind_param('iii', $id, $id, $id);
=======
               $fileStmt = $conn->prepare('SELECT file_path FROM ticket_attachments WHERE ticket_id = ?');
               if (!$fileStmt) {
                  throw new RuntimeException($conn->error);
               }
               $fileStmt->bind_param('i', $id);
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
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

<<<<<<< HEAD
               $attachmentFiles = array_column($fileRows, 'file_path');
=======
               foreach ($fileRows as $fileRow) {
                  if (preg_match('#^(?:ticket-private/)?([A-Za-z0-9_-]+\.(?:jpg|png))$#i', (string)$fileRow['file_path'], $m) === 1) {
                     $attachmentFiles[] = $m[1];
                  }
               }
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
            }
         } catch (Throwable $e) {
            $conn->rollback();
            error_log('Ticket deletion failed for ticket ' . $id . ': ' . $e->getMessage());
            $message = 'ลบใบแจ้งซ่อมไม่สำเร็จ กรุณาตรวจสอบฐานข้อมูล';
            $message_type = 'error';
         }

         // ลบไฟล์แนบจริงหลังลบข้อมูลในฐานข้อมูลสำเร็จ
<<<<<<< HEAD
         foreach ($attachmentFiles as $storedPath) {
            try {
               app_delete_private_file((string)$storedPath);
            } catch (Throwable $e) {
               error_log('Could not delete ticket file: ' . $e->getMessage());
            }
         }
      }
   } elseif ($action === 'assign_team') {
      // มอบหมายทีมช่าง: ช่างหลัก 1 คน (หรือคิวกลาง) + ผู้ช่วยหลายคน
      $id = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT) ?: 0;
      $leadId = filter_input(INPUT_POST, 'lead_id', FILTER_VALIDATE_INT) ?: 0;
      $helperIds = is_array($_POST['helper_ids'] ?? null) ? $_POST['helper_ids'] : [];
      try {
         $message = ticket_assign_team($conn, $id, $leadId, $helperIds, (int)$_SESSION['user_id']);
      } catch (AppUserException $e) {
         $message = $e->getMessage();
         $message_type = 'error';
      } catch (Throwable $e) {
         error_log('Assign team failed: ' . $e->getMessage());
         $message = 'บันทึกการมอบหมายไม่สำเร็จ กรุณาลองใหม่';
         $message_type = 'error';
      }
   } elseif ($action === 'assign_ticket') {
      // 3.3 มอบหมายใบงานที่รอรับให้สตาฟ (โหมด manual หรือ admin ต้องการเลือกเอง)
      $id = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
      $staffId = filter_input(INPUT_POST, 'staff_id', FILTER_VALIDATE_INT);
      try {
         if ($id && $staffId === 0) {
            db_exec($conn, "UPDATE tickets SET queue_assigned_to = NULL, queue_expires_at = NULL WHERE id = ? AND status = 'pending'", 'i', [$id]);
            $message = 'ย้ายใบงานกลับเป็นคิวกลางแล้ว';
         } elseif ($id && $staffId && queue_admin_assign($conn, $id, $staffId)) {
            $message = 'มอบหมายใบงานให้สตาฟแล้ว รอสตาฟกดรับงาน';
         } else {
            $message = 'มอบหมายไม่สำเร็จ (ใบงานต้องอยู่ในสถานะรอรับเรื่อง และสตาฟต้องใช้งานอยู่)';
            $message_type = 'error';
         }
      } catch (Throwable $e) {
         error_log('Assign ticket failed: ' . $e->getMessage());
         $message = 'มอบหมายไม่สำเร็จ กรุณาลองใหม่';
         $message_type = 'error';
      }
=======
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
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
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
<<<<<<< HEAD
               } elseif (in_array($row['status'], TICKET_SIGNING_STATUSES, true)) {
                  $message = 'ใบงานอยู่ระหว่างเซ็นส่งคืน จัดการได้ที่หน้ารับรองงาน';
=======
               } elseif ($row['status'] === 'in_progress' && $status === 'pending') {
                  $message = 'ใบงานที่กำลังดำเนินการไม่สามารถเปลี่ยนกลับเป็นรอรับเรื่องได้';
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
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
<<<<<<< HEAD
                            stage_since = NOW(),
=======
                            assigned_to = CASE WHEN ? = 'in_progress' AND assigned_to IS NULL THEN ? ELSE assigned_to END,
                            accepted_at = CASE WHEN ? = 'in_progress' AND accepted_at IS NULL THEN NOW() ELSE accepted_at END, 
                            completed_at = CASE WHEN ? = 'completed' THEN NOW() ELSE NULL END,
                            resolution_summary = CASE WHEN ? = 'completed' THEN ? ELSE NULL END,
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
                            cancel_reason = CASE WHEN ? = 'cancelled' THEN ? ELSE NULL END
                          WHERE id = ?";
               $u_stmt = $conn->prepare($u_sql);
               if (!$u_stmt) {
                  throw new RuntimeException($conn->error);
               }
<<<<<<< HEAD
               $u_stmt->bind_param('sssi', $status, $status, $statusReason, $id);
=======
               $u_stmt->bind_param('ssissssssi', $status, $status, $actor, $status, $status, $status, $statusReason, $status, $statusReason, $id);
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
               if (!$u_stmt->execute()) {
                  throw new RuntimeException($u_stmt->error);
               }
               $u_stmt->close();

               // บันทึกประวัติลง ticket_logs
<<<<<<< HEAD
               $log_stmt = $conn->prepare('INSERT INTO ticket_logs (ticket_id, old_status, new_status, action_by, note, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
               if (!$log_stmt) {
                  throw new RuntimeException($conn->error);
               }
               $logNote = $statusReason !== '' ? 'เหตุผล: ' . $statusReason : null;
               $log_stmt->bind_param('issis', $id, $old_status, $status, $actor, $logNote);
=======
               $log_stmt = $conn->prepare('INSERT INTO ticket_logs (ticket_id, old_status, new_status, action_by, created_at) VALUES (?, ?, ?, ?, NOW())');
               if (!$log_stmt) {
                  throw new RuntimeException($conn->error);
               }
               $log_stmt->bind_param('issi', $id, $old_status, $status, $actor);
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
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
<<<<<<< HEAD
   // กลับไปหน้า/ตัวกรอง/คำค้นเดิม (อ่านจาก Referer ของหน้านี้เท่านั้น และรับเฉพาะพารามิเตอร์ที่รู้จัก)
   $returnParams = [];
   $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
   if ($referer !== '' && str_ends_with((string)parse_url($referer, PHP_URL_PATH), '/admin_manage_request.php')) {
      parse_str((string)parse_url($referer, PHP_URL_QUERY), $refQuery);
      if (in_array($refQuery['status'] ?? '', $allowed, true)) $returnParams['status'] = $refQuery['status'];
      if (is_string($refQuery['q'] ?? null) && $refQuery['q'] !== '') $returnParams['q'] = mb_substr($refQuery['q'], 0, 100, 'UTF-8');
      if (ctype_digit((string)($refQuery['page'] ?? '')) && (int)$refQuery['page'] > 1) $returnParams['page'] = (int)$refQuery['page'];
   }
   $returnStatus = is_string($_POST['return_status'] ?? null) ? $_POST['return_status'] : '';
   if (!$returnParams && in_array($returnStatus, $allowed, true)) $returnParams['status'] = $returnStatus;
   if ($returnParams) $redirect .= '?' . http_build_query($returnParams);
=======
   $returnStatus = is_string($_POST['return_status'] ?? null) ? $_POST['return_status'] : '';
   if (in_array($returnStatus, $allowed, true)) $redirect .= '?status=' . rawurlencode($returnStatus);
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
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
<<<<<<< HEAD
$search = mb_substr(trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : ''), 0, 100, 'UTF-8');
require_once __DIR__ . '/../includes/pager.php';
$perPageOptions = [10, 30, 50, 100];
$perPage = pager_per_page($perPageOptions, 30);
$page = max(1, (int)($_GET['page'] ?? 1));
$conditions = [];
$bindTypes = '';
$bindValues = [];
if ($filter === 'awaiting_signoff') {
   $filter = 'signing'; // ลิงก์เดิมก่อน v3
}
if (isset(TICKET_STATUS_GROUPS[$filter])) {
   $conditions[] = 't.status IN (' . ticket_status_sql(TICKET_STATUS_GROUPS[$filter]) . ')';
} elseif (in_array($filter, $allowed, true)) {
   $conditions[] = 't.status = ?';
   $bindTypes .= 's';
   $bindValues[] = $filter;
} else {
   $filter = '';
}
if ($search !== '') {
   $like = '%' . addcslashes($search, '%_\\') . '%';
   $conditions[] = '(t.ticket_no LIKE ? OR t.location_note LIKE ? OR t.symptom LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.id_number LIKE ?)';
   $bindTypes .= 'ssssss';
   array_push($bindValues, $like, $like, $like, $like, $like, $like);
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$totalFiltered = 0;
$maxPage = 1;
$listUrl = static function (array $override = []) use (&$filter, &$search, &$page): string {
   $params = array_filter(array_merge(['status' => $filter, 'q' => $search, 'per' => $GLOBALS['perPage'] === 30 ? '' : $GLOBALS['perPage'], 'page' => $page], $override), static fn($v) => $v !== '' && $v !== null && $v !== 1);
   return 'admin_manage_request.php' . ($params ? '?' . http_build_query($params) : '');
};

$tickets = [];
$counts = array_fill_keys($allowed, 0);
$queueMode = queue_settings($conn)['mode'];
$staffOptions = [];
try {
   // สตาฟพร้อมภาระงานปัจจุบัน (ช่วยให้ admin เลือกคนที่ว่าง)
   $staffOptions = db_all(
      $conn,
      "SELECT u.user_id, u.first_name, u.last_name, u.is_active_queue, " . term_score_sql(term_current($conn)) . " AS score,
              (SELECT COUNT(*) FROM tickets t WHERE t.status IN ('accepted', 'awaiting_intake_sign', 'in_progress', 'inspected_ready')
                  AND (t.assigned_to = u.user_id OR EXISTS (SELECT 1 FROM ticket_assignees a WHERE a.ticket_id = t.id AND a.user_id = u.user_id))) AS active_jobs
         FROM users u
        WHERE u.role = 'staff' AND u.status = 'active'" . term_staff_sql($conn) . "
        ORDER BY u.is_active_queue DESC, active_jobs ASC, COALESCE(u.queue_order, 9999), u.first_name"
   );
} catch (Throwable $e) {
   error_log('Staff list failed: ' . $e->getMessage());
}
=======
$where = in_array($filter, $allowed, true) ? 'WHERE t.status = ?' : '';

$tickets = [];
$counts = ['pending' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0];
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
$categoryStats = [];

try {
   $sql = 'SELECT 
                t.id AS ticket_id, t.ticket_no, NULL AS sub_category, t.symptom AS symptoms, t.urgency, t.status, t.created_at,
                t.accepted_at, t.completed_at, t.resolution_summary, t.cancel_reason,
<<<<<<< HEAD
                t.custom_category, t.device_detail, t.quantity, t.contact_person,
                t.primary_contact_name, t.primary_contact_phone, t.primary_contact_email,
                t.secondary_contact_name, t.secondary_contact_phone, t.secondary_contact_email, t.urgency_reason,
                t.coordinator_teacher, t.coordinator_phone, t.room_caretaker, t.room_caretaker_phone,
                t.repair_method, t.completion_image, t.queue_assigned_to, t.queue_expires_at, t.assigned_to,
                qs.first_name AS queue_first, qs.last_name AS queue_last,
=======
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
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
<<<<<<< HEAD
            LEFT JOIN categories c ON c.id = t.category_id
            LEFT JOIN users assignee ON assignee.user_id = t.assigned_to
            LEFT JOIN users qs ON qs.user_id = t.queue_assigned_to
            ' . $where . '
            ORDER BY t.created_at DESC, t.id DESC
            LIMIT ? OFFSET ?';

   // แบ่งหน้า: นับจำนวนตามตัวกรองก่อน แล้วดึงเฉพาะหน้าที่เปิด
   $totalFiltered = (int)(db_one($conn, 'SELECT COUNT(*) AS n FROM tickets t JOIN users u ON u.user_id = t.requester_id ' . $where, $bindTypes, $bindValues)['n'] ?? 0);
   $maxPage = max(1, (int)ceil($totalFiltered / $perPage));
   $page = min($page, $maxPage);
   $tickets = db_all($conn, $sql, $bindTypes . 'ii', array_merge($bindValues, [$perPage, ($page - 1) * $perPage]));

   $attachmentsByTicket = [];
   $helpersByTicket = [];
=======
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
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
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
<<<<<<< HEAD
         $attachmentsByTicket[(int)$attachment['ticket_id']][] = (int)$attachment['id'];
      }
      $attachmentStmt->close();

      $helpersByTicket = [];
      $helperRows = db_all(
         $conn,
         "SELECT a.ticket_id, u.user_id, u.first_name, u.last_name
            FROM ticket_assignees a JOIN users u ON u.user_id = a.user_id
           WHERE a.ticket_id IN ({$placeholders}) ORDER BY u.first_name",
         $attachmentTypes,
         $ticketIds
      );
      foreach ($helperRows as $hr) {
         $helpersByTicket[(int)$hr['ticket_id']][] = ['id' => (int)$hr['user_id'], 'name' => $hr['first_name'] . ' ' . $hr['last_name']];
      }
=======
         $path = $attachment['file_path'];
         if (preg_match('#^(?:ticket-private/)?[A-Za-z0-9_-]+\.(?:jpg|png)$#i', str_replace('ticket-private/', '', $path))) {
            $attachmentsByTicket[(int)$attachment['ticket_id']][] = (int)$attachment['id'];
         }
      }
      $attachmentStmt->close();
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
   }

   // ดึงจำนวนสถิติแยกตามสถานะ
   $count_res = $conn->query('SELECT status, COUNT(*) AS total FROM tickets GROUP BY status');
   if ($count_res) {
      while ($c_row = $count_res->fetch_assoc()) {
         if (isset($counts[$c_row['status']])) {
            $counts[$c_row['status']] = (int)$c_row['total'];
         }
<<<<<<< HEAD
         $grp = ticket_status_group((string)$c_row['status']);
         if ($grp !== $c_row['status'] && isset($counts[$grp])) {
            $counts[$grp] += (int)$c_row['total'];
         }
=======
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
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

<<<<<<< HEAD
// ---------- แถบสรุปสถานะ: แนวโน้ม 14 วัน + งานที่ค้างนานสุด ----------
$trendDays = 14;
$trendDates = [];
for ($i = $trendDays - 1; $i >= 0; $i--) {
   $trendDates[] = date('Y-m-d', strtotime("-{$i} day"));
}
$trend = array_fill_keys(array_keys(TICKET_STATUS_GROUPS), array_fill_keys($trendDates, 0));
// เหตุการณ์ที่นับเป็นแนวโน้มของแต่ละกลุ่ม: แจ้งใหม่ / สตาฟรับงาน / ซ่อมเสร็จส่งเซ็น / รับรองเสร็จ / ยกเลิก
$trendEvent = ['pending' => 'pending', 'accepted' => 'working', 'in_progress' => 'working', 'inspected_ready' => 'signing', 'completed' => 'completed', 'cancelled' => 'cancelled'];
$oldest = ['pending' => null, 'working' => null, 'signing' => null];
$closedThisMonth = ['completed' => 0, 'cancelled' => 0];
try {
   // "รอรับเรื่อง" นับเฉพาะใบงานใหม่ (log แรก) ไม่นับการตีกลับ
   $trendRows = db_all(
      $conn,
      "SELECT new_status, DATE(created_at) AS d, COUNT(*) AS n
         FROM ticket_logs
        WHERE created_at >= CURDATE() - INTERVAL ? DAY
          AND (new_status <> 'pending' OR old_status IS NULL)
          AND (new_status <> 'in_progress' OR old_status = 'pending')
        GROUP BY new_status, DATE(created_at)",
      'i',
      [$trendDays - 1]
   );
   foreach ($trendRows as $row) {
      $grp = $trendEvent[$row['new_status']] ?? null;
      if ($grp !== null && isset($trend[$grp][$row['d']])) {
         $trend[$grp][$row['d']] += (int)$row['n'];
      }
   }
   $age = db_one(
      $conn,
      "SELECT (SELECT MIN(created_at) FROM tickets WHERE status = 'pending') AS pending,
              (SELECT MIN(accepted_at) FROM tickets WHERE status IN ('accepted', 'awaiting_intake_sign', 'in_progress', 'inspected_ready')) AS working,
              (SELECT MIN(stage_since) FROM tickets WHERE status IN ('awaiting_requester_sign', 'awaiting_admin_sign', 'disputed')) AS signing,
              (SELECT COUNT(*) FROM tickets WHERE status = 'completed' AND completed_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS completed_month,
              (SELECT COUNT(*) FROM ticket_logs WHERE new_status = 'cancelled' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS cancelled_month"
   ) ?? [];
   foreach ($oldest as $key => $_) {
      $oldest[$key] = !empty($age[$key]) ? strtotime($age[$key]) : null;
   }
   $closedThisMonth = ['completed' => (int)($age['completed_month'] ?? 0), 'cancelled' => (int)($age['cancelled_month'] ?? 0)];
} catch (Throwable $e) {
   error_log('Request overview stats failed: ' . $e->getMessage());
}

function rq_ago(?int $ts): string
{
   if ($ts === null) return '';
   $minutes = max(0, (int)floor((time() - $ts) / 60));
   if ($minutes < 60) return $minutes . ' นาที';
   if ($minutes < 1440) return floor($minutes / 60) . ' ชม.';
   return floor($minutes / 1440) . ' วัน';
}

/** กราฟแท่งเล็ก 14 วัน (SVG) — แท่งขวาสุดคือวันนี้ */
function rq_sparkline(array $values, string $label): string
{
   $max = max(1, ...array_values($values));
   $total = array_sum($values);
   $barW = 6;
   $gap = 2;
   $h = 28;
   $count = count($values);
   $w = $count * ($barW + $gap) - $gap;
   $bars = '';
   $i = 0;
   foreach ($values as $v) {
      $bh = $v > 0 ? max(3, (int)round($v / $max * $h)) : 1;
      $x = $i * ($barW + $gap);
      $opacity = $i === $count - 1 ? '1' : '.45';
      $bars .= '<rect x="' . $x . '" y="' . ($h - $bh) . '" width="' . $barW . '" height="' . $bh . '" rx="1.5" fill="currentColor" opacity="' . ($v > 0 ? $opacity : '.25') . '"/>';
      $i++;
   }
   $aria = $label . ' 14 วันล่าสุด รวม ' . $total . ' รายการ สูงสุด ' . max($values) . ' ต่อวัน วันนี้ ' . end($values);
   return '<svg class="rq-spark" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '" role="img" aria-label="' . h($aria) . '"><title>' . h($aria) . '</title>' . $bars . '</svg>';
}

$statusLabel = TICKET_STATUS_LABELS;
=======
$statusLabel = [
   'pending' => 'รอรับเรื่อง',
   'in_progress' => 'กำลังดำเนินการ',
   'completed' => 'เสร็จสิ้น',
   'cancelled' => 'ยกเลิก'
];
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
$userTypeLabels = ['student' => 'นักศึกษา', 'teacher' => 'ครู/อาจารย์', 'staff' => 'บุคลากร'];

require_once __DIR__ . '/admin_header.php';
?>

         <div class="admin-page-head">
            <nav class="admin-crumb">
<<<<<<< HEAD
               <a href="admin_dashboard.php"><?= tr('แดชบอร์ด', 'Dashboard') ?></a>
               <i class="fa-solid fa-chevron-right"></i>
               <span><?= tr('จัดการคำขอซ่อม', 'Repair requests') ?></span>
            </nav>
            <h1 class="admin-page-title"><?= tr('จัดการคำขอซ่อม', 'Repair requests') ?></h1>
            <p class="admin-page-sub"><?= tr('ค้นหา ติดตาม และปรับสถานะงานซ่อม', 'Search, track and update repair jobs') ?></p>
=======
               <a href="admin_dashboard.php">Dashboard</a>
               <i class="fa-solid fa-chevron-right"></i>
               <span>จัดการคำขอซ่อม</span>
            </nav>
            <h1 class="admin-page-title">จัดการคำขอซ่อม</h1>
            <p class="admin-page-sub">ค้นหา ติดตาม และปรับสถานะงานซ่อม</p>
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
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

<<<<<<< HEAD
         <style>
            /* ===== หน้าจัดการคำขอซ่อม: แถบสถานะ + ตาราง ===== */
            .rq-overview { display: grid; gap: 16px; margin-bottom: 24px; }
            @media (min-width: 1180px) { .rq-overview { grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); } }
            .rq-group { min-width: 0; }
            .rq-group-head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin: 0 2px 8px; }
            .rq-group-title { margin: 0; font-size: 14px; font-weight: 500; color: #3F3D39; }
            .rq-group-meta { font-size: 13px; color: #6B6862; }
            .rq-cells { display: grid; gap: 1px; overflow: hidden; border: 1px solid #E7E4DF; border-radius: 14px; background: #E7E4DF; }
            .rq-group--open .rq-cells { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .rq-group--closed .rq-cells { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .rq-stat { position: relative; display: flex; flex-direction: column; gap: 10px; min-width: 0; padding: 16px 18px 14px; background: #fff; color: #161616; text-decoration: none; transition: background-color .15s ease; }
            .rq-stat:hover { background: #FAFAF8; }
            .rq-stat[aria-current="true"] { background: #FAFAF8; box-shadow: inset 0 -3px 0 var(--c, #161616); }
            .rq-stat-label { display: flex; align-items: center; gap: 8px; font-size: 14px; color: #3F3D39; }
            .rq-stat-label::before { content: ''; flex: none; width: 8px; height: 8px; border-radius: 50%; background: var(--c, #9A968F); }
            .rq-stat-body { display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; }
            .rq-stat-value { font-size: 32px; font-weight: 600; line-height: 1; letter-spacing: -.02em; font-variant-numeric: tabular-nums; }
            .rq-group--closed .rq-stat-value { font-size: 26px; color: #3F3D39; }
            .rq-spark { flex: none; color: var(--c, #6B6862); }
            .rq-stat-meta { min-height: 18px; font-size: 13px; line-height: 1.4; color: #6B6862; }
            .rq-stat-meta strong { font-weight: 500; color: #3F3D39; }
            .rq-stat-meta.is-alert strong { color: #B45309; }
            @media (max-width: 720px) {
               .rq-group--open .rq-cells, .rq-group--closed .rq-cells { grid-template-columns: minmax(0, 1fr); }
               .rq-stat { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 4px 12px; padding: 12px 16px; }
               .rq-stat-label { grid-column: 1; }
               .rq-stat-body { grid-column: 1 / -1; grid-row: 1 / span 2; justify-self: end; flex-direction: row-reverse; align-items: center; }
               .rq-stat-meta { grid-column: 1; min-height: 0; }
               .rq-stat-value { font-size: 24px !important; }
            }

            .rq-table { width: 100%; min-width: 980px; table-layout: fixed; border-collapse: collapse; font-size: 14px; }
            .rq-table thead th { position: sticky; top: 0; z-index: 1; padding: 10px 16px; background: #FAFAF8; border-bottom: 1px solid #EFEDE9; text-align: left; font-size: 13px; font-weight: 500; color: #6B6862; white-space: nowrap; }
            .rq-table tbody td { padding: 14px 16px; border-bottom: 1px solid #F1EFEC; vertical-align: top; color: #161616; }
            .rq-table tbody tr:last-child td { border-bottom: 0; }
            .rq-table tbody tr:hover td { background: #FCFBFA; }
            .rq-table tbody tr.is-urgent td:first-child { box-shadow: inset 3px 0 0 #DC2626; }
            .rq-no { font-weight: 600; white-space: nowrap; letter-spacing: .01em; }
            .rq-sub { margin-top: 2px; font-size: 13px; color: #6B6862; }
            .rq-truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .rq-clamp { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; overflow-wrap: anywhere; }
            .rq-title { font-weight: 500; }
            .rq-symptom { margin-top: 2px; font-size: 13px; line-height: 1.5; color: #57544F; }
            .rq-loc { display: flex; align-items: center; gap: 6px; margin-top: 6px; font-size: 13px; color: #6B6862; }
            .rq-loc i { flex: none; font-size: 11px; color: #8A867F; }
            .rq-urgent { display: inline-flex; align-items: center; gap: 4px; margin-top: 6px; padding: 1px 8px; border-radius: 999px; background: #FEF2F2; color: #B91C1C; font-size: 12px; font-weight: 500; }
            .rq-who { margin-top: 6px; font-size: 13px; color: #57544F; }
            .rq-who b { font-weight: 500; color: #161616; }
            .rq-actions { display: flex; flex-direction: column; gap: 8px; }
            .rq-actions-row { display: flex; align-items: center; gap: 6px; }
            .rq-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 36px; padding: 0 12px; border: 1px solid #D4D0C9; border-radius: 9px; background: #fff; color: #161616; font-size: 13px; font-weight: 500; text-decoration: none; white-space: nowrap; cursor: pointer; }
            .rq-btn:hover { background: #F5F4F1; }
            .rq-btn--primary { border-color: #161616; background: #161616; color: #fff; }
            .rq-btn--primary:hover { background: #000; }
            .rq-btn--icon { width: 36px; padding: 0; color: #57544F; }
            .rq-btn--danger { color: #B91C1C; }
            .rq-btn--danger:hover { background: #FEF2F2; border-color: #FECACA; }
            .rq-assign { display: flex; gap: 6px; }
            .rq-assign select { flex: 1; min-width: 0; min-height: 36px; padding: 0 8px; border: 1px solid #D4D0C9; border-radius: 9px; background: #fff; font-size: 13px; }
            .rq-empty { padding: 48px 16px; text-align: center; color: #6B6862; }

            /* จอแคบ: แต่ละแถวเป็นการ์ด */
            @media (max-width: 860px) {
               .rq-table { min-width: 0; table-layout: auto; }
               .rq-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
               .rq-table, .rq-table tbody, .rq-table tr, .rq-table td { display: block; width: 100%; }
               .rq-table tbody tr { padding: 14px 16px; border-bottom: 1px solid #EFEDE9; }
               .rq-table tbody td { padding: 4px 0; border: 0; }
               .rq-table tbody td[data-label]::before { content: attr(data-label); display: block; margin-bottom: 2px; font-size: 12px; color: #6B6862; }
               .rq-table tbody td:first-child::before { display: none; }
               .rq-table tbody tr.is-urgent td:first-child { box-shadow: none; }
               .rq-table tbody tr.is-urgent { box-shadow: inset 3px 0 0 #DC2626; }
               .rq-actions { padding-top: 8px; }
               .rq-btn { min-height: 44px; }
               .rq-btn--icon { width: 44px; }
               .rq-assign select { min-height: 44px; }
            }
         </style>
         <script>
            // เลือกสถานะแล้วกรองทันที (ไม่ต้องกดปุ่มค้นหา) — คำค้นที่พิมพ์ไว้ส่งไปด้วย และกลับไปหน้า 1
            document.addEventListener('change', function (event) {
               const select = event.target.closest('select[data-autosubmit]');
               if (!select || !select.form) return;
               if (typeof select.form.requestSubmit === 'function') select.form.requestSubmit(); else select.form.submit();
            });
         </script>

         <?php
         $openKeys = ['pending', 'working', 'signing'];
         $openTotal = array_sum(array_map(static fn($k) => (int)($counts[$k] ?? 0), $openKeys));
         $metaFor = static function (string $key) use ($oldest, $closedThisMonth): array {
            return match ($key) {
               'pending' => $oldest['pending'] ? ['รอนานสุด', rq_ago($oldest['pending']), time() - $oldest['pending'] > 3600] : ['ไม่มีงานรอรับ', '', false],
               'working' => $oldest['working'] ? ['ซ่อมนานสุด', rq_ago($oldest['working']), time() - $oldest['working'] > 86400 * 3] : ['ไม่มีงานค้าง', '', false],
               'signing' => $oldest['signing'] ? ['รอเซ็นนานสุด', rq_ago($oldest['signing']), time() - $oldest['signing'] > 86400] : ['ไม่มีงานรอเซ็น', '', false],
               'completed' => ['เดือนนี้', number_format($closedThisMonth['completed']) . ' งาน', false],
               'cancelled' => ['เดือนนี้', number_format($closedThisMonth['cancelled']) . ' งาน', false],
               default => ['', '', false],
            };
         };
         $trendLabel = ['pending' => 'แจ้งเข้าใหม่', 'working' => 'สตาฟรับงาน', 'signing' => 'ซ่อมเสร็จ ส่งเซ็น', 'completed' => 'รับรองเสร็จ', 'cancelled' => 'ยกเลิก'];
         $groupLabel = TICKET_GROUP_LABELS;
         $renderStat = static function (string $key) use ($groupLabel, $counts, $filter, $search, $trend, $trendLabel, $metaFor): void {
            [$metaLabel, $metaValue, $alert] = $metaFor($key);
            $href = 'admin_manage_request.php?' . http_build_query(array_filter(['status' => $filter === $key ? '' : $key, 'q' => $search]));
            ?>
            <a href="<?= h(rtrim($href, '?')) ?>" class="rq-stat st-<?= h($key) ?>" <?= $filter === $key ? 'aria-current="true"' : '' ?>
               title="<?= $filter === $key ? 'กดอีกครั้งเพื่อดูทุกสถานะ' : 'กรองเฉพาะ ' . h($groupLabel[$key]) ?>">
               <span class="rq-stat-label"><?= h($groupLabel[$key]) ?></span>
               <span class="rq-stat-body">
                  <span class="rq-stat-value"><?= number_format((int)($counts[$key] ?? 0)) ?></span>
                  <?= rq_sparkline($trend[$key], $trendLabel[$key]) ?>
               </span>
               <span class="rq-stat-meta <?= $alert ? 'is-alert' : '' ?>"><?= h($metaLabel) ?><?= $metaValue !== '' ? ' <strong>' . h($metaValue) . '</strong>' : '' ?></span>
            </a>
            <?php
         };
         ?>

         <!-- สรุปสถานะ: กดช่องเพื่อกรอง, กราฟแท่ง = จำนวนที่เข้าสถานะนั้นต่อวันใน 14 วัน -->
         <section class="rq-overview" aria-label="สรุปสถานะใบงาน">
            <div class="rq-group rq-group--open">
               <div class="rq-group-head">
                  <h2 class="rq-group-title">งานที่ยังเปิดอยู่</h2>
                  <span class="rq-group-meta"><?= number_format($openTotal) ?> งาน · กราฟ 14 วันล่าสุด</span>
               </div>
               <div class="rq-cells">
                  <?php foreach ($openKeys as $key) $renderStat($key); ?>
               </div>
            </div>
            <div class="rq-group rq-group--closed">
               <div class="rq-group-head">
                  <h2 class="rq-group-title">ปิดแล้ว</h2>
                  <span class="rq-group-meta">ทั้งหมด <?= number_format((int)($counts['completed'] ?? 0) + (int)($counts['cancelled'] ?? 0)) ?> งาน</span>
               </div>
               <div class="rq-cells">
                  <?php foreach (['completed', 'cancelled'] as $key) $renderStat($key); ?>
               </div>
            </div>
         </section>

         <!-- ตารางรายการคำขอแจ้งซ่อม -->
         <div class="admin-tray">
            <section class="admin-card">
               <div class="admin-toolbar justify-between">
                  <div>
                     <h2 class="font-medium text-gray-900">รายการคำขอ</h2>
                     <p class="mt-1 text-sm text-gray-500">
                        พบ <?= number_format($totalFiltered) ?> รายการ<?= $filter ? ' · สถานะ: ' . h($statusLabel[$filter] ?? $filter) : '' ?><?= $search !== '' ? ' · ค้นหา: "' . h($search) . '"' : '' ?><?= $maxPage > 1 ? ' · หน้า ' . $page . '/' . $maxPage : '' ?>
                     </p>
                  </div>
                  <form method="get" class="flex flex-wrap items-center gap-2">
                     <label class="sr-only" for="request-search">ค้นหาใบงาน</label>
                     <input id="request-search" type="search" name="q" value="<?= h($search) ?>" maxlength="100" placeholder="ค้นหาเลขใบงาน ผู้แจ้ง สถานที่" class="min-w-[240px] rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:border-gray-900">
                     <label class="sr-only" for="status-filter">กรองสถานะ</label>
                     <select id="status-filter" name="status" data-autosubmit class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:border-gray-900">
=======
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
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
                        <option value="">ทุกสถานะ</option>
                        <?php foreach ($statusLabel as $key => $label): ?>
                           <option value="<?= h($key) ?>" <?= $filter === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                     </select>
<<<<<<< HEAD
                     <button class="admin-btn">ค้นหา</button>
                     <?php if ($filter || $search !== ''): ?>
=======
                     <button class="admin-btn">กรอง</button>
                     <?php if ($filter): ?>
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
                        <a href="admin_manage_request.php" class="admin-reset-link">ล้าง</a>
                     <?php endif; ?>
                  </form>
               </div>

<<<<<<< HEAD
               <div class="overflow-x-auto">
                  <table class="rq-table">
                     <colgroup>
                        <col style="width: 150px">
                        <col>
                        <col style="width: 190px">
                        <col style="width: 210px">
                        <col style="width: 230px">
                     </colgroup>
                     <thead>
                        <tr>
                           <th scope="col">ใบงาน</th>
                           <th scope="col">เรื่องที่แจ้ง</th>
                           <th scope="col">ผู้แจ้ง</th>
                           <th scope="col">สถานะ</th>
                           <th scope="col">จัดการ</th>
                        </tr>
                     </thead>
                     <tbody>
                        <?php if (!$tickets): ?>
                           <tr><td colspan="5" class="rq-empty"><?= $filter || $search !== '' ? 'ไม่พบใบงานตามเงื่อนไขนี้ ลองล้างตัวกรอง' : 'ยังไม่มีคำขอซ่อม' ?></td></tr>
                        <?php endif; ?>

                        <?php foreach ($tickets as $t):
                           $isUrgent = $t['urgency'] === 'urgent';
                           $categoryText = ($t['category_name'] ?? 'ไม่ระบุหมวดหมู่') . (!empty($t['custom_category']) ? ' · ' . $t['custom_category'] : '');
                           $ticketDetails = [
                              'ticketNo' => $t['ticket_no'],
                              'createdAt' => date('d/m/Y H:i', strtotime($t['created_at'])),
                              'status' => $statusLabel[$t['status']] ?? $t['status'],
                              'urgency' => $isUrgent ? 'ด่วน' . (!empty($t['urgency_reason']) ? ' · ' . $t['urgency_reason'] : '') : 'ปกติ',
                              'category' => $categoryText,
                              'deviceDetail' => $t['device_detail'],
                              'quantity' => (string)$t['quantity'],
                              'contactPerson' => $t['contact_person'],
                              'primaryContact' => trim(($t['primary_contact_name'] ?: $t['first_name'] . ' ' . $t['last_name'])
                                 . (!empty($t['primary_contact_phone']) ? ' · ' . $t['primary_contact_phone'] : '')
                                 . (!empty($t['primary_contact_email']) ? ' · ' . $t['primary_contact_email'] : '')),
                              'secondaryContact' => trim(($t['secondary_contact_name'] ?? '')
                                 . (!empty($t['secondary_contact_phone']) ? ' · ' . $t['secondary_contact_phone'] : '')
                                 . (!empty($t['secondary_contact_email']) ? ' · ' . $t['secondary_contact_email'] : '')),
                              'coordinator' => trim(($t['coordinator_teacher'] ?? '') . (!empty($t['coordinator_phone']) ? ' (' . $t['coordinator_phone'] . ')' : '')),
                              'caretaker' => trim(($t['room_caretaker'] ?? '') . (!empty($t['room_caretaker_phone']) ? ' (' . $t['room_caretaker_phone'] . ')' : '')),
                              'repairMethod' => $t['repair_method'],
                              'hasCompletionImage' => !empty($t['completion_image']),
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
                           $canChangeStatus = !empty($statusTransitions[$t['status']]);
                        ?>
                           <tr class="<?= $isUrgent ? 'is-urgent' : '' ?>">
                              <td>
                                 <div class="rq-no">#<?= h($t['ticket_no']) ?></div>
                                 <div class="rq-sub"><?= h(date('d/m/Y H:i', strtotime($t['created_at']))) ?></div>
                                 <?php if ($isUrgent): ?>
                                    <span class="rq-urgent" title="<?= h($t['urgency_reason'] ?? '') ?>"><i class="fa-solid fa-bolt" aria-hidden="true"></i> ด่วน</span>
                                 <?php endif; ?>
                              </td>
                              <td data-label="เรื่องที่แจ้ง">
                                 <div class="rq-title rq-truncate" title="<?= h($categoryText) ?>"><?= h($categoryText) ?></div>
                                 <div class="rq-symptom rq-clamp" title="<?= h($t['symptoms']) ?>"><?= h($t['symptoms']) ?></div>
                                 <div class="rq-loc"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span class="rq-truncate" title="<?= h($t['location_name']) ?>"><?= h($t['location_name']) ?></span></div>
                              </td>
                              <td data-label="ผู้แจ้ง">
                                 <div class="rq-truncate" title="<?= h($t['first_name'] . ' ' . $t['last_name']) ?>"><?= h($t['first_name'] . ' ' . $t['last_name']) ?></div>
                                 <div class="rq-sub rq-truncate"><?= h($t['id_number'] ?? '—') ?> · <?= h($t['department_name'] ?? 'ไม่ระบุแผนก') ?></div>
                              </td>
                              <td data-label="สถานะ">
                                 <span class="admin-chip st-<?= h($t['status']) ?>"><?= h($statusLabel[$t['status']] ?? $t['status']) ?></span>
                                 <div class="rq-who">
                                    <?php $rowHelpers = $helpersByTicket[(int)$t['ticket_id']] ?? []; ?>
                                    <?php if (!in_array($t['status'], ['pending', 'cancelled'], true) && !empty($t['tech_first'])): ?>
                                       ช่างหลัก <b><?= h($t['tech_first'] . ' ' . $t['tech_last']) ?></b>
                                       <?php if ($rowHelpers): ?><br>ผู้ช่วย <b><?= h(implode(', ', array_column($rowHelpers, 'name'))) ?></b><?php endif; ?>
                                    <?php elseif ($t['status'] === 'pending'): ?>
                                       <?php if (!empty($t['queue_first'])): ?>
                                          รอ <b><?= h($t['queue_first'] . ' ' . $t['queue_last']) ?></b> กดรับ<?php if (!empty($t['queue_expires_at'])): ?><br>ภายใน <?= h(date('H:i', strtotime($t['queue_expires_at']))) ?> น.<?php endif; ?>
                                       <?php else: ?>
                                          อยู่ในคิวกลาง (ใครก็กดรับได้)
                                       <?php endif; ?>
                                       <?php if ($rowHelpers): ?><br>ผู้ช่วย <b><?= h(implode(', ', array_column($rowHelpers, 'name'))) ?></b><?php endif; ?>
                                    <?php elseif ($t['status'] === 'cancelled'): ?>
                                       <?= h($t['cancelled_by'] === null ? 'ไม่ทราบผู้ยกเลิก' : ((int)$t['cancelled_by'] === (int)$t['requester_id'] ? 'ผู้แจ้งยกเลิกเอง' : 'ผู้ดูแลยกเลิก')) ?>
                                    <?php endif; ?>
                                 </div>
                              </td>
                              <td data-label="จัดการ">
                                 <div class="rq-actions">
                                    <?php $isWorking = in_array($t['status'], TICKET_STAFF_ACTIVE_STATUSES, true); ?>
                                    <?php if ($t['status'] === 'pending' || $isWorking):
                                       $assignPayload = [
                                          'ticketId' => (int)$t['ticket_id'],
                                          'ticketNo' => $t['ticket_no'],
                                          'status' => $t['status'],
                                          'leadId' => $t['status'] === 'pending' ? (int)($t['queue_assigned_to'] ?? 0) : 0,
                                          'leadName' => $isWorking ? trim(($t['tech_first'] ?? '') . ' ' . ($t['tech_last'] ?? '')) : '',
                                          'lockedLeadId' => $isWorking ? (int)($t['assigned_to'] ?? 0) : 0,
                                          'helpers' => array_column($rowHelpers, 'id'),
                                       ]; ?>
                                       <button type="button" class="rq-btn <?= $t['status'] === 'pending' ? 'rq-btn--primary' : '' ?>" data-open-assign
                                          data-assign="<?= h(json_encode($assignPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>">
                                          <i class="fa-solid fa-user-plus" aria-hidden="true"></i> <?= $t['status'] === 'pending' ? 'มอบหมายงาน' : 'จัดทีมช่าง' ?>
                                       </button>
                                    <?php elseif (in_array($t['status'], TICKET_SIGNING_STATUSES, true)): ?>
                                       <a href="admin_signoff.php?id=<?= (int)$t['ticket_id'] ?>" class="rq-btn <?= $t['status'] === 'awaiting_requester_sign' ? '' : 'rq-btn--primary' ?>"><i class="fa-solid fa-file-signature" aria-hidden="true"></i> <?= $t['status'] === 'awaiting_requester_sign' ? 'ดูขั้นเซ็น' : 'ตรวจและเซ็นรับรอง' ?></a>
                                    <?php elseif ($t['status'] === 'completed'): ?>
                                       <a href="../certificate.php?id=<?= (int)$t['ticket_id'] ?>" target="_blank" rel="noopener" class="rq-btn"><i class="fa-solid fa-file-lines" aria-hidden="true"></i> ใบรับรองงาน</a>
                                    <?php endif; ?>
                                    <div class="rq-actions-row">
                                       <button type="button" class="rq-btn rq-btn--icon" aria-label="ดูรายละเอียด #<?= h($t['ticket_no']) ?>" title="ดูรายละเอียด"
                                          data-ticket-details="<?= h(json_encode($ticketDetails, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE)) ?>">
                                          <i class="fa-regular fa-eye" aria-hidden="true"></i>
                                       </button>
                                       <?php if ($canChangeStatus): ?>
                                          <button type="button" class="rq-btn rq-btn--icon" aria-label="เปลี่ยนสถานะ #<?= h($t['ticket_no']) ?>" title="เปลี่ยนสถานะ"
                                             data-open-status-modal
                                             data-ticket-id="<?= (int)$t['ticket_id'] ?>"
                                             data-ticket-no="<?= h($t['ticket_no']) ?>"
                                             data-current-status="<?= h($t['status']) ?>"
                                             data-current-label="<?= h($statusLabel[$t['status']] ?? $t['status']) ?>"
                                             data-allowed-statuses="<?= h(json_encode($statusTransitions[$t['status']])) ?>">
                                             <i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i>
                                          </button>
                                       <?php endif; ?>
                                       <button type="button" class="rq-btn rq-btn--icon rq-btn--danger" aria-label="ลบใบแจ้งซ่อม #<?= h($t['ticket_no']) ?>" title="ลบใบแจ้งซ่อม"
                                          data-open-ticket-delete
                                          data-ticket-id="<?= (int)$t['ticket_id'] ?>"
                                          data-ticket-no="<?= h($t['ticket_no']) ?>">
                                          <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                                       </button>
                                    </div>
                                 </div>
=======
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
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
                              </td>
                           </tr>
                        <?php endforeach; ?>
                     </tbody>
                  </table>
               </div>
<<<<<<< HEAD
               <?php pager_render('admin_manage_request.php', $totalFiltered, $page, $perPage, $perPageOptions, static fn(int $p): string => $listUrl(['page' => $p]), ['status' => $filter, 'q' => $search], 'เปลี่ยนหน้ารายการคำขอ'); ?>
            </section>
         </div>

         <!-- หน้าต่างมอบหมายทีมช่าง: รายชื่อชุดเดียว แตะเพื่อเลือก คนแรก = ช่างหลัก -->
         <style>
            .as-dialog { width: min(480px, calc(100% - 2rem)); max-height: min(640px, calc(100dvh - 2rem)); margin: auto; padding: 0; overflow: hidden; /* เลื่อนเฉพาะรายชื่อข้างใน */ border: 1px solid #E7E4DF; border-radius: 16px; box-shadow: 0 24px 60px rgba(22, 22, 22, .2); }
            .as-dialog::backdrop { background: rgba(22, 22, 22, .45); }
            .as-dialog form { display: flex; flex-direction: column; max-height: calc(min(640px, 100dvh - 2rem) - 2px); /* หักเส้นขอบ dialog */ }
            .as-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 18px 12px; }
            .as-title { margin: 0; font-size: 17px; font-weight: 600; color: #161616; }
            .as-sub { font-size: 13px; color: #6B6862; }
            .as-tools { padding: 0 18px 10px; }
            .as-hint { margin: 0 0 8px; font-size: 13px; color: #57544F; }
            .as-search { width: 100%; min-height: 38px; padding: 0 12px; border: 1px solid #D9D6D0; border-radius: 10px; font-size: 14px; background: #fff; }
            .as-search:focus { outline: none; border-color: #161616; }
            .as-list { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; margin: 0; padding: 4px 10px 8px; list-style: none; border-top: 1px solid #EFEDE9; border-bottom: 1px solid #EFEDE9; }
            .as-group { padding: 10px 8px 4px; font-size: 12px; color: #6B6862; }
            .as-row { display: flex; align-items: center; gap: 10px; min-height: 44px; padding: 4px 8px; border-radius: 10px; cursor: pointer; }
            .as-row[hidden] { display: none; }
            .as-row:hover { background: #F7F6F4; }
            .as-row.is-selected { background: #F5F4F1; }
            .as-row.is-locked { cursor: default; }
            .as-box { flex: none; display: grid; place-items: center; width: 20px; height: 20px; border: 1.5px solid #BDB8B0; border-radius: 6px; color: transparent; font-size: 12px; }
            .as-row.is-selected .as-box { border-color: #161616; background: #161616; color: #fff; }
            .as-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 14px; color: #161616; }
            .as-load { flex: none; font-size: 12.5px; color: #6B6862; font-variant-numeric: tabular-nums; }
            .as-load.is-free { color: #047857; }
            .as-role { flex: none; display: none; min-height: 28px; padding: 0 10px; border: 1px solid #D4D0C9; border-radius: 999px; background: #fff; font-size: 12px; color: #3F3D39; cursor: pointer; }
            .as-row.is-selected .as-role { display: inline-flex; align-items: center; }
            .as-row.is-lead .as-role { border-color: #161616; background: #161616; color: #fff; cursor: default; }
            .as-foot { display: flex; flex-direction: column; gap: 10px; padding: 12px 18px 16px; }
            .as-summary { font-size: 13px; line-height: 1.5; color: #3F3D39; }
            .as-summary b { font-weight: 600; color: #161616; }
            .as-actions { display: flex; justify-content: flex-end; gap: 8px; }
            @media (max-width: 560px) { .as-actions .rq-btn { flex: 1; min-height: 44px; } }
         </style>
         <dialog id="assignDialog" class="as-dialog" aria-labelledby="assignTitle">
            <form method="post">
               <input type="hidden" name="csrf" value="<?= h($_SESSION['admin_csrf']) ?>">
               <input type="hidden" name="action" value="assign_team">
               <input type="hidden" name="ticket_id" value="">
               <input type="hidden" name="lead_id" value="0">
               <div data-helper-inputs></div>
               <div class="as-head">
                  <div>
                     <h2 id="assignTitle" class="as-title">มอบหมายงาน</h2>
                     <div class="as-sub" data-assign-sub></div>
                  </div>
                  <button type="button" class="rq-btn rq-btn--icon" data-assign-close aria-label="ปิดหน้าต่าง"><?= app_icon('x') ?></button>
               </div>
               <div class="as-tools">
                  <p class="as-hint" data-assign-hint>แตะชื่อเพื่อเลือก คนแรกเป็นช่างหลัก คนต่อไปเป็นผู้ช่วย · ไม่เลือกใคร = คิวกลาง</p>
                  <label class="sr-only" for="assignSearch">ค้นหาชื่อสตาฟ</label>
                  <input id="assignSearch" type="search" class="as-search" placeholder="ค้นหาชื่อสตาฟ" autocomplete="off">
               </div>
               <ul class="as-list" role="listbox" aria-multiselectable="true" aria-label="สตาฟ">
                  <?php $printedPaused = false; foreach ($staffOptions as $so):
                     $soName = $so['first_name'] . ' ' . $so['last_name'];
                     $jobs = (int)$so['active_jobs'];
                     if (!$printedPaused && (int)$so['is_active_queue'] !== 1): $printedPaused = true; ?>
                        <li class="as-group" role="presentation">พักคิวอยู่</li>
                     <?php endif; ?>
                     <li class="as-row" role="option" aria-selected="false" tabindex="0"
                        data-staff-id="<?= (int)$so['user_id'] ?>" data-staff-name="<?= h($soName) ?>">
                        <span class="as-box" aria-hidden="true"><?= app_icon('check') ?></span>
                        <span class="as-name"><?= h($soName) ?></span>
                        <span class="as-load <?= $jobs === 0 ? 'is-free' : '' ?>"><?= $jobs === 0 ? 'ว่าง' : 'ทำอยู่ ' . $jobs ?> · <?= (int)$so['score'] ?> คะแนน</span>
                        <button type="button" class="as-role" data-make-lead>ผู้ช่วย</button>
                     </li>
                  <?php endforeach; ?>
               </ul>
               <div class="as-foot">
                  <div class="as-summary" data-assign-summary aria-live="polite"></div>
                  <div class="as-actions">
                     <button type="button" class="rq-btn" data-assign-close>ยกเลิก</button>
                     <button type="submit" class="rq-btn rq-btn--primary">บันทึก</button>
                  </div>
               </div>
            </form>
         </dialog>
         <script>
            (function () {
               const dialog = document.getElementById('assignDialog');
               if (!dialog) return;
               const form = dialog.querySelector('form');
               const rows = Array.from(dialog.querySelectorAll('.as-row'));
               const summary = dialog.querySelector('[data-assign-summary]');
               const hint = dialog.querySelector('[data-assign-hint]');
               const search = document.getElementById('assignSearch');
               let team = [];          // ลำดับที่เลือก: team[0] = ช่างหลัก
               let lockedLead = 0;     // งานที่รับแล้ว: ช่างหลักเปลี่ยนไม่ได้

               const nameOf = function (id) {
                  const row = rows.find(function (r) { return Number(r.dataset.staffId) === id; });
                  return row ? row.dataset.staffName : '';
               };
               function render() {
                  rows.forEach(function (row) {
                     const id = Number(row.dataset.staffId);
                     const idx = team.indexOf(id);
                     const selected = idx !== -1;
                     const lead = selected && idx === 0;
                     row.classList.toggle('is-selected', selected);
                     row.classList.toggle('is-lead', lead);
                     row.classList.toggle('is-locked', lead && lockedLead === id);
                     row.setAttribute('aria-selected', selected ? 'true' : 'false');
                     const role = row.querySelector('[data-make-lead]');
                     role.textContent = lead ? 'ช่างหลัก' : 'ผู้ช่วย';
                     role.title = lead ? '' : (lockedLead ? '' : 'กดเพื่อตั้งเป็นช่างหลัก');
                     role.disabled = lead || !!lockedLead;
                  });
                  if (!team.length) {
                     summary.innerHTML = 'ยังไม่เลือกใคร · งานจะอยู่ใน<b>คิวกลาง</b> ให้สตาฟคนไหนว่างก็กดรับได้';
                  } else {
                     const helpers = team.slice(1).map(nameOf);
                     summary.textContent = '';
                     const b = document.createElement('b');
                     b.textContent = nameOf(team[0]);
                     summary.append('ช่างหลัก ', b);
                     if (helpers.length) summary.append(' · ผู้ช่วย ' + helpers.join(', '));
                  }
               }
               function toggle(row) {
                  const id = Number(row.dataset.staffId);
                  if (lockedLead === id) return;
                  const idx = team.indexOf(id);
                  if (idx === -1) team.push(id); else team.splice(idx, 1);
                  render();
               }
               rows.forEach(function (row) {
                  row.addEventListener('click', function (e) {
                     if (e.target.closest('[data-make-lead]')) return;
                     toggle(row);
                  });
                  row.addEventListener('keydown', function (e) {
                     if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); toggle(row); }
                  });
                  row.querySelector('[data-make-lead]').addEventListener('click', function () {
                     const id = Number(row.dataset.staffId);
                     if (lockedLead) return;
                     team = [id].concat(team.filter(function (x) { return x !== id; }));
                     render();
                  });
               });
               search.addEventListener('input', function () {
                  const q = search.value.trim().toLowerCase();
                  rows.forEach(function (row) { row.hidden = q !== '' && !row.dataset.staffName.toLowerCase().includes(q); });
               });
               document.querySelectorAll('[data-open-assign]').forEach(function (btn) {
                  btn.addEventListener('click', function () {
                     const data = JSON.parse(btn.dataset.assign);
                     form.elements.ticket_id.value = data.ticketId;
                     dialog.querySelector('[data-assign-sub]').textContent = 'ใบงาน #' + data.ticketNo;
                     const pending = data.status === 'pending';
                     document.getElementById('assignTitle').textContent = pending ? 'มอบหมายงาน' : 'จัดทีมช่าง';
                     hint.textContent = pending
                        ? 'แตะชื่อเพื่อเลือก คนแรกเป็นช่างหลัก คนต่อไปเป็นผู้ช่วย · ไม่เลือกใคร = คิวกลาง'
                        : 'ช่างหลักรับงานแล้ว เพิ่มหรือเอาผู้ช่วยออกได้';
                     lockedLead = pending ? 0 : (data.lockedLeadId || 0);
                     const lead = pending ? (data.leadId || 0) : lockedLead;
                     team = (lead ? [lead] : []).concat((data.helpers || []).filter(function (h) { return h !== lead; }));
                     search.value = '';
                     rows.forEach(function (row) { row.hidden = false; });
                     render();
                     dialog.showModal();
                     search.focus();
                  });
               });
               form.addEventListener('submit', function () {
                  form.elements.lead_id.value = lockedLead ? 0 : (team[0] || 0);
                  const box = dialog.querySelector('[data-helper-inputs]');
                  box.replaceChildren();
                  team.slice(1).forEach(function (id) {
                     const input = document.createElement('input');
                     input.type = 'hidden';
                     input.name = 'helper_ids[]';
                     input.value = id;
                     box.appendChild(input);
                  });
               });
               dialog.querySelectorAll('[data-assign-close]').forEach(function (b) { b.addEventListener('click', function () { dialog.close(); }); });
               dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
            })();
         </script>

=======
            </section>
         </div>

>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
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
<<<<<<< HEAD
                  <button type="button" data-close-status-modal aria-label="ปิด" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-900"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
=======
                  <button type="button" data-close-status-modal aria-label="ปิด" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-900"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
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
<<<<<<< HEAD
                     <textarea name="status_reason" id="statusUpdateReason" rows="3" maxlength="255" class="w-full resize-y rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-500 focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10" data-status-reason></textarea>
=======
                     <textarea name="status_reason" id="statusUpdateReason" rows="3" maxlength="255" class="w-full resize-y rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10" data-status-reason></textarea>
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
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
<<<<<<< HEAD
                  <button type="button" data-close-ticket-delete aria-label="ปิด" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-900"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
=======
                  <button type="button" data-close-ticket-delete aria-label="ปิด" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-900"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
               </div>
               <div class="space-y-4 px-5 py-5 sm:px-6">
                  <p class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                     กำลังจะลบใบแจ้งซ่อม <span class="font-mono font-semibold" id="ticketDeleteTicketNo"></span> พร้อมรูปแนบและประวัติการดำเนินงานทั้งหมด
                  </p>
                  <div>
                     <label for="ticketDeleteConfirmInput" class="mb-1.5 block text-sm font-medium text-gray-800">พิมพ์รหัสใบแจ้งซ่อมเพื่อยืนยันการลบ</label>
                     <input type="text" id="ticketDeleteConfirmInput" name="confirm_ticket_no" autocomplete="off" spellcheck="false" autocapitalize="off" autocorrect="off" placeholder="รหัสใบแจ้งซ่อมของใบงานนี้"
<<<<<<< HEAD
                        class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 font-mono text-sm outline-none transition placeholder:font-kanit placeholder:text-gray-500 focus:border-red-600 focus:ring-2 focus:ring-red-600/10">
=======
                        class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 font-mono text-sm outline-none transition placeholder:font-kanit placeholder:text-gray-400 focus:border-red-600 focus:ring-2 focus:ring-red-600/10">
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
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
<<<<<<< HEAD
                        repsSetIconText(deleteHint, 'check', 'รหัสตรงกันแล้ว กด "ยืนยันลบใบแจ้งซ่อม" เพื่อทำรายการ');
                        deleteHint.className = 'mt-1.5 text-xs font-medium text-emerald-600';
                     } else {
                        repsSetIconText(deleteHint, 'x', 'รหัสที่พิมพ์ยังไม่ตรงกัน กรุณาตรวจสอบอีกครั้ง');
=======
                        deleteHint.textContent = 'รหัสตรงกันแล้ว กด "ยืนยันลบใบแจ้งซ่อม" เพื่อทำรายการ';
                        deleteHint.className = 'mt-1.5 text-xs font-medium text-emerald-600';
                     } else {
                        deleteHint.textContent = 'รหัสที่พิมพ์ยังไม่ตรงกัน กรุณาตรวจสอบอีกครั้ง';
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
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
<<<<<<< HEAD
                        repsSetIconText(deleteHint, 'x', 'รหัสใบแจ้งซ่อมไม่ตรง จึงยังลบไม่ได้');
=======
                        deleteHint && (deleteHint.textContent = '✗ รหัสใบแจ้งซ่อมไม่ตรง จึงยังลบไม่ได้');
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
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
<<<<<<< HEAD
                     addField(requesterFields, 'ผู้ติดต่อหลัก (Snapshot)', details.primaryContact, true);
                     addField(locationFields, 'สถานที่ / ห้อง', details.location);
                     addField(locationFields, 'หมวดหมู่', details.category);
                     addField(locationFields, 'อุปกรณ์ (ชนิด/ยี่ห้อ/รุ่น/ครุภัณฑ์)', details.deviceDetail);
                     addField(locationFields, 'จำนวนที่ชำรุด', details.quantity);
                     addField(locationFields, 'ผู้ติดต่อหน้างาน', details.contactPerson);
                     addField(locationFields, 'ผู้ติดต่อสำรอง', details.secondaryContact, true);
                     addField(locationFields, 'ครูผู้ประสานงาน', details.coordinator);
                     addField(locationFields, 'ครูผู้ดูแลห้อง', details.caretaker);
=======
                     addField(locationFields, 'สถานที่ / ห้อง', details.location);
                     addField(locationFields, 'หมวดหมู่', details.category);
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
                     addField(statusFields, 'วันที่แจ้ง', details.createdAt);
                     addField(statusFields, 'สถานะ', details.status);
                     addField(statusFields, 'ความเร่งด่วน', details.urgency);
                     addField(statusFields, 'ผู้รับผิดชอบ', details.assignee);
                     addField(statusFields, 'เริ่มดำเนินการ', details.acceptedAt);
                     addField(statusFields, 'วันที่เสร็จสิ้น', details.completedAt);
                     symptoms.textContent = details.symptoms || 'ไม่มีรายละเอียด';
<<<<<<< HEAD
                     addField(resolutionFields, 'วิธีดำเนินการ', details.repairMethod, true);
                     addField(resolutionFields, 'สรุปการแก้ไข', details.resolutionSummary, true);
                     addField(resolutionFields, (details.cancelledByLabel || 'เหตุผลการยกเลิก') + ' · เหตุผล', details.cancelReason, true, true);
                     attachments.replaceChildren();
                     const imageUrls = (Array.isArray(details.attachments) ? details.attachments : []).map(function (id) {
                        return { url: '../file.php?t=' + encodeURIComponent(details.ticket_id) + '&a=' + encodeURIComponent(id), alt: 'รูปประกอบคำขอซ่อม' };
                     });
                     if (details.hasCompletionImage) {
                        imageUrls.push({ url: '../file.php?t=' + encodeURIComponent(details.ticket_id) + '&k=completion', alt: 'รูปหลังซ่อมเสร็จ' });
                     }
                     attachmentSection.classList.toggle('hidden', imageUrls.length === 0);
                     imageUrls.forEach(function (item) {
                        const path = item.url;
                        const link = document.createElement('a');
                        link.href = path;
=======
                     addField(resolutionFields, 'สรุปการแก้ไข', details.resolutionSummary, true);
                     addField(resolutionFields, (details.cancelledByLabel || 'เหตุผลการยกเลิก') + ' · เหตุผล', details.cancelReason, true, true);
                     attachments.replaceChildren();
                     const imagePaths = Array.isArray(details.attachments) ? details.attachments : [];
                     attachmentSection.classList.toggle('hidden', imagePaths.length === 0);
                     imagePaths.forEach(function (path) {
                        const link = document.createElement('a');
                        link.href = 'ticket_attachment.php?id=' + encodeURIComponent(details.ticket_id) + '&attachment=' + encodeURIComponent(path);
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
                        link.target = '_blank';
                        link.rel = 'noopener noreferrer';
                        link.className = 'block overflow-hidden rounded-lg border border-gray-200 bg-gray-50';
                        link.setAttribute('aria-label', 'เปิดรูปภาพขนาดเต็ม');
                        const image = document.createElement('img');
<<<<<<< HEAD
                        image.src = path;
                        image.alt = item.alt;
                        image.title = item.alt;
=======
                        image.src = 'ticket_attachment.php?id=' + encodeURIComponent(details.ticket_id) + '&attachment=' + encodeURIComponent(path);
                        image.alt = 'รูปประกอบคำขอซ่อม';
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
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
<<<<<<< HEAD
<?php $footerBase = '../'; $footerVariant = 'app'; require __DIR__ . '/../includes/site_footer.php'; ?>
=======
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
   </div>
</body>
</html>
