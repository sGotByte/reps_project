<?php
// เส้นทางเดิม: ส่งต่อไป file.php ซึ่งตรวจสิทธิ์ admin / staff / ผู้แจ้ง
$ticketId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$attachmentId = filter_input(INPUT_GET, 'attachment', FILTER_VALIDATE_INT);
if (!$ticketId || !$attachmentId) {
   http_response_code(400);
   exit('คำขอไม่ถูกต้อง');
}
header('Location: ../file.php?t=' . $ticketId . '&a=' . $attachmentId, true, 302);
exit;
