<?php
declare(strict_types=1);

/**
 * REPS · page.php — หน้าเว็บเนื้อหาสาธารณะจากตาราง site_pages / site_blocks
 *
 * ปกติแสดงเฉพาะหน้า status = published
 * ผู้ดูแลที่ล็อกอินแล้วเปิด ?preview=1 เพื่อดูฉบับร่าง (ไม่สร้าง session ให้ผู้เยี่ยมชมทั่วไป)
 */

$preview = isset($_GET['preview']) && $_GET['preview'] === '1';
$formPost = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_submit'] ?? '') === '1';
$forms = [];
$APP_SKIP_SESSION = !$preview && !$formPost;
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/db_helpers.php';
require_once __DIR__ . '/includes/site_config.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/visual_builder.php';
require_once __DIR__ . '/includes/form_builder.php';

if ($preview) {
    require_once __DIR__ . '/includes/user_auth.php';
    app_require_active_user($conn, 'login.php', 'admin');
}

$slug = is_string($_GET['slug'] ?? null) ? $_GET['slug'] : '';
if (preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug) !== 1) {
    http_response_code(404);
    exit('ไม่พบหน้าเว็บ');
}

$sql = "SELECT id, slug, title, status FROM site_pages WHERE slug = ?"
    . ($preview ? '' : " AND status = 'published'")
    . ' LIMIT 1';
$page = db_one($conn, $sql, 's', [$slug]);
if (!$page) {
    http_response_code(404);
    exit('ไม่พบหน้าเว็บ');
}

try {
    $blocks = visual_builder_load_blocks($conn, (int)$page['id'], $preview ? 'draft' : 'published');
    if ($preview && $blocks === [] && $page['status'] === 'published') {
        $blocks = visual_builder_load_blocks($conn, (int)$page['id'], 'published');
    }
    $forms = form_builder_page_forms($conn, $blocks, $preview ? 'draft' : 'published');
} catch (InvalidArgumentException|RuntimeException $e) {
    error_log('Public CMS page render failed: ' . $e->getMessage());
    http_response_code(500);
    exit('ไม่สามารถแสดงหน้าเว็บได้ในขณะนี้');
}

$formCsrfToken = '';
if (!$preview && $forms !== []) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    require_once __DIR__ . '/includes/csrf.php';
    $formCsrfToken = app_csrf_token();
}

$submittedFormId = 0;
if ($formPost) {
    if ($preview) {
        http_response_code(405);
        exit('Draft preview forms cannot be submitted');
    }
    require_once __DIR__ . '/includes/csrf.php';
    if (!app_csrf_is_valid($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
    $formId = filter_var($_POST['form_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$formId || !isset($forms[$formId]) || !form_builder_page_contains($blocks, (int)$formId)) {
        http_response_code(404);
        exit('ไม่พบแบบฟอร์ม');
    }
    if (is_string($_POST['website'] ?? null) && trim($_POST['website']) !== '') {
        $submittedFormId = (int)$formId;
    } else {
        $sessionHash = hash('sha256', session_id());
        if (form_builder_submission_rate_limited($conn, $sessionHash)) {
            http_response_code(429);
            exit('ส่งแบบฟอร์มบ่อยเกินไป กรุณาลองใหม่ภายหลัง');
        }
        try {
            $answers = form_builder_validate_submission($forms[$formId], $_POST['answers'] ?? null);
        } catch (InvalidArgumentException) {
            http_response_code(422);
            exit('ข้อมูลในแบบฟอร์มไม่ถูกต้อง กรุณาตรวจสอบแล้วลองอีกครั้ง');
        }
        form_builder_save_submission($conn, (int)$formId, $sessionHash, $answers);
        $submittedFormId = (int)$formId;
    }
    $_SESSION['site_form_success_id'] = $submittedFormId;
    header('Location: page.php?slug=' . rawurlencode((string)$page['slug']), true, 303);
    exit;
}

if (!$preview && $forms !== []) {
    $submittedFormId = (int)($_SESSION['site_form_success_id'] ?? 0);
    unset($_SESSION['site_form_success_id']);
}
$renderedBlocks = visual_builder_render_blocks($blocks, '', $forms, $formCsrfToken, $preview, $submittedFormId);

$siteName = app_site_name($conn);
$siteLogo = app_logo_url($conn);
$publicUrl = 'page.php?slug=' . rawurlencode((string)$page['slug']);
$editUrl = 'admin/admin_pages.php?id=' . (int)$page['id'];
if (!$preview) {
    header('Cache-Control: no-cache');
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(app_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)$page['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> | <?= htmlspecialchars($siteName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
    <?php if ($preview): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
    <link rel="icon" type="image/svg+xml" href="assets/images/logo/reps-icon.svg">
    <link rel="stylesheet" href="assets/css/app.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/app.css') ?>">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/base.css?v=18">
    <link rel="stylesheet" href="assets/css/visual-builder.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/visual-builder.css') ?>">
</head>
<body class="public-page vb-public">
    <?php if ($preview): ?>
        <div class="vb-preview-bar">
            <strong><?= tr('ตัวอย่างฉบับร่าง', 'Draft preview') ?></strong>
            <span><?= tr('หน้านี้ยังไม่แสดงต่อสาธารณะจนกว่าจะเผยแพร่', 'This page is not public until it is published.') ?></span>
            <a href="<?= htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') ?>"><?= tr('กลับไปแก้ไข', 'Back to editor') ?></a>
            <?php if ($page['status'] === 'published'): ?>
                <a href="<?= htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8') ?>"><?= tr('ดูฉบับเผยแพร่', 'View published') ?></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <a class="skip-link" href="#main"><?= tr('ข้ามไปยังเนื้อหาหลัก', 'Skip to main content') ?></a>
    <header class="vb-site-bar">
        <a href="index.php" aria-label="<?= htmlspecialchars($siteName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <img src="<?= htmlspecialchars($siteLogo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="">
            <span><?= htmlspecialchars($siteName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
        </a>
        <nav aria-label="<?= tr('เมนูหลัก', 'Main navigation') ?>">
            <a href="index.php"><?= tr('หน้าหลัก', 'Home') ?></a>
            <?php if ($preview): ?>
                <a href="admin/admin_dashboard.php"><?= tr('แดชบอร์ด', 'Dashboard') ?></a>
            <?php else: ?>
                <a href="login.php"><?= tr('เข้าสู่ระบบ', 'Sign in') ?></a>
            <?php endif; ?>
        </nav>
    </header>
    <main class="vb-page" id="main">
        <h1><?= htmlspecialchars((string)$page['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
        <?= $renderedBlocks ?>
    </main>
    <?php $footerBase = ''; $footerVariant = 'light'; require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
