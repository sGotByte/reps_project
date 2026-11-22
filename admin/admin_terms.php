<?php
declare(strict_types=1);

/**
 * REPS · admin/admin_terms.php — ภาคเรียนและทีมสตาฟ (Lab boy) ต่อภาคเรียน
 *
 * - สร้าง/แก้ภาคเรียน: ปีการศึกษา ภาคเรียนที่ วันเริ่ม-สิ้นสุด (ห้ามช่วงวันที่ซ้อนกัน) จำนวนสตาฟสูงสุด
 * - เลือกทีมสตาฟของแต่ละภาคเรียน (ไม่เกินจำนวนสูงสุด) และลำดับคิว
 * - ข้อมูลใบงาน/คะแนนแยกตามช่วงวันที่ของภาคเรียน (includes/terms.php) — ลบภาคเรียนไม่ลบใบงาน
 */

$page_title = 'ภาคเรียนและทีมสตาฟ';
$current_page = 'terms';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/site_config.php';
require_once __DIR__ . '/../includes/terms.php';
require_once __DIR__ . '/../includes/private_storage.php';
require_once __DIR__ . '/../includes/db_helpers.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

function h($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function th_date(string $ymd): string
{
    $m = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $ts = strtotime($ymd);
    return (int)date('j', $ts) . ' ' . $m[(int)date('n', $ts)] . ' ' . ((int)date('Y', $ts) + 543);
}

$defaultMax = max(1, min(10, (int)(app_get_settings($conn)['term_staff_max'] ?? 4)));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['admin_csrf'], is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '')) {
        http_response_code(403);
        exit('คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $termId = filter_input(INPUT_POST, 'term_id', FILTER_VALIDATE_INT) ?: 0;
    $flash = ['message' => 'บันทึกแล้ว', 'type' => 'success'];
    $back = 'admin_terms.php' . ($termId ? '?edit=' . $termId : '');
    try {
        if ($action === 'save_term') {
            $year = filter_input(INPUT_POST, 'year_be', FILTER_VALIDATE_INT, ['options' => ['min_range' => 2560, 'max_range' => 2700]]);
            $no = filter_input(INPUT_POST, 'term_no', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3]]);
            $max = filter_input(INPUT_POST, 'max_staff', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]);
            $start = is_string($_POST['start_date'] ?? null) ? $_POST['start_date'] : '';
            $end = is_string($_POST['end_date'] ?? null) ? $_POST['end_date'] : '';
            $note = mb_substr(trim(is_string($_POST['note'] ?? null) ? $_POST['note'] : ''), 0, 200);
            $validDate = static fn(string $d): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1 && checkdate((int)substr($d, 5, 2), (int)substr($d, 8, 2), (int)substr($d, 0, 4));
            if (!$year || !$no || !$max) {
                throw new AppUserException('กรุณาระบุปีการศึกษา (พ.ศ.) ภาคเรียนที่ 1–3 และจำนวนสตาฟสูงสุด 1–10 คน');
            }
            if (!$validDate($start) || !$validDate($end) || $end <= $start) {
                throw new AppUserException('วันเริ่มและวันสิ้นสุดภาคเรียนไม่ถูกต้อง (วันสิ้นสุดต้องหลังวันเริ่ม)');
            }
            $overlap = db_one($conn, 'SELECT year_be, term_no FROM academic_terms WHERE id <> ? AND start_date <= ? AND end_date >= ? LIMIT 1', 'iss', [$termId, $end, $start]);
            if ($overlap) {
                throw new AppUserException('ช่วงวันที่ซ้อนกับภาคเรียน ' . (int)$overlap['term_no'] . '/' . (int)$overlap['year_be'] . ' ข้อมูลจะปนกัน กรุณาปรับวันที่');
            }
            $dup = db_one($conn, 'SELECT id FROM academic_terms WHERE id <> ? AND year_be = ? AND term_no = ?', 'iii', [$termId, $year, $no]);
            if ($dup) {
                throw new AppUserException("มีภาคเรียน {$no}/{$year} อยู่แล้ว");
            }
            if ($termId) {
                $count = (int)(db_one($conn, 'SELECT COUNT(*) AS n FROM term_staff WHERE term_id = ?', 'i', [$termId])['n'] ?? 0);
                if ($count > $max) {
                    throw new AppUserException("ทีมภาคเรียนนี้มีสตาฟ {$count} คนแล้ว ตั้งจำนวนสูงสุดต่ำกว่านี้ไม่ได้ (นำสตาฟออกจากทีมก่อน)");
                }
                db_exec($conn, 'UPDATE academic_terms SET year_be = ?, term_no = ?, start_date = ?, end_date = ?, max_staff = ?, note = ? WHERE id = ?', 'iissisi', [$year, $no, $start, $end, $max, $note, $termId]);
                $flash['message'] = "บันทึกภาคเรียน {$no}/{$year} แล้ว";
            } else {
                $termId = db_insert($conn, 'INSERT INTO academic_terms (year_be, term_no, start_date, end_date, max_staff, note, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)', 'iissisi', [$year, $no, $start, $end, $max, $note, $adminId]);
                $flash['message'] = "สร้างภาคเรียน {$no}/{$year} แล้ว เลือกทีมสตาฟของภาคเรียนนี้ด้านล่าง";
            }
            $back = 'admin_terms.php?edit=' . $termId;
        } elseif ($action === 'save_roster' && $termId) {
            $term = db_one($conn, 'SELECT * FROM academic_terms WHERE id = ?', 'i', [$termId]);
            if (!$term) {
                throw new AppUserException('ไม่พบภาคเรียนนี้');
            }
            $picked = array_values(array_unique(array_map('intval', is_array($_POST['staff'] ?? null) ? $_POST['staff'] : [])));
            if (count($picked) > (int)$term['max_staff']) {
                throw new AppUserException('เลือกสตาฟได้ไม่เกิน ' . (int)$term['max_staff'] . ' คนต่อภาคเรียน');
            }
            if ($picked) {
                $valid = array_map('intval', array_column(db_all($conn, "SELECT user_id FROM users WHERE role = 'staff' AND status = 'active' AND user_id IN (" . implode(',', array_fill(0, count($picked), '?')) . ')', str_repeat('i', count($picked)), $picked), 'user_id'));
                if (count($valid) !== count($picked)) {
                    throw new AppUserException('มีผู้ใช้ที่ไม่ใช่สตาฟที่ใช้งานอยู่ในรายการที่เลือก');
                }
            }
            $isCurrent = (int)(term_current($conn)['id'] ?? 0) === $termId;
            db_transaction($conn, function () use ($conn, $termId, $picked, $adminId, $isCurrent) {
                db_exec($conn, 'DELETE FROM term_staff WHERE term_id = ?', 'i', [$termId]);
                foreach ($picked as $i => $uid) {
                    db_exec($conn, 'INSERT INTO term_staff (term_id, user_id, queue_order, added_by) VALUES (?, ?, ?, ?)', 'iiii', [$termId, $uid, $i + 1, $adminId]);
                }
                if ($isCurrent) {
                    // ภาคเรียนปัจจุบัน: ลำดับคิวตามที่เลือก ทีมใหม่เปิดรับคิว สตาฟนอกทีมปิดคิว
                    foreach ($picked as $i => $uid) {
                        db_exec($conn, 'UPDATE users SET queue_order = ?, is_active_queue = 1 WHERE user_id = ?', 'ii', [$i + 1, $uid]);
                    }
                    $notIn = $picked ? ' AND user_id NOT IN (' . implode(',', $picked) . ')' : '';
                    $conn->query("UPDATE users SET is_active_queue = 0, queue_order = NULL WHERE role = 'staff'" . $notIn);
                    // คิวที่ค้างอยู่กับสตาฟนอกทีม ปล่อยเป็นคิวกลาง
                    $conn->query("UPDATE tickets t JOIN users u ON u.user_id = t.queue_assigned_to SET t.queue_assigned_to = NULL, t.queue_expires_at = NULL WHERE t.status = 'pending' AND u.role = 'staff'" . str_replace('user_id', 'u.user_id', $notIn));
                }
            });
            $flash['message'] = 'บันทึกทีมสตาฟ ' . term_label($term) . ' แล้ว (' . count($picked) . ' คน)';
        } elseif ($action === 'delete_term' && $termId) {
            $term = db_one($conn, 'SELECT * FROM academic_terms WHERE id = ?', 'i', [$termId]);
            if (!$term) {
                throw new AppUserException('ไม่พบภาคเรียนนี้');
            }
            if (trim((string)($_POST['confirm'] ?? '')) !== term_label($term)) {
                throw new AppUserException('พิมพ์ ' . term_label($term) . ' เพื่อยืนยันการลบภาคเรียน');
            }
            db_exec($conn, 'DELETE FROM academic_terms WHERE id = ?', 'i', [$termId]);
            $flash['message'] = 'ลบภาคเรียน ' . term_label($term) . ' แล้ว (ใบงานและคะแนนไม่ถูกลบ)';
            $back = 'admin_terms.php';
        } else {
            throw new AppUserException('คำสั่งไม่ถูกต้อง');
        }
    } catch (AppUserException $e) {
        $flash = ['message' => $e->getMessage(), 'type' => 'error'];
    } catch (Throwable $e) {
        error_log('admin_terms ' . $action . ': ' . $e->getMessage());
        $flash = ['message' => 'บันทึกไม่สำเร็จ กรุณาลองใหม่', 'type' => 'error'];
    }
    $_SESSION['admin_flash'] = $flash;
    header('Location: ' . $back, true, 303);
    exit;
}

