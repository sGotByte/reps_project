<?php
declare(strict_types=1);

/**
 * REPS · cron_queue.php — ให้ cron ภายนอก (เช่น cron-job.org) เรียกทุก 5 นาที
 *   https://<โดเมน>/cron_queue.php?token=<ค่า queue_cron_token ใน system_settings>
 * ถ้า queue_cron_token ว่าง endpoint นี้จะปิด
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/queue_manager.php';
require_once __DIR__ . '/includes/ticket_service.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$expected = trim((string)(app_get_settings($conn)['queue_cron_token'] ?? ''));
$given = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';

if (strlen($expected) < 16 || !hash_equals($expected, $given)) {
    http_response_code(403);
    exit('forbidden');
}

queue_process_expired_tickets($conn);
$reminders = ticket_send_sign_reminders($conn, true); // เตือนผู้แจ้งที่ยังไม่เซ็น (v3 4.3)
echo 'ok reminders=' . $reminders;
