<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Include database connection and services
require_once '../includes/database.php';
require_once '../includes/facility-booking-service.php';

$pdo = get_db_connection();
$facilityService = new FacilityBookingService($pdo);

$error_message = '';
$success_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $programme_id = (int)($_POST['programme_id'] ?? 0);
    
    try {
        switch ($action) {
            case 'create_facility_booking':
                $bookingId = $facilityService->createProgrammeFacilityBooking($programme_id);
                $success_message = "Facility booking created successfully! Booking ID: #{$bookingId}";
                break;
                
            case 'update_facility_booking':
                $facility_type = $_POST['facility_type'] ?? '';
                $booking_date = $_POST['booking_date'] ?? '';
                $start_time = $_POST['start_time'] ?? '';
                $end_time = $_POST['end_time'] ?? '';
                
                $facilityService->updateProgrammeFacilityBooking(
                    $programme_id, 
                    $facility_type, 
                    $booking_date, 
                    $start_time, 
                    $end_time
                );
                $success_message = "Facility booking updated successfully!";
                break;
                
            case 'delete_facility_booking':
                $facilityService->deleteProgrammeFacilityBooking($programme_id);
                $success_message = "Facility booking deleted successfully!";
                break;
                
            case 'toggle_facility_requirement':
                $requires_facility = (bool)($_POST['requires_facility'] ?? false);
                
                $stmt = $pdo->prepare("
                    UPDATE programmes 
                    SET requires_facility = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$requires_facility, $programme_id]);
                
                if ($requires_facility) {
                    $success_message = "Programme now requires facility booking";
                } else {
                    // Delete existing facility booking if it exists
                    $facilityService->deleteProgrammeFacilityBooking($programme_id);
                    $success_message = "Programme no longer requires facility booking";
                }
                break;
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get all programmes with facility booking information
$programmes = $facilityService->getAllProgrammeFacilityBookings();

$facility_types = [
    'conference_room' => 'Conference Room',
    'training_room' => 'Training Room',
    'breakout_area' => 'Breakout Area'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programme Facility Management - XpressPoint Admin</title>
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="admin-styles.css">
    
    <style>
        .facility-management-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .programme-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #3b82f6;
        }
        
        .programme-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .programme-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }
        
        .facility-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .status-requires { background: #fef3c7; color: #92400e; }
        .status-booked { background: #d1fae5; color: #065f46; }
        .status-pending { background: #dbeafe; color: #1e40af; }
        .status-no-facility { background: #f3f4f6; color: #6b7280; }
        
        .facility-details {
            background: #f8fafc;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            border: 1px solid #e2e8f0;
        }
        
        .facility-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .facility-info-item {
            display: flex;
            flex-direction: column;
        }
        
        .facility-info-label {
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 0.25rem;
            font-weight: 600;
        }
        
        .facility-info-value {
            font-weight: 600;
            color: #1e293b;
        }
        
        .facility-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-facility {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }
        
        .btn-create { background: #10b981; color: white; }
        .btn-edit { background: #3b82f6; color: white; }
        .btn-delete { background: #ef4444; color: white; }
        .btn-toggle { background: #6b7280; color: white; }
        .btn-view { background: #8b5cf6; color: white; }
        
        .btn-facility:hover {
            opacity: 0.9;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 12px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #64748b;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }
        
        .btn-modal {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-primary { background: #3b82f6; color: white; }
        .btn-secondary { background: #6b7280; color: white; }
        
        .conflict-warning {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .conflict-warning i {
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="admin-header">
        <h1>
            <i class="fas fa-building"></i>
            Programme Facility Management
        </h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </header>
    
    <div class="admin-container">
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('facility_management'); ?>
        
        <!-- Page Header -->
        <div class="page-header">
            <h2 class="page-title">
                <i class="fas fa-building"></i>
                Programme Facility Management
            </h2>
            <div class="d-flex gap-2">
                <a href="programmes.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Programmes
                </a>
                <a href="facility-calendar.php" class="btn btn-primary">
                    <i class="fas fa-calendar"></i>
                    Calendar View
                </a>
                <a href="bookings.php" class="btn btn-secondary">
                    <i class="fas fa-calendar-alt"></i>
                    All Bookings
                </a>
            </div>
        </div>
        
        <!-- Alerts -->
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Programmes with Facility Management -->
        <div class="facility-management-container">
            <?php if (!empty($programmes)): ?>
                <?php foreach ($programmes as $programme): ?>
                    <div class="programme-card">
                        <div class="programme-header">
                            <h3 class="programme-title">
                                <i class="fas fa-graduation-cap"></i>
                                <?php echo htmlspecialchars($programme['title']); ?>
                            </h3>
                            <span class="facility-status status-<?php echo $programme['booking_id'] ? 'booked' : ($programme['requires_facility'] ? 'pending' : 'no-facility'); ?>">
                                <?php if ($programme['booking_id']): ?>
                                    <i class="fas fa-check-circle"></i>
                                    Facility Booked
                                <?php elseif ($programme['requires_facility']): ?>
                                    <i class="fas fa-clock"></i>
                                    Requires Facility
                                <?php else: ?>
                                    <i class="fas fa-times-circle"></i>
                                    No Facility Required
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <?php if ($programme['booking_id']): ?>
                            <div class="facility-details">
                                <h4 style="margin: 0 0 1rem 0; color: #1e293b; font-size: 1rem;">
                                    <i class="fas fa-building"></i>
                                    Facility Booking Details
                                </h4>
                                
                                <div class="facility-info-grid">
                                    <div class="facility-info-item">
                                        <span class="facility-info-label">Booking ID</span>
                                        <span class="facility-info-value">#<?php echo $programme['booking_id']; ?></span>
                                    </div>
                                    <div class="facility-info-item">
                                        <span class="facility-info-label">Facility Type</span>
                                        <span class="facility-info-value"><?php echo $facility_types[$programme['facility_type']] ?? $programme['facility_type']; ?></span>
                                    </div>
                                    <div class="facility-info-item">
                                        <span class="facility-info-label">Date</span>
                                        <span class="facility-info-value"><?php echo date('M j, Y', strtotime($programme['booking_date'])); ?></span>
                                    </div>
                                    <div class="facility-info-item">
                                        <span class="facility-info-label">Time</span>
                                        <span class="facility-info-value">
                                            <?php echo date('g:i A', strtotime($programme['start_time'])); ?> - 
                                            <?php echo date('g:i A', strtotime($programme['end_time'])); ?>
                                        </span>
                                    </div>
                                    <div class="facility-info-item">
                                        <span class="facility-info-label">Status</span>
                                        <span class="facility-info-value">
                                            <span class="badge <?php echo $programme['status'] === 'confirmed' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($programme['status']); ?>
                                            </span>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="facility-actions">
                                    <button onclick="editFacilityBooking(<?php echo $programme['programme_id']; ?>)" class="btn-facility btn-edit">
                                        <i class="fas fa-edit"></i>
                                        Edit Booking
                                    </button>
                                    <a href="booking-details.php?id=<?php echo $programme['booking_id']; ?>" class="btn-facility btn-view">
                                        <i class="fas fa-eye"></i>
                                        View Details
                                    </a>
                                    <?php if (($_SESSION['admin_role'] ?? 'admin') !== 'manager'): ?>
                                    <button onclick="deleteFacilityBooking(<?php echo $programme['programme_id']; ?>)" class="btn-facility btn-delete">
                                        <i class="fas fa-trash"></i>
                                        Delete Booking
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="facility-details">
                                <p style="margin: 0 0 1rem 0; color: #64748b;">
                                    <?php if ($programme['requires_facility']): ?>
                                        This programme requires a facility booking but none has been created yet.
                                    <?php else: ?>
                                        This programme does not require facility booking.
                                    <?php endif; ?>
                                </p>
                                
                                <div class="facility-actions">
                                    <?php if ($programme['requires_facility']): ?>
                                        <button onclick="createFacilityBooking(<?php echo $programme['programme_id']; ?>)" class="btn-facility btn-create">
                                            <i class="fas fa-plus"></i>
                                            Create Facility Booking
                                        </button>
                                    <?php endif; ?>
                                    <button onclick="toggleFacilityRequirement(<?php echo $programme['programme_id']; ?>, <?php echo $programme['requires_facility'] ? 'false' : 'true'; ?>)" class="btn-facility btn-toggle">
                                        <i class="fas fa-toggle-<?php echo $programme['requires_facility'] ? 'off' : 'on'; ?>"></i>
                                        <?php echo $programme['requires_facility'] ? 'Disable Facility' : 'Require Facility'; ?>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card">
                    <div style="padding: 60px 20px; text-align: center; color: #64748b;">
                        <i class="fas fa-building" style="font-size: 64px; margin-bottom: 20px; opacity: 0.5;"></i>
                        <h3 style="margin-bottom: 8px; color: #1e293b;">No programmes found</h3>
                        <p style="margin: 0; font-size: 16px;">
                            Create some training programmes first to manage facility bookings.
                        </p>
                        <a href="add-programme.php" class="btn btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-plus"></i>
                            Add Programme
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Edit Facility Booking Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Facility Booking</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <form id="editForm" method="POST">
                <input type="hidden" name="action" value="update_facility_booking">
                <input type="hidden" name="programme_id" id="editProgrammeId">
                
                <div class="form-group">
                    <label class="form-label" for="editFacilityType">Facility Type</label>
                    <select class="form-control" id="editFacilityType" name="facility_type" required>
                        <?php foreach ($facility_types as $value => $label): ?>
                            <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="editBookingDate">Booking Date</label>
                    <input type="date" class="form-control" id="editBookingDate" name="booking_date" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="editStartTime">Start Time</label>
                    <input type="time" class="form-control" id="editStartTime" name="start_time" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="editEndTime">End Time</label>
                    <input type="time" class="form-control" id="editEndTime" name="end_time" required>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-modal btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-modal btn-primary">Update Booking</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function createFacilityBooking(programmeId) {
            if (confirm('Create facility booking for this programme?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="create_facility_booking">
                    <input type="hidden" name="programme_id" value="${programmeId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function editFacilityBooking(programmeId) {
            // You would typically fetch current booking details here
            // For now, we'll just show the modal
            document.getElementById('editProgrammeId').value = programmeId;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function deleteFacilityBooking(programmeId) {
            if (confirm('Delete facility booking for this programme? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_facility_booking">
                    <input type="hidden" name="programme_id" value="${programmeId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function toggleFacilityRequirement(programmeId, requiresFacility) {
            const action = requiresFacility ? 'Enable' : 'Disable';
            if (confirm(`${action} facility requirement for this programme?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="toggle_facility_requirement">
                    <input type="hidden" name="programme_id" value="${programmeId}">
                    <input type="hidden" name="requires_facility" value="${requiresFacility}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
