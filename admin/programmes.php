<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager', 'trainer']);
csrf_guard();
if (auth_is_trainer()) {
    header('Location: facilities.php');
    exit;
}

// Include database connection
require_once '../includes/database.php';
require_once __DIR__ . '/includes/ui.php';
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
                    WHERE (title LIKE ? OR category LIKE ? OR duration LIKE ?)";
            $search_param = "%$search_term%";
            $search_params = [$search_param, $search_param, $search_param];
            if (auth_is_trainer()) {
                $sql .= " AND owner_user_id = ?";
                $search_params[] = (int) (auth_user()['id'] ?? 0);
            }
            $sql .= " LIMIT 10";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($search_params);
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
$isTrainerList = auth_is_trainer();
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

$programme_stats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'webinar' => 0, 'training' => 0];
$list_page = max(1, (int) ($_GET['page'] ?? 1));
$list_per_page = ui_page_size(25);
$list_total = 0;
$can_delete_programmes = (($_SESSION['admin_role'] ?? 'admin') !== 'manager');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo && in_array($_POST['action'] ?? '', ['bulk_delete', 'bulk_update_status'], true)) {
    $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
    if (($_POST['action'] ?? '') === 'bulk_delete' && $can_delete_programmes) {
        $stmt = $pdo->prepare("DELETE FROM programmes WHERE id = ?");
        foreach ($ids as $id) {
            $stmt->execute([$id]);
        }
        $message = count($ids) . ' programme' . (count($ids) === 1 ? '' : 's') . ' deleted.';
    } elseif (in_array($_POST['status'] ?? '', ['active', 'inactive'], true)) {
        $active = $_POST['status'] === 'active' ? 1 : 0;
        $stmt = $active
            ? $pdo->prepare("UPDATE programmes SET is_active = 1 WHERE id = ?")
            : $pdo->prepare("UPDATE programmes SET is_active = 0, registration_status = 'closed' WHERE id = ?");
        foreach ($ids as $id) {
            $stmt->execute([$id]);
        }
        $message = count($ids) . ' programme' . (count($ids) === 1 ? '' : 's') . ' updated.';
    }
}

