<?php
declare(strict_types=1);

/**
 * REPS · includes/signature.php — โมดูลเซ็นดิจิทัลกลาง (v3 หัวข้อ 4.2)
 *
 * วิธีเซ็นที่รองรับ:
 *   draw  = วาดลายเซ็นบนจอ (canvas)
 *   saved = ใช้ลายเซ็นอิเล็กทรอนิกส์ที่ผู้ใช้อัปโหลดไว้ในหน้าโปรไฟล์
 * ทุกครั้งที่เซ็นจะสร้างไฟล์ลายเซ็นใหม่แยกต่อการเซ็น (คัดลอกจากลายเซ็นที่บันทึกไว้) ลายเซ็นในใบงานจึงไม่เปลี่ยน
 * แม้ภายหลังผู้ใช้เปลี่ยนหรือลบลายเซ็นในโปรไฟล์
 *
 *   echo signature_field($conn, $userId, '');          // ช่องเซ็นในฟอร์ม (ต้องอยู่ใน <form>)
 *   [$path, $method] = signature_capture($conn, $userId);  // ฝั่งเซิร์ฟเวอร์ ตอนรับ POST
 */

require_once __DIR__ . '/db_helpers.php';
require_once __DIR__ . '/private_storage.php';

function signature_saved_path(mysqli $conn, int $userId): ?string
{
    try {
        $row = db_one($conn, 'SELECT signature_path FROM users WHERE user_id = ?', 'i', [$userId]);
    } catch (Throwable) {
        return null;
    }
    $path = (string)($row['signature_path'] ?? '');
    return $path !== '' && app_private_file_abs($path) !== null && is_file((string)app_private_file_abs($path)) ? $path : null;
}

/**
 * อ่านลายเซ็นจาก POST (sig_method, sig_data) แล้วบันทึกเป็นไฟล์ใหม่
 * @return array{0:string,1:string} [path, method]
 */
function signature_capture(mysqli $conn, int $userId): array
{
    $method = is_string($_POST['sig_method'] ?? null) ? $_POST['sig_method'] : 'draw';
    if ($method === 'saved') {
        $saved = signature_saved_path($conn, $userId);
        if ($saved === null) {
            throw new AppUserException('ยังไม่มีลายเซ็นที่บันทึกไว้ กรุณาวาดลายเซ็น หรืออัปโหลดลายเซ็นในหน้าโปรไฟล์ก่อน');
        }
        $src = (string)app_private_file_abs($saved);
        $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION)) ?: 'png';
        $name = 'sig_' . bin2hex(random_bytes(16)) . '.' . $ext;
        $dir = app_ensure_upload_dir();
        if (!@copy($src, $dir . DIRECTORY_SEPARATOR . $name)) {
            throw new RuntimeException('Could not copy saved signature');
        }
        @chmod($dir . DIRECTORY_SEPARATOR . $name, 0600);
        return ['ticket-private/' . $name, 'saved'];
    }
    $data = is_string($_POST['sig_data'] ?? null) ? $_POST['sig_data'] : '';
    return [app_store_signature_data_url($data), 'draw'];
}

