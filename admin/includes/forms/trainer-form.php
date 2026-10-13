<?php
require_once __DIR__ . '/helpers.php';
$mode = $mode ?? 'add';
$form_id = $form_id ?? ($mode === 'edit' ? 'edit-trainer-form' : 'add-trainer-form');
$save_label = $save_label ?? ($mode === 'edit' ? 'Save changes' : 'Add trainer');
$cancel_url = $cancel_url ?? 'trainers.php';
$can_delete = $mode === 'edit' && (($_SESSION['admin_role'] ?? 'admin') !== 'manager') && !empty($record_id);
?>
<div class="form-page">
    <?php form_summary(); ?>
    <p class="form-intro">Fields with a star are required. A square headshot works best.</p>
    <div class="form-layout">
        <?php form_spy(['profile' => 'Profile', 'practice' => 'Practice', 'display' => 'Display']); ?>
        <form class="form-main" id="<?php echo form_h($form_id); ?>" method="POST" enctype="multipart/form-data" data-form-guard novalidate>
            <?php echo csrf_field(); ?>
            <?php form_section_open('profile', 'Profile', 'Name, title, and the photo shown on the site.'); ?>
            <?php form_text('name', 'name', 'Trainer name', ['required' => true, 'placeholder' => "Enter the trainer's full name"]); ?>
            <?php form_text('title', 'title', 'Professional title', ['required' => true, 'placeholder' => 'e.g., Senior HR Consultant']); ?>
            <?php form_text('experience_years', 'experience_years', 'Years of experience', ['type' => 'number', 'min' => '0', 'max' => '50', 'placeholder' => 'e.g., 15']); ?>
            <?php
            $image_before = '';
            if ($mode === 'edit' && form_value('image_path')) {
                $image_before = '<div class="current-upload"><img src="../' . form_h(form_value('image_path')) . '" alt="Current trainer photo">'
                    . '<label class="form-toggle" for="remove_image"><input type="checkbox" id="remove_image" name="remove_image" value="1"><span class="form-toggle-ui" aria-hidden="true"></span><span class="form-toggle-copy"><span class="form-toggle-label">Remove current image</span></span></label></div>';
            }
            form_file('image', 'image', 'Profile image', [
                'accept' => 'image/*',
                'hint' => 'Recommended size: 300×300px. JPG, PNG, GIF, or WebP.',
                'before' => $image_before,
                'preview_id' => 'image-preview',
            ]);
            ?>
            <?php form_section_close(); ?>

            <?php form_section_open('practice', 'Practice', 'Separate specialties and credentials with commas.'); ?>
            <?php form_textarea('description', 'description', 'Professional description', ['rows' => 4, 'placeholder' => "Describe the trainer's background, expertise, and achievements..."]); ?>
            <?php form_text('specialties', 'specialties', 'Specialties', ['placeholder' => 'Employee Relations, Training & Development', 'hint' => 'Separate multiple specialties with commas.']); ?>
            <?php form_text('credentials', 'credentials', 'Credentials and certifications', ['placeholder' => 'SPHR Certified, Master Trainer', 'hint' => 'Separate multiple credentials with commas.']); ?>
            <?php form_section_close(); ?>

            <?php form_section_open('display', 'Display', 'Controls where this trainer appears.'); ?>
            <?php form_text('display_order', 'display_order', 'Display order', ['type' => 'number', 'min' => '0', 'max' => '999', 'hint' => 'Lower numbers appear first.']); ?>
            <?php form_toggle('is_active', 'is_active', 'Active trainer', ['value' => '1', 'default' => $mode === 'add', 'hint' => 'Only active trainers are displayed on the website.']); ?>
            <?php form_section_close(); ?>
        </form>
    </div>
    <?php
    $delete = '';
    if ($can_delete) {
        $delete = '<form class="form-delete" method="POST" action="trainers.php">'
            . csrf_field()
            . '<input type="hidden" name="action" value="delete">'
            . '<input type="hidden" name="trainer_id" value="' . form_h($record_id) . '">'
            . '<button type="submit" class="btn btn-danger" data-confirm-title="Delete this trainer?" data-confirm="This action cannot be undone." data-confirm-ok="Delete" data-confirm-icon="fas fa-trash"><i class="fas fa-trash" aria-hidden="true"></i> Delete</button>'
            . '</form>';
    }
    form_bar($form_id, $save_label, $cancel_url, $delete);
    ?>
</div>
