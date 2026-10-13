<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager']);
csrf_guard();

// Include database connection
require_once '../includes/database.php';
require_once __DIR__ . '/includes/ui.php';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['bulk_delete', 'bulk_update_status'], true)) {
    if (!auth_verify_csrf($_POST['csrf_token'] ?? '')) {
        $error_message = 'The form expired. Please try again.';
    } else {
        $pdo = get_db_connection();
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
        $allowed = ['new', 'contacted', 'qualified', 'blacklisted'];
        if ($pdo && ($_POST['action'] ?? '') === 'bulk_delete') {
            $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = ?");
            foreach ($ids as $id) {
                $stmt->execute([$id]);
            }
            header('Location: contacts.php?removed=1');
            exit;
        } elseif ($pdo && in_array($_POST['status'] ?? '', $allowed, true)) {
            foreach ($ids as $id) {
                $previous = $pdo->prepare("SELECT status FROM contacts WHERE id = ?");
                $previous->execute([$id]);
                $previous_status = (string) $previous->fetchColumn();
                $pdo->prepare("UPDATE contacts SET status = ? WHERE id = ?")->execute([$_POST['status'], $id]);
                blacklist_sync_status($pdo, 'contact', $id, $previous_status, (string) $_POST['status']);
            }
            $success_message = count($ids) . ' contact status' . (count($ids) === 1 ? '' : 'es') . ' updated.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $contact_id = (int) ($_POST['contact_id'] ?? 0);
    if (!auth_verify_csrf($_POST['csrf_token'] ?? '')) {
        $error_message = 'The form expired. Please try again.';
    } elseif ($contact_id > 0) {
        $pdo = get_db_connection();
        if ($pdo) {
            $pdo->prepare("DELETE FROM contacts WHERE id = ?")->execute([$contact_id]);
            header('Location: contacts.php?removed=1');
            exit;
        }
        $error_message = 'Database connection failed.';
    }
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $contact_id = $_POST['contact_id'] ?? null;
    $new_status = $_POST['status'] ?? null;
    
    if ($contact_id && $new_status) {
        $pdo = get_db_connection();
        if ($pdo) {
            try {
                $previous = $pdo->prepare("SELECT status FROM contacts WHERE id = ?");
                $previous->execute([$contact_id]);
                $previous_status = (string)$previous->fetchColumn();
                $stmt = $pdo->prepare("UPDATE contacts SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $contact_id]);
                blacklist_sync_status($pdo, 'contact', (int)$contact_id, $previous_status, (string)$new_status);
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
$per_page = ui_page_size(25);
$offset = ($page - 1) * $per_page;

$pdo = get_db_connection();
$contacts = [];
$contact_stats = ['total' => 0, 'custom' => 0, 'new' => 0, 'contacted' => 0, 'qualified' => 0, 'blacklisted' => 0];
$blacklist_lookup = [];
$total_contacts = 0;
$total_pages = 0;

if ($pdo) {
    try {
        $contact_stats = ['total' => 0, 'custom' => 0, 'new' => 0, 'contacted' => 0, 'qualified' => 0, 'blacklisted' => 0];
        $contact_stats['total'] = (int) $pdo->query("SELECT COUNT(*) FROM contacts")->fetchColumn();
        $contact_stats['custom'] = (int) $pdo->query("SELECT COUNT(*) FROM contacts WHERE interest = 'custom'")->fetchColumn();
        foreach ($pdo->query("SELECT status, COUNT(*) AS count FROM contacts GROUP BY status") as $row) {
            if (isset($contact_stats[$row['status']])) {
                $contact_stats[$row['status']] = (int) $row['count'];
            }
        }

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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include_once __DIR__ . '/includes/theme-boot.php'; ?>
    <link rel="stylesheet" href="<?php echo admin_asset('admin.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/contacts.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/list.css'); ?>">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Contacts', 'fas fa-users'); ?>
    
    <div class="admin-container">
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('contacts'); ?>
        
        <?php
        $contact_filter = (string) ($filter ?? '');
        $contact_search = (string) ($search ?? '');
        renderPageHead('Contact Inquiries', 'Messages from the site, including custom requests.');
        if (isset($_GET['removed'])) {
            echo '<div class="alert alert-success"><i class="fas fa-check-circle" aria-hidden="true"></i> Contact removed.</div>';
        }
        if (isset($success_message)) {
            echo '<div class="alert alert-success"><i class="fas fa-check-circle" aria-hidden="true"></i> ' . ui_h($success_message) . '</div>';
        }
        if (isset($error_message)) {
            echo '<div class="alert alert-error"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> ' . ui_h($error_message) . '</div>';
        }
        renderStatusChips([
            ['value' => '', 'label' => 'All', 'count' => $contact_stats['total']],
            ['value' => 'custom', 'label' => 'Custom requests', 'count' => $contact_stats['custom']],
            ['value' => 'new', 'label' => 'New', 'count' => $contact_stats['new']],
            ['value' => 'contacted', 'label' => 'Contacted', 'count' => $contact_stats['contacted']],
            ['value' => 'qualified', 'label' => 'Qualified', 'count' => $contact_stats['qualified']],
            ['value' => 'blacklisted', 'label' => 'Blacklisted', 'count' => $contact_stats['blacklisted']],
        ], $contact_filter);
        $contact_active = [];
        if ($contact_search !== '') {
            $contact_active[] = ['label' => 'Search: ' . $contact_search, 'href' => ui_query(['search' => null], ['page'])];
        }
        if ($contact_filter !== '') {
            $contact_active[] = ['label' => 'Filter: ' . $contact_filter, 'href' => ui_query(['filter' => null], ['page'])];
        }
        renderListToolbar('search', $contact_search, 'Search name, email, or company', '', '<button class="btn btn-secondary" type="button" onclick="exportContacts()">Export</button>', 'contacts.php', $contact_active);
        echo '<div class="search-suggestions" id="searchSuggestions"></div>';
        ?>
        <?php if (empty($contacts)): ?>
            <?php
            if ((int) $total_contacts === 0 && $contact_search === '' && $contact_filter === '') {
                renderEmptyState('fas fa-users', 'No contacts yet', 'Inquiries from the website will show up here.', 'dashboard.php', 'Back to dashboard');
            } else {
                renderEmptyState('fas fa-search', 'No matching contacts', 'Nothing matches the current search or filter.', 'contacts.php', 'Clear filters');
            }
            ?>
        <?php else: ?>
            <form method="post" id="listBulkForm">
                <input type="hidden" name="csrf_token" value="<?php echo ui_h(auth_csrf_token()); ?>">
                <input type="hidden" name="action" id="bulkAction" value="">
                <?php renderBulkBar([
                    'new' => 'New',
                    'contacted' => 'Contacted',
                    'qualified' => 'Qualified',
                    'blacklisted' => 'Blacklisted',
                ], true); ?>
                <div class="list-table-wrap">
                    <table class="list-table">
                        <thead>
                            <tr>
                                <th scope="col"><input type="checkbox" id="listSelectAll" aria-label="Select all rows"></th>
                                <th scope="col">Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Interest</th>
                                <th scope="col">Status</th>
                                <th scope="col">Submitted</th>
                                <th scope="col"><span class="u-clip">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contacts as $contact):
                                $safe_status = json_encode((string) ($contact['status'] ?? 'new'));
                            ?>
                            <tr>
                                <td class="list-select" data-label=""><input type="checkbox" class="list-check" name="ids[]" value="<?php echo (int) $contact['id']; ?>" aria-label="Select <?php echo ui_h($contact['name']); ?>"></td>
                                <td data-label="Name"><?php echo ui_h($contact['name']); ?></td>
                                <td data-label="Email"><a href="mailto:<?php echo ui_h($contact['email']); ?>"><?php echo ui_h($contact['email']); ?></a></td>
                                <td data-label="Interest"><?php echo ui_h($interest_labels[$contact['interest']] ?? $contact['interest']); ?></td>
                                <td data-label="Status"><?php renderStatusBadge($contact['status'] ?? 'new', $status_labels[$contact['status'] ?? 'new'] ?? null); ?></td>
                                <td data-label="Submitted"><?php echo date('M j, Y', strtotime($contact['created_at'])); ?></td>
                                <td data-label="Actions">
                                    <?php renderRowActions([
                                        ['label' => 'View', 'icon' => 'fas fa-eye', 'href' => 'contact-details.php?id=' . (int) $contact['id']],
                                        ['label' => 'Edit', 'icon' => 'fas fa-edit', 'onclick' => 'updateStatus(' . (int) $contact['id'] . ', ' . $safe_status . ')'],
                                        ['label' => 'Delete', 'icon' => 'fas fa-trash', 'danger' => true, 'onclick' => 'removeContact(' . (int) $contact['id'] . ')'],
                                    ]); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            <?php renderPagination($page, $per_page, (int) $total_contacts); ?>
        <?php endif; ?>
    </div>
    <script>
        function removeContact(id) {
            var run = function () {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'contacts.php';
                var token = document.querySelector('#listBulkForm [name="csrf_token"]');
                form.innerHTML = '<input type="hidden" name="csrf_token" value="' + (token ? token.value : '') + '"><input type="hidden" name="action" value="delete"><input type="hidden" name="contact_id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            };
            if (window.xpmConfirm) {
                window.xpmConfirm({ title: 'Remove this contact?', message: 'This inquiry will be deleted.', confirmText: 'Remove', icon: 'fas fa-trash' }).then(function (ok) { if (ok) run(); });
            } else {
                run();
            }
        }
    </script>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal modal-overlay is-top">
        <div class="modal-content modal-dialog">
            <div class="modal-header modal-head">
                <h3 class="modal-title">
                    <i class="fas fa-edit ink" aria-hidden="true"></i>
                    Update Contact Status
                </h3>
                <button class="modal-close" aria-label="Close" onclick="closeModal()">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <form method="POST" class="status-form modal-body">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="contact_id" id="contactId">
                <div class="form-group u-mb-5">
                    <label class="field-label" for="status">Contact Status</label>
                    <select name="status" id="statusSelect" class="form-control" required>
                        <option value="new">New</option>
                        <option value="contacted">Contacted</option>
                        <option value="qualified">Qualified</option>
                        <option value="blacklisted">Blacklisted</option>
                    </select>
                </div>
                <div class="form-actions cluster">
                    <button type="button" class="btn btn-secondary grow" onclick="closeModal()">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-ink grow">
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
                        <i class="fas fa-user suggestion-icon" aria-hidden="true"></i>
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
        
        var exportSelection = [];
        function exportContacts(ids) {
            exportSelection = ids || [];
            showExportModal();
        }
        window.exportApplications = exportContacts;
        
        function showExportModal() {
            // Create modal overlay
            const overlay = document.createElement('div');
            overlay.className = 'modal-overlay is-open';
            
            const modal = document.createElement('div');
            modal.className = 'export-sheet';
            
            modal.innerHTML = `
                <div class="cluster u-mb-5">
                    <i class="fas fa-download icon-lg" aria-hidden="true"></i>
                    <h3 class="modal-title">Export Contacts</h3>
                </div>
                
                <p class="u-mb-5">
                    Choose the format for your contacts export:
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
            const exportBtn = document.querySelector('button[onclick="exportContacts()"]');
            
            // Get current search and filter parameters
            const searchParams = new URLSearchParams(window.location.search);
            const search = searchParams.get('search') || '';
            const filter = searchParams.get('filter') || '';
            
            // Create export URL with format
            const selected = (exportSelection || []).join(',');
            const exportUrl = `export-contacts.php?format=${format}&search=${encodeURIComponent(search)}&filter=${encodeURIComponent(filter)}` + (selected ? `&ids=${encodeURIComponent(selected)}` : '');
            
            var exportName = `contacts.${format}`;
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