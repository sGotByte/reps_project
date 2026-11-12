<?php
require_once __DIR__ . '/csrf.php';
$errors = [];
$old = [
    'user_type' => 'student',
    'username' => '',
    'id_number' => '',
    'first_name' => '',
    'last_name' => '',
    'department_id' => '',
    'phone_number' => '',
    'email' => '',
];

$departments = [];
$departmentResult = $conn->query('SELECT d_id, name FROM department ORDER BY name');
if ($departmentResult) {
    $departments = $departmentResult->fetch_all(MYSQLI_ASSOC);
    $departmentResult->free();
} else {
    $errors[] = 'โหลดรายการแผนกไม่ได้ กรุณาลองใหม่ภายหลัง';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_csrf_is_valid($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
    foreach ($old as $key => $_) {
        $old[$key] = trim($_POST[$key] ?? '');
    }
    // รองรับทั้งฟอร์มที่ส่ง username หรือ id_number (ข้อ 9: เปลี่ยนเป็นยูเซอร์)
    if ($old['username'] === '' && $old['id_number'] !== '') {
        $old['username'] = $old['id_number'];
    } elseif ($old['id_number'] === '' && $old['username'] !== '') {
        $old['id_number'] = $old['username'];
    }

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!in_array($old['user_type'], ['student', 'teacher', 'staff'], true)) {
        $errors[] = 'ประเภทผู้ใช้ไม่ถูกต้อง';
    }
    if ($old['username'] === '' || strlen($old['username']) > 50) {
        $errors[] = 'กรุณากรอกชื่อผู้ใช้ (Username) ไม่เกิน 50 ตัวอักษร';
    }
    if ($old['first_name'] === '' || $old['last_name'] === '') {
        $errors[] = 'กรุณากรอกชื่อและนามสกุล';
    }
    $departmentIds = array_map('strval', array_column($departments, 'd_id'));
    if (!ctype_digit($old['department_id']) || !in_array($old['department_id'], $departmentIds, true)) {
        $errors[] = 'กรุณาเลือกแผนกวิชา/สังกัดจากรายการ';
    }
    if (!preg_match('/^0\d{8,9}$/', $old['phone_number'])) {
        $errors[] = 'เบอร์โทรศัพท์ไม่ถูกต้อง (เช่น 0812345678)';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';
    }
    if (strlen($password) < 8) {
        $errors[] = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน';
    }

    if (!$errors) {
        $checkStmt = $conn->prepare('SELECT user_id FROM users WHERE id_number = ? OR email = ? LIMIT 1');
        if (!$checkStmt) {
            error_log('Registration duplicate check prepare failed: ' . $conn->error);
            $errors[] = 'ตรวจสอบข้อมูลไม่สำเร็จ กรุณาลองใหม่ภายหลัง';
        } else {
            $checkStmt->bind_param('ss', $old['username'], $old['email']);
            if (!$checkStmt->execute()) {
                error_log('Registration duplicate check failed: ' . $checkStmt->error);
                $errors[] = 'ตรวจสอบข้อมูลไม่สำเร็จ กรุณาลองใหม่ภายหลัง';
            } else {
                $checkStmt->store_result();
                if ($checkStmt->num_rows > 0) {
                    $errors[] = 'ชื่อผู้ใช้หรืออีเมลนี้ถูกใช้งานแล้ว';
                }
            }
            $checkStmt->close();
        }

        if (!$errors) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'user';
            $status = 'active';
            $deptId = (int)$old['department_id'];

            $insertStmt = $conn->prepare(
                'INSERT INTO users (id_number, user_type, first_name, last_name, department_id, phone_number, email, password, role, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            if (!$insertStmt) {
                error_log('Registration insert prepare failed: ' . $conn->error);
                $errors[] = 'บันทึกข้อมูลไม่สำเร็จ กรุณาลองใหม่ภายหลัง';
            } else {
                $insertStmt->bind_param(
                    'ssssisssss',
                    $old['username'],
                    $old['user_type'],
                    $old['first_name'],
                    $old['last_name'],
                    $deptId,
                    $old['phone_number'],
                    $old['email'],
                    $passwordHash,
                    $role,
                    $status
                );

                if ($insertStmt->execute()) {
                    header('Location: login.php?registered=1');
                    exit;
                }
                error_log('Registration insert failed: ' . $insertStmt->error);
                $errors[] = 'บันทึกข้อมูลไม่สำเร็จ กรุณาลองใหม่ภายหลัง';
                $insertStmt->close();
            }
        }
    }
}