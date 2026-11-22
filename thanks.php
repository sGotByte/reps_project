<?php
declare(strict_types=1);

/**
 * REPS · thanks.php — ผู้แจ้งขอบคุณ Lab boy ที่ซ่อมใบงานของตัวเอง (ไม่บังคับ)
 *   - เลือกช่างในทีม → โอนเงินผ่าน QR พร้อมเพย์ของคนนั้น หรือบันทึกของที่ให้ (ขนม เครื่องดื่ม อื่น ๆ)
 *   - บันทึกแล้ว Lab boy กดยืนยันว่าได้รับ · ผู้แจ้งยกเลิกได้ตอนยังรอยืนยัน
 * เปิดได้เฉพาะผู้แจ้งของใบงาน (ตรวจฝั่งเซิร์ฟเวอร์ทุกครั้ง) ระบบไม่รับเงินและไม่ตรวจสลิปอัตโนมัติ
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/user_auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/site_config.php';
require_once __DIR__ . '/includes/ticket_service.php';
require_once __DIR__ . '/includes/ticket_view.php';
require_once __DIR__ . '/includes/thanks.php';
require_once __DIR__ . '/includes/user_menu.php';
require_once __DIR__ . '/includes/notifications.php';

/** @var mysqli $conn */
$ticketId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT) ?: 0;
$userId = (int)app_require_active_user($conn, 'login.php?next=' . rawurlencode('thanks.php?id=' . $ticketId), null);
$csrfToken = app_csrf_token();
$siteName = app_site_name($conn);