/** ช่องเซ็นสำหรับใส่ในฟอร์ม — $base = '' (หน้าในราก) หรือ '../' */
function signature_field(mysqli $conn, int $userId, string $base = '', string $label = 'ลายเซ็น'): string
{
    $h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $saved = signature_saved_path($conn, $userId);
    $uid = 'sig' . bin2hex(random_bytes(4));
    ob_start(); ?>
<fieldset class="sig-field" data-sig-field id="<?= $uid ?>">
    <legend class="sig-legend"><?= $h($label) ?></legend>
    <input type="hidden" name="sig_method" value="<?= $saved ? 'saved' : 'draw' ?>" data-sig-method>
    <input type="hidden" name="sig_data" value="" data-sig-data>
    <?php if ($saved): ?>
        <div class="sig-tabs" role="tablist">
            <button type="button" role="tab" class="sig-tab is-active" data-sig-tab="saved" aria-selected="true">ใช้ลายเซ็นที่บันทึกไว้</button>
            <button type="button" role="tab" class="sig-tab" data-sig-tab="draw" aria-selected="false">วาดลายเซ็นใหม่</button>
        </div>
        <div class="sig-pane" data-sig-pane="saved">
            <img class="sig-saved" src="<?= $h($base) ?>file.php?k=mysig&amp;v=<?= $h(substr(basename($saved), 0, 20)) ?>" alt="ลายเซ็นที่บันทึกไว้">
        </div>
    <?php endif; ?>
    <div class="sig-pane" data-sig-pane="draw"<?= $saved ? ' hidden' : '' ?>>
        <canvas class="sig-canvas" width="640" height="200" data-sig-canvas aria-label="พื้นที่วาดลายเซ็น"></canvas>
        <div class="sig-tools">
            <span class="sig-hint">ใช้นิ้วหรือเมาส์เซ็นในกรอบ</span>
            <button type="button" class="sig-clear" data-sig-clear>ล้างลายเซ็น</button>
        </div>
    </div>
    <?php if (!$saved): ?>
        <p class="sig-note">อัปโหลดลายเซ็นเก็บไว้ได้ที่ <a href="<?= $h($base) ?>profile.php#signature">หน้าโปรไฟล์</a> เพื่อเลือกใช้ครั้งต่อไปโดยไม่ต้องวาด</p>
    <?php endif; ?>
</fieldset>
<?php
    static $assets = false;
    if (!$assets) {
        $assets = true; ?>
<style>
.sig-field { min-width: 0; margin: 0; padding: 0; border: 0; }
.sig-legend { margin-bottom: 6px; font-size: 14px; font-weight: 500; color: #161616; }
.sig-tabs { display: inline-flex; gap: 4px; margin-bottom: 8px; padding: 3px; border: 1px solid #E7E4DF; border-radius: 10px; background: #F7F6F4; }
.sig-tab { min-height: 34px; padding: 0 12px; border: 0; border-radius: 8px; background: transparent; color: #57544F; font: inherit; font-size: 13px; cursor: pointer; }
.sig-tab.is-active { background: #fff; color: #161616; font-weight: 500; box-shadow: 0 1px 2px rgba(22, 22, 22, .08); }
.sig-saved { display: block; max-width: 100%; height: 120px; padding: 10px; border: 1px solid #E7E4DF; border-radius: 12px; background: #fff; object-fit: contain; }
.sig-canvas { display: block; width: 100%; max-width: 640px; height: auto; aspect-ratio: 16 / 5; border: 1px dashed #BDB8B0; border-radius: 12px; background: #fff; touch-action: none; cursor: crosshair; }
.sig-tools { display: flex; align-items: center; justify-content: space-between; gap: 8px; max-width: 640px; margin-top: 6px; font-size: 12.5px; color: #6B6862; }
.sig-clear { min-height: 32px; padding: 0 10px; border: 1px solid #D4D0C9; border-radius: 8px; background: #fff; color: #3F3D39; font: inherit; font-size: 12.5px; cursor: pointer; }
.sig-note { margin: 6px 0 0; font-size: 12.5px; color: #6B6862; }
.sig-note a { color: #161616; text-decoration: underline; }
</style>
<script>
(function () {
    function setup(field) {
        var method = field.querySelector('[data-sig-method]');
        var data = field.querySelector('[data-sig-data]');
        var canvas = field.querySelector('[data-sig-canvas]');
        var ctx = canvas.getContext('2d');
        var drawing = false, dirty = false, last = null;
        ctx.lineWidth = 2.6; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#111';
        function pos(e) {
            var r = canvas.getBoundingClientRect();
            return { x: (e.clientX - r.left) * canvas.width / r.width, y: (e.clientY - r.top) * canvas.height / r.height };
        }
        canvas.addEventListener('pointerdown', function (e) { drawing = true; last = pos(e); canvas.setPointerCapture(e.pointerId); });
        canvas.addEventListener('pointermove', function (e) {
            if (!drawing) return;
            var p = pos(e);
            ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke();
            last = p; dirty = true;
        });
        ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (ev) { canvas.addEventListener(ev, function () { drawing = false; }); });
        field.querySelector('[data-sig-clear]').addEventListener('click', function () { ctx.clearRect(0, 0, canvas.width, canvas.height); dirty = false; });
        field.querySelectorAll('[data-sig-tab]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                var m = tab.getAttribute('data-sig-tab');
                method.value = m;
                field.querySelectorAll('[data-sig-tab]').forEach(function (t) {
                    var on = t === tab; t.classList.toggle('is-active', on); t.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                field.querySelectorAll('[data-sig-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-sig-pane') !== m; });
            });
        });
        var form = field.closest('form');
        if (form) form.addEventListener('submit', function (e) {
            if (e.submitter && e.submitter.hasAttribute('data-sig-skip')) return; // ปุ่มที่ไม่ต้องใช้ลายเซ็น (เช่น ไม่ยอมรับ)
            if (method.value === 'draw') {
                if (!dirty) { e.preventDefault(); alert('กรุณาเซ็นชื่อในกรอบก่อนยืนยัน'); return; }
                data.value = canvas.toDataURL('image/png');
            }
        });
    }
    function init() { document.querySelectorAll('[data-sig-field]').forEach(setup); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>
<?php }
    return (string)ob_get_clean();
}
