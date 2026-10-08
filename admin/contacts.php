<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Include database connection
require_once '../includes/database.php';
require_once '../includes/blacklist-lookup.php';

// Handle AJAX search suggestions
if (isset($_GET['ajax_search']) && $_GET['ajax_search']) {
    $search_term = $_GET['search_term'] ?? '';
    $pdo = get_db_connection();
    $suggestions = [];
    
    if ($pdo && $search_term) {
        try {
            $sql = "SELECT DISTINCT name, email, company FROM contacts 
                    WHERE name LIKE ? OR email LIKE ? OR company LIKE ? 
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

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $contact_id = $_POST['contact_id'] ?? null;
    $new_status = $_POST['status'] ?? null;
    
    if ($contact_id && $new_status) {
        $pdo = get_db_connection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE contacts SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $contact_id]);
                $success_message = "Contact status updated successfully!";
            } catch (PDOException $e) {
                $error_message = "Error updating contact status.";
            }
        } else {
            $error_message = "Database connection failed.";
        }
    }
}

// Get filter parameters
$filter = $_GET['filter'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$pdo = get_db_connection();
$contacts = [];
$blacklist_lookup = [];
$total_contacts = 0;
$total_pages = 0;

if ($pdo) {
    try {
        // Build query based on filters
        $where_conditions = [];
        $params = [];
        
        if ($filter === 'custom') {
            $where_conditions[] = "interest = 'custom'";
        } elseif ($filter && $filter !== 'all') {
            $where_conditions[] = "status = ?";
            $params[] = $filter;
        }
        
        if ($search) {
            $where_conditions[] = "(name LIKE ? OR email LIKE ? OR company LIKE ?)";
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM contacts $where_clause";
        $stmt = $pdo->prepare($count_sql);
        $stmt->execute($params);
        $total_contacts = $stmt->fetchColumn();
        $total_pages = ceil($total_contacts / $per_page);
        
        // Get contacts with pagination
        $sql = "SELECT * FROM contacts $where_clause ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $contacts = $stmt->fetchAll();
        $blacklist_lookup = load_blacklist_lookup($pdo);
        
    } catch (PDOException $e) {
        // Handle error silently for demo
    }
}

$interest_labels = [
    'hr-leadership' => 'HR Leadership',
    'employee-relations' => 'Employee Relations',
    'hr-analytics' => 'HR Analytics',
    'hr-compliance' => 'HR Compliance',
    'recruitment' => 'Recruitment',
    'compensation' => 'Compensation',
    'custom' => 'Custom Service'
];

$status_labels = [
    'new' => 'New',
    'contacted' => 'Contacted',
    'qualified' => 'Qualified',
    'blacklisted' => 'Blacklisted'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacts - XpressPoint Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-styles.css?v=20261006-3">
    <link rel="stylesheet" href="css/contacts.css?v=20261006-3">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Contacts', 'fas fa-users'); ?>
    
    <div class="admin-container">
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('contacts'); ?>
        
        <!-- Page Header -->
        <div class="page-header">
            <h2 class="page-title">
                <i class="fas fa-users"></i>
                Contact Inquiries
            </h2>
            <div class="d-flex gap-2">
                <button onclick="exportContacts()" class="btn btn-primary">
                    <i class="fas fa-download"></i>
                    Export
                </button>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
        </div>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div style="background: #d1fae5; border: 1px solid #10b981; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-check-circle" style="color: #10b981;"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Filters and Search -->
        <div class="card mb-3">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Search & Filters</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="search-filter-container d-flex gap-3" style="align-items: end;">
                    <div class="form-group search-container">
                        <label for="search">Search Contacts</label>
                        <input type="text" id="search" name="search" class="form-control search-input" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search by name, email, or company..."
                               autocomplete="off">
                        <div class="search-suggestions" id="searchSuggestions">
                            <!-- Suggestions will be populated here -->
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="filter">Filter by Type</label>
                        <select id="filter" name="filter" class="form-control filter-select">
                            <option value="">All Contacts</option>
                            <option value="custom" <?php echo $filter === 'custom' ? 'selected' : ''; ?>>Custom Requests</option>
                            <option value="new" <?php echo $filter === 'new' ? 'selected' : ''; ?>>New</option>
                            <option value="contacted" <?php echo $filter === 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                            <option value="qualified" <?php echo $filter === 'qualified' ? 'selected' : ''; ?>>Qualified</option>
                            <option value="blacklisted" <?php echo $filter === 'blacklisted' ? 'selected' : ''; ?>>Blacklisted</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Search
                        </button>
                    </div>
                    <?php if ($search || $filter): ?>
                        <div class="form-group">
                            <a href="contacts.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                Clear
                            </a>
            </div>
        <?php endif; ?>
                </form>
            </div>
        </div>
        
        <!-- Results Summary -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-between align-center">
                    <div>
                        <h3 style="margin: 0; color: #1e293b; font-size: 18px;">
                            <i class="fas fa-list"></i>
                            Contact Results
                        </h3>
                        <p style="margin: 8px 0 0 0; color: #64748b;">
                            Showing <?php echo count($contacts); ?> of <?php echo $total_contacts; ?> contacts
                            <?php if ($search || $filter): ?>
                                (filtered results)
                            <?php endif; ?>
                        </p>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 24px; font-weight: 700; color: #111111;">
                            <?php echo number_format($total_contacts); ?>
                        </div>
                        <div style="font-size: 14px; color: #64748b;">
                            Total Contacts
                        </div>
                    </div>
                </div>
                </div>
            </div>
            
        <!-- Contacts List -->
            <?php if (!empty($contacts)): ?>
            <div style="display: grid; gap: 20px;">
                <?php foreach ($contacts as $contact):
                    $bl_info = blacklist_match($blacklist_lookup, $contact['name'], $contact['phone'] ?? '');
                    $is_blacklisted = (($contact['status'] ?? '') === 'blacklisted') || $bl_info !== null;
                ?>
                    <div class="card contact-card<?php echo $is_blacklisted ? ' is-blacklisted' : ''; ?>">
                        <div class="card-body">
                            <!-- Contact Header -->
                            <div class="contact-info" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
                                <div style="flex: 1;">
                                    <h3 style="margin: 0 0 8px 0; color: #1e293b; font-size: 20px; font-weight: 600; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                        <span>
                                            <i class="fas fa-<?php echo $is_blacklisted ? 'user-slash' : 'user'; ?>" style="color: <?php echo $is_blacklisted ? '#b91c1c' : '#111111'; ?>; margin-right: 8px;"></i>
                                            <?php echo htmlspecialchars($contact['name']); ?>
                                        </span>
                                        <?php if ($is_blacklisted): ?>
                                        <span class="blacklist-flag" title="<?php echo htmlspecialchars($bl_info['reason'] ?? 'Marked as blacklisted'); ?>">
                                            <i class="fas fa-ban"></i> Blacklisted
                                        </span>
                                        <?php endif; ?>
                                    </h3>
                                    <div class="contact-meta" style="display: flex; gap: 16px; align-items: center; margin-bottom: 12px;">
                                        <div style="color: #64748b; font-size: 14px;">
                                            <i class="fas fa-envelope" style="margin-right: 6px;"></i>
                                            <?php echo htmlspecialchars($contact['email']); ?>
                                        </div>
                                        <?php if ($contact['company']): ?>
                                            <div style="color: #64748b; font-size: 14px;">
                                                <i class="fas fa-building" style="margin-right: 6px;"></i>
                                                <?php echo htmlspecialchars($contact['company']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <span class="badge <?php echo $contact['interest'] === 'custom' ? 'custom' : 'primary'; ?>">
                                            <i class="fas fa-tag" style="margin-right: 4px;"></i>
                                            <?php echo htmlspecialchars($interest_labels[$contact['interest']] ?? $contact['interest']); ?>
                                        </span>
                                        <span class="badge status-<?php echo $contact['status'] ?? 'new'; ?>">
                                            <i class="fas fa-circle" style="margin-right: 4px;"></i>
                                            <?php echo htmlspecialchars($status_labels[$contact['status'] ?? 'new']); ?>
                                        </span>
                                        <div style="color: #64748b; font-size: 14px;">
                                            <i class="fas fa-calendar" style="margin-right: 6px;"></i>
                                            <?php echo date('M j, Y', strtotime($contact['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Contact Details -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 16px;">
                                <div>
                                    <h4 style="color: #1e293b; margin-bottom: 12px; font-size: 16px; font-weight: 600;">
                                        <i class="fas fa-file-alt" style="color: #111111; margin-right: 8px;"></i>
                                        Message
                                    </h4>
                                    <div style="color: #374151; line-height: 1.6; background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                        <?php echo nl2br(htmlspecialchars($contact['message'])); ?>
                                    </div>
                                </div>
                                <div>
                                    <h4 style="color: #1e293b; margin-bottom: 12px; font-size: 16px; font-weight: 600;">
                                        <i class="fas fa-info-circle" style="color: #111111; margin-right: 8px;"></i>
                                        Contact Details
                                    </h4>
                                    <div style="color: #374151; line-height: 1.8; background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                        <div style="margin-bottom: 12px;">
                                            <i class="fas fa-user" style="color: #64748b; margin-right: 8px; width: 16px;"></i>
                                            <strong>Name:</strong> <?php echo htmlspecialchars($contact['name']); ?>
                                        </div>
                                        <div style="margin-bottom: 12px;">
                                            <i class="fas fa-envelope" style="color: #64748b; margin-right: 8px; width: 16px;"></i>
                                            <strong>Email:</strong> <?php echo htmlspecialchars($contact['email']); ?>
                                        </div>
                                        <?php if ($contact['company']): ?>
                                            <div style="margin-bottom: 12px;">
                                                <i class="fas fa-building" style="color: #64748b; margin-right: 8px; width: 16px;"></i>
                                                <strong>Company:</strong> <?php echo htmlspecialchars($contact['company']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($contact['phone']): ?>
                                            <div style="margin-bottom: 12px;">
                                                <i class="fas fa-phone" style="color: #64748b; margin-right: 8px; width: 16px;"></i>
                                                <strong>Phone:</strong> <?php echo htmlspecialchars($contact['phone']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <i class="fas fa-tag" style="color: #64748b; margin-right: 8px; width: 16px;"></i>
                                            <strong>Interest:</strong> 
                                            <span class="badge <?php echo $contact['interest'] === 'custom' ? 'custom' : 'primary'; ?>" style="margin-left: 8px;">
                                                <?php echo htmlspecialchars($interest_labels[$contact['interest']] ?? $contact['interest']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                        </div>
                            
                            <!-- Contact Footer -->
                            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 1px solid #e2e8f0;">
                                <div style="color: #64748b; font-size: 14px;">
                                    <i class="fas fa-calendar" style="margin-right: 6px;"></i>
                                    <strong>Submitted:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($contact['created_at'])); ?>
                                </div>
                                <div style="display: flex; gap: 12px; align-items: center;">
                                    <div style="color: #64748b; font-size: 14px;">
                                        <i class="fas fa-tag" style="margin-right: 6px;"></i>
                                        <strong>Interest:</strong> <?php echo htmlspecialchars($interest_labels[$contact['interest']] ?? $contact['interest']); ?>
                                    </div>
                                    <button onclick="updateStatus(<?php echo $contact['id']; ?>, '<?php echo $contact['status'] ?? 'new'; ?>')" class="btn btn-secondary" style="background: #6b7280; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500; display: flex; align-items: center; gap: 6px; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#4b5563'" onmouseout="this.style.backgroundColor='#6b7280'">
                                        <i class="fas fa-edit"></i>
                                        Update Status
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <div style="padding: 60px 20px; text-align: center; color: #64748b;">
                    <i class="fas fa-users" style="font-size: 64px; margin-bottom: 20px; opacity: 0.5;"></i>
                    <h3 style="margin-bottom: 8px; color: #1e293b;">No contacts found</h3>
                    <p style="margin: 0; font-size: 16px;">
                        <?php if ($search || $filter): ?>
                            No contacts match your current search criteria.
            <?php else: ?>
                            No contact inquiries have been submitted yet.
                        <?php endif; ?>
                    </p>
                </div>
                </div>
            <?php endif; ?>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>" 
                       class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal" style="display: none; position: fixed; z-index: 1070; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(2px);">
        <div class="modal-content" style="background-color: white; margin: 5% auto; padding: 0; border-radius: 12px; width: 90%; max-width: 450px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
            <div class="modal-header" style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: #1f2937; font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-edit" style="color: #111111;"></i>
                    Update Contact Status
                </h3>
                <button class="modal-close" onclick="closeModal()" style="background: none; border: none; font-size: 18px; color: #6b7280; cursor: pointer; padding: 4px; border-radius: 4px; transition: color 0.2s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#6b7280'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" class="status-form" style="padding: 24px;">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="contact_id" id="contactId">
                <div class="form-group" style="margin-bottom: 24px;">
                    <label for="status" style="display: block; margin-bottom: 8px; color: #374151; font-weight: 500; font-size: 14px;">Contact Status</label>
                    <select name="status" id="statusSelect" class="form-control" required>
                        <option value="new">New</option>
                        <option value="contacted">Contacted</option>
                        <option value="qualified">Qualified</option>
                        <option value="blacklisted">Blacklisted</option>
                    </select>
                </div>
                <div class="form-actions" style="display: flex; gap: 12px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()" style="flex: 1; background: #f3f4f6; color: #374151; border: none; padding: 12px 16px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#e5e7eb'" onmouseout="this.style.backgroundColor='#f3f4f6'">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" style="flex: 1; background: #111111; color: white; border: none; padding: 12px 16px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#111111'" onmouseout="this.style.backgroundColor='#111111'">
                        Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function updateStatus(contactId, currentStatus) {
            document.getElementById('contactId').value = contactId;
            document.getElementById('statusSelect').value = currentStatus;
            
            const modal = document.getElementById('statusModal');
            modal.style.opacity = '0';
            modal.style.display = 'block';
            setTimeout(() => {
                modal.style.opacity = '1';
            }, 10);
        }

        function closeModal() {
            const statusModal = document.getElementById('statusModal');
            if (statusModal) {
                statusModal.style.opacity = '0';
                setTimeout(() => {
                    statusModal.style.display = 'none';
                }, 200);
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const statusModal = document.getElementById('statusModal');
            if (event.target === statusModal) {
                closeModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
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
            
            fetch('contacts.php?' + params.toString())
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
                const highlightedName = highlightText(suggestion.name, query);
                const highlightedEmail = highlightText(suggestion.email, query);
                const highlightedCompany = suggestion.company ? highlightText(suggestion.company, query) : '';
                
                return `
                    <div class="suggestion-item" data-search="${suggestion.name}">
                        <i class="fas fa-user suggestion-icon"></i>
                        <div class="suggestion-text">
                            <div class="suggestion-name">${highlightedName}</div>
                            <div class="suggestion-details">
                                ${highlightedEmail}
                                ${highlightedCompany ? ' • ' + highlightedCompany : ''}
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
        
        function exportContacts() {
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
                    <h3 style="margin: 0; color: #1e293b;">Export Contacts</h3>
                </div>
                
                <p style="color: #64748b; margin-bottom: 20px;">
                    Choose the format for your contacts export:
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
            const exportBtn = document.querySelector('button[onclick="exportContacts()"]');
            const originalText = exportBtn.innerHTML;
            
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 4px;"></i>Preparing...';
            exportBtn.disabled = true;
            
            // Get current search and filter parameters
            const searchParams = new URLSearchParams(window.location.search);
            const search = searchParams.get('search') || '';
            const filter = searchParams.get('filter') || '';
            
            // Create export URL with format
            const exportUrl = `export-contacts.php?format=${format}&search=${encodeURIComponent(search)}&filter=${encodeURIComponent(filter)}`;
            
            // Create a temporary link and trigger download
            const link = document.createElement('a');
            link.href = exportUrl;
            link.download = `contacts.${format}`;
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