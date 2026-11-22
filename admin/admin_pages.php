<?php
declare(strict_types=1);

/**
 * REPS · admin/admin_pages.php — หน้าเว็บเนื้อหา (CMS) บนตาราง site_pages / site_blocks
 *
 * - แก้เนื้อหาเป็นบล็อกที่ระบบกำหนด (ไม่รับ HTML/CSS/JavaScript จากผู้ใช้)
 * - แยกฉบับร่างกับฉบับเผยแพร่: บันทึกร่างก่อน แล้วกด "บันทึกและเผยแพร่" เมื่อพร้อม
 * - ดูตัวอย่างฉบับร่างได้เฉพาะผู้ดูแลที่ล็อกอิน (page.php?slug=...&preview=1)
 * - ทุกการบันทึกเก็บประวัติใน site_revisions
 */

$page_title = 'หน้าเว็บ (CMS)';
$current_page = 'pages';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/site_config.php';
require_once __DIR__ . '/../includes/site_custom.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/visual_builder.php';
require_once __DIR__ . '/../includes/form_builder.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

function cms_h(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cms_redirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}

function cms_date(?string $value): string
{
    $timestamp = $value !== null && $value !== '' ? strtotime($value) : false;
    return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : '—';
}

function cms_author(array $row): string
{
    $name = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
    return $name !== '' ? $name : '—';
}

/** @return list<array<string,mixed>> */
function cms_normalize_posted_blocks(mixed $posted): array
{
    if (!is_array($posted)) {
        return [];
    }
    $blocks = [];
    foreach ($posted as $block) {
        if (!is_array($block) || !empty($block['remove'])) {
            continue;
        }
        unset($block['remove']);
        $block['is_visible'] = isset($block['is_visible']) && (string)$block['is_visible'] === '1' ? '1' : '0';
        $blocks[] = $block;
    }
    return $blocks;
}

function cms_field_id(string $index, string $name): string
{
    return 'vb-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $index) . '-' . $name;
}

