<?php
declare(strict_types=1);

require_once __DIR__ . '/db_helpers.php';
require_once __DIR__ . '/site_custom.php';

const VISUAL_BUILDER_MAX_BLOCKS = 40;

const VISUAL_BUILDER_ANIMATIONS = [
    'none' => ['label_th' => 'ไม่ใช้', 'label_en' => 'None'],
    'fade-in' => ['label_th' => 'ค่อย ๆ ปรากฏ', 'label_en' => 'Fade in'],
    'fade-up' => ['label_th' => 'เลื่อนขึ้นพร้อมปรากฏ', 'label_en' => 'Fade up'],
    'slide-left' => ['label_th' => 'เลื่อนจากขวา', 'label_en' => 'Slide from right'],
    'slide-right' => ['label_th' => 'เลื่อนจากซ้าย', 'label_en' => 'Slide from left'],
    'zoom-in' => ['label_th' => 'ขยายเข้า', 'label_en' => 'Zoom in'],
];

const VISUAL_BUILDER_HOME_SECTIONS = [
    'hero' => ['label_th' => 'ส่วนแนะนำหลัก', 'label_en' => 'Hero section'],
    'services' => ['label_th' => 'บริการ', 'label_en' => 'Services'],
    'how' => ['label_th' => 'ขั้นตอน', 'label_en' => 'How it works'],
    'showcase' => ['label_th' => 'ฟีเจอร์', 'label_en' => 'Features'],
    'faq' => ['label_th' => 'คำถามที่พบบ่อย', 'label_en' => 'FAQ'],
    'contact' => ['label_th' => 'ติดต่อ', 'label_en' => 'Contact'],
];

const VISUAL_BUILDER_HOME_TEXT_TARGETS = [
    'navigation.services' => ['label_th' => 'เมนูบริการ', 'label_en' => 'Services navigation', 'max' => 80],
    'navigation.how' => ['label_th' => 'เมนูขั้นตอน', 'label_en' => 'Process navigation', 'max' => 80],
    'navigation.features' => ['label_th' => 'เมนูฟีเจอร์', 'label_en' => 'Features navigation', 'max' => 80],
    'navigation.faq' => ['label_th' => 'เมนูคำถาม', 'label_en' => 'FAQ navigation', 'max' => 80],
    'navigation.contact' => ['label_th' => 'เมนูติดต่อ', 'label_en' => 'Contact navigation', 'max' => 80],
    'hero.title_first' => ['label_th' => 'หัวข้อบรรทัดแรก', 'label_en' => 'First heading line', 'max' => 100],
    'hero.title_second' => ['label_th' => 'หัวข้อบรรทัดที่สอง', 'label_en' => 'Second heading line', 'max' => 100],
    'hero.intro' => ['label_th' => 'คำแนะนำหน้าแรก', 'label_en' => 'Homepage introduction', 'max' => 500, 'multiline' => true, 'setting' => 'homepage_intro'],
    'hero.primary_cta' => ['label_th' => 'ปุ่มแจ้งซ่อม', 'label_en' => 'Repair button', 'max' => 80],
    'hero.secondary_cta' => ['label_th' => 'ปุ่มดูขั้นตอน', 'label_en' => 'Process button', 'max' => 80],
    'hero.hours_label' => ['label_th' => 'ป้ายเวลาทำการ', 'label_en' => 'Office hours label', 'max' => 80],
    'hero.hours' => ['label_th' => 'เวลาทำการ', 'label_en' => 'Office hours', 'max' => 120, 'setting' => 'office_hours'],
    'hero.announcement' => ['label_th' => 'ประกาศหน้าแรก', 'label_en' => 'Homepage announcement', 'max' => 500, 'multiline' => true, 'setting' => 'homepage_announcement'],
    'services.title' => ['label_th' => 'หัวข้อบริการ', 'label_en' => 'Services heading', 'max' => 120],
    'services.description' => ['label_th' => 'คำอธิบายบริการ', 'label_en' => 'Services description', 'max' => 300, 'multiline' => true],
    'services.computer.title' => ['label_th' => 'หัวข้อคอมพิวเตอร์', 'label_en' => 'Computer service heading', 'max' => 120],
    'services.computer.description' => ['label_th' => 'รายละเอียดคอมพิวเตอร์', 'label_en' => 'Computer service description', 'max' => 300, 'multiline' => true],
    'services.software.title' => ['label_th' => 'หัวข้อซอฟต์แวร์', 'label_en' => 'Software service heading', 'max' => 120],
    'services.software.description' => ['label_th' => 'รายละเอียดซอฟต์แวร์', 'label_en' => 'Software service description', 'max' => 300, 'multiline' => true],
    'services.peripherals.title' => ['label_th' => 'หัวข้ออุปกรณ์ต่อพ่วง', 'label_en' => 'Peripherals heading', 'max' => 120],
    'services.peripherals.description' => ['label_th' => 'รายละเอียดอุปกรณ์ต่อพ่วง', 'label_en' => 'Peripherals description', 'max' => 300, 'multiline' => true],
    'services.network.title' => ['label_th' => 'หัวข้อเครือข่าย', 'label_en' => 'Network service heading', 'max' => 120],
    'services.network.description' => ['label_th' => 'รายละเอียดเครือข่าย', 'label_en' => 'Network service description', 'max' => 300, 'multiline' => true],
    'how.title' => ['label_th' => 'หัวข้อขั้นตอน', 'label_en' => 'Process heading', 'max' => 120],
    'how.description' => ['label_th' => 'คำอธิบายขั้นตอน', 'label_en' => 'Process description', 'max' => 300, 'multiline' => true],
    'how.step1.title' => ['label_th' => 'ขั้นตอนที่ 1', 'label_en' => 'Step 1 heading', 'max' => 120],
    'how.step1.description' => ['label_th' => 'รายละเอียดขั้นตอนที่ 1', 'label_en' => 'Step 1 description', 'max' => 240, 'multiline' => true],
    'how.step2.title' => ['label_th' => 'ขั้นตอนที่ 2', 'label_en' => 'Step 2 heading', 'max' => 120],
    'how.step2.description' => ['label_th' => 'รายละเอียดขั้นตอนที่ 2', 'label_en' => 'Step 2 description', 'max' => 240, 'multiline' => true],
    'how.step3.title' => ['label_th' => 'ขั้นตอนที่ 3', 'label_en' => 'Step 3 heading', 'max' => 120],
    'how.step3.description' => ['label_th' => 'รายละเอียดขั้นตอนที่ 3', 'label_en' => 'Step 3 description', 'max' => 240, 'multiline' => true],
    'features.title' => ['label_th' => 'หัวข้อฟีเจอร์', 'label_en' => 'Features heading', 'max' => 180],
    'features.description' => ['label_th' => 'คำอธิบายฟีเจอร์', 'label_en' => 'Features description', 'max' => 400, 'multiline' => true],
    'features.card1.title' => ['label_th' => 'ฟีเจอร์: ติดตามสถานะ', 'label_en' => 'Feature: Track status', 'max' => 120],
    'features.card1.description' => ['label_th' => 'รายละเอียดฟีเจอร์: ติดตามสถานะ', 'label_en' => 'Feature details: Track status', 'max' => 300, 'multiline' => true],
    'features.card2.title' => ['label_th' => 'ฟีเจอร์: แจ้งเตือน', 'label_en' => 'Feature: Notifications', 'max' => 120],
    'features.card2.description' => ['label_th' => 'รายละเอียดฟีเจอร์: แจ้งเตือน', 'label_en' => 'Feature details: Notifications', 'max' => 300, 'multiline' => true],
    'features.card3.title' => ['label_th' => 'ฟีเจอร์: แดชบอร์ด', 'label_en' => 'Feature: Dashboards', 'max' => 120],
    'features.card3.description' => ['label_th' => 'รายละเอียดฟีเจอร์: แดชบอร์ด', 'label_en' => 'Feature details: Dashboards', 'max' => 300, 'multiline' => true],
    'features.card4.title' => ['label_th' => 'ฟีเจอร์: แนบรูป', 'label_en' => 'Feature: Attach images', 'max' => 120],
    'features.card4.description' => ['label_th' => 'รายละเอียดฟีเจอร์: แนบรูป', 'label_en' => 'Feature details: Attach images', 'max' => 300, 'multiline' => true],
    'features.card5.title' => ['label_th' => 'ฟีเจอร์: ประวัติครุภัณฑ์', 'label_en' => 'Feature: Equipment history', 'max' => 120],
    'features.card5.description' => ['label_th' => 'รายละเอียดฟีเจอร์: ประวัติครุภัณฑ์', 'label_en' => 'Feature details: Equipment history', 'max' => 300, 'multiline' => true],
    'faq.title' => ['label_th' => 'หัวข้อคำถามที่พบบ่อย', 'label_en' => 'FAQ heading', 'max' => 120],
    'faq.description' => ['label_th' => 'คำอธิบายคำถามที่พบบ่อย', 'label_en' => 'FAQ description', 'max' => 300, 'multiline' => true],
    'faq.question1' => ['label_th' => 'คำถามที่พบบ่อย 1', 'label_en' => 'FAQ question 1', 'max' => 240],
    'faq.question2' => ['label_th' => 'คำถามที่พบบ่อย 2', 'label_en' => 'FAQ question 2', 'max' => 240],
    'faq.question3' => ['label_th' => 'คำถามที่พบบ่อย 3', 'label_en' => 'FAQ question 3', 'max' => 240],
    'faq.question4' => ['label_th' => 'คำถามที่พบบ่อย 4', 'label_en' => 'FAQ question 4', 'max' => 240],
    'faq.question5' => ['label_th' => 'คำถามที่พบบ่อย 5', 'label_en' => 'FAQ question 5', 'max' => 240],
    'faq.question6' => ['label_th' => 'คำถามที่พบบ่อย 6', 'label_en' => 'FAQ question 6', 'max' => 240],
    'contact.title' => ['label_th' => 'หัวข้อติดต่อ', 'label_en' => 'Contact heading', 'max' => 120],
    'contact.description' => ['label_th' => 'คำอธิบายติดต่อ', 'label_en' => 'Contact description', 'max' => 300, 'multiline' => true],
    'contact.primary_cta' => ['label_th' => 'ปุ่มแจ้งซ่อมส่วนท้าย', 'label_en' => 'Footer repair button', 'max' => 100],
    'footer.site_name' => ['label_th' => 'ชื่อเว็บไซต์ส่วนท้าย', 'label_en' => 'Footer site name', 'max' => 100, 'setting' => 'site_name'],
    'footer.tagline' => ['label_th' => 'คำโปรยส่วนท้าย', 'label_en' => 'Footer tagline', 'max' => 200, 'setting' => 'site_tagline'],
    'footer.org_name' => ['label_th' => 'ชื่อหน่วยงาน', 'label_en' => 'Organization name', 'max' => 150, 'setting' => 'org_name'],
    'footer.org_unit' => ['label_th' => 'หน่วยงานที่ดูแล', 'label_en' => 'Responsible unit', 'max' => 200, 'setting' => 'org_unit'],
    'footer.hours' => ['label_th' => 'เวลาทำการส่วนท้าย', 'label_en' => 'Footer office hours', 'max' => 120, 'setting' => 'office_hours'],
    'footer.contact' => ['label_th' => 'ช่องทางติดต่อ', 'label_en' => 'Contact information', 'max' => 200, 'setting' => 'org_contact'],
    'footer.note' => ['label_th' => 'ข้อความเพิ่มเติมส่วนท้าย', 'label_en' => 'Footer note', 'max' => 300, 'multiline' => true, 'setting' => 'footer_text'],
];

