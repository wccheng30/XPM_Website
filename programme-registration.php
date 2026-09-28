<?php
session_start();

// Include shared database connection
require_once 'includes/database.php';
require_once 'config/email_config.php';
require_once 'includes/programme-question-config.php';

// Helper function to safely display prices
function formatPrice($price) {
    if (is_numeric($price)) {
        return 'RM ' . number_format($price, 0);
    } else {
        return htmlspecialchars($price);
    }
}

$pdo = get_db_connection();
$success_message = '';
$error_message = '';
$programme = null;
$programme_question_map = [];

// Email notification function
function sendRegistrationNotification($programme_id, $name, $email, $phone, $ic_number, $company, $position, $billing_address, $billing_contact_no, $vehicle_type, $vehicle_type_other, $plate_number, $payment_method, $how_did_you_hear, $how_did_you_hear_other, $spicy_preference, $allergies, $allergies_other, $dietary_preference, $dietary_restrictions, $interested_programs, $interested_programs_other, $additional_notes) {
    // Programme registration email from configuration
    $programme_email = PROGRAMME_REGISTRATION_EMAIL;
    
    // Get programme details
    global $pdo;
    $stmt = $pdo->prepare("SELECT title, duration, price FROM programmes WHERE id = ?");
    $stmt->execute([$programme_id]);
    $programme = $stmt->fetch();
    
    $programme_title = $programme ? $programme['title'] : 'Unknown Programme';
    $programme_duration = $programme ? $programme['duration'] : 'N/A';
    $programme_price = $programme ? $programme['price'] : 'N/A';
    
    // Format values for display
    $vehicle_display = $vehicle_type === 'other' ? $vehicle_type_other : ucfirst($vehicle_type);
    $allergies_display = $allergies === 'other' ? $allergies_other : ucfirst($allergies);
    $dietary_display = $dietary_preference === 'other' ? $dietary_restrictions : ucfirst($dietary_preference);
    $hear_display = $how_did_you_hear === 'other' ? $how_did_you_hear_other : ucfirst(str_replace('_', ' ', $how_did_you_hear));
    $programs_display = $interested_programs === 'other' ? $interested_programs_other : ucfirst(str_replace('_', ' ', $interested_programs));
    
    $subject = "New Programme Registration - " . $programme_title;
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background-color: #2c3e50; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .section { margin-bottom: 20px; }
            .section h3 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 5px; }
            .field { margin-bottom: 10px; }
            .field strong { color: #2c3e50; }
            .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>New Programme Registration</h1>
            <p>Someone has registered for a programme</p>
        </div>
        
        <div class='content'>
            <div class='section'>
                <h3>Programme Details</h3>
                <div class='field'><strong>Programme:</strong> " . htmlspecialchars($programme_title) . "</div>
                <div class='field'><strong>Duration:</strong> " . htmlspecialchars($programme_duration) . "</div>
                <div class='field'><strong>Price:</strong> " . htmlspecialchars($programme_price) . "</div>
            </div>
            
            <div class='section'>
                <h3>Personal Information</h3>
                <div class='field'><strong>Name:</strong> " . htmlspecialchars($name) . "</div>
                <div class='field'><strong>Email:</strong> " . htmlspecialchars($email) . "</div>
                <div class='field'><strong>Phone:</strong> " . htmlspecialchars($phone) . "</div>
                <div class='field'><strong>IC Number:</strong> " . htmlspecialchars($ic_number) . "</div>
                <div class='field'><strong>Company:</strong> " . htmlspecialchars($company) . "</div>
                <div class='field'><strong>Position:</strong> " . htmlspecialchars($position) . "</div>
            </div>
            
            <div class='section'>
                <h3>Billing Information</h3>
                <div class='field'><strong>Billing Address:</strong> " . htmlspecialchars($billing_address) . "</div>
                <div class='field'><strong>Billing Contact:</strong> " . htmlspecialchars($billing_contact_no) . "</div>
                <div class='field'><strong>Payment Method:</strong> " . htmlspecialchars(ucfirst(str_replace('_', ' ', $payment_method))) . "</div>
            </div>
            
            <div class='section'>
                <h3>Vehicle Information</h3>
                <div class='field'><strong>Vehicle Type:</strong> " . htmlspecialchars($vehicle_display) . "</div>
                <div class='field'><strong>Plate Number:</strong> " . htmlspecialchars($plate_number) . "</div>
            </div>
            
            <div class='section'>
                <h3>Preferences & Requirements</h3>
                <div class='field'><strong>Spicy Preference:</strong> " . htmlspecialchars(ucfirst(str_replace('_', ' ', $spicy_preference))) . "</div>
                <div class='field'><strong>Dietary Preference:</strong> " . htmlspecialchars($dietary_display) . "</div>
                <div class='field'><strong>Allergies:</strong> " . htmlspecialchars($allergies_display) . "</div>
            </div>
            
            <div class='section'>
                <h3>Marketing & Interests</h3>
                <div class='field'><strong>How did they hear:</strong> " . htmlspecialchars($hear_display) . "</div>
                <div class='field'><strong>Interested Programs:</strong> " . htmlspecialchars($programs_display) . "</div>
            </div>
            
            " . (!empty($additional_notes) ? "
            <div class='section'>
                <h3>Additional Notes</h3>
                <div class='field'>" . nl2br(htmlspecialchars($additional_notes)) . "</div>
            </div>
            " : "") . "
        </div>
        
        <div class='footer'>
            <p>This is an automated notification from Xpress Point Management</p>
            <p>Registration submitted on " . date('Y-m-d H:i:s') . "</p>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . FROM_EMAIL . "\r\n";
    $headers .= "Reply-To: " . $email . "\r\n";
    
    // Send email to programme registration email
    mail($programme_email, $subject, $message, $headers);
}

