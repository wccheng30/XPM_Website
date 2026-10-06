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
        try {
            $stmt = $pdo->prepare("UPDATE programme_registrations SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $registration_id]);
            $success_message = "Registration status updated successfully!";
        } catch (PDOException $e) {
            $error_message = "Error updating registration status.";
        }
    }
}

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
    <link rel="stylesheet" href="admin-styles.css?v=20261005-5">
    <link rel="stylesheet" href="css/registrations.css?v=20260930-4">
    <link rel="stylesheet" href="css/registration-details.css?v=20260930-4">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Programme Registrations', 'fas fa-user-graduate'); ?>
    
    <div class="admin-container">
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('registrations'); ?>
        
        <!-- Page Header -->
        <div class="card mb-3">
            <div class="card-body">
                <h2 style="margin-bottom: 8px; color: #1e293b; font-size: 24px; font-weight: 700;">
                    <i class="fas fa-graduation-cap" style="color: #111111; margin-right: 12px;"></i>
                    Programme Registrations
                </h2>
                <p style="color: #64748b; margin: 0; font-size: 16px;">Manage and review programme registrations from participants</p>
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
                <h3><i class="fas fa-graduation-cap"></i> Total Registrations</h3>
                <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-change">
                    <i class="fas fa-chart-line"></i>
                    All time registrations
                </div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-clock"></i> Pending Review</h3>
                <div class="stat-value"><?php echo number_format($stats['pending']); ?></div>
                <div class="stat-change">
                    <i class="fas fa-exclamation-circle"></i>
                    Awaiting confirmation
                </div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-check-circle"></i> Confirmed</h3>
                <div class="stat-value"><?php echo number_format($stats['confirmed']); ?></div>
                <div class="stat-change">
                    <i class="fas fa-check-circle"></i>
                    Registrations confirmed
                </div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-hourglass-half"></i> Waitlisted</h3>
                <div class="stat-value"><?php echo number_format($stats['waitlisted']); ?></div>
                <div class="stat-change">
                    <i class="fas fa-clock"></i>
                    On waiting list
                </div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-times"></i> Cancelled</h3>
                <div class="stat-value"><?php echo number_format($stats['cancelled']); ?></div>
                <div class="stat-change">
                    <i class="fas fa-times-circle"></i>
                    Cancelled registrations
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="card mb-3">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Search & Filters</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="d-flex gap-3" style="align-items: end;">
                    <div class="form-group" style="flex: 1;">
                        <label for="search">Search Registrations</label>
                        <input type="text" id="search" name="search" class="form-control" 
                               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                               placeholder="Search by participant name, email, phone, company, position, or programme title...">
                    </div>
                    <div class="form-group">
                        <label for="filter">Filter by Status</label>
                        <select id="filter" name="filter" class="form-control">
                            <option value="">All Registrations</option>
                            <option value="pending" <?php echo ($_GET['filter'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo ($_GET['filter'] ?? '') === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="waitlisted" <?php echo ($_GET['filter'] ?? '') === 'waitlisted' ? 'selected' : ''; ?>>Waitlisted</option>
                            <option value="cancelled" <?php echo ($_GET['filter'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
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
                            <a href="registrations.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                Clear
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Registrations Table -->
        <div class="card">
            <div class="card-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="margin: 0; color: #1f2937; font-size: 20px; font-weight: 600;">
                            <i class="fas fa-list" style="color: #111111; margin-right: 8px;"></i>
                            All Registrations
                        </h3>
                        <p style="margin: 4px 0 0 0; color: #6b7280; font-size: 14px;">
                            <?php echo count($registrations); ?> registration<?php echo count($registrations) != 1 ? 's' : ''; ?> found
                        </p>
                    </div>
                    <div style="display: flex; gap: 12px;">
                        <button onclick="exportRegistrations()" class="btn btn-secondary" style="background: #6b7280; color: white; border: none; padding: 10px 16px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s;" onmouseover="this.style.backgroundColor='#4b5563'" onmouseout="this.style.backgroundColor='#6b7280'">
                            <i class="fas fa-download" style="margin-right: 6px;"></i>
                            Export
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($registrations)): ?>
                    <div style="text-align: center; padding: 60px 20px; color: #64748b; background: #f8fafc; border-radius: 12px; margin: 20px 0;">
                        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #111111 0%, #111111 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: white; font-size: 32px;">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <h3 style="margin-bottom: 12px; color: #374151; font-size: 24px; font-weight: 600;">No Registrations Yet</h3>
                        <p style="color: #6b7280; font-size: 16px; margin-bottom: 24px; max-width: 400px; margin-left: auto; margin-right: auto;">
                            Programme registrations will appear here once participants start registering for training programmes.
                        </p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                    <th style="padding: 16px 12px; text-align: left; font-weight: 600; color: #374151; font-size: 14px;">Participant</th>
                                    <th style="padding: 16px 12px; text-align: left; font-weight: 600; color: #374151; font-size: 14px;">Programme</th>
                                    <th style="padding: 16px 12px; text-align: left; font-weight: 600; color: #374151; font-size: 14px;">Contact</th>
                                    <th style="padding: 16px 12px; text-align: left; font-weight: 600; color: #374151; font-size: 14px;">Status</th>
                                    <th style="padding: 16px 12px; text-align: left; font-weight: 600; color: #374151; font-size: 14px;">Registered</th>
                                    <th style="padding: 16px 12px; text-align: left; font-weight: 600; color: #374151; font-size: 14px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registrations as $registration): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='white'">
                                        <td style="padding: 16px 12px;">
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #111111 0%, #111111 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 16px;">
                                                    <i class="fas fa-user-graduate"></i>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600; color: #1f2937; font-size: 15px; margin-bottom: 2px;">
                                                        <?php echo htmlspecialchars($registration['name']); ?>
                                                    </div>
                                                    <div style="font-size: 13px; color: #6b7280;">
                                                        ID: #<?php echo $registration['id']; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="padding: 16px 12px;">
                                            <div style="font-weight: 500; color: #374151; margin-bottom: 4px;">
                                                <?php echo htmlspecialchars($registration['programme_title'] ?? 'N/A'); ?>
                                            </div>
                                            <div style="font-size: 13px; color: #6b7280;">
                                                <i class="fas fa-clock" style="margin-right: 4px;"></i>
                                                <?php echo htmlspecialchars($registration['duration'] ?? 'N/A'); ?>
                                            </div>
                                        </td>
                                        <td style="padding: 16px 12px;">
                                            <div style="margin-bottom: 6px;">
                                                <a href="mailto:<?php echo htmlspecialchars($registration['email']); ?>" style="color: #111111; text-decoration: none; font-size: 14px; display: flex; align-items: center; gap: 4px;">
                                                    <i class="fas fa-envelope" style="font-size: 12px;"></i>
                                                    <?php echo htmlspecialchars($registration['email']); ?>
                                                </a>
                                            </div>
                                            <div style="margin-bottom: 6px; color: #111111; font-size: 14px; display: flex; align-items: center; gap: 4px;">
                                                <i class="fas fa-id-card" style="font-size: 12px;"></i>
                                                <?php echo htmlspecialchars($registration['ic_number'] ?? ''); ?>
                                            </div>
                                            <?php if ($registration['phone']): ?>
                                                <a href="tel:<?php echo htmlspecialchars($registration['phone']); ?>" style="color: #059669; text-decoration: none; font-size: 14px; display: flex; align-items: center; gap: 4px;">
                                                    <i class="fas fa-phone" style="font-size: 12px;"></i>
                                                    <?php echo htmlspecialchars($registration['phone']); ?>
                                                </a>
                                            <?php else: ?>
                                                <span style="color: #9ca3af; font-size: 13px; font-style: italic;">
                                                    <i class="fas fa-phone-slash" style="margin-right: 4px;"></i>
                                                    No phone
                                                </span>
                                            <?php endif; ?>
                                            
                                            <!-- Quick info badges -->
                                            <div style="margin-top: 8px; display: flex; flex-wrap: wrap; gap: 4px;">
                                                <?php if ($registration['company']): ?>
                                                    <span style="background: #f4f4f5; color: #0369a1; padding: 2px 6px; border-radius: 12px; font-size: 11px; font-weight: 500;">
                                                        <i class="fas fa-building" style="margin-right: 3px;"></i><?php echo htmlspecialchars($registration['company']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <?php if ($registration['payment_method']): ?>
                                                    <span style="background: #fef3c7; color: #d97706; padding: 2px 6px; border-radius: 12px; font-size: 11px; font-weight: 500;">
                                                        <i class="fas fa-credit-card" style="margin-right: 3px;"></i><?php echo ucfirst(str_replace('_', ' ', $registration['payment_method'])); ?>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <?php if ($registration['vehicle_type']): ?>
                                                    <span style="background: #f0fdf4; color: #16a34a; padding: 2px 6px; border-radius: 12px; font-size: 11px; font-weight: 500;">
                                                        <i class="fas fa-car" style="margin-right: 3px;"></i><?php echo ucfirst($registration['vehicle_type']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td style="padding: 16px 12px;">
                                            <span class="status-badge status-<?php echo $registration['status']; ?>" style="padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                                <i class="fas fa-circle" style="font-size: 8px; margin-right: 6px;"></i>
                                                <?php echo ucfirst($registration['status']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 16px 12px;">
                                            <div style="font-size: 14px; color: #374151; font-weight: 500;">
                                                <?php echo date('M j, Y', strtotime($registration['created_at'])); ?>
                                            </div>
                                            <div style="font-size: 12px; color: #6b7280;">
                                                <?php 
                                                $days_ago = floor((time() - strtotime($registration['created_at'])) / (60 * 60 * 24));
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
                                        <td style="padding: 16px 12px;">
                                            <div style="display: flex; gap: 8px; align-items: center;">
                                                <button onclick="viewRegistration(<?php echo $registration['id']; ?>)" style="background: linear-gradient(135deg, #111111 0%, #111111 100%); color: white; border: none; padding: 10px 16px; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600; transition: all 0.2s; display: flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(17, 17, 17, 0.3);" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(17, 17, 17, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(17, 17, 17, 0.3)'">
                                                    <i class="fas fa-info-circle" style="font-size: 12px;"></i>
                                                    View Details
                                                </button>
                                                <button onclick="updateStatus(<?php echo $registration['id']; ?>, '<?php echo $registration['status']; ?>')" style="background: #6b7280; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500; transition: all 0.2s; display: flex; align-items: center; gap: 4px;" onmouseover="this.style.backgroundColor='#4b5563'" onmouseout="this.style.backgroundColor='#6b7280'">
                                                    <i class="fas fa-edit" style="font-size: 11px;"></i>
                                                    Status
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>


    <!-- Registration Details Modal -->
    <div id="detailsModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); opacity: 0; transition: opacity 0.3s ease;">
        <div class="modal-content" style="background-color: white; margin: 5% auto; padding: 0; border-radius: 12px; width: 95%; max-width: 800px; height: 85%; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); transform: scale(0.9); transition: transform 0.3s ease;">
            <div class="modal-header" style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: #1f2937; font-size: 18px; font-weight: 600;">
                    <i class="fas fa-user-graduate" style="color: #111111; margin-right: 8px;"></i>
                    Registration Details
                </h3>
                <button class="modal-close" onclick="closeModal()" style="background: none; border: none; font-size: 20px; color: #6b7280; cursor: pointer; padding: 4px; border-radius: 4px; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#f3f4f6'" onmouseout="this.style.backgroundColor='transparent'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="registrationDetails" style="padding: 24px; height: calc(100% - 80px); overflow-y: auto;">
                <!-- Registration details will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal" style="display: none; position: fixed; z-index: 1070; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); opacity: 0; transition: opacity 0.3s ease;">
        <div class="modal-content" style="background-color: white; margin: 10% auto; padding: 0; border-radius: 12px; width: 90%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); transform: scale(0.9); transition: transform 0.3s ease;">
            <div class="modal-header" style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: #1f2937; font-size: 18px; font-weight: 600;">
                    <i class="fas fa-edit" style="color: #111111; margin-right: 8px;"></i>
                    Update Registration Status
                </h3>
                <button class="modal-close" onclick="closeModal()" style="background: none; border: none; font-size: 20px; color: #6b7280; cursor: pointer; padding: 4px; border-radius: 4px; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#f3f4f6'" onmouseout="this.style.backgroundColor='transparent'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" class="status-form" style="padding: 24px;">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="registration_id" id="registrationId">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="status" style="display: block; margin-bottom: 8px; color: #374151; font-weight: 500; font-size: 14px;">Status</label>
                    <select name="status" id="statusSelect" class="form-control" required>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="waitlisted">Waitlisted</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="blacklisted">Blacklisted</option>
                    </select>
                </div>
                <div class="form-actions" style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()" style="background: #6b7280; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#4b5563'" onmouseout="this.style.backgroundColor='#6b7280'">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" style="background: #111111; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#111111'" onmouseout="this.style.backgroundColor='#111111'">
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
                <div style="text-align: center; padding: 40px 20px;">
                    <div style="width: 40px; height: 40px; border: 3px solid #e5e7eb; border-top: 3px solid #111111; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 16px;"></div>
                    <p style="color: #6b7280; margin: 0;">Loading registration details...</p>
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
                        <div style="text-align: center; padding: 40px 20px; color: #ef4444;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 16px;"></i>
                            <h4 style="margin-bottom: 8px; color: #dc2626;">Error Loading Details</h4>
                            <p style="color: #6b7280; margin: 0;">Unable to load registration details. Please try again.</p>
                            <p style="color: #6b7280; margin: 8px 0 0 0; font-size: 12px;">Error: ${error.message}</p>
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

        function exportRegistrations() {
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
                    <h3 style="margin: 0; color: #1e293b;">Export Registrations</h3>
                </div>
                
                <p style="color: #64748b; margin-bottom: 20px;">
                    Choose the format for your registrations export:
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
            const exportBtn = document.querySelector('button[onclick="exportRegistrations()"]');
            const originalText = exportBtn.innerHTML;
            
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 4px;"></i>Preparing...';
            exportBtn.disabled = true;
            
            // Get current search and filter parameters
            const searchParams = new URLSearchParams(window.location.search);
            const search = searchParams.get('search') || '';
            const filter = searchParams.get('filter') || '';
            
            // Create export URL with format
            const exportUrl = `export-registrations.php?format=${format}&search=${encodeURIComponent(search)}&filter=${encodeURIComponent(filter)}`;
            
            // Create a temporary link and trigger download
            const link = document.createElement('a');
            link.href = exportUrl;
            link.download = `programme_registrations.${format}`;
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
