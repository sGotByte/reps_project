<?php
declare(strict_types=1);

/**
 * REPS · includes/i18n_output.php — แปลข้อความ UI ทั้งหน้าเป็นภาษาอังกฤษตอนแสดงผล
 *
 * ทำงานเฉพาะเมื่อผู้ใช้เลือก EN (cookie reps_lang) และ response เป็น HTML:
 *   - แปล text node และ attribute ที่ผู้ใช้เห็น (placeholder, title, aria-label, alt ฯลฯ)
 *   - แปลเฉพาะข้อความที่ "ตรงทั้งก้อน" กับพจนานุกรม lang/en.php หรือตรงรูปแบบที่มี {n} (ตัวเลข) / {x} (ข้อมูล)
 *     → ข้อมูลจากฐานข้อมูล (ชื่อ สถานที่ อาการ ชื่อวัสดุ แผนก ฯลฯ) ไม่ตรงพจนานุกรมจึงคงเดิม
 *   - ไม่แตะ <script>, <style>, เนื้อหาใน <textarea>, ค่า value ของฟอร์ม, PDF, รูป และ JSON
 *
 * เพิ่มคำแปล: แก้ lang/en.php ('exact' = ข้อความคงที่, 'patterns' = ข้อความที่มีข้อมูลปน)
 * ข้อความที่ประกอบใน PHP แบบซับซ้อนให้ใช้ tr() จาก includes/i18n.php แทน
 */

require_once __DIR__ . '/i18n.php';

const I18N_ATTRS = 'placeholder|title|aria-label|alt|data-confirm|data-label|data-empty|label';
const I18N_NUM = '\d+(?:[.,:\/]\d+)*';
const I18N_HOLD = "\u{E000}";   // อักขระ private-use ใช้คั่นส่วนที่ถอดเก็บไว้ ไม่ชนกับข้อความจริง

/** คำที่อยู่ในตำแหน่ง {x} แล้วหมายถึง "สถานะ" (ต่างจากความหมายตอนเป็นปุ่ม) */
const I18N_CAPTURE_OVERRIDES = [
    'ยกเลิก'   => 'Cancelled',
    'เสร็จสิ้น' => 'Completed',
    'เริ่มต้น'  => 'Created',
];

/** เรียกครั้งเดียวตอนเริ่ม request (จาก db_connect.php) */
function i18n_boot(): void
{
    static $booted = false;
    if ($booted || PHP_SAPI === 'cli' || app_lang() !== 'en') {
        return;
    }
    $booted = true;
    ob_start('i18n_translate_response');
}

function i18n_translate_response(string $buffer): string
{
    if ($buffer === '') {
        return $buffer;
    }
    foreach (headers_list() as $header) {
        if (stripos($header, 'Content-Type:') === 0 && stripos($header, 'text/html') === false) {
            return $buffer; // PDF / รูป / JSON
        }
    }
    try {
        return i18n_translate_html($buffer);
    } catch (Throwable $e) {
        error_log('i18n_translate_response: ' . $e->getMessage());
        return $buffer;
    }
}

/** @return array{exact: array<string,string>, buckets: array<string,list<array{0:string,1:string,2:bool}>>, general: list<array{0:string,1:string,2:bool}>} */
function i18n_dictionary(): array
{
    static $dict = null;
    if ($dict !== null) {
        return $dict;
    }
    $dict = ['exact' => [], 'buckets' => [], 'general' => []];
    $file = dirname(__DIR__) . '/lang/en.php';
    if (!is_file($file)) {
        return $dict;
    }
    $raw = require $file;
    $dict['exact'] = $raw['exact'] ?? [];
    foreach ($raw['patterns'] ?? [] as $p) {
        [$th, $en] = $p;
        $attrOnly = (bool)($p[2] ?? false);
        // ตัวอักษรนำหน้าก่อน token แรก ใช้คัดกรองเร็ว ๆ ก่อนรัน regex
        $prefix = (string)preg_split('/\{[nx]\}/', $th, 2)[0];
        $regex = '/^' . strtr(preg_quote($th, '/'), ['\{n\}' => '(' . I18N_NUM . ')', '\{x\}' => '(.+?)']) . '$/u';
        $entry = [$regex, $en, $attrOnly];
        if (mb_strlen($prefix) >= 2) {
            $dict['buckets'][mb_substr($prefix, 0, 2)][] = $entry;
        } else {
            $dict['general'][] = $entry;
        }
    }
    return $dict;
}

/**
 * ข้อความที่ admin แก้เองในหน้าปรับแต่งเว็บ (includes/site_custom.php ตั้งค่าให้) — ตรวจก่อนพจนานุกรมเสมอ
 * @param array<string,string>|null $set ข้อความเดิม => ข้อความใหม่ของภาษาปัจจุบัน
 */
function i18n_overrides(?array $set = null): array
{
    static $map = [];
    if ($set !== null) {
        $map = $set;
    }
    return $map;
}

