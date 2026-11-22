<?php
declare(strict_types=1);

$page_title = 'Form Builder';
$current_page = 'forms';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/site_config.php';
require_once __DIR__ . '/../includes/site_custom.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/form_builder.php';

if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));

function fb_h(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fb_redirect(string $url): never
{
    header('Location: ' . $url, true, 303);
    exit;
}

function fb_record_revision(mysqli $conn, int $formId, array $definition, string $status, int $adminId, string $note): void
{
    site_revision_save($conn, 'form', (string)$formId, ['definition' => $definition, 'status' => $status], $adminId, $note);
}

$adminId = (int)$adminId;
$errorMessage = '';
$migrationReady = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['admin_csrf'], is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '')) {
        http_response_code(403);
        exit('คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
    try {
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        $formId = filter_var($_POST['form_id'] ?? null, FILTER_VALIDATE_INT);
        if ($action === 'create_form') {
            $key = form_builder_validate_form_key($_POST['form_key'] ?? null);
            $title = form_builder_text($_POST['title'] ?? null, 200);
            $definition = form_builder_default_definition($title);
            $json = json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $formId = db_insert($conn, 'INSERT INTO site_forms (form_key, draft_json, created_by, updated_by) VALUES (?, ?, ?, ?)', 'ssii', [$key, $json, $adminId, $adminId]);
            fb_record_revision($conn, $formId, $definition, 'draft', $adminId, 'Created form');
            fb_redirect('admin_forms.php?id=' . $formId . '&created=1');
        }

        if (!$formId || $formId < 1) throw new InvalidArgumentException('Form ID is required');
        $form = form_builder_load($conn, (int)$formId);
        if (!$form) throw new InvalidArgumentException('Form does not exist or has an invalid schema');

        if ($action === 'save_draft' || $action === 'publish_form') {
            $definition = form_builder_normalize_posted_definition($_POST['definition'] ?? null);
            $json = json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $newStatus = $action === 'publish_form' ? 'published' : (string)$form['status'];
            db_transaction($conn, static function () use ($conn, $formId, $json, $newStatus, $adminId): void {
                if ($newStatus === 'published') {
                    db_exec($conn, 'UPDATE site_forms SET draft_json = ?, published_json = ?, status = ?, updated_by = ? WHERE id = ?', 'sssii', [$json, $json, $newStatus, $adminId, $formId]);
                } else {
                    db_exec($conn, 'UPDATE site_forms SET draft_json = ?, updated_by = ? WHERE id = ?', 'sii', [$json, $adminId, $formId]);
                }
            });
            fb_record_revision($conn, (int)$formId, $definition, $newStatus, $adminId, $action === 'publish_form' ? 'Published form' : 'Saved form draft');
            fb_redirect('admin_forms.php?id=' . $formId . '&' . ($action === 'publish_form' ? 'published=1' : 'saved=1'));
        }

        if ($action === 'unpublish_form') {
            form_builder_require_unreferenced_from_published_pages($conn, (int)$formId);
            db_exec($conn, "UPDATE site_forms SET status = 'draft', updated_by = ? WHERE id = ?", 'ii', [$adminId, $formId]);
            fb_record_revision($conn, (int)$formId, $form['draft'], 'draft', $adminId, 'Unpublished form');
            fb_redirect('admin_forms.php?id=' . $formId . '&unpublished=1');
        }

        if ($action === 'restore_revision') {
            $revisionId = (int)($_POST['revision_id'] ?? 0);
            $revision = db_one($conn, "SELECT data_json FROM site_revisions WHERE id = ? AND entity_type = 'form' AND entity_id = ? LIMIT 1", 'is', [$revisionId, (string)$formId]);
            if (!$revision) throw new InvalidArgumentException('Form revision does not exist');
            try {
                $snapshot = json_decode((string)$revision['data_json'], true, 32, JSON_THROW_ON_ERROR);
                $definition = form_builder_validate_definition($snapshot['definition'] ?? null);
            } catch (JsonException|InvalidArgumentException $e) {
                throw new InvalidArgumentException('Form revision is invalid', 0, $e);
            }
            $json = json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            db_exec($conn, 'UPDATE site_forms SET draft_json = ?, updated_by = ? WHERE id = ?', 'sii', [$json, $adminId, $formId]);
            fb_record_revision($conn, (int)$formId, $definition, (string)$form['status'], $adminId, 'Restored from revision #' . $revisionId);
            fb_redirect('admin_forms.php?id=' . $formId . '&restored=1');
        }

        if ($action === 'delete_submission') {
            $submissionId = (int)($_POST['submission_id'] ?? 0);
            $submission = db_one($conn, 'SELECT id FROM site_form_submissions WHERE id = ? AND form_id = ? LIMIT 1', 'ii', [$submissionId, $formId]);
            if (!$submission) throw new InvalidArgumentException('Submission does not exist');
            db_transaction($conn, static function () use ($conn, $submissionId, $formId, $adminId): void {
                db_exec($conn, 'DELETE FROM site_form_submissions WHERE id = ? AND form_id = ?', 'ii', [$submissionId, $formId]);
                site_revision_save($conn, 'form_submission', (string)$submissionId, ['form_id' => $formId], $adminId, 'Deleted form submission');
            });
            fb_redirect('admin_forms.php?id=' . $formId . '&submissions=1');
        }

        throw new InvalidArgumentException('Unknown form action');
    } catch (InvalidArgumentException $e) {
        $_SESSION['form_builder_error'] = tr('ข้อมูลฟอร์มไม่ถูกต้อง กรุณาตรวจสอบแล้วลองอีกครั้ง', 'Invalid form data. Review the fields and try again.');
        error_log('admin_forms validation: ' . $e->getMessage());
        $redirectId = (int)($_POST['form_id'] ?? 0);
        fb_redirect('admin_forms.php' . ($redirectId > 0 ? '?id=' . $redirectId : ''));
    } catch (Throwable $e) {
        $_SESSION['form_builder_error'] = tr('บันทึกไม่สำเร็จ กรุณาตรวจสอบ migration แล้วลองใหม่', 'Could not save. Check the form migration and try again.');
        error_log('admin_forms save: ' . $e->getMessage());
        $redirectId = (int)($_POST['form_id'] ?? 0);
        fb_redirect('admin_forms.php' . ($redirectId > 0 ? '?id=' . $redirectId : ''));
    }
}

$errorMessage = is_string($_SESSION['form_builder_error'] ?? null) ? $_SESSION['form_builder_error'] : '';
unset($_SESSION['form_builder_error']);
$selectedId = (int)($_GET['id'] ?? 0);
$form = null;
$forms = [];
$revisions = [];
$submissions = [];
try {
    $forms = db_all($conn, 'SELECT f.id, f.form_key, f.status, f.updated_at, f.draft_json, (SELECT COUNT(*) FROM site_form_submissions s WHERE s.form_id = f.id) AS submissions FROM site_forms f ORDER BY f.updated_at DESC, f.id DESC');
    foreach ($forms as &$formRow) {
        try { $formRow['definition'] = form_builder_definition_from_json((string)$formRow['draft_json']); }
        catch (InvalidArgumentException) { $formRow['definition'] = ['title' => tr('ฟอร์มเสียหาย', 'Invalid form')]; }
    }
    unset($formRow);
    if ($selectedId > 0) {
        $form = form_builder_load($conn, $selectedId);
        if (!$form) throw new InvalidArgumentException('Form does not exist');
        $revisions = db_all($conn, "SELECT r.id, r.note, r.created_at, COALESCE(u.first_name,'') AS first_name, COALESCE(u.last_name,'') AS last_name FROM site_revisions r LEFT JOIN users u ON u.user_id = r.created_by WHERE r.entity_type = 'form' AND r.entity_id = ? ORDER BY r.id DESC LIMIT 12", 's', [(string)$selectedId]);
        if (isset($_GET['submissions'])) {
            $submissions = db_all($conn, 'SELECT id, data_json, submitted_at FROM site_form_submissions WHERE form_id = ? ORDER BY id DESC LIMIT 100', 'i', [$selectedId]);
        }
    }
} catch (Throwable $e) {
    $migrationReady = false;
    error_log('admin_forms load: ' . $e->getMessage());
}

$admin_extra_head = '<link rel="stylesheet" href="../assets/css/form-builder.css?v=2"><script src="../assets/js/form-builder.js?v=1" defer></script><script src="../assets/js/cms-pages.js?v=1" defer></script>';
require_once __DIR__ . '/admin_header.php';
?>

<div class="admin-page-head">
    <nav class="admin-crumb"><a href="admin_dashboard.php"><?= tr('แดชบอร์ด', 'Dashboard') ?></a><i class="fa-solid fa-chevron-right" aria-hidden="true"></i><span><?= tr('Form Builder', 'Form Builder') ?></span></nav>
    <h1><?= tr('Form Builder', 'Form Builder') ?></h1>
    <p class="admin-page-sub"><?= tr('สร้างแบบฟอร์มปลอดภัยสำหรับฝังในหน้า CMS โดยไม่แก้ไขแบบฟอร์มแจ้งซ่อมหรือสมัครสมาชิก', 'Create safe forms for CMS pages without changing repair requests or registration.') ?></p>
</div>

<?php if ($errorMessage !== ''): ?><div class="vb-studio-message vb-studio-message--error" role="alert"><?= fb_h($errorMessage) ?></div><?php endif; ?>
<?php if (!$migrationReady): ?><div class="vb-studio-message vb-studio-message--error" role="alert"><?= tr('ยังอ่าน Form Builder ไม่ได้ กรุณาตรวจสอบและ apply migration 20261122_reps4_visual_forms.sql', 'Form Builder is unavailable. Check and apply migration 20261122_reps4_visual_forms.sql.') ?></div><?php else: ?>
    <?php if ($selectedId < 1): ?>
        <section class="fb-panel">
            <h2><?= tr('สร้างแบบฟอร์ม', 'Create a form') ?></h2>
            <form method="post" class="fb-create-form">
                <input type="hidden" name="csrf" value="<?= fb_h($_SESSION['admin_csrf']) ?>">
                <label class="vb-field"><span><?= tr('ชื่อแบบฟอร์ม', 'Form title') ?></span><input name="title" maxlength="200" required></label>
                <label class="vb-field"><span>Key</span><input name="form_key" maxlength="80" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" required><small><?= tr('ใช้ตัวพิมพ์เล็ก ตัวเลข และขีดกลาง', 'Lowercase letters, numbers, and hyphens only.') ?></small></label>
                <button class="admin-btn" name="action" value="create_form"><?= tr('สร้างแบบฟอร์ม', 'Create form') ?></button>
            </form>
        </section>
        <section class="fb-panel">
            <h2><?= tr('แบบฟอร์ม', 'Forms') ?></h2>
            <?php if ($forms === []): ?><p class="vb-help"><?= tr('ยังไม่มีแบบฟอร์ม', 'No forms yet.') ?></p><?php else: ?>
                <div class="fb-form-list">
                    <?php foreach ($forms as $item): ?>
                        <a href="admin_forms.php?id=<?= (int)$item['id'] ?>"><strong><?= fb_h($item['definition']['title']) ?></strong><span><code><?= fb_h($item['form_key']) ?></code> · <?= $item['status'] === 'published' ? tr('เผยแพร่', 'Published') : tr('ฉบับร่าง', 'Draft') ?> · <?= (int)$item['submissions'] ?> <?= tr('คำตอบ', 'responses') ?></span></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php elseif ($form !== null): ?>
        <?php $definition = $form['draft']; $isPublished = $form['status'] === 'published'; ?>
        <div class="fb-actions">
            <a class="admin-btn admin-btn-secondary" href="admin_forms.php"><?= tr('กลับรายการฟอร์ม', 'Back to forms') ?></a>
            <a class="admin-btn admin-btn-secondary" href="admin_pages.php"><?= tr('แก้หน้า CMS', 'Edit CMS pages') ?></a>
            <a class="admin-btn admin-btn-secondary" href="admin_forms.php?id=<?= (int)$selectedId ?>&submissions=1"><?= tr('ดูคำตอบ', 'View responses') ?></a>
            <?php if ($isPublished): ?><form method="post" data-cms-confirm="<?= fb_h(tr('ยกเลิกการเผยแพร่แบบฟอร์มนี้?', 'Unpublish this form?')) ?>"><input type="hidden" name="csrf" value="<?= fb_h($_SESSION['admin_csrf']) ?>"><input type="hidden" name="form_id" value="<?= (int)$selectedId ?>"><button class="admin-btn admin-btn-secondary" name="action" value="unpublish_form"><?= tr('ยกเลิกเผยแพร่', 'Unpublish') ?></button></form><?php endif; ?>
        </div>
        <?php if (!isset($_GET['submissions'])): ?>
            <form method="post" class="fb-panel fb-editor" id="fbEditor" data-max-fields="<?= FORM_BUILDER_MAX_FIELDS ?>">
                <input type="hidden" name="csrf" value="<?= fb_h($_SESSION['admin_csrf']) ?>"><input type="hidden" name="form_id" value="<?= (int)$selectedId ?>">
                <div class="fb-form-properties">
                    <label class="vb-field"><span><?= tr('ชื่อแบบฟอร์ม', 'Form title') ?></span><input name="definition[title]" maxlength="200" required value="<?= fb_h($definition['title']) ?>"></label>
                    <label class="vb-field"><span><?= tr('คำอธิบาย', 'Description') ?></span><textarea name="definition[description]" maxlength="1000" rows="3"><?= fb_h($definition['description']) ?></textarea></label>
                    <label class="vb-field"><span><?= tr('ข้อความปุ่มส่ง', 'Submit button label') ?></span><input name="definition[submit_label]" maxlength="80" required value="<?= fb_h($definition['submit_label']) ?>"></label>
                    <label class="vb-field"><span><?= tr('ข้อความหลังส่ง', 'Success message') ?></span><input name="definition[success_message]" maxlength="300" required value="<?= fb_h($definition['success_message']) ?>"></label>
                </div>
                <div class="vb-panel-head"><h2><?= tr('ฟิลด์', 'Fields') ?></h2><span><?= tr('สูงสุด', 'Maximum') ?> <?= FORM_BUILDER_MAX_FIELDS ?></span></div>
                <div class="fb-field-list" id="fbFieldList">
                    <?php foreach ($definition['fields'] as $index => $field): ?>
                        <fieldset class="fb-field-card" data-form-field>
                            <legend><?= tr('ฟิลด์', 'Field') ?> <?= $index + 1 ?></legend>
                            <div class="fb-field-grid">
                                <label class="vb-field"><span><?= tr('Key', 'Key') ?></span><input name="definition[fields][<?= $index ?>][key]" pattern="[a-z][a-z0-9_]{1,39}" maxlength="40" required value="<?= fb_h($field['key']) ?>"></label>
                                <label class="vb-field"><span><?= tr('ป้ายชื่อ', 'Label') ?></span><input name="definition[fields][<?= $index ?>][label]" maxlength="100" required value="<?= fb_h($field['label']) ?>"></label>
                                <label class="vb-field"><span><?= tr('ชนิด', 'Type') ?></span><select name="definition[fields][<?= $index ?>][type]" data-form-field-type><?php foreach (FORM_BUILDER_FIELD_TYPES as $type): ?><option value="<?= fb_h($type) ?>"<?= $field['type'] === $type ? ' selected' : '' ?>><?= fb_h(ucfirst($type)) ?></option><?php endforeach; ?></select></label>
                                <label class="fb-required"><input type="checkbox" name="definition[fields][<?= $index ?>][required]" value="1"<?= $field['required'] ? ' checked' : '' ?>> <?= tr('จำเป็นต้องกรอก', 'Required') ?></label>
                                <label class="vb-field fb-options"><span><?= tr('ตัวเลือก (บรรทัดละรายการ)', 'Options (one per line)') ?></span><textarea name="definition[fields][<?= $index ?>][options]" rows="3"><?= fb_h(implode("\n", $field['options'])) ?></textarea></label>
                            </div>
                            <div class="fb-field-actions"><button type="button" class="admin-btn admin-btn-secondary" data-field-move="up">↑ <?= tr('ขึ้น', 'Move up') ?></button><button type="button" class="admin-btn admin-btn-secondary" data-field-move="down">↓ <?= tr('ลง', 'Move down') ?></button><button type="button" class="admin-btn admin-btn-danger" data-field-remove><?= tr('ลบฟิลด์', 'Remove field') ?></button></div>
                        </fieldset>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="admin-btn admin-btn-secondary" data-field-add><?= tr('เพิ่มฟิลด์', 'Add field') ?></button>
                <div class="fb-form-actions"><button class="admin-btn admin-btn-secondary" name="action" value="save_draft"><?= tr('บันทึกร่าง', 'Save draft') ?></button><button class="admin-btn" name="action" value="publish_form"><?= tr('เผยแพร่แบบฟอร์ม', 'Publish form') ?></button></div>
            </form>
            <section class="fb-panel"><h2><?= tr('ประวัติแบบฟอร์ม', 'Form revisions') ?></h2>
                <?php if ($revisions === []): ?><p class="vb-help"><?= tr('ยังไม่มีประวัติ', 'No revisions yet.') ?></p><?php else: ?><ol class="fb-revisions"><?php foreach ($revisions as $revision): ?><li><span><?= fb_h(date('d/m/Y H:i', strtotime((string)$revision['created_at']))) ?> · <?= fb_h(trim($revision['first_name'] . ' ' . $revision['last_name'])) ?> · <?= fb_h($revision['note']) ?></span><form method="post" data-cms-confirm="<?= fb_h(tr('กู้คืนเป็นฉบับร่างหรือไม่?', 'Restore this revision as a draft?')) ?>"><input type="hidden" name="csrf" value="<?= fb_h($_SESSION['admin_csrf']) ?>"><input type="hidden" name="form_id" value="<?= (int)$selectedId ?>"><input type="hidden" name="revision_id" value="<?= (int)$revision['id'] ?>"><button class="admin-btn admin-btn-secondary" name="action" value="restore_revision"><?= tr('กู้คืนร่าง', 'Restore draft') ?></button></form></li><?php endforeach; ?></ol><?php endif; ?>
            </section>
        <?php else: ?>
            <section class="fb-panel"><h2><?= tr('คำตอบล่าสุด', 'Latest responses') ?></h2>
                <?php if ($submissions === []): ?><p class="vb-help"><?= tr('ยังไม่มีคำตอบ', 'No responses yet.') ?></p><?php else: ?><div class="fb-submissions"><?php foreach ($submissions as $submission): ?><article><header><strong>#<?= (int)$submission['id'] ?></strong><time><?= fb_h(date('d/m/Y H:i', strtotime((string)$submission['submitted_at']))) ?></time></header><dl><?php $answers = json_decode((string)$submission['data_json'], true); foreach (is_array($answers) ? $answers : [] as $answer): ?><dt><?= fb_h($answer['label'] ?? '') ?></dt><dd><?= nl2br(fb_h((string)($answer['value'] ?? ''))) ?></dd><?php endforeach; ?></dl><form method="post" data-cms-confirm="<?= fb_h(tr('ลบคำตอบนี้ถาวรหรือไม่?', 'Delete this response permanently?')) ?>"><input type="hidden" name="csrf" value="<?= fb_h($_SESSION['admin_csrf']) ?>"><input type="hidden" name="form_id" value="<?= (int)$selectedId ?>"><input type="hidden" name="submission_id" value="<?= (int)$submission['id'] ?>"><button class="admin-btn admin-btn-danger" name="action" value="delete_submission"><?= tr('ลบคำตอบ', 'Delete response') ?></button></form></article><?php endforeach; ?></div><?php endif; ?>
            </section>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

      </div>
<?php $footerBase = '../'; $footerVariant = 'app'; require __DIR__ . '/../includes/site_footer.php'; ?>
   </div>
</body>
</html>
