<?php
declare(strict_types=1);

/**
 * REPS · admin/admin_site.php — ปรับแต่งเว็บแบบไม่ต้องเขียนโค้ด (feedback-todo-v3 หัวข้อ 6 เฟส 1)
 *
 * แท็บ: ข้อมูลเว็บไซต์ · ธีม · เมนู · ข้อความ · Footer · คลังสื่อ
 * - ทุกค่าเป็นข้อความล้วนหรือค่าจากรายการที่กำหนด (ไม่รับ HTML/CSS/JavaScript) และ escape ตอนแสดงผล
 * - ทุกส่วนมีปุ่มคืนค่าเริ่มต้น และทุกการบันทึกเก็บประวัติใน site_revisions (ใครแก้ เมื่อไร)
 * - ผลแสดงทั้งเว็บผ่าน includes/site_custom.php และ includes/site_menu.php
 */

$page_title = 'ปรับแต่งเว็บ';
$current_page = 'site';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/site_config.php';
require_once __DIR__ . '/../includes/site_custom.php';
require_once __DIR__ . '/../includes/site_menu.php';
require_once __DIR__ . '/../includes/private_storage.php';
require_once __DIR__ . '/../includes/db_helpers.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

function h($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

const SITE_TABS = [
    'identity' => ['ข้อมูลเว็บไซต์', 'Site identity', 'fa-id-card'],
    'theme'    => ['ธีม', 'Theme', 'fa-palette'],
    'menus'    => ['เมนู', 'Menus', 'fa-bars'],
    'texts'    => ['ข้อความ', 'Texts', 'fa-font'],
    'footer'   => ['Footer', 'Footer', 'fa-shoe-prints'],
    'media'    => ['คลังสื่อ', 'Media library', 'fa-images'],
];
const SITE_DEFAULT_NAME = 'REPS - ระบบแจ้งซ่อมอุปกรณ์คอมพิวเตอร์';

$themes = ['default' => tr('ค่าเริ่มต้น (ดำ)', 'Default (black)'), 'blue' => tr('น้ำเงิน', 'Blue'), 'emerald' => tr('เขียว', 'Green'), 'rose' => tr('ชมพูแดง', 'Rose'), 'amber' => tr('ส้มอำพัน', 'Amber')];
$radii = ['square' => tr('เหลี่ยม', 'Square'), 'default' => tr('ค่าเริ่มต้น', 'Default'), 'round' => tr('โค้งมน', 'Rounded')];

$tab = is_string($_GET['tab'] ?? null) && isset(SITE_TABS[$_GET['tab']]) ? $_GET['tab'] : 'identity';
$area = is_string($_GET['area'] ?? null) && isset(SITE_MENU_AREAS[$_GET['area']]) ? $_GET['area'] : 'admin';

function site_setting_save(mysqli $conn, string $key, string $value): void
{
    db_exec($conn, 'INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', 'ss', [$key, $value]);
}

/** ค่าข้อความล้วน: ตัดช่องว่าง รวมบรรทัด และจำกัดความยาว */
function site_post_text(string $key, int $max, bool $multiline = false): string
{
    $raw = is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
    $raw = trim(str_replace(["\r\n", "\r"], "\n", $raw));
    if (!$multiline) {
        $raw = (string)preg_replace('/\s+/u', ' ', $raw);
    }
    if (mb_strlen($raw) > $max) {
        throw new AppUserException('ข้อความยาวเกิน ' . $max . ' ตัวอักษร');
    }
    return $raw;
}

function site_post_hex(string $key): string
{
    $raw = trim(is_string($_POST[$key] ?? null) ? $_POST[$key] : '');
    if ($raw !== '' && site_hex($raw) === '') {
        throw new AppUserException('สีต้องอยู่ในรูปแบบ #RRGGBB');
    }
    return site_hex($raw);
}

/** รูปจากคลังสื่อที่เลือก (id) หรืออัปโหลดใหม่ → path ที่จะเก็บใน settings · null = ไม่เปลี่ยน */
function site_pick_image(mysqli $conn, string $field, int $maxDim, ?int $userId): ?string
{
    if (isset($_FILES[$field . '_file']) && (int)$_FILES[$field . '_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        return site_media_add($conn, $_FILES[$field . '_file'], $field === 'logo' ? 'โลโก้' : 'ไอคอนแท็บ', $userId, $maxDim)['path'];
    }
    $choice = is_string($_POST[$field . '_media'] ?? null) ? $_POST[$field . '_media'] : 'keep';
    if ($choice === 'keep') {
        return null;
    }
    if ($choice === 'default') {
        return '';
    }
    $row = db_one($conn, 'SELECT file_path FROM site_media WHERE id = ?', 'i', [(int)$choice]);
    if (!$row) {
        throw new AppUserException('ไม่พบรูปที่เลือกในคลังสื่อ');
    }
    return (string)$row['file_path'];
}

/** ไฟล์โลโก้รุ่นเก่าที่ไม่ได้อยู่ในคลังสื่อ ลบทิ้งเมื่อถูกแทนที่ */
function site_drop_legacy_file(mysqli $conn, string $path): void
{
    if ($path !== '' && !db_one($conn, 'SELECT id FROM site_media WHERE file_path = ?', 's', [$path])) {
        app_delete_private_file($path);
    }
}

$redirect = 'admin_site.php?tab=' . $tab . ($tab === 'menus' ? '&area=' . $area : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['admin_csrf'], is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '')) {
        http_response_code(403);
        exit('คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $flash = ['message' => 'บันทึกแล้ว', 'type' => 'success'];
    $current = app_get_settings($conn);

    try {
        switch ($action) {
            case 'save_identity':
                $name = site_post_text('site_name', 100);
                if ($name === '') {
                    throw new AppUserException('กรุณาระบุชื่อเว็บไซต์');
                }
                $tagline = site_post_text('site_tagline', 200);
                $logo = site_pick_image($conn, 'logo', 800, $adminId);
                $favicon = site_pick_image($conn, 'favicon', 256, $adminId);
                db_transaction($conn, function () use ($conn, $name, $tagline, $logo, $favicon) {
                    site_setting_save($conn, 'site_name', $name);
                    site_setting_save($conn, 'site_tagline', $tagline);
                    if ($logo !== null) site_setting_save($conn, 'site_logo_path', $logo);
                    if ($favicon !== null) site_setting_save($conn, 'site_favicon_path', $favicon);
                });
                if ($logo !== null && ($current['site_logo_path'] ?? '') !== $logo) site_drop_legacy_file($conn, (string)($current['site_logo_path'] ?? ''));
                if ($favicon !== null && ($current['site_favicon_path'] ?? '') !== $favicon) site_drop_legacy_file($conn, (string)($current['site_favicon_path'] ?? ''));
                site_revision_save($conn, 'identity', '', ['site_name' => $name, 'site_tagline' => $tagline, 'site_logo_path' => $logo ?? ($current['site_logo_path'] ?? ''), 'site_favicon_path' => $favicon ?? ($current['site_favicon_path'] ?? '')], $adminId, 'บันทึก');
                break;

            case 'reset_identity':
                db_transaction($conn, function () use ($conn) {
                    site_setting_save($conn, 'site_name', SITE_DEFAULT_NAME);
                    site_setting_save($conn, 'site_tagline', '');
                    site_setting_save($conn, 'site_logo_path', '');
                    site_setting_save($conn, 'site_favicon_path', '');
                });
                site_drop_legacy_file($conn, (string)($current['site_logo_path'] ?? ''));
                site_drop_legacy_file($conn, (string)($current['site_favicon_path'] ?? ''));
                site_revision_save($conn, 'identity', '', ['site_name' => SITE_DEFAULT_NAME, 'site_tagline' => '', 'site_logo_path' => '', 'site_favicon_path' => ''], $adminId, 'คืนค่าเริ่มต้น');
                $flash['message'] = 'คืนค่าข้อมูลเว็บไซต์เป็นค่าเริ่มต้นแล้ว';
                break;

            case 'save_theme':
            case 'reset_theme':
                $reset = $action === 'reset_theme';
                $theme = [
                    'theme_color'        => $reset ? 'default' : (is_string($_POST['theme_color'] ?? null) && isset(APP_THEME_PALETTES[$_POST['theme_color']]) ? $_POST['theme_color'] : 'default'),
                    'primary_color'      => $reset ? '' : site_post_hex('primary_color'),
                    'theme_accent_color' => $reset ? '' : site_post_hex('theme_accent_color'),
                    'theme_bg_color'     => $reset ? '' : site_post_hex('theme_bg_color'),
                    'theme_font'         => $reset ? 'kanit' : (is_string($_POST['theme_font'] ?? null) && isset(SITE_FONTS[$_POST['theme_font']]) ? $_POST['theme_font'] : 'kanit'),
                    'theme_radius'       => $reset ? 'default' : (is_string($_POST['theme_radius'] ?? null) && array_key_exists($_POST['theme_radius'], SITE_RADIUS) ? $_POST['theme_radius'] : 'default'),
                ];
                db_transaction($conn, function () use ($conn, $theme) {
                    foreach ($theme as $key => $value) site_setting_save($conn, $key, $value);
                });
                site_revision_save($conn, 'theme', '', $theme, $adminId, $reset ? 'คืนค่าเริ่มต้น' : 'บันทึก');
                $flash['message'] = $reset ? 'คืนค่าธีมเป็นค่าเริ่มต้นแล้ว' : 'บันทึกธีมแล้ว';
                break;

            case 'save_menu':
                $defaults = site_menu_defaults()[$area];
                $allowedRoles = site_menu_area_roles($area);
                $existing = [];
                foreach (site_menu_rows($conn, $area) as $row) {
                    if ($row['item_key'] === null || $row['item_key'] === '') $existing[(int)$row['id']] = $row;
                }
                $posted = is_array($_POST['m'] ?? null) ? $_POST['m'] : [];
                $ops = [];
                $pos = 0;
                foreach ($posted as $m) {
                    if (!is_array($m)) continue;
                    $pos++;
                    $label = mb_substr(trim((string)($m['label'] ?? '')), 0, 80);
                    $labelEn = mb_substr(trim((string)($m['label_en'] ?? '')), 0, 80);
                    $key = is_string($m['key'] ?? null) ? $m['key'] : '';
                    if ($key !== '') {
                        if (!isset($defaults[$key])) continue;
                        $roles = array_values(array_intersect(is_array($m['roles'] ?? null) ? $m['roles'] : [], $defaults[$key][5]));
                        $visible = $defaults[$key][6] || ($m['visible'] ?? '') === '1' ? 1 : 0;
                        if ($defaults[$key][6] && !$roles) $roles = $defaults[$key][5];
                        $ops[] = ['builtin', $key, $label, $labelEn, $pos * 10, implode(',', $roles), $visible];
                        continue;
                    }
                    $id = (int)($m['id'] ?? 0);
                    if (!isset($existing[$id])) continue;
                    if (($m['delete'] ?? '') === '1') {
                        $ops[] = ['delete', $id];
                        continue;
                    }
                    $target = site_safe_link((string)($m['target'] ?? ''));
                    if ($label === '' || $target === null) {
                        throw new AppUserException('ลิงก์เพิ่มเติมต้องมีชื่อ และปลายทางต้องเป็นไฟล์ .php ในเว็บ หรือ URL http(s)');
                    }
                    $roles = array_values(array_intersect(is_array($m['roles'] ?? null) ? $m['roles'] : [], $allowedRoles));
                    $icon = in_array($m['icon'] ?? '', SITE_MENU_ICONS, true) ? (string)$m['icon'] : 'fa-link';
                    $group = $area === 'admin' && isset(SITE_MENU_GROUPS[$m['group'] ?? '']) ? (string)$m['group'] : 'links';
                    $ops[] = ['custom', $id, $label, $labelEn, $pos * 10, implode(',', $roles), ($m['visible'] ?? '') === '1' ? 1 : 0, $target, $icon, $group, ($m['new_tab'] ?? '') === '1' ? 1 : 0];
                }
                $newLabel = mb_substr(site_post_text('new_label', 80), 0, 80);
                if ($newLabel !== '') {
                    $target = site_safe_link(is_string($_POST['new_target'] ?? null) ? $_POST['new_target'] : '');
                    if ($target === null) {
                        throw new AppUserException('ปลายทางของลิงก์ใหม่ต้องเป็นไฟล์ .php ในเว็บ (เช่น page.php?slug=rules) หรือ URL ที่ขึ้นต้นด้วย http:// หรือ https://');
                    }
                    $roles = array_values(array_intersect(is_array($_POST['new_roles'] ?? null) ? $_POST['new_roles'] : [], $allowedRoles));
                    if (!$roles) $roles = $allowedRoles;
                    $icon = in_array($_POST['new_icon'] ?? '', SITE_MENU_ICONS, true) ? (string)$_POST['new_icon'] : 'fa-link';
                    $group = $area === 'admin' && isset(SITE_MENU_GROUPS[$_POST['new_group'] ?? '']) ? (string)$_POST['new_group'] : 'links';
                    $ops[] = ['insert', $newLabel, mb_substr(site_post_text('new_label_en', 80), 0, 80), ($pos + 1) * 10, implode(',', $roles), $target, $icon, $group, ($_POST['new_tab'] ?? '') === '1' ? 1 : 0];
                }
                db_transaction($conn, function () use ($conn, $ops, $area, $adminId) {
                    foreach ($ops as $op) {
                        switch ($op[0]) {
                            case 'builtin':
                                db_exec($conn, 'INSERT INTO site_menus (area, item_key, label, label_en, sort_order, visible_roles, is_visible, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE label = VALUES(label), label_en = VALUES(label_en), sort_order = VALUES(sort_order), visible_roles = VALUES(visible_roles), is_visible = VALUES(is_visible), updated_by = VALUES(updated_by)',
                                    'ssssisii', [$area, $op[1], $op[2], $op[3], $op[4], $op[5], $op[6], $adminId]);
                                break;
                            case 'delete':
                                db_exec($conn, 'DELETE FROM site_menus WHERE id = ? AND area = ? AND item_key IS NULL', 'is', [$op[1], $area]);
                                break;
                            case 'custom':
                                db_exec($conn, 'UPDATE site_menus SET label = ?, label_en = ?, sort_order = ?, visible_roles = ?, is_visible = ?, target = ?, icon = ?, group_key = ?, new_tab = ?, updated_by = ? WHERE id = ? AND area = ? AND item_key IS NULL',
                                    'ssisisssiiis', [$op[2], $op[3], $op[4], $op[5], $op[6], $op[7], $op[8], $op[9], $op[10], $adminId, $op[1], $area]);
                                break;
                            case 'insert':
                                db_exec($conn, 'INSERT INTO site_menus (area, item_key, label, label_en, sort_order, visible_roles, is_visible, target, icon, group_key, new_tab, updated_by) VALUES (?, NULL, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?)',
                                    'sssissssii', [$area, $op[1], $op[2], $op[3], $op[4], $op[5], $op[6], $op[7], $op[8], $adminId]);
                                break;
                        }
                    }
                });
                site_revision_save($conn, 'menu', $area, db_all($conn, 'SELECT item_key, label, label_en, target, icon, group_key, sort_order, visible_roles, is_visible, new_tab FROM site_menus WHERE area = ? ORDER BY sort_order', 's', [$area]), $adminId, 'บันทึก');
                $flash['message'] = 'บันทึกเมนูแล้ว';
                break;

            case 'reset_menu':
                db_exec($conn, 'DELETE FROM site_menus WHERE area = ?', 's', [$area]);
                site_revision_save($conn, 'menu', $area, [], $adminId, 'คืนค่าเริ่มต้น');
                $flash['message'] = 'คืนค่าเมนูเป็นค่าเริ่มต้นแล้ว (ลิงก์เพิ่มเติมของพื้นที่นี้ถูกลบ)';
                break;

            case 'save_text':
            case 'delete_text':
            case 'reset_texts':
                $map = site_text_overrides($conn);
                if ($action === 'reset_texts') {
                    $map = [];
                    $flash['message'] = 'คืนข้อความทั้งหมดเป็นค่าเริ่มต้นแล้ว';
                } else {
                    $source = trim((string)preg_replace('/\s+/u', ' ', is_string($_POST['source'] ?? null) ? $_POST['source'] : ''));
                    if ($source === '' || mb_strlen($source) > 300) {
                        throw new AppUserException('กรุณาระบุข้อความเดิมบนหน้าเว็บ (ไม่เกิน 300 ตัวอักษร)');
                    }
                    if (preg_match('/[\x{0E01}-\x{0E5B}]/u', $source) !== 1) {
                        throw new AppUserException('ข้อความเดิมต้องเป็นข้อความภาษาไทยที่แสดงบนหน้าเว็บ');
                    }
                    $th = site_post_text('text_th', 300);
                    $en = site_post_text('text_en', 300);
                    if ($action === 'delete_text' || ($th === '' && $en === '')) {
                        unset($map[$source]);
                        $flash['message'] = 'คืนข้อความ "' . $source . '" เป็นค่าเริ่มต้นแล้ว';
                    } else {
                        if (!isset($map[$source]) && count($map) >= 500) {
                            throw new AppUserException('แก้ข้อความได้สูงสุด 500 รายการ');
                        }
                        $map[$source] = ['th' => $th, 'en' => $en];
                        $flash['message'] = 'บันทึกข้อความแล้ว';
                    }
                }
                ksort($map);
                site_setting_save($conn, 'site_text_overrides', $map ? (string)json_encode($map, JSON_UNESCAPED_UNICODE) : '');
                site_revision_save($conn, 'texts', '', $map, $adminId, $action === 'reset_texts' ? 'คืนค่าเริ่มต้น' : 'บันทึก');
                break;

            case 'save_footer':
            case 'reset_footer':
                $reset = $action === 'reset_footer';
                $footer = [
                    'org_name'     => $reset ? '' : site_post_text('org_name', 150),
                    'org_unit'     => $reset ? '' : site_post_text('org_unit', 200),
                    'office_hours' => $reset ? '' : site_post_text('office_hours', 100),
                    'org_contact'  => $reset ? '' : site_post_text('org_contact', 200),
                    'footer_text'  => $reset ? '' : site_post_text('footer_text', 300, true),
                ];
                $links = [];
                if (!$reset) {
                    $labels = is_array($_POST['link_label'] ?? null) ? $_POST['link_label'] : [];
                    $urls = is_array($_POST['link_url'] ?? null) ? $_POST['link_url'] : [];
                    foreach ($labels as $i => $label) {
                        $label = mb_substr(trim((string)$label), 0, 60);
                        $url = trim((string)($urls[$i] ?? ''));
                        if ($label === '' && $url === '') continue;
                        $safe = site_safe_link($url);
                        if ($label === '' || $safe === null) {
                            throw new AppUserException('ลิงก์ใน footer ต้องมีชื่อ และปลายทางต้องเป็นไฟล์ .php ในเว็บ URL http(s) mailto: หรือ tel:');
                        }
                        $links[] = ['label' => $label, 'url' => $safe];
                    }
                    if (count($links) > 8) {
                        throw new AppUserException('ลิงก์ใน footer ได้สูงสุด 8 ลิงก์');
                    }
                }
                $footer['footer_links'] = $links ? (string)json_encode($links, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
                db_transaction($conn, function () use ($conn, $footer) {
                    foreach ($footer as $key => $value) site_setting_save($conn, $key, $value);
                });
                site_revision_save($conn, 'footer', '', $footer, $adminId, $reset ? 'คืนค่าเริ่มต้น' : 'บันทึก');
                $flash['message'] = $reset ? 'คืนค่า footer เป็นค่าเริ่มต้นแล้ว' : 'บันทึก footer แล้ว';
                break;

            case 'upload_media':
                if (!isset($_FILES['media_file']) || (int)$_FILES['media_file']['error'] === UPLOAD_ERR_NO_FILE) {
                    throw new AppUserException('กรุณาเลือกรูปที่จะอัปโหลด');
                }
                site_media_add($conn, $_FILES['media_file'], site_post_text('media_title', 150), $adminId);
                $flash['message'] = 'เพิ่มรูปเข้าคลังสื่อแล้ว';
                break;

            case 'rename_media':
                db_exec($conn, 'UPDATE site_media SET title = ? WHERE id = ?', 'si', [site_post_text('media_title', 150), (int)($_POST['media_id'] ?? 0)]);
                $flash['message'] = 'แก้ชื่อรูปแล้ว';
                break;

            case 'delete_media':
                $media = db_one($conn, 'SELECT id, file_path FROM site_media WHERE id = ?', 'i', [(int)($_POST['media_id'] ?? 0)]);
                if (!$media) {
                    throw new AppUserException('ไม่พบรูปนี้');
                }
                $used = site_media_usage($conn, (string)$media['file_path']);
                if ($used) {
                    throw new AppUserException('ลบไม่ได้ รูปนี้ใช้เป็น' . implode(' และ ', $used) . ' อยู่ กรุณาเปลี่ยนก่อน');
                }
                db_exec($conn, 'DELETE FROM site_media WHERE id = ?', 'i', [(int)$media['id']]);
                app_delete_private_file((string)$media['file_path']);
                $flash['message'] = 'ลบรูปแล้ว';
                break;

            default:
                throw new AppUserException('ไม่รู้จักคำสั่งนี้');
        }
    } catch (AppUserException $e) {
        $flash = ['message' => $e->getMessage(), 'type' => 'error'];
    } catch (Throwable $e) {
        error_log('admin_site ' . $action . ': ' . $e->getMessage());
        $flash = ['message' => 'บันทึกไม่สำเร็จ กรุณาลองใหม่', 'type' => 'error'];
    }
    $_SESSION['admin_flash'] = $flash;
    $back = is_string($_POST['back'] ?? null) && preg_match('/^[a-z_=&0-9%.\-]*$/i', $_POST['back']) === 1 ? $_POST['back'] : '';
    header('Location: ' . $redirect . ($back !== '' ? '&' . $back : ''), true, 303);
    exit;
}

$message = is_array($_SESSION['admin_flash'] ?? null) ? (string)($_SESSION['admin_flash']['message'] ?? '') : '';
$messageType = is_array($_SESSION['admin_flash'] ?? null) ? (string)($_SESSION['admin_flash']['type'] ?? 'success') : 'success';
unset($_SESSION['admin_flash']);

$s = app_get_settings($conn);
$media = db_all($conn, 'SELECT m.*, u.first_name FROM site_media m LEFT JOIN users u ON u.user_id = m.uploaded_by ORDER BY m.created_at DESC, m.id DESC');

/** การแก้ล่าสุดของส่วนนี้ */
function site_last_edit(mysqli $conn, string $type, string $id = ''): string
{
    $row = db_one($conn, 'SELECT r.created_at, r.note, u.first_name, u.last_name FROM site_revisions r LEFT JOIN users u ON u.user_id = r.created_by WHERE r.entity_type = ? AND r.entity_id = ? ORDER BY r.id DESC LIMIT 1', 'ss', [$type, $id]);
    if (!$row) {
        return tr('ยังไม่เคยแก้ไข (ใช้ค่าเริ่มต้น)', 'Never edited (defaults)');
    }
    $who = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: '-';
    return tr('แก้ล่าสุด', 'Last edited') . ' ' . date('d/m/Y H:i', strtotime((string)$row['created_at'])) . ' · ' . $who . ' · ' . $row['note'];
}

$csrfField = '<input type="hidden" name="csrf" value="' . h($_SESSION['admin_csrf']) . '">';

/** ปุ่มคืนค่าเริ่มต้น (ฟอร์มแยก ยืนยันก่อน) */
function site_reset_button(string $action, string $csrfField, string $confirm, string $label = ''): string
{
    return '<form method="post" class="inline" data-confirm-submit="' . h($confirm) . '">' . $csrfField
        . '<input type="hidden" name="action" value="' . h($action) . '">'
        . '<button class="admin-btn admin-btn-danger-outline"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> ' . h($label !== '' ? $label : tr('คืนค่าเริ่มต้น', 'Reset to default')) . '</button></form>';
}

/** ตัวเลือกรูปจากคลังสื่อ */
function site_media_picker(string $field, string $currentPath, array $media, string $help): string
{
    ob_start(); ?>
    <fieldset class="site-picker">
        <legend class="sr-only"><?= h($help) ?></legend>
        <label class="site-pick"><input type="radio" name="<?= h($field) ?>_media" value="keep" checked><span class="site-pick__box site-pick__box--text"><?= tr('ไม่เปลี่ยน', 'Keep') ?></span></label>
        <label class="site-pick"><input type="radio" name="<?= h($field) ?>_media" value="default"><span class="site-pick__box"><img src="../assets/images/logo/<?= $field === 'logo' ? 'reps-logo.svg' : 'reps-icon.svg' ?>" alt="" data-site-original></span><span class="site-pick__cap"><?= tr('ค่าเริ่มต้น', 'Default') ?></span></label>
        <?php foreach ($media as $m): ?>
            <label class="site-pick<?= $m['file_path'] === $currentPath ? ' is-current' : '' ?>">
                <input type="radio" name="<?= h($field) ?>_media" value="<?= (int)$m['id'] ?>">
                <span class="site-pick__box"><img src="../file.php?m=<?= (int)$m['id'] ?>" alt="" loading="lazy"></span>
                <span class="site-pick__cap"><?= h($m['title']) ?><?= $m['file_path'] === $currentPath ? ' · ' . tr('ใช้อยู่', 'in use') : '' ?></span>
            </label>
        <?php endforeach; ?>
    </fieldset>
    <label class="mt-3 block text-sm"><?= tr('หรืออัปโหลดรูปใหม่ (เพิ่มเข้าคลังสื่อด้วย)', 'Or upload a new image (also added to the library)') ?>
        <input type="file" name="<?= h($field) ?>_file" accept="image/png,image/jpeg,image/webp" data-photo-input data-max-mb="3">
    </label>
    <p class="mt-1 text-xs text-gray-500"><?= h($help) ?></p>
    <?php return (string)ob_get_clean();
}

$admin_extra_head = <<<'HTML'
<style>
.site-tabs{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:20px}
.site-tabs a{display:inline-flex;align-items:center;gap:8px;min-height:40px;padding:0 14px;border:1px solid var(--admin-line);border-radius:999px;background:#fff;color:#55534F;font-size:13.5px;text-decoration:none}
.site-tabs a:hover{border-color:var(--admin-line-strong);color:var(--admin-ink)}
.site-tabs a[aria-current="page"]{background:var(--admin-ink);border-color:var(--admin-ink);color:#fff}
.site-tabs i{font-size:12px}
.site-head{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px}
.site-meta{color:var(--admin-muted);font-size:12px}
.site-picker{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:10px}
.site-pick{position:relative;display:flex;flex-direction:column;gap:6px;cursor:pointer}
.site-pick input{position:absolute;opacity:0;pointer-events:none}
.site-pick__box{display:flex;align-items:center;justify-content:center;height:72px;padding:8px;border:1px solid var(--admin-line);border-radius:12px;background:repeating-conic-gradient(#F4F3F1 0 25%,#fff 0 50%) 0 0/14px 14px}
.site-pick__box--text{background:#fff;color:var(--admin-muted);font-size:13px}
.site-pick__box img{max-width:100%;max-height:100%;object-fit:contain}
.site-pick__cap{overflow:hidden;color:var(--admin-muted);font-size:12px;text-overflow:ellipsis;white-space:nowrap}
.site-pick input:checked + .site-pick__box{border-color:var(--admin-ink);box-shadow:0 0 0 2px var(--admin-ink)}
.site-pick input:focus-visible + .site-pick__box{outline:2px solid var(--admin-ink);outline-offset:2px}
.site-color{display:flex;gap:8px;align-items:center}
.site-color input[type=color]{width:42px;height:42px;padding:2px;border:1px solid var(--admin-line-strong);border-radius:10px;background:#fff;flex:none;cursor:pointer}
.site-preview{padding:18px;border:1px solid var(--admin-line);border-radius:14px;background:var(--pv-bg,#FAFAF8);font-family:var(--pv-font,'Kanit'),sans-serif}
.site-preview .pv-card{padding:16px;border:1px solid #ECEAE6;border-radius:12px;background:#fff}
.site-preview .pv-btn{display:inline-flex;align-items:center;min-height:38px;padding:0 14px;border:1px solid var(--pv-primary,#161616);border-radius:var(--pv-btn-r,10px);background:var(--pv-primary,#161616);color:#fff;font-size:13px}
.site-preview .pv-btn2{background:#fff;color:#55534F;border-color:#D9D6D0}
.site-preview .pv-input{display:block;width:100%;min-height:38px;margin-top:10px;padding:0 12px;border:1px solid #D9D6D0;border-radius:var(--pv-field-r,10px);background:#fff;color:#6B6862;font-size:13px;line-height:38px}
.site-preview .pv-tab{display:inline-block;padding:6px 2px;border-bottom:2px solid var(--pv-accent,#D97706);font-size:13px}
.site-menu-table td,.site-menu-table th{padding:8px 6px;vertical-align:top}
.site-menu-table input[type=text]{width:100%;min-width:120px}
.site-menu-table .is-hidden-row{opacity:.55}
.site-move{display:inline-flex;flex-direction:column;gap:2px}
.site-move button{display:inline-flex;align-items:center;justify-content:center;width:30px;height:24px;min-height:24px!important;border:1px solid var(--admin-line);border-radius:7px;background:#fff;color:#55534F;font-size:11px;cursor:pointer}
.site-move button:hover{border-color:var(--admin-line-strong);color:var(--admin-ink)}
.site-roles{display:flex;flex-wrap:wrap;gap:4px 10px;font-size:12.5px}
.site-media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px}
.site-media-item{border:1px solid var(--admin-line);border-radius:14px;background:#fff;overflow:hidden}
.site-media-item .site-pick__box{height:120px;border:0;border-bottom:1px solid var(--admin-line);border-radius:0}
.site-media-item .body{padding:10px;font-size:12px;color:var(--admin-muted)}
.site-text-row td{padding:10px 8px;vertical-align:top}
@media (max-width:767px){.site-menu-table thead{display:none}.site-menu-table tr{display:block;padding:10px 0;border-bottom:1px solid var(--admin-line)}.site-menu-table td{display:block;padding:4px 0;border:0!important}}
</style>
HTML;

require_once __DIR__ . '/admin_header.php';
?>

<div class="admin-page-head">
    <nav class="admin-crumb">
        <a href="admin_dashboard.php"><?= tr('แดชบอร์ด', 'Dashboard') ?></a>
        <i class="fa-solid fa-chevron-right"></i>
        <span><?= tr('ปรับแต่งเว็บ', 'Customize site') ?></span>
    </nav>
    <h1 class="admin-page-title"><?= tr('ปรับแต่งเว็บ', 'Customize site') ?></h1>
    <p class="admin-page-sub"><?= tr('เปลี่ยนชื่อ โลโก้ สี ฟอนต์ เมนู ข้อความ และ footer ของทั้งเว็บได้เองโดยไม่ต้องแก้โค้ด ทุกส่วนคืนค่าเริ่มต้นได้ และระบบบันทึกว่าใครแก้เมื่อไร', 'Change the name, logo, colours, font, menus, texts and footer of the whole site without code. Every section can be reset, and each change records who made it and when.') ?></p>
</div>

<?php if ($message): ?>
    <div class="mb-4 rounded-lg border p-3 text-sm <?= $messageType === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' ?>" role="status"><?= h($message) ?></div>
<?php endif; ?>

<nav class="site-tabs" aria-label="<?= tr('ส่วนที่ปรับแต่งได้', 'Sections') ?>">
    <?php foreach (SITE_TABS as $key => [$th, $en, $icon]): ?>
        <a href="admin_site.php?tab=<?= h($key) ?>"<?= $key === $tab ? ' aria-current="page"' : '' ?>><i class="fa-solid <?= h($icon) ?>" aria-hidden="true"></i><?= tr($th, $en) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($tab === 'identity'): ?>
    <div class="site-head">
        <span class="site-meta"><?= h(site_last_edit($conn, 'identity')) ?></span>
        <?= site_reset_button('reset_identity', $csrfField, tr('คืนชื่อเว็บ คำโปรย โลโก้ และไอคอนแท็บเป็นค่าเริ่มต้น?', 'Reset name, tagline, logo and tab icon?')) ?>
    </div>
    <form method="post" enctype="multipart/form-data" class="space-y-6">
        <?= $csrfField ?><input type="hidden" name="action" value="save_identity">
        <div class="admin-tray">
            <div class="admin-tray-label"><span><?= tr('ชื่อและคำโปรย', 'Name and tagline') ?></span></div>
            <section class="admin-card p-5 grid gap-4 sm:grid-cols-2 text-sm">
                <label class="block"><?= tr('ชื่อเว็บไซต์', 'Site name') ?> *
                    <input name="site_name" required maxlength="100" value="<?= h($s['site_name'] ?? '') ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                    <span class="mt-1 block text-xs text-gray-500"><?= tr('แสดงใน footer ชื่อแท็บของหน้าเข้าสู่ระบบ และข้อความแจ้งเตือน', 'Shown in the footer, sign-in tab title and notifications') ?></span>
                </label>
                <label class="block"><?= tr('คำโปรย', 'Tagline') ?>
                    <input name="site_tagline" maxlength="200" value="<?= h($s['site_tagline'] ?? '') ?>" placeholder="<?= tr('เช่น แจ้งซ่อมง่าย ติดตามได้ทุกขั้นตอน', 'e.g. Easy repair requests, tracked at every step') ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                    <span class="mt-1 block text-xs text-gray-500"><?= tr('แสดงใต้ชื่อเว็บใน footer และใช้เป็นคำอธิบายหน้าแรกในผลค้นหา', 'Shown under the site name in the footer and used as the homepage description') ?></span>
                </label>
            </section>
        </div>
        <div class="admin-tray">
            <div class="admin-tray-label"><span><?= tr('โลโก้', 'Logo') ?></span></div>
            <section class="admin-card p-5 text-sm">
                <?= site_media_picker('logo', (string)($s['site_logo_path'] ?? ''), $media, tr('PNG/JPG/WEBP ไม่เกิน 3 MB ระบบย่อให้ด้านยาวไม่เกิน 800 px · แนะนำพื้นโปร่งใส สูงประมาณ 64 px ขึ้นไป', 'PNG/JPG/WEBP up to 3 MB, resized to 800 px · transparent background recommended')) ?>
            </section>
        </div>
        <div class="admin-tray">
            <div class="admin-tray-label"><span><?= tr('ไอคอนแท็บเบราว์เซอร์ (favicon)', 'Browser tab icon (favicon)') ?></span></div>
            <section class="admin-card p-5 text-sm">
                <?= site_media_picker('favicon', (string)($s['site_favicon_path'] ?? ''), $media, tr('ใช้รูปสี่เหลี่ยมจัตุรัส ระบบย่อเหลือ 256 px', 'Use a square image; resized to 256 px')) ?>
            </section>
        </div>
        <div class="flex justify-end"><button class="admin-btn"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> <?= tr('บันทึก', 'Save') ?></button></div>
    </form>

<?php elseif ($tab === 'theme'):
    $palette = APP_THEME_PALETTES[$s['theme_color'] ?? 'default'] ?? APP_THEME_PALETTES['default']; ?>
    <div class="site-head">
        <span class="site-meta"><?= h(site_last_edit($conn, 'theme')) ?></span>
        <?= site_reset_button('reset_theme', $csrfField, tr('คืนสี ฟอนต์ และความโค้งมุมเป็นค่าเริ่มต้น?', 'Reset colours, font and corners?')) ?>
    </div>
    <form method="post" class="grid gap-6 lg:grid-cols-[1fr_340px]" id="themeForm">
        <?= $csrfField ?><input type="hidden" name="action" value="save_theme">
        <div class="admin-tray">
            <div class="admin-tray-label"><span><?= tr('สีและรูปแบบ', 'Colours and style') ?></span></div>
            <section class="admin-card p-5 grid gap-4 sm:grid-cols-2 text-sm">
                <label class="block"><?= tr('ชุดสีสำเร็จรูป', 'Preset') ?>
                    <select name="theme_color" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" data-pv="preset">
                        <?php foreach ($themes as $key => $label): ?>
                            <option value="<?= h($key) ?>" data-primary="<?= h(APP_THEME_PALETTES[$key]['primary']) ?>" data-accent="<?= h(APP_THEME_PALETTES[$key]['accent']) ?>" <?= ($s['theme_color'] ?? 'default') === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block"><?= tr('ฟอนต์', 'Font') ?>
                    <select name="theme_font" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" data-pv="font">
                        <?php foreach (SITE_FONTS as $key => [$family]): ?>
                            <option value="<?= h($key) ?>" data-family="<?= h($family) ?>" <?= ($s['theme_font'] ?? 'kanit') === $key ? 'selected' : '' ?>><?= h($family) ?><?= $key === 'kanit' ? ' (' . tr('ค่าเริ่มต้น', 'default') . ')' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php foreach ([
                    'primary_color' => [tr('สีหลัก (ปุ่ม แถบเมนู)', 'Primary (buttons, menus)'), $palette['primary']],
                    'theme_accent_color' => [tr('สีรอง (ไฮไลต์ เมนูที่เลือก ตัวเลขแจ้งเตือน)', 'Accent (highlights, current menu, badges)'), $palette['accent']],
                    'theme_bg_color' => [tr('สีพื้นหลังหน้าทำงาน', 'Page background'), '#FAFAF8'],
                ] as $key => [$label, $fallback]): ?>
                    <label class="block"><?= h($label) ?>
                        <span class="site-color mt-1">
                            <input type="color" value="<?= h(site_hex($s[$key] ?? '') ?: $fallback) ?>" data-color-for="<?= h($key) ?>" aria-label="<?= h($label) ?>">
                            <input name="<?= h($key) ?>" maxlength="7" pattern="#[0-9A-Fa-f]{6}" value="<?= h($s[$key] ?? '') ?>" placeholder="<?= h($fallback) ?> (<?= tr('ว่าง = ค่าเดิม', 'empty = default') ?>)" class="w-full rounded-lg border border-gray-300 px-3 py-2 font-mono" data-pv="<?= h($key) ?>">
                        </span>
                    </label>
                <?php endforeach; ?>
                <label class="block"><?= tr('ความโค้งมุมปุ่มและช่องกรอก', 'Button and field corners') ?>
                    <select name="theme_radius" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" data-pv="radius">
                        <?php foreach ($radii as $key => $label): ?>
                            <option value="<?= h($key) ?>" <?= ($s['theme_radius'] ?? 'default') === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <p class="sm:col-span-2 text-xs text-gray-500"><?= tr('สีพื้นหลังไม่เปลี่ยนหน้าเข้าสู่ระบบ (พื้นเข้ม) หน้าแรก จอสรุปงาน และใบรับรองที่พิมพ์ · เลือกสีที่ตัวอักษรขาวบนสีหลักยังอ่านง่าย', 'Background does not change the sign-in page, homepage, wallboard or printed certificate · keep white text readable on the primary colour') ?></p>
            </section>
        </div>
        <div class="admin-tray">
            <div class="admin-tray-label"><span><?= tr('ตัวอย่าง', 'Preview') ?></span></div>
            <section class="admin-card p-4">
                <div class="site-preview" id="themePreview">
                    <div class="pv-card">
                        <div style="font-weight:600;font-size:15px;color:#161616"><?= tr('ใบงาน LB-2569-XXXX', 'Job LB-2569-XXXX') ?></div>
                        <div class="pv-tab"><?= tr('งานของฉัน', 'My jobs') ?></div>
                        <span class="pv-input"><?= tr('ค้นหาเลขใบงาน', 'Search job number') ?></span>
                        <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
                            <span class="pv-btn"><?= tr('บันทึก', 'Save') ?></span>
                            <span class="pv-btn pv-btn2"><?= tr('ยกเลิก', 'Cancel') ?></span>
                        </div>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500"><?= tr('ตัวอย่างเปลี่ยนทันทีที่เลือก ยังไม่มีผลกับเว็บจนกว่าจะกดบันทึก', 'Preview updates instantly; the site changes only after saving') ?></p>
            </section>
        </div>
        <div class="lg:col-span-2 flex justify-end"><button class="admin-btn"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> <?= tr('บันทึกธีม', 'Save theme') ?></button></div>
    </form>
    <script>
    (function () {
        var form = document.getElementById('themeForm'), pv = document.getElementById('themePreview');
        if (!form || !pv) return;
        var hex = /^#[0-9A-Fa-f]{6}$/;
        var loaded = {};
        function val(name) { var el = form.querySelector('[name="' + name + '"]'); return el ? el.value.trim() : ''; }
        function update() {
            var preset = form.querySelector('[data-pv="preset"]');
            var opt = preset.options[preset.selectedIndex];
            var primary = hex.test(val('primary_color')) ? val('primary_color') : opt.dataset.primary;
            var accent = hex.test(val('theme_accent_color')) ? val('theme_accent_color') : opt.dataset.accent;
            var bg = hex.test(val('theme_bg_color')) ? val('theme_bg_color') : '#FAFAF8';
            var font = form.querySelector('[data-pv="font"]');
            var fopt = font.options[font.selectedIndex];
            var family = fopt.dataset.family;
            if (!loaded[family] && family !== 'Kanit') {
                loaded[family] = true;
                var link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = 'https://fonts.googleapis.com/css2?family=' + encodeURIComponent(family).replace(/%20/g, '+') + ':wght@400;600&display=swap';
                document.head.appendChild(link);
            }
            var radius = val('theme_radius');
            pv.style.setProperty('--pv-primary', primary);
            pv.style.setProperty('--pv-accent', accent);
            pv.style.setProperty('--pv-bg', bg);
            pv.style.setProperty('--pv-font', "'" + family + "'");
            pv.style.setProperty('--pv-btn-r', radius === 'square' ? '4px' : radius === 'round' ? '999px' : '10px');
            pv.style.setProperty('--pv-field-r', radius === 'square' ? '4px' : radius === 'round' ? '14px' : '10px');
        }
        form.querySelectorAll('[data-color-for]').forEach(function (picker) {
            var text = form.querySelector('[name="' + picker.dataset.colorFor + '"]');
            picker.addEventListener('input', function () { text.value = picker.value.toUpperCase(); update(); });
            text.addEventListener('input', function () { if (hex.test(text.value.trim())) picker.value = text.value.trim(); update(); });
        });
        form.addEventListener('change', update);
        update();
    })();
    </script>

<?php elseif ($tab === 'menus'):
    $items = site_menu_all($conn, $area);
    $areaRoles = site_menu_area_roles($area);
    $roleNames = ['admin' => tr('ผู้ดูแล', 'Admin'), 'staff' => tr('สตาฟ', 'Staff'), 'user' => tr('ผู้แจ้ง', 'Requester')]; ?>
    <nav class="site-tabs" aria-label="<?= tr('พื้นที่เมนู', 'Menu areas') ?>" style="margin-top:-8px">
        <?php foreach (SITE_MENU_AREAS as $key => [$th, $en]): ?>
            <a href="admin_site.php?tab=menus&amp;area=<?= h($key) ?>"<?= $key === $area ? ' aria-current="page"' : '' ?>><?= tr($th, $en) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="site-head">
        <span class="site-meta"><?= h(site_last_edit($conn, 'menu', $area)) ?></span>
        <?= site_reset_button('reset_menu', $csrfField, tr('คืนเมนูพื้นที่นี้เป็นค่าเริ่มต้น? ชื่อ ลำดับ และลิงก์เพิ่มเติมของพื้นที่นี้จะถูกลบ', 'Reset this menu? Names, order and extra links in this area will be removed.')) ?>
    </div>
    <form method="post" class="space-y-6" id="menuForm">
        <?= $csrfField ?><input type="hidden" name="action" value="save_menu">
        <div class="admin-tray">
            <div class="admin-tray-label"><span><?= tr(SITE_MENU_AREAS[$area][0], SITE_MENU_AREAS[$area][1]) ?></span><span><?= tr('เลื่อนขึ้นลงเพื่อเรียงลำดับ', 'Use the arrows to reorder') ?></span></div>
            <section class="admin-card p-3 overflow-x-auto">
                <table class="site-menu-table w-full text-sm">
                    <thead><tr>
                        <th class="text-left"><?= tr('ลำดับ', 'Order') ?></th>
                        <th class="text-left"><?= tr('เมนู', 'Item') ?></th>
                        <th class="text-left"><?= tr('ชื่อที่แสดง (ไทย)', 'Label (Thai)') ?></th>
                        <th class="text-left"><?= tr('ชื่อที่แสดง (อังกฤษ)', 'Label (English)') ?></th>
                        <th class="text-left"><?= tr('ผู้ที่เห็น', 'Visible to') ?></th>
                        <th class="text-left"><?= tr('แสดง', 'Show') ?></th>
                    </tr></thead>
                    <tbody id="menuRows">
                    <?php foreach ($items as $i => $item):
                        $nm = 'm[' . $i . ']'; ?>
                        <tr class="<?= $item['visible'] ? '' : 'is-hidden-row' ?>">
                            <td>
                                <span class="site-move">
                                    <button type="button" data-move="up" aria-label="<?= tr('เลื่อนขึ้น', 'Move up') ?>"><i class="fa-solid fa-chevron-up" aria-hidden="true"></i></button>
                                    <button type="button" data-move="down" aria-label="<?= tr('เลื่อนลง', 'Move down') ?>"><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button>
                                </span>
                            </td>
                            <td>
                                <?php if ($item['custom']): ?>
                                    <input type="hidden" name="<?= $nm ?>[id]" value="<?= (int)$item['id'] ?>">
                                    <div class="flex items-center gap-2 font-medium"><i class="fa-solid <?= h($item['icon']) ?> text-xs" aria-hidden="true"></i><?= tr('ลิงก์เพิ่มเติม', 'Extra link') ?></div>
                                    <input type="text" name="<?= $nm ?>[target]" value="<?= h($item['target']) ?>" maxlength="255" class="mt-1 rounded-lg border border-gray-300 px-2 py-1 font-mono text-xs" aria-label="<?= tr('ปลายทาง', 'Target') ?>">
                                    <div class="mt-1 flex flex-wrap gap-2 text-xs">
                                        <select name="<?= $nm ?>[icon]" class="rounded-lg border border-gray-300" aria-label="<?= tr('ไอคอน', 'Icon') ?>">
                                            <?php foreach (SITE_MENU_ICONS as $icon): ?><option value="<?= h($icon) ?>" <?= $icon === $item['icon'] ? 'selected' : '' ?>><?= h(substr($icon, 3)) ?></option><?php endforeach; ?>
                                        </select>
                                        <?php if ($area === 'admin'): ?>
                                            <select name="<?= $nm ?>[group]" class="rounded-lg border border-gray-300" aria-label="<?= tr('กลุ่ม', 'Group') ?>">
                                                <?php foreach (SITE_MENU_GROUPS as $g => [$gth, $gen]): ?><option value="<?= h($g) ?>" <?= $g === $item['group'] ? 'selected' : '' ?>><?= tr($gth, $gen) ?></option><?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                        <label class="inline-flex items-center gap-1"><input type="checkbox" name="<?= $nm ?>[new_tab]" value="1" <?= $item['new_tab'] ? 'checked' : '' ?>> <?= tr('แท็บใหม่', 'New tab') ?></label>
                                        <label class="inline-flex items-center gap-1 text-red-700"><input type="checkbox" name="<?= $nm ?>[delete]" value="1"> <?= tr('ลบ', 'Delete') ?></label>
                                    </div>
                                <?php else: ?>
                                    <input type="hidden" name="<?= $nm ?>[key]" value="<?= h($item['key']) ?>">
                                    <div class="flex items-center gap-2"><i class="fa-solid <?= h($item['icon']) ?> text-xs text-gray-500" aria-hidden="true"></i><span><?= h(tr($item['default_th'], $item['default_en'])) ?></span></div>
                                    <div class="mt-1 font-mono text-xs text-gray-500"><?= h($item['target']) ?><?= $area === 'admin' ? ' · ' . h(tr(SITE_MENU_GROUPS[$item['group']][0], SITE_MENU_GROUPS[$item['group']][1])) : '' ?></div>
                                <?php endif; ?>
                            </td>
                            <td><input type="text" name="<?= $nm ?>[label]" maxlength="80" value="<?= h($item['label']) ?>" placeholder="<?= h($item['default_th']) ?>" class="rounded-lg border border-gray-300 px-2 py-1" aria-label="<?= tr('ชื่อไทย', 'Thai label') ?>"<?= $item['custom'] ? ' required' : '' ?>></td>
                            <td><input type="text" name="<?= $nm ?>[label_en]" maxlength="80" value="<?= h($item['label_en']) ?>" placeholder="<?= h($item['default_en']) ?>" class="rounded-lg border border-gray-300 px-2 py-1" aria-label="<?= tr('ชื่ออังกฤษ', 'English label') ?>"></td>
                            <td>
                                <div class="site-roles">
                                    <?php foreach ($item['allowed_roles'] as $r): ?>
                                        <label class="inline-flex items-center gap-1"><input type="checkbox" name="<?= $nm ?>[roles][]" value="<?= h($r) ?>" <?= in_array($r, $item['roles'], true) ? 'checked' : '' ?><?= count($item['allowed_roles']) === 1 ? ' disabled' : '' ?>> <?= h($roleNames[$r]) ?></label>
                                        <?php if (count($item['allowed_roles']) === 1): ?><input type="hidden" name="<?= $nm ?>[roles][]" value="<?= h($r) ?>"><?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($item['locked']): ?>
                                    <span class="text-xs text-gray-500" title="<?= tr('เมนูนี้ซ่อนไม่ได้ เพื่อไม่ให้เข้าหน้าตั้งค่าไม่ได้', 'This item cannot be hidden') ?>"><?= tr('แสดงเสมอ', 'Always') ?></span>
                                <?php else: ?>
                                    <label class="inline-flex items-center gap-1"><input type="checkbox" name="<?= $nm ?>[visible]" value="1" <?= $item['visible'] ? 'checked' : '' ?> data-visible> <?= tr('แสดง', 'Show') ?></label>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="px-2 pt-2 text-xs text-gray-500"><?= tr('ชื่อว่าง = ใช้ชื่อเดิมของระบบ · การซ่อนเมนูไม่ได้ปิดสิทธิ์เข้าหน้า (สิทธิ์ยังตรวจที่หน้าเว็บเหมือนเดิม) · ', 'Empty label = system default · Hiding an item does not remove access (permissions are still checked by each page) · ') ?><?= $area === 'account' ? tr('รายการ แก้ไขโปรไฟล์ เปลี่ยนรูป และออกจากระบบ แสดงเสมอ', 'Edit profile, Change photo and Sign out are always shown') : '' ?></p>
            </section>
        </div>
        <div class="admin-tray">
            <div class="admin-tray-label"><span><?= tr('เพิ่มลิงก์', 'Add a link') ?></span></div>
            <section class="admin-card p-5 grid gap-4 sm:grid-cols-2 text-sm">
                <label class="block"><?= tr('ชื่อ (ไทย)', 'Label (Thai)') ?>
                    <input name="new_label" maxlength="80" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                </label>
                <label class="block"><?= tr('ชื่อ (อังกฤษ ไม่บังคับ)', 'Label (English, optional)') ?>
                    <input name="new_label_en" maxlength="80" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                </label>
                <label class="block sm:col-span-2"><?= tr('ปลายทาง', 'Target') ?>
                    <input name="new_target" maxlength="255" placeholder="https://... <?= tr('หรือ', 'or') ?> history.php" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 font-mono">
                    <span class="mt-1 block text-xs text-gray-500"><?= tr('ไฟล์ .php ในเว็บนี้ (ไม่ต้องใส่ / หน้า) หรือ URL ที่ขึ้นต้นด้วย http:// หรือ https://', 'A .php page on this site (no leading /) or a URL starting with http:// or https://') ?></span>
                </label>
                <label class="block"><?= tr('ไอคอน', 'Icon') ?>
                    <select name="new_icon" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                        <?php foreach (SITE_MENU_ICONS as $icon): ?><option value="<?= h($icon) ?>"><?= h(substr($icon, 3)) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <?php if ($area === 'admin'): ?>
                    <label class="block"><?= tr('กลุ่ม', 'Group') ?>
                        <select name="new_group" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                            <?php foreach (SITE_MENU_GROUPS as $g => [$gth, $gen]): ?><option value="<?= h($g) ?>" <?= $g === 'links' ? 'selected' : '' ?>><?= tr($gth, $gen) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>
                <fieldset class="block">
                    <legend><?= tr('ผู้ที่เห็น', 'Visible to') ?></legend>
                    <div class="site-roles mt-2">
                        <?php foreach ($areaRoles as $r): ?>
                            <label class="inline-flex items-center gap-1"><input type="checkbox" name="new_roles[]" value="<?= h($r) ?>" checked> <?= h($roleNames[$r]) ?></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="new_tab" value="1"> <?= tr('เปิดในแท็บใหม่', 'Open in a new tab') ?></label>
            </section>
        </div>
        <div class="flex justify-end"><button class="admin-btn"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> <?= tr('บันทึกเมนู', 'Save menu') ?></button></div>
    </form>
    <script>
    (function () {
        var body = document.getElementById('menuRows');
        if (!body) return;
        body.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-move]');
            if (!btn) return;
            var row = btn.closest('tr');
            if (btn.dataset.move === 'up' && row.previousElementSibling) body.insertBefore(row, row.previousElementSibling);
            if (btn.dataset.move === 'down' && row.nextElementSibling) body.insertBefore(row.nextElementSibling, row);
            btn.focus();
        });
        body.addEventListener('change', function (e) {
            if (e.target.matches('[data-visible]')) e.target.closest('tr').classList.toggle('is-hidden-row', !e.target.checked);
        });
    })();
    </script>

<?php elseif ($tab === 'texts'):
    $overrides = site_text_overrides($conn);
    $dict = (array)((require __DIR__ . '/../lang/en.php')['exact'] ?? []);
    $q = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
    $results = [];
    if ($q !== '') {
        foreach ($dict as $th => $en) {
            if (mb_stripos((string)$th, $q) !== false || stripos((string)$en, $q) !== false) {
                $results[(string)$th] = (string)$en;
                if (count($results) >= 40) break;
            }
        }
        // ข้อความที่ไม่อยู่ในพจนานุกรมก็แก้ได้ ถ้าพิมพ์ตรงทั้งก้อน
        if (!isset($results[$q]) && preg_match('/[\x{0E01}-\x{0E5B}]/u', $q) === 1) {
            $results = [$q => (string)($dict[$q] ?? '')] + $results;
        }
    }
    $textRow = static function (string $source, string $defaultEn, array $ov, string $csrfField, string $q): string {
        ob_start(); ?>
        <tr class="site-text-row border-b border-gray-100">
            <td class="w-1/3">
                <div class="font-medium text-gray-900 break-words"><?= h($source) ?></div>
                <?php if ($defaultEn !== ''): ?><div class="mt-1 text-xs text-gray-500 break-words">EN: <?= h($defaultEn) ?></div><?php endif; ?>
            </td>
            <td>
                <form method="post" class="grid gap-2 sm:grid-cols-[1fr_1fr_auto] items-start">
                    <?= $csrfField ?><input type="hidden" name="action" value="save_text"><input type="hidden" name="source" value="<?= h($source) ?>">
                    <input type="hidden" name="back" value="<?= h($q !== '' ? 'q=' . rawurlencode($q) : '') ?>">
                    <input name="text_th" maxlength="300" value="<?= h($ov['th'] ?? '') ?>" placeholder="<?= tr('ข้อความไทยใหม่ (ว่าง = เดิม)', 'New Thai text (empty = default)') ?>" class="w-full rounded-lg border border-gray-300 px-2 py-1 text-sm" aria-label="<?= tr('ข้อความไทยใหม่', 'New Thai text') ?>">
                    <input name="text_en" maxlength="300" value="<?= h($ov['en'] ?? '') ?>" placeholder="<?= tr('ข้อความอังกฤษใหม่ (ว่าง = เดิม)', 'New English text (empty = default)') ?>" class="w-full rounded-lg border border-gray-300 px-2 py-1 text-sm" aria-label="<?= tr('ข้อความอังกฤษใหม่', 'New English text') ?>">
                    <span class="flex gap-2">
                        <button class="admin-btn"><?= tr('บันทึก', 'Save') ?></button>
                        <?php if ($ov): ?><button class="admin-btn admin-btn-secondary" name="action" value="delete_text" title="<?= tr('คืนค่าเดิม', 'Reset') ?>"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i><span class="sr-only"><?= tr('คืนค่าเดิม', 'Reset') ?></span></button><?php endif; ?>
                    </span>
                </form>
            </td>
        </tr>
        <?php return (string)ob_get_clean();
    }; ?>
    <div class="site-head">
        <span class="site-meta"><?= h(site_last_edit($conn, 'texts')) ?> · <?= tr('แก้ไว้', 'Overridden') ?> <?= count($overrides) ?> <?= tr('รายการ', 'items') ?></span>
        <?php if ($overrides): ?><?= site_reset_button('reset_texts', $csrfField, tr('คืนข้อความที่แก้ไว้ทั้งหมดเป็นค่าเริ่มต้น?', 'Reset all edited texts?'), tr('คืนค่าทั้งหมด', 'Reset all')) ?><?php endif; ?>
    </div>
    <div class="admin-tray">
        <div class="admin-tray-label"><span><?= tr('ค้นหาข้อความบนหน้าเว็บ', 'Find a text on the site') ?></span></div>
        <section class="admin-card p-5 text-sm">
            <form method="get" class="flex flex-wrap gap-2">
                <input type="hidden" name="tab" value="texts">
                <input name="q" value="<?= h($q) ?>" maxlength="300" placeholder="<?= tr('พิมพ์คำที่เห็นบนหน้าเว็บ เช่น แจ้งซ่อม', 'Type a word you see, e.g. แจ้งซ่อม') ?>" class="flex-1 min-w-[220px] rounded-lg border border-gray-300 px-3 py-2">
                <button class="admin-btn"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> <?= tr('ค้นหา', 'Search') ?></button>
            </form>
            <p class="mt-2 text-xs text-gray-500"><?= tr('ระบบแทนเฉพาะข้อความที่ตรงทั้งก้อน (ปุ่ม ป้ายกำกับ หัวข้อ) ทุกหน้าที่ใช้ข้อความนั้น ข้อความที่มีตัวเลขหรือข้อมูลปน และข้อมูลจากฐานข้อมูล (หมวดหมู่ แผนก) แก้ที่หน้าจัดการของข้อมูลนั้น · ข้อความอีเมลแก้ได้ในเฟสแม่แบบอีเมล', 'Only whole texts are replaced (buttons, labels, headings) on every page that uses them. Texts with numbers or data mixed in, and database data (categories, departments), are edited on their own pages.') ?></p>
        </section>
    </div>
    <?php if ($q !== ''): ?>
        <div class="admin-tray">
            <div class="admin-tray-label"><span><?= tr('ผลการค้นหา', 'Results') ?> (<?= count($results) ?>)</span></div>
            <section class="admin-card p-3 overflow-x-auto">
                <?php if (!$results): ?>
                    <p class="p-3 text-sm text-gray-500"><?= tr('ไม่พบข้อความนี้ ลองพิมพ์ให้สั้นลง', 'Not found. Try a shorter word.') ?></p>
                <?php else: ?>
                    <table class="w-full text-sm"><tbody>
                        <?php foreach ($results as $th => $en) echo $textRow($th, $en, $overrides[$th] ?? [], $csrfField, $q); ?>
                    </tbody></table>
                <?php endif; ?>
            </section>
        </div>
    <?php endif; ?>
    <div class="admin-tray">
        <div class="admin-tray-label"><span><?= tr('ข้อความที่แก้ไว้', 'Edited texts') ?> (<?= count($overrides) ?>)</span></div>
        <section class="admin-card p-3 overflow-x-auto">
            <?php if (!$overrides): ?>
                <p class="p-3 text-sm text-gray-500"><?= tr('ยังไม่ได้แก้ข้อความใด ค้นหาด้านบนเพื่อเริ่ม', 'No texts edited yet. Search above to start.') ?></p>
            <?php else: ?>
                <table class="w-full text-sm"><tbody>
                    <?php foreach ($overrides as $th => $ov) echo $textRow($th, (string)($dict[$th] ?? ''), $ov, $csrfField, $q); ?>
                </tbody></table>
            <?php endif; ?>
        </section>
    </div>

<?php elseif ($tab === 'footer'):
    $links = site_footer_links($conn);
    while (count($links) < 4) $links[] = ['label' => '', 'url' => '']; ?>
    <div class="site-head">
        <span class="site-meta"><?= h(site_last_edit($conn, 'footer')) ?></span>
        <?= site_reset_button('reset_footer', $csrfField, tr('คืนข้อมูลใน footer เป็นค่าเริ่มต้น?', 'Reset the footer?')) ?>
    </div>
    <form method="post" class="space-y-6">
        <?= $csrfField ?><input type="hidden" name="action" value="save_footer">
        <div class="admin-tray">
            <div class="admin-tray-label"><span><?= tr('ข้อมูลหน่วยงานและการติดต่อ', 'Organisation and contact') ?></span></div>
            <section class="admin-card p-5 grid gap-4 sm:grid-cols-2 text-sm">
                <label class="block"><?= tr('ชื่อสถานศึกษา / หน่วยงาน', 'Institution / organisation') ?>
                    <input name="org_name" maxlength="150" value="<?= h($s['org_name'] ?? '') ?>" placeholder="<?= tr('เช่น วิทยาลัยเทคนิค...', 'e.g. ... Technical College') ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                </label>
                <label class="block"><?= tr('หน่วยงานที่ดูแลระบบ', 'Unit running the service') ?>
                    <input name="org_unit" maxlength="200" value="<?= h($s['org_unit'] ?? '') ?>" placeholder="แผนกเทคโนโลยีธุรกิจดิจิทัล · ห้องปฏิบัติการกลางคอมพิวเตอร์" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                </label>
                <label class="block"><?= tr('เวลาทำการ', 'Opening hours') ?>
                    <input name="office_hours" maxlength="100" value="<?= h($s['office_hours'] ?? '') ?>" placeholder="จันทร์–ศุกร์ 08:30–16:30 น." class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                </label>
                <label class="block"><?= tr('ช่องทางติดต่อ', 'Contact') ?>
                    <input name="org_contact" maxlength="200" value="<?= h($s['org_contact'] ?? '') ?>" placeholder="<?= tr('เบอร์โทร อีเมล หรือห้อง', 'Phone, email or room') ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                </label>
                <label class="block sm:col-span-2"><?= tr('ข้อความเพิ่มเติม (ไม่บังคับ ขึ้นบรรทัดใหม่ได้)', 'Extra note (optional, multi-line)') ?>
                    <textarea name="footer_text" maxlength="300" rows="2" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"><?= h($s['footer_text'] ?? '') ?></textarea>
                </label>
            </section>
        </div>
        <div class="admin-tray">
            <div class="admin-tray-label"><span><?= tr('ลิงก์ใน footer (สูงสุด 8)', 'Footer links (max 8)') ?></span></div>
            <section class="admin-card p-5 text-sm">
                <div class="grid gap-3" id="footerLinks">
                    <?php foreach ($links as $i => $link): ?>
                        <div class="grid gap-2 sm:grid-cols-[1fr_2fr]">
                            <input name="link_label[]" maxlength="60" value="<?= h($link['label']) ?>" placeholder="<?= tr('ชื่อลิงก์', 'Link label') ?>" class="rounded-lg border border-gray-300 px-3 py-2" aria-label="<?= tr('ชื่อลิงก์', 'Link label') ?> <?= $i + 1 ?>">
                            <input name="link_url[]" maxlength="255" value="<?= h($link['url']) ?>" placeholder="https://... · page.php · mailto:... · tel:..." class="rounded-lg border border-gray-300 px-3 py-2 font-mono" aria-label="<?= tr('ปลายทาง', 'Target') ?> <?= $i + 1 ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="admin-btn admin-btn-secondary mt-3" id="addFooterLink"><i class="fa-solid fa-plus" aria-hidden="true"></i> <?= tr('เพิ่มแถว', 'Add row') ?></button>
                <p class="mt-2 text-xs text-gray-500"><?= tr('เว้นว่างทั้งแถว = ไม่ใช้ · ลิงก์ภายนอกเปิดในแท็บใหม่', 'Leave a row empty to skip · external links open in a new tab') ?></p>
            </section>
        </div>
        <div class="flex justify-end"><button class="admin-btn"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> <?= tr('บันทึก footer', 'Save footer') ?></button></div>
    </form>
    <script>
    (function () {
        var btn = document.getElementById('addFooterLink'), box = document.getElementById('footerLinks');
        if (!btn || !box) return;
        btn.addEventListener('click', function () {
            if (box.children.length >= 8) { btn.disabled = true; return; }
            var row = box.lastElementChild.cloneNode(true);
            row.querySelectorAll('input').forEach(function (i) { i.value = ''; });
            box.appendChild(row);
            row.querySelector('input').focus();
            if (box.children.length >= 8) btn.disabled = true;
        });
    })();
    </script>

<?php elseif ($tab === 'media'):
    $totalBytes = array_sum(array_map(static fn(array $m): int => (int)$m['size'], $media)); ?>
    <div class="site-head">
        <span class="site-meta"><?= count($media) ?> / <?= SITE_MEDIA_MAX_FILES ?> <?= tr('รูป', 'images') ?> · <?= number_format($totalBytes / 1048576, 1) ?> MB</span>
    </div>
    <div class="admin-tray">
        <div class="admin-tray-label"><span><?= tr('อัปโหลดรูป', 'Upload an image') ?></span></div>
        <section class="admin-card p-5 text-sm">
            <form method="post" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
                <?= $csrfField ?><input type="hidden" name="action" value="upload_media">
                <label class="block"><?= tr('ไฟล์รูป', 'Image file') ?>
                    <input type="file" name="media_file" required accept="image/png,image/jpeg,image/webp" data-photo-input data-max-mb="3">
                </label>
                <label class="block flex-1 min-w-[200px]"><?= tr('ชื่อรูป (ไม่บังคับ)', 'Title (optional)') ?>
                    <input name="media_title" maxlength="150" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                </label>
                <button class="admin-btn"><i class="fa-solid fa-upload" aria-hidden="true"></i> <?= tr('อัปโหลด', 'Upload') ?></button>
            </form>
            <p class="mt-2 text-xs text-gray-500"><?= tr('JPG, PNG หรือ WEBP ไม่เกิน 3 MB · ระบบย่อให้ด้านยาวไม่เกิน 1600 px ตัดข้อมูล EXIF (เช่น พิกัด GPS) และตั้งชื่อไฟล์ใหม่ · รูปในคลังเปิดดูได้โดยไม่ต้องเข้าสู่ระบบ อย่าอัปโหลดเอกสารส่วนตัว', 'JPG, PNG or WEBP up to 3 MB · resized to 1600 px, EXIF (e.g. GPS) removed, renamed · library images are public, do not upload private documents') ?></p>
        </section>
    </div>
    <?php if (!$media): ?>
        <p class="text-sm text-gray-500"><?= tr('ยังไม่มีรูปในคลังสื่อ', 'The library is empty') ?></p>
    <?php else: ?>
        <div class="site-media-grid">
            <?php foreach ($media as $m):
                $used = site_media_usage($conn, (string)$m['file_path']); ?>
                <div class="site-media-item">
                    <a class="site-pick__box" href="../file.php?m=<?= (int)$m['id'] ?>" target="_blank" rel="noopener"><img src="../file.php?m=<?= (int)$m['id'] ?>" alt="<?= h($m['title']) ?>" loading="lazy"></a>
                    <div class="body">
                        <form method="post" class="flex gap-1">
                            <?= $csrfField ?><input type="hidden" name="action" value="rename_media"><input type="hidden" name="media_id" value="<?= (int)$m['id'] ?>">
                            <input name="media_title" maxlength="150" value="<?= h($m['title']) ?>" class="min-w-0 flex-1 rounded-lg border border-gray-300 px-2 py-1 text-xs" aria-label="<?= tr('ชื่อรูป', 'Title') ?>">
                            <button class="admin-btn admin-btn-secondary" title="<?= tr('บันทึกชื่อ', 'Save title') ?>"><i class="fa-solid fa-check" aria-hidden="true"></i><span class="sr-only"><?= tr('บันทึกชื่อ', 'Save title') ?></span></button>
                        </form>
                        <div class="mt-2"><?= (int)$m['width'] ?>×<?= (int)$m['height'] ?> · <?= number_format((int)$m['size'] / 1024) ?> KB<?= $m['first_name'] ? ' · ' . h($m['first_name']) : '' ?></div>
                        <div class="mt-1 font-mono break-all">file.php?m=<?= (int)$m['id'] ?></div>
                        <?php if ($used): ?>
                            <div class="mt-2 admin-chip is-ok"><?= tr('ใช้เป็น', 'Used as') ?> <?= h(implode(', ', $used)) ?></div>
                        <?php else: ?>
                            <form method="post" class="mt-2" data-confirm-submit="<?= tr('ลบรูปนี้ถาวร?', 'Delete this image permanently?') ?>">
                                <?= $csrfField ?><input type="hidden" name="action" value="delete_media"><input type="hidden" name="media_id" value="<?= (int)$m['id'] ?>">
                                <button class="admin-btn admin-btn-danger-outline"><i class="fa-solid fa-trash" aria-hidden="true"></i> <?= tr('ลบ', 'Delete') ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
document.addEventListener('submit', function (e) {
    var form = e.target.closest('[data-confirm-submit]');
    if (form && !window.confirm(form.getAttribute('data-confirm-submit'))) e.preventDefault();
});
</script>

      </div>
<?php $footerBase = '../'; $footerVariant = 'app'; require __DIR__ . '/../includes/site_footer.php'; ?>
   </div>
</body>
</html>
