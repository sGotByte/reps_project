<?php
declare(strict_types=1);

/**
 * REPS · includes/site_menu.php — เมนูที่ admin ปรับแต่งได้ (ชื่อ ลำดับ แสดง/ซ่อน แยกตาม role และลิงก์เพิ่มเติม)
 *
 * พื้นที่เมนู:
 *   admin   = แถบเมนูด้านข้างของผู้ดูแล (admin/admin_header.php)
 *   staff   = แท็บหน้าทีมช่าง (includes/staff_nav.php)
 *   account = เมนูเมื่อกดรูปโปรไฟล์ (includes/user_menu.php) แยกรายการตาม role
 *
 * เมนูของระบบมีปลายทางตายตัวในโค้ด (admin เปลี่ยนได้แค่ชื่อ ลำดับ การแสดง และ role ที่เห็นภายในขอบเขตเดิม)
 * การซ่อนเมนูเป็นเรื่องการแสดงผลเท่านั้น สิทธิ์เข้าหน้ายังตรวจที่ฝั่งเซิร์ฟเวอร์ของแต่ละหน้าเหมือนเดิม
 */

require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/site_custom.php';

const SITE_MENU_AREAS = [
    'admin'   => ['แถบเมนูผู้ดูแล', 'Admin sidebar'],
    'staff'   => ['แท็บหน้าทีมช่าง', 'Repair staff tabs'],
    'account' => ['เมนูบัญชี (กดรูปโปรไฟล์)', 'Account menu (profile photo)'],
];

const SITE_MENU_GROUPS = [
    'overview' => ['ภาพรวม', 'Overview'],
    'repair'   => ['งานซ่อม', 'Repairs'],
    'team'     => ['ทีมสตาฟ', 'Staff team'],
    'data'     => ['ข้อมูล', 'Data'],
    'report'   => ['รายงาน', 'Reports'],
    'links'    => ['ลิงก์เพิ่มเติม', 'More links'],
];

/** กลุ่มเดิมก่อนจัดเมนูใหม่: ลิงก์ที่ครูเพิ่มเองไว้ในกลุ่มเหล่านี้ ย้ายไปกลุ่มใหม่ที่ใกล้เคียง */
const SITE_MENU_LEGACY_GROUPS = ['manage' => 'data', 'monitor' => 'overview'];
const SITE_MENU_ROLES = ['admin', 'staff', 'user'];

/**
 * เมนูตั้งต้นของระบบ: key => [ชื่อไทย, ชื่ออังกฤษ, ปลายทาง (จากราก), ไอคอน, กลุ่ม, role ที่เห็นได้, ห้ามซ่อน, เปิดแท็บใหม่]
 * @return array<string, array<string, array{0:string,1:string,2:string,3:string,4:string,5:list<string>,6:bool,7:bool}>>
 */