/** Editable text-only targets on existing REPS screens. These targets never control actions or ticket data. */
const VISUAL_BUILDER_CONTENT_PAGES = [
    'login' => [
        'label_th' => 'เข้าสู่ระบบ', 'label_en' => 'Login', 'path' => '../login.php',
        'targets' => [
            'login.title' => ['label_th' => 'หัวข้อเข้าสู่ระบบ', 'label_en' => 'Login heading', 'max' => 100],
            'login.intro' => ['label_th' => 'คำแนะนำเข้าสู่ระบบ', 'label_en' => 'Login introduction', 'max' => 240],
        ],
    ],
    'register' => [
        'label_th' => 'สมัครสมาชิก', 'label_en' => 'Register', 'path' => '../register.php',
        'targets' => [
            'register.title' => ['label_th' => 'หัวข้อสมัครสมาชิก', 'label_en' => 'Registration heading', 'max' => 100],
            'register.intro' => ['label_th' => 'คำแนะนำสมัครสมาชิก', 'label_en' => 'Registration introduction', 'max' => 240],
        ],
    ],
    'user_dashboard' => [
        'label_th' => 'แดชบอร์ดผู้แจ้ง', 'label_en' => 'Requester dashboard', 'path' => '../dashboard.php',
        'targets' => [
            'user.greeting' => ['label_th' => 'คำทักทาย', 'label_en' => 'Greeting', 'max' => 100],
            'user.intro' => ['label_th' => 'คำอธิบายแดชบอร์ด', 'label_en' => 'Dashboard introduction', 'max' => 240],
            'user.tickets_title' => ['label_th' => 'หัวข้อใบงาน', 'label_en' => 'Ticket list heading', 'max' => 120],
            'user.tickets_intro' => ['label_th' => 'คำอธิบายรายการใบงาน', 'label_en' => 'Ticket list introduction', 'max' => 240],
        ],
    ],
    'staff_dashboard' => [
        'label_th' => 'แดชบอร์ดสตาฟ', 'label_en' => 'Staff dashboard', 'path' => '../staff/staff_dashboard.php',
        'targets' => [
            'staff.role_label' => ['label_th' => 'ป้ายกำกับบทบาทสตาฟ', 'label_en' => 'Staff role label', 'max' => 120],
            'staff.queue_title' => ['label_th' => 'หัวข้อคิวงาน', 'label_en' => 'Queue heading', 'max' => 120],
            'staff.active_title' => ['label_th' => 'หัวข้องานที่กำลังทำ', 'label_en' => 'Active work heading', 'max' => 120],
            'staff.guide' => ['label_th' => 'คำแนะนำขั้นตอนงาน', 'label_en' => 'Workflow introduction', 'max' => 240],
        ],
    ],
    'admin_dashboard' => [
        'label_th' => 'แดชบอร์ดผู้ดูแล', 'label_en' => 'Admin dashboard', 'path' => 'admin_dashboard.php',
        'targets' => [
            'admin.recent_title' => ['label_th' => 'หัวข้อคำขอล่าสุด', 'label_en' => 'Recent requests heading', 'max' => 120],
            'admin.recent_intro' => ['label_th' => 'คำอธิบายคำขอล่าสุด', 'label_en' => 'Recent requests introduction', 'max' => 240],
        ],
    ],
];

