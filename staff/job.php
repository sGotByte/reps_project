<?php
declare(strict_types=1);

/**
 * REPS · staff/job.php — หน้างานของใบงานหนึ่งใบสำหรับทีมช่าง (v3 หัวข้อ 3–4)
 *   accepted        -> บันทึกเช็คลิสต์อุปกรณ์ตอนรับ (อุปกรณ์หลัก/พ่วง สภาพ เลขเครื่อง รูป)
 *   in_progress     -> บันทึกผลซ่อม + เบิกวัสดุ + ตรวจซ้ำเทียบเช็คลิสต์ตอนรับ
 *   inspected_ready -> สตาฟเซ็นส่งมอบ (ลำดับ 1)
 * ทุกคำสั่งตรวจฝั่งเซิร์ฟเวอร์ว่าผู้ใช้อยู่ในทีมช่างของใบงาน และใบงานอยู่ในสถานะที่ถูกต้อง (ใน ticket_service)
 * สตาฟ/ครูคนอื่นเปิดดูได้แบบอ่านอย่างเดียว
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/user_auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/site_config.php';
require_once __DIR__ . '/../includes/ticket_service.php';
require_once __DIR__ . '/../includes/ticket_view.php';
require_once __DIR__ . '/../includes/signature.php';

/** @var mysqli $conn */
$userId = require_role($conn, ['staff', 'admin'], '../login.php?next=staff');
$ticketId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT) ?: 0;
$siteName = app_site_name($conn);
$csrfToken = app_csrf_token();

