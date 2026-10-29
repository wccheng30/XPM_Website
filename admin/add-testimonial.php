<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager']);
csrf_guard();

require_once '../includes/database.php';
require_once '../includes/homepage.php';

$pdo = get_db_connection();
$field_errors = [];
$values = [
    'author_name' => '',
    'author_role' => '',
    'highlight' => '',
    'quote' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parsed = xpm_testimonial_fields($_POST);
    $values = $parsed['values'];
    $field_errors = $parsed['errors'];
    $photo_path = '';

    if (!$pdo) {
        $field_errors['_form'] = 'Database connection failed.';
    }

    $saved = auth_save_image($_FILES['photo'] ?? [], 'assets/images/testimonials', 'testimonial');
    if (!$saved['ok']) {
        $field_errors['photo'] = $saved['error'] !== '' ? $saved['error'] : 'A photo is required.';
    } else {
        $photo_path = $saved['path'];
    }

    if (!$field_errors && $pdo) {
        try {
            $order = (int) $pdo->query('SELECT COALESCE(MAX(display_order), 0) + 1 FROM testimonials')->fetchColumn();
            $stmt = $pdo->prepare('INSERT INTO testimonials (author_name, author_role, highlight, quote, photo_path, display_order) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $values['author_name'],
                $values['author_role'],
                $values['highlight'],
                $values['quote'],
                $photo_path,
                $order,
            ]);
            header('Location: testimonials.php?status=saved');
            exit;
        } catch (PDOException $e) {
            auth_remove_uploaded_image($photo_path, 'testimonial');
            $field_errors['_form'] = 'The testimonial could not be saved.';
        }
    } elseif ($photo_path !== '') {
        auth_remove_uploaded_image($photo_path, 'testimonial');
    }
}

$mode = 'add';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Testimonial - XpressPoint Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include_once __DIR__ . '/includes/theme-boot.php'; ?>
    <link rel="stylesheet" href="<?php echo admin_asset('admin.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/forms.css'); ?>">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Add testimonial', 'fas fa-plus', ['url' => 'testimonials.php', 'label' => 'Testimonials']); ?>
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
