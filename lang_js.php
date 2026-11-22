<?php
declare(strict_types=1);

/**
 * REPS · lang_js.php — ส่งพจนานุกรม lang/en.php ให้ assets/js/i18n-client.js
 * (ใช้แปลข้อความที่ JavaScript สร้างหลังโหลดหน้า) โหลดเฉพาะเมื่อผู้ใช้เลือก EN
 * ไม่มีข้อมูลผู้ใช้ จึง cache ได้ยาว — URL มี ?v=<เวลาแก้ไขไฟล์> ไว้ล้าง cache
 */

require_once __DIR__ . '/includes/i18n_output.php';

$file = __DIR__ . '/lang/en.php';
$raw = is_file($file) ? require $file : ['exact' => [], 'patterns' => []];

// regex ฝั่ง JS (โหมด u): escape เฉพาะอักขระพิเศษของ regex
$jsQuote = static fn(string $s): string => (string)preg_replace('/[.*+?^${}()|\[\]\\\\\/]/', '\\\\$0', $s);
$patterns = [];
foreach ($raw['patterns'] as $p) {
    $src = strtr($jsQuote($p[0]), ['\{n\}' => '(' . I18N_NUM . ')', '\{x\}' => '(.+?)']);
    $prefix = (string)preg_split('/\{[nx]\}/', $p[0], 2)[0];
    $patterns[] = ['^' . $src . '$', $p[1], !empty($p[2]) ? 1 : 0, mb_strlen($prefix) >= 2 ? mb_substr($prefix, 0, 2) : ''];
}

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: public, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');
echo 'window.REPS_I18N=' . json_encode(
    ['exact' => $raw['exact'], 'patterns' => $patterns, 'overrides' => I18N_CAPTURE_OVERRIDES],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
) . ';';