$message = is_array($_SESSION['admin_flash'] ?? null) ? (string)($_SESSION['admin_flash']['message'] ?? '') : '';
$messageType = is_array($_SESSION['admin_flash'] ?? null) ? (string)($_SESSION['admin_flash']['type'] ?? 'success') : 'success';
unset($_SESSION['admin_flash']);

$terms = term_all($conn, true);
$current = term_current($conn);
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: 0;
$editing = $editId ? term_get($conn, $editId) : null;
$isNew = isset($_GET['new']) || !$terms;

// ค่าเสนอสำหรับภาคเรียนใหม่: ต่อจากภาคเรียนล่าสุด
$suggest = ['year_be' => (int)date('Y') + 543, 'term_no' => 1, 'start_date' => '', 'end_date' => '', 'max_staff' => $defaultMax, 'note' => ''];
if ($terms) {
    $last = $terms[0];
    $nextNo = (int)$last['term_no'] >= 2 ? 1 : (int)$last['term_no'] + 1;
    $suggest['year_be'] = (int)$last['year_be'] + ($nextNo === 1 ? 1 : 0);
    $suggest['term_no'] = $nextNo;
    $suggest['start_date'] = date('Y-m-d', strtotime($last['end_date'] . ' +1 day'));
}
$form = $editing ?? $suggest;

