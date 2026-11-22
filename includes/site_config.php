<?php
declare(strict_types=1);

/**
 * REPS · includes/site_config.php
 * โหลดการตั้งค่าเว็บไซต์ (ชื่อเว็บ, ธีมสี, กติกาคิว) จากฐานข้อมูล
 */

function app_get_settings(mysqli $conn): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $defaults = [
        'site_name'                 => 'REPS - ระบบแจ้งซ่อมอุปกรณ์คอมพิวเตอร์',
        'theme_color'               => 'default',
        'queue_timeout_minutes'     => '30',
        'queue_penalty_points'      => '5',
        'academic_year_start_month' => '5',
    ];

    $res = @$conn->query('SELECT setting_key, setting_value FROM system_settings');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $defaults[$row['setting_key']] = (string)($row['setting_value'] ?? '');
        }
        $res->free();
    }

    $cached = $defaults;
    return $cached;
}

function app_site_name(mysqli $conn): string
{
    return app_get_settings($conn)['site_name'] ?: 'REPS';
}

/** URL โลโก้: ไฟล์ที่ admin อัปโหลด (ผ่าน file.php) หรือโลโก้ตั้งต้น; $prefix เช่น '../' สำหรับหน้าในโฟลเดอร์ย่อย */
function app_logo_url(mysqli $conn, string $prefix = ''): string
{
    return trim((string)(app_get_settings($conn)['site_logo_path'] ?? '')) !== ''
        ? $prefix . 'file.php?s=logo'
        : $prefix . 'assets/images/logo/reps-logo.svg';
}

/** ชุดสีสำเร็จรูป (ใช้ทั้งตอนแสดงผลและในหน้าปรับแต่งเว็บ) */
const APP_THEME_PALETTES = [
    'default' => ['primary' => '#161616', 'hover' => '#2A2927', 'accent' => '#D97706'],
    'blue'    => ['primary' => '#1D4ED8', 'hover' => '#1E40AF', 'accent' => '#0284C7'],
    'emerald' => ['primary' => '#047857', 'hover' => '#065F46', 'accent' => '#10B981'],
    'rose'    => ['primary' => '#BE123C', 'hover' => '#9F1239', 'accent' => '#E11D48'],
    'amber'   => ['primary' => '#B45309', 'hover' => '#92400E', 'accent' => '#D97706'],
];

function app_theme_css(mysqli $conn): string
{
    return app_theme_css_from_settings(app_get_settings($conn));
}

function app_theme_css_from_settings(array $settings): string
{
    $theme = ($settings['theme_color'] ?? '') ?: 'default';
    $p = APP_THEME_PALETTES[$theme] ?? APP_THEME_PALETTES['default'];
    // สีหลักแบบกำหนดเอง (รับเฉพาะ hex #RRGGBB เพื่อกัน CSS injection)
    $custom = trim((string)($settings['primary_color'] ?? ''));
    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $custom) === 1) {
        $p['primary'] = $custom;
        $p['hover'] = $custom;
    }

    return "<style id=\"reps-dynamic-theme\">
        :root {
            --admin-ink: {$p['primary']};
            --ink: {$p['primary']};
            --theme-primary: {$p['primary']};
            --theme-hover: {$p['hover']};
            --theme-accent: {$p['accent']};
        }
        .admin-btn:not(.admin-btn-secondary):not(.admin-btn-danger-outline),
        .btn:not(.secondary) {
            background-color: {$p['primary']} !important;
            border-color: {$p['primary']} !important;
        }
        .admin-btn:not(.admin-btn-secondary):not(.admin-btn-danger-outline):hover,
        .btn:not(.secondary):hover {
            background-color: {$p['hover']} !important;
            border-color: {$p['hover']} !important;
        }
    </style>";
}