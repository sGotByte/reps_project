<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/login_rate_limit.php';
if (is_file(__DIR__ . '/includes/site_config.php')) {
    require_once __DIR__ . '/includes/site_config.php';
}

$siteName = function_exists('app_site_name') ? app_site_name($conn) : 'REPS';
$error_msg = '';
$csrfToken = app_csrf_token();
$usernameInput = is_string($_POST['username'] ?? null) ? $_POST['username'] : '';

$allowedNext = ['admin', 'war_room', 'teacher_report', 'staff', 'dashboard', 'request_form', 'profile', 'track'];
$next = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['next'] ?? '') : ($_GET['next'] ?? '');
$next = is_string($next) && in_array($next, $allowedNext, true) ? $next : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_csrf_is_valid($_POST['csrf'] ?? null)) {
        http_response_code(403);
        $error_msg = 'คำขอหมดอายุ กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง';
    } else {
        $username = trim(is_string($_POST['username'] ?? null) ? $_POST['username'] : '');
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        if ($username !== '' && $password !== '' && strlen($username) <= 20) {
            if (appLoginIsRateLimited($username)) {
                $error_msg = 'พยายามเข้าสู่ระบบหลายครั้งเกินไป กรุณารอ 15 นาทีแล้วลองใหม่';
            } else {
                $stmt = $conn->prepare('SELECT user_id, id_number, first_name, last_name, email, password, role, status FROM users WHERE id_number = ? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('s', $username);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    $allowedRoles = ['admin', 'staff', 'user'];

                    if ($user && password_verify($password, $user['password']) && $user['status'] === 'active' && in_array($user['role'], $allowedRoles, true)) {
                        appLoginClearAccountFailures($username);
                        session_regenerate_id(true);
                        $_SESSION = [];
                        $_SESSION['user_id'] = (int)$user['user_id'];
                        $_SESSION['u_id'] = (int)$user['user_id'];
                        $_SESSION['username'] = $user['id_number'];
                        $_SESSION['id_number'] = $user['id_number'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['u_role'] = $user['role'];
                        $_SESSION['auth_password_hash'] = $user['password'];
                        $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
                        app_csrf_token();

                        if ($user['role'] === 'admin') {
                            $adminTargets = [
                                'war_room'       => 'admin/admin_war_room.php',
                                'teacher_report' => 'admin/admin_teacher_report.php',
                                'admin'          => 'admin/admin_dashboard.php',
                            ];
                            $target = $adminTargets[$next] ?? 'admin/admin_dashboard.php';
                        } elseif ($user['role'] === 'staff') {
                            $target = 'staff/staff_dashboard.php';
                        } else {
                            $userTargets = [
                                'request_form' => 'request_form.php',
                                'profile'      => 'profile.php',
                                'track'        => 'dashboard.php',
                                'dashboard'    => 'dashboard.php',
                            ];
                            $target = $userTargets[$next] ?? 'dashboard.php';
                        }

                        header('Location: ' . $target, true, 303);
                        exit;
                    }

                    appLoginRecordFailure($username);
                    $error_msg = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง หรือบัญชีถูกระงับการใช้งาน';
                } else {
                    $error_msg = 'เกิดข้อผิดพลาดในการตรวจสอบข้อมูลกับฐานข้อมูล';
                }
            }
        } else {
            appLoginRecordFailure($username);
            $error_msg = 'กรุณากรอกชื่อผู้ใช้ (ไม่เกิน 20 ตัวอักษร) และรหัสผ่านให้ครบถ้วน';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0A0A0A">
    <title>เข้าสู่ระบบ | <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/images/logo/reps-icon.svg">
    <link rel="alternate icon" type="image/png" sizes="32x32" href="assets/images/logo/favicon-32.png">
    <link rel="apple-touch-icon" href="assets/images/logo/apple-touch-icon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/app.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/app.css') ?>">
    <link rel="stylesheet" href="assets/css/auth.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/auth.css') ?>">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
</head>

<body class="auth-dark antialiased min-h-screen flex flex-col justify-between relative overflow-x-hidden font-sans">

    <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden auth-fade-mask" aria-hidden="true">
        <div class="absolute inset-0 auth-grid"></div>
    </div>
    <div class="auth-rails" aria-hidden="true"></div>

    <header class="auth-header">
        <div class="max-w-6xl mx-auto h-16 px-6 flex items-center justify-between">
            <a class="flex items-center gap-3 focus:outline-none" href="index.php" aria-label="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?> หน้าหลัก">
                <img src="assets/images/logo/reps-logo.svg" alt="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>" class="auth-logo h-6 sm:h-7 w-auto">
            </a>
        </div>
    </header>

    <main id="main" class="w-full relative z-10 flex-1 flex flex-col items-center justify-center px-4 py-12 sm:py-16">
        <div class="w-full max-w-md mx-auto flex flex-col items-center">

            <div class="auth-card">

                <div class="auth-card-title">
                    <h1>เข้าสู่ระบบ</h1>
                    <p>สำหรับนักศึกษา ครูอาจารย์ ฝ่ายซ่อม และผู้ดูแลระบบ</p>
                </div>

                <?php if (isset($_GET['registered'])): ?>
                    <div class="auth-alert auth-alert-ok">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        <span>สร้างบัญชีสำเร็จแล้ว กรุณาเข้าสู่ระบบ</span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_msg)): ?>
                    <div class="auth-alert auth-alert-error" role="alert">
                        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <div id="errorMessage" class="auth-alert auth-alert-error hidden" role="alert">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <span>กรุณากรอกชื่อผู้ใช้และรหัสผ่าน</span>
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
                            <a class="auth-label hover:text-white transition-colors underline-offset-4 hover:underline" href="forgot_password.php">
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

    <footer class="auth-footer">
        <div class="max-w-6xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <span>แผนกเทคโนโลยีธุรกิจดิจิทัล</span>
            <span>&copy; <?= date('Y'); ?> Developed by Digital Business Technology.</span>
        </div>
    </footer>

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
                errorMessage?.classList.remove('hidden');
                return;
            }

            errorMessage?.classList.add('hidden');
            if (btnText) btnText.disabled = true;
        });
    </script>
</body>

</html>