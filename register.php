<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';
$csrfToken = app_csrf_token();
require __DIR__ . '/includes/registration_handler.php';
$inputClass  = 'auth-field';
$pwdClass    = 'auth-field auth-field-pad';
$eyeBtnClass = 'auth-eye';
?>
<!DOCTYPE html>
<html lang="th">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <meta name="theme-color" content="#0A0A0A">
   <title>สร้างบัญชี | REPS</title>
   <link rel="icon" type="image/svg+xml" href="assets/images/logo/reps-icon.svg">
   <link rel="alternate icon" type="image/png" sizes="32x32" href="assets/images/logo/favicon-32.png">
   <link rel="apple-touch-icon" href="assets/images/logo/apple-touch-icon.png">

   <!-- Google Fonts: Google Sans, Kanit -->
   <link rel="preconnect" href="https://fonts.googleapis.com">
   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
   <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

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
         </a>

         <a href="login.php" class="auth-btn auth-btn-inline rounded-lg">เข้าสู่ระบบ</a>
      </div>
   </header>

   <!-- Main Content -->
   <main id="main" class="w-full relative z-10 flex-1 flex flex-col items-center justify-center px-4 py-12 sm:py-16">
      <div class="w-full max-w-md mx-auto flex flex-col items-center">

         <div class="auth-card">

            <div class="auth-card-title">
               <h1>สร้างบัญชี</h1>
               <p>กรอกข้อมูลเพื่อเริ่มใช้งานระบบแจ้งซ่อมออนไลน์</p>
            </div>

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
   <footer class="auth-footer">
      <div class="max-w-6xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
         <span>แผนกเทคโนโลยีธุรกิจดิจิทัล</span>
         <span>&copy; <?= date('Y'); ?> Developed by Digital Business Technology.</span>
      </div>
   </footer>

</body>

</html>
