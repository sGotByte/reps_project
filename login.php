<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/login_rate_limit.php';
if (is_file(__DIR__ . '/includes/site_config.php')) {
    require_once __DIR__ . '/includes/site_config.php';
}

$siteName = function_exists('app_site_name') ? app_site_name($conn) : 'REPS';$error_msg = '';
$csrfToken = app_csrf_token();$usernameInput = is_string($_POST['username'] ?? null) ? $_POST['username'] : '';

$allowedNext = ['admin', 'war_room', 'teacher_report', 'staff', 'dashboard', 'request_form', 'profile', 'track'];
$next =$_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['next'] ?? '') : ($_GET['next'] ?? '');
// ลิงก์จากอีเมลขั้นตอนใบงาน: next=sign.php?id=<เลข> (รับเฉพาะรูปแบบนี้ กัน open redirect)
$nextTicket = is_string($next) && preg_match('/^sign\.php\?id=([1-9][0-9]{0,9})$/',$next, $nm) === 1 ? (int)$nm[1] : 0;
$next = $nextTicket > 0 ? 'sign.php?id=' . $nextTicket : (is_string($next) && in_array($next, $allowedNext, true) ?$next : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_csrf_is_valid($_POST['csrf'] ?? null)) {
        http_response_code(403);
        $error_msg = 'คำขอหมดอายุ กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง';
    } else {
        $username = trim(is_string($_POST['username'] ?? null) ? $_POST['username'] : '');$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        if ($username !== '' && $password !== '' && strlen($username) <= 20) {
            if (appLoginIsRateLimited($username)) {$error_msg = 'พยายามเข้าสู่ระบบหลายครั้งเกินไป กรุณารอ 15 นาทีแล้วลองใหม่';
            } else {
                $stmt =$conn->prepare('SELECT user_id, id_number, first_name, last_name, email, password, role, status, reject_reason FROM users WHERE id_number = ? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('s',$username);
                    $stmt->execute();$user = $stmt->get_result()->fetch_assoc();$stmt->close();

                    $allowedRoles = ['admin', 'staff', 'user'];

                    if ($user && password_verify($password, $user['password']) &&$user['status'] === 'active' && in_array($user['role'],$allowedRoles, true)) {
                        appLoginClearAccountFailures($username);
                        session_regenerate_id(true);
                        $_SESSION = [];$_SESSION['user_id'] = (int)$user['user_id'];$_SESSION['u_id'] = (int)$user['user_id'];$_SESSION['username'] = $user['id_number'];$_SESSION['id_number'] = $user['id_number'];$_SESSION['role'] = $user['role'];$_SESSION['u_role'] = $user['role'];$_SESSION['auth_password_hash'] = $user['password'];$_SESSION['user_name'] = trim($user['first_name'] . ' ' .$user['last_name']);
                        app_csrf_token();

                        if ($user['role'] === 'admin') {
                            $adminTargets = [ 'war_room'       => 'admin/admin_war_room.php', 'teacher_report' => 'admin/admin_teacher_report.php', 'admin'          => 'admin/admin_dashboard.php', ];$target = $adminTargets[$next] ?? 'admin/admin_dashboard.php';
                        } elseif ($user['role'] === 'staff') {$target = 'staff/staff_dashboard.php';
                        } else {
                            $userTargets = [ 'request_form' => 'request_form.php', 'profile'      => 'profile.php', 'track'        => 'dashboard.php', 'dashboard'    => 'dashboard.php', ];$target = $userTargets[$next] ?? 'dashboard.php';
                        }

                        if ($nextTicket > 0) {
                            $target = 'sign.php?id=' . $nextTicket; // sign.php ส่งต่อไปหน้าตามบทบาทเอง
                        }
                        header('Location: ' . $target, true, 303);
                        exit;
                    }

                    // แจ้งสถานะบัญชีเฉพาะเมื่อรหัสผ่านถูกต้อง (ไม่เปิดเผยว่ามีบัญชีนี้อยู่ให้คนที่ไม่รู้รหัส)
                    if ($user && password_verify($password, $user['password']) &&$user['status'] !== 'active') {
                        $error_msg = match ($user['status']) {
                            'pending'  => 'บัญชีของคุณอยู่ระหว่างรอผู้ดูแลระบบอนุมัติ กรุณารอการยืนยันก่อนเข้าสู่ระบบ',
                            'rejected' => 'การสมัครบัญชีนี้ไม่ได้รับการอนุมัติ' . (!empty($user['reject_reason']) ? ' (เหตุผล: ' . $user['reject_reason'] . ')' : ''),
                            default    => 'บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ',
                        };
                    } else {
                        appLoginRecordFailure($username);$error_msg = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
                    }
                } else {
                    $error_msg = 'เกิดข้อผิดพลาดในการตรวจสอบข้อมูลกับฐานข้อมูล';
                }
            }
        } else {
            appLoginRecordFailure($username);$error_msg = 'กรุณากรอกชื่อผู้ใช้ (ไม่เกิน 20 ตัวอักษร) และรหัสผ่านให้ครบถ้วน';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#FFFFFF">
    <title>เข้าสู่ระบบ | <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/images/logo/reps-icon.svg">
    <link rel="alternate icon" type="image/png" sizes="32x32" href="assets/images/logo/favicon-32.png">
    <link rel="apple-touch-icon" href="assets/images/logo/apple-touch-icon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/app.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/app.css') ?>">
    <link rel="stylesheet" href="assets/css/auth.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/auth.css') ?>">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/base.css?v=18">

    <style>
        /* Light Theme (No Grid / No Dashed Rails) */
        body.auth-light {
            background-color: #FAFAFA;
            color: #111827;
        }
        body.auth-light .auth-header {
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid #E5E7EB;
        }
        body.auth-light .auth-card {
            background-color: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 16px;
            box-shadow: 0 4px 24px -4px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.03);
            padding: 2rem;
            width: 100%;
        }
        body.auth-light .auth-card-title h1 {
            color: #111827;
        }
        body.auth-light .auth-card-title p {
            color: #6B7280;
        }
        body.auth-light .auth-label {
            color: #374151;
        }
        body.auth-light .auth-field,
        body.auth-light select {
            background-color: #FFFFFF;
            border: 1px solid #D1D5DB;
            color: #111827;
            border-radius: 10px;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        body.auth-light select {
            appearance: none;
            -webkit-appearance: none;
            padding-right: 2.25rem;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%234B5563' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1rem;
        }
        body.auth-light .auth-field::placeholder {
            color: #9CA3AF;
        }
        body.auth-light .auth-field:focus,
        body.auth-light select:focus {
            border-color: #111827;
            box-shadow: 0 0 0 3px rgba(17, 24, 39, 0.08);
            outline: none;
        }
        body.auth-light .auth-eye {
            color: #6B7280;
        }
        body.auth-light .auth-eye:hover {
            color: #111827;
        }
        body.auth-light .auth-btn-primary {
            background-color: #111827;
            color: #FFFFFF;
            border: 1px solid #111827;
            border-radius: 10px;
        }
        body.auth-light .auth-btn-primary:hover {
            background-color: #1F2937;
        }
        body.auth-light .auth-alt {
            color: #6B7280;
            border-top: 1px solid #F3F4F6;
        }
        body.auth-light .auth-alt a {
            color: #111827;
            font-weight: 500;
        }

        /* New Clean Alert Design */
        .auth-alert-new.hidden {
            display: none !important;
        }
        .auth-alert-new {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 13px 15px;
            border-radius: 12px;
            font-size: 0.875rem;
            line-height: 1.55;
            margin-bottom: 1.25rem;
            border: 1px solid transparent;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        }
        .auth-alert-new .alert-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 9999px;
            flex-shrink: 0;
            margin-top: 1px;
            font-size: 0.8rem;
        }
        .auth-alert-new .alert-content {
            flex: 1;
        }
        .auth-alert-new .alert-title {
            display: block;
            font-weight: 600;
            margin-bottom: 2px;
            font-size: 0.9rem;
        }

        .auth-alert-new.is-success {
            background-color: #F0FDF4;
            border-color: #BBF7D0;
            color: #166534;
        }
        .auth-alert-new.is-success .alert-icon {
            background-color: #DCFCE7;
            color: #15803D;
        }

        .auth-alert-new.is-info {
            background-color: #F8FAFC;
            border-color: #E2E8F0;
            color: #334155;
        }
        .auth-alert-new.is-info .alert-icon {
            background-color: #E2E8F0;
            color: #0F172A;
        }

        .auth-alert-new.is-error {
            background-color: #FEF2F2;
            border-color: #FECACA;
            color: #991B1B;
        }
        .auth-alert-new.is-error .alert-icon {
            background-color: #FEE2E2;
            color: #DC2626;
        }
    </style>
</head>

<body class="auth-light antialiased min-h-screen flex flex-col justify-between relative overflow-x-hidden font-sans">

    <header class="auth-header sticky top-0 z-20">
        <div class="auth-header-inner">
            <a class="auth-logo-link focus:outline-none" href="index.php" aria-label="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?> หน้าหลัก">
                <img src="<?= htmlspecialchars(app_logo_url($conn), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>" class="auth-logo">
            </a>
        </div>
    </header>

    <main id="main" class="w-full relative z-10 flex-1 flex flex-col items-center justify-center px-6 py-12 sm:py-16">
        <div class="w-full max-w-md mx-auto flex flex-col items-center">

            <div class="auth-card">

                <div class="auth-card-title">
                    <h1>เข้าสู่ระบบ</h1>
                    <p>สำหรับนักศึกษา ครูอาจารย์ ฝ่ายซ่อม และผู้ดูแลระบบ</p>
                </div>

                <?php if (isset($_GET['registered'])): ?>
                    <div class="auth-alert-new is-success" role="status">
                        <div class="alert-icon"><i class="fa-solid fa-check" aria-hidden="true"></i></div>
                        <div class="alert-content">
                            <span class="alert-title">ส่งคำขอสมัครสมาชิกสำเร็จ</span>
                            <span>ครูผู้ดูแลระบบจะตรวจสอบและอนุมัติบัญชี เมื่ออนุมัติแล้วสามารถเข้าสู่ระบบด้วยชื่อผู้ใช้และรหัสผ่านที่ตั้งไว้ได้ทันที</span>
                        </div>
                    </div>
                <?php elseif ($next === 'request_form' &&$_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
                    <div class="auth-alert-new is-info" role="note">
                        <div class="alert-icon"><i class="fa-solid fa-info" aria-hidden="true"></i></div>
                        <div class="alert-content">
                            <span class="alert-title">กรุณาเข้าสู่ระบบก่อนแจ้งซ่อม</span>
                            <span>หากยังไม่มีบัญชี สามารถสมัครสมาชิกได้ที่ด้านล่าง (บัญชีใหม่ต้องรอครูอนุมัติก่อนใช้งาน)</span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_msg)): ?>
                    <div class="auth-alert-new is-error" role="alert">
                        <div class="alert-icon"><i class="fa-solid fa-exclamation" aria-hidden="true"></i></div>
                        <div class="alert-content">
                            <span class="alert-title">ไม่สามารถเข้าสู่ระบบได้</span>
                            <span><?= htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <div id="errorMessage" class="auth-alert-new is-error hidden" role="alert" aria-hidden="true">
                    <div class="alert-icon"><i class="fa-solid fa-exclamation" aria-hidden="true"></i></div>
                    <div class="alert-content">
                        <span class="alert-title">ข้อมูลไม่ครบถ้วน</span>
                        <span>กรุณากรอกชื่อผู้ใช้และรหัสผ่านให้ครบถ้วน</span>
                    </div>
                </div>

                <form action="login.php" method="POST" id="loginForm" class="space-y-4" novalidate>
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="space-y-1.5">
                        <label class="auth-label" for="username">
                            ชื่อผู้ใช้ / รหัสประจำตัว
                        </label>
                        <input type="text" id="username" name="username" required autocomplete="username" maxlength="20"
                            placeholder="กรอกชื่อผู้ใช้หรือรหัสประจำตัว"
                            value="<?= htmlspecialchars($usernameInput, ENT_QUOTES, 'UTF-8') ?>"
                            class="auth-field">
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between gap-4">
                            <label class="auth-label" for="password">
                                รหัสผ่าน
                            </label>
                            <a class="text-xs text-gray-500 hover:text-gray-900 transition-colors underline-offset-4 hover:underline" href="forgot_password.php">
                                ลืมรหัสผ่าน?
                            </a>
                        </div>
                        <div class="relative">
                            <input type="password" id="password" name="password" required autocomplete="current-password"
                                placeholder="••••••••"
                                class="auth-field auth-field-pad">
                            <button type="button" id="togglePassword" onclick="togglePasswordVisibility()" aria-label="แสดง/ซ่อนรหัสผ่าน"
                                class="auth-eye">
                                <i class="fa-regular fa-eye text-[15px]" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" id="submitBtn" class="auth-btn auth-btn-primary rounded-lg">
                        <span>เข้าสู่ระบบ</span>
                        <i class="fa-solid fa-arrow-right text-[13px]" aria-hidden="true"></i>
                    </button>
                </form>

                <div id="registerSection" class="auth-alt">
                    ยังไม่มีบัญชีผู้ใช้?
                    <a href="register.php">สร้างบัญชี</a>
                </div>
            </div>
        </div>
    </main>

    <?php $footerBase = '';$footerVariant = 'light'; require __DIR__ . '/includes/site_footer.php'; ?>

    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            if (!passwordInput || !toggleIcon) return;

            const isHidden = passwordInput.type === 'password';
            passwordInput.type = isHidden ? 'text' : 'password';
            toggleIcon.className = isHidden ?
                'fa-regular fa-eye-slash text-[15px]' :
                'fa-regular fa-eye text-[15px]';
        }

        document.getElementById('loginForm')?.addEventListener('submit', function(e) {
            const username = document.getElementById('username')?.value.trim();
            const password = document.getElementById('password')?.value.trim();
            const errorMessage = document.getElementById('errorMessage');
            const btnText = document.getElementById('submitBtn');

            if (!username || !password) {
                e.preventDefault();
                if (errorMessage) {
                    errorMessage.classList.remove('hidden');
                    errorMessage.setAttribute('aria-hidden', 'false');
                }
                return;
            }

            if (errorMessage) {
                errorMessage.classList.add('hidden');
                errorMessage.setAttribute('aria-hidden', 'true');
            }
            if (btnText) btnText.disabled = true;
        });
    </script>
</body>

</html>