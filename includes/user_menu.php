<?php
declare(strict_types=1);

/**
 * REPS · includes/user_menu.php — รูปโปรไฟล์ + เมนูย่อยเมื่อกดที่รูป (ใช้ร่วมกันทุก navbar)
 *
 *   echo user_menu_render($conn, '');      // หน้าในราก
 *   echo user_menu_render($conn, '../');   // หน้าใน admin/ staff/
 *   echo user_avatar_html($userRow, '', 'user-avatar--lg');   // แสดงรูปของผู้ใช้คนใดก็ได้
 *
 * รูปเก็บใน private storage (users.avatar_path) และเปิดผ่าน file.php?u=<id>&k=avatar&v=<ชื่อไฟล์>
 * ไม่มีรูป → แสดงอักษรตัวแรกของชื่อ
 */

require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/site_menu.php';

/** ข้อมูลผู้ใช้ปัจจุบันสำหรับเมนู (cache ต่อ request) */
function user_menu_current(mysqli $conn): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        return null;
    }
    try {
        $stmt = $conn->prepare(
            'SELECT u.user_id, u.first_name, u.last_name, u.user_type, u.role, u.avatar_path, d.name AS department_name
               FROM users u LEFT JOIN department d ON d.d_id = u.department_id WHERE u.user_id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $cache = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
    } catch (Throwable $e) {
        // ยังไม่ได้รัน migration รูปโปรไฟล์ — ใช้ข้อมูลจาก session แทน
        error_log('user_menu_current: ' . $e->getMessage());
        $name = trim((string)($_SESSION['user_name'] ?? ''));
        $cache = ['user_id' => $userId, 'first_name' => $name, 'last_name' => '', 'user_type' => '', 'role' => (string)($_SESSION['role'] ?? 'user'), 'avatar_path' => null, 'department_name' => null];
    }
    return $cache;
}

function user_avatar_url(array $user, string $base = ''): ?string
{
    $path = (string)($user['avatar_path'] ?? '');
    if ($path === '') {
        return null;
    }
    return $base . 'file.php?u=' . (int)$user['user_id'] . '&k=avatar&v=' . rawurlencode(substr(basename($path), 0, 40));
}

function user_initial(array $user): string
{
    $name = trim((string)($user['first_name'] ?? ''));
    return $name === '' ? '?' : mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
}

function user_avatar_html(array $user, string $base = '', string $class = ''): string
{
    $h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $url = user_avatar_url($user, $base);
    $cls = trim('user-avatar ' . $class);
    if ($url !== null) {
        return '<span class="' . $h($cls) . '"><img src="' . $h($url) . '" alt="" loading="lazy" decoding="async"></span>';
    }
    return '<span class="' . $h($cls . ' user-avatar--initial') . '" aria-hidden="true">' . $h(user_initial($user)) . '</span>';
}

function user_menu_render(mysqli $conn, string $base = '', bool $showName = true): string
{
    $user = user_menu_current($conn);
    if ($user === null) {
        return '';
    }
    $h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $role = normalize_role((string)($user['role'] ?? 'user'));
    $fullName = trim($user['first_name'] . ' ' . $user['last_name']);
    $types = ['student' => tr('นักศึกษา', 'Student'), 'teacher' => tr('ครู/อาจารย์', 'Teacher'), 'staff' => tr('บุคลากร', 'Personnel')];
    $meta = implode(' · ', array_filter([
        app_lang() === 'en' ? (['admin' => 'Admin', 'staff' => 'Staff', 'user' => 'Requester'][$role] ?? $role) : role_label($role),
        $types[$user['user_type']] ?? '',
        (string)($user['department_name'] ?? ''),
    ]));

    // รายการตาม role จาก includes/site_menu.php (admin ปรับได้ที่หน้า ปรับแต่งเว็บ)
    $links = [];
    foreach (site_menu_items($conn, 'account', $role) as $item) {
        $links[] = [site_link_href($item['target'], $base), $item['text'], $item['icon'], $item['new_tab']];
    }

    ob_start(); ?>
<?= lang_switch_html($base) ?>
<details class="user-menu" data-user-menu>
    <summary class="user-menu__btn" aria-label="<?= tr('เมนูบัญชี', 'Account menu') ?>: <?= $h($fullName) ?>">
        <?= user_avatar_html($user, $base) ?>
        <?php if ($showName): ?><span class="user-menu__name"><?= $h($user['first_name']) ?></span><?php endif; ?>
        <i class="fa-solid fa-chevron-down user-menu__chev" aria-hidden="true"></i>
    </summary>
    <div class="user-menu__panel">
        <div class="user-menu__head">
            <a class="user-menu__photo" href="<?= $h($base) ?>profile.php#avatar" title="<?= tr('เปลี่ยนรูปโปรไฟล์', 'Change photo') ?>">
                <?= user_avatar_html($user, $base, 'user-avatar--lg') ?>
                <span class="user-menu__photo-edit" aria-hidden="true"><i class="fa-solid fa-camera"></i></span>
                <span class="sr-only"><?= tr('เปลี่ยนรูปโปรไฟล์', 'Change photo') ?></span>
            </a>
            <div class="user-menu__who">
                <div class="user-menu__full"><?= $h($fullName) ?></div>
                <div class="user-menu__meta"><?= $h($meta) ?></div>
            </div>
        </div>
        <nav class="user-menu__list" aria-label="<?= tr('เมนูบัญชี', 'Account menu') ?>">
            <a href="<?= $h($base) ?>profile.php"><i class="fa-solid fa-user-pen" aria-hidden="true"></i><?= tr('แก้ไขโปรไฟล์', 'Edit profile') ?></a>
            <a href="<?= $h($base) ?>profile.php#avatar"><i class="fa-solid fa-camera" aria-hidden="true"></i><?= tr('เปลี่ยนรูปโปรไฟล์', 'Change photo') ?></a>
            <span class="user-menu__sep" role="separator"></span>
            <?php foreach ($links as [$href, $label, $icon, $newTab]): ?>
                <a href="<?= $h($href) ?>"<?= $newTab ? ' target="_blank" rel="noopener"' : '' ?>><i class="fa-solid <?= $h($icon) ?>" aria-hidden="true"></i><?= $h($label) ?></a>
            <?php endforeach; ?>
            <span class="user-menu__sep" role="separator"></span>
            <a class="user-menu__logout" href="<?= $h($base) ?>logout.php"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i><?= tr('ออกจากระบบ', 'Sign out') ?></a>
        </nav>
    </div>
</details>
<script>
(function () {
    var el = document.currentScript && document.currentScript.previousElementSibling;
    if (!el || !el.matches('[data-user-menu]')) return;
    document.addEventListener('click', function (ev) { if (el.open && !el.contains(ev.target)) el.open = false; });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && el.open) { el.open = false; el.querySelector('summary').focus(); }
    });
})();
</script>
<?php
    return (string)ob_get_clean();
}
