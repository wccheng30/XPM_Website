<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager']);
csrf_guard();

require_once '../includes/database.php';
require_once '../includes/homepage.php';

$pdo = get_db_connection();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id < 1 || !$pdo) {
    header('Location: testimonials.php');
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT * FROM testimonials WHERE id = ?');
    $stmt->execute([$id]);
    $testimonial = $stmt->fetch();
} catch (PDOException $e) {
    $testimonial = null;
}

if (!$testimonial) {
    header('Location: testimonials.php');
    exit;
}

$field_errors = [];
$values = [
    'author_name' => $testimonial['author_name'],
    'author_role' => $testimonial['author_role'],
    'highlight' => $testimonial['highlight'],
    'quote' => $testimonial['quote'],
    'photo_path' => $testimonial['photo_path'],
    'display_order' => $testimonial['display_order'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parsed = xpm_testimonial_fields($_POST);
    $values = $parsed['values'];
    $values['photo_path'] = $testimonial['photo_path'];
    $field_errors = $parsed['errors'];
    $photo_path = (string) $testimonial['photo_path'];
    $uploaded = '';

    $has_upload = isset($_FILES['photo']) && (int) ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($has_upload) {
        $saved = auth_save_image($_FILES['photo'], 'assets/images/testimonials', 'testimonial');
        if (!$saved['ok']) {
            $field_errors['photo'] = $saved['error'] !== '' ? $saved['error'] : 'The photo could not be uploaded.';
        } else {
            $uploaded = $saved['path'];
            $photo_path = $uploaded;
        }
    }

    if (!$field_errors) {
        try {
            $update = $pdo->prepare('UPDATE testimonials SET author_name = ?, author_role = ?, highlight = ?, quote = ?, photo_path = ?, display_order = ? WHERE id = ?');
            $update->execute([
                $values['author_name'],
                $values['author_role'],
                $values['highlight'],
                $values['quote'],
                $photo_path,
                $values['display_order'],
                $id,
            ]);
            if ($uploaded !== '' && $uploaded !== (string) $testimonial['photo_path']) {
                auth_remove_uploaded_image($testimonial['photo_path'] ?? '', 'testimonial');
            }
            header('Location: testimonials.php?status=saved');
            exit;
        } catch (PDOException $e) {
            if ($uploaded !== '') {
                auth_remove_uploaded_image($uploaded, 'testimonial');
            }
            $field_errors['_form'] = 'The testimonial could not be saved.';
        }
    } elseif ($uploaded !== '') {
        auth_remove_uploaded_image($uploaded, 'testimonial');
    }
}

$mode = 'edit';
$record_id = $id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Testimonial - XpressPoint Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include_once __DIR__ . '/includes/theme-boot.php'; ?>
    <link rel="stylesheet" href="<?php echo admin_asset('admin.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/forms.css'); ?>">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Edit testimonial', 'fas fa-edit', ['url' => 'testimonials.php', 'label' => 'Testimonials']); ?>
    <div class="admin-container">
        <?php include 'includes/navigation.php'; renderAdminNavigation('testimonials'); ?>
        <main class="admin-main">
            <?php include __DIR__ . '/includes/forms/testimonial-form.php'; ?>
        </main>
    </div>
    <script defer src="<?php echo admin_asset('../assets/js/leave-guard.js'); ?>"></script>
    <script defer src="<?php echo admin_asset('js/forms.js'); ?>"></script>
</body>
</html>
