<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';
<<<<<<< HEAD
require_once __DIR__ . '/includes/site_config.php';
$csrfToken = app_csrf_token();
require __DIR__ . '/includes/registration_handler.php';
$siteName = function_exists('app_site_name') ? app_site_name($conn) : 'REPS';
=======
$csrfToken = app_csrf_token();
require __DIR__ . '/includes/registration_handler.php';
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
$inputClass  = 'auth-field';
$pwdClass    = 'auth-field auth-field-pad';
$eyeBtnClass = 'auth-eye';
?>
<!DOCTYPE html>
<html lang="th">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
<<<<<<< HEAD
   <meta name="theme-color" content="#FFFFFF">
   <title>สร้างบัญชี | <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></title>
=======
   <meta name="theme-color" content="#0A0A0A">
   <title>สร้างบัญชี | REPS</title>
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
   <link rel="icon" type="image/svg+xml" href="assets/images/logo/reps-icon.svg">
   <link rel="alternate icon" type="image/png" sizes="32x32" href="assets/images/logo/favicon-32.png">
   <link rel="apple-touch-icon" href="assets/images/logo/apple-touch-icon.png">

<<<<<<< HEAD
   <!-- Google Fonts: Kanit -->
   <link rel="preconnect" href="https://fonts.googleapis.com">
   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
   <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

   <link rel="stylesheet" href="assets/css/app.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/app.css') ?>">
   <link rel="stylesheet" href="assets/css/auth.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/auth.css') ?>">
   <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
   <link rel="stylesheet" href="assets/css/base.css?v=18">
=======
   <!-- Google Fonts: Google Sans, Kanit -->
   <link rel="preconnect" href="https://fonts.googleapis.com">
   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
   <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff

   <style>
      .form-row-2 {
         display: grid;
         grid-template-columns: repeat(2, minmax(0, 1fr));
         gap: 12px;
      }

      @media (max-width: 480px) {
         .form-row-2 {
            grid-template-columns: 1fr;
         }
      }
<<<<<<< HEAD

      /* Light Theme (No Grid / No Dashed Rails) */
      body.auth-light {
         background-color: #FAFAFA;
         color: #111827;
      }
      body.auth-light .auth-header {
         background-color: rgba(255, 255, 255, 0.9);
         backdrop-filter: blur(8px);
         border-bottom: 1px solid #E5E7EB;
      }
      body.auth-light .auth-card {
         background-color: #FFFFFF;
         border: 1px solid #E5E7EB;
         border-radius: 16px;
         box-shadow: 0 4px 24px -4px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.03);
         padding: 2rem;
         width: 100%;
      }
      body.auth-light .auth-card-title h1 {
         color: #111827;
      }
      body.auth-light .auth-card-title p {
         color: #6B7280;
      }
      body.auth-light .auth-label,
      body.auth-light label {
         color: #374151;
      }
      body.auth-light .auth-field,
      body.auth-light select {
         background-color: #FFFFFF;
         border: 1px solid #D1D5DB;
         color: #111827;
         border-radius: 10px;
         transition: border-color 0.15s ease, box-shadow 0.15s ease;
      }
      /* Dropdown เมนูขอบมน */
      body.auth-light select {
         appearance: none;
         -webkit-appearance: none;
         width: 100%;
         padding: 0.625rem 2.25rem 0.625rem 0.875rem;
         border-radius: 10px;
         background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%234B5563' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
         background-repeat: no-repeat;
         background-position: right 0.75rem center;
         background-size: 1rem;
         cursor: pointer;
      }
      body.auth-light .auth-field::placeholder {
         color: #9CA3AF;
      }
      body.auth-light .auth-field:focus,
      body.auth-light select:focus {
         border-color: #111827;
         box-shadow: 0 0 0 3px rgba(17, 24, 39, 0.08);
         outline: none;
      }
      body.auth-light .auth-eye {
         color: #6B7280;
      }
      body.auth-light .auth-eye:hover {
         color: #111827;
      }
      body.auth-light .auth-btn-primary {
         background-color: #111827;
         color: #FFFFFF;
         border: 1px solid #111827;
         border-radius: 10px;
      }
      body.auth-light .auth-btn-primary:hover {
         background-color: #1F2937;
      }
      body.auth-light .auth-btn-inline {
         background-color: #FFFFFF;
         color: #111827;
         border: 1px solid #D1D5DB;
         border-radius: 10px;
      }
      body.auth-light .auth-btn-inline:hover {
         background-color: #F3F4F6;
      }
      body.auth-light .auth-alt {
         color: #6B7280;
         border-top: 1px solid #F3F4F6;
      }
      body.auth-light .auth-alt a {
         color: #111827;
         font-weight: 500;
      }

      /* New Clean Alert Design */
      .auth-alert-new.hidden,
      body.auth-light .auth-alert.hidden {
         display: none !important;
      }
      .auth-alert-new,
      body.auth-light .auth-alert {
         display: flex;
         align-items: flex-start;
         gap: 12px;
         padding: 13px 15px;
         border-radius: 12px;
         font-size: 0.875rem;
         line-height: 1.55;
         margin-bottom: 1.25rem;
         border: 1px solid transparent;
         box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
      }
      body.auth-light .auth-alert-error:empty {
         display: none !important;
      }
      .auth-alert-new .alert-icon {
         display: flex;
         align-items: center;
         justify-content: center;
         width: 24px;
         height: 24px;
         border-radius: 9999px;
         flex-shrink: 0;
         margin-top: 1px;
         font-size: 0.8rem;
      }
      .auth-alert-new .alert-content {
         flex: 1;
      }
      .auth-alert-new .alert-title {
         display: block;
         font-weight: 600;
         margin-bottom: 2px;
         font-size: 0.9rem;
      }

      .auth-alert-new.is-info,
      body.auth-light .auth-alert-info {
         background-color: #FFFBEB;
         border-color: #FDE68A;
         color: #92400E;
      }
      .auth-alert-new.is-info .alert-icon {
         background-color: #FEF3C7;
         color: #B45309;
      }

      body.auth-light .auth-alert-error {
         background-color: #FEF2F2;
         border-color: #FECACA;
      
         color: #991B1B;
      }
   </style>
