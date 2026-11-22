<?php
<<<<<<< HEAD
// เส้นทางเดิม: ส่งต่อไป file.php ซึ่งตรวจสิทธิ์ admin / staff / ผู้แจ้ง
=======
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/private_storage.php';
require_once __DIR__ . '/../db_connect.php';

>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
$ticketId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$attachmentId = filter_input(INPUT_GET, 'attachment', FILTER_VALIDATE_INT);
if (!$ticketId || !$attachmentId) {
   http_response_code(400);
   exit('คำขอไม่ถูกต้อง');
}
<<<<<<< HEAD
header('Location: ../file.php?t=' . $ticketId . '&a=' . $attachmentId, true, 302);
exit;
=======
$stmt = $conn->prepare('SELECT file_path FROM ticket_attachments WHERE id = ? AND ticket_id = ? LIMIT 1');
if (!$stmt) {
   http_response_code(500);
   exit('เปิดไฟล์แนบไม่ได้');
}
$stmt->bind_param('ii', $attachmentId, $ticketId);
if (!$stmt->execute()) {
   $stmt->close();
   http_response_code(500);
   exit('เปิดไฟล์แนบไม่ได้');
}
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row || !preg_match('#^(?:ticket-private/)?([A-Za-z0-9_-]+\.(?:jpg|png))$#i', (string)$row['file_path'], $m)) {
   http_response_code(404);
   exit('ไม่พบไฟล์แนบ');
}
$filename = $m[1];
$privatePath = app_ticket_upload_dir() . DIRECTORY_SEPARATOR . $filename;
$legacyPath = __DIR__ . '/../uploads/tickets/' . $filename;
$filePath = is_file($privatePath) ? $privatePath : $legacyPath;
if (!is_file($filePath) || !is_readable($filePath)) {
   http_response_code(404);
   exit('ไม่พบไฟล์แนบ');
}
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($filePath);
if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
   http_response_code(404);
   exit('ไม่พบไฟล์แนบ');
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($filePath);
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
