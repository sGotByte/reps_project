<?php

<<<<<<< HEAD
/**
 * ที่เก็บไฟล์ส่วนตัว (รูปแนบใบงาน รูปปิดงาน ลายเซ็น โลโก้ QR)
 * - ไฟล์ทุกชนิดเก็บใน <TICKET_UPLOAD_DIR>/tickets และอ้างอิงในฐานข้อมูลเป็น "ticket-private/<ชื่อไฟล์>"
 * - เปิดดูผ่าน file.php ที่ตรวจสิทธิ์เท่านั้น
 */

/** ข้อผิดพลาดที่แสดงข้อความให้ผู้ใช้เห็นได้ (ภาษาไทย ไม่มีรายละเอียดภายใน) */
class AppUserException extends RuntimeException
{
}

const APP_PRIVATE_FILE_PATTERN = '/^[A-Za-z0-9_-]+\.(?:jpe?g|png|webp)$/i';
const APP_IMAGE_MIME_EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

function app_path_is_absolute(string $path): bool
{
   return str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
}

=======
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
/** Return a persistent attachment directory outside the public document root. */
function app_ticket_upload_dir(): string
{
   $configured = trim((string)(getenv('TICKET_UPLOAD_DIR') ?: ''));
   $base = $configured !== '' ? $configured : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'reps_private_uploads';

   // Relative paths are resolved against the project root so shared hosts without shell access do not need absolute paths.
<<<<<<< HEAD
   if (!app_path_is_absolute($base)) {
      $base = dirname(__DIR__) . DIRECTORY_SEPARATOR . $base;
   }
   $base = rtrim($base, '/\\');
=======
   if (!str_starts_with($base, DIRECTORY_SEPARATOR)) {
      $base = dirname(__DIR__) . DIRECTORY_SEPARATOR . $base;
   }
   $base = rtrim($base, DIRECTORY_SEPARATOR);
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
   if ($base === '') {
      throw new RuntimeException('TICKET_UPLOAD_DIR is not a valid path');
   }

   $resolvedBase = realpath($base);
   if ($resolvedBase === false) {
      $parent = realpath(dirname($base));
      $resolvedBase = ($parent !== false ? $parent : dirname($base)) . DIRECTORY_SEPARATOR . basename($base);
   }
<<<<<<< HEAD
   $candidate = rtrim($resolvedBase, '/\\') . DIRECTORY_SEPARATOR . 'tickets';
   $documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
   if ($documentRoot !== false && $documentRoot !== '') {
      $normalizedRoot = rtrim($documentRoot, '/\\');
=======
   $candidate = rtrim($resolvedBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'tickets';
   $documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
   if ($documentRoot !== false) {
      $normalizedRoot = rtrim($documentRoot, DIRECTORY_SEPARATOR);
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
      if ($resolvedBase === $normalizedRoot || str_starts_with($resolvedBase, $normalizedRoot . DIRECTORY_SEPARATOR)) {
         // Shared hosting such as InfinityFree cannot write outside htdocs on the main domain.
         // In that case the directory must be explicitly allowed and protected by its own .htaccess.
         $allowInDocroot = filter_var((string)(getenv('TICKET_UPLOAD_ALLOW_IN_DOCROOT') ?: 'false'), FILTER_VALIDATE_BOOLEAN);
         if (!$allowInDocroot) {
            throw new RuntimeException('Ticket upload storage must be outside the document root');
         }
         $protector = $candidate . DIRECTORY_SEPARATOR . '.htaccess';
         $protection = is_file($protector) ? strtolower((string)file_get_contents($protector)) : '';
         if (preg_match('/require\s+all\s+denied/', $protection) !== 1) {
            throw new RuntimeException('TICKET_UPLOAD_DIR inside the document root must contain tickets/.htaccess with Require all denied');
         }
      }
   }
   return $candidate;
}
<<<<<<< HEAD

function app_ensure_upload_dir(): string
{
   $dir = app_ticket_upload_dir();
   if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
      throw new RuntimeException('Could not create upload directory');
   }
   return $dir;
}

/**
 * แปลง path ที่เก็บในฐานข้อมูลเป็น path จริงบนดิสก์ (รองรับ path รุ่นเก่า)
 * คืน null ถ้า path ไม่ถูกรูปแบบ
 */
function app_private_file_abs(?string $storedPath): ?string
{
   $storedPath = trim((string)$storedPath);
   if ($storedPath === '') {
      return null;
   }
   $candidates = [];
   if (preg_match('#^(?:ticket-private/)?([^/\\\\]+)$#', $storedPath, $m) === 1) {
      $candidates[] = app_ticket_upload_dir() . DIRECTORY_SEPARATOR . $m[1];
      $candidates[] = dirname(__DIR__) . '/uploads/tickets/' . $m[1];
   } elseif (preg_match('#^private_uploads/completions/([^/\\\\]+)$#', $storedPath, $m) === 1) {
      $candidates[] = dirname(__DIR__) . '/private_uploads/completions/' . $m[1];
   } else {
      return null;
   }
   if (preg_match(APP_PRIVATE_FILE_PATTERN, $m[1]) !== 1) {
      return null;
   }
   foreach ($candidates as $candidate) {
      if (is_file($candidate)) {
         return $candidate;
      }
   }
   return $candidates[0];
}

function app_delete_private_file(?string $storedPath): void
{
   $abs = app_private_file_abs($storedPath);
   if ($abs !== null && is_file($abs)) {
      @unlink($abs);
   }
}

/**
 * ตรวจและบันทึกรูปที่อัปโหลด: ตรวจ MIME จริง จำกัดขนาด ตั้งชื่อสุ่ม และย่อ/เข้ารหัสใหม่ด้วย GD (ตัด EXIF)
 * @return string path สำหรับเก็บในฐานข้อมูล เช่น ticket-private/tk_ab12.jpg
 */
function app_store_uploaded_image(array $file, string $prefix, int $maxBytes = 5242880, int $maxDimension = 1600): string
{
   $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
   if ($error !== UPLOAD_ERR_OK) {
      throw new AppUserException($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
         ? 'ไฟล์มีขนาดใหญ่เกินกว่าที่เซิร์ฟเวอร์รับได้'
         : 'อัปโหลดรูปไม่สำเร็จ กรุณาลองใหม่');
   }
   $tmp = (string)($file['tmp_name'] ?? '');
   if ($tmp === '' || !is_uploaded_file($tmp)) {
      throw new AppUserException('อัปโหลดรูปไม่สำเร็จ กรุณาลองใหม่');
   }
   $size = (int)($file['size'] ?? 0);
   if ($size <= 0 || $size > $maxBytes) {
      throw new AppUserException('ขนาดไฟล์ต้องไม่เกิน ' . round($maxBytes / 1048576, 1) . ' MB');
   }
   $finfo = new finfo(FILEINFO_MIME_TYPE);
   $mime = (string)$finfo->file($tmp);
   if (!isset(APP_IMAGE_MIME_EXTENSIONS[$mime]) || @getimagesize($tmp) === false) {
      throw new AppUserException('ไฟล์ต้องเป็นรูปภาพ JPG, PNG หรือ WEBP เท่านั้น');
   }

   $dir = app_ensure_upload_dir();
   $prefix = preg_replace('/[^a-z0-9]/i', '', $prefix) ?: 'f';

   $reencoded = app_reencode_image($tmp, $mime, $maxDimension);
   if ($reencoded !== null) {
      [$binary, $ext] = $reencoded;
      $name = $prefix . '_' . bin2hex(random_bytes(16)) . '.' . $ext;
      if (file_put_contents($dir . DIRECTORY_SEPARATOR . $name, $binary, LOCK_EX) === false) {
         throw new RuntimeException('Could not write uploaded image');
      }
   } else {
      $name = $prefix . '_' . bin2hex(random_bytes(16)) . '.' . APP_IMAGE_MIME_EXTENSIONS[$mime];
      if (!move_uploaded_file($tmp, $dir . DIRECTORY_SEPARATOR . $name)) {
         throw new RuntimeException('Could not move uploaded image');
      }
   }
   @chmod($dir . DIRECTORY_SEPARATOR . $name, 0600);
   return 'ticket-private/' . $name;
}

/**
 * ย่อรูปให้ด้านยาวสุดไม่เกิน $maxDimension และเข้ารหัสใหม่ (คืน null ถ้าไม่มี GD)
 * @return array{0:string,1:string}|null [binary, extension]
 */
function app_reencode_image(string $path, string $mime, int $maxDimension): ?array
{
   if (!extension_loaded('gd')) {
      return null;
   }
   $source = match ($mime) {
      'image/jpeg' => @imagecreatefromjpeg($path),
      'image/png'  => @imagecreatefrompng($path),
      'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
      default      => false,
   };
   if (!$source) {
      return null;
   }
   $width = imagesx($source);
   $height = imagesy($source);
   $scale = min(1, $maxDimension / max($width, $height, 1));
   $newWidth = max(1, (int)round($width * $scale));
   $newHeight = max(1, (int)round($height * $scale));

   $canvas = imagecreatetruecolor($newWidth, $newHeight);
   $keepAlpha = $mime !== 'image/jpeg';
   if ($keepAlpha) {
      imagealphablending($canvas, false);
      imagesavealpha($canvas, true);
      imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
   }
   imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
   imagedestroy($source);

   ob_start();
   if ($mime === 'image/png') {
      imagepng($canvas, null, 6);
      $ext = 'png';
   } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
      imagewebp($canvas, null, 82);
      $ext = 'webp';
   } else {
      imagejpeg($canvas, null, 82);
      $ext = 'jpg';
   }
   $binary = (string)ob_get_clean();
   imagedestroy($canvas);
   return $binary !== '' ? [$binary, $ext] : null;
}

