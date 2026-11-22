<?php
declare(strict_types=1);

/**
 * REPS · includes/requester_nav.php — เมนูสำหรับ "ผู้แจ้ง" (role = user) เท่านั้น
 *
 * แทน <header class="admin-topbar"> เดิมของหน้าผู้แจ้ง — วางต่อจาก <body> ในตำแหน่งเดียวกับ header เดิม
 *   เดสก์ท็อป (>880px) : แถบบนแบบเดิม (โลโก้ · ปุ่มแจ้งซ่อมใหม่ · กระดิ่ง · TH/EN · เมนูบัญชี)
 *   มือถือ (≤880px)    : แถบบน (ปุ่มเมนู · โลโก้ · กระดิ่ง · TH/EN · รูปโปรไฟล์)
 *                        + ลิ้นชักเมนู #requester-sidebar + แถบแท็บล่าง ใบงาน | [+ แจ้งซ่อม] | ประวัติ
 *
 * ตัวแปรที่รับจากหน้า ($conn ต้องมี):
 *   $rqActive  'jobs' | 'history' | 'new' | 'profile'   หน้าที่กำลังเปิดอยู่
 *   $rqBottom  bool (ค่าเริ่มต้น true) false = ไม่แสดงแถบแท็บล่าง (หน้าที่มีแถบปุ่มของตัวเอง เช่นฟอร์มแจ้งซ่อม)
 *   $rqBase    prefix path (ค่าเริ่มต้น '')
 *
 * หน้าที่ใช้ร่วมกับ staff/admin (profile.php, request_form.php) ให้เรียกเฉพาะเมื่อ current_role() === 'user' — role อื่นคงเมนูเดิม
 */

require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/user_menu.php';

$rqActive = (string)($rqActive ?? '');
$rqBottom = (bool)($rqBottom ?? true);
$rqBase = (string)($rqBase ?? '');
$rqH = static fn(mixed $v): string => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');

