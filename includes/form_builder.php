<?php
declare(strict_types=1);

require_once __DIR__ . '/db_helpers.php';
require_once __DIR__ . '/visual_builder.php';

const FORM_BUILDER_MAX_FIELDS = 20;
const FORM_BUILDER_FIELD_TYPES = ['text', 'email', 'tel', 'textarea', 'select', 'checkbox'];

function form_builder_text(mixed $value, int $max, bool $optional = false, bool $multiline = false): string
{
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
        throw new InvalidArgumentException('Form text must be valid UTF-8');
    }
    $value = str_replace(["\r\n", "\r"], "\n", trim($value));
    if (!$multiline) {
        $value = (string)preg_replace('/\s+/u', ' ', $value);
    }
    if ((!$optional && $value === '') || mb_strlen($value, 'UTF-8') > $max) {
        throw new InvalidArgumentException('Form text is empty or too long');
    }
    return $value;
}

function form_builder_validate_key(mixed $key): string
{
    if (!is_string($key) || preg_match('/\A[a-z][a-z0-9_]{1,39}\z/', $key) !== 1) {
        throw new InvalidArgumentException('Invalid form field key');
    }
    return $key;
}

function form_builder_validate_form_key(mixed $key): string
{
    if (!is_string($key) || strlen($key) > 80 || preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $key) !== 1) {
        throw new InvalidArgumentException('Form key must use lowercase letters, numbers, and hyphens');
    }
    return $key;
}

function form_builder_normalize_posted_definition(mixed $posted): array
{
    if (!is_array($posted) || !is_array($posted['fields'] ?? null)) {
        throw new InvalidArgumentException('Form fields are required');
    }
    $fields = [];
    foreach (array_values($posted['fields']) as $field) {
        if (!is_array($field)) throw new InvalidArgumentException('Invalid posted form field');
        $type = is_string($field['type'] ?? null) ? $field['type'] : '';
        $options = [];
        if ($type === 'select') {
            $rawOptions = is_string($field['options'] ?? null) ? $field['options'] : '';
            $options = array_values(array_filter(array_map('trim', preg_split('/\R/u', $rawOptions) ?: []), static fn(string $value): bool => $value !== ''));
        }
        $fields[] = [
            'key' => $field['key'] ?? null,
            'label' => $field['label'] ?? null,
            'type' => $type,
            'required' => isset($field['required']) && (string)$field['required'] === '1',
            'options' => $options,
        ];
    }
    return form_builder_validate_definition([
        'title' => $posted['title'] ?? null,
        'description' => $posted['description'] ?? '',
        'submit_label' => $posted['submit_label'] ?? null,
        'success_message' => $posted['success_message'] ?? null,
        'fields' => $fields,
    ]);
}

