<?php
declare(strict_types=1);

$page_title = 'Users';
$current_page = 'users';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$actorId = (int)($_SESSION['user_id'] ?? 0);
$actorRole = (string)($_SESSION['role'] ?? 'admin');

// ตรงกับโครงสร้างตาราง users: enum('admin', 'staff', 'user') โดย admin มีสิทธิ์สูงสุด
$allowedRoles = ['admin', 'staff', 'user'];
$roleLabels = [
    'admin'     => 'Admin',
    'staff'     => 'Staff',
    'user'      => 'User',
    'requester' => 'User',
];

$message = is_array($_SESSION['admin_flash'] ?? null) ? (string)($_SESSION['admin_flash']['message'] ?? '') : '';
$messageType = is_array($_SESSION['admin_flash'] ?? null) ? (string)($_SESSION['admin_flash']['type'] ?? 'success') : 'success';
unset($_SESSION['admin_flash']);

function app_confirm_normalize(string $value): string
{
    return strtoupper(preg_replace('/\s+/u', '', $value) ?? '');
}

// โหลดรายการแผนกสำหรับฟอร์มเพิ่มผู้ใช้
$departments = [];
$deptRes = $conn->query('SELECT d_id, name FROM department ORDER BY name ASC');
if ($deptRes) {
    $departments = $deptRes->fetch_all(MYSQLI_ASSOC);
    $deptRes->free();
}

