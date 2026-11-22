<?php
declare(strict_types=1);

$page_title = 'จัดการผู้ใช้';
$current_page = 'users';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/user_admin.php';

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
$userStatusLabels = ['pending' => 'รออนุมัติ', 'active' => 'ใช้งานได้', 'disabled' => 'ระงับใช้งาน', 'rejected' => 'ปฏิเสธการสมัคร'];

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
                            audit_user($conn, (int)$insStmt->insert_id, $actorId, "เพิ่มบัญชี {$idNumber} {$firstName} {$lastName} role {$newRole}");
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
                    if ($updateStmt->affected_rows > 0) {
                        audit_user($conn, $id, $actorId, 'เปลี่ยน role: ' . $target['role'] . ' → ' . $role);
                        if ($role !== 'staff') {
                            $conn->query('UPDATE users SET is_active_queue = 0 WHERE user_id = ' . (int)$id);
                            $conn->query("UPDATE tickets SET queue_assigned_to = NULL, queue_expires_at = NULL WHERE status = 'pending' AND queue_assigned_to = " . (int)$id);
                        }
                    }
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
                } elseif ($target['status'] === 'pending') {
                    $message = 'บัญชีนี้รออนุมัติอยู่ กรุณาใช้ปุ่มอนุมัติหรือปฏิเสธ';
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
                        if ($stmt->affected_rows > 0) {
                            audit_user($conn, $id, $actorId, 'เปลี่ยนสถานะบัญชี: ' . $target['status'] . ' → ' . $status);
                            if ($status === 'disabled') {
                                // บัญชีถูกระงับ: ถอดออกจากคิวและคืนงานที่รอรับให้เป็นคิวกลาง
                                $conn->query('UPDATE users SET is_active_queue = 0 WHERE user_id = ' . (int)$id);
                                $conn->query("UPDATE tickets SET queue_assigned_to = NULL, queue_expires_at = NULL WHERE status = 'pending' AND queue_assigned_to = " . (int)$id);
                            }
                        }
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
                    try {
                        $referenceCount = user_reference_count($conn, $id);
                    } catch (Throwable $e) {
                        error_log('User reference check failed: ' . $e->getMessage());
                        $referenceCount = -1;
                    }

                    if ($referenceCount !== 0) {
                        $message = $referenceCount < 0
                            ? 'ตรวจสอบข้อมูลที่เกี่ยวข้องไม่สำเร็จ'
                            : 'ลบไม่ได้ เนื่องจากบัญชีนี้มีใบงานหรือประวัติที่ระบบต้องเก็บไว้ (ใช้การระงับบัญชีแทน)';
                        $messageType = 'error';
                    } elseif ($target['role'] === 'admin' && $target['status'] === 'active' && user_active_admin_count($conn) <= 1) {
                        $message = 'ไม่สามารถลบ Admin ที่ใช้งานอยู่คนสุดท้ายได้';
                        $messageType = 'error';
                    }

                    if ($message === '') {
                        $avatarBeforeDelete = user_avatar_path($conn, $id);
                        $deleteStmt = $conn->prepare('DELETE FROM users WHERE user_id = ?');
                        $deleteStmt->bind_param('i', $id);
                        if ($deleteStmt->execute() && $deleteStmt->affected_rows === 1) {
                            user_delete_private_files($avatarBeforeDelete);
                            audit_user($conn, null, $actorId, 'ลบบัญชี #' . $id . ' ' . $target['id_number'] . ' (role ' . $target['role'] . ')');
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
    } elseif ($action === 'approve_selected') {
        // อนุมัติหลายบัญชีพร้อมกัน (ช่วงเปิดภาคเรียนที่มีผู้สมัครจำนวนมาก)
        $ids = array_values(array_unique(array_filter(array_map('intval', is_array($_POST['user_ids'] ?? null) ? $_POST['user_ids'] : []), static fn($v) => $v > 0)));
        $approved = 0;
        foreach (array_slice($ids, 0, 200) as $bulkId) {
            $target = db_one($conn, "SELECT user_id, id_number, first_name, last_name FROM users WHERE user_id = ? AND status = 'pending'", 'i', [$bulkId]);
            if (!$target) continue;
            if (db_exec($conn, "UPDATE users SET status = 'active', approved_by = ?, approved_at = NOW(), reject_reason = NULL WHERE user_id = ? AND status = 'pending'", 'ii', [$actorId, $bulkId]) === 1) {
                audit_user($conn, $bulkId, $actorId, 'อนุมัติการสมัคร (แบบกลุ่ม) ' . user_audit_label($target));
                $approved++;
            }
        }
        $message = $approved > 0 ? "อนุมัติแล้ว {$approved} บัญชี" : 'ยังไม่ได้เลือกบัญชีที่จะอนุมัติ';
        $messageType = $approved > 0 ? 'success' : 'error';
    } elseif ($id && in_array($action, ['approve_user', 'reject_user'], true)) {
        // 3.7 อนุมัติ / ปฏิเสธผู้สมัครใหม่
        $rejectReason = trim(is_string($_POST['reject_reason'] ?? null) ? $_POST['reject_reason'] : '');
        $target = db_one($conn, 'SELECT user_id, id_number, first_name, last_name, email, status FROM users WHERE user_id = ?', 'i', [$id]);
        if (!$target || $target['status'] !== 'pending') {
            $message = 'บัญชีนี้ไม่ได้อยู่ในสถานะรออนุมัติ';
            $messageType = 'error';
        } elseif ($action === 'reject_user' && mb_strlen($rejectReason) > 255) {
            $message = 'เหตุผลต้องไม่เกิน 255 ตัวอักษร';
            $messageType = 'error';
        } else {
            $newStatus = $action === 'approve_user' ? 'active' : 'rejected';
            $reasonVal = $action === 'reject_user' && $rejectReason !== '' ? $rejectReason : null;
            db_exec(
                $conn,
                "UPDATE users SET status = ?, approved_by = ?, approved_at = NOW(), reject_reason = ? WHERE user_id = ? AND status = 'pending'",
                'sisi',
                [$newStatus, $actorId, $reasonVal, $id]
            );
            audit_user($conn, $id, $actorId, ($newStatus === 'active' ? 'อนุมัติการสมัคร ' : 'ปฏิเสธการสมัคร ') . user_audit_label($target) . ($reasonVal ? ' เหตุผล: ' . $reasonVal : ''));
            $message = ($newStatus === 'active' ? 'อนุมัติบัญชี ' : 'ปฏิเสธบัญชี ') . $target['first_name'] . ' ' . $target['last_name'] . ' แล้ว';

            // ตัวเลือกเสริม: แจ้งผลทางอีเมล (ส่งไม่ได้ก็ไม่กระทบการอนุมัติ)
            require_once __DIR__ . '/../includes/mailer.php';
            if (app_mail_is_configured()) {
                try {
                    $body = 'สวัสดี ' . $target['first_name'] . ",\n\n"
                        . ($newStatus === 'active'
                            ? "บัญชี REPS ของคุณได้รับการอนุมัติแล้ว สามารถเข้าสู่ระบบด้วยชื่อผู้ใช้ {$target['id_number']} ได้ทันที\n"
                            : "การสมัครบัญชี REPS ของคุณไม่ได้รับการอนุมัติ" . ($reasonVal ? "\nเหตุผล: {$reasonVal}" : '') . "\n");
                    app_mail_send($target['email'], 'ผลการสมัครบัญชี REPS', $body);
                    $message .= ' และส่งอีเมลแจ้งผลแล้ว';
                } catch (Throwable $e) {
                    error_log('Approval email failed: ' . $e->getMessage());
                    $message .= ' (ส่งอีเมลแจ้งผลไม่สำเร็จ)';
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
    if (isset($userStatusLabels[$returnStatus])) $returnParams['status'] = $returnStatus;
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

if (isset($userStatusLabels[$statusFilter])) {
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

$pendingUsers = [];
try {
    $pendingUsers = db_all(
        $conn,
        "SELECT u.user_id, u.id_number, u.user_type, u.first_name, u.last_name, u.email, u.phone_number, u.created_at, d.name AS department_name
           FROM users u LEFT JOIN department d ON d.d_id = u.department_id
          WHERE u.status = 'pending' ORDER BY u.created_at ASC"
    );
} catch (Throwable $e) {
    error_log('Pending users query failed: ' . $e->getMessage());
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
            <a href="admin_dashboard.php"><?= tr('แดชบอร์ด', 'Dashboard') ?></a>
            <i class="fa-solid fa-chevron-right"></i>
            <span><?= tr('จัดการผู้ใช้', 'Users') ?></span>
        </nav>
        <h1 class="admin-page-title"><?= tr('จัดการผู้ใช้', 'Users') ?></h1>
        <p class="admin-page-sub"><?= tr('เพิ่มบัญชีผู้ใช้ใหม่ จัดการสถานะการเข้าใช้งาน และกำหนดสิทธิ์ (Admin สิทธิ์สูงสุด / Staff / User)', 'Add accounts, manage access status and assign roles (Admin has full access / Staff / User)') ?></p>
    </div>

    <?php if ($actorRole === 'admin'): ?>
        <button type="button" id="openAddUserModalBtn" class="admin-btn inline-flex items-center gap-2 self-start sm:self-auto cursor-pointer">
            <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
            <span><?= tr('เพิ่มผู้ใช้งาน', 'Add user') ?></span>
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
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <?php foreach ($userStatusLabels as $key => $label): ?>
        <a href="?status=<?= h($key) ?>" class="admin-stat st-<?= h($key) ?> <?= $statusFilter === $key ? 'is-active' : '' ?>">
            <div class="admin-stat-label"><?= h($label) ?></div>
            <div class="admin-stat-value"><?= number_format((int)($stats[$key] ?? 0)) ?></div>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($pendingUsers): ?>
<!-- 3.7 ผู้สมัครรออนุมัติ -->
<style>
    .pu-card { margin-bottom: 24px; overflow: hidden; border: 1px solid #E7E4DF; border-radius: 16px; background: #fff; }
    .pu-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 20px; border-bottom: 1px solid #EFEDE9; }
    .pu-title { margin: 0; font-size: 16px; font-weight: 600; color: #161616; }
    .pu-sub { margin-top: 2px; font-size: 13px; color: #6B6862; }
    .pu-bulk { display: flex; align-items: center; gap: 10px; font-size: 13px; color: #57544F; }
    .pu-table { width: 100%; table-layout: fixed; border-collapse: collapse; font-size: 14px; }
    .pu-table th { padding: 10px 16px; background: #FAFAF8; border-bottom: 1px solid #EFEDE9; text-align: left; font-size: 13px; font-weight: 500; color: #6B6862; white-space: nowrap; }
    .pu-table td { padding: 12px 16px; border-bottom: 1px solid #F1EFEC; vertical-align: middle; }
    .pu-table tr:last-child td { border-bottom: 0; }
    .pu-table tbody tr:hover td { background: #FCFBFA; }
    .pu-table tr:has(input[data-select-row]:checked) td { background: #F7F6F4; }
    .pu-truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pu-muted { margin-top: 1px; font-size: 13px; color: #6B6862; }
    .pu-check { width: 18px; height: 18px; accent-color: #161616; cursor: pointer; }
    .pu-actions { display: flex; justify-content: flex-end; gap: 8px; }
    .pu-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 36px; padding: 0 14px; border: 1px solid #D4D0C9; border-radius: 9px; background: #fff; color: #161616; font-size: 13px; font-weight: 500; white-space: nowrap; cursor: pointer; }
    .pu-btn:hover { background: #F5F4F1; }
    .pu-btn--approve { border-color: var(--theme-primary, #161616); background: var(--theme-primary, #161616); color: #fff; }
    .pu-btn--approve:hover { background: var(--theme-hover, #000); border-color: var(--theme-hover, #000); }
    .pu-btn--reject { color: #B91C1C; }
    .pu-btn--reject:hover { background: #FEF2F2; border-color: #FECACA; }
    .pu-btn--dark { border-color: var(--theme-primary, #161616); background: var(--theme-primary, #161616); color: #fff; }
    .pu-btn--dark:hover { background: var(--theme-hover, #000); }
    .pu-btn:disabled { opacity: .45; cursor: not-allowed; }
    .pu-dialog { width: min(440px, calc(100% - 2rem)); margin: auto; padding: 0; border: 1px solid #E7E4DF; border-radius: 16px; box-shadow: 0 24px 60px rgba(22, 22, 22, .2); }
    .pu-dialog::backdrop { background: rgba(22, 22, 22, .45); }
    .pu-dialog textarea { width: 100%; min-height: 90px; padding: 10px 12px; border: 1px solid #D4D0C9; border-radius: 10px; font-size: 14px; resize: vertical; }
    .pu-chip { min-height: 32px; padding: 0 12px; border: 1px solid #D4D0C9; border-radius: 999px; background: #fff; font-size: 13px; color: #3F3D39; cursor: pointer; }
    .pu-chip:hover { border-color: #161616; }
    @media (max-width: 900px) {
        .pu-table thead { display: none; }
        .pu-table, .pu-table tbody, .pu-table tr, .pu-table td { display: block; width: 100%; }
        .pu-table tr { position: relative; padding: 12px 16px 12px 48px; border-bottom: 1px solid #EFEDE9; }
        .pu-table td { padding: 2px 0; border: 0; }
        .pu-table td:first-child { position: absolute; left: 16px; top: 16px; width: auto; }
        .pu-actions { justify-content: flex-start; padding-top: 8px; }
        .pu-btn { min-height: 44px; }
    }
</style>
<section class="pu-card" aria-labelledby="pendingTitle">
    <div class="pu-head">
        <div>
            <h2 id="pendingTitle" class="pu-title">ผู้สมัครรออนุมัติ <span class="font-normal text-gray-500">· <?= count($pendingUsers) ?> บัญชี</span></h2>
            <div class="pu-sub">ตรวจชื่อ รหัส และแผนกให้ตรงกับทะเบียนก่อนอนุมัติ</div>
        </div>
        <form method="post" id="bulkApproveForm" class="pu-bulk" onsubmit="return confirm('ยืนยันอนุมัติบัญชีที่เลือกทั้งหมด?')">
            <input type="hidden" name="csrf" value="<?= h($_SESSION['admin_csrf']) ?>">
            <input type="hidden" name="action" value="approve_selected">
            <span data-selected-count aria-live="polite">ยังไม่ได้เลือก</span>
            <button class="pu-btn pu-btn--dark" data-bulk-submit disabled><i class="fa-solid fa-user-check" aria-hidden="true"></i> อนุมัติที่เลือก</button>
        </form>
    </div>
    <table class="pu-table" data-pager id="pendingUsers">
        <colgroup>
            <col style="width: 52px">
            <col style="width: 27%">
            <col style="width: 15%">
            <col style="width: 22%">
            <col style="width: 13%">
            <col style="width: 210px">
        </colgroup>
        <thead>
            <tr>
                <th scope="col"><input type="checkbox" class="pu-check" form="bulkApproveForm" data-select-all aria-label="เลือกทั้งหมด"></th>
                <th scope="col">ชื่อ / อีเมล</th>
                <th scope="col">รหัส / สถานภาพ</th>
                <th scope="col">แผนก / เบอร์โทร</th>
                <th scope="col">สมัครเมื่อ</th>
                <th scope="col" style="text-align:right">พิจารณา</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pendingUsers as $p):
                $pName = $p['first_name'] . ' ' . $p['last_name']; ?>
                <tr>
                    <td><input type="checkbox" class="pu-check" form="bulkApproveForm" name="user_ids[]" value="<?= (int)$p['user_id'] ?>" data-select-row aria-label="เลือก <?= h($pName) ?>"></td>
                    <td>
                        <div class="pu-truncate font-medium" title="<?= h($pName) ?>"><?= h($pName) ?></div>
                        <div class="pu-muted pu-truncate" title="<?= h($p['email']) ?>"><?= h($p['email']) ?></div>
                    </td>
                    <td>
                        <div class="pu-truncate"><?= h($p['id_number']) ?></div>
                        <div class="pu-muted"><?= h(['student' => 'นักศึกษา', 'teacher' => 'ครู/อาจารย์', 'staff' => 'บุคลากร'][$p['user_type']] ?? $p['user_type']) ?></div>
                    </td>
                    <td>
                        <div class="pu-truncate" title="<?= h($p['department_name'] ?? '') ?>"><?= h($p['department_name'] ?? 'ไม่ระบุแผนก') ?></div>
                        <div class="pu-muted"><?= h($p['phone_number']) ?></div>
                    </td>
                    <td>
                        <div><?= h(date('d/m/Y', strtotime($p['created_at']))) ?></div>
                        <div class="pu-muted"><?= h(date('H:i', strtotime($p['created_at']))) ?> น.</div>
                    </td>
                    <td>
                        <div class="pu-actions">
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?= h($_SESSION['admin_csrf']) ?>">
                                <input type="hidden" name="action" value="approve_user">
                                <input type="hidden" name="user_id" value="<?= (int)$p['user_id'] ?>">
                                <button class="pu-btn pu-btn--approve" aria-label="อนุมัติ <?= h($pName) ?>"><i class="fa-solid fa-check" aria-hidden="true"></i> อนุมัติ</button>
                            </form>
                            <button type="button" class="pu-btn pu-btn--reject" data-open-reject data-user-id="<?= (int)$p['user_id'] ?>" data-user-name="<?= h($pName) ?>" aria-label="ปฏิเสธ <?= h($pName) ?>">
                                <i class="fa-solid fa-xmark" aria-hidden="true"></i> ปฏิเสธ
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<dialog id="rejectDialog" class="pu-dialog" aria-labelledby="rejectTitle">
    <form method="post" class="flex flex-col">
        <input type="hidden" name="csrf" value="<?= h($_SESSION['admin_csrf']) ?>">
        <input type="hidden" name="action" value="reject_user">
        <input type="hidden" name="user_id" value="">
        <div style="padding: 18px 20px 6px">
            <h2 id="rejectTitle" class="pu-title">ปฏิเสธการสมัคร</h2>
            <div class="pu-sub" data-reject-name></div>
        </div>
        <div style="padding: 10px 20px 16px; display: flex; flex-direction: column; gap: 10px">
            <label for="rejectReason" class="text-sm font-medium text-gray-800">เหตุผล (ผู้สมัครจะเห็นข้อความนี้ตอนเข้าสู่ระบบ)</label>
            <div style="display:flex; flex-wrap:wrap; gap:6px">
                <?php foreach (['ข้อมูลไม่ตรงกับทะเบียน', 'สมัครซ้ำกับบัญชีเดิม', 'ไม่ใช่นักศึกษาหรือบุคลากรของวิทยาลัย'] as $preset): ?>
                    <button type="button" class="pu-chip" data-reason="<?= h($preset) ?>"><?= h($preset) ?></button>
                <?php endforeach; ?>
            </div>
            <textarea id="rejectReason" name="reject_reason" maxlength="255" placeholder="ไม่บังคับ"></textarea>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:8px; padding: 12px 20px; border-top: 1px solid #EFEDE9; background: #FAFAF8; border-radius: 0 0 16px 16px">
            <button type="button" class="pu-btn" data-reject-close>ยกเลิก</button>
            <button class="pu-btn pu-btn--reject" style="border-color:#B91C1C">ยืนยันปฏิเสธ</button>
        </div>
    </form>
</dialog>
<script>
    (function () {
        const rows = Array.from(document.querySelectorAll('[data-select-row]'));
        const all = document.querySelector('[data-select-all]');
        const count = document.querySelector('[data-selected-count]');
        const submit = document.querySelector('[data-bulk-submit]');
        function sync() {
            const n = rows.filter(function (r) { return r.checked; }).length;
            count.textContent = n ? 'เลือกแล้ว ' + n + ' บัญชี' : 'ยังไม่ได้เลือก';
            submit.disabled = n === 0;
            if (all) { all.checked = n === rows.length && n > 0; all.indeterminate = n > 0 && n < rows.length; }
        }
        all && all.addEventListener('change', function () { rows.forEach(function (r) { r.checked = all.checked; }); sync(); });
        rows.forEach(function (r) { r.addEventListener('change', sync); });
        sync();

        const dialog = document.getElementById('rejectDialog');
        const form = dialog.querySelector('form');
        document.querySelectorAll('[data-open-reject]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                form.reset();
                form.elements.user_id.value = btn.dataset.userId;
                dialog.querySelector('[data-reject-name]').textContent = btn.dataset.userName;
                dialog.showModal();
            });
        });
        dialog.querySelectorAll('[data-reason]').forEach(function (chip) {
            chip.addEventListener('click', function () { form.elements.reject_reason.value = chip.dataset.reason; });
        });
        dialog.querySelector('[data-reject-close]').addEventListener('click', function () { dialog.close(); });
    })();
</script>
<?php endif; ?>

<!-- ตารางรายชื่อผู้ใช้และแถบค้นหา -->
<div class="admin-tray">
    <div class="admin-tray-label">
        <span>รายชื่อผู้ใช้</span>
        <span><?= count($users) ?> บัญชี</span>
    </div>
    <section class="admin-card">
        <form method="get" class="admin-toolbar">
            <input name="q" value="<?= h($search) ?>" placeholder="ค้นหาชื่อ รหัส อีเมล หรือแผนก" class="min-w-[220px] flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-gray-900">
            <select name="status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-gray-900">
                <option value="">ทุกสถานะ</option>
                <?php foreach ($userStatusLabels as $key => $label): ?>
                    <option value="<?= h($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="admin-toolbar-actions">
                <button class="admin-btn">ค้นหา</button>
                <a href="admin_manage_user.php" class="admin-reset-link">ล้าง</a>
            </div>
        </form>

        <style>
            .ud-table { width: 100%; min-width: 900px; table-layout: fixed; border-collapse: collapse; font-size: 14px; }
            .ud-table th { padding: 10px 16px; background: #FAFAF8; border-bottom: 1px solid #EFEDE9; text-align: left; font-size: 13px; font-weight: 500; color: #6B6862; white-space: nowrap; }
            .ud-table td { padding: 12px 16px; border-bottom: 1px solid #F1EFEC; vertical-align: middle; }
            .ud-table tbody tr:last-child td { border-bottom: 0; }
            .ud-table tbody tr:hover td { background: #FCFBFA; }
            .ud-truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .ud-muted { margin-top: 1px; font-size: 13px; color: #6B6862; }
            .ud-actions { display: flex; justify-content: flex-end; gap: 6px; }
            .ud-actions form { display: inline-flex; }
            .ud-icon { display: inline-grid; place-items: center; width: 36px; height: 36px; border: 1px solid #D4D0C9; border-radius: 9px; background: #fff; color: #3F3D39; font-size: 13px; cursor: pointer; text-decoration: none; }
            .ud-icon:hover:not(:disabled) { background: #F5F4F1; border-color: #BDB8B0; }
            .ud-icon:disabled { opacity: .35; cursor: not-allowed; }
            .ud-icon--warn { color: #B45309; }
            .ud-icon--ok { color: #047857; }
            .ud-icon--danger { color: #B91C1C; }
            .ud-icon--danger:hover:not(:disabled) { background: #FEF2F2; border-color: #FECACA; }
            @media (max-width: 900px) {
                .ud-table { min-width: 0; }
                .ud-table thead { display: none; }
                .ud-table, .ud-table tbody, .ud-table tr, .ud-table td { display: block; width: 100%; }
                .ud-table tr { padding: 12px 16px; border-bottom: 1px solid #EFEDE9; }
                .ud-table td { padding: 2px 0; border: 0; }
                .ud-actions { justify-content: flex-start; padding-top: 8px; }
                .ud-icon { width: 44px; height: 44px; }
            }
        </style>
        <div class="overflow-x-auto">
            <table class="ud-table" data-pager id="userDirectory">
                <colgroup>
                    <col style="width: 25%">
                    <col style="width: 14%">
                    <col style="width: 21%">
                    <col style="width: 11%">
                    <col style="width: 10%">
                    <col style="width: 11%">
                    <col style="width: 140px">
                </colgroup>
                <thead>
                    <tr>
                        <th scope="col">ชื่อผู้ใช้</th>
                        <th scope="col">รหัส / ประเภท</th>
                        <th scope="col">แผนก / ติดต่อ</th>
                        <th scope="col">สมัครเมื่อ</th>
                        <th scope="col">สิทธิ์</th>
                        <th scope="col">สถานะ</th>
                        <th scope="col" style="text-align:right">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$users): ?>
                        <tr data-pager-ignore><td colspan="7" class="px-4 py-12 text-center text-gray-500">ไม่พบผู้ใช้ตามเงื่อนไขนี้</td></tr>
                    <?php endif; ?>

                    <?php foreach ($users as $u):
                        $roleChipClass = ['admin' => 'role-admin role-dark', 'staff' => 'role-staff role-pink'][$u['role']] ?? '';
                        $uName = $u['first_name'] . ' ' . $u['last_name'];
                        $isSelf = (int)$u['user_id'] === $actorId; ?>
                        <tr>
                            <td>
                                <div class="ud-truncate font-medium text-gray-900" title="<?= h($uName) ?>"><?= h($uName) ?></div>
                                <div class="ud-muted ud-truncate" title="<?= h($u['email']) ?>"><?= h($u['email']) ?></div>
                            </td>
                            <td>
                                <div class="ud-truncate"><?= h($u['id_number']) ?></div>
                                <div class="ud-muted"><?= h(['student' => 'นักศึกษา', 'teacher' => 'ครู/อาจารย์', 'staff' => 'บุคลากร'][$u['user_type']] ?? $u['user_type']) ?></div>
                            </td>
                            <td>
                                <div class="ud-truncate" title="<?= h($u['department_name'] ?? '') ?>"><?= h($u['department_name'] ?? 'ไม่ระบุแผนก') ?></div>
                                <div class="ud-muted"><?= h($u['phone_number']) ?></div>
                            </td>
                            <td><?= h(date('d/m/Y', strtotime($u['created_at']))) ?></td>
                            <td><span class="admin-chip <?= $roleChipClass ?>"><?= h(['admin' => 'ครู (Admin)', 'staff' => 'สตาฟ', 'user' => 'ผู้ใช้'][$u['role']] ?? $u['role']) ?></span></td>
                            <td><span class="admin-chip st-<?= h($u['status']) ?>"><?= h($userStatusLabels[$u['status']] ?? $u['status']) ?></span></td>
                            <td>
                                <div class="ud-actions">
                                    <a href="admin_edit_user.php?user_id=<?= (int)$u['user_id'] ?>" class="ud-icon" title="แก้ไขข้อมูล" aria-label="แก้ไขข้อมูล <?= h($uName) ?>">
                                        <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                    </a>
                                    <?php if ($u['status'] !== 'pending'): ?>
                                        <form method="post">
                                            <input type="hidden" name="csrf" value="<?= h($_SESSION['admin_csrf']) ?>">
                                            <input type="hidden" name="return_q" value="<?= h($search) ?>">
                                            <input type="hidden" name="return_status" value="<?= h($statusFilter) ?>">
                                            <input type="hidden" name="action" value="change_status">
                                            <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                                            <input type="hidden" name="status" value="<?= $u['status'] === 'active' ? 'disabled' : 'active' ?>">
                                            <?php if ($u['status'] === 'active'): ?>
                                                <button class="ud-icon ud-icon--warn" onclick="return confirm('ยืนยันระงับบัญชี <?= h($uName) ?>?')" <?= $isSelf ? 'disabled' : '' ?> title="ระงับการใช้งาน" aria-label="ระงับบัญชี <?= h($uName) ?>">
                                                    <i class="fa-solid fa-user-slash" aria-hidden="true"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="ud-icon ud-icon--ok" onclick="return confirm('ยืนยันเปิดใช้งานบัญชี <?= h($uName) ?>?')" <?= $isSelf ? 'disabled' : '' ?> title="เปิดใช้งาน" aria-label="เปิดใช้งานบัญชี <?= h($uName) ?>">
                                                    <i class="fa-solid fa-user-check" aria-hidden="true"></i>
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    <?php endif; ?>
                                    <button type="button" class="ud-icon ud-icon--danger" data-open-user-delete
                                        data-user-id="<?= (int)$u['user_id'] ?>"
                                        data-id-number="<?= h($u['id_number']) ?>"
                                        data-user-name="<?= h($uName) ?>"
                                        <?= $isSelf ? 'disabled' : '' ?> title="ลบบัญชี" aria-label="ลบบัญชี <?= h($uName) ?>">
                                        <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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
            <button type="button" data-close-add-user aria-label="ปิด" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-200/70 hover:text-gray-900">
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
            <button type="button" data-close-user-delete aria-label="ปิด" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-900"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="space-y-4 px-5 py-5 sm:px-6">
            <p class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                กำลังจะลบบัญชี <strong id="userDeleteName" class="break-all"></strong>
                <br>รหัสประจำตัว <span class="font-mono font-semibold" id="userDeleteIdNumber"></span>
            </p>
            <div>
                <label for="userDeleteConfirmInput" class="mb-1.5 block text-sm font-medium text-gray-800">พิมพ์รหัสประจำตัวเพื่อยืนยันการลบ</label>
                <input type="text" id="userDeleteConfirmInput" name="confirm_id_number" autocomplete="off" spellcheck="false" autocapitalize="off" autocorrect="off" placeholder="รหัสประจำตัวของบัญชีนี้"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 font-mono text-sm outline-none transition placeholder:font-kanit placeholder:text-gray-500 focus:border-red-600 focus:ring-2 focus:ring-red-600/10">
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
                    repsSetIconText(hint, 'check', 'รหัสตรงกันแล้ว กด "ยืนยันลบบัญชี" เพื่อทำรายการ');
                    hint.className = 'mt-1.5 text-xs font-medium text-emerald-600';
                } else {
                    repsSetIconText(hint, 'x', 'รหัสที่พิมพ์ยังไม่ตรงกัน กรุณาตรวจสอบอีกครั้ง');
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
                repsSetIconText(hint, 'x', 'รหัสประจำตัวไม่ตรง จึงยังลบบัญชีไม่ได้');
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
<?php $footerBase = '../'; $footerVariant = 'app'; require __DIR__ . '/../includes/site_footer.php'; ?>
</div>
</body>
</html>