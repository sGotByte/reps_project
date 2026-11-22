<?php
declare(strict_types=1);

/**
 * REPS · includes/terms.php — ภาคเรียนและทีมสตาฟต่อภาคเรียน
 *
 * - สตาฟ (Lab boy) เปลี่ยนทุกภาคเรียน ไม่เกิน max_staff คน (ตั้งที่ admin/admin_terms.php)
 * - ข้อมูลแยกตามช่วงวันที่ของภาคเรียน: ใบงานนับตาม created_at, คะแนนนับตาม score_logs.created_at
 *   คะแนนภาคเรียน = 100 + ผลรวมคะแนนที่ได้/ถูกหักในช่วงภาคเรียนนั้น (เริ่มใหม่ทุกภาคเรียน)
 * - ภาคเรียนปัจจุบัน = ภาคเรียนที่ช่วงวันที่ครอบวันนี้ ถ้าไม่มี (ช่วงปิดภาค) ใช้ภาคเรียนล่าสุดที่เริ่มไปแล้ว
 * - ยังไม่มีภาคเรียนในระบบ = ทำงานแบบเดิม (ไม่กรองสตาฟ) เพื่อไม่ให้ระบบเดิมหยุดทำงานหลังอัปเดต
 */

require_once __DIR__ . '/db_helpers.php';

const TERM_NAMES = [1 => 'ภาคเรียนที่ 1', 2 => 'ภาคเรียนที่ 2', 3 => 'ภาคฤดูร้อน'];

/** ภาคเรียนทั้งหมด ใหม่สุดก่อน (cache ต่อ request) */
function term_all(mysqli $conn, bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        try {
            $cache = db_all($conn, 'SELECT t.*, (SELECT COUNT(*) FROM term_staff s WHERE s.term_id = t.id) AS staff_count FROM academic_terms t ORDER BY t.start_date DESC, t.id DESC');
        } catch (Throwable $e) {
            $cache = []; // ยังไม่ได้รัน migration ภาคเรียน
        }
    }
    return $cache;
}

function term_get(mysqli $conn, int $termId): ?array
{
    foreach (term_all($conn) as $t) {
        if ((int)$t['id'] === $termId) return $t;
    }
    return null;
}

/** ภาคเรียนที่ครอบวันที่นี้ (YYYY-MM-DD) */
function term_for_date(mysqli $conn, string $date): ?array
{
    foreach (term_all($conn) as $t) {
        if ($t['start_date'] <= $date && $date <= $t['end_date']) return $t;
    }
    return null;
}

/** ภาคเรียนปัจจุบัน (หรือภาคเรียนล่าสุดที่เริ่มแล้ว ถ้าวันนี้อยู่ในช่วงปิดภาค) */
function term_current(mysqli $conn): ?array
{
    $today = date('Y-m-d');
    $hit = term_for_date($conn, $today);
    if ($hit) return $hit;
    foreach (term_all($conn) as $t) {          // เรียงใหม่สุดก่อน
        if ($t['start_date'] <= $today) return $t;
    }
    return null;
}

/** ภาคเรียนจาก ?term=<id> (ค่าเริ่มต้น = ภาคเรียนปัจจุบัน) */
function term_from_request(mysqli $conn, string $param = 'term'): ?array
{
    $id = filter_input(INPUT_GET, $param, FILTER_VALIDATE_INT);
    return ($id ? term_get($conn, (int)$id) : null) ?? term_current($conn);
}

/** "2/2569" */
function term_label(?array $t): string
{
    return $t ? (int)$t['term_no'] . '/' . (int)$t['year_be'] : '-';
}

/** "ภาคเรียนที่ 2/2569" */
function term_title(?array $t): string
{
    if (!$t) return 'ยังไม่ได้ตั้งภาคเรียน';
    return ((int)$t['term_no'] === 3 ? 'ภาคฤดูร้อน ' : 'ภาคเรียนที่ ') . term_label($t);
}

/** ช่วงเวลาเต็มของภาคเรียน [เริ่ม 00:00:00, สิ้นสุด 23:59:59] */
function term_range(array $t): array
{
    return [$t['start_date'] . ' 00:00:00', $t['end_date'] . ' 23:59:59'];
}

/** มีการตั้งภาคเรียนแล้วหรือยัง */
function terms_enabled(mysqli $conn): bool
{
    return term_all($conn) !== [];
}

/** user_id ของทีมสตาฟในภาคเรียน เรียงตามลำดับคิว */
function term_roster_ids(mysqli $conn, int $termId): array
{
    static $cache = [];
    if (!isset($cache[$termId])) {
        $cache[$termId] = array_map('intval', array_column(
            db_all($conn, 'SELECT user_id FROM term_staff WHERE term_id = ? ORDER BY queue_order, user_id', 'i', [$termId]),
            'user_id'
        ));
    }
    return $cache[$termId];
}

/**
 * เงื่อนไข SQL กรองสตาฟให้เหลือเฉพาะทีมของภาคเรียน (ค่าเริ่มต้น = ปัจจุบัน)
 * คืน '' เมื่อยังไม่มีภาคเรียนในระบบ · term_id เป็นตัวเลขจากฐานข้อมูล จึงต่อสตริงได้ปลอดภัย
 */
function term_staff_sql(mysqli $conn, string $userCol = 'u.user_id', ?array $term = null): string
{
    $term ??= term_current($conn);
    if (!$term) return '';
    return ' AND ' . $userCol . ' IN (SELECT ts.user_id FROM term_staff ts WHERE ts.term_id = ' . (int)$term['id'] . ')';
}

/** เงื่อนไขช่วงวันที่ของภาคเรียนสำหรับคอลัมน์ datetime (วันที่มาจากคอลัมน์ DATE จึงต่อสตริงได้ปลอดภัย) */
function term_date_sql(?array $term, string $col): string
{
    if (!$term) return '';
    [$from, $to] = term_range($term);
    return " AND {$col} BETWEEN '" . date('Y-m-d H:i:s', strtotime($from)) . "' AND '" . date('Y-m-d H:i:s', strtotime($to)) . "'";
}

/** นิพจน์ SQL คะแนนภาคเรียน: เริ่ม 100 (เต็ม) + คะแนนที่หัก/ครูบวกในช่วงภาคเรียน อยู่ในช่วง 0–100 · ไม่มีภาคเรียน = คะแนนสะสมเดิม */
function term_score_sql(?array $term, string $userCol = 'u.user_id', string $fallback = 'u.score'): string
{
    if (!$term) return $fallback;
    return 'LEAST(100, GREATEST(0, 100 + COALESCE((SELECT SUM(sl.points_change) FROM score_logs sl WHERE sl.user_id = ' . $userCol . term_date_sql($term, 'sl.created_at') . '), 0)))';
}

/** สตาฟคนนี้อยู่ในทีมภาคเรียนปัจจุบันหรือไม่ (ไม่มีภาคเรียน = อนุญาต) */
function term_staff_allowed(mysqli $conn, int $userId): bool
{
    $term = term_current($conn);
    return !$term || in_array($userId, term_roster_ids($conn, (int)$term['id']), true);
}
