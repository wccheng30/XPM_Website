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

$pdo = get_db_connection();
$applications = [];
$blacklist_lookup = [];
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
        try {
            $stmt = $pdo->prepare("UPDATE applications SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $application_id]);
            $success_message = "Application status updated successfully!";
        } catch (PDOException $e) {
            $error_message = "Error updating application status.";
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
            $error_message = "Error blacklisting candidate: " . $e->getMessage();
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
            $error_message = "Error removing from blacklist: " . $e->getMessage();
        }
    }
}

// Handle application deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_application') {
    $application_id = $_POST['application_id'] ?? null;
    
    if ($application_id) {
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
                if (!empty($app_files['resume_path'])) {
                    $resume_full_path = '../' . $app_files['resume_path'];
                    if (file_exists($resume_full_path)) {
                        unlink($resume_full_path);
                    }
                }
                if (!empty($app_files['cover_letter_path'])) {
                    $cover_letter_full_path = '../' . $app_files['cover_letter_path'];
                    if (file_exists($cover_letter_full_path)) {
                        unlink($cover_letter_full_path);
                    }
                }
            }
            
            $success_message = "Application deleted successfully!";
        } catch (PDOException $e) {
            $error_message = "Error deleting application.";
        }
    } else {
        $error_message = "Missing application ID.";
    }
}

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
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $applications = $stmt->fetchAll();
        
        $blacklist_lookup = load_blacklist_lookup($pdo);
        
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
    <link rel="stylesheet" href="admin-styles.css?v=20261006-3">
    <link rel="stylesheet" href="css/applications.css?v=20261006-3">
    <link rel="stylesheet" href="css/application-details.css?v=20261006-3">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Job Applications', 'fas fa-file-alt'); ?>
    
    <div class="admin-container">
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('applications'); ?>
        
        <!-- Page Header -->
        <div class="card mb-3">
            <div class="card-body">
                <h2 style="margin-bottom: 8px; color: #1e293b; font-size: 24px; font-weight: 700;">
                    <i class="fas fa-file-alt" style="color: #111111; margin-right: 12px;"></i>
                    Job Applications
                </h2>
                <p style="color: #64748b; margin: 0; font-size: 16px;">Manage and review job applications from candidates</p>
            </div>
        </div>

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

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><i class="fas fa-file-alt"></i> Total Applications</h3>
                    <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                    <div class="stat-change">
                        <i class="fas fa-chart-line"></i>
                        All time applications
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3><i class="fas fa-clock"></i> Pending Review</h3>
                    <div class="stat-value"><?php echo number_format($stats['pending']); ?></div>
                    <div class="stat-change">
                        <i class="fas fa-exclamation-circle"></i>
                        Awaiting review
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3><i class="fas fa-eye"></i> KIV</h3>
                    <div class="stat-value"><?php echo number_format($stats['reviewed']); ?></div>
                    <div class="stat-change">
                        <i class="fas fa-check-circle"></i>
                        Applications reviewed
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3><i class="fas fa-star"></i> Shortlisted</h3>
                    <div class="stat-value"><?php echo number_format($stats['shortlisted']); ?></div>
                    <div class="stat-change">
                        <i class="fas fa-star"></i>
                        Top candidates
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3><i class="fas fa-times"></i> Rejected</h3>
                    <div class="stat-value"><?php echo number_format($stats['rejected']); ?></div>
                    <div class="stat-change">
                        <i class="fas fa-times-circle"></i>
                        Not selected
                    </div>
                </div>
                                <div class="stat-card">
                    <h3><i class="fas fa-calendar-check"></i> Interview</h3>
                    <div class="stat-value"><?php echo number_format($stats['waiting for interview']); ?></div>
                    <div class="stat-change">
                        <i class="fas fa-exclamation-circle"></i>
                        Scheduled for interview
                    </div>
                </div>
                                <div class="stat-card">
                    <h3><i class="fas fa-times"></i> Hired</h3>
                    <div class="stat-value"><?php echo number_format($stats['hired']); ?></div>
                    <div class="stat-change">
                        <i class="fas fa-check-circle"></i>
                        Hired
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3><i class="fas fa-filter"></i> Search & Filters</h3>
                </div>
                <div class="card-body">
                    <form method="GET" class="search-filter-container d-flex gap-3" style="align-items: end;">
                        <div class="form-group" style="flex: 1;">
                            <label for="search">Search Applications</label>
                            <input type="text" id="search" name="search" class="form-control search-input" 
                                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                                   placeholder="Search by candidate name, email, phone, or job title...">
                        </div>
                        <div class="form-group">
                            <label for="filter">Filter by Status</label>
                            <select id="filter" name="filter" class="form-control filter-select">
                                <option value="">All Applications</option>
                                <option value="pending" <?php echo ($_GET['filter'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="reviewed" <?php echo ($_GET['filter'] ?? '') === 'reviewed' ? 'selected' : ''; ?>>KIV</option>
                                <option value="shortlisted" <?php echo ($_GET['filter'] ?? '') === 'shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                                <option value="rejected" <?php echo ($_GET['filter'] ?? '') === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="waiting for interview" <?php echo ($_GET['filter'] ?? '') === 'waiting for interview' ? 'selected' : ''; ?>>Interview</option>
                                <option value="hired" <?php echo ($_GET['filter'] ?? '') === 'hired' ? 'selected' : ''; ?>>Hired</option>
                                <option value="blacklisted" <?php echo ($_GET['filter'] ?? '') === 'blacklisted' ? 'selected' : ''; ?>>Blacklisted</option>
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
                                <a href="applications.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i>
                                    Clear
                                </a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Applications Table -->
             <div class="card">
                 <div class="card-header">
                     <div style="display: flex; justify-content: space-between; align-items: center;">
                         <div>
                             <h3 style="margin: 0; color: #1f2937; font-size: 20px; font-weight: 600;">
                                 <i class="fas fa-list" style="color: #111111; margin-right: 8px;"></i>
                                 All Applications
                             </h3>
                             <p style="margin: 4px 0 0 0; color: #6b7280; font-size: 14px;">
                                 <?php echo count($applications); ?> application<?php echo count($applications) != 1 ? 's' : ''; ?> found
                             </p>
                         </div>
                                             <div style="display: flex; gap: 12px;">
                        <button class="btn btn-secondary" onclick="exportApplications()" style="background: #6b7280; color: white; border: none; padding: 10px 16px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s;" onmouseover="this.style.backgroundColor='#4b5563'" onmouseout="this.style.backgroundColor='#6b7280'">
                            <i class="fas fa-download" style="margin-right: 6px;"></i>
                            Export
                        </button>
                    </div>
                     </div>
                 </div>
                <div class="card-body">
                                         <?php if (empty($applications)): ?>
                         <div style="text-align: center; padding: 60px 20px; color: #64748b; background: #f8fafc; border-radius: 12px; margin: 20px 0;">
                             <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #111111 0%, #111111 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: white; font-size: 32px;">
                                 <i class="fas fa-file-alt"></i>
                             </div>
                             <h3 style="margin-bottom: 12px; color: #374151; font-size: 24px; font-weight: 600;">No Applications Yet</h3>
                             <p style="color: #6b7280; font-size: 16px; margin-bottom: 24px; max-width: 400px; margin-left: auto; margin-right: auto;">
                                 Job applications will appear here once candidates start applying to your job postings.
                             </p>
                             <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                                 <a href="jobs.php" style="background: #111111; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 500; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#111111'" onmouseout="this.style.backgroundColor='#111111'">
                                     <i class="fas fa-plus" style="margin-right: 8px;"></i>
                                     Add Job Posting
                                 </a>
                                 <a href="dashboard.php" style="background: #f3f4f6; color: #374151; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 500; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#e5e7eb'" onmouseout="this.style.backgroundColor='#f3f4f6'">
                                     <i class="fas fa-chart-bar" style="margin-right: 8px;"></i>
                                     View Dashboard
                                 </a>
                             </div>
                         </div>
                    <?php else: ?>
                        <div class="table-container" style="overflow-x: auto;">
                                                         <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                             <thead>
                                 <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                     <th style="padding: 16px 12px; text-align: left; font-weight: 600; color: #374151; font-size: 14px;">Candidate</th>
                                     <th style="padding: 16px 12px; text-align: left; font-weight: 600; color: #374151; font-size: 14px;">Position</th>
                                     <th style="padding: 16px 12px; text-align: left; font-weight: 600; color: #374151; font-size: 14px;">Contact</th>
                                     <th style="padding: 16px 12px; text-align: left; font-weight: 600; color: #374151; font-size: 14px;">Status</th>
                                     <th style="padding: 16px 12px; text-align: left; font-weight: 600; color: #374151; font-size: 14px;">Applied</th>
                                     <th style="padding: 16px 12px; text-align: center; font-weight: 600; color: #374151; font-size: 14px;">Actions</th>
                                 </tr>
                             </thead>
                                                         <tbody>
                                 <?php foreach ($applications as $application): 
                                     $bl_info = blacklist_match($blacklist_lookup, $application['name'], $application['phone'] ?? '');
                                     $is_blacklisted = ($application['status'] === 'blacklisted') || $bl_info !== null;
                                 ?>
                                     <tr class="<?php echo $is_blacklisted ? 'is-blacklisted' : ''; ?>" style="border-bottom: 1px solid #f1f5f9;" <?php if (!$is_blacklisted): ?>onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='white'"<?php endif; ?>>
                                         <td style="padding: 16px 12px;">
                                             <div style="display: flex; align-items: center; gap: 12px;">
                                                 <div style="width: 40px; height: 40px; background: linear-gradient(135deg, <?php echo $is_blacklisted ? '#ef4444 0%, #b91c1c 100%' : '#111111 0%, #111111 100%'; ?>); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 16px;">
                                                     <i class="fas fa-<?php echo $is_blacklisted ? 'user-slash' : 'user'; ?>"></i>
                                                 </div>
                                                 <div>
                                                     <div style="font-weight: 600; color: #1f2937; font-size: 15px; margin-bottom: 2px; display: flex; align-items: center; gap: 8px;">
                                                         <?php echo htmlspecialchars($application['name']); ?>
                                                         <?php if ($is_blacklisted): ?>
                                                         <span class="blacklist-flag" title="<?php echo htmlspecialchars($bl_info['reason'] ?? 'Marked as blacklisted'); ?>">
                                                             <i class="fas fa-ban"></i> Blacklisted
                                                         </span>
                                                         <?php endif; ?>
                                                     </div>
                                                     <div style="font-size: 13px; color: #6b7280;">
                                                         ID: #<?php echo $application['id']; ?>
                                                     </div>
                                                 </div>
                                             </div>
                                         </td>
                                         <td style="padding: 16px 12px;">
                                             <div style="font-weight: 500; color: #374151; margin-bottom: 4px;">
                                                 <?php echo htmlspecialchars($application['job_title'] ?? 'N/A'); ?>
                                             </div>
                                             <div style="font-size: 13px; color: #6b7280;">
                                                 <i class="fas fa-briefcase" style="margin-right: 4px;"></i>
                                                 Full-time
                                             </div>
                                         </td>
                                         <td style="padding: 16px 12px;">
                                             <div style="margin-bottom: 6px;">
                                                 <a href="mailto:<?php echo htmlspecialchars($application['email']); ?>" style="color: #111111; text-decoration: none; font-size: 14px; display: flex; align-items: center; gap: 4px;">
                                                     <i class="fas fa-envelope" style="font-size: 12px;"></i>
                                                     <?php echo htmlspecialchars($application['email']); ?>
                                                 </a>
                                             </div>
                                            <div style="margin-bottom: 6px; color: #111111; font-size: 14px; display: flex; align-items: center; gap: 4px;">
                                                <i class="fas fa-id-card" style="font-size: 12px;"></i>
                                                <?php echo htmlspecialchars($application['ic_number'] ?? ''); ?>
                                            </div>
                                             <?php if ($application['phone']): ?>
                                                 <a href="tel:<?php echo htmlspecialchars($application['phone']); ?>" style="color: #059669; text-decoration: none; font-size: 14px; display: flex; align-items: center; gap: 4px;">
                                                     <i class="fas fa-phone" style="font-size: 12px;"></i>
                                                     <?php echo htmlspecialchars($application['phone']); ?>
                                                 </a>
                                             <?php else: ?>
                                                 <span style="color: #9ca3af; font-size: 13px; font-style: italic;">
                                                     <i class="fas fa-phone-slash" style="margin-right: 4px;"></i>
                                                     No phone
                                                 </span>
                                             <?php endif; ?>
                                         </td>
                                         <td style="padding: 16px 12px;">
                                             <span class="status-badge status-<?php echo str_replace(' ', '-', strtolower($application['status'])); ?>" style="padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                                 <i class="fas fa-circle" style="font-size: 8px; margin-right: 6px;"></i>
                                                 <?php 
                                                    $display_status = $application['status'];
                                                    $display_status = str_replace('reviewed', 'KIV', $display_status);
                                                    $display_status = str_replace('waiting for interview', 'Interview', $display_status);
                                                    echo ucfirst($display_status); 
                                                 ?>
                                             </span>
                                         </td>
                                         <td style="padding: 16px 12px;">
                                             <div style="font-size: 14px; color: #374151; font-weight: 500;">
                                                 <?php echo date('M j, Y', strtotime($application['created_at'])); ?>
                                             </div>
                                             <div style="font-size: 12px; color: #6b7280;">
                                                 <?php 
                                                 $days_ago = floor((time() - strtotime($application['created_at'])) / (60 * 60 * 24));
                                                 if ($days_ago == 0) {
                                                     echo "Today";
                                                 } elseif ($days_ago == 1) {
                                                     echo "Yesterday";
                                                 } else {
                                                     echo $days_ago . " days ago";
                                                 }
                                                 ?>
                                             </div>
                                         </td>
                                         <td style="padding: 16px 12px; text-align: center;">
                                             <div style="display: flex; gap: 8px; justify-content: center;">
                                                 <button onclick="viewApplication(<?php echo $application['id']; ?>)" style="background: #111111; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#111111'" onmouseout="this.style.backgroundColor='#111111'">
                                                     <i class="fas fa-eye" style="margin-right: 4px;"></i>
                                                     View
                                                 </button>
                                                 <button onclick="updateStatus(<?php echo $application['id']; ?>, '<?php echo $application['status']; ?>')" style="background: #6b7280; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#4b5563'" onmouseout="this.style.backgroundColor='#6b7280'">
                                                     <i class="fas fa-edit" style="margin-right: 4px;"></i>
                                                     Edit
                                                 </button>
                                                 <button onclick="updateComment(<?php echo $application['id']; ?>, '<?php echo $application['interviewer_comment']; ?>')" style="background: #6b7280; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#4b5563'" onmouseout="this.style.backgroundColor='#6b7280'">
                                                     <i class="fas fa-edit" style="margin-right: 4px;"></i>
                                                     Update Comment
                                                 </button>
                                                 <?php if ($is_blacklisted): ?>
                                                 <button onclick="unblacklistCandidate(<?php echo $application['id']; ?>, '<?php echo htmlspecialchars(addslashes($application['name'])); ?>', '<?php echo htmlspecialchars(addslashes($application['phone'] ?? '')); ?>')" style="background: #059669; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; transition: background-color 0.2s; display: flex; align-items: center; gap: 4px;" onmouseover="this.style.backgroundColor='#047857'" onmouseout="this.style.backgroundColor='#059669'">
                                                     <i class="fas fa-user-check" style="font-size: 11px;"></i>
                                                     Unblacklist
                                                 </button>
                                                 <?php else: ?>
                                                 <button onclick="blacklistCandidate(<?php echo $application['id']; ?>, '<?php echo htmlspecialchars(addslashes($application['name'])); ?>', '<?php echo htmlspecialchars(addslashes($application['phone'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($application['job_title'] ?? '')); ?>')" style="background: #1f2937; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; transition: background-color 0.2s; display: flex; align-items: center; gap: 4px;" onmouseover="this.style.backgroundColor='#111827'" onmouseout="this.style.backgroundColor='#1f2937'">
                                                     <i class="fas fa-user-slash" style="font-size: 11px;"></i>
                                                     Blacklist
                                                 </button>
                                                 <?php endif; ?>
                                                 <?php if (($_SESSION['admin_role'] ?? 'admin') !== 'manager'): ?>
                                                 <button onclick="deleteApplication(<?php echo $application['id']; ?>, '<?php echo htmlspecialchars(addslashes($application['name'])); ?>')" style="background: #ef4444; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#dc2626'" onmouseout="this.style.backgroundColor='#ef4444'">
                                                     <i class="fas fa-trash-alt" style="margin-right: 4px;"></i>
                                                     Delete
                                                 </button>
                                                 <?php endif; ?>
                                             </div>
                                         </td>
                                     </tr>
                                 <?php endforeach; ?>
                             </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal" style="display: none; position: fixed; z-index: 1070; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(2px);">
        <div class="modal-content" style="background-color: white; margin: 5% auto; padding: 0; border-radius: 12px; width: 90%; max-width: 450px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
            <div class="modal-header" style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: #1f2937; font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-edit" style="color: #111111;"></i>
                    Update Status
                </h3>
                <button class="modal-close" onclick="closeModal()" style="background: none; border: none; font-size: 18px; color: #6b7280; cursor: pointer; padding: 4px; border-radius: 4px; transition: color 0.2s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#6b7280'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" class="status-form" style="padding: 24px;">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="application_id" id="applicationId">
                <div class="form-group" style="margin-bottom: 24px;">
                    <label for="status" style="display: block; margin-bottom: 8px; color: #374151; font-weight: 500; font-size: 14px;">Application Status</label>
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

    <div id="commentModal" class="modal" style="display: none; position: fixed; z-index: 1070; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(2px);">
        <div class="modal-content" style="background-color: white; margin: 5% auto; padding: 0; border-radius: 12px; width: 90%; max-width: 450px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
            <div class="modal-header" style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: #1f2937; font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-edit" style="color: #111111;"></i>
                    Submit Comment
                </h3>
                <button class="modal-close" onclick="closeModal()" style="background: none; border: none; font-size: 18px; color: #6b7280; cursor: pointer; padding: 4px; border-radius: 4px; transition: color 0.2s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#6b7280'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" class="comment-form" style="padding: 24px;">
                <input type="hidden" name="application_id" id="commentApplicationId">
                <input type="hidden" name="action" value="update_comment">
                    <textarea id="comment" name="comment" class="form-control" rows="4"></textarea>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()" style="flex: 1; background: #f3f4f6; color: #374151; border: none; padding: 12px 16px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#e5e7eb'" onmouseout="this.style.backgroundColor='#f3f4f6'">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" style="flex: 1; background: #111111; color: white; border: none; padding: 12px 16px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#111111'" onmouseout="this.style.backgroundColor='#111111'">
                        Submit
                    </button>             
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal" style="display: none; position: fixed; z-index: 1080; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(2px);">
        <div class="modal-content" style="background-color: white; margin: 10% auto; padding: 0; border-radius: 12px; width: 90%; max-width: 450px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
            <div class="modal-header" style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: #dc2626; font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i>
                    Delete Application
                </h3>
                <button class="modal-close" onclick="closeDeleteModal()" style="background: none; border: none; font-size: 18px; color: #6b7280; cursor: pointer; padding: 4px; border-radius: 4px; transition: color 0.2s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#6b7280'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div style="padding: 24px;">
                <p style="color: #374151; font-size: 15px; margin: 0 0 8px 0;">Are you sure you want to delete the application from:</p>
                <p style="color: #1f2937; font-size: 16px; font-weight: 600; margin: 0 0 16px 0;" id="deleteApplicantName"></p>
                <p style="color: #ef4444; font-size: 13px; margin: 0; display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-info-circle"></i>
                    This action cannot be undone. The application and associated files will be permanently removed.
                </p>
            </div>
            <div style="padding: 16px 24px; border-top: 1px solid #e5e7eb; display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeDeleteModal()" style="background: #f3f4f6; color: #374151; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#e5e7eb'" onmouseout="this.style.backgroundColor='#f3f4f6'">
                    Cancel
                </button>
                <form method="POST" style="margin: 0;" id="deleteForm">
                    <input type="hidden" name="action" value="delete_application">
                    <input type="hidden" name="application_id" id="deleteApplicationId">
                    <button type="submit" style="background: #ef4444; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 6px; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#dc2626'" onmouseout="this.style.backgroundColor='#ef4444'">
                        <i class="fas fa-trash-alt"></i>
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Application Details Modal -->
    <div id="detailsModal" class="modal" style="display: none; position: fixed; z-index: 1060; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(2px);">
        <div class="modal-content" style="background-color: white; margin: 2% auto; padding: 0; border-radius: 12px; width: 90%; max-width: 800px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); position: relative;">
                         <div class="modal-header" style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                 <h3 style="margin: 0; color: #1f2937; font-size: 18px; font-weight: 600;">Application Details</h3>
                 <button class="modal-close" onclick="closeModal()" style="background: none; border: none; font-size: 20px; color: #6b7280; cursor: pointer; padding: 4px; border-radius: 4px; transition: color 0.2s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#6b7280'">
                     <i class="fas fa-times"></i>
                 </button>
             </div>
                         <div id="applicationDetails" style="padding: 20px;" onclick="event.stopPropagation();">
                 <!-- Application details will be loaded here -->
             </div>
        </div>
    </div>

    <!-- Resume Preview Modal -->
    <div id="resumePreviewModal" class="modal" style="display: none; position: fixed; z-index: 1060; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(2px);">
        <div class="modal-content" style="background-color: white; margin: 2% auto; padding: 0; border-radius: 12px; width: 95%; max-width: 900px; height: 90%; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
            <div class="modal-header" style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: #1f2937; font-size: 18px; font-weight: 600;">
                    <i class="fas fa-file-pdf" style="color: #ef4444; margin-right: 8px;"></i>
                    Resume Preview - <span id="resumeCandidateName"></span>
                </h3>
                <button class="modal-close" onclick="closeResumePreview()" style="background: none; border: none; font-size: 20px; color: #6b7280; cursor: pointer; padding: 4px; border-radius: 4px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="resumePreviewContent" style="padding: 20px; height: calc(100% - 80px); overflow-y: auto;">
                <!-- Resume content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Cover Letter Preview Modal -->
    <div id="coverLetterPreviewModal" class="modal" style="display: none; position: fixed; z-index: 1060; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(2px);">
        <div class="modal-content" style="background-color: white; margin: 2% auto; padding: 0; border-radius: 12px; width: 95%; max-width: 900px; height: 90%; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
            <div class="modal-header" style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: #1f2937; font-size: 18px; font-weight: 600;">
                    <i class="fas fa-file-alt" style="color: #111111; margin-right: 8px;"></i>
                    Cover Letter Preview - <span id="coverLetterCandidateName"></span>
                </h3>
                <button class="modal-close" onclick="closeCoverLetterPreview()" style="background: none; border: none; font-size: 20px; color: #6b7280; cursor: pointer; padding: 4px; border-radius: 4px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="coverLetterPreviewContent" style="padding: 20px; height: calc(100% - 80px); overflow-y: auto;">
                <!-- Cover letter content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Blacklist Confirmation Modal -->
    <div id="blacklistModal" class="modal" style="display: none; position: fixed; z-index: 1090; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(2px);">
        <div class="modal-content" style="background-color: white; margin: 8% auto; padding: 0; border-radius: 12px; width: 90%; max-width: 480px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
            <div class="modal-header" style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, #1f2937 0%, #374151 100%); border-radius: 12px 12px 0 0;">
                <h3 style="margin: 0; color: white; font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-user-slash"></i>
                    Blacklist Candidate
                </h3>
                <button class="modal-close" onclick="closeBlacklistModal()" style="background: none; border: none; font-size: 18px; color: rgba(255,255,255,0.7); cursor: pointer; padding: 4px; border-radius: 4px; transition: color 0.2s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.7)'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" id="blacklistForm">
                <input type="hidden" name="action" value="blacklist_candidate">
                <input type="hidden" name="application_id" id="blacklistAppId">
                <input type="hidden" name="candidate_name" id="blacklistName">
                <input type="hidden" name="candidate_phone" id="blacklistPhone">
                <input type="hidden" name="candidate_position" id="blacklistPosition">
                <div style="padding: 24px;">
                    <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px;">
                        <i class="fas fa-exclamation-triangle" style="color: #ef4444; margin-top: 2px;"></i>
                        <div>
                            <p style="margin: 0 0 4px 0; color: #991b1b; font-weight: 600; font-size: 14px;">You are about to blacklist:</p>
                            <p style="margin: 0; color: #1f2937; font-weight: 600; font-size: 16px;" id="blacklistCandidateDisplay"></p>
                        </div>
                    </div>
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; margin-bottom: 8px; color: #374151; font-weight: 500; font-size: 14px;">Reason for Blacklisting</label>
                        <textarea name="blacklist_reason" id="blacklistReason" class="form-control" rows="3" placeholder="Enter the reason for blacklisting this candidate..."></textarea>
                    </div>
                </div>
                <div style="padding: 16px 24px; border-top: 1px solid #e5e7eb; display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" onclick="closeBlacklistModal()" style="background: #f3f4f6; color: #374151; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#e5e7eb'" onmouseout="this.style.backgroundColor='#f3f4f6'">
                        Cancel
                    </button>
                    <button type="submit" style="background: #1f2937; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 6px; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#111827'" onmouseout="this.style.backgroundColor='#1f2937'">
                        <i class="fas fa-user-slash"></i>
                        Blacklist
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Unblacklist Confirmation Modal -->
    <div id="unblacklistModal" class="modal" style="display: none; position: fixed; z-index: 1090; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(2px);">
        <div class="modal-content" style="background-color: white; margin: 10% auto; padding: 0; border-radius: 12px; width: 90%; max-width: 450px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
            <div class="modal-header" style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, #059669 0%, #047857 100%); border-radius: 12px 12px 0 0;">
                <h3 style="margin: 0; color: white; font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-user-check"></i>
                    Remove from Blacklist
                </h3>
                <button class="modal-close" onclick="closeUnblacklistModal()" style="background: none; border: none; font-size: 18px; color: rgba(255,255,255,0.7); cursor: pointer; padding: 4px; border-radius: 4px; transition: color 0.2s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.7)'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" id="unblacklistForm">
                <input type="hidden" name="action" value="unblacklist_candidate">
                <input type="hidden" name="application_id" id="unblacklistAppId">
                <input type="hidden" name="candidate_name" id="unblacklistName">
                <input type="hidden" name="candidate_phone" id="unblacklistPhone">
                <div style="padding: 24px;">
                    <p style="color: #374151; font-size: 15px; margin: 0 0 8px 0;">Are you sure you want to remove the following candidate from the blacklist?</p>
                    <p style="color: #1f2937; font-size: 16px; font-weight: 600; margin: 0 0 16px 0;" id="unblacklistCandidateDisplay"></p>
                    <p style="color: #059669; font-size: 13px; margin: 0; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-info-circle"></i>
                        This will remove them from the blacklist and reset their application status to pending.
                    </p>
                </div>
                <div style="padding: 16px 24px; border-top: 1px solid #e5e7eb; display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" onclick="closeUnblacklistModal()" style="background: #f3f4f6; color: #374151; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500;">
                        Cancel
                    </button>
                    <button type="submit" style="background: #059669; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-user-check"></i>
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
                 <div style="text-align: center; padding: 40px 20px;">
                     <div style="width: 40px; height: 40px; border: 3px solid #e5e7eb; border-top: 3px solid #111111; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 16px;"></div>
                     <p style="color: #6b7280; margin: 0;">Loading application details...</p>
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
                         <div style="text-align: center; padding: 40px 20px; color: #ef4444;">
                             <i class="fas fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 16px;"></i>
                             <h4 style="margin-bottom: 8px; color: #dc2626;">Error Loading Details</h4>
                             <p style="color: #6b7280; margin: 0;">Unable to load application details. Please try again.</p>
                             <p style="color: #6b7280; margin: 8px 0 0 0; font-size: 12px;">Error: ${error.message}</p>
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
             document.getElementById('deleteApplicationId').value = applicationId;
             document.getElementById('deleteApplicantName').textContent = applicantName;
             
             const modal = document.getElementById('deleteModal');
             modal.style.opacity = '0';
             modal.style.display = 'block';
             setTimeout(() => {
                 modal.style.opacity = '1';
             }, 10);
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
                 <div style="text-align: center; padding: 40px 20px;">
                     <div style="width: 40px; height: 40px; border: 3px solid #e5e7eb; border-top: 3px solid #ef4444; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 16px;"></div>
                     <p style="color: #6b7280; margin: 0;">Loading resume content...</p>
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
                         <div style="text-align: center; padding: 40px 20px; color: #ef4444;">
                             <i class="fas fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 16px;"></i>
                             <h4 style="margin-bottom: 8px; color: #dc2626;">Error Loading Resume</h4>
                             <p style="color: #6b7280; margin: 0;">Unable to load resume content. Please try again.</p>
                             <p style="color: #6b7280; margin: 8px 0 0 0; font-size: 12px;">Error: ${error.message}</p>
                             <div style="margin-top: 20px;">
                                 <a href="../${resumePath}" target="_blank" class="btn btn-primary" style="background: #ef4444; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none;">
                                     <i class="fas fa-download" style="margin-right: 6px;"></i>
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
                 <div style="text-align: center; padding: 40px 20px;">
                     <div style="width: 40px; height: 40px; border: 3px solid #e5e7eb; border-top: 3px solid #111111; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 16px;"></div>
                     <p style="color: #6b7280; margin: 0;">Loading cover letter content...</p>
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
                         <div style="text-align: center; padding: 40px 20px; color: #ef4444;">
                             <i class="fas fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 16px;"></i>
                             <h4 style="margin-bottom: 8px; color: #dc2626;">Error Loading Cover Letter</h4>
                             <p style="color: #6b7280; margin: 0;">Unable to load cover letter content. Please try again.</p>
                             <p style="color: #6b7280; margin: 8px 0 0 0; font-size: 12px;">Error: ${error.message}</p>
                             <div style="margin-top: 20px;">
                                 <a href="../${coverLetterPath}" target="_blank" class="btn btn-primary" style="background: #ef4444; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none;">
                                     <i class="fas fa-download" style="margin-right: 6px;"></i>
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
 
        function exportApplications() {
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
                    <h3 style="margin: 0; color: #1e293b;">Export Applications</h3>
                </div>
                
                <p style="color: #64748b; margin-bottom: 20px;">
                    Choose the format for your applications export:
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
            const exportBtn = document.querySelector('button[onclick="exportApplications()"]');
            const originalText = exportBtn.innerHTML;
            
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 4px;"></i>Preparing...';
            exportBtn.disabled = true;
            
            // Get current search and filter parameters
            const searchParams = new URLSearchParams(window.location.search);
            const search = searchParams.get('search') || '';
            const filter = searchParams.get('filter') || '';
            
            // Create export URL with format
            const exportUrl = `export-applications.php?format=${format}&search=${encodeURIComponent(search)}&filter=${encodeURIComponent(filter)}`;
            
            // Create a temporary link and trigger download
            const link = document.createElement('a');
            link.href = exportUrl;
            link.download = `job_applications.${format}`;
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

    <script src="../assets/js/motion.js?v=20261002-10"></script>
</body>
</html>
