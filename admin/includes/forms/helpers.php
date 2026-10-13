<?php
/**
 * Shared admin form markup. Pages pass $values, $mode, and $field_errors.
 */

function form_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function form_values(): array
{
    return (isset($GLOBALS['values']) && is_array($GLOBALS['values'])) ? $GLOBALS['values'] : [];
}

function form_value(string $key, $default = '')
{
    $values = form_values();
    if (!array_key_exists($key, $values) || $values[$key] === null) {
        return $default;
    }
    return $values[$key];
}

function form_errors(): array
{
    $errors = (isset($GLOBALS['field_errors']) && is_array($GLOBALS['field_errors'])) ? $GLOBALS['field_errors'] : [];
    return array_filter($errors, static function ($message) {
        return $message !== '' && $message !== null;
    });
}

function form_error(string $key): string
{
    $errors = form_errors();
    return isset($errors[$key]) ? (string) $errors[$key] : '';
}

function form_on(string $key, bool $default = false): bool
{
    $values = form_values();
    if (!array_key_exists($key, $values)) {
        return $default;
    }
    $value = $values[$key];
    return $value === '1' || $value === 1 || $value === true || $value === 'on';
}

function form_summary(): void
{
    $errors = form_errors();
    if (!$errors) {
        return;
    }
    $first = array_key_first($errors);
    echo '<div class="form-summary" role="alert" tabindex="-1">';
    echo '<p><strong>Check the highlighted fields.</strong></p><ul>';
    foreach ($errors as $key => $message) {
        $target = ($key === '_form' || $key === '') ? '' : '#' . form_h($key);
        echo '<li>';
        if ($target !== '') {
            echo '<a href="' . $target . '">' . form_h($message) . '</a>';
        } else {
            echo form_h($message);
        }
        echo '</li>';
    }
    echo '</ul></div>';
    if ($first && $first !== '_form') {
        echo '<script>document.getElementById(' . json_encode($first) . ')&&document.getElementById(' . json_encode($first) . ').focus();</script>';
    }
}

function form_described(string $id, string $error, string $hint): string
{
    $ids = [];
    if ($hint !== '') {
        $ids[] = $id . '-hint';
    }
    if ($error !== '') {
        $ids[] = $id . '-error';
    }
    $attr = '';
    if ($ids) {
        $attr .= ' aria-describedby="' . form_h(implode(' ', $ids)) . '"';
    }
    if ($error !== '') {
        $attr .= ' aria-invalid="true"';
    }
    return $attr;
}

function form_messages(string $id, string $hint, string $error): void
{
    if ($hint !== '') {
        echo '<p class="form-hint" id="' . form_h($id) . '-hint">' . form_h($hint) . '</p>';
    }
    if ($error !== '') {
        echo '<p class="form-error" id="' . form_h($id) . '-error">' . form_h($error) . '</p>';
    }
}

function form_label(string $id, string $label, bool $required = false): void
{
    echo '<label class="form-label" for="' . form_h($id) . '">';
    echo form_h($label);
    if ($required) {
        echo ' <span class="form-req" aria-hidden="true">*</span>';
    }
    echo '</label>';
}

