<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager']);
csrf_guard();

// Include database connection
require_once '../includes/database.php';
require_once '../includes/blacklist-lookup.php';
require_once __DIR__ . '/includes/ui.php';

$pdo = get_db_connection();
if ($pdo) {
    ensure_application_status_column($pdo);
}
$applications = [];
$stats = [
    'total' => 0,
    'pending' => 0,
    'reviewed' => 0,
    'shortlisted' => 0,
    'rejected' => 0,
    'waiting for interview' => 0,
    'hired' => 0
];

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $application_id = $_POST['application_id'] ?? null;
    $new_status = $_POST['status'] ?? null;
    
    if ($application_id && $new_status) {
        if (!in_array((string) $new_status, application_status_values(), true)) {
            $error_message = "Choose a valid status.";
        } else {
            try {
                $previous = $pdo->prepare("SELECT status FROM applications WHERE id = ?");
                $previous->execute([$application_id]);
                $previous_status = (string)$previous->fetchColumn();
                $stmt = $pdo->prepare("UPDATE applications SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $application_id]);
                blacklist_sync_status($pdo, 'application', (int)$application_id, $previous_status, (string)$new_status);
                $success_message = "Application status updated successfully!";
            } catch (PDOException $e) {
                $error_message = "Error updating application status.";
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_comment') {
    $application_id = $_POST['application_id'] ?? null;
    $comment = $_POST['comment'] ?? "";
if ($application_id) {
    try {
        $stmt = $pdo->prepare("UPDATE applications SET interviewer_comment = ? WHERE id = ?");
        $stmt->execute([$comment, $application_id]);
        $success_message = "Application comment updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating application comment.";
    }
} else {

    $error_message = "Missing application ID or comment.";
}
}

// Handle blacklist candidate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'blacklist_candidate') {
    $application_id = $_POST['application_id'] ?? null;
    $candidate_name = trim($_POST['candidate_name'] ?? '');
    $candidate_phone = trim($_POST['candidate_phone'] ?? '');
    $position = trim($_POST['candidate_position'] ?? '');
    $reason = trim($_POST['blacklist_reason'] ?? '');
    
    if ($application_id && $candidate_name) {
        try {
            // Check if already blacklisted
            $check = $pdo->prepare("SELECT COUNT(*) FROM blacklist WHERE name = ? AND phone_number = ?");
            $check->execute([$candidate_name, $candidate_phone]);
            if ($check->fetchColumn() > 0) {
                $error_message = "This candidate is already on the blacklist.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO blacklist (name, position_applied, phone_number, date, reason) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$candidate_name, $position, $candidate_phone, date('Y-m-d'), $reason]);
                
                // Also update application status to blacklisted
                $stmt2 = $pdo->prepare("UPDATE applications SET status = 'blacklisted' WHERE id = ?");
                $stmt2->execute([$application_id]);
                
                $success_message = htmlspecialchars($candidate_name) . " has been added to the blacklist.";
            }
        } catch (PDOException $e) {
            $error_message = 'The candidate could not be blacklisted.';
        }
    }
}

// Handle unblacklist candidate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unblacklist_candidate') {
    $application_id = $_POST['application_id'] ?? null;
    $candidate_name = trim($_POST['candidate_name'] ?? '');
    $candidate_phone = trim($_POST['candidate_phone'] ?? '');
    
    if ($candidate_name) {
        try {
            $stmt = $pdo->prepare("DELETE FROM blacklist WHERE name = ? AND phone_number = ?");
            $stmt->execute([$candidate_name, $candidate_phone]);
            
            // Reset application status from blacklisted to pending
            if ($application_id) {
                $stmt2 = $pdo->prepare("UPDATE applications SET status = 'pending' WHERE id = ? AND status = 'blacklisted'");
                $stmt2->execute([$application_id]);
            }
            
            $success_message = htmlspecialchars($candidate_name) . " has been removed from the blacklist.";
        } catch (PDOException $e) {
            $error_message = 'The candidate could not be removed from the blacklist.';
        }
    }
}

// Handle application deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_application') {
    $application_id = $_POST['application_id'] ?? null;
    
    if (($_SESSION['admin_role'] ?? 'admin') === 'manager') {
        $error_message = "You cannot delete applications.";
    } elseif ($application_id) {
        try {
            // Get file paths before deleting
            $stmt = $pdo->prepare("SELECT resume_path, cover_letter_path FROM applications WHERE id = ?");
            $stmt->execute([$application_id]);
            $app_files = $stmt->fetch();
            
            // Delete the database record
            $stmt = $pdo->prepare("DELETE FROM applications WHERE id = ?");
            $stmt->execute([$application_id]);
            
            // Delete associated files if they exist
            if ($app_files) {
                auth_remove_stored_file($app_files['resume_path'] ?? '', 'uploads/resumes');
                auth_remove_stored_file($app_files['cover_letter_path'] ?? '', 'uploads/resumes');
            }
            
            $success_message = "Application deleted successfully!";
        } catch (PDOException $e) {
            $error_message = "Error deleting application.";
        }
    } else {
        $error_message = "Missing application ID.";
    }
}

