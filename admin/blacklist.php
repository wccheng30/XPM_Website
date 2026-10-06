<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Include database connection
require_once '../includes/database.php';

$pdo = get_db_connection();
$blacklisted = [];
$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $trainer_id = $_POST['trainer_id'] ?? null;
    $action = $_POST['action'];
    
    if ($trainer_id) {
        try {
            switch ($action) {
                case 'toggle_active':
                    $stmt = $pdo->prepare("UPDATE blacklist SET is_active = NOT is_active WHERE id = ?");
                    $stmt->execute([$trainer_id]);
                    $success_message = "Trainer active status updated successfully!";
                    break;
                    
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM blacklist WHERE id = ?");
                    $stmt->execute([$trainer_id]);
                    $success_message = "Trainer deleted successfully!";
                    break;
            }
        } catch (PDOException $e) {
            $error_message = "Error performing action: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$filter = $_GET['filter'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get blacklist with filtering and pagination
if ($pdo) {
    try {
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
        $stats_sql = "SELECT 
            COUNT(*) as total
            FROM blacklist";
        $stats_stmt = $pdo->query($stats_sql);
        $stats = $stats_stmt->fetch();
        
    } catch (PDOException $e) {
        $error_message = "Error fetching blacklist: " . $e->getMessage();
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
    <link rel="stylesheet" href="admin-styles.css?v=20261005-5">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Blacklist', 'fas fa-user-slash'); ?>
    <div class="admin-container">
        
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('blacklist'); ?>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Blacklisted</p>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3><i class="fas fa-filter"></i> Search & Filters</h3>
                </div>
                <div class="card-body">
                    <form method="GET" class="d-flex gap-3" style="align-items: end; display: flex; gap: 1rem;">
                        <div class="form-group" style="flex: 1;">
                            <label for="search">Search Blacklist</label>
                            <input type="text" name="search" id="search" class="form-control" placeholder="Search by name, position, or reason..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                        <?php if (isset($_GET['search'])): ?>
                            <div class="form-group">
                                <a href="blacklist.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Blacklist Table -->
            <div class="card">
                <div class="card-header">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="margin: 0; color: #1f2937; font-size: 20px; font-weight: 600;">
                                <i class="fas fa-user-slash" style="color: #ef4444; margin-right: 8px;"></i>
                                Blacklisted People
                            </h3>
                            <p style="margin: 4px 0 0 0; color: #6b7280; font-size: 14px;">
                                <?php echo count($blacklist); ?> record<?php echo count($blacklist) != 1 ? 's' : ''; ?> found
                            </p>
                        </div>
                        <div style="display: flex; gap: 12px;">
                            <a href="add-blacklist.php" class="btn btn-primary" style="background: #111111; color: white; border: none; padding: 10px 16px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; text-decoration: none;">
                                <i class="fas fa-plus" style="margin-right: 6px;"></i>
                                Add New
                            </a>
                            <a href="import-blacklist.php" class="btn btn-primary" style="background: #059669; color: white; border: none; padding: 10px 16px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.backgroundColor='#047857'" onmouseout="this.style.backgroundColor='#059669'">
                                <i class="fas fa-upload" style="margin-right: 6px;"></i>
                                Import CSV
                            </a>
                            <button onclick="exportBlacklist()" class="btn btn-secondary" style="background: #6b7280; color: white; border: none; padding: 10px 16px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s;" onmouseover="this.style.backgroundColor='#4b5563'" onmouseout="this.style.backgroundColor='#6b7280'">
                                <i class="fas fa-download" style="margin-right: 6px;"></i>
                                Export
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if (empty($blacklist)): ?>
                        <div style="text-align: center; padding: 60px 20px; color: #64748b; background: #f8fafc; border-radius: 12px; margin: 20px 0;">
                            <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: white; font-size: 32px;">
                                <i class="fas fa-user-slash"></i>
                            </div>
                            <h3 style="margin-bottom: 12px; color: #374151; font-size: 24px; font-weight: 600;">No Blacklisted People</h3>
                            <p style="color: #6b7280; font-size: 16px; margin-bottom: 24px; max-width: 400px; margin-left: auto; margin-right: auto;">
                                There are currently no records in the blacklist.
                            </p>
                            <a href="add-blacklist.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Blacklisted Person
                            </a>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                <thead>
                                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                        <th style="padding: 16px 12px; text-align: left; font-weight: 600; color: #374151; font-size: 14px;">Person</th>
                                        <th style="padding: 16px 12px; text-align: left; font-weight: 600; color: #374151; font-size: 14px;">Position Applied</th>
                                        <th style="padding: 16px 12px; text-align: left; font-weight: 600; color: #374151; font-size: 14px;">Reason</th>
                                        <th style="padding: 16px 12px; text-align: left; font-weight: 600; color: #374151; font-size: 14px;">Date</th>
                                        <th style="padding: 16px 12px; text-align: left; font-weight: 600; color: #374151; font-size: 14px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($blacklist as $person): ?>
                                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='white'">
                                            <td style="padding: 16px 12px;">
                                                <div style="display: flex; align-items: center; gap: 12px;">
                                                    <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 16px;">
                                                        <i class="fas fa-user-slash"></i>
                                                    </div>
                                                    <div>
                                                        <div style="font-weight: 600; color: #1f2937; font-size: 15px; margin-bottom: 2px;">
                                                            <?php echo htmlspecialchars($person['name']); ?>
                                                        </div>
                                                        <?php if ($person['phone_number']): ?>
                                                        <div style="font-size: 13px; color: #6b7280;">
                                                            <i class="fas fa-phone" style="font-size: 11px; margin-right: 4px;"></i>
                                                            <?php echo htmlspecialchars($person['phone_number']); ?>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="padding: 16px 12px;">
                                                <div style="font-weight: 500; color: #374151;">
                                                    <?php echo htmlspecialchars($person['position_applied'] ?: 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td style="padding: 16px 12px;">
                                                <div style="color: #4b5563; font-size: 14px; max-width: 250px; white-space: normal;">
                                                    <?php echo htmlspecialchars($person['reason'] ?: 'No reason provided'); ?>
                                                </div>
                                            </td>
                                            <td style="padding: 16px 12px;">
                                                <div style="font-size: 14px; color: #374151; font-weight: 500;">
                                                    <?php echo !empty($person['date']) ? htmlspecialchars($person['date']) : 'N/A'; ?>
                                                </div>
                                            </td>
                                            <td style="padding: 16px 12px;">
                                                <div style="display: flex; gap: 8px; align-items: center;">
                                                    <a href="edit-blacklist.php?id=<?php echo $person['id']; ?>" class="btn btn-sm btn-primary" style="background: linear-gradient(135deg, #111111 0%, #111111 100%); color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500; transition: all 0.2s; display: flex; align-items: center; gap: 4px; text-decoration: none;" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='translateY(0)'">
                                                        <i class="fas fa-edit" style="font-size: 11px;"></i>
                                                        Edit
                                                    </a>
                                                    <?php if (($_SESSION['admin_role'] ?? 'admin') !== 'manager'): ?>
                                                    <form method="POST" style="margin: 0; display: inline;">
                                                        <input type="hidden" name="trainer_id" value="<?php echo $person['id']; ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <button type="submit" class="btn btn-sm btn-danger" data-confirm-title="Remove from blacklist?" data-confirm="This action cannot be undone." data-confirm-ok="Delete" data-confirm-icon="fas fa-trash" style="background: #ef4444; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500; transition: all 0.2s; display: flex; align-items: center; gap: 4px;" onmouseover="this.style.backgroundColor='#dc2626'" onmouseout="this.style.backgroundColor='#ef4444'">
                                                            <i class="fas fa-trash" style="font-size: 11px;"></i>
                                                            Delete
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination" style="margin-top: 20px;">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-secondary">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                <?php endif; ?>
                                
                                <span class="pagination-info">
                                    Page <?php echo $page; ?> of <?php echo $total_pages; ?> 
                                    (<?php echo $total_trainers; ?> total records)
                                </span>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-secondary">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function exportBlacklist() {
            // Show format selection modal
            showExportModal();
        }
        
        function showExportModal() {
            // Create modal overlay
            const overlay = document.createElement('div');
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
            `;
            
            // Create modal content
            const modal = document.createElement('div');
            modal.style.cssText = `
                background: white;
                border-radius: 12px;
                padding: 24px;
                max-width: 400px;
                width: 90%;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            `;
            
            modal.innerHTML = `
                <div style="display: flex; align-items: center; margin-bottom: 20px;">
                    <i class="fas fa-download" style="color: #111111; margin-right: 12px; font-size: 20px;"></i>
                    <h3 style="margin: 0; color: #1e293b;">Export Blacklist</h3>
                </div>
                
                <p style="color: #64748b; margin-bottom: 20px;">
                    Choose the format for your blacklist export:
                </p>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #374151;">Export Format</label>
                    <select id="exportFormat" class="form-control">
                        <option value="csv">CSV (Comma Separated Values)</option>
                        <option value="json">JSON (JavaScript Object Notation)</option>
                        <option value="sql">SQL (Database Script)</option>
                        <option value="excel">XLSX (Excel)</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button onclick="closeExportModal()" style="padding: 8px 16px; border: 1px solid #d1d5db; background: white; border-radius: 6px; cursor: pointer; color: #374151;">
                        Cancel
                    </button>
                    <button onclick="performExport()" style="padding: 8px 16px; background: #111111; color: white; border: none; border-radius: 6px; cursor: pointer;">
                        <i class="fas fa-download" style="margin-right: 4px;"></i>
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
            const overlay = document.querySelector('[style*="z-index: 10000"]');
            if (overlay) {
                overlay.remove();
            }
        }
        
        function performExport() {
            const format = document.getElementById('exportFormat').value;
            const exportBtn = document.querySelector('button[onclick="exportBlacklist()"]');
            const originalText = exportBtn.innerHTML;
            
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 4px;"></i>Preparing...';
            exportBtn.disabled = true;
            
            // Get current search parameter
            const searchParams = new URLSearchParams(window.location.search);
            const search = searchParams.get('search') || '';
            
            // Create export URL with format
            const exportUrl = `export-blacklist.php?format=${format}&search=${encodeURIComponent(search)}`;
            
            // Create a temporary link and trigger download
            const link = document.createElement('a');
            link.href = exportUrl;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Close modal
            closeExportModal();
            
            // Reset button
            setTimeout(() => {
                exportBtn.innerHTML = originalText;
                exportBtn.disabled = false;
            }, 1000);
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
    <script src="../assets/js/motion.js?v=20261002-10"></script>
</body>
</html>
