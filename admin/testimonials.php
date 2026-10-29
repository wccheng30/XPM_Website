<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager']);
csrf_guard();

require_once '../includes/database.php';
require_once '../includes/homepage.php';
require_once __DIR__ . '/includes/ui.php';

$pdo = get_db_connection();
$testimonials = [];
$success_message = '';
$error_message = '';

$notices = [
    'saved' => 'Testimonial saved.',
    'deleted' => 'Testimonial deleted.',
];
$status = (string) ($_GET['status'] ?? '');
if (isset($notices[$status])) {
    $success_message = $notices[$status];
}

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    try {
        $stmt = $pdo->prepare('SELECT photo_path FROM testimonials WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            $error_message = 'That testimonial could not be found.';
        } else {
            $pdo->prepare('DELETE FROM testimonials WHERE id = ?')->execute([$id]);
            auth_remove_uploaded_image($row['photo_path'] ?? '', 'testimonial');
            header('Location: testimonials.php?status=deleted');
            exit;
        }
    } catch (PDOException $e) {
        $error_message = 'The testimonial could not be deleted.';
    }
}

if ($pdo) {
    try {
        $testimonials = $pdo->query('SELECT * FROM testimonials ORDER BY display_order ASC, id ASC')->fetchAll();
    } catch (PDOException $e) {
        $error_message = 'The list could not be loaded.';
    }
} else {
    $error_message = 'Database connection failed.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testimonials - XpressPoint Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include_once __DIR__ . '/includes/theme-boot.php'; ?>
    <link rel="stylesheet" href="<?php echo admin_asset('admin.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/list.css'); ?>">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Testimonials', 'fas fa-comments'); ?>
    <div class="admin-container">
        <?php include 'includes/navigation.php'; renderAdminNavigation('testimonials'); ?>
        <main class="admin-main">
        <?php
        renderPageHead(
            'Testimonials',
            'Photos and quotes shown on the homepage.',
            '<a class="btn btn-primary" href="add-testimonial.php"><i class="fas fa-plus" aria-hidden="true"></i> New testimonial</a>'
        );
        if ($success_message !== '') {
            echo '<div class="alert alert-success"><i class="fas fa-check-circle" aria-hidden="true"></i> ' . ui_h($success_message) . '</div>';
        }
        if ($error_message !== '') {
            echo '<div class="alert alert-error"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> ' . ui_h($error_message) . '</div>';
        }
        ?>
        <?php if (empty($testimonials)): ?>
            <?php renderEmptyState('fas fa-comments', 'No testimonials yet', 'Add a photo and a quote to show it on the homepage.', 'add-testimonial.php', 'New testimonial'); ?>
        <?php else: ?>
            <div class="list-table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Testimonial</th>
                            <th scope="col">Order</th>
                            <th scope="col"><span class="u-clip">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($testimonials as $item): ?>
                        <?php $photo = xpm_public_image($item['photo_path'] ?? ''); ?>
                        <tr>
                            <td data-label="Name">
                                <span class="list-person">
                                    <?php if ($photo !== ''): ?>
                                        <img class="list-thumb" src="../<?php echo ui_h($photo); ?>" alt="">
                                    <?php endif; ?>
                                    <span>
                                        <strong><?php echo ui_h($item['author_name']); ?></strong>
                                        <?php if (trim((string) $item['author_role']) !== ''): ?>
                                            <span class="list-excerpt"><?php echo ui_h($item['author_role']); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </span>
                            </td>
                            <td data-label="Testimonial"><span class="list-excerpt"><?php echo ui_h(xpm_excerpt($item['quote'])); ?></span></td>
                            <td data-label="Order"><?php echo (int) $item['display_order']; ?></td>
                            <td data-label="Actions">
                                <?php
                                renderRowActions([
                                    ['label' => 'Edit', 'icon' => 'fas fa-edit', 'href' => 'edit-testimonial.php?id=' . (int) $item['id']],
                                    ['label' => 'Delete', 'icon' => 'fas fa-trash', 'danger' => true, 'onclick' => 'postTestimonial(' . (int) $item['id'] . ')'],
                                ]);
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        </main>
    </div>
    <script>
        function postTestimonial(id) {
            var csrf = <?php echo json_encode(auth_csrf_token()); ?>;
            var run = function () {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'testimonials.php';
                form.innerHTML = '<input type="hidden" name="csrf_token" value="' + csrf + '">'
                    + '<input type="hidden" name="action" value="delete">'
                    + '<input type="hidden" name="id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            };
            if (window.xpmConfirm) {
                window.xpmConfirm({
                    title: 'Delete this testimonial?',
                    message: 'It will be removed from the homepage.',
                    confirmText: 'Delete',
                    icon: 'fas fa-trash'
                }).then(function (ok) { if (ok) run(); });
            } else {
                run();
            }
        }
    </script>
    <script defer src="<?php echo admin_asset('js/list.js'); ?>"></script>
</body>
</html>
