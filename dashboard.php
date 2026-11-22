<?php
declare(strict_types=1);

/**
 * REPS · dashboard.php — หน้า "ใบงานของฉัน" (ผู้แจ้งซ่อม)
 * Admin Shell UI Design Alignment & Enhanced Custom Stat Cards with Visual Stepper
 * + Minimalist Card Actions & Vertical Stepper สำหรับประวัติสถานะ
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/user_auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/queue_manager.php';
require_once __DIR__ . '/includes/icons.php';

/* ================================================================
 * ZONE A · Config
 * ================================================================ */

require_once __DIR__ . '/includes/pager.php';
const TICKETS_PER_PAGE_OPTIONS = [10, 25, 50];
define('TICKETS_PER_PAGE', pager_per_page(TICKETS_PER_PAGE_OPTIONS, 10));
const MAX_PAGE_NUMBER = 100000;
const SEARCH_MAX_LENGTH = 100;
const LOCATION_MAX_LENGTH = 255;
const SYMPTOM_MAX_LENGTH = 5000;
const CANCEL_REASON_MAX_LENGTH = 255;

const URGENCY_LABELS = ['urgent' => 'ด่วน', 'normal' => 'ปกติ'];
const USER_TYPE_LABELS = ['student' => 'นักศึกษา', 'teacher' => 'ครู/อาจารย์', 'staff' => 'บุคลากร'];

/** สถานะใบงาน */
enum TicketStatus: string
{
    // ช่วงหลักที่ผู้แจ้งเห็น (ใช้เป็นตัวกรองและขั้นใน tracker) — ค่า in_progress/awaiting_signoff ใช้เป็นชื่อช่วง
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case AwaitingSignoff = 'awaiting_signoff';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    // สถานะละเอียดของ v3
    case Accepted = 'accepted';
    case AwaitingIntakeSign = 'awaiting_intake_sign';
    case InspectedReady = 'inspected_ready';
    case AwaitingRequesterSign = 'awaiting_requester_sign';
    case AwaitingAdminSign = 'awaiting_admin_sign';
    case Disputed = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'รอรับเรื่อง',
            self::InProgress => 'กำลังดำเนินการ',
            self::AwaitingSignoff => 'รอเซ็นรับรอง',
            self::Completed => 'เสร็จสิ้น',
            self::Cancelled => 'ยกเลิก',
            self::Accepted => 'ช่างรับงานแล้ว',
            self::AwaitingIntakeSign => 'รอคุณเซ็นรับทราบ',
            self::InspectedReady => 'ซ่อมเสร็จ รอส่งคืน',
            self::AwaitingRequesterSign => 'รอคุณเซ็นรับคืน',
            self::AwaitingAdminSign => 'รอครูรับรอง',
            self::Disputed => 'คุณไม่ยอมรับ รอครูพิจารณา',
        };
    }

    /** ช่วงหลักของสถานะนี้ */
    public function phase(): self
    {
        return match ($this) {
            self::Accepted, self::AwaitingIntakeSign, self::InspectedReady => self::InProgress,
            self::AwaitingRequesterSign, self::AwaitingAdminSign, self::Disputed => self::AwaitingSignoff,
            default => $this,
        };
    }

    /** ผู้แจ้งต้องเซ็นใบงานนี้ */
    public function needsRequesterSign(): bool
    {
        return $this === self::AwaitingIntakeSign || $this === self::AwaitingRequesterSign;
    }

    /** สถานะจริงทั้งหมดในช่วงหนึ่ง */
    public static function statusesOfPhase(string $phase): array
    {
        return match ($phase) {
            'in_progress' => ['accepted', 'awaiting_intake_sign', 'in_progress', 'inspected_ready'],
            'awaiting_signoff' => ['awaiting_requester_sign', 'awaiting_admin_sign', 'disputed'],
            default => [$phase],
        };
    }
}

/* ================================================================
 * ZONE B · Helpers: DB / Input / Flash / URL
 * ================================================================ */

function db_prepare(mysqli $conn, string $sql): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Prepare failed: ' . $conn->error);
    }
    return $stmt;
}

function post_string(string $key): string
{
    $value = $_POST[$key] ?? null;
    return is_string($value) ? $value : '';
}

function get_string(string $key): string
{
    $value = $_GET[$key] ?? null;
    return is_string($value) ? $value : '';
}

function request_status_filter(): string
{
    $status = trim(get_string('status'));
    if ($status === 'open') {
        return '';
    }

    return in_array($status, [TicketStatus::Pending->value, TicketStatus::InProgress->value, TicketStatus::AwaitingSignoff->value], true)
        ? $status
        : '';
}

function request_search_term(): string
{
    return mb_substr(trim(get_string('q')), 0, SEARCH_MAX_LENGTH, 'UTF-8');
}

function request_page_number(): int
{
    $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
    return is_int($page) ? max(1, min(MAX_PAGE_NUMBER, $page)) : 1;
}

function escape_like(string $value): string
{
    return addcslashes($value, '\\%_');
}

function page_url(array $params): string
{
    if (TICKETS_PER_PAGE !== 10 && !array_key_exists('per', $params)) {
        $params['per'] = TICKETS_PER_PAGE;
    }
    $params = array_filter($params, static fn($v) => $v !== '' && $v !== null && $v !== 1);
    return 'dashboard.php' . ($params !== [] ? '?' . http_build_query($params) : '');
}

function dashboard_return_url(): string
{
    return page_url(['status' => request_status_filter(), 'q' => request_search_term()]);
}

function flash(string $type, string $message): array
{
    return ['type' => $type, 'message' => $message];
}

function take_session_flash(): ?array
{
    $raw = $_SESSION['dashboard_flash'] ?? null;
    unset($_SESSION['dashboard_flash']);

    if (is_array($raw)) {
        $type = $raw['type'] ?? '';
        $message = $raw['message'] ?? '';
        if (!is_string($message) || $message === '') {
            return null;
        }
        return flash(in_array($type, ['error', 'success', 'info'], true) ? $type : 'info', $message);
    }

    if (is_string($raw) && $raw !== '') {
        return flash(classify_flash_text($raw), $raw);
    }
    return null;
}

function classify_flash_text(string $message): string
{
    if (preg_match('/ผิดพลาด|ไม่ถูกต้อง|ไม่สำเร็จ|ไม่สามารถ|กรุณา|ต้องมีความยาว/u', $message) === 1) {
        return 'error';
    }
    if (preg_match('/เรียบร้อย|บันทึกการแก้ไขใบแจ้งซ่อมแล้ว/u', $message) === 1) {
        return 'success';
    }
    return 'info';
}

/* ================================================================
 * ZONE C · Data access
 * ================================================================ */

function fetch_user_profile(mysqli $conn, int $userId): ?array
{
    $stmt = db_prepare(
        $conn,
        'SELECT u.user_id, u.first_name, u.last_name, u.user_type, d.name AS department_name
         FROM users u
         LEFT JOIN department d ON d.d_id = u.department_id
         WHERE u.user_id = ?
         LIMIT 1'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $user;
}

function fetch_categories(mysqli $conn): array
{
    $result = $conn->query('SELECT id, name FROM categories ORDER BY name');
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function fetch_status_counts(mysqli $conn, int $userId): array
{
    $counts = array_fill_keys(array_column(TicketStatus::cases(), 'value'), 0);

    $stmt = db_prepare($conn, 'SELECT status, COUNT(*) AS cnt FROM tickets WHERE requester_id = ? GROUP BY status');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // นับรวมเข้าช่วงหลัก (สถานะละเอียดของ v3 รวมอยู่ใน in_progress / awaiting_signoff)
        $phase = TicketStatus::tryFrom((string)$row['status'])?->phase()->value;
        if ($phase !== null && isset($counts[$phase])) {
            $counts[$phase] += (int)$row['cnt'];
        }
    }
    $stmt->close();
    return $counts;
}

function build_ticket_filter(int $userId, string $status, string $search): array
{
    $clauses = ["t.requester_id = ?", "t.status IN ('pending', 'accepted', 'awaiting_intake_sign', 'in_progress', 'inspected_ready', 'awaiting_requester_sign', 'awaiting_admin_sign', 'disputed')"];
    $types = 'i';
    $params = [$userId];

    if ($status !== '') {
        $phaseStatuses = TicketStatus::statusesOfPhase($status);
        $clauses[] = 't.status IN (' . implode(',', array_fill(0, count($phaseStatuses), '?')) . ')';
        $types .= str_repeat('s', count($phaseStatuses));
        array_push($params, ...$phaseStatuses);
    }

    if ($search !== '') {
        $clauses[] = '(t.ticket_no LIKE ? OR t.symptom LIKE ? OR t.location_note LIKE ?
                       OR EXISTS (SELECT 1 FROM categories sc WHERE sc.id = t.category_id AND sc.name LIKE ?))';
        $term = '%' . escape_like($search) . '%';
        $types .= 'ssss';
        array_push($params, $term, $term, $term, $term);
    }

    return [implode(' AND ', $clauses), $types, $params];
}

function count_tickets(mysqli $conn, string $whereSql, string $types, array $params): int
{
    $stmt = db_prepare($conn, "SELECT COUNT(*) FROM tickets t WHERE {$whereSql}");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_row()[0];
    $stmt->close();
    return $total;
}