function site_menu_defaults(): array
{
    return [
        'admin' => [
            // ตั้งค่าระบบ / ปรับแต่งเว็บ = ปุ่มไอคอนท้ายแถบ (admin_header.php) · จอสรุปงานเปิดจากหน้าภาพรวมงาน
            // มุมมองสตาฟอยู่ในเมนูรูปโปรไฟล์ · หน้าการแจ้งเตือนใช้กระดิ่งแทน (หน้าเหล่านี้ยังเปิดได้ตามเดิม)
            'dashboard'      => ['แดชบอร์ด', 'Dashboard', 'admin/admin_dashboard.php', 'fa-chart-pie', 'overview', ['admin'], false, false],
            'warroom'        => ['ภาพรวมงาน', 'Job overview', 'admin/admin_war_room.php', 'fa-tower-observation', 'overview', ['admin'], false, false],
            'request'        => ['จัดการคำขอซ่อม', 'Repair requests', 'admin/admin_manage_request.php', 'fa-clipboard-list', 'repair', ['admin'], false, false],
            'signoff'        => ['รับรองงาน', 'Sign-off', 'admin/admin_signoff.php', 'fa-file-signature', 'repair', ['admin'], false, false],
            'history'        => ['ประวัติ', 'History', 'admin/admin_history.php', 'fa-clock-rotate-left', 'repair', ['admin'], false, false],
            'terms'          => ['ภาคเรียนและทีม', 'Terms & team', 'admin/admin_terms.php', 'fa-calendar-days', 'team', ['admin'], false, false],
            'scores'         => ['คะแนนสตาฟ', 'Staff scores', 'admin/admin_scores.php', 'fa-star-half-stroke', 'team', ['admin'], false, false],
            'thanks'         => ['คำขอบคุณ Lab boy', 'Lab boy thanks', 'admin/admin_thanks.php', 'fa-heart', 'team', ['admin'], false, false],
            'users'          => ['จัดการผู้ใช้', 'Users', 'admin/admin_manage_user.php', 'fa-users', 'data', ['admin'], false, false],
            'pages'          => ['หน้าเว็บ (CMS)', 'Web pages (CMS)', 'admin/admin_pages.php', 'fa-file-lines', 'data', ['admin'], false, false],
            'forms'          => ['Form Builder', 'Form Builder', 'admin/admin_forms.php', 'fa-list-check', 'data', ['admin'], false, false],
            'visual_builder' => ['Visual Builder', 'Visual Builder', 'admin/admin_visual_builder.php', 'fa-layer-group', 'data', ['admin'], false, false],
            'inventory'      => ['สต็อกวัสดุ', 'Inventory', 'staff/inventory.php', 'fa-boxes-stacked', 'data', ['admin'], false, false],
            'reports'        => ['ตามช่วงวันที่', 'By date range', 'admin/admin_reports.php', 'fa-chart-line', 'report', ['admin'], false, false],
            'teacher_report' => ['ตามภาคเรียน', 'By term', 'admin/admin_teacher_report.php', 'fa-chart-column', 'report', ['admin'], false, false],
            'report_dept'    => ['ตามแผนก/งาน', 'By department', 'admin/admin_report_dept.php', 'fa-building', 'report', ['admin'], false, false],
        ],
        'staff' => [
            'jobs'      => ['งานของฉัน', 'My jobs', 'staff/staff_dashboard.php', 'fa-screwdriver-wrench', '', ['admin', 'staff'], true, false],
            'warroom'   => ['ภาพรวมงาน', 'Job overview', 'admin/admin_war_room.php', 'fa-tower-observation', '', ['admin', 'staff'], false, false],
            'inventory' => ['สต็อกวัสดุ', 'Inventory', 'staff/inventory.php', 'fa-boxes-stacked', '', ['admin', 'staff'], false, false],
            'thanks'    => ['คำขอบคุณ', 'Thanks', 'staff/thanks.php', 'fa-heart', '', ['staff'], false, false],
            'request'   => ['แจ้งซ่อม', 'New request', 'request_form.php', 'fa-plus', '', ['admin', 'staff'], false, false],
        ],
        'account' => [
            'admin_dashboard' => ['แดชบอร์ดผู้ดูแล', 'Admin dashboard', 'admin/admin_dashboard.php', 'fa-chart-pie', '', ['admin'], false, false],
            'staffview'       => ['มุมมองสตาฟ / ปิดงาน', 'Staff view / Close jobs', 'staff/staff_dashboard.php', 'fa-screwdriver-wrench', '', ['admin'], false, false],
            'my_jobs'         => ['งานของฉัน', 'My jobs', 'staff/staff_dashboard.php', 'fa-screwdriver-wrench', '', ['staff'], false, false],
            'inventory'       => ['สต็อกวัสดุ', 'Inventory', 'staff/inventory.php', 'fa-boxes-stacked', '', ['staff'], false, false],
            'my_requests'     => ['ใบงานของฉัน', 'My requests', 'dashboard.php', 'fa-clipboard-list', '', ['user'], false, false],
            'history'         => ['ประวัติการแจ้งซ่อม', 'Request history', 'history.php', 'fa-clock-rotate-left', '', ['user'], false, false],
        ],
    ];
}

/** role ที่เลือกได้ของแต่ละพื้นที่ (ลิงก์ที่เพิ่มเอง) */
function site_menu_area_roles(string $area): array
{
    return match ($area) {
        'admin' => ['admin'],
        'staff' => ['admin', 'staff'],
        default => SITE_MENU_ROLES,
    };
}

/** ไอคอนที่เลือกได้สำหรับลิงก์เพิ่มเติม (Font Awesome ที่มีในเครื่อง) */
const SITE_MENU_ICONS = [
    'fa-link', 'fa-book', 'fa-circle-question', 'fa-bullhorn', 'fa-file-lines', 'fa-calendar',
    'fa-phone', 'fa-envelope', 'fa-globe', 'fa-house', 'fa-star', 'fa-circle-info',
];

