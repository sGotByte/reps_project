<?php
declare(strict_types=1);

/**
 * REPS · admin/admin_scores.php — คะแนนสตาฟ (Lab boy) ต่อภาคเรียน
 *   กติกา: เริ่มภาคเรียนที่ 100 (เต็ม) · หักเมื่อทำผิดข้อกำหนด · ครูผู้ควบคุมบวกคืนได้ไม่เกิน 100
 *   - ครูหัก/ให้คะแนน: เลือกจากรายการตั้งต้น (score_rules) หรือกำหนดคะแนนและเหตุผลเอง
 *   - ไม่ลบประวัติ: ยกเลิกรายการ = บันทึกรายการกลับค่า
 *   - หักอัตโนมัติเมื่อไม่กดรับคิวทันเวลา (ตั้งค่าที่หน้า ตั้งค่าระบบ)
 */

$page_title = 'คะแนนสตาฟ';
$current_page = 'scores';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/staff_score.php';
require_once __DIR__ . '/../includes/ticket_view.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

function h($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

$current = term_current($conn);
$term = term_from_request($conn);
$isCurrentTerm = $term && $current && (int)$term['id'] === (int)$current['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['admin_csrf'], is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '')) {
        http_response_code(403);
        exit('คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $flash = ['message' => 'บันทึกแล้ว', 'type' => 'success'];
    try {
        if ($action === 'adjust') {
            $staffId = (int)($_POST['staff_id'] ?? 0);
            $ruleId = (int)($_POST['rule_id'] ?? 0);
            $ticketId = null;
            $ticketNo = trim(is_string($_POST['ticket_no'] ?? null) ? $_POST['ticket_no'] : '');
            if ($ticketNo !== '') {
                $t = db_one($conn, 'SELECT id FROM tickets WHERE ticket_no = ?', 's', [$ticketNo]);
                if (!$t) throw new AppUserException('ไม่พบใบงานเลขที่ ' . $ticketNo);
                $ticketId = (int)$t['id'];
            }
            $note = trim(is_string($_POST['note'] ?? null) ? $_POST['note'] : '');
            if ($ruleId > 0) {
                $rule = db_one($conn, 'SELECT * FROM score_rules WHERE id = ? AND is_active = 1', 'i', [$ruleId]);
                if (!$rule) throw new AppUserException('ไม่พบรายการที่เลือก');
                $points = (int)$rule['points'];
                $reason = $rule['label'] . ($note !== '' ? ' · ' . $note : '');
            } else {
                $points = filter_var($_POST['points'] ?? '', FILTER_VALIDATE_INT);
                if ($points === false) throw new AppUserException('กรุณาเลือกรายการ หรือระบุคะแนนเป็นตัวเลข (ติดลบ = หัก)');
                $reason = $note;
            }
            $saved = score_adjust($conn, $staffId, (int)$points, $reason, (int)$adminId, $ticketId);
            $flash['message'] = ($saved < 0 ? 'หักคะแนน ' . abs($saved) : 'ให้คะแนน +' . $saved) . ' คะแนนแล้ว' . ($saved > 0 && $saved < (int)$points ? ' (ตัดให้ไม่เกิน 100)' : '');
        } elseif ($action === 'reverse') {
            score_reverse($conn, (int)($_POST['log_id'] ?? 0), (int)$adminId);
            $flash['message'] = 'ยกเลิกรายการแล้ว (บันทึกรายการกลับค่าไว้ในประวัติ)';
        } elseif ($action === 'save_rule' || $action === 'add_rule') {
            $label = mb_substr(trim(is_string($_POST['label'] ?? null) ? $_POST['label'] : ''), 0, 150);
            $points = filter_var($_POST['points'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => -100, 'max_range' => 100]]);
            if ($label === '' || $points === false || $points === 0) throw new AppUserException('รายการต้องมีชื่อ และคะแนน -100 ถึง 100 (ไม่เป็น 0)');
            if ($action === 'add_rule') {
                db_exec($conn, 'INSERT INTO score_rules (label, points, sort_order) VALUES (?, ?, 999)', 'si', [$label, $points]);
                $flash['message'] = 'เพิ่มรายการแล้ว';
            } else {
                db_exec($conn, 'UPDATE score_rules SET label = ?, points = ?, is_active = ? WHERE id = ?', 'siii', [$label, $points, ($_POST['is_active'] ?? '') === '1' ? 1 : 0, (int)($_POST['rule_id'] ?? 0)]);
                $flash['message'] = 'บันทึกรายการแล้ว';
            }
        } else {
            throw new AppUserException('คำสั่งไม่ถูกต้อง');
        }
    } catch (AppUserException $e) {
        $flash = ['message' => $e->getMessage(), 'type' => 'error'];
    } catch (Throwable $e) {
        error_log('admin_scores ' . $action . ': ' . $e->getMessage());
        $flash = ['message' => 'บันทึกไม่สำเร็จ กรุณาลองใหม่', 'type' => 'error'];
    }
    $_SESSION['admin_flash'] = $flash;
    header('Location: admin_scores.php' . ($term ? '?term=' . (int)$term['id'] : ''), true, 303);
    exit;
}

$message = is_array($_SESSION['admin_flash'] ?? null) ? (string)($_SESSION['admin_flash']['message'] ?? '') : '';
$messageType = is_array($_SESSION['admin_flash'] ?? null) ? (string)($_SESSION['admin_flash']['type'] ?? 'success') : 'success';
unset($_SESSION['admin_flash']);

$terms = term_all($conn);
$rules = score_rules($conn, false);
$activeRules = array_values(array_filter($rules, static fn($r) => (int)$r['is_active'] === 1));
$roster = $term
    ? db_all($conn, 'SELECT u.user_id, u.first_name, u.last_name, u.id_number, ' . term_score_sql($term) . ' AS score,
                            (SELECT COUNT(*) FROM score_logs sl WHERE sl.user_id = u.user_id AND sl.points_change < 0' . term_date_sql($term, 'sl.created_at') . ') AS minus_count
                       FROM term_staff ts JOIN users u ON u.user_id = ts.user_id WHERE ts.term_id = ? ORDER BY ts.queue_order', 'i', [(int)$term['id']])
    : db_all($conn, "SELECT u.user_id, u.first_name, u.last_name, u.id_number, u.score, 0 AS minus_count FROM users u WHERE u.role = 'staff' AND u.status = 'active' ORDER BY u.first_name");
$rosterIds = array_map(static fn($r) => (int)$r['user_id'], $roster);
$logs = $rosterIds ? db_all(
    $conn,
    'SELECT sl.*, u.first_name, u.last_name, a.first_name AS by_first, t.ticket_no
       FROM score_logs sl JOIN users u ON u.user_id = sl.user_id
       LEFT JOIN users a ON a.user_id = sl.action_by
       LEFT JOIN tickets t ON t.id = sl.ticket_id
      WHERE sl.points_change <> 0 AND sl.user_id IN (' . implode(',', $rosterIds) . ')' . term_date_sql($term, 'sl.created_at') . '
      ORDER BY sl.id DESC LIMIT 300'
) : [];
$reversed = [];
foreach ($logs as $l) {
    if (preg_match('/^ยกเลิกรายการ #(\d+) /u', (string)$l['reason'], $m)) $reversed[(int)$m[1]] = true;
}
$csrf = '<input type="hidden" name="csrf" value="' . h($_SESSION['admin_csrf']) . '">';
$admin_extra_head = '<style>.sc-page [hidden]{display:none!important}.sc-nowrap{white-space:nowrap}.sc-rule{display:grid;grid-template-columns:84px 1fr auto;gap:6px 8px;align-items:center}.sc-rule .sc-rule-label{grid-column:1 / -1}</style>';

require_once __DIR__ . '/admin_header.php';
?>

<div class="admin-page-head">
    <nav class="admin-crumb">
        <a href="admin_dashboard.php">แดชบอร์ด</a>
        <i class="fa-solid fa-chevron-right"></i>
        <span>คะแนนสตาฟ</span>
    </nav>
    <h1 class="admin-page-title">คะแนนสตาฟ</h1>
    <p class="admin-page-sub">ทุกคนเริ่มภาคเรียนที่ 100 คะแนน (เต็ม) หักเมื่อทำผิดข้อกำหนด และครูผู้ควบคุมบวกคืนได้ไม่เกิน 100 · ไม่กดรับคิวทันเวลาระบบหักให้อัตโนมัติ · ประวัติไม่ถูกลบ ยกเลิกได้ด้วยรายการกลับค่า</p>
</div>

<?php if ($message): ?>
    <div class="mb-4 rounded-lg border p-3 text-sm <?= $messageType === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' ?>" role="status"><?= h($message) ?></div>
<?php endif; ?>

<?php if ($terms): ?>
<form method="get" class="mb-4 flex flex-wrap items-center gap-3 text-sm">
    <label class="text-gray-600">ภาคเรียน
        <select name="term" onchange="this.form.submit()" class="ml-2">
            <?php foreach ($terms as $t): ?><option value="<?= (int)$t['id'] ?>" <?= $term && (int)$term['id'] === (int)$t['id'] ? 'selected' : '' ?>><?= h(term_title($t)) ?><?= $current && (int)$t['id'] === (int)$current['id'] ? ' (ปัจจุบัน)' : '' ?></option><?php endforeach; ?>
        </select>
    </label>
</form>
<?php endif; ?>

<div class="admin-split sc-page">
    <div class="space-y-6 min-w-0">
        <div class="admin-tray">
            <div class="admin-tray-label"><span>คะแนน<?= $term ? ' · ' . h(term_title($term)) : '' ?></span></div>
            <section class="admin-card admin-table-wrap overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr><th class="text-left">สตาฟ</th><th class="text-right">คะแนน</th><th class="text-right">ถูกหัก (ครั้ง)</th></tr></thead>
                    <tbody>
                    <?php if (!$roster): ?><tr><td colspan="3" class="py-4 text-center text-gray-500">ยังไม่มีทีมสตาฟในภาคเรียนนี้</td></tr><?php endif; ?>
                    <?php foreach ($roster as $r): $sc = (int)$r['score']; ?>
                        <tr>
                            <td class="py-3"><?= h($r['first_name'] . ' ' . $r['last_name']) ?> <span class="text-gray-500">· <?= h($r['id_number']) ?></span></td>
                            <td class="py-3 text-right"><span class="admin-chip no-dot <?= $sc >= 100 ? 'is-ok' : ($sc >= 80 ? 'is-warning' : 'is-fail') ?>"><?= $sc ?> / 100</span></td>
                            <td class="py-3 text-right"><?= (int)$r['minus_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>

        <div class="admin-tray">
            <div class="admin-tray-label"><span>ประวัติคะแนน (<?= count($logs) ?>)</span></div>
            <section class="admin-card admin-table-wrap overflow-x-auto">
                <table class="w-full text-sm" data-pager>
                    <thead><tr><th class="text-left">วันที่</th><th class="text-left">สตาฟ</th><th class="text-left">เหตุผล</th><th class="text-right">คะแนน</th><th class="text-left" style="padding-left:20px">โดย</th><th></th></tr></thead>
                    <tbody>
                    <?php if (!$logs): ?><tr data-pager-ignore><td colspan="6" class="py-4 text-center text-gray-500">ยังไม่มีการหักหรือให้คะแนนในภาคเรียนนี้</td></tr><?php endif; ?>
                    <?php foreach ($logs as $l):
                        $pts = (int)$l['points_change'];
                        $isReversal = str_starts_with((string)$l['reason'], 'ยกเลิกรายการ #');
                        $canReverse = $isCurrentTerm && !$isReversal && empty($reversed[(int)$l['id']]); ?>
                        <tr class="<?= !empty($reversed[(int)$l['id']]) ? 'opacity-50' : '' ?>">
                            <td class="py-3 whitespace-nowrap"><?= h(tv_dt($l['created_at'])) ?></td>
                            <td class="py-3 sc-nowrap"><?= h($l['first_name']) ?></td>
                            <td class="py-3"><?= h($l['reason']) ?><?= $l['ticket_no'] ? ' <span class="text-gray-500">· ' . h($l['ticket_no']) . '</span>' : '' ?><?= !empty($reversed[(int)$l['id']]) ? ' <span class="text-gray-500">(ยกเลิกแล้ว)</span>' : '' ?></td>
                            <td class="py-3 text-right font-medium <?= $pts < 0 ? 'text-red-700' : 'text-emerald-700' ?>"><?= $pts > 0 ? '+' . $pts : $pts ?></td>
                            <td class="py-3 sc-nowrap" style="padding-left:20px"><?= h($l['by_first'] ?: 'ระบบ') ?></td>
                            <td class="py-3 text-right" style="padding-left:12px">
                                <?php if ($canReverse): ?>
                                    <form method="post" data-confirm-submit="ยกเลิกรายการนี้? ระบบจะบันทึกรายการกลับค่า">
                                        <?= $csrf ?><input type="hidden" name="action" value="reverse"><input type="hidden" name="log_id" value="<?= (int)$l['id'] ?>">
                                        <button class="admin-reset-link">ยกเลิก</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>
    </div>

    <div class="space-y-6">
        <?php if ($isCurrentTerm || !$terms): ?>
        <div class="admin-tray">
            <div class="admin-tray-label"><span>หัก / ให้คะแนน</span></div>
            <form method="post" class="admin-card p-5 grid gap-3 text-sm" id="adjustForm">
                <?= $csrf ?><input type="hidden" name="action" value="adjust">
                <label class="block">สตาฟ
                    <select name="staff_id" required class="mt-1 w-full">
                        <?php foreach ($roster as $r): ?><option value="<?= (int)$r['user_id'] ?>"><?= h($r['first_name'] . ' ' . $r['last_name']) ?> (<?= (int)$r['score'] ?>)</option><?php endforeach; ?>
                    </select>
                </label>
                <label class="block">รายการ
                    <select name="rule_id" class="mt-1 w-full" data-rule-select>
                        <?php foreach ($activeRules as $rule): ?><option value="<?= (int)$rule['id'] ?>"><?= h($rule['label']) ?> (<?= (int)$rule['points'] > 0 ? '+' : '' ?><?= (int)$rule['points'] ?>)</option><?php endforeach; ?>
                        <option value="0">กำหนดเอง</option>
                    </select>
                </label>
                <label class="block" data-custom-points hidden>คะแนน (ติดลบ = หัก เช่น -10 · บวก = ให้ เช่น 5)
                    <input name="points" type="number" min="-100" max="100" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                </label>
                <label class="block"><span data-note-label>รายละเอียดเพิ่มเติม (ไม่บังคับ)</span>
                    <input name="note" maxlength="200" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="เช่น มาเข้าเวรสาย 30 นาที">
                </label>
                <label class="block">เลขใบงานที่เกี่ยวข้อง (ไม่บังคับ)
                    <input name="ticket_no" maxlength="40" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="เช่น LB-2026-...">
                </label>
                <p class="text-xs text-gray-500">บวกได้ไม่เกิน 100 คะแนน ถ้าเกินระบบตัดให้พอดี 100</p>
                <div class="flex justify-end"><button class="admin-btn"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> บันทึก</button></div>
            </form>
        </div>
        <?php else: ?>
            <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600">ภาคเรียนนี้ผ่านไปแล้ว ดูประวัติได้อย่างเดียว ปรับคะแนนได้เฉพาะภาคเรียนปัจจุบัน</div>
        <?php endif; ?>

        <div class="admin-tray">
            <div class="admin-tray-label"><span>รายการความผิด / ความดีตั้งต้น</span></div>
            <section class="admin-card p-4 text-sm space-y-2">
                <?php foreach ($rules as $rule): ?>
                    <form method="post" class="sc-rule rounded-lg border border-gray-200 p-2 <?= (int)$rule['is_active'] === 1 ? '' : 'opacity-60' ?>">
                        <?= $csrf ?><input type="hidden" name="action" value="save_rule"><input type="hidden" name="rule_id" value="<?= (int)$rule['id'] ?>">
                        <input name="label" maxlength="150" value="<?= h($rule['label']) ?>" aria-label="ชื่อรายการ" class="sc-rule-label w-full rounded-lg border border-gray-300 px-2 py-1">
                        <input name="points" type="number" min="-100" max="100" value="<?= (int)$rule['points'] ?>" aria-label="คะแนน" class="w-full rounded-lg border border-gray-300 px-2 py-1">
                        <label class="inline-flex items-center gap-1 text-xs"><input type="checkbox" name="is_active" value="1" <?= (int)$rule['is_active'] === 1 ? 'checked' : '' ?>> ใช้</label>
                        <button class="admin-btn admin-btn-secondary" title="บันทึก"><i class="fa-solid fa-check" aria-hidden="true"></i><span class="sr-only">บันทึก</span></button>
                    </form>
                <?php endforeach; ?>
                <form method="post" class="flex flex-wrap items-center gap-2 pt-2">
                    <?= $csrf ?><input type="hidden" name="action" value="add_rule">
                    <input name="label" maxlength="150" placeholder="รายการใหม่" aria-label="ชื่อรายการใหม่" class="min-w-0 flex-1 rounded-lg border border-gray-300 px-2 py-1">
                    <input name="points" type="number" min="-100" max="100" placeholder="-10" aria-label="คะแนนของรายการใหม่" class="w-20 rounded-lg border border-gray-300 px-2 py-1">
                    <button class="admin-btn"><i class="fa-solid fa-plus" aria-hidden="true"></i> เพิ่ม</button>
                </form>
                <p class="text-xs text-gray-500">คะแนนติดลบ = หัก บวก = ให้ · ปิด "ใช้" เพื่อซ่อนจากตัวเลือกโดยไม่กระทบประวัติเดิม</p>
            </section>
        </div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('adjustForm');
    if (form) {
        var sel = form.querySelector('[data-rule-select]');
        var sync = function () {
            var custom = sel.value === '0';
            form.querySelector('[data-custom-points]').hidden = !custom;
            form.querySelector('[name=points]').required = custom;
            form.querySelector('[name=note]').required = custom;
            form.querySelector('[data-note-label]').textContent = custom ? 'เหตุผล *' : 'รายละเอียดเพิ่มเติม (ไม่บังคับ)';
        };
        sel.addEventListener('change', sync);
        sync();
    }
    document.addEventListener('submit', function (e) {
        var f = e.target.closest('[data-confirm-submit]');
        if (f && !window.confirm(f.getAttribute('data-confirm-submit'))) e.preventDefault();
    });
})();
</script>

      </div>
<?php $footerBase = '../'; $footerVariant = 'app'; require __DIR__ . '/../includes/site_footer.php'; ?>
   </div>
</body>
</html>