/** บันทึกลายเซ็นจาก canvas (data:image/png;base64,...) */
function app_store_signature_data_url(string $dataUrl, int $maxBytes = 512000): string
{
   if (preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m) !== 1) {
      throw new AppUserException('ไม่พบลายเซ็น กรุณาเซ็นในกรอบก่อนยืนยัน');
   }
   $binary = base64_decode($m[1], true);
   if ($binary === false || strlen($binary) < 100 || strlen($binary) > $maxBytes) {
      throw new AppUserException('ลายเซ็นไม่ถูกต้อง กรุณาเซ็นใหม่');
   }
   $info = @getimagesizefromstring($binary);
   if ($info === false || ($info['mime'] ?? '') !== 'image/png' || $info[0] > 2000 || $info[1] > 1000) {
      throw new AppUserException('ลายเซ็นไม่ถูกต้อง กรุณาเซ็นใหม่');
   }
   $dir = app_ensure_upload_dir();
   $name = 'sig_' . bin2hex(random_bytes(16)) . '.png';
   if (file_put_contents($dir . DIRECTORY_SEPARATOR . $name, $binary, LOCK_EX) === false) {
      throw new RuntimeException('Could not write signature');
   }
   @chmod($dir . DIRECTORY_SEPARATOR . $name, 0600);
   return 'ticket-private/' . $name;
}

/** ส่งไฟล์รูปให้เบราว์เซอร์ (ตรวจ MIME อีกรอบ) แล้วจบการทำงาน */
function app_serve_private_image(?string $absPath, bool $publicCache = false, ?string $cacheControl = null): never
{
   if ($absPath === null || !is_file($absPath) || !is_readable($absPath)) {
      http_response_code(404);
      exit('ไม่พบไฟล์');
   }
   $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($absPath);
   if (!isset(APP_IMAGE_MIME_EXTENSIONS[$mime])) {
      http_response_code(404);
      exit('ไม่พบไฟล์');
   }
   header('Content-Type: ' . $mime);
   header('Content-Length: ' . filesize($absPath));
   header('Content-Disposition: inline; filename="' . basename($absPath) . '"');
   header('X-Content-Type-Options: nosniff');
   header('Cache-Control: ' . ($cacheControl ?? ($publicCache ? 'public, max-age=3600' : 'private, no-store, max-age=0')));
   header_remove('Pragma');
   readfile($absPath);
   exit;
}
=======
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
