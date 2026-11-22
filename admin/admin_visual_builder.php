<?php
declare(strict_types=1);

$page_title = 'Visual Builder';
$current_page = 'visual_builder';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/site_config.php';
require_once __DIR__ . '/../includes/site_custom.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/visual_builder.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

function vb_studio_escape(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function vb_studio_save_setting(mysqli $conn, string $key, string $value): void
{
    db_exec(
        $conn,
        'INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        'ss',
        [$key, $value]
    );
}

function vb_studio_redirect(?string $page = null): never
{
    if ($page === null) {
        $page = is_string($_POST['page'] ?? null) ? $_POST['page'] : 'home';
    }
    if ($page !== 'home' && !isset(VISUAL_BUILDER_CONTENT_PAGES[$page])) {
        $page = 'home';
    }
    header('Location: admin_visual_builder.php?saved=1&page=' . rawurlencode($page), true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['admin_csrf'], is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '')) {
        http_response_code(403);
        exit('คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }

    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    try {
        $settings = app_get_settings($conn);
        switch ($action) {
            case 'save_text':
                $key = is_string($_POST['key'] ?? null) ? $_POST['key'] : '';
                $locale = is_string($_POST['locale'] ?? null) ? $_POST['locale'] : '';
                $target = VISUAL_BUILDER_HOME_TEXT_TARGETS[$key] ?? null;
                if ($target === null || !in_array($locale, ['th', 'en'], true)) {
                    throw new InvalidArgumentException('Invalid homepage text target');
                }
                $value = visual_builder_text_value($_POST['value'] ?? null, $target['max'], $target['multiline'] ?? false);
                $settingKey = $target['setting'] ?? null;
                if (is_string($settingKey) && $locale === 'th') {
                    vb_studio_save_setting($conn, $settingKey, $value);
                } else {
                    $content = visual_builder_home_content($settings);
                    $content[$key][$locale] = $value;
                    vb_studio_save_setting(
                        $conn,
                        'visual_builder_home_content',
                        json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                    );
                }
                site_revision_save($conn, 'home_text', $key, ['locale' => $locale, 'text' => $value], $adminId, 'Visual Builder');
                vb_studio_redirect();

            case 'save_content_text':
                $page = is_string($_POST['page'] ?? null) ? $_POST['page'] : '';
                $key = is_string($_POST['key'] ?? null) ? $_POST['key'] : '';
                $locale = is_string($_POST['locale'] ?? null) ? $_POST['locale'] : 'th';
                $target = VISUAL_BUILDER_CONTENT_PAGES[$page]['targets'][$key] ?? null;
                if ($target === null || !in_array($locale, ['th', 'en'], true)) {
                    throw new InvalidArgumentException('Invalid visual content target');
                }
                $value = visual_builder_text_value($_POST['value'] ?? null, (int)$target['max']);
                $content = visual_builder_content_page_data($settings, $page);
                $content[$key][$locale] = $value;
                $settingKey = 'visual_builder_content_' . $page;
                vb_studio_save_setting(
                    $conn,
                    $settingKey,
                    json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                );
                site_revision_save($conn, 'visual_page_text', $page, ['key' => $key, 'locale' => $locale, 'text' => $value], $adminId, 'Visual Builder');
                vb_studio_redirect($page);

            case 'restore_content_revision':
                $page = is_string($_POST['page'] ?? null) ? $_POST['page'] : '';
                $revisionId = (int)($_POST['revision_id'] ?? 0);
                if (!isset(VISUAL_BUILDER_CONTENT_PAGES[$page]) || $revisionId < 1) {
                    throw new InvalidArgumentException('Invalid visual content revision');
                }
                $revision = db_one(
                    $conn,
                    "SELECT data_json FROM site_revisions WHERE id = ? AND entity_type = 'visual_page_text' AND entity_id = ? LIMIT 1",
                    'is',
                    [$revisionId, $page]
                );
                if (!$revision) {
                    throw new InvalidArgumentException('Visual content revision does not exist');
                }
                try {
                    $snapshot = json_decode((string)$revision['data_json'], true, 16, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    throw new InvalidArgumentException('Visual content revision data is invalid', 0, $e);
                }
                $key = is_array($snapshot) && is_string($snapshot['key'] ?? null) ? $snapshot['key'] : '';
                $locale = is_array($snapshot) && is_string($snapshot['locale'] ?? null) ? $snapshot['locale'] : '';
                $target = VISUAL_BUILDER_CONTENT_PAGES[$page]['targets'][$key] ?? null;
                if ($target === null || !in_array($locale, ['th', 'en'], true)) {
                    throw new InvalidArgumentException('Visual content revision has an invalid target');
                }
                $value = visual_builder_text_value($snapshot['text'] ?? null, (int)$target['max']);
                $content = visual_builder_content_page_data($settings, $page);
                $content[$key][$locale] = $value;
                vb_studio_save_setting(
                    $conn,
                    'visual_builder_content_' . $page,
                    json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                );
                site_revision_save($conn, 'visual_page_text', $page, ['key' => $key, 'locale' => $locale, 'text' => $value], $adminId, 'Restored from revision #' . $revisionId);
                vb_studio_redirect($page);

            case 'restore_home_revision':
                $revisionId = (int)($_POST['revision_id'] ?? 0);
                $revision = db_one(
                    $conn,
                    "SELECT entity_type, entity_id, data_json FROM site_revisions
                      WHERE id = ? AND entity_type IN ('home_text','home_layout','theme','identity') LIMIT 1",
                    'i',
                    [$revisionId]
                );
                if (!$revision) {
                    throw new InvalidArgumentException('Homepage revision does not exist');
                }
                try {
                    $snapshot = json_decode((string)$revision['data_json'], true, 24, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    throw new InvalidArgumentException('Homepage revision data is invalid', 0, $e);
                }
                if (!is_array($snapshot)) {
                    throw new InvalidArgumentException('Homepage revision data is invalid');
                }
                $type = (string)$revision['entity_type'];
                $entityId = (string)$revision['entity_id'];
                $restore = [];
                if ($type === 'home_text') {
                    $target = VISUAL_BUILDER_HOME_TEXT_TARGETS[$entityId] ?? null;
                    $locale = is_string($snapshot['locale'] ?? null) ? $snapshot['locale'] : '';
                    if ($target === null || !in_array($locale, ['th', 'en'], true)) {
                        throw new InvalidArgumentException('Homepage text revision target is invalid');
                    }
                    $value = visual_builder_text_value($snapshot['text'] ?? null, (int)$target['max'], (bool)($target['multiline'] ?? false));
                    $setting = $target['setting'] ?? null;
                    if (is_string($setting) && $locale === 'th') {
                        $restore[$setting] = $value;
                    } else {
                        $content = visual_builder_home_content($settings);
                        $content[$entityId][$locale] = $value;
                        $restore['visual_builder_home_content'] = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                    }
                    $snapshot = ['locale' => $locale, 'text' => $value];
                } elseif ($type === 'home_layout') {
                    $layout = visual_builder_home_layout(['visual_builder_home_layout' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
                    $stored = [];
                    foreach ($layout as $key => $row) {
                        $stored[$key] = ['visible' => $row['visible'], 'size' => $row['size'], 'order' => $row['order'], 'background' => $row['background']];
                    }
                    $restore['visual_builder_home_layout'] = json_encode($stored, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                    $snapshot = $stored;
                } elseif ($type === 'theme') {
                    $themeName = is_string($snapshot['theme_color'] ?? null) ? $snapshot['theme_color'] : '';
                    $font = is_string($snapshot['theme_font'] ?? null) ? $snapshot['theme_font'] : '';
                    $radius = is_string($snapshot['theme_radius'] ?? null) ? $snapshot['theme_radius'] : '';
                    if (!isset(APP_THEME_PALETTES[$themeName]) || !isset(SITE_FONTS[$font]) || !array_key_exists($radius, SITE_RADIUS)) {
                        throw new InvalidArgumentException('Theme revision contains invalid tokens');
                    }
                    foreach (['primary_color', 'theme_accent_color', 'theme_bg_color'] as $colorKey) {
                        $color = is_string($snapshot[$colorKey] ?? null) ? $snapshot[$colorKey] : '';
                        if ($color !== '' && site_hex($color) === '') throw new InvalidArgumentException('Theme revision contains an invalid color');
                        $restore[$colorKey] = site_hex($color);
                    }
                    $restore['theme_color'] = $themeName;
                    $restore['theme_font'] = $font;
                    $restore['theme_radius'] = $radius;
                    $snapshot = $restore;
                } else {
                    $path = is_string($snapshot['site_logo_path'] ?? null) ? $snapshot['site_logo_path'] : '';
                    if ($path !== '' && !db_one($conn, 'SELECT id FROM site_media WHERE file_path = ? LIMIT 1', 's', [$path])) {
                        throw new InvalidArgumentException('Logo revision points to an unavailable media item');
                    }
                    $restore['site_logo_path'] = $path;
                    $snapshot = $restore;
                }
                db_transaction($conn, function () use ($conn, $restore, $type, $entityId, $snapshot, $revisionId, $adminId): void {
                    foreach ($restore as $settingKey => $settingValue) {
                        vb_studio_save_setting($conn, $settingKey, (string)$settingValue);
                    }
                    if ($type === 'home_layout') {
                        $layout = json_decode((string)$restore['visual_builder_home_layout'], true, 16, JSON_THROW_ON_ERROR);
                        vb_studio_save_setting($conn, 'homepage_show_steps', !empty($layout['how']['visible']) ? '1' : '0');
                        vb_studio_save_setting($conn, 'homepage_show_faq', !empty($layout['faq']['visible']) ? '1' : '0');
                    }
                    site_revision_save($conn, $type, $entityId, $snapshot, $adminId, 'Visual Builder restore from revision #' . $revisionId);
                });
                vb_studio_redirect('home');

            case 'save_layout':
                $key = is_string($_POST['key'] ?? null) ? $_POST['key'] : '';
                if (!isset(VISUAL_BUILDER_HOME_SECTIONS[$key])) {
                    throw new InvalidArgumentException('Invalid homepage section');
                }
                $layout = visual_builder_home_layout($settings);
                $visibility = is_string($_POST['visible'] ?? null) ? $_POST['visible'] : '';
                $size = is_string($_POST['size'] ?? null) ? $_POST['size'] : '';
                $move = is_string($_POST['move'] ?? null) ? $_POST['move'] : '';
                $background = is_string($_POST['background'] ?? null) ? trim($_POST['background']) : '';
                $useBackgroundValue = $_POST['use_background'] ?? '';
                if (!in_array($useBackgroundValue, ['', '1'], true)) {
                    throw new InvalidArgumentException('Invalid background option');
                }
                $useBackground = $useBackgroundValue === '1';
                if (!in_array($visibility, ['0', '1'], true)
                    || !in_array($size, ['compact', 'normal', 'wide'], true)
                    || !in_array($move, ['', 'up', 'down'], true)
                    || ($useBackground && site_hex($background) === '')) {
                    throw new InvalidArgumentException('Invalid section layout');
                }

                $layout[$key]['visible'] = $visibility === '1';
                $layout[$key]['size'] = $size;
                $layout[$key]['background'] = $useBackground ? site_hex($background) : '';
                if ($move !== '') {
                    $keys = array_keys($layout);
                    $index = array_search($key, $keys, true);
                    $otherIndex = $move === 'up' ? $index - 1 : $index + 1;
                    if ($index !== false && isset($keys[$otherIndex])) {
                        [$keys[$index], $keys[$otherIndex]] = [$keys[$otherIndex], $keys[$index]];
                        foreach ($keys as $position => $sectionKey) {
                            $layout[$sectionKey]['order'] = ($position + 1) * 10;
                        }
                    }
                }
                $storedLayout = [];
                foreach ($layout as $sectionKey => $row) {
                    $storedLayout[$sectionKey] = [
                        'visible' => $row['visible'],
                        'size' => $row['size'],
                        'order' => $row['order'],
                        'background' => $row['background'],
                    ];
                }
                $layoutJson = json_encode($storedLayout, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                db_transaction($conn, function () use ($conn, $key, $visibility, $layoutJson): void {
                    if ($key === 'how' || $key === 'faq') {
                        vb_studio_save_setting($conn, $key === 'how' ? 'homepage_show_steps' : 'homepage_show_faq', $visibility);
                    }
                    vb_studio_save_setting($conn, 'visual_builder_home_layout', $layoutJson);
                });
                site_revision_save($conn, 'home_layout', $key, $storedLayout, $adminId, 'Visual Builder');
                vb_studio_redirect();

            case 'save_logo':
                $postedMediaId = is_string($_POST['media_id'] ?? null) ? $_POST['media_id'] : '';
                if ($postedMediaId === 'keep') {
                    vb_studio_redirect();
                }
                $mediaId = ctype_digit($postedMediaId) ? (int)$postedMediaId : -1;
                if ($mediaId < 0) {
                    throw new InvalidArgumentException('Invalid logo selection');
                }
                $path = '';
                if ($mediaId > 0) {
                    $mediaRow = db_one($conn, 'SELECT file_path FROM site_media WHERE id = ? LIMIT 1', 'i', [$mediaId]);
                    if (!$mediaRow) {
                        throw new InvalidArgumentException('Selected logo does not exist');
                    }
                    $path = (string)$mediaRow['file_path'];
                }
                vb_studio_save_setting($conn, 'site_logo_path', $path);
                site_revision_save($conn, 'identity', '', ['site_logo_path' => $path], $adminId, 'Visual Builder');
                vb_studio_redirect();

            case 'save_theme':
                $themeName = is_string($_POST['theme_color'] ?? null) ? $_POST['theme_color'] : '';
                $font = is_string($_POST['theme_font'] ?? null) ? $_POST['theme_font'] : '';
                $radius = is_string($_POST['theme_radius'] ?? null) ? $_POST['theme_radius'] : '';
                if (!isset(APP_THEME_PALETTES[$themeName]) || !isset(SITE_FONTS[$font]) || !array_key_exists($radius, SITE_RADIUS)) {
                    throw new InvalidArgumentException('Invalid theme selection');
                }
                $primaryColor = is_string($_POST['primary_color'] ?? null) ? trim($_POST['primary_color']) : '';
                $accentColor = is_string($_POST['theme_accent_color'] ?? null) ? trim($_POST['theme_accent_color']) : '';
                $backgroundColor = is_string($_POST['theme_bg_color'] ?? null) ? trim($_POST['theme_bg_color']) : '';
                foreach ([$primaryColor, $accentColor, $backgroundColor] as $color) {
                    if ($color !== '' && site_hex($color) === '') {
                        throw new InvalidArgumentException('Invalid theme color');
                    }
                }
                $theme = [
                    'theme_color' => $themeName,
                    'primary_color' => site_hex($primaryColor) === APP_THEME_PALETTES[$themeName]['primary'] ? '' : site_hex($primaryColor),
                    'theme_accent_color' => site_hex($accentColor) === APP_THEME_PALETTES[$themeName]['accent'] ? '' : site_hex($accentColor),
                    'theme_bg_color' => site_hex($backgroundColor) === '#FAFAF8' ? '' : site_hex($backgroundColor),
                    'theme_font' => $font,
                    'theme_radius' => $radius,
                ];
                db_transaction($conn, function () use ($conn, $theme): void {
                    foreach ($theme as $key => $value) {
                        vb_studio_save_setting($conn, $key, $value);
                    }
                });
                site_revision_save($conn, 'theme', '', $theme, $adminId, 'Visual Builder');
                vb_studio_redirect();

            default:
                throw new InvalidArgumentException('Unknown Visual Builder action');
        }
    } catch (InvalidArgumentException $e) {
        $_SESSION['visual_builder_studio_error'] = tr('ข้อมูลที่ส่งมาไม่ถูกต้อง กรุณาตรวจสอบแล้วลองอีกครั้ง', 'Invalid data. Review the fields and try again.');
        error_log('admin_visual_builder validation: ' . $e->getMessage());
        vb_studio_redirect();
    } catch (RuntimeException $e) {
        $_SESSION['visual_builder_studio_error'] = tr('บันทึกไม่สำเร็จ กรุณาลองใหม่', 'Could not save changes. Please try again.');
        error_log('admin_visual_builder save: ' . $e->getMessage());
        vb_studio_redirect();
    }
}

$settings = app_get_settings($conn);
$selectedPage = is_string($_GET['page'] ?? null) ? $_GET['page'] : 'home';
if ($selectedPage !== 'home' && !isset(VISUAL_BUILDER_CONTENT_PAGES[$selectedPage])) {
    $selectedPage = 'home';
}
$previewPath = $selectedPage === 'home'
    ? '../index.php?visual_builder_preview=1'
    : VISUAL_BUILDER_CONTENT_PAGES[$selectedPage]['path'] . '?visual_builder_preview=1';
$pageRevisions = $selectedPage === 'home'
    ? db_all($conn, "SELECT r.id, r.entity_type, r.entity_id, r.note, r.created_at, COALESCE(u.first_name, '') AS first_name, COALESCE(u.last_name, '') AS last_name FROM site_revisions r LEFT JOIN users u ON u.user_id = r.created_by WHERE r.entity_type IN ('home_text','home_layout','theme','identity') AND (r.note = 'Visual Builder' OR r.note LIKE 'Visual Builder restore from revision #%') ORDER BY r.id DESC LIMIT 12")
    : db_all($conn, "SELECT r.id, r.entity_type, r.entity_id, r.note, r.created_at, COALESCE(u.first_name, '') AS first_name, COALESCE(u.last_name, '') AS last_name FROM site_revisions r LEFT JOIN users u ON u.user_id = r.created_by WHERE r.entity_type = 'visual_page_text' AND r.entity_id = ? ORDER BY r.id DESC LIMIT 12", 's', [$selectedPage]);
$layout = visual_builder_home_layout($settings);
$media = db_all($conn, 'SELECT id, file_path, title FROM site_media ORDER BY created_at DESC, id DESC LIMIT 200');
$currentLogoId = 0;
$legacyLogo = trim((string)($settings['site_logo_path'] ?? '')) !== '';
foreach ($media as $item) {
    if ((string)$item['file_path'] === (string)($settings['site_logo_path'] ?? '')) {
        $currentLogoId = (int)$item['id'];
        $legacyLogo = false;
        break;
    }
}
$errorMessage = is_string($_SESSION['visual_builder_studio_error'] ?? null) ? $_SESSION['visual_builder_studio_error'] : '';
unset($_SESSION['visual_builder_studio_error']);
$activePalette = APP_THEME_PALETTES[$settings['theme_color'] ?? 'default'] ?? APP_THEME_PALETTES['default'];
$admin_extra_head = '<link rel="stylesheet" href="../assets/css/visual-studio.css?v=3"><script src="../assets/js/cms-pages.js?v=1" defer></script><script src="../assets/js/visual-studio.js?v=1" defer></script>';
require_once __DIR__ . '/admin_header.php';
?>

<div class="admin-page-head">
    <nav class="admin-crumb">
        <a href="admin_dashboard.php"><?= tr('แดชบอร์ด', 'Dashboard') ?></a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span>Visual Builder</span>
    </nav>
    <h1 class="admin-page-title">Visual Builder</h1>
    <p class="admin-page-sub"><?= tr('เปิดหน้าแรกเดิม แล้วคลิกหรือเลือกรายการเพื่อแก้เฉพาะส่วนที่เลือก โดยคงโครงสร้างและการทำงานเดิมของ REPS', 'Edit the existing homepage by clicking an element or selecting it in Layers. Existing REPS structure and behavior are preserved.') ?></p>
</div>

<?php if (isset($_GET['saved'])): ?>
    <div class="vb-studio-message vb-studio-message--success" role="status"><?= tr('บันทึกการเปลี่ยนแปลงแล้ว', 'Changes saved') ?></div>
<?php elseif ($errorMessage !== ''): ?>
    <div class="vb-studio-message vb-studio-message--error" role="alert"><?= vb_studio_escape($errorMessage) ?></div>
<?php endif; ?>

<div class="vb-studio-form" id="vbStudioForm">
    <div class="vb-studio-toolbar">
        <strong><?= $selectedPage === 'home' ? tr('หน้าแรก', 'Homepage') : tr(VISUAL_BUILDER_CONTENT_PAGES[$selectedPage]['label_th'], VISUAL_BUILDER_CONTENT_PAGES[$selectedPage]['label_en']) ?></strong>
        <span><?= tr('ตัวอย่างหน้าเดิม · โหมดแก้ไขส่วนตัว', 'Existing page · private editing preview') ?></span>
        <label class="vb-studio-viewport">
            <span><?= tr('หน้าเว็บ', 'Page') ?></span>
            <select id="vbStudioPage">
                <option value="home"<?= $selectedPage === 'home' ? ' selected' : '' ?>><?= tr('หน้าแรก', 'Homepage') ?></option>
                <?php foreach (VISUAL_BUILDER_CONTENT_PAGES as $pageKey => $pageInfo): ?>
                    <option value="<?= vb_studio_escape($pageKey) ?>" data-path="<?= vb_studio_escape($pageInfo['path']) ?>"<?= $selectedPage === $pageKey ? ' selected' : '' ?>><?= tr($pageInfo['label_th'], $pageInfo['label_en']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="vb-studio-viewport">
            <span class="sr-only"><?= tr('ขนาดตัวอย่าง', 'Preview size') ?></span>
            <select id="vbStudioViewport">
                <option value="desktop"><?= tr('เดสก์ท็อป', 'Desktop') ?></option>
                <option value="tablet"><?= tr('แท็บเล็ต', 'Tablet') ?></option>
                <option value="mobile"><?= tr('มือถือ', 'Mobile') ?></option>
            </select>
        </label>
        <div class="vb-studio-history-tools" role="toolbar" aria-label="<?= tr('เครื่องมือแก้ไข', 'Editing tools') ?>">
            <button type="button" class="admin-btn admin-btn-secondary" data-vb-home-undo disabled><?= tr('เลิกทำ', 'Undo') ?></button>
            <button type="button" class="admin-btn admin-btn-secondary" data-vb-home-redo disabled><?= tr('ทำซ้ำ', 'Redo') ?></button>
            <button type="button" class="admin-btn admin-btn-secondary" data-vb-home-copy disabled><?= tr('คัดลอก', 'Copy') ?></button>
            <button type="button" class="admin-btn admin-btn-secondary" data-vb-home-paste disabled><?= tr('วาง', 'Paste') ?></button>
        </div>
        <button type="button" class="admin-btn admin-btn-secondary" data-vb-theme><?= tr('ธีมเว็บไซต์', 'Site theme') ?></button>
        <a class="admin-btn admin-btn-secondary" href="admin_site.php?tab=menus"><?= tr('Navbar / Footer', 'Navbar / Footer') ?></a>
        <a class="admin-btn admin-btn-secondary" href="admin_pages.php"><?= tr('หน้าเว็บ (CMS)', 'Web pages (CMS)') ?></a>
    </div>
    <div class="vb-studio-workspace">
        <aside class="vb-studio-sidebar">
            <section class="vb-studio-panel">
                <h2><?= tr('Layers', 'Layers') ?></h2>
                <div id="vbStudioLayers" class="vb-studio-layers" aria-label="<?= tr('เลือกองค์ประกอบหน้าเว็บ', 'Select a page element') ?>"></div>
            </section>
            <section class="vb-studio-panel">
                <h2><?= tr('Inspector', 'Inspector') ?></h2>
                <div id="vbStudioInspector" class="vb-studio-inspector" aria-live="polite">
                    <p><?= tr('คลิกองค์ประกอบบนหน้าเดิม หรือเลือก Layer เพื่อแก้ไข', 'Click an element on the existing page or choose a layer to edit it.') ?></p>
                </div>
            </section>
            <?php if ($selectedPage !== 'home'): ?>
                <section class="vb-studio-panel">
                    <h2><?= tr('Revision history', 'Revision history') ?></h2>
                    <?php if ($pageRevisions === []): ?>
                        <p class="vb-studio-empty"><?= tr('ยังไม่มีประวัติการแก้ไขหน้านี้', 'No revisions for this page yet.') ?></p>
                    <?php else: ?>
                        <ol class="vb-studio-revisions">
                            <?php foreach ($pageRevisions as $revision): ?>
                                <li>
                                    <span><?= vb_studio_escape(date('d/m/Y H:i', strtotime((string)$revision['created_at']))) ?> · <?= vb_studio_escape(trim($revision['first_name'] . ' ' . $revision['last_name'])) ?></span>
                                    <small><?= vb_studio_escape($revision['note']) ?> · <?= vb_studio_escape($revision['entity_id'] !== '' ? $revision['entity_id'] : $revision['entity_type']) ?></small>
                                    <form method="post" action="admin_visual_builder.php?page=<?= vb_studio_escape($selectedPage) ?>">
                                        <input type="hidden" name="csrf" value="<?= vb_studio_escape($_SESSION['admin_csrf']) ?>">
                                        <input type="hidden" name="page" value="<?= vb_studio_escape($selectedPage) ?>">
                                        <input type="hidden" name="revision_id" value="<?= (int)$revision['id'] ?>">
                                        <button type="submit" name="action" value="<?= $selectedPage === 'home' ? 'restore_home_revision' : 'restore_content_revision' ?>" class="admin-btn admin-btn-secondary" data-cms-confirm="<?= vb_studio_escape(tr('กู้คืนรายการนี้หรือไม่? จะบันทึกเป็น revision ใหม่', 'Restore this revision? A new revision will be recorded.')) ?>"><?= tr('กู้คืน', 'Restore') ?></button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </aside>
        <div class="vb-studio-canvas">
            <iframe
                id="vbStudioFrame"
                title="<?= tr('ตัวอย่างหน้าเว็บ', 'Page preview') ?>"
                src="<?= vb_studio_escape($previewPath) ?>"
                data-vb-page-target="<?= vb_studio_escape($selectedPage) ?>"
                data-vb-locale="<?= vb_studio_escape(app_lang()) ?>"
                data-vb-csrf="<?= vb_studio_escape($_SESSION['admin_csrf']) ?>"
                data-vb-current-logo="<?= $currentLogoId ?>"
                data-vb-legacy-logo="<?= $legacyLogo ? '1' : '0' ?>"
                loading="eager"></iframe>
        </div>
    </div>
    <template id="vbStudioLogoOptions">
        <?php if ($legacyLogo): ?><option value="keep"><?= tr('คงโลโก้เดิม', 'Keep current logo') ?></option><?php endif; ?>
        <option value="0"><?= tr('ใช้โลโก้ตั้งต้น', 'Use default logo') ?></option>
        <?php foreach ($media as $item): ?>
            <option value="<?= (int)$item['id'] ?>"><?= vb_studio_escape($item['title'] !== '' ? $item['title'] : ('#' . $item['id'])) ?></option>
        <?php endforeach; ?>
    </template>
    <template id="vbStudioThemeConfig">
        <form method="post" class="vb-inspector-form">
            <input type="hidden" name="csrf" value="<?= vb_studio_escape($_SESSION['admin_csrf']) ?>">
            <input type="hidden" name="action" value="save_theme">
            <label><?= tr('ชุดสี', 'Palette') ?><select name="theme_color"><?php foreach (APP_THEME_PALETTES as $key => $palette): ?><option value="<?= vb_studio_escape($key) ?>" <?= ($settings['theme_color'] ?? 'default') === $key ? 'selected' : '' ?>><?= vb_studio_escape(tr(ucfirst($key), ucfirst($key))) ?></option><?php endforeach; ?></select></label>
            <label><?= tr('ฟอนต์', 'Font') ?><select name="theme_font"><?php foreach (SITE_FONTS as $key => [$family]): ?><option value="<?= vb_studio_escape($key) ?>" <?= ($settings['theme_font'] ?? 'kanit') === $key ? 'selected' : '' ?>><?= vb_studio_escape($family) ?></option><?php endforeach; ?></select></label>
            <label><?= tr('สีหลัก', 'Primary color') ?><input type="color" name="primary_color" value="<?= vb_studio_escape(site_hex($settings['primary_color'] ?? '') ?: $activePalette['primary']) ?>"></label>
            <label><?= tr('สีเน้น', 'Accent color') ?><input type="color" name="theme_accent_color" value="<?= vb_studio_escape(site_hex($settings['theme_accent_color'] ?? '') ?: $activePalette['accent']) ?>"></label>
            <label><?= tr('สีพื้นหลัง', 'Background color') ?><input type="color" name="theme_bg_color" value="<?= vb_studio_escape(site_hex($settings['theme_bg_color'] ?? '') ?: '#FAFAF8') ?>"></label>
            <label><?= tr('มุมปุ่มและช่องกรอก', 'Button and field corners') ?><select name="theme_radius"><?php foreach (SITE_RADIUS as $key => $value): ?><option value="<?= vb_studio_escape($key) ?>" <?= ($settings['theme_radius'] ?? 'default') === $key ? 'selected' : '' ?>><?= vb_studio_escape(tr(ucfirst($key), ucfirst($key))) ?></option><?php endforeach; ?></select></label>
            <p><?= tr('ใช้ระบบธีมเดิมของ REPS สีและฟอนต์อาจมีผลกับส่วนอื่นของระบบด้วย', 'Uses the existing REPS theme engine; colors and fonts may affect other themed screens too.') ?></p>
            <button type="submit" class="admin-btn"><?= tr('บันทึกธีม', 'Save theme') ?></button>
        </form>
    </template>
</div>

      </div>
<?php $footerBase = '../'; $footerVariant = 'app'; require __DIR__ . '/../includes/site_footer.php'; ?>
   </div>
</body>
</html>
