<?php
declare(strict_types=1);

/**
 * REPS · staff/inventory.php — สต็อกวัสดุ (หัวข้อ 3.11)
 * - staff/admin: ดูยอดคงเหลือ แจ้งเตือนของใกล้หมด และประวัติการเคลื่อนไหว
 * - admin: เพิ่ม/แก้ไขวัสดุ รับเข้า ปรับยอด (บันทึก inventory_movements ทุกครั้ง)
 * การเบิกใช้ทำตอนสตาฟปิดงานในหน้า staff_dashboard (ผูกกับใบงาน)
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/user_auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/site_config.php';
require_once __DIR__ . '/../includes/db_helpers.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/private_storage.php';

$userId = require_role($conn, ['staff', 'admin'], '../login.php?next=staff');
$canManage = can(current_role(), 'inventory.manage');
$csrfToken = app_csrf_token();
$siteName = app_site_name($conn);

function inv_h(mixed $v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function inv_redirect(string $msg, string $type = 'ok'): never
{
    $_SESSION['inventory_flash'] = ['msg' => $msg, 'type' => $type];
    header('Location: inventory.php', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_csrf_is_valid($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('คำขอหมดอายุ กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
    if (!$canManage) {
        http_response_code(403);
        exit('เฉพาะผู้ดูแลระบบเท่านั้นที่จัดการสต็อกได้');
    }
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $str = static fn(string $k): string => trim(is_string($_POST[$k] ?? null) ? $_POST[$k] : '');
    $int = static function (string $k): ?int {
        $v = filter_var($_POST[$k] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => -100000, 'max_range' => 100000]]);
        return $v === false ? null : $v;
    };

    try {
        if ($action === 'save_item') {
            $itemId = $int('item_id') ?? 0;
            $code = $str('item_code');
            $name = $str('name');
            $unit = $str('unit') ?: 'ชิ้น';
            $minStock = $int('min_stock');
            if ($name === '' || mb_strlen($name) > 255 || mb_strlen($code) > 50 || mb_strlen($unit) > 50 || $minStock === null || $minStock < 0) {
                inv_redirect('กรุณากรอกชื่อวัสดุ หน่วย และจุดแจ้งเตือน (ตัวเลขตั้งแต่ 0) ให้ถูกต้อง', 'err');
            }
            $codeVal = $code !== '' ? $code : null;
            // รูปวัสดุ (ไม่บังคับ) — ตรวจ MIME/ขนาดและย่อใน app_store_uploaded_image
            $newImage = null;
            if ((int)($_FILES['item_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                try {
                    $newImage = app_store_uploaded_image($_FILES['item_image'], 'inv', 5 * 1048576, 1200);
                } catch (AppUserException $e) {
                    inv_redirect($e->getMessage(), 'err');
                }
            }
            if ($itemId > 0) {
                $old = db_one($conn, 'SELECT image_path FROM inventory_items WHERE id = ?', 'i', [$itemId]);
                if (!$old) {
                    app_delete_private_file($newImage);
                    inv_redirect('ไม่พบวัสดุนี้', 'err');
                }
                $removeImage = ($_POST['remove_image'] ?? '') === '1';
                $imagePath = $newImage ?? ($removeImage ? null : $old['image_path']);
                try {
                    db_exec($conn, 'UPDATE inventory_items SET item_code = ?, name = ?, unit = ?, min_stock = ?, image_path = ? WHERE id = ?', 'sssisi', [$codeVal, $name, $unit, $minStock, $imagePath, $itemId]);
                } catch (Throwable $e) {
                    app_delete_private_file($newImage);
                    throw $e;
                }
                if ($old['image_path'] && $old['image_path'] !== $imagePath) {
                    app_delete_private_file($old['image_path']);
                }
                inv_redirect('บันทึกข้อมูลวัสดุแล้ว');
            }
            $initial = max(0, $int('stock_qty') ?? 0);
            try {
                db_transaction($conn, function () use ($conn, $codeVal, $name, $unit, $minStock, $initial, $userId, $newImage) {
                    $newId = db_insert($conn, 'INSERT INTO inventory_items (item_code, name, unit, image_path, stock_qty, min_stock) VALUES (?, ?, ?, ?, ?, ?)', 'ssssii', [$codeVal, $name, $unit, $newImage, $initial, $minStock]);
                    if ($initial > 0) {
                        db_exec(
                            $conn,
                            "INSERT INTO inventory_movements (item_id, movement_type, qty_change, stock_before, stock_after, action_by, note) VALUES (?, 'receive', ?, 0, ?, ?, 'ยอดยกมาเมื่อเพิ่มวัสดุ')",
                            'iiii',
                            [$newId, $initial, $initial, $userId]
                        );
                    }
                });
            } catch (Throwable $e) {
                app_delete_private_file($newImage);
                throw $e;
            }
            inv_redirect('เพิ่มวัสดุใหม่แล้ว');
        }

        if ($action === 'receive' || $action === 'adjust') {
            $itemId = $int('item_id') ?? 0;
            $qty = $int('qty');
            $note = mb_substr($str('note'), 0, 255);
            if ($itemId <= 0 || $qty === null || ($action === 'receive' && $qty <= 0) || ($action === 'adjust' && $qty < 0)) {
                inv_redirect($action === 'receive' ? 'จำนวนรับเข้าต้องมากกว่า 0' : 'ยอดคงเหลือใหม่ต้องเป็นตัวเลขตั้งแต่ 0', 'err');
            }
            if ($action === 'adjust' && $note === '') {
                inv_redirect('การปรับยอดต้องระบุเหตุผล', 'err');
            }
            db_transaction($conn, function () use ($conn, $action, $itemId, $qty, $note, $userId) {
                $item = db_one($conn, 'SELECT stock_qty FROM inventory_items WHERE id = ? FOR UPDATE', 'i', [$itemId]);
                if (!$item) {
                    throw new RuntimeException('Item not found');
                }
                $before = (int)$item['stock_qty'];
                $after = $action === 'receive' ? $before + $qty : $qty;
                db_exec($conn, 'UPDATE inventory_items SET stock_qty = ? WHERE id = ?', 'ii', [$after, $itemId]);
                db_exec(
                    $conn,
                    'INSERT INTO inventory_movements (item_id, movement_type, qty_change, stock_before, stock_after, action_by, note) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    'isiiiis',
                    [$itemId, $action, $after - $before, $before, $after, $userId, $note !== '' ? $note : null]
                );
            });
            inv_redirect($action === 'receive' ? 'บันทึกรับเข้าแล้ว' : 'ปรับยอดคงเหลือแล้ว');
        }
    } catch (Throwable $e) {
        error_log('Inventory action failed: ' . $e->getMessage());
        $duplicate = str_contains($e->getMessage(), 'Duplicate');
        inv_redirect($duplicate ? 'รหัสวัสดุนี้มีอยู่แล้ว' : 'ทำรายการไม่สำเร็จ กรุณาลองใหม่', 'err');
    }
    inv_redirect('คำสั่งไม่ถูกต้อง', 'err');
}

$flash = is_array($_SESSION['inventory_flash'] ?? null) ? $_SESSION['inventory_flash'] : null;
unset($_SESSION['inventory_flash']);

$items = db_all($conn, 'SELECT id, item_code, name, unit, image_path, stock_qty, min_stock, updated_at FROM inventory_items ORDER BY (stock_qty <= min_stock) DESC, name');
/** URL รูปวัสดุ (v = ชื่อไฟล์ เพื่อให้ cache เปลี่ยนเมื่อเปลี่ยนรูป) */
$invImageUrl = static fn(array $it): string => $it['image_path']
    ? '../file.php?k=inv&i=' . (int)$it['id'] . '&v=' . substr(md5((string)$it['image_path']), 0, 8)
    : '';