/** แปลข้อความหนึ่งก้อน (ถอด entity แล้ว) — คืน null ถ้าไม่มีคำแปล · $useDictionary = false ใช้เฉพาะข้อความที่ admin แก้ */
function i18n_lookup(string $text, bool $isAttr, int $depth = 0, bool $useDictionary = true): ?string
{
    $overrides = i18n_overrides();
    if ($depth === 0 && isset($overrides[$text])) {
        return $overrides[$text];
    }
    if (!$useDictionary) {
        return null;
    }
    $dict = i18n_dictionary();
    if (isset($dict['exact'][$text])) {
        return $dict['exact'][$text];
    }
    $candidates = array_merge($dict['buckets'][mb_substr($text, 0, 2)] ?? [], $dict['general']);
    foreach ($candidates as [$regex, $en, $attrOnly]) {
        if ($attrOnly && !$isAttr) {
            continue;
        }
        if (preg_match($regex, $text, $m) !== 1) {
            continue;
        }
        $i = 0;
        return (string)preg_replace_callback('/\{[nx]\}/', static function (array $tok) use ($m, &$i, $isAttr, $depth): string {
            $value = $m[++$i] ?? '';
            if ($tok[0] === '{n}' || $depth > 0) {
                return $value;
            }
            // ส่วนที่จับได้อาจเป็นคำของระบบเอง (เช่น ชื่อสถานะ วันที่) — แปลซ้อนได้หนึ่งชั้น ถ้าไม่ตรงก็คงข้อมูลเดิม
            return I18N_CAPTURE_OVERRIDES[$value] ?? i18n_lookup($value, $isAttr, $depth + 1) ?? $value;
        }, $en);
    }
    return null;
}

function i18n_translate_fragment(string $raw, bool $isAttr, bool $useDictionary = true): string
{
    if (preg_match('/[\x{0E01}-\x{0E5B}]/u', $raw) !== 1) {
        return $raw;
    }
    if (preg_match('/^(\s*)(.*?)(\s*)$/su', $raw, $parts) !== 1) {
        return $raw;
    }
    $text = html_entity_decode($parts[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim((string)preg_replace('/\s+/u', ' ', $text));
    if ($text === '') {
        return $raw;
    }
    $translated = i18n_lookup($text, $isAttr, 0, $useDictionary);
    if ($translated === null) {
        return $raw;
    }
    return $parts[1] . htmlspecialchars($translated, ENT_QUOTES, 'UTF-8') . $parts[3];
}

/** $useDictionary = false: แทนเฉพาะข้อความที่ admin แก้ (โหมดภาษาไทย) ไม่แปลและไม่ใส่สคริปต์แปลฝั่งเบราว์เซอร์ */
function i18n_translate_html(string $html, bool $useDictionary = true): string
{
    if ($useDictionary ? !i18n_dictionary()['exact'] && !i18n_overrides() : !i18n_overrides()) {
        return $html;
    }
    // เนื้อหาใน <textarea> เป็นข้อมูลที่ผู้ใช้พิมพ์: ถอดเก็บไว้ก่อน (แต่ยังแปล placeholder ของแท็กได้)
    $held = [];
    $html = preg_replace_callback('#(<textarea\b[^>]*>)(.*?)(</textarea>)#is', static function (array $m) use (&$held): string {
        $held[] = $m[2];
        return $m[1] . I18N_HOLD . (count($held) - 1) . I18N_HOLD . $m[3];
    }, $html) ?? $html;

    // ส่วนที่ห้ามแตะ: สคริปต์ สไตล์ และคอมเมนต์
    $parts = preg_split('#(<script\b.*?</script>|<style\b.*?</style>|<!--.*?-->)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return $html;
    }
    foreach ($parts as $i => $part) {
        if ($i % 2 === 1 || $part === '') {
            continue;
        }
        $part = preg_replace_callback(
            '/\b(' . I18N_ATTRS . ')="([^"]*)"/u',
            static fn(array $m): string => $m[1] . '="' . i18n_translate_fragment($m[2], true, $useDictionary) . '"',
            $part
        ) ?? $part;
        $part = preg_replace_callback(
            '/>([^<>]+)(?=<)/u',
            static fn(array $m): string => '>' . i18n_translate_fragment($m[1], false, $useDictionary),
            $part
        ) ?? $part;
        $parts[$i] = $part;
    }
    $html = implode('', $parts);

    if ($held) {
        $html = preg_replace_callback(
            '/' . I18N_HOLD . '(\d+)' . I18N_HOLD . '/u',
            static fn(array $m): string => $held[(int)$m[1]] ?? '',
            $html
        ) ?? $html;
    }
    if (!$useDictionary) {
        return $html;
    }
    $html = (string)preg_replace('/<html\s+lang="th"/i', '<html lang="en"', $html, 1);

    // สคริปต์แปลฝั่งเบราว์เซอร์ (ข้อความที่ JS สร้างทีหลัง, confirm(), ป้ายกราฟ) — ต้องอยู่ใน <head> ก่อน Chart.js
    $pos = stripos($html, '</head>');
    if ($pos !== false) {
        $root = dirname(__DIR__);
        $dir = basename(dirname(str_replace(chr(92), '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''))));
        $base = in_array($dir, ['admin', 'staff'], true) ? '../' : '';
        $tags = '<script src="' . $base . 'lang_js.php?v=' . (int)@filemtime($root . '/lang/en.php') . '"></script>'
            . '<script src="' . $base . 'assets/js/i18n-client.js?v=' . (int)@filemtime($root . '/assets/js/i18n-client.js') . '"></script>';
        $html = substr($html, 0, $pos) . $tags . "
" . substr($html, $pos);
    }
    return $html;
}
