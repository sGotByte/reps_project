<?php

/**
 * db_connect.php
 * ไฟล์สำหรับเชื่อมต่อฐานข้อมูล MySQL และเริ่มต้น Session
 */

$envCandidates = [
    dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env',
    __DIR__ . DIRECTORY_SEPARATOR . '.env',
];
$GLOBALS['APP_ENV_FILE'] = '';
foreach ($envCandidates as $localEnvFile) {
    if (!is_file($localEnvFile) || !is_readable($localEnvFile)) {
        continue;
    }

    $GLOBALS['APP_ENV_FILE'] = $localEnvFile;
    foreach (file($localEnvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $envLine) {
        $envLine = trim($envLine);
        if ($envLine === '' || str_starts_with($envLine, '#') || !str_contains($envLine, '=')) {
            continue;
        }

        [$envKey, $envValue] = explode('=', $envLine, 2);
        $envKey = trim($envKey);
        $envValue = trim($envValue);
        $existingValue = $envKey !== '' ? getenv($envKey) : false;
        if ($envKey !== '' && ($existingValue === false || $existingValue === '')) {
            if (strlen($envValue) >= 2 && (($envValue[0] === '"' && str_ends_with($envValue, '"')) || ($envValue[0] === "'" && str_ends_with($envValue, "'")))) {
                $envValue = substr($envValue, 1, -1);
            }
            putenv($envKey . '=' . $envValue);
            $_ENV[$envKey] = $envValue;
        }
    }
    break;
}

$displayErrors = filter_var(getenv('APP_DISPLAY_ERRORS') ?: 'false', FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $displayErrors ? '1' : '0');
ini_set('log_errors', '1');

// หน้าสาธารณะ (เช่น index.php สำหรับผู้เยี่ยมชม) ตั้ง $APP_SKIP_SESSION = true เพื่อไม่สร้าง session ใหม่
if (session_status() === PHP_SESSION_NONE && empty($GLOBALS['APP_SKIP_SESSION'])) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_lifetime', '604800');
    ini_set('session.gc_maxlifetime', '604800');
    if (isset($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) === 'on') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

// แปล UI เป็นภาษาอังกฤษตอนแสดงผลเมื่อผู้ใช้เลือก EN (ไม่ทำอะไรเลยเมื่อเป็นภาษาไทย)
require_once __DIR__ . '/includes/i18n_output.php';
i18n_boot();

$appTimezone = trim((string)(getenv('APP_TIMEZONE') ?: 'Asia/Bangkok'));
if (!in_array($appTimezone, timezone_identifiers_list(), true)) {
    error_log('Invalid APP_TIMEZONE, falling back to Asia/Bangkok');
    $appTimezone = 'Asia/Bangkok';
}
date_default_timezone_set($appTimezone);

function env_value(string $key): string {
    $v = getenv($key);
    if ($v === false || $v === '') {
        $v = $_ENV[$key] ?? '';
    }
    return (string)$v;
}

$host   = env_value('DB_HOST');
$port   = filter_var(env_value('DB_PORT') ?: '3306', FILTER_VALIDATE_INT);
$user   = env_value('DB_USER');
$pass   = env_value('DB_PASSWORD');
$dbname = env_value('DB_NAME');

$missingConfig = [];
foreach (['DB_HOST' => $host, 'DB_PORT' => $port, 'DB_USER' => $user, 'DB_PASSWORD' => $pass, 'DB_NAME' => $dbname] as $key => $value) {
    if ($value === '' || $value === false || $value === null) {
        $missingConfig[] = $key;
    }
}

if ($missingConfig) {
    http_response_code(500);
    error_log('Missing database configuration keys: ' . implode(', ', $missingConfig));
    die('Database configuration is missing: ' . implode(', ', $missingConfig));
}

mysqli_report(MYSQLI_REPORT_OFF);

$conn = @new mysqli($host, $user, $pass, $dbname, $port);

if ($conn->connect_error) {
    http_response_code(500);
    error_log('Database connection failed: ' . $conn->connect_error);
    die('ขออภัย! ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาลองใหม่ภายหลัง');
}

if (!$conn->set_charset("utf8mb4")) {
    http_response_code(500);
    die("ขออภัย! ไม่สามารถตั้งค่าการเชื่อมต่อฐานข้อมูลเป็น utf8mb4 ได้");
}

$dbTimezoneOffset = (new DateTimeImmutable('now', new DateTimeZone($appTimezone)))->format('P');
if (!$conn->query("SET time_zone = '" . $conn->real_escape_string($dbTimezoneOffset) . "'")) {
    error_log('Could not set MySQL session time zone: ' . $conn->error);
}

// การปรับแต่งเว็บจากหน้า admin (ธีม ฟอนต์ โลโก้ favicon ข้อความ) — ไม่มีการปรับแต่ง = ไม่ทำอะไร
require_once __DIR__ . '/includes/site_custom.php';
site_custom_boot($conn);