function visual_builder_content_page_data(array $settings, string $page): array
{
    if (!isset(VISUAL_BUILDER_CONTENT_PAGES[$page])) {
        throw new InvalidArgumentException('Unknown visual content page');
    }
    $raw = (string)($settings['visual_builder_content_' . $page] ?? '');
    if ($raw === '') {
        return [];
    }
    try {
        $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        error_log('Invalid Visual Builder content JSON for ' . $page . ': ' . $e->getMessage());
        return [];
    }
    return is_array($data) ? $data : [];
}

function visual_builder_content_text(array $settings, string $page, string $key, string $fallback, ?string $locale = null): string
{
    $target = VISUAL_BUILDER_CONTENT_PAGES[$page]['targets'][$key] ?? null;
    if ($target === null) {
        throw new InvalidArgumentException('Unknown visual content target');
    }
    $locale ??= function_exists('app_lang') ? app_lang() : 'th';
    $locale = $locale === 'en' ? 'en' : 'th';
    $data = visual_builder_content_page_data($settings, $page);
    $value = $data[$key][$locale] ?? null;
    if (!is_string($value)) {
        return $fallback;
    }
    try {
        return visual_builder_text_value($value, (int)$target['max']);
    } catch (InvalidArgumentException) {
        return $fallback;
    }
}

function visual_builder_content_target_attrs(bool $editMode, string $page, string $key): string
{
    if (!$editMode) {
        return '';
    }
    $pageData = VISUAL_BUILDER_CONTENT_PAGES[$page] ?? null;
    $target = $pageData['targets'][$key] ?? null;
    if ($pageData === null || $target === null) {
        throw new InvalidArgumentException('Unknown visual content target');
    }
    $label = function_exists('tr') ? tr($target['label_th'], $target['label_en']) : $target['label_en'];
    return ' data-vb-page="' . visual_builder_escape($page) . '"'
        . ' data-vb-key="' . visual_builder_escape($key) . '" data-vb-kind="text"'
        . ' data-vb-label="' . visual_builder_escape($label) . '" data-vb-max-length="' . (int)$target['max'] . '"';
}

const VISUAL_BUILDER_COMPONENTS = [
    'heading' => [
        'label_th' => 'หัวข้อ',
        'label_en' => 'Heading',
        'animation' => true,
        'fields' => [
            'text' => ['type' => 'text', 'label_th' => 'ข้อความ', 'label_en' => 'Text', 'max' => 200],
            'level' => ['type' => 'select', 'label_th' => 'ระดับหัวข้อ', 'label_en' => 'Heading level', 'options' => ['h2', 'h3']],
        ],
    ],
    'paragraph' => [
        'label_th' => 'ข้อความ',
        'label_en' => 'Paragraph',
        'animation' => true,
        'fields' => [
            'text' => ['type' => 'textarea', 'label_th' => 'ข้อความ', 'label_en' => 'Text', 'max' => 3000],
        ],
    ],
    'button' => [
        'label_th' => 'ปุ่มลิงก์',
        'label_en' => 'Link button',
        'animation' => true,
        'fields' => [
            'label' => ['type' => 'text', 'label_th' => 'ข้อความบนปุ่ม', 'label_en' => 'Button label', 'max' => 80],
            'url' => ['type' => 'text', 'label_th' => 'ปลายทาง', 'label_en' => 'Destination', 'max' => 255],
            'variant' => ['type' => 'select', 'label_th' => 'รูปแบบ', 'label_en' => 'Style', 'options' => ['primary', 'secondary']],
        ],
    ],
    'image' => [
        'label_th' => 'รูปภาพ',
        'label_en' => 'Image',
        'animation' => true,
        'fields' => [
            'media_id' => ['type' => 'media', 'label_th' => 'รูปจากคลังสื่อ', 'label_en' => 'Media library image'],
            'alt' => ['type' => 'text', 'label_th' => 'คำอธิบายรูป', 'label_en' => 'Image description', 'max' => 200],
        ],
    ],
    'list' => [
        'label_th' => 'รายการ',
        'label_en' => 'List',
        'animation' => true,
        'fields' => [
            'text' => ['type' => 'textarea', 'label_th' => 'รายการ (บรรทัดละข้อ)', 'label_en' => 'Items (one per line)', 'max' => 3000],
            'style' => ['type' => 'select', 'label_th' => 'รูปแบบ', 'label_en' => 'Style', 'options' => ['bullet', 'number']],
        ],
    ],
    'quote' => [
        'label_th' => 'ข้อความอ้างอิง',
        'label_en' => 'Quote',
        'animation' => true,
        'fields' => [
            'text' => ['type' => 'textarea', 'label_th' => 'ข้อความ', 'label_en' => 'Text', 'max' => 1000],
            'cite' => ['type' => 'text', 'label_th' => 'แหล่งที่มา (ไม่บังคับ)', 'label_en' => 'Source (optional)', 'max' => 120],
        ],
    ],
    'callout' => [
        'label_th' => 'กล่องข้อความเน้น',
        'label_en' => 'Callout',
        'animation' => true,
        'fields' => [
            'text' => ['type' => 'textarea', 'label_th' => 'ข้อความ', 'label_en' => 'Text', 'max' => 1000],
            'tone' => ['type' => 'select', 'label_th' => 'สี', 'label_en' => 'Tone', 'options' => ['info', 'success', 'warning']],
        ],
    ],
    'divider' => [
        'label_th' => 'เส้นคั่น',
        'label_en' => 'Divider',
        'animation' => true,
        'fields' => [],
    ],
    'spacer' => [
        'label_th' => 'ช่องว่าง',
        'label_en' => 'Spacer',
        'animation' => true,
        'fields' => [
            'size' => ['type' => 'select', 'label_th' => 'ขนาด', 'label_en' => 'Size', 'options' => ['small', 'medium', 'large']],
        ],
    ],
    'form' => [
        'label_th' => 'แบบฟอร์ม',
        'label_en' => 'Form',
        'animation' => true,
        'fields' => [
            'form_id' => ['type' => 'form', 'label_th' => 'แบบฟอร์มที่เผยแพร่', 'label_en' => 'Published form'],
        ],
    ],
];

