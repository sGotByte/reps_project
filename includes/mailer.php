<?php
declare(strict_types=1);

/**
 * REPS · includes/mailer.php — จุดส่งอีเมลกลางของทั้งระบบ (v3 หัวข้อ 5)
 *
 * เลือกช่องทางส่งจาก environment:
 *   MAIL_TRANSPORT=smtp   Gmail SMTP (STARTTLS 587) ใช้ MAIL_USERNAME / MAIL_PASSWORD (App Password) / MAIL_FROM
 *   MAIL_TRANSPORT=brevo  Brevo Transactional Email ผ่าน HTTPS API ใช้ BREVO_API_KEY / MAIL_FROM (+ MAIL_FROM_NAME)
 *   ไม่ตั้ง MAIL_TRANSPORT: ใช้ brevo ถ้ามี BREVO_API_KEY ไม่งั้นใช้ smtp
 *
 * โฮสต์ฟรีบางเจ้าปิดการเชื่อมต่อออกไปพอร์ต SMTP ทำให้ Gmail SMTP ส่งไม่ออก ช่องทาง API (HTTPS 443) ใช้แทนได้
 * ผู้เรียกต้องจับ exception เอง — การส่งเมลล้มเหลวต้องไม่ทำให้ขั้นตอนในระบบล้มเหลว
 */

require_once __DIR__ . '/gmail_smtp.php';

function app_mail_transport(): string
{
    $t = strtolower(trim((string)(getenv('MAIL_TRANSPORT') ?: '')));
    if (in_array($t, ['smtp', 'brevo'], true)) {
        return $t;
    }
    return trim((string)(getenv('BREVO_API_KEY') ?: '')) !== '' ? 'brevo' : 'smtp';
}

function app_mail_from(): string
{
    return trim((string)(getenv('MAIL_FROM') ?: getenv('MAIL_USERNAME') ?: ''));
}

function app_mail_is_configured(): bool
{
    if (filter_var(getenv('REPS_E2E') ?: 'false', FILTER_VALIDATE_BOOLEAN)) {
        return false;
    }
    if (app_mail_transport() === 'brevo') {
        return trim((string)(getenv('BREVO_API_KEY') ?: '')) !== ''
            && filter_var(app_mail_from(), FILTER_VALIDATE_EMAIL) !== false
            && function_exists('curl_init');
    }
    return gmailSmtpIsConfigured();
}

/** ส่งอีเมลข้อความล้วน (UTF-8) — โยน RuntimeException เมื่อส่งไม่สำเร็จ */
function app_mail_send(string $to, string $subject, string $body): void
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Invalid recipient address');
    }
    if (!app_mail_is_configured()) {
        throw new RuntimeException('Mail transport (' . app_mail_transport() . ') is not configured');
    }
    if (app_mail_transport() === 'brevo') {
        app_mail_send_brevo($to, $subject, $body);
        return;
    }
    sendGmailSmtpMail($to, $subject, $body);
}

function app_mail_send_brevo(string $to, string $subject, string $body): void
{
    $payload = json_encode([
        'sender' => ['email' => app_mail_from(), 'name' => trim((string)(getenv('MAIL_FROM_NAME') ?: 'REPS'))],
        'to' => [['email' => $to]],
        'subject' => $subject,
        'textContent' => $body,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . trim((string)getenv('BREVO_API_KEY')),
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $response = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Brevo request failed: ' . $error);
    }
    if ($code < 200 || $code >= 300) {
        throw new RuntimeException('Brevo HTTP ' . $code . ': ' . mb_substr((string)$response, 0, 300));
    }
}
