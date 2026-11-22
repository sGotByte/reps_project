<?php
declare(strict_types=1);

/**
 * REPS · database/seeds/seed_demo.php — สร้างข้อมูลจำลองสำหรับทดสอบ/สาธิต
 *
 *   php database/seeds/seed_demo.php            ลบข้อมูลจำลองเดิม แล้วสร้างใหม่
 *   php database/seeds/seed_demo.php --clean    ลบข้อมูลจำลองอย่างเดียว
 *
 * ข้อมูลจำลองทั้งหมดแยกจากข้อมูลจริงด้วยรหัสนำหน้า:
 *   users.id_number = demo_*, tickets.ticket_no = DM-*, inventory_items.item_code = DM-*
 * ข้อมูลเริ่มภาคเรียน 1/2569 (ทีม Lab boy 2 คน) ถึงปัจจุบัน 2/2569 (3 คน) พร้อมตั้งภาคเรียนและทีมใน academic_terms/term_staff
 * บัญชีสตาฟ: lb2569-<ภาค>-<ลำดับ> รหัสผ่านแยกต่อบัญชี (เก็บที่ database/seeds/demo_credentials.local.txt ไม่เข้า git)
 * บัญชีจำลองอื่น (demo_admin*, demo_u*): Demo1234!
 *
 * ทำงานเฉพาะ CLI และฐานข้อมูลบนเครื่องตัวเอง (DB_HOST = localhost/127.0.0.1) เท่านั้น
 * คำเตือน: --clean ลบไฟล์รูปจำลอง (dm_*) และรูปของใบงานจำลองใน TICKET_UPLOAD_DIR ซึ่งใช้ร่วมกันทุกฐานข้อมูลที่ชี้ไปโฟลเดอร์เดียวกัน
 *          อย่ารันกับสำเนาฐานข้อมูลที่ใช้โฟลเดอร์รูปเดียวกับฐานข้อมูลหลัก (tests/e2e/run.sh สำรองและคืนโฟลเดอร์รูปให้เอง)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$GLOBALS['APP_SKIP_SESSION'] = true;
require __DIR__ . '/../../db_connect.php';
require __DIR__ . '/../../includes/db_helpers.php';
require __DIR__ . '/../../includes/private_storage.php';
require __DIR__ . '/demo_images.php';

$dbHost = (string)(getenv('DB_HOST') ?: '');
if (!in_array($dbHost, ['127.0.0.1', 'localhost', '::1'], true)) {
    fwrite(STDERR, "ปฏิเสธ: DB_HOST = {$dbHost} ไม่ใช่ฐานข้อมูลในเครื่อง (สคริปต์นี้ห้ามรันบนเซิร์ฟเวอร์จริง)\n");
    exit(1);
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ---------------------------------------------------------------- ลบข้อมูลจำลอง
/** บัญชีจำลองทั้งหมด: รหัสขึ้นต้น demo_ หรืออีเมลโดเมน @demo.reps.invalid (บัญชีสตาฟใช้ชื่อผู้ใช้แบบจริง) */
const DEMO_USERS_SQL = "(SELECT user_id FROM (SELECT user_id FROM users WHERE id_number LIKE 'demo\\_%' OR email LIKE '%@demo.reps.invalid') demo_u)";

function demo_clean(mysqli $conn): void
{
    $users = DEMO_USERS_SQL;
    // ใบงานจำลอง = DM-* และใบงานใดก็ตามที่บัญชีจำลองแจ้ง/เป็นทีมช่าง/เซ็น/เบิกวัสดุ
    // (เช่น มีคนลองแจ้งซ่อมผ่านเว็บด้วยบัญชี demo_u*) — ไม่งั้นลบบัญชีจำลองไม่ได้เพราะ foreign key
    $ids = array_map('intval', array_column($conn->query(
        "SELECT id FROM tickets WHERE ticket_no LIKE 'DM-%' OR requester_id IN {$users} OR assigned_to IN {$users}
            OR id IN (SELECT ticket_id FROM ticket_signoffs WHERE signer_id IN {$users})
            OR id IN (SELECT ticket_id FROM ticket_materials WHERE withdrawn_by IN {$users})
            OR id IN (SELECT ticket_id FROM ticket_assignees WHERE user_id IN {$users})"
    )->fetch_all(MYSQLI_ASSOC), 'id'));
    $tickets = $ids ? '(' . implode(',', $ids) . ')' : '(0)';
    // ไฟล์รูปของใบงานที่ไม่ได้ขึ้นต้น dm_ (สร้างผ่านเว็บ) ลบตามไปด้วย
    $files = $conn->query(
        "SELECT file_path AS p FROM ticket_attachments WHERE ticket_id IN {$tickets}
         UNION SELECT completion_image FROM tickets WHERE id IN {$tickets} AND completion_image IS NOT NULL
         UNION SELECT intake_photo FROM tickets WHERE id IN {$tickets} AND intake_photo IS NOT NULL
         UNION SELECT signature_image FROM ticket_signoffs WHERE ticket_id IN {$tickets} AND signature_image IS NOT NULL
         UNION SELECT photo_in FROM ticket_item_checks WHERE ticket_id IN {$tickets} AND photo_in IS NOT NULL
         UNION SELECT photo_out FROM ticket_item_checks WHERE ticket_id IN {$tickets} AND photo_out IS NOT NULL"
    )->fetch_all(MYSQLI_ASSOC);
    $conn->query("DELETE FROM email_logs WHERE ticket_id IN {$tickets}");
    $conn->query("DELETE FROM inventory_movements WHERE item_id IN (SELECT id FROM inventory_items WHERE item_code LIKE 'DM-%') OR ticket_id IN {$tickets}");
    $conn->query("DELETE FROM score_logs WHERE ticket_id IN {$tickets} OR user_id IN {$users}");
    foreach (['ticket_materials', 'ticket_signoffs', 'ticket_assignees', 'ticket_item_checks', 'ticket_attachments', 'ticket_logs'] as $table) {
        $conn->query("DELETE FROM {$table} WHERE ticket_id IN {$tickets}");
    }
    $conn->query("DELETE FROM ticket_assignees WHERE user_id IN {$users}");
    $conn->query("DELETE FROM tickets WHERE id IN {$tickets}");
    foreach ($files as $f) {
        if (!str_starts_with(basename((string)$f['p']), 'dm_')) {
            app_delete_private_file((string)$f['p']);
        }
    }
    $conn->query("DELETE FROM inventory_items WHERE item_code LIKE 'DM-%'");
    $conn->query("DELETE FROM user_audit_logs WHERE target_user_id IN {$users} OR changed_by IN {$users}");
    $conn->query("UPDATE users SET approved_by = NULL WHERE approved_by IN {$users}");
    $conn->query("DELETE FROM users WHERE user_id IN {$users}"); // term_staff ลบตาม (ON DELETE CASCADE)
    // รูปจำลอง (ชื่อไฟล์ขึ้นต้น dm_)
    foreach (array_merge(glob(app_ticket_upload_dir() . DIRECTORY_SEPARATOR . 'dm_*.jpg') ?: [], glob(app_ticket_upload_dir() . DIRECTORY_SEPARATOR . 'dm_sig_*.png') ?: [], glob(app_ticket_upload_dir() . DIRECTORY_SEPARATOR . 'dm_tipqr_*.png') ?: []) as $file) {
        @unlink($file);
    }
}

demo_clean($conn);
echo "ลบข้อมูลจำลองเดิมแล้ว\n";
if (in_array('--clean', $argv, true)) {
    exit(0);
}

mt_srand(2569);
$pick = static fn(array $a) => $a[mt_rand(0, count($a) - 1)];
$chance = static fn(float $p): bool => mt_rand() / mt_getrandmax() < $p;
$fmt = static fn(int $ts): string => date('Y-m-d H:i:s', $ts);
$now = time();

/** เลื่อนเวลาให้อยู่ในเวลาทำการ (จ.-ศ. 08:30-16:30) */
$workTime = static function (int $ts): int {
    for ($i = 0; $i < 10; $i++) {
        $dow = (int)date('N', $ts);
        $minutes = (int)date('G', $ts) * 60 + (int)date('i', $ts);
        if ($dow >= 6) {
            $ts = strtotime(date('Y-m-d', $ts) . ' 08:40 +' . (8 - $dow) . ' day');
            continue;
        }
        if ($minutes < 510) { $ts = strtotime(date('Y-m-d', $ts) . ' 08:35') + mt_rand(0, 3600); continue; }
        if ($minutes > 990) { $ts = strtotime(date('Y-m-d', $ts) . ' 08:35 +1 day') + mt_rand(0, 3600); continue; }
        break;
    }
    return $ts;
};