$can_delete_applications = (($_SESSION['admin_role'] ?? 'admin') !== 'manager');
$allowed_application_statuses = application_status_values();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['bulk_delete', 'bulk_update_status'], true)) {
    $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
    if (!$ids) {
        $error_message = "Select at least one application.";
    } elseif ($_POST['action'] === 'bulk_delete') {
        if (!$can_delete_applications) {
            $error_message = "You cannot delete applications.";
        } else {
            $removed = 0;
            foreach ($ids as $application_id) {
                $stmt = $pdo->prepare("SELECT resume_path, cover_letter_path FROM applications WHERE id = ?");
                $stmt->execute([$application_id]);
                $app_files = $stmt->fetch();
                $stmt = $pdo->prepare("DELETE FROM applications WHERE id = ?");
                $stmt->execute([$application_id]);
                if ($app_files) {
                    foreach (['resume_path', 'cover_letter_path'] as $file_key) {
                        auth_remove_stored_file($app_files[$file_key] ?? '', 'uploads/resumes');
                    }
                }
                $removed++;
            }
            $success_message = $removed . " application" . ($removed === 1 ? '' : 's') . " deleted.";
        }
    } else {
        $new_status = (string) ($_POST['status'] ?? '');
        if (!in_array($new_status, $allowed_application_statuses, true)) {
            $error_message = "Choose a valid status.";
        } else {
            foreach ($ids as $application_id) {
                $previous = $pdo->prepare("SELECT status FROM applications WHERE id = ?");
                $previous->execute([$application_id]);
                $previous_status = (string) $previous->fetchColumn();
                $stmt = $pdo->prepare("UPDATE applications SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $application_id]);
                blacklist_sync_status($pdo, 'application', (int) $application_id, $previous_status, $new_status);
            }
            $success_message = count($ids) . " application status" . (count($ids) === 1 ? '' : 'es') . " updated.";
        }
    }
}

$list_page = max(1, (int) ($_GET['page'] ?? 1));
$list_per_page = ui_page_size(25);
$list_total = 0;

// Get applications with job details
if ($pdo) {
    try {
        // Get statistics
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM applications GROUP BY status");
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
            $where_conditions[] = "(a.name LIKE ? OR a.email LIKE ? OR a.phone LIKE ? OR j.title LIKE ?)";
            $search_param = "%$search_term%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        // Status filter
        if (!empty($_GET['filter'])) {
            $where_conditions[] = "a.status = ?";
            $params[] = $_GET['filter'];
        }
        
        // Build the final query
        $sql = "SELECT a.*, j.title as job_title 
                FROM applications a 
                LEFT JOIN jobs j ON a.job_id = j.id";
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(" AND ", $where_conditions);
        }
        $sql .= " ORDER BY a.created_at DESC";

        $count_sql = "SELECT COUNT(*) FROM applications a LEFT JOIN jobs j ON a.job_id = j.id";
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
        $applications = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        $error_message = "Error loading applications.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Applications - Admin Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include_once __DIR__ . '/includes/theme-boot.php'; ?>
    <link rel="stylesheet" href="<?php echo admin_asset('admin.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/applications.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/detail.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/application-details.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/list.css'); ?>">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Job Applications', 'fas fa-file-alt'); ?>
    
    <div class="admin-container">
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('applications'); ?>
        
        <?php
        renderPageHead(
            'Job Applications',
            'Review candidates and update their status.',
            '<a class="btn btn-primary" href="add-job.php"><i class="fas fa-plus" aria-hidden="true"></i> New job</a>'
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
        $app_filter = (string) ($_GET['filter'] ?? '');
        $app_search = (string) ($_GET['search'] ?? '');
        renderStatusChips([
            ['value' => '', 'label' => 'All', 'count' => $stats['total'] ?? 0],
            ['value' => 'pending', 'label' => 'Pending', 'count' => $stats['pending'] ?? 0],
            ['value' => 'reviewed', 'label' => 'Reviewed', 'count' => $stats['reviewed'] ?? 0],
            ['value' => 'shortlisted', 'label' => 'Shortlisted', 'count' => $stats['shortlisted'] ?? 0],
            ['value' => 'waiting for interview', 'label' => 'Waiting for interview', 'count' => $stats['waiting for interview'] ?? 0],
            ['value' => 'rejected', 'label' => 'Rejected', 'count' => $stats['rejected'] ?? 0],
            ['value' => 'hired', 'label' => 'Hired', 'count' => $stats['hired'] ?? 0],
        ], $app_filter);
        $app_active = [];
        if ($app_search !== '') {
            $app_active[] = ['label' => 'Search: ' . $app_search, 'href' => ui_query(['search' => null], ['page'])];
        }
        if ($app_filter !== '') {
            $app_active[] = ['label' => 'Status: ' . $app_filter, 'href' => ui_query(['filter' => null], ['page'])];
        }
        renderListToolbar(
            'search',
            $app_search,
            'Search name, email, phone, or job',
            '',
            '<button class="btn btn-secondary" type="button" onclick="exportApplications()">Export</button>',
            'applications.php',
            $app_active
        );
        ?>

        <?php if (empty($applications)): ?>
            <?php
            if ($list_total === 0 && $app_search === '' && $app_filter === '') {
                renderEmptyState('fas fa-file-alt', 'No applications yet', 'Applications show up here after someone applies to a job.', 'add-job.php', 'New job');
            } else {
                renderEmptyState('fas fa-search', 'No matching applications', 'Nothing matches the current search or status.', 'applications.php', 'Clear filters');
            }
            ?>
        <?php else: ?>
            <form method="post" id="listBulkForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" id="bulkAction" value="">
                <?php renderBulkBar([
                    'pending' => 'Pending',
                    'reviewed' => 'Reviewed',
                    'shortlisted' => 'Shortlisted',
                    'waiting for interview' => 'Waiting for interview',
                    'rejected' => 'Rejected',
                    'hired' => 'Hired',
                ], $can_delete_applications); ?>
                <div class="list-table-wrap">
                    <table class="list-table">
                        <thead>
                            <tr>
                                <th scope="col"><input type="checkbox" id="listSelectAll" aria-label="Select all rows"></th>
                                <th scope="col">Candidate</th>
                                <th scope="col">Position</th>
                                <th scope="col">Contact</th>
                                <th scope="col">Status</th>
                                <th scope="col">Applied</th>
                                <th scope="col"><span class="u-clip">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $application):
                                $is_blacklisted = ($application['status'] === 'blacklisted');
                                $safe_name = json_encode((string) $application['name']);
                                $safe_phone = json_encode((string) ($application['phone'] ?? ''));
                                $safe_job = json_encode((string) ($application['job_title'] ?? ''));
                                $safe_comment = json_encode((string) ($application['interviewer_comment'] ?? ''));
                                $safe_status = json_encode((string) $application['status']);
                            ?>
                            <tr<?php echo $is_blacklisted ? ' class="is-blacklisted"' : ''; ?>>
                                <td class="list-select" data-label="">
                                    <input type="checkbox" class="list-check" name="ids[]" value="<?php echo (int) $application['id']; ?>" aria-label="Select <?php echo htmlspecialchars($application['name']); ?>">
                                </td>
                                <td data-label="Candidate">
                                    <div class="section-title">
                                        <?php echo htmlspecialchars($application['name']); ?>
                                    </div>
                                    <div class="meta">ID #<?php echo (int) $application['id']; ?></div>
                                </td>
                                <td data-label="Position"><?php echo htmlspecialchars($application['job_title'] ?? 'N/A'); ?></td>
                                <td data-label="Contact">
                                    <a href="mailto:<?php echo htmlspecialchars($application['email']); ?>"><?php echo htmlspecialchars($application['email']); ?></a>
                                    <?php if (!empty($application['phone'])): ?>
                                        <div><a href="tel:<?php echo htmlspecialchars($application['phone']); ?>"><?php echo htmlspecialchars($application['phone']); ?></a></div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Status"><?php renderStatusBadge($application['status']); ?></td>
                                <td data-label="Applied"><?php echo date('M j, Y', strtotime($application['created_at'])); ?></td>
                                <td data-label="Actions">
                                    <?php
                                    $row_actions = [
                                        ['label' => 'View', 'icon' => 'fas fa-eye', 'onclick' => 'viewApplication(' . (int) $application['id'] . ')'],
                                        ['label' => 'Edit', 'icon' => 'fas fa-edit', 'onclick' => 'updateStatus(' . (int) $application['id'] . ', ' . $safe_status . ')'],
                                        ['label' => 'Update comment', 'icon' => 'fas fa-comment', 'onclick' => 'updateComment(' . (int) $application['id'] . ', ' . $safe_comment . ')'],
                                    ];
                                    if ($is_blacklisted) {
                                        $row_actions[] = ['label' => 'Unblacklist', 'icon' => 'fas fa-user-check', 'onclick' => 'unblacklistCandidate(' . (int) $application['id'] . ', ' . $safe_name . ', ' . $safe_phone . ')'];
                                    } else {
                                        $row_actions[] = ['label' => 'Blacklist', 'icon' => 'fas fa-user-slash', 'onclick' => 'blacklistCandidate(' . (int) $application['id'] . ', ' . $safe_name . ', ' . $safe_phone . ', ' . $safe_job . ')'];
                                    }
                                    if ($can_delete_applications) {
                                        $row_actions[] = ['label' => 'Delete', 'icon' => 'fas fa-trash', 'danger' => true, 'onclick' => 'deleteApplication(' . (int) $application['id'] . ', ' . $safe_name . ')'];
                                    }
                                    renderRowActions($row_actions);
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

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal modal-overlay is-top">
        <div class="modal-content modal-dialog">
            <div class="modal-header modal-head">
                <h3 class="modal-title">
                    <i class="fas fa-edit ink" aria-hidden="true"></i>
                    Update Status
                </h3>
                <button class="modal-close" aria-label="Close" onclick="closeModal()">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <form method="POST" class="status-form modal-body">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="application_id" id="applicationId">
                <div class="form-group u-mb-5">
                    <label class="field-label" for="status">Application Status</label>
                    <select name="status" id="statusSelect" class="form-control" required>
                        <option value="pending">Pending</option>
                        <option value="reviewed">KIV</option>
                        <option value="shortlisted">Shortlisted</option>
                        <option value="rejected">Rejected</option>
                        <option value="blacklisted">Blacklisted</option>
                        <option value="waiting for interview"> Interview </option>
                        <option value="hired"> Hired </option>  
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

    <div id="commentModal" class="modal modal-overlay is-top">
        <div class="modal-content modal-dialog">
            <div class="modal-header modal-head">
                <h3 class="modal-title">
                    <i class="fas fa-edit ink" aria-hidden="true"></i>
                    Submit Comment
                </h3>
                <button class="modal-close" aria-label="Close" onclick="closeModal()">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <form method="POST" class="comment-form modal-body">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="application_id" id="commentApplicationId">
                <input type="hidden" name="action" value="update_comment">
                    <textarea id="comment" name="comment" class="form-control" rows="4"></textarea>
                    <button type="button" class="btn btn-secondary grow" onclick="closeModal()">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-ink grow">
                        Submit
                    </button>             
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal modal-overlay is-higher">
        <div class="modal-content modal-dialog modal-drop">
            <div class="modal-header modal-head">
                <h3 class="modal-title">
                    <i class="fas fa-exclamation-triangle is-danger" aria-hidden="true"></i>
                    Delete Application
                </h3>
                <button class="modal-close" aria-label="Close" onclick="closeDeleteModal()">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="modal-body">
                <p class="name-line mb-2">Are you sure you want to delete the application from:</p>
                <p class="section-title" id="deleteApplicantName"></p>
                <p class="cluster cluster-tight meta is-danger u-reset">
                    <i class="fas fa-info-circle" aria-hidden="true"></i>
                    This action cannot be undone. The application and associated files will be permanently removed.
                </p>
            </div>
            <div class="modal-foot">
                <button class="btn btn-secondary" type="button" onclick="closeDeleteModal()">
                    Cancel
                </button>
                <form class="u-reset" method="POST" id="deleteForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete_application">
                    <input type="hidden" name="application_id" id="deleteApplicationId">
                    <button class="btn btn-danger" type="submit">
                        <i class="fas fa-trash-alt" aria-hidden="true"></i>
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Application Details Modal -->
    <div id="detailsModal" class="modal modal-overlay">
        <div class="modal-content modal-dialog modal-wide modal-scroll">
                         <div class="modal-header modal-head">
                 <h3 class="modal-title">Application Details</h3>
                 <button class="modal-close" aria-label="Close" onclick="closeModal()">
                     <i class="fas fa-times" aria-hidden="true"></i>
                 </button>
             </div>
                         <div class="modal-body-loose" id="applicationDetails" onclick="event.stopPropagation();">
                 <!-- Application details will be loaded here -->
             </div>
        </div>
    </div>

    <!-- Resume Preview Modal -->
    <div id="resumePreviewModal" class="modal modal-overlay">
        <div class="modal-content modal-dialog modal-lg">
            <div class="modal-header modal-head">
                <h3 class="modal-title">
                    <i class="fas fa-file-pdf ico-muted is-danger" aria-hidden="true"></i>
                    Resume Preview - <span id="resumeCandidateName"></span>
                </h3>
                <button class="modal-close" aria-label="Close" onclick="closeResumePreview()">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="modal-body-scroll" id="resumePreviewContent">
                <!-- Resume content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Cover Letter Preview Modal -->
    <div id="coverLetterPreviewModal" class="modal modal-overlay">
        <div class="modal-content modal-dialog modal-lg">
            <div class="modal-header modal-head">
                <h3 class="modal-title">
                    <i class="fas fa-file-alt ico-muted ink" aria-hidden="true"></i>
                    Cover Letter Preview - <span id="coverLetterCandidateName"></span>
                </h3>
                <button class="modal-close" aria-label="Close" onclick="closeCoverLetterPreview()">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="modal-body-scroll" id="coverLetterPreviewContent">
                <!-- Cover letter content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Blacklist Confirmation Modal -->
    <div id="blacklistModal" class="modal modal-overlay is-highest">
        <div class="modal-content modal-dialog modal-drop">
            <div class="modal-header modal-head modal-head-ink">
                <h3 class="modal-title">
                    <i class="fas fa-user-slash" aria-hidden="true"></i>
                    Blacklist Candidate
                </h3>
                <button class="modal-close" aria-label="Close" onclick="closeBlacklistModal()">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <form method="POST" id="blacklistForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="blacklist_candidate">
                <input type="hidden" name="application_id" id="blacklistAppId">
                <input type="hidden" name="candidate_name" id="blacklistName">
                <input type="hidden" name="candidate_phone" id="blacklistPhone">
                <input type="hidden" name="candidate_position" id="blacklistPosition">
                <div class="modal-body">
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle is-danger u-mt-1" aria-hidden="true"></i>
                        <div>
                            <p class="subhead is-danger">Add this candidate to the blacklist?</p>
                            <p class="name-line" id="blacklistCandidateDisplay"></p>
                        </div>
                    </div>
                    <div class="u-mb-4">
                        <label class="field-label">Reason for Blacklisting</label>
                        <textarea name="blacklist_reason" id="blacklistReason" class="form-control" rows="3" placeholder="Enter the reason for blacklisting this candidate..."></textarea>
                    </div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-secondary" type="button" onclick="closeBlacklistModal()">
                        Cancel
                    </button>
                    <button class="btn btn-ink" type="submit">
                        <i class="fas fa-user-slash" aria-hidden="true"></i>
                        Blacklist
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Unblacklist Confirmation Modal -->
    <div id="unblacklistModal" class="modal modal-overlay is-highest">
        <div class="modal-content modal-dialog modal-drop">
            <div class="modal-header modal-head modal-head-ok">
                <h3 class="modal-title">
                    <i class="fas fa-user-check" aria-hidden="true"></i>
                    Remove from Blacklist
                </h3>
                <button class="modal-close" aria-label="Close" onclick="closeUnblacklistModal()">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <form method="POST" id="unblacklistForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="unblacklist_candidate">
                <input type="hidden" name="application_id" id="unblacklistAppId">
                <input type="hidden" name="candidate_name" id="unblacklistName">
                <input type="hidden" name="candidate_phone" id="unblacklistPhone">
                <div class="modal-body">
                    <p class="name-line mb-2">Are you sure you want to remove the following candidate from the blacklist?</p>
                    <p class="section-title" id="unblacklistCandidateDisplay"></p>
                    <p class="cluster cluster-tight meta is-success u-reset">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        This will remove them from the blacklist and reset their application status to pending.
                    </p>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-secondary" type="button" onclick="closeUnblacklistModal()">
                        Cancel
                    </button>
                    <button class="btn btn-success" type="submit">
                        <i class="fas fa-user-check" aria-hidden="true"></i>
                        Remove from Blacklist
                    </button>
                </div>
            </form>
        </div>
    </div>

         <script>
         function updateStatus(applicationId, currentStatus) {
             document.getElementById('applicationId').value = applicationId;
             document.getElementById('statusSelect').value = currentStatus;
             document.getElementById('statusModal').style.display = 'block';
             
             // Add smooth animation
             const modal = document.getElementById('statusModal');
             modal.style.opacity = '0';
             modal.style.display = 'block';
             setTimeout(() => {
                 modal.style.opacity = '1';
             }, 10);
         }

         function updateComment(applicationId, comment) {
            document.getElementById('commentApplicationId').value = applicationId;
            document.getElementById('comment').value = comment;
            document.getElementById('commentModal').style.display = 'block';

            // Add smooth animation
            const modal = document.getElementById('commentModal');
            modal.style.opacity = '0';
            modal.style.display = 'block';
            setTimeout(() => {
                modal.style.opacity = '1';
            }, 10);
         }
 
         function viewApplication(applicationId) {
             // View application clicked for ID: ' + applicationId
             
             // Show loading state
             const detailsModal = document.getElementById('detailsModal');
             const detailsContent = document.getElementById('applicationDetails');
             
             if (!detailsModal || !detailsContent) {
                 console.error('Modal elements not found!');
                 xpmAlert('Modal elements were not found. Please refresh the page.');
                 return;
             }
             
             detailsContent.innerHTML = `
                 <div class="empty-state is-compact">
                     <div class="spinner-ring"></div>
                     <p class="page-sub">Loading application details...</p>
                 </div>
             `;
             
             detailsModal.style.display = 'block';
             detailsModal.style.opacity = '1';
             
             // Prevent body scroll when modal is open
             document.body.style.overflow = 'hidden';
             
             // Load application details via AJAX
             fetch(`application-details.php?id=${applicationId}`)
                 .then(response => {
                     // Response status: ' + response.status
                     if (!response.ok) {
                         throw new Error('Network response was not ok');
                     }
                     return response.text();
                 })
                 .then(html => {
                     // Received HTML length: ' + html.length
                     detailsContent.innerHTML = html;
                 })
                 .catch(error => {
                     console.error('Error loading application details:', error);
                     detailsContent.innerHTML = `
                         <div class="empty-state is-compact is-error">
                             <i class="fas fa-exclamation-triangle empty-icon" aria-hidden="true"></i>
                             <h4 class="mb-2">Error Loading Details</h4>
                             <p class="page-sub">Unable to load application details. Please try again.</p>
                             <p class="fine-note">Error: ${error.message}</p>
                         </div>
                     `;
                 });
         }
 
         function closeModal() {
             const detailsModal = document.getElementById('detailsModal');
             const statusModal = document.getElementById('statusModal');
             const commentModal = document.getElementById('commentModal');
             
             if (detailsModal) {
                 detailsModal.style.opacity = '0';
                 setTimeout(() => {
                     detailsModal.style.display = 'none';
                     // Restore body scroll
                     document.body.style.overflow = 'auto';
                 }, 200);
             }
             
             if (statusModal) {
                 statusModal.style.opacity = '0';
                 setTimeout(() => {
                     statusModal.style.display = 'none';
                 }, 200);
             }

             if (commentModal) {
                commentModal.style.opacity = '0';
                setTimeout(() => {
                    commentModal.style.display = 'none';
                }, 200);
             }
         }

         function deleteApplication(applicationId, applicantName) {
             var submitDelete = function () {
                 document.getElementById('deleteApplicationId').value = applicationId;
                 document.getElementById('deleteForm').submit();
             };
             if (window.xpmConfirm) {
                 window.xpmConfirm({
                     title: 'Delete this application?',
                     message: 'Delete the application from ' + applicantName + '? This cannot be undone.',
                     confirmText: 'Delete',
                     icon: 'fas fa-trash'
                 }).then(function (ok) { if (ok) submitDelete(); });
             } else {
                 submitDelete();
             }
         }

         function closeDeleteModal() {
             const deleteModal = document.getElementById('deleteModal');
             if (deleteModal) {
                 deleteModal.style.opacity = '0';
                 setTimeout(() => {
                     deleteModal.style.display = 'none';
                 }, 200);
             }
         }

         function blacklistCandidate(applicationId, candidateName, candidatePhone, position) {
             document.getElementById('blacklistAppId').value = applicationId;
             document.getElementById('blacklistName').value = candidateName;
             document.getElementById('blacklistPhone').value = candidatePhone;
             document.getElementById('blacklistPosition').value = position;
             document.getElementById('blacklistCandidateDisplay').textContent = candidateName;
             document.getElementById('blacklistReason').value = '';
             
             const modal = document.getElementById('blacklistModal');
             modal.style.opacity = '0';
             modal.style.display = 'block';
             setTimeout(() => {
                 modal.style.opacity = '1';
             }, 10);
         }

         function closeBlacklistModal() {
             const modal = document.getElementById('blacklistModal');
             if (modal) {
                 modal.style.opacity = '0';
                 setTimeout(() => {
                     modal.style.display = 'none';
                 }, 200);
             }
         }

         function unblacklistCandidate(applicationId, candidateName, candidatePhone) {
             document.getElementById('unblacklistAppId').value = applicationId;
             document.getElementById('unblacklistName').value = candidateName;
             document.getElementById('unblacklistPhone').value = candidatePhone;
             document.getElementById('unblacklistCandidateDisplay').textContent = candidateName;
             
             const modal = document.getElementById('unblacklistModal');
             modal.style.opacity = '0';
             modal.style.display = 'block';
             setTimeout(() => {
                 modal.style.opacity = '1';
             }, 10);
         }

         function closeUnblacklistModal() {
             const modal = document.getElementById('unblacklistModal');
             if (modal) {
                 modal.style.opacity = '0';
                 setTimeout(() => {
                     modal.style.display = 'none';
                 }, 200);
             }
         }

         function previewResume(resumePath, candidateName) {
             // Previewing resume for: ' + candidateName
             
             const resumeModal = document.getElementById('resumePreviewModal');
             const resumeContent = document.getElementById('resumePreviewContent');
             const candidateNameSpan = document.getElementById('resumeCandidateName');
             
             if (!resumeModal || !resumeContent) {
                 console.error('Resume modal elements not found!');
                 xpmAlert('Resume preview could not be opened. Please refresh the page.');
                 return;
             }
             
             // Set candidate name
             candidateNameSpan.textContent = candidateName;
             
             // Show loading state
             resumeContent.innerHTML = `
                 <div class="empty-state is-compact">
                     <div class="spinner-ring"></div>
                     <p class="page-sub">Loading resume content...</p>
                 </div>
             `;
             
             resumeModal.style.display = 'block';
             
             // Load resume content via AJAX
             fetch(`resume-preview.php?path=${encodeURIComponent(resumePath)}`)
                 .then(response => {
                     // Resume response status: ' + response.status
                     if (!response.ok) {
                         throw new Error('Network response was not ok');
                     }
                     return response.text();
                 })
                 .then(html => {
                     // Received resume HTML length: ' + html.length
                     resumeContent.innerHTML = html;
                 })
                 .catch(error => {
                     console.error('Error loading resume content:', error);
                     resumeContent.innerHTML = `
                         <div class="empty-state is-compact is-error">
                             <i class="fas fa-exclamation-triangle empty-icon" aria-hidden="true"></i>
                             <h4 class="mb-2">Error Loading Resume</h4>
                             <p class="page-sub">Unable to load resume content. Please try again.</p>
                             <p class="fine-note">Error: ${error.message}</p>
                             <div class="u-mt-5">
                                 <a href="../${resumePath}" target="_blank" class="btn btn-danger">
                                     <i class="fas fa-download ico-md" aria-hidden="true"></i>
                                     Download Resume Instead
                                 </a>
                             </div>
                         </div>
                     `;
                 });
         }

         function closeResumePreview() {
             const resumeModal = document.getElementById('resumePreviewModal');
             resumeModal.style.opacity = '0';
             setTimeout(() => {
                 resumeModal.style.display = 'none';
                 // Restore body scroll
                 document.body.style.overflow = 'auto';
             }, 200);
         }

         function previewCoverLetter(coverLetterPath, candidateName) {
             // Previewing cover letter for: ' + candidateName
             
             const coverLetterModal = document.getElementById('coverLetterPreviewModal');
             const coverLetterContent = document.getElementById('coverLetterPreviewContent');
             const candidateNameSpan = document.getElementById('coverLetterCandidateName');
             
             if (!coverLetterModal || !coverLetterContent) {
                 console.error('Cover letter modal elements not found!');
                 xpmAlert('Cover letter preview could not be opened. Please refresh the page.');
                 return;
             }
             
             // Set candidate name
             candidateNameSpan.textContent = candidateName;
             
             // Show loading state
             coverLetterContent.innerHTML = `
                 <div class="empty-state is-compact">
                     <div class="spinner-ring"></div>
                     <p class="page-sub">Loading cover letter content...</p>
                 </div>
             `;
             
             coverLetterModal.style.display = 'block';
             
             // Load cover letter content via AJAX
             fetch(`cover-letter-preview.php?path=${encodeURIComponent(coverLetterPath)}`)
                 .then(response => {
                     // Cover letter response status: ' + response.status
                     if (!response.ok) {
                         throw new Error('Network response was not ok');
                     }
                     return response.text();
                 })
                 .then(html => {
                     // Received cover letter HTML length: ' + html.length
                     coverLetterContent.innerHTML = html;
                 })
                 .catch(error => {
                     console.error('Error loading cover letter content:', error);
                     coverLetterContent.innerHTML = `
                         <div class="empty-state is-compact is-error">
                             <i class="fas fa-exclamation-triangle empty-icon" aria-hidden="true"></i>
                             <h4 class="mb-2">Error Loading Cover Letter</h4>
                             <p class="page-sub">Unable to load cover letter content. Please try again.</p>
                             <p class="fine-note">Error: ${error.message}</p>
                             <div class="u-mt-5">
                                 <a href="../${coverLetterPath}" target="_blank" class="btn btn-danger">
                                     <i class="fas fa-download ico-md" aria-hidden="true"></i>
                                     Download Cover Letter Instead
                                 </a>
                             </div>
                         </div>
                     `;
                 });
         }

         function closeCoverLetterPreview() {
             const coverLetterModal = document.getElementById('coverLetterPreviewModal');
             coverLetterModal.style.opacity = '0';
             setTimeout(() => {
                 coverLetterModal.style.display = 'none';
                 // Restore body scroll
                 document.body.style.overflow = 'auto';
             }, 200);
         }
 
        var exportSelection = [];

        function exportApplications(ids) {
            exportSelection = ids || [];
            showExportModal();
        }
        window.exportApplications = exportApplications;
        
        function showExportModal() {
            // Create modal overlay
            const overlay = document.createElement('div');
            overlay.className = 'modal-overlay is-open';
            
            const modal = document.createElement('div');
            modal.className = 'export-sheet';
            
            modal.innerHTML = `
                <div class="cluster u-mb-5">
                    <i class="fas fa-download icon-lg" aria-hidden="true"></i>
                    <h3 class="modal-title">Export Applications</h3>
                </div>
                
                <p class="u-mb-5">
                    Choose the format for your applications export:
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
            const exportBtn = document.querySelector('button[onclick="exportApplications()"]');
            
            // Get current search and filter parameters
            const searchParams = new URLSearchParams(window.location.search);
            const search = searchParams.get('search') || '';
            const filter = searchParams.get('filter') || '';
            const selected = (exportSelection || []).join(',');
            
            // Create export URL with format
            const exportUrl = `export-applications.php?format=${format}&search=${encodeURIComponent(search)}&filter=${encodeURIComponent(filter)}` + (selected ? `&ids=${encodeURIComponent(selected)}` : '');
            
            var exportName = `job_applications.${format}`;
            if (window.xpmExportDownload) {
                window.xpmExportDownload(exportUrl, exportName, exportBtn);
            } else {
                window.location.href = exportUrl;
            }
            closeExportModal();
        }
 
         // Close modal when clicking outside
         window.onclick = function(event) {
             const detailsModal = document.getElementById('detailsModal');
             const statusModal = document.getElementById('statusModal');
             const resumeModal = document.getElementById('resumePreviewModal');
             const coverLetterModal = document.getElementById('coverLetterPreviewModal');
             const deleteModal = document.getElementById('deleteModal');
             const blacklistModal = document.getElementById('blacklistModal');
             const unblacklistModal = document.getElementById('unblacklistModal');
             
             if (event.target === detailsModal) {
                 closeModal();
             } else if (event.target === statusModal) {
                 closeModal();
             } else if (event.target === resumeModal) {
                 closeResumePreview();
             } else if (event.target === coverLetterModal) {
                 closeCoverLetterPreview();
             } else if (event.target === deleteModal) {
                 closeDeleteModal();
             } else if (event.target === blacklistModal) {
                 closeBlacklistModal();
             } else if (event.target === unblacklistModal) {
                 closeUnblacklistModal();
             }
         }
         
         // Add keyboard support for modals
         document.addEventListener('keydown', function(event) {
             if (event.key === 'Escape') {
                 closeModal();
                 closeBlacklistModal();
                 closeUnblacklistModal();
             }
         });
         
         // Add CSS animation for spinner
         const style = document.createElement('style');
         style.textContent = `
             @keyframes spin {
                 0% { transform: rotate(0deg); }
                 100% { transform: rotate(360deg); }
             }
         `;
         document.head.appendChild(style);
     </script>

    <script defer src="<?php echo admin_asset('js/list.js'); ?>"></script>
</body>
</html>