function h(mixed $v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function thanks_redirect(int $ticketId, string $msg, string $type = 'ok'): never
{
    $_SESSION['thanks_flash'] = ['msg' => $msg, 'type' => $type];
    header('Location: thanks.php?id=' . $ticketId, true, 303);
    exit;
}

$ticket = $ticketId > 0 ? tv_ticket($conn, $ticketId) : null;
if (!$ticket || (int)$ticket['requester_id'] !== $userId || !thanks_enabled($conn)) {
    http_response_code(404);
    exit('ไม่พบหน้านี้');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_csrf_is_valid($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('คำขอหมดอายุ กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    try {
        if ($action === 'give') {
            thanks_create(
                $conn,
                $ticketId,
                $userId,
                (int)($_POST['to_user_id'] ?? 0),
                is_string($_POST['kind'] ?? null) ? $_POST['kind'] : '',
                is_string($_POST['amount'] ?? null) ? $_POST['amount'] : '',
                is_string($_POST['item_note'] ?? null) ? $_POST['item_note'] : '',
                is_string($_POST['message'] ?? null) ? $_POST['message'] : '',
                $_FILES['slip'] ?? null
            );
            thanks_redirect($ticketId, 'บันทึกคำขอบคุณแล้ว ขอบคุณที่ให้กำลังใจทีมช่าง');
        }
        if ($action === 'cancel') {
            thanks_cancel($conn, (int)($_POST['thanks_id'] ?? 0), $userId);
            thanks_redirect($ticketId, 'ยกเลิกรายการแล้ว', 'warn');
        }
        throw new AppUserException('คำสั่งไม่ถูกต้อง');
    } catch (AppUserException $e) {
        thanks_redirect($ticketId, $e->getMessage(), 'err');
    } catch (Throwable $e) {
        error_log('thanks.php action "' . $action . '" failed: ' . $e->getMessage());
        thanks_redirect($ticketId, 'บันทึกไม่สำเร็จ กรุณาลองใหม่อีกครั้ง', 'err');
    }
}

$flash = $_SESSION['thanks_flash'] ?? null;
unset($_SESSION['thanks_flash']);
$team = thanks_team($conn, $ticketId);
$block = thanks_block_reason($conn, $ticket, $userId);
$cfg = thanks_settings($conn);
$given = db_all(
    $conn,
    'SELECT th.*, u.first_name, u.last_name FROM ticket_thanks th JOIN users u ON u.user_id = th.to_user_id WHERE th.ticket_id = ? AND th.from_user_id = ? ORDER BY th.id DESC',
    'ii',
    [$ticketId, $userId]
);
$activeGiven = count(array_filter($given, static fn($g) => $g['status'] !== 'cancelled'));
$homeUrl = role_home_url(current_role());
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ขอบคุณทีมช่าง · ใบงาน <?= h($ticket['ticket_no']) ?> | <?= h($siteName) ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/images/logo/reps-icon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <?= app_theme_css($conn) ?>
    <?= tv_assets() ?>
    <style>
        body { margin: 0; background: #FAFAF8; color: #161616; font-family: 'Kanit', system-ui, sans-serif; }
        .th-top { position: sticky; top: 0; z-index: 30; border-bottom: 1px solid #ECEAE6; background: rgba(255, 255, 255, .95); backdrop-filter: blur(6px); }
        .th-top-inner { display: flex; align-items: center; justify-content: space-between; gap: 12px; max-width: 72rem; min-height: 64px; margin: 0 auto; padding: 0 1.5rem; }
        .th-top-right { display: flex; align-items: center; gap: 8px; }
        .th-main { max-width: 44rem; margin: 0 auto; padding: 24px 1.5rem 48px; display: grid; gap: 16px; }
        .th-main > * { min-width: 0; }
        .th-back { color: #57544F; font-size: 14px; text-decoration: none; }
        .th-title { margin: 4px 0 0; font-size: 26px; font-weight: 500; }
        .th-sub { margin: 4px 0 0; color: #57544F; font-size: 14px; line-height: 1.6; }
        .th-flash { padding: 12px 14px; border-radius: 12px; font-size: 14px; }
        .th-flash--ok { border: 1px solid #BBE5CC; background: #F0FAF4; color: #14532D; }
        .th-flash--warn { border: 1px solid #F1D9BF; background: #FFFBF5; color: #7C2D12; }
        .th-flash--err { border: 1px solid #F5C2C0; background: #FEF2F2; color: #7F1D1D; }
        .th-form { display: grid; gap: 18px; }
        .th-label { margin: 0 0 8px; font-size: 14px; font-weight: 500; color: #161616; }
        .th-people, .th-kinds { display: flex; flex-wrap: wrap; gap: 8px; }
        .th-choice { position: relative; }
        .th-choice input { position: absolute; opacity: 0; pointer-events: none; }
        .th-choice span { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; padding: 0 14px; border: 1px solid #D4D0C9; border-radius: 12px; background: #fff; font-size: 14px; cursor: pointer; }
        .th-choice input:checked + span { border-color: var(--theme-primary, #161616); box-shadow: inset 0 0 0 1px var(--theme-primary, #161616); font-weight: 500; }
        .th-choice input:focus-visible + span { outline: 2px solid #161616; outline-offset: 2px; }
        .th-choice small { color: #6B6862; font-size: 12px; font-weight: 400; }
        .th-qr { display: grid; justify-items: center; gap: 8px; padding: 16px; border: 1px solid #ECEAE6; border-radius: 14px; background: #fff; text-align: center; }
        .th-qr img { width: 220px; max-width: 100%; height: auto; border-radius: 10px; }
        .th-qr p { margin: 0; color: #57544F; font-size: 13px; }
        .th-field { display: block; font-size: 14px; color: #3F3D39; }
        .th-input { width: 100%; min-height: 44px; margin-top: 4px; padding: 8px 12px; border: 1px solid #D4D0C9; border-radius: 10px; background: #fff; font: inherit; font-size: 15px; box-sizing: border-box; }
        .th-input:focus { outline: none; border-color: #161616; box-shadow: 0 0 0 3px rgba(22, 22, 22, .08); }
        .th-grid { display: grid; gap: 12px; }
        @media (min-width: 560px) { .th-grid { grid-template-columns: 1fr 1fr; } }
        .th-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 46px; padding: 0 20px; border: 1px solid var(--theme-primary, #161616); border-radius: 12px; background: var(--theme-primary, #161616); color: #fff; font: inherit; font-size: 15px; font-weight: 500; cursor: pointer; }
        .th-btn--ghost { min-height: 36px; padding: 0 12px; border-color: #D4D0C9; background: #fff; color: #57544F; font-size: 13px; }
        .th-note { margin: 0; color: #6B6862; font-size: 12.5px; line-height: 1.6; }
        .th-list { display: grid; gap: 10px; margin: 0; padding: 0; list-style: none; }
        .th-item { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 12px; padding: 12px 14px; border: 1px solid #ECEAE6; border-radius: 12px; background: #fff; font-size: 14px; }
        .th-item small { display: block; color: #6B6862; font-size: 12.5px; }
        [hidden] { display: none !important; }
        @media (max-width: 640px) { .th-title { font-size: 22px; } .th-btn { width: 100%; } }
    </style>
    <link rel="stylesheet" href="assets/css/base.css?v=18">
</head>
<body>
    <header class="th-top">
        <div class="th-top-inner">
            <a href="<?= h($homeUrl) ?>"><img src="assets/images/logo/reps-logo.svg" alt="REPS" style="height:24px"></a>
            <div class="th-top-right">
                <?= notif_bell($conn, '') ?>
                <?= user_menu_render($conn, '') ?>
            </div>
        </div>
    </header>

    <main class="th-main">
        <div>
            <a class="th-back" href="history.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> ประวัติการแจ้งซ่อม</a>
            <h1 class="th-title">ขอบคุณทีมช่าง</h1>
            <p class="th-sub">ใบงาน <?= h($ticket['ticket_no']) ?> · <?= h($ticket['location_note']) ?><br>ถ้าประทับใจการบริการ ให้กำลังใจช่างที่ซ่อมได้ <b>ไม่บังคับ</b> และไม่มีผลกับคะแนนของช่าง</p>
        </div>

        <?php if ($flash): ?>
            <div role="status" class="th-flash th-flash--<?= h(in_array($flash['type'] ?? '', ['err', 'warn'], true) ? $flash['type'] : 'ok') ?>"><?= h($flash['msg'] ?? '') ?></div>
        <?php endif; ?>

        <?php if ($block !== null): ?>
            <div class="tv-alert"><?= h($block) ?></div>
        <?php elseif (!$team): ?>
            <div class="tv-alert">ใบงานนี้ไม่มีช่างที่ยังใช้งานอยู่ให้ขอบคุณ</div>
        <?php elseif ($activeGiven >= THANKS_PER_TICKET): ?>
            <div class="tv-alert">บันทึกคำขอบคุณของใบงานนี้ครบ <?= THANKS_PER_TICKET ?> รายการแล้ว</div>
        <?php else: ?>
            <section class="tv-card">
                <div class="tv-card-body">
                    <form method="post" enctype="multipart/form-data" class="th-form" id="thanksForm">
                        <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">
                        <input type="hidden" name="action" value="give">
                        <input type="hidden" name="ticket_id" value="<?= (int)$ticketId ?>">

                        <fieldset style="border:0;margin:0;padding:0">
                            <legend class="th-label">ให้ใคร</legend>
                            <div class="th-people">
                                <?php foreach ($team as $i => $m): ?>
                                    <label class="th-choice">
                                        <input type="radio" name="to_user_id" value="<?= (int)$m['user_id'] ?>" <?= $i === 0 ? 'checked' : '' ?> data-has-qr="<?= !empty($m['promptpay_qr_path']) ? '1' : '0' ?>" data-name="<?= h($m['first_name']) ?>">
                                        <span><?= h($m['first_name'] . ' ' . $m['last_name']) ?> <small><?= (int)$m['is_lead'] === 1 ? 'ช่างหลัก' : 'ผู้ช่วย' ?></small></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>

                        <fieldset style="border:0;margin:0;padding:0">
                            <legend class="th-label">ให้อะไร</legend>
                            <div class="th-kinds">
                                <?php foreach (THANKS_KINDS as $k => $label): ?>
                                    <label class="th-choice"><input type="radio" name="kind" value="<?= h($k) ?>" <?= $k === 'money' ? 'checked' : '' ?>><span><?= h($label) ?></span></label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>

                        <?php foreach ($team as $m): ?>
                            <div class="th-qr" data-qr-for="<?= (int)$m['user_id'] ?>" hidden>
                                <?php if (!empty($m['promptpay_qr_path'])): ?>
                                    <img src="file.php?k=tipqr&amp;u=<?= (int)$m['user_id'] ?>&amp;t=<?= (int)$ticketId ?>" alt="QR พร้อมเพย์ของ <?= h($m['first_name']) ?>" loading="lazy">
                                    <p>สแกนด้วยแอปธนาคารเพื่อโอนให้ <b><?= h($m['first_name'] . ' ' . $m['last_name']) ?></b> โดยตรง แล้วกรอกจำนวนเงินด้านล่าง</p>
                                <?php else: ?>
                                    <p><?= h($m['first_name']) ?> ยังไม่ได้ตั้ง QR พร้อมเพย์ ให้เป็นเงินสดแล้วบันทึกจำนวนเงินไว้ได้ หรือเลือกให้เป็นของแทน</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <div class="th-grid">
                            <label class="th-field" data-item-field hidden>ให้อะไร *
                                <input class="th-input" name="item_note" maxlength="200" placeholder="เช่น กาแฟเย็น 2 แก้ว">
                            </label>
                            <label class="th-field"><span data-amount-label>จำนวนเงิน (บาท) *</span>
                                <input class="th-input" name="amount" inputmode="decimal" pattern="[0-9]+([.][0-9]{1,2})?" placeholder="เช่น 50" autocomplete="off">
                            </label>
                        </div>
                        <label class="th-field">ข้อความถึงช่าง (ไม่บังคับ)
                            <input class="th-input" name="message" maxlength="300" placeholder="เช่น ขอบคุณที่มาซ่อมให้เร็วมาก">
                        </label>
                        <label class="th-field" data-slip-field>สลิปการโอน (ไม่บังคับ)
                            <input type="file" name="slip" accept="image/jpeg,image/png,image/webp" data-photo-input data-max-mb="3">
                        </label>
                        <p class="th-note">ระบบไม่ได้รับเงินและไม่ตรวจสลิป เป็นการบันทึกไว้เป็นหลักฐาน ช่างจะกดยืนยันเมื่อได้รับ · สูงสุด <?= number_format($cfg['max']) ?> บาทต่อครั้ง</p>
                        <div><button type="submit" class="th-btn"><i class="fa-solid fa-heart" aria-hidden="true"></i> บันทึกคำขอบคุณ</button></div>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($given): ?>
            <section class="tv-card">
                <div class="tv-card-head"><h2 class="tv-card-title">ที่คุณให้ในใบงานนี้</h2></div>
                <div class="tv-card-body">
                    <ul class="th-list">
                        <?php foreach ($given as $g): ?>
                            <li class="th-item">
                                <div>
                                    <?= h(thanks_describe($g)) ?> · ถึง <?= h($g['first_name'] . ' ' . $g['last_name']) ?>
                                    <small><?= h(tv_dt($g['created_at'])) ?><?= $g['message'] !== '' ? ' · “' . h($g['message']) . '”' : '' ?><?= $g['staff_note'] !== '' ? ' · ช่าง: ' . h($g['staff_note']) : '' ?></small>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <span class="tv-tag <?= $g['status'] === 'confirmed' ? 'tv-tag--ok' : ($g['status'] === 'pending' ? '' : 'tv-tag--warn') ?>"><?= h(THANKS_STATUS[$g['status']] ?? $g['status']) ?></span>
                                    <?php if ($g['status'] === 'pending'): ?>
                                        <form method="post" data-confirm="ยกเลิกรายการนี้?">
                                            <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="ticket_id" value="<?= (int)$ticketId ?>">
                                            <input type="hidden" name="thanks_id" value="<?= (int)$g['id'] ?>">
                                            <button class="th-btn th-btn--ghost">ยกเลิก</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
        <?php endif; ?>
    </main>
<?php $footerBase = ''; $footerVariant = 'light'; require __DIR__ . '/includes/site_footer.php'; ?>
<script>
(function () {
    var form = document.getElementById('thanksForm');
    if (form) {
        var sync = function () {
            var kind = (form.querySelector('input[name=kind]:checked') || {}).value;
            var person = form.querySelector('input[name=to_user_id]:checked');
            var money = kind === 'money';
            form.querySelectorAll('[data-qr-for]').forEach(function (el) { el.hidden = !money || !person || el.dataset.qrFor !== person.value; });
            form.querySelector('[data-item-field]').hidden = money;
            form.querySelector('[name=item_note]').required = !money;
            form.querySelector('[name=amount]').required = money;
            form.querySelector('[data-amount-label]').textContent = money ? 'จำนวนเงิน (บาท) *' : 'มูลค่าโดยประมาณ (บาท ไม่บังคับ)';
            form.querySelector('[data-slip-field]').hidden = !money;
        };
        form.addEventListener('change', sync);
        sync();
    }
    document.addEventListener('submit', function (e) {
        var f = e.target.closest('form[data-confirm]');
        if (f && !window.confirm(f.dataset.confirm)) e.preventDefault();
    });
})();
</script>
</body>
</html>