function visual_builder_home_content(array $settings): array
{
    $raw = (string)($settings['visual_builder_home_content'] ?? '');
    static $cachedRaw = null;
    static $cachedContent = [];
    if ($cachedRaw === $raw) {
        return $cachedContent;
    }
    if ($raw === '') {
        $cachedRaw = '';
        $cachedContent = [];
        return [];
    }

    try {
        $decoded = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('Invalid homepage visual content JSON', 0, $e);
    }
    if (!is_array($decoded)) {
        throw new RuntimeException('Homepage visual content must be an object');
    }

    $content = [];
    foreach ($decoded as $key => $locales) {
        if (!is_string($key) || !isset(VISUAL_BUILDER_HOME_TEXT_TARGETS[$key]) || !is_array($locales)) {
            throw new RuntimeException('Unknown homepage visual content target');
        }
        if (array_diff(array_keys($locales), ['th', 'en'])) {
            throw new RuntimeException('Unknown homepage visual content locale');
        }
        foreach (['th', 'en'] as $locale) {
            if (!array_key_exists($locale, $locales)) {
                continue;
            }
            $target = VISUAL_BUILDER_HOME_TEXT_TARGETS[$key];
            $content[$key][$locale] = visual_builder_text_value($locales[$locale], $target['max'], $target['multiline'] ?? false);
        }
    }

    $cachedRaw = $raw;
    $cachedContent = $content;
    return $content;
}

function visual_builder_home_text(array $settings, string $key, string $fallback): string
{
    if (!isset(VISUAL_BUILDER_HOME_TEXT_TARGETS[$key])) {
        throw new InvalidArgumentException('Unknown homepage text target');
    }
    $locale = function_exists('app_lang') && app_lang() === 'en' ? 'en' : 'th';
    $content = visual_builder_home_content($settings);
    return $content[$key][$locale] ?? $fallback;
}

function visual_builder_home_layout(array $settings): array
{
    $raw = (string)($settings['visual_builder_home_layout'] ?? '');
    $stored = [];
    if ($raw !== '') {
        try {
            $stored = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Invalid homepage visual layout JSON', 0, $e);
        }
        if (!is_array($stored)) {
            throw new RuntimeException('Homepage visual layout must be an object');
        }
        if (array_diff(array_keys($stored), array_keys(VISUAL_BUILDER_HOME_SECTIONS))) {
            throw new RuntimeException('Unknown homepage visual section');
        }
    }

    $layout = [];
    foreach (array_keys(VISUAL_BUILDER_HOME_SECTIONS) as $index => $key) {
        $row = $stored[$key] ?? [];
        if (!is_array($row)) {
            throw new RuntimeException('Invalid homepage section layout');
        }
        if (array_diff(array_keys($row), ['visible', 'size', 'order', 'background'])
            || (array_key_exists('visible', $row) && !is_bool($row['visible']))) {
            throw new RuntimeException('Invalid homepage section layout properties');
        }
        $visible = match ($key) {
            'how' => ($settings['homepage_show_steps'] ?? '1') === '1',
            'faq' => ($settings['homepage_show_faq'] ?? '1') === '1',
            default => is_bool($row['visible'] ?? null) ? $row['visible'] : true,
        };
        $size = $row['size'] ?? 'normal';
        $order = $row['order'] ?? (($index + 1) * 10);
        $background = $row['background'] ?? '';
        if (!in_array($size, ['compact', 'normal', 'wide'], true)
            || !is_int($order)
            || $order < 1
            || $order > 60
            || !is_string($background)
            || ($background !== '' && site_hex($background) === '')) {
            throw new RuntimeException('Invalid homepage section layout value');
        }
        $layout[$key] = ['visible' => $visible, 'size' => $size, 'order' => $order, 'background' => site_hex($background)];
    }
    uasort($layout, static fn(array $a, array $b): int => $a['order'] <=> $b['order']);

    return $layout;
}

function visual_builder_home_target_attrs(bool $editMode, string $key): string
{
    if (!$editMode) {
        return '';
    }

    if (isset(VISUAL_BUILDER_HOME_SECTIONS[$key])) {
        $target = VISUAL_BUILDER_HOME_SECTIONS[$key];
        $kind = 'section';
    } elseif (isset(VISUAL_BUILDER_HOME_TEXT_TARGETS[$key])) {
        $target = VISUAL_BUILDER_HOME_TEXT_TARGETS[$key];
        $kind = 'text';
    } elseif ($key === 'home.logo') {
        $target = ['label_th' => 'โลโก้เว็บไซต์', 'label_en' => 'Site logo'];
        $kind = 'image';
    } else {
        throw new InvalidArgumentException('Unknown homepage visual target');
    }

    $label = function_exists('tr') ? tr($target['label_th'], $target['label_en']) : $target['label_en'];
    $maxLength = $kind === 'text' ? ' data-vb-max-length="' . (int)$target['max'] . '"' : '';
    return ' data-vb-key="' . visual_builder_escape($key) . '" data-vb-kind="' . $kind . '" data-vb-label="' . visual_builder_escape($label) . '"' . $maxLength;
}

function visual_builder_home_section_attrs(bool $editMode, array $layout, string $key): string
{
    $attrs = visual_builder_home_target_attrs($editMode, $key);
    if (!$editMode) {
        return $attrs;
    }

    $section = $layout[$key];
    return $attrs
        . ' data-vb-visible="' . ($section['visible'] ? '1' : '0') . '"'
        . ' data-vb-size="' . visual_builder_escape($section['size']) . '"'
        . ' data-vb-background="' . visual_builder_escape($section['background']) . '"'
        . ' data-vb-order="' . (int)$section['order'] . '"';
}

function visual_builder_home_divider_attrs(bool $editMode, string $key): string
{
    return $editMode ? ' data-vb-divider-after="' . visual_builder_escape($key) . '"' : '';
}

function visual_builder_home_section_class(array $layout, string $key): string
{
    return 'vb-home-size--' . visual_builder_escape($layout[$key]['size']);
}

function visual_builder_home_order_style(array $layout, string $key, int $offset = 0): string
{
    $style = 'order:' . ((int)$layout[$key]['order'] * 2 + $offset) . ';';
    if ($offset === 0 && $layout[$key]['background'] !== '') {
        $style .= 'background-color:' . $layout[$key]['background'] . ';';
    }

    return $style;
}

function visual_builder_page_key(int $pageId): string
{
    if ($pageId < 1) {
        throw new InvalidArgumentException('Page ID must be positive');
    }

    return 'page_' . $pageId;
}

