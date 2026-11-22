<?php
declare(strict_types=1);

/**
 * REPS · includes/site_custom.php — ระบบปรับแต่งเว็บ (feedback-todo-v3 หัวข้อ 6 เฟส 1)
 *
 * ทำงานผ่าน output buffer จุดเดียว (เรียก site_custom_boot() จาก db_connect.php) จึงมีผลทุกหน้าโดยไม่ต้องแก้ทีละไฟล์:
 *   - ธีม: สีหลัก สีรอง สีพื้นหลัง ฟอนต์ ความโค้งมุม (CSS variables + กฎที่จำกัดขอบเขต)
 *   - โลโก้และ favicon ที่ admin เลือก
 *   - ข้อความ UI ที่ admin แก้เอง (แทนเฉพาะข้อความที่ตรงทั้งก้อน เหมือนระบบแปลภาษา)
 * ทุกค่ามาจาก system_settings ที่ผ่านการตรวจรูปแบบตอนบันทึกแล้ว และตรวจซ้ำอีกครั้งตอนแสดงผล (ไม่รับ HTML/CSS ดิบ)
 * ถ้าไม่มีการปรับแต่งเลย จะไม่เปิด buffer และหน้าเว็บทำงานเหมือนเดิมทุกประการ
 */

require_once __DIR__ . '/site_config.php';
require_once __DIR__ . '/i18n_output.php';

/** ฟอนต์ที่เลือกได้ (ชื่อ family, พารามิเตอร์ Google Fonts) — ไม่รับชื่อฟอนต์อิสระเพื่อกัน CSS injection */
const SITE_FONTS = [
    'kanit'   => ['Kanit', 'Kanit:wght@300;400;500;600;700'],
    'prompt'  => ['Prompt', 'Prompt:wght@300;400;500;600;700'],
    'sarabun' => ['Sarabun', 'Sarabun:wght@300;400;500;600;700'],
    'noto'    => ['Noto Sans Thai', 'Noto+Sans+Thai:wght@300;400;500;600;700'],
    'plex'    => ['IBM Plex Sans Thai', 'IBM+Plex+Sans+Thai:wght@300;400;500;600;700'],
    'anuphan' => ['Anuphan', 'Anuphan:wght@300;400;500;600;700'],
];

const SITE_RADIUS = [
    'square'  => ['4px', '4px'],
    'default' => null,
    'round'   => ['999px', '14px'],
];

/** หน้าที่ไม่เปลี่ยนสีพื้นหลัง (จอขึ้นทีวีพื้นเข้ม เอกสารพิมพ์) */
const SITE_BG_EXCLUDED_SCRIPTS = ['warboard.php', 'certificate.php', 'admin_report_pdf.php'];

function site_hex(?string $value): string
{
    $value = trim((string)$value);
    return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1 ? strtoupper($value) : '';
}

/** ข้อความที่ admin แก้: [ข้อความเดิม => ['th' => ..., 'en' => ...]] */
function site_text_overrides(mysqli $conn): array
{
    $raw = (string)(app_get_settings($conn)['site_text_overrides'] ?? '');
    $data = $raw !== '' ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        return [];
    }
    $out = [];
    foreach ($data as $source => $row) {
        if (!is_string($source) || $source === '' || !is_array($row)) {
            continue;
        }
        $out[$source] = ['th' => trim((string)($row['th'] ?? '')), 'en' => trim((string)($row['en'] ?? ''))];
    }
    return $out;
}

/** @return list<array{label:string,url:string}> */
function site_footer_links(mysqli $conn): array
{
    $raw = (string)(app_get_settings($conn)['footer_links'] ?? '');
    $data = $raw !== '' ? json_decode($raw, true) : null;
    $out = [];
    foreach (is_array($data) ? $data : [] as $row) {
        $label = trim((string)($row['label'] ?? ''));
        $url = site_safe_link((string)($row['url'] ?? ''));
        if ($label !== '' && $url !== null) {
            $out[] = ['label' => $label, 'url' => $url];
        }
    }
    return $out;
}

/**
 * ตรวจปลายทางลิงก์ที่ admin กรอก: ไฟล์ .php ภายในเว็บ (ไม่มี ..) หรือ URL http(s) ภายนอก หรือ mailto/tel
 * คืน null ถ้าไม่ปลอดภัย (เช่น javascript:)
 */