$rosters = [];
foreach (db_all($conn, 'SELECT s.term_id, s.queue_order, u.user_id, u.first_name, u.last_name, u.id_number, u.status FROM term_staff s JOIN users u ON u.user_id = s.user_id ORDER BY s.term_id, s.queue_order') as $r) {
    $rosters[(int)$r['term_id']][] = $r;
}
$ticketCounts = [];
foreach ($terms as $t) {
    [$from, $to] = term_range($t);
    $ticketCounts[(int)$t['id']] = db_one($conn, "SELECT COUNT(*) AS n, SUM(status = 'completed') AS done FROM tickets WHERE created_at BETWEEN ? AND ?", 'ss', [$from, $to]);
}
$allStaff = db_all($conn, "SELECT user_id, first_name, last_name, id_number, user_type FROM users WHERE role = 'staff' AND status = 'active' ORDER BY first_name, last_name");

require_once __DIR__ . '/admin_header.php';
?>

<div class="admin-page-head">
    <nav class="admin-crumb">
        <a href="admin_dashboard.php">แดชบอร์ด</a>
        <i class="fa-solid fa-chevron-right"></i>
        <span>ภาคเรียนและทีมสตาฟ</span>
    </nav>
    <h1 class="admin-page-title">ภาคเรียนและทีมสตาฟ</h1>
    <p class="admin-page-sub">สตาฟ (Lab boy) เปลี่ยนทุกภาคเรียน ตั้งภาคเรียนและเลือกทีมไว้ ระบบจะจ่ายคิวเฉพาะทีมของภาคเรียนปัจจุบัน และแยกงาน คะแนน และรายงานตามภาคเรียน ไม่ให้ข้อมูลปนกัน</p>
