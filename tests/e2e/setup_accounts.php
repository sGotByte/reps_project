<?php
declare(strict_types=1);

/**
 * เตรียมบัญชีและค่าตั้งค่าที่ชุดทดสอบ e2e ต้องใช้ (รันซ้ำได้) — ใช้กับฐานข้อมูลทดสอบในเครื่องเท่านั้น
 *   t_admin (admin) · t_staff (staff คิว 1) · t_staff2 (staff คิว 2) · t_user (ผู้แจ้ง)  รหัสผ่าน Test1234!
 * สตาฟคนอื่นทั้งหมดถูกพักคิว เพื่อให้ลำดับคิวในการทดสอบคาดเดาได้
 */

$GLOBALS['APP_SKIP_SESSION'] = true;
require __DIR__ . '/../../db_connect.php';
require __DIR__ . '/../../includes/db_helpers.php';

$host = strtolower((string)(getenv('DB_HOST') ?: 'localhost'));
if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
    fwrite(STDERR, "ปฏิเสธ: DB_HOST = {$host} ไม่ใช่ฐานข้อมูลในเครื่อง\n");
    exit(1);
}

$hash = password_hash('Test1234!', PASSWORD_DEFAULT);
$dept = (int)(db_one($conn, 'SELECT d_id FROM department ORDER BY d_id LIMIT 1')['d_id'] ?? 1);
$accounts = [
    ['t_admin', 'teacher', 'admin', null, 0],
    ['t_staff', 'staff', 'staff', 1, 1],
    ['t_staff2', 'staff', 'staff', 2, 1],
    ['t_user', 'student', 'user', null, 0],
];
foreach ($accounts as $i => [$id, $type, $role, $order, $activeQueue]) {
    $email = $id . '@example.invalid';
    $phone = '08000000' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
    $existing = db_one($conn, 'SELECT user_id FROM users WHERE id_number = ?', 's', [$id]);
    if ($existing) {
        db_exec(
            $conn,
            "UPDATE users SET first_name = ?, last_name = 'ทดสอบ', user_type = ?, role = ?, status = 'active', password = ?, queue_order = ?, is_active_queue = ?, email = ?, department_id = ? WHERE user_id = ?",
            'ssssiisii',
            ['T-' . $id, $type, $role, $hash, $order, $activeQueue, $email, $dept, (int)$existing['user_id']]
        );
    } else {
        db_exec(
            $conn,
            "INSERT INTO users (id_number, first_name, last_name, user_type, department_id, phone_number, email, password, role, status, queue_order, is_active_queue, score)
             VALUES (?, ?, 'ทดสอบ', ?, ?, ?, ?, ?, ?, 'active', ?, ?, 100)",
            'sssissssii',
            [$id, 'T-' . $id, $type, $dept, $phone, $email, $hash, $role, $order, $activeQueue]
        );
    }
}
// ไม่ให้บัญชีทดสอบค้างสถานะ "ต้องเปลี่ยนรหัสผ่าน" จากรอบก่อน (คอลัมน์จาก migration 20261120)
$conn->query("UPDATE users SET must_change_password = 0 WHERE id_number IN ('t_admin', 't_staff', 't_staff2', 't_user')");
$conn->query("UPDATE users SET is_active_queue = 0 WHERE role = 'staff' AND id_number NOT IN ('t_staff', 't_staff2')");
// ภาคเรียน: ให้ทีมของภาคเรียนปัจจุบันเป็น t_staff, t_staff2 (ถ้ายังไม่มีภาคเรียนครอบวันนี้ สร้างภาคเรียนทดสอบ)
// run.sh คืนฐานข้อมูลเดิมหลังทดสอบเสมอ
require __DIR__ . '/../../includes/terms.php';
$term = term_for_date($conn, date('Y-m-d'));
if (!$term) {
    $year = (int)date('Y') + 543;
    db_exec($conn, "INSERT INTO academic_terms (year_be, term_no, start_date, end_date, max_staff, note) VALUES (?, 3, ?, ?, 4, 'e2e')", 'iss', [$year + 100, date('Y-m-d', strtotime('-30 days')), date('Y-m-d', strtotime('+30 days'))]);
    $termId = (int)$conn->insert_id;
} else {
    $termId = (int)$term['id'];
}
db_exec($conn, 'DELETE FROM term_staff WHERE term_id = ?', 'i', [$termId]);
foreach (['t_staff' => 1, 't_staff2' => 2] as $id => $order) {
    db_exec($conn, 'INSERT INTO term_staff (term_id, user_id, queue_order) SELECT ?, user_id, ? FROM users WHERE id_number = ?', 'iis', [$termId, $order, $id]);
}
// ใบงานที่ค้างคิวอยู่กับสตาฟคนอื่น ปล่อยเป็นคิวกลาง เพื่อให้ลำดับคิวในการทดสอบคาดเดาได้
$conn->query("UPDATE tickets t JOIN users u ON u.user_id = t.queue_assigned_to SET t.queue_assigned_to = NULL, t.queue_expires_at = NULL WHERE t.status = 'pending' AND u.id_number NOT IN ('t_staff', 't_staff2')");
foreach ([
    'queue_dispatch_mode' => 'auto', 'queue_timeout_minutes' => '30', 'queue_penalty_points' => '5',
    'completion_bonus_points' => '0', 'queue_work_start' => '', 'queue_work_end' => '',
    'requester_sign_skip_days' => '3', 'intake_sign_skip_hours' => '24',
] as $k => $v) {
    db_exec($conn, 'INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', 'ss', [$k, $v]);
}
echo "เตรียมบัญชีทดสอบแล้ว\n";
