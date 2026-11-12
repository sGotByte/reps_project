<?php
declare(strict_types=1);

/**
 * REPS · staff/staff_dashboard.php
 * ศูนย์ปฏิบัติการฝ่ายซ่อม (Staff):
 * - จัดการคิวงาน & คะแนนวินัย + บันทึกลง score_logs (ข้อ 2, 12)
 * - กดรับงาน / ปฏิเสธงานตามคิว (หัก 5 คะแนน และโอนคิวให้สตาฟคนถัดไป) (ข้อ 2)
 * - ปิดงานง่ายๆ พร้อมรูปหลักฐาน (completion_image), วิธีซ่อม (repair_method), สรุปผล (resolution_summary) (ข้อ 3, 13, 18)
 * - เบิกอะไหล่ลง ticket_materials และตัดสต็อก inventory_items (ข้อ 16, 17)
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/csrf.php';
if (is_file(__DIR__ . '/../includes/site_config.php')) {
    require_once __DIR__ . '/../includes/site_config.php';
}
if (is_file(__DIR__ . '/../includes/queue_manager.php')) {
    require_once __DIR__ . '/../includes/queue_manager.php';
}

/** @var mysqli $conn */
$userId = (int)($_SESSION['user_id'] ?? 0);
$userRole = (string)($_SESSION['role'] ?? '');
if ($userId <= 0 || !in_array($userRole, ['staff', 'admin'], true)) {
    header('Location: ../login.php?next=dashboard', true, 303);
    exit;
}

if (function_exists('queue_process_expired_tickets')) {
    queue_process_expired_tickets($conn);
}

$siteName = function_exists('app_site_name') ? app_site_name($conn) : 'REPS';
$csrfToken = app_csrf_token();