/** ช่องกรอกของบล็อกหนึ่งชนิด (ใช้ทั้งการ์ดที่บันทึกแล้วและ template ของ JS) */
function cms_block_fields(string $type, array $config, string $index, array $media, array $forms = []): string
{
    $component = VISUAL_BUILDER_COMPONENTS[$type] ?? null;
    if ($component === null) {
        return '';
    }
    if ($component['fields'] === []) {
        return '<p class="vb-help">' . cms_h(tr('บล็อกนี้ไม่มีการตั้งค่า', 'This block has no settings.')) . '</p>';
    }

    $optionLabels = [
        'h2' => tr('หัวข้อใหญ่ (H2)', 'Large heading (H2)'),
        'h3' => tr('หัวข้อย่อย (H3)', 'Subheading (H3)'),
        'primary' => tr('หลัก', 'Primary'),
        'secondary' => tr('รอง', 'Secondary'),
        'bullet' => tr('หัวข้อย่อยแบบจุด', 'Bulleted'),
        'number' => tr('หัวข้อย่อยแบบตัวเลข', 'Numbered'),
        'info' => tr('ข้อมูล', 'Info'),
        'success' => tr('สำเร็จ', 'Success'),
        'warning' => tr('เตือน', 'Warning'),
        'small' => tr('เล็ก', 'Small'),
        'medium' => tr('กลาง', 'Medium'),
        'large' => tr('ใหญ่', 'Large'),
    ];

    $html = '';
    foreach ($component['fields'] as $name => $field) {
        $id = cms_field_id($index, $name);
        $label = tr($field['label_th'], $field['label_en']);
        $value = $config[$name] ?? '';
        $fieldName = 'blocks[' . $index . '][' . $name . ']';

        if ($field['type'] === 'textarea') {
            $control = '<textarea id="' . cms_h($id) . '" name="' . cms_h($fieldName) . '" rows="4" maxlength="'
                . (int)$field['max'] . '">' . cms_h((string)$value) . '</textarea>';
        } elseif ($field['type'] === 'select') {
            $options = '';
            foreach ($field['options'] as $option) {
                $options .= '<option value="' . cms_h($option) . '"' . ((string)$value === (string)$option ? ' selected' : '') . '>'
                    . cms_h($optionLabels[$option] ?? $option) . '</option>';
            }
            $control = '<select id="' . cms_h($id) . '" name="' . cms_h($fieldName) . '">' . $options . '</select>';
        } elseif ($field['type'] === 'media') {
            $options = '<option value="0">' . cms_h(tr('— ยังไม่ได้เลือกรูป —', '— No image selected —')) . '</option>';
            foreach ($media as $item) {
                $title = trim((string)($item['title'] ?? ''));
                $options .= '<option value="' . (int)$item['id'] . '"' . ((int)$value === (int)$item['id'] ? ' selected' : '') . '>'
                    . cms_h($title !== '' ? $title : ('#' . (int)$item['id'])) . '</option>';
            }
            $control = '<select id="' . cms_h($id) . '" name="' . cms_h($fieldName) . '">' . $options . '</select>';
            $control .= '<small>' . cms_h(tr('รูปจากคลังสื่อ (หน้า ปรับแต่งเว็บ → คลังสื่อ)', 'Image from the media library (Customize site → Media)')) . '</small>';
        } elseif ($field['type'] === 'form') {
            $options = '<option value="0">' . cms_h(tr('— เลือกแบบฟอร์ม —', '— Select a form —')) . '</option>';
            foreach ($forms as $form) {
                $options .= '<option value="' . (int)$form['id'] . '"' . ((int)$value === (int)$form['id'] ? ' selected' : '') . '>'
                    . cms_h($form['title']) . ' · ' . cms_h($form['status']) . '</option>';
            }
            $control = '<select id="' . cms_h($id) . '" name="' . cms_h($fieldName) . '">' . $options . '</select>'
                . '<small>' . cms_h(tr('เผยแพร่แบบฟอร์มก่อนเผยแพร่หน้านี้', 'Publish the form before publishing this page.')) . '</small>';
        } else {
            $control = '<input type="text" id="' . cms_h($id) . '" name="' . cms_h($fieldName) . '" maxlength="'
                . (int)$field['max'] . '" value="' . cms_h((string)$value) . '">';
        }

        $html .= '<label class="vb-field" for="' . cms_h($id) . '"><span>' . cms_h($label) . '</span>' . $control . '</label>';
    }

    $animationId = cms_field_id($index, 'animation');
    $animationName = 'blocks[' . $index . '][animation]';
    $animation = is_string($config['animation'] ?? null) ? $config['animation'] : 'none';
    $animationOptions = '';
    foreach (VISUAL_BUILDER_ANIMATIONS as $key => $item) {
        $animationOptions .= '<option value="' . cms_h($key) . '"' . ($animation === $key ? ' selected' : '') . '>'
            . cms_h(tr($item['label_th'], $item['label_en'])) . '</option>';
    }
    $html .= '<label class="vb-field" for="' . cms_h($animationId) . '"><span>'
        . cms_h(tr('ภาพเคลื่อนไหว', 'Animation')) . '</span><select id="' . cms_h($animationId)
        . '" name="' . cms_h($animationName) . '" data-vb-animation>' . $animationOptions . '</select></label>';

    return $html;
}

/** การ์ดบล็อกหนึ่งใบ (index เป็น int สำหรับข้อมูลจริง หรือ '__INDEX__' สำหรับ template) */
function cms_block_card(string $type, array $config, int|string $index, bool $visible, array $media, array $forms = []): string
{
    $component = VISUAL_BUILDER_COMPONENTS[$type] ?? null;
    if ($component === null) {
        return '';
    }
    $typeOptions = '';
    foreach (VISUAL_BUILDER_COMPONENTS as $key => $item) {
        $typeOptions .= '<option value="' . cms_h($key) . '"' . ($key === $type ? ' selected' : '') . '>'
            . cms_h(tr($item['label_th'], $item['label_en'])) . '</option>';
    }
    $number = is_int($index) ? (string)($index + 1) : '';
    $label = tr($component['label_th'], $component['label_en']);

    return '<fieldset class="vb-block-card" data-vb-block>'
        . '<legend><span data-vb-layer-number>' . cms_h($number) . '</span> · <span data-vb-type-label>' . cms_h($label) . '</span></legend>'
        . '<div class="vb-block-toolbar">'
        . '<label class="vb-field vb-type-field"><span>' . cms_h(tr('ชนิด', 'Type')) . '</span>'
        . '<select name="blocks[' . cms_h((string)$index) . '][type]" data-vb-type>' . $typeOptions . '</select></label>'
        . '<label class="vb-visibility-field"><span>' . cms_h(tr('แสดง', 'Visible')) . '</span>'
        . '<input type="checkbox" name="blocks[' . cms_h((string)$index) . '][is_visible]" value="1"' . ($visible ? ' checked' : '') . '></label>'
        . '<label class="vb-visibility-field"><span>' . cms_h(tr('ลบ', 'Remove')) . '</span>'
        . '<input type="checkbox" name="blocks[' . cms_h((string)$index) . '][remove]" value="1"></label>'
        . '<div class="vb-move-controls">'
        . '<button type="button" data-vb-move="up" title="' . cms_h(tr('เลื่อนขึ้น', 'Move up')) . '" aria-label="' . cms_h(tr('เลื่อนขึ้น', 'Move up')) . '"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></button>'
        . '<button type="button" data-vb-move="down" title="' . cms_h(tr('เลื่อนลง', 'Move down')) . '" aria-label="' . cms_h(tr('เลื่อนลง', 'Move down')) . '"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i></button>'
        . '</div>'
        . '<button type="button" class="admin-btn admin-btn-secondary" data-vb-remove title="' . cms_h(tr('ลบบล็อกนี้', 'Remove this block')) . '">'
        . '<i class="fa-solid fa-trash" aria-hidden="true"></i></button>'
        . '</div>'
        . '<div class="vb-properties" data-vb-properties>' . cms_block_fields($type, $config, (string)$index, $media, $forms) . '</div>'
        . '</fieldset>';
}

