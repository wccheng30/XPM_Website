<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Include database connection
require_once '../includes/database.php';
require_once '../includes/programme-pricing.php';

// Helper function to safely display prices
function formatPrice($price) {
    if (is_numeric($price)) {
        if ((float)$price == 0) {
            return 'Free';
        }
        return 'RM ' . number_format($price, 2);
    } else {
        return htmlspecialchars($price);
    }
}

function getDisplayPrice($programme, $priceKey = 'price') {
    if (!isset($programme[$priceKey]) || $programme[$priceKey] === '' || $programme[$priceKey] === null) {
        return null;
    }
    return $programme[$priceKey];
}

// Handle AJAX search suggestions
if (isset($_GET['ajax_search']) && $_GET['ajax_search']) {
    $search_term = $_GET['search_term'] ?? '';
    $pdo = get_db_connection();
    $suggestions = [];
    
    if ($pdo && $search_term) {
        try {
            $sql = "SELECT DISTINCT title, category, duration FROM programmes 
                    WHERE title LIKE ? OR category LIKE ? OR duration LIKE ? 
                    LIMIT 10";
            $search_param = "%$search_term%";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$search_param, $search_param, $search_param]);
            $suggestions = $stmt->fetchAll();
        } catch (PDOException $e) {
            // Handle error silently
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($suggestions);
    exit;
}

$pdo = get_db_connection();
$programmes = [];
$error = '';
$message = '';

// Get messages from URL parameters
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