// จัดการคำสั่ง POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['admin_csrf'], is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '')) {
    http_response_code(403);
    exit('คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lockResult = $conn->query("SELECT GET_LOCK('reps_active_admin_guard', 5) AS acquired");
    if (!$lockResult || (int)$lockResult->fetch_assoc()['acquired'] !== 1) {
        http_response_code(503);
        exit('ระบบกำลังจัดการบัญชีผู้ดูแล กรุณาลองใหม่');
    }

    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

    // 1. เพิ่มผู้ใช้งานใหม่ (เฉพาะ admin สิทธิ์สูงสุด)
    if ($action === 'add_user') {
        if ($actorRole !== 'admin') {
            $message = 'เฉพาะบัญชีระดับ Admin เท่านั้นที่สามารถเพิ่มผู้ใช้งานได้';
            $messageType = 'error';
        } else {
            $idNumber   = trim((string)($_POST['id_number'] ?? ''));
            $userType   = trim((string)($_POST['user_type'] ?? 'student'));
            $firstName  = trim((string)($_POST['first_name'] ?? ''));
            $lastName   = trim((string)($_POST['last_name'] ?? ''));
            $deptId     = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT) ?: 0;
            $phone      = trim((string)($_POST['phone_number'] ?? ''));
            $email      = trim((string)($_POST['email'] ?? ''));
            $password   = (string)($_POST['password'] ?? '');
            $newRole    = trim((string)($_POST['role'] ?? 'user'));

            if ($idNumber === '' || strlen($idNumber) > 20) {
                $message = 'กรุณากรอกรหัสประจำตัว / ชื่อผู้ใช้ไม่เกิน 20 ตัวอักษร';
                $messageType = 'error';
            } elseif (!in_array($userType, ['student', 'teacher', 'staff'], true)) {
                $message = 'สถานภาพบุคคลไม่ถูกต้อง';
                $messageType = 'error';
            } elseif ($firstName === '' || $lastName === '') {
                $message = 'กรุณากรอกชื่อจริงและนามสกุลให้ครบถ้วน';
                $messageType = 'error';
            } elseif ($deptId <= 0) {
                $message = 'กรุณาเลือกแผนกวิชา / สังกัด';
                $messageType = 'error';
            } elseif (!preg_match('/^0\d{8,9}$/', $phone)) {
                $message = 'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง (เช่น 0812345678)';
                $messageType = 'error';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'รูปแบบอีเมลไม่ถูกต้อง';
                $messageType = 'error';
            } elseif (strlen($password) < 8) {
                $message = 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร';
                $messageType = 'error';
            } elseif (!in_array($newRole, $allowedRoles, true)) {
                $message = 'สิทธิ์ (Role) ที่เลือกไม่ถูกต้อง';
                $messageType = 'error';
            } else {
                $dupStmt = $conn->prepare('SELECT user_id FROM users WHERE id_number = ? OR email = ? LIMIT 1');
                $dupStmt->bind_param('ss', $idNumber, $email);
                $dupStmt->execute();
                $dupStmt->store_result();
                $isDup = $dupStmt->num_rows > 0;
                $dupStmt->close();

                if ($isDup) {
                    $message = 'รหัสประจำตัว / ชื่อผู้ใช้ หรืออีเมลนี้มีอยู่ในระบบแล้ว';
                    $messageType = 'error';
                } else {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $status = 'active';
                    $isActiveQueue = ($newRole === 'staff') ? 1 : 0;

                    $insStmt = $conn->prepare(
                        'INSERT INTO users (id_number, user_type, first_name, last_name, department_id, phone_number, email, password, role, status, is_active_queue)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    if ($insStmt) {
                        $insStmt->bind_param(
                            'ssssisssssi',
                            $idNumber,
                            $userType,
                            $firstName,
                            $lastName,
                            $deptId,
                            $phone,
                            $email,
                            $passwordHash,
                            $newRole,
                            $status,
                            $isActiveQueue
                        );
                        if ($insStmt->execute()) {
                            $message = "เพิ่มบัญชีผู้ใช้ {$firstName} {$lastName} ({$roleLabels[$newRole]}) เรียบร้อยแล้ว";
                        } else {
                            $message = 'เพิ่มบัญชีผู้ใช้ไม่สำเร็จ กรุณาตรวจสอบข้อมูลอีกครั้ง';
                            $messageType = 'error';
                            error_log('Admin add_user failed: ' . $insStmt->error);
                        }
                        $insStmt->close();
                    } else {
                        $message = 'เตรียมคำสั่งบันทึกข้อมูลไม่สำเร็จ';
                        $messageType = 'error';
                    }
                }
            }
        }
    } elseif ($id && $action === 'change_role') {
        $role = is_string($_POST['role'] ?? null) ? $_POST['role'] : '';
        if ($actorRole !== 'admin') {
            $message = 'เฉพาะบัญชีระดับ Admin เท่านั้นที่สามารถเปลี่ยนสิทธิ์ได้';
            $messageType = 'error';
        } elseif (!in_array($role, $allowedRoles, true)) {
            $message = 'Role ที่เลือกไม่ถูกต้อง';
            $messageType = 'error';
        } elseif ($id === $actorId) {
            $message = 'ไม่สามารถเปลี่ยน Role ของบัญชีที่กำลังใช้งานอยู่ได้';
            $messageType = 'error';
        } else {
            $targetStmt = $conn->prepare('SELECT role, status FROM users WHERE user_id = ? LIMIT 1');
            $targetStmt->bind_param('i', $id);
            $targetStmt->execute();
            $target = $targetStmt->get_result()->fetch_assoc();
            $targetStmt->close();

            if (!$target) {
                $message = 'ไม่พบผู้ใช้ที่เลือก';
                $messageType = 'error';
            } elseif ($target['role'] === $role) {
                $message = 'บัญชีนี้ใช้ Role ที่เลือกอยู่แล้ว';
            } elseif ($target['role'] === 'admin' && $target['status'] === 'active' && $role !== 'admin') {
                $activeAdmins = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'admin' AND status = 'active'");
                $activeAdminCount = $activeAdmins ? (int)$activeAdmins->fetch_assoc()['total'] : 0;
                if ($activeAdminCount <= 1) {
                    $message = 'ไม่สามารถลด Role ของ Admin ที่ใช้งานอยู่คนสุดท้ายได้';
                    $messageType = 'error';
                }
            }

            if ($message === '') {
                $updateStmt = $conn->prepare('UPDATE users SET role = ? WHERE user_id = ?');
                $updateStmt->bind_param('si', $role, $id);
                if ($updateStmt->execute()) {
                    $message = $updateStmt->affected_rows > 0 ? 'เปลี่ยน Role เรียบร้อยแล้ว' : 'Role ไม่มีการเปลี่ยนแปลง';
                } else {
                    $message = 'เปลี่ยน Role ไม่สำเร็จ';
                    $messageType = 'error';
                }
                $updateStmt->close();
            }
        }
    } elseif ($id && $action === 'change_status') {
        $status = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
        if ($actorRole !== 'admin') {
            $message = 'เฉพาะบัญชีระดับ Admin เท่านั้นที่สามารถเปลี่ยนสถานะผู้ใช้ได้';
            $messageType = 'error';
        } elseif (in_array($status, ['active', 'disabled'], true)) {
            if ($id === $actorId) {
                $message = 'ไม่สามารถเปลี่ยนสถานะบัญชีที่กำลังใช้งานอยู่ได้';
                $messageType = 'error';
            } else {
                $targetStmt = $conn->prepare('SELECT role, status FROM users WHERE user_id = ? LIMIT 1');
                $targetStmt->bind_param('i', $id);
                $targetStmt->execute();
                $target = $targetStmt->get_result()->fetch_assoc();
                $targetStmt->close();

                if (!$target) {
                    $message = 'ไม่พบผู้ใช้ที่เลือก';
                    $messageType = 'error';
                } elseif ($target['role'] === 'admin' && $target['status'] === 'active' && $status === 'disabled') {
                    $activeAdmins = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'admin' AND status = 'active'");
                    $activeAdminCount = $activeAdmins ? (int)$activeAdmins->fetch_assoc()['total'] : 0;
                    if ($activeAdminCount <= 1) {
                        $message = 'ไม่สามารถระงับ Admin ที่ใช้งานอยู่คนสุดท้ายได้';
                        $messageType = 'error';
                    }
                }

                if ($message === '') {
                    $stmt = $conn->prepare('UPDATE users SET status = ? WHERE user_id = ?');
                    $stmt->bind_param('si', $status, $id);
                    if ($stmt->execute()) {
                        $message = $stmt->affected_rows > 0 ? 'ปรับสถานะผู้ใช้แล้ว' : 'สถานะไม่มีการเปลี่ยนแปลง';
                    } else {
                        $message = 'ปรับสถานะไม่สำเร็จ';
                        $messageType = 'error';
                    }
                    $stmt->close();
                }
            }
        }
    } elseif ($id && $action === 'delete_user') {
        if ($actorRole !== 'admin') {
            $message = 'เฉพาะบัญชีระดับ Admin เท่านั้นที่สามารถลบบัญชีผู้ใช้ได้';
            $messageType = 'error';
        } elseif ($id === $actorId) {
            $message = 'ไม่สามารถลบบัญชีที่กำลังใช้งานอยู่ได้';
            $messageType = 'error';
        } else {
            $targetStmt = $conn->prepare('SELECT role, status, id_number FROM users WHERE user_id = ? LIMIT 1');
            $targetStmt->bind_param('i', $id);
            $targetStmt->execute();
            $target = $targetStmt->get_result()->fetch_assoc();
            $targetStmt->close();

            if (!$target) {
                $message = 'ไม่พบผู้ใช้ที่เลือก';
                $messageType = 'error';
            } else {
                $confirmIdNumber = trim(is_string($_POST['confirm_id_number'] ?? null) ? $_POST['confirm_id_number'] : '');
                if (!hash_equals(app_confirm_normalize((string)$target['id_number']), app_confirm_normalize($confirmIdNumber))) {
                    $message = 'รหัสประจำตัวที่พิมพ์ไม่ตรงกับบัญชีนี้ การลบไม่ถูกดำเนินการ';
                    $messageType = 'error';
                } else {
                    $referenceStmt = $conn->prepare(
                        'SELECT
                            (SELECT COUNT(*) FROM tickets WHERE requester_id = ?) AS requester_tickets,
                            (SELECT COUNT(*) FROM tickets WHERE assigned_to = ?) AS assigned_tickets,
                            (SELECT COUNT(*) FROM ticket_logs WHERE action_by = ?) AS action_logs'
                    );
                    $referenceStmt->bind_param('iii', $id, $id, $id);
                    $referenceStmt->execute();
                    $references = $referenceStmt->get_result()->fetch_assoc();
                    $referenceStmt->close();

                    if (!$references || (int)$references['requester_tickets'] + (int)$references['assigned_tickets'] + (int)$references['action_logs'] > 0) {
                        $message = 'ลบไม่ได้ เนื่องจากบัญชีนี้มีใบงานหรือประวัติที่ระบบต้องเก็บไว้';
                        $messageType = 'error';
                    } elseif ($target['role'] === 'admin' && $target['status'] === 'active') {
                        $activeAdmins = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'admin' AND status = 'active'");
                        $activeAdminCount = $activeAdmins ? (int)$activeAdmins->fetch_assoc()['total'] : 0;
                        if ($activeAdminCount <= 1) {
                            $message = 'ไม่สามารถลบ Admin ที่ใช้งานอยู่คนสุดท้ายได้';
                            $messageType = 'error';
                        }
                    }

                    if ($message === '') {
                        $deleteStmt = $conn->prepare('DELETE FROM users WHERE user_id = ?');
                        $deleteStmt->bind_param('i', $id);
                        if ($deleteStmt->execute() && $deleteStmt->affected_rows === 1) {
                            $message = 'ลบบัญชีผู้ใช้เรียบร้อยแล้ว';
                        } else {
                            $message = 'ลบบัญชีผู้ใช้ไม่สำเร็จ';
                            $messageType = 'error';
                        }
                        $deleteStmt->close();
                    }
                }
            }
        }
    }

    $conn->query("SELECT RELEASE_LOCK('reps_active_admin_guard')");
    $_SESSION['admin_flash'] = ['message' => $message, 'type' => $messageType];
    $returnQuery = is_string($_POST['return_q'] ?? null) ? trim($_POST['return_q']) : '';
    $returnStatus = is_string($_POST['return_status'] ?? null) ? $_POST['return_status'] : '';
    $returnParams = [];
    if ($returnQuery !== '') $returnParams['q'] = $returnQuery;
    if (in_array($returnStatus, ['active', 'disabled'], true)) $returnParams['status'] = $returnStatus;
    $redirect = 'admin_manage_user.php' . ($returnParams ? '?' . http_build_query($returnParams) : '');
    header('Location: ' . $redirect, true, 303);
    exit;
}

