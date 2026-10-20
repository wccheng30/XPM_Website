<?php
require_once __DIR__ . '/helpers.php';
$mode = $mode ?? 'add';
$form_id = $form_id ?? ($mode === 'edit' ? 'edit-programme-form' : 'add-programme-form');
$save_label = $save_label ?? ($mode === 'edit' ? 'Update programme' : 'Create programme');
$cancel_url = $cancel_url ?? 'programmes.php';
$can_delete = $mode === 'edit' && (($_SESSION['admin_role'] ?? 'admin') !== 'manager') && !empty($record_id);
if (!isset($questions)) {
    if ($mode === 'edit' && isset($question_configs)) {
        $questions = $question_configs;
    } elseif (isset($question_templates)) {
        $questions = $question_templates;
    } else {
        $questions = function_exists('getDefaultProgrammeQuestions') ? getDefaultProgrammeQuestions() : [];
    }
}

$programme_icons = [
    'Leadership and management' => [
        'fas fa-graduation-cap' => 'Graduation cap',
        'fas fa-crown' => 'Crown',
        'fas fa-star' => 'Star',
        'fas fa-trophy' => 'Trophy',
        'fas fa-medal' => 'Medal',
        'fas fa-user-tie' => 'Business person',
        'fas fa-users' => 'Team',
    ],
    'Analytics and data' => [
        'fas fa-chart-line' => 'Line chart',
        'fas fa-chart-bar' => 'Bar chart',
        'fas fa-chart-pie' => 'Pie chart',
        'fas fa-calculator' => 'Calculator',
        'fas fa-database' => 'Database',
        'fas fa-brain' => 'Brain',
    ],
    'Communication and marketing' => [
        'fas fa-bullhorn' => 'Bullhorn',
        'fas fa-comments' => 'Comments',
        'fas fa-phone' => 'Phone',
        'fas fa-envelope' => 'Envelope',
        'fas fa-video' => 'Video',
        'fas fa-microphone' => 'Microphone',
    ],
    'Project and task management' => [
        'fas fa-tasks' => 'Tasks',
        'fas fa-project-diagram' => 'Project diagram',
        'fas fa-calendar-check' => 'Calendar check',
        'fas fa-clock' => 'Clock',
        'fas fa-check-circle' => 'Check circle',
        'fas fa-list-check' => 'Checklist',
    ],
    'HR and recruitment' => [
        'fas fa-user-plus' => 'Add user',
        'fas fa-user-edit' => 'Edit user',
        'fas fa-handshake' => 'Handshake',
        'fas fa-briefcase' => 'Briefcase',
        'fas fa-id-card' => 'ID card',
        'fas fa-user-graduate' => 'Graduate',
    ],
    'Technology and skills' => [
        'fas fa-laptop-code' => 'Laptop code',
        'fas fa-code' => 'Code',
        'fas fa-cogs' => 'Cogs',
        'fas fa-tools' => 'Tools',
        'fas fa-lightbulb' => 'Lightbulb',
        'fas fa-rocket' => 'Rocket',
    ],
    'Finance and business' => [
        'fas fa-dollar-sign' => 'Dollar sign',
        'fas fa-chart-line' => 'Growth chart',
        'fas fa-building' => 'Building',
        'fas fa-coins' => 'Coins',
        'fas fa-piggy-bank' => 'Piggy bank',
        'fas fa-balance-scale' => 'Balance scale',
    ],
];
$known_icons = [];
foreach ($programme_icons as $group) {
    foreach (array_keys($group) as $icon_value) {
        $known_icons[$icon_value] = true;
    }
}
$current_icon = (string) form_value('programme_icon', 'fas fa-graduation-cap');
$icon_options = [];
if ($mode === 'edit') {
    $icon_options[] = ['value' => '', 'label' => 'Select an icon'];
}
if ($current_icon !== '' && $current_icon !== 'custom' && !isset($known_icons[$current_icon])) {
    $icon_options[] = ['value' => $current_icon, 'label' => $current_icon . ' (Custom)', 'selected' => true];
}
foreach ($programme_icons as $group_label => $icons) {
    $items = [];
    foreach ($icons as $icon_value => $icon_label) {
        $items[] = ['value' => $icon_value, 'label' => $icon_label];
    }
    $icon_options[] = ['group' => $group_label, 'options' => $items];
}
$icon_options[] = ['group' => 'Custom', 'options' => [['value' => 'custom', 'label' => 'Enter custom icon class']]];

