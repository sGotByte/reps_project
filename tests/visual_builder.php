<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/visual_builder.php';
require_once __DIR__ . '/../includes/form_builder.php';

function vb_test(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function vb_test_reject(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException($message);
}

$heading = visual_builder_validate_config('heading', [
    'text' => '<script>alert(1)</script>',
    'level' => 'h2',
]);
$rendered = visual_builder_render_blocks([['type' => 'heading', 'config' => $heading]]);
vb_test(str_contains($rendered, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'Heading text was not escaped');
vb_test(!str_contains($rendered, '<script>'), 'User-provided script markup was rendered');
vb_test($heading['animation'] === 'none', 'Legacy component config did not get the default animation');
$animatedHeading = visual_builder_validate_config('heading', [
    'text' => 'Animated heading',
    'level' => 'h2',
    'animation' => 'fade-up',
]);
$animatedHtml = visual_builder_render_blocks([['type' => 'heading', 'config' => $animatedHeading]]);
vb_test(str_contains($animatedHtml, 'data-vb-animation="fade-up"'), 'Allowlisted animation was not rendered');
vb_test_reject(
    static fn() => visual_builder_validate_config('heading', ['text' => 'Invalid', 'level' => 'h2', 'animation' => 'run-script']),
    'Invalid animation was accepted'
);

$paragraph = visual_builder_validate_config('paragraph', ['text' => "First line\nSecond line"]);
$renderedParagraph = visual_builder_render_blocks([['type' => 'paragraph', 'config' => $paragraph]]);
vb_test(str_contains($renderedParagraph, "First line<br>\nSecond line"), 'Paragraph line breaks were not rendered safely');

$button = visual_builder_validate_config('button', [
    'label' => 'Read more',
    'url' => 'https://example.test/guide',
    'variant' => 'primary',
]);
$renderedButton = visual_builder_render_blocks([['type' => 'button', 'config' => $button]]);
vb_test(str_contains($renderedButton, 'rel="noopener noreferrer"'), 'External links must be isolated from the opener');

$image = visual_builder_validate_config('image', [
    'media_id' => '12',
    'alt' => '"><img src=x>',
]);
$renderedImage = visual_builder_render_blocks([['type' => 'image', 'config' => $image]]);
vb_test(str_contains($renderedImage, 'file.php?m=12'), 'Image did not use the public media endpoint');
vb_test(str_contains($renderedImage, 'alt="&quot;&gt;&lt;img src=x&gt;"'), 'Image description was not escaped');
vb_test(substr_count($renderedImage, '<img') === 1, 'Image description created additional markup');
$adminPreview = visual_builder_render_blocks([['type' => 'image', 'config' => $image]], '../');
vb_test(str_contains($adminPreview, '../file.php?m=12'), 'Admin preview did not use the correct media route');
$adminButton = visual_builder_render_blocks([['type' => 'button', 'config' => visual_builder_validate_config('button', [
    'label' => 'Dashboard',
    'url' => 'index.php',
    'variant' => 'primary',
])]], '../');
vb_test(str_contains($adminButton, 'href="../index.php"'), 'Admin preview did not resolve internal links from its route');

vb_test_reject(
    static fn() => visual_builder_validate_config('button', ['label' => 'Bad', 'url' => 'javascript:alert(1)', 'variant' => 'primary']),
    'JavaScript URL was accepted'
);
vb_test_reject(
    static fn() => visual_builder_validate_config('heading', ['text' => 'Hello', 'level' => 'h2', 'onclick' => 'alert(1)']),
    'Unknown component property was accepted'
);
vb_test_reject(
    static fn() => visual_builder_validate_config('custom_html', ['html' => '<script>alert(1)</script>']),
    'Unregistered component type was accepted'
);
vb_test_reject(
    static fn() => visual_builder_validate_posted_blocks([[
        'type' => 'heading',
        'text' => 'Valid heading',
        'level' => 'h2',
        'is_visible' => 'yes',
    ]]),
    'Invalid component visibility was accepted'
);
$postedBlocks = visual_builder_validate_posted_blocks([[
    'type' => 'heading',
    'text' => 'Hidden heading',
    'level' => 'h2',
    'is_visible' => '0',
]]);
vb_test($postedBlocks[0]['is_visible'] === false, 'Posted component visibility was not preserved');
$animatedPostedBlocks = visual_builder_validate_posted_blocks([[
    'type' => 'paragraph', 'text' => 'Safe text', 'animation' => 'zoom-in', 'is_visible' => '1',
]]);
vb_test($animatedPostedBlocks[0]['config']['animation'] === 'zoom-in', 'Posted animation was not normalized');

$pageTextSettings = [
    'visual_builder_content_login' => json_encode([
        'login.title' => ['th' => '<img src=x onerror=alert(1)>', 'en' => 'Sign in'],
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
];
$pageText = visual_builder_content_text($pageTextSettings, 'login', 'login.title', 'Default title', 'th');
vb_test($pageText === '<img src=x onerror=alert(1)>', 'Page text override was not read from its allowlisted key');
vb_test(!str_contains(visual_builder_escape($pageText), '<img'), 'Page text helper produced executable markup');
vb_test(visual_builder_content_text($pageTextSettings, 'login', 'login.title', 'Default title', 'en') === 'Sign in', 'English page text override was not selected');
vb_test(visual_builder_content_text([], 'login', 'login.title', 'Default title', 'en') === 'Default title', 'Missing page text did not use its fallback');
vb_test(str_contains(visual_builder_content_target_attrs(true, 'login', 'login.title'), 'data-vb-page="login"'), 'Preview target did not include its page scope');
vb_test(visual_builder_content_target_attrs(false, 'login', 'login.title') === '', 'Public page unexpectedly exposed editor metadata');
vb_test(
    isset(VISUAL_BUILDER_CONTENT_PAGES['user_dashboard']['targets']['user.greeting'], VISUAL_BUILDER_CONTENT_PAGES['user_dashboard']['targets']['user.tickets_title']),
    'Requester dashboard targets are missing from the Visual Builder registry'
);
vb_test(
    isset(VISUAL_BUILDER_CONTENT_PAGES['staff_dashboard']['targets']['staff.role_label'], VISUAL_BUILDER_CONTENT_PAGES['staff_dashboard']['targets']['staff.queue_title'], VISUAL_BUILDER_CONTENT_PAGES['staff_dashboard']['targets']['staff.active_title']),
    'Staff dashboard targets are missing from the Visual Builder registry'
);
vb_test(
    str_contains(visual_builder_content_target_attrs(true, 'user_dashboard', 'user.greeting'), 'data-vb-page="user_dashboard"'),
    'Requester dashboard did not produce preview metadata'
);
vb_test(
    str_contains(visual_builder_content_target_attrs(true, 'staff_dashboard', 'staff.queue_title'), 'data-vb-page="staff_dashboard"'),
    'Staff dashboard did not produce preview metadata'
);
vb_test_reject(
    static fn() => visual_builder_content_target_attrs(true, 'login', 'login.password_field'),
    'Non-allowlisted authentication field became editable'
);

$homeContent = [
    'visual_builder_home_content' => json_encode([
        'hero.title_first' => ['th' => '<script>alert(1)</script>', 'en' => 'Welcome'],
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
];
$homeTitle = visual_builder_home_text($homeContent, 'hero.title_first', 'Default title');
vb_test($homeTitle === (app_lang() === 'en' ? 'Welcome' : '<script>alert(1)</script>'), 'Homepage text override did not follow the active locale');
vb_test(!str_contains(visual_builder_escape($homeTitle), '<script>'), 'Homepage text override was not escaped for rendering');
$homeLayout = visual_builder_home_layout([
    'visual_builder_home_layout' => json_encode([
        'services' => ['visible' => false, 'size' => 'wide', 'order' => 10, 'background' => '#ABCDEF'],
    ], JSON_THROW_ON_ERROR),
]);
vb_test($homeLayout['services']['visible'] === false, 'Homepage section visibility was not preserved');
vb_test($homeLayout['services']['background'] === '#ABCDEF', 'Homepage section color was not normalized');
vb_test(str_contains(visual_builder_home_order_style($homeLayout, 'services'), 'background-color:#ABCDEF'), 'Homepage section color was not safely rendered');

$hidden = visual_builder_render_blocks([['type' => 'heading', 'config' => ['text' => 'Hidden', 'level' => 'h2'], 'is_visible' => false]]);
vb_test($hidden === '', 'Hidden component was rendered');

vb_test(visual_builder_validate_page_slug('rules-2026') === 'rules-2026', 'Valid page slug was rejected');
vb_test(visual_builder_validate_page_title('  Rules   page ') === 'Rules page', 'Page title was not normalized');
vb_test_reject(
    static fn() => visual_builder_validate_page_slug('Bad Slug'),
    'Unsafe page slug was accepted'
);
vb_test_reject(
    static fn() => visual_builder_validate_page_slug('a'),
    'Too-short page slug was accepted'
);
vb_test_reject(
    static fn() => visual_builder_validate_page_title('   '),
    'Empty page title was accepted'
);

$list = visual_builder_validate_config('list', [
    'text' => "One\n<script>alert(1)</script>\nThree",
    'style' => 'number',
]);
$renderedList = visual_builder_render_blocks([['type' => 'list', 'config' => $list]]);
vb_test(str_contains($renderedList, '<ol>') && str_contains($renderedList, '<li>Three</li>'), 'List was not rendered as an ordered list');
vb_test(str_contains($renderedList, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'List item was not escaped');
vb_test(!str_contains($renderedList, '<script>'), 'List item created executable markup');
vb_test_reject(
    static fn() => visual_builder_validate_config('list', ['text' => 'One', 'style' => 'grid']),
    'Invalid list style was accepted'
);
$tooManyItems = implode("\n", array_fill(0, 31, 'Item'));
vb_test_reject(
    static fn() => visual_builder_validate_config('list', ['text' => $tooManyItems, 'style' => 'bullet']),
    'A list over the item limit was accepted'
);

$quote = visual_builder_validate_config('quote', ['text' => "First\nSecond", 'cite' => '']);
$renderedQuote = visual_builder_render_blocks([['type' => 'quote', 'config' => $quote]]);
vb_test(str_contains($renderedQuote, '<blockquote>') && str_contains($renderedQuote, "First<br>\nSecond"), 'Quote line breaks were not rendered safely');
vb_test(!str_contains($renderedQuote, '<cite></cite>'), 'Empty quote source rendered a cite element');

$callout = visual_builder_validate_config('callout', ['text' => '<b>Important</b>', 'tone' => 'warning']);
$renderedCallout = visual_builder_render_blocks([['type' => 'callout', 'config' => $callout]]);
vb_test(str_contains($renderedCallout, 'vb-callout--warning'), 'Callout tone class was not rendered');
vb_test(str_contains($renderedCallout, '&lt;b&gt;Important&lt;/b&gt;'), 'Callout text was not escaped');
vb_test_reject(
    static fn() => visual_builder_validate_config('callout', ['text' => 'Note', 'tone' => 'danger']),
    'Invalid callout tone was accepted'
);

$formDefinition = form_builder_validate_definition([
    'title' => '<img src=x onerror=alert(1)>',
    'description' => 'Tell us what you need.',
    'submit_label' => 'Send',
    'success_message' => 'Received',
    'fields' => [
        ['key' => 'email_address', 'label' => 'Email', 'type' => 'email', 'required' => true, 'options' => []],
        ['key' => 'category', 'label' => 'Category', 'type' => 'select', 'required' => true, 'options' => ['Repair', 'Other']],
    ],
]);
$renderedForm = form_builder_render($formDefinition, 7, 'csrf-token');
vb_test(str_contains($renderedForm, '&lt;img src=x onerror=alert(1)&gt;'), 'Form title was not escaped');
vb_test(!str_contains($renderedForm, '<img'), 'Form title introduced markup');
vb_test(str_contains($renderedForm, 'name="csrf" value="csrf-token"'), 'Form did not include the CSRF field');
vb_test(str_contains($renderedForm, 'name="answers[email_address]"'), 'Form field was not rendered');
vb_test(!str_contains($renderedForm, '<script'), 'Form renderer emitted executable content');
vb_test_reject(static fn() => form_builder_validate_fields([
    ['key' => 'duplicate', 'label' => 'One', 'type' => 'text', 'required' => false, 'options' => []],
    ['key' => 'duplicate', 'label' => 'Two', 'type' => 'text', 'required' => false, 'options' => []],
]), 'Duplicate form keys were accepted');
vb_test_reject(static fn() => form_builder_validate_fields([
    ['key' => 'unsafe_type', 'label' => 'Unsafe', 'type' => 'html', 'required' => false, 'options' => []],
]), 'Unsupported form field type was accepted');
vb_test_reject(static fn() => form_builder_validate_submission($formDefinition, [
    'email_address' => 'invalid-email', 'category' => 'Repair',
]), 'Invalid email answer was accepted');
vb_test_reject(static fn() => form_builder_validate_submission($formDefinition, [
    'email_address' => 'a@example.test', 'category' => 'Not an option',
]), 'Unlisted select answer was accepted');
$safeAnswers = form_builder_validate_submission($formDefinition, [
    'email_address' => 'a@example.test', 'category' => 'Repair',
]);
vb_test($safeAnswers['category']['value'] === 'Repair', 'Valid select answer was not normalized');

$divider = visual_builder_validate_config('divider', []);
vb_test(visual_builder_render_blocks([['type' => 'divider', 'config' => $divider]]) === '<hr class="vb-block vb-block--divider">', 'Divider was not rendered');
$spacer = visual_builder_validate_config('spacer', ['size' => 'large']);
vb_test(str_contains(visual_builder_render_blocks([['type' => 'spacer', 'config' => $spacer]]), 'vb-spacer--large'), 'Spacer size was not rendered');
vb_test_reject(
    static fn() => visual_builder_validate_config('spacer', ['size' => 'huge']),
    'Invalid spacer size was accepted'
);

echo "Visual Builder schema and safe-render tests passed\n";
