<?php
declare(strict_types=1);

/**
 * REPS · includes/staff_nav.php — แถบบนเต็มความกว้าง + ลิ้นชักเมนู (hamburger)
 *
 * วางต่อจาก <body> เหมือนเดิม ไฟล์นี้ใส่ CSS/JS ของตัวเองครบ
 *   แถบบน : [☰] [Logo] ชื่อหน้า ........ [🔔] [ก ชื่อ ▾]
 *   ☰ เปิดลิ้นชักเมนู (ทั้งเดสก์ท็อปและมือถือ)
 *
 * ตัวแปรที่รับจากหน้า:
 *   $staffNavActive  'jobs' | 'inventory' | 'thanks'
 *   $staffNavBadge   จำนวนวัสดุต่ำกว่าจุดแจ้งเตือน (ไม่ส่งมาก็นับเอง)
 *   $staffNavTitle   ชื่อหน้าบนแถบ (ไม่ส่งมาก็ใช้ชื่อเมนูที่ active)
 */

$snActive = (string)($staffNavActive ?? '');
$snH = static fn(mixed $v): string => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');

// ---- ข้อมูลผู้ใช้ + ตัวเลขแจ้งเตือน ----
$snUser = [];
$snLow = isset($staffNavBadge) ? (int)$staffNavBadge : null;
$snTurn = 0;
if (isset($conn, $userId) && function_exists('db_one')) {
    try {
        $snUser = db_one($conn, 'SELECT first_name, last_name, is_active_queue FROM users WHERE user_id = ?', 'i', [(int)$userId]) ?? [];
        $snTurn = (int)(db_one($conn, "SELECT COUNT(*) AS n FROM tickets WHERE status = 'pending' AND queue_assigned_to = ?", 'i', [(int)$userId])['n'] ?? 0);
        if ($snLow === null) {
            $snLow = (int)(db_one($conn, 'SELECT COUNT(*) AS n FROM inventory_items WHERE stock_qty <= min_stock')['n'] ?? 0);
        }
    } catch (Throwable $snErr) {
        // ไม่ให้เมนูล่มเพราะตัวเลขแจ้งเตือน
    }
}
$snLow = (int)($snLow ?? 0);
$snFirst = trim((string)($snUser['first_name'] ?? ''));
$snName = trim($snFirst . ' ' . (string)($snUser['last_name'] ?? ''));
$snInitial = $snName !== '' && preg_match('/^\X/u', $snName, $snM) ? (function_exists('mb_strtoupper') ? mb_strtoupper($snM[0]) : $snM[0]) : 'S';
$snIsStaff = function_exists('current_role') && current_role() === 'staff';
$snQueueOn = $snIsStaff && isset($snUser['is_active_queue']) ? ((int)$snUser['is_active_queue'] === 1) : null;

// ---- เมนูหลัก (เพิ่มหน้าใหม่ที่นี่ที่เดียว) ----
$snItems = [
    'jobs'      => ['href' => 'staff_dashboard.php', 'icon' => 'fa-clipboard-list', 'label' => 'งานซ่อม'],
    'inventory' => ['href' => 'inventory.php',       'icon' => 'fa-boxes-stacked',  'label' => 'สต็อกวัสดุ'],
    'thanks'    => ['href' => 'thanks.php',          'icon' => 'fa-heart',          'label' => 'คำขอบคุณ'],
];
$snHomeHref = 'staff_dashboard.php';
$snProfileHref = '../profile.php';
$snLogoutHref = '../logout.php';

$snTitle = (string)($staffNavTitle ?? ($snItems[$snActive]['label'] ?? 'สตาฟ'));
$snBadgeFor = static function (string $key) use ($snLow, $snTurn): int {
    return $key === 'inventory' ? $snLow : ($key === 'jobs' ? $snTurn : 0);
};

