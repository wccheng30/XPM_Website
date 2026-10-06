<?php
// Set timezone for consistent date handling
date_default_timezone_set('Asia/Kuala_Lumpur');

// Include shared database connection
require_once 'includes/database.php';

$pdo = get_db_connection();
$error_message = '';
$success_message = '';

// Handle booking lookup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lookup_email'])) {
    $email = trim($_POST['lookup_email']);
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM bookings 
                WHERE customer_email = ? 
                ORDER BY booking_date DESC, start_time ASC
            ");
            $stmt->execute([$email]);
            $bookings = $stmt->fetchAll();
        } catch (PDOException $e) {
            $error_message = "Error retrieving bookings.";
        }
    }
}

// Handle booking cancellation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    $booking_id = (int)$_POST['booking_id'];
    $email = trim($_POST['customer_email']);
    
    try {
        // Verify booking belongs to this email
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND customer_email = ?");
        $stmt->execute([$booking_id, $email]);
        $booking = $stmt->fetch();
        
        if ($booking && $booking['status'] === 'pending') {
            // Update booking status
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', cancellation_reason = 'Cancelled by customer' WHERE id = ?");
            $stmt->execute([$booking_id]);
            
            $success_message = "Your booking has been cancelled successfully.";
        } else {
            $error_message = "Booking not found or cannot be cancelled.";
        }
    } catch (PDOException $e) {
        $error_message = "Error cancelling booking.";
    }
}

$facility_types = [
    'conference_room' => 'Conference Room',
    'training_room' => 'Training Room',
    'breakout_area' => 'Breakout Area'
];

$status_colors = [
    'pending' => '#f59e0b',
    'confirmed' => '#10b981',
    'cancelled' => '#ef4444',
    'completed' => '#3b82f6'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - XpressPoint HR Training</title>
    <?php $xpm_page_styles = ['pages/my-bookings.css']; include 'partials/site-head.php'; ?>
</head>
<body>
    <?php $xpm_active = 'facilities'; include 'partials/site-nav.php'; ?>
<?php
$xpm_status_badges = [
    'pending' => 'badge-warning',
    'confirmed' => 'badge-success',
    'cancelled' => 'badge-danger',
    'completed' => 'badge-dark'
];
?>

    <main id="main">
        <header class="page-hero">
            <div class="container container-narrow">
                <nav class="breadcrumb" aria-label="Breadcrumb" data-reveal="fade">
                    <a href="index.html">Home</a><i class="fas fa-chevron-right"></i>
                    <a href="facility-booking.php">Facility Booking</a><i class="fas fa-chevron-right"></i>
                    <span>My Bookings</span>
                </nav>
                <span class="eyebrow" data-reveal><i class="fas fa-calendar-check"></i> Manage</span>
                <h1 data-split>My bookings.</h1>
                <p class="lead" data-reveal>View the status of your facility bookings, or cancel a pending request.</p>
            </div>
        </header>

        <section class="section">
            <div class="container container-narrow">
                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-circle-exclamation"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-circle-check"></i>
                        <span><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!isset($bookings)): ?>
                    <div class="card lookup-card" data-reveal>
                        <span class="icon-box"><i class="fas fa-magnifying-glass"></i></span>
                        <h2>Look up your bookings</h2>
                        <p>Enter the email address you used when booking.</p>
                        <form method="POST" class="form lookup-form">
                            <div class="form-group">
                                <label for="lookup_email">Email address</label>
                                <div class="input-icon">
                                    <i class="fas fa-envelope"></i>
                                    <input type="email" id="lookup_email" name="lookup_email" required placeholder="you@company.com">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg btn-block">
                                Find my bookings <i class="fas fa-arrow-right"></i>
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <?php if (empty($bookings)): ?>
                        <div class="empty-state" data-reveal>
                            <span class="icon-box"><i class="fas fa-calendar-xmark"></i></span>
                            <h3>No bookings found</h3>
                            <p>We couldn't find any bookings for this email address.</p>
                            <div class="cluster" style="justify-content:center">
                                <a href="facility-booking.php" class="btn btn-primary">Make a new booking</a>
                                <a href="my-bookings.php" class="btn btn-outline">Try another email</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bookings-toolbar" data-reveal="fade">
                            <p><strong><?php echo count($bookings); ?></strong> booking<?php echo count($bookings) === 1 ? '' : 's'; ?> found</p>
                            <a href="my-bookings.php" class="btn btn-outline btn-sm">
                                <i class="fas fa-rotate"></i> Look up a different email
                            </a>
                        </div>

                        <div class="bookings-list" data-reveal-stagger>
                            <?php foreach ($bookings as $booking): ?>
                            <article class="booking-card">
                                <div class="booking-head">
                                    <div>
                                        <span class="booking-id">Booking #<?php echo $booking['id']; ?></span>
                                        <h3><?php echo htmlspecialchars($facility_types[$booking['facility_type']] ?? $booking['facility_type']); ?></h3>
                                    </div>
                                    <span class="badge <?php echo $xpm_status_badges[$booking['status']] ?? ''; ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </div>

                                <dl class="booking-grid">
                                    <div>
                                        <dt>Date</dt>
                                        <dd><?php echo date('M j, Y', strtotime($booking['booking_date'])); ?></dd>
                                    </div>
                                    <div>
                                        <dt>Time</dt>
                                        <dd><?php echo date('g:i A', strtotime($booking['start_time'])); ?> - <?php echo date('g:i A', strtotime($booking['end_time'])); ?></dd>
                                    </div>
                                    <div>
                                        <dt>Attendees</dt>
                                        <dd><?php echo $booking['attendees_count']; ?> people</dd>
                                    </div>
                                    <?php if ($booking['company']): ?>
                                    <div>
                                        <dt>Company</dt>
                                        <dd><?php echo htmlspecialchars($booking['company']); ?></dd>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <dt>Booked on</dt>
                                        <dd><?php echo date('M j, Y g:i A', strtotime($booking['created_at'])); ?></dd>
                                    </div>
                                </dl>

                                <?php if ($booking['purpose']): ?>
                                <div class="booking-note">
                                    <span>Purpose</span>
                                    <p><?php echo htmlspecialchars($booking['purpose']); ?></p>
                                </div>
                                <?php endif; ?>

                                <?php if ($booking['special_requirements']): ?>
                                <div class="booking-note">
                                    <span>Special requirements</span>
                                    <p><?php echo htmlspecialchars($booking['special_requirements']); ?></p>
                                </div>
                                <?php endif; ?>

                                <div class="booking-actions">
                                    <a href="mailto:hr@xpresspointservices.com?subject=Booking Inquiry - #<?php echo $booking['id']; ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-envelope"></i> Contact support
                                    </a>
                                    <?php if ($booking['status'] === 'pending'): ?>
                                    <form method="POST">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                        <input type="hidden" name="customer_email" value="<?php echo htmlspecialchars($booking['customer_email']); ?>">
                                        <button type="submit" name="cancel_booking" class="btn btn-danger btn-sm" data-confirm-title="Cancel this booking?" data-confirm="This booking will be cancelled." data-confirm-ok="Cancel booking" data-confirm-icon="fas fa-xmark">
                                            <i class="fas fa-xmark"></i> Cancel booking
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <?php include 'partials/site-footer.php'; ?>
</body>
</html>