$passwordHash = password_hash('Demo1234!', PASSWORD_DEFAULT);
$departments = array_map('intval', array_column(db_all($conn, 'SELECT d_id FROM department'), 'd_id'));
$categories = db_all($conn, 'SELECT id, name FROM categories ORDER BY id');
$otherCategoryId = null;
foreach ($categories as $c) {
    if (str_contains($c['name'], 'อื่นๆ')) $otherCategoryId = (int)$c['id'];
}

$firstNames = ['กิตติพงษ์', 'ธนพล', 'ณัฐวุฒิ', 'ภาณุพงศ์', 'สิทธิชัย', 'วรเมธ', 'พีรพัฒน์', 'อนุชา', 'ชยพล', 'ศุภกร', 'ปวีณา', 'ณัฐธิดา', 'กมลชนก', 'สุภาวดี', 'พิมพ์ชนก', 'วรรณิดา', 'อรอุมา', 'จิราพร', 'ชลธิชา', 'ธิดารัตน์', 'ศิริพร', 'นภัสสร', 'ปิยะพงษ์', 'ธีรเดช', 'อภิสิทธิ์', 'จักรพันธ์', 'วีรยุทธ', 'สุรเชษฐ์', 'ณรงค์ศักดิ์', 'ชนิดา', 'เบญจมาศ', 'รัตนาภรณ์', 'สมศักดิ์', 'ประภาพร', 'วิไลวรรณ', 'มนัสนันท์'];
$lastNames = ['ศรีสุข', 'ทองดี', 'แก้วมณี', 'บุญมา', 'จันทร์เพ็ญ', 'สุขสวัสดิ์', 'วงศ์ใหญ่', 'พรหมมา', 'มั่นคง', 'รุ่งเรือง', 'ชัยมงคล', 'สายทอง', 'อินทร์แก้ว', 'ปัญญาดี', 'เพชรรัตน์', 'นาคสวัสดิ์', 'ศรีวงศ์', 'กาญจนา', 'บุญเรือง', 'ทองคำ'];
$phone = static fn(): string => '08' . mt_rand(0, 9) . str_pad((string)mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT);

$insertUser = $conn->prepare(
    'INSERT INTO users (id_number, first_name, last_name, user_type, department_id, phone_number, email, password, role, status,
                        approved_by, approved_at, reject_reason, score, queue_order, is_active_queue, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 100, ?, ?, ?)'
);
$makeUser = function (string $idNumber, string $type, string $role, string $status, int $createdTs, ?int $approvedBy = null, ?string $reject = null, ?int $queueOrder = null, int $activeQueue = 0) use ($insertUser, $conn, $pick, $firstNames, $lastNames, $departments, $phone, $passwordHash, $fmt): int {
    $first = $pick($firstNames);
    $last = $pick($lastNames);
    $dept = $role === 'staff' ? 13 : $pick($departments);
    if (!in_array($dept, $departments, true)) $dept = $pick($departments);
    $tel = $phone();
    $email = $idNumber . '@demo.reps.invalid';
    $created = $fmt($createdTs);
    $approvedAt = $approvedBy !== null ? $fmt($createdTs + mt_rand(3600, 86400)) : null;
    $insertUser->bind_param('ssssisssssissiis', $idNumber, $first, $last, $type, $dept, $tel, $email, $passwordHash, $role, $status, $approvedBy, $approvedAt, $reject, $queueOrder, $activeQueue, $created);
    $insertUser->execute();
    return (int)$conn->insert_id;
};

// ข้อมูลเริ่มที่ภาคเรียน 1/2569 (ระบบเริ่มใช้งาน) · ภาคเรียนนับต่อเนื่องรวมช่วงปิดภาค เพื่อไม่ให้ใบงานตกหล่น
$start = strtotime('2026-05-01 09:00:00');
$termDefs = [
    1 => ['year' => 2569, 'no' => 1, 'start' => '2026-05-01', 'end' => '2026-10-31', 'note' => 'ทีม Lab boy 2 คน'],
    2 => ['year' => 2569, 'no' => 2, 'start' => '2026-11-01', 'end' => '2027-04-30', 'note' => 'ทีม Lab boy 3 คน'],
];
/** ทีมสตาฟ (Lab boy) ต่อภาคเรียน: ชื่อผู้ใช้ = lb<ปีการศึกษา>-<ภาคเรียน>-<ลำดับ> */
$staffDefs = [
    1 => [
        ['lb2569-1-01', 'ณัฐวุฒิ', 'แก้วมณี'],
        ['lb2569-1-02', 'ธนพล', 'ศรีสุข'],
    ],
    2 => [
        ['lb2569-2-01', 'ภาณุพงศ์', 'บุญเรือง'],
        ['lb2569-2-02', 'ศุภกร', 'ทองคำ'],
        ['lb2569-2-03', 'กมลชนก', 'พรหมมา'],
    ],
];
// รหัสผ่านแยกต่อบัญชีสตาฟ: สุ่มครั้งแรกแล้วเก็บไว้ในไฟล์ในเครื่อง (ไม่เข้า git) รันซ้ำได้รหัสเดิม
$credFile = __DIR__ . '/demo_credentials.local.txt';
$savedPasswords = [];
if (is_file($credFile)) {
    foreach (file($credFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (preg_match('/^(lb\S+)\s+(\S+)/', $line, $m)) $savedPasswords[$m[1]] = $m[2];
    }
}
$makePassword = static function (): string {
    $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!@#$%*?'];
    $chars = [];
    foreach ($sets as $set) for ($i = 0; $i < ($set === '!@#$%*?' ? 1 : 3); $i++) $chars[] = $set[random_int(0, strlen($set) - 1)];
    for ($i = 0, $all = implode('', $sets); $i < 2; $i++) $chars[] = $all[random_int(0, strlen($all) - 1)];
    for ($i = count($chars) - 1; $i > 0; $i--) { $j = random_int(0, $i); [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]]; }
    return implode('', $chars);
};

// ---------------------------------------------------------------- ผู้ใช้
$conn->begin_transaction();
$admins = [
    $makeUser('demo_admin1', 'teacher', 'admin', 'active', $start - 86400 * 30),
    $makeUser('demo_admin2', 'teacher', 'admin', 'active', $start - 86400 * 20),
];
$auditInsert = $conn->prepare('INSERT INTO user_audit_logs (target_user_id, changed_by, action_detail, created_at) VALUES (?, ?, ?, ?)');
$audit = function (int $target, int $actor, string $detail, int $ts) use ($auditInsert, $fmt): void {
    $at = $fmt($ts);
    $auditInsert->bind_param('iiss', $target, $actor, $detail, $at);
    $auditInsert->execute();
};