// ตัวกรองค้นหาและสถานะ (GET)
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$statusFilter = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.id_number LIKE ? OR d.name LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term);
    $types .= 'sssss';
}

if (in_array($statusFilter, ['active', 'disabled'], true)) {
    $where[] = 'u.status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}

$users = [];
$stats = [];

$sql = 'SELECT u.user_id, u.id_number, u.user_type, u.first_name, u.last_name, d.name AS department_name, u.phone_number, u.email, u.status, u.role, u.created_at 
        FROM users u
        LEFT JOIN department d ON d.d_id = u.department_id
        ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . ' 
        ORDER BY u.created_at DESC';

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $loadError = 'โหลดข้อมูลผู้ใช้ไม่ได้ กรุณาตรวจสอบฐานข้อมูล';
}

$statQuery = $conn->query('SELECT status, COUNT(*) AS total FROM users GROUP BY status');
if ($statQuery) {
    while ($row = $statQuery->fetch_assoc()) {
        $stats[$row['status']] = $row['total'];
    }
    $statQuery->free();
}

if (!function_exists('h')) {
    function h(mixed $v): string
    {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

require_once __DIR__ . '/admin_header.php';
?>

<style>
    /* ป้าย Role สีดำสำหรับ Admin และ Staff */
    .admin-chip.role-dark {
    background-color: #000000 !important;
    color: #FFFFFF !important;
    border-color: #161616 !important;
    font-weight: 500;
}
   .admin-chip.role-pink {
    background-color: #636363 !important;
    color: #FFFFFF !important;
    border-color: #3e373a !important;
    font-weight: 500;
}
</style>

<div class="admin-page-head flex flex-col sm:flex-row sm:items-end justify-between gap-4">
    <div>
        <nav class="admin-crumb">
            <a href="admin_dashboard.php">Dashboard</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>จัดการผู้ใช้</span>
        </nav>
        <h1 class="admin-page-title">จัดการผู้ใช้</h1>
        <p class="admin-page-sub">เพิ่มบัญชีผู้ใช้ใหม่ จัดการสถานะการเข้าใช้งาน และกำหนดสิทธิ์ (Admin สิทธิ์สูงสุด / Staff / User)</p>
    </div>

    <?php if ($actorRole === 'admin'): ?>
        <button type="button" id="openAddUserModalBtn" class="admin-btn inline-flex items-center gap-2 self-start sm:self-auto cursor-pointer">
            <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
            <span>เพิ่มผู้ใช้งาน</span>
        </button>
    <?php endif; ?>
</div>

<?php if ($message): ?>
    <div class="mb-4 rounded-lg border p-3 text-sm <?= $messageType === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-blue-100 bg-blue-50 text-blue-800' ?>" role="status">
        <?= h($message) ?>
    </div>
<?php endif; ?>

<?php if (isset($loadError)): ?>
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
        <?= h($loadError) ?>
    </div>
<?php endif; ?>

<!-- การ์ดแสดงสถิติสถานะ -->
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
    <?php foreach (['active' => 'ใช้งานได้', 'disabled' => 'ระงับใช้งาน'] as $key => $label): ?>
        <div class="admin-stat st-<?= h($key) ?>">
            <div class="admin-stat-label"><?= h($label) ?></div>
            <div class="admin-stat-value"><?= number_format((int)($stats[$key] ?? 0)) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<!-- ตารางรายชื่อผู้ใช้และแถบค้นหา -->
<div class="admin-tray">
    <div class="admin-tray-label">
        <span>User Directory</span>
        <span><?= count($users) ?> บัญชี</span>
    </div>
    <section class="admin-card">
        <form method="get" class="admin-toolbar">
            <input name="q" value="<?= h($search) ?>" placeholder="ค้นหาชื่อ รหัส อีเมล หรือแผนก" class="min-w-[220px] flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-gray-900">
            <select name="status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-gray-900">
                <option value="">ทุกสถานะ</option>
                <?php foreach (['active' => 'ใช้งานได้', 'disabled' => 'ระงับใช้งาน'] as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <div class="admin-toolbar-actions">
                <button class="admin-btn">ค้นหา</button>
                <a href="admin_manage_user.php" class="admin-reset-link">ล้าง</a>
            </div>
        </form>

        <div class="overflow-x-auto admin-table-wrap">
            <table class="w-full min-w-[1000px] text-left text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">ชื่อผู้ใช้</th>
                        <th class="px-4 py-3 font-medium">รหัส / ประเภท</th>
                        <th class="px-4 py-3 font-medium">แผนก / ติดต่อ</th>
                        <th class="px-4 py-3 font-medium">สมัครเมื่อ</th>
                        <th class="px-4 py-3 font-medium">Role</th>
                        <th class="px-4 py-3 font-medium">สถานะ</th>
                        <th class="px-4 py-3 font-medium">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (!$users): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-gray-500">ไม่พบผู้ใช้</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($users as $u):
                      $roleChipClass = [
                     'admin' => 'role-admin role-dark',
                     'staff' => 'role-staff role-pink',
                     ][$u['role']] ?? '';
                    ?>
                        <tr class="hover:bg-gray-50/70 transition-colors">
                            <td class="px-4 py-4">
                                <div class="font-medium text-gray-900"><?= h($u['first_name'] . ' ' . $u['last_name']) ?></div>
                                <div class="text-xs text-gray-500"><?= h($u['email']) ?></div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="text-gray-900 font-mono text-xs"><?= h($u['id_number']) ?></div>
                                <div class="text-xs text-gray-500"><?= ['student' => 'นักศึกษา', 'teacher' => 'อาจารย์', 'staff' => 'บุคลากร'][$u['user_type']] ?? h($u['user_type']) ?></div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="text-gray-900"><?= h($u['department_name'] ?? 'ไม่ระบุแผนก') ?></div>
                                <div class="text-xs text-gray-500"><?= h($u['phone_number']) ?></div>
                            </td>
                            <td class="px-4 py-4 text-xs text-gray-600">
                                <?= h(date('d/m/Y', strtotime($u['created_at']))) ?>
                            </td>
                            <td class="px-4 py-4">
                              <span class="admin-chip <?= $roleChipClass ?>">                                    
                              <?= $roleLabels[$u['role']] ?? h($u['role']) ?>
                              </span>
                            </td>
                            <td class="px-4 py-4">
                                <span class="admin-chip st-<?= h($u['status']) ?>">
                                    <?= ['active' => 'ใช้งานได้', 'disabled' => 'ระงับใช้งาน'][$u['status']] ?? h($u['status']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex w-fit flex-wrap items-center gap-1.5">
                                    <a href="admin_edit_user.php?user_id=<?= (int)$u['user_id'] ?>" class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:border-gray-500 hover:bg-gray-50 transition-colors" title="แก้ไขข้อมูลผู้ใช้">
                                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                    </a>

                                    <form method="post" class="inline-flex">
                                        <input type="hidden" name="csrf" value="<?= h($_SESSION['admin_csrf']) ?>">
                                        <input type="hidden" name="return_q" value="<?= h($search) ?>">
                                        <input type="hidden" name="return_status" value="<?= h($statusFilter) ?>">
                                        <input type="hidden" name="action" value="change_status">
                                        <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                                        <input type="hidden" name="status" value="<?= $u['status'] === 'active' ? 'disabled' : 'active' ?>">
                                        <?php if ($u['status'] === 'active'): ?>
                                            <button class="inline-flex items-center gap-1.5 rounded-md border border-amber-200 px-2.5 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50 transition-colors disabled:opacity-40" onclick="return confirm('ยืนยันการระงับบัญชีนี้หรือไม่?')" <?= (int)$u['user_id'] === $actorId ? 'disabled' : '' ?> title="ระงับการใช้งาน">
                                                <i class="fa-solid fa-user-slash" aria-hidden="true"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="inline-flex items-center gap-1.5 rounded-md border border-emerald-200 px-2.5 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50 transition-colors disabled:opacity-40" onclick="return confirm('ยืนยันการเปิดใช้งานบัญชีนี้หรือไม่?')" <?= (int)$u['user_id'] === $actorId ? 'disabled' : '' ?>>
                                                <i class="fa-solid fa-user-check" aria-hidden="true"></i> เปิดใช้งาน
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                                <button type="button" data-open-user-delete
                                    data-user-id="<?= (int)$u['user_id'] ?>"
                                    data-id-number="<?= h($u['id_number']) ?>"
                                    data-user-name="<?= h($u['first_name'] . ' ' . $u['last_name']) ?>"
                                    class="mt-2 inline-flex items-center gap-1.5 rounded-md border border-red-200 px-2.5 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 transition-colors disabled:opacity-40 focus:outline-none focus:ring-2 focus:ring-red-400" <?= (int)$u['user_id'] === $actorId ? 'disabled' : '' ?>>
                                    <i class="fa-regular fa-trash-can" aria-hidden="true"></i> ลบผู้ใช้
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="border-t px-5 py-3 text-xs text-gray-500">แสดง <?= count($users) ?> บัญชี</div>
    </section>
</div>

<!-- Modal เพิ่มผู้ใช้งานใหม่ -->
<dialog id="addUserModal" aria-labelledby="addUserTitle" class="m-auto w-[min(540px,calc(100%-2rem))] overflow-hidden rounded-2xl border border-gray-200 bg-white p-0 shadow-2xl backdrop:bg-gray-950/40 backdrop:backdrop-blur-sm">
    <form method="post" id="addUserForm">
        <input type="hidden" name="csrf" value="<?= h($_SESSION['admin_csrf']) ?>">
        <input type="hidden" name="return_q" value="<?= h($search) ?>">
        <input type="hidden" name="return_status" value="<?= h($statusFilter) ?>">
        <input type="hidden" name="action" value="add_user">

        <div class="flex items-start justify-between gap-4 border-b border-gray-100 bg-gray-50/80 px-5 py-4 sm:px-6">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-900 text-white shadow-sm">
                    <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                </span>
                <div>
                    <p class="text-xs font-medium text-gray-500">User Management</p>
                    <h2 id="addUserTitle" class="mt-0.5 font-semibold text-gray-950">เพิ่มผู้ใช้งานใหม่</h2>
                </div>
            </div>
            <button type="button" data-close-add-user aria-label="ปิด" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-200/70 hover:text-gray-900">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 px-5 py-5 sm:px-6 text-sm">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">รหัสประจำตัว / ชื่อผู้ใช้ <span class="text-red-500">*</span></label>
                <input type="text" name="id_number" required maxlength="20" placeholder="เช่น admin01 หรือ 6620901001"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900">
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">สิทธิ์การใช้งาน (Role) <span class="text-red-500">*</span></label>
                <select name="role" required class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-gray-900">
                    <option value="user">User (ผู้แจ้งซ่อมทั่วไป)</option>
                    <option value="staff">Staff (เจ้าหน้าที่ฝ่ายซ่อม)</option>
                    <option value="admin">Admin (สิทธิ์สูงสุด)</option>
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">ชื่อจริง <span class="text-red-500">*</span></label>
                <input type="text" name="first_name" required placeholder="กรอกชื่อจริง"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900">
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">นามสกุล <span class="text-red-500">*</span></label>
                <input type="text" name="last_name" required placeholder="กรอกนามสกุล"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900">
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">สถานภาพบุคคล <span class="text-red-500">*</span></label>
                <select name="user_type" required class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-gray-900">
                    <option value="student">นักศึกษา</option>
                    <option value="teacher">อาจารย์ / ครู</option>
                    <option value="staff">บุคลากร / เจ้าหน้าที่</option>
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">แผนกวิชา / สังกัด <span class="text-red-500">*</span></label>
                <select name="department_id" required class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-gray-900">
                    <option value="">-- เลือกแผนก --</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= (int)$dept['d_id'] ?>"><?= h($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">เบอร์โทรศัพท์ <span class="text-red-500">*</span></label>
                <input type="tel" name="phone_number" required pattern="0[0-9]{8,9}" placeholder="0812345678"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900">
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">อีเมล <span class="text-red-500">*</span></label>
                <input type="email" name="email" required placeholder="user@example.com"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900">
            </div>

            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-medium text-gray-700">รหัสผ่านเริ่มต้น (อย่างน้อย 8 ตัวอักษร) <span class="text-red-500">*</span></label>
                <input type="password" name="password" required minlength="8" placeholder="กำหนดรหัสผ่านอย่างน้อย 8 ตัวอักษร"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900">
            </div>
        </div>

        <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50/70 px-5 py-3.5 sm:px-6">
            <button type="button" data-close-add-user class="rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50">ยกเลิก</button>
            <button type="submit" class="admin-btn inline-flex items-center gap-2 cursor-pointer">
                <i class="fa-solid fa-check" aria-hidden="true"></i> บันทึกผู้ใช้งาน
            </button>
        </div>
    </form>
</dialog>

<!-- Modal ยืนยันการลบบัญชี (ต้องพิมพ์รหัสประจำตัว) -->
<dialog id="userDeleteModal" aria-labelledby="userDeleteTitle" class="m-auto w-[min(460px,calc(100%-2rem))] overflow-hidden rounded-2xl border border-red-100 bg-white p-0 shadow-2xl backdrop:bg-gray-950/40 backdrop:backdrop-blur-sm">
    <form method="post" id="userDeleteForm">
        <input type="hidden" name="csrf" value="<?= h($_SESSION['admin_csrf']) ?>">
        <input type="hidden" name="action" value="delete_user">
        <input type="hidden" name="user_id" id="userDeleteUserId">
        <div class="flex items-start justify-between gap-4 border-b border-red-100 bg-gradient-to-br from-red-50 to-white px-5 py-4 sm:px-6">
            <div class="flex min-w-0 items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-600 text-white shadow-sm"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-red-600">การดำเนินการที่ย้อนกลับไม่ได้</p>
                    <h2 id="userDeleteTitle" class="mt-0.5 font-semibold text-gray-950">ยืนยันการลบบัญชี</h2>
                </div>
            </div>
            <button type="button" data-close-user-delete aria-label="ปิด" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-900"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="space-y-4 px-5 py-5 sm:px-6">
            <p class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                กำลังจะลบบัญชี <strong id="userDeleteName" class="break-all"></strong>
                <br>รหัสประจำตัว <span class="font-mono font-semibold" id="userDeleteIdNumber"></span>
            </p>
            <div>
                <label for="userDeleteConfirmInput" class="mb-1.5 block text-sm font-medium text-gray-800">พิมพ์รหัสประจำตัวเพื่อยืนยันการลบ</label>
                <input type="text" id="userDeleteConfirmInput" name="confirm_id_number" autocomplete="off" spellcheck="false" autocapitalize="off" autocorrect="off" placeholder="รหัสประจำตัวของบัญชีนี้"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 font-mono text-sm outline-none transition placeholder:font-kanit placeholder:text-gray-400 focus:border-red-600 focus:ring-2 focus:ring-red-600/10">
                <p id="userDeleteHint" class="mt-1.5 text-xs text-gray-500">บัญชีที่มีใบงานหรือประวัติจะลบไม่ได้ ยังไม่ต้องสนใจตัวพิมพ์เล็ก-ใหญ่และช่องว่าง</p>
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50/70 px-5 py-3.5 sm:px-6">
            <button type="button" data-close-user-delete class="rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50">ปิด</button>
            <button type="submit" id="userDeleteSubmit" disabled class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition enabled:hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-40 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">
                <i class="fa-regular fa-trash-can" aria-hidden="true"></i> ยืนยันลบบัญชี
            </button>
        </div>
    </form>
</dialog>

<script>
    (function () {
        // Modal เพิ่มผู้ใช้งาน
        const addModal = document.getElementById('addUserModal');
        const openAddBtn = document.getElementById('openAddUserModalBtn');
        if (addModal && openAddBtn) {
            openAddBtn.addEventListener('click', function () {
                addModal.showModal();
            });
            addModal.querySelectorAll('[data-close-add-user]').forEach(function (btn) {
                btn.addEventListener('click', function () { addModal.close(); });
            });
            addModal.addEventListener('click', function (e) {
                if (e.target === addModal) addModal.close();
            });
        }

        // Modal ลบบัญชี
        const modal = document.getElementById('userDeleteModal');
        const form = document.getElementById('userDeleteForm');
        const confirmInput = document.getElementById('userDeleteConfirmInput');
        const submitButton = document.getElementById('userDeleteSubmit');
        const hint = document.getElementById('userDeleteHint');
        const hintDefault = hint ? hint.textContent : '';
        const idSpan = document.getElementById('userDeleteIdNumber');
        let expected = '';

        function normalize(value) {
            return String(value || '').replace(/\s+/g, '').toUpperCase();
        }

        function refreshConfirmState() {
            const typed = normalize(confirmInput.value);
            const match = expected !== '' && typed !== '' && typed === normalize(expected);
            submitButton.disabled = !match;
            if (hint) {
                if (confirmInput.value.trim() === '') {
                    hint.textContent = hintDefault;
                    hint.className = 'mt-1.5 text-xs text-gray-500';
                } else if (match) {
                    hint.textContent = '✓ รหัสตรงกันแล้ว กด "ยืนยันลบบัญชี" เพื่อทำรายการ';
                    hint.className = 'mt-1.5 text-xs font-medium text-emerald-600';
                } else {
                    hint.textContent = '✗ รหัสที่พิมพ์ยังไม่ตรงกัน กรุณาตรวจสอบอีกครั้ง';
                    hint.className = 'mt-1.5 text-xs font-medium text-amber-600';
                }
            }
        }

        document.querySelectorAll('[data-open-user-delete]').forEach(function (button) {
            button.addEventListener('click', function () {
                document.getElementById('userDeleteUserId').value = button.dataset.userId;
                document.getElementById('userDeleteName').textContent = button.dataset.userName;
                expected = String(button.dataset.idNumber || '');
                idSpan.textContent = expected;
                confirmInput.value = '';
                refreshConfirmState();
                modal.showModal();
                confirmInput.focus();
            });
        });

        ['input', 'change', 'keyup', 'paste', 'cut'].forEach(function (type) {
            confirmInput.addEventListener(type, function () { refreshConfirmState(); });
        });

        form.addEventListener('submit', function (event) {
            if (normalize(confirmInput.value) !== normalize(expected)) {
                event.preventDefault();
                refreshConfirmState();
                hint && (hint.textContent = '✗ รหัสประจำตัวไม่ตรง จึงยังลบบัญชีไม่ได้');
                return;
            }
            submitButton.disabled = true;
        });

        modal.querySelectorAll('[data-close-user-delete]').forEach(function (button) {
            button.addEventListener('click', function () { modal.close(); });
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