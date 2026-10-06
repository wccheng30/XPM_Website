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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-styles.css?v=20261005-5">
    <link rel="stylesheet" href="css/booking-details.css?v=20260930-4">
    
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Booking Details', 'fas fa-calendar-check', ['url' => 'bookings.php', 'label' => 'Bookings']); ?>
    
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
        function postBookingAction(bookingId, action, extra) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'bookings.php';
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = action;
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'booking_id';
            idInput.value = bookingId;
            form.appendChild(actionInput);
            form.appendChild(idInput);
            if (extra) {
                Object.keys(extra).forEach(function (name) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = extra[name];
                    form.appendChild(input);
                });
            }
            document.body.appendChild(form);
            form.submit();
        }

        function confirmBooking(bookingId) {
            xpmConfirm({
                title: 'Confirm this booking?',
                message: 'The booking status will change to confirmed.',
                confirmText: 'Confirm',
                icon: 'fas fa-check'
            }).then(function (ok) {
                if (ok) postBookingAction(bookingId, 'confirm');
            });
        }
        
        function cancelBooking(bookingId) {
            xpmPrompt({
                title: 'Cancel this booking?',
                message: 'Please provide a reason for cancellation.',
                placeholder: 'Reason',
                confirmText: 'Cancel booking',
                icon: 'fas fa-xmark'
            }).then(function (reason) {
                if (reason === null) return;
                postBookingAction(bookingId, 'cancel', { cancellation_reason: reason });
            });
        }
        
        function completeBooking(bookingId) {
            xpmConfirm({
                title: 'Mark this booking as completed?',
                message: 'The booking status will change to completed.',
                confirmText: 'Mark completed',
                icon: 'fas fa-check-double'
            }).then(function (ok) {
                if (ok) postBookingAction(bookingId, 'complete');
            });
        }
    </script>
    <script src="../assets/js/motion.js?v=20261002-10"></script>
</body>
</html>