if (!function_exists('h')) {
    function h(mixed $v): string
    {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

// ตรวจสอบชื่อคอลัมน์จริงของตาราง inventory_items แบบอัตโนมัติ
$invCols = [];
$colRes = $conn->query('SHOW COLUMNS FROM inventory_items');
if ($colRes instanceof mysqli_result) {
    while ($c = $colRes->fetch_assoc()) {
        $invCols[] = $c['Field'];
    }
    $colRes->free();
}
$invPkCol   = in_array('id', $invCols, true) ? 'id' : 'item_id';
$invNameCol = in_array('name', $invCols, true) ? 'name' : 'item_name';
$invQtyCol  = in_array('quantity', $invCols, true) ? 'quantity' : 'stock_qty';
$invUnitCol = in_array('unit', $invCols, true) ? 'unit' : null;

$flashMsg = is_array($_SESSION['staff_flash'] ?? null) ? (string)($_SESSION['staff_flash']['msg'] ?? '') : '';
$flashType = is_array($_SESSION['staff_flash'] ?? null) ? (string)($_SESSION['staff_flash']['type'] ?? 'ok') : 'ok';
unset($_SESSION['staff_flash']);

// จัดการคำสั่ง POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_csrf_is_valid($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('คำขอหมดอายุ กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }

    $action   = trim((string)($_POST['action'] ?? ''));
    $ticketId = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT) ?: 0;
    $msg = '';
    $type = 'ok';

    // 1. เปิด/ปิดสถานะรับคิวงานของตัวเอง (ข้อ 12)
    if ($action === 'toggle_queue') {
        $newState = (int)($_POST['is_active_queue'] ?? 0) === 1 ? 1 : 0;
        $stmt = $conn->prepare('UPDATE users SET is_active_queue = ? WHERE user_id = ?');
        if ($stmt) {
            $stmt->bind_param('ii', $newState, $userId);
            $stmt->execute();
            $stmt->close();
        }
        $msg = $newState === 1 ? 'เปิดสถานะรับคิวงานเรียบร้อยแล้ว' : 'ปิดพักการรับคิวงานชั่วคราวแล้ว';
    }

    // 2. กดรับงานเข้าดำเนินการ (ข้อ 2)
    elseif ($action === 'accept_ticket' && $ticketId > 0) {
        $stmt = $conn->prepare(
            "UPDATE tickets 
             SET status = 'in_progress', assigned_to = ?, queue_assigned_to = NULL, queue_expires_at = NULL, accepted_at = NOW() 
             WHERE id = ? AND status = 'pending'"
        );
        if ($stmt) {
            $stmt->bind_param('ii', $userId, $ticketId);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $logStmt = $conn->prepare("INSERT INTO ticket_logs (ticket_id, action_by, action_detail) VALUES (?, ?, 'สตาฟกดรับงานเข้าดำเนินการ')");
                if ($logStmt) {
                    $logStmt->bind_param('ii', $ticketId, $userId);
                    $logStmt->execute();
                    $logStmt->close();
                }
                $msg = 'รับงานเรียบร้อยแล้ว! สถานะเปลี่ยนเป็นกำลังดำเนินการ';
            } else {
                $msg = 'ไม่สามารถรับงานนี้ได้ (อาจมีสตาฟท่านอื่นรับไปแล้ว)';
                $type = 'err';
            }
            $stmt->close();
        }
    }

    // 3. ปฏิเสธ/ไม่รับงานเมื่อถึงคิว -> หักคะแนน 5 แต้ม + บันทึก score_logs + โอนคิวให้สตาฟคนถัดไป (ข้อ 2)
    elseif ($action === 'decline_ticket' && $ticketId > 0) {
        $penalty = -5;
        $conn->begin_transaction();
        try {
            $penStmt = $conn->prepare('UPDATE users SET score = GREATEST(0, score + ?) WHERE user_id = ?');
            if ($penStmt) {
                $penStmt->bind_param('ii', $penalty, $userId);
                $penStmt->execute();
                $penStmt->close();
            }

            $reason = 'ปฏิเสธไม่รับงานซ่อมเมื่อถึงคิว';
            $scStmt = $conn->prepare('INSERT INTO score_logs (user_id, ticket_id, points_change, reason, action_by) VALUES (?, ?, ?, ?, ?)');
            if ($scStmt) {
                $scStmt->bind_param('iiisi', $userId, $ticketId, $penalty, $reason, $userId);
                $scStmt->execute();
                $scStmt->close();
            }

            $nextStaffId = null;
            $nextStmt = $conn->prepare(
                "SELECT user_id FROM users 
                 WHERE role = 'staff' AND status = 'active' AND is_active_queue = 1 AND user_id != ? 
                 ORDER BY queue_order ASC, score DESC, user_id ASC LIMIT 1"
            );
            if ($nextStmt) {
                $nextStmt->bind_param('i', $userId);
                $nextStmt->execute();
                $res = $nextStmt->get_result();
                $nextRow = $res ? $res->fetch_assoc() : null;
                $nextStaffId = $nextRow ? (int)$nextRow['user_id'] : null;
                $nextStmt->close();
            }

            $updStmt = $conn->prepare(
                "UPDATE tickets 
                 SET queue_assigned_to = ?, queue_expires_at = IF(? IS NOT NULL, DATE_ADD(NOW(), INTERVAL 30 MINUTE), NULL), status = 'pending' 
                 WHERE id = ? AND queue_assigned_to = ?"
            );
            if ($updStmt) {
                $updStmt->bind_param('iiii', $nextStaffId, $nextStaffId, $ticketId, $userId);
                $updStmt->execute();
                $updStmt->close();
            }

            $conn->commit();
            $msg = 'ปฏิเสธงานตามคิว ระบบหักคะแนน 5 แต้ม และส่งคิวให้สตาฟคนถัดไปเรียบร้อยแล้ว';
            $type = 'err';
        } catch (Throwable $e) {
            $conn->rollback();
            $msg = 'เกิดข้อผิดพลาดในการโอนคิวงาน: ' . $e->getMessage();
            $type = 'err';
        }
    }

    // 4. ปิดงานซ่อม + อัปโหลดรูปหลังซ่อม + บันทึกวิธีซ่อม + เบิกอะไหล่ลง ticket_materials (ข้อ 3, 13, 16, 17, 18)
    elseif ($action === 'complete_ticket' && $ticketId > 0) {
        $repairMethod      = trim((string)($_POST['repair_method'] ?? ''));
        $resolutionSummary = trim((string)($_POST['resolution_summary'] ?? ''));
        $itemIds           = $_POST['item_id'] ?? [];
        $itemQtys          = $_POST['item_qty'] ?? [];
        $itemNotes         = $_POST['item_note'] ?? [];

        $uploadedPath = null;
        if (isset($_FILES['completion_image']) && $_FILES['completion_image']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['completion_image']['tmp_name'];
            $mime = mime_content_type($tmpName);
            $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($allowedMimes[$mime])) {
                $_SESSION['staff_flash'] = ['msg' => 'รองรับเฉพาะไฟล์รูปภาพ JPG, PNG หรือ WEBP เท่านั้น', 'type' => 'err'];
                header('Location: staff_dashboard.php', true, 303);
                exit;
            }
            $uploadDir = __DIR__ . '/../private_uploads/completions';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            $fileName = 'done_' . $ticketId . '_' . time() . '.' . $allowedMimes[$mime];
            if (move_uploaded_file($tmpName, $uploadDir . '/' . $fileName)) {
                $uploadedPath = 'private_uploads/completions/' . $fileName;
            }
        }

        $conn->begin_transaction();
        try {
            if (is_array($itemIds) && is_array($itemQtys)) {
                $totalItems = count($itemIds);
                for ($i = 0; $i < $totalItems; $i++) {
                    $iid  = (int)($itemIds[$i] ?? 0);
                    $qty  = (int)($itemQtys[$i] ?? 0);
                    $note = trim((string)($itemNotes[$i] ?? ''));
                    if ($iid > 0 && $qty > 0) {
                        $stockStmt = $conn->prepare("UPDATE inventory_items SET {$invQtyCol} = GREATEST(0, {$invQtyCol} - ?) WHERE {$invPkCol} = ?");
                        if ($stockStmt) {
                            $stockStmt->bind_param('ii', $qty, $iid);
                            $stockStmt->execute();
                            $stockStmt->close();
                        }

                        $noteVal = $note !== '' ? $note : null;
                        $matStmt = $conn->prepare('INSERT INTO ticket_materials (ticket_id, item_id, qty_used, withdrawn_by, note) VALUES (?, ?, ?, ?, ?)');
                        if ($matStmt) {
                            $matStmt->bind_param('iiiis', $ticketId, $iid, $qty, $userId, $noteVal);
                            $matStmt->execute();
                            $matStmt->close();
                        }
                    }
                }
            }

            $doneStmt = $conn->prepare(
                "UPDATE tickets 
                 SET status = 'completed', 
                     completed_at = NOW(), 
                     repair_method = ?, 
                     resolution_summary = ?, 
                     completion_image = COALESCE(?, completion_image) 
                 WHERE id = ? AND assigned_to = ?"
            );
            if ($doneStmt) {
                $doneStmt->bind_param('sssii', $repairMethod, $resolutionSummary, $uploadedPath, $ticketId, $userId);
                $doneStmt->execute();
                $doneStmt->close();
            }

            $bonus = 2;
            $bonusStmt = $conn->prepare('UPDATE users SET score = score + ? WHERE user_id = ?');
            if ($bonusStmt) {
                $bonusStmt->bind_param('ii', $bonus, $userId);
                $bonusStmt->execute();
                $bonusStmt->close();
            }

            $reasonDone = 'ปิดงานซ่อมสำเร็จตามกำหนด';
            $scDoneStmt = $conn->prepare('INSERT INTO score_logs (user_id, ticket_id, points_change, reason, action_by) VALUES (?, ?, ?, ?, ?)');
            if ($scDoneStmt) {
                $scDoneStmt->bind_param('iiisi', $userId, $ticketId, $bonus, $reasonDone, $userId);
                $scDoneStmt->execute();
                $scDoneStmt->close();
            }

            $conn->commit();
            $msg = 'ปิดงานซ่อมเรียบร้อยแล้ว! (+2 คะแนน)';
        } catch (Throwable $e) {
            $conn->rollback();
            $msg = 'เกิดข้อผิดพลาดในการปิดงาน: ' . $e->getMessage();
            $type = 'err';
        }
    }

    $_SESSION['staff_flash'] = ['msg' => $msg, 'type' => $type];
    header('Location: staff_dashboard.php', true, 303);
    exit;
}

