<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager']);
csrf_guard();

// Include database connection
require_once '../includes/database.php';
require_once '../includes/blacklist-lookup.php';
require_once __DIR__ . '/includes/ui.php';

$pdo = get_db_connection();
$registrations = [];
$stats = [
    'total' => 0,
    'pending' => 0,
    'confirmed' => 0,
    'waitlisted' => 0,
    'cancelled' => 0
];

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $registration_id = $_POST['registration_id'] ?? null;
    $new_status = $_POST['status'] ?? null;
    
    if ($registration_id && $new_status) {
        if (!in_array((string) $new_status, registration_status_values(), true)) {
            $error_message = "Choose a valid status.";
        } else {
            try {
                $previous = $pdo->prepare("SELECT status FROM programme_registrations WHERE id = ?");
                $previous->execute([$registration_id]);
                $previous_status = (string)$previous->fetchColumn();
                $stmt = $pdo->prepare("UPDATE programme_registrations SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $registration_id]);
                blacklist_sync_status($pdo, 'registration', (int)$registration_id, $previous_status, (string)$new_status);
                $success_message = "Registration status updated successfully!";
            } catch (PDOException $e) {
                $error_message = "Error updating registration status.";
            }
        }
    }
}

$allowed_registration_statuses = registration_status_values();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_update_status') {
    $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
    $new_status = (string) ($_POST['status'] ?? '');
    if (!$ids) {
        $error_message = "Select at least one registration.";
    } elseif (!in_array($new_status, $allowed_registration_statuses, true)) {
        $error_message = "Choose a valid status.";
    } else {
        foreach ($ids as $registration_id) {
            $previous = $pdo->prepare("SELECT status FROM programme_registrations WHERE id = ?");
            $previous->execute([$registration_id]);
            $previous_status = (string) $previous->fetchColumn();
            $stmt = $pdo->prepare("UPDATE programme_registrations SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $registration_id]);
            blacklist_sync_status($pdo, 'registration', (int) $registration_id, $previous_status, $new_status);
        }
        $success_message = count($ids) . " registration status" . (count($ids) === 1 ? '' : 'es') . " updated.";
    }
}

$list_page = max(1, (int) ($_GET['page'] ?? 1));
$list_per_page = ui_page_size(25);
$list_total = 0;

