<?php
declare(strict_types=1);

/**
 * REPS · includes/permissions.php
 * ระบบจัดการสิทธิ์กลางตามลำดับ: teacher (สูงสุด) > admin > staff > user
 */

const ROLE_LEVELS = [
    'teacher'   => 40, // ครู: สิทธิ์สูงสุด ดูรายงานประจำปี เพิ่ม/แก้ไขผู้ใช้ทุกคนได้
    'admin'     => 30, // แอดมิน: จัดการระบบ คิว สต็อก และภาพรวมทั้งหมด
    'staff'     => 20, // ฝ่ายซ่อม (Lab Boy): รับงานตามคิว บันทึกซ่อม เบิกของ ปิดงาน
    'user'      => 10, // ผู้แจ้งซ่อมทั่วไป (นักศึกษา/บุคลากร)
    'requester' => 10, // รองรับค่าเก่าชั่วคราว
];

function normalize_role(string $role): string
{
    return $role === 'requester' ? 'user' : $role;
}

function has_min_role(string $currentRole, string $minRole): bool
{
    $curr = ROLE_LEVELS[normalize_role($currentRole)] ?? 0;
    $min  = ROLE_LEVELS[normalize_role($minRole)] ?? 999;
    return $curr >= $min;
}

function can_edit_user(array $actor, array $targetUser): bool
{
    $actorRole  = normalize_role((string)($actor['role'] ?? 'user'));
    $targetRole = normalize_role((string)($targetUser['role'] ?? 'user'));

    if ($actorRole === 'teacher') {
        return true; // ครูสิทธิ์สูงสุด แก้ได้ทุกคน
    }
    if ($actorRole === 'admin') {
        return $targetRole !== 'teacher'; // แอดมินห้ามแก้บัญชีครู
    }
    return (int)$actor['user_id'] === (int)$targetUser['user_id'];
}

function role_home_url(string $role): string
{
    return match (normalize_role($role)) {
        'teacher', 'admin' => 'admin/admin_dashboard.php',
        'staff'            => 'staff/staff_dashboard.php',
        default            => 'dashboard.php',
    };
}