</head>

<body class="auth-light antialiased min-h-screen flex flex-col justify-between relative overflow-x-hidden font-sans">

   <!-- Header -->
   <header class="auth-header sticky top-0 z-20">
      <div class="auth-header-inner">
         <a class="auth-logo-link focus:outline-none" href="index.php" aria-label="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?> หน้าหลัก">
            <img src="<?= htmlspecialchars(app_logo_url($conn), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>" class="auth-logo">
=======
   </style>

   <link rel="stylesheet" href="assets/css/app.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/app.css') ?>">
   <link rel="stylesheet" href="assets/css/auth.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/auth.css') ?>">
   <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
</head>

<body class="auth-dark antialiased min-h-screen flex flex-col justify-between relative overflow-x-hidden font-sans">

   <!-- เส้นประแนวตั้งสองเส้นวิ่งตลอดหน้า + กริดพื้นหลังบาง ๆ -->
   <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden auth-fade-mask" aria-hidden="true">
      <div class="absolute inset-0 auth-grid"></div>
   </div>
   <div class="auth-rails" aria-hidden="true"></div>

   <!-- Header -->
   <header class="auth-header">
      <div class="max-w-6xl mx-auto h-16 px-6 flex items-center justify-between">
         <a class="flex items-center gap-3 focus:outline-none" href="index.php" aria-label="REPS หน้าหลัก">
            <img src="assets/images/logo/reps-logo.svg" alt="REPS" class="auth-logo h-6 sm:h-7 w-auto">
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
         </a>

         <a href="login.php" class="auth-btn auth-btn-inline rounded-lg">เข้าสู่ระบบ</a>
      </div>
   </header>

   <!-- Main Content -->
<<<<<<< HEAD
   <main id="main" class="w-full relative z-10 flex-1 flex flex-col items-center justify-center px-6 py-12 sm:py-16">
=======
   <main id="main" class="w-full relative z-10 flex-1 flex flex-col items-center justify-center px-4 py-12 sm:py-16">
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
      <div class="w-full max-w-md mx-auto flex flex-col items-center">

         <div class="auth-card">

            <div class="auth-card-title">
               <h1>สร้างบัญชี</h1>
               <p>กรอกข้อมูลเพื่อเริ่มใช้งานระบบแจ้งซ่อมออนไลน์</p>
            </div>

<<<<<<< HEAD
            <div class="auth-alert-new is-info" role="note">
               <div class="alert-icon"><i class="fa-solid fa-user-clock" aria-hidden="true"></i></div>
               <div class="alert-content">
                  <span class="alert-title">ขั้นตอนการอนุมัติบัญชี</span>
                  <span>บัญชีใหม่ต้องรอครูผู้ดูแลระบบอนุมัติก่อน จึงจะเข้าสู่ระบบและแจ้งซ่อมได้ หากอุปกรณ์เสียเร่งด่วนสามารถแจ้งเจ้าหน้าที่ห้องปฏิบัติการคอมพิวเตอร์ได้โดยตรง</span>
               </div>
            </div>

=======
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
            <form action="register.php" method="POST" id="registerForm" class="space-y-4" novalidate>
               <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
               <?php require __DIR__ . '/includes/registration_fields.php'; ?>

               <button type="submit" id="submitBtn" class="auth-btn auth-btn-primary rounded-lg">
                  <span data-registration-submit-label>สร้างบัญชี</span>
                  <i class="fa-solid fa-arrow-right text-[13px]" aria-hidden="true"></i>
               </button>
            </form>

            <p class="auth-alt">
               มีบัญชีอยู่แล้ว?
               <a href="login.php">เข้าสู่ระบบ</a>
            </p>
         </div>
      </div>
   </main>

   <!-- Footer -->
<<<<<<< HEAD
   <?php $footerBase = ''; $footerVariant = 'light'; require __DIR__ . '/includes/site_footer.php'; ?>

</body>

</html>
=======
   <footer class="auth-footer">
      <div class="max-w-6xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
         <span>แผนกเทคโนโลยีธุรกิจดิจิทัล</span>
         <span>&copy; <?= date('Y'); ?> Developed by Digital Business Technology.</span>
      </div>
   </footer>

</body>

</html>
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