// Get registrations with programme details
if ($pdo) {
    try {
        // Get statistics
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM programme_registrations GROUP BY status");
        $status_counts = $stmt->fetchAll();
        
        foreach ($status_counts as $status) {
            $stats[$status['status']] = $status['count'];
            $stats['total'] += $status['count'];
        }
        
        // Build the query with filters
        $where_conditions = [];
        $params = [];
        
        // Search filter
        if (!empty($_GET['search'])) {
            $search_term = $_GET['search'];
            $where_conditions[] = "(pr.name LIKE ? OR pr.email LIKE ? OR pr.phone LIKE ? OR pr.company LIKE ? OR pr.position LIKE ? OR p.title LIKE ?)";
            $search_param = "%$search_term%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        // Status filter
        if (!empty($_GET['filter'])) {
            $where_conditions[] = "pr.status = ?";
            $params[] = $_GET['filter'];
        }
        
        // Build the final query
        $sql = "SELECT pr.*, p.title as programme_title, p.duration, p.price 
                FROM programme_registrations pr 
                LEFT JOIN programmes p ON pr.programme_id = p.id";
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(" AND ", $where_conditions);
        }
        $sql .= " ORDER BY pr.created_at DESC";

        $count_sql = "SELECT COUNT(*) FROM programme_registrations pr LEFT JOIN programmes p ON pr.programme_id = p.id";
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
        $registrations = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        $error_message = "Error loading registrations.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programme Registrations - Admin Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include_once __DIR__ . '/includes/theme-boot.php'; ?>
    <link rel="stylesheet" href="<?php echo admin_asset('admin.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/registrations.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/detail.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/registration-details.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/list.css'); ?>">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Programme Registrations', 'fas fa-user-graduate'); ?>
    
    <div class="admin-container">
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('registrations'); ?>
        
        <?php
        renderPageHead(
            'Programme Registrations',
            'Review participants and update their status.',
            '<a class="btn btn-primary" href="add-programme.php"><i class="fas fa-plus" aria-hidden="true"></i> New programme</a>'
        );
        ?>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle" aria-hidden="true"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php
        $reg_filter = (string) ($_GET['filter'] ?? '');
        $reg_search = (string) ($_GET['search'] ?? '');
        renderStatusChips([
            ['value' => '', 'label' => 'All', 'count' => $stats['total'] ?? 0],
            ['value' => 'pending', 'label' => 'Pending', 'count' => $stats['pending'] ?? 0],
            ['value' => 'confirmed', 'label' => 'Confirmed', 'count' => $stats['confirmed'] ?? 0],
            ['value' => 'waitlisted', 'label' => 'Waitlisted', 'count' => $stats['waitlisted'] ?? 0],
            ['value' => 'cancelled', 'label' => 'Cancelled', 'count' => $stats['cancelled'] ?? 0],
            ['value' => 'blacklisted', 'label' => 'Blacklisted', 'count' => $stats['blacklisted'] ?? 0],
        ], $reg_filter);
        $reg_active = [];
        if ($reg_search !== '') {
            $reg_active[] = ['label' => 'Search: ' . $reg_search, 'href' => ui_query(['search' => null], ['page'])];
        }
        if ($reg_filter !== '') {
            $reg_active[] = ['label' => 'Status: ' . $reg_filter, 'href' => ui_query(['filter' => null], ['page'])];
        }
        renderListToolbar(
            'search',
            $reg_search,
            'Search name, email, phone, or programme',
            '',
            '<button class="btn btn-secondary" type="button" onclick="exportRegistrations()">Export</button>',
            'registrations.php',
            $reg_active
        );
        ?>

        <?php if (empty($registrations)): ?>
            <?php
            if ($list_total === 0 && $reg_search === '' && $reg_filter === '') {
                renderEmptyState('fas fa-user-graduate', 'No registrations yet', 'Registrations show up here after someone joins a programme.', 'add-programme.php', 'New programme');
            } else {
                renderEmptyState('fas fa-search', 'No matching registrations', 'Nothing matches the current search or status.', 'registrations.php', 'Clear filters');
            }
            ?>
        <?php else: ?>
            <form method="post" id="listBulkForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" id="bulkAction" value="">
                <?php renderBulkBar([
                    'pending' => 'Pending',
                    'confirmed' => 'Confirmed',
                    'waitlisted' => 'Waitlisted',
                    'cancelled' => 'Cancelled',
                ], false); ?>
                <div class="list-table-wrap">
                    <table class="list-table">
                        <thead>
                            <tr>
                                <th scope="col"><input type="checkbox" id="listSelectAll" aria-label="Select all rows"></th>
                                <th scope="col">Participant</th>
                                <th scope="col">Programme</th>
                                <th scope="col">Contact</th>
                                <th scope="col">Status</th>
                                <th scope="col">Registered</th>
                                <th scope="col"><span class="u-clip">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registrations as $registration):
                                $is_blacklisted = ($registration['status'] === 'blacklisted');
                                $safe_status = json_encode((string) $registration['status']);
                            ?>
                            <tr<?php echo $is_blacklisted ? ' class="is-blacklisted"' : ''; ?>>
                                <td class="list-select" data-label="">
                                    <input type="checkbox" class="list-check" name="ids[]" value="<?php echo (int) $registration['id']; ?>" aria-label="Select <?php echo htmlspecialchars($registration['name']); ?>">
                                </td>
                                <td data-label="Participant">
                                    <div class="section-title">
                                        <?php echo htmlspecialchars($registration['name']); ?>
                                    </div>
                                    <div class="meta">ID #<?php echo (int) $registration['id']; ?></div>
                                </td>
                                <td data-label="Programme"><?php echo htmlspecialchars($registration['programme_title'] ?? 'N/A'); ?></td>
                                <td data-label="Contact">
                                    <a href="mailto:<?php echo htmlspecialchars($registration['email']); ?>"><?php echo htmlspecialchars($registration['email']); ?></a>
                                    <?php if (!empty($registration['phone'])): ?>
                                        <div><a href="tel:<?php echo htmlspecialchars($registration['phone']); ?>"><?php echo htmlspecialchars($registration['phone']); ?></a></div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Status"><?php renderStatusBadge($registration['status']); ?></td>
                                <td data-label="Registered"><?php echo date('M j, Y', strtotime($registration['created_at'])); ?></td>
                                <td data-label="Actions">
                                    <?php renderRowActions([
                                        ['label' => 'View', 'icon' => 'fas fa-eye', 'onclick' => 'viewRegistration(' . (int) $registration['id'] . ')'],
                                        ['label' => 'Edit', 'icon' => 'fas fa-edit', 'onclick' => 'updateStatus(' . (int) $registration['id'] . ', ' . $safe_status . ')'],
                                    ]); ?>
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


    <!-- Registration Details Modal -->
    <div id="detailsModal" class="modal modal-overlay is-base is-fade">
        <div class="modal-content modal-dialog modal-wide modal-zoom">
            <div class="modal-header modal-head">
                <h3 class="modal-title">
                    <i class="fas fa-user-graduate ico-muted ink" aria-hidden="true"></i>
                    Registration Details
                </h3>
                <button class="modal-close" aria-label="Close" onclick="closeModal()">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="modal-body modal-body-scroll" id="registrationDetails">
                <!-- Registration details will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal modal-overlay is-top is-fade">
        <div class="modal-content modal-dialog modal-md modal-drop modal-zoom">
            <div class="modal-header modal-head">
                <h3 class="modal-title">
                    <i class="fas fa-edit ico-muted ink" aria-hidden="true"></i>
                    Update Registration Status
                </h3>
                <button class="modal-close" aria-label="Close" onclick="closeModal()">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <form method="POST" class="status-form modal-body">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="registration_id" id="registrationId">
                <div class="form-group u-mb-5">
                    <label class="field-label" for="status">Status</label>
                    <select name="status" id="statusSelect" class="form-control" required>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="waitlisted">Waitlisted</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="blacklisted">Blacklisted</option>
                    </select>
                </div>
                <div class="form-actions toolbar-actions">
                    <button type="button" class="btn btn-ghost" onclick="closeModal()">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-ink">
                        Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function viewRegistration(registrationId) {
            // Show loading state
            const detailsModal = document.getElementById('detailsModal');
            const detailsContent = document.getElementById('registrationDetails');
            
            if (!detailsModal || !detailsContent) {
                console.error('Modal elements not found!');
                xpmAlert('Modal elements were not found. Please refresh the page.');
                return;
            }
            
            detailsContent.innerHTML = `
                <div class="empty-state is-compact">
                    <div class="spinner-ring"></div>
                    <p class="page-sub">Loading registration details...</p>
                </div>
            `;
            
            detailsModal.style.display = 'block';
            detailsModal.style.opacity = '1';
            
            // Prevent body scroll when modal is open
            document.body.style.overflow = 'hidden';
            
            // Load registration details via AJAX
            fetch(`registration-details.php?id=${registrationId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(html => {
                    detailsContent.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading registration details:', error);
                    detailsContent.innerHTML = `
                        <div class="empty-state is-compact is-error">
                            <i class="fas fa-exclamation-triangle empty-icon" aria-hidden="true"></i>
                            <h4 class="mb-2">Error Loading Details</h4>
                            <p class="page-sub">Unable to load registration details. Please try again.</p>
                            <p class="fine-note">Error: ${error.message}</p>
                        </div>
                    `;
                });
        }

        function updateStatus(registrationId, currentStatus) {
            document.getElementById('registrationId').value = registrationId;
            document.getElementById('statusSelect').value = currentStatus;
            
            const modal = document.getElementById('statusModal');
            modal.style.display = 'block';
            modal.style.opacity = '0';
            
            setTimeout(() => {
                modal.style.opacity = '1';
                modal.querySelector('.modal-content').style.transform = 'scale(1)';
            }, 10);
        }

        function closeModal() {
            const detailsModal = document.getElementById('detailsModal');
            const statusModal = document.getElementById('statusModal');
            
            if (detailsModal && detailsModal.style.display === 'block') {
                detailsModal.style.opacity = '0';
                setTimeout(() => {
                    detailsModal.style.display = 'none';
                    // Restore body scroll
                    document.body.style.overflow = 'auto';
                }, 300);
            }
            
            if (statusModal && statusModal.style.display === 'block') {
                statusModal.style.opacity = '0';
                statusModal.querySelector('.modal-content').style.transform = 'scale(0.9)';
                
                setTimeout(() => {
                    statusModal.style.display = 'none';
                }, 300);
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const detailsModal = document.getElementById('detailsModal');
            const statusModal = document.getElementById('statusModal');
            
            if (event.target === detailsModal) {
                closeModal();
            } else if (event.target === statusModal) {
                closeModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        var exportSelection = [];

        function exportRegistrations(ids) {
            exportSelection = ids || [];
            showExportModal();
        }
        window.exportApplications = exportRegistrations;
        
        function showExportModal() {
            // Create modal overlay
            const overlay = document.createElement('div');
            overlay.className = 'modal-overlay is-open';
            
            const modal = document.createElement('div');
            modal.className = 'export-sheet';
            
            modal.innerHTML = `
                <div class="cluster u-mb-5">
                    <i class="fas fa-download icon-lg" aria-hidden="true"></i>
                    <h3 class="modal-title">Export Registrations</h3>
                </div>
                
                <p class="u-mb-5">
                    Choose the format for your registrations export:
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
            const exportBtn = document.querySelector('button[onclick="exportRegistrations()"]');
            
            // Get current search and filter parameters
            const searchParams = new URLSearchParams(window.location.search);
            const search = searchParams.get('search') || '';
            const filter = searchParams.get('filter') || '';
            
            // Create export URL with format
            const liveIds = window.xpmCheckedIds ? window.xpmCheckedIds() : [];
            const selected = (liveIds.length ? liveIds : (exportSelection || [])).join(',');
            const exportUrl = `export-registrations.php?format=${format}&search=${encodeURIComponent(search)}&filter=${encodeURIComponent(filter)}` + (selected ? `&ids=${encodeURIComponent(selected)}` : '');
            
            var exportName = `programme_registrations.${format}`;
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
