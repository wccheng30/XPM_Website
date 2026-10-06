<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Include database connection
require_once '../includes/database.php';
require_once '../includes/email-notifications.php';

$pdo = get_db_connection();

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $booking_id = (int)$_POST['booking_id'];
    $action = $_POST['action'];
    
    try {
        $emailNotifications = new EmailNotifications();
        
        switch ($action) {
            case 'confirm':
                $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed', confirmed_by = ?, confirmed_at = NOW() WHERE id = ?");
                $stmt->execute([$_SESSION['admin_user_id'] ?? 1, $booking_id]);
                $emailNotifications->sendStatusUpdate($booking_id, 'confirmed');
                break;
            case 'cancel':
                $cancellation_reason = $_POST['cancellation_reason'] ?? '';
                $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', cancellation_reason = ? WHERE id = ?");
                $stmt->execute([$cancellation_reason, $booking_id]);
                $emailNotifications->sendStatusUpdate($booking_id, 'cancelled');
                break;
            case 'complete':
                $stmt = $pdo->prepare("UPDATE bookings SET status = 'completed' WHERE id = ?");
                $stmt->execute([$booking_id]);
                $emailNotifications->sendStatusUpdate($booking_id, 'completed');
                break;
        }
        $success_message = "Booking updated successfully! Customer has been notified via email.";
    } catch (PDOException $e) {
        $error_message = "Error updating booking.";
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$facility_filter = $_GET['facility'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "b.status = ?";
    $params[] = $status_filter;
}

if ($facility_filter) {
    $where_conditions[] = "b.facility_type = ?";
    $params[] = $facility_filter;
}



if ($search) {
    $where_conditions[] = "(b.customer_name LIKE ? OR b.customer_email LIKE ? OR b.company LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get bookings
$bookings = [];
if ($pdo) {
    try {
        $query = "
            SELECT b.*, u.username as confirmed_by_name
            FROM bookings b
            LEFT JOIN users u ON b.confirmed_by = u.id
            $where_clause
            ORDER BY b.booking_date DESC, b.start_time ASC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $bookings = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error_message = "Error loading bookings.";
    }
}

// Get statistics
$stats = [];
if ($pdo) {
    try {
        // Total bookings
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM bookings");
        $stats['total'] = $stmt->fetch()['total'];
        
        // Pending bookings
        $stmt = $pdo->query("SELECT COUNT(*) as pending FROM bookings WHERE status = 'pending'");
        $stats['pending'] = $stmt->fetch()['pending'];
        
        // Confirmed bookings
        $stmt = $pdo->query("SELECT COUNT(*) as confirmed FROM bookings WHERE status = 'confirmed'");
        $stats['confirmed'] = $stmt->fetch()['confirmed'];
        
        // Today's bookings
        $stmt = $pdo->query("SELECT COUNT(*) as today FROM bookings WHERE booking_date = CURDATE()");
        $stats['today'] = $stmt->fetch()['today'];
    } catch (PDOException $e) {
        // Handle error silently
    }
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
    <title>Bookings Management - XpressPoint Admin</title>
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
    <link rel="stylesheet" href="css/bookings.css?v=20260930-4">
    
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Facility Bookings', 'fas fa-calendar-alt'); ?>
    
    <div class="admin-container">
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('bookings'); ?>
    
        <div class="page-header">
            <h1><i class="fas fa-calendar-alt"></i> Bookings Management</h1>
            <p>Manage facility bookings and reservations</p>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['confirmed'] ?? 0; ?></div>
                <div class="stat-label">Confirmed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['today'] ?? 0; ?></div>
                <div class="stat-label">Today's Bookings</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <form method="GET">
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, email, or company">
                </div>
                
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">All Status</option>
                        <?php foreach ($status_options as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo $status_filter === $value ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="facility">Facility</label>
                    <select id="facility" name="facility">
                        <option value="">All Facilities</option>
                        <?php foreach ($facility_types as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo $facility_filter === $value ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                

                
                <div class="filter-actions">
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="bookings.php" class="btn-clear">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Bookings List -->
        <div class="bookings-list">
            <?php if (empty($bookings)): ?>
                <div class="no-bookings">
                    <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <h3>No bookings found</h3>
                    <p>No bookings match your current filters.</p>
                </div>
            <?php else: ?>
                <?php foreach ($bookings as $booking): ?>
                    <div class="booking-card">
                        <div class="booking-header">
                            <span class="booking-id">#<?php echo $booking['id']; ?></span>
                            <span class="booking-status status-<?php echo $booking['status']; ?>">
                                <?php echo ucfirst($booking['status']); ?>
                            </span>
                        </div>
                        
                        <div class="booking-details">
                            <div class="detail-group">
                                <span class="detail-label">Customer</span>
                                <span class="detail-value"><?php echo htmlspecialchars($booking['customer_name']); ?></span>
                            </div>
                            
                            <div class="detail-group">
                                <span class="detail-label">Email</span>
                                <span class="detail-value"><?php echo htmlspecialchars($booking['customer_email']); ?></span>
                            </div>
                            
                            <div class="detail-group">
                                <span class="detail-label">Facility</span>
                                <span class="detail-value"><?php echo htmlspecialchars($facility_types[$booking['facility_type']] ?? $booking['facility_type']); ?></span>
                            </div>
                            
                            <div class="detail-group">
                                <span class="detail-label">Date & Time</span>
                                <span class="detail-value">
                                    <?php echo date('M j, Y', strtotime($booking['booking_date'])); ?><br>
                                    <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - 
                                    <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                                </span>
                            </div>
                            
                            <div class="detail-group">
                                <span class="detail-label">Attendees</span>
                                <span class="detail-value"><?php echo $booking['attendees_count']; ?> people</span>
                            </div>
                            
                            <div class="detail-group">
                                <span class="detail-label">Company</span>
                                <span class="detail-value"><?php echo htmlspecialchars($booking['company'] ?: 'Not specified'); ?></span>
                            </div>
                        </div>
                        
                        <?php if ($booking['purpose']): ?>
                            <div class="detail-group" style="margin-bottom: 1rem;">
                                <span class="detail-label">Purpose</span>
                                <span class="detail-value"><?php echo htmlspecialchars($booking['purpose']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="booking-actions">
                            <a href="booking-details.php?id=<?php echo $booking['id']; ?>" class="btn-action btn-view">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            
                            <?php if ($booking['status'] === 'pending'): ?>
                                <button onclick="confirmBooking(<?php echo $booking['id']; ?>)" class="btn-action btn-confirm">
                                    <i class="fas fa-check"></i> Confirm
                                </button>
                                <button onclick="cancelBooking(<?php echo $booking['id']; ?>)" class="btn-action btn-cancel">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            <?php elseif ($booking['status'] === 'confirmed'): ?>
                                <button onclick="completeBooking(<?php echo $booking['id']; ?>)" class="btn-action btn-complete">
                                    <i class="fas fa-check-double"></i> Mark Complete
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Cancel Booking Modal -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Cancel Booking</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="booking_id" id="cancelBookingId">
                
                <div class="form-group">
                    <label for="cancellation_reason">Cancellation Reason</label>
                    <textarea name="cancellation_reason" id="cancellation_reason" rows="3" placeholder="Please provide a reason for cancellation..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-danger">Cancel Booking</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Close</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function confirmBooking(bookingId) {
            if (confirm('Are you sure you want to confirm this booking?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="confirm">
                    <input type="hidden" name="booking_id" value="${bookingId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function cancelBooking(bookingId) {
            document.getElementById('cancelBookingId').value = bookingId;
            document.getElementById('cancelModal').style.display = 'block';
        }
        
        function completeBooking(bookingId) {
            if (confirm('Are you sure you want to mark this booking as completed?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="complete">
                    <input type="hidden" name="booking_id" value="${bookingId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function closeModal() {
            document.getElementById('cancelModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('cancelModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
        
        // Dark mode toggle functionality
        function toggleTheme() {
            const body = document.body;
            const themeIcon = document.getElementById('theme-icon');
            const themeText = document.getElementById('theme-text');
            
            if (body.classList.contains('dark-mode')) {
                body.classList.remove('dark-mode');
                localStorage.setItem('theme', 'light');
                themeIcon.className = 'fas fa-moon';
                themeText.textContent = 'Dark Mode';
            } else {
                body.classList.add('dark-mode');
                localStorage.setItem('theme', 'dark');
                themeIcon.className = 'fas fa-sun';
                themeText.textContent = 'Light Mode';
            }
        }
        
        // Load saved theme on page load
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme');
            const themeIcon = document.getElementById('theme-icon');
            const themeText = document.getElementById('theme-text');
            
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-mode');
                themeIcon.className = 'fas fa-sun';
                themeText.textContent = 'Light Mode';
            }
        });
    </script>
    <script src="../assets/js/motion.js?v=20261002-10"></script>
</body>
</html>
