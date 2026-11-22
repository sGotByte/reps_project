<?php
$currentUser = null;

// ไม่มี cookie session = ยังไม่ล็อกอิน → ไม่ต้องเปิด session (ไม่สร้างไฟล์ session / Set-Cookie ให้ผู้เยี่ยมชมทั่วไป)
// หมายเหตุ: ถ้า login.php ตั้ง session_name() หรือ cookie params เอง ต้องเรียกชุดเดียวกันก่อนบรรทัดนี้
if (session_status() === PHP_SESSION_NONE && isset($_COOKIE[session_name()])) {
   ini_set('session.cookie_httponly', '1');
   ini_set('session.cookie_samesite', 'Strict');
   if (isset($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) === 'on') {
      ini_set('session.cookie_secure', '1');
   }
   // read_and_close = อ่านข้อมูลแล้วปล่อย lock ทันที ไม่บล็อก request อื่นของผู้ใช้เดียวกัน
   session_start(['read_and_close' => true]);
}

// ตรวจสอบสถานะการเข้าสู่ระบบเพื่อปรับเมนูบนหน้าแรก (ไม่บังคับให้เข้าสู่ระบบ)
$sessionUserId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
$APP_SKIP_SESSION = true;
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/site_config.php';
require_once __DIR__ . '/includes/icons.php';
$siteSettings = app_get_settings($conn);
$siteLogoUrl = app_logo_url($conn);
$showSteps = ($siteSettings['homepage_show_steps'] ?? '1') === '1';
$showFaq = ($siteSettings['homepage_show_faq'] ?? '1') === '1';
$showAnnouncement = ($siteSettings['homepage_show_announcement'] ?? '0') === '1' && trim((string)($siteSettings['homepage_announcement'] ?? '')) !== '';
$homeIntro = trim((string)($siteSettings['homepage_intro'] ?? ''));
$footerText = trim((string)($siteSettings['footer_text'] ?? ''));

if ($sessionUserId && $sessionUserId > 0) {

   $account = null;
   $accountStmt = $conn->prepare('SELECT first_name, last_name, role, status, password FROM users WHERE user_id = ? LIMIT 1');
   if ($accountStmt) {
      $accountStmt->bind_param('i', $sessionUserId);
      if ($accountStmt->execute()) {
         $account = $accountStmt->get_result()->fetch_assoc();
      }
      $accountStmt->close();
   }

   $validSession = $account && $account['status'] === 'active'
      && is_valid_role(normalize_role((string)$account['role']))
      && isset($_SESSION['auth_password_hash'])
      && hash_equals((string)$account['password'], (string)$_SESSION['auth_password_hash']);

   if ($validSession) {
      $currentUser = [
         'name' => trim($account['first_name'] . ' ' . $account['last_name']),
         'role' => normalize_role((string)$account['role']),
         'dashboard' => role_home_url((string)$account['role']),
      ];
   } else {
      // เซสชันหมดอายุหรือไม่ถูกต้อง — เปิด session แบบเขียนได้อีกครั้งเพื่อล้างทิ้ง
      session_start();
      $_SESSION = [];
      if (ini_get('session.use_cookies')) {
         $params = session_get_cookie_params();
         setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
      }
      session_destroy();
   }
}