</div>

<?php if ($message): ?>
    <div class="mb-4 rounded-lg border p-3 text-sm <?= $messageType === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' ?>" role="status"><?= h($message) ?></div>
<?php endif; ?>

<?php if (!$terms): ?>
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">ยังไม่มีภาคเรียนในระบบ ตอนนี้สตาฟทุกคนรับคิวได้ (แบบเดิม) สร้างภาคเรียนแรกด้านล่างเพื่อเริ่มแยกข้อมูลตามภาคเรียน</div>
<?php endif; ?>

<div class="admin-split">
    <div class="admin-tray">
        <div class="admin-tray-label"><span>ภาคเรียนทั้งหมด</span><a class="admin-btn admin-btn-secondary" href="admin_terms.php?new=1"><i class="fa-solid fa-plus" aria-hidden="true"></i> เพิ่มภาคเรียน</a></div>
        <section class="admin-card p-3">
            <?php if (!$terms): ?>
                <p class="p-4 text-sm text-gray-500 text-center">ยังไม่มีภาคเรียน</p>
            <?php endif; ?>
            <div class="space-y-3">
            <?php foreach ($terms as $t):
                $tid = (int)$t['id'];
                $isCur = $current && (int)$current['id'] === $tid;
                $state = $isCur ? ['ภาคเรียนปัจจุบัน', 'is-ok'] : ($t['start_date'] > date('Y-m-d') ? ['ยังไม่เริ่ม', 'is-warning'] : ['ผ่านมาแล้ว', 'is-low']);
                $roster = $rosters[$tid] ?? [];
                $tc = $ticketCounts[$tid] ?? ['n' => 0, 'done' => 0]; ?>
                <article class="rounded-xl border <?= $isCur ? 'border-gray-900' : 'border-gray-200' ?> p-4 <?= $editId === $tid ? 'bg-gray-50' : '' ?>">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-base font-semibold text-gray-900"><?= h(term_title($t)) ?></h2>
                            <span class="admin-chip <?= $state[1] ?>"><?= h($state[0]) ?></span>
                        </div>
                        <a class="admin-btn admin-btn-secondary" href="admin_terms.php?edit=<?= $tid ?>#roster"><i class="fa-solid fa-pen" aria-hidden="true"></i> แก้ไข / เลือกทีม</a>
                    </div>
                    <div class="mt-1 text-sm text-gray-600"><?= h(th_date($t['start_date'])) ?> – <?= h(th_date($t['end_date'])) ?> · ใบงาน <?= (int)$tc['n'] ?> ใบ (เสร็จ <?= (int)$tc['done'] ?>)<?= $t['note'] !== '' ? ' · ' . h($t['note']) : '' ?></div>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                        <span class="text-gray-500">ทีมสตาฟ <?= count($roster) ?>/<?= (int)$t['max_staff'] ?>:</span>
                        <?php if (!$roster): ?><span class="text-red-700">ยังไม่ได้เลือกทีม<?= $isCur ? ' (ไม่มีใครรับคิวได้)' : '' ?></span><?php endif; ?>
                        <?php foreach ($roster as $r): ?>
                            <span class="admin-chip no-dot"><?= (int)$r['queue_order'] ?>. <?= h($r['first_name'] . ' ' . $r['last_name']) ?> <span class="text-gray-500">· <?= h($r['id_number']) ?></span></span>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        </section>
    </div>

    <div class="space-y-6">
        <?php if ($editing || $isNew): ?>
        <div class="admin-tray">
            <div class="admin-tray-label"><span><?= $editing ? 'แก้ไข ' . h(term_title($editing)) : 'เพิ่มภาคเรียน' ?></span></div>
            <form method="post" class="admin-card p-5 grid gap-3 text-sm">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['admin_csrf']) ?>">
                <input type="hidden" name="action" value="save_term">
                <input type="hidden" name="term_id" value="<?= (int)($editing['id'] ?? 0) ?>">
                <div class="grid grid-cols-2 gap-3">
                    <label class="block">ภาคเรียนที่
                        <select name="term_no" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                            <?php foreach (TERM_NAMES as $n => $label): ?><option value="<?= $n ?>" <?= (int)$form['term_no'] === $n ? 'selected' : '' ?>><?= $n ?> · <?= h($label) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block">ปีการศึกษา (พ.ศ.)
                        <input type="number" name="year_be" min="2560" max="2700" required value="<?= (int)$form['year_be'] ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                    </label>
                    <label class="block">วันเปิดภาคเรียน
                        <input type="date" name="start_date" required value="<?= h($form['start_date']) ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                    </label>
                    <label class="block">วันสิ้นสุด (รวมช่วงปิดภาค)
                        <input type="date" name="end_date" required value="<?= h($form['end_date']) ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                    </label>
                    <label class="block">สตาฟสูงสุด (คน)
                        <input type="number" name="max_staff" min="1" max="10" required value="<?= (int)$form['max_staff'] ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                    </label>
                    <label class="block">หมายเหตุ
                        <input name="note" maxlength="200" value="<?= h($form['note']) ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                    </label>
                </div>
                <p class="text-xs text-gray-500">ใบงานนับเข้าภาคเรียนตามวันที่แจ้ง ให้ตั้งวันสิ้นสุดต่อเนื่องถึงวันก่อนเปิดภาคถัดไป (รวมช่วงปิดภาค) เพื่อไม่ให้มีใบงานตกหล่น · ช่วงวันที่ซ้อนกับภาคอื่นไม่ได้</p>
                <div class="flex justify-end gap-2">
                    <?php if ($editing): ?><a class="admin-btn admin-btn-secondary" href="admin_terms.php">ยกเลิก</a><?php endif; ?>
                    <button class="admin-btn"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> บันทึกภาคเรียน</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($editing):
            $roster = $rosters[(int)$editing['id']] ?? [];
            $rosterIds = array_map(static fn($r) => (int)$r['user_id'], $roster);
            // ทีมเดิมก่อน (ตามลำดับคิว) แล้วต่อด้วยสตาฟคนอื่น
            $ordered = [];
            foreach ($rosterIds as $uid) foreach ($allStaff as $s) if ((int)$s['user_id'] === $uid) $ordered[] = $s;
            foreach ($allStaff as $s) if (!in_array((int)$s['user_id'], $rosterIds, true)) $ordered[] = $s; ?>
        <div class="admin-tray" id="roster">
            <div class="admin-tray-label"><span>ทีมสตาฟ <?= h(term_label($editing)) ?></span><span data-roster-count><?= count($rosterIds) ?>/<?= (int)$editing['max_staff'] ?></span></div>
            <form method="post" class="admin-card p-4 text-sm" data-roster data-max="<?= (int)$editing['max_staff'] ?>">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['admin_csrf']) ?>">
                <input type="hidden" name="action" value="save_roster">
                <input type="hidden" name="term_id" value="<?= (int)$editing['id'] ?>">
                <?php if (!$allStaff): ?>
                    <p class="text-gray-500">ยังไม่มีผู้ใช้บทบาทสตาฟ เปลี่ยนบทบาทผู้ใช้เป็นสตาฟได้ที่ <a class="underline" href="admin_manage_user.php">จัดการผู้ใช้</a></p>
                <?php endif; ?>
                <ul class="space-y-2" data-roster-list>
                    <?php foreach ($ordered as $s):
                        $on = in_array((int)$s['user_id'], $rosterIds, true); ?>
                        <li class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2">
                            <label class="flex flex-1 items-center gap-2 min-w-0">
                                <input type="checkbox" name="staff[]" value="<?= (int)$s['user_id'] ?>" <?= $on ? 'checked' : '' ?>>
                                <span class="truncate"><?= h($s['first_name'] . ' ' . $s['last_name']) ?> <span class="text-gray-500">· <?= h($s['id_number']) ?></span></span>
                            </label>
                            <span class="flex gap-1">
                                <button type="button" class="admin-btn admin-btn-secondary" data-move="up" aria-label="เลื่อนขึ้น"><i class="fa-solid fa-chevron-up" aria-hidden="true"></i></button>
                                <button type="button" class="admin-btn admin-btn-secondary" data-move="down" aria-label="เลื่อนลง"><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="mt-3 text-xs text-gray-500">ติ๊กเลือกไม่เกิน <?= (int)$editing['max_staff'] ?> คน · ลำดับจากบนลงล่าง = ลำดับคิว · <?= $current && (int)$current['id'] === (int)$editing['id'] ? 'บันทึกแล้วทีมใหม่เปิดรับคิวทันที สตาฟนอกทีมถูกปิดคิว และคิวที่ค้างอยู่กับคนนอกทีมย้ายเป็นคิวกลาง' : 'ทีมนี้จะเริ่มรับคิวเมื่อถึงวันเปิดภาคเรียน' ?> · ไม่พบชื่อ? เปลี่ยนบทบาทผู้ใช้เป็นสตาฟที่ <a class="underline" href="admin_manage_user.php">จัดการผู้ใช้</a></p>
                <div class="mt-3 flex justify-end"><button class="admin-btn"><i class="fa-solid fa-users" aria-hidden="true"></i> บันทึกทีมสตาฟ</button></div>
            </form>
        </div>

        <div class="admin-tray">
            <div class="admin-tray-label"><span>ลบภาคเรียน</span></div>
            <form method="post" class="admin-card admin-danger-card p-4 text-sm grid gap-2">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['admin_csrf']) ?>">
                <input type="hidden" name="action" value="delete_term">
                <input type="hidden" name="term_id" value="<?= (int)$editing['id'] ?>">
                <p class="text-gray-600">ลบเฉพาะการตั้งค่าภาคเรียนและรายชื่อทีม ใบงานและคะแนนยังอยู่ครบ</p>
                <label class="block">พิมพ์ <b><?= h(term_label($editing)) ?></b> เพื่อยืนยัน
                    <input name="confirm" autocomplete="off" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                </label>
                <div class="flex justify-end"><button class="admin-btn admin-btn-danger-outline"><i class="fa-solid fa-trash" aria-hidden="true"></i> ลบภาคเรียน</button></div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var form = document.querySelector('[data-roster]');
    if (!form) return;
    var list = form.querySelector('[data-roster-list]');
    var max = parseInt(form.dataset.max, 10);
    var counter = document.querySelector('[data-roster-count]');
    function sync() {
        var boxes = list.querySelectorAll('input[type=checkbox]');
        var n = list.querySelectorAll('input[type=checkbox]:checked').length;
        boxes.forEach(function (b) { b.disabled = !b.checked && n >= max; });
        if (counter) counter.textContent = n + '/' + max;
    }
    list.addEventListener('change', sync);
    list.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-move]');
        if (!btn) return;
        var li = btn.closest('li');
        if (btn.dataset.move === 'up' && li.previousElementSibling) list.insertBefore(li, li.previousElementSibling);
        if (btn.dataset.move === 'down' && li.nextElementSibling) list.insertBefore(li.nextElementSibling, li);
        btn.focus();
    });
    sync();
})();
</script>

      </div>
<?php $footerBase = '../'; $footerVariant = 'app'; require __DIR__ . '/../includes/site_footer.php'; ?>
   </div>
</body>
</html>
