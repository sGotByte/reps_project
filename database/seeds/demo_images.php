<?php
declare(strict_types=1);

/**
 * รูปประกอบสำหรับข้อมูลจำลอง — ใช้รูปถ่ายตัวอย่างจาก database/seeds/photos/
 * (Wikimedia Commons สัญญาอนุญาตเสรี ดูเครดิตใน photos/CREDITS.md) จับคู่ตามอาการเสีย/วิธีซ่อม
 * เป็นรูปตัวอย่างเพื่อสาธิตหน้าตาระบบ ไม่ใช่หลักฐานงานซ่อมจริง
 */

const DEMO_PHOTO_DIR = __DIR__ . '/photos';

function demo_photo_manifest(): array
{
    static $m = null;
    return $m ??= (is_file(DEMO_PHOTO_DIR . '/manifest.php') ? require DEMO_PHOTO_DIR . '/manifest.php' : []);
}

/** หมวดรูป "ตอนแจ้งซ่อม" จากข้อความอาการ */
function demo_photo_groups_before(string $symptom): array
{
    return match (true) {
        str_contains($symptom, 'โปรเจกเตอร์') => ['projector'],
        str_contains($symptom, 'เครื่องพิมพ์') => ['printer'],
        str_contains($symptom, 'Wi-Fi') => ['wifi'],
        str_contains($symptom, 'อินเทอร์เน็ต'), str_contains($symptom, 'LAN') => ['lan'],
        str_contains($symptom, 'เมาส์') => ['mouse'],
        str_contains($symptom, 'คีย์บอร์ด') => ['keyboard'],
        str_contains($symptom, 'ลำโพง') => ['speaker'],
        str_contains($symptom, 'HDMI') => ['hdmi'],
        str_contains($symptom, 'จอฟ้า') => ['bsod'],
        str_contains($symptom, 'นาฬิกา') => ['cmos'],
        str_contains($symptom, 'พัดลม') => ['fan'],
        str_contains($symptom, 'ช้า') => ['disk', 'freeze'],
        str_contains($symptom, 'ค้าง') => ['freeze', 'fan'],
        str_contains($symptom, 'Office') => ['lab'],
        default => ['nopower'],
    };
}

/** หมวดรูป "หลังซ่อม" จากวิธีดำเนินการ */
function demo_photo_groups_after(string $method): array
{
    return match (true) {
        str_contains($method, 'พาวเวอร์ซัพพลาย') => ['nopower', 'tools'],
        str_contains($method, 'ซิลิโคน'), str_contains($method, 'ฝุ่น') => ['paste', 'tools'],
        str_contains($method, 'RJ45') => ['nettools'],
        str_contains($method, 'เมาส์') => ['mouse'],
        str_contains($method, 'คีย์บอร์ด') => ['keyboard'],
        str_contains($method, 'Access Point'), str_contains($method, 'เครือข่าย') => ['wifi', 'nettools'],
        str_contains($method, 'HDMI') => ['hdmi'],
        str_contains($method, 'ถ่านไบออส') => ['cmos'],
        str_contains($method, 'ลำโพง'), str_contains($method, 'เสียง') => ['speaker'],
        str_contains($method, 'พิมพ์') => ['printer'],
        default => ['lab'],
    };
}

/**
 * วางรูปจากหมวดที่กำหนดลงที่ $dest (hard link ถ้าทำได้ ไม่งั้นคัดลอก) — คืน false ถ้าไม่มีรูปในหมวด
 * $seed ทำให้ใบงานเดิมได้รูปเดิมเสมอ และรูปที่ 2 ของใบงานไม่ซ้ำรูปแรก
 */
function demo_photo_place(array $groups, string $dest, int $seed): bool
{
    $manifest = demo_photo_manifest();
    $pool = [];
    foreach ($groups as $g) {
        foreach ($manifest[$g] ?? [] as $file) {
            $pool[] = $file;
        }
    }
    if (!$pool) {
        return false;
    }
    $src = DEMO_PHOTO_DIR . '/' . $pool[abs($seed) % count($pool)];
    if (!is_file($src)) {
        return false;
    }
    @unlink($dest);
    return @link($src, $dest) || @copy($src, $dest);
}