function visual_builder_default_config(string $type, string $pageTitle = ''): array
{
    $config = match ($type) {
        'heading' => ['text' => $pageTitle !== '' ? $pageTitle : 'Page heading', 'level' => 'h2'],
        'paragraph' => ['text' => 'Add your text here.'],
        'button' => ['label' => 'Learn more', 'url' => 'index.php', 'variant' => 'primary'],
        'image' => ['media_id' => 0, 'alt' => ''],
        'list' => ['text' => 'First item', 'style' => 'bullet'],
        'quote' => ['text' => 'Add a quote here.', 'cite' => ''],
        'callout' => ['text' => 'Add an important note here.', 'tone' => 'info'],
        'divider' => [],
        'spacer' => ['size' => 'medium'],
        'form' => ['form_id' => 0],
        default => throw new InvalidArgumentException('Unknown visual component'),
    };
    $config['animation'] = 'none';

    return $config;
}

function visual_builder_animation_value(mixed $value): string
{
    if ($value === null || $value === '') {
        return 'none';
    }
    if (!is_string($value) || !array_key_exists($value, VISUAL_BUILDER_ANIMATIONS)) {
        throw new InvalidArgumentException('Invalid animation preset');
    }

    return $value;
}

function visual_builder_optional_text(mixed $value, int $maxLength): string
{
    if ($value === null || $value === '') {
        return '';
    }
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
        throw new InvalidArgumentException('Optional text must be valid UTF-8');
    }
    $value = (string)preg_replace('/\s+/u', ' ', trim($value));
    if (mb_strlen($value, 'UTF-8') > $maxLength) {
        throw new InvalidArgumentException('Optional text is too long');
    }

    return $value;
}

function visual_builder_list_items(string $text): array
{
    $items = [];
    foreach (preg_split('/\R/u', $text) ?: [] as $line) {
        $line = (string)preg_replace('/\s+/u', ' ', trim($line));
        if ($line !== '') {
            $items[] = $line;
        }
    }

    return $items;
}

function visual_builder_text_value(mixed $value, int $maxLength, bool $multiline = false): string
{
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
        throw new InvalidArgumentException('Component text must be valid UTF-8');
    }

    $value = str_replace(["\r\n", "\r"], "\n", trim($value));
    if (!$multiline) {
        $value = str_replace("\n", ' ', $value);
        $value = (string)preg_replace('/\s+/u', ' ', $value);
    }
    if ($value === '' || mb_strlen($value, 'UTF-8') > $maxLength) {
        throw new InvalidArgumentException('Component text is empty or too long');
    }

    return $value;
}

function visual_builder_validate_config(string $type, array $config): array
{
    $component = VISUAL_BUILDER_COMPONENTS[$type] ?? null;
    if ($component === null) {
        throw new InvalidArgumentException('Unknown visual component');
    }

    $fields = $component['fields'];
    // `animation` was added after the original CMS blocks were stored. Treat
    // its absence as `none` so old revisions remain readable.
    $supportsAnimation = ($component['animation'] ?? false) === true;
    $allowedConfigFields = $supportsAnimation ? array_merge(array_keys($fields), ['animation']) : array_keys($fields);
    $requiredConfigFields = array_diff(array_keys($fields), $supportsAnimation ? ['animation'] : []);
    if (array_diff(array_keys($config), $allowedConfigFields)
        || array_diff($requiredConfigFields, array_keys($config))) {
        throw new InvalidArgumentException('Component properties do not match its schema');
    }

    $validated = match ($type) {
        'heading' => [
            'text' => visual_builder_text_value($config['text'], 200),
            'level' => in_array($config['level'], ['h2', 'h3'], true)
                ? $config['level']
                : throw new InvalidArgumentException('Invalid heading level'),
        ],
        'paragraph' => [
            'text' => visual_builder_text_value($config['text'], 3000, true),
        ],
        'button' => [
            'label' => visual_builder_text_value($config['label'], 80),
            'url' => site_safe_link(is_string($config['url']) ? $config['url'] : '')
                ?? throw new InvalidArgumentException('Invalid button destination'),
            'variant' => in_array($config['variant'], ['primary', 'secondary'], true)
                ? $config['variant']
                : throw new InvalidArgumentException('Invalid button style'),
        ],
        'image' => [
            'media_id' => is_int($config['media_id']) && $config['media_id'] > 0
                ? $config['media_id']
                : (is_string($config['media_id']) && ctype_digit($config['media_id']) && (int)$config['media_id'] > 0
                    ? (int)$config['media_id']
                    : throw new InvalidArgumentException('Invalid media ID')),
            'alt' => is_string($config['alt']) && mb_check_encoding($config['alt'], 'UTF-8') && mb_strlen($config['alt'], 'UTF-8') <= 200
                ? trim($config['alt'])
                : throw new InvalidArgumentException('Invalid image description'),
        ],
        'list' => (static function () use ($config): array {
            $text = visual_builder_text_value($config['text'], 3000, true);
            $items = visual_builder_list_items($text);
            if (count($items) < 1 || count($items) > 30) {
                throw new InvalidArgumentException('A list must contain between 1 and 30 items');
            }
            foreach ($items as $item) {
                if (mb_strlen($item, 'UTF-8') > 300) {
                    throw new InvalidArgumentException('List item is too long');
                }
            }
            return [
                'text' => implode("\n", $items),
                'style' => in_array($config['style'], ['bullet', 'number'], true)
                    ? $config['style']
                    : throw new InvalidArgumentException('Invalid list style'),
            ];
        })(),
        'quote' => [
            'text' => visual_builder_text_value($config['text'], 1000, true),
            'cite' => visual_builder_optional_text($config['cite'], 120),
        ],
        'callout' => [
            'text' => visual_builder_text_value($config['text'], 1000, true),
            'tone' => in_array($config['tone'], ['info', 'success', 'warning'], true)
                ? $config['tone']
                : throw new InvalidArgumentException('Invalid callout tone'),
        ],
        'divider' => [],
        'spacer' => [
            'size' => in_array($config['size'], ['small', 'medium', 'large'], true)
                ? $config['size']
                : throw new InvalidArgumentException('Invalid spacer size'),
        ],
        'form' => [
            'form_id' => (is_int($config['form_id']) && $config['form_id'] > 0)
                ? $config['form_id']
                : (is_string($config['form_id']) && ctype_digit($config['form_id']) && (int)$config['form_id'] > 0
                    ? (int)$config['form_id']
                    : throw new InvalidArgumentException('Invalid form ID')),
        ],
        default => throw new InvalidArgumentException('Unknown visual component'),
    };
    if ($supportsAnimation) {
        $validated['animation'] = visual_builder_animation_value($config['animation'] ?? 'none');
    }
    return $validated;
}