if ($pdo) {
    try {
        // Build the query with filters
        $where_conditions = [];
        $params = [];
        
        // Search filter
        if (!empty($_GET['search'])) {
            $search_term = $_GET['search'];
            $where_conditions[] = "(title LIKE ? OR category LIKE ? OR duration LIKE ? OR description LIKE ?)";
            $search_param = "%$search_term%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        // Status filter
        if (!empty($_GET['filter'])) {
            if ($_GET['filter'] === 'active') {
                $where_conditions[] = "is_active = 1";
            } elseif ($_GET['filter'] === 'inactive') {
                $where_conditions[] = "is_active = 0";
            }
        }
        
        // Build the final query
        $sql = "SELECT * FROM programmes";
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(" AND ", $where_conditions);
        }
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $programmes = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
} else {
    $error = "Unable to connect to database";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Programmes - XpressPoint Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-styles.css?v=20261005-5">
    <link rel="stylesheet" href="css/programmes.css?v=20260930-4">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Training Programmes', 'fas fa-graduation-cap'); ?>

    <div class="admin-container">
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('programmes'); ?>

        <!-- Page Header -->
        <div class="page-header">
            <h2 class="page-title">
                <i class="fas fa-graduation-cap"></i>
                Training Courses
            </h2>
            <div class="d-flex gap-2">
                <a href="add-programme.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Add New Programme
                </a>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Search and Filters -->
        <div class="card mb-3">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Search & Filters</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="d-flex gap-3" style="align-items: end;">
                    <div class="form-group search-container">
                        <label for="search">Search Programmes</label>
                        <input type="text" id="search" name="search" class="form-control" 
                               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                               placeholder="Search by programme title, category, or duration..."
                               autocomplete="off">
                        <div class="search-suggestions" id="searchSuggestions">
                            <!-- Suggestions will be populated here -->
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="filter">Filter by Status</label>
                        <select id="filter" name="filter" class="form-control">
                            <option value="">All Programmes</option>
                            <option value="active" <?php echo ($_GET['filter'] ?? '') === 'active' ? 'selected' : ''; ?>>Active Only</option>
                            <option value="inactive" <?php echo ($_GET['filter'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Search
                        </button>
                    </div>
                    <?php if (isset($_GET['search']) || isset($_GET['filter'])): ?>
                        <div class="form-group">
                            <a href="programmes.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                Clear
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Programmes Overview -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-between align-center">
                    <div>
                        <h3 style="margin: 0; color: #1e293b; font-size: 18px;">
                            <i class="fas fa-list"></i>
                            Training Programmes Overview
                        </h3>
                        <p style="margin: 8px 0 0 0; color: #64748b;">
                            Manage your HR training courses and programmes
                        </p>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 24px; font-weight: 700; color: #111111;">
                            <?php echo count($programmes); ?>
                        </div>
                        <div style="font-size: 14px; color: #64748b;">
                            Total Programmes
                        </div>
                        <div style="margin-top: 12px;">
                            <button onclick="exportProgrammes()" class="btn btn-secondary" style="background: #6b7280; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500; transition: all 0.2s;" onmouseover="this.style.backgroundColor='#4b5563'" onmouseout="this.style.backgroundColor='#6b7280'">
                                <i class="fas fa-download" style="margin-right: 4px;"></i>
                                Export
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Programmes List -->
        <?php if (!empty($programmes)): ?>
            <div style="display: grid; gap: 20px;">
                <?php foreach ($programmes as $programme): ?>
                    <div class="card" id="programme-<?php echo $programme['id']; ?>">
                        <div class="card-body">
                            <!-- Programme Header -->
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; flex-wrap: wrap; gap: 16px;">
                                <div style="flex: 1;">
                                    <h3 style="margin: 0 0 8px 0; color: #1e293b; font-size: 20px; font-weight: 600;">
                                        <i class="fas fa-graduation-cap" style="color: #111111; margin-right: 8px;"></i>
                                        <?php echo htmlspecialchars($programme['title']); ?>
                                    </h3>
                                    <div style="display: flex; gap: 16px; align-items: center; margin-bottom: 12px; flex-wrap: wrap;">
                                        <span class="badge primary">
                                            <i class="fas fa-tag" style="margin-right: 4px;"></i>
                                            <?php echo htmlspecialchars($programme['category']); ?>
                                        </span>
                                        <div style="color: #64748b; font-size: 14px;">
                                            <i class="fas fa-clock" style="margin-right: 6px;"></i>
                                            <?php echo htmlspecialchars($programme['duration']); ?>
                                        </div>
                                        <span class="badge <?php echo $programme['is_active'] ? 'success' : 'warning'; ?>">
                                            <i class="fas fa-dollar-sign" style="margin-right: 4px;"></i>
                                            <?php echo formatPrice(getDisplayPrice($programme, 'price')); ?>
                                            <?php $sst_note = programme_sst_note($programme); ?>
                                            <?php if ($sst_note !== ''): ?>
                                                <small style="margin-left: 4px; font-weight: normal; opacity: 0.9;">
                                                    (<?php echo htmlspecialchars($sst_note); ?>)
                                                </small>
                                            <?php endif; ?>
                                            <?php if (!empty($programme['original_price']) && $programme['original_price'] > $programme['price']): ?>
                                                <span style="margin-left: 8px; font-size: 12px; opacity: 0.7; text-decoration: line-through;">
                                                    <i class="fas fa-tag" style="margin-right: 4px;"></i>
                                                    <?php echo formatPrice(getDisplayPrice($programme, 'original_price')); ?>
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="badge <?php echo $programme['is_active'] ? 'success' : 'warning'; ?>">
                                            <i class="fas fa-toggle-<?php echo $programme['is_active'] ? 'on' : 'off'; ?>" style="margin-right: 4px;"></i>
                                            <?php echo $programme['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                        <?php if (isset($programme['is_featured']) && $programme['is_featured'] == 1): ?>
                                            <span class="badge" style="background-color: #f59e0b; color: white;">
                                                <i class="fas fa-star" style="margin-right: 4px;"></i>
                                                Featured
                                            </span>
                                        <?php endif; ?>
                                        <?php if (isset($programme['registration_status'])): ?>
                                            <span class="badge <?php 
                                                echo $programme['registration_status'] === 'open' ? 'success' : 
                                                    ($programme['registration_status'] === 'closed' ? 'warning' : 'danger'); 
                                            ?>">
                                                <i class="fas fa-<?php 
                                                    echo $programme['registration_status'] === 'open' ? 'unlock' : 
                                                        ($programme['registration_status'] === 'closed' ? 'lock' : 'users'); 
                                                ?>" style="margin-right: 4px;"></i>
                                                <?php echo ucfirst($programme['registration_status']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="d-flex gap-3 flex-wrap align-center">
                                    <a href="edit-programme.php?id=<?php echo $programme['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                        Edit
                                    </a>
                                    <form method="POST" action="programme-actions.php" style="display: contents;">
                                        <input type="hidden" name="action" value="duplicate">
                                        <input type="hidden" name="programme_id" value="<?php echo $programme['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-info" onclick="return confirm('Duplicate this training programme? A copy will be created as inactive.')">
                                            <i class="fas fa-copy"></i>
                                            Duplicate
                                        </button>
                                    </form>
                                    <?php if ($programme['is_active']): ?>
                                        <form method="POST" action="programme-actions.php" style="display: contents;">
                                            <input type="hidden" name="action" value="deactivate">
                                            <input type="hidden" name="programme_id" value="<?php echo $programme['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Deactivate this training programme?')">
                                                <i class="fas fa-pause"></i>
                                                Deactivate
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="programme-actions.php" style="display: contents;">
                                            <input type="hidden" name="action" value="activate">
                                            <input type="hidden" name="programme_id" value="<?php echo $programme['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="fas fa-play"></i>
                                                Activate
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (($_SESSION['admin_role'] ?? 'admin') !== 'manager'): ?>
                                    <form method="POST" action="programme-actions.php" style="margin: 0;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="programme_id" value="<?php echo $programme['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this training programme? This action cannot be undone.')">
                                            <i class="fas fa-trash"></i>
                                            Delete
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Programme Details -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 16px;">
                                <div>
                                    <h4 style="color: #1e293b; margin-bottom: 12px; font-size: 16px; font-weight: 600;">
                                        <i class="fas fa-file-alt" style="color: #111111; margin-right: 8px;"></i>
                                        Programme Description
                                    </h4>
                                    <div style="color: #374151; line-height: 1.6; background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                        <?php echo nl2br(htmlspecialchars($programme['description'])); ?>
                                    </div>
                                </div>
                                <div>
                                    <h4 style="color: #1e293b; margin-bottom: 12px; font-size: 16px; font-weight: 600;">
                                        <i class="fas fa-info-circle" style="color: #111111; margin-right: 8px;"></i>
                                        Programme Details
                                    </h4>
                                    <div style="color: #374151; line-height: 1.8; background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                        <div style="margin-bottom: 12px;">
                                            <i class="fas fa-tag" style="color: #64748b; margin-right: 8px; width: 16px;"></i>
                                            <strong>Category:</strong> <?php echo htmlspecialchars($programme['category']); ?>
                                        </div>
                                        <div style="margin-bottom: 12px;">
                                            <i class="fas fa-clock" style="color: #64748b; margin-right: 8px; width: 16px;"></i>
                                            <strong>Duration:</strong> <?php echo htmlspecialchars($programme['duration']); ?>
                                        </div>
                                        <div style="margin-bottom: 12px;">
                                            <i class="fas fa-dollar-sign" style="color: #64748b; margin-right: 8px; width: 16px;"></i>
                                            <strong>Current Price:</strong> <?php echo formatPrice(getDisplayPrice($programme, 'price')); ?>
                                            <?php $sst_note = programme_sst_note($programme); ?>
                                            <?php if ($sst_note !== ''): ?>
                                                <span style="font-size: 13px; color: #64748b; margin-left: 6px;">
                                                    (<?php echo htmlspecialchars($sst_note); ?>)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($programme['original_price'])): ?>
                                        <div style="margin-bottom: 12px;">
                                            <i class="fas fa-tag" style="color: #64748b; margin-right: 8px; width: 16px;"></i>
                                            <strong>Original Price:</strong> <?php echo formatPrice(getDisplayPrice($programme, 'original_price')); ?>
                                        </div>
                                        <?php endif; ?>
                                        <div style="margin-bottom: 12px;">
                                            <i class="fas fa-users" style="color: #64748b; margin-right: 8px; width: 16px;"></i>
                                            <strong>Max Participants:</strong> <?php echo htmlspecialchars($programme['max_participants'] ?? 25); ?>
                                        </div>
                                        <div style="margin-bottom: 12px;">
                                            <i class="fas fa-certificate" style="color: #64748b; margin-right: 8px; width: 16px;"></i>
                                            <strong>Certification:</strong> <?php echo htmlspecialchars($programme['certification'] ?? 'HRD Corp Certified'); ?>
                                        </div>
                                        <div style="margin-bottom: 12px;">
                                            <i class="fas fa-map-marker-alt" style="color: #64748b; margin-right: 8px; width: 16px;"></i>
                                            <strong>Location/Format:</strong> <?php echo htmlspecialchars($programme['location'] ?? 'Online'); ?>
                                        </div>
                                        <?php if (!empty($programme['start_date']) && $programme['start_date'] !== '0000-00-00'): ?>
                                        <div style="margin-bottom: 12px;">
                                            <i class="fas fa-calendar-alt" style="color: #64748b; margin-right: 8px; width: 16px;"></i>
                                            <strong>Start Date:</strong> <?php echo htmlspecialchars($programme['start_date']); ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($programme['end_date']) && $programme['end_date'] !== '0000-00-00'): ?>
                                        <div style="margin-bottom: 12px;">
                                            <i class="fas fa-calendar-alt" style="color: #64748b; margin-right: 8px; width: 16px;"></i>
                                            <strong>End Date:</strong> <?php echo htmlspecialchars($programme['end_date']); ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($programme['start_time'])): ?>
                                        <div style="margin-bottom: 12px;">
                                            <i class="fas fa-clock" style="color: #64748b; margin-right: 8px; width: 16px;"></i>
                                            <strong>Start Time:</strong> <?php echo htmlspecialchars($programme['start_time']); ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($programme['end_time'])): ?>
                                        <div style="margin-bottom: 12px;">
                                            <i class="fas fa-clock" style="color: #64748b; margin-right: 8px; width: 16px;"></i>
                                            <strong>End Time:</strong> <?php echo htmlspecialchars($programme['end_time']); ?>
                                        </div>
                                        <?php endif; ?>
                                        <div style="margin-bottom: 12px;">
                                            <i class="<?php echo htmlspecialchars($programme['programme_icon'] ?? 'fas fa-graduation-cap'); ?>" style="color: #64748b; margin-right: 8px; width: 16px;"></i>
                                            <strong>Icon:</strong> <?php echo htmlspecialchars($programme['programme_icon'] ?? 'fas fa-graduation-cap'); ?>
                                        </div>
                                        <div style="margin-bottom: 12px;">
                                            <i class="fas fa-tag" style="color: #64748b; margin-right: 8px; width: 16px;"></i>
                                            <strong>Badge:</strong> <?php echo htmlspecialchars($programme['programme_badge'] ?? 'Popular'); ?>
                                        </div>
                                        <?php if (!empty($programme['schedule_type'])): ?>
                                        <div style="margin-bottom: 12px;">
                                            <i class="fas fa-calendar" style="color: #64748b; margin-right: 8px; width: 16px;"></i>
                                            <strong>Schedule Type:</strong> <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $programme['schedule_type']))); ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($programme['modules'])): ?>
                                        <div style="margin-bottom: 12px;">
                                            <i class="fas fa-list" style="color: #64748b; margin-right: 8px; width: 16px;"></i>
                                            <strong>Modules:</strong> <?php echo htmlspecialchars($programme['modules']); ?>
                                        </div>
                                        <?php endif; ?>
                                        <div>
                                            <i class="fas fa-toggle-<?php echo $programme['is_active'] ? 'on' : 'off'; ?>" style="color: #64748b; margin-right: 8px; width: 16px;"></i>
                                            <strong>Status:</strong> 
                                            <span class="badge <?php echo $programme['is_active'] ? 'success' : 'warning'; ?>" style="margin-left: 8px;">
                                                <?php echo $programme['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Programme Footer -->
                            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 1px solid #e2e8f0;">
                                <div style="color: #64748b; font-size: 14px;">
                                    <i class="fas fa-calendar" style="margin-right: 6px;"></i>
                                    <strong>Created:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($programme['created_at'])); ?>
                                </div>
                                <div style="color: #64748b; font-size: 14px;">
                                    <i class="fas fa-clock" style="margin-right: 6px;"></i>
                                    <strong>Last Updated:</strong> <?php echo date('F j, Y', strtotime($programme['updated_at'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <div style="padding: 60px 20px; text-align: center; color: #64748b;">
                    <i class="fas fa-graduation-cap" style="font-size: 64px; margin-bottom: 20px; opacity: 0.5;"></i>
                    <h3 style="margin-bottom: 8px; color: #1e293b;">No training programmes yet</h3>
                    <p style="margin: 0; font-size: 16px;">
                        Start building your training portfolio by adding your first programme.
                    </p>
                    <a href="add-programme.php" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-plus"></i>
                        Add Your First Programme
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Autocomplete functionality
        let searchTimeout;
        const searchInput = document.getElementById('search');
        const suggestionsContainer = document.getElementById('searchSuggestions');
        
        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            
            // Clear previous timeout
            clearTimeout(searchTimeout);
            
            // Hide suggestions if query is empty
            if (query.length < 2) {
                suggestionsContainer.style.display = 'none';
                return;
            }
            
            // Show loading state
            suggestionsContainer.innerHTML = '<div class="search-loading"><i class="fas fa-spinner"></i> Searching...</div>';
            suggestionsContainer.style.display = 'block';
            
            // Debounce the search
            searchTimeout = setTimeout(() => {
                fetchSuggestions(query);
            }, 300);
        });
        
        // Handle keyboard navigation
        searchInput.addEventListener('keydown', function(e) {
            const suggestions = suggestionsContainer.querySelectorAll('.suggestion-item');
            const activeSuggestion = suggestionsContainer.querySelector('.suggestion-item.active');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (activeSuggestion) {
                    activeSuggestion.classList.remove('active');
                    const nextSuggestion = activeSuggestion.nextElementSibling;
                    if (nextSuggestion) {
                        nextSuggestion.classList.add('active');
                    } else {
                        suggestions[0]?.classList.add('active');
                    }
                } else {
                    suggestions[0]?.classList.add('active');
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (activeSuggestion) {
                    activeSuggestion.classList.remove('active');
                    const prevSuggestion = activeSuggestion.previousElementSibling;
                    if (prevSuggestion) {
                        prevSuggestion.classList.add('active');
                    } else {
                        suggestions[suggestions.length - 1]?.classList.add('active');
                    }
                } else {
                    suggestions[suggestions.length - 1]?.classList.add('active');
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (activeSuggestion) {
                    const searchValue = activeSuggestion.getAttribute('data-search');
                    searchInput.value = searchValue;
                    suggestionsContainer.style.display = 'none';
                    // Submit the form
                    searchInput.closest('form').submit();
                }
            } else if (e.key === 'Escape') {
                suggestionsContainer.style.display = 'none';
            }
        });
        
        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                suggestionsContainer.style.display = 'none';
            }
        });
        
        function fetchSuggestions(query) {
            const params = new URLSearchParams({
                ajax_search: '1',
                search_term: query
            });
            
            fetch('programmes.php?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    displaySuggestions(data, query);
                })
                .catch(error => {
                    console.error('Error fetching suggestions:', error);
                    suggestionsContainer.innerHTML = '<div class="no-suggestions">Error loading suggestions</div>';
                });
        }
        
        function displaySuggestions(suggestions, query) {
            if (suggestions.length === 0) {
                suggestionsContainer.innerHTML = '<div class="no-suggestions">No matches found</div>';
                return;
            }
            
            const html = suggestions.map(suggestion => {
                const highlightedTitle = highlightText(suggestion.title, query);
                const highlightedCategory = highlightText(suggestion.category, query);
                const highlightedDuration = highlightText(suggestion.duration, query);
                
                return `
                    <div class="suggestion-item" data-search="${suggestion.title}">
                        <i class="fas fa-graduation-cap suggestion-icon"></i>
                        <div class="suggestion-text">
                            <div class="suggestion-name">${highlightedTitle}</div>
                            <div class="suggestion-details">
                                ${highlightedCategory} • ${highlightedDuration}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
            
            suggestionsContainer.innerHTML = html;
            
            // Add click handlers
            suggestionsContainer.querySelectorAll('.suggestion-item').forEach(item => {
                item.addEventListener('click', function() {
                    const searchValue = this.getAttribute('data-search');
                    searchInput.value = searchValue;
                    suggestionsContainer.style.display = 'none';
                    // Submit the form
                    searchInput.closest('form').submit();
                });
            });
        }
        
        function highlightText(text, query) {
            if (!query) return text;
            const regex = new RegExp(`(${query})`, 'gi');
            return text.replace(regex, '<span class="suggestion-highlight">$1</span>');
        }

        function exportProgrammes() {
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
                    <h3 style="margin: 0; color: #1e293b;">Export Programmes</h3>
                </div>
                
                <p style="color: #64748b; margin-bottom: 20px;">
                    Choose the format for your programmes export:
                </p>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #374151;">Export Format</label>
                    <select id="exportFormat" class="form-control">
                        <option value="csv">CSV (Comma Separated Values)</option>
                        <option value="json">JSON (JavaScript Object Notation)</option>
                        <option value="sql">SQL (Database Script)</option>
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
            const exportBtn = document.querySelector('button[onclick="exportProgrammes()"]');
            const originalText = exportBtn.innerHTML;
            
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 4px;"></i>Preparing...';
            exportBtn.disabled = true;
            
            // Get current search and filter parameters
            const searchParams = new URLSearchParams(window.location.search);
            const search = searchParams.get('search') || '';
            const filter = searchParams.get('filter') || '';
            
            // Create export URL with format
            const exportUrl = `export-programmes.php?format=${format}&search=${encodeURIComponent(search)}&filter=${encodeURIComponent(filter)}`;
            
            // Create a temporary link and trigger download
            const link = document.createElement('a');
            link.href = exportUrl;
            link.download = `training_programmes.${format}`;
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
    </script>
    <script src="../assets/js/motion.js?v=20261002-10"></script>
</body>
</html> 