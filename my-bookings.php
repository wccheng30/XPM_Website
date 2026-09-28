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
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        .bookings-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 3rem 2rem;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            padding-top: 120px;
        }
        
        .bookings-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .bookings-header h1 {
            font-size: 3rem;
            font-weight: 900;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .bookings-header p {
            font-size: 1.2rem;
            color: #64748b;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .lookup-form {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 3rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .lookup-form h2 {
            text-align: center;
            margin-bottom: 1.5rem;
            color: #1e293b;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }
        
        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .bookings-list {
            display: grid;
            gap: 1.5rem;
        }
        
        .booking-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }
        
        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .booking-id {
            font-weight: 700;
            color: #667eea;
            font-size: 1.1rem;
        }
        
        .booking-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .booking-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 0.25rem;
        }
        
        .detail-value {
            font-weight: 600;
            color: #1e293b;
        }
        
        .booking-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .btn-cancel {
            background: #ef4444;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-cancel:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }
        
        .btn-cancel:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }
        
        .no-bookings {
            text-align: center;
            padding: 3rem;
            color: #64748b;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 2rem;
            padding: 0.5rem 1rem;
            background: white;
            border-radius: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            transform: translateX(-5px);
            text-decoration: none;
        }
        
        @media (max-width: 768px) {
            .bookings-container {
                padding: 2rem 1rem;
                padding-top: 100px;
            }
            
            .bookings-header h1 {
                font-size: 2rem;
            }
            
            .booking-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .booking-details {
                grid-template-columns: 1fr;
            }
            
            .booking-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar" id="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <a href="index.html">
                    <img src="assets/images/XPMLogo.png" alt="Xpress Point Management Services" class="logo-img">
                </a>
            </div>
            
            <div class="nav-menu" id="nav-menu">
                <a href="index.html#hero" class="nav-link">Home</a>
                <a href="index.html#about" class="nav-link">About</a>
                <a href="index.html#initiator" class="nav-link">Founder</a>
                <a href="index.html#services" class="nav-link">Services</a>
                <a href="index.html#facilities" class="nav-link">Facilities</a>
                <a href="index.html#testimonials" class="nav-link">Testimonials</a>
                <a href="programmes.php" class="nav-link">Programmes</a>
                <a href="jobs.php" class="nav-link">Careers</a>
                <a href="index.html#portfolio" class="nav-link">Portfolio</a>
                <a href="index.html#contact" class="nav-link">Contact</a>
            </div>
            
            <div class="nav-toggle" id="nav-toggle">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
        </div>
    </nav>

    <div class="bookings-container">
        <a href="facility-booking.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Back to Booking
        </a>
        
        <div class="bookings-header">
            <h1>My Bookings</h1>
            <p>View and manage your facility bookings</p>
        </div>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!isset($bookings)): ?>
            <div class="lookup-form">
                <h2>Lookup Your Bookings</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="lookup_email">Email Address</label>
                        <input type="email" id="lookup_email" name="lookup_email" required placeholder="Enter your email address">
                    </div>
                    <button type="submit" class="btn">
                        <i class="fas fa-search"></i> Find My Bookings
                    </button>
                </form>
            </div>
        <?php else: ?>
            <?php if (empty($bookings)): ?>
                <div class="no-bookings">
                    <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem; color: #cbd5e1;"></i>
                    <h3>No bookings found</h3>
                    <p>No bookings found for this email address.</p>
                    <a href="facility-booking.php" class="btn" style="display: inline-block; width: auto; margin-top: 1rem;">
                        Make a New Booking
                    </a>
                </div>
            <?php else: ?>
                <div class="bookings-list">
                    <?php foreach ($bookings as $booking): ?>
                        <div class="booking-card">
                            <div class="booking-header">
                                <span class="booking-id">Booking #<?php echo $booking['id']; ?></span>
                                <span class="booking-status" style="background: <?php echo $status_colors[$booking['status']]; ?>; color: white;">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </div>
                            
                            <div class="booking-details">
                                <div class="detail-item">
                                    <span class="detail-label">Facility</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($facility_types[$booking['facility_type']] ?? $booking['facility_type']); ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Date</span>
                                    <span class="detail-value"><?php echo date('M j, Y', strtotime($booking['booking_date'])); ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Time</span>
                                    <span class="detail-value"><?php echo date('g:i A', strtotime($booking['start_time'])); ?> - <?php echo date('g:i A', strtotime($booking['end_time'])); ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Attendees</span>
                                    <span class="detail-value"><?php echo $booking['attendees_count']; ?> people</span>
                                </div>
                                
                                <?php if ($booking['company']): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Company</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($booking['company']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Booked On</span>
                                    <span class="detail-value"><?php echo date('M j, Y g:i A', strtotime($booking['created_at'])); ?></span>
                                </div>
                            </div>
                            
                            <?php if ($booking['purpose']): ?>
                                <div class="detail-item" style="margin-bottom: 1rem;">
                                    <span class="detail-label">Purpose</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['purpose']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($booking['special_requirements']): ?>
                                <div class="detail-item" style="margin-bottom: 1rem;">
                                    <span class="detail-label">Special Requirements</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['special_requirements']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="booking-actions">
                                <?php if ($booking['status'] === 'pending'): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                        <input type="hidden" name="customer_email" value="<?php echo htmlspecialchars($booking['customer_email']); ?>">
                                        <button type="submit" name="cancel_booking" class="btn-cancel">
                                            <i class="fas fa-times"></i> Cancel Booking
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <a href="mailto:hr@xpresspointservices.com?subject=Booking Inquiry - #<?php echo $booking['id']; ?>" class="btn-cancel" style="background: #3b82f6; text-decoration: none;">
                                    <i class="fas fa-envelope"></i> Contact Support
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="text-align: center; margin-top: 2rem;">
                    <a href="my-bookings.php" class="btn" style="display: inline-block; width: auto;">
                        <i class="fas fa-refresh"></i> Lookup Different Email
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="footer-logo">
                        <img src="assets/images/XPMLogo.png" alt="Xpress Point Management Services Logo">
                    </div>
                    <p>Empowering organizations with comprehensive HR services and training solutions since 2010.</p>
                    <div class="social-links">
                        <a href="https://www.linkedin.com/in/icol-chua-18248623/" target="_blank" rel="noopener noreferrer"><i class="fab fa-linkedin"></i></a>
                        <a href="https://www.facebook.com/XpressPointManagementServices" target="_blank" rel="noopener noreferrer"><i class="fab fa-facebook"></i></a>
                        <a href="https://www.instagram.com/xpresspoint_mlk/" target="_blank" rel="noopener noreferrer"><i class="fab fa-instagram"></i></a>
                        <a href="https://api.whatsapp.com/send?phone=601120816113&text&context=Afe5aPTzugSnNb11RVXu8gplTXAka-fQ3OSnoIHLoEqmW6cDcc2-nda4uIC8TFMPge3mZXDvEk9uqftJnydY7o4PQeFu4teMCQA9M_MxuKCiQ09tgidUXLf8Za7t5li-eJv1K9QAXOxfmXK4qCobgVak8Q&source&app=facebook" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="index.html#about">About Us</a></li>
                        <li><a href="index.html#services">Services</a></li>
                        <li><a href="programmes.php">Training Programmes</a></li>
                        <li><a href="jobs.php">Careers</a></li>
                        <li><a href="index.html#contact">Contact</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Why Choose Us</h4>
                    <ul>
                        <li><a href="index.html#about">15+ Years Experience</a></li>
                        <li><a href="index.html#services">Certified Professionals</a></li>
                        <li><a href="index.html#services">Custom Solutions</a></li>
                        <li><a href="index.html#testimonials">Proven Results</a></li>
                        <li><a href="index.html#contact">Ongoing Support</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Contact Info</h4>
                    <ul>
                        <li><i class="fas fa-map-marker-alt"></i> No.6, Jalan Berkat 12, Taman Malim Jaya, 75250 Melaka</li>
                        <li><i class="fas fa-phone"></i> 016 772 1113</li>
                        <li><i class="fas fa-envelope"></i> hr@xpresspointservices.com</li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2024 Xpress Point Management Services. All rights reserved.</p>
                <div class="footer-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                    <a href="#">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script src="assets/js/main.js"></script>
</body>
</html>