$assetVersion = static function (string $relativePath): int {
   return (int)@filemtime(__DIR__ . '/' . $relativePath);
};
?>
<!DOCTYPE html>
<html lang="th">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <meta name="description" content="ระบบบริหารจัดการและแจ้งซ่อมอุปกรณ์คอมพิวเตอร์ของห้องปฏิบัติการกลางคอมพิวเตอร์ แผนกเทคโนโลยีธุรกิจดิจิทัล — แจ้งซ่อม ติดตามสถานะงานซ่อมได้ทุกขั้นตอน">
   <meta name="theme-color" content="#111827">
   <title>REPS - ระบบแจ้งซ่อมอุปกรณ์คอมพิวเตอร์</title>
   <link rel="icon" type="image/svg+xml" href="assets/images/logo/reps-icon.svg">
   <link rel="alternate icon" type="image/png" sizes="32x32" href="assets/images/logo/favicon-32.png">
   <link rel="apple-touch-icon" href="assets/images/logo/apple-touch-icon.png">

   <!-- เปิดใช้ reveal ตั้งแต่ <head> เพื่อไม่ให้เนื้อหากระพริบ (ถ้า JS ปิด เนื้อหายังแสดงครบ) -->
   <script>document.documentElement.classList.add('reveal-init')</script>

   <link rel="preconnect" href="https://fonts.googleapis.com">
   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
   <!-- แยก request: ถ้า family ใดโหลดไม่ได้ จะไม่ลากตัวอื่นล้มไปด้วย -->
   <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">

   <!-- home.css ต้องมาก่อน app.css เพื่อคงลำดับ cascade -->
   <link rel="stylesheet" href="assets/css/home.css?v=<?= $assetVersion('assets/css/home.css') ?>">
   <link rel="stylesheet" href="assets/css/app.css?v=<?= $assetVersion('assets/css/app.css') ?>">
   <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">

   <style>
      /* Hero 3D canvas: อยู่หลังเนื้อหา คลิกทะลุได้ และจางหายที่ขอบเพื่อไม่รบกวนข้อความ */
      .hero-canvas {
         position: absolute;
         inset: 0;
         width: 100%;
         height: 100%;
         z-index: 0;
         pointer-events: none;
         opacity: 0;
         transition: opacity 1.2s ease;
         -webkit-mask-image: radial-gradient(ellipse 85% 65% at 50% 78%, #000 25%, transparent 100%);
         mask-image: radial-gradient(ellipse 85% 65% at 50% 78%, #000 25%, transparent 100%);
      }

      .hero-canvas.is-ready {
         opacity: 1;
      }
   </style>
   <link rel="stylesheet" href="assets/css/base.css?v=18">
</head>

<body class="public-page bg-white text-muted antialiased">

   <a href="#main" class="skip-link">ข้ามไปยังเนื้อหาหลัก</a>

   <!-- Navigation Bar -->
   <header class="fixed top-0 inset-x-0 z-50 border-b border-line bg-white/80 backdrop-blur">
      <div class="max-w-6xl mx-auto flex items-center gap-8 px-6 h-16">

         <!-- Logo (ใส่ width/height ตามขนาดจริงของไฟล์ เพื่อกัน layout shift) -->
         <a class="flex items-center shrink-0" href="index.php" aria-label="REPS หน้าหลัก">
            <img src="<?= htmlspecialchars($siteLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars(app_site_name($conn), ENT_QUOTES, 'UTF-8') ?>" class="h-6 sm:h-7 w-auto">
         </a>

         <!-- Navigation Links -->
         <nav data-main-nav aria-label="เมนูหลัก" class="font-kanit hidden lg:flex items-center gap-8 text-sm text-muted whitespace-nowrap">
            <a href="#services" class="nav-link hover:text-ink transition-colors">บริการแจ้งซ่อม</a>
            <a href="#how" class="nav-link hover:text-ink transition-colors">ขั้นตอนการให้บริการ</a>
            <a href="#showcase" class="nav-link hover:text-ink transition-colors">ฟีเจอร์</a>
            <a href="#faq" class="nav-link hover:text-ink transition-colors">คำถามที่พบบ่อย</a>
            <a href="#contact" class="nav-link hover:text-ink transition-colors">ติดต่อเรา</a>
         </nav>

         <!-- Actions -->
         <div class="flex items-center gap-3 ml-auto">
            <?php if ($currentUser): ?>
               <span class="font-kanit hidden sm:inline-block max-w-[10rem] truncate text-sm text-muted whitespace-nowrap">
                  สวัสดี, <?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?>
               </span>
               <a href="<?= htmlspecialchars($currentUser['dashboard'], ENT_QUOTES, 'UTF-8') ?>" class="font-kanit text-sm bg-[#171717] text-white font-medium px-5 py-2.5 rounded-lg hover:bg-[#262626] transition-colors duration-150 whitespace-nowrap">
                  แดชบอร์ด
               </a>
            <?php else: ?>
               <a href="login.php" class="font-kanit hidden sm:inline-flex items-center min-h-[44px] text-sm text-ink font-medium px-4 rounded-lg hover:bg-neutral-100 transition-colors duration-150 whitespace-nowrap">
                  เข้าสู่ระบบ
               </a>
               <a href="register.php" class="font-kanit inline-flex items-center min-h-[44px] text-sm bg-[#171717] text-white font-medium px-5 rounded-lg hover:bg-[#262626] transition-colors duration-150 whitespace-nowrap">
                  สมัครสมาชิก
               </a>
            <?php endif; ?>
            <button type="button" id="mobile-menu-button" aria-expanded="false" aria-controls="mobile-menu" aria-label="เปิดเมนู" class="lg:hidden inline-flex h-10 w-10 items-center justify-center rounded-lg border border-line text-ink hover:bg-neutral-50 transition-colors">
               <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
         </div>
      </div>

      <nav id="mobile-menu" aria-label="เมนูหลักบนมือถือ" hidden class="lg:hidden border-t border-line bg-white px-6 py-3">
         <div class="mx-auto flex max-w-6xl flex-col gap-1 font-kanit text-sm text-muted">
            <a href="#services" class="nav-link hover:text-ink transition-colors">บริการแจ้งซ่อม</a>
            <a href="#how" class="nav-link hover:text-ink transition-colors">ขั้นตอนการให้บริการ</a>
            <a href="#showcase" class="nav-link hover:text-ink transition-colors">ฟีเจอร์</a>
            <a href="#faq" class="nav-link hover:text-ink transition-colors">คำถามที่พบบ่อย</a>
            <a href="#contact" class="nav-link hover:text-ink transition-colors">ติดต่อเรา</a>
            <div class="mt-1 border-t border-line pt-1">
               <?php if ($currentUser): ?>
                  <a href="<?= htmlspecialchars($currentUser['dashboard'], ENT_QUOTES, 'UTF-8') ?>" class="block rounded-lg px-3 py-2.5 hover:bg-neutral-50 hover:text-ink">แดชบอร์ดของฉัน</a>
                  <a href="logout.php" class="block rounded-lg px-3 py-2.5 hover:bg-neutral-50 hover:text-ink">ออกจากระบบ</a>
               <?php else: ?>
                  <a href="login.php" class="block rounded-lg px-3 py-2.5 hover:bg-neutral-50 hover:text-ink">เข้าสู่ระบบ</a>
                  <a href="register.php" class="block rounded-lg px-3 py-2.5 hover:bg-neutral-50 hover:text-ink">เริ่มต้นใช้งาน</a>
               <?php endif; ?>
            </div>
         </div>
      </nav>
   </header>

   <main id="main">
      <div class="rails" aria-hidden="true"></div>

      <!-- SECTION HERO -->
      <section class="hero-full relative flex items-center justify-center px-6 overflow-hidden">
         <canvas id="hero-3d" class="hero-canvas" aria-hidden="true"></canvas>
         <div class="relative z-10 max-w-4xl mx-auto text-center flex flex-col items-center">

            <h1 class="mt-6 font-kanit text-[clamp(2.25rem,6vw,3.75rem)] font-normal text-ink leading-[1.2] tracking-[-0.02em] text-balance max-w-3xl">
               ระบบบริหารจัดการ<br class="hidden sm:inline" />และแจ้งซ่อมอุปกรณ์คอมพิวเตอร์
            </h1>

            <?php if ($showAnnouncement): ?>
               <div class="mt-4 max-w-xl rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 whitespace-pre-line" role="note">
                  <i class="fa-solid fa-bullhorn mr-1" aria-hidden="true"></i><?= htmlspecialchars((string)$siteSettings['homepage_announcement'], ENT_QUOTES, 'UTF-8') ?>
               </div>
            <?php endif; ?>

            <p class="mt-4 mb-2 text-muted text-base sm:text-lg max-w-xl mx-auto leading-relaxed text-pretty whitespace-pre-line">
               <?= $homeIntro !== '' ? htmlspecialchars($homeIntro, ENT_QUOTES, 'UTF-8') : 'แจ้งซ่อมคอมพิวเตอร์ อุปกรณ์ไอที และระบบเครือข่าย ติดตามสถานะงานซ่อมได้ทุกขั้นตอน ตั้งแต่เข้าคิวจนครูรับรองงานเสร็จ' ?>
            </p>

            <div class="w-full mt-8 flex flex-col sm:flex-row items-center justify-center gap-4">
               <a href="request_form.php"
                  class="font-kanit w-full sm:w-48 inline-flex items-center justify-center bg-ink text-white font-medium text-base px-6 py-4 rounded-xl hover:bg-[#262626] hover:shadow-md transition-all duration-150 active:scale-[0.99]">
                  แจ้งซ่อมทันที
               </a>
               <a href="#how"
                  class="font-kanit w-full sm:w-48 inline-flex items-center justify-center border border-line bg-white text-ink font-medium text-base px-6 py-4 rounded-xl hover:border-ink/50 hover:bg-[#F6F6F4] transition-all duration-150 active:scale-[0.99]">
                  ขั้นตอนการให้บริการ
               </a>
            </div>

            <div class="mt-10 inline-flex items-center gap-2 bg-white px-4 py-2 font-kanit text-xs sm:text-sm text-muted">
               <i class="fa-regular fa-clock text-ink" aria-hidden="true"></i>
               <span>เวลาทำการ: <span class="text-ink tabular-nums"><?= htmlspecialchars(trim((string)($siteSettings['office_hours'] ?? '')) ?: 'จันทร์–ศุกร์ 08:30–16:30 น.', ENT_QUOTES, 'UTF-8') ?></span></span>
            </div>
         </div>

         <!-- Scroll cue -->
         <a href="#services" aria-label="เลื่อนลงไปดูบริการ" class="scroll-cue absolute bottom-6 left-1/2 -translate-x-1/2 z-10 inline-flex flex-col items-center gap-1.5 font-kanit text-xs text-neutral-500 hover:text-ink transition-colors">
            <span>เลื่อนลง</span>
            <i class="fa-solid fa-chevron-down text-[10px]" aria-hidden="true"></i>
         </a>
      </section>

      <div class="divider" aria-hidden="true"></div>

      <!-- SECTION SERVICES -->
      <section id="services" class="bg-[#F6F6F4] w-full px-6 py-24 scroll-mt-20">
         <div class="max-w-6xl mx-auto">
            <div data-reveal class="section-head">
               <span class="section-eyebrow font-mono font-normal">
                  01 Services
                  <span class="h-px w-8 bg-neutral-300" aria-hidden="true"></span>
               </span>
               <div class="section-head-body max-w-xl px-6">
                  <h2 class="font-kanit text-3xl sm:text-4xl font-normal text-ink leading-[1.25] tracking-tight text-balance">ขอบเขตงานบริการ</h2>
                  <p class="mt-2.5 text-muted text-sm sm:text-base leading-relaxed">
                     ครอบคลุมทุกปัญหาทางด้านเทคโนโลยีสารสนเทศและอุปกรณ์ภายในห้องปฏิบัติการ
                  </p>
               </div>
            </div>

            <div data-reveal class="cells-wrap">
               <div class="cells">
                  <div class="service-card cell group relative">
                     <div class="flex items-center justify-between mb-4">
                        <i class="fa-solid fa-laptop text-xl text-ink" aria-hidden="true"></i>
                     </div>
                     <h3 class="font-kanit text-lg font-normal text-ink leading-snug mb-3">คอมพิวเตอร์และฮาร์ดแวร์</h3>
                     <p class="text-sm text-muted leading-relaxed">ตรวจเช็กเครื่องเปิดไม่ติด อุปกรณ์ชำรุด หรืออัปเกรดชิ้นส่วนต่าง ๆ</p>
                  </div>

                  <div class="service-card cell group relative">
                     <div class="flex items-center justify-between mb-4">
                        <i class="fa-brands fa-windows text-xl text-ink" aria-hidden="true"></i>
                     </div>
                     <h3 class="font-kanit text-lg font-normal text-ink leading-snug mb-3">ระบบปฏิบัติการและซอฟต์แวร์</h3>
                     <p class="text-sm text-muted leading-relaxed">ติดตั้งโปรแกรม แก้ไขปัญหาระบบ Windows ติดไวรัส หรือข้อผิดพลาดการใช้งาน</p>
                  </div>

                  <div class="service-card cell group relative">
                     <div class="flex items-center justify-between mb-4">
                        <i class="fa-solid fa-print text-xl text-ink" aria-hidden="true"></i>
                     </div>
                     <h3 class="font-kanit text-lg font-normal text-ink leading-snug mb-3">อุปกรณ์ต่อพ่วงและเครื่องพิมพ์</h3>
                     <p class="text-sm text-muted leading-relaxed">ซ่อมบำรุงเครื่องพิมพ์ สแกนเนอร์ และโปรเจกเตอร์ในห้องเรียน/ห้องปฏิบัติการ</p>
                  </div>

                  <div class="service-card cell group relative">
                     <div class="flex items-center justify-between mb-4">
                        <i class="fa-solid fa-globe text-xl text-ink" aria-hidden="true"></i>
                     </div>
                     <h3 class="font-kanit text-lg font-normal text-ink leading-snug mb-3">ระบบเครือข่ายและอินเทอร์เน็ต</h3>
                     <p class="text-sm text-muted leading-relaxed">แก้ปัญหาสาย LAN เชื่อมต่อ Wi-Fi ไม่ได้ หรือสัญญาณขัดข้อง</p>
                  </div>
               </div>
            </div>
         </div>
      </section>

      <div class="divider" aria-hidden="true"></div>

      <!-- SECTION HOW (Stepper) -->
      <?php if ($showSteps): ?>
      <section id="how" class="bg-gray-50 w-full px-6 py-24 scroll-mt-20">
         <div class="max-w-6xl mx-auto">
            <div data-reveal class="section-head">
               <span class="section-eyebrow font-mono font-normal">
                  02 Process
                  <span class="h-px w-8 bg-neutral-300" aria-hidden="true"></span>
               </span>
               <div class="section-head-body max-w-xl px-6">
                  <h2 class="font-kanit text-4xl font-normal text-ink">ขั้นตอนการให้บริการ</h2>
                  <p class="mt-2 text-sm sm:text-base text-muted">ขั้นตอนง่าย ๆ เพื่อความสะดวกและติดตามผลได้จริง</p>
               </div>
            </div>

            <div data-reveal class="steps-wrap">
               <ol class="steps m-0 p-0 list-none">
                  <li class="step">
                     <span class="step-num" aria-hidden="true">1</span>
                     <div class="step-body">
                        <span class="font-kanit font-normal text-base text-ink leading-snug">กรอกข้อมูลแจ้งซ่อม</span>
                        <span class="mt-1 font-sans text-sm text-muted leading-relaxed max-w-[220px]">ระบุอาการและอุปกรณ์ที่ชำรุดผ่านแบบฟอร์มออนไลน์</span>
                     </div>
                  </li>
                  <li class="step">
                     <span class="step-num" aria-hidden="true">2</span>
                     <div class="step-body">
                        <span class="font-kanit font-normal text-base text-ink leading-snug">รับเรื่องและส่งต่อช่าง</span>
                        <span class="mt-1 font-sans text-sm text-muted leading-relaxed max-w-[220px]">ใบงานเข้าคิว สตาฟฝ่ายซ่อมคนถัดไปกดรับงาน</span>
                     </div>
                  </li>
                  <li class="step">
                     <span class="step-num" aria-hidden="true">3</span>
                     <div class="step-body">
                        <span class="font-kanit font-normal text-base text-ink leading-snug">เข้าแก้ไขและปิดงาน</span>
                        <span class="mt-1 font-sans text-sm text-muted leading-relaxed max-w-[220px]">ช่างเข้าดำเนินการและปิดงานเมื่อซ่อมเสร็จสิ้น</span>
                     </div>
                  </li>
               </ol>
            </div>
         </div>
      </section>
      <?php endif; ?>

      <div class="divider" aria-hidden="true"></div>

      <!-- SECTION FEATURES -->
      <section id="showcase" class="relative bg-white w-full px-6 py-24 scroll-mt-20">
         <div class="showcase-wrap max-w-6xl mx-auto">

            <div data-reveal class="section-head">
               <span class="section-eyebrow font-mono font-normal">
                  03 Features
                  <span class="h-px w-8 bg-neutral-300" aria-hidden="true"></span>
               </span>
            </div>

            <div data-reveal class="showcase-bento">
               <div class="showcase-bento-intro">
                  <h2 class="font-kanit text-3xl sm:text-4xl font-normal text-ink leading-[1.25] tracking-tight text-balance">
                     ออกแบบมาเพื่อห้องปฏิบัติการ<br class="hidden sm:inline">และการติดตามงานที่โปร่งใส
                  </h2>
                  <p class="mt-2.5 text-sm sm:text-base text-muted leading-relaxed max-w-md">
                     ยกระดับการแจ้งซ่อมอุปกรณ์คอมพิวเตอร์จากกระดาษสู่ระบบออนไลน์ ตรวจสอบสถานะได้ทุกขั้นตอนตั้งแต่วินาทีที่เปิดใบงาน
                  </p>
               </div>

               <!-- Card 1: ติดตามสถานะ -->
               <article class="feature-item showcase-slot-1">
                  <div class="feature-stage" aria-hidden="true">
                     <div class="ui-window">
                        <div class="ui-window-bar"><span></span><span></span><span></span></div>
                        <div class="ui-window-body">
                           <div class="flex items-center justify-between gap-1 px-1">
                              <span class="w-2.5 h-2.5 rounded-full bg-neutral-800"></span>
                              <span class="h-0.5 flex-1 bg-neutral-200 anim-step-line transition-all"></span>
                              <span class="w-2.5 h-2.5 rounded-full bg-neutral-300 anim-step-dot transition-all"></span>
                              <span class="h-0.5 flex-1 bg-neutral-200"></span>
                              <span class="w-2.5 h-2.5 rounded-full bg-neutral-200"></span>
                           </div>
                           <div class="mt-2 rounded border border-neutral-200 bg-neutral-50 px-2.5 py-2 anim-pill-slide transition-all">
                              <div class="flex items-center justify-between">
                                 <span class="font-mono text-[10px] font-medium">REQ-2026</span>
                                 <span class="font-kanit text-[9px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-800">กำลังซ่อม</span>
                              </div>
                              <div class="ui-bar w-3/4 mt-1.5"></div>
                           </div>
                        </div>
                     </div>
                  </div>
                  <div class="feature-meta">
                     <h3 class="feature-title">
                        ติดตามสถานะทุกขั้นตอน <span class="feature-arrow"><?= app_icon('arrow-right') ?></span>
                     </h3>
                     <p class="feature-desc">
                        เช็กสถานะได้ตั้งแต่เข้าคิว กำลังซ่อม รอครูรับรอง ไปจนถึงเสร็จสมบูรณ์ พร้อมรูปหลังซ่อม
                     </p>
                  </div>
               </article>

               <!-- Card 2: Instant LINE Notification -->
               <article class="feature-item showcase-slot-2">
                  <div class="feature-stage" aria-hidden="true">
                     <div class="ui-window">
                        <div class="ui-window-bar"><span></span><span></span><span></span></div>
                        <div class="ui-window-body">
                           <div class="anim-toast anim-toast-1 flex items-center gap-2 rounded border border-neutral-200 bg-neutral-50 p-1.5">
                              <span class="w-5 h-5 rounded-full bg-neutral-200 flex items-center justify-center text-[10px] text-neutral-600">
                                 <i class="fa-solid fa-wrench"></i>
                              </span>
                              <div class="flex-1 space-y-1">
                                 <div class="ui-bar w-2/3"></div>
                                 <div class="ui-bar w-1/2 opacity-60"></div>
                              </div>
                           </div>
                           <div class="anim-toast anim-toast-2 flex items-center gap-2 rounded border border-neutral-200 bg-white p-1.5 shadow-2xs">
                              <span class="w-5 h-5 rounded-full bg-neutral-100 flex items-center justify-center text-[10px] text-neutral-700 anim-bell transition-transform">
                                 <i class="fa-solid fa-bell"></i>
                              </span>
                              <div class="flex-1 space-y-1">
                                 <div class="ui-bar w-3/4 bg-neutral-300"></div>
                                 <div class="ui-bar w-1/3 opacity-60"></div>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
                  <div class="feature-meta">
                     <h3 class="feature-title">
                        แจ้งเตือนทีมช่างทันที <span class="feature-arrow"><?= app_icon('arrow-right') ?></span>
                     </h3>
                     <p class="feature-desc">
                        แจ้งเตือนทีมช่างผ่าน LINE ทันทีที่มีใบแจ้งซ่อมใหม่ และส่งอีเมลถึงผู้แจ้งเมื่องานเสร็จ
                     </p>
                  </div>
               </article>

               <!-- Card 3: Role-Based Dashboard -->
               <article class="feature-item showcase-slot-3">
                  <div class="feature-stage" aria-hidden="true">
                     <div class="ui-window">
                        <div class="ui-window-bar"><span></span><span></span><span></span></div>
                        <div class="ui-window-body !flex-row gap-2.5 items-stretch">
                           <div class="w-1/4 flex flex-col items-center gap-1.5 pt-0.5">
                              <div class="w-6 h-6 rounded-full bg-neutral-200 flex items-center justify-center text-[8px] text-neutral-600 anim-avatar transition-all">
                                 <i class="fa-solid fa-play -rotate-90"></i>
                              </div>
                              <div class="ui-bar w-full !h-1"></div>
                              <div class="ui-bar w-4/5 !h-1 self-start"></div>
                              <div class="ui-bar w-full !h-1"></div>
                           </div>
                           <div class="flex-1 flex flex-col gap-1.5">
                              <div class="ui-bar w-1/3 !h-1.5"></div>
                              <div class="grid grid-cols-2 gap-1.5 flex-1">
                                 <div class="ui-block anim-grid-1"></div>
                                 <div class="ui-block anim-grid-2 row-span-2"></div>
                                 <div class="ui-block anim-grid-3"></div>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
                  <div class="feature-meta">
                     <h3 class="feature-title">
                        หน้าจอตามบทบาท <span class="feature-arrow"><?= app_icon('arrow-right') ?></span>
                     </h3>
                     <p class="feature-desc">
                        แยกหน้าจอสำหรับผู้แจ้ง สตาฟฝ่ายซ่อม และครูผู้ดูแล แต่ละคนเห็นเฉพาะสิ่งที่ต้องทำ
                     </p>
                  </div>
               </article>

               <!-- Card 4: Photo & Symptom Attachment -->
               <article class="feature-item showcase-slot-4">
                  <div class="feature-stage" aria-hidden="true">
                     <div class="ui-window">
                        <div class="ui-window-bar"><span></span><span></span><span></span></div>
                        <div class="ui-window-body">
                           <div class="anim-dropzone flex-1 rounded-md border border-dashed border-neutral-300 bg-neutral-50 flex flex-col items-center justify-center gap-1 p-2 transition-all">
                              <i class="fa-regular fa-image text-base text-neutral-500 anim-upload-icon transition-all"></i>
                              <div class="ui-bar w-16 !h-1.5"></div>
                              <span class="font-mono text-[9px] text-neutral-500">JPG, PNG, WEBP</span>
                           </div>
                        </div>
                     </div>
                  </div>
                  <div class="feature-meta">
                     <h3 class="feature-title">
                        แนบรูปประกอบ <span class="feature-arrow"><?= app_icon('arrow-right') ?></span>
                     </h3>
                     <p class="feature-desc">
                        แนบภาพถ่ายอาการเสียหรือหน้าจอ Error เพื่อให้ช่างวิเคราะห์และเตรียมอะไหล่ได้ตรงจุด
                     </p>
                  </div>
               </article>

               <!-- Card 5: Equipment & Lab History -->
               <article class="feature-item showcase-slot-5">
                  <div class="feature-stage" aria-hidden="true">
                     <div class="ui-window">
                        <div class="ui-window-bar"><span></span><span></span><span></span></div>
                        <div class="ui-window-body gap-1.5">
                           <div class="anim-row-1 flex items-center justify-between rounded border border-neutral-200/80 px-2 py-1 transition-all">
                              <span class="font-mono text-[9px] text-neutral-500">LAB-01 / PC-12</span>
                              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                           </div>
                           <div class="anim-row-2 flex items-center justify-between rounded border border-neutral-200 px-2 py-1 transition-all">
                              <span class="font-mono text-[9px] text-neutral-700 font-medium">LAB-02 / PC-05</span>
                              <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                           </div>
                           <div class="anim-row-3 flex items-center justify-between rounded border border-neutral-200/80 px-2 py-1 transition-all">
                              <span class="font-mono text-[9px] text-neutral-500">LAB-03 / PRN-01</span>
                              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                           </div>
                        </div>
                     </div>
                  </div>
                  <div class="feature-meta">
                     <h3 class="feature-title">
                        Lab &amp; Asset History <span class="feature-arrow"><?= app_icon('arrow-right') ?></span>
                     </h3>
                     <p class="feature-desc">
                        บันทึกประวัติการซ่อมบำรุงรายเครื่องและรายห้องปฏิบัติการ ตรวจสอบย้อนหลังได้แม่นยำ
                     </p>
                  </div>
               </article>
            </div>

         </div>
      </section>

      <div class="divider" aria-hidden="true"></div>

      <!-- SECTION FAQ -->
      <?php if ($showFaq): ?>
      <section id="faq" class="bg-[#F6F6F4] w-full px-6 py-24 scroll-mt-20">
         <div class="max-w-6xl mx-auto">
            <div data-reveal class="section-head">
               <span class="section-eyebrow font-mono font-normal">
                  04 FAQ
                  <span class="h-px w-8 bg-neutral-300" aria-hidden="true"></span>
               </span>
               <div class="section-head-body max-w-xl px-6">
                  <h2 class="font-kanit text-3xl sm:text-4xl font-normal text-ink leading-[1.25] tracking-tight text-balance">
                     คำถามที่พบบ่อย
                  </h2>
                  <p class="mt-2.5 text-muted text-sm sm:text-base leading-relaxed">
                     ข้อควรรู้และการเตรียมข้อมูลเบื้องต้นก่อนเปิดใบแจ้งซ่อม เพื่อให้ทีมช่างเข้าแก้ไขได้รวดเร็วที่สุด
                  </p>
               </div>
            </div>

            <div data-reveal class="faq-wrap">
               <div class="faq-grid">
                  <details class="faq-item" open>
                     <summary>
                        <span class="font-kanit text-base font-normal text-ink leading-snug">
                           ก่อนกดแจ้งซ่อม ต้องเตรียมข้อมูลอะไรบ้าง?
                        </span>
                        <span class="faq-icon" aria-hidden="true">
                           <i class="fa-solid fa-plus"></i>
                        </span>
                     </summary>
                     <div class="faq-answer">
                        โปรดระบุ <strong>หมายเลขห้องปฏิบัติการ</strong>, <strong>หมายเลขเครื่อง (เช่น PC-01) หรือรหัสครุภัณฑ์</strong> และอาการเสียที่พบอย่างชัดเจน หากมีภาพถ่ายหน้าจอ Error สามารถแนบมาพร้อมใบแจ้งซ่อมได้ทันที
                     </div>
                  </details>

                  <details class="faq-item" open>
                     <summary>
                        <span class="font-kanit text-base font-normal text-ink leading-snug">
                           ใช้เวลานานแค่ไหนในการเข้าตรวจสอบและซ่อมบำรุง?
                        </span>
                        <span class="faq-icon" aria-hidden="true">
                           <i class="fa-solid fa-plus"></i>
                        </span>
                     </summary>
                     <div class="faq-answer">
                        เจ้าหน้าที่จะรับเรื่องภายในวันทำการ (จันทร์ - ศุกร์ 08:30 - 16:30 น.) กรณีปัญหาซอฟต์แวร์หรือเครือข่ายเบื้องต้นจะดำเนินการภายใน 1 วันทำการ ส่วนกรณีรออะไหล่จะมีอัปเดตสถานะแจ้งให้ทราบในระบบ
                     </div>
                  </details>

                  <details class="faq-item">
                     <summary>
                        <span class="font-kanit text-base font-normal text-ink leading-snug">
                           หากเครื่องเปิดไม่ติดหรือจอไม่แสดงผล ควรเช็กอะไรเบื้องต้น?
                        </span>
                        <span class="faq-icon" aria-hidden="true">
                           <i class="fa-solid fa-plus"></i>
                        </span>
                     </summary>
                     <div class="faq-answer">
                        แนะนำให้ตรวจสอบสวิตช์ปลั๊กไฟพ่วงใต้โต๊ะ สายไฟหลังเคส และสายสัญญาณจอภาพ (HDMI/VGA) ว่าเสียบแน่นดีหรือไม่ หากตรวจสอบแล้วยังใช้งานไม่ได้ สามารถเปิดใบแจ้งซ่อมได้ทันที
                     </div>
                  </details>

                  <details class="faq-item">
                     <summary>
                        <span class="font-kanit text-base font-normal text-ink leading-snug">
                           อินเทอร์เน็ตใช้งานไม่ได้ทั้งห้อง ต้องแจ้งแยกทีละเครื่องไหม?
                        </span>
                        <span class="faq-icon" aria-hidden="true">
                           <i class="fa-solid fa-plus"></i>
                        </span>
                     </summary>
                     <div class="faq-answer">
                        ไม่ต้องแจ้งแยกเครื่องครับ สามารถเปิดใบแจ้งซ่อมเพียง 1 รายการในหมวดระบบเครือข่าย แล้วระบุหมายเลขห้องปฏิบัติการและอาการว่าไม่สามารถเชื่อมต่อได้ทั้งห้อง
                     </div>
                  </details>

                  <details class="faq-item">
                     <summary>
                        <span class="font-kanit text-base font-normal text-ink leading-snug">
                           ต้องการให้ติดตั้งโปรแกรมสำหรับการเรียนการสอน แจ้งผ่านระบบนี้ได้หรือไม่?
                        </span>
                        <span class="faq-icon" aria-hidden="true">
                           <i class="fa-solid fa-plus"></i>
                        </span>
                     </summary>
                     <div class="faq-answer">
                        แจ้งได้ครับ โดยเลือกหมวดหมู่ระบบปฏิบัติการและซอฟต์แวร์ พร้อมระบุชื่อโปรแกรม เวอร์ชันที่ต้องการ และหมายเลขห้องปฏิบัติการล่วงหน้าอย่างน้อย 2-3 วันทำการ
                     </div>
                  </details>

                  <details class="faq-item">
                     <summary>
                        <span class="font-kanit text-base font-normal text-ink leading-snug">
                           จะติดตามความคืบหน้าของงานซ่อมที่แจ้งไปแล้วได้จากที่ไหน?
                        </span>
                        <span class="faq-icon" aria-hidden="true">
                           <i class="fa-solid fa-plus"></i>
                        </span>
                     </summary>
                     <div class="faq-answer">
                        สามารถเข้าสู่ระบบแล้วดูที่หน้า <strong>แดชบอร์ด</strong> ของคุณได้ตลอดเวลา ระบบจะแสดงสถานะล่าสุด (รอรับเรื่อง / กำลังดำเนินการ / รอครูรับรอง / เสร็จสมบูรณ์) พร้อมรายละเอียดการแก้ไขจากช่างผู้รับผิดชอบ
                     </div>
                  </details>
               </div>
            </div>
         </div>
      </section>
      <?php endif; ?>

      <div class="divider" aria-hidden="true"></div>

      <!-- SECTION CONTACT CTA -->
      <section id="contact" class="contact-wrap max-w-6xl mx-auto py-24 scroll-mt-20">

         <span class="section-eyebrow contact-eyebrow font-mono font-normal" aria-hidden="true">
            05 Contact
            <span class="h-px w-8 bg-neutral-300"></span>
         </span>

         <div data-reveal class="relative overflow-hidden rounded-lg bg-gradient-to-br from-neutral-950 to-neutral-800 shadow-xl px-6 py-14 sm:px-12 sm:py-16 text-left">
            <div class="pointer-events-none absolute -top-24 -right-24 h-64 w-64 rounded-full border border-dashed border-white/15" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-28 -left-20 h-72 w-72 rounded-full border border-white/10" aria-hidden="true"></div>
            <div class="pointer-events-none absolute top-1/3 left-1/4 h-40 w-40 rounded-full bg-white/10 blur-3xl" aria-hidden="true"></div>

            <div class="relative z-10">

               <h2 class="mt-3 font-kanit text-2xl sm:text-3xl font-normal text-white leading-[1.3] text-balance">
                  พร้อมแจ้งซ่อมแล้วหรือยัง?
               </h2>
               <p class="mt-3 text-sm sm:text-base text-white/70 max-w-xl leading-relaxed">
                  เปิดตั๋วแจ้งซ่อมได้ทันที และติดตามความคืบหน้าได้จากทุกที่ ตลอดเวลาทำการ
               </p>

               <div class="mt-10 grid grid-cols-1 sm:grid-cols-3 gap-px rounded-xl overflow-hidden border border-white/10 bg-white/10 text-left">
                  <div class="bg-white/[0.06] backdrop-blur px-6 py-5">
                     <div class="flex items-center gap-3">
                        <i class="fa-solid fa-clock text-white/80" aria-hidden="true"></i>
                        <span class="font-kanit text-xs text-white/75">เวลาทำการ</span>
                     </div>
                     <p class="mt-1.5 font-kanit text-sm font-medium text-white tabular-nums">จันทร์ - ศุกร์ 08:30 - 16:30 น.</p>
                  </div>
                  <div class="bg-white/[0.06] backdrop-blur px-6 py-5">
                     <div class="flex items-center gap-3">
                        <i class="fa-solid fa-bolt text-white/80" aria-hidden="true"></i>
                        <span class="font-kanit text-xs text-white/75">สถานะงานซ่อม</span>
                     </div>
                     <p class="mt-1.5 font-kanit text-sm font-medium text-white">ติดตามได้ทุกขั้นตอน</p>
                  </div>
                  <div class="bg-white/[0.06] backdrop-blur px-6 py-5">
                     <div class="flex items-center gap-3">
                        <i class="fa-solid fa-user-gear text-white/80" aria-hidden="true"></i>
                        <span class="font-kanit text-xs text-white/75">ทีมช่างประจำ</span>
                     </div>
                     <p class="mt-1.5 font-kanit text-sm font-medium text-white">ห้องปฏิบัติการกลางคอมพิวเตอร์</p>
                  </div>
               </div>

               <div class="mt-10 flex flex-col sm:flex-row items-center sm:justify-start gap-4">
                  <a href="request_form.php"
                     class="font-kanit w-full sm:w-56 inline-flex items-center justify-center bg-white text-neutral-900 font-medium text-base px-6 py-4 rounded-xl hover:bg-neutral-200 transition-colors duration-150 active:scale-[0.99]">
                     แจ้งซ่อมทันที
                  </a>
                  <?php if ($currentUser): ?>
                     <a href="<?= htmlspecialchars($currentUser['dashboard'], ENT_QUOTES, 'UTF-8') ?>"
                        class="font-kanit w-full sm:w-56 inline-flex items-center justify-center border border-white/40 text-white font-medium text-base px-6 py-4 rounded-xl hover:bg-white/10 transition-colors duration-150 active:scale-[0.99]">
                        ไปที่แดชบอร์ด
                     </a>
                  <?php else: ?>
                     <a href="login.php"
                        class="font-kanit w-full sm:w-56 inline-flex items-center justify-center border border-white/40 text-white font-medium text-base px-6 py-4 rounded-xl hover:bg-white/10 transition-colors duration-150 active:scale-[0.99]">
                        เข้าสู่ระบบติดตามสถานะ
                     </a>
                  <?php endif; ?>
               </div>
            </div>
         </div>
      </section>

      <div class="divider" aria-hidden="true"></div>

   </main>

   <!-- FOOTER -->
    <?php $footerBase = ''; $footerVariant = 'light'; require __DIR__ . '/includes/site_footer.php'; ?>

   <script>
      // ---- Mobile menu ----
      const menuButton = document.getElementById('mobile-menu-button');
      const mobileMenu = document.getElementById('mobile-menu');
      if (menuButton && mobileMenu) {
         const setMenu = (open) => {
            menuButton.setAttribute('aria-expanded', String(open));
            menuButton.setAttribute('aria-label', open ? 'ปิดเมนู' : 'เปิดเมนู');
            mobileMenu.hidden = !open;
            menuButton.querySelector('i').className = open ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
         };
         menuButton.addEventListener('click', () => {
            setMenu(menuButton.getAttribute('aria-expanded') !== 'true');
         });
         mobileMenu.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setMenu(false)));
         document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && menuButton.getAttribute('aria-expanded') === 'true') {
               setMenu(false);
               menuButton.focus();
            }
         });
         document.addEventListener('click', (event) => {
            if (menuButton.getAttribute('aria-expanded') === 'true' &&
               !mobileMenu.contains(event.target) &&
               !menuButton.contains(event.target)) {
               setMenu(false);
            }
         });
      }

      // ---- Reveal on scroll (คลาส reveal-init ถูกใส่ไว้ใน <head> แล้ว) ----
      const revealEls = document.querySelectorAll('[data-reveal]');
      if ('IntersectionObserver' in window) {
         const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
               if (entry.isIntersecting) {
                  entry.target.classList.add('is-revealed');
                  revealObserver.unobserve(entry.target);
               }
            });
         }, {
            threshold: 0,
            rootMargin: '0px 0px -10% 0px'
         });
         revealEls.forEach((el) => revealObserver.observe(el));
      } else {
         revealEls.forEach((el) => el.classList.add('is-revealed'));
      }

      // ---- Scrollspy: ไฮไลต์ลิงก์เมนูของ section ที่กำลังดู (desktop nav เท่านั้น) ----
      const spyLinks = Array.from(document.querySelectorAll('nav[data-main-nav] a[href^="#"]'));
      const spySections = [];
      spyLinks.forEach((link) => {
         const section = document.getElementById(link.getAttribute('href').slice(1));
         if (section) spySections.push([section, link]);
      });
      if ('IntersectionObserver' in window && spySections.length) {
         const setActiveLink = (activeLink) => {
            spyLinks.forEach((link) => link.removeAttribute('aria-current'));
            if (activeLink) activeLink.setAttribute('aria-current', 'true');
         };
         const spyObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
               if (entry.isIntersecting) {
                  const match = spySections.find(([section]) => section === entry.target);
                  if (match) setActiveLink(match[1]);
               }
            });
         }, {
            rootMargin: '-40% 0px -55% 0px'
         });
         spySections.forEach(([section]) => spyObserver.observe(section));

         // ท้ายหน้า: section สุดท้าย (ติดต่อเรา) สั้นเกินกว่าจะเข้าแถบตรวจจับ จึงไฮไลต์เมื่อเลื่อนสุดหน้า
         let spyTicking = false;
         window.addEventListener('scroll', () => {
            if (spyTicking) return;
            spyTicking = true;
            requestAnimationFrame(() => {
               if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4) {
                  setActiveLink(spySections[spySections.length - 1][1]);
               }
               spyTicking = false;
            });
         }, {
            passive: true
         });
      }

      // ---- Hero 3D: ตาข่ายจุดแบบ perspective ไหลช้าๆ + parallax เบาๆ ตามเมาส์ ----
      // เบามาก: ~1,000 จุด, หยุดเมื่อพ้นจอ/แท็บถูกซ่อน, ปิดการเคลื่อนไหวเมื่อผู้ใช้ตั้ง reduced-motion
      (() => {
         const canvas = document.getElementById('hero-3d');
         const ctx = canvas && canvas.getContext('2d');
         if (!ctx) return;

         const hero = canvas.parentElement;
         const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

         // ค่าที่ปรับแต่งได้
         const COLS = 41; // จำนวนจุดแนวกว้าง
         const ROWS = 28; // จำนวนแถวแนวลึก
         const GAP = 1.5; // ระยะห่างระหว่างจุด
         const AMP = 0.55; // ความสูงคลื่น (น้อย = เรียบ)
         const SPEED = 0.35; // ความเร็วไหลเข้าหาผู้ชม
         const CAM_Y = 5; // ความสูงกล้อง
         const CAM_Z = 6; // ระยะกล้องถอยหลัง
         const PITCH = 0.35; // มุมก้มของกล้อง (เรเดียน)
         const INK = '23, 23, 23'; // สีหมึก (ตรงกับธีมขาว-ดำ)

         let w = 0,
            h = 0,
            dpr = 1,
            running = false,
            rafId = 0;
         let mx = 0,
            my = 0,
            camX = 0,
            tilt = 0; // เป้าหมายเมาส์ (-1..1) และค่าที่ปรับแบบนุ่มนวล
         let travel = 0,
            last = 0;

         const sinP = Math.sin(PITCH),
            cosP = Math.cos(PITCH);
         const wave = (x, z, t) => Math.sin(x * 0.28 + t * 0.55) * AMP + Math.cos(z * 0.24 - t * 0.4) * AMP;

         function resize() {
            const rect = hero.getBoundingClientRect();
            dpr = Math.min(window.devicePixelRatio || 1, 2);
            w = Math.max(1, Math.round(rect.width));
            h = Math.max(1, Math.round(rect.height));
            canvas.width = Math.round(w * dpr);
            canvas.height = Math.round(h * dpr);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            if (!running) draw(0);
         }

         function draw(t) {
            ctx.clearRect(0, 0, w, h);

            const F = Math.max(h * 0.9, w * 0.6); // focal length
            const cx = w / 2;
            const cy = h * 0.72; // ดันตาข่ายไปอยู่ครึ่งล่างของ hero
            const offset = travel % GAP;
            const sinT = Math.sin(PITCH + tilt * 0.04),
               cosT = Math.cos(PITCH + tilt * 0.04);

            for (let r = 0; r <= ROWS; r++) {
               const z = r * GAP - offset; // ตำแหน่งแถวบนจอ
               const zw = z + travel; // ตำแหน่งในโลก (ให้คลื่นเกาะติดตาข่าย)
               const depth = r / ROWS; // 0 = ใกล้, 1 = ไกล
               // จางเข้าที่ขอบใกล้/ไกล เพื่อไม่ให้แถวโผล่หรือหายกะทันหัน
               const fade = Math.min(1, (z + GAP) / (GAP * 2)) * (1 - depth * depth);
               if (fade <= 0.01) continue;

               const pts = [];
               for (let c = 0; c < COLS; c++) {
                  const x = (c - (COLS - 1) / 2) * GAP;
                  const y = wave(x, zw, t);
                  const px = x - camX;
                  const py = y - CAM_Y;
                  const pz = z + CAM_Z;
                  const zv = -py * sinT + pz * cosT;
                  if (zv < 0.5) {
                     pts.push(null);
                     continue;
                  }
                  const yv = py * cosT + pz * sinT;
                  pts.push([cx + px * F / zv, cy - yv * F / zv]);
               }

               // เส้นบางมากตามแนวกว้าง
               ctx.beginPath();
               let started = false;
               for (const p of pts) {
                  if (!p) {
                     started = false;
                     continue;
                  }
                  if (!started) {
                     ctx.moveTo(p[0], p[1]);
                     started = true;
                  } else ctx.lineTo(p[0], p[1]);
               }
               ctx.strokeStyle = `rgba(${INK}, ${(0.06 * fade).toFixed(3)})`;
               ctx.lineWidth = 1;
               ctx.stroke();

               // จุด: ใกล้ = ใหญ่/เข้มกว่าเล็กน้อย
               ctx.fillStyle = `rgba(${INK}, ${(0.28 * fade).toFixed(3)})`;
               const size = 1.7 - depth * 1.0;
               for (const p of pts) {
                  if (!p || p[0] < -4 || p[0] > w + 4) continue;
                  ctx.beginPath();
                  ctx.arc(p[0], p[1], size, 0, 6.2832);
                  ctx.fill();
               }
            }
         }

         function frame(now) {
            if (!running) return;
            const dt = Math.min((now - last) / 1000, 0.05);
            last = now;
            travel += dt * SPEED;
            camX += (mx * 3 - camX) * 0.04; // lerp ให้นุ่ม ไม่กระตุก
            tilt += (my - tilt) * 0.04;
            draw(now / 1000);
            rafId = requestAnimationFrame(frame);
         }

         function start() {
            if (running || reduceMotion.matches || document.hidden) return;
            running = true;
            last = performance.now();
            rafId = requestAnimationFrame(frame);
         }

         function stop() {
            running = false;
            cancelAnimationFrame(rafId);
         }

         hero.addEventListener('pointermove', (e) => {
            const rect = hero.getBoundingClientRect();
            mx = ((e.clientX - rect.left) / rect.width - 0.5) * 2;
            my = ((e.clientY - rect.top) / rect.height - 0.5) * 2;
         }, {
            passive: true
         });
         hero.addEventListener('pointerleave', () => {
            mx = 0;
            my = 0;
         });

         if ('ResizeObserver' in window) new ResizeObserver(resize).observe(hero);
         else window.addEventListener('resize', resize);
         resize();

         if ('IntersectionObserver' in window) {
            new IntersectionObserver(([entry]) => (entry.isIntersecting ? start() : stop()), {
               threshold: 0
            }).observe(hero);
         } else {
            start();
         }
         document.addEventListener('visibilitychange', () => (document.hidden ? stop() : start()));
         reduceMotion.addEventListener('change', () => (reduceMotion.matches ? (stop(), draw(0)) : start()));

         draw(0);
         requestAnimationFrame(() => canvas.classList.add('is-ready'));
      })();
   </script>

</body>
</html>
