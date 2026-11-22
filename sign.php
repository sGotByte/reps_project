<?php
declare(strict_types=1);

/**
 * REPS · sign.php — หน้าผู้แจ้งเซ็นรับทราบ/รับคืนของใบงานตัวเอง (v3 หัวข้อ 3–4)
 *   awaiting_intake_sign    -> เซ็นรับทราบเช็คลิสต์อุปกรณ์ที่ช่างรับไป (ช่างเริ่มซ่อมหลังเซ็น)
 *   awaiting_requester_sign -> ตรวจผลเทียบตอนรับ แล้วเซ็นรับคืน (ลำดับ 2) หรือ "ไม่ยอมรับ" พร้อมเหตุผล
 * เปิดได้เฉพาะผู้แจ้งของใบงาน (ตรวจ requester_id ฝั่งเซิร์ฟเวอร์ทุกครั้ง) ลิงก์จากอีเมลมาที่หน้านี้และต้องล็อกอินก่อน
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/user_auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/site_config.php';
require_once __DIR__ . '/includes/ticket_service.php';
require_once __DIR__ . '/includes/ticket_view.php';
require_once __DIR__ . '/includes/signature.php';
require_once __DIR__ . '/includes/thanks.php';
require_once __DIR__ . '/includes/user_menu.php';
require_once __DIR__ . '/includes/notifications.php';

/** @var mysqli $conn */
$ticketId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT) ?: 0;
$userId = (int)app_require_active_user($conn, 'login.php?next=' . rawurlencode('sign.php?id=' . $ticketId), null);
$csrfToken = app_csrf_token();
$siteName = app_site_name($conn);

