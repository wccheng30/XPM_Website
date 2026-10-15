<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager']);
csrf_guard();

// Include database connection
require_once '../includes/database.php';
require_once __DIR__ . '/includes/ui.php';
require_once '../includes/blacklist-lookup.php';

$pdo = get_db_connection();
$blacklisted = [];
$blacklist = [];
$total_trainers = 0;
$stats = ['total' => 0];
$can_delete_blacklist = (($_SESSION['admin_role'] ?? 'admin') !== 'manager');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_delete' && $can_delete_blacklist) {
    $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
    try {
        foreach ($ids as $trainer_id) {
            $existing = $pdo->prepare("SELECT name, phone_number FROM blacklist WHERE id = ?");
            $existing->execute([$trainer_id]);
            $person = $existing->fetch(PDO::FETCH_ASSOC);
            if ($person) {
                blacklist_clear_marked($pdo, (string) $person['name'], (string) $person['phone_number']);
            }
            $stmt = $pdo->prepare("DELETE FROM blacklist WHERE id = ?");
            $stmt->execute([$trainer_id]);
        }
        $success_message = count($ids) . " record" . (count($ids) === 1 ? '' : 's') . " removed.";
    } catch (PDOException $e) {
        $error_message = "Error performing action.";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $trainer_id = $_POST['trainer_id'] ?? null;
    $action = $_POST['action'];
    
    if ($trainer_id) {
        try {
            switch ($action) {
                case 'delete':
                    if (!$can_delete_blacklist) {
                        $error_message = "You do not have permission to remove blacklist records.";
                        break;
                    }
                    $existing = $pdo->prepare("SELECT name, phone_number FROM blacklist WHERE id = ?");
                    $existing->execute([$trainer_id]);
                    $person = $existing->fetch(PDO::FETCH_ASSOC);
                    if ($person) {
                        blacklist_clear_marked($pdo, (string)$person['name'], (string)$person['phone_number']);
                    }
                    $stmt = $pdo->prepare("DELETE FROM blacklist WHERE id = ?");
                    $stmt->execute([$trainer_id]);
                    $success_message = "Removed from the blacklist.";
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

// Get blacklist with filtering and pagination
if ($pdo) {
    try {
        blacklist_import_marked($pdo);

        // Build query
        $where_conditions = [];
        $params = [];
        
        if ($search) {
            $where_conditions[] = "(name LIKE ? OR name LIKE ? OR reason LIKE ? OR position_applied LIKE ? or date LIKE ?)";
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM blacklist $where_clause";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($params);
        $total_trainers = $count_stmt->fetchColumn();
        
        // Get blacklist
        $sql = "SELECT * FROM blacklist $where_clause ORDER BY date DESC LIMIT $per_page OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $blacklist = $stmt->fetchAll();
        
        // Get statistics
        $stats_sql = "SELECT COUNT(*) as total FROM blacklist";
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
    <title>Blacklist - XpressPoint Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include_once __DIR__ . '/includes/theme-boot.php'; ?>
    <link rel="stylesheet" href="<?php echo admin_asset('admin.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/list.css'); ?>">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Blacklist', 'fas fa-user-slash'); ?>
    <div class="admin-container">
        
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('blacklist'); ?>

        <main class="admin-main">
        <?php
        $bl_filter = (string) ($filter ?? '');
        $bl_search = (string) ($search ?? '');
        renderPageHead(
            'Blacklist',
            'People who should not be hired or enrolled.',
            '<a class="btn btn-primary" href="add-blacklist.php"><i class="fas fa-plus" aria-hidden="true"></i> New entry</a>'
        );
        if (isset($success_message)) {
            echo '<div class="alert alert-success"><i class="fas fa-check-circle" aria-hidden="true"></i> ' . $success_message . '</div>';
        }
        if (isset($error_message)) {
            echo '<div class="alert alert-error"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> ' . ui_h($error_message) . '</div>';
        }
        renderStatusChips([
            ['value' => '', 'label' => 'All', 'count' => (int) ($stats['total'] ?? 0)],
        ], '');
        $bl_active = [];
        if ($bl_search !== '') {
            $bl_active[] = ['label' => 'Search: ' . $bl_search, 'href' => ui_query(['search' => null], ['page'])];
        }
        renderListToolbar(
            'search',
            $bl_search,
            'Search name, position, or reason',
            '<a class="btn btn-secondary" href="import-blacklist.php">Import</a>',
            '<button class="btn btn-secondary" type="button" onclick="exportBlacklist()">Export</button>',
            'blacklist.php',
            $bl_active
        );
        ?>
        <?php if (empty($blacklist)): ?>
            <?php
            if ((int) $total_trainers === 0 && $bl_search === '' && $bl_filter === '') {
                renderEmptyState('fas fa-user-slash', 'No blacklist entries', 'Add someone who should be blocked from hiring or enrolment.', 'add-blacklist.php', 'New entry');
            } else {
                renderEmptyState('fas fa-search', 'No matching entries', 'Nothing matches the current search or status.', 'blacklist.php', 'Clear filters');
            }
            ?>
        <?php else: ?>
            <form method="post" id="listBulkForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" id="bulkAction" value="">
                <?php renderBulkBar([], $can_delete_blacklist); ?>
                <div class="list-table-wrap">
                    <table class="list-table">
                        <thead>
                            <tr>
                                <th scope="col"><input type="checkbox" id="listSelectAll" aria-label="Select all rows"></th>
                                <th scope="col">Person</th>
                                <th scope="col">Position</th>
                                <th scope="col">Reason</th>
                                <th scope="col">Date</th>
                                <th scope="col"><span class="u-clip">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($blacklist as $person): ?>
                            <tr>
                                <td class="list-select" data-label=""><input type="checkbox" class="list-check" name="ids[]" value="<?php echo (int) $person['id']; ?>" aria-label="Select <?php echo ui_h($person['name']); ?>"></td>
                                <td data-label="Person">
                                    <?php echo ui_h($person['name']); ?>
                                    <?php if (!empty($person['phone_number'])): ?><div class="meta"><?php echo ui_h($person['phone_number']); ?></div><?php endif; ?>
                                </td>
                                <td data-label="Position"><?php echo ui_h($person['position_applied'] ?: 'N/A'); ?></td>
                                <td data-label="Reason"><?php echo ui_h($person['reason'] ?: 'No reason provided'); ?></td>
                                <td data-label="Date"><?php echo ui_h(!empty($person['date']) ? $person['date'] : 'N/A'); ?></td>
                                <td data-label="Actions">
                                    <?php
                                    $actions = [
                                        ['label' => 'Edit', 'icon' => 'fas fa-edit', 'href' => 'edit-blacklist.php?id=' . (int) $person['id']],
                                    ];
                                    if ($can_delete_blacklist) {
                                        $actions[] = ['label' => 'Delete', 'icon' => 'fas fa-trash', 'danger' => true, 'onclick' => 'removeBlacklist(' . (int) $person['id'] . ')'];
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
        <script>
            function removeBlacklist(id) {
                var run = function () {
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'blacklist.php';
                    form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="trainer_id" value="' + id + '">';
                    document.body.appendChild(form);
                    form.submit();
                };
                if (window.xpmConfirm) {
                    window.xpmConfirm({ title: 'Remove from blacklist?', message: 'This action cannot be undone.', confirmText: 'Delete', icon: 'fas fa-trash' }).then(function (ok) { if (ok) run(); });
                } else {
                    run();
                }
            }
        </script>

    </div>

    <script>
        function exportBlacklist() {
            // Show format selection modal
            showExportModal();
        }
        
        function showExportModal() {
            // Create modal overlay
            const overlay = document.createElement('div');
            overlay.className = 'modal-overlay is-open';
            
            const modal = document.createElement('div');
            modal.className = 'export-sheet';
            
            modal.innerHTML = `
                <div class="cluster u-mb-5">
                    <i class="fas fa-download icon-lg" aria-hidden="true"></i>
                    <h3 class="modal-title">Export Blacklist</h3>
                </div>
                
                <p class="u-mb-5">
                    Choose the format for your blacklist export:
                </p>
                
                <div class="u-mb-5">
                    <label class="field-label">Export Format</label>
                    <select id="exportFormat" class="form-control">
                        <option value="csv">CSV (Comma Separated Values)</option>
                        <option value="json">JSON (JavaScript Object Notation)</option>
                        <option value="sql">SQL (Database Script)</option>
                        <option value="excel">XLSX (Excel)</option>
                    </select>
                </div>
                
                <div class="toolbar-actions">
                    <button class="btn btn-secondary btn-sm" onclick="closeExportModal()">
                        Cancel
                    </button>
                    <button class="btn btn-ink btn-sm" onclick="performExport()">
                        <i class="fas fa-download ico" aria-hidden="true"></i>
                        Export
                    </button>
                </div>
            `;
            
            overlay.appendChild(modal);
            document.body.appendChild(overlay);
            
            // Close modal when clicking overlay
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    closeExportModal();
                }
            });
        }
        
        function closeExportModal() {
            const overlay = document.querySelector('.modal-overlay.is-open');
            if (overlay) overlay.remove();
        }
        
        function performExport() {
            const format = document.getElementById('exportFormat').value;
            const exportBtn = document.querySelector('button[onclick="exportBlacklist()"]');
            
            // Get current search parameter
            const searchParams = new URLSearchParams(window.location.search);
            const search = searchParams.get('search') || '';
            
            // Create export URL with format
            const liveIds = window.xpmCheckedIds ? window.xpmCheckedIds() : [];
            const selected = (liveIds.length ? liveIds : (window.exportSelection || [])).join(',');
            const exportUrl = `export-blacklist.php?format=${format}&search=${encodeURIComponent(search)}` + (selected ? `&ids=${encodeURIComponent(selected)}` : '');
            
            var exportName = `export`;
            if (window.xpmExportDownload) {
                window.xpmExportDownload(exportUrl, exportName, exportBtn);
            } else {
                window.location.href = exportUrl;
            }
            closeExportModal();
        }

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
        window.exportSelection = [];
        var previousExport = window.exportBlacklist;
        window.exportBlacklist = function (ids) {
            window.exportSelection = ids || [];
            if (typeof showExportModal === 'function') showExportModal();
        };
        window.exportApplications = window.exportBlacklist;
    </script>
    <script defer src="<?php echo admin_asset('js/list.js'); ?>"></script>
</body>
</html>