$lowCount = count(array_filter($items, static fn($i) => (int)$i['stock_qty'] <= (int)$i['min_stock']));
$movements = db_all(
    $conn,
    "SELECT m.*, i.name AS item_name, i.unit, u.first_name, u.last_name, t.ticket_no
       FROM inventory_movements m
       JOIN inventory_items i ON i.id = m.item_id
       LEFT JOIN users u ON u.user_id = m.action_by
       LEFT JOIN tickets t ON t.id = m.ticket_id
      ORDER BY m.created_at DESC, m.id DESC LIMIT 50"
);
$typeLabels = ['receive' => 'รับเข้า', 'withdraw' => 'เบิกใช้', 'adjust' => 'ปรับยอด'];

// ---------- ภาพรวมด้านบน ----------
$stockOut = count(array_filter($items, static fn($i) => (int)$i['stock_qty'] === 0));
$stockLow = $lowCount - $stockOut;
$stockOk = count($items) - $lowCount;
$reorder = array_values(array_filter($items, static fn($i) => (int)$i['stock_qty'] <= (int)$i['min_stock']));
usort($reorder, static fn($a, $b) => ((int)$a['stock_qty'] / max(1, (int)$a['min_stock'])) <=> ((int)$b['stock_qty'] / max(1, (int)$b['min_stock'])));

$trendDays = 30;
$withdrawDaily = [];
$receiveDaily = [];
for ($i = $trendDays - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} day"));
    $withdrawDaily[$d] = 0;
    $receiveDaily[$d] = 0;
}
foreach (db_all(
    $conn,
    "SELECT DATE(created_at) AS d, movement_type, COUNT(*) AS n FROM inventory_movements
      WHERE created_at >= CURDATE() - INTERVAL ? DAY AND movement_type IN ('withdraw', 'receive')
      GROUP BY DATE(created_at), movement_type",
    'i',
    [$trendDays - 1]
) as $row) {
    if ($row['movement_type'] === 'withdraw' && isset($withdrawDaily[$row['d']])) $withdrawDaily[$row['d']] = (int)$row['n'];
    if ($row['movement_type'] === 'receive' && isset($receiveDaily[$row['d']])) $receiveDaily[$row['d']] = (int)$row['n'];
}
$topItems = db_all(
    $conn,
    "SELECT i.name, i.unit, COUNT(*) AS times, SUM(-m.qty_change) AS qty
       FROM inventory_movements m JOIN inventory_items i ON i.id = m.item_id
      WHERE m.movement_type = 'withdraw' AND m.created_at >= CURDATE() - INTERVAL ? DAY
      GROUP BY m.item_id, i.name, i.unit ORDER BY times DESC, qty DESC LIMIT 4",
    'i',
    [$trendDays - 1]
);

/** กราฟพื้นที่ (SVG) จากค่ารายวัน */
function inv_area_chart(array $values, string $label): string
{
    $w = 280;
    $h = 72;
    $pad = 4;
    $n = count($values);
    $max = max(1, ...array_values($values));
    $points = [];
    $i = 0;
    foreach ($values as $v) {
        $x = $pad + ($n > 1 ? $i * ($w - 2 * $pad) / ($n - 1) : 0);
        $y = $h - $pad - ($v / $max) * ($h - 2 * $pad - 6);
        $points[] = [round($x, 1), round($y, 1)];
        $i++;
    }
    // เส้นโค้งนุ่มด้วย midpoint quadratic
    $d = 'M' . $points[0][0] . ' ' . $points[0][1];
    for ($k = 1; $k < $n; $k++) {
        $mx = ($points[$k - 1][0] + $points[$k][0]) / 2;
        $my = ($points[$k - 1][1] + $points[$k][1]) / 2;
        $d .= ' Q' . $points[$k - 1][0] . ' ' . $points[$k - 1][1] . ' ' . round($mx, 1) . ' ' . round($my, 1);
    }
    $last = end($points);
    $d .= ' T' . $last[0] . ' ' . $last[1];
    $area = $d . ' L' . $last[0] . ' ' . ($h - $pad) . ' L' . $points[0][0] . ' ' . ($h - $pad) . ' Z';
    $total = array_sum($values);
    $aria = $label . ' 30 วันล่าสุด รวม ' . $total . ' ครั้ง สูงสุด ' . max($values) . ' ครั้งต่อวัน';
    $e = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    return '<svg class="ix-area" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" role="img" aria-label="' . $e($aria) . '">'
        . '<defs><linearGradient id="ixAreaFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="currentColor" stop-opacity=".22"/><stop offset="1" stop-color="currentColor" stop-opacity="0"/></linearGradient></defs>'
        . '<path class="ix-area__fill" d="' . $area . '" fill="url(#ixAreaFill)"/>'
        . '<path class="ix-area__line" d="' . $d . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" pathLength="1"/>'
        . '<circle class="ix-area__dot" cx="' . $last[0] . '" cy="' . $last[1] . '" r="3.5" fill="currentColor"/>'
        . '</svg>';
}
?>
<?php $adminShell = current_role() === 'admin'; ?>
<?php if ($adminShell):
    $page_title = 'สต็อกวัสดุ';
    $current_page = 'inventory';
    ob_start(); ?>
