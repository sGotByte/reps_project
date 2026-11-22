<?php
declare(strict_types=1);

/**
 * REPS · includes/permissions.php
 * ระบบสิทธิ์กลาง ลำดับ: admin (ครูประจำปีการศึกษา) > staff > user
 * users.user_type (student/teacher/staff) คือสถานภาพบุคคล ไม่ใช่สิทธิ์
 */

const ROLE_LEVELS = [
    'admin' => 30, // ครู: สิทธิ์สูงสุด จัดการผู้ใช้ คิว สต็อก รายงาน รับรองงาน
    'staff' => 20, // ฝ่ายซ่อม: รับงานตามคิว ปิดงาน เบิกวัสดุ
    'user'  => 10, // ผู้แจ้งซ่อมทั่วไป
];

const ROLE_LABELS = [
    'admin' => 'ผู้ดูแลระบบ (ครู)',
    'staff' => 'สตาฟฝ่ายซ่อม',
    'user'  => 'ผู้ใช้ทั่วไป',
];

/**
 * ตารางสิทธิ์ตามหัวข้อ 2 ใน feedback-todo.md
 * key = ความสามารถ, value = role ที่ทำได้
 */
const ROLE_CAPABILITIES = [
    'war_room.view'      => ['admin', 'staff'],
    'queue.manage'       => ['admin'],
    'ticket.work'        => ['admin', 'staff'],   // รับงาน อัปเดตสถานะ ปิดงาน
    'ticket.signoff'     => ['admin'],            // ลายเซ็นรับรองงาน
    'ticket.view_all'    => ['admin', 'staff'],
    'report.view_all'    => ['admin'],
    'user.manage'        => ['admin'],            // แก้ไขผู้ใช้ / อนุมัติสมัครใหม่
    'inventory.use'      => ['admin', 'staff'],
    'inventory.manage'   => ['admin'],            // เพิ่ม/แก้ไขวัสดุ รับเข้า ปรับยอด
    'site.customize'     => ['admin'],
];

function normalize_role(string $role): string
{
    // รองรับค่าเก่า 'requester' และ 'teacher' จากโค้ดรุ่นก่อน
    return match ($role) {
        'requester' => 'user',
        'teacher'   => 'admin',
        default     => $role,
    };
}

function is_valid_role(string $role): bool
{
    return array_key_exists($role, ROLE_LEVELS);
}

function has_min_role(string $currentRole, string $minRole): bool
{
    $curr = ROLE_LEVELS[normalize_role($currentRole)] ?? 0;
    $min  = ROLE_LEVELS[normalize_role($minRole)] ?? PHP_INT_MAX;
    return $curr >= $min;
}

function can(string $role, string $capability): bool
{
    return in_array(normalize_role($role), ROLE_CAPABILITIES[$capability] ?? [], true);
}

function role_label(string $role): string
{
    return ROLE_LABELS[normalize_role($role)] ?? $role;
}

/** admin แก้ได้ทุกบัญชี ส่วนคนอื่นแก้ได้เฉพาะของตัวเอง */
function can_edit_user(array $actor, array $targetUser): bool
{
    if (normalize_role((string)($actor['role'] ?? 'user')) === 'admin') {
        return true;
    }
    return (int)($actor['user_id'] ?? 0) === (int)($targetUser['user_id'] ?? -1);
}

function role_home_url(string $role): string
{
    return match (normalize_role($role)) {
        'admin' => 'admin/admin_dashboard.php',
        'staff' => 'staff/staff_dashboard.php',
        default => 'dashboard.php',
    };
}
