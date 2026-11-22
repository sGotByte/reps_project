<?php
declare(strict_types=1);

/**
 * REPS · includes/staff_nav.php — เมนูสตาฟแบบ sidebar (แทน top nav เดิม)
 *
 * วางต่อจาก <body> เหมือนเดิม ไม่ต้องเปลี่ยนโครงหน้า — ไฟล์นี้ใส่ CSS/JS ของตัวเองครบ
 *   เดสก์ท็อป (≥1024px) : sidebar ติดซ้าย 264px
 *   มือถือ/แท็บเล็ต     : แถบบน (ปุ่มเมนู + ชื่อหน้า + สถานะคิว) + แถบแท็บล่างแตะด้วยนิ้วโป้ง + ลิ้นชักเมนูเต็ม
 *
 * ตัวแปรที่รับจากหน้า (ไม่บังคับทั้งหมด ยกเว้น $staffNavActive):
 *   $staffNavActive  'jobs' | 'inventory' | 'thanks'
 *   $staffNavBadge   จำนวนวัสดุต่ำกว่าจุดแจ้งเตือน (ไม่ส่งมาก็นับเอง)
 *   $staffNavTitle   ชื่อหน้าบนแถบมือถือ (ไม่ส่งมาก็ใช้ชื่อเมนูที่ active)
 */

$snActive = (string)($staffNavActive ?? '');
$snH = static fn(mixed $v): string => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');

// ---- ข้อมูลผู้ใช้ + ตัวเลขแจ้งเตือน (query เบา ๆ ถ้าพลาดก็แสดงเมนูต่อได้) ----
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
$snName = trim((string)($snUser['first_name'] ?? '') . ' ' . (string)($snUser['last_name'] ?? ''));
$snInitial = $snName !== '' && preg_match('/^\X/u', $snName, $snM) ? (function_exists('mb_strtoupper') ? mb_strtoupper($snM[0]) : $snM[0]) : 'S';
$snIsStaff = function_exists('current_role') && current_role() === 'staff';
$snQueueOn = $snIsStaff && isset($snUser['is_active_queue']) ? ((int)$snUser['is_active_queue'] === 1) : null;
$snSite = isset($siteName) && (string)$siteName !== '' ? (string)$siteName : 'REPS';

// ---- เมนูหลัก (เพิ่มหน้าใหม่ที่นี่ที่เดียว) ----
$snItems = [
    'jobs'      => ['href' => 'staff_dashboard.php', 'icon' => 'fa-clipboard-list', 'label' => 'งานซ่อม',    'short' => 'งาน'],
    'inventory' => ['href' => 'inventory.php',       'icon' => 'fa-boxes-stacked',  'label' => 'สต็อกวัสดุ', 'short' => 'สต็อก'],
    'thanks'    => ['href' => 'thanks.php',          'icon' => 'fa-heart',          'label' => 'คำขอบคุณ',  'short' => 'ขอบคุณ'],
];
// ลิงก์บัญชี — ตรวจ path ให้ตรงกับระบบจริงของคุณ
$snProfileHref = '../profile.php';
$snLogoutHref = '../logout.php';

$snTitle = (string)($staffNavTitle ?? ($snItems[$snActive]['label'] ?? 'สตาฟ'));
$snBadgeFor = static function (string $key) use ($snLow, $snTurn): int {
    return $key === 'inventory' ? $snLow : ($key === 'jobs' ? $snTurn : 0);
};
require_once __DIR__ . '/site_config.php';
require_once __DIR__ . '/notifications.php';
$snNotifications = notif_bell($conn, '../');
?>
<style>
/* ===== REPS staff shell ===== */
.sn-aside, .sn-top, .sn-bottom, .sn-overlay { font-family: 'Kanit', system-ui, sans-serif; }
.sn-aside, .sn-top, .sn-bottom, .sn-aside *, .sn-top *, .sn-bottom * { box-sizing: border-box; }
.sn-aside a, .sn-top a, .sn-bottom a { text-decoration: none; }