function visual_builder_validate_posted_blocks(mixed $postedBlocks): array
{
    if (!is_array($postedBlocks) || count($postedBlocks) < 1 || count($postedBlocks) > VISUAL_BUILDER_MAX_BLOCKS) {
        throw new InvalidArgumentException('A page must contain between 1 and ' . VISUAL_BUILDER_MAX_BLOCKS . ' blocks');
    }

    $blocks = [];
    foreach (array_values($postedBlocks) as $block) {
        if (!is_array($block) || !is_string($block['type'] ?? null)) {
            throw new InvalidArgumentException('Invalid component data');
        }
        $type = $block['type'];
        $component = VISUAL_BUILDER_COMPONENTS[$type] ?? null;
        if ($component === null) {
            throw new InvalidArgumentException('Unknown visual component');
        }
        $allowedKeys = array_merge(['type'], array_keys($component['fields']));
        if (($component['animation'] ?? false) === true) {
            $allowedKeys[] = 'animation';
        }
        $allowedKeys[] = 'is_visible';
        if (array_diff(array_keys($block), $allowedKeys)) {
            throw new InvalidArgumentException('Unexpected component property');
        }
        if (!in_array($block['is_visible'] ?? null, ['0', '1', 0, 1], true)) {
            throw new InvalidArgumentException('Invalid component visibility');
        }

        $config = [];
        foreach (array_keys($component['fields']) as $field) {
            if (!array_key_exists($field, $block)) {
                throw new InvalidArgumentException('Missing component property');
            }
            $config[$field] = $block[$field];
        }
        if (($component['animation'] ?? false) === true) {
            $config['animation'] = $block['animation'] ?? 'none';
        }
        $blocks[] = [
            'type' => $type,
            'config' => visual_builder_validate_config($type, $config),
            'is_visible' => (int)$block['is_visible'] === 1,
        ];
    }

    return $blocks;
}

function visual_builder_load_blocks(mysqli $conn, int $pageId, string $status): array
{
    if (!in_array($status, ['draft', 'published'], true)) {
        throw new InvalidArgumentException('Invalid page status');
    }

    $rows = db_all(
        $conn,
        'SELECT block_type, config_json, is_visible FROM site_blocks WHERE page_key = ? AND status = ? ORDER BY sort_order, id',
        'ss',
        [visual_builder_page_key($pageId), $status]
    );
    $blocks = [];
    foreach ($rows as $row) {
        try {
            $config = json_decode((string)$row['config_json'], true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Invalid stored visual component JSON', 0, $e);
        }
        if (!is_array($config)) {
            throw new RuntimeException('Stored visual component config must be an object');
        }
        $type = (string)$row['block_type'];
        $blocks[] = [
            'type' => $type,
            'config' => visual_builder_validate_config($type, $config),
            'is_visible' => (int)$row['is_visible'] === 1,
        ];
    }

    return $blocks;
}

function visual_builder_store_blocks(mysqli $conn, int $pageId, array $blocks, string $status, int $userId): void
{
    if (!in_array($status, ['draft', 'published'], true)) {
        throw new InvalidArgumentException('Invalid page status');
    }

    db_exec($conn, 'DELETE FROM site_blocks WHERE page_key = ? AND status = ?', 'ss', [visual_builder_page_key($pageId), $status]);
    foreach (array_values($blocks) as $index => $block) {
        if (!is_array($block) || !is_string($block['type'] ?? null) || !is_array($block['config'] ?? null) || !is_bool($block['is_visible'] ?? null)) {
            throw new InvalidArgumentException('Invalid visual component data');
        }
        $type = (string)$block['type'];
        $config = visual_builder_validate_config($type, $block['config']);
        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        db_exec(
            $conn,
            'INSERT INTO site_blocks (page_key, block_type, sort_order, config_json, is_visible, status, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
            'ssisisi',
            [visual_builder_page_key($pageId), $type, ($index + 1) * 10, $json, (int)$block['is_visible'], $status, $userId]
        );
    }
}

function visual_builder_record_revision(mysqli $conn, int $pageId, string $status, array $snapshot, int $userId, string $note = ''): void
{
    $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if ($note === '') {
        $note = $status === 'published' ? 'Published page' : 'Saved page draft';
    }
    db_insert(
        $conn,
        'INSERT INTO site_revisions (entity_type, entity_id, data_json, note, created_by) VALUES (?, ?, ?, ?, ?)',
        'ssssi',
        ['page', (string)$pageId, $json, mb_substr($note, 0, 200), $userId]
    );
}

function visual_builder_validate_page_title(mixed $title): string
{
    if (!is_string($title) || !mb_check_encoding($title, 'UTF-8')) {
        throw new InvalidArgumentException('Page title must be valid UTF-8');
    }
    $title = (string)preg_replace('/\s+/u', ' ', trim($title));
    if ($title === '' || mb_strlen($title, 'UTF-8') > 200) {
        throw new InvalidArgumentException('Page title is empty or too long');
    }

    return $title;
}

function visual_builder_validate_page_slug(mixed $slug): string
{
    if (!is_string($slug)) {
        throw new InvalidArgumentException('Page slug must be a string');
    }
    $slug = strtolower(trim($slug));
    if (preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug) !== 1 || strlen($slug) < 2 || strlen($slug) > 80) {
        throw new InvalidArgumentException('Page slug must use lowercase letters, numbers, and single hyphens');
    }

    return $slug;
}

function visual_builder_page_by_id(mysqli $conn, int $pageId): ?array
{
    if ($pageId < 1) {
        return null;
    }

    return db_one($conn, 'SELECT id, slug, title, status, updated_by, updated_at FROM site_pages WHERE id = ? LIMIT 1', 'i', [$pageId]);
}

function visual_builder_page_by_slug(mysqli $conn, string $slug): ?array
{
    $slug = visual_builder_validate_page_slug($slug);

    return db_one($conn, 'SELECT id, slug, title, status, updated_by, updated_at FROM site_pages WHERE slug = ? LIMIT 1', 's', [$slug]);
}

/** @return list<array<string,mixed>> */
function visual_builder_pages_all(mysqli $conn): array
{
    return db_all(
        $conn,
        "SELECT p.id, p.slug, p.title, p.status, p.updated_by, p.updated_at,
                COALESCE(u.first_name, '') AS first_name, COALESCE(u.last_name, '') AS last_name,
                (SELECT COUNT(*) FROM site_blocks b WHERE b.page_key = CONCAT('page_', p.id) AND b.status = 'draft') AS draft_blocks,
                (SELECT COUNT(*) FROM site_blocks b WHERE b.page_key = CONCAT('page_', p.id) AND b.status = 'published') AS published_blocks
           FROM site_pages p
           LEFT JOIN users u ON u.user_id = p.updated_by
          ORDER BY p.updated_at DESC, p.id DESC"
    );
}

function visual_builder_page_block_count(mysqli $conn, int $pageId, string $status): int
{
    if (!in_array($status, ['draft', 'published'], true)) {
        throw new InvalidArgumentException('Invalid page status');
    }
    $row = db_one(
        $conn,
        'SELECT COUNT(*) AS c FROM site_blocks WHERE page_key = ? AND status = ?',
        'ss',
        [visual_builder_page_key($pageId), $status]
    );

    return (int)($row['c'] ?? 0);
}