// ภาคเรียน (สร้างถ้ายังไม่มี) และทีมสตาฟของแต่ละภาคเรียน
$termIds = [];
foreach ($termDefs as $k => $td) {
    $row = db_one($conn, 'SELECT id FROM academic_terms WHERE year_be = ? AND term_no = ?', 'ii', [$td['year'], $td['no']]);
    if ($row) {
        db_exec($conn, 'UPDATE academic_terms SET start_date = ?, end_date = ?, max_staff = 4, note = ? WHERE id = ?', 'sssi', [$td['start'], $td['end'], $td['note'], (int)$row['id']]);
        $termIds[$k] = (int)$row['id'];
    } else {
        $termIds[$k] = db_insert($conn, 'INSERT INTO academic_terms (year_be, term_no, start_date, end_date, max_staff, note, created_by) VALUES (?, ?, ?, ?, 4, ?, ?)', 'iisssi', [$td['year'], $td['no'], $td['start'], $td['end'], $td['note'], $admins[0]]);
    }
    db_exec($conn, 'DELETE FROM term_staff WHERE term_id = ?', 'i', [$termIds[$k]]);
}
$currentTermKey = time() >= strtotime($termDefs[2]['start']) ? 2 : 1;
$insertStaff = $conn->prepare(
    "INSERT INTO users (id_number, first_name, last_name, user_type, department_id, phone_number, email, password, role, status,
                        approved_by, approved_at, score, queue_order, is_active_queue, created_at)
     VALUES (?, ?, ?, 'student', ?, ?, ?, ?, 'staff', 'active', ?, ?, 100, ?, ?, ?)"
);
$staffDept = in_array(13, $departments, true) ? 13 : $departments[0];
$rosters = [1 => [], 2 => []];
$credentials = [];
foreach ($staffDefs as $k => $members) {
    foreach ($members as $i => [$username, $first, $last]) {
        $password = $savedPasswords[$username] ?? $makePassword();
        $credentials[] = [$username, $password, $first . ' ' . $last, $termDefs[$k]['no'] . '/' . $termDefs[$k]['year']];
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $created = strtotime($termDefs[$k]['start'] . ' 09:00') - 86400 * (7 - $i);
        $isCurrent = $k === $currentTermKey;
        $order = $isCurrent ? $i + 1 : null;
        $active = $isCurrent ? 1 : 0;
        $tel = $phone();
        $email = $username . '@demo.reps.invalid';
        $createdAt = $fmt($created);
        $approvedAt = $fmt($created + 3600);
        $adminId = $admins[0];
        $insertStaff->bind_param('sssisssisiis', $username, $first, $last, $staffDept, $tel, $email, $hash, $adminId, $approvedAt, $order, $active, $createdAt);
        $insertStaff->execute();
        $id = (int)$conn->insert_id;
        $rosters[$k][] = $id;
        db_exec($conn, 'INSERT INTO term_staff (term_id, user_id, queue_order, added_by, added_at) VALUES (?, ?, ?, ?, ?)', 'iiiis', [$termIds[$k], $id, $i + 1, $admins[0], $createdAt]);
        $audit($id, $admins[0], 'เปลี่ยน role: user → staff (ทีมภาคเรียน ' . $termDefs[$k]['no'] . '/' . $termDefs[$k]['year'] . ')', $created + 7200);
    }
}
$staff = array_merge($rosters[1], $rosters[2]);
/** ทีมของภาคเรียนที่ครอบเวลานี้ */
$rosterAt = static function (int $ts) use ($rosters, $termDefs): array {
    return $ts >= strtotime($termDefs[2]['start']) ? $rosters[2] : $rosters[1];
};

$requesters = [];
for ($i = 1; $i <= 60; $i++) {
    $type = $i <= 45 ? 'student' : 'teacher';
    $created = $start + mt_rand(0, max(1, $now - $start - 86400 * 20));
    $approver = $pick($admins);
    $id = $makeUser(sprintf('demo_u%03d', $i), $type, 'user', 'active', $created, $approver);
    $requesters[] = ['id' => $id, 'created' => $created];
    $audit($id, $approver, 'อนุมัติการสมัคร #' . $id . ' demo_u' . str_pad((string)$i, 3, '0', STR_PAD_LEFT), $created + 3600);
}
// บัญชีรออนุมัติ / ถูกปฏิเสธ / ถูกระงับ
for ($i = 1; $i <= 6; $i++) {
    $makeUser(sprintf('demo_new%02d', $i), $i <= 5 ? 'student' : 'teacher', 'user', 'pending', $now - mt_rand(3600, 86400 * 4));
}
foreach (['ข้อมูลไม่ตรงกับทะเบียนนักศึกษา', 'สมัครซ้ำกับบัญชีเดิม'] as $n => $reason) {
    $id = $makeUser('demo_rej' . ($n + 1), 'student', 'user', 'rejected', $now - 86400 * mt_rand(5, 40), $admins[1], $reason);
    $audit($id, $admins[1], 'ปฏิเสธการสมัคร เหตุผล: ' . $reason, $now - 86400 * 4);
}
$disabled = $makeUser('demo_off1', 'student', 'user', 'disabled', $start + 86400 * 60, $admins[0]);
$audit($disabled, $admins[0], 'เปลี่ยนสถานะบัญชี: active → disabled', $start + 86400 * 200);
$conn->commit();