// ---- สวิตช์ภาษา: ส่ง ?lang=th|en กลับหน้าเดิม (ปรับให้เข้ากับระบบ i18n ของคุณ) ----
$snLang = (($_GET['lang'] ?? $_COOKIE['lang'] ?? 'th') === 'en') ? 'en' : 'th';
$snLangUrl = static function (string $l): string {
    $q = $_GET;
    $q['lang'] = $l;
    return strtok((string)($_SERVER['REQUEST_URI'] ?? ''), '?') . '?' . http_build_query($q);
};

require_once __DIR__ . '/site_config.php';
require_once __DIR__ . '/notifications.php';
$snNotifications = notif_bell($conn, '../');
$snLogoUrl = app_logo_url($conn, '../');
$snSiteName = function_exists('app_site_name') ? app_site_name($conn) : 'REPS';
?>
<style>
/* ===== REPS staff shell · minimal ===== */
:root { --sn-top: 60px; --sn-side: 248px; --sn-ink: #111111; --sn-mute: #8A8A85; --sn-soft: rgba(17,17,17,.045); --sn-line: rgba(17,17,17,.07); }
.sn-top, .sn-aside, .sn-overlay { font-family: 'Kanit', system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
.sn-top *, .sn-aside * { box-sizing: border-box; }
.sn-top a, .sn-aside a { text-decoration: none; }
body.sn-on { padding-top: var(--sn-top); }

/* แถบบน: โปร่งแสง ไร้เส้นขอบหนัก */
.sn-top { position: fixed; inset: 0 0 auto 0; z-index: 50; display: flex; align-items: center; gap: 10px; height: var(--sn-top); padding: 0 20px 0 14px; background: rgba(255,255,255,.82); -webkit-backdrop-filter: saturate(180%) blur(16px); backdrop-filter: saturate(180%) blur(16px); box-shadow: 0 1px 0 var(--sn-line); color: var(--sn-ink); }
.sn-top__brand { display: inline-flex; align-items: center; gap: 8px; flex: none; padding: 4px 6px; border-radius: 10px; color: var(--sn-ink); transition: background-color .15s ease; }
.sn-top__brand:hover { background: var(--sn-soft); }
.sn-top__brand img { display: block; width: auto; max-width: 124px; height: 26px; object-fit: contain; }
.sn-top__sep { width: 1px; height: 18px; background: var(--sn-line); flex: none; }
.sn-top__title { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 15px; font-weight: 500; letter-spacing: -.005em; }
.sn-top__right { display: flex; align-items: center; gap: 10px; flex: none; }

.sn-menu-btn { display: inline-grid; place-items: center; flex: none; width: 40px; height: 40px; padding: 0; border: 0; border-radius: 12px; background: transparent; color: var(--sn-ink); font-size: 16px; cursor: pointer; transition: background-color .15s ease; }
.sn-menu-btn:hover { background: var(--sn-soft); }
.sn-menu-btn:focus-visible, .sn-user__btn:focus-visible, .sn-link:focus-visible, .sn-top__brand:focus-visible { outline: 2px solid var(--sn-ink); outline-offset: 2px; }

.sn-user { position: relative; }
.sn-user__btn { display: flex; align-items: center; gap: 8px; height: 40px; padding: 0 10px 0 4px; border: 0; border-radius: 999px; background: transparent; color: var(--sn-ink); font: inherit; font-size: 14px; font-weight: 500; cursor: pointer; transition: background-color .15s ease; }
.sn-user__btn:hover, .sn-user__btn[aria-expanded="true"] { background: var(--sn-soft); }
.sn-av { display: grid; place-items: center; flex: none; width: 32px; height: 32px; border-radius: 50%; background: var(--sn-ink); color: #FFFFFF; font-size: 13px; font-weight: 600; }
.sn-user__name { max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sn-user__btn .fa-chevron-down { color: var(--sn-mute); font-size: 10px; transition: transform .15s ease; }
.sn-user__btn[aria-expanded="true"] .fa-chevron-down { transform: rotate(180deg); }
.sn-dd { position: absolute; right: 0; top: calc(100% + 10px); z-index: 70; display: none; min-width: 232px; padding: 6px; border-radius: 16px; background: #FFFFFF; box-shadow: 0 0 0 1px var(--sn-line), 0 18px 48px -8px rgba(17,17,17,.18); }
.sn-dd.is-open { display: block; animation: sn-pop .14s ease-out; }
@keyframes sn-pop { from { opacity: 0; transform: translateY(-4px) scale(.98); } to { opacity: 1; transform: none; } }
.sn-dd__head { padding: 10px 12px 12px; margin-bottom: 4px; line-height: 1.4; box-shadow: 0 1px 0 var(--sn-line); }
.sn-dd__head b { display: block; font-size: 14px; font-weight: 500; }

.sn-state { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--sn-mute); }
.sn-state::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: #C4C4BE; }
.sn-state.is-on { color: #15803D; }
.sn-state.is-on::before { background: #22C55E; }

/* ลิงก์: ไร้เส้น ใช้พื้นอ่อนบอกสถานะ */
.sn-link { position: relative; display: flex; align-items: center; gap: 12px; min-height: 42px; padding: 0 12px; border-radius: 12px; color: #5C5C57; font-size: 14px; transition: background-color .15s ease, color .15s ease; }
.sn-link i { flex: none; width: 18px; color: var(--sn-mute); font-size: 14px; text-align: center; transition: color .15s ease; }
.sn-link:hover { background: var(--sn-soft); color: var(--sn-ink); }
.sn-link:hover i { color: var(--sn-ink); }
.sn-link[aria-current="page"] { background: var(--sn-ink); color: #FFFFFF; font-weight: 500; }
.sn-link[aria-current="page"] i { color: #FFFFFF; }
.sn-link--danger, .sn-link--danger i { color: #B91C1C; }
.sn-link--danger:hover { background: #FEF2F2; color: #B91C1C; }
.sn-link--danger:hover i { color: #B91C1C; }

.sn-badge { flex: none; margin-left: auto; min-width: 20px; padding: 0 6px; border-radius: 999px; background: #F59E0B; color: #1F1300; font-size: 11px; font-weight: 600; line-height: 20px; text-align: center; font-variant-numeric: tabular-nums; }
.sn-link[aria-current="page"] .sn-badge { background: #FFFFFF; color: var(--sn-ink); }

/* sidebar */
.sn-aside { position: fixed; z-index: 45; display: flex; flex-direction: column; background: #FAFAF9; color: var(--sn-ink); box-shadow: 1px 0 0 var(--sn-line); }
.sn-brand { display: flex; align-items: center; gap: 10px; flex: none; height: var(--sn-top); padding: 0 10px 0 20px; }
.sn-brand img { display: block; width: auto; max-width: 140px; height: 28px; object-fit: contain; }
.sn-close { margin-left: auto; }
.sn-scroll { flex: 1 1 auto; overflow-y: auto; overscroll-behavior: contain; padding: 16px 12px; }
.sn-label { padding: 4px 12px 8px; color: var(--sn-mute); font-size: 11px; font-weight: 500; letter-spacing: .1em; text-transform: uppercase; }
.sn-nav { display: grid; gap: 2px; }
.sn-overlay { display: none; }

@media (min-width: 1024px) {
    .sn-aside { top: var(--sn-top); bottom: 0; left: 0; width: var(--sn-side); transition: transform .28s cubic-bezier(.16,1,.3,1), visibility 0s; }
    .sn-aside .sn-brand { display: none; }
    body.sn-on { padding-left: var(--sn-side); transition: padding-left .28s cubic-bezier(.16,1,.3,1); }
    html.sn-collapsed body.sn-on { padding-left: 0; }
    html.sn-collapsed .sn-aside { transform: translateX(-100%); visibility: hidden; transition: transform .28s cubic-bezier(.16,1,.3,1), visibility 0s linear .28s; }
    .sn-close { display: none; }
}
@media (max-width: 1023px) {
    html.sn-lock { overflow: hidden; }
    .sn-aside { top: 0; bottom: 0; left: 0; z-index: 60; width: min(300px, 86vw); background: #FFFFFF; transform: translateX(-100%); visibility: hidden; box-shadow: none; transition: transform .28s cubic-bezier(.16,1,.3,1), visibility 0s linear .28s; }
    .sn-aside.is-open { transform: none; visibility: visible; box-shadow: 0 24px 64px rgba(17,17,17,.22); transition: transform .28s cubic-bezier(.16,1,.3,1); }
    .sn-overlay { position: fixed; inset: 0; z-index: 55; display: block; background: rgba(17,17,17,.32); -webkit-backdrop-filter: blur(2px); backdrop-filter: blur(2px); opacity: 0; pointer-events: none; transition: opacity .2s ease; }
    .sn-overlay.is-open { opacity: 1; pointer-events: auto; }
}
@media (max-width: 640px) {
    .sn-top { gap: 6px; padding: 0 12px 0 8px; }
    .sn-top__right { gap: 6px; }
    .sn-user__name, .sn-user__btn .fa-chevron-down { display: none; }
    .sn-user__btn { padding: 0 4px; }
}
@media (max-width: 480px) {
    .sn-top__brand img { max-width: 96px; height: 22px; }
    .sn-top__sep { display: none; }
}
@media (prefers-reduced-motion: reduce) {
    .sn-aside, body.sn-on, .sn-overlay, .sn-dd { transition: none !important; animation: none !important; }
}
@media print {
    .sn-top, .sn-aside, .sn-overlay { display: none !important; }
    body.sn-on { padding: 0 !important; }
}
</style>

<header class="sn-top">
    <button type="button" class="sn-menu-btn" data-sn-open aria-controls="snAside" aria-expanded="false" aria-label="เปิดเมนู"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>
    <a class="sn-top__brand" href="<?= $snH($snHomeHref) ?>" aria-label="<?= $snH($snSiteName) ?>">
        <img src="<?= $snH($snLogoUrl) ?>" alt="<?= $snH($snSiteName) ?>">
    </a>
    <span class="sn-top__sep" aria-hidden="true"></span>
    <div class="sn-top__title"><?= $snH($snTitle) ?></div>

    <div class="sn-top__right">
        <?= $snNotifications ?>

        <div class="sn-user">
            <button type="button" class="sn-user__btn" id="snUserBtn" aria-haspopup="true" aria-expanded="false" aria-controls="snUserMenu">
                <span class="sn-av" aria-hidden="true"><?= $snH($snInitial) ?></span>
                <span class="sn-user__name"><?= $snH($snFirst !== '' ? $snFirst : 'สตาฟ') ?></span>
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="sn-dd" id="snUserMenu">
                <div class="sn-dd__head">
                    <b><?= $snH($snName !== '' ? $snName : 'สตาฟ') ?></b>
                    <?php if ($snQueueOn !== null): ?>
                        <span class="sn-state<?= $snQueueOn ? ' is-on' : '' ?>"><?= $snQueueOn ? 'เปิดรับคิวอยู่' : 'พักรับคิว' ?></span>
                    <?php endif; ?>
                </div>
                <a class="sn-link" href="<?= $snH($snProfileHref) ?>"><i class="fa-solid fa-user" aria-hidden="true"></i><span>โปรไฟล์ของฉัน</span></a>
                <a class="sn-link sn-link--danger" href="<?= $snH($snLogoutHref) ?>"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i><span>ออกจากระบบ</span></a>
            </div>
        </div>
    </div>
</header>

<div class="sn-overlay" id="snOverlay" aria-hidden="true"></div>

<aside class="sn-aside" id="snAside" aria-label="เมนูสตาฟ">
    <div class="sn-brand">
        <a href="<?= $snH($snHomeHref) ?>" aria-label="<?= $snH($snSiteName) ?>">
            <img src="<?= $snH($snLogoUrl) ?>" alt="<?= $snH($snSiteName) ?>">
        </a>
        <button type="button" class="sn-menu-btn sn-close" data-sn-close aria-label="ปิดเมนู"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </div>
    <div class="sn-scroll">
        <div class="sn-label">เมนูหลัก</div>
        <nav class="sn-nav" aria-label="เมนูหลัก">
            <?php foreach ($snItems as $snKey => $snItem): $snB = $snBadgeFor($snKey); ?>
                <a class="sn-link" href="<?= $snH($snItem['href']) ?>"<?= $snActive === $snKey ? ' aria-current="page"' : '' ?>>
                    <i class="fa-solid <?= $snH($snItem['icon']) ?>" aria-hidden="true"></i>
                    <span><?= $snH($snItem['label']) ?></span>
                    <?php if ($snB > 0): ?>
                        <span class="sn-badge" title="<?= $snKey === 'jobs' ? 'ถึงคิวคุณ' : 'ต่ำกว่าจุดแจ้งเตือน' ?>"><?= $snB ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</aside>

<script>
(function () {
    var d = document, html = d.documentElement, aside = d.getElementById('snAside'), overlay = d.getElementById('snOverlay');
    if (!aside) return;
    d.body.classList.add('sn-on');
    var mq = window.matchMedia('(min-width: 1024px)');
    var KEY = 'repsStaffNavCollapsed';
    var openers = Array.prototype.slice.call(d.querySelectorAll('[data-sn-open]'));
    var lastFocus = null;

    try { if (localStorage.getItem(KEY) === '1') html.classList.add('sn-collapsed'); } catch (e) {}

    function syncAria() {
        var expanded = mq.matches ? !html.classList.contains('sn-collapsed') : aside.classList.contains('is-open');
        openers.forEach(function (o) { o.setAttribute('aria-expanded', expanded ? 'true' : 'false'); });
    }
    function setDrawer(open) {
        aside.classList.toggle('is-open', open);
        overlay.classList.toggle('is-open', open);
        html.classList.toggle('sn-lock', open);
        syncAria();
        if (open) {
            lastFocus = d.activeElement;
            var c = aside.querySelector('[data-sn-close]');
            if (c) c.focus();
        } else if (lastFocus) {
            lastFocus.focus();
            lastFocus = null;
        }
    }
    function toggleSide() {
        var c = html.classList.toggle('sn-collapsed');
        try { localStorage.setItem(KEY, c ? '1' : '0'); } catch (e) {}
        syncAria();
    }

    openers.forEach(function (o) {
        o.addEventListener('click', function () { if (mq.matches) toggleSide(); else setDrawer(!aside.classList.contains('is-open')); });
    });
    overlay.addEventListener('click', function () { setDrawer(false); });
    aside.querySelector('[data-sn-close]').addEventListener('click', function () { setDrawer(false); });
    syncAria();

    // dropdown ผู้ใช้
    var ub = d.getElementById('snUserBtn'), um = d.getElementById('snUserMenu');
    function setUser(open) {
        um.classList.toggle('is-open', open);
        ub.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    ub.addEventListener('click', function (e) { e.stopPropagation(); setUser(!um.classList.contains('is-open')); });
    d.addEventListener('click', function (e) { if (!um.contains(e.target)) setUser(false); });

    d.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (um.classList.contains('is-open')) { setUser(false); ub.focus(); }
            if (aside.classList.contains('is-open')) setDrawer(false);
            return;
        }
        if (e.key !== 'Tab' || !aside.classList.contains('is-open') || mq.matches) return;
        var f = aside.querySelectorAll('a[href], button:not([disabled])');
        if (!f.length) return;
        var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && d.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && d.activeElement === last) { e.preventDefault(); first.focus(); }
    });

    // ย่อ/ขยายหน้าจอข้ามจุดตัด → ปิดลิ้นชักและปลดล็อกการเลื่อน
    var onChange = function () { if (aside.classList.contains('is-open')) setDrawer(false); else syncAria(); };
    if (mq.addEventListener) mq.addEventListener('change', onChange); else if (mq.addListener) mq.addListener(onChange);
})();
</script>