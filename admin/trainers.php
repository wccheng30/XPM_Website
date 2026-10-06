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
$trainers = [];
$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0
];

// Handle trainer actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
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
                    $stmt = $pdo->prepare("DELETE FROM trainers WHERE id = ?");
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
        $error_message = "Error fetching trainers: " . $e->getMessage();
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
    <?php include_once 'includes/header.php'; renderAdminHeader('Trainers', 'fas fa-chalkboard-teacher'); ?>
    <div class="admin-container">
        
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('trainers'); ?>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Trainers</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['active']; ?></h3>
                        <p>Active Trainers</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-pause-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['inactive']; ?></h3>
                        <p>Inactive Trainers</p>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <label for="filter">Filter by:</label>
                        <select name="filter" id="filter">
                            <option value="">All Trainers</option>
                            <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="search">Search:</label>
                        <input type="text" name="search" id="search" placeholder="Search trainers..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <a href="trainers.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </form>
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

            <!-- Trainers List -->
            <div class="content-section">
                <div class="section-header">
                    <div>
                        <h2>Trainers List</h2>
                        <p>Manage your training team members</p>
                    </div>
                    <div class="section-actions">
                        <a href="add-trainer.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New Trainer
                        </a>
                        <a href="export-trainers.php" class="btn btn-secondary">
                            <i class="fas fa-download"></i> Export
                        </a>
                    </div>
                </div>

                <?php if (empty($trainers)): ?>
                    <div class="empty-state">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <h3>No trainers found</h3>
                        <p>Get started by adding your first trainer.</p>
                        <a href="add-trainer.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Trainer
                        </a>
                    </div>
                <?php else: ?>
                    <div class="trainers-grid">
                        <?php foreach ($trainers as $trainer): ?>
                            <div class="trainer-card" id="trainer-<?php echo $trainer['id']; ?>">
                                <div class="trainer-image">
                                    <?php if ($trainer['image_path']): ?>
                                        <img src="../<?php echo htmlspecialchars($trainer['image_path']); ?>" alt="<?php echo htmlspecialchars($trainer['name']); ?>">
                                    <?php else: ?>
                                        <div class="default-avatar">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="trainer-badges">
                                        <?php if ($trainer['is_active']): ?>
                                            <span class="badge badge-active">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="trainer-info">
                                    <h3><?php echo htmlspecialchars($trainer['name']); ?></h3>
                                    <p class="trainer-title"><?php echo htmlspecialchars($trainer['title']); ?></p>
                                    <p class="trainer-experience"><?php echo $trainer['experience_years']; ?>+ Years Experience</p>
                                    
                                    <?php if ($trainer['specialties']): ?>
                                        <div class="trainer-specialties">
                                            <?php 
                                            $specialties = json_decode($trainer['specialties'], true);
                                            if ($specialties && is_array($specialties)):
                                                foreach (array_slice($specialties, 0, 3) as $specialty):
                                            ?>
                                                <span class="specialty-tag"><?php echo htmlspecialchars($specialty); ?></span>
                                            <?php 
                                                endforeach;
                                            endif;
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="trainer-actions">
                                        <a href="edit-trainer.php?id=<?php echo $trainer['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="trainer_id" value="<?php echo $trainer['id']; ?>">
                                            <input type="hidden" name="action" value="toggle_active">
                                            <button type="submit" class="btn btn-sm <?php echo $trainer['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                                <i class="fas fa-power-off"></i> <?php echo $trainer['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                        
                                        <?php if (($_SESSION['admin_role'] ?? 'admin') !== 'manager'): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this trainer? This action cannot be undone.');">
                                            <input type="hidden" name="trainer_id" value="<?php echo $trainer['id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-secondary">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <span class="pagination-info">
                                Page <?php echo $page; ?> of <?php echo $total_pages; ?> 
                                (<?php echo $total_trainers; ?> total trainers)
                            </span>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-secondary">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
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
    <script src="../assets/js/motion.js?v=20261002-10"></script>
</body>
</html>
