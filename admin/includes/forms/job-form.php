<?php
require_once __DIR__ . '/helpers.php';
$mode = $mode ?? 'add';
$form_id = $form_id ?? ($mode === 'edit' ? 'edit-job-form' : 'add-job-form');
$save_label = $save_label ?? ($mode === 'edit' ? 'Update job' : 'Post job');
$cancel_url = $cancel_url ?? 'jobs.php';
$can_delete = $mode === 'edit' && (($_SESSION['admin_role'] ?? 'admin') !== 'manager') && !empty($record_id);

$predefined_types = ['Full-time', 'Part-time', 'Contract', 'Internship', 'Remote', 'On Site'];
$type_value = (string) form_value('type');
$is_custom_type = $mode === 'edit' ? ($type_value !== '' && !in_array($type_value, $predefined_types, true)) : ($type_value === 'custom');
$custom_type_value = '';
if ($is_custom_type) {
    $custom_type_value = $type_value === 'custom' ? (string) form_value('custom_job_type') : $type_value;
} else {
    $custom_type_value = (string) form_value('custom_job_type');
}

$job_icons = [
    'Business and professional' => [
        'fas fa-briefcase' => 'Briefcase',
        'fas fa-user-tie' => 'Business person',
        'fas fa-chalkboard-teacher' => 'Teacher',
        'fas fa-users' => 'Team',
        'fas fa-handshake' => 'Handshake',
    ],
    'Creative and design' => [
        'fas fa-pen-fancy' => 'Pen fancy',
        'fas fa-palette' => 'Palette',
        'fas fa-camera' => 'Camera',
        'fas fa-video' => 'Video',
        'fas fa-music' => 'Music',
    ],
    'Technology' => [
        'fas fa-laptop-code' => 'Laptop code',
        'fas fa-code' => 'Code',
        'fas fa-database' => 'Database',
        'fas fa-mobile-alt' => 'Mobile',
        'fas fa-network-wired' => 'Network',
    ],
    'Management and leadership' => [
        'fas fa-crown' => 'Crown',
        'fas fa-star' => 'Star',
        'fas fa-trophy' => 'Trophy',
        'fas fa-medal' => 'Medal',
        'fas fa-chess-king' => 'Chess king',
    ],
    'Support and service' => [
        'fas fa-tools' => 'Tools',
        'fas fa-wrench' => 'Wrench',
        'fas fa-cog' => 'Cog',
        'fas fa-tasks' => 'Tasks',
        'fas fa-clipboard-list' => 'Clipboard',
    ],
];
$known_icons = [];
foreach ($job_icons as $group) {
    $known_icons = array_merge($known_icons, array_keys($group));
}
$current_icon = (string) form_value('job_icon', 'fas fa-briefcase');
$icon_options = [];
if ($current_icon !== '' && $current_icon !== 'custom' && !in_array($current_icon, $known_icons, true)) {
    $icon_options[] = ['value' => $current_icon, 'label' => $current_icon . ' (Custom)', 'selected' => true];
}
foreach ($job_icons as $group_label => $icons) {
    $items = [];
    foreach ($icons as $icon_value => $icon_label) {
        $items[] = ['value' => $icon_value, 'label' => $icon_label];
    }
    $icon_options[] = ['group' => $group_label, 'options' => $items];
}
$icon_options[] = ['group' => 'Custom', 'options' => [['value' => 'custom', 'label' => 'Custom icon']]];
$type_options = [['value' => '', 'label' => 'Select job type']];
foreach ($predefined_types as $job_type) {
    $type_options[] = ['value' => $job_type, 'label' => $job_type, 'selected' => !$is_custom_type && $type_value === $job_type];
}
$type_options[] = ['value' => 'custom', 'label' => 'Custom type', 'selected' => $is_custom_type];
?>
<div class="form-page">
    <?php form_summary(); ?>
    <p class="form-intro">Fields with a star are required. You can add images and extras after the first save.</p>
    <div class="form-layout">
        <?php form_spy(['basics' => 'Basics', 'schedule' => 'Schedule', 'images' => 'Images', 'listing' => 'Listing']); ?>
        <form class="form-main" id="<?php echo form_h($form_id); ?>" method="POST" enctype="multipart/form-data" data-form-guard novalidate>
            <?php echo csrf_field(); ?>
            <?php form_section_open('basics', 'Basics', 'The title, location, and description visitors read first.'); ?>
            <div class="form-grid">
                <?php form_text('title', 'title', 'Job title', ['required' => true]); ?>
                <?php form_text('location', 'location', 'Location', ['required' => true, 'hint' => 'City or Remote.']); ?>
            </div>
            <?php form_select('type', 'type', 'Job type', $type_options, ['required' => true]); ?>
            <div class="form-field" id="custom-type-input"<?php echo $is_custom_type ? '' : ' hidden'; ?>>
                <label class="form-label" for="custom_job_type">Custom job type</label>
                <input class="form-control" type="text" id="custom_job_type" name="custom_job_type" placeholder="e.g., Freelance, Temporary, Seasonal" value="<?php echo form_h($custom_type_value); ?>">
                <p class="form-hint" id="custom_job_type-hint">Enter a custom job type such as Freelance, Temporary, or Seasonal.</p>
            </div>
            <?php form_textarea('description', 'description', 'Job description', ['required' => true, 'rows' => 6, 'placeholder' => 'Describe the role, responsibilities, and what the job entails...']); ?>
            <?php form_text('company_name', 'company_name', 'Company name', ['placeholder' => 'Enter company name']); ?>
            <?php form_section_close(); ?>

            <?php form_section_open('schedule', 'Schedule', 'Leave hours blank when the company did not specify them.'); ?>
            <?php form_toggle('hours_not_specified', 'hours_not_specified', 'Work hours not specified by company', ['hint' => 'Check this if the company did not provide specific work hours.']); ?>
            <div id="work-hours-section" class="form-grid">
                <?php form_text('start_time', 'start_time', 'Work start time', ['type' => 'time', 'hint' => 'Typical work start time.']); ?>
                <?php form_text('end_time', 'end_time', 'Work end time', ['type' => 'time', 'hint' => 'Typical work end time.']); ?>
            </div>
            <?php form_section_close(); ?>

            <?php form_section_open('images', 'Images', 'A square logo with a transparent background works best.'); ?>
            <?php
            $logo_before = '';
            if ($mode === 'edit' && form_value('poster_image')) {
                $logo_before = form_current_image((string) form_value('poster_image'), 'poster_image', 'Current logo');
            }
            if ($mode === 'edit') {
                $logo_before .= '<input type="hidden" id="remove_poster_image" name="remove_poster_image" value="0">';
            }
            form_file('poster_image', 'poster_image', 'Company logo', [
                'accept' => 'image/*',
                'hint' => 'Shown with the job. Recommended 200×200px. PNG, JPG, GIF, or WebP.',
                'before' => $logo_before,
                'preview_id' => 'poster_image_preview',
            ]);
            ?>
            <?php form_section_close(); ?>

            <?php form_section_open('listing', 'Listing', 'Icon, pay, and the requirements shown under the description.'); ?>
            <?php form_select('job_icon', 'job_icon', 'Job icon', $icon_options, ['hint' => 'Select an icon that represents this job.']); ?>
            <div class="form-field" id="custom-icon-input" hidden>
                <label class="form-label" for="custom_icon_class">Custom icon class</label>
                <input class="form-control" type="text" id="custom_icon_class" name="custom_icon_class" placeholder="e.g., fas fa-rocket" value="<?php echo form_h(form_value('custom_icon_class')); ?>">
                <p class="form-hint">Enter a Font Awesome class such as fas fa-rocket.</p>
            </div>
            <div class="icon-preview" id="preview-icon"><i class="<?php echo form_h($current_icon !== '' && $current_icon !== 'custom' ? $current_icon : 'fas fa-briefcase'); ?>" aria-hidden="true"></i><span class="form-hint">Icon preview</span></div>
            <div class="form-grid">
                <?php form_text('job_badge', 'job_badge', 'Job badge', ['placeholder' => 'e.g., New, Senior, Urgent, Remote']); ?>
                <?php form_text('salary_range', 'salary_range', 'Salary range', ['placeholder' => 'e.g., RM 3,000 - RM 5,000']); ?>
            </div>
            <?php form_text('experience_level', 'experience_level', 'Experience level', ['placeholder' => 'e.g., Entry Level, Mid Level, Senior Level', 'hint' => 'Enter the experience level required for this position.']); ?>
            <?php form_textarea('requirements', 'requirements', 'Job requirements', ['rows' => 6, 'placeholder' => 'List the qualifications, skills, and experience required...', 'hint' => 'Optional. Visitors see this under the job description.']); ?>
            <?php form_toggle('is_active', 'is_active', 'Active job posting', ['value' => '1', 'default' => $mode === 'add', 'hint' => 'Active jobs are visible on the website.']); ?>
            <?php form_section_close(); ?>
        </form>
    </div>
    <?php
    $delete = '';
    if ($can_delete) {
        $delete = '<form class="form-delete" method="POST" action="job-actions.php">'
            . csrf_field()
            . '<input type="hidden" name="action" value="delete">'
            . '<input type="hidden" name="job_id" value="' . form_h($record_id) . '">'
            . '<button type="submit" class="btn btn-danger" data-confirm-title="Delete this job?" data-confirm="This action cannot be undone." data-confirm-ok="Delete" data-confirm-icon="fas fa-trash"><i class="fas fa-trash" aria-hidden="true"></i> Delete</button>'
            . '</form>';
    }
    form_bar($form_id, $save_label, $cancel_url, $delete);
    ?>
</div>
