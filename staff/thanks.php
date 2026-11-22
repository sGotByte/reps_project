<?php
declare(strict_types=1);

/**
 * REPS · staff/thanks.php — คำขอบคุณที่ Lab boy ได้รับจากผู้แจ้ง
 *   - รายการรอยืนยัน: กด "ได้รับแล้ว" / "ไม่ได้รับ" พร้อมหมายเหตุ (เฉพาะของตัวเอง)
 *   - ประวัติและยอดรวมของภาคเรียนปัจจุบัน · ไม่เกี่ยวกับคะแนน ไม่จัดอันดับ
 * ผู้ดูแลถูกส่งไปหน้ารวม admin/admin_thanks.php
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/user_auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/site_config.php';
require_once __DIR__ . '/../includes/thanks.php';
require_once __DIR__ . '/../includes/ticket_view.php';

$userId = require_role($conn, ['staff', 'admin'], '../login.php?next=staff');
if (current_role() === 'admin') {
    header('Location: ../admin/admin_thanks.php', true, 303);
    exit;
}
$csrfToken = app_csrf_token();
$siteName = app_site_name($conn);

function h(mixed $v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_csrf_is_valid($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('คำขอหมดอายุ กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    try {
        if ($action !== 'received' && $action !== 'not_received') {
            throw new AppUserException('คำสั่งไม่ถูกต้อง');
        }
        thanks_respond($conn, (int)($_POST['thanks_id'] ?? 0), $userId, $action === 'received', is_string($_POST['staff_note'] ?? null) ? $_POST['staff_note'] : '');
        $_SESSION['st_flash'] = [$action === 'received' ? 'ยืนยันว่าได้รับแล้ว' : 'บันทึกว่าไม่ได้รับแล้ว', 'ok'];
    } catch (AppUserException $e) {
        $_SESSION['st_flash'] = [$e->getMessage(), 'err'];
    } catch (Throwable $e) {
        error_log('staff/thanks.php: ' . $e->getMessage());
        $_SESSION['st_flash'] = ['บันทึกไม่สำเร็จ กรุณาลองใหม่', 'err'];
    }
    header('Location: thanks.php', true, 303);
    exit;
}

$flash = $_SESSION['st_flash'] ?? null;
unset($_SESSION['st_flash']);
$term = term_current($conn);
$summary = thanks_summary($conn, $userId, $term)[$userId] ?? ['money' => 0, 'gifts' => 0, 'pending' => 0, 'count' => 0];
$rows = db_all(
    $conn,
    "SELECT th.*, t.ticket_no, t.location_note, u.first_name, u.last_name
       FROM ticket_thanks th
       JOIN tickets t ON t.id = th.ticket_id
       JOIN users u ON u.user_id = th.from_user_id
      WHERE th.to_user_id = ? AND th.status <> 'cancelled'" . term_date_sql($term, 'th.created_at') . "
      ORDER BY th.status = 'pending' DESC, th.id DESC
      LIMIT 200",
    'i',
    [$userId]
);
$pending = array_values(array_filter($rows, static fn($r) => $r['status'] === 'pending'));
$history = array_values(array_filter($rows, static fn($r) => $r['status'] !== 'pending'));
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>คำขอบคุณ | <?= h($siteName) ?></title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo/reps-icon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Kanit', 'sans-serif'] } } } };</script>
    <?= app_theme_css($conn) ?>
    <?= tv_assets() ?>
    <style>
        body { background: #FAFAF8; color: #161616; font-family: 'Kanit', sans-serif; }
        .st-sum { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
        .st-stat { padding: 14px 16px; border: 1px solid #ECEAE6; border-radius: 14px; background: #fff; }
        .st-stat b { display: block; margin-top: 2px; font-size: 22px; font-weight: 600; font-variant-numeric: tabular-nums; }
        .st-stat span { color: #6B6862; font-size: 13px; }
        .st-item { display: grid; gap: 10px; padding: 14px 16px; border: 1px solid #ECEAE6; border-radius: 14px; background: #fff; }
        .st-item-head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 6px 12px; }
        .st-what { font-size: 15px; font-weight: 500; }
        .st-meta { color: #6B6862; font-size: 13px; }
        .st-msg { padding: 8px 12px; border-radius: 10px; background: #FAFAF8; color: #3F3D39; font-size: 14px; }
        .st-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .st-actions input { flex: 1 1 200px; min-height: 40px; padding: 0 10px; border: 1px solid #D4D0C9; border-radius: 10px; font: inherit; font-size: 14px; }
        .st-btn { display: inline-flex; align-items: center; gap: 6px; min-height: 40px; padding: 0 14px; border: 1px solid var(--theme-primary, #161616); border-radius: 10px; background: var(--theme-primary, #161616); color: #fff; font: inherit; font-size: 14px; cursor: pointer; }
        .st-btn--ghost { border-color: #D4D0C9; background: #fff; color: #57544F; }
        .st-flash { padding: 12px 14px; border-radius: 12px; font-size: 14px; }
        .st-flash--ok { border: 1px solid #BBE5CC; background: #F0FAF4; color: #14532D; }
        .st-flash--err { border: 1px solid #F5C2C0; background: #FEF2F2; color: #7F1D1D; }
    </style>
    <link rel="stylesheet" href="../assets/css/base.css?v=18">
</head>
<body class="min-h-screen flex flex-col antialiased">
    <?php $staffNavActive = 'thanks'; require __DIR__ . '/../includes/staff_nav.php'; ?>
    <main class="flex-1 max-w-4xl w-full mx-auto px-4 sm:px-7 py-7 space-y-5">
        <div>
            <h1 class="text-2xl font-medium">คำขอบคุณ</h1>
            <p class="text-sm text-neutral-600 mt-1">น้ำใจจากผู้แจ้งที่ประทับใจงานของคุณ<?= $term ? ' · ' . h(term_title($term)) : '' ?> · ไม่เกี่ยวกับคะแนน กดยืนยันเมื่อได้รับจริง</p>
        </div>

        <?php if ($flash): ?>
            <div role="status" class="st-flash st-flash--<?= $flash[1] === 'err' ? 'err' : 'ok' ?>"><?= h($flash[0]) ?></div>
        <?php endif; ?>

        <div class="st-sum">
            <div class="st-stat"><span>เงินที่ได้รับ (ยืนยันแล้ว)</span><b><?= number_format($summary['money'], 0) ?> บาท</b></div>
            <div class="st-stat"><span>ขนม / เครื่องดื่ม / ของ</span><b><?= (int)$summary['gifts'] ?> ครั้ง</b></div>
            <div class="st-stat"><span>รอคุณยืนยัน</span><b><?= count($pending) ?> รายการ</b></div>
        </div>

        <section class="space-y-3" aria-labelledby="pendingTitle">
            <h2 id="pendingTitle" class="text-base font-semibold">รอยืนยัน</h2>
            <?php if (!$pending): ?>
                <p class="text-sm text-neutral-500">ไม่มีรายการรอยืนยัน</p>
            <?php endif; ?>
            <?php foreach ($pending as $r): ?>
                <article class="st-item">
                    <div class="st-item-head">
                        <span class="st-what"><?= h(thanks_describe($r)) ?></span>
                        <span class="st-meta"><?= h(tv_dt($r['created_at'])) ?></span>
                    </div>
                    <div class="st-meta">จาก <?= h($r['first_name'] . ' ' . $r['last_name']) ?> · ใบงาน <a class="underline" href="job.php?id=<?= (int)$r['ticket_id'] ?>"><?= h($r['ticket_no']) ?></a> · <?= h($r['location_note']) ?>
                        <?php if ($r['slip_path']): ?> · <a class="underline" target="_blank" rel="noopener" href="../file.php?k=slip&amp;i=<?= (int)$r['id'] ?>">ดูสลิป</a><?php endif; ?>
                    </div>
                    <?php if ($r['message'] !== ''): ?><div class="st-msg">“<?= h($r['message']) ?>”</div><?php endif; ?>
                    <form method="post" class="st-actions">
                        <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">
                        <input type="hidden" name="thanks_id" value="<?= (int)$r['id'] ?>">
                        <input name="staff_note" maxlength="200" placeholder="หมายเหตุ (ไม่บังคับ)" aria-label="หมายเหตุ">
                        <button class="st-btn" name="action" value="received"><i class="fa-solid fa-check" aria-hidden="true"></i> ได้รับแล้ว</button>
                        <button class="st-btn st-btn--ghost" name="action" value="not_received">ไม่ได้รับ</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="space-y-3" aria-labelledby="historyTitle">
            <h2 id="historyTitle" class="text-base font-semibold">ประวัติ</h2>
            <?php if (!$history): ?>
                <p class="text-sm text-neutral-500">ยังไม่มีประวัติในภาคเรียนนี้</p>
            <?php endif; ?>
            <?php foreach ($history as $r): ?>
                <article class="st-item">
                    <div class="st-item-head">
                        <span class="st-what"><?= h(thanks_describe($r)) ?></span>
                        <span class="tv-tag <?= $r['status'] === 'confirmed' ? 'tv-tag--ok' : 'tv-tag--warn' ?>"><?= h(THANKS_STATUS[$r['status']] ?? $r['status']) ?></span>
                    </div>
                    <div class="st-meta">จาก <?= h($r['first_name'] . ' ' . $r['last_name']) ?> · ใบงาน <?= h($r['ticket_no']) ?> · <?= h(tv_dt($r['created_at'])) ?><?= $r['staff_note'] !== '' ? ' · หมายเหตุ: ' . h($r['staff_note']) : '' ?></div>
                    <?php if ($r['message'] !== ''): ?><div class="st-msg">“<?= h($r['message']) ?>”</div><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
<?php $footerBase = '../'; $footerVariant = 'light'; require __DIR__ . '/../includes/site_footer.php'; ?>
</body>
</html>