function visual_builder_page_slug_available(mysqli $conn, string $slug, int $exceptPageId = 0): bool
{
    $slug = visual_builder_validate_page_slug($slug);
    $row = db_one($conn, 'SELECT id FROM site_pages WHERE slug = ? LIMIT 1', 's', [$slug]);

    return $row === null || (int)$row['id'] === $exceptPageId;
}

function visual_builder_page_snapshot(array $page, array $blocks): array
{
    return [
        'title' => (string)$page['title'],
        'slug' => (string)$page['slug'],
        'status' => (string)$page['status'],
        'blocks' => $blocks,
    ];
}

function visual_builder_page_create(mysqli $conn, string $title, string $slug, int $userId): int
{
    $title = visual_builder_validate_page_title($title);
    $slug = visual_builder_validate_page_slug($slug);
    if (!visual_builder_page_slug_available($conn, $slug)) {
        throw new InvalidArgumentException('Page slug is already in use');
    }

    return db_transaction($conn, static function () use ($conn, $title, $slug, $userId): int {
        $pageId = db_insert(
            $conn,
            "INSERT INTO site_pages (slug, title, status, updated_by) VALUES (?, ?, 'draft', ?)",
            'ssi',
            [$slug, $title, $userId]
        );
        $blocks = [[
            'type' => 'heading',
            'config' => ['text' => $title, 'level' => 'h2'],
            'is_visible' => true,
        ]];
        visual_builder_store_blocks($conn, $pageId, $blocks, 'draft', $userId);
        visual_builder_record_revision(
            $conn,
            $pageId,
            'draft',
            ['title' => $title, 'slug' => $slug, 'status' => 'draft', 'blocks' => $blocks],
            $userId,
            'Created page'
        );

        return $pageId;
    });
}

function visual_builder_page_save_draft(mysqli $conn, int $pageId, string $title, string $slug, array $blocks, int $userId): void
{
    $title = visual_builder_validate_page_title($title);
    $slug = visual_builder_validate_page_slug($slug);
    if ($blocks === []) {
        throw new InvalidArgumentException('A page must contain at least one block');
    }
    $page = visual_builder_page_by_id($conn, $pageId);
    if ($page === null) {
        throw new InvalidArgumentException('Page does not exist');
    }
    if (!visual_builder_page_slug_available($conn, $slug, $pageId)) {
        throw new InvalidArgumentException('Page slug is already in use');
    }

    db_transaction($conn, static function () use ($conn, $pageId, $title, $slug, $blocks, $userId): void {
        db_exec(
            $conn,
            'UPDATE site_pages SET slug = ?, title = ?, updated_by = ? WHERE id = ?',
            'ssii',
            [$slug, $title, $userId, $pageId]
        );
        visual_builder_store_blocks($conn, $pageId, $blocks, 'draft', $userId);
        visual_builder_record_revision(
            $conn,
            $pageId,
            'draft',
            ['title' => $title, 'slug' => $slug, 'status' => 'draft', 'blocks' => $blocks],
            $userId,
            'Saved page draft'
        );
    });
}

function visual_builder_page_publish(mysqli $conn, int $pageId, int $userId): void
{
    $page = visual_builder_page_by_id($conn, $pageId);
    if ($page === null) {
        throw new InvalidArgumentException('Page does not exist');
    }
    $blocks = visual_builder_load_blocks($conn, $pageId, 'draft');
    if ($blocks === []) {
        throw new InvalidArgumentException('A page must contain at least one block before publishing');
    }

    db_transaction($conn, static function () use ($conn, $pageId, $page, $blocks, $userId): void {
        visual_builder_store_blocks($conn, $pageId, $blocks, 'published', $userId);
        db_exec(
            $conn,
            "UPDATE site_pages SET status = 'published', updated_by = ? WHERE id = ?",
            'ii',
            [$userId, $pageId]
        );
        visual_builder_record_revision(
            $conn,
            $pageId,
            'published',
            ['title' => (string)$page['title'], 'slug' => (string)$page['slug'], 'status' => 'published', 'blocks' => $blocks],
            $userId,
            'Published page'
        );
    });
}

function visual_builder_page_unpublish(mysqli $conn, int $pageId, int $userId): void
{
    $page = visual_builder_page_by_id($conn, $pageId);
    if ($page === null) {
        throw new InvalidArgumentException('Page does not exist');
    }
    db_transaction($conn, static function () use ($conn, $pageId, $page, $userId): void {
        db_exec($conn, "UPDATE site_pages SET status = 'draft', updated_by = ? WHERE id = ?", 'ii', [$userId, $pageId]);
        visual_builder_record_revision(
            $conn,
            $pageId,
            'draft',
            ['title' => (string)$page['title'], 'slug' => (string)$page['slug'], 'status' => 'draft'],
            $userId,
            'Unpublished page'
        );
    });
}

function visual_builder_page_revert_draft(mysqli $conn, int $pageId, int $userId): void
{
    $page = visual_builder_page_by_id($conn, $pageId);
    if ($page === null) {
        throw new InvalidArgumentException('Page does not exist');
    }
    $blocks = visual_builder_load_blocks($conn, $pageId, 'published');
    if ($blocks === []) {
        throw new InvalidArgumentException('There is no published content to restore');
    }
    db_transaction($conn, static function () use ($conn, $pageId, $blocks, $userId): void {
        visual_builder_store_blocks($conn, $pageId, $blocks, 'draft', $userId);
        db_exec($conn, 'UPDATE site_pages SET updated_by = ? WHERE id = ?', 'ii', [$userId, $pageId]);
        visual_builder_record_revision(
            $conn,
            $pageId,
            'draft',
            ['blocks' => $blocks],
            $userId,
            'Reverted draft from published'
        );
    });
}

function visual_builder_page_delete(mysqli $conn, int $pageId): void
{
    $page = visual_builder_page_by_id($conn, $pageId);
    if ($page === null) {
        throw new InvalidArgumentException('Page does not exist');
    }
    db_transaction($conn, static function () use ($conn, $pageId): void {
        db_exec($conn, 'DELETE FROM site_blocks WHERE page_key = ?', 's', [visual_builder_page_key($pageId)]);
        db_exec($conn, 'DELETE FROM site_pages WHERE id = ?', 'i', [$pageId]);
    });
}

/** @return list<array<string,mixed>> */
function visual_builder_page_revisions(mysqli $conn, int $pageId, int $limit = 10): array
{
    $limit = max(1, min(50, $limit));

    return db_all(
        $conn,
        "SELECT r.id, r.note, r.created_at, COALESCE(u.first_name, '') AS first_name, COALESCE(u.last_name, '') AS last_name
           FROM site_revisions r
           LEFT JOIN users u ON u.user_id = r.created_by
          WHERE r.entity_type = 'page' AND r.entity_id = ?
          ORDER BY r.id DESC
          LIMIT " . $limit,
        's',
        [(string)$pageId]
    );
}

