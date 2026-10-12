<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager']);

// Include database connection
require_once '../includes/database.php';

// Handle AJAX search suggestions
if (isset($_GET['ajax_search']) && $_GET['ajax_search']) {
    $search_term = $_GET['search_term'] ?? '';
    $pdo = get_db_connection();
    $suggestions = [];
    
    if ($pdo && $search_term) {
        try {
            $sql = "SELECT DISTINCT title, location, type FROM jobs 
                    WHERE title LIKE ? OR location LIKE ? OR type LIKE ? 
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
$jobs = [];
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
            $where_conditions[] = "(title LIKE ? OR location LIKE ? OR type LIKE ? OR description LIKE ? OR requirements LIKE ?)";
            $search_param = "%$search_term%";
            $params[] = $search_param;
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
        $sql = "SELECT * FROM jobs";
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(" AND ", $where_conditions);
        }
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $jobs = $stmt->fetchAll();
        
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
    <title>Job Management - XpressPoint Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-styles.css?v=20261005-5">
    <link rel="stylesheet" href="css/jobs.css?v=20260930-4">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Jobs', 'fas fa-briefcase'); ?>
    
    <div class="admin-container">
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('jobs'); ?>
        
        <!-- Page Header -->
        <div class="page-header">
            <h2 class="page-title">
                <i class="fas fa-briefcase"></i>
                Career Opportunities
            </h2>
            <div class="d-flex gap-2">
                <a href="add-job.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Add New Job
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
                        <label for="search">Search Jobs</label>
                        <input type="text" id="search" name="search" class="form-control" 
                               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                               placeholder="Search by job title, location, or type..."
                               autocomplete="off">
                        <div class="search-suggestions" id="searchSuggestions">
                            <!-- Suggestions will be populated here -->
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="filter">Filter by Status</label>
                        <select id="filter" name="filter" class="form-control">
                            <option value="">All Jobs</option>
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
                            <a href="jobs.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                Clear
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Jobs Overview -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-between align-center">
                    <div>
                        <h3 style="margin: 0; color: #1e293b; font-size: 18px;">
                            <i class="fas fa-list"></i>
                            Job Postings Overview
                        </h3>
                        <p style="margin: 8px 0 0 0; color: #64748b;">
                            Manage your career opportunities and job postings
                        </p>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 24px; font-weight: 700; color: #111111;">
                            <?php echo count($jobs); ?>
                        </div>
                        <div style="font-size: 14px; color: #64748b;">
                            Total Jobs
                        </div>
                        <div style="margin-top: 12px; display: flex; gap: 8px;">
                            <button onclick="exportJobs()" class="btn btn-secondary" style="background: #6b7280; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500; transition: all 0.2s;" onmouseover="this.style.backgroundColor='#4b5563'" onmouseout="this.style.backgroundColor='#6b7280'">
                                <i class="fas fa-download" style="margin-right: 4px;"></i>
                                Export
                            </button>
                            <a href="import-jobs.php" class="btn btn-primary" style="background: #111111; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center;" onmouseover="this.style.backgroundColor='#111111'" onmouseout="this.style.backgroundColor='#111111'">
                                <i class="fas fa-upload" style="margin-right: 4px;"></i>
                                Import
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Jobs List -->
        <?php if (!empty($jobs)): ?>
            <div class="dense-list">
                <?php foreach ($jobs as $job): ?>
                    <div class="card" id="job-<?php echo $job['id']; ?>">
                        <div class="card-body">
                            <!-- Job Header -->
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
                                <div style="flex: 1;">
                                    <h3 style="margin: 0 0 8px 0; color: #1e293b; font-size: 20px; font-weight: 600;">
                                        <i class="fas fa-briefcase" style="color: #111111; margin-right: 8px;"></i>
                                        <?php echo htmlspecialchars($job['title']); ?>
                                    </h3>
                                    <div style="display: flex; gap: 16px; align-items: center; margin-bottom: 12px;">
                                        <div style="color: #64748b; font-size: 14px;">
                                            <i class="fas fa-map-marker-alt" style="margin-right: 6px;"></i>
                                            <?php echo htmlspecialchars($job['location']); ?>
                                        </div>
                                        <div style="color: #64748b; font-size: 14px;">
                                            <i class="fas fa-building" style="margin-right: 6px;"></i>
                                            <?php echo htmlspecialchars($job['company_name'] ?? 'HR Training'); ?>
                                        </div>
                                        <span class="badge primary">
                                            <i class="fas fa-tag" style="margin-right: 4px;"></i>
                                            <?php echo htmlspecialchars($job['type']); ?>
                                        </span>
                                        <span class="badge <?php echo $job['is_active'] ? 'success' : 'warning'; ?>">
                                            <i class="fas fa-toggle-<?php echo $job['is_active'] ? 'on' : 'off'; ?>" style="margin-right: 4px;"></i>
                                            <?php echo $job['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="d-flex gap-2">
                                    <a href="edit-job.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                        Edit
                                    </a>
                                    <form method="POST" action="job-actions.php" style="display: inline;">
                                        <input type="hidden" name="action" value="duplicate">
                                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-info" data-confirm-title="Duplicate this job?" data-confirm="A copy will be created as inactive." data-confirm-ok="Duplicate" data-confirm-icon="fas fa-copy">
                                            <i class="fas fa-copy"></i>
                                            Duplicate
                                        </button>
                                    </form>
                                    <?php if ($job['is_active']): ?>
                                        <form method="POST" action="job-actions.php" style="display: inline;">
                                            <input type="hidden" name="action" value="deactivate">
                                            <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-warning" data-confirm-title="Deactivate this job?" data-confirm="This job posting will be marked inactive." data-confirm-ok="Deactivate" data-confirm-icon="fas fa-pause">
                                                <i class="fas fa-pause"></i>
                                                Deactivate
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="job-actions.php" style="display: inline;">
                                            <input type="hidden" name="action" value="activate">
                                            <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="fas fa-play"></i>
                                                Activate
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (($_SESSION['admin_role'] ?? 'admin') !== 'manager'): ?>
                                    <form method="POST" action="job-actions.php" style="display: inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" data-confirm-title="Delete this job?" data-confirm="This action cannot be undone." data-confirm-ok="Delete" data-confirm-icon="fas fa-trash">
                                            <i class="fas fa-trash"></i>
                                            Delete
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Job Details -->
                            <div class="record-extra" style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 16px;">
                                <div>
                                    <h4 style="color: #1e293b; margin-bottom: 12px; font-size: 16px; font-weight: 600;">
                                        <i class="fas fa-file-alt" style="color: #111111; margin-right: 8px;"></i>
                                        Job Description
                                    </h4>
                                    <div style="color: #374151; line-height: 1.6; background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                        <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                                    </div>
                                </div>
                                <div>
                                    <h4 style="color: #1e293b; margin-bottom: 12px; font-size: 16px; font-weight: 600;">
                                        <i class="fas fa-list-check" style="color: #111111; margin-right: 8px;"></i>
                                        Requirements
                                    </h4>
                                    <div style="color: #374151; line-height: 1.6; background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                        <?php echo nl2br(htmlspecialchars($job['requirements'])); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Job Footer -->
                            <div class="record-extra" style="display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 1px solid #e2e8f0;">
                                <div style="color: #64748b; font-size: 14px;">
                                    <i class="fas fa-calendar" style="margin-right: 6px;"></i>
                                    <strong>Posted:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($job['created_at'])); ?>
                                </div>
                                <div style="color: #64748b; font-size: 14px;">
                                    <i class="fas fa-clock" style="margin-right: 6px;"></i>
                                    <strong>Last Updated:</strong> <?php echo date('F j, Y', strtotime($job['updated_at'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <div style="padding: 60px 20px; text-align: center; color: #64748b;">
                    <i class="fas fa-briefcase" style="font-size: 64px; margin-bottom: 20px; opacity: 0.5;"></i>
                    <h3 style="margin-bottom: 8px; color: #1e293b;">No jobs posted yet</h3>
                    <p style="margin: 0; font-size: 16px;">
                        Start building your team by posting your first job opportunity.
                    </p>
                    <a href="add-job.php" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-plus"></i>
                        Add Your First Job
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
            
            fetch('jobs.php?' + params.toString())
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
                const highlightedLocation = highlightText(suggestion.location, query);
                const highlightedType = highlightText(suggestion.type, query);
                
                return `
                    <div class="suggestion-item" data-search="${suggestion.title}">
                        <i class="fas fa-briefcase suggestion-icon"></i>
                        <div class="suggestion-text">
                            <div class="suggestion-name">${highlightedTitle}</div>
                            <div class="suggestion-details">
                                ${highlightedLocation} • ${highlightedType}
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

        function exportJobs() {
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
                    <h3 style="margin: 0; color: #1e293b;">Export Jobs</h3>
                </div>
                
                <p style="color: #64748b; margin-bottom: 20px;">
                    Choose the format for your job export:
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
            const exportBtn = document.querySelector('button[onclick="exportJobs()"]');
            const originalText = exportBtn.innerHTML;
            
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 4px;"></i>Preparing...';
            exportBtn.disabled = true;
            
            // Get current search and filter parameters
            const searchParams = new URLSearchParams(window.location.search);
            const search = searchParams.get('search') || '';
            const filter = searchParams.get('filter') || '';
            
            // Create export URL with format
            const exportUrl = `export-jobs.php?format=${format}&search=${encodeURIComponent(search)}&filter=${encodeURIComponent(filter)}`;
            
            // Create a temporary link and trigger download
            const link = document.createElement('a');
            link.href = exportUrl;
            link.download = `job_postings.${format}`;
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