/** @return list<array{key:string,label:string,type:string,required:bool,options:list<string>}> */
function form_builder_validate_fields(mixed $fields): array
{
    if (!is_array($fields) || count($fields) < 1 || count($fields) > FORM_BUILDER_MAX_FIELDS) {
        throw new InvalidArgumentException('A form must contain between 1 and ' . FORM_BUILDER_MAX_FIELDS . ' fields');
    }
    $validated = [];
    $seen = [];
    foreach (array_values($fields) as $field) {
        if (!is_array($field) || array_diff(array_keys($field), ['key', 'label', 'type', 'required', 'options'])) {
            throw new InvalidArgumentException('Invalid form field schema');
        }
        $key = form_builder_validate_key($field['key'] ?? null);
        if (isset($seen[$key])) {
            throw new InvalidArgumentException('Form field keys must be unique');
        }
        $seen[$key] = true;
        $label = form_builder_text($field['label'] ?? null, 100);
        $type = $field['type'] ?? null;
        if (!is_string($type) || !in_array($type, FORM_BUILDER_FIELD_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported form field type');
        }
        if (!is_bool($field['required'] ?? null)) {
            throw new InvalidArgumentException('Form required flag must be boolean');
        }
        $options = $field['options'] ?? [];
        if ($type === 'select') {
            if (!is_array($options) || count($options) < 2 || count($options) > 20) {
                throw new InvalidArgumentException('Select fields require 2 to 20 options');
            }
            $options = array_map(static fn($option): string => form_builder_text($option, 100), array_values($options));
            if (count(array_unique($options, SORT_STRING)) !== count($options)) {
                throw new InvalidArgumentException('Select options must be unique');
            }
        } elseif ($options !== []) {
            throw new InvalidArgumentException('Only select fields may define options');
        }
        $validated[] = ['key' => $key, 'label' => $label, 'type' => $type, 'required' => $field['required'], 'options' => $options];
    }
    return $validated;
}

/** @return array{title:string,description:string,submit_label:string,success_message:string,fields:list<array{key:string,label:string,type:string,required:bool,options:list<string>}>} */
function form_builder_validate_definition(mixed $definition): array
{
    if (!is_array($definition) || array_diff(array_keys($definition), ['title', 'description', 'submit_label', 'success_message', 'fields'])) {
        throw new InvalidArgumentException('Invalid form definition');
    }
    return [
        'title' => form_builder_text($definition['title'] ?? null, 200),
        'description' => form_builder_text($definition['description'] ?? '', 1000, true, true),
        'submit_label' => form_builder_text($definition['submit_label'] ?? null, 80),
        'success_message' => form_builder_text($definition['success_message'] ?? null, 300),
        'fields' => form_builder_validate_fields($definition['fields'] ?? null),
    ];
}

function form_builder_definition_from_json(mixed $raw): array
{
    if (!is_string($raw) || $raw === '') {
        throw new InvalidArgumentException('Form definition is missing');
    }
    try {
        $decoded = json_decode($raw, true, 24, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new InvalidArgumentException('Form definition JSON is invalid', 0, $e);
    }
    return form_builder_validate_definition($decoded);
}

function form_builder_default_definition(string $title = 'New form'): array
{
    return [
        'title' => $title,
        'description' => '',
        'submit_label' => 'Send',
        'success_message' => 'Thank you. Your response has been received.',
        'fields' => [[
            'key' => 'full_name', 'label' => 'Full name', 'type' => 'text', 'required' => true, 'options' => [],
        ]],
    ];
}

function form_builder_load(mysqli $conn, int $formId): ?array
{
    if ($formId < 1) return null;
    $row = db_one($conn, 'SELECT id, form_key, draft_json, published_json, status FROM site_forms WHERE id = ? LIMIT 1', 'i', [$formId]);
    if (!$row) return null;
    try {
        $row['draft'] = form_builder_definition_from_json($row['draft_json']);
        $row['published'] = $row['published_json'] !== null ? form_builder_definition_from_json($row['published_json']) : null;
    } catch (InvalidArgumentException $e) {
        error_log('Invalid stored form ' . $formId . ': ' . $e->getMessage());
        return null;
    }
    return $row;
}

/** @param list<array<string,mixed>> $blocks @return array<int,array<string,mixed>> */
function form_builder_page_forms(mysqli $conn, array $blocks, string $version): array
{
    if (!in_array($version, ['draft', 'published'], true)) {
        throw new InvalidArgumentException('Invalid form version');
    }
    $forms = [];
    foreach ($blocks as $block) {
        if (($block['type'] ?? null) !== 'form' || empty($block['is_visible']) || !is_array($block['config'] ?? null)) continue;
        $formId = (int)($block['config']['form_id'] ?? 0);
        if ($formId < 1 || isset($forms[$formId])) continue;
        $form = form_builder_load($conn, $formId);
        if (!$form) continue;
        $definition = $version === 'draft' ? $form['draft'] : ($form['status'] === 'published' ? $form['published'] : null);
        if ($definition !== null) $forms[$formId] = $definition;
    }
    return $forms;
}

/** @param list<array<string,mixed>> $blocks */
function form_builder_require_published_references(mysqli $conn, array $blocks): void
{
    foreach ($blocks as $block) {
        if (($block['type'] ?? null) !== 'form' || empty($block['is_visible'])) continue;
        $form = form_builder_load($conn, (int)($block['config']['form_id'] ?? 0));
        if (!$form || $form['status'] !== 'published' || $form['published'] === null) {
            throw new InvalidArgumentException('Publish every embedded form before publishing this page');
        }
    }
}

/** Prevent a published CMS page from embedding a form that is being unpublished. */
function form_builder_require_unreferenced_from_published_pages(mysqli $conn, int $formId): void
{
    $rows = db_all(
        $conn,
        "SELECT b.config_json FROM site_blocks b
         INNER JOIN site_pages p ON b.page_key = CONCAT('page_', p.id) AND p.status = 'published'
         WHERE b.block_type = 'form' AND b.status = 'published' AND b.is_visible = 1"
    );
    foreach ($rows as $row) {
        try {
            $config = json_decode((string)$row['config_json'], true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            continue;
        }
        if (is_array($config) && (int)($config['form_id'] ?? 0) === $formId) {
            throw new InvalidArgumentException('Unpublish pages embedding this form before unpublishing the form');
        }
    }
}

/** @return array<string,array{label:string,type:string,value:string}> */
function form_builder_validate_submission(array $definition, mixed $input): array
{
    $definition = form_builder_validate_definition($definition);
    if (!is_array($input) || array_diff(array_keys($input), array_column($definition['fields'], 'key'))) {
        throw new InvalidArgumentException('Unexpected form submission fields');
    }
    $answers = [];
    foreach ($definition['fields'] as $field) {
        $value = $input[$field['key']] ?? '';
        if ($field['type'] === 'checkbox') {
            if (!in_array($value, ['', '1', 1], true)) throw new InvalidArgumentException('Invalid checkbox value');
            $value = (string)$value;
        } elseif (!is_string($value)) {
            throw new InvalidArgumentException('Form answers must be strings');
        }
        $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
        $max = $field['type'] === 'textarea' ? 2000 : 255;
        if (mb_strlen($value, 'UTF-8') > $max || ($field['required'] && $value === '')) {
            throw new InvalidArgumentException('A required form answer is missing or too long');
        }
        if ($value !== '' && $field['type'] === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Invalid email address');
        }
        if ($value !== '' && $field['type'] === 'tel' && preg_match('/\A\+?[0-9(). -]{5,25}\z/', $value) !== 1) {
            throw new InvalidArgumentException('Invalid telephone number');
        }
        if ($field['type'] === 'select' && $value !== '' && !in_array($value, $field['options'], true)) {
            throw new InvalidArgumentException('Invalid select option');
        }
        $answers[$field['key']] = ['label' => $field['label'], 'type' => $field['type'], 'value' => $value];
    }
    return $answers;
}

function form_builder_page_contains(array $blocks, int $formId): bool
{
    foreach ($blocks as $block) {
        if (($block['type'] ?? null) === 'form'
            && !empty($block['is_visible'])
            && (int)($block['config']['form_id'] ?? 0) === $formId) {
            return true;
        }
    }
    return false;
}

function form_builder_submission_rate_limited(mysqli $conn, string $sessionHash): bool
{
    $row = db_one(
        $conn,
        'SELECT COUNT(*) AS total FROM site_form_submissions WHERE session_hash = ? AND submitted_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)',
        's',
        [$sessionHash]
    );
    return (int)($row['total'] ?? 0) >= 5;
}

function form_builder_save_submission(mysqli $conn, int $formId, string $sessionHash, array $answers): void
{
    $json = json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    db_exec(
        $conn,
        'INSERT INTO site_form_submissions (form_id, data_json, session_hash) VALUES (?, ?, ?)',
        'iss',
        [$formId, $json, $sessionHash]
    );
}

function form_builder_render(array $definition, int $formId, string $csrfToken, bool $preview = false, bool $submitted = false): string
{
    $definition = form_builder_validate_definition($definition);
    $escape = static fn(string $value): string => visual_builder_escape($value);
    $html = '<section class="vb-block vb-block--form"><h2>' . $escape($definition['title']) . '</h2>';
    if ($definition['description'] !== '') {
        $html .= '<p class="vb-form-description">' . nl2br($escape($definition['description']), false) . '</p>';
    }
    if ($submitted) {
        $html .= '<p class="vb-form-success" role="status">' . $escape($definition['success_message']) . '</p>';
    } elseif ($preview) {
        $html .= '<p class="vb-form-preview-note">' . $escape('Form is disabled in draft preview.') . '</p>';
    } else {
        $html .= '<form class="vb-form" method="post">'
            . '<input type="hidden" name="form_submit" value="1">'
            . '<input type="hidden" name="form_id" value="' . $formId . '">'
            . '<input type="hidden" name="csrf" value="' . $escape($csrfToken) . '">'
            . '<div class="vb-form-honeypot" aria-hidden="true"><label>Leave this field empty<input name="website" tabindex="-1" autocomplete="off"></label></div>';
        foreach ($definition['fields'] as $field) {
            $name = 'answers[' . $field['key'] . ']';
            $required = $field['required'] ? ' required' : '';
            $id = 'site-form-' . $formId . '-' . $field['key'];
            $html .= '<div class="vb-form-field">';
            if ($field['type'] === 'checkbox') {
                $html .= '<label for="' . $id . '"><input id="' . $id . '" name="' . $name . '" type="checkbox" value="1"' . $required . '> ' . $escape($field['label']) . '</label>';
            } else {
                $html .= '<label for="' . $id . '">' . $escape($field['label']) . ($field['required'] ? ' <span aria-hidden="true">*</span>' : '') . '</label>';
                if ($field['type'] === 'textarea') {
                    $html .= '<textarea id="' . $id . '" name="' . $name . '" maxlength="2000"' . $required . '></textarea>';
                } elseif ($field['type'] === 'select') {
                    $html .= '<select id="' . $id . '" name="' . $name . '"' . $required . '><option value="">—</option>';
                    foreach ($field['options'] as $option) $html .= '<option value="' . $escape($option) . '">' . $escape($option) . '</option>';
                    $html .= '</select>';
                } else {
                    $inputType = in_array($field['type'], ['email', 'tel'], true) ? $field['type'] : 'text';
                    $html .= '<input id="' . $id . '" name="' . $name . '" type="' . $inputType . '" maxlength="255"' . $required . '>';
                }
            }
            $html .= '</div>';
        }
        $html .= '<button type="submit">' . $escape($definition['submit_label']) . '</button></form>';
    }
    return $html . '</section>';
}