function site_safe_link(string $url): ?string
{
    $url = trim($url);
    if ($url === '' || strlen($url) > 255 || preg_match('/[\s<>"\'`\\\\]/', $url) === 1) {
        return null;
    }
    if (preg_match('#^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*\.php(?:\?[A-Za-z0-9_\-=&%.]*)?(?:\#[A-Za-z0-9_-]*)?$#', $url) === 1) {
        return $url;
    }
    if (preg_match('#^https?://#i', $url) === 1 && filter_var($url, FILTER_VALIDATE_URL) !== false) {
        return $url;
    }
    if (preg_match('/^mailto:[^@\s]+@[^@\s]+$/i', $url) === 1 || preg_match('/^tel:\+?[0-9\-]{3,20}$/', $url) === 1) {
        return $url;
    }
    return null;
}

/** ลิงก์ภายในต้องเติม ../ เมื่ออยู่ในหน้าโฟลเดอร์ย่อย */
function site_link_href(string $url, string $base): string
{
    return preg_match('#^(?:https?:|mailto:|tel:)#i', $url) === 1 ? $url : $base . $url;
}

/** ระดับโฟลเดอร์ของหน้าปัจจุบัน ('' หรือ '../') */
function site_request_base(): string
{
    $dir = basename(dirname(str_replace(chr(92), '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''))));
    return in_array($dir, ['admin', 'staff'], true) ? '../' : '';
}

/** มีการปรับแต่งธีมเพิ่มจากค่าเดิมหรือไม่ */
function site_theme_customized(array $s): bool
{
    return ($s['theme_color'] ?? 'default') !== 'default'
        || site_hex($s['primary_color'] ?? '') !== ''
        || site_hex($s['theme_accent_color'] ?? '') !== ''
        || site_hex($s['theme_bg_color'] ?? '') !== ''
        || (($s['theme_font'] ?? 'kanit') !== 'kanit' && isset(SITE_FONTS[$s['theme_font'] ?? '']))
        || (SITE_RADIUS[$s['theme_radius'] ?? 'default'] ?? null) !== null;
}

/** CSS เพิ่มเติมของธีม (สีรอง พื้นหลัง ฟอนต์ ความโค้งมุม) — ค่าทุกตัวผ่าน allowlist/regex แล้ว */
function site_theme_extra_css(array $s, string $script = ''): string
{
    $css = [];
    $head = '';
    $accent = site_hex($s['theme_accent_color'] ?? '');
    if ($accent !== '') {
        $css[] = ":root{--theme-accent:{$accent};--reps-selection:{$accent}33}"
            . ".admin-sidebar .side-nav a[aria-current=\"page\"]::after{background:{$accent}}"
            . "nav a[aria-current=\"page\"].border-b-2{border-bottom-color:{$accent}!important}"
            . ".notif-bell__count,.stock-alert__tab{background:{$accent}!important;color:#fff!important}";
    }
    $bg = site_hex($s['theme_bg_color'] ?? '');
    if ($bg !== '' && !in_array($script, SITE_BG_EXCLUDED_SCRIPTS, true)) {
        $css[] = ":root{--admin-bg:{$bg}}"
            . "body:not(.auth-dark):not(.public-page),.admin-content{background-color:{$bg}!important}";
    }
    $fontKey = (string)($s['theme_font'] ?? 'kanit');
    if ($fontKey !== 'kanit' && isset(SITE_FONTS[$fontKey])) {
        [$family, $param] = SITE_FONTS[$fontKey];
        $head .= '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=' . $param . '&display=swap">';
        // ไม่แตะไอคอน (Font Awesome / SVG) และตัวอักษร monospace
        $css[] = "body,body :not(i):not(svg):not(code):not(pre):not(kbd):not(.font-mono):not([class*=\"fa-\"]){font-family:'{$family}',sans-serif!important}";
    }
    $radius = SITE_RADIUS[$s['theme_radius'] ?? 'default'] ?? null;
    if ($radius !== null) {
        [$btn, $field] = $radius;
        $css[] = ".admin-btn,.btn,a.admin-btn,button.admin-btn,.admin-content .admin-btn{border-radius:{$btn}!important}"
            . "input:not([type=checkbox]):not([type=radio]):not([type=range]),select,textarea,.admin-select-trigger{border-radius:{$field}!important}";
    }
    if (!$css) {
        return $head;
    }
    return $head . '<style id="reps-site-theme">' . implode('', $css) . '</style>';
}

/**
 * เริ่ม output filter ของการปรับแต่ง (เรียกครั้งเดียวจาก db_connect.php หลังเชื่อมฐานข้อมูล)
 * เปิด buffer เฉพาะเมื่อมีสิ่งที่ต้องเปลี่ยนจริง
 */
function site_custom_boot(mysqli $conn): void
{
    static $booted = false;
    if ($booted || PHP_SAPI === 'cli') {
        return;
    }
    $booted = true;
    try {
        $s = app_get_settings($conn);
    } catch (Throwable $e) {
        return;
    }

    $lang = app_lang();
    $overrides = [];
    // หน้าแก้ข้อความเองต้องเห็นข้อความเดิม จึงไม่แทนข้อความในหน้านี้
    $script = basename(str_replace(chr(92), '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
    foreach ($script === 'admin_site.php' ? [] : site_text_overrides($conn) as $source => $row) {
        $value = $lang === 'en' ? $row['en'] : $row['th'];
        if ($value !== '') {
            $overrides[$source] = $value;
        }
    }
    // โหมด EN: ใส่ข้อความที่แก้ไว้ก่อนพจนานุกรม (ระบบแปลทำต่อในรอบเดียว) · โหมด TH: แทนข้อความในรอบของเราเอง
    if ($lang === 'en') {
        i18n_overrides($overrides);
        $overrides = [];
    }

    $state = [
        'theme'    => site_theme_customized($s),
        'logo'     => trim((string)($s['site_logo_path'] ?? '')) !== '',
        'favicon'  => trim((string)($s['site_favicon_path'] ?? '')) !== '',
        'texts'    => $overrides,
        'settings' => $s,
    ];
    if (!$state['theme'] && !$state['logo'] && !$state['favicon'] && !$state['texts']) {
        return;
    }
    $GLOBALS['SITE_CUSTOM_STATE'] = $state;
    ob_start('site_custom_filter');
}

function site_custom_filter(string $buffer): string
{
    if ($buffer === '' || stripos($buffer, '<html') === false) {
        return $buffer;
    }
    foreach (headers_list() as $header) {
        if (stripos($header, 'Content-Type:') === 0 && stripos($header, 'text/html') === false) {
            return $buffer;
        }
    }
    try {
        return site_custom_apply($buffer, $GLOBALS['SITE_CUSTOM_STATE'] ?? []);
    } catch (Throwable $e) {
        error_log('site_custom_filter: ' . $e->getMessage());
        return $buffer;
    }
}

function site_custom_apply(string $html, array $state): string
{
    if (!$state) {
        return $html;
    }
    $s = $state['settings'];
    $base = site_request_base();
    $script = basename(str_replace(chr(92), '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
    $head = '';

    if ($state['theme']) {
        // หน้าที่ยังไม่ได้ใส่ธีมเอง (ส่วนใหญ่ของเว็บ) ใส่ให้ที่นี่
        if (strpos($html, 'id="reps-dynamic-theme"') === false
            && (($s['theme_color'] ?? 'default') !== 'default' || site_hex($s['primary_color'] ?? '') !== '')) {
            $head .= app_theme_css_from_settings($s);
        }
        $head .= site_theme_extra_css($s, $script);
    }

    if ($state['favicon']) {
        $html = (string)preg_replace('#<link\b[^>]*\brel="(?:alternate icon|icon|shortcut icon|apple-touch-icon)"[^>]*>\s*#i', '', $html);
        $fav = htmlspecialchars($base . 'file.php?s=favicon', ENT_QUOTES, 'UTF-8');
        $head .= '<link rel="icon" href="' . $fav . '"><link rel="apple-touch-icon" href="' . $fav . '">';
    }

    // หน้าปรับแต่งเว็บต้องแสดงโลโก้ตั้งต้นเป็นตัวเลือก "ค่าเริ่มต้น" จึงไม่สลับโลโก้ในหน้านี้
    if ($state['logo'] && $script !== 'admin_site.php') {
        $html = (string)preg_replace(
            '#(["\'])((?:\.\./)*)assets/images/logo/reps-logo\.svg(?:\?[^"\']*)?\1#',
            '$1$2file.php?s=logo$1',
            $html
        );
    }

    if ($state['texts']) {
        i18n_overrides($state['texts']);
        $html = i18n_translate_html($html, false);
    }

    if ($head !== '') {
        $pos = stripos($html, '</head>');
        if ($pos !== false) {
            $html = substr($html, 0, $pos) . $head . "\n" . substr($html, $pos);
        }
    }
    return $html;
}

/** จำกัดจำนวนรูปในคลังสื่อ (โฮสต์ฟรีจำกัดจำนวนไฟล์และพื้นที่) */
const SITE_MEDIA_MAX_FILES = 200;
const SITE_MEDIA_MAX_BYTES = 3145728;

/**
 * เพิ่มรูปเข้าคลังสื่อ: ตรวจ MIME จริง ย่อด้วย GD ตัด EXIF ตั้งชื่อสุ่ม (ผ่าน app_store_uploaded_image)
 * @return array{id:int,path:string}
 */
function site_media_add(mysqli $conn, array $file, string $title, ?int $userId, int $maxDimension = 1600): array
{
    require_once __DIR__ . '/private_storage.php';
    $count = (int)($conn->query('SELECT COUNT(*) AS c FROM site_media')->fetch_assoc()['c'] ?? 0);
    if ($count >= SITE_MEDIA_MAX_FILES) {
        throw new AppUserException('คลังสื่อเต็มแล้ว (' . SITE_MEDIA_MAX_FILES . ' รูป) กรุณาลบรูปที่ไม่ใช้ก่อน');
    }
    $path = app_store_uploaded_image($file, 'media', SITE_MEDIA_MAX_BYTES, $maxDimension);
    $abs = app_private_file_abs($path);
    $info = $abs !== null ? @getimagesize($abs) : false;
    if ($abs === null || $info === false) {
        app_delete_private_file($path);
        throw new AppUserException('อ่านรูปไม่ได้ กรุณาลองไฟล์อื่น');
    }
    $mime = (string)$info['mime'];
    $size = (int)filesize($abs);
    $width = (int)$info[0];
    $height = (int)$info[1];
    $title = mb_substr(trim($title) !== '' ? trim($title) : (string)pathinfo((string)($file['name'] ?? ''), PATHINFO_FILENAME), 0, 150);
    try {
        $stmt = $conn->prepare('INSERT INTO site_media (file_path, title, mime, size, width, height, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('sssiiii', $path, $title, $mime, $size, $width, $height, $userId);
        $stmt->execute();
        $id = (int)$stmt->insert_id;
        $stmt->close();
    } catch (Throwable $e) {
        app_delete_private_file($path);
        throw $e;
    }
    return ['id' => $id, 'path' => $path];
}

/** รูปนี้ถูกใช้อยู่ที่ไหนบ้าง (กันลบรูปที่ยังใช้อยู่) */
function site_media_usage(mysqli $conn, string $path): array
{
    $s = app_get_settings($conn);
    $used = [];
    if (($s['site_logo_path'] ?? '') === $path) $used[] = 'โลโก้';
    if (($s['site_favicon_path'] ?? '') === $path) $used[] = 'ไอคอนแท็บ';
    return $used;
}

/** บันทึกประวัติเวอร์ชันของส่วนที่ปรับแต่ง (ใช้ย้อนกลับในเฟส 3) */
function site_revision_save(mysqli $conn, string $entityType, string $entityId, array $data, ?int $userId, string $note = ''): void
{
    try {
        $stmt = $conn->prepare('INSERT INTO site_revisions (entity_type, entity_id, data_json, note, created_by) VALUES (?, ?, ?, ?, ?)');
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $note = mb_substr($note, 0, 200);
        $stmt->bind_param('ssssi', $entityType, $entityId, $json, $note, $userId);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('site_revision_save: ' . $e->getMessage());
    }
}