$schedule = (string) form_value('schedule_type');
$schedule_options = [
    ['value' => '', 'label' => 'Choose one'],
    ['value' => 'in_person', 'label' => 'In person'],
    ['value' => 'online', 'label' => 'Online'],
    ['value' => 'hybrid', 'label' => 'Hybrid'],
];
if ($schedule !== '' && !in_array($schedule, ['in_person', 'online', 'hybrid'], true)) {
    $schedule_options[] = ['value' => $schedule, 'label' => $schedule, 'selected' => true];
}
?>
<div class="form-page">
    <?php form_summary(); ?>
    <p class="form-intro">Fields with a star are required. You can add images and extras after the first save.</p>
    <div class="form-layout">
        <?php form_spy([
            'basics' => 'Basics',
            'pricing' => 'Pricing',
            'schedule' => 'Schedule and trainers',
            'questions' => 'Registration questions',
            'images' => 'Images',
        ]); ?>
        <form class="form-main" id="<?php echo form_h($form_id); ?>" method="POST" action="" enctype="multipart/form-data" data-form-guard novalidate>
            <?php echo csrf_field(); ?>
            <?php form_section_open('basics', 'Basics', 'Title, format, and the description people see on the programme page.'); ?>
            <div class="form-grid">
                <?php form_text('title', 'title', 'Programme title', ['required' => true]); ?>
                <?php form_select('programme_type', 'programme_type', 'Programme type', [
                    ['value' => 'training', 'label' => 'Training programme'],
                    ['value' => 'webinar', 'label' => 'Webinar'],
                ], ['required' => true, 'default' => 'training']); ?>
            </div>
            <?php form_textarea('description', 'description', 'Programme description', ['required' => true, 'rows' => 4]); ?>
            <?php form_text('location', 'location', 'Location', ['required' => true, 'hint' => 'City or Remote.']); ?>
            <?php form_text('duration', 'duration', 'Duration', ['required' => true, 'placeholder' => 'e.g., 2 days', 'hint' => 'What visitors will read, for example 2 days.']); ?>
            <div class="form-grid">
                <?php form_text('category', 'category', 'Category', ['hint' => 'Enter the category for this programme.']); ?>
                <?php form_text('certification', 'certification', 'Certification', ['placeholder' => 'e.g., HRD Corp Certified', 'default' => 'HRD Corp Certified']); ?>
            </div>
            <?php form_text('programme_badge', 'programme_badge', 'Programme badge', ['placeholder' => 'e.g., Popular, Advanced, Essential']); ?>
            <?php form_textarea('modules', 'modules', 'Key modules', ['rows' => 4, 'placeholder' => "Strategic Planning\nChange Management\nTeam Leadership", 'hint' => 'Enter each key module on a separate line.']); ?>
            <?php form_select('programme_icon', 'programme_icon', 'Programme icon', $icon_options, ['hint' => 'The icon appears on programme cards and poster overlays.', 'default' => 'fas fa-graduation-cap']); ?>
            <div class="form-field" id="custom-icon-input" hidden>
                <label class="form-label" for="custom_icon_class">Custom icon class</label>
                <input class="form-control" type="text" id="custom_icon_class" name="custom_icon_class" placeholder="e.g., fas fa-custom-icon" value="<?php echo form_h(form_value('custom_icon_class')); ?>">
                <p class="form-hint">Enter a Font Awesome class such as fas fa-custom-icon.</p>
            </div>
            <div class="icon-preview" id="preview-icon"><i class="<?php echo form_h($current_icon !== '' && $current_icon !== 'custom' ? $current_icon : 'fas fa-graduation-cap'); ?>" aria-hidden="true"></i><span class="form-hint">Icon preview</span></div>
            <?php form_toggle('is_active', 'is_active', 'Active programme', ['value' => '1', 'default' => true, 'hint' => 'Active programmes are visible on the website.']); ?>
            <?php if ($mode === 'edit'): ?>
                <?php form_select('registration_status', 'registration_status', 'Registration status', [
                    ['value' => 'open', 'label' => 'Open - Accepting registrations'],
                    ['value' => 'closed', 'label' => 'Closed - No more registrations'],
                    ['value' => 'full', 'label' => 'Full - Maximum capacity reached'],
                ], ['hint' => 'Control whether users can register for this programme.', 'default' => 'open']); ?>
            <?php endif; ?>
            <?php form_section_close(); ?>

            <?php form_section_open('pricing', 'Pricing', 'The price participants pay. SST is calculated at 8%.'); ?>
            <div class="form-grid">
                <?php form_text('programme_price', 'programme_price', 'Current price', ['required' => true, 'prefix' => 'RM', 'placeholder' => 'e.g., 1500.00, Free, Contact for pricing', 'extra' => 'oninput="calculateTax()"', 'hint' => 'This is the price participants will pay.']); ?>
                <?php form_text('programme_original_price', 'programme_original_price', 'Original price', ['prefix' => 'RM', 'placeholder' => 'e.g., 2000.00', 'hint' => 'Original price before discount. Leave empty if there is no discount.']); ?>
            </div>
            <div class="form-grid">
                <?php form_select('tax_inclusive', 'tax_inclusive', 'Is the 8% SST already inside the price?', [
                    ['value' => '0', 'label' => 'No'],
                    ['value' => '1', 'label' => 'Yes'],
                ], ['extra' => 'onchange="calculateTax()"', 'default' => '0']); ?>
                <?php form_text('tax_amount', 'tax_amount', 'SST amount', ['type' => 'number', 'step' => '0.01', 'prefix' => 'RM', 'readonly' => true, 'placeholder' => 'Auto-calculated', 'hint' => 'SST amount is automatically calculated at 8%.']); ?>
            </div>
            <?php form_text('max_participants', 'max_participants', 'Max participants', ['type' => 'number', 'min' => '1', 'default' => '25']); ?>
            <?php form_section_close(); ?>

            <?php form_section_open('schedule', 'Schedule and trainers', 'When it runs, who owns it, and whether it needs a room.'); ?>
            <?php if (!function_exists('auth_is_trainer') || !auth_is_trainer()): ?>
                <?php
                $owner_pdo = (isset($pdo) && $pdo) ? $pdo : get_db_connection();
                $trainer_choices = ($owner_pdo && function_exists('auth_trainer_choices')) ? auth_trainer_choices($owner_pdo) : [];
                $owner_options = [['value' => '', 'label' => 'Admin owned']];
                foreach ($trainer_choices as $trainer_choice) {
                    $owner_options[] = [
                        'value' => (string) (int) $trainer_choice['id'],
                        'label' => (string) ($trainer_choice['display_name'] ?: $trainer_choice['email']),
                    ];
                }
                form_select('owner_user_id', 'owner_user_id', 'Trainer owner', $owner_options, ['hint' => 'Leave this as admin owned, or assign the programme to a trainer.']);
                ?>
            <?php endif; ?>
            <?php form_select('schedule_type', 'schedule_type', 'How people attend', $schedule_options); ?>
            <div class="form-grid">
                <?php form_text('start_date', 'start_date', 'Start date', ['type' => 'date']); ?>
                <div id="end_date_group">
                    <?php form_text('end_date', 'end_date', 'End date', ['type' => 'date']); ?>
                </div>
            </div>
            <div class="form-grid">
                <?php form_text('start_time', 'start_time', 'Start time', ['type' => 'time']); ?>
                <?php form_text('end_time', 'end_time', 'End time', ['type' => 'time']); ?>
            </div>
            <div class="schedule-preview" id="schedule_preview" hidden>
                <p class="form-label">Schedule preview</p>
                <div class="preview-box" id="preview_text"></div>
            </div>
            <?php form_toggle('requires_facility', 'requires_facility', 'Requires facility booking', ['value' => '1', 'extra' => 'onchange="toggleFacilityOptions()"', 'hint' => 'This programme needs a physical facility such as a conference room or training room.']); ?>
            <div id="facility-options" hidden>
                <?php form_select('facility_type', 'facility_type', 'Facility type', [
                    ['value' => '', 'label' => 'Select facility type'],
                    ['value' => 'conference_room', 'label' => 'Conference room'],
                    ['value' => 'training_room', 'label' => 'Training room'],
                    ['value' => 'breakout_area', 'label' => 'Breakout area'],
                ]); ?>
                <?php form_toggle('auto_create_facility_booking', 'auto_create_facility_booking', 'Auto-create facility booking', ['value' => '1', 'default' => true, 'hint' => 'Automatically create a facility booking when this programme is saved.']); ?>
                <p class="form-hint">The start date, start time, and end time are used for that booking.</p>
            </div>
            <?php form_section_close(); ?>

            <?php form_section_open('questions', 'Registration questions', 'Choose which questions appear on the registration form, their type, and whether each one is required.'); ?>
            <?php foreach ($questions as $index => $question): ?>
                <?php
                $postedConfig = $_POST['question_configs'][$index] ?? [];
                $isEnabled = isset($postedConfig['is_enabled']) ? 1 : (int) ($question['is_enabled'] ?? 0);
                $isRequired = isset($postedConfig['is_required']) ? 1 : (int) ($question['is_required'] ?? 0);
                $inputType = $postedConfig['input_type'] ?? ($question['input_type'] ?? 'text');
                $fieldLabel = $postedConfig['field_label'] ?? ($question['field_label'] ?? '');
                $fieldOptions = $postedConfig['field_options'] ?? ($question['field_options'] ?? '');
                ?>
                <div class="question-card">
                    <input type="hidden" name="question_configs[<?php echo (int) $index; ?>][field_key]" value="<?php echo form_h($question['field_key'] ?? ''); ?>">
                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-label" for="q<?php echo (int) $index; ?>_label">Question label</label>
                            <input class="form-control" type="text" id="q<?php echo (int) $index; ?>_label" name="question_configs[<?php echo (int) $index; ?>][field_label]" value="<?php echo form_h($fieldLabel); ?>">
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="q<?php echo (int) $index; ?>_type">Input type</label>
                            <select class="form-control" id="q<?php echo (int) $index; ?>_type" name="question_configs[<?php echo (int) $index; ?>][input_type]">
                                <?php foreach (['text' => 'Text input', 'email' => 'Email input', 'tel' => 'Phone input', 'textarea' => 'Textarea', 'select' => 'Select dropdown'] as $value => $label): ?>
                                    <option value="<?php echo form_h($value); ?>" <?php echo $inputType === $value ? 'selected' : ''; ?>><?php echo form_h($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-field question-options"<?php echo $inputType === 'select' ? '' : ' hidden'; ?>>
                        <label class="form-label" for="q<?php echo (int) $index; ?>_options">Dropdown options</label>
                        <textarea class="form-control" id="q<?php echo (int) $index; ?>_options" rows="2" name="question_configs[<?php echo (int) $index; ?>][field_options]" placeholder="value|Label (one option per line)"><?php echo form_h($fieldOptions); ?></textarea>
                        <p class="form-hint">Used when the input type is a dropdown. One option per line, written as value|Label.</p>
                    </div>
                    <div class="form-grid">
                        <?php form_toggle('q' . (int) $index . '_enabled', 'question_configs[' . (int) $index . '][is_enabled]', 'Show in registration form', ['value' => '1', 'checked' => (bool) $isEnabled]); ?>
                        <?php form_toggle('q' . (int) $index . '_required', 'question_configs[' . (int) $index . '][is_required]', 'Required field', ['value' => '1', 'checked' => (bool) $isRequired]); ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php form_section_close(); ?>

            <?php form_section_open('images', 'Images', 'A banner places the programme on the homepage slideshow.'); ?>
            <?php
            $uploads = [
                'banner_image' => ['Banner', 'Featured poster shown at the top of the homepage. Square 1080×1080px recommended (wide banners also work). PNG, JPG, GIF, or WebP up to 5MB.', 'Current banner'],
                'poster_image' => ['Primary poster', 'PNG, JPG, or GIF up to 5MB.', 'Current poster'],
                'poster_image_2' => ['Secondary poster', 'PNG, JPG, or GIF up to 5MB. Optional.', 'Current secondary poster'],
            ];
            foreach ($uploads as $field => $meta) {
                $before = '';
                if ($mode === 'edit') {
                    if (form_value($field)) {
                        $before .= form_current_image((string) form_value($field), $field, $meta[2]);
                    }
                    $before .= '<input type="hidden" id="remove_' . form_h($field) . '" name="remove_' . form_h($field) . '" value="0">';
                }
                form_file($field, $field, $meta[0], [
                    'accept' => 'image/*',
                    'extra' => 'onchange="handleFileUpload(this)"',
                    'hint' => $meta[1],
                    'before' => $before,
                    'preview_id' => $field . '_preview',
                ]);
            }
            ?>
            <?php form_section_close(); ?>
        </form>
    </div>
    <?php
    $delete = '';
    if ($can_delete) {
        $delete = '<form class="form-delete" method="POST" action="programme-actions.php">'
            . csrf_field()
            . '<input type="hidden" name="action" value="delete">'
            . '<input type="hidden" name="programme_id" value="' . form_h($record_id) . '">'
            . '<button type="submit" class="btn btn-danger" data-confirm-title="Delete this programme?" data-confirm="This action cannot be undone." data-confirm-ok="Delete" data-confirm-icon="fas fa-trash"><i class="fas fa-trash" aria-hidden="true"></i> Delete</button>'
            . '</form>';
    }
    form_bar($form_id, $save_label, $cancel_url, $delete);
    ?>
</div>