// ดึงข้อมูลโปรไฟล์ คะแนน และลำดับคิวของสตาฟที่ล็อกอิน
$me = ['first_name' => 'Staff', 'last_name' => '', 'score' => 100, 'queue_order' => null, 'is_active_queue' => 0];
$meStmt = $conn->prepare('SELECT first_name, last_name, score, queue_order, is_active_queue FROM users WHERE user_id = ? LIMIT 1');
if ($meStmt) {
    $meStmt->bind_param('i', $userId);
    $meStmt->execute();
    $resMe = $meStmt->get_result();
    if ($resMe && ($rowMe = $resMe->fetch_assoc())) {
        $me = $rowMe;
    }
    $meStmt->close();
}

// ดึงรายการงานที่รอรับ
$pendingRes = $conn->query(
    "SELECT t.*, c.name AS category_name, u.first_name AS req_first, u.last_name AS req_last, u.phone_number AS req_phone
     FROM tickets t
     LEFT JOIN categories c ON c.id = t.category_id
     LEFT JOIN users u ON u.user_id = t.requester_id
     WHERE t.status = 'pending' AND (t.queue_assigned_to = {$userId} OR t.queue_assigned_to IS NULL)
     ORDER BY (t.queue_assigned_to = {$userId}) DESC, (t.urgency = 'urgent') DESC, t.created_at ASC"
);
$pendingTickets = ($pendingRes instanceof mysqli_result) ? $pendingRes->fetch_all(MYSQLI_ASSOC) : [];