// เมนูหลัก (เพิ่มหน้าใหม่ที่นี่ที่เดียว)
$rqItems = [
    'jobs'    => ['href' => 'dashboard.php',    'icon' => 'fa-clipboard-list',   'label' => tr('ใบงานของฉัน', 'My requests'), 'short' => tr('ใบงาน', 'Requests')],
    'new'     => ['href' => 'request_form.php', 'icon' => 'fa-plus',             'label' => tr('แจ้งซ่อมใหม่', 'New request'), 'short' => tr('แจ้งซ่อม', 'Report')],
    'history' => ['href' => 'history.php',      'icon' => 'fa-clock-rotate-left', 'label' => tr('ประวัติการแจ้งซ่อม', 'History'),  'short' => tr('ประวัติ', 'History')],
];
?>
<style>
/* ===== REPS requester shell ===== */
:root { --rq-accent: #BE2A5A; --rq-accent-soft: #FBEAF0; --rq-ink: #161616; --rq-muted: #6B6862; --rq-line: #ECEAE6; --rq-dark: #2A2226; }

.rq-top, .rq-aside, .rq-bottom, .rq-overlay { font-family: 'Kanit', system-ui, sans-serif; }
.rq-top *, .rq-aside *, .rq-bottom * { box-sizing: border-box; }
.rq-top a, .rq-aside a, .rq-bottom a { text-decoration: none; }

/* ---- แถบบน (ใช้ร่วมกันทั้งเดสก์ท็อปและมือถือ) ---- */
.rq-top { position: sticky; top: 0; z-index: 50; width: 100%; height: 64px; border-bottom: 1px solid var(--rq-line); background: rgba(255, 255, 255, .94); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); box-shadow: 0 1px 2px rgba(22, 22, 22, .035); }
.rq-top__in { display: flex; align-items: center; gap: 12px; height: 100%; max-width: 72rem; margin: 0 auto; padding: 0 28px; }
.rq-brand { display: inline-flex; align-items: center; flex: none; }
.rq-brand img { display: block; width: auto; height: 24px; }
.rq-desktop-nav { display: flex; align-items: center; gap: 4px; margin-left: 20px; }
.rq-desktop-link { display: inline-flex; align-items: center; min-height: 40px; padding: 0 12px; border-radius: 999px; color: var(--rq-muted); font-size: 13px; font-weight: 400; white-space: nowrap; transition: background-color .15s ease, color .15s ease; }
.rq-desktop-link:hover, .rq-desktop-link[aria-current="page"] { background: #F4F3F0; color: var(--rq-ink); }
.rq-desktop-link[aria-current="page"] { font-weight: 500; }
.rq-top__spacer { flex: 1 1 auto; }
.rq-top__end { display: flex; align-items: center; gap: 12px; }
.rq-burger { display: inline-grid; }
.rq-newbtn { display: inline-flex; align-items: center; gap: 8px; height: 40px; padding: 0 18px; border-radius: 999px; background: #161616; color: #fff; font-size: 14px; font-weight: 500; white-space: nowrap; transition: background-color .15s ease; }
.rq-newbtn:hover { background: #2E2E2E; }
.rq-newbtn i { font-size: 12px; }

/* ปุ่มสี่เหลี่ยมมน (มือถือ) */
.rq-iconbtn { display: inline-grid; place-items: center; flex: none; width: 44px; height: 44px; padding: 0; border: 1px solid var(--rq-line); border-radius: 14px; background: #fff; color: var(--rq-ink); font-size: 17px; cursor: pointer; box-shadow: 0 1px 2px rgba(22, 22, 22, .04); transition: background-color .15s ease; }
.rq-iconbtn:hover { background: #F7F6F4; }

/* ---- ลิ้นชัก / overlay / แถบล่าง: ซ่อนบนเดสก์ท็อป ---- */
.rq-bottom { display: none; }

html.rq-lock { overflow: hidden; }
/* ลิ้นชักเมนู */
.rq-overlay { position: fixed; inset: 0; z-index: 55; display: block; background: rgba(22, 22, 22, .45); opacity: 0; pointer-events: none; transition: opacity .2s ease; }
.rq-overlay.is-open { opacity: 1; pointer-events: auto; }
.rq-aside { position: fixed; inset: 0 auto 0 0; z-index: 60; display: flex; flex-direction: column; width: min(320px, 86vw); background: #fff; color: var(--rq-ink); transform: translateX(-100%); visibility: hidden; transition: transform .25s cubic-bezier(.16, 1, .3, 1), visibility 0s linear .25s; }
.rq-aside.is-open { transform: none; visibility: visible; box-shadow: 0 24px 48px rgba(22, 22, 22, .24); transition: transform .25s cubic-bezier(.16, 1, .3, 1); }
.rq-aside__head { display: flex; align-items: center; justify-content: space-between; flex: none; height: 64px; padding: 0 12px 0 18px; border-bottom: 1px solid var(--rq-line); }
.rq-aside__head img { display: block; height: 26px; width: auto; }
.rq-aside__scroll { flex: 1 1 auto; overflow-y: auto; overscroll-behavior: contain; padding: 14px 12px; }
.rq-label { padding: 12px 12px 6px; color: var(--rq-muted); font-size: 11px; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; }
.rq-nav { display: grid; gap: 2px; }
.rq-link { position: relative; display: flex; align-items: center; gap: 12px; min-height: 48px; padding: 0 12px; border-radius: 14px; color: #57544F; font-size: 15px; }
.rq-link i { flex: none; width: 20px; font-size: 15px; text-align: center; }
.rq-link:hover { background: #F7F6F4; color: var(--rq-ink); }
.rq-link[aria-current="page"] { background: var(--rq-accent-soft); color: var(--rq-accent); font-weight: 500; }
.rq-link--danger, .rq-link--danger:hover { color: #B91C1C; }
.rq-link--danger:hover { background: #FEF2F2; }
.rq-aside__foot { flex: none; padding: 10px 12px calc(12px + env(safe-area-inset-bottom, 0px)); border-top: 1px solid var(--rq-line); }


@media (max-width: 880px) {
    .rq-top__in { gap: 8px; padding: 0 12px; }
    .rq-desktop-nav { display: none; }
    .rq-burger { display: inline-grid; }
    .rq-newbtn { display: none; }
    .rq-brand img { height: 26px; }
    .rq-top__end { gap: 6px; }

    /* กระดิ่ง → ปุ่มสี่เหลี่ยมมนเหมือนปุ่มเมนู */
    .rq-top .stock-alert__btn { width: 44px; height: 44px; border: 1px solid var(--rq-line); border-radius: 14px; background: #fff; box-shadow: 0 1px 2px rgba(22, 22, 22, .04); }
    .rq-top .stock-alert__icon { font-size: 18px; }
    .rq-top .stock-alert__count { top: -4px; right: -4px; border-color: #fff; background: var(--rq-accent); }
    .rq-top .lang-switch { padding: 3px; }
    .rq-top .lang-switch__opt { min-width: 38px; height: 34px; }
    .rq-top .lang-switch__opt.is-current { background: var(--rq-dark); }
    /* เมนูบัญชี: เหลือแค่รูปวงกลมโต */
    .rq-top .user-menu__name, .rq-top .user-menu__chev { display: none; }
    .rq-top .user-menu__btn { min-height: 0; padding: 0; border: 1px solid var(--rq-line); border-radius: 14px; background: #fff; box-shadow: 0 1px 2px rgba(22, 22, 22, .04); }
    .rq-top .user-menu__btn:hover, .rq-top .user-menu[open] .user-menu__btn { background: #fff; }
    .rq-top .user-menu__btn .user-avatar { width: 44px; height: 44px; margin: -1px; border-radius: 50%; border: 3px solid #fff; background: var(--rq-dark); box-shadow: none; color: #fff; font-size: 16px; }
    .rq-top .user-menu[open] .user-menu__btn .user-avatar { box-shadow: 0 0 0 2px var(--rq-accent); }
    .rq-top .user-menu__panel { position: fixed; top: 68px; right: 12px; left: auto; width: min(320px, calc(100vw - 24px)); }

    /* แถบแท็บล่าง: ใบงาน | [+ แจ้งซ่อม] | ประวัติ */
    body.rq-has-bottom { padding-bottom: calc(76px + env(safe-area-inset-bottom, 0px)); }
    .rq-bottom { position: fixed; inset: auto 0 0 0; z-index: 50; display: grid; grid-template-columns: repeat(3, 1fr); align-items: end; gap: 4px; padding: 8px 12px calc(8px + env(safe-area-inset-bottom, 0px)); border-top: 1px solid var(--rq-line); background: rgba(255, 255, 255, .97); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); }
    .rq-tab { position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; min-height: 56px; padding: 6px 4px; border-radius: 16px; color: var(--rq-muted); font-size: 12px; line-height: 1.2; transition: background-color .15s ease, color .15s ease; }
    .rq-tab i { font-size: 18px; }
    .rq-tab[aria-current="page"] { background: #F4F3F0; color: var(--rq-ink); font-weight: 500; }
    .rq-tab--fab { background: transparent !important; color: var(--rq-muted); font-weight: 400; }
    .rq-tab--fab .rq-fab { display: grid; place-items: center; width: 52px; height: 52px; margin-top: -26px; border: 4px solid #fff; border-radius: 50%; background: var(--rq-accent); color: #fff; font-size: 20px; box-shadow: 0 8px 18px -4px rgba(190, 42, 90, .55); transition: transform .15s ease; }
    .rq-tab--fab:active .rq-fab { transform: scale(.94); }
    .rq-tab--fab[aria-current="page"] { color: var(--rq-accent); font-weight: 500; }

    /* ปรับหน้า: ช่องไฟกระชับ + การ์ดที่เลือกอยู่ใช้สี accent ตามรูป */
    body.rq-on main { padding-top: 20px; }
    body.rq-on .admin-page-head { margin-bottom: 18px; }
    body.rq-on .admin-stat-card-wrapper.is-active { border-color: var(--rq-accent); box-shadow: 0 0 0 1px var(--rq-accent); }
    body.rq-on .admin-stat-card-wrapper.is-active .admin-stat-inner { border-color: var(--rq-accent-soft); }
}

/* จอใหญ่ (>880px): sidebar ติดซ้ายใต้แถบบนค้างไว้ ดันเนื้อหา · ☰ ใช้พับ/ขยาย */
@media (min-width: 881px) {
    :root { --rq-side: 264px; }
    .rq-bottom, .rq-overlay { display: none !important; }
    html.rq-lock { overflow: visible; }
    body.rq-on { padding-left: var(--rq-side); transition: padding-left .25s cubic-bezier(.16, 1, .3, 1); }
    body.rq-on .rq-top { width: calc(100% + var(--rq-side)); margin-left: calc(-1 * var(--rq-side)); transition: width .25s cubic-bezier(.16, 1, .3, 1), margin-left .25s cubic-bezier(.16, 1, .3, 1); }
    .rq-top__in { max-width: none; padding: 0 20px; }
    .rq-aside { inset: 64px auto 0 0; z-index: 45; width: var(--rq-side); background: #FAFAF9; transform: none; visibility: visible; box-shadow: none; border-right: 1px solid var(--rq-line); transition: transform .25s cubic-bezier(.16, 1, .3, 1), visibility 0s; }
    .rq-aside__head { display: none; }
    html:not(.rq-collapsed) .rq-desktop-nav { display: none; }
    html.rq-collapsed body.rq-on { padding-left: 0; }
    html.rq-collapsed body.rq-on .rq-top { width: 100%; margin-left: 0; }
    html.rq-collapsed .rq-aside { transform: translateX(-100%); visibility: hidden; transition: transform .25s cubic-bezier(.16, 1, .3, 1), visibility 0s linear .25s; }
}

@media print {
    .rq-top, .rq-aside, .rq-bottom, .rq-overlay { display: none !important; }
    body.rq-has-bottom { padding-bottom: 0 !important; }
}
</style>

<header class="rq-top admin-topbar">
    <div class="rq-top__in">
        <button type="button" class="rq-iconbtn rq-burger" data-rq-open aria-controls="requester-sidebar" aria-expanded="false" aria-label="<?= $rqH(tr('เปิดเมนู', 'Open menu')) ?>">
            <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>
        <a href="<?= $rqH($rqBase) ?>dashboard.php" class="rq-brand" aria-label="REPS">
            <img src="<?= $rqH($rqBase) ?>assets/images/logo/reps-logo.svg" alt="REPS">
        </a>
        <nav class="rq-desktop-nav" aria-label="<?= $rqH(tr('เมนูหลัก', 'Main menu')) ?>">
            <?php foreach (['jobs', 'history'] as $rqKey): $rqItem = $rqItems[$rqKey]; ?>
                <a class="rq-desktop-link" href="<?= $rqH($rqBase . $rqItem['href']) ?>"<?= $rqActive === $rqKey ? ' aria-current="page"' : '' ?>><?= $rqH($rqItem['label']) ?></a>
            <?php endforeach; ?>
        </nav>
        <span class="rq-top__spacer"></span>
        <div class="rq-top__end">
            <a href="<?= $rqH($rqBase) ?>request_form.php" class="rq-newbtn">
                <i class="fa-solid fa-plus" aria-hidden="true"></i><span><?= $rqH(tr('แจ้งซ่อมใหม่', 'New request')) ?></span>
            </a>
            <?= notif_bell($conn, $rqBase) ?>
            <?= user_menu_render($conn, $rqBase) ?>
        </div>
    </div>
</header>

<div class="rq-overlay" id="rqOverlay" aria-hidden="true"></div>

<aside class="rq-aside" id="requester-sidebar" aria-label="<?= $rqH(tr('เมนูผู้แจ้ง', 'Requester menu')) ?>">
    <div class="rq-aside__head">
        <img src="<?= $rqH($rqBase) ?>assets/images/logo/reps-logo.svg" alt="REPS">
        <button type="button" class="rq-iconbtn" data-rq-close aria-label="<?= $rqH(tr('ปิดเมนู', 'Close menu')) ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </div>
    <div class="rq-aside__scroll">
        <div class="rq-label"><?= $rqH(tr('เมนูหลัก', 'Main menu')) ?></div>
        <nav class="rq-nav" aria-label="<?= $rqH(tr('เมนูหลัก', 'Main menu')) ?>">
            <?php foreach ($rqItems as $rqKey => $rqItem): ?>
                <a class="rq-link" href="<?= $rqH($rqBase . $rqItem['href']) ?>"<?= $rqActive === $rqKey ? ' aria-current="page"' : '' ?>>
                    <i class="fa-solid <?= $rqH($rqItem['icon']) ?>" aria-hidden="true"></i><span><?= $rqH($rqItem['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="rq-label"><?= $rqH(tr('บัญชี', 'Account')) ?></div>
        <nav class="rq-nav" aria-label="<?= $rqH(tr('บัญชี', 'Account')) ?>">
            <a class="rq-link" href="<?= $rqH($rqBase) ?>profile.php"<?= $rqActive === 'profile' ? ' aria-current="page"' : '' ?>>
                <i class="fa-solid fa-user-pen" aria-hidden="true"></i><span><?= $rqH(tr('แก้ไขโปรไฟล์', 'Edit profile')) ?></span>
            </a>
        </nav>
    </div>
    <div class="rq-aside__foot">
        <a class="rq-link rq-link--danger" href="<?= $rqH($rqBase) ?>logout.php">
            <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i><span><?= $rqH(tr('ออกจากระบบ', 'Sign out')) ?></span>
        </a>
    </div>
</aside>

<?php if ($rqBottom): ?>
<nav class="rq-bottom" aria-label="<?= $rqH(tr('เมนูด่วน', 'Quick menu')) ?>">
    <a class="rq-tab" href="<?= $rqH($rqBase) ?>dashboard.php"<?= $rqActive === 'jobs' ? ' aria-current="page"' : '' ?>>
        <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i><span><?= $rqH($rqItems['jobs']['short']) ?></span>
    </a>
    <a class="rq-tab rq-tab--fab" href="<?= $rqH($rqBase) ?>request_form.php"<?= $rqActive === 'new' ? ' aria-current="page"' : '' ?>>
        <span class="rq-fab" aria-hidden="true"><i class="fa-solid fa-plus"></i></span><span><?= $rqH($rqItems['new']['short']) ?></span>
    </a>
    <a class="rq-tab" href="<?= $rqH($rqBase) ?>history.php"<?= $rqActive === 'history' ? ' aria-current="page"' : '' ?>>
        <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><span><?= $rqH($rqItems['history']['short']) ?></span>
    </a>
</nav>
<?php endif; ?>

<script>
(function () {
    var d = document, html = d.documentElement, aside = d.getElementById('requester-sidebar'), overlay = d.getElementById('rqOverlay');
    if (!aside) return;
    d.body.classList.add('rq-on');
    <?php if ($rqBottom): ?>d.body.classList.add('rq-has-bottom');<?php endif; ?>

    var mq = window.matchMedia('(min-width: 881px)');
    var KEY = 'repsRequesterNavCollapsed';
    var openers = Array.prototype.slice.call(d.querySelectorAll('[data-rq-open]'));
    var closeBtn = aside.querySelector('[data-rq-close]');
    var lastFocus = null;

    try { if (localStorage.getItem(KEY) === '1') html.classList.add('rq-collapsed'); } catch (e) {}

    function syncAria() {
        var expanded = mq.matches ? !html.classList.contains('rq-collapsed') : aside.classList.contains('is-open');
        openers.forEach(function (o) { o.setAttribute('aria-expanded', expanded ? 'true' : 'false'); });
    }
    // มือถือ: ลิ้นชัก
    function setOpen(open) {
        aside.classList.toggle('is-open', open);
        overlay.classList.toggle('is-open', open);
        html.classList.toggle('rq-lock', open);
        syncAria();
        if (open) {
            lastFocus = d.activeElement;
            if (closeBtn) closeBtn.focus();
        } else if (lastFocus) {
            lastFocus.focus();
            lastFocus = null;
        }
    }
    // จอใหญ่: พับ/ขยาย sidebar (จำค่าไว้)
    function toggleSide() {
        var c = html.classList.toggle('rq-collapsed');
        try { localStorage.setItem(KEY, c ? '1' : '0'); } catch (e) {}
        syncAria();
    }

    openers.forEach(function (o) {
        o.addEventListener('click', function () {
            if (mq.matches) toggleSide(); else setOpen(!aside.classList.contains('is-open'));
        });
    });
    overlay.addEventListener('click', function () { setOpen(false); });
    if (closeBtn) closeBtn.addEventListener('click', function () { setOpen(false); });
    syncAria();

    d.addEventListener('keydown', function (e) {
        if (mq.matches || !aside.classList.contains('is-open')) return;
        if (e.key === 'Escape') { setOpen(false); return; }
        if (e.key !== 'Tab') return;
        var f = aside.querySelectorAll('a[href], button:not([disabled])');
        if (!f.length) return;
        var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && d.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && d.activeElement === last) { e.preventDefault(); first.focus(); }
    });

    // ข้ามจุดตัดจอ → ปิดลิ้นชักมือถือที่ค้างอยู่ และปลดล็อกการเลื่อน
    var onChange = function () { if (aside.classList.contains('is-open')) setOpen(false); else syncAria(); };
    if (mq.addEventListener) mq.addEventListener('change', onChange); else if (mq.addListener) mq.addListener(onChange);
})();
</script>