<script src="https://cdn.tailwindcss.com"></script>
<?= app_theme_css($conn) ?>
<style>
        body { background: #FAFAF8; color: #161616; font-family: 'Kanit', sans-serif; }
        .tray { padding: 6px; border: 1px solid #EAE8E4; border-radius: 20px; background: #F7F6F4; }
        .tray-card { border: 1px solid #ECEAE6; border-radius: 14px; background: #FFFFFF; }
        .inv-btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; min-height: 44px; padding: 0 .9rem; border: 1px solid #D4D0C9; border-radius: .6rem; background: #fff; color: #161616; font-size: .875rem; font-weight: 500; }
        .inv-btn:hover { background: #F5F4F1; }
        .inv-field { display: flex; flex-direction: column; gap: .375rem; font-size: .875rem; font-weight: 500; color: #3F3D39; }
        .inv-input { width: 100%; min-height: 44px; padding: 0 .75rem; border: 1px solid #C9C5BE; border-radius: .6rem; background: #fff; font-size: 1rem; font-weight: 400; color: #161616; }
        .inv-input:focus { border-color: #161616; outline: none; box-shadow: 0 0 0 3px rgba(22, 22, 22, .08); }
        .inv-dialog { width: min(480px, calc(100% - 2rem)); margin: auto; padding: 0; border: 1px solid #E7E4DF; border-radius: 1rem; box-shadow: 0 24px 48px rgba(22, 22, 22, .18); }
        .inv-dialog::backdrop { background: rgba(22, 22, 22, .45); }
    </style>
<?php $admin_extra_head = ob_get_clean();
    require __DIR__ . '/../admin/admin_header.php';
else: ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>สต็อกวัสดุ | <?= inv_h($siteName) ?></title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo/reps-icon.svg">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <?= app_theme_css($conn) ?>
    <style>
        body { background: #FAFAF8; color: #161616; font-family: 'Kanit', sans-serif; }
        .tray { padding: 6px; border: 1px solid #EAE8E4; border-radius: 20px; background: #F7F6F4; }
        .tray-card { border: 1px solid #ECEAE6; border-radius: 14px; background: #FFFFFF; }
        .inv-btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; min-height: 44px; padding: 0 .9rem; border: 1px solid #D4D0C9; border-radius: .6rem; background: #fff; color: #161616; font-size: .875rem; font-weight: 500; }
        .inv-btn:hover { background: #F5F4F1; }
        .inv-field { display: flex; flex-direction: column; gap: .375rem; font-size: .875rem; font-weight: 500; color: #3F3D39; }
        .inv-input { width: 100%; min-height: 44px; padding: 0 .75rem; border: 1px solid #C9C5BE; border-radius: .6rem; background: #fff; font-size: 1rem; font-weight: 400; color: #161616; }
        .inv-input:focus { border-color: #161616; outline: none; box-shadow: 0 0 0 3px rgba(22, 22, 22, .08); }
        .inv-dialog { width: min(480px, calc(100% - 2rem)); margin: auto; padding: 0; border: 1px solid #E7E4DF; border-radius: 1rem; box-shadow: 0 24px 48px rgba(22, 22, 22, .18); }
        .inv-dialog::backdrop { background: rgba(22, 22, 22, .45); }
    </style>
   <link rel="stylesheet" href="../assets/css/base.css?v=18">
</head>
<body class="min-h-screen antialiased">
    <?php $staffNavActive = 'inventory'; $staffNavBadge = $lowCount; require __DIR__ . '/../includes/staff_nav.php'; ?>
<?php endif; ?>

    <main class="<?= $adminShell ? 'w-full space-y-6' : 'max-w-6xl mx-auto px-4 sm:px-7 py-7 space-y-6' ?>">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-medium text-neutral-900"><?= tr('สต็อกวัสดุและอะไหล่', 'Materials and spare parts') ?></h1>
                <p class="mt-1 max-w-2xl text-sm text-neutral-600">การเบิกใช้ทำตอนสตาฟส่งปิดงาน ระบบตัดสต็อกให้อัตโนมัติและไม่ให้ยอดติดลบ<?= $canManage ? '' : ' · การเพิ่มและปรับยอดทำได้เฉพาะผู้ดูแลระบบ' ?></p>
            </div>
            <?php if ($canManage): ?>
                <button type="button" data-open="newItemDialog" class="inline-flex items-center justify-center gap-2 min-h-[44px] px-4 rounded-xl bg-neutral-900 text-white text-sm font-medium hover:bg-black">
                    <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i> เพิ่มวัสดุ
                </button>
            <?php endif; ?>
        </div>

        <?php if ($flash): ?>
            <div role="status" class="rounded-xl border px-4 py-3 text-sm <?= ($flash['type'] ?? '') === 'err' ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' ?>"><?= inv_h($flash['msg'] ?? '') ?></div>
        <?php endif; ?>

        <style>
            /* ===== สต็อก: การ์ดภาพรวม + แถบระดับสต็อก ===== */
            .ix-cards { display: grid; gap: 16px; grid-template-columns: repeat(4, minmax(0, 1fr)); }
            @media (max-width: 1180px) { .ix-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
            @media (max-width: 620px) { .ix-cards { grid-template-columns: minmax(0, 1fr); } }
            .ix-card { position: relative; display: flex; flex-direction: column; gap: 12px; min-width: 0; padding: 18px 18px 16px; border: 1px solid #E7E4DF; border-radius: 16px; background: #fff; opacity: 0; transform: translateY(8px); animation: ix-rise .55s cubic-bezier(.16, 1, .3, 1) forwards; animation-delay: calc(var(--i, 0) * 70ms); }
            .ix-card-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
            .ix-card-title { margin: 0; font-size: 14px; font-weight: 500; color: #3F3D39; }
            .ix-card-icon { display: grid; place-items: center; width: 32px; height: 32px; border-radius: 10px; background: #F4F3F0; color: #57544F; font-size: 13px; }
            .ix-card--alert .ix-card-icon { color: #B45309; }
            .ix-big { display: flex; align-items: baseline; gap: 6px; }
            .ix-big strong { font-size: 32px; font-weight: 600; line-height: 1; letter-spacing: -.02em; font-variant-numeric: tabular-nums; color: #161616; }
            .ix-big span { font-size: 13px; color: #6B6862; }
            .ix-card--alert .ix-big strong { color: #B45309; }
            .ix-foot { margin-top: auto; font-size: 13px; color: #6B6862; }

            /* แถบสัดส่วนสถานะ */
            .ix-split { display: flex; height: 10px; overflow: hidden; border-radius: 999px; background: #F1EFEC; }
            .ix-split span { height: 100%; transform-origin: left; transform: scaleX(0); animation: ix-grow-x .9s cubic-bezier(.16, 1, .3, 1) forwards; animation-delay: .25s; }
            .ix-legend { display: flex; flex-wrap: wrap; gap: 4px 14px; font-size: 12.5px; color: #57544F; }
            .ix-legend i { display: inline-block; width: 8px; height: 8px; margin-right: 6px; border-radius: 50%; vertical-align: 1px; }

            /* รายการต้องสั่งเพิ่ม */
            .ix-mini-list { display: grid; gap: 9px; margin: 0; padding: 0; list-style: none; }
            .ix-mini-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 2px 10px; font-size: 13px; }
            .ix-mini-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #161616; }
            .ix-mini-val { color: #6B6862; font-variant-numeric: tabular-nums; }
            .ix-mini-bar { grid-column: 1 / -1; height: 6px; overflow: hidden; border-radius: 999px; background: #F1EFEC; }
            .ix-mini-bar span { display: block; height: 100%; border-radius: inherit; transform-origin: left; transform: scaleX(0); animation: ix-grow-x .9s cubic-bezier(.16, 1, .3, 1) forwards; animation-delay: calc(.3s + var(--j, 0) * 90ms); }

            /* กราฟพื้นที่ */
            .ix-area { display: block; width: 100%; height: 72px; color: var(--theme-primary, #161616); }
            .ix-area__line { stroke-dasharray: 1; stroke-dashoffset: 1; animation: ix-draw 1.2s cubic-bezier(.65, 0, .35, 1) .3s forwards; }
            .ix-area__fill { opacity: 0; animation: ix-fade .8s ease .9s forwards; }
            .ix-area__dot { opacity: 0; animation: ix-fade .3s ease 1.4s forwards; }

            @keyframes ix-rise { to { opacity: 1; transform: none; } }
            @keyframes ix-grow-x { to { transform: scaleX(1); } }
            @keyframes ix-draw { to { stroke-dashoffset: 0; } }
            @keyframes ix-fade { to { opacity: 1; } }

            /* แถวรายการสต็อก: บรรทัดเดียวบนจอกว้าง */
            .ix-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; grid-template-areas: "name qty" "track track" "chip act"; align-items: center; gap: 8px 16px; padding: 12px 20px; }
            .ix-row > .ix-name { grid-area: name; } .ix-row > .ix-qty { grid-area: qty; } .ix-row > .ix-track { grid-area: track; } .ix-row > div:nth-child(4) { grid-area: chip; } .ix-row > .ix-actions { grid-area: act; justify-self: end; }
            .ix-row--head { display: none; }
            @media (min-width: 1024px) {
                .ix-row, .ix-row--head { grid-template-columns: minmax(0, 1fr) 7.5rem minmax(7rem, 11rem) 6.5rem 15rem; grid-template-areas: none; gap: 24px; }
                .ix-row--view { grid-template-columns: minmax(0, 1fr) 7.5rem minmax(7rem, 14rem) 6.5rem; }
                .ix-row > * { grid-area: auto !important; }
                .ix-row--head { display: grid; padding-top: 10px; padding-bottom: 10px; background: #FAFAF8; font-size: 12px; font-weight: 500; color: #57544F; }
            }
            .ix-row:not(.ix-row--head):hover { background: #FCFBFA; }
            .ix-num { text-align: right; }
            .ix-name { display: flex; align-items: center; gap: 12px; min-width: 0; }
            .ix-thumb { position: relative; display: grid; flex: none; place-items: center; width: 44px; height: 44px; overflow: hidden; border: 1px solid #E7E4DF; border-radius: 10px; background: #F4F3F0; color: #A8A39B; font-size: 16px; }
            .ix-thumb img { width: 100%; height: 100%; object-fit: cover; }
            .ix-thumb-zoom { position: absolute; inset: 0; display: grid; place-items: center; background: rgba(22, 22, 22, .45); color: #fff; font-size: 14px; opacity: 0; transition: opacity .15s ease; }
            a.ix-thumb:hover .ix-thumb-zoom, a.ix-thumb:focus-visible .ix-thumb-zoom { opacity: 1; }
            a.ix-thumb:focus-visible { outline: 2px solid #161616; outline-offset: 2px; }
            .ix-thumb--none { border-style: dashed; background: #FAFAF8; }
            .inv-current { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border: 1px solid #ECEAE6; border-radius: 12px; background: #FAFAF8; }
            .inv-current[hidden] { display: none; }
            .inv-dialog { max-height: calc(100dvh - 2rem); overflow-y: auto; }
            .ix-name-main { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 500; color: #161616; }
            .ix-name-sub { font-size: 12.5px; color: #6B6862; }
            .ix-qty { font-size: 13px; color: #6B6862; line-height: 1.3; white-space: nowrap; }
            .ix-qty strong { font-size: 16px; font-weight: 600; color: #161616; font-variant-numeric: tabular-nums; }
            .ix-qty-min { display: block; font-size: 12px; color: #8A867F; }
            .ix-actions { display: inline-flex; border: 1px solid #D4D0C9; border-radius: 10px; overflow: hidden; background: #fff; }
            .ix-act { display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 11px; border: 0; background: transparent; color: #161616; font-size: 13px; font-weight: 500; white-space: nowrap; cursor: pointer; }
            .ix-act + .ix-act { border-left: 1px solid #E7E4DF; }
            .ix-act i { font-size: 11px; color: #57544F; }
            .ix-act:hover { background: #F5F4F1; }
            .ix-act--icon { width: 36px; justify-content: center; padding: 0; }
            @media (pointer: coarse) { .ix-act { height: 44px; } .ix-act--icon { width: 44px; } }
            /* แถบระดับสต็อกในรายการ */
            .ix-chip { display: inline-flex; align-items: center; gap: 6px; padding: 1px 9px; border: 1px solid #E7E4DF; border-radius: 999px; background: #FAFAF8; font-size: 12px; font-weight: 500; color: #3F3D39; white-space: nowrap; }
            .ix-chip::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: var(--c, #6B6862); }
            .ix-chip--ok { --c: #BDB8B0; }
            .ix-chip--low { --c: #B45309; }
            .ix-chip--out { --c: #B45309; color: #B45309; border-color: #E9D3C0; }
            .ix-track { position: relative; height: 8px; border-radius: 999px; background: #F1EFEC; }
            .ix-track-fill { position: absolute; inset: 0 auto 0 0; border-radius: inherit; transform-origin: left; transform: scaleX(0); animation: ix-grow-x .8s cubic-bezier(.16, 1, .3, 1) .15s forwards; }
            .ix-track-min { position: absolute; top: -3px; bottom: -3px; width: 2px; border-radius: 2px; background: #3F3D39; }
        </style>

        <?php
        $levelColor = static fn(int $qty, int $min): string => $qty <= $min ? '#B45309' : 'var(--theme-primary, #161616)';
        $withdrawTotal = array_sum($withdrawDaily);
        $receiveTotal = array_sum($receiveDaily);
        $topMax = max(1, ...array_map(static fn($t) => (int)$t['times'], $topItems ?: [['times' => 1]]));
        $itemCount = count($items);
        ?>
        <section class="ix-cards" aria-label="ภาพรวมสต็อก">
            <article class="ix-card" style="--i:0">
                <div class="ix-card-head">
                    <h2 class="ix-card-title">วัสดุทั้งหมด</h2>
                    <span class="ix-card-icon" aria-hidden="true"><i class="fa-solid fa-boxes-stacked"></i></span>
                </div>
                <div class="ix-big"><strong><?= number_format($itemCount) ?></strong><span>รายการ</span></div>
                <div class="ix-split" role="img" aria-label="สถานะสต็อก: ปกติ <?= $stockOk ?> ใกล้หมด <?= $stockLow ?> หมด <?= $stockOut ?>">
                    <?php foreach ([[$stockOk, 'var(--theme-primary, #161616)', 1], [$stockLow, '#B45309', .45], [$stockOut, '#B45309', 1]] as [$n, $c, $o]): if ($n <= 0 || $itemCount === 0) continue; ?>
                        <span style="width: <?= round($n / $itemCount * 100, 2) ?>%; background: <?= $c ?>; opacity: <?= $o ?>"></span>
                    <?php endforeach; ?>
                </div>
                <div class="ix-legend">
                    <span><i style="background: var(--theme-primary, #161616)"></i>ปกติ <?= $stockOk ?></span>
                    <span><i style="background: #B45309; opacity: .45"></i>ใกล้หมด <?= $stockLow ?></span>
                    <span><i style="background: #B45309"></i>หมด <?= $stockOut ?></span>
                </div>
            </article>

            <article class="ix-card <?= $reorder ? 'ix-card--alert' : '' ?>" style="--i:1">
                <div class="ix-card-head">
                    <h2 class="ix-card-title">ต้องสั่งเพิ่ม</h2>
                    <span class="ix-card-icon" aria-hidden="true"><i class="fa-solid fa-cart-flatbed"></i></span>
                </div>
                <div class="ix-big"><strong><?= count($reorder) ?></strong><span>รายการ<?= $reorder ? '' : ' · สต็อกพอใช้ทุกรายการ' ?></span></div>
                <?php if ($reorder): ?>
                    <ul class="ix-mini-list">
                        <?php foreach (array_slice($reorder, 0, 3) as $j => $r):
                            $qty = (int)$r['stock_qty'];
                            $min = max(1, (int)$r['min_stock']); ?>
                            <li class="ix-mini-row">
                                <span class="ix-mini-name" title="<?= inv_h($r['name']) ?>"><?= inv_h($r['name']) ?></span>
                                <span class="ix-mini-val"><?= $qty ?> / <?= $min ?> <?= inv_h($r['unit']) ?></span>
                                <span class="ix-mini-bar" aria-hidden="true"><span style="--j:<?= $j ?>; width: <?= max(4, min(100, round($qty / $min * 100))) ?>%; background: #B45309"></span></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="ix-foot">ไม่มีวัสดุที่ต่ำกว่าจุดแจ้งเตือน</p>
                <?php endif; ?>
            </article>

            <article class="ix-card" style="--i:2">
                <div class="ix-card-head">
                    <h2 class="ix-card-title">การเบิกใช้ 30 วัน</h2>
                    <span class="ix-card-icon" aria-hidden="true"><i class="fa-solid fa-arrow-trend-up"></i></span>
                </div>
                <div class="ix-big"><strong><?= number_format($withdrawTotal) ?></strong><span>ครั้ง</span></div>
                <?= inv_area_chart($withdrawDaily, 'การเบิกใช้') ?>
                <div class="ix-foot">รับเข้า <?= number_format($receiveTotal) ?> ครั้ง · วันนี้เบิก <?= (int)end($withdrawDaily) ?> ครั้ง</div>
            </article>

            <article class="ix-card" style="--i:3">
                <div class="ix-card-head">
                    <h2 class="ix-card-title">เบิกบ่อยที่สุด</h2>
                    <span class="ix-card-icon" aria-hidden="true"><i class="fa-solid fa-ranking-star"></i></span>
                </div>
                <?php if ($topItems): ?>
                    <ul class="ix-mini-list">
                        <?php foreach ($topItems as $j => $t): ?>
                            <li class="ix-mini-row">
                                <span class="ix-mini-name" title="<?= inv_h($t['name']) ?>"><?= inv_h($t['name']) ?></span>
                                <span class="ix-mini-val"><?= (int)$t['times'] ?> ครั้ง · <?= number_format((int)$t['qty']) ?> <?= inv_h($t['unit']) ?></span>
                                <span class="ix-mini-bar" aria-hidden="true"><span style="--j:<?= $j ?>; width: <?= max(6, round((int)$t['times'] / $topMax * 100)) ?>%; background: var(--theme-primary, #161616); opacity: <?= 1 - $j * .18 ?>"></span></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="ix-foot">ยังไม่มีการเบิกใน 30 วันที่ผ่านมา</p>
                <?php endif; ?>
            </article>
        </section>


        <section aria-labelledby="stock-heading" class="rounded-2xl border border-[#E7E4DF] bg-white">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[#EFEDE9] px-5 py-4">
                <h2 id="stock-heading" class="text-base font-medium text-neutral-900">ยอดคงเหลือ</h2>
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-neutral-600"><?= count($items) ?> รายการ</span>
                    <?php if ($lowCount > 0): ?>
                        <span class="ix-chip ix-chip--low">ต่ำกว่าจุดแจ้งเตือน <?= $lowCount ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$items): ?>
                <p class="px-5 py-10 text-center text-sm text-neutral-600">ยังไม่มีวัสดุในสต็อก<?= $canManage ? ' กด "เพิ่มวัสดุ" เพื่อเริ่มต้น' : '' ?></p>
            <?php else: ?>
                <!-- หัวคอลัมน์ (จอกว้างเท่านั้น) -->
                <div class="ix-row ix-row--head<?= $canManage ? '' : ' ix-row--view' ?>" aria-hidden="true">
                    <span>วัสดุ</span>
                    <span class="ix-num">คงเหลือ</span>
                    <span>ระดับ <span class="font-normal text-neutral-500">· ขีด = จุดแจ้งเตือน</span></span>
                    <span>สถานะ</span>
                    <?php if ($canManage): ?><span class="ix-num">จัดการ</span><?php endif; ?>
                </div>
                <ul class="divide-y divide-[#EFEDE9]" data-pager data-gallery id="stockItems">
                    <?php foreach ($items as $it):
                        $qty = (int)$it['stock_qty'];
                        $min = (int)$it['min_stock'];
                        $scale = max($qty, $min * 3, 1);
                        $state = $qty === 0 ? 'out' : ($qty <= $min ? 'low' : 'ok');
                        $itemJson = ['id' => (int)$it['id'], 'name' => $it['name'], 'unit' => $it['unit'], 'qty' => $qty]; ?>
                        <li class="ix-row<?= $canManage ? '' : ' ix-row--view' ?>">
                            <div class="ix-name">
                                <?php if ($imgUrl = $invImageUrl($it)): ?>
                                    <a class="ix-thumb" href="<?= inv_h($imgUrl) ?>" data-image-viewer data-caption="<?= inv_h($it['name']) ?>" title="ดูรูป <?= inv_h($it['name']) ?>">
                                        <img src="<?= inv_h($imgUrl) ?>" alt="" loading="lazy" width="44" height="44">
                                        <span class="ix-thumb-zoom" aria-hidden="true"><i class="fa-solid fa-magnifying-glass-plus"></i></span>
                                        <span class="sr-only">ดูรูป <?= inv_h($it['name']) ?></span>
                                    </a>
                                <?php else: ?>
                                    <span class="ix-thumb ix-thumb--none" title="ยังไม่มีรูป"><i class="fa-regular fa-image" aria-hidden="true"></i><span class="sr-only">ยังไม่มีรูป</span></span>
                                <?php endif; ?>
                                <div class="min-w-0">
                                    <div class="ix-name-main"><?= inv_h($it['name']) ?></div>
                                    <div class="ix-name-sub"><span class="font-mono"><?= inv_h($it['item_code'] ?: 'ไม่มีรหัส') ?></span></div>
                                </div>
                            </div>
                            <div class="ix-num ix-qty">
                                <strong><?= number_format($qty) ?></strong> <?= inv_h($it['unit']) ?>
                                <span class="ix-qty-min">เตือนที่ <?= number_format($min) ?></span>
                            </div>
                            <div class="ix-track" role="img" aria-label="คงเหลือ <?= $qty ?> จุดแจ้งเตือน <?= $min ?> <?= inv_h($it['unit']) ?>">
                                <span class="ix-track-fill" style="width: <?= max($qty > 0 ? 2 : 0, round($qty / $scale * 100, 1)) ?>%; background: <?= $levelColor($qty, $min) ?>"></span>
                                <span class="ix-track-min" style="left: calc(<?= round($min / $scale * 100, 1) ?>% - 1px)"></span>
                            </div>
                            <div><span class="ix-chip ix-chip--<?= $state ?>"><?= ['ok' => 'ปกติ', 'low' => 'ใกล้หมด', 'out' => 'หมดสต็อก'][$state] ?></span></div>
                            <?php if ($canManage): ?>
                                <div class="ix-actions" role="group" aria-label="จัดการ <?= inv_h($it['name']) ?>">
                                    <button type="button" class="ix-act" data-open="receiveDialog" data-item='<?= inv_h(json_encode($itemJson, JSON_UNESCAPED_UNICODE)) ?>'>
                                        <i class="fa-solid fa-arrow-down" aria-hidden="true"></i>รับเข้า
                                    </button>
                                    <button type="button" class="ix-act" data-open="adjustDialog" data-item='<?= inv_h(json_encode($itemJson, JSON_UNESCAPED_UNICODE)) ?>'>
                                        <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>ปรับยอด
                                    </button>
                                    <button type="button" class="ix-act ix-act--icon" title="แก้ไขข้อมูลวัสดุ" aria-label="แก้ไข <?= inv_h($it['name']) ?>" data-open="editDialog" data-item='<?= inv_h(json_encode(['id' => (int)$it['id'], 'name' => $it['name'], 'unit' => $it['unit'], 'code' => (string)$it['item_code'], 'min' => $min, 'image' => $invImageUrl($it)], JSON_UNESCAPED_UNICODE)) ?>'>
                                        <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section aria-labelledby="move-heading" class="rounded-2xl border border-[#E7E4DF] bg-white">
            <div class="border-b border-[#EFEDE9] px-5 py-4">
                <h2 id="move-heading" class="text-base font-medium text-neutral-900">ประวัติการเคลื่อนไหวล่าสุด</h2>
                <p class="text-sm text-neutral-600">50 รายการล่าสุด ทั้งรับเข้า เบิกใช้ และปรับยอด</p>
            </div>
            <?php if (!$movements): ?>
                <p class="px-5 py-10 text-center text-sm text-neutral-600">ยังไม่มีประวัติการเคลื่อนไหว</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm" data-pager id="stockMoves">
                        <thead class="bg-[#FAFAF8] text-xs text-neutral-600">
                            <tr>
                                <th scope="col" class="px-5 py-2.5 text-left font-medium">เวลา</th>
                                <th scope="col" class="px-5 py-2.5 text-left font-medium">วัสดุ</th>
                                <th scope="col" class="px-5 py-2.5 text-left font-medium">รายการ</th>
                                <th scope="col" class="px-5 py-2.5 text-right font-medium">เปลี่ยนแปลง</th>
                                <th scope="col" class="px-5 py-2.5 text-right font-medium">ยอดก่อน <?= app_icon('arrow-right') ?> หลัง</th>
                                <th scope="col" class="px-5 py-2.5 text-left font-medium">ผู้ทำรายการ / หมายเหตุ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#EFEDE9]">
                            <?php foreach ($movements as $mv): $chg = (int)$mv['qty_change']; ?>
                                <tr>
                                    <td class="px-5 py-3 font-mono text-neutral-600 whitespace-nowrap"><?= inv_h(date('d/m/Y H:i', strtotime($mv['created_at']))) ?></td>
                                    <td class="px-5 py-3 text-neutral-900"><?= inv_h($mv['item_name']) ?></td>
                                    <td class="px-5 py-3 text-neutral-700"><?= inv_h($typeLabels[$mv['movement_type']] ?? $mv['movement_type']) ?><?= $mv['ticket_no'] ? ' · <span class="font-mono">#' . inv_h($mv['ticket_no']) . '</span>' : '' ?></td>
                                    <td class="px-5 py-3 text-right font-mono text-neutral-900"><?= $chg >= 0 ? '+' . $chg : $chg ?></td>
                                    <td class="px-5 py-3 text-right font-mono text-neutral-700 whitespace-nowrap"><?= (int)$mv['stock_before'] ?> <?= app_icon('arrow-right', 'text-neutral-500') ?> <?= (int)$mv['stock_after'] ?></td>
                                    <td class="px-5 py-3 text-neutral-700"><?= inv_h(trim(($mv['first_name'] ?? 'ระบบ') . ' ' . ($mv['last_name'] ?? ''))) ?><?= $mv['note'] ? '<div class="text-neutral-600">' . inv_h($mv['note']) . '</div>' : '' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php if ($canManage): ?>
        <?php
        $dialogs = [
            'receiveDialog' => ['รับวัสดุเข้าสต็อก', 'receive', 'บันทึกรับเข้า'],
            'adjustDialog'  => ['ปรับยอดคงเหลือ', 'adjust', 'บันทึกยอดใหม่'],
            'editDialog'    => ['แก้ไขข้อมูลวัสดุ', 'save_item', 'บันทึกข้อมูล'],
            'newItemDialog' => ['เพิ่มวัสดุใหม่', 'save_item', 'เพิ่มวัสดุ'],
        ];
        foreach ($dialogs as $dialogId => [$dialogTitle, $dialogAction, $submitLabel]): ?>
            <dialog id="<?= $dialogId ?>" aria-labelledby="<?= $dialogId ?>Title" class="inv-dialog">
                <form method="post" class="flex flex-col"<?= $dialogAction === 'save_item' ? ' enctype="multipart/form-data"' : '' ?>>
                    <input type="hidden" name="csrf" value="<?= inv_h($csrfToken) ?>">
                    <input type="hidden" name="action" value="<?= $dialogAction ?>">
                    <input type="hidden" name="item_id" value="<?= $dialogId === 'newItemDialog' ? '0' : '' ?>" data-field="id">
                    <div class="flex items-start justify-between gap-4 border-b border-[#EFEDE9] px-5 py-4">
                        <div class="min-w-0">
                            <h2 id="<?= $dialogId ?>Title" class="text-lg font-medium text-neutral-900"><?= inv_h($dialogTitle) ?></h2>
                            <?php if ($dialogId !== 'newItemDialog'): ?><p class="text-sm text-neutral-600" data-field="summary"></p><?php endif; ?>
                        </div>
                        <button type="button" data-close class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-neutral-600 hover:bg-neutral-100" aria-label="ปิด"><?= app_icon('x') ?></button>
                    </div>
                    <div class="space-y-4 px-5 py-5">
                        <?php if ($dialogAction === 'receive'): ?>
                            <label class="inv-field">จำนวนที่รับเข้า <span class="text-red-700">*</span>
                                <input class="inv-input" type="number" name="qty" min="1" max="100000" required inputmode="numeric">
                            </label>
                            <label class="inv-field">หมายเหตุ (เช่น เลขที่ใบส่งของ)
                                <input class="inv-input" name="note" maxlength="255">
                            </label>
                        <?php elseif ($dialogAction === 'adjust'): ?>
                            <label class="inv-field">ยอดคงเหลือจริงหลังนับ <span class="text-red-700">*</span>
                                <input class="inv-input" type="number" name="qty" min="0" max="100000" required inputmode="numeric">
                            </label>
                            <label class="inv-field">เหตุผลที่ปรับยอด <span class="text-red-700">*</span>
                                <input class="inv-input" name="note" maxlength="255" required>
                            </label>
                        <?php else: ?>
                            <label class="inv-field">ชื่อวัสดุ <span class="text-red-700">*</span>
                                <input class="inv-input" name="name" maxlength="255" required data-field="name">
                            </label>
                            <div class="grid grid-cols-2 gap-4">
                                <label class="inv-field">รหัสวัสดุ
                                    <input class="inv-input font-mono" name="item_code" maxlength="50" data-field="code">
                                </label>
                                <label class="inv-field">หน่วยนับ
                                    <input class="inv-input" name="unit" maxlength="50" value="ชิ้น" data-field="unit">
                                </label>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <?php if ($dialogId === 'newItemDialog'): ?>
                                    <label class="inv-field">ยอดเริ่มต้น
                                        <input class="inv-input" type="number" name="stock_qty" min="0" value="0" inputmode="numeric">
                                    </label>
                                <?php endif; ?>
                                <label class="inv-field <?= $dialogId === 'newItemDialog' ? '' : 'col-span-2' ?>">แจ้งเตือนเมื่อเหลือไม่เกิน
                                    <input class="inv-input" type="number" name="min_stock" min="0" value="5" required inputmode="numeric" data-field="min">
                                </label>
                            </div>
                            <?php if ($dialogId === 'editDialog'): ?>
                                <div class="inv-current" data-field="current" hidden>
                                    <a class="ix-thumb" data-field="current-link" href="#" data-image-viewer data-caption="รูปปัจจุบัน"><img data-field="current-img" src="" alt="รูปปัจจุบัน" width="44" height="44"></a>
                                    <div class="min-w-0 flex-1 text-sm text-neutral-700">รูปปัจจุบัน
                                        <label class="mt-1 flex items-center gap-2 text-sm font-normal text-neutral-700"><input type="checkbox" name="remove_image" value="1" class="h-4 w-4" data-field="remove"> ลบรูปนี้</label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="inv-field"><span data-field="image-label"><?= $dialogId === 'editDialog' ? 'เปลี่ยนรูป' : 'รูปวัสดุ' ?> <span class="font-normal text-neutral-500">(ไม่บังคับ · JPG, PNG, WEBP ไม่เกิน 5 MB)</span></span>
                                <input type="file" name="item_image" accept="image/jpeg,image/png,image/webp" data-photo-input data-max-mb="5" aria-label="<?= $dialogId === 'editDialog' ? 'เปลี่ยนรูปวัสดุ' : 'รูปวัสดุ' ?>">
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-[#EFEDE9] bg-[#FAFAF8] px-5 py-3">
                        <button type="button" data-close class="inv-btn">ยกเลิก</button>
                        <button type="submit" class="inline-flex items-center min-h-[44px] px-5 rounded-lg bg-neutral-900 text-sm font-medium text-white hover:bg-black"><?= inv_h($submitLabel) ?></button>
                    </div>
                </form>
            </dialog>
        <?php endforeach; ?>
        <script>
            (function () {
                document.querySelectorAll('[data-open]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const dialog = document.getElementById(btn.dataset.open);
                        const item = btn.dataset.item ? JSON.parse(btn.dataset.item) : null;
                        const form = dialog.querySelector('form');
                        if (btn.dataset.open !== 'newItemDialog') {
                            form.reset();
                            if (window.RepsPhotoInput) window.RepsPhotoInput.reset(form);
                        }
                        const current = dialog.querySelector('[data-field="current"]');
                        if (current) {
                            current.hidden = !(item && item.image);
                            if (item && item.image) {
                                dialog.querySelector('[data-field="current-img"]').src = item.image;
                                dialog.querySelector('[data-field="current-link"]').href = item.image;
                            }
                        }
                        if (item) {
                            dialog.querySelector('[data-field="id"]').value = item.id;
                            const summary = dialog.querySelector('[data-field="summary"]');
                            if (summary) summary.textContent = item.name + (item.qty !== undefined ? ' · คงเหลือ ' + item.qty + ' ' + item.unit : '');
                            ['name', 'code', 'unit', 'min'].forEach(function (key) {
                                const field = dialog.querySelector('[data-field="' + key + '"]');
                                if (field && item[key] !== undefined) field.value = item[key];
                            });
                        }
                        dialog.showModal();
                        const first = dialog.querySelector('.inv-input');
                        if (first) first.focus();
                    });
                });
                document.querySelectorAll('.inv-dialog [data-close]').forEach(function (btn) {
                    btn.addEventListener('click', function () { btn.closest('dialog').close(); });
                });
            })();
        </script>
    <?php endif; ?>
<?php if ($adminShell): ?>
      </div>
<?php $footerBase = '../'; $footerVariant = 'app'; require __DIR__ . '/../includes/site_footer.php'; ?>
   </div>
<?php else: ?>
<?php $footerBase = '../'; $footerVariant = 'light'; require __DIR__ . '/../includes/site_footer.php'; ?>
<?php endif; ?>
</body>
</html>