// ---------------------------------------------------------------- วัสดุจำลอง
$conn->begin_transaction();
$demoItems = [
    ['DM-RJ45', 'หัวแลน RJ45 Cat6 (สาธิต)', 'หัว', 20],
    ['DM-LAN', 'สายแลน Cat6 (สาธิต)', 'เมตร', 50],
    ['DM-MOUSE', 'เมาส์ USB (สาธิต)', 'ตัว', 5],
    ['DM-KB', 'คีย์บอร์ด USB (สาธิต)', 'ตัว', 5],
    ['DM-BIOS', 'ถ่านไบออส CR2032 (สาธิต)', 'ก้อน', 5],
    ['DM-PASTE', 'ซิลิโคนระบายความร้อน (สาธิต)', 'หลอด', 3],
    ['DM-PSU', 'พาวเวอร์ซัพพลาย 500W (สาธิต)', 'ตัว', 2],
    ['DM-HDMI', 'สาย HDMI 3 เมตร (สาธิต)', 'เส้น', 3],
];
$items = [];
$moveInsert = $conn->prepare('INSERT INTO inventory_movements (item_id, movement_type, qty_change, stock_before, stock_after, ticket_id, action_by, note, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
$move = function (int $itemId, string $type, int $change, int $before, ?int $ticketId, int $by, ?string $note, int $ts) use ($moveInsert, $fmt): void {
    $after = $before + $change;
    $at = $fmt($ts);
    $moveInsert->bind_param('isiiiiiss', $itemId, $type, $change, $before, $after, $ticketId, $by, $note, $at);
    $moveInsert->execute();
};
foreach ($demoItems as [$code, $name, $unit, $min]) {
    db_exec($conn, 'INSERT INTO inventory_items (item_code, name, unit, stock_qty, min_stock) VALUES (?, ?, ?, 0, ?)', 'sssi', [$code, $name, $unit, $min]);
    $id = (int)$conn->insert_id;
    $initial = $min * mt_rand(6, 10);
    $move($id, 'receive', $initial, 0, null, $admins[0], 'ยอดยกมาต้นปีการศึกษา 2569', $start);
    $items[$id] = ['stock' => $initial, 'min' => $min, 'unit' => $unit, 'name' => $name];
}
$conn->commit();

// ---------------------------------------------------------------- ข้อมูลประกอบใบงาน
$rooms = ['อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 1', 'อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 2', 'อาคาร 2 ห้อง 204', 'อาคาร 2 ห้อง 305', 'อาคาร 3 ห้องประชุมใหญ่', 'อาคาร 3 ห้องสมุด', 'อาคารอำนวยการ ห้องธุรการ', 'อาคารอำนวยการ ห้องทะเบียน', 'อาคาร 4 ห้องพักครูแผนกไอที', 'อาคาร 4 ห้อง 412', 'อาคาร 5 ห้องปฏิบัติการไฟฟ้า', 'อาคาร 6 ห้องปฏิบัติการช่างยนต์'];
$symptoms = [
    'คอมพิวเตอร์เปิดไม่ติด ไฟเข้าแต่จอดำ', 'เครื่องค้างบ่อยและรีสตาร์ทเอง', 'จอฟ้าขึ้น error ตอนเปิดเครื่อง', 'เมาส์ขยับไม่ได้ เปลี่ยนพอร์ตแล้วยังเหมือนเดิม',
    'คีย์บอร์ดพิมพ์บางปุ่มไม่ติด', 'อินเทอร์เน็ตหลุดบ่อย เชื่อมต่อ LAN ไม่ได้', 'Wi-Fi ห้องเรียนสัญญาณอ่อนมาก', 'โปรเจกเตอร์ภาพไม่ขึ้น ขึ้น No Signal',
    'ลำโพงห้องเรียนไม่มีเสียง', 'เครื่องพิมพ์กระดาษติดและพิมพ์ไม่ออก', 'โปรแกรม Microsoft Office ใช้งานไม่ได้ แจ้งลิขสิทธิ์หมดอายุ', 'เครื่องช้ามาก เปิดโปรแกรมนาน',
    'นาฬิกาเครื่องรีเซ็ตทุกครั้งที่เปิด', 'พัดลมเครื่องเสียงดังผิดปกติ', 'สาย HDMI ต่อแล้วภาพกระพริบ',
];
$methods = [
    'ตรวจสอบสายไฟและพาวเวอร์ซัพพลาย เปลี่ยนพาวเวอร์ซัพพลายใหม่', 'ทำความสะอาดฝุ่น ทาซิลิโคนใหม่ อัปเดตไดรเวอร์', 'เข้าหัวสาย RJ45 ใหม่ และทดสอบสัญญาณ',
    'เปลี่ยนเมาส์ใหม่จากสต็อก', 'เปลี่ยนคีย์บอร์ดใหม่จากสต็อก', 'รีเซ็ตการตั้งค่าเครือข่ายและรีสตาร์ท Access Point', 'เปลี่ยนสาย HDMI และตั้งค่าความละเอียดจอ',
    'ติดตั้งและเปิดใช้งานโปรแกรมใหม่', 'เปลี่ยนถ่านไบออสและตั้งเวลาใหม่', 'ตรวจสอบไดรเวอร์เสียงและสายลำโพง', 'ล้างหัวพิมพ์และนำกระดาษที่ติดออก',
];
$summaries = ['ใช้งานได้ปกติแล้ว', 'ทดสอบเชื่อมต่อเรียบร้อย', 'ผู้แจ้งตรวจสอบแล้วใช้งานได้', 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ'];
$customCategories = ['ระบบเสียงห้องประชุม', 'กล้องวงจรปิด', 'ปลั๊กไฟและรางไฟ', 'แท็บเล็ตห้องเรียน', 'ระบบเสียงห้องประชุม', 'กล้องวงจรปิด'];
$urgentReasons = ['ใช้สอบปฏิบัติคาบถัดไป', 'ครูผู้สอนใช้สอนวันนี้', 'มีการประชุมผู้บริหารช่วงบ่าย', 'งานทะเบียนต้องพิมพ์เอกสารวันนี้'];
$cancelReasons = ['แจ้งผิดรายการ', 'แจ้งซ้ำกับใบงานอื่น', 'ปัญหาหายแล้ว ไม่ต้องซ่อม'];
$teachers = ['ครูสมชาย วงศ์ใหญ่', 'ครูวิไลวรรณ ศรีสุข', 'ครูประภาพร ทองดี', 'ครูณรงค์ศักดิ์ บุญมา', 'ครูเบญจมาศ แก้วมณี'];
// คนต้นคิวรับงานมากกว่าเล็กน้อย (ในทีมของภาคเรียนนั้น)

$pickStaff = static function (int $ts) use ($rosterAt): int {
    $team = $rosterAt($ts);
    $weights = array_map(static fn(int $i): int => count($team) - $i + 2, array_keys($team));
    $r = mt_rand(1, array_sum($weights));
    foreach ($weights as $i => $w) {
        if (($r -= $w) <= 0) return $team[$i];
    }
    return $team[0];
};

$ticketInsert = $conn->prepare(
    'INSERT INTO tickets (ticket_no, requester_id, category_id, custom_category, device_detail, quantity, location_note, contact_person,
        coordinator_teacher, coordinator_phone, room_caretaker, room_caretaker_phone, symptom, urgency, urgency_reason, status,
        assigned_to, queue_assigned_to, queue_expires_at, resolution_summary, repair_method, cancel_reason, created_at, accepted_at, completed_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$logInsert = $conn->prepare('INSERT INTO ticket_logs (ticket_id, old_status, new_status, action_by, source, created_at) VALUES (?, ?, ?, ?, ?, ?)');
$log = function (int $ticketId, ?string $old, string $new, int $by, string $source, int $ts) use ($logInsert, $fmt): void {
    $at = $fmt($ts);
    $logInsert->bind_param('ississ', $ticketId, $old, $new, $by, $source, $at);
    $logInsert->execute();
};
$scoreInsert = $conn->prepare('INSERT INTO score_logs (user_id, ticket_id, points_change, reason, action_by, created_at) VALUES (?, ?, ?, ?, ?, ?)');
$score = function (int $userId, ?int $ticketId, int $points, string $reason, ?int $by, int $ts) use ($scoreInsert, $fmt): void {
    $at = $fmt($ts);
    $scoreInsert->bind_param('iiisis', $userId, $ticketId, $points, $reason, $by, $at);
    $scoreInsert->execute();
};
$signInsert = $conn->prepare("INSERT INTO ticket_signoffs (ticket_id, signer_role, stage, round_no, signer_id, signature_image, method, signed_at, ip, note) VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?)");
/** ลายเซ็นจำลอง: เส้นหวัดสุ่มต่อผู้ใช้ (ภาพเดียวกันทุกครั้งที่ผู้ใช้คนนั้นเซ็น) — ไฟล์ dm_sig_<user>.png */
$demoSignature = static function (int $userId): string {
    $dir = app_ensure_upload_dir();
    $name = 'dm_sig_' . $userId . '.png';
    $path = $dir . DIRECTORY_SEPARATOR . $name;
    if (!is_file($path)) {
        // ตัวสุ่มเฉพาะที่ (ไม่แตะ mt_rand ของ seed หลัก เพื่อให้ข้อมูลจำลองซ้ำได้เหมือนเดิม)
        $state = ($userId * 7919) & 0x7fffffff;
        $rnd = static function (int $min, int $max) use (&$state): int {
            $state = ($state * 1103515245 + 12345) & 0x7fffffff;
            return $min + $state % ($max - $min + 1);
        };
        $im = imagecreatetruecolor(480, 150);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        $ink = imagecolorallocate($im, 22, 22, 40);
        imagesetthickness($im, 3);
        $x = 30; $y = 90;
        for ($i = 0; $i < 26; $i++) {
            $nx = min(450, $x + $rnd(8, 24));
            $ny = max(25, min(130, $y + $rnd(-38, 38)));
            imageline($im, $x, $y, $nx, $ny, $ink);
            $x = $nx; $y = $ny;
        }
        imageline($im, 40, 120, $rnd(260, 440), $rnd(110, 128), $ink);
        imagepng($im, $path);
        imagedestroy($im);
    }
    return 'ticket-private/' . $name;
};
$sign = function (int $tid, string $role, string $stage, int $by, int $ts, string $method = 'draw', ?string $note = null) use ($signInsert, $fmt, $demoSignature): void {
    $at = $fmt($ts);
    $ip = '10.0.' . mt_rand(1, 20) . '.' . mt_rand(2, 250);
    $img = $method === 'skip' ? null : $demoSignature($by);
    $signInsert->bind_param('ississsss', $tid, $role, $stage, $by, $img, $method, $at, $ip, $note);
    $signInsert->execute();
};
$itemInsert = $conn->prepare(
    'INSERT INTO ticket_item_checks (ticket_id, item_name, item_type, serial_no, sort_order, qty_in, condition_in, note_in, received_by, received_at,
        qty_out, result_out, note_out, checked_out_by, checked_out_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$presetRows = db_all($conn, 'SELECT category_id, item_name, item_type FROM checklist_presets WHERE is_active = 1 ORDER BY sort_order, id');
$presetsByCat = [];
foreach ($presetRows as $p) {
    $presetsByCat[(int)($p['category_id'] ?? 0)][] = $p;
}
$stageUpdate = $conn->prepare('UPDATE tickets SET stage_since = ?, has_discrepancy = ?, discrepancy_note = ?, return_reason = ? WHERE id = ?');
/**
 * สร้างเหตุการณ์ v3 ตั้งแต่รับงานจนถึงสถานะสุดท้าย: เช็คลิสต์ตอนรับ, ผู้แจ้งเซ็นรับทราบ, ซ่อม + ตรวจซ้ำ,
 * สตาฟเซ็นส่งมอบ, ผู้แจ้งเซ็นรับคืน (หรือโต้แย้ง/ครูข้ามขั้น), ครูรับรอง
 */
$flow = function (int $tid, string $final, int $reqId, int $assigned, int $categoryId, int $accepted, int $submitted, int $signed)
    use ($log, $sign, $itemInsert, $presetsByCat, $stageUpdate, $fmt, $chance, $pick, $admins, $now): void {
    $order = ['accepted', 'awaiting_intake_sign', 'in_progress', 'inspected_ready', 'awaiting_requester_sign', 'awaiting_admin_sign', 'completed'];
    $finalIdx = $final === 'disputed' ? 4 : array_search($final, $order, true);
    $tIntake = $accepted + mt_rand(300, 1200);
    $tIntakeSigned = $tIntake + mt_rand(600, 7200);
    $tStaffSigned = $submitted + mt_rand(600, 3600);
    $tReqSigned = $tStaffSigned + mt_rand(1800, 86400);
    if ($signed <= $tReqSigned) {
        $tReqSigned = (int)(($tStaffSigned + $signed) / 2);
    }
    // บังคับลำดับเวลาให้เพิ่มขึ้นเสมอ (ช่วงสุ่มของแต่ละขั้นอาจซ้อนกัน)
    $times = [$accepted, $tIntake, $tIntakeSigned, $submitted, $tStaffSigned, $tReqSigned, $signed];
    for ($i = 1; $i < count($times); $i++) {
        if ($times[$i] <= $times[$i - 1]) {
            $times[$i] = $times[$i - 1] + 120;
        }
    }
    [$accepted, $tIntake, $tIntakeSigned, $submitted, $tStaffSigned, $tReqSigned, $signed] = $times;
    $last = $accepted;
    $prev = 'pending';
    $discrepancy = 0;
    $discNote = null;
    $returnReason = null;
    $skipIntake = $chance(0.04);
    $skipReturn = $chance(0.03);
    $lead = $assigned;

    // เช็คลิสต์ตอนรับ (มีเมื่อผ่านขั้นบันทึกเช็คลิสต์แล้ว)
    $items = [];
    if ($finalIdx >= 1) {
        $rows = $presetsByCat[$categoryId] ?? [['item_name' => 'ตัวเครื่อง', 'item_type' => 'main']];
        $rows = array_slice($rows, 0, mt_rand(2, max(2, min(4, count($rows)))));
        $missingIdx = $finalIdx >= 3 && $chance(0.07) && count($rows) > 1 ? count($rows) - 1 : -1;
        foreach ($rows as $i => $r) {
            $qty = $r['item_type'] === 'main' ? 1 : ($chance(0.8) ? 1 : 0);
            $cond = $r['item_type'] === 'main' && $chance(0.25) ? 'scratched' : 'normal';
            $noteIn = $cond === 'scratched' ? 'มีรอยขีดข่วนเดิม' : null;
            $serial = $r['item_type'] === 'main' ? '7440-' . mt_rand(100, 999) . '-' . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT) : null;
            $done = $finalIdx >= 3;
            $res = $done ? ($i === $missingIdx && $qty > 0 ? 'missing' : 'returned') : null;
            $qtyOut = $done ? ($res === 'missing' ? 0 : $qty) : null;
            $noteOut = $res === 'missing' ? 'ไม่พบตอนส่งคืน' : null;
            $byOut = $done ? $lead : null;
            $atIn = $fmt($tIntake);
            $atOut = $done ? $fmt($submitted) : null;
            $name = $r['item_name'];
            $type = $r['item_type'];
            $itemInsert->bind_param('isssiissisissis', $tid, $name, $type, $serial, $i, $qty, $cond, $noteIn, $lead, $atIn, $qtyOut, $res, $noteOut, $byOut, $atOut);
            $itemInsert->execute();
            if ($res === 'missing') {
                $discrepancy = 1;
                $discNote = $name . ' ไม่พบตอนส่งคืน ผู้แจ้งแจ้งว่าอาจลืมไว้ที่ห้อง';
            }
        }
    }

    $step = static function (string $to, int $ts, int $by, string $source, ?string $note = null) use (&$prev, &$last, $log, $tid): void {
        $log($tid, $prev, $to, $by, $source, $ts);
        $prev = $to;
        $last = $ts;
    };
    $step('accepted', $accepted, $lead, 'staff');
    if ($finalIdx >= 1) {
        $step('awaiting_intake_sign', $tIntake, $lead, 'staff');
    }
    if ($finalIdx >= 2) {
        if ($skipIntake) {
            $by = $pick($admins);
            $sign($tid, 'requester', 'intake', $by, $tIntakeSigned, 'skip', 'ข้ามขั้นผู้แจ้ง: ติดต่อผู้แจ้งไม่ได้ ครูรับทราบรายการแทน');
            $step('in_progress', $tIntakeSigned, $by, 'admin');
        } else {
            $sign($tid, 'requester', 'intake', $reqId, $tIntakeSigned);
            $step('in_progress', $tIntakeSigned, $reqId, 'requester');
        }
    }
    if ($finalIdx >= 3) {
        $step('inspected_ready', $submitted, $lead, 'staff');
    }
    if ($finalIdx >= 4) {
        $sign($tid, 'staff', 'return', $lead, $tStaffSigned);
        $step('awaiting_requester_sign', $tStaffSigned, $lead, 'staff');
    }
    if ($final === 'disputed') {
        $returnReason = 'อุปกรณ์ยังมีอาการเดิมบางครั้ง ขอให้ตรวจอีกครั้ง';
        $step('disputed', $tReqSigned, $reqId, 'requester');
    } elseif ($finalIdx >= 5) {
        if ($skipReturn) {
            $by = $pick($admins);
            $sign($tid, 'requester', 'return', $by, $tReqSigned, 'skip', 'ข้ามขั้นผู้แจ้ง: ผู้แจ้งไม่เข้าระบบเกินกำหนด รับอุปกรณ์คืนแล้ว');
            $step('awaiting_admin_sign', $tReqSigned, $by, 'admin');
        } else {
            $sign($tid, 'requester', 'return', $reqId, $tReqSigned);
            $step('awaiting_admin_sign', $tReqSigned, $reqId, 'requester');
        }
    }
    if ($finalIdx >= 6) {
        $by = $pick($admins);
        $sign($tid, 'admin', 'return', $by, $signed, 'draw', $chance(0.3) ? 'ตรวจรับเรียบร้อย' : null);
        $step('completed', $signed, $by, 'signoff');
    }
    $since = $fmt(min($last, $now));
    $stageUpdate->bind_param('sissi', $since, $discrepancy, $discNote, $returnReason, $tid);
    $stageUpdate->execute();
};
$materialInsert = $conn->prepare('INSERT INTO ticket_materials (ticket_id, item_id, qty_used, withdrawn_by, note, created_at) VALUES (?, ?, ?, ?, NULL, ?)');
$helperInsert = $conn->prepare('INSERT INTO ticket_assignees (ticket_id, user_id, added_by, created_at) VALUES (?, ?, ?, ?)');
$addHelpers = function (int $tid, int $lead, int $ts) use ($helperInsert, $rosterAt, $admins, $pick, $fmt): array {
    $pool = array_values(array_filter($rosterAt($ts), static fn($id) => $id !== $lead)); // ผู้ช่วยจากทีมภาคเรียนเดียวกัน
    shuffle($pool);
    $helpers = array_slice($pool, 0, mt_rand(1, 2));
    $by = $pick($admins);
    $at = $fmt($ts);
    foreach ($helpers as $hid) {
        $helperInsert->bind_param('iiis', $tid, $hid, $by, $at);
        $helperInsert->execute();
    }
    return $helpers;
};
$emailInsert = $conn->prepare("INSERT INTO email_logs (ticket_id, to_email, template_key, status, error, sent_at, created_at) VALUES (?, ?, 'completed', ?, ?, ?, ?)");

// ---------------------------------------------------------------- ใบงาน
$uploadDir = app_ensure_upload_dir();
$attachInsert = $conn->prepare('INSERT INTO ticket_attachments (ticket_id, file_path, uploaded_at) VALUES (?, ?, ?)');
$completionUpdate = $conn->prepare('UPDATE tickets SET completion_image = ? WHERE id = ?');
$imageCount = 0;
$counts = array_fill_keys(['pending', 'accepted', 'awaiting_intake_sign', 'in_progress', 'inspected_ready', 'awaiting_requester_sign', 'awaiting_admin_sign', 'disputed', 'completed', 'cancelled'], 0);
// สถานะเดิม (3 ขั้น) -> สถานะละเอียด v3 แบบสุ่มตามสัดส่วนที่พบจริง
$toV3 = static function (string $old, int $submitted) use ($chance, $now): string {
    if ($old === 'in_progress') {
        $r = mt_rand(1, 100);
        $v = $r <= 12 ? 'accepted' : ($r <= 30 ? 'awaiting_intake_sign' : ($r <= 85 ? 'in_progress' : 'inspected_ready'));
        return $v === 'inspected_ready' && $submitted > $now ? 'in_progress' : $v;
    }
    if ($old === 'awaiting_signoff') {
        $r = mt_rand(1, 100);
        return $r <= 45 ? 'awaiting_requester_sign' : ($r <= 92 ? 'awaiting_admin_sign' : 'disputed');
    }
    return $old;
};
$seq = 0;
$conn->begin_transaction();
for ($month = strtotime('2026-05-01'); $month <= $now; $month = strtotime('+1 month', $month)) {
    $m = (int)date('n', $month);
    // ปิดภาคเรียน (เม.ย., ต.ค.) งานน้อยกว่า
    $perMonth = in_array($m, [4, 10], true) ? mt_rand(8, 14) : mt_rand(20, 34);
    $monthEnd = min(strtotime('+1 month', $month) - 1, $now - 1800);
    for ($k = 0; $k < $perMonth; $k++) {
        $created = $workTime(mt_rand($month, max($month + 1, $monthEnd)));
        if ($created > $now - 1800) continue;
        $requester = $pick($requesters);
        $seq++;
        $ticketNo = sprintf('DM-%s-%04d', date('Y', $created), $seq);
        $category = $pick($categories);
        $categoryId = (int)$category['id'];
        $custom = $categoryId === $otherCategoryId ? $pick($customCategories) : null;
        $urgent = $chance(0.15);
        $age = $now - $created;

        // สถานะตามอายุใบงาน
        if ($age > 86400 * 14) {
            $status = $chance(0.9) ? 'completed' : 'cancelled';
        } else {
            $r = mt_rand(1, 100);
            $status = $r <= 25 ? 'pending' : ($r <= 50 ? 'in_progress' : ($r <= 70 ? 'awaiting_signoff' : ($r <= 93 ? 'completed' : 'cancelled')));
        }
        $staffId = $pickStaff($created);
        $accepted = $workTime($created + mt_rand(600, $urgent ? 5400 : 86400));
        $submitted = $workTime($accepted + mt_rand(3600, $urgent ? 14400 : 86400 * 3));
        $signed = $workTime($submitted + mt_rand(1800, 86400 * 2));
        if (in_array($status, ['in_progress', 'awaiting_signoff', 'completed'], true) && $accepted > $now) $status = 'pending';
        if (in_array($status, ['awaiting_signoff', 'completed'], true) && $submitted > $now) $status = 'in_progress';
        if ($status === 'completed' && $signed > $now) $status = 'awaiting_signoff';

        $assigned = in_array($status, ['in_progress', 'awaiting_signoff', 'completed'], true) ? $staffId : null;
        $queueTo = null;
        $queueExp = null;
        if ($status === 'pending' && $chance(0.6)) {
            $queueTo = $pick($rosterAt($now));
            $queueExp = $fmt($now + mt_rand(300, 1800));
        }
        $statusDb = $toV3($status, $submitted);
        $hasMethod = in_array($statusDb, ['inspected_ready', 'awaiting_requester_sign', 'awaiting_admin_sign', 'disputed', 'completed'], true);
        $method = $hasMethod ? $pick($methods) : null;
        $summary = $hasMethod ? $pick($summaries) : null;
        $cancelReason = $status === 'cancelled' ? $pick($cancelReasons) : null;
        $withTeacher = $chance(0.55);
        $coord = $withTeacher ? $pick($teachers) : null;
        $coordPhone = $withTeacher ? '08' . mt_rand(10000000, 99999999) : null;
        $caretaker = $chance(0.4) ? $pick($teachers) : null;
        $caretakerPhone = $caretaker ? '08' . mt_rand(10000000, 99999999) : null;
        $device = $chance(0.7) ? $pick(['Dell OptiPlex 3080', 'Lenovo ThinkCentre M70', 'Acer Veriton X', 'HP ProDesk 400', 'Epson EB-X51', 'Canon G3010', 'TP-Link Archer C6']) . ' รหัสครุภัณฑ์ 7440-' . mt_rand(100, 999) . '-' . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT) : null;
        $quantity = $chance(0.85) ? 1 : mt_rand(2, 6);
        $location = $pick($rooms) . ' เครื่อง PC-' . str_pad((string)mt_rand(1, 40), 2, '0', STR_PAD_LEFT);
        $contact = 'ผู้แจ้ง (' . '08' . mt_rand(10000000, 99999999) . ')';
        $symptom = $pick($symptoms);
        $urgency = $urgent ? 'urgent' : 'normal';
        $urgentReason = $urgent ? $pick($urgentReasons) : null;
        $createdAt = $fmt($created);
        $acceptedAt = $assigned ? $fmt($accepted) : null;
        $completedAt = $status === 'completed' ? $fmt($signed) : null;
        $reqId = $requester['id'];

        $ticketInsert->bind_param(
            'siississssssssssiisssssss',
            $ticketNo, $reqId, $categoryId, $custom, $device, $quantity, $location, $contact,
            $coord, $coordPhone, $caretaker, $caretakerPhone, $symptom, $urgency, $urgentReason, $statusDb,
            $assigned, $queueTo, $queueExp, $summary, $method, $cancelReason, $createdAt, $acceptedAt, $completedAt
        );
        $ticketInsert->execute();
        $tid = (int)$conn->insert_id;
        $counts[$statusDb]++;

        // รูปประกอบ (รูปถ่ายตัวอย่างจาก seeds/photos): ทุกใบงานมีรูปตอนแจ้ง 1–2 รูป และรูปหลังซ่อมเมื่อมีการซ่อม
        $photos = $chance(0.3) ? 2 : 1;
        for ($p = 1; $p <= $photos; $p++) {
            $name = "dm_{$tid}_a{$p}.jpg";
            if (demo_photo_place(demo_photo_groups_before($symptom), $uploadDir . DIRECTORY_SEPARATOR . $name, $tid * 7 + $p * 3)) {
                $stored = 'ticket-private/' . $name;
                $attachInsert->bind_param('iss', $tid, $stored, $createdAt);
                $attachInsert->execute();
                $imageCount++;
            }
        }
        if ($hasMethod) {
            $name = "dm_{$tid}_done.jpg";
            if (demo_photo_place(demo_photo_groups_after((string)$method), $uploadDir . DIRECTORY_SEPARATOR . $name, $tid * 5 + 1)) {
                $stored = 'ticket-private/' . $name;
                $completionUpdate->bind_param('si', $stored, $tid);
                $completionUpdate->execute();
                $imageCount++;
                // รูปหลังซ่อมเพิ่มเติม 1–2 รูป (ใช้เลขใบงานกำหนด ไม่กระทบลำดับสุ่มของ seed)
                $extra = $tid % 5 === 0 ? 2 : ($tid % 5 === 1 ? 1 : 0);
                for ($x = 1; $x <= $extra; $x++) {
                    $name = "dm_{$tid}_done" . ($x + 1) . '.jpg';
                    if (demo_photo_place(demo_photo_groups_after((string)$method), $uploadDir . DIRECTORY_SEPARATOR . $name, $tid * 5 + 1 + $x * 11)) {
                        db_exec($conn, 'INSERT INTO ticket_completion_photos (ticket_id, round_no, file_path, sort_order, created_by, created_at) VALUES (?, 1, ?, ?, ?, ?)', 'isiis', [$tid, 'ticket-private/' . $name, $x + 1, $staffId, $fmt($submitted)]);
                        $imageCount++;
                    }
                }
            }
        }

        $log($tid, null, 'pending', $reqId, 'web', $created);
        // สตาฟบางคนไม่รับคิวทันเวลา (หักคะแนน)
        if ($assigned && $chance(0.06)) {
            $missed = $pick($rosterAt($created));
            if ($missed !== $assigned) {
                $score($missed, $tid, -5, "คิว: ไม่กดรับงาน #{$ticketNo} ภายใน 30 นาที", null, $created + 1800);
            }
        }
        if ($status === 'cancelled') {
            $byRequester = $chance(0.7);
            $log($tid, 'pending', 'cancelled', $byRequester ? $reqId : $pick($admins), 'web', $workTime($created + mt_rand(600, 86400)));
            continue;
        }
        $helpers = [];
        if ($assigned) {
            $flow($tid, $statusDb, $reqId, $assigned, $categoryId, $accepted, $submitted, $signed);
            if ($chance(0.2)) {
                $helpers = $addHelpers($tid, $assigned, $accepted + 600);
            }
        } elseif ($status === 'pending' && $chance(0.3)) {
            $addHelpers($tid, (int)($queueTo ?? 0), $created + 300);
        }
        if ($hasMethod) {
            // เบิกวัสดุ
            if ($chance(0.4)) {
                $itemId = array_rand($items);
                $qty = mt_rand(1, $items[$itemId]['unit'] === 'เมตร' ? 15 : 2);
                if ($items[$itemId]['stock'] - $qty <= $items[$itemId]['min'] && $chance(0.85)) {
                    // รับเข้าเพิ่มก่อนของหมด
                    $add = $items[$itemId]['min'] * mt_rand(4, 8);
                    $move($itemId, 'receive', $add, $items[$itemId]['stock'], null, $pick($admins), 'จัดซื้อเพิ่มตามรอบ', $accepted - 3600);
                    $items[$itemId]['stock'] += $add;
                }
                if ($items[$itemId]['stock'] >= $qty) {
                    $at = $fmt($submitted);
                    $materialInsert->bind_param('iiiis', $tid, $itemId, $qty, $assigned, $at);
                    $materialInsert->execute();
                    $move($itemId, 'withdraw', -$qty, $items[$itemId]['stock'], $tid, $assigned, 'เบิกใช้กับใบงาน #' . $ticketNo, $submitted);
                    $items[$itemId]['stock'] -= $qty;
                }
            }
        }
        if ($statusDb === 'completed') {
            $signer = $pick($admins); // คงลำดับการสุ่มเดิมไว้ (กติกาใหม่ไม่มีโบนัสคะแนนอัตโนมัติเมื่อปิดงาน)
            $signedAt = $fmt($signed);
            $mailOk = $chance(0.9);
            $to = 'demo_u@demo.reps.invalid';
            $mailStatus = $mailOk ? 'sent' : 'failed';
            $mailErr = $mailOk ? null : 'SMTP connection timed out';
            $sentAt = $mailOk ? $fmt($signed + 5) : null;
            $emailInsert->bind_param('isssss', $tid, $to, $mailStatus, $mailErr, $sentAt, $signedAt);
            $emailInsert->execute();
        }
    }
    $conn->commit();
    $conn->begin_transaction();
}
$conn->commit();

// ---------------------------------------------------------------- งานวันนี้ (ให้ตัวเลข "วันนี้" บนแดชบอร์ดมีข้อมูลเสมอ ไม่ว่าจะรันเวลาใด)
$todayStart = strtotime('today');
$todayAt = static function (int $minutesAgo) use ($now, $todayStart): int {
    return max($todayStart + 60, $now - $minutesAgo * 60);
};
$todayPlan = [
    // [สถานะ, แจ้งเมื่อ(นาทีที่แล้ว), รับงาน, ส่งปิด, เซ็นรับรอง, ด่วน]
    ['completed', 300, 280, 200, 150, false],
    ['completed', 260, 250, 170, 120, true],
    ['awaiting_admin_sign', 240, 225, 90, null, false],
    ['awaiting_requester_sign', 230, 215, 60, null, false],
    ['disputed', 400, 380, 200, null, false],
    ['in_progress', 180, 160, null, null, true],
    ['awaiting_intake_sign', 120, 100, null, null, false],
    ['accepted', 40, 30, null, null, false],
    ['pending', 45, null, null, null, true],
    ['pending', 15, null, null, null, false],
];
$conn->begin_transaction();
foreach ($todayPlan as $n => [$status, $cAgo, $aAgo, $sAgo, $gAgo, $urgent]) {
    $seq++;
    $created = $todayAt($cAgo);
    $ticketNo = sprintf('DM-%s-%04d', date('Y', $created), $seq);
    $requester = $pick($requesters);
    $reqId = $requester['id'];
    $category = $pick($categories);
    $categoryId = (int)$category['id'];
    $custom = $categoryId === $otherCategoryId ? $pick($customCategories) : null;
    $device = null;
    $quantity = 1;
    $location = $pick($rooms) . ' เครื่อง PC-' . str_pad((string)mt_rand(1, 40), 2, '0', STR_PAD_LEFT);
    $contact = 'ผู้แจ้ง (08' . mt_rand(10000000, 99999999) . ')';
    $coord = $pick($teachers);
    $coordPhone = '08' . mt_rand(10000000, 99999999);
    $caretaker = null;
    $caretakerPhone = null;
    $symptom = $pick($symptoms);
    $urgency = $urgent ? 'urgent' : 'normal';
    $urgentReason = $urgent ? $pick($urgentReasons) : null;
    $staffId = $rosters[$currentTermKey][$n % count($rosters[$currentTermKey])];
    $statusDb = $status;
    $assigned = $aAgo !== null ? $staffId : null;
    $queueTo = $status === 'pending' && $n % 2 === 1 ? $rosters[$currentTermKey][0] : null;
    $queueExp = $queueTo ? $fmt($now + 1500) : null;
    $hasMethod = $sAgo !== null;
    $method = $hasMethod ? $pick($methods) : null;
    $summary = $hasMethod ? $pick($summaries) : null;
    $cancelReason = null;
    $createdAt = $fmt($created);
    $acceptedAt = $aAgo !== null ? $fmt($todayAt($aAgo)) : null;
    $completedAt = $gAgo !== null ? $fmt($todayAt($gAgo)) : null;
    $ticketInsert->bind_param(
        'siississssssssssiisssssss',
        $ticketNo, $reqId, $categoryId, $custom, $device, $quantity, $location, $contact,
        $coord, $coordPhone, $caretaker, $caretakerPhone, $symptom, $urgency, $urgentReason, $statusDb,
        $assigned, $queueTo, $queueExp, $summary, $method, $cancelReason, $createdAt, $acceptedAt, $completedAt
    );
    $ticketInsert->execute();
    $tid = (int)$conn->insert_id;
    $counts[$statusDb]++;
    $log($tid, null, 'pending', $reqId, 'web', $created);

    $name = "dm_{$tid}_a1.jpg";
    demo_photo_place(demo_photo_groups_before($symptom), $uploadDir . DIRECTORY_SEPARATOR . $name, $tid * 7 + 3);
    $stored = 'ticket-private/' . $name;
    $attachInsert->bind_param('iss', $tid, $stored, $createdAt);
    $attachInsert->execute();
    $imageCount++;

    $helpers = [];
    if ($assigned) {
        $flow($tid, $status, $reqId, $assigned, $categoryId, $todayAt($aAgo), $sAgo !== null ? $todayAt($sAgo) : $now, $gAgo !== null ? $todayAt($gAgo) : $now);
        if (in_array($n, [1, 3], true)) {
            $helpers = $addHelpers($tid, $assigned, $todayAt($aAgo) + 60);
        }
    }
    if ($hasMethod) {
        $name = "dm_{$tid}_done.jpg";
        demo_photo_place(demo_photo_groups_after((string)$method), $uploadDir . DIRECTORY_SEPARATOR . $name, $tid * 5 + 1);
        $stored = 'ticket-private/' . $name;
        $completionUpdate->bind_param('si', $stored, $tid);
        $completionUpdate->execute();
        $imageCount++;
    }
    if ($status === 'completed') {
        $signer = $pick($admins); // คงลำดับการสุ่มเดิมไว้
    }
}
$conn->commit();

// ---------------------------------------------------------------- QR พร้อมเพย์ตัวอย่างของ Lab boy (ไม่ใช่ QR ใช้งานได้จริง)
$demoQr = static function (int $userId, string $name): string {
    $dir = app_ensure_upload_dir();
    $file = 'dm_tipqr_' . $userId . '.png';
    $im = imagecreatetruecolor(360, 420);
    $white = imagecolorallocate($im, 255, 255, 255);
    $ink = imagecolorallocate($im, 22, 22, 22);
    $muted = imagecolorallocate($im, 107, 104, 98);
    imagefill($im, 0, 0, $white);
    // ลวดลายคล้าย QR จากตัวสุ่มเฉพาะที่ (ไม่กระทบลำดับสุ่มของ seed) — ไม่สามารถสแกนจ่ายเงินได้
    $state = ($userId * 2654435761) & 0x7fffffff;
    for ($y = 0; $y < 25; $y++) {
        for ($x = 0; $x < 25; $x++) {
            $state = ($state * 1103515245 + 12345) & 0x7fffffff;
            $finder = ($x < 7 && $y < 7) || ($x > 17 && $y < 7) || ($x < 7 && $y > 17);
            $on = $finder ? (min($x % 18, $y % 18) === 0 || max($x % 18, $y % 18) === 6 || (in_array($x % 18, [2, 3, 4], true) && in_array($y % 18, [2, 3, 4], true))) : ($state % 2 === 0);
            if ($on) imagefilledrectangle($im, 30 + $x * 12, 30 + $y * 12, 41 + $x * 12, 41 + $y * 12, $ink);
        }
    }
    imagestring($im, 5, 112, 345, 'QR SAMPLE', $ink);
    imagestring($im, 3, 70, 370, 'Demo only - not a real payment QR', $muted);
    imagepng($im, $dir . DIRECTORY_SEPARATOR . $file);
    imagedestroy($im);
    return 'ticket-private/' . $file;
};
foreach ($staffDefs as $k => $members) {
    foreach ($members as $i => [$username, $first, $last]) {
        $uid = $rosters[$k][$i];
        db_exec($conn, 'UPDATE users SET promptpay_qr_path = ? WHERE user_id = ?', 'si', [$demoQr($uid, $first), $uid]);
    }
}

// ---------------------------------------------------------------- คำขอบคุณจำลอง (ผู้แจ้งให้ Lab boy · ไม่เกี่ยวกับคะแนน)
$thanksInsert = $conn->prepare('INSERT INTO ticket_thanks (ticket_id, from_user_id, to_user_id, kind, amount, item_note, message, status, staff_note, confirmed_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$thanksGifts = [['snack', 'ขนมปังกับนมกล่อง', 35], ['drink', 'กาแฟเย็น 2 แก้ว', 90], ['drink', 'ชาไทย 1 แก้ว', 40], ['snack', 'ขนมถุงใหญ่', 60], ['other', 'ผลไม้ 1 ถุง', null]];
$thanksMsgs = ['ขอบคุณที่มาซ่อมให้เร็วมาก', 'ซ่อมเรียบร้อย ใช้งานได้ดีแล้ว', 'ขอบคุณที่อธิบายวิธีใช้ให้ด้วย', '', 'บริการดีมาก'];
$thanksCount = 0;
$conn->begin_transaction();
foreach (db_all($conn, "SELECT t.id, t.requester_id, t.assigned_to, (SELECT MAX(signed_at) FROM ticket_signoffs s WHERE s.ticket_id = t.id AND s.signer_role = 'requester' AND s.stage = 'return') AS returned_at FROM tickets t WHERE t.ticket_no LIKE 'DM-%' AND t.status IN ('awaiting_admin_sign', 'completed') AND t.assigned_to IS NOT NULL ORDER BY t.id") as $t) {
    if (!$chance(0.18) || !$t['returned_at']) continue;
    $created = strtotime($t['returned_at']) + mt_rand(300, 7200);
    if ($created > $now) $created = $now - 60;
    if ($chance(0.55)) {
        $kind = 'money'; $amount = (float)$pick([20, 30, 50, 50, 100]); $item = '';
    } else {
        [$kind, $item, $amount] = $pick($thanksGifts);
        $amount = $amount !== null ? (float)$amount : null;
    }
    $recent = $now - $created < 86400 * 2;
    $status = $recent && $chance(0.6) ? 'pending' : ($chance(0.94) ? 'confirmed' : 'not_received');
    $staffNote = $status === 'not_received' ? 'ยังไม่ได้รับ เช็คบัญชีแล้วไม่พบยอด' : '';
    $confirmedAt = $status === 'pending' ? null : $fmt(min($now, $created + mt_rand(600, 86400)));
    $msg = $pick($thanksMsgs);
    $createdAt = $fmt($created);
    $from = (int)$t['requester_id'];
    $to = (int)$t['assigned_to'];
    $tid = (int)$t['id'];
    $thanksInsert->bind_param('iiisdssssss', $tid, $from, $to, $kind, $amount, $item, $msg, $status, $staffNote, $confirmedAt, $createdAt);
    $thanksInsert->execute();
    $thanksCount++;
}
$conn->commit();

// ---------------------------------------------------------------- ครูหัก/ให้คะแนน (กติกา: เริ่มภาคเรียน 100 · หักตามข้อกำหนด · ครูบวกคืนไม่เกิน 100)
$rules = db_all($conn, 'SELECT label, points FROM score_rules WHERE is_active = 1 ORDER BY sort_order');
$minusRules = array_values(array_filter($rules, static fn($r) => (int)$r['points'] < 0));
$plusRules = array_values(array_filter($rules, static fn($r) => (int)$r['points'] > 0));
$scoreCount = 0;
if ($minusRules) {
    foreach ($termDefs as $k => $td) {
        $termStart = strtotime($td['start'] . ' 09:00');
        $termEnd = min($now - 3600, strtotime($td['end'] . ' 16:00'));
        foreach ($rosters[$k] as $uid) {
            $events = mt_rand(1, 3);
            for ($e = 0; $e < $events; $e++) {
                $rule = $pick($minusRules);
                $score($uid, null, (int)$rule['points'], 'ครูหักคะแนน: ' . $rule['label'], $pick($admins), $workTime(mt_rand($termStart, max($termStart + 1, $termEnd))));
                $scoreCount++;
            }
            if ($plusRules && $chance(0.6)) {
                $rule = $pick($plusRules);
                $score($uid, null, (int)$rule['points'], 'ครูให้คะแนน: ' . $rule['label'], $pick($admins), $workTime(mt_rand($termStart, max($termStart + 1, $termEnd))));
                $scoreCount++;
            }
        }
    }
}

// ---------------------------------------------------------------- สรุปคะแนนและยอดสต็อก
$conn->query("UPDATE users u SET score = GREATEST(0, 100 + COALESCE((SELECT SUM(points_change) FROM score_logs s WHERE s.user_id = u.user_id), 0)) WHERE u.role = 'staff' AND u.email LIKE '%@demo.reps.invalid'");
foreach ($items as $id => $it) {
    db_exec($conn, 'UPDATE inventory_items SET stock_qty = ? WHERE id = ?', 'ii', [$it['stock'], $id]);
}
// ให้มีวัสดุใกล้หมด 2 รายการเพื่อสาธิตการแจ้งเตือน
$lowIds = array_slice(array_keys($items), 5, 2);
foreach ($lowIds as $id) {
    $target = max(0, $items[$id]['min'] - 1);
    $before = (int)db_one($conn, 'SELECT stock_qty FROM inventory_items WHERE id = ?', 'i', [$id])['stock_qty'];
    if ($before > $target) {
        $move($id, 'adjust', $target - $before, $before, null, $admins[0], 'ตรวจนับสต็อกประจำเดือน', $now - 86400 * 2);
        db_exec($conn, 'UPDATE inventory_items SET stock_qty = ? WHERE id = ?', 'ii', [$target, $id]);
    }
}

$total = array_sum($counts);
echo "สร้างข้อมูลจำลองแล้ว: ผู้ใช้ " . (2 + count($staff) + 60 + 6 + 2 + 1) . " บัญชี, ใบงาน {$total} ใบ\n";
foreach ($counts as $s => $n) echo "  {$s}: {$n}\n";
echo "คำขอบคุณจำลอง {$thanksCount} รายการ · ครูหัก/ให้คะแนน {$scoreCount} รายการ · QR ตัวอย่างของ Lab boy ทุกคน\n";
echo "รูปตัวอย่าง {$imageCount} ไฟล์ (รูปตอนแจ้งซ่อม + รูปหลังซ่อม · เครดิตใน database/seeds/photos/CREDITS.md)\n";

// บัญชีสตาฟพร้อมรหัสผ่าน: เก็บในไฟล์ในเครื่อง (อยู่ใน .gitignore และ database/ ถูกกันเข้าจากเว็บด้วย .htaccess)
$lines = ["# บัญชีสตาฟ (Lab boy) จากข้อมูลจำลอง — เครื่องพัฒนาเท่านั้น ห้าม commit", '# ชื่อผู้ใช้        รหัสผ่าน        ชื่อ-สกุล / ภาคเรียน'];
foreach ($credentials as [$u, $p, $name, $term]) {
    $lines[] = sprintf('%-16s %-14s %s · ทีมภาคเรียน %s', $u, $p, $name, $term);
}
file_put_contents($credFile, implode("\n", $lines) . "\n", LOCK_EX);
@chmod($credFile, 0600);
echo "\nทีมสตาฟ (Lab boy) — 1/2569: " . count($rosters[1]) . " คน · 2/2569: " . count($rosters[2]) . " คน (ภาคเรียนปัจจุบัน " . $termDefs[$currentTermKey]['no'] . '/' . $termDefs[$currentTermKey]['year'] . ")\n";
foreach ($credentials as [$u, $p, $name, $term]) echo sprintf("  %-14s %-12s %s (%s)\n", $u, $p, $name, $term);
echo "เก็บรหัสผ่านไว้ที่ database/seeds/demo_credentials.local.txt\n";
echo "บัญชีสาธิตอื่น: demo_admin1, demo_u001  รหัสผ่าน Demo1234!\n";
