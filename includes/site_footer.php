<?php
declare(strict_types=1);

/**
 * REPS · includes/site_footer.php — footer อย่างเป็นทางการ ใช้ร่วมกันทุกหน้า
 *   $footerBase    = '' (หน้าในราก) หรือ '../' (หน้าใน admin/ staff/)
 *   $footerVariant = 'light' (ค่าเริ่มต้น) | 'dark' (หน้า login/สมัครสมาชิก) | 'app' (ในกรอบ admin)
 * ข้อมูลทั้งหมดมาจาก system_settings (แก้ได้ในหน้า ตั้งค่าระบบ) ไม่ฝังข้อความในหน้า
 */

require_once __DIR__ . '/site_config.php';
require_once __DIR__ . '/site_custom.php';
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/i18n.php';

$footerBase = $footerBase ?? '';
$footerVariant = $footerVariant ?? 'light';
$footerSettings = isset($conn) && $conn instanceof mysqli ? app_get_settings($conn) : [];
$footerEsc = static fn(?string $v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$footerSite = trim((string)($footerSettings['site_name'] ?? '')) ?: 'REPS - ระบบแจ้งซ่อมอุปกรณ์คอมพิวเตอร์';
$footerTagline = trim((string)($footerSettings['site_tagline'] ?? ''));
$footerOrg = trim((string)($footerSettings['org_name'] ?? ''));
$footerUnit = trim((string)($footerSettings['org_unit'] ?? '')) ?: 'แผนกเทคโนโลยีธุรกิจดิจิทัล · ห้องปฏิบัติการกลางคอมพิวเตอร์';
$footerHours = trim((string)($footerSettings['office_hours'] ?? '')) ?: 'จันทร์–ศุกร์ 08:30–16:30 น.';
$footerContact = trim((string)($footerSettings['org_contact'] ?? ''));
$footerNote = trim((string)($footerSettings['footer_text'] ?? ''));
$footerLinks = isset($conn) && $conn instanceof mysqli ? site_footer_links($conn) : [];
$footerYear = (int)date('Y') + 543;
?>
<footer class="reps-site-footer reps-site-footer--<?= $footerEsc($footerVariant) ?>" role="contentinfo">
    <div class="reps-site-footer__inner">
        <?php if ($footerVariant === 'dark'): ?>
            <?php /* หน้า login/สมัครสมาชิก: แถวเดียว เรียบตามพื้นหลังเข้ม */ ?>
            <div class="reps-site-footer__compact">
                <span>© <?= $footerYear ?> <?= $footerEsc($footerOrg !== '' ? $footerOrg : $footerUnit) ?></span>
                <?= empty($GLOBALS['repsLangSwitchShown']) ? lang_switch_html($footerBase) : '' ?>
                <span class="reps-site-footer__dev"><span class="sr-only">ผู้พัฒนา </span><?= app_icon('code', 'reps-site-footer__dev-icon') ?> GOD &amp; LUN</span>
            </div>
        <?php else: ?>
            <div class="reps-site-footer__main">
                <div class="reps-site-footer__brand">
                    <div class="reps-site-footer__name"><?= $footerEsc($footerSite) ?></div>
                    <?php if ($footerTagline !== ''): ?><div class="reps-site-footer__tagline"><?= $footerEsc($footerTagline) ?></div><?php endif; ?>
                    <?php if ($footerOrg !== ''): ?><div><?= $footerEsc($footerOrg) ?></div><?php endif; ?>
                    <div><?= $footerEsc($footerUnit) ?></div>
                    <?php if ($footerNote !== ''): ?><div class="reps-site-footer__note"><?= $footerEsc($footerNote) ?></div><?php endif; ?>
                </div>
                <dl class="reps-site-footer__facts">
                    <div>
                        <dt>เวลาทำการ</dt>
                        <dd><?= $footerEsc($footerHours) ?></dd>
                    </div>
                    <?php if ($footerContact !== ''): ?>
                        <div>
                            <dt>ติดต่อ</dt>
                            <dd><?= $footerEsc($footerContact) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($footerLinks): ?>
                        <div>
                            <dt><?= tr('ลิงก์', 'Links') ?></dt>
                            <dd class="reps-site-footer__links">
                                <?php foreach ($footerLinks as $footerLink):
                                    $footerExternal = preg_match('#^https?://#i', $footerLink['url']) === 1; ?>
                                    <a class="reps-site-footer__link" href="<?= $footerEsc(site_link_href($footerLink['url'], $footerBase)) ?>"<?= $footerExternal ? ' target="_blank" rel="noopener"' : '' ?>><?= $footerEsc($footerLink['label']) ?></a>
                                <?php endforeach; ?>
                            </dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </div>
            <div class="reps-site-footer__legal">
                <span>© <?= $footerYear ?> <?= $footerEsc($footerOrg !== '' ? $footerOrg : $footerUnit) ?> · สงวนลิขสิทธิ์</span>
                <?= empty($GLOBALS['repsLangSwitchShown']) ? lang_switch_html($footerBase) : '' ?>
                <span class="reps-site-footer__dev"><span class="sr-only">ผู้พัฒนา </span><?= app_icon('code', 'reps-site-footer__dev-icon') ?> GOD &amp; LUN</span>
            </div>
        <?php endif; ?>
    </div>
</footer>
<script src="<?= $footerEsc($footerBase) ?>assets/js/table-pager.js?v=1" defer></script>
<script src="<?= $footerEsc($footerBase) ?>assets/js/image-viewer.js?v=<?= (int)@filemtime(dirname(__DIR__) . '/assets/js/image-viewer.js') ?>" defer></script>
<script src="<?= $footerEsc($footerBase) ?>assets/js/photo-input.js?v=<?= (int)@filemtime(dirname(__DIR__) . '/assets/js/photo-input.js') ?>" defer></script>
