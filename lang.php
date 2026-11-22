<?php
declare(strict_types=1);

/**
 * REPS · lang.php — ตั้งภาษาเมนู (cookie) แล้วกลับหน้าเดิม
 *   ?set=th|en&back=<path ภายในเว็บ>
 */

require_once __DIR__ . '/includes/i18n.php';

$set = is_string($_GET['set'] ?? null) ? $_GET['set'] : '';
if (in_array($set, APP_LANGS, true)) {
    setcookie('reps_lang', $set, [
        'expires'  => time() + 365 * 86400,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// กลับได้เฉพาะ path ภายในเว็บ (กัน open redirect: ต้องขึ้นต้นด้วย / ตัวเดียว ไม่มี \ และไม่มีอักขระควบคุม)
$back = is_string($_GET['back'] ?? null) ? $_GET['back'] : '';
if ($back === '' || $back[0] !== '/' || str_starts_with($back, '//') || str_contains($back, chr(92)) || preg_match('/[[:cntrl:]]/', $back)) {
    $back = 'index.php';
}
header('Cache-Control: no-store');
header('Location: ' . $back, true, 303);
exit;
