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

// Handle AJAX requests
if (isset($_GET['ajax']) && $_GET['ajax'] === 'booking_details') {
    $booking_id = (int)($_GET['booking_id'] ?? 0);
    
    $stmt = $pdo->prepare("
        SELECT b.*, p.title as programme_title, p.id as programme_id
        FROM bookings b
        LEFT JOIN programmes p ON b.programme_id = p.id
        WHERE b.id = ?
    ");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();
    
    if ($booking) {
        header('Content-Type: application/json');
        echo json_encode($booking);
        exit;
    }
}

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
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get current month and year
$current_month = $_GET['month'] ?? date('n');
$current_year = $_GET['year'] ?? date('Y');
$current_month = (int)$current_month;
$current_year = (int)$current_year;

// Get all facility bookings for the current month
$start_date = date('Y-m-01', mktime(0, 0, 0, $current_month, 1, $current_year));
$end_date = date('Y-m-t', mktime(0, 0, 0, $current_month, 1, $current_year));

$stmt = $pdo->prepare("
    SELECT b.*, p.title as programme_title, p.id as programme_id
    FROM bookings b
    LEFT JOIN programmes p ON b.programme_id = p.id
    WHERE b.booking_date BETWEEN ? AND ?
    AND b.status IN ('pending', 'confirmed')
    ORDER BY b.booking_date, b.start_time
");

$stmt->execute([$start_date, $end_date]);
$bookings = $stmt->fetchAll();

// Group bookings by date
$bookings_by_date = [];
foreach ($bookings as $booking) {
    $date = $booking['booking_date'];
    if (!isset($bookings_by_date[$date])) {
        $bookings_by_date[$date] = [];
    }
    $bookings_by_date[$date][] = $booking;
}

// Get all programmes for dropdown
$stmt = $pdo->query("SELECT id, title FROM programmes ORDER BY title");
$programmes = $stmt->fetchAll();

$facility_types = [
    'conference_room' => 'Conference Room',
    'training_room' => 'Training Room',
    'breakout_area' => 'Breakout Area'
];

// Calendar navigation
$prev_month = $current_month == 1 ? 12 : $current_month - 1;
$prev_year = $current_month == 1 ? $current_year - 1 : $current_year;
$next_month = $current_month == 12 ? 1 : $current_month + 1;
$next_year = $current_month == 12 ? $current_year + 1 : $current_year;

// Get calendar days
$first_day = mktime(0, 0, 0, $current_month, 1, $current_year);
$last_day = mktime(0, 0, 0, $current_month + 1, 0, $current_year);
$days_in_month = date('t', $first_day);
$first_day_of_week = date('w', $first_day);

$month_names = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facility Calendar - XpressPoint Admin</title>
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
    <link rel="stylesheet" href="css/facility-calendar-enhanced.css?v=20260930-4">
    
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Facility Calendar', 'fas fa-calendar-alt'); ?>
    
    <div class="admin-container">
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('facility_calendar'); ?>
        
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
        
        <!-- Calendar Header -->
        <div class="calendar-header">
            <h2 class="calendar-title">
                <i class="fas fa-calendar-alt"></i>
                Facility Booking Calendar
            </h2>
            
            <div class="calendar-nav">
                <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="nav-btn">
                    <i class="fas fa-chevron-left"></i>
                    Previous
                </a>
                
                <div class="current-month">
                    <?php echo $month_names[$current_month] . ' ' . $current_year; ?>
                </div>
                
                <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="nav-btn">
                    Next
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($bookings); ?></div>
                <div class="stat-label">Total Bookings This Month</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count(array_filter($bookings, function($b) { return $b['programme_id']; })); ?></div>
                <div class="stat-label">Programme Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count(array_filter($bookings, function($b) { return !$b['programme_id']; })); ?></div>
                <div class="stat-label">General Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count(array_unique(array_column($bookings, 'booking_date'))); ?></div>
                <div class="stat-label">Days with Bookings</div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="programme-facility-management.php" class="quick-action-btn">
                <i class="fas fa-list"></i>
                List View
            </a>
            <a href="bookings.php" class="quick-action-btn">
                <i class="fas fa-calendar-alt"></i>
                All Bookings
            </a>
            <a href="programmes.php" class="quick-action-btn primary">
                <i class="fas fa-plus"></i>
                Add Programme
            </a>
        </div>
        
        <!-- Calendar -->
        <div class="calendar-grid">
            <!-- Weekday Headers -->
            <div class="calendar-weekdays">
                <div class="weekday">Sunday</div>
                <div class="weekday">Monday</div>
                <div class="weekday">Tuesday</div>
                <div class="weekday">Wednesday</div>
                <div class="weekday">Thursday</div>
                <div class="weekday">Friday</div>
                <div class="weekday">Saturday</div>
            </div>
            
            <!-- Calendar Days -->
            <div class="calendar-days">
                <?php
                // Add empty cells for days before the first day of the month
                for ($i = 0; $i < $first_day_of_week; $i++) {
                    $prev_month_day = date('j', mktime(0, 0, 0, $current_month, 1 - ($first_day_of_week - $i), $current_year));
                    echo '<div class="calendar-day other-month">';
                    echo '<div class="day-number">' . $prev_month_day . '</div>';
                    echo '</div>';
                }
                
                // Add days of the current month
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $current_date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);
                    $is_today = $current_date === date('Y-m-d');
                    $day_class = $is_today ? 'today' : '';
                    
                    echo '<div class="calendar-day ' . $day_class . '">';
                    echo '<div class="day-number">' . $day . '</div>';
                    
                    if (isset($bookings_by_date[$current_date])) {
                        echo '<div class="day-bookings">';
                        foreach ($bookings_by_date[$current_date] as $booking) {
                            $facility_class = $booking['programme_id'] ? 'programme' : $booking['facility_type'];
                            $facility_name = $facility_types[$booking['facility_type']] ?? $booking['facility_type'];
                            $time_range = date('g:i A', strtotime($booking['start_time'])) . ' - ' . date('g:i A', strtotime($booking['end_time']));
                            $title = $booking['programme_title'] ?: $booking['customer_name'];
                            
                            // Determine the appropriate link based on booking type
                            if ($booking['programme_id']) {
                                // Programme booking - link to programme details
                                $link = "edit-programme.php?id=" . $booking['programme_id'];
                                $title_text = "Programme: " . htmlspecialchars($title);
                            } else {
                                // General facility booking - link to booking details
                                $link = "booking-details.php?id=" . $booking['id'];
                                $title_text = htmlspecialchars($title);
                            }
                            
                            echo '<a href="' . $link . '" class="booking-item ' . $facility_class . '" title="Click to view details">';
                            echo '<div style="display: flex; align-items: center; gap: 0.25rem;">';
                            echo '<i class="fas fa-' . ($booking['programme_id'] ? 'graduation-cap' : 'calendar-alt') . '" style="font-size: 0.6rem;"></i>';
                            echo '<span>' . $title_text . '</span>';
                            echo '</div>';
                            echo '<div style="font-size: 0.7rem; opacity: 0.8;">' . $time_range . '</div>';
                            echo '</a>';
                        }
                        echo '</div>';
                    }
                    
                    echo '</div>';
                }
                
                // Add empty cells for days after the last day of the month
                $remaining_days = 42 - ($first_day_of_week + $days_in_month);
                for ($i = 1; $i <= $remaining_days; $i++) {
                    echo '<div class="calendar-day other-month">';
                    echo '<div class="day-number">' . $i . '</div>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
        
        <!-- Legend -->
        <div class="facility-legend">
            <div class="legend-item">
                <div class="legend-color" style="background: #111111;"></div>
                <span>General Bookings (Click → Booking Details)</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #10b981;"></div>
                <span>Conference Room</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #f59e0b;"></div>
                <span>Training Room</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #111111;"></div>
                <span>Breakout Area</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #ef4444;"></div>
                <span>Programme Bookings (Click → Programme Details)</span>
            </div>
        </div>
    </div>
    
    <!-- Booking Details Modal -->
    <div id="bookingModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Booking Details</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div id="bookingDetails">
                <!-- Booking details will be loaded here -->
            </div>
        </div>
    </div>
    
    <script>
        function showBookingDetails(bookingId) {
            fetch(`?ajax=booking_details&booking_id=${bookingId}`)
                .then(response => response.json())
                .then(booking => {
                    const modal = document.getElementById('bookingModal');
                    const details = document.getElementById('bookingDetails');
                    
                    details.innerHTML = `
                        <div style="margin-bottom: 1rem;">
                            <strong>Booking ID:</strong> #${booking.id}
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Customer:</strong> ${booking.customer_name}
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Email:</strong> ${booking.customer_email}
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Facility:</strong> ${booking.facility_type.replace('_', ' ').toUpperCase()}
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Date:</strong> ${new Date(booking.booking_date).toLocaleDateString()}
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Time:</strong> ${booking.start_time} - ${booking.end_time}
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Status:</strong> <span style="color: ${booking.status === 'confirmed' ? '#10b981' : '#f59e0b'}">${booking.status.toUpperCase()}</span>
                        </div>
                        ${booking.programme_title ? `
                        <div style="margin-bottom: 1rem;">
                            <strong>Programme:</strong> ${booking.programme_title}
                        </div>
                        ` : ''}
                        ${booking.purpose ? `
                        <div style="margin-bottom: 1rem;">
                            <strong>Purpose:</strong> ${booking.purpose}
                        </div>
                        ` : ''}
                        <div style="margin-top: 1rem;">
                            <a href="booking-details.php?id=${booking.id}" class="btn btn-primary" style="margin-right: 0.5rem;">
                                <i class="fas fa-eye"></i>
                                View Full Details
                            </a>
                            <a href="bookings.php" class="btn btn-secondary">
                                <i class="fas fa-list"></i>
                                All Bookings
                            </a>
                        </div>
                    `;
                    
                    modal.style.display = 'block';
                })
                .catch(error => {
                    console.error('Error fetching booking details:', error);
                    xpmAlert('Booking details could not be loaded.');
                });
        }
        
        function closeModal() {
            document.getElementById('bookingModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('bookingModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
    <script src="../assets/js/motion.js?v=20261002-10"></script>
</body>
</html>
