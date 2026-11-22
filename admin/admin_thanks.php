<?php
declare(strict_types=1);

/**
 * REPS · admin/admin_thanks.php — คำขอบคุณ Lab boy ทั้งหมด (ครูเห็นทั้งหมด)
 *   ยอดรวมต่อ Lab boy ตามภาคเรียน + รายการทั้งหมด กรองตามคน/สถานะ · ไม่เกี่ยวกับคะแนน ไม่จัดอันดับ
 */

$page_title = 'คำขอบคุณ Lab boy';
$current_page = 'thanks';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/thanks.php';
require_once __DIR__ . '/../includes/ticket_view.php';

function h($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

$terms = term_all($conn);
$term = term_from_request($conn);
$fStaff = filter_input(INPUT_GET, 'staff', FILTER_VALIDATE_INT) ?: 0;
$fStatus = isset(THANKS_STATUS[$_GET['status'] ?? '']) ? (string)$_GET['status'] : '';
$cfg = thanks_settings($conn);

$summary = thanks_summary($conn, null, $term);
// รายชื่อ: ทีมของภาคเรียนที่เลือก + คนที่ได้รับคำขอบคุณในภาคนั้น
$ids = array_keys($summary);
if ($term) $ids = array_values(array_unique(array_merge(term_roster_ids($conn, (int)$term['id']), $ids)));
$people = $ids ? db_all($conn, 'SELECT user_id, first_name, last_name, id_number FROM users WHERE user_id IN (' . implode(',', array_map('intval', $ids)) . ') ORDER BY first_name') : [];

$where = "th.status <> 'cancelled'" . term_date_sql($term, 'th.created_at');
$types = '';
$params = [];
if ($fStaff > 0) { $where .= ' AND th.to_user_id = ?'; $types .= 'i'; $params[] = $fStaff; }
if ($fStatus !== '') { $where .= ' AND th.status = ?'; $types .= 's'; $params[] = $fStatus; }
$rows = db_all(
    $conn,
    "SELECT th.*, t.ticket_no, f.first_name AS from_first, f.last_name AS from_last, s.first_name AS to_first, s.last_name AS to_last
       FROM ticket_thanks th
       JOIN tickets t ON t.id = th.ticket_id
       JOIN users f ON f.user_id = th.from_user_id
       JOIN users s ON s.user_id = th.to_user_id
      WHERE {$where}
      ORDER BY th.id DESC LIMIT 300",
    $types,
    $params
);
$totalMoney = array_sum(array_map(static fn($s) => $s['money'], $summary));

require_once __DIR__ . '/admin_header.php';
?>

<div class="admin-page-head">
    <nav class="admin-crumb">
        <a href="admin_dashboard.php">แดชบอร์ด</a>
        <i class="fa-solid fa-chevron-right"></i>
        <span>คำขอบคุณ Lab boy</span>
    </nav>
    <h1 class="admin-page-title">คำขอบคุณ Lab boy</h1>
    <p class="admin-page-sub">น้ำใจที่ผู้แจ้งให้ Lab boy (ไม่บังคับ) ผู้แจ้งเป็นคนบันทึกและ Lab boy ยืนยันว่าได้รับ · ไม่เกี่ยวกับคะแนน · ระบบไม่รับเงินและไม่ตรวจสลิป</p>
</div>

<?php if (!$cfg['enabled']): ?>
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">ระบบขอบคุณ Lab boy ปิดอยู่ เปิดได้ที่ <a class="underline" href="admin_settings.php">ตั้งค่าระบบ</a></div>
<?php endif; ?>

<form method="get" class="admin-tray">
    <div class="admin-card admin-toolbar">
        <?php if ($terms): ?>
            <label>ภาคเรียน
                <select name="term" onchange="this.form.submit()" class="block">
                    <?php foreach ($terms as $t): ?><option value="<?= (int)$t['id'] ?>" <?= $term && (int)$term['id'] === (int)$t['id'] ? 'selected' : '' ?>><?= h(term_title($t)) ?></option><?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <label>Lab boy
            <select name="staff" onchange="this.form.submit()" class="block">
                <option value="0">ทุกคน</option>
                <?php foreach ($people as $p): ?><option value="<?= (int)$p['user_id'] ?>" <?= $fStaff === (int)$p['user_id'] ? 'selected' : '' ?>><?= h($p['first_name'] . ' ' . $p['last_name']) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>สถานะ
            <select name="status" onchange="this.form.submit()" class="block">
                <option value="">ทั้งหมด</option>
                <?php foreach (THANKS_STATUS as $k => $l): if ($k === 'cancelled') continue; ?><option value="<?= h($k) ?>" <?= $fStatus === $k ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?>
            </select>
        </label>
    </div>
</form>

<div class="admin-tray">
    <div class="admin-tray-label"><span>สรุปต่อ Lab boy<?= $term ? ' · ' . h(term_title($term)) : '' ?></span><span>เงินยืนยันแล้วรวม <?= number_format($totalMoney) ?> บาท</span></div>
    <section class="admin-card admin-table-wrap overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr><th class="text-left">Lab boy</th><th class="text-right">เงินที่ได้รับ (ยืนยัน)</th><th class="text-right">ของ (ครั้ง)</th><th class="text-right">รอยืนยัน</th></tr></thead>
            <tbody>
            <?php if (!$people): ?><tr><td colspan="4" class="py-4 text-center text-gray-500">ยังไม่มีข้อมูล</td></tr><?php endif; ?>
            <?php foreach ($people as $p): $s = $summary[(int)$p['user_id']] ?? ['money' => 0, 'gifts' => 0, 'pending' => 0]; ?>
                <tr>
                    <td class="py-3"><?= h($p['first_name'] . ' ' . $p['last_name']) ?> <span class="text-gray-500">· <?= h($p['id_number']) ?></span></td>
                    <td class="py-3 text-right font-medium"><?= number_format($s['money']) ?> บาท</td>
                    <td class="py-3 text-right"><?= (int)$s['gifts'] ?></td>
                    <td class="py-3 text-right"><?= (int)$s['pending'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>

<div class="admin-tray">
    <div class="admin-tray-label"><span>รายการ (<?= count($rows) ?>)</span></div>
    <section class="admin-card admin-table-wrap overflow-x-auto">
        <table class="w-full text-sm" data-pager>
            <thead><tr><th class="text-left">วันที่</th><th class="text-left">สิ่งที่ให้</th><th class="text-left">จาก → ถึง</th><th class="text-left">ใบงาน</th><th class="text-left">สถานะ</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr data-pager-ignore><td colspan="5" class="py-4 text-center text-gray-500">ยังไม่มีคำขอบคุณ</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="py-3 whitespace-nowrap"><?= h(tv_dt($r['created_at'])) ?></td>
                    <td class="py-3"><?= h(thanks_describe($r)) ?>
                        <?php if ($r['message'] !== ''): ?><div class="text-xs text-gray-500">“<?= h($r['message']) ?>”</div><?php endif; ?>
                        <?php if ($r['slip_path']): ?><a class="text-xs underline" target="_blank" rel="noopener" href="../file.php?k=slip&amp;i=<?= (int)$r['id'] ?>">ดูสลิป</a><?php endif; ?>
                    </td>
                    <td class="py-3"><?= h($r['from_first'] . ' ' . $r['from_last']) ?> → <?= h($r['to_first'] . ' ' . $r['to_last']) ?></td>
                    <td class="py-3"><a class="underline" href="../staff/job.php?id=<?= (int)$r['ticket_id'] ?>"><?= h($r['ticket_no']) ?></a></td>
                    <td class="py-3"><span class="admin-chip <?= $r['status'] === 'confirmed' ? 'is-ok' : ($r['status'] === 'pending' ? 'is-warning' : 'is-fail') ?>"><?= h(THANKS_STATUS[$r['status']] ?? $r['status']) ?></span>
                        <?php if ($r['staff_note'] !== ''): ?><div class="text-xs text-gray-500"><?= h($r['staff_note']) ?></div><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>

      </div>
<?php $footerBase = '../'; $footerVariant = 'app'; require __DIR__ . '/../includes/site_footer.php'; ?>
   </div>
</body>
</html>
