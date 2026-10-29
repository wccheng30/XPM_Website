<?php
require_once __DIR__ . '/helpers.php';
$mode = $mode ?? 'add';
$form_id = $form_id ?? ($mode === 'edit' ? 'edit-testimonial-form' : 'add-testimonial-form');
$save_label = $save_label ?? ($mode === 'edit' ? 'Save changes' : 'Add testimonial');
$cancel_url = $cancel_url ?? 'testimonials.php';
$can_delete = $mode === 'edit' && !empty($record_id);
$sections = ['person' => 'Person', 'words' => 'Testimonial'];
if ($mode === 'edit') {
    $sections['order'] = 'Order';
}
?>
<div class="form-page">
    <?php form_summary(); ?>
    <p class="form-intro">Fields with a star are required. The photo and the words appear on the homepage.</p>
    <div class="form-layout">
        <?php form_spy($sections); ?>
        <form class="form-main" id="<?php echo form_h($form_id); ?>" method="POST" enctype="multipart/form-data" data-form-guard novalidate>
            <?php echo csrf_field(); ?>
            <?php form_section_open('person', 'Person', 'Who the quote is from, and the photo shown on the card.'); ?>
            <?php form_text('author_name', 'author_name', 'Name', ['required' => true, 'placeholder' => 'Full name', 'extra' => 'maxlength="255"']); ?>
            <?php form_text('author_role', 'author_role', 'Role', ['placeholder' => 'Job title, company', 'extra' => 'maxlength="255"', 'hint' => 'Shown under the name.']); ?>
            <?php
            $photo_before = '';
            $photo = xpm_public_image((string) form_value('photo_path'));
            if ($mode === 'edit' && $photo !== '') {
                $photo_before = '<div class="current-upload"><img src="../' . form_h($photo) . '" alt="Current testimonial photo"></div>';
            }
            form_file('photo', 'photo', 'Photo', [
                'accept' => 'image/jpeg,image/png,image/gif,image/webp',
                'required' => $mode === 'add',
                'hint' => 'JPG, PNG, GIF, or WebP up to 5 MB.',
                'before' => $photo_before,
                'preview_id' => 'photo-preview',
                'extra' => $mode === 'add' ? 'required' : '',
            ]);
            ?>
            <?php form_section_close(); ?>

            <?php form_section_open('words', 'Testimonial', 'The opening line is the short bold sentence. The testimonial is the rest of the quote.'); ?>
            <?php form_text('highlight', 'highlight', 'Opening line', ['placeholder' => 'The best organiser.', 'extra' => 'maxlength="255"', 'hint' => 'Optional. Shown in bold before the testimonial.']); ?>
            <?php form_textarea('quote', 'quote', 'Testimonial', ['required' => true, 'rows' => 6, 'placeholder' => 'What the client said.']); ?>
            <?php form_section_close(); ?>

            <?php if ($mode === 'edit'): ?>
                <?php form_section_open('order', 'Order', 'Lower numbers appear first on the homepage.'); ?>
                <?php form_text('display_order', 'display_order', 'Display order', ['type' => 'number', 'min' => '0', 'max' => '9999']); ?>
                <?php form_section_close(); ?>
            <?php endif; ?>
        </form>
    </div>
    <?php
    $delete = '';
    if ($can_delete) {
        $delete = '<form class="form-delete" method="POST" action="testimonials.php">'
            . csrf_field()
            . '<input type="hidden" name="action" value="delete">'
            . '<input type="hidden" name="id" value="' . form_h($record_id) . '">'
            . '<button type="submit" class="btn btn-danger" data-confirm-title="Delete this testimonial?" data-confirm="It will be removed from the homepage." data-confirm-ok="Delete" data-confirm-icon="fas fa-trash"><i class="fas fa-trash" aria-hidden="true"></i> Delete</button>'
            . '</form>';
    }
    form_bar($form_id, $save_label, $cancel_url, $delete);
    ?>
</div>
