<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager']);
csrf_guard();

require_once '../includes/database.php';
require_once '../includes/homepage.php';
require_once __DIR__ . '/includes/ui.php';
require_once __DIR__ . '/includes/forms/helpers.php';

function xpm_collect_uploads($field) {
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        return [];
    }
    $bag = $_FILES[$field];
    if (!is_array($bag['name'] ?? null)) {
        return [$bag];
    }
    $files = [];
    $count = count($bag['name']);
    for ($i = 0; $i < $count; $i++) {
        $files[] = [
            'name' => $bag['name'][$i] ?? '',
            'type' => $bag['type'][$i] ?? '',
            'tmp_name' => $bag['tmp_name'][$i] ?? '',
            'error' => $bag['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $bag['size'][$i] ?? 0,
        ];
    }
    return $files;
}

$pdo = get_db_connection();
$logos = [];
$success_message = '';
$error_message = '';
$field_errors = [];
$values = [];

$status = (string) ($_GET['status'] ?? '');
if ($status === 'deleted') {
    $success_message = 'Logo deleted.';
} elseif ($status === 'added') {
    $count = max(0, (int) ($_GET['count'] ?? 0));
    $success_message = $count === 1 ? '1 logo added.' : $count . ' logos added.';
}

if (!$pdo) {
    $error_message = 'Database connection failed.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    try {
        $stmt = $pdo->prepare('SELECT image_path FROM client_logos WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            $error_message = 'That logo could not be found.';
        } else {
            $pdo->prepare('DELETE FROM client_logos WHERE id = ?')->execute([$id]);
            auth_remove_uploaded_image($row['image_path'] ?? '', 'client');
            header('Location: portfolio.php?status=deleted');
            exit;
        }
    } catch (PDOException $e) {
        $error_message = 'The logo could not be deleted.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $files = xpm_collect_uploads('logos');
    $problems = [];
    $savedCount = 0;
    try {
        $order = (int) $pdo->query('SELECT COALESCE(MAX(display_order), 0) FROM client_logos')->fetchColumn();
        $insert = $pdo->prepare('INSERT INTO client_logos (image_path, display_order) VALUES (?, ?)');
        foreach ($files as $file) {
            if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $saved = auth_save_image($file, 'assets/images/clients', 'client');
            $label = trim((string) ($file['name'] ?? ''));
            if ($label === '') {
                $label = 'A file';
            }
            if (!$saved['ok']) {
                $problems[] = $label . ': ' . ($saved['error'] !== '' ? $saved['error'] : 'The file could not be uploaded.');
                continue;
            }
            try {
                $order++;
                $insert->execute([$saved['path'], $order]);
                $savedCount++;
            } catch (PDOException $e) {
                auth_remove_uploaded_image($saved['path'], 'client');
                $problems[] = $label . ' could not be saved.';
            }
        }
    } catch (PDOException $e) {
        $problems[] = 'The logos could not be saved.';
    }

    if ($savedCount > 0 && !$problems) {
        header('Location: portfolio.php?status=added&count=' . $savedCount);
        exit;
    }
    if ($savedCount > 0) {
        $success_message = $savedCount === 1 ? '1 logo added.' : $savedCount . ' logos added.';
    }
    if ($problems) {
        $error_message = implode(' ', $problems);
        $field_errors['logos'] = $problems[0];
    } elseif ($savedCount === 0) {
        $error_message = 'Choose at least one logo.';
        $field_errors['logos'] = $error_message;
    }
}

if ($pdo) {
    try {
        $logos = $pdo->query('SELECT id, image_path, display_order FROM client_logos ORDER BY display_order ASC, id ASC')->fetchAll();
    } catch (PDOException $e) {
        $error_message = $error_message !== '' ? $error_message : 'The logos could not be loaded.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfolio - XpressPoint Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include_once __DIR__ . '/includes/theme-boot.php'; ?>
    <link rel="stylesheet" href="<?php echo admin_asset('admin.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/forms.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/list.css'); ?>">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Portfolio', 'fas fa-building'); ?>
    <div class="admin-container">
        <?php include 'includes/navigation.php'; renderAdminNavigation('portfolio'); ?>
        <main class="admin-main">
            <?php
            renderPageHead('Portfolio', 'Client logos in the homepage marquee. Add more at any time, or delete one to remove it.');
            if ($success_message !== '') {
                echo '<div class="alert alert-success"><i class="fas fa-check-circle" aria-hidden="true"></i> ' . ui_h($success_message) . '</div>';
            }
            if ($error_message !== '') {
                echo '<div class="alert alert-error"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> ' . ui_h($error_message) . '</div>';
            }
            ?>
            <div class="form-page">
                <?php form_summary(); ?>
                <form class="form-main" id="add-logos-form" method="POST" enctype="multipart/form-data" data-form-guard novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="add">
                    <?php form_section_open('logos', 'Add logos', 'Choose one image or several. JPG, PNG, GIF, or WebP up to 5 MB each.'); ?>
                    <?php
                    form_file('logos', 'logos[]', 'Logo images', [
                        'accept' => 'image/jpeg,image/png,image/gif,image/webp',
                        'required' => true,
                        'extra' => 'multiple required',
                        'hint' => 'Transparent backgrounds work best. New logos are added to the end of the marquee.',
                    ]);
                    ?>
                    <?php form_section_close(); ?>
                    <div class="form-bar">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus" aria-hidden="true"></i> Add logos</button>
                    </div>
                </form>
            </div>

            <?php if (!$logos): ?>
                <?php renderEmptyState('fas fa-building', 'No logos yet', 'Upload a client logo to show it in the homepage portfolio.', '', ''); ?>
            <?php else: ?>
                <div class="logo-board">
                    <?php foreach ($logos as $logo): ?>
                        <?php $path = xpm_public_image($logo['image_path'] ?? ''); ?>
                        <?php if ($path === '') { continue; } ?>
                        <article class="logo-card">
                            <img src="../<?php echo ui_h($path); ?>" alt="Client logo">
                            <form method="POST">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $logo['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm" data-confirm-title="Delete this logo?" data-confirm="It will be removed from the homepage." data-confirm-ok="Delete" data-confirm-icon="fas fa-trash"><i class="fas fa-trash" aria-hidden="true"></i> Delete</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <script defer src="<?php echo admin_asset('../assets/js/leave-guard.js'); ?>"></script>
    <script defer src="<?php echo admin_asset('js/forms.js'); ?>"></script>
</body>
</html>