function form_text(string $id, string $name, string $label, array $opts = []): void
{
    $required = !empty($opts['required']);
    $type = $opts['type'] ?? 'text';
    $error = form_error($id);
    $hint = $opts['hint'] ?? '';
    $value = array_key_exists('value', $opts) ? $opts['value'] : form_value($name, $opts['default'] ?? '');
    $class = 'form-control' . ($error !== '' ? ' is-invalid' : '');
    echo '<div class="form-field">';
    form_label($id, $label, $required);
    if (!empty($opts['prefix']) || !empty($opts['suffix'])) {
        echo '<div class="input-group">';
        if (!empty($opts['prefix'])) {
            echo '<span class="input-affix">' . $opts['prefix'] . '</span>';
        }
    }
    echo '<input class="' . $class . '" type="' . form_h($type) . '" id="' . form_h($id) . '" name="' . form_h($name) . '" value="' . form_h($value) . '"';
    if ($required) {
        echo ' required';
    }
    if (!empty($opts['placeholder'])) {
        echo ' placeholder="' . form_h($opts['placeholder']) . '"';
    }
    if (isset($opts['min'])) {
        echo ' min="' . form_h($opts['min']) . '"';
    }
    if (isset($opts['max'])) {
        echo ' max="' . form_h($opts['max']) . '"';
    }
    if (isset($opts['step'])) {
        echo ' step="' . form_h($opts['step']) . '"';
    }
    if (!empty($opts['readonly'])) {
        echo ' readonly';
    }
    if (!empty($opts['accept'])) {
        echo ' accept="' . form_h($opts['accept']) . '"';
    }
    if (!empty($opts['extra'])) {
        echo ' ' . $opts['extra'];
    }
    echo form_described($id, $error, $hint) . '>';
    if (!empty($opts['prefix']) || !empty($opts['suffix'])) {
        if (!empty($opts['suffix'])) {
            echo '<span class="input-affix">' . $opts['suffix'] . '</span>';
        }
        echo '</div>';
    }
    form_messages($id, $hint, $error);
    echo '</div>';
}

function form_textarea(string $id, string $name, string $label, array $opts = []): void
{
    $required = !empty($opts['required']);
    $error = form_error($id);
    $hint = $opts['hint'] ?? '';
    $value = array_key_exists('value', $opts) ? $opts['value'] : form_value($name, '');
    $rows = (int) ($opts['rows'] ?? 4);
    echo '<div class="form-field">';
    form_label($id, $label, $required);
    echo '<textarea class="form-control' . ($error !== '' ? ' is-invalid' : '') . '" id="' . form_h($id) . '" name="' . form_h($name) . '" rows="' . $rows . '"';
    if ($required) {
        echo ' required';
    }
    if (!empty($opts['placeholder'])) {
        echo ' placeholder="' . form_h($opts['placeholder']) . '"';
    }
    echo form_described($id, $error, $hint) . '>' . form_h($value) . '</textarea>';
    form_messages($id, $hint, $error);
    echo '</div>';
}

function form_select(string $id, string $name, string $label, array $options, array $opts = []): void
{
    $required = !empty($opts['required']);
    $error = form_error($id);
    $hint = $opts['hint'] ?? '';
    $current = array_key_exists('value', $opts) ? (string) $opts['value'] : (string) form_value($name, $opts['default'] ?? '');
    echo '<div class="form-field">';
    form_label($id, $label, $required);
    echo '<select class="form-control' . ($error !== '' ? ' is-invalid' : '') . '" id="' . form_h($id) . '" name="' . form_h($name) . '"';
    if ($required) {
        echo ' required';
    }
    if (!empty($opts['extra'])) {
        echo ' ' . $opts['extra'];
    }
    echo form_described($id, $error, $hint) . '>';
    foreach ($options as $option) {
        if (isset($option['group'])) {
            echo '<optgroup label="' . form_h($option['group']) . '">';
            foreach ($option['options'] as $item) {
                form_option($item, $current);
            }
            echo '</optgroup>';
            continue;
        }
        form_option($option, $current);
    }
    echo '</select>';
    form_messages($id, $hint, $error);
    echo '</div>';
}

function form_option(array $option, string $current): void
{
    $value = (string) $option['value'];
    $selected = array_key_exists('selected', $option) ? $option['selected'] : ($value === $current);
    echo '<option value="' . form_h($value) . '"' . ($selected ? ' selected' : '') . '>' . form_h($option['label']) . '</option>';
}