function fetch_tickets(mysqli $conn, string $whereSql, string $types, array $params, int $offset): array
{
    $sql = "SELECT t.id AS ticket_id, t.ticket_no, t.category_id, t.symptom AS symptoms,
                   t.urgency, t.status, t.created_at, t.accepted_at, t.completed_at,
                   t.resolution_summary, t.cancel_reason, t.completion_image,
                   c.name AS category_name,
                   t.location_note AS location_name,
                   CONCAT(tech.first_name, ' ', tech.last_name) AS tech_name
            FROM tickets t
            LEFT JOIN categories c ON t.category_id = c.id
            LEFT JOIN users tech ON t.assigned_to = tech.user_id
            WHERE {$whereSql}
            ORDER BY t.created_at DESC
            LIMIT ? OFFSET ?";

    $types .= 'ii';
    $params[] = TICKETS_PER_PAGE;
    $params[] = $offset;

    $stmt = db_prepare($conn, $sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function fetch_logs_by_ticket(mysqli $conn, array $ticketIds): array
{
    if ($ticketIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
    $stmt = db_prepare(
        $conn,
        "SELECT l.ticket_id, l.old_status, l.new_status, l.created_at,
                CONCAT(u.first_name, ' ', u.last_name) AS by_name
         FROM ticket_logs l
         LEFT JOIN users u ON l.action_by = u.user_id
         WHERE l.ticket_id IN ({$placeholders})
         ORDER BY l.created_at ASC"
    );
    $stmt->bind_param(str_repeat('i', count($ticketIds)), ...$ticketIds);
    $stmt->execute();
    $result = $stmt->get_result();

    $grouped = [];
    while ($row = $result->fetch_assoc()) {
        $grouped[$row['ticket_id']][] = $row;
    }
    $stmt->close();
    return $grouped;
}

/* ================================================================
 * ZONE D · Actions (แก้ไข / ยกเลิก)
 * ================================================================ */

function handle_update_ticket(mysqli $conn, int $userId, array $categories): array
{
    $ticketId = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
    $categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $location = trim(post_string('location_note'));
    $symptoms = trim(post_string('symptoms'));
    $urgency = post_string('urgency');
    $categoryIds = array_map('intval', array_column($categories, 'id'));

    $isValid = is_int($ticketId) && $ticketId > 0
        && is_int($categoryId) && in_array($categoryId, $categoryIds, true)
        && $location !== '' && mb_strlen($location, 'UTF-8') <= LOCATION_MAX_LENGTH
        && $symptoms !== '' && mb_strlen($symptoms, 'UTF-8') <= SYMPTOM_MAX_LENGTH
        && array_key_exists($urgency, URGENCY_LABELS);

    if (!$isValid) {
        return flash('error', 'ข้อมูลใบแจ้งซ่อมไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง');
    }

    // งานด่วนต้องมีเหตุผลเสมอ (ใช้เหตุผลเดิมได้ถ้าเคยระบุไว้)
    $urgencyReason = mb_substr(trim(post_string('urgency_reason')), 0, 500, 'UTF-8');
    if ($urgency === 'urgent' && $urgencyReason === '') {
        $current = db_prepare($conn, 'SELECT urgency_reason FROM tickets WHERE id = ? AND requester_id = ?');
        $current->bind_param('ii', $ticketId, $userId);
        $current->execute();
        $urgencyReason = (string)($current->get_result()->fetch_assoc()['urgency_reason'] ?? '');
        $current->close();
        if ($urgencyReason === '') {
            return flash('error', 'เลือก "ด่วน" แล้ว กรุณาระบุเหตุผลที่ต้องแก้ไขภายในวันนี้');
        }
    }
    $urgencyReasonValue = $urgency === 'urgent' ? $urgencyReason : null;

    $stmt = db_prepare(
        $conn,
        "UPDATE tickets
         SET location_note = ?, category_id = ?, symptom = ?, urgency = ?, urgency_reason = ?
         WHERE id = ? AND requester_id = ? AND status = 'pending'"
    );
    $stmt->bind_param('sisssii', $location, $categoryId, $symptoms, $urgency, $urgencyReasonValue, $ticketId, $userId);
    $executed = $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    if (!$executed) {
        return flash('error', 'บันทึกการแก้ไขไม่สำเร็จ กรุณาลองใหม่');
    }

    return $affectedRows > 0
        ? flash('success', 'บันทึกการแก้ไขใบแจ้งซ่อมแล้ว')
        : flash('info', 'ใบงานไม่มีการเปลี่ยนแปลง หรือไม่อยู่ในสถานะที่แก้ไขได้');
}

function handle_cancel_ticket(mysqli $conn, int $userId): ?array
{
    $ticketId = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
    if (!is_int($ticketId) || $ticketId <= 0) {
        return null;
    }

    $reason = trim(post_string('cancel_reason'));
    if ($reason === '') {
        return flash('error', 'กรุณาระบุเหตุผลการยกเลิก');
    }
    if (mb_strlen($reason, 'UTF-8') > CANCEL_REASON_MAX_LENGTH) {
        return flash('error', 'เหตุผลการยกเลิกต้องมีความยาวไม่เกิน ' . CANCEL_REASON_MAX_LENGTH . ' ตัวอักษร');
    }

    $conn->begin_transaction();
    try {
        $select = db_prepare(
            $conn,
            "SELECT ticket_no FROM tickets
             WHERE id = ? AND requester_id = ? AND status = 'pending'
               AND (assigned_to IS NULL OR assigned_to = 0)
             LIMIT 1 FOR UPDATE"
        );
        $select->bind_param('ii', $ticketId, $userId);
        $select->execute();
        $ticket = $select->get_result()->fetch_assoc();
        $select->close();

        if ($ticket === null) {
            $conn->rollback();
            return flash('error', 'ไม่สามารถยกเลิกได้ เนื่องจากใบงานเปลี่ยนสถานะแล้ว');
        }

        $update = db_prepare($conn, "UPDATE tickets SET status = 'cancelled', cancel_reason = ? WHERE id = ?");
        $update->bind_param('si', $reason, $ticketId);
        if (!$update->execute() || $update->affected_rows !== 1) {
            throw new RuntimeException('Ticket cancellation did not update a row');
        }
        $update->close();

        $log = db_prepare(
            $conn,
            "INSERT INTO ticket_logs (ticket_id, old_status, new_status, action_by, created_at)
             VALUES (?, 'pending', 'cancelled', ?, NOW())"
        );
        $log->bind_param('ii', $ticketId, $userId);
        if (!$log->execute()) {
            throw new RuntimeException($log->error);
        }
        $log->close();

        $conn->commit();
        return flash('success', 'ยกเลิกใบงาน #' . $ticket['ticket_no'] . ' เรียบร้อยแล้ว');
    } catch (Throwable) {
        $conn->rollback();
        return flash('error', 'เกิดข้อผิดพลาดในการยกเลิกใบงาน');
    }
}

/* ================================================================
 * ZONE E · View helpers
 * ================================================================ */

function h(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function fmt(mixed $value): string
{
    if (!is_string($value) || $value === '') {
        return '—';
    }
    $timestamp = strtotime($value);
    return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : '—';
}

function status_of(mixed $value): ?TicketStatus
{
    return is_string($value) ? TicketStatus::tryFrom($value) : null;
}

function status_label(mixed $value): string
{
    return status_of($value)?->label() ?? (string)($value ?? '');
}

function urgency_label(mixed $value): string
{
    return URGENCY_LABELS[is_string($value) ? $value : ''] ?? (string)($value ?? '');
}

function tracker_step(TicketStatus $status, mixed $when, bool $on, bool $now): array
{
    return [
        'label' => $status->label(),
        'when'  => $when,
        'on'    => $on,
        'now'   => $now,
    ];
}

function build_tracker(array $ticket, array $logs): array
{
    $status = status_of($ticket['status'] ?? null);
    $createdAt = $ticket['created_at'] ?? null;

    if ($status === TicketStatus::Cancelled) {
        $cancelledAt = null;
        foreach ($logs as $log) {
            if (($log['new_status'] ?? null) === 'cancelled') {
                $cancelledAt = $log['created_at'] ?? null;
            }
        }
        return [
            tracker_step(TicketStatus::Pending, $createdAt, true, false),
            tracker_step(TicketStatus::Cancelled, $cancelledAt, true, true),
        ];
    }

    $index = match ($status?->phase()) {
        TicketStatus::InProgress => 1,
        TicketStatus::AwaitingSignoff => 2,
        TicketStatus::Completed => 3,
        default => 0,
    };
    $submittedAt = null;
    foreach ($logs as $log) {
        if (in_array($log['new_status'] ?? null, ['awaiting_requester_sign', 'awaiting_admin_sign'], true) && $submittedAt === null) {
            $submittedAt = $log['created_at'] ?? null;
        }
    }

    return [
        tracker_step(TicketStatus::Pending, $createdAt, true, $index === 0),
        tracker_step(TicketStatus::InProgress, $ticket['accepted_at'] ?? null, $index >= 1, $index === 1),
        tracker_step(TicketStatus::AwaitingSignoff, $submittedAt, $index >= 2, $index === 2),
        tracker_step(TicketStatus::Completed, $ticket['completed_at'] ?? null, $index >= 3, $index === 3),
    ];
}

/**
 * สร้าง timeline ประวัติสถานะ (แสดงเป็น vertical stepper)
 */
function build_log_timeline(array $ticket, array $logs): array
{
    $timeline = [[
        'title'  => 'สร้างใบงาน',
        'meta'   => 'สถานะเริ่มต้น: ' . TicketStatus::Pending->label(),
        'time'   => $ticket['created_at'] ?? null,
        'cancel' => false,
    ]];

    foreach ($logs as $log) {
        if (empty($log['old_status']) && ($log['new_status'] ?? '') === 'pending') {
            continue;
        }
        $timeline[] = [
            'title'  => 'เปลี่ยนเป็น ' . status_label($log['new_status'] ?? ''),
            'meta'   => (!empty($log['old_status']) ? 'จาก ' . status_label($log['old_status']) . ' · ' : '')
                        . 'โดย ' . ($log['by_name'] ?? 'ระบบ'),
            'time'   => $log['created_at'] ?? null,
            'cancel' => ($log['new_status'] ?? '') === 'cancelled',
        ];
    }

    return $timeline;
}

/* ================================================================
 * ZONE F · Main request flow
 * ================================================================ */

$user_id = (int)app_require_active_user($conn, 'login.php?next=dashboard');
$csrfToken = app_csrf_token();

$user = fetch_user_profile($conn, $user_id);
if ($user === null) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$full_name = trim($user['first_name'] . ' ' . $user['last_name']);
$categories = fetch_categories($conn);

// --- POST: แก้ไข / ยกเลิก ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = post_string('action');
    if (in_array($action, ['cancel', 'update_ticket'], true)) {
        if (!app_csrf_is_valid(post_string('csrf'))) {
            http_response_code(403);
            exit('คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
        }

        $result = $action === 'update_ticket'
            ? handle_update_ticket($conn, $user_id, $categories)
            : handle_cancel_ticket($conn, $user_id);

        if ($result !== null) {
            $_SESSION['dashboard_flash'] = $result;
        }

        header('Location: ' . dashboard_return_url(), true, 303);
        exit;
    }
}

// --- GET: แสดงผล ---
$flashData = take_session_flash();
$flash = $flashData['message'] ?? '';
$flashType = $flashData['type'] ?? 'info';

$filter = request_status_filter();
$search = request_search_term();
$page = request_page_number();

$counts = fetch_status_counts($conn, $user_id);
$total_tickets = $counts['pending'] + $counts['in_progress'] + $counts['awaiting_signoff'];

[$where_sql, $bind_types, $bind_params] = build_ticket_filter($user_id, $filter, $search);
$total_filtered = count_tickets($conn, $where_sql, $bind_types, $bind_params);

$max_pages = max(1, (int)ceil($total_filtered / TICKETS_PER_PAGE));
$page = min($page, $max_pages);
$offset = ($page - 1) * TICKETS_PER_PAGE;

$tickets = fetch_tickets($conn, $where_sql, $bind_types, $bind_params, $offset);
$logs_by_ticket = fetch_logs_by_ticket($conn, array_map('intval', array_column($tickets, 'ticket_id')));

// งานที่เพิ่งซ่อมเสร็จ (7 วันล่าสุด) ให้ผู้แจ้งเห็นตอนจบงาน พร้อมรูปหลังซ่อมและใบรับรอง
$recent_done_stmt = db_prepare(
    $conn,
    "SELECT id, ticket_no, location_note, resolution_summary, completed_at, completion_image
       FROM tickets
      WHERE requester_id = ? AND status = 'completed' AND completed_at >= NOW() - INTERVAL 7 DAY
      ORDER BY completed_at DESC LIMIT 5"
);
$recent_done_stmt->bind_param('i', $user_id);
$recent_done_stmt->execute();
$recent_done = $recent_done_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recent_done_stmt->close();

$initial = mb_strtoupper(mb_substr(trim((string)$user['first_name']), 0, 1, 'UTF-8'), 'UTF-8');
$profileMeta = trim((USER_TYPE_LABELS[$user['user_type']] ?? (string)$user['user_type'])
    . (!empty($user['department_name']) ? ' · ' . $user['department_name'] : ''));
$hasFilter = ($filter !== '' || $search !== '');
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ใบงานของฉัน | REPS</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/logo/reps-icon.svg">
    <link rel="alternate icon" type="image/png" sizes="32x32" href="assets/images/logo/favicon-32.png">

    <!-- Shared Typography Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/app.css?v=<?= (int)filemtime(__DIR__ . '/assets/css/app.css') ?>">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Kanit', 'sans-serif'], kanit: ['Kanit', 'sans-serif'] } } } };</script>

    <style>
        :root {
            --admin-bg: #FAFAF8;
            --admin-sidebar: #F7F6F4;
            --admin-tray-line: #EAE8E4;
            --admin-card: #FFFFFF;
            --admin-line: #ECEAE6;
            --admin-line-strong: #D9D6D0;
            --admin-ink: #161616;
            --admin-muted: #6B6862;
            --admin-soft: #F7F6F4;
        }

        body.admin-shell {
            background: var(--admin-bg);
            color: var(--admin-ink);
            font-family: 'Kanit', sans-serif;
        }


        /* ===== Footer ===== */
        .reps-footer {
            margin-top: auto;
            border-top: 1px solid #ECEAE6;
            background: #FFFFFF;
        }
        .reps-footer-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            max-width: 72rem;
            margin: 0 auto;
            padding: 20px 28px;
            color: #6B6862;
            font-family: 'Kanit', sans-serif;
            font-size: 12px;
            line-height: 1.5;
        }
        .reps-footer-tag {
            font-family: 'Kanit', sans-serif;
            font-size: 11px;
            letter-spacing: .06em;
            text-transform: uppercase;
            white-space: nowrap;
        }
        @media (max-width: 640px) {
            .reps-footer-inner { padding: 16px; flex-direction: column; align-items: flex-start; gap: 4px; }
        }

        .admin-topbar {
            background: #FFFFFF !important;
            border-color: var(--admin-line) !important;
            box-shadow: 0 1px 2px rgba(22, 22, 22, .035);
        }

        .admin-topbar .admin-brand {
            color: var(--admin-ink);
            letter-spacing: -.01em;
        }

        .admin-topbar .admin-brand-divider {
            width: 1px;
            height: 22px;
            background: var(--admin-line);
        }

        .admin-topbar .admin-brand-label {
            color: var(--admin-ink);
            font-family: 'Kanit', sans-serif;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .14em;
            text-transform: uppercase;
        }

        .admin-topbar .admin-brand-sub {
            display: block;
            margin-top: 2px;
            color: var(--admin-muted);
            font-family: 'Kanit', sans-serif;
            font-size: 11px;
            font-weight: 400;
            letter-spacing: .12em;
        }

        .admin-topbar .admin-user-name {
            color: var(--admin-ink);
            font-size: 13px;
            font-weight: 500;
        }

        .admin-topbar .admin-user-role {
            color: var(--admin-muted);
            font-family: 'Kanit', sans-serif;
            font-size: 11px;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .admin-topbar .admin-user-avatar {
            background: var(--admin-soft);
            border-color: var(--admin-line);
            color: #55534F;
            font-family: 'Kanit', sans-serif;
            font-size: 13px;
            font-weight: 600;
            box-shadow: inset 0 0 0 3px #fff;
        }

        /* ===== Page Header & Section Titles ===== */
        .admin-page-head {
            margin-bottom: 24px;
        }

        .admin-page-title {
            color: var(--admin-ink);
            font-size: 28px;
            font-weight: 400;
            line-height: 1.2;
            letter-spacing: -.02em;
        }

        .admin-page-sub {
            margin-top: 4px;
            color: var(--admin-muted);
            font-size: 13px;
        }

        .admin-section-title {
            color: var(--admin-ink);
            font-size: 18px;
            font-weight: 500;
            letter-spacing: -.01em;
        }

        .admin-section-sub {
            color: var(--admin-muted);
            font-size: 12px;
            margin-top: 2px;
        }

        /* ===== Stat Cards ===== */
        .admin-stat-card-wrapper {
            background: #F7F6F4;
            border: 1px solid #EAE8E4;
            border-radius: 18px;
            padding: 5px;
            transition: all .2s ease;
            text-decoration: none;
            display: block;
        }

        .admin-stat-card-wrapper:hover {
            border-color: #D9D6D0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }

        .admin-stat-card-wrapper.is-active {
            box-shadow: 0 0 0 2px #161616;
        }

        .admin-stat-inner {
            background: #FFFFFF;
            border-radius: 14px;
            padding: 16px 18px 14px;
            border: 1px solid #ECEAE6;
        }

        .admin-stat-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 14px 6px;
            font-size: 11px;
            color: #6B6862;
        }

        /* ===== Stat Dots ===== */
        .stat-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .stat-dot.dot-black { background-color: #161616; }
        .stat-dot.dot-amber { background-color: #D97706; }
        .stat-dot.dot-blue { background-color: #2563EB; }

        /* ===== Buttons & Inputs ===== */
        .admin-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 38px;
            padding: 0 14px;
            border: 1px solid var(--admin-ink);
            border-radius: 8px;
            background: var(--admin-ink);
            color: #FFFFFF;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            white-space: nowrap;
            box-shadow: 0 1px 1px rgba(22, 22, 22, .08);
            cursor: pointer;
            transition: background-color .15s ease, border-color .15s ease;
        }

        .admin-btn:hover {
            background: #2A2927;
            border-color: #2A2927;
            color: #FFFFFF;
        }

        .admin-btn-secondary {
            background: #FFFFFF;
            border-color: var(--admin-line-strong);
            color: #55534F;
            box-shadow: none;
        }

        .admin-btn-secondary:hover {
            background: var(--admin-soft);
            border-color: #C9C6C0;
            color: var(--admin-ink);
        }

        .admin-btn-danger-outline {
            background: #FFFFFF;
            border-color: #F2D4D0;
            color: #B42318;
            box-shadow: none;
        }

        .admin-btn-danger-outline:hover {
            background: #FEF3F2;
            border-color: #E7B4AD;
            color: #8F1B13;
        }

        .admin-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            padding: 16px 18px;
            border-bottom: 1px solid var(--admin-line);
            background: #FFFFFF;
            border-radius: 14px 14px 0 0;
        }

        .admin-toolbar input[type="search"] {
            border: 1px solid var(--admin-line-strong);
            border-radius: 9px;
            background-color: #FFFFFF;
            color: var(--admin-ink);
            padding: 7px 12px 7px 32px;
            font-size: 13px;
        }

        .admin-toolbar input[type="search"]:focus {
            border-color: #7C7973;
            outline: none;
            box-shadow: 0 0 0 3px rgba(22, 22, 22, .07);
        }

        /* ===== Dashboard search ===== */
        .dashboard-search {
            position: relative;
            width: 100%;
            max-width: 360px;
        }
        .dashboard-search-icon {
            position: absolute;
            top: 50%;
            left: 13px;
            z-index: 1;
            color: #6B6862;
            font-size: 12px;
            pointer-events: none;
            transform: translateY(-50%);
        }
        .dashboard-search-input {
            display: block;
            width: 100%;
            min-height: 40px;
            padding: 9px 58px 9px 36px;
            border: 1px solid #D9D6D0;
            border-radius: 10px;
            outline: none;
            background: #FFFFFF;
            color: #161616;
            font-size: 12px;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .dashboard-search-input::placeholder { color: #6B6862; }
        .dashboard-search-input:hover { border-color: #B9B5AE; }
        .dashboard-search-input:focus { border-color: #161616; box-shadow: 0 0 0 3px rgba(22, 22, 22, .08); }
        .dashboard-search-key {
            position: absolute;
            top: 50%;
            right: 11px;
            min-width: 22px;
            padding: 2px 5px;
            border: 1px solid #EAE8E4;
            border-radius: 5px;
            background: #FAFAF8;
            color: #6B6862;
            font-family: 'Kanit', sans-serif;
            font-size: 11px;
            line-height: 1.2;
            text-align: center;
            transform: translateY(-50%);
            pointer-events: none;
        }
        .dashboard-search-clear {
            position: absolute;
            top: 50%;
            right: 10px;
            display: inline-flex;
            width: 24px;
            height: 24px;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 6px;
            background: transparent;
            color: #6B6862;
            cursor: pointer;
            transform: translateY(-50%);
        }
        .dashboard-search-clear:hover { background: #F7F6F4; color: #161616; }
        .dashboard-search.has-query .dashboard-search-key { display: none; }
        @media (max-width: 639px) {
            .dashboard-search { max-width: none; }
        }

        /* ===== Chips & Badges ===== */
        .admin-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 9px;
            border: 1px solid var(--admin-line);
            border-radius: 999px;
            background: var(--admin-soft);
            color: #55534F;
            font-size: 11.5px;
            font-weight: 500;
            white-space: nowrap;
        }

        .admin-chip::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: var(--c, #B9B5AE);
            flex: none;
        }

        .st-pending { --c: #D97706; }
        .st-in_progress, .st-accepted, .st-awaiting_intake_sign, .st-inspected_ready { --c: #2563EB; }
        .st-awaiting_signoff, .st-awaiting_requester_sign, .st-awaiting_admin_sign { --c: #0E7490; }
        .st-disputed { --c: #B45309; }
        .sign-cta { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 16px; margin: 6px 0 12px; padding: 12px 14px; border: 1px solid #F1D9BF; border-radius: 12px; background: #FFFBF5; color: #7C2D12; font-size: 14px; text-decoration: none; }
        .sign-cta-btn { display: inline-flex; align-items: center; gap: 8px; min-height: 40px; padding: 0 14px; border-radius: 10px; background: var(--theme-primary, #161616); color: #fff; font-weight: 500; }
        .st-completed { --c: #12A150; }
        .st-cancelled { --c: #6B6862; }

        /* ===== Urgency Chips (ปรับใหม่สไตล์มินิมอล) ===== */
        .admin-chip.urgency-urgent {
            background: #161616;
            border-color: #161616;
            color: #FFFFFF;
            font-weight: 500;
        }
        .admin-chip.urgency-urgent::before {
            background: #FFFFFF;
        }

        .admin-chip.urgency-normal {
            background: #F7F6F4;
            border-color: #ECEAE6;
            color: #55534F;
            font-weight: 500;
        }
        .admin-chip.urgency-normal::before {
            background: #6B6862;
        }

        .admin-chip.urgency-low {
            background: #FAFAF8;
            border-color: #EAE8E4;
            color: #6B6862;
            font-weight: 400;
        }
        .admin-chip.urgency-low::before {
            background: #B9B5AE;
        }

        /* ===== Minimal Ticket Cards ===== */
        .ticket-card {
            background: #FFFFFF;
            border: 1px solid #EAE8E4;
            border-radius: 12px;
            padding: 20px;
            transition: border-color .2s ease;
        }

        .ticket-card:hover {
            border-color: #C9C6C0;
        }

        /* ===== Minimal Visual Stepper ===== */
        .visual-stepper-container {
            margin-top: 16px;
            padding: 16px;
            background: #FAFAF8;
            border: 1px solid #EAE8E4;
            border-radius: 10px;
        }

        .visual-stepper {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            position: relative;
            width: 100%;
        }

        @media (max-width: 640px) {
            .visual-stepper {
                flex-direction: column;
                gap: 12px;
            }

            .visual-stepper-line {
                display: none;
            }
        }

        .visual-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            flex: 1;
            text-align: center;
        }

        .visual-step-node {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #FFFFFF;
            border: 2px solid #D9D6D0;
            color: #6B6862;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            transition: all .2s ease;
        }

        /* Completed step */
        .visual-step.is-on .visual-step-node {
            border-color: #161616;
            background: #161616;
            color: #FFFFFF;
        }

        /* Current step */
        .visual-step.is-now .visual-step-node {
            border-color: #161616;
            background: #161616;
            color: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(22, 22, 22, 0.1);
        }

        /* Cancelled step */
        .visual-step.is-cancelled .visual-step-node {
            border-color: #6B6862;
            background: #6B6862;
            color: #FFFFFF;
        }

        .visual-step-content {
            margin-top: 8px;
        }

        .visual-step-title {
            font-size: 12px;
            font-weight: 400;
            color: #6B6862;
            line-height: 1.3;
        }

        .visual-step.is-on .visual-step-title,
        .visual-step.is-now .visual-step-title {
            color: #161616;
            font-weight: 400;
        }

        .visual-step.is-cancelled .visual-step-title {
            color: #6B6862;
        }

        .visual-step-time {
            font-family: 'Kanit', sans-serif;
            font-size: 11px;
            color: #B9B5AE;
            margin-top: 2px;
            overflow-wrap: anywhere;
        }

        .visual-step.is-on .visual-step-time,
        .visual-step.is-now .visual-step-time {
            color: #6B6862;
        }

        /* Progress line */
        .visual-stepper-line {
            position: absolute;
            top: 16px;
            left: calc(50% / var(--total-steps, 3));
            right: calc(50% / var(--total-steps, 3));
            height: 2px;
            background: #D9D6D0;
            z-index: 1;
        }

        .visual-stepper-line-progress {
            height: 100%;
            width: var(--progress, 0%);
            background: #161616;
            transition: width .3s ease;
        }

        /* Mobile: เปลี่ยนเป็น stepper แนวตั้งเพื่อไม่ให้ชื่อและวันที่เบียดกัน */
        @media (max-width: 640px) {
            .visual-stepper {
                align-items: stretch;
                justify-content: flex-start;
                gap: 0;
                padding: 0 2px;
            }

            .visual-stepper-line {
                display: block;
                top: 16px;
                right: auto;
                bottom: 16px;
                left: 17px;
                width: 2px;
                height: auto;
            }

            .visual-stepper-line-progress {
                width: 100%;
                height: var(--progress, 0%);
                transition: height .3s ease;
            }

            .visual-step {
                display: grid;
                grid-template-columns: 32px minmax(0, 1fr);
                align-items: start;
                column-gap: 12px;
                flex: none;
                min-width: 0;
                padding-bottom: 16px;
                text-align: left;
            }

            .visual-step:last-child { padding-bottom: 0; }
            .visual-step-content { min-width: 0; margin-top: 4px; }
            .visual-step-title { line-height: 1.4; }
            .visual-step-time { margin-top: 4px; line-height: 1.35; }
        }

         /* ===== Minimalist Dot Vertical Stepper ===== */
         .log-stepper {
            list-style: none;
            margin: 0;
            padding: 4px 0 0;
         }

         .log-step {
            position: relative;
            display: grid;
            grid-template-columns: 14px minmax(0, 1fr);
            column-gap: 10px;
            min-width: 0;
            padding-bottom: 18px;
         }

         .log-step:last-child { padding-bottom: 0; }

         /* เส้นเชื่อมอยู่กึ่งกลางคอลัมน์ node จึงไม่เหลื่อมเมื่อข้อความขึ้นหลายบรรทัด */
         .log-step::before {
            content: '';
            position: absolute;
            top: 14px;
            bottom: 0;
            left: 6px;
            width: 1px;
            border-radius: 999px;
            background: #E5E2DC;
         }

         .log-step:last-child::before { display: none; }

         .log-step-node {
            position: relative;
            z-index: 1;
            display: flex;
            width: 8px;
            height: 8px;
            margin-top: 6px;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 50%;
            background: #161616;
            color: transparent;
            font-size: 0;
         }

         .log-step-node i { display: none; }

         .log-step.is-complete .log-step-node,
         .log-step.is-current .log-step-node,
         .log-step.is-cancelled .log-step-node {
            border: 0;
            background: #161616;
            color: transparent;
            box-shadow: none;
         }

         .log-step-body { min-width: 0; padding-top: 1px; line-height: 1.45; }
         .log-step-title { color: #161616; font-size: 12px; font-weight: 500; line-height: 1.35; }
         .log-step-meta { margin-top: 3px; color: #73706A; font-size: 11px; overflow-wrap: anywhere; }
         .log-step-time { margin-top: 4px; color: #6B6862; font-family: 'Kanit', sans-serif; font-size: 11px; }

         @media (max-width: 480px) {
            .log-step { grid-template-columns: 14px minmax(0, 1fr); column-gap: 9px; padding-bottom: 16px; }
            .log-step-node { width: 8px; height: 8px; margin-top: 6px; }
            .log-step::before { left: 6px; top: 14px; }
         }
        /* ===== Edit ticket modal ===== */
        dialog {
            border: 1px solid #D9D6D0;
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.18), 0 8px 24px rgba(0, 0, 0, 0.08);
            animation: modalSlideIn 0.25s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        dialog::backdrop {
            background-color: rgba(22, 22, 22, 0.5);
            backdrop-filter: blur(4px);
            animation: backdropFadeIn 0.25s ease-out;
        }

        @keyframes backdropFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .edit-modal {
            width: min(760px, calc(100vw - 24px));
            height: min(90dvh, 850px);
            max-height: min(90vh, 850px);
            overflow: hidden;
            padding: 0;
            border-radius: 20px;
            background: #FFFFFF;
        }

        .edit-modal > form {
            display: flex;
            height: 100%;
            min-height: 0;
            flex-direction: column;
        }

        .edit-modal-body {
            min-height: 0;
            max-height: none;
            flex: 1 1 auto;
            overflow-y: auto;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
            padding: 24px;
            /* แถบเลื่อนใช้สไตล์กลางจาก base.css */
        }

        .edit-modal-header {
            padding: 22px 24px 18px;
            border-bottom: 1px solid #ECEAE6;
            background: linear-gradient(135deg, #FAFAF8 0%, #FFFFFF 72%);
        }

        .edit-modal-title-row,
        .edit-modal-footer,
        .edit-modal-section-head,
        .edit-modal-field-label,
        .edit-modal-meta,
        .edit-modal-progress,
        .edit-modal-check {
            display: flex;
            align-items: center;
        }

        .edit-modal-title-row { justify-content: space-between; gap: 16px; }
        .edit-modal-eyebrow {
            color: #6B6862;
            font-family: 'Kanit', sans-serif;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .edit-modal-title {
            margin: 4px 0 0;
            color: #161616;
            font-size: 20px;
            font-weight: 600;
            letter-spacing: -.025em;
        }
        .edit-modal-subtitle {
            margin: 5px 0 0;
            color: #73706A;
            font-size: 12px;
            line-height: 1.55;
        }
        .edit-modal-close {
            display: inline-flex;
            width: 36px;
            height: 36px;
            flex: none;
            align-items: center;
            justify-content: center;
            border: 1px solid #EAE8E4;
            border-radius: 10px;
            background: #FFFFFF;
            color: #6B6862;
            cursor: pointer;
            transition: .15s ease;
        }
        .edit-modal-close:hover { border-color: #B9B5AE; color: #161616; background: #F7F6F4; }

        .edit-modal-meta {
            flex-wrap: wrap;
            gap: 8px 16px;
            margin-top: 16px;
            color: #73706A;
            font-size: 11px;
        }
        .edit-modal-meta-item { display: inline-flex; align-items: center; gap: 6px; }
        .edit-modal-meta-item i { color: #6B6862; font-size: 11px; }
        .edit-modal-ticket-no { color: #161616; font-family: 'Kanit', sans-serif; font-weight: 600; }
        .edit-modal-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 9px;
            border: 1px solid #ECEAE6;
            border-radius: 999px;
            background: #F7F6F4;
            color: #55534F;
            font-size: 11px;
            font-weight: 600;
        }
        .edit-modal-status::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: #D97706; }

        .edit-modal-progress {
            gap: 8px;
            margin-top: 20px;
            color: #6B6862;
            font-family: 'Kanit', sans-serif;
            font-size: 11px;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .edit-modal-progress-item { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
        .edit-modal-progress-item.is-active { color: #161616; font-weight: 600; }
        .edit-modal-progress-dot { width: 8px; height: 8px; border: 2px solid #D9D6D0; border-radius: 50%; }
        .edit-modal-progress-item.is-active .edit-modal-progress-dot { border-color: #161616; background: #161616; }
        .edit-modal-progress-line { height: 1px; flex: 1; background: #EAE8E4; }

        .edit-modal-alert {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            margin-bottom: 20px;
            padding: 12px 14px;
            border: 1px solid #E7E5E0;
            border-radius: 12px;
            background: #FAFAF8;
            color: #55534F;
            font-size: 11px;
            line-height: 1.55;
        }
        .edit-modal-alert i { margin-top: 2px; color: #7C7973; }

        .edit-modal-section { margin-top: 24px; }
        .edit-modal-section:first-of-type { margin-top: 0; }
        .edit-modal-section-head { justify-content: space-between; gap: 12px; margin-bottom: 12px; }
        .edit-modal-section-title { display: flex; align-items: center; gap: 9px; color: #161616; font-size: 13px; font-weight: 600; }
        .edit-modal-section-title i { color: #6B6862; font-size: 12px; }
        .edit-modal-section-note { color: #6B6862; font-size: 11px; }
        .edit-modal-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .edit-modal-field { min-width: 0; }
        .edit-modal-field.is-full { grid-column: 1 / -1; }
        .edit-modal-field-label { justify-content: space-between; gap: 8px; margin-bottom: 7px; color: #55534F; font-size: 12px; font-weight: 600; }
        .edit-modal-required { color: #C2413A; font-size: 11px; }
        .edit-modal-control {
            display: block;
            width: 100%;
            border: 1px solid #D9D6D0;
            border-radius: 10px;
            outline: none;
            background: #FFFFFF;
            color: #161616;
            font: inherit;
            font-size: 13px;
            transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
        }
        .edit-modal-control[type="text"], .edit-modal-control select { min-height: 43px; padding: 10px 12px; }
        .edit-modal-control:not(textarea) { min-height: 43px; padding: 10px 12px; }
        textarea.edit-modal-control { min-height: 150px; padding: 12px; resize: vertical; line-height: 1.65; }
        .edit-modal-control::placeholder { color: #B9B5AE; }
        .edit-modal-control:hover { border-color: #B9B5AE; }
        .edit-modal-control:focus { border-color: #161616; box-shadow: 0 0 0 3px rgba(22, 22, 22, .08); }
        .edit-modal-control.is-invalid { border-color: #C2413A; box-shadow: 0 0 0 3px rgba(194, 65, 58, .08); }
        .edit-modal-help { margin-top: 6px; color: #6B6862; font-size: 11px; line-height: 1.5; }
        .edit-modal-help i { margin-right: 4px; color: #AAA69F; }
        .edit-modal-counter { color: #6B6862; font-family: 'Kanit', sans-serif; font-size: 11px; font-weight: 400; }
        .edit-modal-counter.is-near-limit { color: #B45309; }
        .edit-modal-counter.is-limit { color: #B42318; }

         /* Urgency Selector - Clean & Minimal Radio Card Structure */
         .urgency-selector-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
         }

         @media (max-width: 600px) {
            .urgency-selector-grid {
               grid-template-columns: 1fr;
            }
         }

         .urgency-card-option {
            position: relative;
            display: block;
            cursor: pointer;
         }

         /* ซ่อน native radio แต่อยู่ในตำแหน่งเดิมเพื่อให้คลิกง่าย */
         .urgency-card-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
            pointer-events: none;
         }

         /* โครงสร้างตัวกล่องการ์ด */
         .urgency-card-box {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 14px 16px;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            background-color: #FFFFFF;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
            min-height: 72px;
         }

         /* Hover State */
         .urgency-card-option:hover .urgency-card-box {
            border-color: #D1D5DB;
            background-color: #FAFAFA;
         }

         /* Custom Radio Circle */
         .urgency-radio-circle {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 1.5px solid #D1D5DB;
            border-radius: 50%;
            background-color: #FFFFFF;
            flex-shrink: 0;
            margin-top: 2px;
            position: relative;
            transition: border-color 0.15s ease;
         }

         /* Checked State - ขอบดำและพื้นหลังอ่อนตามรูปตัวอย่าง */
         .urgency-card-option input[type="radio"]:checked + .urgency-card-box {
            border: 2px solid #111827;
            background-color: #F9FAFB;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
         }

         .urgency-card-option input[type="radio"]:checked + .urgency-card-box .urgency-radio-circle {
            border: 5px solid #111827;
            background-color: #FFFFFF;
         }

         /* Typography Inside Card */
         .urgency-card-content {
            display: flex;
            flex-direction: column;
         }

         .urgency-card-title {
            font-size: 13px;
            font-weight: 600;
            color: #111827;
            line-height: 1.3;
         }

         .urgency-card-desc {
            font-size: 11px;
            color: #9CA3AF;
            margin-top: 2px;
            line-height: 1.3;
         }

        .edit-modal-footer {
            flex: none;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 24px;
            border-top: 1px solid #ECEAE6;
            background: #FAFAF8;
        }
        .edit-modal-footer-note { display: flex; align-items: center; gap: 7px; color: #6B6862; font-size: 11px; line-height: 1.45; }
        .edit-modal-footer-note i { color: #7C7973; }
        .edit-modal-actions { display: flex; align-items: center; gap: 8px; flex: none; }
        .edit-modal-actions .admin-btn { min-height: 40px; }
        .edit-modal-submit.is-loading { cursor: wait; opacity: .7; }

        @media (max-width: 600px) {
            .edit-modal { width: calc(100vw - 16px); height: min(92dvh, 850px); max-height: 92vh; border-radius: 16px; }
            .edit-modal-header, .edit-modal-body { padding-left: 16px; padding-right: 16px; }
            .edit-modal-footer { align-items: flex-start; flex-direction: column; padding: 12px 16px; }
            .edit-modal-actions { width: 100%; }
            .edit-modal-actions .admin-btn { flex: 1; }
            .edit-modal-grid, .edit-modal-urgency-grid { grid-template-columns: 1fr; }
            .edit-modal-field.is-full { grid-column: auto; }
            .edit-modal-progress { gap: 5px; font-size: 11px; }
            .edit-modal-progress-line { min-width: 8px; }
        }

        @media (prefers-reduced-motion: reduce) {
            dialog, dialog::backdrop { animation: none; }
            .edit-modal-control, .edit-modal-urgency-card, .edit-modal-close { transition: none; }
        }

        /* Dropdown reset */
        details summary::-webkit-details-marker { display: none; }
    </style>
   <link rel="stylesheet" href="assets/css/base.css?v=18">
</head>

<body class="admin-shell min-h-screen flex flex-col antialiased">

      <!-- Topbar Header -->
      <header class="admin-topbar sticky top-0 z-30 h-header border-b w-full bg-white">
         <div class="max-w-6xl w-full mx-auto px-4 sm:px-7 flex items-center justify-between h-full">
            <a href="dashboard.php" class="admin-brand flex items-center gap-3 no-underline">
                  <img src="assets/images/logo/reps-logo.svg" alt="REPS" class="h-6 w-auto">
            </a>

            <div class="flex items-center gap-3">
				 <a href="request_form.php" class="admin-btn rounded-full">
                 	<i class="fa-solid fa-plus text-xs rounded-full" aria-hidden="true"></i>
                 	<span><?php require_once __DIR__ . '/includes/i18n.php'; echo tr('แจ้งซ่อมใหม่', 'New request'); ?></span>
               	</a>
                  <?php require_once __DIR__ . '/includes/notifications.php'; echo notif_bell($conn, ''); ?>
                  <?php require_once __DIR__ . '/includes/user_menu.php'; echo user_menu_render($conn, ''); ?>
            </div>
         </div>
      </header>

    <!-- Main Container -->
    <main class="flex-1 max-w-6xl w-full mx-auto p-4 sm:p-7">

        <!-- Page Title & Breadcrumb -->
        <header class="admin-page-head">
            <h1 class="admin-page-title"><?= tr('สวัสดีคุณ', 'Hello,') ?> <span><?= h($user['first_name']) ?></span></h1>
            <p class="admin-page-sub"><?= tr('จัดการและติดตามสถานะใบแจ้งซ่อมอุปกรณ์คอมพิวเตอร์และไอทีของคุณ', 'Manage and track your computer and IT repair requests') ?></p>
        </header>

        <!-- Flash Notice -->
        <?php if ($flash !== ''): ?>
            <div role="status" class="mb-6 flex items-center gap-3 p-4 rounded-xl border text-sm font-medium <?= $flashType === 'error' ? 'border-rose-200 bg-rose-50 text-rose-900' : ($flashType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-neutral-200 bg-white text-neutral-800') ?>">
                <i class="fa-solid <?= $flashType === 'error' ? 'fa-circle-exclamation text-rose-600' : ($flashType === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-info text-neutral-500') ?>" aria-hidden="true"></i>
                <span><?= h($flash) ?></span>
            </div>
        <?php endif; ?>

        <!-- Stat Cards Section -->
        <section class="mb-6" aria-labelledby="stats-heading">
            <h2 id="stats-heading" class="sr-only">สรุปสถานะใบงานซ่อม</h2>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">

                <!-- Card 1: ใบงานเปิดอยู่ -->
                <a href="<?= h(page_url(['q' => $search])) ?>" class="admin-stat-card-wrapper <?= $filter === '' ? 'is-active' : '' ?>">
                    <div class="admin-stat-inner">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs text-[#6B6862] font-normal">ใบงานเปิดอยู่</span>
                            <span class="stat-dot dot-black"></span>
                        </div>
                        <div class="flex items-baseline justify-between">
                            <div class="flex items-baseline gap-1.5">
                                <span class="font-mono text-3xl font-semibold text-[#161616] leading-none"><?= number_format($total_tickets) ?></span>
                                <span class="text-xs text-[#6B6862]">เคส</span>
                            </div>
                        </div>
                    </div>
                    <div class="admin-stat-footer">
                        <span class="ml-auto text-[#161616] font-normal">ยังไม่เสร็จทั้งหมด</span>
                    </div>
                </a>

                <!-- Card 2: รอรับเรื่อง -->
                <a href="<?= h(page_url(['status' => 'pending', 'q' => $search])) ?>" class="admin-stat-card-wrapper <?= $filter === 'pending' ? 'is-active' : '' ?>">
                    <div class="admin-stat-inner">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs text-[#6B6862] font-normal">รอรับเรื่อง</span>
                            <span class="stat-dot dot-amber"></span>
                        </div>
                        <div class="flex items-baseline justify-between">
                            <div class="flex items-baseline gap-1.5">
                                <span class="font-mono text-3xl font-semibold text-[#161616] leading-none"><?= number_format($counts['pending']) ?></span>
                                <span class="text-xs text-[#6B6862]">เคส</span>
                            </div>
                        </div>
                    </div>
                    <div class="admin-stat-footer">
                        <span class="ml-auto text-[#B45309] font-normal">รอสตาฟกดรับงาน</span>
                    </div>
                </a>

                <!-- Card 3: กำลังดำเนินการ -->
                <a href="<?= h(page_url(['status' => 'in_progress', 'q' => $search])) ?>" class="admin-stat-card-wrapper <?= $filter === 'in_progress' ? 'is-active' : '' ?>">
                    <div class="admin-stat-inner">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs text-[#6B6862] font-normal">กำลังดำเนินการ</span>
                            <span class="stat-dot dot-blue"></span>
                        </div>
                        <div class="flex items-baseline justify-between">
                            <div class="flex items-baseline gap-1.5">
                                <span class="font-mono text-3xl font-semibold text-[#161616] leading-none"><?= number_format($counts['in_progress']) ?></span>
                                <span class="text-xs text-[#6B6862]">เคส</span>
                            </div>
                        </div>
                    </div>
                    <div class="admin-stat-footer">
                        <span class="ml-auto text-[#1D4ED8] font-normal">ช่างกำลังซ่อม</span>
                    </div>
                </a>

                <!-- Card 4: รอครูรับรอง -->
                <a href="<?= h(page_url(['status' => 'awaiting_signoff', 'q' => $search])) ?>" class="admin-stat-card-wrapper <?= $filter === 'awaiting_signoff' ? 'is-active' : '' ?>">
                    <div class="admin-stat-inner">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs text-[#6B6862] font-normal">รอครูรับรอง</span>
                            <span class="stat-dot dot-black"></span>
                        </div>
                        <div class="flex items-baseline justify-between">
                            <div class="flex items-baseline gap-1.5">
                                <span class="font-mono text-3xl font-semibold text-[#161616] leading-none"><?= number_format($counts['awaiting_signoff']) ?></span>
                                <span class="text-xs text-[#6B6862]">เคส</span>
                            </div>
                        </div>
                    </div>
                    <div class="admin-stat-footer">
                        <span class="ml-auto text-[#161616] font-normal">ซ่อมแล้ว รอตรวจรับ</span>
                    </div>
                </a>
            </div>
        </section>

        <!-- Main Content Area -->
        <section class="w-full" aria-labelledby="tickets-heading">

            <!-- Header Toolbar -->
            <div class="bg-white rounded-2xl border border-neutral-200 p-4 mb-4 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-neutral-100">
                    <div>
                        <h3 id="tickets-heading" class="admin-section-title">รายการใบแจ้งซ่อมของฉัน</h3>
                        <p class="admin-section-sub">ติดตามสถานะความคืบหน้าและประวัติของแต่ละใบงาน</p>
                    </div>
                    <span class="font-mono text-xs text-neutral-500">ทั้งหมด <?= number_format($total_filtered) ?> รายการ</span>
                </div>

                <!-- Toolbar: Search & Filters -->
                <div class="pt-3 flex flex-col sm:flex-row justify-between gap-3">
                    <form method="GET" action="dashboard.php" class="dashboard-search <?= $search !== '' ? 'has-query' : '' ?>" role="search">
                        <?php if ($filter !== ''): ?>
                            <input type="hidden" name="status" value="<?= h($filter) ?>">
                        <?php endif; ?>
                        <label class="sr-only" for="searchInput">ค้นหาใบแจ้งซ่อม</label>
                        <i class="fa-solid fa-magnifying-glass dashboard-search-icon" aria-hidden="true"></i>
                        <input id="searchInput" class="dashboard-search-input" type="search" name="q" value="<?= h($search) ?>" maxlength="<?= SEARCH_MAX_LENGTH ?>" autocomplete="off" placeholder="ค้นหาเลขใบงาน อาการ หรือสถานที่...">
                        <?php if ($search !== ''): ?>
                            <button type="button" class="dashboard-search-clear" data-clear-search aria-label="ล้างคำค้นหา"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                        <?php else: ?>
                            <span class="dashboard-search-key" aria-hidden="true">/</span>
                        <?php endif; ?>
                    </form>

                    <?php if ($hasFilter): ?>
                        <a href="dashboard.php" class="inline-flex items-center min-h-[44px] px-3 text-sm text-neutral-700 hover:text-neutral-900 underline underline-offset-4 whitespace-nowrap">ล้างตัวกรอง</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Separated Ticket List -->
            <?php if ($tickets === []): ?>
                <div class="bg-white rounded-2xl border border-neutral-200 p-12 text-center">
                    <i class="fa-solid fa-inbox text-3xl text-neutral-300 mb-3" aria-hidden="true"></i>
                    <div class="text-sm font-medium text-neutral-800">
                        <?= $search !== '' ? 'ไม่พบใบงานที่ตรงกับคำค้นหา' : ($filter !== '' ? 'ไม่มีรายการในสถานะนี้' : 'ยังไม่มีใบแจ้งซ่อม') ?>
                    </div>
                    <p class="text-xs text-neutral-500 mt-1 mb-4">
                        <?= $hasFilter ? 'ลองล้างตัวกรองเพื่อดูรายการอื่นของคุณ' : 'เมื่อมีการสร้างใบแจ้งซ่อม รายการจะปรากฏในหน้านี้' ?>
                    </p>
                    <?php if (!$hasFilter): ?>
                        <a href="request_form.php" class="admin-btn text-xs inline-flex">
                            <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i>
                            <span>สร้างใบแจ้งซ่อมใหม่</span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($tickets as $t):
                        $status = status_of($t['status'] ?? null);
                        $isPending = $status === TicketStatus::Pending;
                        $hasTech = !empty($t['tech_name']);
                        $can_edit = $isPending;
                        $can_cancel = $isPending && !$hasTech;
                        $logs = $logs_by_ticket[$t['ticket_id']] ?? [];
                        $trk = build_tracker($t, $logs);
                        $histId = 'hist-' . (int)$t['ticket_id'];

                        // คำนวณความคืบหน้าสำหรับเส้น Stepper
                        $completedSteps = 0;
                        foreach ($trk as $idx => $step) {
                            if ($step['on']) {
                                $completedSteps = $idx;
                            }
                        }
                        $totalStepCount = count($trk);
                        $progressPercent = $totalStepCount > 1 ? ($completedSteps / ($totalStepCount - 1)) * 100 : 0;

                        // Timeline สำหรับประวัติสถานะ (Vertical Stepper)
                        $timeline = build_log_timeline($t, $logs);
                        $timelineLast = count($timeline) - 1;
                        $urgencyClass = match ($t['urgency'] ?? 'normal') {
                            'urgent' => 'urgency-urgent',
                            default => 'urgency-normal',
                        };
                    ?>
                        <!-- Ticket Card -->
                        <article class="ticket-card">
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                <div class="flex items-center gap-2">
                                    <a href="dashboard.php?ticket=<?= rawurlencode((string)$t['ticket_no']) ?>" class="font-mono text-xs font-semibold text-neutral-900 hover:underline">
                                        #<?= h($t['ticket_no']) ?>
                                    </a>
                                    <span class="text-neutral-300">·</span>
                                    <span class="font-mono text-[11px] text-neutral-500"><?= h(fmt($t['created_at'])) ?></span>
                                </div>
                                <span class="admin-chip st-<?= h($t['status']) ?>">
                                    <?= h(status_label($t['status'])) ?>
                                </span>
                            </div>
                            <?php if ($status?->needsRequesterSign()): ?>
                                <a href="sign.php?id=<?= (int)$t['ticket_id'] ?>" class="sign-cta">
                                    <span><b><?= $status === TicketStatus::AwaitingIntakeSign ? 'ช่างรับอุปกรณ์แล้ว กรุณาเซ็นรับทราบรายการ' : 'ซ่อมเสร็จแล้ว กรุณาตรวจและเซ็นรับอุปกรณ์คืน' ?></b></span>
                                    <span class="sign-cta-btn">ไปเซ็น <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                                </a>
                            <?php elseif ($status !== null && $status->phase() !== TicketStatus::Pending): ?>
                                <a href="sign.php?id=<?= (int)$t['ticket_id'] ?>" class="text-xs underline text-neutral-600">ดูเช็คลิสต์และลายเซ็น</a>
                            <?php endif; ?>

                            <h3 class="text-lg font-semibold text-neutral-900 mb-1 flex items-center gap-2">
                                <span><?= h($t['category_name'] ?? 'ไม่ระบุหมวดหมู่') ?></span>
                                <span class="admin-chip <?= $urgencyClass ?> !text-[10.5px] !py-0.5 !px-2">
                                    <?= h(urgency_label($t['urgency'])) ?>
                                </span>
                            </h3>

                            <p class="text-base text-neutral-600 line-clamp-2 mb-3 leading-relaxed">
                                <?= h($t['symptoms']) ?>
                            </p>

                            <div class="flex flex-wrap items-center gap-6 text-xs text-neutral-500 mb-3">
                                <span><i class="fa-solid fa-location-dot text-neutral-500 mr-2" aria-hidden="true"></i><?= h($t['location_name']) ?></span>
                                <span><i class="fa-solid fa-user-gear text-neutral-500 mr-2" aria-hidden="true"></i>ผู้รับผิดชอบ: <strong class="text-neutral-700 font-medium"><?= $hasTech ? h($t['tech_name']) : 'ยังไม่มีผู้รับงาน' ?></strong></span>
                                <?php if ($t['status'] === 'pending'):
                                    try { $queuePos = queue_public_status($conn, (int)$t['ticket_id'])['position']; } catch (Throwable $e) { $queuePos = null; } ?>
                                    <?php if ($queuePos): ?>
                                        <span class="font-medium text-amber-700"><i class="fa-solid fa-list-ol mr-2" aria-hidden="true"></i>คุณเป็นคิวที่ <?= (int)$queuePos ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if (!empty($t['completion_image'])): ?>
                                    <a class="underline text-neutral-700" href="file.php?t=<?= (int)$t['ticket_id'] ?>&amp;k=completion" target="_blank" rel="noopener"><i class="fa-regular fa-image mr-1" aria-hidden="true"></i>รูปหลังซ่อม</a>
                                <?php endif; ?>
                            </div>

                            <!-- Enhanced Visual Stepper Component -->
                            <div class="visual-stepper-container">

                                <div class="visual-stepper" style="--total-steps: <?= (int)$totalStepCount ?>;">
                                    <!-- Dynamic Progress Line -->
                                    <div class="visual-stepper-line">
                                        <div class="visual-stepper-line-progress" style="--progress: <?= (int)$progressPercent ?>%;"></div>
                                    </div>

                                    <?php foreach ($trk as $idx => $step):
                                        $stepClass = $step['now'] ? 'is-now' : ($step['on'] ? 'is-on' : '');
                                        if ($status === TicketStatus::Cancelled && $idx === 1) {
                                            $stepClass = 'is-cancelled';
                                        }
                                    ?>
                                        <div class="visual-step <?= $stepClass ?>">
                                            <div class="visual-step-node">
                                                <?php if ($step['now']): ?>
                                                    <i class="fa-solid fa-circle-dot text-[11px]" aria-hidden="true"></i><span class="sr-only">ขั้นตอนปัจจุบัน</span>
                                                <?php elseif ($step['on']): ?>
                                                    <i class="fa-solid fa-check text-[10px]" aria-hidden="true"></i>
                                                <?php else: ?>
                                                    <span><?= $idx + 1 ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="visual-step-content">
                                                <div class="visual-step-title"><?= h($step['label']) ?></div>
                                                <div class="visual-step-time"><?= $step['on'] ? ($step['when'] ? h(fmt($step['when'])) : '—') : 'รอ' ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Minimalist Card Actions -->
                            <div class="flex flex-wrap items-center justify-between gap-2 mt-4 pt-3 border-t border-neutral-100 text-sm">
                                <button type="button" class="inline-flex items-center gap-1.5 min-h-[44px] text-neutral-600 hover:text-neutral-900 border-0 bg-transparent px-0 cursor-pointer transition" data-hist aria-expanded="false" aria-controls="<?= $histId ?>">
                                    <i class="fa-solid fa-clock-rotate-left text-[11px] text-neutral-500" aria-hidden="true"></i>
                                    <span>ประวัติสถานะ (<?= count($timeline) ?>)</span>
                                    <i class="fa-solid fa-chevron-down text-[9px] text-neutral-500 transition-transform duration-200" data-hist-icon aria-hidden="true"></i>
                                </button>

                                <div class="flex items-center gap-2">
                                    <?php if ($can_edit): ?>
                                        <button type="button" class="inline-flex items-center gap-1.5 min-h-[44px] px-4 rounded-lg border border-neutral-300 bg-white text-neutral-800 hover:bg-neutral-50 cursor-pointer transition" data-edit data-id="<?= (int)$t['ticket_id'] ?>" data-no="<?= h($t['ticket_no']) ?>" data-category="<?= (int)$t['category_id'] ?>" data-category-name="<?= h($t['category_name'] ?? 'ไม่ระบุหมวดหมู่') ?>" data-location="<?= h($t['location_name']) ?>" data-symptoms="<?= h($t['symptoms']) ?>" data-urgency="<?= h($t['urgency']) ?>" data-created="<?= h(fmt($t['created_at'])) ?>">
                                            <i class="fa-solid fa-pen text-xs" aria-hidden="true"></i> แก้ไข
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($can_cancel): ?>
                                        <button type="button" class="inline-flex items-center gap-1.5 min-h-[44px] px-4 rounded-lg border border-rose-200 bg-white text-rose-700 hover:bg-rose-50 cursor-pointer transition" data-cancel data-id="<?= (int)$t['ticket_id'] ?>" data-no="<?= h($t['ticket_no']) ?>">
                                            ยกเลิกใบงาน
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Status Log Drawer (Vertical Stepper) -->
                            <div class="hidden mt-3 p-4 bg-neutral-50 rounded-xl border border-neutral-200" id="<?= $histId ?>">
                                <div class="font-medium text-neutral-800 text-xs mb-3 flex items-center gap-2">
                                    <i class="fa-solid fa-timeline text-neutral-500" aria-hidden="true"></i>
                                    <span>ประวัติสถานะ</span>
                                </div>
                                <ol class="log-stepper">
                                    <?php foreach ($timeline as $i => $item):
                                        $isLast = $i === $timelineLast;
                                        $cls = trim(($isLast ? 'is-current ' : 'is-complete ') . ($item['cancel'] ? 'is-cancelled' : ''));
                                    ?>
                                        <li class="log-step <?= $cls ?>">
                                            <div class="log-step-node">
                                                <?php if ($item['cancel']): ?>
                                                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                                <?php elseif ($isLast && $status !== TicketStatus::Completed): ?>
                                                    <i class="fa-solid fa-circle text-[5px]" aria-hidden="true"></i>
                                                <?php else: ?>
                                                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="log-step-body">
                                                <div class="log-step-title"><?= h($item['title']) ?></div>
                                                <div class="log-step-meta"><?= h($item['meta']) ?></div>
                                                <div class="log-step-time"><?= h(fmt($item['time'])) ?></div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($recent_done !== [] && $filter === '' && $search === ''): ?>
                <section class="mt-6 bg-white rounded-2xl border border-emerald-200 p-4 sm:p-5" aria-labelledby="done-heading">
                    <h3 id="done-heading" class="text-base font-semibold text-neutral-900 flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-emerald-600" aria-hidden="true"></i> ซ่อมเสร็จแล้ว (7 วันล่าสุด)
                    </h3>
                    <ul class="mt-3 divide-y divide-neutral-100">
                        <?php foreach ($recent_done as $done): ?>
                            <li class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="text-sm text-neutral-900"><span class="font-mono font-semibold">#<?= h($done['ticket_no']) ?></span> · <?= h($done['location_note']) ?></div>
                                    <div class="text-sm text-neutral-600 break-words"><?= h($done['resolution_summary'] ?: 'ซ่อมเสร็จและครูรับรองแล้ว') ?> · <?= h(fmt($done['completed_at'])) ?></div>
                                </div>
                                <div class="flex flex-wrap gap-2 shrink-0">
                                    <?php if (!empty($done['completion_image'])): ?>
                                        <a href="file.php?t=<?= (int)$done['id'] ?>&amp;k=completion" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 min-h-[44px] px-4 rounded-lg border border-neutral-300 text-sm text-neutral-800 hover:bg-neutral-50"><i class="fa-regular fa-image" aria-hidden="true"></i> รูปหลังซ่อม</a>
                                    <?php endif; ?>
                                    <a href="certificate.php?id=<?= (int)$done['id'] ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 min-h-[44px] px-4 rounded-lg border border-neutral-300 text-sm text-neutral-800 hover:bg-neutral-50"><i class="fa-solid fa-file-signature" aria-hidden="true"></i> ใบรับรองงาน</a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="history.php" class="mt-2 inline-flex items-center min-h-[44px] text-sm text-neutral-700 underline underline-offset-4">ดูประวัติทั้งหมด</a>
                </section>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($total_filtered > min(TICKETS_PER_PAGE_OPTIONS)): ?>
            <div class="mt-4 overflow-hidden rounded-2xl border border-neutral-200 bg-white">
                <?php pager_render('dashboard.php', $total_filtered, $page, TICKETS_PER_PAGE, TICKETS_PER_PAGE_OPTIONS, static fn(int $p): string => page_url(['status' => $filter, 'q' => $search, 'page' => $p]), ['status' => $filter, 'q' => $search], 'เปลี่ยนหน้าใบงาน'); ?>
            </div>
            <?php endif; ?>

        </section>

    </main>

    <?php $footerBase = ''; $footerVariant = 'light'; require __DIR__ . '/includes/site_footer.php'; ?>

    <!-- Modal: Edit Ticket -->
    <dialog id="editDialog" class="edit-modal" aria-labelledby="editDialogTitle" aria-describedby="editDialogDescription">
        <form method="post">
            <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="action" value="update_ticket">
            <input type="hidden" name="ticket_id" value="">

            <div class="edit-modal-header">
                <div class="edit-modal-title-row">
                    <div>
                        
                        <h2 id="editDialogTitle" class="edit-modal-title">แก้ไขใบแจ้งซ่อม <span class="edit-modal-ticket-no" data-fill="no"></span></h2>
                        <p id="editDialogDescription" class="edit-modal-subtitle">ตรวจสอบและปรับปรุงข้อมูลให้ทีมช่างเข้าใจปัญหาได้ตรงจุด</p>
                    </div>
                    <button type="button" class="edit-modal-close" data-close aria-label="ปิดหน้าต่างแก้ไขใบแจ้งซ่อม">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="edit-modal-meta">
                    <span class="edit-modal-meta-item"><i class="fa-regular fa-calendar" aria-hidden="true"></i> สร้างเมื่อ <strong data-fill="created">—</strong></span>
                    <span class="edit-modal-meta-item"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> แก้ไขได้ขณะรอรับเรื่อง</span>
                    <span class="edit-modal-status">รอรับเรื่อง</span>
                </div>

                <div class="edit-modal-progress" aria-label="ขั้นตอนการแก้ไขใบแจ้งซ่อม">
                    <span class="edit-modal-progress-item is-active"><span class="edit-modal-progress-dot"></span> แก้ไขข้อมูล</span>
                    <span class="edit-modal-progress-line"></span>
                    <span class="edit-modal-progress-item"><span class="edit-modal-progress-dot"></span> ตรวจสอบ</span>
                    <span class="edit-modal-progress-line"></span>
                    <span class="edit-modal-progress-item"><span class="edit-modal-progress-dot"></span> บันทึก</span>
                </div>
            </div>

            <div class="edit-modal-body">
                <div class="edit-modal-alert" role="note">
                    <i class="fa-solid fa-lightbulb" aria-hidden="true"></i>
                    <span>กรอกข้อมูลให้ละเอียด โดยเฉพาะสถานที่ อุปกรณ์ และอาการที่พบ ทีมช่างจะใช้ข้อมูลนี้เพื่อเตรียมการตรวจสอบและจัดลำดับงานให้เหมาะสม</span>
                </div>

                <section class="edit-modal-section" aria-labelledby="editLocationTitle">
                    <div class="edit-modal-section-head">
                        <h3 id="editLocationTitle" class="edit-modal-section-title"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> สถานที่และประเภทงาน</h3>
                        <span class="edit-modal-section-note">ข้อมูลสำคัญต่อการเข้าหน้างาน</span>
                    </div>
                    <div class="edit-modal-grid">
                        <div class="edit-modal-field">
                            <label class="edit-modal-field-label" for="editCategory">หมวดหมู่ปัญหา <span class="edit-modal-required">*</span></label>
                            <select id="editCategory" name="category_id" required class="edit-modal-control" aria-describedby="editCategoryHelp">
                                <option value="" disabled>เลือกหมวดหมู่</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= (int)$category['id'] ?>"><?= h($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p id="editCategoryHelp" class="edit-modal-help"><i class="fa-solid fa-circle-info" aria-hidden="true"></i>เลือกประเภทที่ใกล้เคียงกับปัญหามากที่สุด</p>
                        </div>
                        <div class="edit-modal-field">
                            <label class="edit-modal-field-label" for="editLocation">สถานที่ / อุปกรณ์ <span class="edit-modal-required">*</span></label>
                            <input id="editLocation" type="text" name="location_note" maxlength="<?= LOCATION_MAX_LENGTH ?>" required class="edit-modal-control" placeholder="เช่น อาคาร 1 ชั้น 2 ห้อง 201" aria-describedby="editLocationHelp">
                            <p id="editLocationHelp" class="edit-modal-help"><i class="fa-solid fa-circle-info" aria-hidden="true"></i>ระบุอาคาร ชั้น ห้อง หรือหมายเลขอุปกรณ์</p>
                        </div>
                    </div>
                </section>

                <section class="edit-modal-section" aria-labelledby="editSymptomTitle">
                    <div class="edit-modal-section-head">
                        <h3 id="editSymptomTitle" class="edit-modal-section-title"><i class="fa-solid fa-message" aria-hidden="true"></i> รายละเอียดปัญหา</h3>
                        <span class="edit-modal-section-note">อธิบายให้ช่างเห็นภาพ</span>
                    </div>
                    <div class="edit-modal-field">
                        <label class="edit-modal-field-label" for="editSymptoms">
                            <span>อาการที่พบและสิ่งที่ลองแก้ไขแล้ว <span class="edit-modal-required">*</span></span>
                            <span class="edit-modal-counter" data-counter="symptoms">0/<?= SYMPTOM_MAX_LENGTH ?></span>
                        </label>
                        <textarea id="editSymptoms" name="symptoms" maxlength="<?= SYMPTOM_MAX_LENGTH ?>" required class="edit-modal-control" placeholder="อธิบายอาการที่พบ ช่วงเวลาที่เริ่มเกิด ผลกระทบ และสิ่งที่ลองแก้ไขแล้ว..." aria-describedby="editSymptomsHelp"></textarea>
                        <p id="editSymptomsHelp" class="edit-modal-help"><i class="fa-solid fa-circle-info" aria-hidden="true"></i>หากมีข้อความ Error ให้คัดลอกมาใส่ด้วย และระบุอุปกรณ์ที่ได้รับผลกระทบ</p>
                    </div>
                </section>

                <section class="edit-modal-section" aria-labelledby="editUrgencyTitle">
                  <div class="edit-modal-field-label mb-2">
                     <span>ความเร่งด่วน <span class="edit-modal-required">*</span></span>
                  </div>
                  <fieldset style="border:0;padding:0;margin:0">
                     <legend class="sr-only">เลือกระดับความเร่งด่วน</legend>
                     <div class="urgency-selector-grid">
                           <?php
                           $urgencyDescs = [
                              'urgent' => 'ต้องแก้ไขภายในวันนี้',
                              'normal' => 'ดำเนินการตามคิว',
                           ];
                           foreach (URGENCY_LABELS as $val => $lbl):
                           ?>
                              <label class="urgency-card-option">
                                 <input type="radio" name="urgency" value="<?= h($val) ?>" required>
                                 <span class="urgency-card-box">
                                       <span class="urgency-radio-circle"></span>
                                       <span class="urgency-card-content">
                                          <span class="urgency-card-title"><?= h($lbl) ?></span>
                                          <span class="urgency-card-desc"><?= h($urgencyDescs[$val]) ?></span>
                                       </span>
                                 </span>
                              </label>
                           <?php endforeach; ?>
                     </div>
                  </fieldset>
                  <label class="block mt-3 text-xs text-neutral-600" for="editUrgencyReason">เหตุผลที่ต้องแก้ไขภายในวันนี้ (กรอกเมื่อเลือก "ด่วน" · เว้นว่างเพื่อใช้เหตุผลเดิม)</label>
                  <input type="text" id="editUrgencyReason" name="urgency_reason" maxlength="500" class="edit-modal-control mt-1 w-full">
                  <p class="mt-1 text-[11px] text-rose-700">เลือก "ด่วน" เฉพาะกรณีจำเป็นจริง ๆ ที่ต้องแก้ไขภายในวันนี้</p>
               </section>
            </div>

            <div class="edit-modal-footer">
                <p class="edit-modal-footer-note"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span>แก้ไขได้เฉพาะใบงานของคุณที่ยังรอรับเรื่อง</span></p>
                <div class="edit-modal-actions">
                    <button type="button" class="admin-btn admin-btn-secondary" data-close>ปิดโดยไม่บันทึก</button>
                    <button type="submit" class="admin-btn edit-modal-submit" data-submit>
                        <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                        <span data-submit-label>บันทึกการแก้ไข</span>
                    </button>
                </div>
            </div>
        </form>
    </dialog>

    <!-- Modal: Cancel Ticket -->
    <dialog id="cancelDialog" class="w-[min(440px,calc(100vw-32px))] rounded-2xl border border-neutral-200 bg-white p-0 shadow-2xl backdrop:bg-black/40">
        <form method="post">
            <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="ticket_id" value="">
            <div class="flex items-center justify-between border-b border-neutral-100 px-6 py-4">
                <h2 class="text-sm font-semibold text-rose-600">ยกเลิกใบงาน <span class="font-mono font-normal" data-fill="no"></span></h2>
                <button type="button" class="text-neutral-500 hover:text-neutral-900 border-0 bg-transparent cursor-pointer" data-close aria-label="ปิด"><i class="fa-solid fa-xmark text-base" aria-hidden="true"></i></button>
            </div>
            <div class="space-y-4 px-6 py-5 text-xs">
                <p class="text-neutral-500">ใบงานที่ยกเลิกแล้วจะไม่สามารถนำกลับมาแก้ไขได้อีก โปรดระบุเหตุผลในการยกเลิก</p>
                <div class="flex flex-wrap gap-1.5">
                    <?php foreach (['แจ้งผิดรายการ' => 'แจ้งผิดรายการ', 'แจ้งซ้ำกับใบงานอื่น' => 'แจ้งซ้ำ', 'ปัญหาหายแล้ว ไม่ต้องซ่อม' => 'แก้ไขได้เองแล้ว'] as $reason => $chipLabel): ?>
                        <button type="button" class="min-h-[40px] rounded-full border border-neutral-300 px-3.5 text-sm text-neutral-700 hover:border-neutral-900 hover:text-neutral-900 transition bg-white cursor-pointer" data-reason="<?= h($reason) ?>"><?= h($chipLabel) ?></button>
                    <?php endforeach; ?>
                </div>
                <div>
                    <label for="cancelReasonInput" class="block font-medium text-neutral-700 mb-1">เหตุผลการยกเลิก <span class="text-rose-600">*</span></label>
                    <textarea id="cancelReasonInput" name="cancel_reason" rows="3" maxlength="<?= CANCEL_REASON_MAX_LENGTH ?>" required class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-xs text-neutral-900 focus:border-neutral-900 focus:outline-none" placeholder="ระบุเหตุผลสั้นๆ..."></textarea>
                </div>
            </div>
            <div class="flex items-center justify-end gap-2 border-t border-neutral-100 px-6 py-4 bg-neutral-50 rounded-b-2xl">
                <button type="button" class="admin-btn admin-btn-secondary text-xs" data-close>ปิด</button>
                <button type="submit" class="admin-btn admin-btn-danger-outline text-xs">ยืนยันการยกเลิก</button>
            </div>
        </form>
    </dialog>

    <script>
        (() => {
            'use strict';

            const editDialog = document.getElementById('editDialog');
            const cancelDialog = document.getElementById('cancelDialog');
            const editForm = editDialog?.querySelector('form');
            const maxSymptoms = <?= SYMPTOM_MAX_LENGTH ?>;
            const searchForm = document.querySelector('.dashboard-search');
            const searchInput = document.getElementById('searchInput');

            searchForm?.addEventListener('submit', () => {
                if (searchInput) searchInput.value = searchInput.value.trim();
            });

            searchInput?.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    searchInput.value = '';
                    searchForm?.submit();
                }
            });

            searchForm?.querySelector('[data-clear-search]')?.addEventListener('click', () => {
                if (!searchInput) return;
                searchInput.value = '';
                searchForm.submit();
            });

            document.addEventListener('keydown', (event) => {
                const tagName = document.activeElement?.tagName;
                if (event.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(tagName)) {
                    event.preventDefault();
                    searchInput?.focus();
                    searchInput?.select();
                }
            });

            document.querySelectorAll('[data-hist]').forEach((button) => {
                button.addEventListener('click', () => {
                    const open = button.getAttribute('aria-expanded') !== 'true';
                    button.setAttribute('aria-expanded', String(open));
                    document.getElementById(button.getAttribute('aria-controls'))?.classList.toggle('hidden', !open);
                    const icon = button.querySelector('[data-hist-icon]');
                    if (icon) icon.style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)';
                });
            });

            const updateCounter = () => {
                const field = editForm?.elements.symptoms;
                const counter = editDialog?.querySelector('[data-counter="symptoms"]');
                if (!field || !counter) return;
                counter.textContent = `${field.value.length.toLocaleString('th-TH')}/${maxSymptoms.toLocaleString('th-TH')}`;
            };

            const openDialog = (dialog, form, ticketNo, focusName) => {
                const ticketNumber = dialog.querySelector('[data-fill="no"]');
                if (ticketNumber) ticketNumber.textContent = '#' + ticketNo;
                dialog.showModal();
                requestAnimationFrame(() => form.elements[focusName]?.focus());
            };

            const closeDialog = (dialog) => {
                if (dialog?.open) dialog.close();
            };

            [editDialog, cancelDialog].forEach((dialog) => {
                if (!dialog) return;
                dialog.addEventListener('click', (event) => {
                    if (event.target === dialog) closeDialog(dialog);
                });
                dialog.querySelectorAll('[data-close]').forEach((button) => {
                    button.addEventListener('click', () => closeDialog(dialog));
                });
            });

            document.querySelectorAll('[data-edit]').forEach((button) => {
                button.addEventListener('click', () => {
                    const data = button.dataset;
                    editForm.elements.ticket_id.value = data.id || '';
                    editForm.elements.category_id.value = data.category || '';
                    editForm.elements.location_note.value = data.location || '';
                    editForm.elements.symptoms.value = data.symptoms || '';
                    editForm.querySelectorAll('input[name="urgency"]').forEach((radio) => {
                        radio.checked = radio.value === data.urgency;
                    });
                    updateCounter();
                    openDialog(editDialog, editForm, data.no || '', 'category_id');
                });
            });

            editForm?.addEventListener('input', updateCounter);
            editForm?.addEventListener('submit', (event) => {
                if (!editForm.checkValidity()) {
                    event.preventDefault();
                    editForm.reportValidity();
                    return;
                }
                const submit = event.submitter || editForm.querySelector('[type="submit"]');
                if (submit) {
                    submit.disabled = true;
                    submit.classList.add('is-loading');
                    submit.querySelector('[data-submit-label]')?.replaceChildren(document.createTextNode('กำลังบันทึก...'));
                }
            });

            document.querySelectorAll('[data-cancel]').forEach((button) => {
                button.addEventListener('click', () => {
                    const form = cancelDialog.querySelector('form');
                    form.elements.ticket_id.value = button.dataset.id || '';
                    form.elements.cancel_reason.value = '';
                    openDialog(cancelDialog, form, button.dataset.no || '', 'cancel_reason');
                });
            });

            cancelDialog?.querySelectorAll('[data-reason]').forEach((chip) => {
                chip.addEventListener('click', () => {
                    const field = cancelDialog.querySelector('[name="cancel_reason"]');
                    field.value = chip.dataset.reason || '';
                    field.focus();
                });
            });
        })();
    </script>
</body>
</html>
