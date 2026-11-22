<?php
declare(strict_types=1);

require_once __DIR__ . '/permissions.php';

/**
 * ตรวจสอบบัญชีที่กำลังใช้งาน (active) และสิทธิ์ (role) ตามที่หน้าเว็บกำหนด
 * ลำดับสิทธิ์: admin (ครู) > staff > user (ค่าเก่า requester/teacher แปลงด้วย normalize_role)
 *
 * @param mysqli $conn
 * @param string $loginUrl หน้าเข้าสู่ระบบหากยังไม่ได้ล็อกอิน
 * @param string|array|null $requiredRole สิทธิ์ที่อนุญาต (ค่าเริ่มต้น 'user', ส่ง array ได้เช่น ['admin', 'staff'], null = ทุก role)
 * @return int user_id ของผู้ใช้ที่ผ่านการตรวจสอบ
 */
function app_require_active_user(mysqli $conn, string $loginUrl, string|array|null $requiredRole = 'user'): int
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$userId || $userId < 1) {
        header('Location: ' . $loginUrl, true, 303);
        exit;
    }

    // must_change_password มาจาก migration 20261120 — ถ้ายังไม่ได้รันใช้ query เดิม
    $stmt = $conn->prepare('SELECT role, status, password, must_change_password FROM users WHERE user_id = ? LIMIT 1')
        ?: $conn->prepare('SELECT role, status, password, 0 AS must_change_password FROM users WHERE user_id = ? LIMIT 1');
    if (!$stmt) {
        http_response_code(500);
        exit('ตรวจสอบบัญชีผู้ใช้ไม่ได้ กรุณาลองใหม่ภายหลัง');
    }
    $stmt->bind_param('i', $userId);
    if (!$stmt->execute()) {
        $stmt->close();
        http_response_code(500);
        exit('ตรวจสอบบัญชีผู้ใช้ไม่ได้ กรุณาลองใหม่ภายหลัง');
    }
    $account = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $validSession = $account && $account['status'] === 'active'
        && isset($_SESSION['auth_password_hash'])
        && hash_equals((string)$account['password'], (string)$_SESSION['auth_password_hash']);

    if (!$validSession) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        header('Location: ' . $loginUrl, true, 303);
        exit;
    }

    // แปลง role เก่า ('requester') ให้เป็น 'user' และตรวจสอบว่าอยู่ในระบบสิทธิ์หรือไม่
    $currentRole = normalize_role((string)$account['role']);
    if (!array_key_exists($currentRole, ROLE_LEVELS)) {
        http_response_code(403);
        exit('บัญชีนี้ไม่มี role ที่เข้าใช้งานระบบได้');
    }

    // อัปเดต role ใน session ให้ตรงกับฐานข้อมูลล่าสุดเสมอ
    $_SESSION['role'] = $currentRole;
    $_SESSION['u_role'] = $currentRole;

    // ครูรีเซ็ตเป็นรหัสชั่วคราว → ต้องตั้งรหัสใหม่ก่อนใช้หน้าอื่น (ยกเว้นหน้าตั้งรหัสเอง)
    if ((int)($account['must_change_password'] ?? 0) === 1
        && basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) !== 'change_password.php'
    ) {
        $scriptDir = str_replace('\\', '/', dirname(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/'))));
        $root = in_array(basename($scriptDir), ['admin', 'staff'], true) ? str_replace('\\', '/', dirname($scriptDir)) : $scriptDir;
        $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
        if ($_SERVER['REQUEST_METHOD'] !== 'GET' || str_contains($accept, 'application/json') || !empty($_GET['partial'])) {
            http_response_code(403);
            exit('กรุณาตั้งรหัสผ่านใหม่ก่อนใช้งาน');
        }
        header('Location: ' . rtrim($root, '/') . '/change_password.php', true, 303);
        exit;
    }

    // ตรวจสอบสิทธิ์ตามที่หน้านั้นต้องการ (รองรับทั้ง string เดียวและ array ของหลาย role)
    if ($requiredRole !== null) {
        $allowedRoles = is_array($requiredRole)
            ? array_map('normalize_role', $requiredRole)
            : [normalize_role($requiredRole)];

        if (!in_array($currentRole, $allowedRoles, true)) {
            // dirname() บน Windows คืน "\" สำหรับไฟล์ในราก → ต้องแปลงเป็น "/" ก่อน มิฉะนั้นได้ "\/admin/..." ที่เบราว์เซอร์อ่านเป็นโดเมน "admin"
            $unixDir = static fn(string $p): string => str_replace('\\', '/', dirname(str_replace('\\', '/', $p)));
            $scriptDirectory = $unixDir((string)($_SERVER['SCRIPT_NAME'] ?? '/'));
            $dirName = basename($scriptDirectory);
            $appRoot = in_array($dirName, ['admin', 'staff'], true) ? $unixDir($scriptDirectory) : $scriptDirectory;
            $appRoot = rtrim($appRoot, '/');

            $targetPath = '/' . ltrim(role_home_url($currentRole), '/');
            $target = $appRoot . $targetPath;
            header('Location: ' . ($target !== '' ? $target : '/dashboard.php'), true, 303);
            exit;
        }
    }

    return (int)$userId;
}
/**
 * helper กลางสำหรับทุก endpoint: ตรวจ session + บัญชี active + role จากฐานข้อมูล
 */
function require_role(mysqli $conn, string|array $roles, string $loginUrl): int
{
    return app_require_active_user($conn, $loginUrl, $roles);
}

function current_role(): string
{
    return normalize_role((string)($_SESSION['role'] ?? 'user'));
}