function form_toggle(string $id, string $name, string $label, array $opts = []): void
{
    $checked = array_key_exists('checked', $opts) ? (bool) $opts['checked'] : form_on($name, !empty($opts['default']));
    $hint = $opts['hint'] ?? '';
    $value = $opts['value'] ?? null;
    echo '<div class="form-field">';
    echo '<label class="form-toggle" for="' . form_h($id) . '">';
    echo '<input type="checkbox" id="' . form_h($id) . '" name="' . form_h($name) . '"';
    if ($value !== null) {
        echo ' value="' . form_h($value) . '"';
    }
    if (!empty($opts['extra'])) {
        echo ' ' . $opts['extra'];
    }
    echo $checked ? ' checked' : '';
    echo '>';
    echo '<span class="form-toggle-ui" aria-hidden="true"></span>';
    echo '<span class="form-toggle-copy"><span class="form-toggle-label">' . form_h($label) . '</span>';
    if ($hint !== '') {
        echo '<span class="form-hint" id="' . form_h($id) . '-hint">' . form_h($hint) . '</span>';
    }
    echo '</span></label></div>';
}

function form_file(string $id, string $name, string $label, array $opts = []): void
{
    $error = form_error($id);
    $hint = $opts['hint'] ?? '';
    echo '<div class="form-field form-group">';
    form_label($id, $label, !empty($opts['required']));
    if (!empty($opts['before'])) {
        echo $opts['before'];
    }
    echo '<div class="file-control' . ($error !== '' ? ' is-invalid' : '') . '" id="' . form_h($id) . '_upload">';
    echo '<input class="file-control-input" type="file" id="' . form_h($id) . '" name="' . form_h($name) . '"';
    if (!empty($opts['accept'])) {
        echo ' accept="' . form_h($opts['accept']) . '"';
    }
    if (!empty($opts['extra'])) {
        echo ' ' . $opts['extra'];
    }
    echo form_described($id, $error, $hint) . '>';
    echo '<span class="file-control-button">Choose file</span>';
    echo '<span class="file-name" data-file-name>No file chosen</span>';
    echo '</div>';
    if (!empty($opts['preview_id'])) {
        echo '<div id="' . form_h($opts['preview_id']) . '" class="file-preview-slot"></div>';
    }
    form_messages($id, $hint, $error);
    echo '</div>';
}

function form_current_image(string $src, string $remove_name, string $alt = 'Current image'): string
{
    if ($src === '') {
        return '';
    }
    $html = '<div class="current-upload">';
    $html .= '<img src="../' . form_h($src) . '" alt="' . form_h($alt) . '">';
    $html .= '<input type="hidden" name="current_' . form_h($remove_name) . '" value="' . form_h($src) . '">';
    $html .= '<button type="button" class="btn btn-danger btn-sm" onclick="removeImage(\'' . form_h($remove_name) . '\')"><i class="fas fa-trash" aria-hidden="true"></i> Remove image</button>';
    $html .= '</div>';
    return $html;
}

function form_spy(array $sections): void
{
    echo '<nav class="form-spy" aria-label="On this form">';
    echo '<button type="button" class="form-spy-toggle" aria-expanded="false" aria-controls="form-spy-links">On this form</button>';
    echo '<div class="form-spy-links" id="form-spy-links">';
    foreach ($sections as $id => $label) {
        echo '<a href="#' . form_h($id) . '">' . form_h($label) . '</a>';
    }
    echo '</div></nav>';
}

function form_section_open(string $id, string $title, string $help = ''): void
{
    echo '<section class="form-card" id="' . form_h($id) . '">';
    echo '<h3>' . form_h($title) . '</h3>';
    if ($help !== '') {
        echo '<p class="form-card-help">' . form_h($help) . '</p>';
    }
}

function form_section_close(): void
{
    echo '</section>';
}

function form_bar(string $form_id, string $save, string $cancel, string $delete_html = ''): void
{
    echo '<div class="form-bar">';
    echo '<button type="submit" class="btn btn-primary" form="' . form_h($form_id) . '"><i class="fas fa-save" aria-hidden="true"></i> ' . form_h($save) . '</button>';
    echo '<a class="btn btn-secondary" href="' . form_h($cancel) . '">Cancel</a>';
    echo $delete_html;
    echo '</div>';
}
