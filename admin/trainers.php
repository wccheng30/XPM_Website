<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager']);
csrf_guard();

// Include database connection
require_once '../includes/database.php';
require_once __DIR__ . '/includes/ui.php';

$pdo = get_db_connection();
$trainers = [];
$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0
];

// Handle trainer actions
$can_delete_trainers = (($_SESSION['admin_role'] ?? 'admin') !== 'manager');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['bulk_delete', 'bulk_update_status'], true)) {
    $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
    try {
        if (($_POST['action'] ?? '') === 'bulk_delete' && $can_delete_trainers) {
            $stmt = $pdo->prepare("DELETE FROM trainers WHERE id = ?");
            foreach ($ids as $id) {
                $stmt->execute([$id]);
            }
            $success_message = count($ids) . " trainer" . (count($ids) === 1 ? '' : 's') . " deleted.";
        } elseif (in_array($_POST['status'] ?? '', ['active', 'inactive'], true)) {
            $stmt = $pdo->prepare("UPDATE trainers SET is_active = ? WHERE id = ?");
            $active = $_POST['status'] === 'active' ? 1 : 0;
            foreach ($ids as $id) {
                $stmt->execute([$active, $id]);
            }
            $success_message = count($ids) . " trainer" . (count($ids) === 1 ? '' : 's') . " updated.";
        }
    } catch (PDOException $e) {
        $error_message = "Error performing action.";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $trainer_id = $_POST['trainer_id'] ?? null;
    $action = $_POST['action'];
    
    if ($trainer_id) {
        try {
            switch ($action) {
                case 'toggle_active':
                    $stmt = $pdo->prepare("UPDATE trainers SET is_active = NOT is_active WHERE id = ?");
                    $stmt->execute([$trainer_id]);
                    $success_message = "Trainer active status updated successfully!";
                    break;
                    
                case 'delete':
                    if (!$can_delete_trainers) {
                        $error_message = "You do not have permission to delete trainers.";
                        break;
                    }
                    $stmt = $pdo->prepare("DELETE FROM trainers WHERE id = ?");
                    $stmt->execute([$trainer_id]);
                    $success_message = "Trainer deleted successfully!";
                    break;
            }
        } catch (PDOException $e) {
            $error_message = 'The change could not be saved.';
        }
    }
}

// Get filter parameters
$filter = $_GET['filter'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = ui_page_size(25);
$offset = ($page - 1) * $per_page;

// Get trainers with filtering and pagination
if ($pdo) {
    try {
        // Build query
        $where_conditions = [];
        $params = [];
        
        if ($filter === 'active') {
            $where_conditions[] = "is_active = 1";
        } elseif ($filter === 'inactive') {
            $where_conditions[] = "is_active = 0";
        }
        
        if ($search) {
            $where_conditions[] = "(name LIKE ? OR title LIKE ? OR description LIKE ?)";
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM trainers $where_clause";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($params);
        $total_trainers = $count_stmt->fetchColumn();
        
        // Get trainers
        $sql = "SELECT * FROM trainers $where_clause ORDER BY display_order ASC, name ASC LIMIT $per_page OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $trainers = $stmt->fetchAll();
        
        // Get statistics
        $stats_sql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
            FROM trainers";
        $stats_stmt = $pdo->query($stats_sql);
        $stats = $stats_stmt->fetch();
        
    } catch (PDOException $e) {
        $error_message = 'The list could not be loaded.';
    }
}

$total_pages = ceil($total_trainers / $per_page);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trainers Management - XpressPoint Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include_once __DIR__ . '/includes/theme-boot.php'; ?>
    <link rel="stylesheet" href="<?php echo admin_asset('admin.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/list.css'); ?>">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Trainers', 'fas fa-chalkboard-teacher'); ?>
    <div class="admin-container">
        
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('trainers'); ?>

        <main class="admin-main">
        <?php
        $trainer_filter = (string) ($filter ?? '');
        $trainer_search = (string) ($search ?? '');
        renderPageHead(
            'Trainers',
            'Manage the people who deliver programmes.',
            '<a class="btn btn-primary" href="add-trainer.php"><i class="fas fa-plus" aria-hidden="true"></i> New trainer</a>'
        );
        if (isset($success_message)) {
            echo '<div class="alert alert-success"><i class="fas fa-check-circle" aria-hidden="true"></i> ' . $success_message . '</div>';
        }
        if (isset($error_message)) {
            echo '<div class="alert alert-error"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> ' . ui_h($error_message) . '</div>';
        }
        renderStatusChips([
            ['value' => '', 'label' => 'All', 'count' => (int) ($stats['total'] ?? 0)],
            ['value' => 'active', 'label' => 'Active', 'count' => (int) ($stats['active'] ?? 0)],
            ['value' => 'inactive', 'label' => 'Inactive', 'count' => (int) ($stats['inactive'] ?? 0)],
        ], $trainer_filter);
        $trainer_active = [];
        if ($trainer_search !== '') {
            $trainer_active[] = ['label' => 'Search: ' . $trainer_search, 'href' => ui_query(['search' => null], ['page'])];
        }
        if ($trainer_filter !== '') {
            $trainer_active[] = ['label' => 'Status: ' . $trainer_filter, 'href' => ui_query(['filter' => null], ['page'])];
        }
        renderListToolbar('search', $trainer_search, 'Search trainers', '', '<button class="btn btn-secondary" type="button" onclick="exportTrainers()">Export</button>', 'trainers.php', $trainer_active);
        ?>
        <?php if (empty($trainers)): ?>
            <?php
            if ((int) $total_trainers === 0 && $trainer_search === '' && $trainer_filter === '') {
                renderEmptyState('fas fa-chalkboard-teacher', 'No trainers yet', 'Add a trainer so they can be assigned to programmes.', 'add-trainer.php', 'New trainer');
            } else {
                renderEmptyState('fas fa-search', 'No matching trainers', 'Nothing matches the current search or status.', 'trainers.php', 'Clear filters');
            }
            ?>
        <?php else: ?>
            <form method="post" id="listBulkForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" id="bulkAction" value="">
                <?php renderBulkBar(['active' => 'Active', 'inactive' => 'Inactive'], $can_delete_trainers); ?>
                <div class="list-table-wrap">
                    <table class="list-table">
                        <thead>
                            <tr>
                                <th scope="col"><input type="checkbox" id="listSelectAll" aria-label="Select all rows"></th>
                                <th scope="col">Name</th>
                                <th scope="col">Title</th>
                                <th scope="col">Experience</th>
                                <th scope="col">Status</th>
                                <th scope="col"><span class="u-clip">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trainers as $trainer): ?>
                            <tr>
                                <td class="list-select" data-label=""><input type="checkbox" class="list-check" name="ids[]" value="<?php echo (int) $trainer['id']; ?>" aria-label="Select <?php echo ui_h($trainer['name']); ?>"></td>
                                <td data-label="Name"><?php echo ui_h($trainer['name']); ?></td>
                                <td data-label="Title"><?php echo ui_h($trainer['title']); ?></td>
                                <td data-label="Experience"><?php echo (int) $trainer['experience_years']; ?>+ years</td>
                                <td data-label="Status"><?php renderStatusBadge($trainer['is_active'] ? 'active' : 'inactive'); ?></td>
                                <td data-label="Actions">
                                    <?php
                                    $actions = [
                                        ['label' => 'Edit', 'icon' => 'fas fa-edit', 'href' => 'edit-trainer.php?id=' . (int) $trainer['id']],
                                        ['label' => $trainer['is_active'] ? 'Deactivate' : 'Activate', 'icon' => 'fas fa-power-off', 'onclick' => 'postTrainer(' . (int) $trainer['id'] . ', "toggle_active")'],
                                    ];
                                    if ($can_delete_trainers) {
                                        $actions[] = ['label' => 'Delete', 'icon' => 'fas fa-trash', 'danger' => true, 'onclick' => 'postTrainer(' . (int) $trainer['id'] . ', "delete")'];
                                    }
                                    renderRowActions($actions);
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            <?php renderPagination($page, $per_page, (int) $total_trainers); ?>
        <?php endif; ?>
        </main>

    </div>

    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
    <script>
        function exportTrainers(ids) {
            if (!ids || !ids.length) ids = window.xpmCheckedIds ? window.xpmCheckedIds() : [];
            var params = new URLSearchParams(window.location.search);
            var url = 'export-trainers.php?format=csv&search=' + encodeURIComponent(params.get('search') || '') + '&filter=' + encodeURIComponent(params.get('filter') || '');
            if (ids && ids.length) url += '&ids=' + encodeURIComponent(ids.join(','));
            if (window.xpmExportDownload) window.xpmExportDownload(url, 'trainers.csv');
            else window.location.href = url;
        }
        window.exportApplications = exportTrainers;
        function postTrainer(id, action) {
            var labels = {
                toggle_active: ['Change trainer status?', 'This switches whether the trainer is active.', 'Update', 'fas fa-power-off'],
                delete: ['Delete this trainer?', 'This action cannot be undone.', 'Delete', 'fas fa-trash']
            };
            var run = function () {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'trainers.php';
                form.innerHTML = '<input type="hidden" name="action" value="' + action + '"><input type="hidden" name="trainer_id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            };
            if (labels[action] && window.xpmConfirm) {
                window.xpmConfirm({ title: labels[action][0], message: labels[action][1], confirmText: labels[action][2], icon: labels[action][3] }).then(function (ok) { if (ok) run(); });
            } else {
                run();
            }
        }
    </script>
    <script defer src="<?php echo admin_asset('js/list.js'); ?>"></script>
</body>
</html>
