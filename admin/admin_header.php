<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/stock_alert.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/user_menu.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/site_menu.php';

$current_page = $current_page ?? 'dashboard';
// หน้าที่อยู่นอกโฟลเดอร์ admin (เช่น staff/inventory.php) ต้องอ้างลิงก์เมนูด้วย ../admin/
$adminLinkBase = basename(dirname((string)($_SERVER['SCRIPT_NAME'] ?? ''))) === 'admin' ? '' : '../admin/';
$headerUserName = trim((string)($_SESSION['user_name'] ?? ''));
if ($headerUserName === '') {
   $headerUserName = 'ผู้ดูแลระบบ';
}
$headerUserRole = ($_SESSION['u_role'] ?? '') === 'admin' ? 'ผู้ดูแลระบบ' : (string)($_SESSION['u_role'] ?? '');
$headerSections = [
   'dashboard'     => tr('แดชบอร์ด', 'Dashboard'),
   'request'       => tr('จัดการคำขอซ่อม', 'Repair requests'),
   'users'         => tr('จัดการผู้ใช้', 'Users'),
   'reports'       => tr('รายงานตามช่วงวันที่', 'Report by date range'),
   'report_dept'   => tr('รายงานตามแผนก/งาน', 'Report by department'),
   'scores'        => tr('คะแนนสตาฟ', 'Staff scores'),
   'thanks'        => tr('คำขอบคุณ Lab boy', 'Lab boy thanks'),
   'notifications' => tr('การแจ้งเตือน', 'Notifications'),
   'history'       => tr('ประวัติ', 'History'),
   'system'        => tr('ตรวจระบบ', 'System check'),
   'signoff'       => tr('รับรองงาน', 'Sign-off'),
   'settings'      => tr('ตั้งค่าระบบ', 'Settings'),
   'site'          => tr('ปรับแต่งเว็บ', 'Customize site'),
   'terms'         => tr('ภาคเรียนและทีม', 'Terms & team'),
   'inventory'     => tr('สต็อกวัสดุ', 'Inventory'),
   'staffview'     => tr('มุมมองสตาฟ', 'Staff view'),
   'warroom'       => tr('ภาพรวมงาน', 'Job overview'),
   'teacher_report'=> tr('รายงานตามภาคเรียน', 'Report by term'),
];
$headerAwaitingCount = 0;
$headerPendingUsers = 0;
if (isset($conn) && $conn instanceof mysqli) {
   $headerCountRes = $conn->query("SELECT (SELECT COUNT(*) FROM tickets WHERE status IN ('awaiting_admin_sign', 'disputed')) AS awaiting, (SELECT COUNT(*) FROM users WHERE status = 'pending') AS pending_users");
   if ($headerCountRes) {
      $headerCounts = $headerCountRes->fetch_assoc();
      $headerAwaitingCount = (int)($headerCounts['awaiting'] ?? 0);
      $headerPendingUsers = (int)($headerCounts['pending_users'] ?? 0);
   }
}
$headerSection = $headerSections[$current_page] ?? tr('ภาพรวมระบบ', 'Overview');

$headerInitial = mb_strtoupper(mb_substr(trim($headerUserName), 0, 1, 'UTF-8'), 'UTF-8');
if ($headerInitial === '') {
   $headerInitial = 'A';
}

$thaiDayShort = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'];
$thaiMonthShort = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
$headerDateLabel = app_lang() === 'en'
   ? date('D j M Y')
   : $thaiDayShort[(int)date('w')] . ' ' . (int)date('j') . ' ' . $thaiMonthShort[(int)date('n') - 1] . ' ' . ((int)date('Y') + 543);
