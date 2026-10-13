<?php
require_once __DIR__ . '/helpers.php';
$mode = $mode ?? 'add';
$form_id = $form_id ?? ($mode === 'edit' ? 'edit-blacklist-form' : 'add-blacklist-form');
$save_label = $save_label ?? ($mode === 'edit' ? 'Save changes' : 'Add to blacklist');
$cancel_url = $cancel_url ?? 'blacklist.php';
$can_delete = $mode === 'edit' && (($_SESSION['admin_role'] ?? 'admin') !== 'manager') && !empty($record_id);
?>
<div class="form-page">
    <?php form_summary(); ?>
    <p class="form-intro">Fields with a star are required.</p>
    <div class="form-layout">
        <?php form_spy(['person' => 'Person', 'reason' => 'Reason']); ?>
        <form class="form-main" id="<?php echo form_h($form_id); ?>" method="POST" data-form-guard novalidate>
            <?php echo csrf_field(); ?>
            <?php form_section_open('person', 'Person', 'Who is being added, and how to reach them.'); ?>
            <?php form_text('name', 'name', 'Name', ['required' => true, 'placeholder' => 'Enter full name']); ?>
            <?php form_text('position', 'position', 'Position applied', ['placeholder' => 'e.g., Senior Developer', 'hint' => 'The role they applied for, if this came from a job application.']); ?>
            <?php form_text('phone', 'phone', 'Phone number', ['placeholder' => 'e.g., +60123456789', 'prefix' => '<i class="fas fa-phone" aria-hidden="true"></i>', 'hint' => 'Optional. Use digits, spaces, and a leading + if needed.']); ?>
            <?php form_text('date', 'date', 'Date', ['type' => 'date', 'hint' => 'The date this entry should be recorded.']); ?>
            <?php form_section_close(); ?>
            <?php form_section_open('reason', 'Reason', 'Say why this person is on the blacklist.'); ?>
            <?php form_textarea('reason', 'reason', 'Reason for blacklist', ['rows' => 5, 'placeholder' => 'Describe the reason for blacklisting...']); ?>
            <?php form_section_close(); ?>
        </form>
    </div>
    <?php
    $delete = '';
    if ($can_delete) {
        $delete = '<form class="form-delete" method="POST" action="blacklist.php">'
            . csrf_field()
            . '<input type="hidden" name="action" value="delete">'
            . '<input type="hidden" name="trainer_id" value="' . form_h($record_id) . '">'
            . '<button type="submit" class="btn btn-danger" data-confirm-title="Remove this blacklist entry?" data-confirm="This action cannot be undone." data-confirm-ok="Remove" data-confirm-icon="fas fa-trash"><i class="fas fa-trash" aria-hidden="true"></i> Delete</button>'
            . '</form>';
    }
    form_bar($form_id, $save_label, $cancel_url, $delete);
    ?>
</div>