/** แถวที่ admin ปรับแต่งไว้ของพื้นที่หนึ่ง (cache ต่อ request) */
function site_menu_rows(mysqli $conn, string $area): array
{
    static $cache = [];
    if (!array_key_exists($area, $cache)) {
        $cache[$area] = [];
        try {
            $stmt = $conn->prepare('SELECT * FROM site_menus WHERE area = ? ORDER BY sort_order, id');
            $stmt->bind_param('s', $area);
            $stmt->execute();
            $cache[$area] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } catch (Throwable $e) {
            // ยังไม่ได้รัน migration — ใช้เมนูตั้งต้น
            error_log('site_menu_rows: ' . $e->getMessage());
        }
    }
    return $cache[$area];
}

/**
 * รายการเมนูทั้งหมดของพื้นที่ (รวมที่ซ่อน) สำหรับหน้าแก้ไข เรียงตามลำดับที่ตั้งไว้
 * @return list<array{key:?string,id:?int,label:string,label_en:string,default_th:string,default_en:string,target:string,icon:string,group:string,roles:list<string>,allowed_roles:list<string>,visible:bool,locked:bool,new_tab:bool,custom:bool,sort:int}>
 */
function site_menu_all(mysqli $conn, string $area): array
{
    $defaults = site_menu_defaults()[$area] ?? [];
    $rows = site_menu_rows($conn, $area);
    $byKey = [];
    $items = [];
    foreach ($rows as $row) {
        if ($row['item_key'] !== null && $row['item_key'] !== '') {
            $byKey[$row['item_key']] = $row;
        }
    }
    $i = 0;
    foreach ($defaults as $key => [$th, $en, $href, $icon, $group, $roles, $locked, $newTab]) {
        $row = $byKey[$key] ?? null;
        $savedRoles = $row ? array_values(array_intersect(explode(',', (string)$row['visible_roles']), $roles)) : $roles;
        $items[] = [
            'key' => $key, 'id' => $row ? (int)$row['id'] : null,
            'label' => $row ? (string)$row['label'] : '', 'label_en' => $row ? (string)$row['label_en'] : '',
            'default_th' => $th, 'default_en' => $en, 'target' => $href, 'icon' => $icon, 'group' => $group,
            'roles' => $savedRoles, 'allowed_roles' => $roles,
            'visible' => $locked || !$row || (int)$row['is_visible'] === 1,
            'locked' => $locked, 'new_tab' => $newTab, 'custom' => false,
            'sort' => $row ? (int)$row['sort_order'] : ($i + 1) * 10,
        ];
        $i++;
    }
    foreach ($rows as $row) {
        if ($row['item_key'] !== null && $row['item_key'] !== '') {
            continue;
        }
        $target = site_safe_link((string)$row['target']);
        if ($target === null) {
            continue;
        }
        $allowed = site_menu_area_roles($area);
        $items[] = [
            'key' => null, 'id' => (int)$row['id'],
            'label' => (string)$row['label'], 'label_en' => (string)$row['label_en'],
            'default_th' => (string)$row['label'], 'default_en' => (string)$row['label_en'],
            'target' => $target, 'icon' => in_array($row['icon'], SITE_MENU_ICONS, true) ? (string)$row['icon'] : 'fa-link',
            'group' => isset(SITE_MENU_GROUPS[$row['group_key']]) ? (string)$row['group_key'] : (SITE_MENU_LEGACY_GROUPS[$row['group_key']] ?? 'links'),
            'roles' => array_values(array_intersect(explode(',', (string)$row['visible_roles']), $allowed)),
            'allowed_roles' => $allowed,
            'visible' => (int)$row['is_visible'] === 1, 'locked' => false, 'new_tab' => (int)$row['new_tab'] === 1,
            'custom' => true, 'sort' => (int)$row['sort_order'],
        ];
    }
    usort($items, static fn(array $a, array $b): int => $a['sort'] <=> $b['sort']);
    return $items;
}

/** เมนูที่ role นี้เห็น พร้อมชื่อในภาษาปัจจุบัน */
function site_menu_items(mysqli $conn, string $area, string $role): array
{
    $en = app_lang() === 'en';
    $out = [];
    foreach (site_menu_all($conn, $area) as $item) {
        if (!$item['visible'] || !in_array($role, $item['roles'], true)) {
            continue;
        }
        $label = $en
            ? ($item['label_en'] !== '' ? $item['label_en'] : ($item['custom'] ? $item['label'] : tr($item['default_th'], $item['default_en'])))
            : ($item['label'] !== '' ? $item['label'] : $item['default_th']);
        if ($label === '') {
            continue;
        }
        $item['text'] = $label;
        $out[] = $item;
    }
    return $out;
}