?>
<!DOCTYPE html>
<html lang="th">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title><?= isset($page_title) ? htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') . ' | REPS' : 'REPS - Admin Panel'; ?></title>
   <link rel="icon" type="image/svg+xml" href="../assets/images/logo/reps-icon.svg">
   <link rel="alternate icon" type="image/png" sizes="32x32" href="../assets/images/logo/favicon-32.png">
   <link rel="apple-touch-icon" href="../assets/images/logo/apple-touch-icon.png">

   <!-- Shared admin fonts -->
   <link rel="preconnect" href="https://fonts.googleapis.com">
   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
   <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
   <style>
      * {
         box-sizing: border-box;
      }

      /* Shared admin typography */
      body, h1, h2, h3, h4, h5, h6, span {
         font-family: 'Kanit', sans-serif;
      }

      /* Sidebar nav — ตัวชี้เหลือ/เข้มของเมนูทำตาม .nav-link ของ dashboard.php
         เส้นซ้ายหนา 2px เพื่อให้เห็นตอน hover (ถ้าใช้ 1px จะถูกเส้นรางของ sidebar บัง) */
      .side-nav {
         border-left: 1px solid #E5E7EB;
      }

      .side-nav a {
         display: block;
         margin-left: -1px;
         padding: 8px 16px;
         border-left: 2px solid transparent;
         font-size: 15px;
         line-height: 1.6;
         color: #696969;
         text-decoration: none;
         transition: color .15s, border-color .15s;
      }

      .side-nav a:hover {
         color: #111827;
         border-left-color: #D1D5DB;
      }

      .side-nav a:focus-visible {
         outline: 2px solid #111827;
         outline-offset: -2px;
      }

      .side-nav a[aria-current="page"] {
         color: #111827;
         border-left-color: #111827;
         font-weight: 500;
      }
   </style>

   <link rel="stylesheet" href="../assets/css/app.css?v=<?= (int)filemtime(__DIR__ . '/../assets/css/app.css') ?>">
   <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
   <style>
      :root {
         /* โทนเดียวกับ .dash ใน admin_dashboard.php — พื้นหลังอ่อนเกือบขาว */
         --admin-bg: #FAFAF8;
         --admin-sidebar: #F7F6F4;
         --admin-tray: #F5F4F1;
         --admin-tray-line: #EAE8E4;
         --admin-card: #FFFFFF;
         --admin-line: #ECEAE6;
         --admin-line-strong: #D9D6D0;
         --admin-ink: #161616;
         --admin-muted: #6B6862;
         --admin-soft: #F5F4F1;
      }

      body.admin-shell {
         background: var(--admin-bg);
         color: var(--admin-ink);
         font-family: 'Kanit', sans-serif;
      }

      body.admin-shell,
      body.admin-shell button,
      body.admin-shell input,
      body.admin-shell select,
      body.admin-shell textarea {
         font-family: 'Kanit', sans-serif;
      }

      .admin-topbar {
         background: #FFFFFF !important;
         border-color: var(--admin-line) !important;
         box-shadow: 0 1px 2px rgba(22, 22, 22, .035);
      }

      .admin-topbar a:focus-visible,
      .admin-topbar button:focus-visible {
         outline: 2px solid var(--admin-ink);
         outline-offset: 2px;
      }

      /* ===== Brand (อยู่ที่หัว sidebar) ===== */
      .admin-brand {
         color: var(--admin-ink);
         letter-spacing: -.01em;
      }

      .admin-brand-divider {
         display: none;
         width: 1px;
         height: 26px;
         background: var(--admin-line);
         flex: none;
      }

      .admin-brand-copy {
         display: block;
         min-width: 0;
      }

      .admin-brand-label {
         display: block;
         color: var(--admin-ink);
         font-family: 'Kanit', sans-serif;
         font-size: 11px;
         font-weight: 600;
         letter-spacing: .14em;
         text-transform: uppercase;
         line-height: 1.2;
      }

      .admin-brand-sub {
         display: block;
         margin-top: 2px;
         color: var(--admin-muted);
         font-family: 'Kanit', sans-serif;
         font-size: 11px;
         font-weight: 400;
         letter-spacing: .12em;
      }

      /* ป้ายหัวข้อหน้าใน topbar (แทนโลโก้ที่ย้ายไป sidebar แล้ว) */
      .admin-topbar .admin-topbar-titles {
         display: flex;
         flex-direction: column;
         justify-content: center;
         line-height: 1.25;
      }

      .admin-topbar .admin-topbar-eyebrow {
         color: var(--admin-muted);
         font-family: 'Kanit', sans-serif;
         font-size: 11px;
         font-weight: 500;
         letter-spacing: .14em;
         text-transform: uppercase;
      }

      .admin-topbar .admin-topbar-section {
         color: var(--admin-ink);
         font-size: 14px;
         font-weight: 500;
         letter-spacing: -.01em;
         white-space: nowrap;
         overflow: hidden;
         text-overflow: ellipsis;
      }

      @media (max-width: 639px) {
         .admin-topbar .admin-topbar-eyebrow {
            display: none;
         }
      }

      .admin-topbar .admin-nav-toggle {
         display: inline-flex;
         align-items: center;
         justify-content: center;
         width: 38px;
         height: 38px;
         flex: none;
         background: #FFFFFF;
         border: 1px solid var(--admin-line);
         border-radius: 10px;
         color: #55534F;
         cursor: pointer;
         transition: background-color .15s ease, border-color .15s ease, color .15s ease;
      }

      .admin-topbar .admin-nav-toggle:hover {
         background: var(--admin-soft);
         border-color: var(--admin-line-strong);
         color: var(--admin-ink);
      }


      /* เวลาทำงานแบบ mono */
      .admin-topbar .admin-topbar-clock {
         display: none;
         text-align: right;
         line-height: 1.2;
      }

      @media (min-width: 1280px) {
         .admin-topbar .admin-topbar-clock {
            display: block;
         }
      }
      .admin-topbar .admin-user-name {
         color: var(--admin-ink);
         font-size: 13px;
         font-weight: 500;
      }

      .admin-topbar .admin-user-role {
         color: var(--admin-muted);
         font-family: 'Kanit', sans-serif;
         font-size: 11px;
         letter-spacing: .1em;
         text-transform: uppercase;
      }

      .admin-topbar .admin-user-avatar {
         background: var(--admin-soft);
         border-color: var(--admin-line);
         color: #55534F;
         font-family: 'Kanit', sans-serif;
         font-size: 13px;
         font-weight: 600;
         text-transform: uppercase;
         box-shadow: inset 0 0 0 3px #fff;
      }

      .admin-sidebar {
         background: var(--admin-sidebar) !important;
         border-color: var(--admin-line) !important;
         padding: 0;
      }

      /* หัว sidebar: โลโก้ + ปุ่มปิดเมนู (มือถือ) */
      .admin-sidebar .admin-side-brand {
         display: flex;
         align-items: center;
         gap: 10px;
         flex: none;
         padding: 14px 16px;
         border-bottom: 1px solid var(--admin-line);
      }

      .admin-sidebar .admin-side-brand .admin-brand {
         flex: 1;
      }

      .admin-sidebar .admin-side-brand .admin-brand-divider {
         display: block;
      }

      .admin-sidebar .admin-side-brand .admin-brand-label {
         font-size: 11px;
         letter-spacing: .1em;
         white-space: nowrap;
      }

      .admin-sidebar .admin-side-brand .admin-brand-sub {
         font-size: 11px;
         letter-spacing: .08em;
         white-space: nowrap;
      }

      .admin-sidebar .admin-side-close {
         display: inline-flex;
         align-items: center;
         justify-content: center;
         width: 32px;
         height: 32px;
         flex: none;
         background: #FFFFFF;
         border: 1px solid var(--admin-line);
         border-radius: 9px;
         color: #55534F;
         cursor: pointer;
         transition: background-color .15s ease, border-color .15s ease, color .15s ease;
      }

      .admin-sidebar .admin-side-close:hover {
         background: #FFFFFF;
         border-color: var(--admin-line-strong);
         color: var(--admin-ink);
      }

      .admin-sidebar .admin-side-close:focus-visible {
         outline: 2px solid var(--admin-ink);
         outline-offset: 2px;
      }

      .admin-sidebar > nav {
         padding: 12px 16px 0;
      }

      .admin-sidebar .admin-nav-label {
         color: var(--admin-muted);
         font-family: 'Kanit', sans-serif;
         font-size: 11px;
         font-weight: 500;
         letter-spacing: .12em;
         padding: 0 10px;
      }

      .admin-sidebar .side-nav {
         border-left: 0;
         display: flex;
         flex-direction: column;
         gap: 1px;
      }

      /* กลุ่มเมนู: หัวกลุ่มกดพับ/กางได้ · จอเตี้ยพับกลุ่มที่ไม่ใช่หน้าปัจจุบันให้พอดีจอ (สคริปต์ท้ายไฟล์) */
      .admin-sidebar .admin-nav-group + .admin-nav-group {
         margin-top: 6px;
      }

      /* ตัวเลขชิดขวาเสมอ · หน้าปัจจุบันที่มีตัวเลข จุดอยู่ถัดจากตัวเลข */
      .admin-sidebar .side-nav a > .stock-alert__tab {
         margin-left: auto;
      }

      .admin-sidebar .side-nav a:has(> .stock-alert__tab)::after {
         margin-left: 8px;
      }

      .admin-sidebar .admin-nav-head {
         display: flex;
         align-items: center;
         gap: 8px;
         width: 100%;
         min-height: 28px;
         margin: 0 0 2px;
         border: 0;
         border-radius: 8px;
         background: transparent;
         text-align: left;
         cursor: pointer;
         transition: color .15s ease, background-color .15s ease;
      }

      .admin-sidebar .admin-nav-head:hover {
         color: var(--admin-ink);
         background: rgba(22, 22, 22, .035);
      }

      .admin-sidebar .admin-nav-head:focus-visible {
         outline: 2px solid var(--admin-ink);
         outline-offset: 1px;
      }

      .admin-sidebar .admin-nav-head__text {
         flex: 1 1 auto;
         min-width: 0;
      }

      .admin-sidebar .admin-nav-head__chev {
         flex: none;
         opacity: .7;
         transition: transform .18s ease;
      }

      /* ตัวเลขรวมที่หัวกลุ่มแสดงเฉพาะตอนพับ (กางอยู่ เห็นที่เมนูย่อยแล้ว) */
      .admin-sidebar .admin-nav-head__badge {
         display: none;
         letter-spacing: 0;
      }

      .admin-sidebar .admin-nav-group.is-collapsed .admin-nav-head__badge {
         display: inline-grid;
      }

      .admin-sidebar .admin-nav-group.is-collapsed .admin-nav-head__chev {
         transform: rotate(-90deg);
      }

      .admin-sidebar .admin-nav-group.is-collapsed > .side-nav {
         display: none;
      }

      .admin-sidebar .admin-side-foot {
         display: flex;
         align-items: center;
         gap: 6px;
      }

      .admin-sidebar .admin-side-foot .admin-logout {
         flex: 1 1 auto;
         min-width: 0;
         white-space: nowrap;
      }

      .admin-sidebar .admin-foot-icon {
         display: inline-grid;
         place-items: center;
         flex: none;
         width: 36px;
         height: 36px;
         border: 1px solid var(--admin-line);
         border-radius: 10px;
         background: #FFFFFF;
         color: #5F5C57;
         font-size: 13px;
         text-decoration: none;
         transition: color .15s ease, border-color .15s ease;
      }

      .admin-sidebar .admin-foot-icon:hover,
      .admin-sidebar .admin-foot-icon[aria-current="page"] {
         color: var(--admin-ink);
         border-color: var(--admin-line-strong);
      }

      .admin-sidebar .admin-foot-icon[aria-current="page"] {
         box-shadow: inset 0 0 0 1px var(--admin-ink);
      }

      .admin-sidebar .admin-foot-icon:focus-visible {
         outline: 2px solid var(--admin-ink);
         outline-offset: 2px;
      }

      @media (pointer: coarse) {
         .admin-sidebar .admin-foot-icon { width: 44px; height: 44px; }
         .admin-sidebar .admin-nav-head { min-height: 40px; }
      }

      .admin-sidebar .side-nav a {
         display: flex;
         align-items: center;
         margin-left: 0;
         padding: 7px 10px;
         border: 1px solid transparent;
         border-left: 1px solid transparent;
         border-radius: 10px;
         color: #6B6964;
         font-size: 13.5px;
         line-height: 1.45;
         text-decoration: none;
         transition: color .15s ease, background-color .15s ease, border-color .15s ease, box-shadow .15s ease;
      }

      /* ชื่อเมนูบรรทัดเดียวเสมอ (ชื่อยาวที่ตั้งเองตัดด้วย … และดูชื่อเต็มได้จาก title) */
      .admin-sidebar .side-nav a > span:not(.stock-alert__tab) {
         min-width: 0;
         overflow: hidden;
         text-overflow: ellipsis;
         white-space: nowrap;
      }

      .admin-sidebar .side-nav a > i {
         flex: none;
         width: 16px;
         font-size: 12px;
         opacity: .75;
         transition: opacity .15s ease;
      }

      .admin-sidebar .side-nav a:hover {
         color: var(--admin-ink);
         background: #FFFFFF;
         border-color: var(--admin-line);
      }

      .admin-sidebar .side-nav a:hover > i {
         opacity: 1;
      }

      .admin-sidebar .side-nav a[aria-current="page"] {
         color: var(--admin-ink);
         background: #FFFFFF;
         border-color: var(--admin-line);
         font-weight: 500;
         box-shadow: 0 1px 2px rgba(22, 22, 22, .04);
      }

      .admin-sidebar .side-nav a[aria-current="page"] > i {
         opacity: 1;
      }

      .admin-sidebar .side-nav a[aria-current="page"]::after {
         content: '';
         width: 5px;
         height: 5px;
         margin-left: auto;
         border-radius: 50%;
         background: var(--admin-ink);
         flex: none;
      }

      /* ปุ่มออกจากระบบติดขอบล่างของแถบข้างเสมอ (เมนูด้านบนเลื่อนได้เมื่อจอเตี้ย) */
      .admin-sidebar .admin-side-foot {
         position: sticky;
         bottom: 0;
         z-index: 1;
         flex: none;
         margin: 12px 0 0;
         padding: 10px 16px 14px;
         border-top: 1px solid var(--admin-line);
         background: var(--admin-sidebar);
      }

      .admin-sidebar .admin-logout {
         color: #5F5C57;
         border: 1px solid transparent;
         border-radius: 10px;
         transition: color .15s ease, background-color .15s ease, border-color .15s ease;
      }

      .admin-sidebar .admin-logout:hover {
         background: #FFFFFF;
         border-color: var(--admin-line);
         color: #B42318;
      }

      .admin-content {
         background: var(--admin-bg);
      }

      .admin-content > .p-6 {
         padding: 28px;
      }

      .admin-content h1.text-3xl,
      .admin-content h1.text-2xl {
         color: var(--admin-ink);
         font-weight: 400;
         letter-spacing: -.02em;
      }

      .admin-content .rounded-xl.bg-white {
         border-radius: 16px;
         box-shadow: 0 1px 2px rgba(22, 22, 22, .03);
      }

      .admin-content section.rounded-xl.border.bg-white:not([class*="border-red-"]):not([class*="border-amber-"]):not([class*="border-emerald-"]),
      .admin-content form.rounded-xl.border.bg-white,
      .admin-content > .p-6 > div.rounded-xl.border.bg-white:not([class*="border-red-"]):not([class*="border-amber-"]):not([class*="border-emerald-"]) {
         border-color: var(--admin-line);
         border-radius: 16px;
         box-shadow: 0 1px 2px rgba(22, 22, 22, .03);
      }

      .admin-content section.rounded-xl.border.bg-white > form.border-b,
      .admin-content section.rounded-xl.border.bg-white > div.border-b,
      .admin-content form.rounded-xl.border.bg-white > div.border-b {
         border-color: var(--admin-line);
      }

      .admin-content input:not([type="checkbox"]):not([type="radio"]),
      .admin-content select,
      .admin-content textarea {
         border-color: var(--admin-line-strong);
         border-radius: 10px;
         background-color: #FFFFFF;
         color: var(--admin-ink);
      }

      .admin-content select {
         min-height: 42px;
         padding: 0 34px 0 12px;
         appearance: none;
         background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' fill='none' stroke='%238E8B85' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
         background-repeat: no-repeat;
         background-position: right 12px center;
         font-size: 15px;
         line-height: 1.5;
         transition: border-color .15s ease, box-shadow .15s ease;
      }

      .admin-content input[type="date"] {
         min-height: 42px;
         padding: 0 12px;
         border-radius: 10px;
         background-color: #FFFFFF;
         color: var(--admin-ink);
         color-scheme: light;
         font-size: 15px;
         line-height: 1.5;
         transition: border-color .15s ease, box-shadow .15s ease;
      }

      .admin-content input[type="date"]::-webkit-calendar-picker-indicator {
         cursor: pointer;
         opacity: .7;
      }

      .admin-content select:disabled {
         background-color: var(--admin-soft);
         color: var(--admin-muted);
         cursor: not-allowed;
      }

      .admin-content select option {
         padding: 8px 12px;
         border-radius: 8px;
         background-color: #FFFFFF;
         color: var(--admin-ink);
         font: inherit;
      }

      .admin-content select option:disabled {
         color: var(--admin-muted);
      }

      .admin-select-wrap {
         position: relative;
         display: inline-flex;
         max-width: 100%;
         vertical-align: middle;
      }

      .admin-select-wrap--full {
         display: flex;
         width: 100%;
      }

      .admin-select-native {
         position: absolute !important;
         inset: 0;
         width: 100% !important;
         height: 100% !important;
         min-height: 0 !important;
         padding: 0 !important;
         border: 0 !important;
         opacity: 0;
         pointer-events: none;
      }

      .admin-select-trigger {
         position: relative;
         display: flex;
         align-items: center;
         width: 100%;
         min-width: 140px;
         min-height: 42px;
         padding: 0 36px 0 12px;
         border: 1px solid var(--admin-line-strong);
         border-radius: 10px;
         background: #FFFFFF;
         color: var(--admin-ink);
         font: 400 15px/1.5 'Kanit', sans-serif;
         text-align: left;
         cursor: pointer;
         transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
      }

      .admin-select-trigger::after {
         position: absolute;
         top: 15px;
         right: 14px;
         width: 8px;
         height: 8px;
         border-right: 1.5px solid var(--admin-muted);
         border-bottom: 1.5px solid var(--admin-muted);
         content: '';
         transform: rotate(45deg);
         transition: transform .15s ease, top .15s ease;
      }

      .admin-select-wrap.is-open .admin-select-trigger::after {
         top: 18px;
         transform: rotate(225deg);
      }

      .admin-select-trigger:hover {
         border-color: #B9B5AE;
      }

      .admin-select-trigger:focus-visible,
      .admin-select-wrap.is-open .admin-select-trigger {
         border-color: var(--admin-ink);
         outline: none;
         box-shadow: 0 0 0 3px rgba(22, 22, 22, .07);
      }

      .admin-select-trigger:disabled {
         background: var(--admin-soft);
         color: var(--admin-muted);
         cursor: not-allowed;
      }

      .admin-select-value {
         overflow: hidden;
         text-overflow: ellipsis;
         white-space: nowrap;
      }

      .admin-select-menu {
         position: fixed;
         top: 0;
         left: 0;
         z-index: 1400;
         width: 200px;
         max-width: min(320px, calc(100vw - 32px));
         max-height: 260px;
         overflow-y: auto;
         padding: 5px;
         border: 1px solid var(--admin-line);
         border-radius: 12px;
         background: #FFFFFF;
         box-shadow: 0 12px 30px rgba(22, 22, 22, .14);
      }

      .admin-select-menu[hidden] {
         display: none;
      }

      .admin-select-option {
         display: flex;
         width: 100%;
         align-items: center;
         padding: 9px 10px;
         border: 0;
         border-radius: 8px;
         background: transparent;
         color: var(--admin-ink);
         font: 400 15px/1.4 'Kanit', sans-serif;
         text-align: left;
         cursor: pointer;
         transition: background-color .12s ease, color .12s ease;
      }

      .admin-select-option:hover,
      .admin-select-option.is-active {
         background: var(--admin-soft);
      }

      .admin-select-option.is-selected {
         color: var(--admin-ink);
         font-weight: 500;
      }

      .admin-select-option:disabled {
         color: #B9B5AE;
         cursor: not-allowed;
      }

      .admin-select-option:disabled:hover {
         background: transparent;
      }

      .admin-content input:not([type="checkbox"]):not([type="radio"]):focus,
      .admin-content select:focus,
      .admin-content textarea:focus {
         border-color: #7C7973;
         box-shadow: 0 0 0 3px rgba(22, 22, 22, .07);
      }

      .admin-content table thead.bg-gray-50,
      .admin-content table thead {
         background: var(--admin-soft);
      }

      .admin-content table thead th {
         color: var(--admin-muted);
         font-family: 'Kanit', sans-serif;
         font-size: 11px;
         font-weight: 500;
         letter-spacing: .07em;
         text-transform: uppercase;
      }

      .admin-content table tbody tr {
         border-color: var(--admin-line);
      }

      .admin-content table tbody tr:hover {
         background-color: #FBFAF8;
      }

      .admin-content button.bg-gray-900,
      .admin-content a.bg-gray-900,
      .admin-content button.bg-neutral-900,
      .admin-content a.bg-neutral-900 {
         border-radius: 9px;
         background: var(--admin-ink);
         box-shadow: 0 1px 1px rgba(22, 22, 22, .08);
      }

      .admin-content button.bg-gray-900:hover,
      .admin-content a.bg-gray-900:hover,
      .admin-content button.bg-neutral-900:hover,
      .admin-content a.bg-neutral-900:hover {
         background: #2A2927;
      }

      .admin-content dialog {
         border-color: var(--admin-line);
         border-radius: 18px;
         box-shadow: 0 28px 70px rgba(22, 22, 22, .18);
      }

      /* ===== Page header ===== */
      .admin-page-head {
         margin-bottom: 26px;
      }

      .admin-page-title {
         color: var(--admin-ink);
         font-size: 30px;
         font-weight: 400;
         line-height: 1.2;
         letter-spacing: -.02em;
      }

      .admin-page-sub {
         margin-top: 6px;
         color: var(--admin-muted);
         font-size: 13px;
         line-height: 1.6;
      }

      .admin-crumb {
         display: flex;
         align-items: center;
         gap: 8px;
         margin-bottom: 10px;
         color: var(--admin-muted);
         font-family: 'Kanit', sans-serif;
         font-size: 13px;
         font-weight: 400;
      }

      .admin-crumb a {
         color: var(--admin-muted);
         text-decoration: none;
         transition: color .15s ease;
      }

      .admin-crumb a:hover {
         color: var(--admin-ink);
      }

      .admin-crumb i {
         font-size: 11px;
         opacity: .55;
      }

      .admin-crumb span {
         color: var(--admin-ink);
      }

      /* ===== Tray + inner card ===== */
      .admin-tray {
         display: flex;
         flex-direction: column;
         margin-bottom: 24px;
         padding: 6px;
         background: var(--admin-soft);
         border: 1px solid var(--admin-tray-line);
         border-radius: 20px;
      }

      .admin-tray-label {
         display: flex;
         align-items: center;
         justify-content: space-between;
         gap: 10px;
         padding: 8px 12px 10px;
         color: var(--admin-muted);
         font-family: 'Kanit', sans-serif;
         font-size: 11px;
         font-weight: 500;
         line-height: 1.4;
         letter-spacing: .08em;
         text-transform: uppercase;
      }

      .admin-tray > .admin-card {
         flex: 1;
         margin: 0 !important;
         background: #FFFFFF;
         border: 1px solid var(--admin-line) !important;
         border-radius: 14px !important;
         box-shadow: none !important;
      }

      /* ===== Stat card ===== */
      .admin-stat {
         display: block;
         min-width: 0;
         padding: 15px 17px;
         background: #FFFFFF;
         border: 4px solid var(--admin-soft);
         border-radius: 18px;
         box-shadow: 0 0 0 1px var(--admin-tray-line), 0 1px 2px rgba(22, 22, 22, .03);
         color: var(--admin-ink);
         text-decoration: none;
         transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
      }

      a.admin-stat:hover {
         border-color: #EDEBE7;
         box-shadow: 0 0 0 1px var(--admin-line-strong), 0 8px 20px rgba(22, 22, 22, .06);
         transform: translateY(-1px);
      }

      a.admin-stat.is-active {
         border-color: #EDEBE7;
         box-shadow: 0 0 0 2px var(--admin-ink), 0 0 0 6px var(--admin-soft);
      }

      .admin-stat-label {
         display: flex;
         align-items: center;
         gap: 7px;
         color: var(--admin-muted);
         font-family: 'Kanit', sans-serif;
         font-size: 12px;
         font-weight: 400;
         letter-spacing: .06em;
         text-transform: uppercase;
      }

      .admin-stat-label::before {
         content: '';
         width: 6px;
         height: 6px;
         border-radius: 50%;
         background: var(--c, #C9C6C0);
         flex: none;
      }

      .admin-stat-value {
         margin-top: 6px;
         color: var(--admin-ink);
         font-family: 'Kanit', sans-serif;
         font-size: 26px;
         font-weight: 600;
         line-height: 1.1;
         letter-spacing: -.02em;
      }

      .admin-stat-icon {
         margin-left: auto;
         color: #C9C6C0;
         font-size: 12px;
      }

      .admin-stat-note {
         margin-top: 4px;
         color: var(--admin-muted);
         font-size: 11px;
         line-height: 1.5;
      }

      /* ===== Toolbar / filters ===== */
      .admin-content .admin-toolbar {
         display: flex;
         flex-wrap: wrap;
         align-items: center;
         gap: 10px;
         padding: 16px 18px;
      }

      .admin-content .admin-toolbar > label,
      .admin-content .admin-toolbar .admin-field-label {
         color: var(--admin-muted);
         font-family: 'Kanit', sans-serif;
         font-size: 11px;
         font-weight: 500;
         letter-spacing: .06em;
         text-transform: uppercase;
      }

      .admin-content .admin-toolbar input:not([type="checkbox"]):not([type="radio"]),
      .admin-content .admin-toolbar select,
      .admin-content .admin-toolbar .admin-select-wrap {
         margin-top: 4px;
      }

      .admin-content .admin-toolbar .admin-toolbar-actions {
         display: flex;
         flex-wrap: wrap;
         align-items: center;
         justify-content: flex-end;
         gap: 8px;
         margin-left: auto;
      }

      .admin-content .admin-toolbar .admin-reset-link {
         display: inline-flex;
         align-items: center;
         min-height: 38px;
      }

      /* ===== Table card spacing (same rhythm as dashboard .recent-card) ===== */
      .admin-content .admin-table-wrap {
         padding: 20px 22px 8px;
      }

      /* ===== Chips ===== */
      .admin-chip {
         display: inline-flex;
         align-items: center;
         gap: 6px;
         padding: 3px 9px;
         border: 1px solid var(--admin-line);
         border-radius: 999px;
         background: var(--admin-soft);
         color: #55534F;
         font-family: 'Kanit', sans-serif;
         font-size: 11.5px;
         font-weight: 500;
         line-height: 1.5;
         white-space: nowrap;
      }

      .admin-chip::before {
         content: '';
         width: 5px;
         height: 5px;
         border-radius: 50%;
         background: var(--c, #B9B5AE);
         flex: none;
      }

      .admin-chip i {
         font-size: 11px;
      }

      .admin-chip.is-urgent {
         background: var(--admin-ink);
         border-color: var(--admin-ink);
         color: #FFFFFF;
      }

      .admin-chip.is-urgent::before {
         background: #FFFFFF;
      }

      .admin-chip.is-low {
         background: transparent;
         color: var(--admin-muted);
         --c: #C9C6C0;
      }

      .admin-chip.is-warning {
         background: #FFFBEB;
         border-color: #FDE68A;
         color: #92400E;
         --c: #D97706;
      }

      .admin-chip.is-ok {
         background: #F3FAF5;
         border-color: #CDE9D6;
         color: #12703A;
         --c: #12A150;
      }

      .admin-chip.is-fail {
         background: #FEF3F2;
         border-color: #F2D4D0;
         color: #B42318;
         --c: #B42318;
      }

      .admin-chip.role-admin {
         background: var(--admin-ink);
         border-color: var(--admin-ink);
         color: #FFFFFF;
      }

      .admin-chip.role-admin::before {
         background: #FFFFFF;
      }

      .admin-chip.no-dot::before {
         display: none;
      }

      .st-pending { --c: #D97706; }
      .st-in_progress,
      .st-accepted,
      .st-awaiting_intake_sign,
      .st-inspected_ready,
      .st-working { --c: #2563EB; }
      .st-awaiting_signoff,
      .st-awaiting_requester_sign,
      .st-awaiting_admin_sign,
      .st-signing { --c: #0E7490; }
      .st-disputed { --c: #B45309; }
      .st-completed,
      .st-active { --c: #12A150; }
      .st-cancelled,
      .st-disabled { --c: #6B6862; }

      /* ===== Buttons ===== */
      .admin-content .admin-btn {
         display: inline-flex;
         align-items: center;
         justify-content: center;
         gap: 7px;
         min-height: 38px;
         padding: 0 14px;
         border: 1px solid var(--admin-ink);
         border-radius: 10px;
         background: var(--admin-ink);
         color: #FFFFFF;
         font-size: 13px;
         font-weight: 500;
         line-height: 1.4;
         text-decoration: none;
         white-space: nowrap;
         box-shadow: 0 1px 1px rgba(22, 22, 22, .08);
         cursor: pointer;
         transition: background-color .15s ease, border-color .15s ease, color .15s ease, box-shadow .15s ease;
      }

      .admin-content .admin-btn:hover {
         background: #2A2927;
         border-color: #2A2927;
         color: #FFFFFF;
         box-shadow: 0 4px 12px rgba(22, 22, 22, .12);
      }

      .admin-content .admin-btn-secondary {
         background: #FFFFFF;
         border-color: var(--admin-line-strong);
         color: #55534F;
         box-shadow: none;
      }

      .admin-content .admin-btn-secondary:hover {
         background: var(--admin-soft);
         border-color: #C9C6C0;
         color: var(--admin-ink);
         box-shadow: none;
      }

      .admin-content .admin-btn-danger {
         background: #B42318;
         border-color: #B42318;
         color: #FFFFFF;
      }

      .admin-content .admin-btn-danger:hover {
         background: #8F1B13;
         border-color: #8F1B13;
      }

      .admin-content .admin-btn-danger-outline {
         background: #FFFFFF;
         border-color: #F2D4D0;
         color: #B42318;
         box-shadow: none;
      }

      /* เนื้อหาหลัก + คอลัมน์ฟอร์มด้านขวา (หน้าคะแนนสตาฟ ภาคเรียน) — ไม่พึ่ง class แบบ arbitrary ของ Tailwind */
      .admin-split {
         display: grid;
         gap: 24px;
         align-items: start;
      }

      .admin-split > * {
         min-width: 0;
      }

      @media (min-width: 1180px) {
         .admin-split {
            grid-template-columns: minmax(0, 1fr) 360px;
         }
      }

      .admin-content .admin-btn-danger-outline:hover {
         background: #FEF3F2;
         border-color: #E7B4AD;
         color: #8F1B13;
      }

      .admin-reset-link {
         color: var(--admin-muted);
         font-size: 12px;
         text-decoration: none;
      }

      .admin-reset-link:hover {
         color: var(--admin-ink);
         text-decoration: underline;
         text-underline-offset: 2px;
      }

      /* ===== Shared accents ===== */
      .admin-icon {
         display: flex;
         align-items: center;
         justify-content: center;
         width: 36px;
         height: 36px;
         border: 1px solid var(--admin-line);
         border-radius: 10px;
         background: var(--admin-soft);
         color: #55534F;
         flex: none;
      }

      .admin-icon.is-ink {
         background: var(--admin-ink);
         border-color: var(--admin-ink);
         color: #FFFFFF;
      }

      .admin-profile-avatar {
         display: flex;
         align-items: center;
         justify-content: center;
         width: 64px;
         height: 64px;
         border: 1px solid var(--admin-line);
         border-radius: 50%;
         background: var(--admin-soft);
         color: #55534F;
         font-family: 'Kanit', sans-serif;
         font-size: 24px;
         font-weight: 600;
         box-shadow: inset 0 0 0 3px #FFFFFF;
      }

      .admin-code-box {
         border: 1px dashed var(--admin-line-strong);
         border-radius: 12px;
         background: var(--admin-soft);
      }

      .admin-form-label {
         display: flex;
         align-items: center;
         gap: 8px;
         color: var(--admin-muted);
         font-family: 'Kanit', sans-serif;
         font-size: 11px;
         font-weight: 500;
         letter-spacing: .06em;
         text-transform: uppercase;
      }

      .admin-danger-card {
         border: 1px solid #F2D4D0 !important;
         background: #FFFCFB !important;
      }

      /* ===== Table refinement ===== */
      .admin-content table {
         border-collapse: separate;
         border-spacing: 0;
      }

      .admin-content table thead th {
         padding-top: 10px;
         padding-bottom: 10px;
         background: var(--admin-soft);
      }

      .admin-content table thead th:first-child {
         padding-left: 14px;
         border-top-left-radius: 999px;
         border-bottom-left-radius: 999px;
      }

      .admin-content table thead th:last-child {
         padding-right: 14px;
         border-top-right-radius: 999px;
         border-bottom-right-radius: 999px;
      }

      .admin-content table tbody td {
         border-bottom: 1px solid #F1EFEC;
      }

      .admin-content table tbody tr:last-child td {
         border-bottom: 0;
      }

      @media (max-width: 767px) {
         .admin-page-title {
            font-size: 26px;
         }

         .admin-tray {
            border-radius: 16px;
            padding: 4px;
         }

         .admin-tray > .admin-card {
            border-radius: 12px !important;
         }

         .admin-stat {
            padding: 13px 14px;
            border-width: 4px;
            border-radius: 16px;
         }

         .admin-stat-value {
            font-size: 23px;
         }

         .admin-content .admin-toolbar {
            padding: 14px;
         }

         .admin-content .admin-table-wrap {
            padding: 16px 14px 6px;
         }

         .admin-content .admin-toolbar > label,
         .admin-content .admin-toolbar > div,
         .admin-content .admin-toolbar > form,
         .admin-content .admin-toolbar .admin-toolbar-actions {
            width: 100%;
         }

         .admin-content .admin-toolbar input:not([type="checkbox"]):not([type="radio"]),
         .admin-content .admin-toolbar select,
         .admin-content .admin-toolbar .admin-select-wrap {
            width: 100%;
         }

         .admin-content table thead th:first-child {
            padding-left: 12px;
         }

         .admin-content table thead th:last-child {
            padding-right: 12px;
         }
      }

      @media (max-width: 767px) {
         .admin-content > .p-6 {
            padding: 20px 16px;
         }

         .admin-sidebar .admin-side-brand {
            padding: 12px;
         }

         .admin-sidebar > nav {
            padding: 14px 12px 0;
         }

         .admin-sidebar .admin-side-foot {
            margin-top: 10px;
            padding: 8px 12px 12px;
         }
      }

      /* ===== ยุบ/กาง sidebar บนเดสก์ท็อป (กดปุ่ม toggle ที่ topbar) ===== */
      .admin-content {
         background: var(--admin-bg);
      }

      @media (min-width: 1024px) {
         body.admin-nav-collapsed #sidebar {
            transform: translateX(-100%);
         }

         body.admin-nav-collapsed .admin-content {
            margin-left: 0;
         }
      }
   </style>
   <link rel="stylesheet" href="../assets/css/admin-system.css?v=1">
   <link rel="stylesheet" href="../assets/css/base.css?v=18">
   <?= app_icon_js() ?>
   <?= $admin_extra_head ?? '' ?>
   <link rel="stylesheet" href="../assets/css/staff-workspace.css?v=4">
</head>

<body class="admin-shell min-h-screen text-muted antialiased">

   <!-- Mobile Overlay -->
   <div id="sidebarOverlay" class="hidden fixed inset-0 bg-black/40 z-[1050]"></div>

   <!-- Top Header -->
   <header class="admin-topbar fixed top-0 left-0 right-0 h-header bg-white border-b border-line flex items-center justify-between px-4 md:px-6 z-[1200]">
      <div class="flex items-center gap-3 min-w-0">
         <button type="button" id="sidebarToggle" aria-label="เปิด/ปิดเมนู" aria-controls="sidebar" aria-expanded="true"
            class="admin-nav-toggle">
            <i class="fa-solid fa-bars text-sm" id="sidebarToggleIcon"></i>
         </button>

         <div class="admin-topbar-titles min-w-0">
            <span class="admin-topbar-section"><?= htmlspecialchars($headerSection, ENT_QUOTES, 'UTF-8') ?></span>
         </div>
      </div>

      <div class="flex items-center gap-3 ml-auto">
         <?php $headerStockAlert = isset($conn) && $conn instanceof mysqli ? stock_alert_load($conn) : ['count' => 0, 'items' => []]; ?>
         <?= isset($conn) && $conn instanceof mysqli ? notif_bell($conn, '../') : '' ?>
         <?= isset($conn) && $conn instanceof mysqli ? user_menu_render($conn, '../', true, false) : '' ?>
      </div>
   </header>

   <!-- Sidebar -->
   <aside id="sidebar"
      class="admin-sidebar fixed top-header left-0 h-[calc(100vh-64px)] w-sidebar border-r border-line bg-white flex flex-col overflow-y-auto z-[1100]
               -translate-x-full lg:translate-x-0 transition-transform duration-200 ease-in-out">
      <div class="admin-side-brand">
         <a href="<?= $adminLinkBase ?>admin_dashboard.php" class="admin-brand flex items-center gap-2.5 group focus:outline-none min-w-0">
            <img src="../assets/images/logo/reps-logo.svg" alt="REPS" class="h-6 w-auto flex-none">
            <!-- <span class="admin-brand-divider" aria-hidden="true"></span>
            <span class="admin-brand-copy">
               <span class="admin-brand-label">Admin Panel</span>
               <span class="admin-brand-sub">REPS Console</span>
            </span> -->
         </a>

         <button type="button" id="sidebarClose" aria-label="ปิดเมนู"
            class="admin-side-close lg:hidden">
            <i class="fa-solid fa-xmark text-sm"></i>
         </button>
      </div>

      <nav class="flex-1" aria-label="<?= tr('เมนูผู้ดูแลระบบ', 'Admin menu') ?>">
         <?php
         // เมนูจาก includes/site_menu.php (admin ปรับชื่อ ลำดับ การแสดงได้ที่หน้า ปรับแต่งเว็บ)
         $headerMenu = isset($conn) && $conn instanceof mysqli ? site_menu_items($conn, 'admin', 'admin') : [];
         // ตัวเลขที่ต้องดำเนินการของแต่ละเมนู (กลุ่มที่พับอยู่แสดงผลรวมที่หัวกลุ่ม)
         $headerBadges = [
            'signoff'   => [(int)$headerAwaitingCount, tr('งานรอรับรอง', 'awaiting sign-off')],
            'users'     => [(int)$headerPendingUsers, tr('ผู้สมัครรออนุมัติ', 'sign-ups pending')],
            'inventory' => [(int)($headerStockAlert['count'] ?? 0), tr('รายการใกล้หมด', 'items low')],
         ];
         foreach (SITE_MENU_GROUPS as $groupKey => [$groupTh, $groupEn]):
            $groupItems = array_values(array_filter($headerMenu, static fn(array $m): bool => $m['group'] === $groupKey));
            if (!$groupItems) continue;
            $groupBadge = array_sum(array_map(static fn(array $m): int => $headerBadges[(string)$m['key']][0] ?? 0, $groupItems));
            $groupActive = (bool)array_filter($groupItems, static fn(array $m): bool => (string)$m['key'] !== '' && (string)$m['key'] === $current_page); ?>
         <div class="admin-nav-group<?= $groupActive ? ' is-active' : '' ?>" data-nav-group="<?= htmlspecialchars($groupKey, ENT_QUOTES, 'UTF-8') ?>">
            <button type="button" class="admin-nav-label admin-nav-head" aria-expanded="true" aria-controls="navg-<?= htmlspecialchars($groupKey, ENT_QUOTES, 'UTF-8') ?>">
               <span class="admin-nav-head__text"><?= tr($groupTh, $groupEn) ?></span>
               <?php if ($groupBadge > 0): ?><span class="stock-alert__tab admin-nav-head__badge" aria-hidden="true"><?= $groupBadge ?></span><?php endif; ?>
               <svg class="admin-nav-head__chev" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div class="side-nav flex flex-col" id="navg-<?= htmlspecialchars($groupKey, ENT_QUOTES, 'UTF-8') ?>">
               <?php foreach ($groupItems as $menuItem):
                  $menuKey = (string)$menuItem['key'];
                  $menuHref = site_link_href($menuItem['target'], '../');
                  [$menuCount, $menuCountLabel] = $headerBadges[$menuKey] ?? [0, '']; ?>
               <a href="<?= htmlspecialchars($menuHref, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($menuItem['text'], ENT_QUOTES, 'UTF-8') ?>"<?= $menuKey !== '' && $menuKey === $current_page ? ' aria-current="page"' : '' ?><?= $menuItem['new_tab'] ? ' target="_blank" rel="noopener"' : '' ?>>
                  <i class="fa-solid <?= htmlspecialchars($menuItem['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                  <span><?= htmlspecialchars($menuItem['text'], ENT_QUOTES, 'UTF-8') ?></span>
                  <?php if ($menuCount > 0): ?><span class="stock-alert__tab ml-auto"><?= $menuCount ?><span class="sr-only"> <?= htmlspecialchars($menuCountLabel, ENT_QUOTES, 'UTF-8') ?></span></span><?php endif; ?>
               </a>
               <?php endforeach; ?>
            </div>
         </div>
         <?php endforeach; ?>
      </nav>

      <div class="admin-side-foot">
         <a href="../logout.php" class="admin-logout flex items-center gap-2.5 px-2.5 py-2 rounded-md text-gray-500 no-underline text-sm">
            <i class="fa-solid fa-right-from-bracket text-xs"></i>
            <span><?= tr('ออกจากระบบ', 'Sign out') ?></span>
         </a>
         <a href="<?= $adminLinkBase ?>admin_settings.php" class="admin-foot-icon" title="<?= tr('ตั้งค่าระบบ', 'Settings') ?>" aria-label="<?= tr('ตั้งค่าระบบ', 'Settings') ?>"<?= ($current_page ?? '') === 'settings' ? ' aria-current="page"' : '' ?>><i class="fa-solid fa-sliders" aria-hidden="true"></i></a>
         <a href="<?= $adminLinkBase ?>admin_site.php" class="admin-foot-icon" title="<?= tr('ปรับแต่งเว็บ', 'Customize site') ?>" aria-label="<?= tr('ปรับแต่งเว็บ', 'Customize site') ?>"<?= ($current_page ?? '') === 'site' ? ' aria-current="page"' : '' ?>><i class="fa-solid fa-palette" aria-hidden="true"></i></a>
      </div>
   </aside>

   <script>
      /* กลุ่มเมนูพับได้: กดหัวกลุ่มเพื่อพับ/กาง (จำกลุ่มที่พับเองไว้) · ถ้าเมนูยาวเกินจอ พับกลุ่มจากล่างขึ้นบน
         ยกเว้นกลุ่มของหน้าปัจจุบันและกลุ่มที่เพิ่งกดเปิด จึงไม่ต้องมีแถบเลื่อน */
      (function () {
         const side = document.getElementById('sidebar');
         const groups = Array.from(document.querySelectorAll('[data-nav-group]'));
         if (!side || !groups.length) return;
         const STORE = 'reps-admin-nav-closed';
         let closed = [];
         try { closed = JSON.parse(localStorage.getItem(STORE) || '[]'); } catch (e) {}
         if (!Array.isArray(closed)) closed = [];
         const keyOf = (g) => g.getAttribute('data-nav-group');
         const save = () => { try { localStorage.setItem(STORE, JSON.stringify(closed)); } catch (e) {} };
         const setCollapsed = (g, c) => {
            g.classList.toggle('is-collapsed', c);
            g.querySelector('.admin-nav-head').setAttribute('aria-expanded', c ? 'false' : 'true');
         };
         const overflowing = () => side.scrollHeight > side.clientHeight + 1;
         function fit(keep) {
            groups.forEach((g) => setCollapsed(g, closed.includes(keyOf(g)) && !g.classList.contains('is-active') && g !== keep));
            const auto = [];
            for (let i = groups.length - 1; i >= 0 && overflowing(); i--) {
               const g = groups[i];
               if (g === keep || g.classList.contains('is-active') || g.classList.contains('is-collapsed')) continue;
               setCollapsed(g, true);
               auto.push(g);
            }
            // รอบสอง: กางกลับกลุ่มที่พับอัตโนมัติ (จากบนลงล่าง) ถ้ายังมีที่พอ
            auto.reverse().forEach((g) => {
               setCollapsed(g, false);
               if (overflowing()) setCollapsed(g, true);
            });
         }
         groups.forEach((g) => {
            g.querySelector('.admin-nav-head').addEventListener('click', () => {
               const key = keyOf(g);
               if (g.classList.contains('is-collapsed')) {
                  closed = closed.filter((k) => k !== key);
                  save();
                  fit(g);
               } else {
                  if (!closed.includes(key)) closed.push(key);
                  save();
                  setCollapsed(g, true);
               }
            });
         });
         fit(null);
         window.addEventListener('load', () => fit(null));
         let t = 0;
         window.addEventListener('resize', () => { clearTimeout(t); t = setTimeout(() => fit(null), 150); });
      })();
   </script>

   <script>
      const sidebar = document.getElementById('sidebar');
      const sidebarToggle = document.getElementById('sidebarToggle');
      const sidebarToggleIcon = document.getElementById('sidebarToggleIcon');
      const sidebarClose = document.getElementById('sidebarClose');
      const sidebarOverlay = document.getElementById('sidebarOverlay');
      const DESKTOP_BP = 1024;
      const NAV_STORE_KEY = 'reps-admin-nav-collapsed';

      const isDesktop = () => window.innerWidth >= DESKTOP_BP;

      /* โหมดมือถือ: เลื่อน sidebar เข้า/ออก พร้อม overlay */
      function setMobileMenu(open) {
         sidebar.classList.toggle('-translate-x-full', !open);
         sidebar.classList.toggle('translate-x-0', open);
         sidebarOverlay.classList.toggle('hidden', !open);
         sidebarToggleIcon.className = (open ? 'fa-solid fa-xmark' : 'fa-solid fa-bars') + ' text-sm';
      }

      /* โหมดเดสก์ท็อป: ยุบ/กาง sidebar และปรับระยะห่างของเนื้อหา (จำค่าไว้ใน localStorage) */
      function setDesktopCollapsed(collapsed) {
         document.body.classList.toggle('admin-nav-collapsed', collapsed);
         try {
            localStorage.setItem(NAV_STORE_KEY, collapsed ? '1' : '0');
         } catch (e) {}
      }

      const isDesktopCollapsed = () => document.body.classList.contains('admin-nav-collapsed');
      const isMobileMenuOpen = () => sidebar.classList.contains('translate-x-0');

      function updateToggleState() {
         const expanded = isDesktop() ? !isDesktopCollapsed() : isMobileMenuOpen();
         sidebarToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      }

      function syncNavState() {
         let collapsed = false;
         try {
            collapsed = localStorage.getItem(NAV_STORE_KEY) === '1';
         } catch (e) {}
         setDesktopCollapsed(isDesktop() && collapsed);
         setMobileMenu(false);
         updateToggleState();
      }

      function toggleSidebar() {
         if (isDesktop()) {
            setDesktopCollapsed(!isDesktopCollapsed());
         } else {
            setMobileMenu(!isMobileMenuOpen());
         }
         updateToggleState();
      }

      function closeMobileMenu() {
         if (!isDesktop() && isMobileMenuOpen()) {
            setMobileMenu(false);
            updateToggleState();
         }
      }

      sidebarToggle.addEventListener('click', toggleSidebar);
      if (sidebarClose) sidebarClose.addEventListener('click', closeMobileMenu);
      sidebarOverlay.addEventListener('click', closeMobileMenu);
      document.addEventListener('keydown', (e) => {
         if (e.key === 'Escape') closeMobileMenu();
      });
      document.addEventListener('click', (e) => {
         if (e.target.closest('#sidebar a')) closeMobileMenu();
      });

      let wasDesktop = isDesktop();
      window.addEventListener('resize', () => {
         if (isDesktop() !== wasDesktop) {
            wasDesktop = isDesktop();
            syncNavState();
         }
      });

      syncNavState();

      const adminClock = document.getElementById('adminClock');
      if (adminClock) {
         const updateClock = () => {
            adminClock.textContent = new Date().toLocaleTimeString('en-GB', {
               hour: '2-digit',
               minute: '2-digit',
               hour12: false
            });
         };
         updateClock();
         setInterval(updateClock, 30000);
      }

      function initializeAdminSelects() {
         document.querySelectorAll('.admin-content select:not([multiple])').forEach((select, selectIndex) => {
            if (select.dataset.customized === 'true') return;

            const wrapper = document.createElement('div');
            wrapper.className = 'admin-select-wrap';
            if (select.classList.contains('w-full')) wrapper.classList.add('admin-select-wrap--full');

            select.parentNode.insertBefore(wrapper, select);
            wrapper.appendChild(select);
            select.dataset.customized = 'true';
            select.classList.add('admin-select-native');
            select.tabIndex = -1;
            select.setAttribute('aria-hidden', 'true');

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'admin-select-trigger';
            button.setAttribute('role', 'combobox');
            button.setAttribute('aria-haspopup', 'listbox');
            button.setAttribute('aria-expanded', 'false');
            button.setAttribute('aria-controls', `admin-select-options-${selectIndex}`);

            const value = document.createElement('span');
            value.className = 'admin-select-value';
            button.appendChild(value);

            const menu = document.createElement('div');
            menu.className = 'admin-select-menu';
            menu.id = `admin-select-options-${selectIndex}`;
            menu.setAttribute('role', 'listbox');
            menu.hidden = true;
            wrapper.append(button, menu);

            const labels = Array.from(select.labels || []);
            const labelText = labels.map((label) => label.textContent.trim()).filter(Boolean).join(' ');
            if (labelText) {
               button.setAttribute('aria-label', labelText);
               menu.setAttribute('aria-label', labelText);
            } else if (select.getAttribute('aria-label')) {
               button.setAttribute('aria-label', select.getAttribute('aria-label'));
               menu.setAttribute('aria-label', select.getAttribute('aria-label'));
            }

            const optionButtons = new Map();
            let activeIndex = -1;

            function optionIsDisabled(option) {
               return option.disabled || (option.parentElement?.tagName === 'OPTGROUP' && option.parentElement.disabled);
            }

            function updateValue() {
               const selected = select.options[select.selectedIndex];
               value.textContent = selected ? selected.textContent.trim() : '';
               button.setAttribute('aria-valuetext', value.textContent);
               button.disabled = select.disabled;
               button.setAttribute('aria-disabled', select.disabled ? 'true' : 'false');
               button.setAttribute('aria-invalid', select.validity.valid ? 'false' : 'true');
            }

            function setActive(index) {
               activeIndex = index;
               button.removeAttribute('aria-activedescendant');
               optionButtons.forEach((optionButton, optionIndex) => {
                  const active = optionIndex === activeIndex;
                  optionButton.classList.toggle('is-active', active);
                  if (active) button.setAttribute('aria-activedescendant', optionButton.id);
               });
               if (activeIndex < 0) button.removeAttribute('aria-activedescendant');
            }

            function renderOptions() {
               menu.replaceChildren();
               optionButtons.clear();
               Array.from(select.options).forEach((option, optionIndex) => {
                  if (option.hidden) return;
                  const optionButton = document.createElement('button');
                  optionButton.type = 'button';
                  optionButton.id = `${menu.id}-${optionIndex}`;
                  optionButton.className = 'admin-select-option';
                  optionButton.setAttribute('role', 'option');
                  optionButton.textContent = option.textContent.trim();
                  optionButton.disabled = optionIsDisabled(option);
                  optionButton.setAttribute('aria-selected', option.selected ? 'true' : 'false');
                  optionButton.classList.toggle('is-selected', option.selected);
                  optionButton.addEventListener('pointerenter', () => setActive(optionIndex));
                  optionButton.addEventListener('click', () => {
                     if (optionButton.disabled) return;
                     select.selectedIndex = optionIndex;
                     select.dispatchEvent(new Event('input', { bubbles: true }));
                     select.dispatchEvent(new Event('change', { bubbles: true }));
                     updateValue();
                     closeMenu();
                     button.focus();
                  });
                  optionButtons.set(optionIndex, optionButton);
                  menu.appendChild(optionButton);
               });
               setActive(activeIndex);
            }

            function positionMenu() {
               const rect = button.getBoundingClientRect();
               const viewportWidth = Math.max(200, window.innerWidth - 32);
               const menuWidth = Math.min(Math.max(rect.width, 200), viewportWidth);
               const below = window.innerHeight - rect.bottom - 12;
               const above = rect.top - 12;
               const shouldOpenUp = below < Math.min(menu.scrollHeight, 260) && above > below;
               const availableHeight = shouldOpenUp ? above : below;
               const maxHeight = Math.max(80, Math.min(260, availableHeight - 8));

               menu.style.width = `${menuWidth}px`;
               menu.style.left = `${Math.max(16, Math.min(rect.left, window.innerWidth - menuWidth - 16))}px`;
               menu.style.maxHeight = `${maxHeight}px`;
               menu.style.top = shouldOpenUp ? 'auto' : `${rect.bottom + 6}px`;
               menu.style.bottom = shouldOpenUp ? `${window.innerHeight - rect.top + 6}px` : 'auto';
            }

            function closeMenu() {
               wrapper.classList.remove('is-open');
               menu.hidden = true;
               button.setAttribute('aria-expanded', 'false');
               button.removeAttribute('aria-activedescendant');
               activeIndex = -1;
            }

            function openMenu() {
               if (select.disabled) return;
               updateValue();
               renderOptions();
               menu.style.maxHeight = '';
               menu.hidden = false;
               wrapper.classList.add('is-open');
               button.setAttribute('aria-expanded', 'true');
               positionMenu();
               const enabledOptions = Array.from(select.options)
                  .map((option, index) => optionIsDisabled(option) || option.hidden ? -1 : index)
                  .filter((index) => index >= 0);
               const selectedIndex = enabledOptions.includes(select.selectedIndex) ? select.selectedIndex : enabledOptions[0];
               setActive(selectedIndex ?? -1);
               optionButtons.get(activeIndex)?.scrollIntoView({ block: 'nearest' });
            }

            button.addEventListener('click', () => {
               if (menu.hidden) openMenu();
               else closeMenu();
            });

            button.addEventListener('keydown', (event) => {
               const enabledOptions = Array.from(select.options)
                  .map((option, index) => optionIsDisabled(option) || option.hidden ? -1 : index)
                  .filter((index) => index >= 0);
               if (!enabledOptions.length) return;

               if (['ArrowDown', 'ArrowUp', 'Home', 'End', 'Enter', ' ', 'Escape'].includes(event.key)) {
                  event.preventDefault();
               }
               if (event.key === 'Tab') {
                  closeMenu();
                  return;
               }
               if (event.key === 'Escape') {
                  closeMenu();
                  return;
               }
               if (menu.hidden) {
                  if (['ArrowDown', 'ArrowUp', 'Home', 'End', 'Enter', ' '].includes(event.key)) openMenu();
                  return;
               }

               const current = enabledOptions.indexOf(activeIndex);
               if (event.key === 'ArrowDown') setActive(enabledOptions[Math.min(current + 1, enabledOptions.length - 1)]);
               if (event.key === 'ArrowUp') setActive(enabledOptions[Math.max(current - 1, 0)]);
               if (event.key === 'Home') setActive(enabledOptions[0]);
               if (event.key === 'End') setActive(enabledOptions[enabledOptions.length - 1]);
               if (event.key === 'Enter' || event.key === ' ') optionButtons.get(activeIndex)?.click();
            });

            labels.forEach((label) => {
               label.addEventListener('click', (event) => {
                  event.preventDefault();
                  button.focus();
                  openMenu();
               });
            });

            select.addEventListener('change', () => {
               updateValue();
               renderOptions();
            });
            select.addEventListener('invalid', () => {
               button.focus();
               openMenu();
            });
            select.form?.addEventListener('reset', () => {
               window.setTimeout(() => {
                  updateValue();
                  renderOptions();
                  closeMenu();
               }, 0);
            });
            document.addEventListener('pointerdown', (event) => {
               if (!wrapper.contains(event.target) && !menu.contains(event.target)) closeMenu();
            });
            document.addEventListener('scroll', (event) => {
               if (!menu.hidden && event.target !== menu) closeMenu();
            }, true);
            window.addEventListener('resize', () => {
               if (!menu.hidden) positionMenu();
            });

            const observer = new MutationObserver(() => {
               updateValue();
               renderOptions();
            });
            observer.observe(select, {
               attributes: true,
               childList: true,
               subtree: true,
               attributeFilter: ['disabled', 'hidden', 'label', 'value']
            });

            updateValue();
            renderOptions();
         });
      }

      if (document.readyState === 'loading') {
         document.addEventListener('DOMContentLoaded', initializeAdminSelects, { once: true });
      } else {
         initializeAdminSelects();
      }
   </script>

   <!-- Page wrapper: content starts after sidebar + header -->
   <div class="admin-content lg:ml-sidebar pt-header min-h-screen flex flex-col">
      <div class="p-6 flex-1">