// ดึงงานที่ฉันกำลังดำเนินการอยู่
$myActiveRes = $conn->query(
    "SELECT t.*, c.name AS category_name, u.first_name AS req_first, u.last_name AS req_last, u.phone_number AS req_phone
     FROM tickets t
     LEFT JOIN categories c ON c.id = t.category_id
     LEFT JOIN users u ON u.user_id = t.requester_id
     WHERE t.status = 'in_progress' AND t.assigned_to = {$userId}
     ORDER BY (t.urgency = 'urgent') DESC, t.accepted_at ASC"
);
$myActiveTickets = ($myActiveRes instanceof mysqli_result) ? $myActiveRes->fetch_all(MYSQLI_ASSOC) : [];

// ดึงรายการอุปกรณ์ในสต็อก inventory_items
$unitSelect = $invUnitCol ? ", {$invUnitCol} AS item_unit" : ", 'ชิ้น' AS item_unit";
$invRes = $conn->query("SELECT {$invPkCol} AS item_id, {$invNameCol} AS item_name, {$invQtyCol} AS stock_qty {$unitSelect} FROM inventory_items WHERE {$invQtyCol} > 0 ORDER BY {$invNameCol} ASC");
$inventoryItems = ($invRes instanceof mysqli_result) ? $invRes->fetch_all(MYSQLI_ASSOC) : [];