if ($pdo) {
    try {
        foreach ($pdo->query("SELECT is_active, COUNT(*) AS count FROM programmes GROUP BY is_active") as $row) {
            $key = ((int) $row['is_active'] === 1) ? 'active' : 'inactive';
            $programme_stats[$key] = (int) $row['count'];
            $programme_stats['total'] += (int) $row['count'];
        }
        foreach ($pdo->query("SELECT programme_type, COUNT(*) AS count FROM programmes GROUP BY programme_type") as $row) {
            $type = (string) $row['programme_type'];
            if (isset($programme_stats[$type])) {
                $programme_stats[$type] = (int) $row['count'];
            }
        }

        // Build the query with filters
        $where_conditions = [];
        $params = [];
        
        if (auth_is_trainer()) {
            $where_conditions[] = "programmes.owner_user_id = ?";
            $params[] = (int) (auth_user()['id'] ?? 0);
        }

        // Search filter
        if (!empty($_GET['search'])) {
            $search_term = $_GET['search'];
            $where_conditions[] = "(programmes.title LIKE ? OR programmes.category LIKE ? OR programmes.duration LIKE ? OR programmes.description LIKE ?)";
            $search_param = "%$search_term%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        // Status filter
        if (!empty($_GET['filter'])) {
            if ($_GET['filter'] === 'active') {
                $where_conditions[] = "programmes.is_active = 1";
            } elseif ($_GET['filter'] === 'inactive') {
                $where_conditions[] = "programmes.is_active = 0";
            } elseif ($_GET['filter'] === 'webinar') {
                $where_conditions[] = "programmes.programme_type = 'webinar'";
            } elseif ($_GET['filter'] === 'training') {
                $where_conditions[] = "programmes.programme_type = 'training'";
            }
        }
        
        // Build the final query
        $sql = "SELECT programmes.*, users.display_name AS owner_name, users.email AS owner_email FROM programmes LEFT JOIN users ON users.id = programmes.owner_user_id";
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(" AND ", $where_conditions);
        }
        $sql .= " ORDER BY programmes.created_at DESC";
        $count_sql = "SELECT COUNT(*) FROM programmes LEFT JOIN users ON users.id = programmes.owner_user_id";
        if (!empty($where_conditions)) {
            $count_sql .= " WHERE " . implode(" AND ", $where_conditions);
        }
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($params);
        $list_total = (int) $count_stmt->fetchColumn();
        $list_pages = max(1, (int) ceil($list_total / $list_per_page));
        if ($list_page > $list_pages) {
            $list_page = $list_pages;
        }
        $sql .= " LIMIT " . (int) $list_per_page . " OFFSET " . (int) (($list_page - 1) * $list_per_page);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $programmes = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        $error = 'The request could not be completed.';
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include_once __DIR__ . '/includes/theme-boot.php'; ?>
    <link rel="stylesheet" href="<?php echo admin_asset('admin.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/programmes.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/list.css'); ?>">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Training Programmes', 'fas fa-graduation-cap'); ?>

    <div class="admin-container">
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('programmes'); ?>

        <?php
        $programme_filter = (string) ($_GET['filter'] ?? '');
        $programme_search = (string) ($_GET['search'] ?? '');
        renderPageHead(
            'Training Programmes',
            'Open, close, and review the programmes people can join.',
            '<a class="btn btn-primary" href="add-programme.php"><i class="fas fa-plus" aria-hidden="true"></i> New programme</a>'
        );
        if ($error) {
            echo '<div class="alert alert-error"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> ' . ui_h($error) . '</div>';
        }
        if ($message) {
            echo '<div class="alert alert-success"><i class="fas fa-check-circle" aria-hidden="true"></i> ' . ui_h($message) . '</div>';
        }
        renderStatusChips([
            ['value' => '', 'label' => 'All', 'count' => $programme_stats['total']],
            ['value' => 'active', 'label' => 'Active', 'count' => $programme_stats['active']],
            ['value' => 'inactive', 'label' => 'Inactive', 'count' => $programme_stats['inactive']],
            ['value' => 'training', 'label' => 'Training', 'count' => $programme_stats['training']],
            ['value' => 'webinar', 'label' => 'Webinar', 'count' => $programme_stats['webinar']],
        ], $programme_filter);
        $programme_active = [];
        if ($programme_search !== '') {
            $programme_active[] = ['label' => 'Search: ' . $programme_search, 'href' => ui_query(['search' => null], ['page'])];
        }
        if ($programme_filter !== '') {
            $programme_active[] = ['label' => 'Filter: ' . $programme_filter, 'href' => ui_query(['filter' => null], ['page'])];
        }
        renderListToolbar(
            'search',
            $programme_search,
            'Search title, category, or duration',
            '',
            '<button class="btn btn-secondary" type="button" onclick="exportProgrammes()">Export</button>',
            'programmes.php',
            $programme_active
        );
        echo '<div class="search-suggestions" id="searchSuggestions"></div>';
        ?>
        <?php if (empty($programmes)): ?>
            <?php
            if ($list_total === 0 && $programme_search === '' && $programme_filter === '') {
                renderEmptyState('fas fa-book-open', 'No programmes yet', 'Create a programme so people can register.', 'add-programme.php', 'New programme');
            } else {
                renderEmptyState('fas fa-search', 'No matching programmes', 'Nothing matches the current search or filter.', 'programmes.php', 'Clear filters');
            }
            ?>
        <?php else: ?>
            <form method="post" id="listBulkForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" id="bulkAction" value="">
                <?php renderBulkBar(['active' => 'Active', 'inactive' => 'Inactive'], $can_delete_programmes); ?>
                <div class="list-table-wrap">
                    <table class="list-table">
                        <thead>
                            <tr>
                                <th scope="col"><input type="checkbox" id="listSelectAll" aria-label="Select all rows"></th>
                                <th scope="col">Title</th>
                                <th scope="col">Type</th>
                                <th scope="col">Price</th>
                                <th scope="col">Status</th>
                                <th scope="col"><span class="u-clip">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($programmes as $programme): ?>
                            <tr>
                                <td class="list-select" data-label=""><input type="checkbox" class="list-check" name="ids[]" value="<?php echo (int) $programme['id']; ?>" aria-label="Select <?php echo ui_h($programme['title']); ?>"></td>
                                <td data-label="Title"><?php echo ui_h($programme['title']); ?></td>
                                <td data-label="Type"><?php renderStatusBadge($programme['programme_type'] ?? 'training'); ?></td>
                                <td data-label="Price"><?php echo formatPrice(getDisplayPrice($programme) ?? 0); ?></td>
                                <td data-label="Status"><?php renderStatusBadge(!empty($programme['is_active']) ? 'active' : 'inactive'); ?></td>
                                <td data-label="Actions">
                                    <?php
                                    $actions = [
                                        ['label' => 'Edit', 'icon' => 'fas fa-edit', 'href' => 'edit-programme.php?id=' . (int) $programme['id']],
                                        ['label' => 'Duplicate', 'icon' => 'fas fa-copy', 'onclick' => 'postProgramme(' . (int) $programme['id'] . ', "duplicate")'],
                                    ];
                                    if (!empty($programme['is_active'])) {
                                        $actions[] = ['label' => 'Deactivate', 'icon' => 'fas fa-pause', 'onclick' => 'postProgramme(' . (int) $programme['id'] . ', "deactivate")'];
                                    } else {
                                        $actions[] = ['label' => 'Activate', 'icon' => 'fas fa-play', 'onclick' => 'postProgramme(' . (int) $programme['id'] . ', "activate")'];
                                    }
                                    if ($can_delete_programmes) {
                                        $actions[] = ['label' => 'Delete', 'icon' => 'fas fa-trash', 'danger' => true, 'onclick' => 'postProgramme(' . (int) $programme['id'] . ', "delete")'];
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
            <?php renderPagination($list_page, $list_per_page, $list_total); ?>
        <?php endif; ?>
    </div>
    <script>
        function postProgramme(id, action) {
            var labels = {
                duplicate: ['Duplicate this programme?', 'A copy will be created as inactive.', 'Duplicate', 'fas fa-copy'],
                deactivate: ['Deactivate this programme?', 'This programme will be hidden and registration will be closed.', 'Deactivate', 'fas fa-pause'],
                delete: ['Delete this programme?', 'This action cannot be undone.', 'Delete', 'fas fa-trash']
            };
            var run = function () {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'programme-actions.php';
                form.innerHTML = '<input type="hidden" name="action" value="' + action + '"><input type="hidden" name="programme_id" value="' + id + '">';
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
            suggestionsContainer.innerHTML = '<div class="search-loading"><i class="fas fa-spinner" aria-hidden="true"></i> Searching...</div>';
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
                        <i class="fas fa-graduation-cap suggestion-icon" aria-hidden="true"></i>
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

        var exportSelection = [];
        function exportProgrammes(ids) {
            exportSelection = ids || [];
            showExportModal();
        }
        window.exportApplications = exportProgrammes;
        
        function showExportModal() {
            // Create modal overlay
            const overlay = document.createElement('div');
            overlay.className = 'modal-overlay is-open';
            
            const modal = document.createElement('div');
            modal.className = 'export-sheet';
            
            modal.innerHTML = `
                <div class="cluster u-mb-5">
                    <i class="fas fa-download icon-lg" aria-hidden="true"></i>
                    <h3 class="modal-title">Export Programmes</h3>
                </div>
                
                <p class="u-mb-5">
                    Choose the format for your programmes export:
                </p>
                
                <div class="u-mb-5">
                    <label class="field-label">Export Format</label>
                    <select id="exportFormat" class="form-control">
                        <option value="csv">CSV (Comma Separated Values)</option>
                        <option value="json">JSON (JavaScript Object Notation)</option>
                        <option value="sql">SQL (Database Script)</option>
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
            const exportBtn = document.querySelector('button[onclick="exportProgrammes()"]');
            
            // Get current search and filter parameters
            const searchParams = new URLSearchParams(window.location.search);
            const search = searchParams.get('search') || '';
            const filter = searchParams.get('filter') || '';
            
            // Create export URL with format
            const selected = (exportSelection || []).join(',');
            const exportUrl = `export-programmes.php?format=${format}&search=${encodeURIComponent(search)}&filter=${encodeURIComponent(filter)}` + (selected ? `&ids=${encodeURIComponent(selected)}` : '');
            
            var exportName = `training_programmes.${format}`;
            if (window.xpmExportDownload) {
                window.xpmExportDownload(exportUrl, exportName, exportBtn);
            } else {
                window.location.href = exportUrl;
            }
            closeExportModal();
        }
    </script>
    <script defer src="<?php echo admin_asset('js/list.js'); ?>"></script>
</body>
</html> 