function h(mixed $v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function job_redirect(int $ticketId, string $msg, string $type = 'ok'): never
{
    $_SESSION['job_flash'] = ['msg' => $msg, 'type' => $type];
    header('Location: job.php?id=' . $ticketId, true, 303);
    exit;
}

/** อัปโหลดรูปที่ไม่บังคับ: คืน path หรือ null (โยน AppUserException ถ้าไฟล์ผิด) */
function job_optional_image(?array $file, string $prefix): ?string
{
    if (!$file || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    return app_store_uploaded_image($file, $prefix);
}

/** แปลง $_FILES['x']['name'][i] เป็นรายไฟล์ */
function job_file_at(string $field, $index): ?array
{
    $f = $_FILES[$field] ?? null;
    if (!is_array($f) || !is_array($f['error'] ?? null) || !array_key_exists($index, $f['error'])) {
        return null;
    }
    return ['name' => $f['name'][$index], 'type' => $f['type'][$index], 'tmp_name' => $f['tmp_name'][$index], 'error' => $f['error'][$index], 'size' => $f['size'][$index]];
}

$ticket = $ticketId > 0 ? tv_ticket($conn, $ticketId) : null;
if (!$ticket) {
    http_response_code(404);
    exit('ไม่พบใบงาน');
}
$isTeam = ticket_is_team_member($conn, $ticket, $userId);

// ---------------------------------------------------------------- POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_csrf_is_valid($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('คำขอหมดอายุ กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $stored = []; // ไฟล์ที่บันทึกในคำขอนี้ — ลบทิ้งถ้าขั้นตอนล้มเหลว
    try {
        if ($action === 'save_intake') {
            $names = is_array($_POST['it_name'] ?? null) ? $_POST['it_name'] : [];
            $items = [];
            foreach ($names as $i => $rawName) {
                $name = trim(is_string($rawName) ? $rawName : '');
                if ($name === '') {
                    continue;
                }
                $type = ($_POST['it_type'][$i] ?? '') === 'main' ? 'main' : 'accessory';
                $cond = $_POST['it_cond'][$i] ?? 'normal';
                $items[] = [
                    'name' => mb_substr($name, 0, 150, 'UTF-8'),
                    'type' => $type,
                    'serial' => mb_substr(trim((string)($_POST['it_serial'][$i] ?? '')), 0, 100, 'UTF-8'),
                    'qty' => max(0, min(999, (int)($_POST['it_qty'][$i] ?? 0))),
                    'condition' => isset(ITEM_CONDITION_LABELS[$cond]) ? $cond : 'normal',
                    'note' => mb_substr(trim((string)($_POST['it_note'][$i] ?? '')), 0, 255, 'UTF-8'),
                    'photo' => null,
                    'file_index' => $i,
                ];
            }
            if (count($items) > 40) {
                throw new AppUserException('เช็คลิสต์มีได้ไม่เกิน 40 รายการ');
            }
            foreach ($items as &$it) {
                $path = job_optional_image(job_file_at('it_photo', $it['file_index']), 'chk');
                if ($path) {
                    $stored[] = $path;
                    $it['photo'] = $path;
                }
                unset($it['file_index']);
            }
            unset($it);
            $overall = job_optional_image($_FILES['intake_photo'] ?? null, 'intake');
            if ($overall) {
                $stored[] = $overall;
            }
            $note = mb_substr(trim((string)($_POST['intake_note'] ?? '')), 0, 500, 'UTF-8');
            ticket_save_intake($conn, $ticketId, $userId, $items, $note, $overall);
            job_redirect($ticketId, 'บันทึกเช็คลิสต์ตอนรับแล้ว ระบบแจ้งผู้แจ้งให้เซ็นรับทราบ ช่างเริ่มซ่อมได้หลังผู้แจ้งเซ็น');
        }

        if ($action === 'submit_recheck') {
            $repairMethod = trim((string)($_POST['repair_method'] ?? ''));
            $summary = trim((string)($_POST['resolution_summary'] ?? ''));
            if ($repairMethod === '' || $summary === '' || mb_strlen($repairMethod) > 5000 || mb_strlen($summary) > 2000) {
                throw new AppUserException('กรุณากรอกวิธีดำเนินการและสรุปผลให้ครบ');
            }
            $materials = [];
            foreach ((array)($_POST['item_id'] ?? []) as $i => $rawId) {
                $iid = (int)$rawId;
                if ($iid <= 0) {
                    continue;
                }
                $qty = (int)($_POST['item_qty'][$i] ?? 0);
                if ($qty <= 0 || $qty > 1000) {
                    throw new AppUserException('จำนวนวัสดุที่เบิกต้องอยู่ระหว่าง 1 ถึง 1000');
                }
                $note = trim((string)($_POST['item_note'][$i] ?? ''));
                if (isset($materials[$iid])) {
                    $materials[$iid]['qty'] += $qty;
                } else {
                    $materials[$iid] = ['item_id' => $iid, 'qty' => $qty, 'note' => $note];
                }
            }
            $results = [];
            foreach ((array)($_POST['rc_result'] ?? []) as $checkId => $res) {
                $checkId = (int)$checkId;
                $path = job_optional_image(job_file_at('rc_photo', $checkId), 'chk');
                if ($path) {
                    $stored[] = $path;
                }
                $results[$checkId] = [
                    'result' => is_string($res) ? $res : '',
                    'qty' => (int)($_POST['rc_qty'][$checkId] ?? 0),
                    'note' => trim((string)($_POST['rc_note'][$checkId] ?? '')),
                    'photo' => $path,
                ];
            }
            // รูปหลังซ่อม 1–3 รูป: completion_images[] (ฟอร์มใหม่) หรือ completion_image (ฟอร์มเดิม)
            $photoFiles = [];
            if (isset($_FILES['completion_images']['name']) && is_array($_FILES['completion_images']['name'])) {
                foreach (array_keys($_FILES['completion_images']['name']) as $k) {
                    if ((int)$_FILES['completion_images']['error'][$k] === UPLOAD_ERR_NO_FILE) continue;
                    $photoFiles[] = [
                        'name' => $_FILES['completion_images']['name'][$k], 'type' => $_FILES['completion_images']['type'][$k],
                        'tmp_name' => $_FILES['completion_images']['tmp_name'][$k], 'error' => $_FILES['completion_images']['error'][$k],
                        'size' => $_FILES['completion_images']['size'][$k],
                    ];
                }
            } elseif (isset($_FILES['completion_image']) && (int)$_FILES['completion_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $photoFiles[] = $_FILES['completion_image'];
            }
            if (!$photoFiles) {
                throw new AppUserException('กรุณาแนบรูปหลักฐานหลังซ่อมเสร็จอย่างน้อย 1 รูป');
            }
            if (count($photoFiles) > 3) {
                throw new AppUserException('แนบรูปหลังซ่อมได้สูงสุด 3 รูป');
            }
            $photoPaths = [];
            foreach ($photoFiles as $pf) {
                $photoPaths[] = $stored[] = app_store_uploaded_image($pf, 'done');
            }
            ticket_submit_recheck(
                $conn, $ticketId, $userId, $repairMethod, $summary, $photoPaths[0], array_values($materials), $results,
                trim((string)($_POST['discrepancy_note'] ?? '')), trim((string)($_POST['return_note'] ?? '')),
                array_slice($photoPaths, 1)
            );
            job_redirect($ticketId, 'บันทึกผลซ่อมและตรวจซ้ำแล้ว ขั้นต่อไป: เซ็นส่งมอบเมื่อคืนอุปกรณ์ให้ผู้แจ้ง');
        }

        if ($action === 'staff_sign') {
            [$sigPath, $method] = signature_capture($conn, $userId);
            $stored[] = $sigPath;
            ticket_staff_sign($conn, $ticketId, $userId, $sigPath, $method, trim((string)($_POST['sign_note'] ?? '')));
            job_redirect($ticketId, 'เซ็นส่งมอบแล้ว ระบบแจ้งผู้แจ้งให้เซ็นรับคืน');
        }
        throw new AppUserException('คำสั่งไม่ถูกต้อง');
    } catch (AppUserException $e) {
        foreach ($stored as $p) {
            app_delete_private_file($p);
        }
        job_redirect($ticketId, $e->getMessage(), 'err');
    } catch (Throwable $e) {
        foreach ($stored as $p) {
            app_delete_private_file($p);
        }
        error_log('job.php action "' . $action . '" failed: ' . $e->getMessage());
        job_redirect($ticketId, 'ทำรายการไม่สำเร็จ กรุณาลองใหม่อีกครั้ง', 'err');
    }
}

// ---------------------------------------------------------------- ข้อมูลแสดงผล
$flash = $_SESSION['job_flash'] ?? null;
unset($_SESSION['job_flash']);
$items = ticket_item_checks($conn, $ticketId);
$ticket['has_intake'] = (bool)$items;
$signoffs = ticket_signoffs($conn, $ticketId);
$logs = tv_logs($conn, $ticketId);
$status = (string)$ticket['status'];
$presets = $status === 'accepted' ? checklist_presets_for($conn, $ticket['category_id'] !== null ? (int)$ticket['category_id'] : null) : [];
$inventoryItems = $status === 'in_progress' ? db_all($conn, 'SELECT id, name, unit, stock_qty FROM inventory_items WHERE stock_qty > 0 ORDER BY name') : [];
$team = db_all($conn, 'SELECT u.first_name, u.last_name FROM ticket_assignees a JOIN users u ON u.user_id = a.user_id WHERE a.ticket_id = ?', 'i', [$ticketId]);
$me = db_one($conn, 'SELECT first_name, last_name FROM users WHERE user_id = ?', 'i', [$userId]) ?? [];

$adminShell = current_role() === 'admin';
$pageTitle = 'ใบงาน ' . $ticket['ticket_no'];
$extraHead = '<script src="https://cdn.tailwindcss.com"></script>'
    . "<script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Kanit', 'sans-serif'], kanit: ['Kanit', 'sans-serif'], mono: ['Kanit', 'sans-serif'] } } } };</script>"
    . app_theme_css($conn) . tv_assets() . <<<'HTML'
<style>
body { background: #FAFAF8; color: #161616; font-family: 'Kanit', sans-serif; }

/* โครงหน้า: งานที่ต้องทำ (ซ้าย) · ข้อมูลอ้างอิง (ขวา) — มือถือเรียงเป็นคอลัมน์เดียว: สรุปงาน → ฟอร์ม → อ้างอิง */
.jb-page { display: flex; flex-direction: column; gap: 16px; }
.jb-head { display: flex; flex-direction: column; gap: 6px; }
.jb-back { align-self: flex-start; display: inline-flex; align-items: center; gap: 6px; min-height: 32px; color: #57544F; font-size: 14px; text-decoration: none; }
.jb-back:hover { color: #161616; text-decoration: underline; text-underline-offset: 3px; }
.jb-head-row { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px; }
.jb-title { margin: 0; font-size: 26px; font-weight: 500; line-height: 1.2; letter-spacing: -.01em; font-variant-numeric: tabular-nums; }
.jb-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.jb-layout { display: grid; gap: 16px; align-items: start; }
.jb-main, .jb-aside { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
.jb-main { container-type: inline-size; }
.jb-layout .tv-card + .tv-card { margin-top: 0; }
.jb-aside-sum { display: none; }
@media (min-width: 1024px) {
  .jb-layout { grid-template-columns: minmax(0, 1fr) 340px; gap: 20px; }
  .jb-brief { display: none; }
  .jb-aside-sum { display: block; }
}

/* สรุปงาน */
.jb-sum-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 12px; margin-top: 12px; padding-top: 12px; border-top: 1px solid #F1EFEC; }
.jb-aside .tv-kv { grid-template-columns: 1fr; gap: 10px; }

/* ข้อมูลอ้างอิงในคอลัมน์แคบ: ตารางเช็คลิสต์เรียงเป็นรายการ ลายเซ็นที่ยังไม่เซ็นเป็นแถวสั้น */
.jb-aside .tv-table { min-width: 0; }
.jb-aside .tv-table thead { display: none; }
.jb-aside .tv-table tr { display: grid; gap: 4px; padding: 10px 0; border-bottom: 1px solid #F1EFEC; }
.jb-aside .tv-table tr:first-child { padding-top: 0; }
.jb-aside .tv-table tr:last-child { border-bottom: 0; padding-bottom: 0; }
.jb-aside .tv-table td { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 8px; padding: 0; border: 0; }
.jb-aside .tv-table td[data-label]::before { content: attr(data-label); min-width: 84px; color: #6B6862; font-size: 12px; }
.jb-aside .tv-table tr.is-diff { margin: 0 -10px; padding-left: 10px; padding-right: 10px; border-radius: 10px; background: #FFFBF5; }
.jb-aside .tv-table tr.is-diff td { background: transparent; }
.jb-aside .tv-sig.is-missing { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 12px; }
.jb-aside .tv-sig.is-missing .tv-sig-wait { display: inline; height: auto; margin: 0; }

/* ฟอร์ม */
.jb-form { display: flex; flex-direction: column; gap: 16px; }
.jb-field { display: block; font-size: 13px; color: #3F3D39; }
.jb-input { width: 100%; min-height: 40px; margin-top: 4px; padding: 0 10px; border: 1px solid #D4D0C9; border-radius: 10px; background: #fff; font: inherit; font-size: 14px; color: #161616; }
textarea.jb-input { padding: 8px 10px; line-height: 1.5; }
.jb-input:focus { outline: none; border-color: #161616; box-shadow: 0 0 0 3px rgba(22, 22, 22, .08); }
.jb-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 44px; padding: 0 18px; border: 1px solid var(--theme-primary, #161616); border-radius: 12px; background: var(--theme-primary, #161616); color: #fff; font: inherit; font-size: 14px; font-weight: 500; text-decoration: none; cursor: pointer; }
.jb-btn:hover { background: var(--theme-hover, #2A2927); }
.jb-btn--ghost { border-color: #D4D0C9; background: #fff; color: #161616; }
.jb-btn--ghost:hover { border-color: #B9B5AE; background: #FAFAF8; }
.jb-btn--sm { min-height: 36px; padding: 0 12px; font-size: 13px; border-radius: 10px; }
.jb-btn:disabled { opacity: .6; cursor: progress; }
.jb-add { align-self: flex-start; min-height: 40px; }
.jb-pair { display: grid; gap: 12px; }
@container (min-width: 600px) { .jb-pair { grid-template-columns: 1fr 1fr; } }

/* ปุ่มบันทึกท้ายฟอร์ม: คำอธิบายผลที่ตามมา + ปุ่มหลัก */
.jb-submit { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px 16px; margin-top: 4px; padding-top: 16px; border-top: 1px solid #F1EFEC; }
.jb-submit p { flex: 1 1 260px; margin: 0; color: #57544F; font-size: 13px; line-height: 1.55; }
@media (max-width: 640px) { .jb-submit .jb-btn { width: 100%; } }

/* รายการอุปกรณ์ตอนรับ */
.jb-items { display: grid; gap: 12px; }
.jb-item { padding: 12px 14px 14px; border: 1px solid #ECEAE6; border-radius: 14px; background: #FCFBFA; }
.jb-item-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 8px; }
.jb-item-no { font-size: 13px; font-weight: 600; color: #3F3D39; }
.jb-del { display: inline-flex; align-items: center; gap: 6px; min-height: 32px; padding: 0 10px; border: 1px solid transparent; border-radius: 9px; background: transparent; color: #B42318; font: inherit; font-size: 13px; cursor: pointer; }
.jb-del:hover { border-color: #F2D4D0; background: #FEF3F2; }
.jb-del[hidden] { display: none; }
.jb-item-grid, .jb-rc-grid { display: grid; gap: 10px; grid-template-columns: repeat(6, minmax(0, 1fr)); }
.jb-item-grid > *, .jb-rc-grid > * { grid-column: span 6; }
.jb-item-grid .c-type { grid-column: span 4; }
.jb-item-grid .c-qty { grid-column: span 2; }
@container (min-width: 600px) {
  .jb-item-grid, .jb-rc-grid { grid-template-columns: repeat(12, minmax(0, 1fr)); }
  .jb-item-grid .c-name { grid-column: span 6; }
  .jb-item-grid .c-type { grid-column: span 4; }
  .jb-item-grid .c-qty { grid-column: span 2; }
  .jb-item-grid .c-serial { grid-column: span 6; }
  .jb-item-grid .c-cond { grid-column: span 6; }
  .jb-item-grid .c-note { grid-column: span 6; }
  .jb-item-grid .c-photo { grid-column: span 6; }
  .jb-rc-grid .c-qty { grid-column: span 2; }
  .jb-rc-grid .c-note { grid-column: span 4; }
  .jb-rc-grid .c-photo { grid-column: span 6; }
}

/* ตรวจซ้ำก่อนส่งคืน */
.jb-step { display: flex; flex-direction: column; gap: 12px; min-width: 0; margin: 0; padding: 0; border: 0; }
.jb-step + .jb-step { padding-top: 16px; border-top: 1px solid #F1EFEC; }
.jb-step > legend { float: left; width: 100%; margin-bottom: 2px; padding: 0; font-size: 14px; font-weight: 600; color: #161616; }
.jb-step > legend + * { clear: both; }
.jb-rc { display: grid; gap: 10px; padding: 12px 14px 14px; border: 1px solid #ECEAE6; border-radius: 14px; }
.jb-rc.is-diff { border-color: #F1D9BF; background: #FFFBF5; }
.jb-rc-head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 6px 12px; font-size: 14px; }
.jb-seg { display: flex; flex-wrap: wrap; gap: 6px; }
.jb-seg label { display: inline-flex; align-items: center; gap: 6px; min-height: 40px; padding: 0 12px; border: 1px solid #D4D0C9; border-radius: 10px; background: #fff; font-size: 13.5px; cursor: pointer; }
.jb-seg input:checked + span { font-weight: 600; }
.jb-seg label:has(input:checked) { border-color: #161616; box-shadow: inset 0 0 0 1px #161616; }
.jb-stock-rows { display: grid; gap: 8px; }
.jb-stock-row { display: grid; gap: 8px; }
.jb-stock-row .jb-input { margin-top: 0; }
@container (min-width: 600px) { .jb-stock-row { grid-template-columns: minmax(0, 6fr) minmax(0, 2fr) minmax(0, 4fr); } }
/* ช่องรูปที่อยู่เต็มแถว (รูปหลังซ่อม) กว้างครึ่งฟอร์ม ให้ปุ่มขนาดเท่าช่องรูปอื่น */
@container (min-width: 600px) { .jb-step > .jb-field > .photo-in, .jb-form > .jb-field > .photo-in { max-width: calc(50% - 5px); } }

/* มุมมองผู้ดูแล: admin_header แทน <select> ด้วยดรอปดาวน์ของตัวเอง (inline-flex กว้างตามข้อความ)
   ในหน้านี้ให้เต็มช่องและอยู่ใต้ป้ายกำกับ สูงเท่าช่องกรอกอื่น */
.jb-page .admin-select-wrap { display: flex; width: 100%; margin-top: 4px; }
.jb-page .jb-stock-row .admin-select-wrap { margin-top: 0; }
.jb-page .admin-select-trigger { min-width: 0; min-height: 40px; border-color: #D4D0C9; font-size: 14px; }
.jb-page .admin-select-trigger::after { top: 14px; }
.jb-page .admin-select-wrap.is-open .admin-select-trigger::after { top: 17px; }

.jb-flash { padding: 12px 14px; border-radius: 12px; font-size: 14px; }
.jb-flash--ok { border: 1px solid #BBE5CC; background: #F0FAF4; color: #14532D; }
.jb-flash--err { border: 1px solid #F5C2C0; background: #FEF2F2; color: #7F1D1D; }
</style>
HTML;

if ($adminShell) {
    $page_title = $pageTitle;
    $current_page = 'staffview';
    $admin_extra_head = $extraHead;
    require __DIR__ . '/../admin/admin_header.php';
} else { ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= h($pageTitle) ?> | <?= h($siteName) ?></title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo/reps-icon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <?= $extraHead ?>
    <link rel="stylesheet" href="../assets/css/base.css?v=18">
</head>
<body class="min-h-screen flex flex-col antialiased">
    <?php $staffNavActive = 'jobs'; require __DIR__ . '/../includes/staff_nav.php'; ?>
<?php } ?>

<?php
$reqPhoneDigits = preg_replace('/[^0-9+]/', '', (string)($ticket['primary_contact_phone'] ?? $ticket['req_phone'] ?? ''));
$teamLine = trim(($ticket['staff_first'] ?? '') . ' ' . ($ticket['staff_last'] ?? '')) ?: '-';
if ($team) {
    $teamLine .= ' · ผู้ช่วย ' . implode(', ', array_map(static fn($m) => $m['first_name'] . ' ' . $m['last_name'], $team));
}
$jobSummary = static function () use ($ticket, $reqPhoneDigits, $teamLine): string {
    ob_start(); ?>
    <?= tv_summary($ticket, '../', true) ?>
    <div class="jb-sum-foot">
        <span class="tv-muted">ทีม: <?= h($teamLine) ?></span>
        <?php if ($reqPhoneDigits !== ''): ?>
            <a class="jb-btn jb-btn--ghost jb-btn--sm" href="tel:<?= h($reqPhoneDigits) ?>"><i class="fa-solid fa-phone text-xs" aria-hidden="true"></i> โทรหาผู้แจ้ง</a>
        <?php endif; ?>
    </div>
    <?php return (string)ob_get_clean();
};
?>
<main class="jb-page <?= $adminShell ? 'w-full' : 'flex-1 max-w-6xl w-full mx-auto px-4 sm:px-7 py-6' ?>">
    <header class="jb-head">
        <a href="staff_dashboard.php#mine" class="jb-back"><i class="fa-solid fa-arrow-left text-xs" aria-hidden="true"></i> งานของฉัน</a>
        <div class="jb-head-row">
            <div class="min-w-0">
                <h1 class="jb-title">ใบงาน <?= h($ticket['ticket_no']) ?></h1>
                <div class="jb-chips">
                    <span class="tv-tag <?= $status === 'completed' ? 'tv-tag--ok' : (in_array($status, ['disputed'], true) ? 'tv-tag--warn' : 'tv-tag--main') ?>"><?= h(TICKET_STATUS_LABELS[$status] ?? $status) ?></span>
                    <?php if ($ticket['urgency'] === 'urgent'): ?><span class="tv-tag tv-tag--warn">ด่วน<?= !empty($ticket['urgency_reason']) ? ' · ' . h($ticket['urgency_reason']) : '' ?></span><?php endif; ?>
                    <?php if ((int)$ticket['sign_round'] > 1): ?><span class="tv-tag">รอบเซ็นที่ <?= (int)$ticket['sign_round'] ?></span><?php endif; ?>
                </div>
            </div>
            <?php if ($status === 'completed'): ?>
                <a class="jb-btn jb-btn--ghost" target="_blank" rel="noopener" href="../certificate.php?id=<?= (int)$ticketId ?>"><i class="fa-solid fa-print text-xs" aria-hidden="true"></i> ใบรับรองงาน</a>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($flash): ?>
        <div role="status" class="jb-flash jb-flash--<?= ($flash['type'] ?? '') === 'err' ? 'err' : 'ok' ?>"><?= h($flash['msg'] ?? '') ?></div>
    <?php endif; ?>

    <section class="tv-card" aria-label="ขั้นตอนใบงาน"><div class="tv-card-body"><?= tv_steps($status) ?></div></section>

    <div class="jb-layout">
        <div class="jb-main">

            <?php /* มือถือ: สรุปงานก่อนลงมือ (คอมพิวเตอร์แสดงในคอลัมน์ขวา) */ ?>
            <section class="tv-card jb-brief">
                <div class="tv-card-head"><h2 class="tv-card-title">งานนี้</h2></div>
                <div class="tv-card-body"><?= $jobSummary() ?></div>
            </section>

            <?php if ($status === 'in_progress' && (int)$ticket['sign_round'] > 1 && !empty($ticket['return_reason'])): ?>
                <div class="tv-alert"><b>ครูส่งกลับให้แก้ไข</b> (รอบเซ็นที่ <?= (int)$ticket['sign_round'] ?>): <?= h($ticket['return_reason']) ?></div>
            <?php endif; ?>

            <?php if (!$isTeam && in_array($status, ['accepted', 'in_progress', 'inspected_ready'], true)): ?>
                <div class="tv-alert">คุณไม่ได้อยู่ในทีมช่างของใบงานนี้ จึงดูได้อย่างเดียว</div>
            <?php endif; ?>

            <?php /* ---------------------------------------------------------- 1. เช็คลิสต์ตอนรับ */ ?>
            <?php if ($status === 'accepted' && $isTeam): ?>
                <section class="tv-card">
                    <div class="tv-card-head">
                        <h2 class="tv-card-title">บันทึกเช็คลิสต์อุปกรณ์ตอนรับ</h2>
                        <span class="tv-card-sub">ตรวจกับผู้แจ้งก่อนนำอุปกรณ์ไปซ่อม · จำนวน 0 = ไม่ได้รับมา</span>
                    </div>
                    <form method="post" enctype="multipart/form-data" class="tv-card-body jb-form" data-busy>
                        <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">
                        <input type="hidden" name="action" value="save_intake">
                        <input type="hidden" name="ticket_id" value="<?= (int)$ticketId ?>">
                        <div class="jb-items" id="intakeItems">
                            <?php
                            $rows = $presets ?: [['item_name' => '', 'item_type' => 'main']];
                            foreach ($rows as $i => $p): ?>
                                <div class="jb-item" data-item-row>
                                    <div class="jb-item-head">
                                        <span class="jb-item-no" data-item-no>รายการที่ <?= $i + 1 ?></span>
                                        <button type="button" class="jb-del" data-remove-row aria-label="ลบรายการนี้"><i class="fa-solid fa-trash-can" aria-hidden="true"></i> ลบ</button>
                                    </div>
                                    <div class="jb-item-grid">
                                        <label class="jb-field c-name">อุปกรณ์
                                            <input class="jb-input" name="it_name[]" maxlength="150" value="<?= h($p['item_name']) ?>" placeholder="เช่น ตัวเครื่อง, สายชาร์จ" <?= $i === 0 ? 'required' : '' ?>>
                                        </label>
                                        <label class="jb-field c-type">ประเภท
                                            <select class="jb-input" name="it_type[]">
                                                <option value="main" <?= $p['item_type'] === 'main' ? 'selected' : '' ?>>อุปกรณ์หลัก</option>
                                                <option value="accessory" <?= $p['item_type'] !== 'main' ? 'selected' : '' ?>>อุปกรณ์พ่วง</option>
                                            </select>
                                        </label>
                                        <label class="jb-field c-qty">จำนวน
                                            <input class="jb-input" type="number" name="it_qty[]" min="0" max="999" value="<?= $p['item_type'] === 'main' ? 1 : 0 ?>" inputmode="numeric">
                                        </label>
                                        <label class="jb-field c-serial">เลขเครื่อง / ครุภัณฑ์
                                            <input class="jb-input" name="it_serial[]" maxlength="100" placeholder="ถ้ามี">
                                        </label>
                                        <label class="jb-field c-cond">สภาพตอนรับ
                                            <select class="jb-input" name="it_cond[]">
                                                <?php foreach (ITEM_CONDITION_LABELS as $k => $lbl): ?><option value="<?= h($k) ?>"><?= h($lbl) ?></option><?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label class="jb-field c-note">หมายเหตุ
                                            <input class="jb-input" name="it_note[]" maxlength="255" placeholder="เช่น มีรอยขีดข่วนมุมขวา">
                                        </label>
                                        <label class="jb-field c-photo">รูป (ไม่บังคับ)
                                            <input type="file" name="it_photo[<?= $i ?>]" accept="image/jpeg,image/png,image/webp" data-photo data-photo-input>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="jb-btn jb-btn--ghost jb-add" id="addItemRow"><i class="fa-solid fa-plus text-xs" aria-hidden="true"></i> เพิ่มรายการ</button>
                        <div class="jb-pair">
                            <label class="jb-field">หมายเหตุรวม
                                <input class="jb-input" name="intake_note" maxlength="500" placeholder="เช่น ผู้แจ้งนำมาส่งเองที่ห้องช่าง">
                            </label>
                            <label class="jb-field">รูปรวมอุปกรณ์ตอนรับ (แนะนำ)
                                <input type="file" name="intake_photo" accept="image/jpeg,image/png,image/webp" data-photo-input>
                            </label>
                        </div>
                        <div class="jb-submit">
                            <p>เมื่อบันทึกแล้วแก้ไขไม่ได้ ระบบส่งอีเมลสรุปรายการให้ผู้แจ้ง และผู้แจ้งต้องเซ็นรับทราบก่อนจึงเริ่มซ่อมได้</p>
                            <button type="submit" class="jb-btn">บันทึกเช็คลิสต์และส่งให้ผู้แจ้งเซ็น</button>
                        </div>
                    </form>
                </section>
            <?php endif; ?>

            <?php if ($status === 'awaiting_intake_sign'): ?>
                <div class="tv-alert">รอผู้แจ้ง <b><?= h($ticket['req_first'] . ' ' . $ticket['req_last']) ?></b> เซ็นรับทราบเช็คลิสต์ (ตั้งแต่ <?= h(tv_dt($ticket['stage_since'])) ?>) ช่างเริ่มซ่อมได้หลังผู้แจ้งเซ็น หากผู้แจ้งไม่เซ็นจนเกินกำหนด ครูผู้ดูแลข้ามขั้นได้</div>
            <?php endif; ?>

            <?php /* ---------------------------------------------------------- 2. ผลซ่อม + ตรวจซ้ำ */ ?>
            <?php if ($status === 'in_progress' && $isTeam): ?>
                <section class="tv-card">
                    <div class="tv-card-head">
                        <h2 class="tv-card-title">ซ่อมเสร็จ: บันทึกผลและตรวจซ้ำก่อนส่งคืน</h2>
                        <span class="tv-card-sub">ตรวจทุกรายการเทียบกับตอนรับ</span>
                    </div>
                    <form method="post" enctype="multipart/form-data" class="tv-card-body jb-form" data-busy id="recheckForm">
                        <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">
                        <input type="hidden" name="action" value="submit_recheck">
                        <input type="hidden" name="ticket_id" value="<?= (int)$ticketId ?>">

                        <fieldset class="jb-step">
                            <legend>1. ตรวจอุปกรณ์เทียบกับตอนรับ</legend>
                            <?php if (!$items): ?>
                                <p class="tv-muted">ใบงานนี้ไม่มีเช็คลิสต์ตอนรับ (เปิดก่อนใช้ระบบเช็คลิสต์) ข้ามขั้นตรวจซ้ำได้</p>
                            <?php endif; ?>
                            <?php foreach ($items as $it): $cid = (int)$it['id']; $prev = $it['result_out'] ?? 'returned'; ?>
                                <div class="jb-rc" data-rc>
                                    <div class="jb-rc-head">
                                        <span><b><?= h($it['item_name']) ?></b> <?= $it['item_type'] === 'main' ? '<span class="tv-tag tv-tag--main">อุปกรณ์หลัก</span>' : '' ?></span>
                                        <span class="tv-muted">ตอนรับ: <?= (int)$it['qty_in'] === 0 ? 'ไม่มีมา' : (int)$it['qty_in'] . ' · ' . h(ITEM_CONDITION_LABELS[$it['condition_in']] ?? '') ?><?= $it['serial_no'] ? ' · ' . h($it['serial_no']) : '' ?></span>
                                    </div>
                                    <div class="jb-seg" role="radiogroup" aria-label="ผลตรวจ <?= h($it['item_name']) ?>">
                                        <?php foreach (ITEM_RESULT_LABELS as $rk => $rl): ?>
                                            <label><input type="radio" name="rc_result[<?= $cid ?>]" value="<?= h($rk) ?>" <?= $prev === $rk ? 'checked' : '' ?> data-rc-result><span><?= h($rl) ?></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="jb-rc-grid">
                                        <label class="jb-field c-qty">จำนวนที่คืน
                                            <input class="jb-input" type="number" min="0" max="999" name="rc_qty[<?= $cid ?>]" value="<?= (int)($it['qty_out'] ?? $it['qty_in']) ?>" data-rc-qty data-qty-in="<?= (int)$it['qty_in'] ?>">
                                        </label>
                                        <label class="jb-field c-note">หมายเหตุ
                                            <input class="jb-input" name="rc_note[<?= $cid ?>]" maxlength="255" value="<?= h($it['note_out'] ?? '') ?>">
                                        </label>
                                        <label class="jb-field c-photo">รูปตอนส่งคืน (ไม่บังคับ)
                                            <input type="file" name="rc_photo[<?= $cid ?>]" accept="image/jpeg,image/png,image/webp" data-photo-input>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <label class="jb-field" id="discrepancyBox" hidden>อธิบายข้อแตกต่าง <span class="text-red-600">*</span>
                                <textarea class="jb-input" name="discrepancy_note" rows="2" maxlength="2000" placeholder="เช่น ไม่พบสายชาร์จ ผู้แจ้งแจ้งว่าอาจลืมไว้ที่ห้อง"><?= h($ticket['discrepancy_note'] ?? '') ?></textarea>
                            </label>
                            <label class="jb-field">หมายเหตุการส่งคืน (ไม่บังคับ)
                                <input class="jb-input" name="return_note" maxlength="500" value="<?= h($ticket['return_note'] ?? '') ?>">
                            </label>
                        </fieldset>

                        <fieldset class="jb-step">
                            <legend>2. ผลการซ่อม</legend>
                            <label class="jb-field">รูปหลังซ่อมเสร็จ (หลักฐาน 1–3 รูป) <span class="text-red-600">*</span>
                                <input type="file" name="completion_images[]" multiple accept="image/jpeg,image/png,image/webp" required data-photo-input data-max="3">
                            </label>
                            <label class="jb-field">วิธีดำเนินการ / สิ่งที่ซ่อม <span class="text-red-600">*</span>
                                <textarea class="jb-input" name="repair_method" rows="3" required maxlength="5000" placeholder="เช่น เข้าหัวสาย RJ45 ใหม่ รีเซ็ต Switch"><?= h($ticket['repair_method'] ?? '') ?></textarea>
                            </label>
                            <label class="jb-field">สรุปผลการซ่อม <span class="text-red-600">*</span>
                                <input class="jb-input" name="resolution_summary" required maxlength="2000" placeholder="เช่น ใช้งานได้ปกติแล้ว" value="<?= h($ticket['resolution_summary'] ?? '') ?>">
                            </label>
                        </fieldset>

                        <fieldset class="jb-step">
                            <legend>3. เบิกวัสดุจากสต็อก (ถ้ามี)</legend>
                            <div id="stockRows" class="jb-stock-rows">
                                <div class="jb-stock-row">
                                    <select name="item_id[]" aria-label="วัสดุที่เบิก" class="jb-input">
                                        <option value="0">-- ไม่ได้เบิกวัสดุ --</option>
                                        <?php foreach ($inventoryItems as $inv): ?>
                                            <option value="<?= (int)$inv['id'] ?>"><?= h($inv['name']) ?> (เหลือ <?= (int)$inv['stock_qty'] ?> <?= h($inv['unit']) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="number" name="item_qty[]" min="1" max="1000" placeholder="จำนวน" aria-label="จำนวนที่เบิก" class="jb-input">
                                    <input type="text" name="item_note[]" maxlength="255" placeholder="หมายเหตุ (ถ้ามี)" aria-label="หมายเหตุการเบิก" class="jb-input">
                                </div>
                            </div>
                            <button type="button" class="jb-btn jb-btn--ghost jb-add" id="addStockRow"><i class="fa-solid fa-plus text-xs" aria-hidden="true"></i> เพิ่มวัสดุ</button>
                            <p class="tv-muted">ระบบบันทึกผู้เบิกเป็น <b><?= h(($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? '')) ?></b> และตัดสต็อกทันที (เบิกเกินยอดคงเหลือไม่ได้)</p>
                        </fieldset>

                        <div class="jb-submit">
                            <p>บันทึกแล้วไปขั้นเซ็นส่งมอบ</p>
                            <button type="submit" class="jb-btn">บันทึกผลซ่อมและผลตรวจซ้ำ</button>
                        </div>
                    </form>
                </section>
            <?php endif; ?>

            <?php /* ---------------------------------------------------------- 3. สตาฟเซ็นส่งมอบ */ ?>
            <?php if ($status === 'inspected_ready' && $isTeam): ?>
                <section class="tv-card">
                    <div class="tv-card-head">
                        <h2 class="tv-card-title">เซ็นส่งมอบงาน (ลำดับ 1 จาก 3)</h2>
                        <span class="tv-card-sub">เซ็นเมื่อส่งคืนอุปกรณ์ให้ผู้แจ้ง · เซ็นแล้วแก้ไขไม่ได้</span>
                    </div>
                    <form method="post" class="tv-card-body jb-form" data-busy>
                        <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">
                        <input type="hidden" name="action" value="staff_sign">
                        <input type="hidden" name="ticket_id" value="<?= (int)$ticketId ?>">
                        <?php if ((int)$ticket['has_discrepancy'] === 1): ?>
                            <div class="tv-alert"><b>มีข้อแตกต่างจากตอนรับ:</b> <?= h($ticket['discrepancy_note']) ?></div>
                        <?php endif; ?>
                        <?= signature_field($conn, $userId, '../', 'ลายเซ็นสตาฟผู้ส่งมอบ') ?>
                        <label class="jb-field">หมายเหตุ (ไม่บังคับ)
                            <input class="jb-input" name="sign_note" maxlength="255">
                        </label>
                        <div class="jb-submit">
                            <p>ต่อไปผู้แจ้งเซ็นรับคืน แล้วครูผู้ดูแลรับรองงาน</p>
                            <button type="submit" class="jb-btn">เซ็นส่งมอบ</button>
                        </div>
                    </form>
                </section>
            <?php endif; ?>

            <?php if (in_array($status, TICKET_SIGNING_STATUSES, true)): ?>
                <div class="tv-alert"><?= $status === 'awaiting_requester_sign'
                    ? 'รอผู้แจ้งเซ็นรับคืน (ตั้งแต่ ' . h(tv_dt($ticket['stage_since'])) . ')'
                    : ($status === 'disputed' ? '<b>ผู้แจ้งไม่ยอมรับ:</b> ' . h($ticket['return_reason']) . ' · รอครูพิจารณา' : 'รอครูผู้ดูแลรับรองงาน') ?></div>
            <?php endif; ?>
        </div>

        <aside class="jb-aside" aria-label="ข้อมูลประกอบใบงาน">
            <section class="tv-card jb-aside-sum">
                <div class="tv-card-head"><h2 class="tv-card-title">งานนี้</h2></div>
                <div class="tv-card-body"><?= $jobSummary() ?></div>
            </section>

            <?php if ($items): ?>
                <section class="tv-card">
                    <div class="tv-card-head"><h2 class="tv-card-title">เช็คลิสต์อุปกรณ์</h2>
                        <?php if ((int)$ticket['has_discrepancy'] === 1): ?><span class="tv-tag tv-tag--warn">พบข้อแตกต่าง</span><?php endif; ?>
                    </div>
                    <div class="tv-card-body"><?= tv_checklist($items, $ticketId, '../') ?></div>
                </section>
            <?php endif; ?>

            <?php if ($signoffs || in_array($status, array_merge(TICKET_SIGNING_STATUSES, ['inspected_ready', 'awaiting_intake_sign', 'completed']), true)): ?>
                <section class="tv-card">
                    <div class="tv-card-head"><h2 class="tv-card-title">ลายเซ็น</h2></div>
                    <div class="tv-card-body"><?= tv_signatures($signoffs, $ticketId, '../', $ticket) ?></div>
                </section>
            <?php endif; ?>

            <section class="tv-card">
                <div class="tv-card-head"><h2 class="tv-card-title">ประวัติ</h2></div>
                <div class="tv-card-body"><?= tv_timeline($logs) ?></div>
            </section>
        </aside>
    </div>
</main>

<script>
(function () {
    // ป้องกันกดส่งซ้ำ
    document.querySelectorAll('form[data-busy]').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            if (e.defaultPrevented) return;
            var b = f.querySelector('button[type=submit]');
            if (b) { setTimeout(function () { b.disabled = true; b.textContent = 'กำลังบันทึก...'; }, 0); }
        });
    });
    // เช็คลิสต์ตอนรับ: เพิ่ม/ลบแถว (ชื่อไฟล์รูปใช้ index ใหม่เสมอ)
    var list = document.getElementById('intakeItems');
    var nextIndex = list ? list.querySelectorAll('[data-item-row]').length : 0;
    var add = document.getElementById('addItemRow');
    // เลขรายการเรียงใหม่ทุกครั้ง และซ่อนปุ่มลบเมื่อเหลือรายการเดียว
    var renumber = function () {
        var rows = list.querySelectorAll('[data-item-row]');
        rows.forEach(function (r, i) {
            r.querySelector('[data-item-no]').textContent = 'รายการที่ ' + (i + 1);
            r.querySelector('[data-remove-row]').hidden = rows.length <= 1;
        });
    };
    if (add && list) {
        add.addEventListener('click', function () {
            var row = list.querySelector('[data-item-row]').cloneNode(true);
            row.querySelectorAll('input').forEach(function (i) { i.value = i.type === 'number' ? '1' : ''; i.required = false; });
            row.querySelector('select[name="it_type[]"]').value = 'accessory';
            row.querySelector('select[name="it_cond[]"]').selectedIndex = 0;
            row.querySelector('[data-photo]').name = 'it_photo[' + (nextIndex++) + ']';
            if (window.RepsPhotoInput) window.RepsPhotoInput.reset(row);
            list.appendChild(row);
            renumber();
            row.querySelector('input[name="it_name[]"]').focus();
        });
        list.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-remove-row]');
            if (!btn) return;
            if (list.querySelectorAll('[data-item-row]').length <= 1) return;
            var row = btn.closest('[data-item-row]');
            var next = row.nextElementSibling || row.previousElementSibling;
            row.remove();
            renumber();
            if (next) next.querySelector('input[name="it_name[]"]').focus();
        });
        renumber();
    }
    // ตรวจซ้ำ: เน้นรายการที่ต่างจากตอนรับ และบังคับคำอธิบายเมื่อมีข้อแตกต่าง
    var rcForm = document.getElementById('recheckForm');
    if (rcForm) {
        var box = document.getElementById('discrepancyBox');
        var update = function () {
            var diff = false;
            rcForm.querySelectorAll('[data-rc]').forEach(function (rc) {
                var res = rc.querySelector('[data-rc-result]:checked');
                var qty = rc.querySelector('[data-rc-qty]');
                var d = !res || res.value !== 'returned' || Number(qty.value) !== Number(qty.dataset.qtyIn);
                rc.classList.toggle('is-diff', d);
                diff = diff || d;
            });
            box.hidden = !diff;
            box.querySelector('textarea').required = diff;
        };
        rcForm.addEventListener('change', update);
        rcForm.addEventListener('input', update);
        update();
        document.getElementById('addStockRow').addEventListener('click', function () {
            var c = document.getElementById('stockRows');
            var clone = c.firstElementChild.cloneNode(true);
            clone.querySelector('select').value = '0';
            clone.querySelectorAll('input').forEach(function (i) { i.value = ''; });
            c.appendChild(clone);
        });
    }
})();
</script>
<?php if ($adminShell): ?>
      </div>
<?php $footerBase = '../'; $footerVariant = 'app'; require __DIR__ . '/../includes/site_footer.php'; ?>
   </div>
<?php else: ?>
<?php $footerBase = '../'; $footerVariant = 'light'; require __DIR__ . '/../includes/site_footer.php'; ?>
<?php endif; ?>
</body>
</html>
