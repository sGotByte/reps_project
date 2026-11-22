<?php
declare(strict_types=1);

/**
 * REPS · includes/icons.php — ไอคอน SVG แบบ inline (ใช้แทน emoji และอักษรสัญลักษณ์)
 * เส้นเดียวกันทั้งชุด: stroke 2, ปลายมน, ขนาดตาม font-size (1em) และสีตาม currentColor
 *
 *   echo app_icon('arrow-right');                 // ตกแต่ง (aria-hidden)
 *   echo app_icon('check', 'text-emerald-600', 'สำเร็จ'); // มีความหมาย (role="img" + aria-label)
 */

const APP_ICON_PATHS = [
    'arrow-left'  => '<path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>',
    'arrow-right' => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
    'check'       => '<path d="M20 6 9 17l-5-5"/>',
    'x'           => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
    'dot'         => '<circle cx="12" cy="12" r="5" fill="currentColor" stroke="none"/>',
    'dot-outline' => '<circle cx="12" cy="12" r="5"/>',
    'bell'        => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
    'code'        => '<path d="m16 18 6-6-6-6"/><path d="m8 6-6 6 6 6"/>',
];

function app_icon(string $name, string $class = '', ?string $label = null): string
{
    $paths = APP_ICON_PATHS[$name] ?? '';
    $a11y = $label !== null && $label !== ''
        ? 'role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '"'
        : 'aria-hidden="true" focusable="false"';
    return '<svg class="app-icon ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" ' . $a11y
        . ' xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="1em" height="1em" fill="none"'
        . ' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
        . $paths . '</svg>';
}

/** สคริปต์ JS ที่สร้าง SVG เดียวกัน สำหรับข้อความที่อัปเดตฝั่งเบราว์เซอร์ */
function app_icon_js(): string
{
    $map = json_encode(APP_ICON_PATHS, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    return <<<JS
<script>
window.repsIcon = function (name) {
   var paths = {$map};
   var ns = 'http://www.w3.org/2000/svg';
   var svg = document.createElementNS(ns, 'svg');
   svg.setAttribute('class', 'app-icon');
   svg.setAttribute('viewBox', '0 0 24 24');
   svg.setAttribute('width', '1em');
   svg.setAttribute('height', '1em');
   svg.setAttribute('fill', 'none');
   svg.setAttribute('stroke', 'currentColor');
   svg.setAttribute('stroke-width', '2');
   svg.setAttribute('stroke-linecap', 'round');
   svg.setAttribute('stroke-linejoin', 'round');
   svg.setAttribute('aria-hidden', 'true');
   svg.setAttribute('focusable', 'false');
   svg.innerHTML = paths[name] || '';
   return svg;
};
/* ใส่ไอคอน + ข้อความลงใน element (ใช้แทนการพิมพ์ ✓ / ✗ นำหน้าข้อความ) */
window.repsSetIconText = function (el, name, text) {
   if (!el) return;
   el.replaceChildren(window.repsIcon(name), document.createTextNode(' ' + text));
};
</script>
JS;
}
