<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager', 'trainer']);

// Include database connection
require_once '../includes/database.php';
require_once __DIR__ . '/includes/ui.php';

$pdo = get_db_connection();
$booking = null;
$error_message = '';

// Get booking ID from URL
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($booking_id && $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT b.*, u.username as confirmed_by_name, p.owner_user_id
            FROM bookings b
            LEFT JOIN users u ON b.confirmed_by = u.id
            LEFT JOIN programmes p ON b.programme_id = p.id
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
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include_once __DIR__ . '/includes/theme-boot.php'; ?>
    <link rel="stylesheet" href="<?php echo admin_asset('admin.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/detail.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/booking-details.css'); ?>">
    
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Booking Details', 'fas fa-calendar-check', ['url' => 'facilities.php', 'label' => 'Facilities']); ?>
    
    <div class="admin-container">
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('bookings'); ?>
    
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php elseif ($booking):
            $canChangeBooking = auth_can_change_booking(auth_user(), $booking['owner_user_id'] ?? null, $booking['programme_id'] ?? null);
            $start = strtotime($booking['start_time']);
            $end = strtotime($booking['end_time']);
            $duration = round(($end - $start) / 3600, 1);
            $primary = '';
            if ($canChangeBooking && $booking['status'] === 'pending') {
                $primary = '<button type="button" class="btn btn-primary" onclick="confirmBooking(' . (int) $booking['id'] . ')"><i class="fas fa-check" aria-hidden="true"></i> Confirm booking</button>';
            } elseif ($canChangeBooking && $booking['status'] === 'confirmed') {
                $primary = '<button type="button" class="btn btn-primary" onclick="completeBooking(' . (int) $booking['id'] . ')"><i class="fas fa-check-double" aria-hidden="true"></i> Mark completed</button>';
            }
        ?>
            <div class="detail-page">
                <header class="detail-head">
                    <div class="detail-head-copy">
                        <a class="detail-back" href="facilities.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to bookings</a>
                        <h2 class="detail-title"><?php echo htmlspecialchars($booking['customer_name']); ?></h2>
                        <div class="detail-head-meta">
                            <?php renderStatusBadge($booking['status']); ?>
                            <span class="meta">Booking #<?php echo (int) $booking['id']; ?></span>
                        </div>
                    </div>
                    <?php echo $primary; ?>
                </header>
                <div class="detail-layout">
                    <div class="detail-main">
                        <section class="detail-card">
                            <h3>Personal details</h3>
                            <div class="kv-list">
                                <div class="kv-row"><span class="kv-label">Name</span><span class="kv-value"><?php echo htmlspecialchars($booking['customer_name']); ?></span></div>
                                <div class="kv-row"><span class="kv-label">Email</span><span class="kv-value"><a href="mailto:<?php echo htmlspecialchars($booking['customer_email']); ?>"><?php echo htmlspecialchars($booking['customer_email']); ?></a></span></div>
                                <div class="kv-row"><span class="kv-label">Phone</span><span class="kv-value"><?php echo htmlspecialchars($booking['customer_phone'] ?: 'Not provided'); ?></span></div>
                                <div class="kv-row"><span class="kv-label">Company</span><span class="kv-value"><?php echo htmlspecialchars($booking['company'] ?: 'Not specified'); ?></span></div>
                                <div class="kv-row"><span class="kv-label">Attendees</span><span class="kv-value"><?php echo (int) $booking['attendees_count']; ?> people</span></div>
                            </div>
                        </section>
                        <section class="detail-card">
                            <h3>Booking info</h3>
                            <div class="kv-list">
                                <div class="kv-row"><span class="kv-label">Facility</span><span class="kv-value"><?php echo htmlspecialchars($facility_types[$booking['facility_type']] ?? $booking['facility_type']); ?></span></div>
                                <div class="kv-row"><span class="kv-label">Date</span><span class="kv-value"><?php echo date('l, F j, Y', strtotime($booking['booking_date'])); ?></span></div>
                                <div class="kv-row"><span class="kv-label">Time</span><span class="kv-value"><?php echo date('g:i A', $start); ?> – <?php echo date('g:i A', $end); ?></span></div>
                                <div class="kv-row"><span class="kv-label">Duration</span><span class="kv-value"><?php echo $duration . ' hour' . ($duration != 1 ? 's' : ''); ?></span></div>
                                <div class="kv-row"><span class="kv-label">Purpose</span><span class="kv-value"><?php echo htmlspecialchars($booking['purpose'] ?: 'Not specified'); ?></span></div>
                                <div class="kv-row"><span class="kv-label">Requirements</span><span class="kv-value"><?php echo htmlspecialchars($booking['special_requirements'] ?: 'None'); ?></span></div>
                                <?php if ($booking['cancellation_reason']): ?>
                                <div class="kv-row"><span class="kv-label">Cancellation</span><span class="kv-value"><?php echo htmlspecialchars($booking['cancellation_reason']); ?></span></div>
                                <?php endif; ?>
                            </div>
                        </section>
                    </div>
                    <aside class="detail-side">
                        <section class="detail-card">
                            <h3>Current status</h3>
                            <?php renderStatusBadge($booking['status']); ?>
                            <div class="detail-stack">
                                <?php if ($canChangeBooking && $booking['status'] === 'pending'): ?>
                                    <button type="button" class="btn btn-primary" onclick="confirmBooking(<?php echo (int) $booking['id']; ?>)"><i class="fas fa-check" aria-hidden="true"></i> Confirm booking</button>
                                    <button type="button" class="btn btn-secondary" onclick="cancelBooking(<?php echo (int) $booking['id']; ?>)"><i class="fas fa-times" aria-hidden="true"></i> Cancel booking</button>
                                <?php elseif ($canChangeBooking && $booking['status'] === 'confirmed'): ?>
                                    <button type="button" class="btn btn-primary" onclick="completeBooking(<?php echo (int) $booking['id']; ?>)"><i class="fas fa-check-double" aria-hidden="true"></i> Mark completed</button>
                                <?php else: ?>
                                    <p class="detail-prose">This booking is <?php echo htmlspecialchars($booking['status']); ?>.</p>
                                <?php endif; ?>
                            </div>
                        </section>
                        <section class="detail-card">
                            <h3>Quick actions</h3>
                            <div class="detail-actions">
                                <a class="btn btn-secondary" href="mailto:<?php echo htmlspecialchars($booking['customer_email']); ?>"><i class="fas fa-envelope" aria-hidden="true"></i> Email</a>
                                <a class="btn btn-secondary" href="facilities.php"><i class="fas fa-calendar-alt" aria-hidden="true"></i> Open calendar</a>
                            </div>
                        </section>
                        <section class="detail-card">
                            <h3>Activity</h3>
                            <ol class="detail-timeline">
                                <li>
                                    <div>
                                        <strong>Booked</strong>
                                        <time datetime="<?php echo htmlspecialchars($booking['created_at']); ?>"><?php echo date('M j, Y', strtotime($booking['created_at'])); ?></time>
                                    </div>
                                </li>
                                <?php if ($booking['confirmed_by_name']): ?>
                                <li>
                                    <div>
                                        <strong>Confirmed by <?php echo htmlspecialchars($booking['confirmed_by_name']); ?></strong>
                                        <time datetime="<?php echo htmlspecialchars($booking['confirmed_at']); ?>"><?php echo date('M j, Y', strtotime($booking['confirmed_at'])); ?></time>
                                    </div>
                                </li>
                                <?php endif; ?>
                            </ol>
                        </section>
                    </aside>
                </div>
            </div>
        <?php endif; ?>
    </div>


    <script>
        function postBookingAction(bookingId, action, extra) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'facilities.php';
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
</body>
</html>
