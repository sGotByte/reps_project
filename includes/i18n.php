<?php
declare(strict_types=1);

/**
 * REPS · includes/i18n.php — สลับภาษา TH/EN
 *
 *   tr('แดชบอร์ด', 'Dashboard')         // คืนข้อความตามภาษาที่เลือก
 *   echo lang_switch_html('../');        // ปุ่มสลับ TH | EN (ลิงก์ไป lang.php แล้วกลับหน้าเดิม)
 *
 * ภาษาเก็บใน cookie `reps_lang` (th|en, ค่าเริ่มต้น th)
 * tr() ใช้กับข้อความที่ประกอบใน PHP; ข้อความ UI ที่เหลือทั้งหน้าแปลตอนแสดงผลโดย includes/i18n_output.php + lang/en.php
 * ข้อมูลที่ผู้ใช้กรอก (ชื่อ สถานที่ อาการ ฯลฯ) ไม่ถูกแปล
 */

const APP_LANGS = ['th', 'en'];

function app_lang(): string
{
    static $lang = null;
    if ($lang === null) {
        $c = $_COOKIE['reps_lang'] ?? 'th';
        $lang = is_string($c) && in_array($c, APP_LANGS, true) ? $c : 'th';
    }
    return $lang;
}

function tr(string $th, string $en): string
{
    if (app_lang() !== 'en') {
        return $th; // ข้อความไทยที่ admin แก้ จะถูกแทนตอนแสดงผล (includes/site_custom.php)
    }
    // ข้อความอังกฤษที่ admin แก้ไว้ (คีย์คือข้อความไทยเดิม) — ตั้งโดย site_custom_boot()
    if (function_exists('i18n_overrides')) {
        $overrides = i18n_overrides();
        if (isset($overrides[$th])) {
            return $overrides[$th];
        }
    }
    return $en;
}

function lang_switch_html(string $base = ''): string
{
    $GLOBALS['repsLangSwitchShown'] = true;   // footer จะไม่แสดงปุ่มซ้ำ ถ้า navbar มีแล้ว
    $h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $back = (string)($_SERVER['REQUEST_URI'] ?? '');
    $cur = app_lang();
    $out = '<div class="lang-switch" role="group" aria-label="' . $h(tr('ภาษาของเมนู', 'Menu language')) . '">';
    foreach (['th' => ['TH', 'ภาษาไทย'], 'en' => ['EN', 'English']] as $code => [$short, $name]) {
        if ($code === $cur) {
            $out .= '<span class="lang-switch__opt is-current" aria-current="true" title="' . $h($name) . '">' . $short . '</span>';
        } else {
            $out .= '<a class="lang-switch__opt" href="' . $h($base . 'lang.php?set=' . $code . '&back=' . rawurlencode($back)) . '" hreflang="' . $code . '" title="' . $h($name) . '" rel="nofollow">' . $short . '</a>';
        }
    }
    return $out . '</div>';
}