// Get programme details if programme_id is provided
if (isset($_GET['programme_id'])) {
    $programme_id = (int)$_GET['programme_id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM programmes WHERE id = ? AND is_active = 1");
        $stmt->execute([$programme_id]);
        $programme = $stmt->fetch();
    } catch (PDOException $e) {
        $error_message = "Error loading programme details.";
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $programme_id = $_POST['programme_id'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $ic_number = trim($_POST['ic_number'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $billing_address = trim($_POST['billing_address'] ?? '');
    $billing_contact_no = trim($_POST['billing_contact_no'] ?? '');
    $vehicle_type = trim($_POST['vehicle_type'] ?? '');
    $vehicle_type_other = trim($_POST['vehicle_type_other'] ?? '');
    $plate_number = trim($_POST['plate_number'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $how_did_you_hear = trim($_POST['how_did_you_hear'] ?? '');
    $how_did_you_hear_other = trim($_POST['how_did_you_hear_other'] ?? '');
    $spicy_preference = trim($_POST['spicy_preference'] ?? '');
    $allergies = trim($_POST['allergies'] ?? '');
    $allergies_other = trim($_POST['allergies_other'] ?? '');
    $dietary_preference = trim($_POST['dietary_preference'] ?? 'none');
    $dietary_restrictions = trim($_POST['dietary_restrictions'] ?? '');
    $interested_programs = trim($_POST['interested_programs'] ?? '');
    $interested_programs_other = trim($_POST['interested_programs_other'] ?? '');
    $additional_notes = trim($_POST['additional_notes'] ?? '');

    $selected_question_configs = [];
    if (!empty($programme_id) && is_numeric($programme_id)) {
        $selected_question_configs = getProgrammeQuestionConfigs($pdo, (int)$programme_id, true);
    }

    $required_field_keys = [];
    foreach ($selected_question_configs as $question_config) {
        if (!empty($question_config['is_required'])) {
            $required_field_keys[] = $question_config['field_key'];
        }
    }

    $missing_required = false;
    foreach ($required_field_keys as $field_key) {
        $posted_value = trim((string)($_POST[$field_key] ?? ''));
        if ($posted_value === '') {
            $missing_required = true;
            break;
        }
    }

    // Validation
    if (empty($programme_id) || $missing_required) {
        $error_message = "Please fill in all required fields.";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO programme_registrations 
                (programme_id, name, email, phone, ic_number, company, position, billing_address, billing_contact_no, 
                 vehicle_type, vehicle_type_other, plate_number, payment_method, how_did_you_hear, how_did_you_hear_other, spicy_preference, 
                 allergies, allergies_other, dietary_preference, dietary_restrictions, interested_programs, interested_programs_other, additional_notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $programme_id,
                $name,
                $email,
                $phone,
                $ic_number,
                $company,
                $position,
                $billing_address,
                $billing_contact_no,
                $vehicle_type,
                $vehicle_type_other,
                $plate_number,
                $payment_method,
                $how_did_you_hear,
                $how_did_you_hear_other,
                $spicy_preference,
                $allergies,
                $allergies_other,
                $dietary_preference,
                $dietary_restrictions,
                $interested_programs,
                $interested_programs_other,
                $additional_notes
            ]);
            
            $success_message = "Registration submitted successfully! We'll contact you soon with programme details.";
            
            // Send email notification to admin
            sendRegistrationNotification($programme_id, $name, $email, $phone, $ic_number, $company, $position, $billing_address, $billing_contact_no, $vehicle_type, $vehicle_type_other, $plate_number, $payment_method, $how_did_you_hear, $how_did_you_hear_other, $spicy_preference, $allergies, $allergies_other, $dietary_preference, $dietary_restrictions, $interested_programs, $interested_programs_other, $additional_notes);
            
            // Clear form data
            $_POST = [];
            
        } catch (PDOException $e) {
            $error_message = $e->getMessage();
        }
    }
}

// Get all active programmes for dropdown
$all_programmes = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, title, duration, price FROM programmes WHERE is_active = 1 ORDER BY created_at DESC");
        $all_programmes = $stmt->fetchAll();
        foreach ($all_programmes as $programme_option) {
            $programme_question_map[(int)$programme_option['id']] = getProgrammeQuestionConfigs($pdo, (int)$programme_option['id'], true);
        }
    } catch (PDOException $e) {
        // Handle error silently
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Register for comprehensive HR training programmes. Join our professional development courses today.">
    <meta name="keywords" content="HR training, programme registration, professional development, HR courses">
    <meta name="author" content="Xpress Point Management">
    <title>Programme Registration - Xpress Point Management</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    
    <style>
        /* Fix for the Back to Programmes button */
        .form-actions .btn-secondary {
            background: #6b7280 !important;
            color: white !important;
            border: 2px solid #6b7280 !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        .form-actions .btn-secondary:hover {
            background: #4b5563 !important;
            border-color: #4b5563 !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 25px rgba(107, 114, 128, 0.3) !important;
        }
        
        /* Form actions - exact match to job application */
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .form-actions .btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .form-actions .btn-primary {
            background: #2563eb;
            color: white;
            border: 2px solid #2563eb;
        }
        
        .form-actions .btn-primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
        }
        
        .form-actions .btn-secondary {
            background: transparent;
            color: #6b7280;
            border: 2px solid #d1d5db;
        }
        
        .form-actions .btn-secondary:hover {
            background: #f3f4f6;
            border-color: #9ca3af;
            color: #374151;
        }
        
        /* Application container grid layout */
        .application-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 3rem;
            margin-top: 3rem;
        }
        
        /* Mobile responsive layout */
        @media (max-width: 768px) {
            .application-container {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                margin-top: 2rem;
                padding: 0 0.5rem;
            }
            
            .job-details-card,
            .application-form-card {
                padding: 1rem;
                margin: 0;
                width: 100%;
                box-sizing: border-box;
            }
            
            /* Force all form rows to be single column on mobile */
            .form-row {
                grid-template-columns: 1fr !important;
                gap: 1rem;
                width: 100%;
            }
            
            /* Ensure all form groups take full width */
            .form-group {
                width: 100%;
                min-width: 0;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 0.75rem;
                width: 100%;
            }
            
            .form-actions .btn {
                width: 100%;
                justify-content: center;
                min-height: 48px; /* Touch-friendly height */
                box-sizing: border-box;
            }
            
            .section-title {
                font-size: 1.5rem;
            }
            
            .section-subtitle {
                font-size: 0.9rem;
            }
            
            /* Mobile-specific form improvements */
            .form-header {
                margin-bottom: 1.5rem;
                text-align: left;
            }
            
            .form-header h3 {
                font-size: 1.4rem;
            }
            
            .form-header p {
                font-size: 0.9rem;
            }
            
            /* Improve mobile input experience */
            .input-wrapper {
                width: 100%;
                min-width: 0;
            }
            
            .input-wrapper input,
            .input-wrapper select,
            .input-wrapper textarea {
                width: 100%;
                padding: 1rem 0.75rem 1rem 2.5rem;
                font-size: 16px; /* Prevents zoom on iOS */
                border-radius: 8px;
                box-sizing: border-box;
                min-width: 0;
            }
            
            .input-wrapper i {
                font-size: 1rem;
                left: 0.75rem;
            }
            
            .form-group label {
                font-size: 0.9rem;
                margin-bottom: 0.25rem;
            }
            
            /* Ensure programme details are mobile-friendly */
            .job-details-card .job-details {
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .job-details-card .detail-item {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 0.9rem;
                flex-wrap: wrap;
            }
            
            .job-details-card .detail-item i {
                min-width: 16px;
                text-align: center;
            }
            
            .job-details-card .detail-item span {
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
        }
        
        /* Tablet responsive adjustments */
        @media (max-width: 1024px) and (min-width: 769px) {
            .application-container {
                gap: 2rem;
            }
            
            .form-row {
                gap: 1rem;
            }
        }
        
        /* Extra small mobile devices */
        @media (max-width: 480px) {
            .application-container {
                gap: 1rem;
                padding: 0 0.25rem;
            }
            
            .job-details-card,
            .application-form-card {
                padding: 0.75rem;
                margin: 0;
                width: 100%;
                box-sizing: border-box;
            }
            
            .form-header h3 {
                font-size: 1.3rem;
            }
            
            .form-header p {
                font-size: 0.85rem;
            }
            
            .input-wrapper input,
            .input-wrapper select,
            .input-wrapper textarea {
                padding: 0.875rem 0.5rem 0.875rem 2.25rem;
                font-size: 16px;
                border-radius: 6px;
            }
            
            .input-wrapper i {
                left: 0.5rem;
                font-size: 0.9rem;
            }
            
            .form-group label {
                font-size: 0.85rem;
            }
            
            .form-actions .btn {
                min-height: 44px;
                padding: 0.75rem 1rem;
                font-size: 0.85rem;
            }
            
            .section-title {
                font-size: 1.4rem;
            }
            
            .section-subtitle {
                font-size: 0.85rem;
            }
            
            /* Ensure all elements fit within screen width */
            .job-details-card .job-content h3 {
                font-size: 1.2rem;
            }
            
            .job-details-card .job-description {
                font-size: 0.9rem;
            }
            
            .job-details-card .detail-item {
                font-size: 0.85rem;
                gap: 0.4rem;
            }
            
            .job-details-card .detail-item i {
                min-width: 14px;
                font-size: 0.8rem;
            }
            
            .job-details-card .detail-item span {
                font-size: 0.8rem;
                line-height: 1.3;
            }
        }
        
        /* Match job application styling exactly */
        .job-details-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(37, 99, 235, 0.1);
            border-radius: 16px;
            padding: 2rem;
            height: fit-content;
        }
        
        .application-form-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(37, 99, 235, 0.1);
            border-radius: 16px;
            padding: 2rem;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .form-header h3 {
            font-size: 1.75rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .form-header p {
            color: #6b7280;
        }
        
        .application-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            width: 100%;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            width: 100%;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            width: 100%;
            min-width: 0;
        }
        
        .form-group label {
            font-weight: 600;
            color: #374151;
        }
        
        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
            min-width: 0;
        }
        
        .input-wrapper i {
            position: absolute;
            left: 1rem;
            color: #6b7280;
            font-size: 1rem;
            z-index: 2;
        }
        
        .input-wrapper input,
        .input-wrapper select,
        .input-wrapper textarea {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid rgba(37, 99, 235, 0.2);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            /* Mobile-friendly improvements */
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        
        /* Improve select dropdown appearance */
        .input-wrapper select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 1rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 3rem;
        }
        
        /* Mobile input improvements */
        @media (max-width: 768px) {
            .input-wrapper input,
            .input-wrapper select,
            .input-wrapper textarea {
                padding: 1.25rem 1rem 1.25rem 3rem;
                font-size: 16px; /* Prevents zoom on iOS */
                border-radius: 10px;
            }
            
            .input-wrapper i {
                font-size: 1.1rem;
                left: 1.25rem;
            }
            
            .form-group label {
                font-size: 0.95rem;
                margin-bottom: 0.25rem;
            }
            
            .form-header h3 {
                font-size: 1.5rem;
            }
            
            .form-header p {
                font-size: 0.95rem;
            }
        }
        
        .input-wrapper textarea {
            padding-left: 3rem;
            resize: vertical;
            min-height: 120px;
        }
        
        .input-wrapper input:focus,
        .input-wrapper select:focus,
        .input-wrapper textarea:focus {
            outline: none;
            border-color: rgba(37, 99, 235, 0.5);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        /* Touch-friendly focus states for mobile */
        @media (hover: none) and (pointer: coarse) {
            .input-wrapper input:focus,
            .input-wrapper select:focus,
            .input-wrapper textarea:focus {
                box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
            }
            
            .form-actions .btn:active {
                transform: scale(0.98);
            }
        }
        
        .input-wrapper input::placeholder,
        .input-wrapper textarea::placeholder {
            color: #9ca3af;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .form-actions .btn {
            padding: 1rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            min-height: 48px; /* Touch-friendly minimum height */
            justify-content: center;
        }
        
        .form-actions .btn-primary {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
        }
        
        .form-actions .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4);
        }
        
        .form-actions .btn-secondary {
            background: #6b7280;
            color: white;
            border: 2px solid #6b7280;
        }
        
        .form-actions .btn-secondary:hover {
            background: #4b5563;
            border-color: #4b5563;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(107, 114, 128, 0.3);
        }
        
        /* Alert Styles */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
            border: 1px solid;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border-color: rgba(34, 197, 94, 0.3);
            color: #16a34a;
        }
        
        .alert-success i {
            color: #16a34a;
            font-size: 1.25rem;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.3);
            color: #dc2626;
        }
        
        .alert-error i {
            color: #dc2626;
            font-size: 1.25rem;
        }
        
        .alert span {
            font-size: 1rem;
            line-height: 1.5;
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
                <a href="programmes.php" class="nav-link active">Programmes</a>
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

    <!-- Programme Registration Section -->
    <section id="programme-registration" class="programme">
        <!-- Background Elements -->
        <div class="programme-background">
            <div class="programme-gradient-bg"></div>
            <div class="programme-grid-pattern"></div>
            <div class="programme-particles">
                <div class="programme-particle programme-particle-1"></div>
                <div class="programme-particle programme-particle-2"></div>
                <div class="programme-particle programme-particle-3"></div>
            </div>
            <div class="programme-shapes">
                <div class="programme-shape programme-shape-1"></div>
                <div class="programme-shape programme-shape-2"></div>
                <div class="programme-shape programme-shape-3"></div>
                <div class="programme-shape programme-shape-4"></div>
                <div class="programme-shape programme-shape-5"></div>
            </div>
        </div>
        
        <div class="container">
            <div class="section-header">
                <div class="section-badge">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Programme Registration</span>
                </div>
                <h2 class="section-title">
                    Register for Professional Development
                </h2>
                <p class="section-subtitle">
                    Join our comprehensive HR training programmes and advance your career
                </p>
            </div>

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

            <div class="application-container">
                <?php if ($programme): ?>
                    <!-- Programme Details Card -->
                    <div class="job-details-card">
                        <?php if (!empty($programme['poster_image'])): ?>
                        <!-- Programme Poster Container with Footer -->
                        <div class="programme-poster-container">
                            <div class="programme-poster">
                                <img src="<?php echo htmlspecialchars($programme['poster_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($programme['title']); ?> Poster"
                                     class="programme-poster-img">
                            </div>
                            <div class="programme-poster-footer">
                                <div class="programme-icon-standalone">
                                    <i class="<?php echo htmlspecialchars($programme['programme_icon'] ?? 'fas fa-graduation-cap'); ?>"></i>
                                </div>
                                <div class="programme-badge-standalone">
                                    <span><?php echo htmlspecialchars($programme['duration']); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="job-header">
                            <div class="job-icon">
                                <i class="<?php echo htmlspecialchars($programme['programme_icon'] ?? 'fas fa-graduation-cap'); ?>"></i>
                            </div>
                            <div class="job-badge">
                                <span><?php echo htmlspecialchars($programme['duration']); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="job-content">
                            <h3><?php echo htmlspecialchars($programme['title']); ?></h3>
                            <p class="job-description"><?php echo htmlspecialchars($programme['description']); ?></p>
                            
                            <div class="job-details">
                                <div class="detail-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo htmlspecialchars($programme['duration']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-dollar-sign"></i>
                                    <span><?php echo formatPrice($programme['price']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-certificate"></i>
                                    <span><?php echo htmlspecialchars($programme['certification'] ?? 'HRD Corp Certified'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($programme['location'] ?? 'Online'); ?></span>
                                </div>

                                <?php if (!empty($programme['start_date']) && $programme['start_date'] !== '0000-00-00'): ?>
                                <div class="detail-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>
                                        <?php 
                                        try {
                                            $start_date = new DateTime($programme['start_date']);
                                            if (!empty($programme['end_date']) && $programme['end_date'] !== '0000-00-00' && $programme['end_date'] !== $programme['start_date']) {
                                                $end_date = new DateTime($programme['end_date']);
                                                echo $start_date->format('M j, Y') . ' - ' . $end_date->format('M j, Y');
                                            } else {
                                                echo $start_date->format('M j, Y');
                                            }
                                        } catch (Exception $e) {
                                            echo 'Date TBA';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <?php else: ?>
                                <div class="detail-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>Dates TBA - Contact for Schedule</span>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($programme['start_time']) && !empty($programme['end_time'])): ?>
                                <div class="detail-item">
                                    <i class="fas fa-clock"></i>
                                    <span>
                                        <?php 
                                        $start_time = new DateTime($programme['start_time']);
                                        $end_time = new DateTime($programme['end_time']);
                                        echo $start_time->format('g:i A') . ' - ' . $end_time->format('g:i A');
                                        ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Registration Form -->
                <div class="application-form-card">
                    <div class="form-header">
                        <h3>Registration Form</h3>
                        <p>Please fill out the form below to register for this programme</p>
                    </div>

                    <form method="POST" class="application-form" id="programme-registration-form">
                        <input type="hidden" name="programme_id" value="<?php echo $programme ? $programme['id'] : ''; ?>">
                        
                        <!-- Full Name - Full Width for Mobile -->
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <div class="input-wrapper">
                                <i class="fas fa-user"></i>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required placeholder="Enter your full name">
                            </div>
                        </div>

                        <!-- Email - Full Width for Mobile -->
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <div class="input-wrapper">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required placeholder="Enter your email address">
                            </div>
                        </div>

                        <!-- Phone and IC - Stacked on Mobile -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone Number *</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-phone"></i>
                                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required placeholder="Enter your phone number">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="ic_number">IC Number *</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-id-card"></i>
                                    <input type="text" id="ic_number" name="ic_number" value="<?php echo htmlspecialchars($_POST['ic_number'] ?? ''); ?>" required placeholder="Enter your IC number">
                                </div>
                            </div>
                        </div>

                        <!-- Programme Selection - Full Width for Mobile -->
                        <div class="form-group">
                            <label for="programme_select">Select Programme *</label>
                            <div class="input-wrapper">
                                <i class="fas fa-graduation-cap"></i>
                                <select id="programme_select" name="programme_id" required <?php echo $programme ? 'disabled' : ''; ?>>
                                    <option value="">Choose a programme...</option>
                                    <?php foreach ($all_programmes as $programme_option): ?>
                                        <option value="<?php echo $programme_option['id']; ?>" 
                                            <?php echo ($programme && $programme['id'] == $programme_option['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($programme_option['title']); ?> - <?php echo formatPrice($programme_option['price']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Company and Position - Stacked on Mobile -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="company">Company/Organization *</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-building"></i>
                                    <input type="text" id="company" name="company" value="<?php echo htmlspecialchars($_POST['company'] ?? ''); ?>" required placeholder="Enter your company name">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="position">Position/Title *</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-briefcase"></i>
                                    <input type="text" id="position" name="position" value="<?php echo htmlspecialchars($_POST['position'] ?? ''); ?>" required placeholder="Enter your job title">
                                </div>
                            </div>
                        </div>





                        <!-- Billing Information Section -->
                        <div class="form-group">
                            <label for="billing_address">Billing Address *</label>
                            <div class="input-wrapper">
                                <i class="fas fa-map-marker-alt"></i>
                                <textarea id="billing_address" name="billing_address" rows="3" required placeholder="Enter your complete billing address"><?php echo htmlspecialchars($_POST['billing_address'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="billing_contact_no">Billing Contact Number *</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-phone"></i>
                                    <input type="tel" id="billing_contact_no" name="billing_contact_no" value="<?php echo htmlspecialchars($_POST['billing_contact_no'] ?? ''); ?>" required placeholder="Enter billing contact number">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="payment_method">Payment Method *</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-credit-card"></i>
                                    <select id="payment_method" name="payment_method" required>
                                        <option value="">Select payment method...</option>
                                        <option value="hrdf_claim" <?php echo ($_POST['payment_method'] ?? '') === 'hrdf_claim' ? 'selected' : ''; ?>>HRDF Claim</option>
                                        <option value="non_hrdf_claim" <?php echo ($_POST['payment_method'] ?? '') === 'non_hrdf_claim' ? 'selected' : ''; ?>>Non HRDF Claim</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Vehicle Information Section -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="vehicle_type">What vehicle will you be using? (for parking reservation) *</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-car"></i>
                                    <select id="vehicle_type" name="vehicle_type" required>
                                        <option value="">Select vehicle type...</option>
                                        <option value="car" <?php echo ($_POST['vehicle_type'] ?? '') === 'car' ? 'selected' : ''; ?>>Car</option>
                                        <option value="motorcycle" <?php echo ($_POST['vehicle_type'] ?? '') === 'motorcycle' ? 'selected' : ''; ?>>Motorcycle</option>
                                        <option value="other" <?php echo ($_POST['vehicle_type'] ?? '') === 'other' ? 'selected' : ''; ?>>Other (please specify below)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group" id="vehicle_type_other_group" style="display: none;">
                                <label for="vehicle_type_other">Specify Other Vehicle Type</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-car"></i>
                                    <input type="text" id="vehicle_type_other" name="vehicle_type_other" value="<?php echo htmlspecialchars($_POST['vehicle_type_other'] ?? ''); ?>" placeholder="Please specify your vehicle type...">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="plate_number">Plate Number (for parking reservation) *</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-id-card"></i>
                                    <input type="text" id="plate_number" name="plate_number" value="<?php echo htmlspecialchars($_POST['plate_number'] ?? ''); ?>" required placeholder="Enter your vehicle plate number">
                                </div>
                            </div>
                        </div>

                        <!-- Marketing Information Section -->
                        <div class="form-group">
                            <label for="how_did_you_hear">How did you hear about this class? *</label>
                            <div class="input-wrapper">
                                <i class="fas fa-bullhorn"></i>
                                <select id="how_did_you_hear" name="how_did_you_hear" required>
                                    <option value="">Select how you heard about us...</option>
                                    <option value="website" <?php echo ($_POST['how_did_you_hear'] ?? '') === 'website' ? 'selected' : ''; ?>>Website</option>
                                    <option value="social_media" <?php echo ($_POST['how_did_you_hear'] ?? '') === 'social_media' ? 'selected' : ''; ?>>Social Media</option>
                                    <option value="referral" <?php echo ($_POST['how_did_you_hear'] ?? '') === 'referral' ? 'selected' : ''; ?>>Referral</option>
                                    <option value="email" <?php echo ($_POST['how_did_you_hear'] ?? '') === 'email' ? 'selected' : ''; ?>>Email</option>
                                    <option value="advertisement" <?php echo ($_POST['how_did_you_hear'] ?? '') === 'advertisement' ? 'selected' : ''; ?>>Advertisement</option>
                                    <option value="other" <?php echo ($_POST['how_did_you_hear'] ?? '') === 'other' ? 'selected' : ''; ?>>Other (please specify below)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group" id="how_did_you_hear_other_group" style="display: none;">
                            <label for="how_did_you_hear_other">Specify Other Source</label>
                            <div class="input-wrapper">
                                <i class="fas fa-bullhorn"></i>
                                <textarea id="how_did_you_hear_other" name="how_did_you_hear_other" rows="3" placeholder="Please specify how you heard about this class..."><?php echo htmlspecialchars($_POST['how_did_you_hear_other'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <!-- Dietary Preferences Section -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="spicy_preference">Spicy Preference *</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-pepper-hot"></i>
                                    <select id="spicy_preference" name="spicy_preference" required>
                                        <option value="">Select spicy preference...</option>
                                        <option value="spicy" <?php echo ($_POST['spicy_preference'] ?? '') === 'spicy' ? 'selected' : ''; ?>>Spicy</option>
                                        <option value="no_spicy" <?php echo ($_POST['spicy_preference'] ?? '') === 'no_spicy' ? 'selected' : ''; ?>>No Spicy</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="dietary_preference">Dietary Preference *</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-leaf"></i>
                                    <select id="dietary_preference" name="dietary_preference" required>
                                        <option value="">Select dietary preference...</option>
                                        <option value="none" <?php echo ($_POST['dietary_preference'] ?? '') === 'none' ? 'selected' : ''; ?>>No dietary restrictions</option>
                                        <option value="vegetarian" <?php echo ($_POST['dietary_preference'] ?? '') === 'vegetarian' ? 'selected' : ''; ?>>Vegetarian</option>
                                        <option value="vegan" <?php echo ($_POST['dietary_preference'] ?? '') === 'vegan' ? 'selected' : ''; ?>>Vegan</option>
                                        <option value="halal" <?php echo ($_POST['dietary_preference'] ?? '') === 'halal' ? 'selected' : ''; ?>>Halal</option>
                                        <option value="other" <?php echo ($_POST['dietary_preference'] ?? '') === 'other' ? 'selected' : ''; ?>>Other (please specify below)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group" id="dietary_restrictions_group" style="display: none;">
                            <label for="dietary_restrictions">Specific Dietary Restrictions</label>
                            <div class="input-wrapper">
                                <i class="fas fa-utensils"></i>
                                <textarea id="dietary_restrictions" name="dietary_restrictions" rows="3" placeholder="Please specify any specific foods you cannot eat or other dietary requirements..."><?php echo htmlspecialchars($_POST['dietary_restrictions'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="allergies">Any Allergies? *</label>
                            <div class="input-wrapper">
                                <i class="fas fa-exclamation-triangle"></i>
                                <select id="allergies" name="allergies" required>
                                    <option value="">Select allergy option...</option>
                                    <option value="none" <?php echo ($_POST['allergies'] ?? '') === 'none' ? 'selected' : ''; ?>>No allergies</option>
                                    <option value="nuts" <?php echo ($_POST['allergies'] ?? '') === 'nuts' ? 'selected' : ''; ?>>Nuts</option>
                                    <option value="dairy" <?php echo ($_POST['allergies'] ?? '') === 'dairy' ? 'selected' : ''; ?>>Dairy</option>
                                    <option value="gluten" <?php echo ($_POST['allergies'] ?? '') === 'gluten' ? 'selected' : ''; ?>>Gluten</option>
                                    <option value="seafood" <?php echo ($_POST['allergies'] ?? '') === 'seafood' ? 'selected' : ''; ?>>Seafood</option>
                                    <option value="eggs" <?php echo ($_POST['allergies'] ?? '') === 'eggs' ? 'selected' : ''; ?>>Eggs</option>
                                    <option value="other" <?php echo ($_POST['allergies'] ?? '') === 'other' ? 'selected' : ''; ?>>Other (please specify below)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group" id="allergies_other_group" style="display: none;">
                            <label for="allergies_other">Specify Other Allergies</label>
                            <div class="input-wrapper">
                                <i class="fas fa-exclamation-triangle"></i>
                                <textarea id="allergies_other" name="allergies_other" rows="3" placeholder="Please specify any other food allergies..."><?php echo htmlspecialchars($_POST['allergies_other'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <!-- Interest in Other Programs -->
                        <div class="form-group">
                            <label for="interested_programs">What more programs would you like to hear about?</label>
                            <div class="input-wrapper">
                                <i class="fas fa-graduation-cap"></i>
                                <select id="interested_programs" name="interested_programs">
                                    <option value="">Select program interest...</option>
                                    <option value="laws_compliance" <?php echo ($_POST['interested_programs'] ?? '') === 'laws_compliance' ? 'selected' : ''; ?>>Laws and Compliance Training</option>
                                    <option value="soft_skills" <?php echo ($_POST['interested_programs'] ?? '') === 'soft_skills' ? 'selected' : ''; ?>>Soft Skills Development</option>
                                    <option value="other" <?php echo ($_POST['interested_programs'] ?? '') === 'other' ? 'selected' : ''; ?>>Other (please specify below)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group" id="interested_programs_other_group" style="display: none;">
                            <label for="interested_programs_other">Specify Other Programs</label>
                            <div class="input-wrapper">
                                <i class="fas fa-graduation-cap"></i>
                                <textarea id="interested_programs_other" name="interested_programs_other" rows="4" placeholder="Please specify any other programs you're interested in..."><?php echo htmlspecialchars($_POST['interested_programs_other'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="additional_notes">Additional Notes</label>
                            <div class="input-wrapper">
                                <i class="fas fa-file-alt"></i>
                                <textarea id="additional_notes" name="additional_notes" rows="6" placeholder="Tell us about your learning goals, specific requirements, or any questions you have..."><?php echo htmlspecialchars($_POST['additional_notes'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                                <span>Submit Registration</span>
                            </button>
                            <a href="programmes.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i>
                                <span>Back to Programmes</span>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

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
                        <li><a href="index.html#about">20+ Years Experience</a></li>
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
    <!-- Poster Modal Lightbox -->
    <div id="posterModal" class="poster-modal">
        <div class="poster-modal-overlay"></div>
        <div class="poster-modal-content">
            <div class="poster-modal-header">
                <h3 id="posterModalTitle">Programme Poster</h3>
                <button class="poster-modal-close" id="posterModalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="poster-modal-body">
                <div class="poster-zoom-container">
                    <img id="posterModalImage" src="" alt="Programme Poster" class="poster-zoom-image">
                </div>
                <div class="poster-zoom-controls">
                    <button class="zoom-btn zoom-out" id="zoomOut">
                        <i class="fas fa-search-minus"></i>
                    </button>
                    <span class="zoom-level" id="zoomLevel">100%</span>
                    <button class="zoom-btn zoom-in" id="zoomIn">
                        <i class="fas fa-search-plus"></i>
                    </button>
                    <button class="zoom-btn zoom-reset" id="zoomReset">
                        <i class="fas fa-expand-arrows-alt"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    
    <script>
        // Auto-save functionality for programme registration
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const formId = 'programme-registration-form';
            let autoSaveInterval;
            let hasUnsavedChanges = false;
            
            // Auto-save form data
            function autoSaveForm() {
                const formData = new FormData(form);
                const data = {};
                
                // Collect all form data
                for (let [key, value] of formData.entries()) {
                    data[key] = value;
                }
                
                // Save to localStorage
                localStorage.setItem(`form_${formId}_draft`, JSON.stringify(data));
                hasUnsavedChanges = true;
                
                // Show auto-save indicator
                showAutoSaveIndicator();
            }
            
            // Restore form data from localStorage
            function restoreFormData() {
                const savedData = localStorage.getItem(`form_${formId}_draft`);
                if (savedData) {
                    try {
                        const data = JSON.parse(savedData);
                        
                        // Restore form fields
                        Object.keys(data).forEach(key => {
                            const element = form.querySelector(`[name="${key}"]`);
                            if (element) {
                                if (element.type === 'checkbox') {
                                    element.checked = data[key] === '1' || data[key] === 'on';
                                } else if (element.type === 'radio') {
                                    if (element.value === data[key]) {
                                        element.checked = true;
                                    }
                                } else {
                                    element.value = data[key];
                                }
                            }
                        });
                        
                        // Show restore notification
                        showRestoreNotification();
                        hasUnsavedChanges = true;
                    } catch (e) {
                        console.error('Error restoring form data:', e);
                    }
                }
            }
            
            // Clear saved form data
            function clearSavedFormData() {
                localStorage.removeItem(`form_${formId}_draft`);
                hasUnsavedChanges = false;
            }
            
            // Show auto-save indicator
            function showAutoSaveIndicator() {
                let indicator = document.getElementById('auto-save-indicator');
                if (!indicator) {
                    indicator = document.createElement('div');
                    indicator.id = 'auto-save-indicator';
                    indicator.style.cssText = `
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        background: #10b981;
                        color: white;
                        padding: 8px 16px;
                        border-radius: 6px;
                        font-size: 14px;
                        z-index: 1000;
                        opacity: 0;
                        transition: opacity 0.3s ease;
                    `;
                    document.body.appendChild(indicator);
                }
                
                indicator.textContent = 'Auto-saved';
                indicator.style.opacity = '1';
                
                setTimeout(() => {
                    indicator.style.opacity = '0';
                }, 2000);
            }
            
            // Show restore notification
            function showRestoreNotification() {
                let notification = document.getElementById('restore-notification');
                if (!notification) {
                    notification = document.createElement('div');
                    notification.id = 'restore-notification';
                    notification.style.cssText = `
                        position: fixed;
                        top: 20px;
                        left: 50%;
                        transform: translateX(-50%);
                        background: #3b82f6;
                        color: white;
                        padding: 12px 24px;
                        border-radius: 8px;
                        font-size: 14px;
                        z-index: 1000;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    `;
                    document.body.appendChild(notification);
                }
                
                notification.innerHTML = `
                    <i class="fas fa-info-circle" style="margin-right: 8px;"></i>
                    Form data restored from previous session
                    <button onclick="this.parentElement.remove()" style="background: none; border: none; color: white; margin-left: 12px; cursor: pointer;">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, 5000);
            }
            
            // Warn before leaving with unsaved changes
            window.addEventListener('beforeunload', function(e) {
                if (hasUnsavedChanges) {
                    e.preventDefault();
                    e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                    return e.returnValue;
                }
            });
            
            // Start auto-save when form is modified
            form.addEventListener('input', function() {
                if (!autoSaveInterval) {
                    autoSaveInterval = setInterval(autoSaveForm, 2000);
                }
            });
            
            // Clear auto-save on successful form submission
            form.addEventListener('submit', function() {
                clearSavedFormData();
                if (autoSaveInterval) {
                    clearInterval(autoSaveInterval);
                    autoSaveInterval = null;
                }
            });
            
            // Restore form data on page load
            restoreFormData();
            
            // Handle dietary preference change
            const dietaryPreference = document.getElementById('dietary_preference');
            const dietaryRestrictionsGroup = document.getElementById('dietary_restrictions_group');
            
            function toggleDietaryRestrictions() {
                if (dietaryPreference.value === 'other') {
                    // Show with slide-down animation
                    dietaryRestrictionsGroup.style.display = 'block';
                    dietaryRestrictionsGroup.style.opacity = '0';
                    dietaryRestrictionsGroup.style.transform = 'translateY(-10px)';
                    
                    // Trigger animation
                    setTimeout(() => {
                        dietaryRestrictionsGroup.style.transition = 'all 0.3s ease-out';
                        dietaryRestrictionsGroup.style.opacity = '1';
                        dietaryRestrictionsGroup.style.transform = 'translateY(0)';
                    }, 10);
                } else {
                    // Hide with slide-up animation
                    dietaryRestrictionsGroup.style.transition = 'all 0.3s ease-in';
                    dietaryRestrictionsGroup.style.opacity = '0';
                    dietaryRestrictionsGroup.style.transform = 'translateY(-10px)';
                    
                    // Hide after animation completes
                    setTimeout(() => {
                        dietaryRestrictionsGroup.style.display = 'none';
                        // Clear the textarea when hidden
                        document.getElementById('dietary_restrictions').value = '';
                    }, 300);
                }
            }
            
            // Add event listener
            dietaryPreference.addEventListener('change', toggleDietaryRestrictions);
            
            // Check on page load if "other" is already selected
            toggleDietaryRestrictions();
            
            // Handle allergies change
            const allergies = document.getElementById('allergies');
            const allergiesOtherGroup = document.getElementById('allergies_other_group');
            
            function toggleAllergiesOther() {
                if (allergies.value === 'other') {
                    // Show with slide-down animation
                    allergiesOtherGroup.style.display = 'block';
                    allergiesOtherGroup.style.opacity = '0';
                    allergiesOtherGroup.style.transform = 'translateY(-10px)';
                    
                    // Trigger animation
                    setTimeout(() => {
                        allergiesOtherGroup.style.transition = 'all 0.3s ease-out';
                        allergiesOtherGroup.style.opacity = '1';
                        allergiesOtherGroup.style.transform = 'translateY(0)';
                    }, 10);
                } else {
                    // Hide with slide-up animation
                    allergiesOtherGroup.style.transition = 'all 0.3s ease-in';
                    allergiesOtherGroup.style.opacity = '0';
                    allergiesOtherGroup.style.transform = 'translateY(-10px)';
                    
                    // Hide after animation completes
                    setTimeout(() => {
                        allergiesOtherGroup.style.display = 'none';
                        // Clear the textarea when hidden
                        document.getElementById('allergies_other').value = '';
                    }, 300);
                }
            }
            
            // Add event listener
            allergies.addEventListener('change', toggleAllergiesOther);
            
            // Check on page load if "other" is already selected
            toggleAllergiesOther();
            
            // Handle interested programs change
            const interestedPrograms = document.getElementById('interested_programs');
            const interestedProgramsOtherGroup = document.getElementById('interested_programs_other_group');
            
            function toggleInterestedProgramsOther() {
                if (interestedPrograms.value === 'other') {
                    // Show with slide-down animation
                    interestedProgramsOtherGroup.style.display = 'block';
                    interestedProgramsOtherGroup.style.opacity = '0';
                    interestedProgramsOtherGroup.style.transform = 'translateY(-10px)';
                    
                    // Trigger animation
                    setTimeout(() => {
                        interestedProgramsOtherGroup.style.transition = 'all 0.3s ease-out';
                        interestedProgramsOtherGroup.style.opacity = '1';
                        interestedProgramsOtherGroup.style.transform = 'translateY(0)';
                    }, 10);
                } else {
                    // Hide with slide-up animation
                    interestedProgramsOtherGroup.style.transition = 'all 0.3s ease-in';
                    interestedProgramsOtherGroup.style.opacity = '0';
                    interestedProgramsOtherGroup.style.transform = 'translateY(-10px)';
                    
                    // Hide after animation completes
                    setTimeout(() => {
                        interestedProgramsOtherGroup.style.display = 'none';
                        // Clear the textarea when hidden
                        document.getElementById('interested_programs_other').value = '';
                    }, 300);
                }
            }
            
            // Add event listener
            interestedPrograms.addEventListener('change', toggleInterestedProgramsOther);
            
            // Check on page load if "other" is already selected
            toggleInterestedProgramsOther();
            
            // Handle how did you hear change
            const howDidYouHear = document.getElementById('how_did_you_hear');
            const howDidYouHearOtherGroup = document.getElementById('how_did_you_hear_other_group');
            
            function toggleHowDidYouHearOther() {
                if (howDidYouHear.value === 'other') {
                    // Show with slide-down animation
                    howDidYouHearOtherGroup.style.display = 'block';
                    howDidYouHearOtherGroup.style.opacity = '0';
                    howDidYouHearOtherGroup.style.transform = 'translateY(-10px)';
                    
                    // Trigger animation
                    setTimeout(() => {
                        howDidYouHearOtherGroup.style.transition = 'all 0.3s ease-out';
                        howDidYouHearOtherGroup.style.opacity = '1';
                        howDidYouHearOtherGroup.style.transform = 'translateY(0)';
                    }, 10);
                } else {
                    // Hide with slide-up animation
                    howDidYouHearOtherGroup.style.transition = 'all 0.3s ease-in';
                    howDidYouHearOtherGroup.style.opacity = '0';
                    howDidYouHearOtherGroup.style.transform = 'translateY(-10px)';
                    
                    // Hide after animation completes
                    setTimeout(() => {
                        howDidYouHearOtherGroup.style.display = 'none';
                        // Clear the textarea when hidden
                        document.getElementById('how_did_you_hear_other').value = '';
                    }, 300);
                }
            }
            
            // Add event listener
            howDidYouHear.addEventListener('change', toggleHowDidYouHearOther);
            
            // Check on page load if "other" is already selected
            toggleHowDidYouHearOther();
            
            // Handle vehicle type change
            const vehicleType = document.getElementById('vehicle_type');
            const vehicleTypeOtherGroup = document.getElementById('vehicle_type_other_group');
            
            function toggleVehicleTypeOther() {
                if (vehicleType.value === 'other') {
                    // Show with slide-down animation
                    vehicleTypeOtherGroup.style.display = 'block';
                    vehicleTypeOtherGroup.style.opacity = '0';
                    vehicleTypeOtherGroup.style.transform = 'translateY(-10px)';
                    
                    // Trigger animation
                    setTimeout(() => {
                        vehicleTypeOtherGroup.style.transition = 'all 0.3s ease-out';
                        vehicleTypeOtherGroup.style.opacity = '1';
                        vehicleTypeOtherGroup.style.transform = 'translateY(0)';
                    }, 10);
                } else {
                    // Hide with slide-up animation
                    vehicleTypeOtherGroup.style.transition = 'all 0.3s ease-in';
                    vehicleTypeOtherGroup.style.opacity = '0';
                    vehicleTypeOtherGroup.style.transform = 'translateY(-10px)';
                    
                    // Hide after animation completes
                    setTimeout(() => {
                        vehicleTypeOtherGroup.style.display = 'none';
                        // Clear the input when hidden
                        document.getElementById('vehicle_type_other').value = '';
                    }, 300);
                }
            }
            
            // Add event listener
            vehicleType.addEventListener('change', toggleVehicleTypeOther);
            
            // Check on page load if "other" is already selected
            toggleVehicleTypeOther();
        });
    </script>

    <script>
        const programmeQuestionMap = <?php echo json_encode($programme_question_map, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

        function parseDropdownOptions(rawOptions) {
            if (!rawOptions) return [];
            return rawOptions
                .split('\n')
                .map(line => line.trim())
                .filter(Boolean)
                .map(line => {
                    const [value, label] = line.includes('|') ? line.split('|', 2) : [line, line];
                    return { value: value.trim(), label: (label || value).trim() };
                });
        }

        function setFieldVisibility(fieldName, isVisible) {
            const field = document.querySelector(`[name="${fieldName}"]`);
            if (!field) return;
            const group = field.closest('.form-group');
            if (!group) return;

            group.style.display = isVisible ? '' : 'none';
            field.disabled = !isVisible;
        }

        function applyQuestionConfiguration(programmeId) {
            const configs = programmeQuestionMap[programmeId] || [];
            const configByKey = {};
            configs.forEach(cfg => {
                configByKey[cfg.field_key] = cfg;
            });

            const allKeys = [
                'name', 'email', 'phone', 'ic_number', 'company', 'position',
                'billing_address', 'billing_contact_no', 'payment_method',
                'vehicle_type', 'plate_number', 'how_did_you_hear', 'spicy_preference',
                'dietary_preference', 'allergies', 'interested_programs', 'additional_notes'
            ];

            allKeys.forEach(fieldKey => {
                const field = document.querySelector(`[name="${fieldKey}"]`);
                if (!field) return;

                const cfg = configByKey[fieldKey];
                const isEnabled = !!cfg;
                const isRequired = isEnabled && Number(cfg.is_required) === 1;

                setFieldVisibility(fieldKey, isEnabled);
                field.required = isRequired;

                const label = document.querySelector(`label[for="${field.id}"]`);
                if (label && cfg && cfg.field_label) {
                    label.textContent = cfg.field_label + (isRequired ? ' *' : '');
                }

                if (cfg && cfg.input_type === 'select' && field.tagName.toLowerCase() === 'select') {
                    const options = parseDropdownOptions(cfg.field_options || '');
                    if (options.length > 0) {
                        const currentValue = field.value;
                        field.innerHTML = '<option value="">Select...</option>';
                        options.forEach(opt => {
                            const optionEl = document.createElement('option');
                            optionEl.value = opt.value;
                            optionEl.textContent = opt.label;
                            field.appendChild(optionEl);
                        });
                        if (currentValue && [...field.options].some(opt => opt.value === currentValue)) {
                            field.value = currentValue;
                        }
                    }
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const programmeSelect = document.getElementById('programme_select');
            if (!programmeSelect) return;

            applyQuestionConfiguration(programmeSelect.value);
            programmeSelect.addEventListener('change', function() {
                applyQuestionConfiguration(this.value);
            });
        });
    </script>
</body>
</html>