// ดึงประวัติคะแนนล่าสุด 5 รายการของสตาฟจาก score_logs
$scHistRes = $conn->query("SELECT points_change, reason, created_at FROM score_logs WHERE user_id = {$userId} ORDER BY created_at DESC LIMIT 5");
$scoreHistory = ($scHistRes instanceof mysqli_result) ? $scHistRes->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ศูนย์ปฏิบัติการฝ่ายซ่อม (Staff) | <?= h($siteName) ?></title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo/reps-icon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500;600&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { kanit: ['Kanit', 'sans-serif'], mono: ['JetBrains Mono', 'monospace'] } } } };</script>
    <?= function_exists('app_theme_css') ? app_theme_css($conn) : '' ?>
    <style>
        body { background: #FAFAF8; color: #161616; font-family: 'Kanit', 'Inter', sans-serif; }
        .tray { padding: 6px; border: 1px solid #EAE8E4; border-radius: 20px; background: #F7F6F4; }
        .tray-card { border: 1px solid #ECEAE6; border-radius: 14px; background: #FFFFFF; }
    </style>
</head>
<body class="min-h-screen flex flex-col antialiased">

    <header class="sticky top-0 z-30 h-16 border-b border-[#ECEAE6] bg-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-7 h-full flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="staff_dashboard.php"><img src="../assets/images/logo/reps-logo.svg" alt="REPS" class="h-6 w-auto"></a>
                <span class="text-neutral-300">|</span>
                <span class="font-mono text-xs font-semibold uppercase tracking-wider px-2.5 py-0.5 rounded bg-[#4B5563] text-white">STAFF</span>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <a href="../request_form.php" class="px-3 py-1.5 rounded-lg border border-neutral-200 bg-white hover:bg-neutral-50">แจ้งซ่อม</a>
                <a href="../profile.php" class="px-3 py-1.5 rounded-lg border border-neutral-200 bg-white hover:bg-neutral-50">โปรไฟล์</a>
                <a href="../logout.php" class="px-3 py-1.5 rounded-lg border border-red-200 text-red-700 hover:bg-red-50">ออกจากระบบ</a>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-5xl w-full mx-auto px-4 sm:px-7 py-7 space-y-6">

        <?php if ($flashMsg !== ''): ?>
            <div class="rounded-xl border p-3.5 text-xs sm:text-sm <?= $flashType === 'err' ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' ?>">
                <?= h($flashMsg) ?>
            </div>
        <?php endif; ?>

        <!-- แถบสถานะสตาฟ: คะแนน + สถานะคิวรับงาน (ข้อ 2, 12) -->
        <section class="tray">
            <div class="tray-card p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="text-xs text-neutral-500">เจ้าหน้าที่ฝ่ายซ่อม (Staff)</div>
                    <h1 class="text-xl sm:text-2xl font-medium text-neutral-900 mt-0.5"><?= h($me['first_name'] . ' ' . $me['last_name']) ?></h1>
                    <div class="flex flex-wrap items-center gap-2.5 mt-2 text-xs">
                        <span class="font-mono font-semibold px-2.5 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200">
                            <i class="fa-solid fa-star text-amber-500 mr-1"></i> คะแนนวินัย: <?= (int)$me['score'] ?> pt
                        </span>
                        <span class="font-mono px-2.5 py-1 rounded-full bg-neutral-100 text-neutral-700">
                            ลำดับคิว: <?= $me['queue_order'] !== null ? '#' . (int)$me['queue_order'] : 'ตามคะแนน' ?>
                        </span>
                    </div>
                </div>

                <form method="POST" class="flex items-center gap-2">
                    <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="action" value="toggle_queue">
                    <?php if ((int)$me['is_active_queue'] === 1): ?>
                        <input type="hidden" name="is_active_queue" value="0">
                        <button type="submit" class="px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-medium hover:bg-emerald-700 transition cursor-pointer">
                            🟢 กำลังเปิดรับคิวงาน (คลิกเพื่อพักคิว)
                        </button>
                    <?php else: ?>
                        <input type="hidden" name="is_active_queue" value="1">
                        <button type="submit" class="px-4 py-2.5 rounded-xl bg-neutral-800 text-white text-xs font-medium hover:bg-neutral-900 transition cursor-pointer">
                            ⚪ ปิดรับคิวอยู่ (คลิกเพื่อเปิดรับงาน)
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </section>

        <!-- Section 1: งานที่กำลังดำเนินการอยู่ -->
        <section class="tray border-neutral-300">
            <div class="px-4 py-2.5 flex items-center justify-between text-xs">
                <span class="font-semibold text-neutral-900 flex items-center gap-2">
                    <i class="fa-solid fa-screwdriver-wrench text-neutral-700"></i> งานที่กำลังดำเนินการของฉัน (<?= count($myActiveTickets) ?> งาน)
                </span>
                <span class="text-neutral-500 text-[11px]">แนบรูปหลังซ่อม & เบิกอะไหล่ตอนปิดงาน</span>
            </div>
            <div class="tray-card p-4 space-y-3">
                <?php if (empty($myActiveTickets)): ?>
                    <p class="text-xs text-neutral-400 text-center py-6">ไม่มีงานค้างที่กำลังดำเนินการอยู่</p>
                <?php else: ?>
                    <?php foreach ($myActiveTickets as $job):
                        $catDisplay = !empty($job['custom_category']) ? $job['custom_category'] : ($job['category_name'] ?? 'ทั่วไป');
                    ?>
                        <div class="p-4 rounded-xl border border-neutral-200 bg-[#FAFAF8] flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="space-y-1 text-xs">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-mono font-semibold text-sm text-neutral-900">#<?= h($job['ticket_no']) ?></span>
                                    <span class="px-2 py-0.5 rounded bg-neutral-200 text-neutral-800 font-medium"><?= h($catDisplay) ?></span>
                                    <?php if (($job['urgency'] ?? '') === 'urgent'): ?>
                                        <span class="px-2 py-0.5 rounded bg-red-100 text-red-700 font-medium">ด่วน (<?= h($job['urgency_reason'] ?? '-') ?>)</span>
                                    <?php endif; ?>
                                </div>
                                <div class="font-medium text-neutral-900 text-sm"><?= h($job['location_note']) ?> (จำนวน <?= (int)$job['quantity'] ?>)</div>
                                <p class="text-neutral-600"><?= h($job['symptom']) ?></p>
                                <div class="text-neutral-500 pt-1 flex flex-wrap gap-x-4 gap-y-1">
                                    <span>ผู้แจ้ง: <?= h($job['req_first'] . ' ' . $job['req_last']) ?> (<?= h($job['req_phone'] ?? '-') ?>)</span>
                                    <?php if (!empty($job['contact_person'])): ?>
                                        <span>ผู้ติดต่อหน้างาน: <?= h($job['contact_person']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($job['coordinator_teacher'])): ?>
                                        <span>ครูผู้ประสานงาน: <?= h($job['coordinator_teacher']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($job['room_caretaker'])): ?>
                                        <span>ครูผู้ดูแลห้อง: <?= h($job['room_caretaker']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <button type="button"
                                onclick="openCompleteModal(<?= (int)$job['id'] ?>, '<?= h($job['ticket_no']) ?>', '<?= h($job['location_note']) ?>')"
                                class="shrink-0 px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-medium hover:bg-emerald-700 transition cursor-pointer inline-flex items-center gap-2">
                                <i class="fa-solid fa-circle-check"></i> ปิดงานซ่อม
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Section 2: คิวงานใหม่ที่รอรับเรื่อง -->
        <section class="tray">
            <div class="px-4 py-2.5 flex items-center justify-between text-xs">
                <span class="font-semibold text-neutral-900 flex items-center gap-2">
                    <i class="fa-solid fa-bell text-amber-600"></i> งานเข้าใหม่ / คิวงานที่รอรับ (<?= count($pendingTickets) ?> รายการ)
                </span>
                <span class="text-red-600 text-[11px]">* หากถึงคิวแล้วปฏิเสธงาน จะถูกหัก 5 คะแนน</span>
            </div>
            <div class="tray-card p-4 space-y-3">
                <?php if (empty($pendingTickets)): ?>
                    <p class="text-xs text-neutral-400 text-center py-6">ยังไม่มีใบแจ้งซ่อมใหม่ในคิวขณะนี้</p>
                <?php else: ?>
                    <?php foreach ($pendingTickets as $pt):
                        $isMyDirectQueue = ((int)($pt['queue_assigned_to'] ?? 0) === $userId);
                        $catDisplay = !empty($pt['custom_category']) ? $pt['custom_category'] : ($pt['category_name'] ?? 'ทั่วไป');
                    ?>
                        <div class="p-4 rounded-xl border <?= $isMyDirectQueue ? 'border-amber-300 bg-amber-50/40' : 'border-neutral-200 bg-white' ?> flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="space-y-1 text-xs">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-mono font-semibold text-sm">#<?= h($pt['ticket_no']) ?></span>
                                    <?php if ($isMyDirectQueue): ?>
                                        <span class="px-2 py-0.5 rounded-full bg-amber-600 text-white text-[11px] font-medium">ถึงคิวของคุณแล้ว!</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full bg-neutral-100 text-neutral-600 text-[11px]">คิวกลาง</span>
                                    <?php endif; ?>
                                    <?php if (($pt['urgency'] ?? '') === 'urgent'): ?>
                                        <span class="px-2 py-0.5 rounded-full bg-red-600 text-white text-[11px]">ด่วน</span>
                                    <?php endif; ?>
                                    <span class="text-neutral-400 font-mono"><?= date('d/m/Y H:i', strtotime($pt['created_at'])) ?></span>
                                </div>
                                <div class="font-medium text-neutral-900 text-sm"><?= h($pt['location_note']) ?> · <?= h($catDisplay) ?> (จำนวน <?= (int)$pt['quantity'] ?>)</div>
                                <p class="text-neutral-600"><?= h($pt['symptom']) ?></p>
                                <div class="text-neutral-500">
                                    ผู้แจ้ง: <?= h($pt['req_first'] . ' ' . $pt['req_last']) ?> (<?= h($pt['req_phone'] ?? '-') ?>)
                                    <?php if (!empty($pt['queue_expires_at']) && $isMyDirectQueue): ?>
                                        · <span class="text-amber-700 font-medium">หมดเวลารับคิว: <?= date('H:i น.', strtotime($pt['queue_expires_at'])) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <form method="POST">
                                    <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">
                                    <input type="hidden" name="action" value="accept_ticket">
                                    <input type="hidden" name="ticket_id" value="<?= (int)$pt['id'] ?>">
                                    <button type="submit" class="px-4 py-2 rounded-xl bg-neutral-900 text-white text-xs font-medium hover:bg-black transition cursor-pointer">
                                        รับงานนี้
                                    </button>
                                </form>

                                <?php if ($isMyDirectQueue): ?>
                                    <form method="POST" onsubmit="return confirm('ยืนยันปฏิเสธงานตามคิว? ระบบจะหัก 5 คะแนนและส่งคิวให้สตาฟคนถัดไป');">
                                        <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="action" value="decline_ticket">
                                        <input type="hidden" name="ticket_id" value="<?= (int)$pt['id'] ?>">
                                        <button type="submit" class="px-3 py-2 rounded-xl border border-red-200 text-red-700 text-xs font-medium hover:bg-red-50 transition cursor-pointer">
                                            ไม่รับงาน (-5 pt)
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Section 3: ประวัติคะแนนล่าสุด (จาก score_logs) -->
        <?php if (!empty($scoreHistory)): ?>
            <section class="tray">
                <div class="px-4 py-2 text-xs font-medium text-neutral-600">ประวัติคะแนนวินัยล่าสุด</div>
                <div class="tray-card divide-y divide-neutral-100 text-xs">
                    <?php foreach ($scoreHistory as $sh):
                        $pts = (int)$sh['points_change'];
                    ?>
                        <div class="px-4 py-2.5 flex items-center justify-between">
                            <div>
                                <span class="text-neutral-800"><?= h($sh['reason']) ?></span>
                                <span class="text-neutral-400 font-mono ml-2"><?= date('d/m/Y H:i', strtotime($sh['created_at'])) ?></span>
                            </div>
                            <span class="font-mono font-semibold <?= $pts >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
                                <?= $pts >= 0 ? '+' . $pts : $pts ?> pt
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

    </main>

    <!-- Modal ปิดงานซ่อม (ข้อ 3, 13, 16, 17, 18) -->
    <dialog id="completeJobModal" class="m-auto w-[min(540px,calc(100%-2rem))] rounded-2xl border border-neutral-200 bg-white p-0 shadow-2xl backdrop:bg-black/40 backdrop:backdrop-blur-xs">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="action" value="complete_ticket">
            <input type="hidden" name="ticket_id" id="modalTicketId">

            <div class="px-5 py-4 border-b border-neutral-100 flex items-center justify-between">
                <div>
                    <div class="font-mono text-xs text-neutral-500">COMPLETE TICKET · <span id="modalTicketNo"></span></div>
                    <h2 class="text-base font-semibold text-neutral-900" id="modalTicketLoc">ปิดงานซ่อม</h2>
                </div>
                <button type="button" onclick="document.getElementById('completeJobModal').close()" class="w-8 h-8 rounded-lg text-neutral-400 hover:bg-neutral-100">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="p-5 space-y-4 text-xs">
                <div>
                    <label class="block font-medium text-neutral-800 mb-1">1. รูปภาพหลังซ่อมเสร็จ (หลักฐานปิดงาน) <span class="text-red-500">*</span></label>
                    <input type="file" name="completion_image" accept="image/jpeg,image/png,image/webp" required
                        class="w-full rounded-lg border border-neutral-300 p-2 text-xs bg-neutral-50">
                </div>

                <div>
                    <label class="block font-medium text-neutral-800 mb-1">2. วิธีดำเนินการ / สิ่งที่ซ่อมไปบ้าง <span class="text-red-500">*</span></label>
                    <textarea name="repair_method" rows="2" required placeholder="เช่น เข้าหัวสาย RJ45 ใหม่, รีเซ็ต Switch, ตรวจสอบสัญญาณอินเทอร์เน็ต"
                        class="w-full rounded-lg border border-neutral-300 p-2.5 outline-none focus:border-neutral-900"></textarea>
                </div>

                <div>
                    <label class="block font-medium text-neutral-800 mb-1">3. สรุปผลการซ่อม / หมายเหตุปิดงาน <span class="text-red-500">*</span></label>
                    <input type="text" name="resolution_summary" required placeholder="เช่น ใช้งานได้ปกติแล้ว / ทดสอบเชื่อมต่อเรียบร้อย"
                        class="w-full rounded-lg border border-neutral-300 p-2.5 outline-none focus:border-neutral-900">
                </div>

                <div class="p-3 rounded-xl border border-neutral-200 bg-neutral-50 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-neutral-800">4. เบิกอะไหล่ / อุปกรณ์จากสต็อก (ถ้ามี)</span>
                        <button type="button" onclick="addStockRow()" class="text-neutral-900 underline font-medium cursor-pointer">+ เพิ่มรายการ</button>
                    </div>
                    <div id="stockRows" class="space-y-2">
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2">
                            <select name="item_id[]" class="sm:col-span-6 rounded-lg border border-neutral-300 bg-white px-2.5 py-2">
                                <option value="0">-- ไม่ได้เบิกอุปกรณ์ --</option>
                                <?php foreach ($inventoryItems as $inv): ?>
                                    <option value="<?= (int)$inv['item_id'] ?>">
                                        <?= h($inv['item_name']) ?> (เหลือ <?= (int)$inv['stock_qty'] ?> <?= h($inv['item_unit']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="item_qty[]" min="1" placeholder="จำนวน" class="sm:col-span-2 rounded-lg border border-neutral-300 bg-white px-2.5 py-2">
                            <input type="text" name="item_note[]" placeholder="หมายเหตุ (ถ้ามี)" class="sm:col-span-4 rounded-lg border border-neutral-300 bg-white px-2.5 py-2">
                        </div>
                    </div>
                    <div class="text-[11px] text-neutral-500">ระบบจะบันทึกชื่อผู้เบิกเป็น <strong><?= h($me['first_name'] . ' ' . $me['last_name']) ?></strong> อัตโนมัติ</div>
                </div>
            </div>

            <div class="px-5 py-3.5 border-t border-neutral-100 bg-neutral-50 flex justify-end gap-2 text-xs">
                <button type="button" onclick="document.getElementById('completeJobModal').close()" class="px-3.5 py-2 rounded-lg border border-neutral-300 bg-white">ยกเลิก</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white font-medium hover:bg-emerald-700 cursor-pointer">
                    ยืนยันปิดงานซ่อม
                </button>
            </div>
        </form>
    </dialog>

    <script>
        function openCompleteModal(ticketId, ticketNo, loc) {
            document.getElementById('modalTicketId').value = ticketId;
            document.getElementById('modalTicketNo').textContent = ticketNo;
            document.getElementById('modalTicketLoc').textContent = loc || 'ปิดงานซ่อม';
            document.getElementById('completeJobModal').showModal();
        }

        function addStockRow() {
            const container = document.getElementById('stockRows');
            const firstRow = container.firstElementChild;
            if (!firstRow) return;
            const clone = firstRow.cloneNode(true);
            clone.querySelector('select').value = '0';
            clone.querySelectorAll('input').forEach(inp => inp.value = '');
            container.appendChild(clone);
        }
    </script>
</body>
</html>