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
    <link rel="stylesheet" href="admin-styles.css">
    
    <style>
        .calendar-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .calendar-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        }
        
        .calendar-nav {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .nav-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .nav-btn:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }
        
        .current-month {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            min-width: 200px;
            text-align: center;
        }
        
        .calendar-grid {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .weekday {
            padding: 1rem;
            text-align: center;
            font-weight: 600;
            color: #64748b;
            border-right: 1px solid #e2e8f0;
        }
        
        .weekday:last-child {
            border-right: none;
        }
        
        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
        }
        
        .calendar-day {
            min-height: 120px;
            padding: 0.75rem;
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            position: relative;
            background: white;
            transition: all 0.2s;
        }
        
        .calendar-day:hover {
            background: #f8fafc;
        }
        
        .calendar-day:nth-child(7n) {
            border-right: none;
        }
        
        .day-number {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .day-bookings {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .booking-item {
            background: #3b82f6;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            position: relative;
            text-decoration: none;
            display: block;
        }
        
        .booking-item:hover {
            transform: scale(1.02);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .booking-item.conference_room {
            background: #10b981;
        }
        
        .booking-item.training_room {
            background: #f59e0b;
        }
        
        .booking-item.breakout_area {
            background: #8b5cf6;
        }
        
        .booking-item.programme {
            background: #ef4444;
        }
        
        .other-month {
            background: #f8fafc;
            color: #9ca3af;
        }
        
        .today {
            background: #fef3c7;
        }
        
        .today .day-number {
            color: #92400e;
            font-weight: 700;
        }
        
        .booking-tooltip {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            display: none;
            padding: 1rem;
            margin-top: 0.5rem;
        }
        
        .booking-tooltip.show {
            display: block;
        }
        
        .tooltip-title {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }
        
        .tooltip-details {
            font-size: 0.875rem;
            color: #64748b;
            line-height: 1.4;
        }
        
        .facility-legend {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: #64748b;
        }
        
        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }
        
        .quick-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .quick-action-btn {
            padding: 0.5rem 1rem;
            background: #6b7280;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .quick-action-btn:hover {
            background: #4b5563;
        }
        
        .quick-action-btn.primary {
            background: #3b82f6;
        }
        
        .quick-action-btn.primary:hover {
            background: #2563eb;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: #64748b;
            margin-top: 0.25rem;
        }
        
        @media (max-width: 768px) {
            .calendar-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .calendar-nav {
                width: 100%;
                justify-content: space-between;
            }
            
            .calendar-day {
                min-height: 80px;
                padding: 0.5rem;
            }
            
            .booking-item {
                font-size: 0.7rem;
                padding: 0.2rem 0.4rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="admin-header">
        <h1>
            <i class="fas fa-calendar-alt"></i>
            Facility Booking Calendar
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
                <div class="legend-color" style="background: #3b82f6;"></div>
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
                <div class="legend-color" style="background: #8b5cf6;"></div>
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
                    alert('Error loading booking details');
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
</body>
</html>
