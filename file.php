<?php
declare(strict_types=1);

/**
 * REPS · file.php — เปิดไฟล์ส่วนตัวพร้อมตรวจสิทธิ์
 *   ?t=<ticket_id>&a=<attachment_id>  รูปแนบใบงาน
 *   ?t=<ticket_id>&k=completion       รูปหลักฐานปิดงาน (รูปแรก)
 *   ?t=<ticket_id>&k=done&i=<photo_id>  รูปหลักฐานปิดงานเพิ่มเติม (รูปที่ 2–3)
 *   ?t=<ticket_id>&k=signature        ลายเซ็นครูผู้รับรอง (ล่าสุด)
 *   ?t=<ticket_id>&g=<signoff_id>     ลายเซ็นแต่ละขั้น (สตาฟ ผู้แจ้ง ครู)
 *   ?t=<ticket_id>&k=intake           รูปรวมอุปกรณ์ตอนรับ
 *   ?t=<ticket_id>&k=item_in|item_out&i=<check_id>  รูปรายการเช็คลิสต์ตอนรับ/ตอนส่งคืน
 *   ?k=mysig                          ลายเซ็นที่ผู้ใช้อัปโหลดไว้ (เจ้าของเท่านั้น)
 *   ?s=logo | ?s=favicon              ไฟล์สาธารณะของเว็บ (โลโก้ / ไอคอนแท็บ)
 *   ?m=<media_id>                     รูปในคลังสื่อของเว็บ (สาธารณะ)
 *   ?k=tipqr&u=<staff>&t=<ticket>  QR พร้อมเพย์ของ Lab boy (เจ้าของ, admin, ผู้แจ้งของใบงานที่คนนั้นอยู่ในทีม)
 *   ?k=slip&i=<thanks_id>       สลิปคำขอบคุณ (ผู้บันทึก, ผู้รับ, admin)
 *   ?k=inv&i=<item_id>&v=<ver>  รูปวัสดุในสต็อก (สตาฟ, admin) · v = ชื่อไฟล์ ใช้แค่ล้าง cache
 *   ?u=<user_id>&k=avatar             รูปโปรไฟล์ (ผู้ใช้ที่ล็อกอินอยู่ทุกคนเปิดได้ · URL มีชื่อไฟล์ จึง cache ได้)
 * สิทธิ์ใบงาน: admin และ staff ดูได้ทุกใบ ผู้แจ้งดูได้เฉพาะใบงานของตัวเอง
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/private_storage.php';
require_once __DIR__ . '/includes/db_helpers.php';

// คลังสื่อของเว็บ (รูปที่ admin อัปโหลดไว้ใช้บนหน้าเว็บ) เป็นไฟล์สาธารณะ — URL มี id คงที่ จึง cache ได้
$mediaId = filter_input(INPUT_GET, 'm', FILTER_VALIDATE_INT);
if ($mediaId) {
   $stmt = $conn->prepare('SELECT file_path FROM site_media WHERE id = ? LIMIT 1');
   $stmt->bind_param('i', $mediaId);
   $stmt->execute();
   $row = $stmt->get_result()->fetch_assoc();
   $stmt->close();
   app_serve_private_image($row ? app_private_file_abs($row['file_path']) : null, true);
}

$siteKey = is_string($_GET['s'] ?? null) ? $_GET['s'] : '';
if ($siteKey !== '') {
   require_once __DIR__ . '/includes/site_config.php';
   $settingKey = ['logo' => 'site_logo_path', 'favicon' => 'site_favicon_path'][$siteKey] ?? null;
   if ($settingKey === null) {
      http_response_code(404);
      exit('ไม่พบไฟล์');
   }
   $settings = app_get_settings($conn);
   app_serve_private_image(app_private_file_abs($settings[$settingKey] ?? ''), true);
}

require_once __DIR__ . '/includes/user_auth.php';
$userId = app_require_active_user($conn, 'login.php', null);
$role = current_role();

$avatarUserId = filter_input(INPUT_GET, 'u', FILTER_VALIDATE_INT);
if ($avatarUserId && ($_GET['k'] ?? '') === 'avatar') {
   $stmt = $conn->prepare('SELECT avatar_path FROM users WHERE user_id = ? LIMIT 1');
   $stmt->bind_param('i', $avatarUserId);
   $stmt->execute();
   $row = $stmt->get_result()->fetch_assoc();
   $stmt->close();
   app_serve_private_image($row ? app_private_file_abs($row['avatar_path']) : null, false, 'private, max-age=86400');
}

// ลายเซ็นอิเล็กทรอนิกส์ที่ผู้ใช้อัปโหลดไว้ (เจ้าของเท่านั้น)
if (($_GET['k'] ?? '') === 'mysig') {
   $stmt = $conn->prepare('SELECT signature_path FROM users WHERE user_id = ? LIMIT 1');
   $stmt->bind_param('i', $userId);
   $stmt->execute();
   $row = $stmt->get_result()->fetch_assoc();
   $stmt->close();
   app_serve_private_image($row ? app_private_file_abs($row['signature_path']) : null);
}

// QR พร้อมเพย์ของ Lab boy: เจ้าของ, admin, หรือผู้แจ้งของใบงานที่ Lab boy คนนั้นอยู่ในทีม (?t=)
if (($_GET['k'] ?? '') === 'tipqr') {
   $qrUserId = filter_input(INPUT_GET, 'u', FILTER_VALIDATE_INT) ?: 0;
   $qrTicket = filter_input(INPUT_GET, 't', FILTER_VALIDATE_INT) ?: 0;
   $allowed = $qrUserId === $userId || $role === 'admin';
   if (!$allowed && $qrTicket > 0) {
      $allowed = (bool)db_one(
         $conn,
         'SELECT 1 FROM tickets t WHERE t.id = ? AND t.requester_id = ? AND (t.assigned_to = ? OR EXISTS (SELECT 1 FROM ticket_assignees a WHERE a.ticket_id = t.id AND a.user_id = ?))',
         'iiii',
         [$qrTicket, $userId, $qrUserId, $qrUserId]
      );
   }
   $row = $allowed ? db_one($conn, 'SELECT promptpay_qr_path FROM users WHERE user_id = ?', 'i', [$qrUserId]) : null;
   app_serve_private_image($row ? app_private_file_abs($row['promptpay_qr_path']) : null);
}

// สลิปคำขอบคุณ: ผู้แจ้งที่บันทึก, Lab boy ผู้รับ, admin
if (($_GET['k'] ?? '') === 'slip') {
   $slip = db_one($conn, 'SELECT from_user_id, to_user_id, slip_path FROM ticket_thanks WHERE id = ?', 'i', [filter_input(INPUT_GET, 'i', FILTER_VALIDATE_INT) ?: 0]);
   $allowed = $slip && ($role === 'admin' || (int)$slip['from_user_id'] === $userId || (int)$slip['to_user_id'] === $userId);
   app_serve_private_image($allowed ? app_private_file_abs($slip['slip_path']) : null);
}

// รูปวัสดุในสต็อก: สตาฟและครู (ผู้ที่ดูหน้าสต็อกได้)
if (($_GET['k'] ?? '') === 'inv') {
   $inv = can($role, 'inventory.use')
      ? db_one($conn, 'SELECT image_path FROM inventory_items WHERE id = ?', 'i', [filter_input(INPUT_GET, 'i', FILTER_VALIDATE_INT) ?: 0])
      : null;
   app_serve_private_image($inv ? app_private_file_abs($inv['image_path']) : null, false, 'private, max-age=86400');
}

$ticketId = filter_input(INPUT_GET, 't', FILTER_VALIDATE_INT);
if (!$ticketId) {
   http_response_code(400);
   exit('คำขอไม่ถูกต้อง');
}

$stmt = $conn->prepare('SELECT requester_id, completion_image, intake_photo FROM tickets WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $ticketId);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
$stmt->close();

// สิทธิ์: admin/staff ดูได้ทุกใบงาน ผู้แจ้งดูได้เฉพาะใบงานของตัวเอง
if (!$ticket || (!can($role, 'ticket.view_all') && (int)$ticket['requester_id'] !== $userId)) {
   http_response_code(404);
   exit('ไม่พบไฟล์');
}

$kind = is_string($_GET['k'] ?? null) ? $_GET['k'] : '';
$attachmentId = filter_input(INPUT_GET, 'a', FILTER_VALIDATE_INT);
$signoffId = filter_input(INPUT_GET, 'g', FILTER_VALIDATE_INT); // ไม่ใช้ s เพราะ ?s= เป็นไฟล์สาธารณะของเว็บ
$checkId = filter_input(INPUT_GET, 'i', FILTER_VALIDATE_INT);

$fetchPath = static function (string $sql, string $types, array $params) use ($conn): ?string {
   $stmt = $conn->prepare($sql);
   $stmt->bind_param($types, ...$params);
   $stmt->execute();
   $row = $stmt->get_result()->fetch_row();
   $stmt->close();
   return $row ? (string)$row[0] : null;
};

if ($attachmentId) {
   app_serve_private_image(app_private_file_abs($fetchPath('SELECT file_path FROM ticket_attachments WHERE id = ? AND ticket_id = ? LIMIT 1', 'ii', [$attachmentId, $ticketId])));
}
if ($signoffId) {
   app_serve_private_image(app_private_file_abs($fetchPath('SELECT signature_image FROM ticket_signoffs WHERE id = ? AND ticket_id = ? LIMIT 1', 'ii', [$signoffId, $ticketId])));
}
if ($checkId && in_array($kind, ['item_in', 'item_out'], true)) {
   $col = $kind === 'item_in' ? 'photo_in' : 'photo_out';
   app_serve_private_image(app_private_file_abs($fetchPath("SELECT {$col} FROM ticket_item_checks WHERE id = ? AND ticket_id = ? LIMIT 1", 'ii', [$checkId, $ticketId])));
}
if ($kind === 'completion') {
   app_serve_private_image(app_private_file_abs($ticket['completion_image']));
}
if ($kind === 'done' && $checkId) {
   // รูปหลังซ่อมเพิ่มเติม (รูปที่ 2–3) — ผูกกับใบงานนี้เท่านั้น
   app_serve_private_image(app_private_file_abs($fetchPath('SELECT file_path FROM ticket_completion_photos WHERE id = ? AND ticket_id = ? LIMIT 1', 'ii', [$checkId, $ticketId])));
}
if ($kind === 'intake') {
   app_serve_private_image(app_private_file_abs($ticket['intake_photo']));
}
if ($kind === 'signature') {
   // ลิงก์เดิม: ลายเซ็นครูผู้รับรองล่าสุดของใบงาน
   app_serve_private_image(app_private_file_abs($fetchPath(
      "SELECT signature_image FROM ticket_signoffs WHERE ticket_id = ? AND signer_role = 'admin' ORDER BY round_no DESC, id DESC LIMIT 1",
      'i',
      [$ticketId]
   )));
}

http_response_code(400);
exit('คำขอไม่ถูกต้อง');