/** Restore a historical page snapshot into the draft while retaining revision history. */
function visual_builder_page_restore_revision(mysqli $conn, int $pageId, int $revisionId, int $userId): void
{
    if ($pageId < 1 || $revisionId < 1 || $userId < 1) {
        throw new InvalidArgumentException('Invalid page revision request');
    }
    $revision = db_one(
        $conn,
        "SELECT data_json FROM site_revisions WHERE id = ? AND entity_type = 'page' AND entity_id = ? LIMIT 1",
        'is',
        [$revisionId, (string)$pageId]
    );
    if ($revision === null) {
        throw new InvalidArgumentException('Page revision does not exist');
    }
    try {
        $snapshot = json_decode((string)$revision['data_json'], true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new InvalidArgumentException('Page revision data is invalid', 0, $e);
    }
    if (!is_array($snapshot) || !is_array($snapshot['blocks'] ?? null) || $snapshot['blocks'] === []) {
        throw new InvalidArgumentException('This revision has no restorable page content');
    }
    $title = visual_builder_validate_page_title($snapshot['title'] ?? null);
    $slug = visual_builder_validate_page_slug($snapshot['slug'] ?? null);
    $blocks = [];
    foreach ($snapshot['blocks'] as $block) {
        if (!is_array($block) || !is_string($block['type'] ?? null)
            || !is_array($block['config'] ?? null) || !is_bool($block['is_visible'] ?? null)) {
            throw new InvalidArgumentException('Page revision contains invalid component data');
        }
        $blocks[] = [
            'type' => $block['type'],
            'config' => visual_builder_validate_config($block['type'], $block['config']),
            'is_visible' => $block['is_visible'],
        ];
    }
    if (count($blocks) > VISUAL_BUILDER_MAX_BLOCKS) {
        throw new InvalidArgumentException('Page revision exceeds the current component limit');
    }
    db_transaction($conn, static function () use ($conn, $pageId, $revisionId, $title, $slug, $blocks, $userId): void {
        if (!db_one($conn, 'SELECT id FROM site_pages WHERE id = ? FOR UPDATE', 'i', [$pageId])) {
            throw new InvalidArgumentException('Page does not exist');
        }
        if (!visual_builder_page_slug_available($conn, $slug, $pageId)) {
            throw new InvalidArgumentException('Page revision slug is already in use');
        }
        db_exec($conn, 'UPDATE site_pages SET title = ?, slug = ?, updated_by = ? WHERE id = ?', 'ssii', [$title, $slug, $userId, $pageId]);
        visual_builder_store_blocks($conn, $pageId, $blocks, 'draft', $userId);
        visual_builder_record_revision(
            $conn,
            $pageId,
            'draft',
            ['title' => $title, 'slug' => $slug, 'status' => 'draft', 'blocks' => $blocks],
            $userId,
            'Restored from revision #' . $revisionId
        );
    });
}

function visual_builder_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function visual_builder_render_blocks(array $blocks, string $basePath = '', array $forms = [], string $csrfToken = '', bool $preview = false, int $submittedFormId = 0): string
{
    if (!in_array($basePath, ['', '../'], true)) {
        throw new InvalidArgumentException('Invalid visual component asset path');
    }

    $html = '';
    foreach ($blocks as $block) {
        if (!is_array($block) || !is_string($block['type'] ?? null) || !is_array($block['config'] ?? null)) {
            throw new InvalidArgumentException('Invalid stored visual block');
        }
        $isVisible = $block['is_visible'] ?? true;
        if (!is_bool($isVisible)) {
            throw new InvalidArgumentException('Component visibility must be boolean');
        }
        if (!$isVisible) {
            continue;
        }

        $type = $block['type'];
        $config = visual_builder_validate_config($type, $block['config']);
        $blockHtml = match ($type) {
            'heading' => '<section class="vb-block vb-block--heading"><' . $config['level'] . '>'
                . visual_builder_escape($config['text']) . '</' . $config['level'] . '></section>',
            'paragraph' => '<section class="vb-block vb-block--paragraph"><p>'
                . nl2br(visual_builder_escape($config['text']), false) . '</p></section>',
            'button' => '<section class="vb-block vb-block--button"><a class="vb-button vb-button--' . $config['variant']
                . '" href="' . visual_builder_escape(site_link_href($config['url'], $basePath)) . '"'
                . (preg_match('#^https?://#i', $config['url']) === 1 ? ' target="_blank" rel="noopener noreferrer"' : '')
                . '>' . visual_builder_escape($config['label']) . '</a></section>',
            'image' => '<figure class="vb-block vb-block--image"><img src="' . $basePath . 'file.php?m=' . $config['media_id']
                . '" alt="' . visual_builder_escape($config['alt']) . '" loading="lazy"></figure>',
            'list' => (static function () use ($config): string {
                $tag = $config['style'] === 'number' ? 'ol' : 'ul';
                $html = '<section class="vb-block vb-block--list"><' . $tag . '>';
                foreach (visual_builder_list_items($config['text']) as $item) {
                    $html .= '<li>' . visual_builder_escape($item) . '</li>';
                }
                return $html . '</' . $tag . '></section>';
            })(),
            'quote' => '<section class="vb-block vb-block--quote"><blockquote><p>'
                . nl2br(visual_builder_escape($config['text']), false) . '</p>'
                . ($config['cite'] !== '' ? '<cite>' . visual_builder_escape($config['cite']) . '</cite>' : '')
                . '</blockquote></section>',
            'callout' => '<aside class="vb-block vb-block--callout vb-callout vb-callout--' . $config['tone'] . '"><p>'
                . nl2br(visual_builder_escape($config['text']), false) . '</p></aside>',
            'divider' => '<hr class="vb-block vb-block--divider">',
            'spacer' => '<div class="vb-block vb-block--spacer vb-spacer--' . $config['size'] . '" aria-hidden="true"></div>',
            'form' => isset($forms[$config['form_id']])
                ? form_builder_render($forms[$config['form_id']], $config['form_id'], $csrfToken, $preview, $submittedFormId === $config['form_id'])
                : '<section class="vb-block vb-block--form-unavailable"><p>'
                    . visual_builder_escape(function_exists('tr') ? tr('แบบฟอร์มนี้ยังไม่พร้อมใช้งาน', 'This form is not currently available.') : 'This form is not currently available.')
                    . '</p></section>',
            default => throw new InvalidArgumentException('Unknown visual component'),
        };
        if ($config['animation'] !== 'none') {
            $blockHtml = (string)preg_replace(
                '/^<(section|figure|aside|hr|div)\b/',
                '<$1 data-vb-animation="' . visual_builder_escape($config['animation']) . '"',
                $blockHtml,
                1
            );
        }
        $html .= $blockHtml;
    }

    return $html;
}
