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
$booking = null;
$error_message = '';

// Get booking ID from URL
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($booking_id && $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT b.*, u.username as confirmed_by_name
            FROM bookings b
            LEFT JOIN users u ON b.confirmed_by = u.id
            WHERE b.id = ?
        ");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            $error_message = "Booking not found.";
        }
    } catch (PDOException $e) {
        $error_message = "Error loading booking details.";
    }
} else {
    $error_message = "Invalid booking ID.";
}

$facility_types = [
    'conference_room' => 'Conference Room',
    'training_room' => 'Training Room',
    'breakout_area' => 'Breakout Area'
];

$status_options = [
    'pending' => 'Pending',
    'confirmed' => 'Confirmed',
    'cancelled' => 'Cancelled',
    'completed' => 'Completed'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details - XpressPoint Admin</title>
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="admin-styles.css">
    
    <style>
        .booking-details-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .booking-header {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .booking-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .booking-id {
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
        }
        
        .booking-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-confirmed { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .status-completed { background: #dbeafe; color: #1e40af; }
        
        .booking-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .info-section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .info-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)) !important;
            gap: 1.5rem !important;
        }
        
        .info-item {
            display: flex !important;
            flex-direction: column !important;
            background: #f8fafc !important;
            padding: 1rem !important;
            border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important;
        }
        
        .info-item.email-item {
            grid-column: 1 / -1 !important;
        }
        
        .info-label {
            font-size: 0.8rem;
            color: #718096;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-weight: 600 !important;
            color: #2d3748 !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
            hyphens: auto !important;
        }
        
        .info-value.email-value {
            font-family: 'Courier New', monospace !important;
            font-size: 0.9rem !important;
            background: #f1f5f9 !important;
            padding: 0.5rem !important;
            border-radius: 4px !important;
            border: 1px solid #e2e8f0 !important;
        }
        
        .info-value.empty {
            color: #a0aec0;
            font-style: italic;
        }
        
        .booking-actions {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 2rem;
        }
        
        .actions-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 1rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-confirm { background: #10b981; color: white; }
        .btn-cancel { background: #ef4444; color: white; }
        .btn-complete { background: #3b82f6; color: white; }
        .btn-back { background: #6b7280; color: white; }
        
        .btn-action:hover {
            opacity: 0.9;
        }
        
        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 2rem;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr !important;
                gap: 1rem !important;
            }
            
            .info-item {
                padding: 0.75rem !important;
            }
            
            .info-value.email-value {
                font-size: 0.85rem !important;
                padding: 0.375rem !important;
            }
        }
        
        @media (max-width: 480px) {
            .info-grid {
                gap: 0.75rem !important;
            }
            
            .info-item {
                padding: 0.5rem !important;
            }
            
            .info-label {
                font-size: 0.75rem !important;
            }
            
            .info-value {
                font-size: 0.9rem !important;
            }
            
            .info-value.email-value {
                font-size: 0.8rem !important;
                padding: 0.25rem !important;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="admin-header">
        <h1>
            <i class="fas fa-calendar-alt"></i>
            XpressPoint Admin Dashboard
        </h1>
        <div class="user-info">
            <button class="theme-toggle" onclick="toggleTheme()">
                <i class="fas fa-moon" id="theme-icon"></i>
                <span id="theme-text">Dark Mode</span>
            </button>
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
            <a href="logout-confirm.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </header>
    
    <div class="admin-container">
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('bookings'); ?>
    
        <a href="bookings.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Back to Bookings
        </a>
        
        <?php if ($error_message): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php elseif ($booking): ?>
            <div class="booking-details-container">
                <!-- Booking Header -->
                <div class="booking-header">
                    <div class="booking-title">
                        <span class="booking-id">Booking #<?php echo $booking['id']; ?></span>
                        <span class="booking-status status-<?php echo $booking['status']; ?>">
                            <?php echo ucfirst($booking['status']); ?>
                        </span>
                    </div>
                    
                    <div class="booking-info">
                        <div class="info-item">
                            <span class="info-label">Facility</span>
                            <span class="info-value"><?php echo htmlspecialchars($facility_types[$booking['facility_type']] ?? $booking['facility_type']); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Date</span>
                            <span class="info-value"><?php echo date('l, F j, Y', strtotime($booking['booking_date'])); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Time</span>
                            <span class="info-value">
                                <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - 
                                <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Duration</span>
                            <span class="info-value">
                                <?php 
                                $start = strtotime($booking['start_time']);
                                $end = strtotime($booking['end_time']);
                                $duration = round(($end - $start) / 3600, 1);
                                echo $duration . ' hour' . ($duration != 1 ? 's' : '');
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Customer Information -->
                <div class="info-section">
                    <h3 class="section-title">
                        <i class="fas fa-user"></i>
                        Customer Information
                    </h3>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Full Name</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['customer_name']); ?></span>
                        </div>
                        
                        <div class="info-item email-item">
                            <span class="info-label">Email Address</span>
                            <span class="info-value email-value"><?php echo htmlspecialchars($booking['customer_email']); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Phone Number</span>
                            <span class="info-value <?php echo empty($booking['customer_phone']) ? 'empty' : ''; ?>">
                                <?php echo htmlspecialchars($booking['customer_phone'] ?: 'Not provided'); ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Company</span>
                            <span class="info-value <?php echo empty($booking['company']) ? 'empty' : ''; ?>">
                                <?php echo htmlspecialchars($booking['company'] ?: 'Not specified'); ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Number of Attendees</span>
                            <span class="info-value"><?php echo $booking['attendees_count']; ?> people</span>
                        </div>
                    </div>
                </div>
                
                <!-- Booking Details -->
                <div class="info-section">
                    <h3 class="section-title">
                        <i class="fas fa-calendar-alt"></i>
                        Booking Details
                    </h3>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Purpose</span>
                            <span class="info-value <?php echo empty($booking['purpose']) ? 'empty' : ''; ?>">
                                <?php echo htmlspecialchars($booking['purpose'] ?: 'Not specified'); ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Special Requirements</span>
                            <span class="info-value <?php echo empty($booking['special_requirements']) ? 'empty' : ''; ?>">
                                <?php echo htmlspecialchars($booking['special_requirements'] ?: 'None'); ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Booking Created</span>
                            <span class="info-value"><?php echo date('M j, Y \a\t g:i A', strtotime($booking['created_at'])); ?></span>
                        </div>
                        
                        <?php if ($booking['confirmed_by_name']): ?>
                            <div class="info-item">
                                <span class="info-label">Confirmed By</span>
                                <span class="info-value"><?php echo htmlspecialchars($booking['confirmed_by_name']); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Confirmed On</span>
                                <span class="info-value"><?php echo date('M j, Y \a\t g:i A', strtotime($booking['confirmed_at'])); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($booking['cancellation_reason']): ?>
                            <div class="info-item">
                                <span class="info-label">Cancellation Reason</span>
                                <span class="info-value"><?php echo htmlspecialchars($booking['cancellation_reason']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Booking Actions -->
                <div class="booking-actions">
                    <h3 class="actions-title">Actions</h3>
                    
                    <div class="action-buttons">
                        <a href="bookings.php" class="btn-action btn-back">
                            <i class="fas fa-arrow-left"></i>
                            Back to Bookings
                        </a>
                        
                        <?php if ($booking['status'] === 'pending'): ?>
                            <button onclick="confirmBooking(<?php echo $booking['id']; ?>)" class="btn-action btn-confirm">
                                <i class="fas fa-check"></i>
                                Confirm Booking
                            </button>
                            <button onclick="cancelBooking(<?php echo $booking['id']; ?>)" class="btn-action btn-cancel">
                                <i class="fas fa-times"></i>
                                Cancel Booking
                            </button>
                        <?php elseif ($booking['status'] === 'confirmed'): ?>
                            <button onclick="completeBooking(<?php echo $booking['id']; ?>)" class="btn-action btn-complete">
                                <i class="fas fa-check-double"></i>
                                Mark as Completed
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function confirmBooking(bookingId) {
            if (confirm('Are you sure you want to confirm this booking?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'bookings.php';
                form.innerHTML = `
                    <input type="hidden" name="action" value="confirm">
                    <input type="hidden" name="booking_id" value="${bookingId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function cancelBooking(bookingId) {
            const reason = prompt('Please provide a reason for cancellation:');
            if (reason !== null) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'bookings.php';
                form.innerHTML = `
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="booking_id" value="${bookingId}">
                    <input type="hidden" name="cancellation_reason" value="${reason}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function completeBooking(bookingId) {
            if (confirm('Are you sure you want to mark this booking as completed?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'bookings.php';
                form.innerHTML = `
                    <input type="hidden" name="action" value="complete">
                    <input type="hidden" name="booking_id" value="${bookingId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