.sn-aside { position: fixed; inset: 0 auto 0 0; z-index: 60; display: flex; flex-direction: column; width: 264px; background: #FFFFFF; border-right: 1px solid #ECEAE6; color: #161616; }
.sn-brand { display: flex; align-items: center; gap: 10px; flex: none; height: 64px; padding: 0 16px 0 18px; border-bottom: 1px solid #F1EFEC; }
.sn-brand img { width: auto; max-width: 112px; height: 30px; flex: none; object-fit: contain; }
.sn-brand__text { flex: 1 1 auto; min-width: 0; line-height: 1.25; }
.sn-brand__name { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 15px; font-weight: 600; }
.sn-brand__role { display: block; font-size: 12px; color: #6B6862; }
.sn-icon-btn.sn-close { display: none; margin-left: auto; }

.sn-scroll { flex: 1 1 auto; overflow-y: auto; overscroll-behavior: contain; padding: 14px 12px; }
.sn-label { padding: 10px 12px 6px; color: #6B6862; font-size: 11px; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; }
.sn-nav { display: grid; gap: 2px; }
.sn-link { position: relative; display: flex; align-items: center; gap: 12px; min-height: 44px; padding: 0 12px; border-radius: 12px; color: #57544F; font-size: 14.5px; transition: background-color .15s ease, color .15s ease; }
.sn-link i { flex: none; width: 18px; font-size: 14px; text-align: center; }
.sn-link:hover { background: #F7F6F4; color: #161616; }
.sn-link[aria-current="page"] { background: #F4F3F0; color: #161616; font-weight: 500; }
.sn-link[aria-current="page"]::before { content: ''; position: absolute; left: 3px; top: 12px; bottom: 12px; width: 3px; border-radius: 3px; background: var(--theme-primary, #161616); }
.sn-link--danger, .sn-link--danger:hover { color: #B91C1C; }
.sn-link--danger:hover { background: #FEF2F2; }

.sn-badge { flex: none; margin-left: auto; min-width: 22px; padding: 0 7px; border-radius: 999px; background: #B45309; color: #FFFFFF; font-size: 12px; font-weight: 500; line-height: 20px; text-align: center; font-variant-numeric: tabular-nums; }

.sn-foot { flex: none; padding: 10px 12px 12px; border-top: 1px solid #F1EFEC; }
.sn-me { display: flex; align-items: center; gap: 10px; padding: 8px 10px 10px; }
.sn-av { display: grid; place-items: center; flex: none; width: 38px; height: 38px; border-radius: 50%; background: #F4F3F0; box-shadow: inset 0 0 0 1px rgba(22, 22, 22, .08); color: #3F3D39; font-size: 16px; font-weight: 600; }
.sn-me__who { min-width: 0; line-height: 1.3; }
.sn-me__name { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 14px; font-weight: 500; }
.sn-state { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: #6B6862; }
.sn-state::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: #A8A49D; }
.sn-state.is-on { color: #166534; }
.sn-state.is-on::before { background: #16A34A; }

/* ปุ่มไอคอน (มือถือ) */
.sn-icon-btn { display: inline-grid; place-items: center; flex: none; width: 44px; height: 44px; padding: 0; border: 0; border-radius: 12px; background: transparent; color: #161616; font-size: 18px; cursor: pointer; }
.sn-icon-btn:hover { background: #F4F3F0; }

/* แถบบน + แถบล่าง + overlay: ซ่อนบนเดสก์ท็อป */
.sn-top, .sn-bottom, .sn-overlay { display: none; }

@media (min-width: 1024px) {
    body.sn-on, body:has(> .sn-aside) { padding-left: 264px; }
}

@media (max-width: 1023px) {
    body.sn-on, body:has(> .sn-aside) { padding-bottom: calc(60px + env(safe-area-inset-bottom, 0px)); }
    html.sn-lock { overflow: hidden; }

    /* ลิ้นชักเมนู */
    .sn-aside { width: min(320px, 86vw); transform: translateX(-100%); visibility: hidden; box-shadow: none; transition: transform .25s cubic-bezier(.16, 1, .3, 1), visibility 0s linear .25s; }
    .sn-aside.is-open { transform: none; visibility: visible; box-shadow: 0 24px 48px rgba(22, 22, 22, .24); transition: transform .25s cubic-bezier(.16, 1, .3, 1); }
    .sn-icon-btn.sn-close { display: inline-grid; }
    .sn-overlay { position: fixed; inset: 0; z-index: 55; display: block; background: rgba(22, 22, 22, .45); opacity: 0; pointer-events: none; transition: opacity .2s ease; }
    .sn-overlay.is-open { opacity: 1; pointer-events: auto; }

    /* แถบบน */
    .sn-top { position: sticky; top: 0; z-index: 50; display: flex; align-items: center; flex: none; gap: 4px; height: 56px; padding: 0 12px 0 6px; border-bottom: 1px solid #ECEAE6; background: rgba(255, 255, 255, .96); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); }
    .sn-top__title { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 16px; font-weight: 500; color: #161616; }
    .sn-pill { flex: none; padding: 0 10px; border-radius: 999px; background: #F4F3F0; font-size: 12px; line-height: 26px; }

    /* แถบแท็บล่าง */
    .sn-bottom { position: fixed; inset: auto 0 0 0; z-index: 50; grid-template-columns: repeat(4, 1fr); padding-bottom: env(safe-area-inset-bottom, 0px); border-top: 1px solid #ECEAE6; background: #FFFFFF; }
    .sn-bottom { display: grid; }
    .sn-tab { position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; min-height: 60px; padding: 0; border: 0; background: transparent; color: #6B6862; font: inherit; font-size: 12px; cursor: pointer; }
    .sn-tab i { font-size: 18px; }
    .sn-tab[aria-current="page"] { color: #161616; font-weight: 500; }
    .sn-tab[aria-current="page"]::before { content: ''; position: absolute; top: 0; left: 50%; width: 28px; height: 3px; margin-left: -14px; border-radius: 0 0 3px 3px; background: var(--theme-primary, #161616); }
    .sn-tab .sn-badge { position: absolute; top: 6px; left: 50%; margin-left: 6px; min-width: 18px; padding: 0 5px; border: 2px solid #FFFFFF; font-size: 11px; line-height: 14px; box-sizing: content-box; }
}

@media print {
    .sn-aside, .sn-top, .sn-bottom, .sn-overlay { display: none !important; }
    body.sn-on, body:has(> .sn-aside) { padding: 0 !important; }
}
</style>

<header class="sn-top">
    <button type="button" class="sn-icon-btn" data-sn-open aria-controls="snAside" aria-expanded="false" aria-label="เปิดเมนู"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>
    <div class="sn-top__title"><?= $snH($snTitle) ?></div>
    <?php if ($snQueueOn !== null): ?>
        <span class="sn-pill sn-state<?= $snQueueOn ? ' is-on' : '' ?>"><?= $snQueueOn ? 'เปิดรับคิว' : 'พักคิว' ?></span>
    <?php endif; ?>
</header>

<div class="sn-overlay" id="snOverlay" aria-hidden="true"></div>

<aside class="sn-aside" id="snAside" aria-label="เมนูสตาฟ">
    <div class="sn-brand">
        <img src="<?= $snH(app_logo_url($conn, '../')) ?>" alt="">
        <div class="sn-brand__text">
            <span class="sn-brand__name"><?= $snH($snSite) ?></span>
            <span class="sn-brand__role">เจ้าหน้าที่ฝ่ายซ่อม</span>
        </div>
        <?= $snNotifications ?>
        <button type="button" class="sn-icon-btn sn-close" data-sn-close aria-label="ปิดเมนู"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
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

        <div class="sn-label" style="margin-top:12px">บัญชี</div>
        <nav class="sn-nav" aria-label="บัญชี">
            <a class="sn-link" href="<?= $snH($snProfileHref) ?>"><i class="fa-solid fa-user" aria-hidden="true"></i><span>โปรไฟล์ของฉัน</span></a>
        </nav>
    </div>

    <div class="sn-foot">
        <div class="sn-me">
            <span class="sn-av" aria-hidden="true"><?= $snH($snInitial) ?></span>
            <div class="sn-me__who">
                <span class="sn-me__name"><?= $snH($snName !== '' ? $snName : 'สตาฟ') ?></span>
                <?php if ($snQueueOn !== null): ?>
                    <span class="sn-state<?= $snQueueOn ? ' is-on' : '' ?>"><?= $snQueueOn ? 'เปิดรับคิวอยู่' : 'พักรับคิว' ?></span>
                <?php endif; ?>
            </div>
        </div>
        <a class="sn-link sn-link--danger" href="<?= $snH($snLogoutHref) ?>"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i><span>ออกจากระบบ</span></a>
    </div>
</aside>

<nav class="sn-bottom" aria-label="เมนูด่วน">
    <?php foreach ($snItems as $snKey => $snItem): $snB = $snBadgeFor($snKey); ?>
        <a class="sn-tab" href="<?= $snH($snItem['href']) ?>"<?= $snActive === $snKey ? ' aria-current="page"' : '' ?>>
            <i class="fa-solid <?= $snH($snItem['icon']) ?>" aria-hidden="true"></i>
            <span><?= $snH($snItem['short']) ?></span>
            <?php if ($snB > 0): ?><span class="sn-badge"><?= $snB ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
    <button type="button" class="sn-tab" data-sn-open aria-controls="snAside" aria-expanded="false">
        <i class="fa-solid fa-bars" aria-hidden="true"></i><span>เมนู</span>
    </button>
</nav>

<script>
(function () {
    var d = document, aside = d.getElementById('snAside'), overlay = d.getElementById('snOverlay');
    if (!aside) return;
    d.body.classList.add('sn-on');
    var openers = Array.prototype.slice.call(d.querySelectorAll('[data-sn-open]'));
    var lastFocus = null;

    function setOpen(open) {
        aside.classList.toggle('is-open', open);
        overlay.classList.toggle('is-open', open);
        d.documentElement.classList.toggle('sn-lock', open);
        openers.forEach(function (o) { o.setAttribute('aria-expanded', open ? 'true' : 'false'); });
        if (open) {
            lastFocus = d.activeElement;
            var first = aside.querySelector('[data-sn-close]');
            if (first) first.focus();
        } else if (lastFocus) {
            lastFocus.focus();
            lastFocus = null;
        }
    }

    openers.forEach(function (o) { o.addEventListener('click', function () { setOpen(true); }); });
    overlay.addEventListener('click', function () { setOpen(false); });
    aside.querySelector('[data-sn-close]').addEventListener('click', function () { setOpen(false); });

    d.addEventListener('keydown', function (e) {
        if (!aside.classList.contains('is-open')) return;
        if (e.key === 'Escape') { setOpen(false); return; }
        if (e.key !== 'Tab') return;
        // วนโฟกัสอยู่ในลิ้นชักตอนเปิด
        var f = aside.querySelectorAll('a[href], button:not([disabled])');
        if (!f.length) return;
        var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && d.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && d.activeElement === last) { e.preventDefault(); first.focus(); }
    });

    // ขยายเป็นจอใหญ่ตอนลิ้นชักเปิดอยู่ → ปิดและปลดล็อกการเลื่อน
    var mq = window.matchMedia('(min-width: 1024px)');
    var onChange = function (e) { if (e.matches && aside.classList.contains('is-open')) setOpen(false); };
    if (mq.addEventListener) mq.addEventListener('change', onChange); else if (mq.addListener) mq.addListener(onChange);
})();
</script>
