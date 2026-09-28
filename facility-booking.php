<?php
// Set timezone for consistent date handling
date_default_timezone_set('Asia/Kuala_Lumpur');

// Include shared database connection
require_once 'includes/database.php';
require_once 'includes/email-notifications.php';

$pdo = get_db_connection();
$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
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
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
                 /* Enhanced Booking Page Styles */
         .booking-container {
             max-width: 1400px;
             margin: 0 auto;
             padding: 3rem 2rem;
             background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
             min-height: 100vh;
             padding-top: 120px; /* Add top padding to account for fixed navigation */
             position: relative;
             overflow: hidden;
         }
         
                   /* Animated Background Elements */
          .booking-container::before {
              content: '';
              position: absolute;
              top: 0;
              left: 0;
              right: 0;
              bottom: 0;
              background: 
                  radial-gradient(circle at 20% 80%, rgba(102, 126, 234, 0.25) 0%, transparent 50%),
                  radial-gradient(circle at 80% 20%, rgba(118, 75, 162, 0.25) 0%, transparent 50%),
                  radial-gradient(circle at 40% 40%, rgba(16, 185, 129, 0.15) 0%, transparent 50%);
              animation: backgroundFloat 20s ease-in-out infinite;
              z-index: 0;
          }
         
                   .booking-container::after {
              content: '';
              position: absolute;
              top: 0;
              left: 0;
              right: 0;
              bottom: 0;
              background-image: 
                  url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23667eea' fill-opacity='0.08'%3E%3Ccircle cx='30' cy='30' r='3'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
              animation: patternMove 30s linear infinite;
              z-index: 0;
          }
         
         /* Floating Particles */
         .floating-particles {
             position: absolute;
             top: 0;
             left: 0;
             width: 100%;
             height: 100%;
             overflow: hidden;
             z-index: 1;
         }
         
                   .particle {
              position: absolute;
              width: 8px;
              height: 8px;
              background: linear-gradient(135deg, #667eea, #764ba2);
              border-radius: 50%;
              opacity: 0.8;
              animation: float 15s infinite linear;
              box-shadow: 0 0 10px rgba(102, 126, 234, 0.5);
          }
         
         .particle:nth-child(1) {
             left: 10%;
             animation-delay: 0s;
             animation-duration: 20s;
         }
         
         .particle:nth-child(2) {
             left: 20%;
             animation-delay: 2s;
             animation-duration: 18s;
         }
         
         .particle:nth-child(3) {
             left: 30%;
             animation-delay: 4s;
             animation-duration: 22s;
         }
         
         .particle:nth-child(4) {
             left: 40%;
             animation-delay: 6s;
             animation-duration: 16s;
         }
         
         .particle:nth-child(5) {
             left: 50%;
             animation-delay: 8s;
             animation-duration: 24s;
         }
         
         .particle:nth-child(6) {
             left: 60%;
             animation-delay: 10s;
             animation-duration: 19s;
         }
         
         .particle:nth-child(7) {
             left: 70%;
             animation-delay: 12s;
             animation-duration: 21s;
         }
         
         .particle:nth-child(8) {
             left: 80%;
             animation-delay: 14s;
             animation-duration: 17s;
         }
         
         .particle:nth-child(9) {
             left: 90%;
             animation-delay: 16s;
             animation-duration: 23s;
         }
         
         .particle:nth-child(10) {
             left: 95%;
             animation-delay: 18s;
             animation-duration: 20s;
         }
         
         /* Animated Shapes */
         .animated-shapes {
             position: absolute;
             top: 0;
             left: 0;
             width: 100%;
             height: 100%;
             overflow: hidden;
             z-index: 1;
         }
         
                   .shape {
              position: absolute;
              opacity: 0.25;
              animation: shapeFloat 25s infinite ease-in-out;
              box-shadow: 0 0 20px rgba(102, 126, 234, 0.3);
          }
         
                   .shape-1 {
              top: 10%;
              left: 5%;
              width: 100px;
              height: 100px;
              background: linear-gradient(135deg, #667eea, #764ba2);
              border-radius: 50%;
              animation-delay: 0s;
          }
         
                   .shape-2 {
              top: 60%;
              right: 10%;
              width: 80px;
              height: 80px;
              background: linear-gradient(135deg, #10b981, #059669);
              border-radius: 20px;
              transform: rotate(45deg);
              animation-delay: 5s;
          }
         
                   .shape-3 {
              bottom: 20%;
              left: 15%;
              width: 60px;
              height: 60px;
              background: linear-gradient(135deg, #f59e0b, #d97706);
              border-radius: 8px;
              animation-delay: 10s;
          }
         
                   .shape-4 {
              top: 30%;
              right: 20%;
              width: 70px;
              height: 70px;
              background: linear-gradient(135deg, #8b5cf6, #7c3aed);
              border-radius: 12px;
              animation-delay: 15s;
          }
         
         /* Ensure content is above background */
         .booking-container > * {
             position: relative;
             z-index: 2;
         }
        
        .booking-header {
            text-align: center;
            margin-bottom: 4rem;
            position: relative;
        }
        
        .booking-header::before {
            content: '';
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 2px;
        }
        
        .booking-header h1 {
            font-size: 3.5rem;
            font-weight: 900;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 4px 8px rgba(102, 126, 234, 0.1);
        }
        
        .booking-header p {
            font-size: 1.2rem;
            color: #64748b;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }
        
        .booking-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            margin-bottom: 4rem;
        }
        
        .facility-selection {
            background: white;
            border-radius: 25px;
            padding: 2.5rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.08);
            border: 1px solid rgba(102, 126, 234, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .facility-selection::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .facility-selection h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .facility-selection h2::before {
            content: '🏢';
            font-size: 1.5rem;
        }
        
        .facility-selection > p {
            color: #64748b;
            margin-bottom: 2rem;
            font-size: 1rem;
        }
        
        .facility-option {
            border: 2px solid #e2e8f0;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            background: #fafbfc;
        }
        
        .facility-option:hover {
            border-color: #667eea;
            transform: translateY(-4px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.15);
            background: white;
        }
        
        .facility-option.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, #f8f9ff 0%, #e8ecff 100%);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.2);
        }
        
        .facility-option.selected::after {
            content: '✓';
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 30px;
            height: 30px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .facility-option h3 {
            color: #1e293b;
            margin-bottom: 0.75rem;
            font-size: 1.4rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .facility-option h3::before {
            font-size: 1.2rem;
        }
        
        .facility-option h3[data-facility="conference_room"]::before { content: '🏢'; }
        .facility-option h3[data-facility="training_room"]::before { content: '🎓'; }
        .facility-option h3[data-facility="breakout_area"]::before { content: '💬'; }
        
        .facility-option p {
            color: #64748b;
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        
        .facility-capacity {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .facility-features {
            list-style: none;
            padding: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }
        
        .facility-features li {
            color: #475569;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .facility-features li::before {
            content: "✓";
            color: #10b981;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .booking-form {
            background: white;
            border-radius: 25px;
            padding: 2.5rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.08);
            border: 1px solid rgba(102, 126, 234, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .booking-form::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        .booking-form h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .booking-form h2::before {
            content: '📝';
            font-size: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 2rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: #374151;
            font-size: 1rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f9fafb;
        }
        
                 .form-group input:focus,
         .form-group select:focus,
         .form-group textarea:focus {
             outline: none;
             border-color: #667eea;
             background: white;
             box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
         }
         
         .form-help {
             display: block;
             margin-top: 0.5rem;
             font-size: 0.875rem;
             color: #64748b;
             font-style: italic;
         }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        .submit-btn {
            width: 100%;
            padding: 1.25rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 1.2rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.3);
        }
        
        .submit-btn:active {
            transform: translateY(-1px);
        }
        
        .alert {
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-weight: 600;
            border: none;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 3rem;
            padding: 0.75rem 1.5rem;
            background: white;
            border-radius: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            transform: translateX(-5px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
            text-decoration: none;
        }
        
                     /* Progress indicator */
         .booking-progress {
             display: flex;
             justify-content: center;
             margin-bottom: 3rem;
             gap: 4rem;
         }
         
         .progress-step {
             display: flex;
             align-items: center;
             gap: 1rem;
             padding: 0 1rem;
         }
        
        .progress-step.active {
            color: #667eea;
            font-weight: 600;
        }
        
        .progress-step.completed {
            color: #10b981;
        }
        
        .progress-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #e5e7eb;
            transition: all 0.3s ease;
        }
        
        .progress-step.active .progress-dot {
            background: #667eea;
            transform: scale(1.2);
        }
        
        .progress-step.completed .progress-dot {
            background: #10b981;
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .booking-grid {
                gap: 2rem;
            }
            
            .facility-features {
                grid-template-columns: 1fr;
            }
            
            .booking-progress {
                gap: 2rem;
            }
        }
        
        @media (max-width: 768px) {
            .booking-container {
                padding: 1.5rem 1rem;
                padding-top: 90px;
            }
            
            .booking-header h1 {
                font-size: 2.2rem;
                margin-bottom: 1rem;
            }
            
            .booking-header p {
                font-size: 1rem;
                padding: 0 1rem;
            }
            
            .booking-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .facility-selection,
            .booking-form {
                padding: 1.5rem;
                border-radius: 20px;
            }
            
            .facility-option {
                padding: 1.5rem;
                margin-bottom: 1rem;
            }
            
            .facility-option h3 {
                font-size: 1.2rem;
            }
            
            .facility-features {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .booking-progress {
                gap: 1rem;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .progress-step {
                padding: 0 0.5rem;
                font-size: 0.9rem;
            }
            
            .progress-step span {
                display: none;
            }
            
            .progress-step::after {
                content: attr(data-step);
                font-size: 0.8rem;
                margin-left: 0.5rem;
            }
            
            .form-group {
                margin-bottom: 1.5rem;
            }
            
            .form-group input,
            .form-group select,
            .form-group textarea {
                padding: 0.875rem;
                font-size: 16px; /* Prevents zoom on iOS */
            }
            
            .submit-btn {
                padding: 1rem;
                font-size: 1.1rem;
            }
            
            .back-link {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
                margin-bottom: 2rem;
            }
            
            .alert {
                padding: 1rem;
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 480px) {
             .booking-container {
                 padding: 1rem 0.75rem;
                 padding-top: 80px;
             }
             
             /* Reduce animation intensity on mobile for better performance */
             .particle {
                 width: 4px;
                 height: 4px;
                 animation-duration: 12s;
             }
             
             .shape {
                 opacity: 0.05;
                 animation-duration: 20s;
             }
             
             .facility-selection,
             .booking-form {
                 animation: none; /* Disable card float on mobile for better UX */
             }
             
             .booking-header h1 {
                 font-size: 1.8rem;
             }
             
             .booking-header p {
                 font-size: 0.9rem;
                 padding: 0 0.5rem;
             }
             
             .facility-selection,
             .booking-form {
                 padding: 1rem;
                 border-radius: 15px;
             }
             
             .facility-option {
                 padding: 1rem;
             }
             
             .facility-option h3 {
                 font-size: 1.1rem;
             }
             
             .booking-progress {
                 gap: 0.5rem;
             }
             
             .progress-step {
                 padding: 0 0.25rem;
             }
             
             .form-group input,
             .form-group select,
             .form-group textarea {
                 padding: 0.75rem;
             }
             
             .submit-btn {
                 padding: 0.875rem;
                 font-size: 1rem;
             }
             
                           /* Footer mobile optimizations */
              .footer-logo {
                  display: flex;
                  justify-content: center;
                  align-items: center;
                  margin-bottom: 1rem;
              }
              
              .footer-logo img {
                  width: 120px;
                  height: auto;
              }
             
             .footer-content {
                 grid-template-columns: 1fr;
                 gap: 2rem;
                 text-align: center;
             }
             
             .footer-section {
                 padding: 0 1rem;
             }
             
             .footer-section h4 {
                 font-size: 1.2rem;
                 margin-bottom: 1rem;
             }
             
             .footer-section ul li {
                 margin-bottom: 0.75rem;
                 font-size: 0.95rem;
             }
             
             .social-links {
                 justify-content: center;
                 gap: 1rem;
             }
             
             .social-links a {
                 width: 45px;
                 height: 45px;
                 font-size: 1.2rem;
             }
             
             .footer-bottom {
                 flex-direction: column;
                 gap: 1rem;
                 text-align: center;
                 padding: 1rem;
             }
             
             .footer-links {
                 gap: 1rem;
                 flex-wrap: wrap;
                 justify-content: center;
             }
         }
        
        /* Animation for facility selection */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .facility-option.selected {
            animation: pulse 0.6s ease-in-out;
        }
        
        /* Loading state for submit button */
        .submit-btn.loading {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .submit-btn.loading::after {
            content: '';
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 0.5rem;
        }
        
                 @keyframes spin {
             0% { transform: rotate(0deg); }
             100% { transform: rotate(360deg); }
         }
         
         /* Background Animation Keyframes */
                   @keyframes backgroundFloat {
              0%, 100% {
                  transform: translateY(0px) rotate(0deg);
              }
              25% {
                  transform: translateY(-40px) rotate(2deg);
              }
              50% {
                  transform: translateY(-20px) rotate(-2deg);
              }
              75% {
                  transform: translateY(-60px) rotate(1deg);
              }
          }
         
                   @keyframes patternMove {
              0% {
                  transform: translateX(0) translateY(0);
              }
              50% {
                  transform: translateX(-60px) translateY(-40px);
              }
              100% {
                  transform: translateX(0) translateY(0);
              }
          }
         
         @keyframes float {
             0% {
                 transform: translateY(100vh) rotate(0deg);
                 opacity: 0;
             }
             10% {
                 opacity: 0.6;
             }
             90% {
                 opacity: 0.6;
             }
             100% {
                 transform: translateY(-100px) rotate(360deg);
                 opacity: 0;
             }
         }
         
                   @keyframes shapeFloat {
              0%, 100% {
                  transform: translateY(0px) rotate(0deg) scale(1);
              }
              25% {
                  transform: translateY(-30px) rotate(90deg) scale(1.2);
              }
              50% {
                  transform: translateY(-50px) rotate(180deg) scale(0.8);
              }
              75% {
                  transform: translateY(-20px) rotate(270deg) scale(1.1);
              }
          }
         
        /* Enhanced Wizard Styles */
        .booking-wizard {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.08);
            overflow: hidden;
            position: relative;
            z-index: 2;
        }
        
        .wizard-step {
            display: none;
            padding: 3rem;
            min-height: 600px;
        }
        
        .wizard-step.active {
            display: block;
            animation: slideIn 0.5s ease-in-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .step-content h2 {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
            text-align: center;
        }
        
        .step-content > p {
            text-align: center;
            color: #64748b;
            margin-bottom: 3rem;
            font-size: 1.1rem;
        }
        
        /* Facility Cards */
        .facilities-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-bottom: 2rem;
            justify-items: center;
        }
        
        .facility-card[data-facility="breakout_area"] {
            grid-column: 2;
        }
        
        .facility-card {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 20px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            user-select: none;
        }
        
        .facility-card:hover {
            border-color: #667eea;
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.15);
        }
        
        .facility-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, #f8f9ff 0%, #e8ecff 100%);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.2);
        }
        
        .facility-image {
            position: relative;
            height: 200px;
            overflow: hidden;
        }
        
        .facility-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        
        .facility-card:hover .facility-image img {
            transform: scale(1.05);
        }
        
        .facility-overlay {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 40px;
            height: 40px;
            background: rgba(102, 126, 234, 0.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            opacity: 0;
            transform: scale(0.8);
            transition: all 0.3s ease;
        }
        
        .facility-card.selected .facility-overlay {
            opacity: 1;
            transform: scale(1);
        }
        
        .facility-card.selected {
            position: relative;
        }
        
        .facility-card.selected::after {
            content: 'Click to unselect';
            position: absolute;
            bottom: 1rem;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        
        .facility-card.selected:hover::after {
            opacity: 1;
        }
        
        .facility-info {
            padding: 2rem;
        }
        
        .facility-info h3 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.75rem;
        }
        
        .facility-info p {
            color: #64748b;
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        
        .facility-capacity {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .facility-features {
            list-style: none;
            padding: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }
        
        .facility-features li {
            color: #475569;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .facility-features li::before {
            content: "✓";
            color: #10b981;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        /* Calendar Styles */
        .datetime-selection {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin-bottom: 2rem;
        }
        
        .calendar-section h3,
        .timeslot-section h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .calendar-container {
            background: #f8fafc;
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .calendar-nav {
            background: #667eea;
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .calendar-nav:hover {
            background: #5a67d8;
                 transform: scale(1.1);
        }
        
        .calendar-header h4 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem;
        }
        
        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            position: relative;
        }
        
        .calendar-day.other-month {
            color: #cbd5e1;
            cursor: not-allowed;
        }
        
        .calendar-day.available {
            background: #f0f9ff;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }
        
        .calendar-day.available:hover {
            background: #0ea5e9;
            color: white;
            transform: scale(1.1);
        }
        
        .calendar-day.selected {
            background: #667eea;
            color: white;
            transform: scale(1.1);
        }
        
        .calendar-day.past {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
        }
        
        .calendar-day.booked {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
            cursor: not-allowed;
            position: relative;
        }
        
        .booking-indicator {
            position: absolute;
            top: 2px;
            right: 2px;
            font-size: 0.7rem;
            color: #dc2626;
        }
        
        .calendar-day-header {
            font-weight: 600;
            color: #64748b;
            text-align: center;
            padding: 0.5rem;
            font-size: 0.9rem;
        }
        
        /* Time Input */
        .time-input-container {
            background: #f8fafc;
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
        }
        
        .time-help {
            text-align: center;
            color: #64748b;
            font-style: italic;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        
        /* Time Selection Method */
        .method-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            background: white;
            padding: 0.5rem;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }
        
        .method-tab {
            flex: 1;
            padding: 0.75rem 1rem;
            border: none;
            background: transparent;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .method-tab.active {
            background: #667eea;
            color: white;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }
        
        .method-tab:hover:not(.active) {
            background: #f1f5f9;
            color: #374151;
        }
        
        /* Preset Time Slots */
        .preset-times h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .time-slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .time-slot-preset {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .time-slot-preset:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.15);
        }
        
        .time-slot-preset.selected {
            border-color: #10b981;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.2);
        }
        
        .time-display {
            font-weight: 600;
            color: #1e293b;
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }
        
        .duration {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 500;
        }
        
        /* Custom Time Input */
        .custom-times {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
        }
        
        /* Selected Time Display */
        .selected-time-display {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 2px solid #10b981;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1rem;
        }
        
        .selected-time-display h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #065f46;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .selected-time-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }
        
        #selectedTimeText {
            font-weight: 600;
            color: #065f46;
            font-size: 1.1rem;
        }
        
        .change-time-btn {
            background: #10b981;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .change-time-btn:hover {
            background: #059669;
            transform: translateY(-1px);
        }
        
        .no-selection {
            text-align: center;
            color: #64748b;
            font-style: italic;
            padding: 2rem;
        }
        
        .timeslots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }
        
        .timeslot {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .timeslot.available {
            border-color: #10b981;
            color: #065f46;
        }
        
        .timeslot.available:hover {
            background: #10b981;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }
        
        .timeslot.selected {
            background: #10b981;
            color: white;
            border-color: #059669;
        }
        
        .timeslot.booked {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fca5a5;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .timeslot-time {
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .timeslot-status {
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }
        
        /* Booking Summary */
        .booking-summary {
            background: linear-gradient(135deg, #f8f9ff 0%, #e8ecff 100%);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }
        
        .booking-summary h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(102, 126, 234, 0.1);
        }
        
        .summary-item:last-child {
            border-bottom: none;
        }
        
        .summary-item .label {
            font-weight: 600;
            color: #64748b;
        }
        
        .summary-item .value {
            font-weight: 600;
            color: #1e293b;
        }
        
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            border: 1px solid #e2e8f0;
        }
        
        /* Confirmation Styles */
        .confirmation-summary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .confirmation-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .confirmation-card h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .confirmation-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .confirmation-item:last-child {
            border-bottom: none;
        }
        
        .confirmation-item .label {
            font-weight: 600;
            color: #64748b;
        }
        
        .confirmation-item .value {
            font-weight: 600;
            color: #1e293b;
            text-align: right;
        }
        
        .terms-content {
            background: #f8fafc;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .terms-content p {
            margin-bottom: 0.75rem;
            color: #475569;
            font-size: 0.95rem;
        }
        
        .terms-content p:last-child {
            margin-bottom: 0;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            font-weight: 500;
            color: #374151;
        }
        
        .checkbox-label input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: #667eea;
        }
        
        /* Navigation Buttons */
        .wizard-navigation {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: 2rem 3rem;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            gap: 1rem;
        }
        
        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #64748b;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #475569;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .datetime-selection {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .confirmation-summary {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .facilities-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .facility-card[data-facility="breakout_area"] {
                grid-column: 1;
            }
        }
        
        @media (max-width: 768px) {
            .wizard-step {
                padding: 2rem 1.5rem;
            }
            
            .wizard-navigation {
                padding: 1.5rem;
                flex-direction: column;
                gap: 1rem;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .calendar-grid {
                gap: 0.25rem;
            }
            
            .calendar-day {
                font-size: 0.9rem;
            }
            
            .timeslots-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .wizard-step {
                padding: 1.5rem 1rem;
            }
            
            .step-content h2 {
                font-size: 1.5rem;
            }
            
            .facility-info {
                padding: 1.5rem;
            }
            
            .facility-features {
                grid-template-columns: 1fr;
            }
            
            .calendar-container,
            .timeslots-container {
                padding: 1rem;
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

         <div class="booking-container">
         <!-- Animated Background Elements -->
         <div class="floating-particles">
             <div class="particle"></div>
             <div class="particle"></div>
             <div class="particle"></div>
             <div class="particle"></div>
             <div class="particle"></div>
             <div class="particle"></div>
             <div class="particle"></div>
             <div class="particle"></div>
             <div class="particle"></div>
             <div class="particle"></div>
         </div>
         
         <div class="animated-shapes">
             <div class="shape shape-1"></div>
             <div class="shape shape-2"></div>
             <div class="shape shape-3"></div>
             <div class="shape shape-4"></div>
         </div>
         
        <div style="display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap;">
         <a href="index.html#facilities" class="back-link">
             <i class="fas fa-arrow-left"></i>
             Back to Facilities
         </a>
            <a href="my-bookings.php" class="back-link" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;">
                <i class="fas fa-calendar-check"></i>
                View My Bookings
            </a>
        </div>
        
        <div class="booking-header">
            <h1>Book Our Facilities</h1>
            <p>Reserve our state-of-the-art training facilities for your next meeting, training session, or event</p>
        </div>
        
                 <!-- Progress Indicator -->
         <div class="booking-progress">
             <div class="progress-step active" data-step="1. Select">
                 <div class="progress-dot"></div>
                 <span>Select Facility</span>
             </div>
             <div class="progress-step" data-step="2. Fill">
                 <div class="progress-dot"></div>
                 <span>Fill Details</span>
             </div>
             <div class="progress-step" data-step="3. Confirm">
                 <div class="progress-dot"></div>
                 <span>Confirm Booking</span>
             </div>
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
        
        <!-- Enhanced Booking Wizard -->
        <div class="booking-wizard" id="bookingWizard">
            <!-- Step 1: Facility Selection -->
            <div class="wizard-step active" id="step1">
                <div class="step-content">
                    <h2>Select Your Facility</h2>
                <p>Choose the facility that best suits your needs</p>
                
                    <div class="facilities-grid">
                        <?php 
                        // Reorder facilities: conference_room, breakout_area, training_room
                        $facilityOrder = ['conference_room', 'breakout_area', 'training_room'];
                        foreach ($facilityOrder as $key): 
                            $facility = $facilities[$key];
                        ?>
                            <div class="facility-card" onclick="selectFacility('<?php echo $key; ?>')" data-facility="<?php echo $key; ?>">
                                <div class="facility-image">
                                    <?php 
                                    $imagePath = "assets/images/";
                                    $imageFile = "";
                                    
                                    // Set specific images for each facility
                                    switch($key) {
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
                                    <img src="<?php echo $imagePath . $imageFile; ?>" alt="<?php echo htmlspecialchars($facility['name']); ?>" onerror="this.src='assets/images/conference-room.jpg'">
                                    <div class="facility-overlay">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                </div>
                                <div class="facility-info">
                                    <h3><?php echo htmlspecialchars($facility['name']); ?></h3>
                        <p><?php echo htmlspecialchars($facility['description']); ?></p>
                        <div class="facility-capacity"><?php echo htmlspecialchars($facility['capacity']); ?></div>
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
                    <h2>Select Date & Time</h2>
                    <p>Choose your preferred date and available time slot</p>
                    
                    <div class="datetime-selection">
                        <div class="calendar-section">
                            <h3>Select Date</h3>
                            <div class="calendar-container">
                                <div class="calendar-header">
                                    <button type="button" class="calendar-nav" onclick="changeMonth(-1)">
                                        <i class="fas fa-chevron-left"></i>
                                    </button>
                                    <h4 id="currentMonth"></h4>
                                    <button type="button" class="calendar-nav" onclick="changeMonth(1)">
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                </div>
                                <div class="calendar-grid" id="calendarGrid">
                                    <!-- Calendar will be populated by JavaScript -->
                                </div>
                            </div>
                        </div>
                        
                        <div class="timeslot-section">
                            <h3>Preferred Time</h3>
                            <div class="time-input-container">
                                <div class="time-selection-method">
                                    <div class="method-tabs">
                                        <button type="button" class="method-tab active" onclick="switchTimeMethod('preset')">
                                            <i class="fas fa-clock"></i> Quick Select
                                        </button>
                                        <button type="button" class="method-tab" onclick="switchTimeMethod('custom')">
                                            <i class="fas fa-edit"></i> Custom Time
                                        </button>
                                    </div>
                                    
                                    <!-- Preset Time Slots -->
                                    <div class="preset-times" id="presetTimes">
                                        <h4>Popular Time Slots</h4>
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
                                    
                                    <!-- Custom Time Input -->
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
                                    
                                    <!-- Selected Time Display -->
                                    <div class="selected-time-display" id="selectedTimeDisplay" style="display: none;">
                                        <h4>Selected Time</h4>
                                        <div class="selected-time-info">
                                            <span id="selectedTimeText"></span>
                                            <button type="button" class="change-time-btn" onclick="switchTimeMethod('preset')">
                                                <i class="fas fa-edit"></i> Change
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <p class="time-help">Choose a preset time slot or set your own custom time. We'll confirm availability during our confirmation call.</p>
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="booking_date" id="booking_date" value="<?php echo htmlspecialchars($_POST['booking_date'] ?? ''); ?>" required>
                </div>
            </div>
            
            <!-- Step 3: Booking Details -->
            <div class="wizard-step" id="step3">
                <div class="step-content">
                <h2>Booking Details</h2>
                    <p>Provide your contact information and booking requirements</p>
                    
                    <div class="booking-summary">
                        <h3>Booking Summary</h3>
                        <div class="summary-item">
                            <span class="label">Facility:</span>
                            <span class="value" id="summaryFacility">-</span>
                        </div>
                        <div class="summary-item">
                            <span class="label">Date:</span>
                            <span class="value" id="summaryDate">-</span>
                        </div>
                        <div class="summary-item">
                            <span class="label">Time:</span>
                            <span class="value" id="summaryTime">-</span>
                        </div>
                    </div>
                    
                    <div class="form-section">
                <div class="form-group">
                    <label for="customer_name">Full Name *</label>
                    <input type="text" id="customer_name" name="customer_name" value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="customer_email">Email Address *</label>
                    <input type="email" id="customer_email" name="customer_email" value="<?php echo htmlspecialchars($_POST['customer_email'] ?? ''); ?>" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="customer_phone">Phone Number</label>
                        <input type="tel" id="customer_phone" name="customer_phone" value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="company">Company Name</label>
                        <input type="text" id="company" name="company" value="<?php echo htmlspecialchars($_POST['company'] ?? ''); ?>">
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
                </div>
                </div>
                
            <!-- Step 4: Confirmation -->
            <div class="wizard-step" id="step4">
                <div class="step-content">
                    <h2>Confirm Your Booking</h2>
                    <p>Review your booking details before submitting</p>
                    
                    <div class="confirmation-summary">
                        <div class="confirmation-card">
                            <h3>Booking Details</h3>
                            <div class="confirmation-item">
                                <span class="label">Facility:</span>
                                <span class="value" id="confirmFacility">-</span>
                            </div>
                            <div class="confirmation-item">
                                <span class="label">Date:</span>
                                <span class="value" id="confirmDate">-</span>
                            </div>
                            <div class="confirmation-item">
                                <span class="label">Time:</span>
                                <span class="value" id="confirmTime">-</span>
                            </div>
                            <div class="confirmation-item">
                                <span class="label">Attendees:</span>
                                <span class="value" id="confirmAttendees">-</span>
                            </div>
                            <div class="confirmation-item">
                                <span class="label">Contact:</span>
                                <span class="value" id="confirmContact">-</span>
                            </div>
                            <div class="confirmation-item">
                                <span class="label">Company:</span>
                                <span class="value" id="confirmCompany">-</span>
                            </div>
                        </div>
                        
                        <div class="confirmation-card">
                            <h3>Terms & Conditions</h3>
                            <div class="terms-content">
                                <p>• Booking confirmation will be sent within 2 hours</p>
                                <p>• Cancellations must be made 24 hours in advance</p>
                                <p>• Special requirements are subject to availability</p>
                                <p>• Payment terms will be discussed during confirmation</p>
                            </div>
                            <label class="checkbox-label">
                                <input type="checkbox" id="agreeTerms" required>
                                <span class="checkmark"></span>
                                I agree to the terms and conditions
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Navigation Buttons -->
            <div class="wizard-navigation">
                <button type="button" class="btn btn-secondary" id="prevBtn" onclick="changeStep(-1)" style="display: none;">
                    <i class="fas fa-arrow-left"></i> Previous
                </button>
                <button type="button" class="btn btn-primary" id="nextBtn" onclick="changeStep(1)">
                    Next <i class="fas fa-arrow-right"></i>
                </button>
                <button type="submit" class="btn btn-success" id="submitBtn" style="display: none;">
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
            <input type="hidden" name="customer_phone" id="form_customer_phone">
            <input type="hidden" name="company" id="form_company">
            <input type="hidden" name="attendees_count" id="form_attendees_count">
            <input type="hidden" name="purpose" id="form_purpose">
            <input type="hidden" name="special_requirements" id="form_special_requirements">
        </form>
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
                        alert('Please select a facility first.');
                        return false;
                    }
                    break;
                case 2:
                    if (!selectedDate) {
                        alert('Please select a date.');
                        return false;
                    }
                    const startTime = document.getElementById('start_time').value;
                    const endTime = document.getElementById('end_time').value;
                    if (!startTime || !endTime) {
                        alert('Please select both start and end times.');
                        return false;
                    }
                    if (startTime >= endTime) {
                        alert('End time must be after start time.');
                        return false;
                    }
                    break;
                case 3:
                    const requiredFields = ['customer_name', 'customer_email'];
                    for (let field of requiredFields) {
                        const input = document.getElementById(field);
                        if (!input.value.trim()) {
                            alert(`Please fill in the ${input.previousElementSibling.textContent.replace('*', '').trim()}.`);
                            input.focus();
                            return false;
                        }
                    }
                    break;
                case 4:
                    if (!document.getElementById('agreeTerms').checked) {
                        alert('Please agree to the terms and conditions.');
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
                alert(`Maximum capacity for ${facilities[selectedFacility].name} is ${maxCapacity} people.`);
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
                alert('End time must be after start time');
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
                alert('End time must be after start time');
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
</body>
</html>