$adminId = (int)$adminId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['admin_csrf'], is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '')) {
        http_response_code(403);
        exit('คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }

    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $pageId = (int)($_POST['page_id'] ?? 0);

    try {
        switch ($action) {
            case 'create_page':
                $newId = visual_builder_page_create(
                    $conn,
                    is_string($_POST['title'] ?? null) ? $_POST['title'] : '',
                    is_string($_POST['slug'] ?? null) ? $_POST['slug'] : '',
                    $adminId
                );
                cms_redirect('admin_pages.php?id=' . $newId . '&created=1');

            case 'save_draft':
            case 'publish':
                if ($pageId < 1) {
                    throw new InvalidArgumentException('Page ID is required');
                }
                $blocks = visual_builder_validate_posted_blocks(cms_normalize_posted_blocks($_POST['blocks'] ?? null));
                if ($action === 'publish') {
                    form_builder_require_published_references($conn, $blocks);
                }
                visual_builder_page_save_draft(
                    $conn,
                    $pageId,
                    is_string($_POST['title'] ?? null) ? $_POST['title'] : '',
                    is_string($_POST['slug'] ?? null) ? $_POST['slug'] : '',
                    $blocks,
                    $adminId
                );
                if ($action === 'publish') {
                    visual_builder_page_publish($conn, $pageId, $adminId);
                }
                cms_redirect('admin_pages.php?id=' . $pageId . '&' . ($action === 'publish' ? 'published=1' : 'saved=1'));

            case 'unpublish_page':
                visual_builder_page_unpublish($conn, $pageId, $adminId);
                cms_redirect('admin_pages.php?id=' . $pageId . '&unpublished=1');

            case 'revert_draft':
                visual_builder_page_revert_draft($conn, $pageId, $adminId);
                cms_redirect('admin_pages.php?id=' . $pageId . '&reverted=1');

            case 'restore_revision':
                visual_builder_page_restore_revision($conn, $pageId, (int)($_POST['revision_id'] ?? 0), $adminId);
                cms_redirect('admin_pages.php?id=' . $pageId . '&revision_restored=1');

            case 'delete_page':
                visual_builder_page_delete($conn, $pageId);
                cms_redirect('admin_pages.php?deleted=1');

            default:
                throw new InvalidArgumentException('Unknown page action');
        }
    } catch (InvalidArgumentException $e) {
        $_SESSION['cms_pages_error'] = tr('บันทึกไม่สำเร็จ: ', 'Could not save: ') . $e->getMessage();
        error_log('admin_pages validation: ' . $e->getMessage());
        cms_redirect('admin_pages.php' . ($pageId > 0 ? '?id=' . $pageId : ''));
    } catch (Throwable $e) {
        $_SESSION['cms_pages_error'] = tr('บันทึกไม่สำเร็จ กรุณาลองใหม่', 'Could not save changes. Please try again.');
        error_log('admin_pages save: ' . $e->getMessage());
        cms_redirect('admin_pages.php' . ($pageId > 0 ? '?id=' . $pageId : ''));
    }
}

$errorMessage = is_string($_SESSION['cms_pages_error'] ?? null) ? $_SESSION['cms_pages_error'] : '';
unset($_SESSION['cms_pages_error']);

$pageId = (int)($_GET['id'] ?? 0);
$page = null;
$blocks = [];
$publishedCount = 0;
$revisions = [];
$media = [];
$availableForms = [];
$formBuilderReady = false;
$listError = '';

try {
    if ($pageId > 0) {
        $media = db_all($conn, 'SELECT id, title FROM site_media ORDER BY created_at DESC, id DESC LIMIT 200');
        $page = visual_builder_page_by_id($conn, $pageId);
        if ($page !== null) {
            $blocks = visual_builder_load_blocks($conn, $pageId, 'draft');
            $publishedCount = visual_builder_page_block_count($conn, $pageId, 'published');
            $revisions = visual_builder_page_revisions($conn, $pageId, 12);
        }
    }
} catch (Throwable $e) {
    $listError = tr('อ่านข้อมูลหน้าเว็บไม่ได้ กรุณาตรวจสอบว่าตาราง site_pages / site_blocks ถูกสร้างแล้ว', 'Could not read pages. Check that site_pages / site_blocks exist.');
    error_log('admin_pages load: ' . $e->getMessage());
}

if ($pageId > 0) {
    try {
        foreach (db_all($conn, 'SELECT id, form_key, draft_json, status FROM site_forms ORDER BY updated_at DESC, id DESC') as $formRow) {
            $definition = form_builder_definition_from_json((string)$formRow['draft_json']);
            $availableForms[] = [
                'id' => (int)$formRow['id'],
                'form_key' => (string)$formRow['form_key'],
                'title' => $definition['title'],
                'status' => (string)$formRow['status'],
            ];
        }
        $formBuilderReady = true;
    } catch (Throwable $e) {
        error_log('admin_pages form list unavailable: ' . $e->getMessage());
    }
}

$pages = [];
if ($pageId < 1 && $listError === '') {
    try {
        $pages = visual_builder_pages_all($conn);
    } catch (Throwable $e) {
        $listError = tr('อ่านรายการหน้าเว็บไม่ได้', 'Could not load the page list.');
        error_log('admin_pages list: ' . $e->getMessage());
    }
}

$assetVersion = static fn(string $path): int => (int)@filemtime(__DIR__ . '/../' . $path);
$admin_extra_head = '<link rel="stylesheet" href="../assets/css/visual-builder.css?v=' . $assetVersion('assets/css/visual-builder.css') . '">'
    . '<script src="../assets/js/visual-builder.js?v=' . $assetVersion('assets/js/visual-builder.js') . '" defer></script>'
    . '<script src="../assets/js/cms-pages.js?v=' . $assetVersion('assets/js/cms-pages.js') . '" defer></script>';
require_once __DIR__ . '/admin_header.php';
?>

<div class="admin-page-head">
    <nav class="admin-crumb">
        <a href="admin_dashboard.php"><?= tr('แดชบอร์ด', 'Dashboard') ?></a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <?php if ($page !== null): ?>
            <a href="admin_pages.php"><?= tr('หน้าเว็บ (CMS)', 'Web pages (CMS)') ?></a>
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            <span><?= cms_h($page['title']) ?></span>
        <?php else: ?>
            <span><?= tr('หน้าเว็บ (CMS)', 'Web pages (CMS)') ?></span>
        <?php endif; ?>
    </nav>
    <h1 class="admin-page-title"><?= $page !== null ? cms_h($page['title']) : tr('หน้าเว็บ (CMS)', 'Web pages (CMS)') ?></h1>
    <p class="admin-page-sub">
        <?= $page !== null
            ? tr('แก้เนื้อหาเป็นบล็อก บันทึกเป็นฉบับร่างแล้วเผยแพร่เมื่อพร้อม', 'Edit block-based content, save a draft, then publish when ready.')
            : tr('สร้างหน้าเนื้อหาเพิ่มเติม เช่น กฎระเบียบ ติดต่อ หรือคำแนะนำ โดยไม่ต้องเขียนโค้ด', 'Create extra content pages such as rules, contact, or guides without writing code.') ?>
    </p>
</div>

<?php if ($errorMessage !== ''): ?>
    <div class="vb-studio-message vb-studio-message--error" role="alert"><?= cms_h($errorMessage) ?></div>
<?php elseif (isset($_GET['created'])): ?>
    <div class="vb-studio-message vb-studio-message--success" role="status"><?= tr('สร้างหน้าเว็บแล้ว เริ่มแก้ไขฉบับร่างได้เลย', 'Page created. Start editing the draft.') ?></div>
<?php elseif (isset($_GET['saved'])): ?>
    <div class="vb-studio-message vb-studio-message--success" role="status"><?= tr('บันทึกฉบับร่างแล้ว', 'Draft saved') ?></div>
<?php elseif (isset($_GET['published'])): ?>
    <div class="vb-studio-message vb-studio-message--success" role="status"><?= tr('เผยแพร่หน้าเว็บแล้ว', 'Page published') ?></div>
<?php elseif (isset($_GET['unpublished'])): ?>
    <div class="vb-studio-message vb-studio-message--success" role="status"><?= tr('เปลี่ยนกลับเป็นฉบับร่างแล้ว หน้าเว็บจะไม่แสดงต่อสาธารณะ', 'Unpublished. The page is no longer public.') ?></div>
<?php elseif (isset($_GET['reverted'])): ?>
    <div class="vb-studio-message vb-studio-message--success" role="status"><?= tr('คัดลอกฉบับเผยแพร่กลับมาเป็นฉบับร่างแล้ว', 'Published content copied back to the draft.') ?></div>
<?php elseif (isset($_GET['revision_restored'])): ?>
    <div class="vb-studio-message vb-studio-message--success" role="status"><?= tr('กู้คืนฉบับแก้ไขเป็นร่างแล้ว', 'Revision restored to draft.') ?></div>
<?php elseif (isset($_GET['deleted'])): ?>
    <div class="vb-studio-message vb-studio-message--success" role="status"><?= tr('ลบหน้าเว็บแล้ว', 'Page deleted') ?></div>
<?php endif; ?>

<?php if ($listError !== ''): ?>
    <div class="vb-note" role="alert"><?= cms_h($listError) ?></div>
<?php elseif ($pageId > 0 && $page === null): ?>
    <div class="vb-empty"><?= tr('ไม่พบหน้าเว็บที่ต้องการแก้ไข', 'The requested page was not found.') ?> · <a href="admin_pages.php"><?= tr('กลับไปรายการ', 'Back to list') ?></a></div>
<?php elseif ($page === null): ?>

    <section class="vb-form" aria-labelledby="cms-new-title">
        <div class="vb-list-head">
            <div>
                <h2 id="cms-new-title"><?= tr('สร้างหน้าใหม่', 'New page') ?></h2>
                <p><?= tr('ตั้งชื่อและ slug สำหรับ URL เช่น page.php?slug=rules', 'Set a title and slug for the URL, e.g. page.php?slug=rules') ?></p>
            </div>
        </div>
        <form method="post" action="admin_pages.php" class="vb-page-properties">
            <input type="hidden" name="csrf" value="<?= cms_h($_SESSION['admin_csrf']) ?>">
            <input type="hidden" name="action" value="create_page">
            <label class="vb-field">
                <span><?= tr('ชื่อหน้า', 'Page title') ?></span>
                <input type="text" name="title" maxlength="200" required placeholder="<?= cms_h(tr('เช่น กฎระเบียบการใช้งาน', 'e.g. Lab rules')) ?>">
            </label>
            <label class="vb-field">
                <span>Slug</span>
                <input type="text" name="slug" maxlength="80" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="rules">
                <small><?= tr('ใช้ a–z, 0–9 และขีดกลาง (-) เท่านั้น', 'Lowercase a–z, 0–9, and hyphens only') ?></small>
            </label>
            <div class="vb-form-actions">
                <button type="submit" class="admin-btn"><?= tr('สร้างหน้า', 'Create page') ?></button>
            </div>
        </form>
    </section>

    <section class="vb-editor-head" aria-labelledby="cms-list-title">
        <div>
            <h2 id="cms-list-title"><?= tr('หน้าเว็บทั้งหมด', 'All pages') ?></h2>
            <p><?= tr('ฉบับร่างแก้ได้ตลอด ฉบับเผยแพร่คือสิ่งที่ผู้เยี่ยมชมเห็น', 'Drafts can be edited anytime; the published version is what visitors see.') ?></p>
        </div>
        <span class="vb-status"><?= count($pages) ?> <?= tr('หน้า', 'pages') ?></span>
    </section>

    <?php if ($pages === []): ?>
        <div class="vb-empty"><?= tr('ยังไม่มีหน้าเว็บเนื้อหา สร้างหน้าแรกได้จากฟอร์มด้านบน', 'No content pages yet. Create the first one above.') ?></div>
    <?php else: ?>
        <div class="vb-pages-table-wrap">
            <table class="vb-pages-table">
                <thead>
                    <tr>
                        <th><?= tr('หน้า', 'Page') ?></th>
                        <th><?= tr('สถานะ', 'Status') ?></th>
                        <th><?= tr('บล็อก', 'Blocks') ?></th>
                        <th><?= tr('แก้ไขล่าสุด', 'Last edited') ?></th>
                        <th><?= tr('จัดการ', 'Actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pages as $item): ?>
                        <tr>
                            <td>
                                <strong><?= cms_h($item['title']) ?></strong><br>
                                <code>page.php?slug=<?= cms_h($item['slug']) ?></code>
                            </td>
                            <td>
                                <span class="vb-status<?= $item['status'] === 'published' ? ' vb-status--published' : '' ?>">
                                    <?= $item['status'] === 'published' ? tr('เผยแพร่', 'Published') : tr('ฉบับร่าง', 'Draft') ?>
                                </span>
                            </td>
                            <td>
                                <?= tr('ร่าง', 'Draft') ?> <?= (int)$item['draft_blocks'] ?>
                                · <?= tr('เผยแพร่', 'Published') ?> <?= (int)$item['published_blocks'] ?>
                            </td>
                            <td>
                                <?= cms_date((string)$item['updated_at']) ?><br>
                                <small><?= cms_h(cms_author($item)) ?></small>
                            </td>
                            <td>
                                <div class="vb-row-actions">
                                    <a class="admin-btn admin-btn-secondary" href="admin_pages.php?id=<?= (int)$item['id'] ?>"><?= tr('แก้ไข', 'Edit') ?></a>
                                    <?php if ($item['status'] === 'published'): ?>
                                        <a class="admin-btn admin-btn-secondary" href="../page.php?slug=<?= rawurlencode((string)$item['slug']) ?>" target="_blank" rel="noopener"><?= tr('เปิดหน้า', 'View') ?></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php else: ?>

    <?php
    $isPublished = $page['status'] === 'published';
    $draftCount = count($blocks);
    $previewUrl = '../page.php?slug=' . rawurlencode((string)$page['slug']) . '&preview=1';
    $publicUrl = '../page.php?slug=' . rawurlencode((string)$page['slug']);
    ?>

    <section class="vb-editor-head">
        <div>
            <h2><?= tr('สถานะหน้าเว็บ', 'Page status') ?></h2>
            <p>
                <span class="vb-status<?= $isPublished ? ' vb-status--published' : '' ?>"><?= $isPublished ? tr('เผยแพร่', 'Published') : tr('ฉบับร่าง', 'Draft') ?></span>
                · <?= tr('ฉบับร่าง', 'Draft') ?> <?= $draftCount ?> <?= tr('บล็อก', 'blocks') ?>
                · <?= tr('ฉบับเผยแพร่', 'Published') ?> <?= $publishedCount ?> <?= tr('บล็อก', 'blocks') ?>
            </p>
        </div>
        <div class="vb-row-actions">
            <?php if ($isPublished): ?>
                <a class="admin-btn admin-btn-secondary" href="<?= cms_h($publicUrl) ?>" target="_blank" rel="noopener"><?= tr('เปิดฉบับเผยแพร่', 'Open published page') ?></a>
            <?php endif; ?>
            <a class="admin-btn admin-btn-secondary" href="admin_forms.php"><?= tr('Form Builder', 'Form Builder') ?></a>
            <a class="admin-btn admin-btn-secondary" href="<?= cms_h($previewUrl) ?>" target="_blank" rel="noopener"><?= tr('ดูตัวอย่างฉบับร่าง', 'Preview draft') ?></a>
        </div>
    </section>

    <div class="vb-pages-actions">
        <?php if ($isPublished): ?>
            <form method="post" action="admin_pages.php?id=<?= (int)$page['id'] ?>">
                <input type="hidden" name="csrf" value="<?= cms_h($_SESSION['admin_csrf']) ?>">
                <input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>">
                <button type="submit" name="action" value="unpublish_page" class="admin-btn admin-btn-secondary" data-cms-confirm="<?= cms_h(tr('ยกเลิกการเผยแพร่หน้าเว็บนี้?', 'Unpublish this page?')) ?>"><?= tr('ยกเลิกการเผยแพร่', 'Unpublish') ?></button>
            </form>
            <form method="post" action="admin_pages.php?id=<?= (int)$page['id'] ?>">
                <input type="hidden" name="csrf" value="<?= cms_h($_SESSION['admin_csrf']) ?>">
                <input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>">
                <button type="submit" name="action" value="revert_draft" class="admin-btn admin-btn-secondary" data-cms-confirm="<?= cms_h(tr('คัดลอกฉบับเผยแพร่ทับฉบับร่าง? การแก้ไขร่างที่ยังไม่เผยแพร่จะหายไป', 'Replace the draft with the published version? Unpublished draft edits will be lost.')) ?>"><?= tr('ย้อนร่างจากฉบับเผยแพร่', 'Revert draft from published') ?></button>
            </form>
        <?php endif; ?>
        <form method="post" action="admin_pages.php?id=<?= (int)$page['id'] ?>" class="vb-pages-actions__danger">
            <input type="hidden" name="csrf" value="<?= cms_h($_SESSION['admin_csrf']) ?>">
            <input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>">
            <button type="submit" name="action" value="delete_page" class="admin-btn admin-btn-danger" data-cms-confirm="<?= cms_h(tr('ลบหน้าเว็บนี้ถาวร? ฉบับร่างและฉบับเผยแพร่จะถูกลบทั้งหมด', 'Delete this page permanently? Both draft and published content will be removed.')) ?>"><?= tr('ลบหน้าเว็บ', 'Delete page') ?></button>
        </form>
    </div>

    <div class="vb-pages-layout">
        <div class="vb-pages-editor">
            <form method="post" action="admin_pages.php?id=<?= (int)$page['id'] ?>" id="vbPageForm" data-vb-editor data-max-blocks="<?= VISUAL_BUILDER_MAX_BLOCKS ?>">
                <input type="hidden" name="csrf" value="<?= cms_h($_SESSION['admin_csrf']) ?>">
                <input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>">

                <div class="vb-page-properties">
                    <label class="vb-field">
                        <span><?= tr('ชื่อหน้า', 'Page title') ?></span>
                        <input type="text" name="title" maxlength="200" required value="<?= cms_h($page['title']) ?>">
                    </label>
                    <label class="vb-field">
                        <span>Slug</span>
                        <input type="text" name="slug" maxlength="80" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*" value="<?= cms_h($page['slug']) ?>">
                        <small><?= tr('เปลี่ยน slug แล้วลิงก์เดิมจะใช้ไม่ได้', 'Changing the slug breaks existing links.') ?></small>
                    </label>
                </div>

                <div class="vb-panel-head">
                    <h2><?= tr('บล็อกเนื้อหา', 'Content blocks') ?></h2>
                    <span><?= tr('สูงสุด', 'Up to') ?> <?= VISUAL_BUILDER_MAX_BLOCKS ?> <?= tr('บล็อก', 'blocks') ?></span>
                </div>

                <div class="vb-editor-tools" role="toolbar" aria-label="<?= tr('เครื่องมือแก้ไขบล็อก', 'Block editing tools') ?>">
                    <div class="vb-add-row">
                    <label class="vb-field vb-type-field">
                        <span class="sr-only"><?= tr('ชนิดบล็อกใหม่', 'New block type') ?></span>
                        <select id="vb-add-type">
                            <?php foreach (VISUAL_BUILDER_COMPONENTS as $key => $component): ?>
                                <option value="<?= cms_h($key) ?>"><?= cms_h(tr($component['label_th'], $component['label_en'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="button" class="admin-btn" data-vb-add>
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        <?= tr('เพิ่มบล็อก', 'Add block') ?>
                    </button>
                    </div>
                    <div class="vb-history-tools">
                        <button type="button" class="admin-btn admin-btn-secondary" data-vb-undo disabled><?= tr('เลิกทำ', 'Undo') ?></button>
                        <button type="button" class="admin-btn admin-btn-secondary" data-vb-redo disabled><?= tr('ทำซ้ำ', 'Redo') ?></button>
                        <button type="button" class="admin-btn admin-btn-secondary" data-vb-copy disabled><?= tr('คัดลอกบล็อก', 'Copy block') ?></button>
                        <button type="button" class="admin-btn admin-btn-secondary" data-vb-paste><?= tr('วางบล็อก', 'Paste block') ?></button>
                    </div>
                </div>

                <section class="vb-live-canvas-panel" aria-labelledby="vb-live-canvas-title">
                    <div class="vb-panel-head">
                        <h3 id="vb-live-canvas-title"><?= tr('ตัวอย่างสด', 'Live canvas') ?></h3>
                        <span><?= tr('อัปเดตตามค่าที่แก้ในฟอร์ม', 'Updates as you edit') ?></span>
                    </div>
                    <div class="vb-live-canvas" id="vbLiveCanvas" data-page-title="<?= cms_h((string)$page['title']) ?>" data-page-base="../"></div>
                </section>

                <div class="vb-block-list" id="vbBlockList">
                    <?php if ($blocks === []): ?>
                        <div class="vb-empty"><?= tr('ยังไม่มีบล็อก เพิ่มบล็อกแรกด้านบน', 'No blocks yet. Add the first block above.') ?></div>
                    <?php endif; ?>
                    <?php foreach ($blocks as $index => $block): ?>
                        <?= cms_block_card($block['type'], $block['config'], $index, $block['is_visible'], $media, $availableForms) ?>
                    <?php endforeach; ?>
                </div>

                <div class="vb-form-actions">
                    <span class="vb-help"><?= tr('บันทึกร่างก่อน แล้วเผยแพร่เมื่อพร้อม', 'Save a draft first, then publish when ready.') ?></span>
                    <button type="submit" name="action" value="save_draft" class="admin-btn admin-btn-secondary"><?= tr('บันทึกร่าง', 'Save draft') ?></button>
                    <button type="submit" name="action" value="publish" class="admin-btn" data-cms-confirm="<?= cms_h(tr('บันทึกและเผยแพร่หน้าเว็บนี้?', 'Save and publish this page?')) ?>"><?= tr('บันทึกและเผยแพร่', 'Save and publish') ?></button>
                </div>
            </form>
        </div>

        <aside class="vb-preview-panel" aria-labelledby="cms-preview-title">
            <div class="vb-preview-bar">
                <strong id="cms-preview-title"><?= tr('ตัวอย่างฉบับร่าง', 'Draft preview') ?></strong>
                <a href="<?= cms_h($previewUrl) ?>" target="_blank" rel="noopener"><?= tr('เปิดเต็มหน้า', 'Open full page') ?></a>
            </div>
            <div class="vb-preview-viewports" role="group" aria-label="<?= tr('ขนาดตัวอย่าง', 'Preview size') ?>">
                <button type="button" data-vb-viewport="desktop" aria-pressed="true"><?= tr('เดสก์ท็อป', 'Desktop') ?></button>
                <button type="button" data-vb-viewport="tablet" aria-pressed="false"><?= tr('แท็บเล็ต', 'Tablet') ?></button>
                <button type="button" data-vb-viewport="mobile" aria-pressed="false"><?= tr('มือถือ', 'Mobile') ?></button>
            </div>
            <div class="vb-preview-frame" data-vb-preview-frame>
                <iframe id="vbPagePreview" src="<?= cms_h($previewUrl) ?>" title="<?= tr('ตัวอย่างหน้าเว็บฉบับร่าง', 'Draft page preview') ?>" loading="lazy"></iframe>
            </div>
            <p class="vb-help"><?= tr('ตัวอย่างแสดงข้อมูลที่บันทึกล่าสุด · ต้องบันทึกร่างก่อนจึงจะเห็นการแก้ไขใหม่', 'The preview shows the last saved draft. Save the draft to see new edits.') ?></p>
        </aside>
    </div>

    <template id="vb-block-template">
        <?= cms_block_card('heading', visual_builder_default_config('heading', (string)$page['title']), '__INDEX__', true, $media, $availableForms) ?>
    </template>
    <?php foreach (VISUAL_BUILDER_COMPONENTS as $key => $component): ?>
        <template id="vb-fields-<?= cms_h($key) ?>">
            <?= cms_block_fields($key, visual_builder_default_config($key, (string)$page['title']), '__INDEX__', $media, $availableForms) ?>
        </template>
    <?php endforeach; ?>

    <section class="vb-revisions" aria-labelledby="cms-revisions-title">
        <h3 id="cms-revisions-title"><?= tr('ประวัติการแก้ไข', 'Revision history') ?></h3>
        <?php if ($revisions === []): ?>
            <p class="vb-help"><?= tr('ยังไม่มีประวัติ', 'No revisions yet.') ?></p>
        <?php else: ?>
            <ol>
                <?php foreach ($revisions as $revision): ?>
                    <li>
                        <?= cms_date((string)$revision['created_at']) ?>
                        · <?= cms_h(cms_author($revision)) ?>
                        · <?= cms_h($revision['note'] !== '' ? $revision['note'] : tr('บันทึก', 'Saved')) ?>
                        <form method="post" action="admin_pages.php?id=<?= (int)$page['id'] ?>" class="vb-revision-restore-form">
                            <input type="hidden" name="csrf" value="<?= cms_h($_SESSION['admin_csrf']) ?>">
                            <input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>">
                            <input type="hidden" name="revision_id" value="<?= (int)$revision['id'] ?>">
                            <button type="submit" name="action" value="restore_revision" class="admin-btn admin-btn-secondary" data-cms-confirm="<?= cms_h(tr('กู้คืน revision นี้เป็นฉบับร่างหรือไม่? ร่างปัจจุบันจะถูกแทนที่', 'Restore this revision as the draft? The current draft will be replaced.')) ?>"><?= tr('กู้คืนเป็นร่าง', 'Restore to draft') ?></button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

<?php endif; ?>

      </div>
<?php $footerBase = '../'; $footerVariant = 'app'; require __DIR__ . '/../includes/site_footer.php'; ?>
   </div>
</body>
</html>