function h(mixed $v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function sign_redirect(int $ticketId, string $msg, string $type = 'ok'): never
{
    $_SESSION['sign_flash'] = ['msg' => $msg, 'type' => $type];
    header('Location: sign.php?id=' . $ticketId, true, 303);
    exit;
}

$ticket = $ticketId > 0 ? tv_ticket($conn, $ticketId) : null;
if (!$ticket || (int)$ticket['requester_id'] !== $userId) {
    // ผู้ดูแล/สตาฟที่เปิดลิงก์ของใบงานคนอื่น พาไปหน้าของบทบาทตัวเอง
    if ($ticket && current_role() === 'admin') {
        header('Location: admin/admin_signoff.php?id=' . $ticketId, true, 303);
        exit;
    }
    if ($ticket && current_role() === 'staff') {
        header('Location: staff/job.php?id=' . $ticketId, true, 303);
        exit;
    }
    http_response_code(404);
    exit('ไม่พบใบงาน');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_csrf_is_valid($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('คำขอหมดอายุ กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $sigPath = null;
    try {
        if ($action === 'sign_intake' || $action === 'sign_return') {
            [$sigPath, $method] = signature_capture($conn, $userId);
            $note = trim((string)($_POST['sign_note'] ?? ''));
            if ($action === 'sign_intake') {
                ticket_requester_sign_intake($conn, $ticketId, $userId, $sigPath, $method, $note);
                sign_redirect($ticketId, 'เซ็นรับทราบรายการอุปกรณ์แล้ว ช่างจะเริ่มซ่อม');
            }
            ticket_requester_sign_return($conn, $ticketId, $userId, $sigPath, $method, ($_POST['ack_discrepancy'] ?? '') === '1', $note);
            sign_redirect($ticketId, 'เซ็นรับอุปกรณ์คืนแล้ว รอครูผู้ดูแลรับรองเพื่อปิดงาน');
        }
        if ($action === 'dispute') {
            ticket_requester_dispute($conn, $ticketId, $userId, (string)($_POST['dispute_reason'] ?? ''));
            sign_redirect($ticketId, 'บันทึกการไม่ยอมรับแล้ว ครูผู้ดูแลจะพิจารณาใบงานนี้', 'warn');
        }
        throw new AppUserException('คำสั่งไม่ถูกต้อง');
    } catch (AppUserException $e) {
        app_delete_private_file($sigPath);
        sign_redirect($ticketId, $e->getMessage(), 'err');
    } catch (Throwable $e) {
        app_delete_private_file($sigPath);
        error_log('sign.php action "' . $action . '" failed: ' . $e->getMessage());
        sign_redirect($ticketId, 'ทำรายการไม่สำเร็จ กรุณาลองใหม่อีกครั้ง', 'err');
    }
}

$flash = $_SESSION['sign_flash'] ?? null;
unset($_SESSION['sign_flash']);
$items = ticket_item_checks($conn, $ticketId);
$ticket['has_intake'] = (bool)$items;
$signoffs = ticket_signoffs($conn, $ticketId);
$logs = tv_logs($conn, $ticketId);
$status = (string)$ticket['status'];
$homeUrl = role_home_url(current_role());
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ใบงาน <?= h($ticket['ticket_no']) ?> | <?= h($siteName) ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/images/logo/reps-icon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <?= app_theme_css($conn) ?>
    <?= tv_assets() ?>
    <style>
        body { margin: 0; background: #FAFAF8; color: #161616; font-family: 'Kanit', system-ui, sans-serif; }
        .sg-top { position: sticky; top: 0; z-index: 30; border-bottom: 1px solid #ECEAE6; background: rgba(255, 255, 255, .95); backdrop-filter: blur(6px); }
        .sg-top-inner { display: flex; align-items: center; justify-content: space-between; gap: 12px; max-width: 72rem; min-height: 64px; margin: 0 auto; padding: 0 1.5rem; }
        .sg-top-right { display: flex; align-items: center; gap: 8px; }
        .sg-main { max-width: 56rem; margin: 0 auto; padding: 24px 1.5rem 48px; display: grid; gap: 16px; }
        .sg-main > * { min-width: 0; } /* ให้ grid หดตามจอ ไม่ถูกเนื้อหากว้าง (แถบขั้นตอน/ตาราง) ดันจนล้น */
        .sg-back { color: #57544F; font-size: 14px; text-decoration: none; }
        .sg-title { margin: 4px 0 0; font-size: 26px; font-weight: 500; }
        .sg-sub { margin: 2px 0 0; color: #6B6862; font-size: 14px; }
        .sg-flash { padding: 12px 14px; border-radius: 12px; font-size: 14px; }
        .sg-flash--ok { border: 1px solid #BBE5CC; background: #F0FAF4; color: #14532D; }
        .sg-flash--warn { border: 1px solid #F1D9BF; background: #FFFBF5; color: #7C2D12; }
        .sg-flash--err { border: 1px solid #F5C2C0; background: #FEF2F2; color: #7F1D1D; }
        .sg-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 46px; padding: 0 20px; border: 1px solid var(--theme-primary, #161616); border-radius: 12px; background: var(--theme-primary, #161616); color: #fff; font: inherit; font-size: 15px; font-weight: 500; cursor: pointer; }
        .sg-btn--ghost { border-color: #D4D0C9; background: #fff; color: #B45309; }
        .sg-input { width: 100%; min-height: 42px; margin-top: 4px; padding: 8px 10px; border: 1px solid #D4D0C9; border-radius: 10px; font: inherit; font-size: 15px; box-sizing: border-box; }
        .sg-field { display: block; font-size: 14px; color: #3F3D39; }
        .sg-ack { display: flex; gap: 10px; align-items: flex-start; padding: 12px; border: 1px solid #F1D9BF; border-radius: 12px; background: #FFFBF5; font-size: 14px; }
        .sg-ack input { width: 20px; height: 20px; margin-top: 2px; }
        .sg-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        details.sg-dispute summary { cursor: pointer; color: #B45309; font-size: 14px; }
        @media (max-width: 640px) { .sg-title { font-size: 22px; } }
    </style>
    <link rel="stylesheet" href="assets/css/base.css?v=18">
</head>
<body>
    <header class="sg-top">
        <div class="sg-top-inner">
            <a href="<?= h($homeUrl) ?>"><img src="assets/images/logo/reps-logo.svg" alt="REPS" style="height:24px"></a>
            <div class="sg-top-right">
                <?= notif_bell($conn, '') ?>
                <?= user_menu_render($conn, '') ?>
            </div>
        </div>
    </header>

    <main class="sg-main">
        <div>
            <a class="sg-back" href="dashboard.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> ใบงานของฉัน</a>
            <h1 class="sg-title">ใบงาน <?= h($ticket['ticket_no']) ?></h1>
            <p class="sg-sub"><?= h(TICKET_STATUS_LABELS[$status] ?? $status) ?> · <?= h($ticket['location_note']) ?></p>
        </div>

        <?php if ($flash): ?>
            <div role="status" class="sg-flash sg-flash--<?= h(in_array($flash['type'] ?? '', ['err', 'warn'], true) ? $flash['type'] : 'ok') ?>"><?= h($flash['msg'] ?? '') ?></div>
        <?php endif; ?>

        <section class="tv-card"><div class="tv-card-body"><?= tv_steps($status) ?></div></section>

        <?php if ($status === 'awaiting_intake_sign'): ?>
            <section class="tv-card">
                <div class="tv-card-head">
                    <h2 class="tv-card-title">ตรวจรายการอุปกรณ์ที่ช่างรับไป</h2>
                    <span class="tv-card-sub">ช่าง <?= h(trim(($ticket['staff_first'] ?? '') . ' ' . ($ticket['staff_last'] ?? ''))) ?> · <?= h(tv_dt($ticket['stage_since'])) ?></span>
                </div>
                <div class="tv-card-body">
                    <?= tv_checklist($items, $ticketId, '') ?>
                    <?php if (!empty($ticket['intake_note'])): ?><p class="tv-muted" style="margin-top:10px">หมายเหตุ: <?= h($ticket['intake_note']) ?></p><?php endif; ?>
                    <?php if (!empty($ticket['intake_photo'])): ?><a class="tv-photo" target="_blank" rel="noopener" href="file.php?t=<?= (int)$ticketId ?>&amp;k=intake">ดูรูปรวมอุปกรณ์ตอนรับ</a><?php endif; ?>
                </div>
            </section>
            <section class="tv-card">
                <div class="tv-card-head"><h2 class="tv-card-title">เซ็นรับทราบรายการ</h2></div>
                <form method="post" class="tv-card-body" style="display:grid;gap:14px">
                    <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="action" value="sign_intake">
                    <input type="hidden" name="ticket_id" value="<?= (int)$ticketId ?>">
                    <p class="tv-muted" style="margin:0">เซ็นเพื่อยืนยันว่ารายการและสภาพอุปกรณ์ข้างต้นถูกต้อง ใช้เป็นหลักฐานตรวจเทียบตอนส่งคืน หากรายการไม่ถูกต้อง กรุณาติดต่อช่างก่อนเซ็น</p>
                    <?= signature_field($conn, $userId, '', 'ลายเซ็นผู้แจ้ง') ?>
                    <label class="sg-field">หมายเหตุ (ไม่บังคับ)<input class="sg-input" name="sign_note" maxlength="255"></label>
                    <div class="sg-actions"><button type="submit" class="sg-btn">เซ็นรับทราบ</button></div>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($status === 'awaiting_requester_sign'): ?>
            <section class="tv-card">
                <div class="tv-card-head">
                    <h2 class="tv-card-title">ผลการซ่อมและการตรวจอุปกรณ์ก่อนส่งคืน</h2>
                    <span class="tv-card-sub"><?= (int)$ticket['has_discrepancy'] === 1 ? 'พบข้อแตกต่างจากตอนรับ' : 'ตรวจครบถ้วน' ?></span>
                </div>
                <div class="tv-card-body" style="display:grid;gap:14px">
                    <?= tv_summary($ticket, '', false) ?>
                    <?php if ((int)$ticket['has_discrepancy'] === 1): ?>
                        <div class="tv-alert"><b>ข้อแตกต่างจากตอนรับ:</b> <?= h($ticket['discrepancy_note']) ?></div>
                    <?php endif; ?>
                    <?= tv_checklist($items, $ticketId, '') ?>
                </div>
            </section>
            <section class="tv-card">
                <div class="tv-card-head"><h2 class="tv-card-title">เซ็นรับอุปกรณ์คืน (ลำดับ 2 จาก 3)</h2></div>
                <form method="post" class="tv-card-body" style="display:grid;gap:14px">
                    <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="action" value="sign_return">
                    <input type="hidden" name="ticket_id" value="<?= (int)$ticketId ?>">
                    <?php if ((int)$ticket['has_discrepancy'] === 1): ?>
                        <label class="sg-ack"><input type="checkbox" name="ack_discrepancy" value="1" required>
                            <span>ฉันรับทราบข้อแตกต่างของอุปกรณ์ตามรายการข้างต้นแล้ว</span></label>
                    <?php endif; ?>
                    <?= signature_field($conn, $userId, '', 'ลายเซ็นผู้แจ้ง') ?>
                    <label class="sg-field">หมายเหตุ (ไม่บังคับ)<input class="sg-input" name="sign_note" maxlength="255"></label>
                    <div class="sg-actions"><button type="submit" class="sg-btn">เซ็นรับคืน</button></div>
                </form>
                <div class="tv-card-body" style="border-top:1px solid #EFEDE9">
                    <details class="sg-dispute">
                        <summary>ไม่ยอมรับ / มีข้อโต้แย้ง</summary>
                        <form method="post" style="display:grid;gap:10px;margin-top:10px" onsubmit="return confirm('ยืนยันไม่ยอมรับการส่งคืน? ใบงานจะส่งให้ครูผู้ดูแลพิจารณา');">
                            <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">
                            <input type="hidden" name="action" value="dispute">
                            <input type="hidden" name="ticket_id" value="<?= (int)$ticketId ?>">
                            <label class="sg-field">เหตุผล <span style="color:#B91C1C">*</span>
                                <textarea class="sg-input" name="dispute_reason" rows="3" minlength="5" maxlength="500" required placeholder="เช่น สายชาร์จไม่ได้คืนมา เครื่องยังเปิดไม่ติด"></textarea>
                            </label>
                            <div class="sg-actions"><button type="submit" class="sg-btn sg-btn--ghost">ส่งข้อโต้แย้ง</button></div>
                        </form>
                    </details>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($status === 'disputed'): ?>
            <div class="tv-alert">คุณแจ้งไม่ยอมรับใบงานนี้: <?= h($ticket['return_reason']) ?> · ครูผู้ดูแลกำลังพิจารณา</div>
        <?php elseif ($status === 'awaiting_admin_sign'): ?>
            <div class="tv-alert">คุณเซ็นรับคืนแล้ว รอครูผู้ดูแลรับรองเพื่อปิดงาน</div>
        <?php elseif ($status === 'completed'): ?>
            <div class="sg-actions"><a class="sg-btn" target="_blank" rel="noopener" href="certificate.php?id=<?= (int)$ticketId ?>">เปิดใบรับรองงาน</a></div>
        <?php endif; ?>

        <?php if (thanks_can_give($conn, $ticket, $userId)): ?>
            <?php /* ไม่บังคับ: ชวนขอบคุณทีมช่างหลังรับอุปกรณ์คืนแล้ว */ ?>
            <section class="tv-card">
                <div class="tv-card-body" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px">
                    <div>
                        <div style="font-weight:500">อยากขอบคุณทีมช่างไหม</div>
                        <div class="tv-muted">ไม่บังคับ · ให้กำลังใจช่างที่ซ่อมได้ด้วยการโอนผ่าน QR หรือบันทึกว่าให้ขนม/เครื่องดื่ม</div>
                    </div>
                    <a class="sg-btn" href="thanks.php?id=<?= (int)$ticketId ?>" style="background:#fff;color:#161616;border-color:#D4D0C9"><i class="fa-solid fa-heart" aria-hidden="true"></i> ขอบคุณทีมช่าง</a>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!in_array($status, ['awaiting_intake_sign', 'awaiting_requester_sign'], true)): ?>
            <section class="tv-card">
                <div class="tv-card-head"><h2 class="tv-card-title">ข้อมูลใบงาน</h2></div>
                <div class="tv-card-body"><?= tv_summary($ticket, '', false) ?></div>
            </section>
            <?php if ($items): ?>
                <section class="tv-card">
                    <div class="tv-card-head"><h2 class="tv-card-title">เช็คลิสต์อุปกรณ์</h2></div>
                    <div class="tv-card-body"><?= tv_checklist($items, $ticketId, '') ?></div>
                </section>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($signoffs): ?>
            <section class="tv-card">
                <div class="tv-card-head"><h2 class="tv-card-title">ลายเซ็น</h2></div>
                <div class="tv-card-body"><?= tv_signatures($signoffs, $ticketId, '', $ticket) ?></div>
            </section>
        <?php endif; ?>

        <section class="tv-card">
            <div class="tv-card-head"><h2 class="tv-card-title">ประวัติ</h2></div>
            <div class="tv-card-body"><?= tv_timeline($logs) ?></div>
        </section>
    </main>

    <?php $footerBase = ''; $footerVariant = 'light'; require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
