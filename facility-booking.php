<?php
// Set timezone for consistent date handling
date_default_timezone_set('Asia/Kuala_Lumpur');

// Include shared database connection
require_once 'includes/database.php';
require_once 'includes/phone-field.php';
require_once 'includes/email-notifications.php';

$pdo = get_db_connection();
$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? '');
    $customer_phone = combine_phone($_POST['customer_phone_code'] ?? '+60', $_POST['customer_phone'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $facility_type = $_POST['facility_type'] ?? '';
    $booking_date = $_POST['booking_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $attendees_count = intval($_POST['attendees_count'] ?? 1);
    $purpose = trim($_POST['purpose'] ?? '');
    $special_requirements = trim($_POST['special_requirements'] ?? '');

    // Validation
    if (empty($customer_name) || empty($customer_email) || empty($facility_type) || 
        empty($booking_date) || empty($start_time) || empty($end_time)) {
        $error_message = "Please fill in all required fields.";
    } elseif (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } elseif (strtotime($end_time) <= strtotime($start_time)) {
        $error_message = "End time must be after start time.";
    } elseif (strtotime($booking_date . ' 00:00:00') < strtotime(date('Y-m-d') . ' 00:00:00')) {
        // Debug: Log the comparison values
        error_log("Booking date: " . $booking_date . " (timestamp: " . strtotime($booking_date . ' 00:00:00') . ")");
        error_log("Current date: " . date('Y-m-d') . " (timestamp: " . strtotime(date('Y-m-d') . ' 00:00:00') . ")");
        $error_message = "Booking date cannot be in the past.";
    } else {
        try {
            // Check for booking conflicts
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as conflict_count 
                FROM bookings 
                WHERE facility_type = ? 
                AND booking_date = ? 
                AND status IN ('pending', 'confirmed')
                AND (
                    (start_time <= ? AND end_time > ?) OR
                    (start_time < ? AND end_time >= ?) OR
                    (start_time >= ? AND end_time <= ?)
                )
            ");
            
            $stmt->execute([
                $facility_type, 
                $booking_date, 
                $start_time, $start_time, 
                $end_time, $end_time, 
                $start_time, $end_time
            ]);
            
            $conflict = $stmt->fetch();
            
            if ($conflict['conflict_count'] > 0) {
                $error_message = "This facility is already booked for the selected time. Please choose a different time or facility.";
            } else {
                // Insert booking
                $stmt = $pdo->prepare("
                    INSERT INTO bookings 
                    (customer_name, customer_email, customer_phone, company, facility_type, 
                     booking_date, start_time, end_time, attendees_count, purpose, special_requirements) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $customer_name,
                    $customer_email,
                    $customer_phone,
                    $company,
                    $facility_type,
                    $booking_date,
                    $start_time,
                    $end_time,
                    $attendees_count,
                    $purpose,
                    $special_requirements
                ]);
                
                $booking_id = $pdo->lastInsertId();
                
                // Send confirmation email
                $emailNotifications = new EmailNotifications();
                $emailSent = $emailNotifications->sendBookingConfirmation($booking_id);
                
                $success_message = "Booking submitted successfully! We'll confirm your reservation within 2 hours.";
                if ($emailSent) {
                    $success_message .= " A confirmation email has been sent to your email address.";
                }
                
                // Clear form data
                $_POST = [];
            }
            
        } catch (PDOException $e) {
            $error_message = "Error submitting booking. Please try again.";
        }
    }
}

 // Get facility details for display
 $facilities = [
     'conference_room' => [
         'name' => 'Conference Room',
         'capacity' => 'Up to 9 people',
         'features' => ['Presentation equipment', 'Comfortable seating', 'Refreshment area', 'Free Wi-Fi'],
         'description' => 'Professional conference room with comfortable seating, presentation equipment, and flexible layouts for small meetings.'
     ],
     'training_room' => [
         'name' => 'Training Room',
         'capacity' => 'Up to 40+ people',
         'features' => ['Presentation equipment', 'Group training sessions', 'Refreshment area', 'Free Wi-Fi'],
         'description' => 'Large dedicated training space with comfortable seating and presentation equipment for HR workshops and large group sessions.'
     ],
     'breakout_area' => [
         'name' => 'Breakout Areas',
         'capacity' => 'Up to 5 people',
         'features' => ['Comfortable seating', 'Refreshment areas', 'Discussion spaces', 'Free Wi-Fi access'],
         'description' => 'Intimate lounge areas and meeting spaces for informal discussions and small group networking.'
     ]
 ];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facility Booking - XpressPoint HR Training</title>
    <meta name="description" content="Book Xpress Point's conference room, training room or breakout areas online.">
    <?php $xpm_page_styles = ['pages/forms.css', 'pages/facility-booking.css']; include 'partials/site-head.php'; ?>
</head>
<body>
    <?php $xpm_active = 'facilities'; include 'partials/site-nav.php'; ?>

    <main id="main">
        <header class="page-hero">
            <div class="container">
                <nav class="breadcrumb" aria-label="Breadcrumb" data-reveal="fade">
                    <a href="index.html">Home</a><i class="fas fa-chevron-right"></i>
                    <a href="index.html#facilities">Facilities</a><i class="fas fa-chevron-right"></i>
                    <span>Book a facility</span>
                </nav>
                <div class="booking-hero">
                    <div>
                        <span class="eyebrow" data-reveal><i class="fas fa-building"></i> Facility booking</span>
                        <h1 data-split>Book our facilities</h1>
                        <p class="lead" data-reveal>Reserve our training facilities for your next meeting, training session, or event.</p>
                    </div>
                    <div class="booking-hero-actions" data-reveal>
                        <a href="index.html#facilities" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Back to Facilities
                        </a>
                        <a href="my-bookings.php" class="btn btn-primary">
                            <i class="fas fa-calendar-check"></i> View My Bookings
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <section class="section form-page booking-page">
            <div class="container">
                <div class="booking-container">
                    <ol class="booking-progress" aria-label="Booking steps">
                        <li class="progress-step active" data-step="1">
                            <span class="progress-dot"><i class="fas fa-check"></i></span>
                            <span>Facility</span>
                        </li>
                        <li class="progress-step" data-step="2">
                            <span class="progress-dot"><i class="fas fa-check"></i></span>
                            <span>Date &amp; time</span>
                        </li>
                        <li class="progress-step" data-step="3">
                            <span class="progress-dot"><i class="fas fa-check"></i></span>
                            <span>Your details</span>
                        </li>
                        <li class="progress-step" data-step="4">
                            <span class="progress-dot"><i class="fas fa-check"></i></span>
                            <span>Confirm</span>
                        </li>
                    </ol>

                    <?php if ($error_message): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <span><?php echo htmlspecialchars($error_message); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <span><?php echo htmlspecialchars($success_message); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="booking-wizard form" id="bookingWizard">
                        <!-- Step 1: Facility Selection -->
                        <div class="wizard-step active" id="step1">
                            <div class="step-content">
                                <div class="step-head">
                                    <span class="step-no">1</span>
                                    <div>
                                        <h2>Select your facility</h2>
                                        <p>Choose the facility that best suits your needs.</p>
                                    </div>
                                </div>

                                <div class="facilities-grid">
                                    <?php
                                    $facilityOrder = ['conference_room', 'breakout_area', 'training_room'];
                                    foreach ($facilityOrder as $key):
                                        $facility = $facilities[$key];
                                        switch ($key) {
                                            case 'conference_room':
                                                $imageFile = "conference-room.jpg";
                                                break;
                                            case 'training_room':
                                                $imageFile = "simulation-center.jpg";
                                                break;
                                            case 'breakout_area':
                                                $imageFile = "breakout-area.jpg";
                                                break;
                                            default:
                                                $imageFile = "conference-room.jpg";
                                        }
                                    ?>
                                        <div class="facility-card" onclick="selectFacility('<?php echo $key; ?>')" data-facility="<?php echo $key; ?>" role="button" tabindex="0">
                                            <div class="facility-image">
                                                <img src="assets/images/<?php echo $imageFile; ?>" alt="<?php echo htmlspecialchars($facility['name']); ?>" onerror="this.src='assets/images/conference-room.jpg'">
                                                <div class="facility-overlay">
                                                    <i class="fas fa-check"></i>
                                                </div>
                                            </div>
                                            <div class="facility-info">
                                                <div class="facility-capacity"><i class="fas fa-users"></i> <?php echo htmlspecialchars($facility['capacity']); ?></div>
                                                <h3><?php echo htmlspecialchars($facility['name']); ?></h3>
                                                <p><?php echo htmlspecialchars($facility['description']); ?></p>
                                                <ul class="facility-features">
                                                    <?php foreach ($facility['features'] as $feature): ?>
                                                        <li><?php echo htmlspecialchars($feature); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <input type="hidden" name="facility_type" id="facility_type" value="<?php echo htmlspecialchars($_POST['facility_type'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <!-- Step 2: Date & Time Selection -->
                        <div class="wizard-step" id="step2">
                            <div class="step-content">
                                <div class="step-head">
                                    <span class="step-no">2</span>
                                    <div>
                                        <h2>Select date &amp; time</h2>
                                        <p>Choose your preferred date and time slot.</p>
                                    </div>
                                </div>

                                <div class="datetime-selection">
                                    <div class="calendar-section">
                                        <h3>Select date</h3>
                                        <div class="calendar-container">
                                            <div class="calendar-header">
                                                <button type="button" class="calendar-nav" onclick="changeMonth(-1)" aria-label="Previous month">
                                                    <i class="fas fa-chevron-left"></i>
                                                </button>
                                                <h4 id="currentMonth"></h4>
                                                <button type="button" class="calendar-nav" onclick="changeMonth(1)" aria-label="Next month">
                                                    <i class="fas fa-chevron-right"></i>
                                                </button>
                                            </div>
                                            <div class="calendar-grid" id="calendarGrid">
                                                <!-- Calendar will be populated by JavaScript -->
                                            </div>
                                            <div class="calendar-legend">
                                                <span><i class="legend-dot is-available"></i> Available</span>
                                                <span><i class="legend-dot is-booked"></i> Has bookings</span>
                                                <span><i class="legend-dot is-selected"></i> Selected</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="timeslot-section">
                                        <h3>Preferred time</h3>
                                        <div class="time-input-container">
                                            <div class="time-selection-method">
                                                <div class="method-tabs">
                                                    <button type="button" class="method-tab active" onclick="switchTimeMethod('preset')">
                                                        <i class="fas fa-clock"></i> Quick Select
                                                    </button>
                                                    <button type="button" class="method-tab" onclick="switchTimeMethod('custom')">
                                                        <i class="fas fa-pen"></i> Custom Time
                                                    </button>
                                                </div>

                                                <div class="preset-times" id="presetTimes">
                                                    <h4>Popular time slots</h4>
                                                    <div class="time-slots-grid">
                                                        <div class="time-slot-preset" onclick="selectPresetTime('09:00', '10:00')">
                                                            <div class="time-display">9:00 AM - 10:00 AM</div>
                                                            <div class="duration">1 hour</div>
                                                        </div>
                                                        <div class="time-slot-preset" onclick="selectPresetTime('09:00', '11:00')">
                                                            <div class="time-display">9:00 AM - 11:00 AM</div>
                                                            <div class="duration">2 hours</div>
                                                        </div>
                                                        <div class="time-slot-preset" onclick="selectPresetTime('10:00', '12:00')">
                                                            <div class="time-display">10:00 AM - 12:00 PM</div>
                                                            <div class="duration">2 hours</div>
                                                        </div>
                                                        <div class="time-slot-preset" onclick="selectPresetTime('13:00', '15:00')">
                                                            <div class="time-display">1:00 PM - 3:00 PM</div>
                                                            <div class="duration">2 hours</div>
                                                        </div>
                                                        <div class="time-slot-preset" onclick="selectPresetTime('14:00', '16:00')">
                                                            <div class="time-display">2:00 PM - 4:00 PM</div>
                                                            <div class="duration">2 hours</div>
                                                        </div>
                                                        <div class="time-slot-preset" onclick="selectPresetTime('15:00', '17:00')">
                                                            <div class="time-display">3:00 PM - 5:00 PM</div>
                                                            <div class="duration">2 hours</div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="custom-times" id="customTimes" style="display: none;">
                                                    <div class="form-row">
                                                        <div class="form-group">
                                                            <label for="start_time">Start Time *</label>
                                                            <input type="time" id="start_time" name="start_time" value="<?php echo htmlspecialchars($_POST['start_time'] ?? ''); ?>" required>
                                                        </div>

                                                        <div class="form-group">
                                                            <label for="end_time">End Time *</label>
                                                            <input type="time" id="end_time" name="end_time" value="<?php echo htmlspecialchars($_POST['end_time'] ?? ''); ?>" required>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="selected-time-display" id="selectedTimeDisplay" style="display: none;">
                                                    <h4>Selected time</h4>
                                                    <div class="selected-time-info">
                                                        <span><i class="fas fa-clock"></i> <span id="selectedTimeText"></span></span>
                                                        <button type="button" class="change-time-btn" onclick="switchTimeMethod('preset')">
                                                            <i class="fas fa-pen"></i> Change
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="time-help"><i class="fas fa-circle-info"></i> Choose a preset time slot or set your own custom time. We'll confirm availability during our confirmation call.</p>
                                        </div>
                                    </div>
                                </div>

                                <input type="hidden" name="booking_date" id="booking_date" value="<?php echo htmlspecialchars($_POST['booking_date'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <!-- Step 3: Booking Details -->
                        <div class="wizard-step" id="step3">
                            <div class="step-content">
                                <div class="step-head">
                                    <span class="step-no">3</span>
                                    <div>
                                        <h2>Booking details</h2>
                                        <p>Provide your contact information and booking requirements.</p>
                                    </div>
                                </div>

                                <div class="details-layout">
                                    <div class="details-fields">
                                        <div class="form-group">
                                            <label for="customer_name">Full Name *</label>
                                            <input type="text" id="customer_name" name="customer_name" value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>" required placeholder="Your full name">
                                        </div>

                                        <div class="form-group">
                                            <label for="customer_email">Email Address *</label>
                                            <input type="email" id="customer_email" name="customer_email" value="<?php echo htmlspecialchars($_POST['customer_email'] ?? ''); ?>" required placeholder="you@company.com">
                                        </div>

                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="customer_phone">Phone Number</label>
                                                <?php render_phone_field('customer_phone', 'customer_phone', false, $_POST['customer_phone_code'] ?? '+60', $_POST['customer_phone'] ?? ''); ?>
                                            </div>

                                            <div class="form-group">
                                                <label for="company">Company Name</label>
                                                <input type="text" id="company" name="company" value="<?php echo htmlspecialchars($_POST['company'] ?? ''); ?>" placeholder="Your company">
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="attendees_count">Number of Attendees</label>
                                            <input type="number" id="attendees_count" name="attendees_count" value="<?php echo htmlspecialchars($_POST['attendees_count'] ?? '1'); ?>" min="1" max="50" onchange="validateAttendees()">
                                            <small class="form-help">Maximum capacity will be validated based on selected facility</small>
                                        </div>

                                        <div class="form-group">
                                            <label for="purpose">Purpose of Booking</label>
                                            <textarea id="purpose" name="purpose" rows="3" placeholder="Briefly describe the purpose of your booking..."><?php echo htmlspecialchars($_POST['purpose'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="form-group">
                                            <label for="special_requirements">Special Requirements</label>
                                            <textarea id="special_requirements" name="special_requirements" rows="3" placeholder="Any special equipment, setup, or catering requirements..."><?php echo htmlspecialchars($_POST['special_requirements'] ?? ''); ?></textarea>
                                        </div>
                                    </div>

                                    <aside class="booking-summary">
                                        <h3>Booking summary</h3>
                                        <div class="summary-item">
                                            <span class="label">Facility</span>
                                            <span class="value" id="summaryFacility">-</span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="label">Date</span>
                                            <span class="value" id="summaryDate">-</span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="label">Time</span>
                                            <span class="value" id="summaryTime">-</span>
                                        </div>
                                    </aside>
                                </div>
                            </div>
                        </div>

                        <!-- Step 4: Confirmation -->
                        <div class="wizard-step" id="step4">
                            <div class="step-content">
                                <div class="step-head">
                                    <span class="step-no">4</span>
                                    <div>
                                        <h2>Confirm your booking</h2>
                                        <p>Review your booking details before submitting.</p>
                                    </div>
                                </div>

                                <div class="confirmation-summary">
                                    <div class="confirmation-card">
                                        <h3>Booking details</h3>
                                        <div class="confirmation-item">
                                            <span class="label">Facility</span>
                                            <span class="value" id="confirmFacility">-</span>
                                        </div>
                                        <div class="confirmation-item">
                                            <span class="label">Date</span>
                                            <span class="value" id="confirmDate">-</span>
                                        </div>
                                        <div class="confirmation-item">
                                            <span class="label">Time</span>
                                            <span class="value" id="confirmTime">-</span>
                                        </div>
                                        <div class="confirmation-item">
                                            <span class="label">Attendees</span>
                                            <span class="value" id="confirmAttendees">-</span>
                                        </div>
                                        <div class="confirmation-item">
                                            <span class="label">Contact</span>
                                            <span class="value" id="confirmContact">-</span>
                                        </div>
                                        <div class="confirmation-item">
                                            <span class="label">Company</span>
                                            <span class="value" id="confirmCompany">-</span>
                                        </div>
                                    </div>

                                    <div class="confirmation-card">
                                        <h3>Terms &amp; conditions</h3>
                                        <ul class="check-list terms-content">
                                            <li>Booking confirmation will be sent within 2 hours</li>
                                            <li>Cancellations must be made 24 hours in advance</li>
                                            <li>Special requirements are subject to availability</li>
                                            <li>Payment terms will be discussed during confirmation</li>
                                        </ul>
                                        <label class="checkbox-label">
                                            <input type="checkbox" id="agreeTerms" required>
                                            <span>I agree to the terms and conditions</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Navigation Buttons -->
                        <div class="wizard-navigation">
                            <button type="button" class="btn btn-outline" id="prevBtn" onclick="changeStep(-1)" style="display: none;">
                                <i class="fas fa-arrow-left"></i> Previous
                            </button>
                            <button type="button" class="btn btn-primary" id="nextBtn" onclick="changeStep(1)">
                                Next <i class="fas fa-arrow-right"></i>
                            </button>
                            <button type="submit" class="btn btn-primary" id="submitBtn" style="display: none;">
                                <i class="fas fa-calendar-check"></i> Submit Booking
                            </button>
                        </div>
                    </div>

                    <!-- Hidden form for submission -->
                    <form method="POST" id="bookingForm" style="display: none;">
                        <input type="hidden" name="facility_type" id="form_facility_type">
                        <input type="hidden" name="booking_date" id="form_booking_date">
                        <input type="hidden" name="start_time" id="form_start_time">
                        <input type="hidden" name="end_time" id="form_end_time">
                        <input type="hidden" name="customer_name" id="form_customer_name">
                        <input type="hidden" name="customer_email" id="form_customer_email">
                        <input type="hidden" name="customer_phone_code" id="form_customer_phone_code">
                        <input type="hidden" name="customer_phone" id="form_customer_phone">
                        <input type="hidden" name="company" id="form_company">
                        <input type="hidden" name="attendees_count" id="form_attendees_count">
                        <input type="hidden" name="purpose" id="form_purpose">
                        <input type="hidden" name="special_requirements" id="form_special_requirements">
                    </form>
                </div>
            </div>
        </section>
    </main>

    <?php include 'partials/site-footer.php'; ?>

    <script>
        // Enhanced Booking Wizard JavaScript
        let currentStep = 1;
        let selectedFacility = '';
        let selectedDate = '';
        let selectedTimeSlot = '';
        let currentMonth = new Date();
        
        // Facility data
        const facilities = {
            'conference_room': { name: 'Conference Room', capacity: 9 },
            'training_room': { name: 'Training Room', capacity: 40 },
            'breakout_area': { name: 'Breakout Area', capacity: 5 }
        };
        
        // Initialize wizard
        document.addEventListener('DOMContentLoaded', function() {
            initializeCalendar();
            updateProgressIndicator();
            updateNavigationButtons();
        });
        
        // Facility selection
        function selectFacility(facilityType) {
            const clickedCard = event.currentTarget;
            const isCurrentlySelected = clickedCard.classList.contains('selected');
            
            // Remove selected class from all cards
            document.querySelectorAll('.facility-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // If clicking the same facility, unselect it
            if (isCurrentlySelected) {
                selectedFacility = '';
                document.getElementById('facility_type').value = '';
            } else {
                // Add selected class to clicked card
                clickedCard.classList.add('selected');
                selectedFacility = facilityType;
                document.getElementById('facility_type').value = facilityType;
            }
            
            // Update progress and enable next button
            updateProgressIndicator();
            updateNavigationButtons();
        }
        
        // Calendar functionality
        function initializeCalendar() {
            renderCalendar();
        }
        
        function changeMonth(direction) {
            currentMonth.setMonth(currentMonth.getMonth() + direction);
            renderCalendar();
        }
        
        function renderCalendar() {
            const year = currentMonth.getFullYear();
            const month = currentMonth.getMonth();
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const daysInMonth = lastDay.getDate();
            const startingDayOfWeek = firstDay.getDay();
            
            const monthNames = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];
            
            document.getElementById('currentMonth').textContent = `${monthNames[month]} ${year}`;
            
            const calendarGrid = document.getElementById('calendarGrid');
            calendarGrid.innerHTML = '';
            
            // Add day headers
            const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            dayHeaders.forEach(day => {
                const dayHeader = document.createElement('div');
                dayHeader.className = 'calendar-day-header';
                dayHeader.textContent = day;
                calendarGrid.appendChild(dayHeader);
            });
            
            // Add empty cells for days before the first day of the month
            for (let i = 0; i < startingDayOfWeek; i++) {
                const emptyDay = document.createElement('div');
                emptyDay.className = 'calendar-day other-month';
                calendarGrid.appendChild(emptyDay);
            }
            
            // Load booking data for this month
            loadMonthBookings(year, month + 1, daysInMonth);
        }
        
        function loadMonthBookings(year, month, daysInMonth) {
            const monthStr = `${year}-${month.toString().padStart(2, '0')}`;
            
            fetch(`api/booking-availability.php?month=${monthStr}`)
                .then(response => response.json())
                .then(data => {
                    renderCalendarDays(year, month, daysInMonth, data.availability || {});
                })
                .catch(error => {
                    console.error('Error loading booking data:', error);
                    renderCalendarDays(year, month, daysInMonth, {});
                });
        }
        
        function renderCalendarDays(year, month, daysInMonth, availability) {
            const calendarGrid = document.getElementById('calendarGrid');
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            for (let day = 1; day <= daysInMonth; day++) {
                const dayElement = document.createElement('div');
                dayElement.className = 'calendar-day';
                dayElement.textContent = day;
                
                const currentDate = new Date(year, month - 1, day);
                const dateStr = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
                
                if (currentDate < today) {
                    dayElement.classList.add('past');
                } else {
                    // Check if this date has bookings
                    const hasBookings = availability[dateStr] && Object.keys(availability[dateStr]).length > 0;
                    
                    if (hasBookings) {
                        dayElement.classList.add('booked');
                        dayElement.title = 'This date has existing bookings';
                        
                        // Add booking indicator
                        const bookingIndicator = document.createElement('div');
                        bookingIndicator.className = 'booking-indicator';
                        bookingIndicator.innerHTML = '<i class="fas fa-calendar-times"></i>';
                        dayElement.appendChild(bookingIndicator);
                    } else {
                        dayElement.classList.add('available');
                        dayElement.onclick = () => selectDate(year, month, day);
                    }
                }
                
                if (selectedDate && currentDate.toDateString() === new Date(selectedDate).toDateString()) {
                    dayElement.classList.add('selected');
                }
                
                calendarGrid.appendChild(dayElement);
            }
        }
        
        function selectDate(year, month, day) {
            selectedDate = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
            document.getElementById('booking_date').value = selectedDate;
            
            // Update calendar display
            renderCalendar();
            
            updateProgressIndicator();
            updateNavigationButtons();
        }
        
        // Step navigation
        function changeStep(direction) {
            const totalSteps = 4;
            const newStep = currentStep + direction;
            
            if (newStep >= 1 && newStep <= totalSteps) {
                // Validate current step before proceeding
                if (direction > 0 && !validateCurrentStep()) {
                    return;
                }
                
                currentStep = newStep;
                showStep(currentStep);
                updateProgressIndicator();
                updateNavigationButtons();
                updateSummary();
            }
        }
        
        function showStep(step) {
            // Hide all steps
            document.querySelectorAll('.wizard-step').forEach(stepEl => {
                stepEl.classList.remove('active');
            });
            
            // Show current step
            document.getElementById(`step${step}`).classList.add('active');
        }
        
        function validateCurrentStep() {
            switch (currentStep) {
                case 1:
                    if (!selectedFacility) {
                        xpmAlert('Please select a facility first.');
                        return false;
                    }
                    break;
                case 2:
                    if (!selectedDate) {
                        xpmAlert('Please select a date.');
                        return false;
                    }
                    const startTime = document.getElementById('start_time').value;
                    const endTime = document.getElementById('end_time').value;
                    if (!startTime || !endTime) {
                        xpmAlert('Please select both start and end times.');
                        return false;
                    }
                    if (startTime >= endTime) {
                        xpmAlert('End time must be after start time.');
                        return false;
                    }
                    break;
                case 3:
                    const requiredFields = ['customer_name', 'customer_email'];
                    for (let field of requiredFields) {
                        const input = document.getElementById(field);
                        if (!input.value.trim()) {
                            xpmAlert(`Please fill in the ${input.previousElementSibling.textContent.replace('*', '').trim()}.`);
                            input.focus();
                            return false;
                        }
                    }
                    break;
                case 4:
                    if (!document.getElementById('agreeTerms').checked) {
                        xpmAlert('Please agree to the terms and conditions.');
                        return false;
                    }
                    break;
            }
            return true;
        }
        
        function updateProgressIndicator() {
            const progressSteps = document.querySelectorAll('.progress-step');
            progressSteps.forEach((stepEl, index) => {
                if (index < currentStep) {
                    stepEl.classList.add('completed');
                    stepEl.classList.remove('active');
                } else if (index === currentStep - 1) {
                    stepEl.classList.add('active');
                    stepEl.classList.remove('completed');
                } else {
                    stepEl.classList.remove('active', 'completed');
                }
            });
        }
        
        function updateNavigationButtons() {
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const submitBtn = document.getElementById('submitBtn');
            
            // Show/hide previous button
            prevBtn.style.display = currentStep > 1 ? 'flex' : 'none';
            
            // Show/hide next/submit buttons
            if (currentStep < 4) {
                nextBtn.style.display = 'flex';
                submitBtn.style.display = 'none';
            } else {
                nextBtn.style.display = 'none';
                submitBtn.style.display = 'flex';
            }
        }
        
        function updateSummary() {
            if (currentStep >= 3) {
                // Update booking summary
                if (selectedFacility) {
                    document.getElementById('summaryFacility').textContent = facilities[selectedFacility].name;
                }
                if (selectedDate) {
                    document.getElementById('summaryDate').textContent = new Date(selectedDate).toLocaleDateString('en-US', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                }
                const startTime = document.getElementById('start_time').value;
                const endTime = document.getElementById('end_time').value;
                if (startTime && endTime) {
                    const startFormatted = new Date('2000-01-01T' + startTime).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                    const endFormatted = new Date('2000-01-01T' + endTime).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                    document.getElementById('summaryTime').textContent = `${startFormatted} - ${endFormatted}`;
                }
            }
            
            if (currentStep === 4) {
                // Update confirmation summary
                document.getElementById('confirmFacility').textContent = facilities[selectedFacility].name;
                document.getElementById('confirmDate').textContent = new Date(selectedDate).toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                const startTime = document.getElementById('start_time').value;
                const endTime = document.getElementById('end_time').value;
                if (startTime && endTime) {
                    const startFormatted = new Date('2000-01-01T' + startTime).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                    const endFormatted = new Date('2000-01-01T' + endTime).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                    document.getElementById('confirmTime').textContent = `${startFormatted} - ${endFormatted}`;
                }
                document.getElementById('confirmAttendees').textContent = document.getElementById('attendees_count').value + ' people';
                document.getElementById('confirmContact').textContent = document.getElementById('customer_name').value + ' (' + document.getElementById('customer_email').value + ')';
                document.getElementById('confirmCompany').textContent = document.getElementById('company').value || 'Not specified';
            }
        }
        
        // Form submission
        document.getElementById('submitBtn').addEventListener('click', function() {
            if (!validateCurrentStep()) return;
            
            // Populate hidden form
            document.getElementById('form_facility_type').value = selectedFacility;
            document.getElementById('form_booking_date').value = selectedDate;
            document.getElementById('form_start_time').value = document.getElementById('start_time').value;
            document.getElementById('form_end_time').value = document.getElementById('end_time').value;
            document.getElementById('form_customer_name').value = document.getElementById('customer_name').value;
            document.getElementById('form_customer_email').value = document.getElementById('customer_email').value;
            document.getElementById('form_customer_phone_code').value = document.getElementById('customer_phone_code').value;
            document.getElementById('form_customer_phone').value = document.getElementById('customer_phone').value;
            document.getElementById('form_company').value = document.getElementById('company').value;
            document.getElementById('form_attendees_count').value = document.getElementById('attendees_count').value;
            document.getElementById('form_purpose').value = document.getElementById('purpose').value;
            document.getElementById('form_special_requirements').value = document.getElementById('special_requirements').value;
            
            // Add loading state
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            this.disabled = true;
            
            // Submit form
            document.getElementById('bookingForm').submit();
        });
        
        // Attendees validation
        function validateAttendees() {
            if (!selectedFacility) return;
            
            const attendeesInput = document.getElementById('attendees_count');
            const attendeesCount = parseInt(attendeesInput.value) || 1;
            const maxCapacity = facilities[selectedFacility].capacity;
            
            if (attendeesCount > maxCapacity) {
                attendeesInput.style.borderColor = '#ef4444';
                xpmAlert(`Maximum capacity for ${facilities[selectedFacility].name} is ${maxCapacity} people.`);
                attendeesInput.value = maxCapacity;
            }
            
            attendeesInput.style.borderColor = '#10b981';
        }
        
        // Real-time form validation
        document.getElementById('customer_email').addEventListener('blur', function() {
            const email = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                this.style.borderColor = '#ef4444';
            } else if (email) {
                this.style.borderColor = '#10b981';
            }
        });
        
        // Enhanced form interactions
        document.querySelectorAll('input, select, textarea').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });
        
        // Page load animations
        window.addEventListener('load', function() {
            document.querySelector('.booking-container').style.opacity = '0';
            document.querySelector('.booking-container').style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                document.querySelector('.booking-container').style.transition = 'all 0.6s ease';
                document.querySelector('.booking-container').style.opacity = '1';
                document.querySelector('.booking-container').style.transform = 'translateY(0)';
            }, 100);
            
            // Check if there's a success or error message and scroll to it
            const successMessage = document.querySelector('.alert-success');
            const errorMessage = document.querySelector('.alert-error');
            
            if (successMessage || errorMessage) {
                setTimeout(() => {
                    const messageElement = successMessage || errorMessage;
                    messageElement.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'center' 
                    });
                }, 500); // Wait for page animations to complete
            }
        });
         
        // Time selection methods
        function switchTimeMethod(method) {
            const presetTimes = document.getElementById('presetTimes');
            const customTimes = document.getElementById('customTimes');
            const selectedDisplay = document.getElementById('selectedTimeDisplay');
            const tabs = document.querySelectorAll('.method-tab');
            
            // Update tab states
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            if (method === 'preset') {
                presetTimes.style.display = 'block';
                customTimes.style.display = 'none';
                selectedDisplay.style.display = 'none';
            } else {
                presetTimes.style.display = 'none';
                customTimes.style.display = 'block';
                selectedDisplay.style.display = 'none';
            }
        }
        
        function selectPresetTime(startTime, endTime) {
            // Remove selected class from all preset slots
            document.querySelectorAll('.time-slot-preset').forEach(slot => {
                slot.classList.remove('selected');
            });
            
            // Add selected class to clicked slot
            event.currentTarget.classList.add('selected');
            
            // Set the time values
            document.getElementById('start_time').value = startTime;
            document.getElementById('end_time').value = endTime;
            
            // Show selected time display
            showSelectedTime(startTime, endTime);
            
            // Update progress and navigation
            updateProgressIndicator();
            updateNavigationButtons();
        }
        
        function showSelectedTime(startTime, endTime) {
            const startFormatted = new Date('2000-01-01T' + startTime).toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit', 
                hour12: true 
            });
            const endFormatted = new Date('2000-01-01T' + endTime).toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit', 
                hour12: true 
            });
            
            document.getElementById('selectedTimeText').textContent = `${startFormatted} - ${endFormatted}`;
            document.getElementById('selectedTimeDisplay').style.display = 'block';
            document.getElementById('presetTimes').style.display = 'none';
        }
        
        // Enhanced time input validation
        document.getElementById('start_time').addEventListener('change', function() {
            const startTime = this.value;
            const endTime = document.getElementById('end_time').value;
            
            if (startTime && endTime && startTime >= endTime) {
                this.style.borderColor = '#ef4444';
                xpmAlert('End time must be after start time');
                this.value = '';
            } else {
                this.style.borderColor = '#10b981';
                updateProgressIndicator();
                updateNavigationButtons();
            }
        });
        
        document.getElementById('end_time').addEventListener('change', function() {
            const startTime = document.getElementById('start_time').value;
            const endTime = this.value;
            
            if (startTime && endTime && startTime >= endTime) {
                this.style.borderColor = '#ef4444';
                xpmAlert('End time must be after start time');
                this.value = '';
            } else {
                this.style.borderColor = '#10b981';
                updateProgressIndicator();
                updateNavigationButtons();
            }
        });
        
        // Mobile optimizations
        function isMobile() {
            return window.innerWidth <= 768;
        }
        
        if (isMobile()) {
            document.querySelectorAll('input, select, textarea').forEach(input => {
                input.addEventListener('focus', function() {
                    this.style.fontSize = '16px';
                });
            });
        }
    </script>

    <script>
        document.querySelectorAll('.facility-card[role="button"], .time-slot-preset').forEach(function(card) {
            if (!card.hasAttribute('tabindex')) card.setAttribute('tabindex', '0');
            card.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    card.click();
                }
            });
        });
    </script>
</body>
</html>
